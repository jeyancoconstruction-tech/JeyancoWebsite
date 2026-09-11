<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\LaborType;
use App\Models\Shift;
use App\Models\SystemSetting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What Today's Attendance is allowed to show, and what it is allowed to count.
 *
 * One night-shift worker appeared with three sign-ins against a single day.
 * Three separate faults stacked into that one screen:
 *
 *   1. A finger held a moment too long read twice, and the second read timed
 *      him out ten seconds after timing him in. A whole shift's worth of
 *      nothing, carried as a record of its own.
 *   2. The shift he had finished the night before was still on the day view,
 *      because a night crew's workday stays "current" until the next one
 *      opens in the evening — true of where a clock would land, wrong as the
 *      line between today and the history.
 *   3. The cards counted rows. One man on site, three rows, "3 PRESENT".
 *
 * Each is checked here, because each on its own looks like the system letting
 * somebody clock in over and over.
 */
class AttendanceDayViewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        SystemSetting::current()->forceFill(['schedule_rules_from' => '2026-09-01'])->save();
    }

    private function worker(string $name, bool $night): Employee
    {
        return Employee::create([
            'name'            => $name,
            'status'          => Employee::STATUS_ACTIVE,
            'employment_type' => Employee::EMPLOYMENT_DAILY,
            'labor_type_id'   => LaborType::create(['name' => 'Mason ' . $name, 'daily_rate' => 800, 'ot_rate' => 125])->id,
            'shift_id'        => Shift::where('crosses_midnight', $night)->firstOrFail()->id,
            'rate_per_hour'   => 100,
        ]);
    }

    private ?User $admin = null;

    /** Memoised: the page is read several times in one test, and username is unique. */
    private function admin(): User
    {
        return $this->admin ??= User::create([
            'name' => 'Admin', 'username' => 'admin.dayview', 'password' => 'secret123',
            'is_admin' => true, 'is_active' => true,
        ]);
    }

    /** Returns the kiosk's answer rather than asserting it succeeded. */
    private function clock(Employee $e, string $type, string $at): array
    {
        Carbon::setTestNow(Carbon::parse($at, 'Asia/Manila'));

        return $this->postJson('/api/kiosk/attendance', [
            'employee_id' => $e->id, 'type' => $type,
        ])->assertOk()->json();
    }

    private function page(string $at)
    {
        Carbon::setTestNow(Carbon::parse($at, 'Asia/Manila'));

        return $this->actingAs($this->admin())->get(route('attendance'))->assertOk();
    }

    // ── 1. The sensor reading the same finger twice ──────────────────────

    public function test_a_time_out_seconds_after_the_time_in_is_refused(): void
    {
        $emp = $this->worker('Double Tap', true);
        $this->clock($emp, 'time_in', '2026-09-10 20:00:00');

        $answer = $this->clock($emp, 'time_out', '2026-09-10 20:00:10');

        $this->assertFalse($answer['success']);
        $this->assertSame('just_timed_in', $answer['code']);

        $row = Attendance::where('employee_id', $emp->id)->sole();
        $this->assertNull($row->time_out, 'the day the first read opened stays open');
    }

    public function test_a_real_time_out_still_closes_the_day(): void
    {
        $emp = $this->worker('Honest', true);
        $this->clock($emp, 'time_in', '2026-09-10 20:00:00');

        $answer = $this->clock($emp, 'time_out', '2026-09-10 20:02:00');

        $this->assertTrue($answer['success'], 'two minutes is a short shift, not a double read');
        $this->assertNotNull(Attendance::where('employee_id', $emp->id)->sole()->time_out);
    }

    public function test_the_double_read_leaves_one_record_not_two(): void
    {
        $emp = $this->worker('Once Only', true);
        $this->clock($emp, 'time_in', '2026-09-10 20:00:00');
        $this->clock($emp, 'time_out', '2026-09-10 20:00:05');
        $this->clock($emp, 'time_out', '2026-09-10 20:00:11');

        $this->assertSame(1, Attendance::where('employee_id', $emp->id)->count());
    }

    // ── 2. A night that is over is not still today ───────────────────────

    public function test_a_finished_night_leaves_the_day_view_the_next_morning(): void
    {
        $emp = $this->worker('Night Crew', true);

        // Thursday evening into Friday's small hours.
        $this->clock($emp, 'time_in', '2026-09-10 20:00:00');
        $this->clock($emp, 'time_out', '2026-09-11 03:00:00');

        // Mid-shift it belongs on the day view — that is the whole point.
        $page = $this->page('2026-09-11 02:00:00');
        $this->assertCount(1, $page->viewData('todayAttendances'));

        // Friday morning: their shift ended at 5, and their next does not open
        // until the evening. The night is finished, so it is history.
        $page = $this->page('2026-09-11 10:00:00');
        $this->assertCount(0, $page->viewData('todayAttendances'),
            'last night is not today, even though their next day has not opened');
        $this->assertSame(1, $page->viewData('historyAttendances')->total());
    }

    public function test_the_day_crew_and_the_night_crew_are_not_mixed_into_one_heading(): void
    {
        $night = $this->worker('Night Crew', true);
        $day   = $this->worker('Day Crew', false);

        // The night crew worked Thursday night; the day crew works Friday.
        $this->clock($night, 'time_in', '2026-09-10 20:00:00');
        $this->clock($night, 'time_out', '2026-09-11 03:00:00');
        $this->clock($day, 'time_in', '2026-09-11 08:00:00');

        $rows = $this->page('2026-09-11 10:00:00')->viewData('todayAttendances');

        $this->assertCount(1, $rows, 'Friday shows Friday');
        $this->assertSame($day->id, $rows->first()->employee_id);
    }

    // ── 3. Present means people ──────────────────────────────────────────

    public function test_one_worker_with_several_stretches_counts_once(): void
    {
        $emp = $this->worker('Busy Night', true);

        // A night in three stretches: away for a while, back, then still on
        // site. Three rows, all one man, all one workday.
        $this->clock($emp, 'time_in', '2026-09-10 20:00:00');
        $this->clock($emp, 'time_out', '2026-09-10 22:00:00');
        $this->clock($emp, 'time_in', '2026-09-10 22:30:00');
        $this->clock($emp, 'time_out', '2026-09-10 23:00:00');
        $this->clock($emp, 'time_in', '2026-09-10 23:30:00');

        $page = $this->page('2026-09-10 23:45:00');

        $this->assertCount(3, $page->viewData('todayAttendances'), 'every stretch is still shown');
        $this->assertSame(1, $page->viewData('presentToday'), 'but one man is one man');
        $this->assertSame(1, $page->viewData('clockedIn'));
    }

    // ── The forgotten day ────────────────────────────────────────────────

    public function test_a_day_nobody_closed_is_closed_and_flagged_when_the_page_is_read(): void
    {
        $emp = $this->worker('Forgot', true);
        $this->clock($emp, 'time_in', '2026-09-10 20:00:00');

        // Well past the end of the shift, and past the grace that follows it.
        $this->page('2026-09-11 18:00:00');

        $row = Attendance::where('employee_id', $emp->id)->sole();

        $this->assertNotNull($row->time_out, 'the office cannot resolve a row that stays open for good');
        $this->assertSame('auto', $row->close_type);
        $this->assertTrue((bool) $row->needs_review);
        // The end of the session he was in — the first half of the night —
        // not the end of the whole shift. Nobody knows when he actually left,
        // so the guess is never stretched further than it has to be.
        $this->assertSame('2026-09-11 00:00:00', Carbon::parse($row->time_out)->toDateTimeString());
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
