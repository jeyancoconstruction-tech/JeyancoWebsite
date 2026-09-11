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
 * When a day stops being "still being worked" and becomes a missed sign-out.
 *
 * One clock decides it: the end of the shift the day was worked under, plus
 * an hour to walk off site. Before that the worker may still be finishing.
 * After it, nobody is coming back to press the button.
 *
 *   Day    ends 17:00 → due 18:00
 *   Night  ends 05:00 → due 06:00, the following morning
 *
 * The date the row happens to carry has nothing to do with it. Asking the
 * calendar flagged the entire night crew every night at midnight; asking
 * whether the workday had rolled over left the card reading 0 while the row
 * beside it wore an Invalid badge, because the two were asking different
 * questions.
 */
class MissedSignOutTest extends TestCase
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

    private function admin(): User
    {
        return $this->admin ??= User::create([
            'name' => 'Admin', 'username' => 'admin.missed', 'password' => 'secret123',
            'is_admin' => true, 'is_active' => true,
        ]);
    }

    private function timeIn(Employee $e, string $at): void
    {
        Carbon::setTestNow(Carbon::parse($at, 'Asia/Manila'));
        $this->postJson('/api/kiosk/attendance', ['employee_id' => $e->id, 'type' => 'time_in'])
             ->assertOk()->assertJson(['success' => true]);
    }

    private function statusAt(Employee $e, string $moment): string
    {
        Carbon::setTestNow(Carbon::parse($moment, 'Asia/Manila'));

        return Attendance::with('shift')->where('employee_id', $e->id)->sole()->status;
    }

    private function page(string $at)
    {
        Carbon::setTestNow(Carbon::parse($at, 'Asia/Manila'));

        return $this->actingAs($this->admin())->get(route('attendance'))->assertOk();
    }

    // ── The day shift: ends 17:00, due 18:00 ─────────────────────────────

    public function test_still_on_site_during_the_shift_is_active(): void
    {
        $emp = $this->worker('Day Crew', false);
        $this->timeIn($emp, '2026-09-11 08:00:00');

        $this->assertSame('active', $this->statusAt($emp, '2026-09-11 14:00:00'));
    }

    public function test_the_hour_after_the_shift_is_still_active(): void
    {
        $emp = $this->worker('Day Crew', false);
        $this->timeIn($emp, '2026-09-11 08:00:00');

        $this->assertSame('active', $this->statusAt($emp, '2026-09-11 17:30:00'),
            'half an hour over is a worker walking to the kiosk, not a missed sign-out');
    }

    public function test_past_the_hour_it_is_a_missed_sign_out(): void
    {
        $emp = $this->worker('Day Crew', false);
        $this->timeIn($emp, '2026-09-11 08:00:00');

        $this->assertSame('invalid', $this->statusAt($emp, '2026-09-11 18:30:00'));
    }

    // ── The night shift: ends 05:00 the next morning, due 06:00 ──────────

    public function test_a_night_crew_mid_shift_is_active_however_late_the_clock_reads(): void
    {
        $emp = $this->worker('Night Crew', true);
        $this->timeIn($emp, '2026-09-11 20:00:00');

        $this->assertSame('active', $this->statusAt($emp, '2026-09-12 03:00:00'));
        $this->assertSame('active', $this->statusAt($emp, '2026-09-12 05:30:00'));
    }

    public function test_a_night_crew_past_six_in_the_morning_is_a_missed_sign_out(): void
    {
        $emp = $this->worker('Night Crew', true);
        $this->timeIn($emp, '2026-09-11 20:00:00');

        $this->assertSame('invalid', $this->statusAt($emp, '2026-09-12 07:00:00'));
    }

    // ── The card says what the badges say ────────────────────────────────

    public function test_the_card_stays_at_zero_while_the_shift_is_running(): void
    {
        $emp = $this->worker('Night Crew', true);
        $this->timeIn($emp, '2026-09-11 20:00:00');

        $page = $this->page('2026-09-12 03:00:00');

        $this->assertSame(0, $page->viewData('invalidCount'));
        $this->assertSame('active', $page->viewData('todayAttendances')->first()->status);
    }

    public function test_the_card_counts_the_row_the_badge_flags(): void
    {
        $emp = $this->worker('Night Crew', true);
        $this->timeIn($emp, '2026-09-11 20:00:00');

        $page = $this->page('2026-09-12 07:00:00');

        $this->assertSame(1, $page->viewData('invalidCount'),
            'the card and the badge have to be answering the same question');
    }

    /**
     * closeStale gives a forgotten day a time-out six hours on. That makes it
     * read Present, which is how a missed sign-out used to drop off the card
     * an hour after appearing on it.
     */
    public function test_a_day_the_system_closed_is_still_on_the_office_queue(): void
    {
        $emp = $this->worker('Forgot', true);
        $this->timeIn($emp, '2026-09-11 20:00:00');

        $page = $this->page('2026-09-12 12:00:00');
        $row  = Attendance::where('employee_id', $emp->id)->sole();

        $this->assertSame('auto', $row->close_type, 'the system closed it');
        $this->assertSame('present', $row->status, 'and it now reads as a finished day');

        $this->assertSame(1, $page->viewData('invalidCount'),
            'but the office still has to confirm the time on it');
        $page->assertSee('Needs review');
    }

    public function test_an_ordinary_finished_day_is_on_nobody_queue(): void
    {
        $emp = $this->worker('Day Crew', false);
        $this->timeIn($emp, '2026-09-11 08:00:00');

        Carbon::setTestNow(Carbon::parse('2026-09-11 17:00:00', 'Asia/Manila'));
        $this->postJson('/api/kiosk/attendance', ['employee_id' => $emp->id, 'type' => 'time_out'])
             ->assertOk()->assertJson(['success' => true]);

        $page = $this->page('2026-09-11 20:00:00');

        $this->assertSame(0, $page->viewData('invalidCount'));
        $page->assertDontSee('Needs review');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
