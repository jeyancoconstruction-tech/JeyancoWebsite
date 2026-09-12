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
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The dashboard's "today" is Today's Attendance's "today".
 *
 * On a Saturday morning the dashboard listed a night-shift worker under Live
 * Attendance · TODAY and counted him present, while the Attendance page — the
 * screen its own "View all" opens — showed nobody. Both were looking at the
 * same finished night. The Attendance page had already learned that a night
 * which is over is history, even before the crew's next day opens in the
 * evening; the dashboard was still asking where a clock would land, which for
 * the night crew stays "last night" until their next shift opens.
 *
 * @see AttendanceDayViewTest::test_a_finished_night_leaves_the_day_view_the_next_morning()
 */
class DashboardWorkdayTest extends TestCase
{
    use RefreshDatabase;

    private ?User $admin = null;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::current()->forceFill(['schedule_rules_from' => '2026-09-01'])->save();

        // The weekly payout tile reaches Holiday::typeMap(), which is MySQL's
        // YEAR(); SQLite has to be taught it before the page will render.
        $pdo = DB::connection()->getPdo();
        if (DB::connection()->getDriverName() === 'sqlite' && method_exists($pdo, 'sqliteCreateFunction')) {
            $pdo->sqliteCreateFunction('YEAR',  fn ($d) => $d ? (int) substr((string) $d, 0, 4) : null, 1);
            $pdo->sqliteCreateFunction('MONTH', fn ($d) => $d ? (int) substr((string) $d, 5, 2) : null, 1);
        }
    }

    private function worker(string $name, bool $night, string $status = Employee::STATUS_ACTIVE): Employee
    {
        return Employee::create([
            'name'            => $name,
            'status'          => $status,
            'employment_type' => Employee::EMPLOYMENT_DAILY,
            'labor_type_id'   => LaborType::create(['name' => 'Mason ' . $name, 'daily_rate' => 800, 'ot_rate' => 125])->id,
            'shift_id'        => Shift::where('crosses_midnight', $night)->firstOrFail()->id,
            'rate_per_hour'   => 100,
        ]);
    }

    /** Memoised: both screens are read in one test, and username is unique. */
    private function admin(): User
    {
        return $this->admin ??= User::create([
            'name' => 'Admin', 'username' => 'admin.dashday', 'password' => 'secret123',
            'is_admin' => true, 'is_active' => true,
        ]);
    }

    /** Clock through the kiosk, which is the only thing that records one. */
    private function clock(Employee $e, string $type, string $at): void
    {
        Carbon::setTestNow(Carbon::parse($at, 'Asia/Manila'));
        $this->postJson('/api/kiosk/attendance', ['employee_id' => $e->id, 'type' => $type])
             ->assertOk()
             ->assertJson(['success' => true]);
    }

    private function dashboard(string $at)
    {
        Carbon::setTestNow(Carbon::parse($at, 'Asia/Manila'));

        return $this->actingAs($this->admin())->get('/dashboard')->assertOk();
    }

    private function attendancePage(string $at)
    {
        Carbon::setTestNow(Carbon::parse($at, 'Asia/Manila'));

        return $this->actingAs($this->admin())->get(route('attendance'))->assertOk();
    }

    public function test_a_finished_night_leaves_live_attendance_the_next_morning(): void
    {
        $emp = $this->worker('Night Crew', true);
        $this->clock($emp, 'time_in', '2026-09-10 20:00:00');

        // Mid-shift the crew is today's — the reason the dashboard stopped
        // reading the calendar in the first place.
        $page = $this->dashboard('2026-09-11 02:00:00');
        $this->assertCount(1, $page->viewData('todayAttendance'));
        $this->assertSame(1, $page->viewData('presentToday'));
        $this->assertSame(1, $page->viewData('stillIn'));

        $this->clock($emp, 'time_out', '2026-09-11 03:00:00');

        // Friday morning: their shift ended at 5 and their next does not open
        // until the evening. The night is finished, so it is not today.
        $page = $this->dashboard('2026-09-11 10:00:00');
        $this->assertCount(0, $page->viewData('todayAttendance'),
            'last night is not today, even though their next day has not opened');
        $this->assertSame(0, $page->viewData('presentToday'));
        $page->assertSee('Nobody has timed in yet today.');
    }

    public function test_live_attendance_lists_what_its_view_all_opens(): void
    {
        $night = $this->worker('Night Crew', true);
        $day   = $this->worker('Day Crew', false);

        // The night crew worked Thursday night; the day crew works Friday.
        $this->clock($night, 'time_in', '2026-09-10 20:00:00');
        $this->clock($night, 'time_out', '2026-09-11 03:00:00');
        $this->clock($day, 'time_in', '2026-09-11 08:00:00');

        $dash  = $this->dashboard('2026-09-11 10:00:00');
        $board = $this->attendancePage('2026-09-11 10:00:00');

        $this->assertSame(
            $board->viewData('todayAttendances')->pluck('id')->all(),
            $dash->viewData('todayAttendance')->pluck('id')->all(),
            'the two screens have to agree on who is here'
        );
        $this->assertSame($board->viewData('presentToday'), $dash->viewData('presentToday'));
        $this->assertSame([$day->id], $dash->viewData('todayAttendance')->pluck('employee_id')->all());
    }

    public function test_yesterday_is_the_workday_each_crew_last_finished(): void
    {
        $night = $this->worker('Night Crew', true);
        $day   = $this->worker('Day Crew', false);

        // Both crews worked Thursday: one by daylight, one from Thursday
        // evening into Friday's small hours.
        $this->clock($day, 'time_in', '2026-09-10 08:00:00');
        $this->clock($day, 'time_out', '2026-09-10 17:00:00');
        $this->clock($night, 'time_in', '2026-09-10 20:00:00');
        $this->clock($night, 'time_out', '2026-09-11 03:00:00');

        $page = $this->dashboard('2026-09-11 10:00:00');

        $this->assertSame(2, $page->viewData('presentYesterday'),
            'on Friday morning Thursday is yesterday for both crews');
        $this->assertSame(0, $page->viewData('presentToday'));
    }

    public function test_a_pending_workers_old_clock_is_not_counted(): void
    {
        $pending = $this->worker('Not Enrolled', false, Employee::STATUS_PENDING);

        // A pending name cannot clock any more; this is a row from before
        // that rule, still on file.
        Carbon::setTestNow(Carbon::parse('2026-09-11 08:00:00', 'Asia/Manila'));
        Attendance::create([
            'employee_id' => $pending->id,
            'shift_id'    => $pending->shift_id,
            'date'        => '2026-09-11',
            'time_in'     => '2026-09-11 08:00:00',
        ]);

        $page = $this->dashboard('2026-09-11 10:00:00');

        $this->assertSame(0, $page->viewData('presentToday'));
        $this->assertCount(0, $page->viewData('todayAttendance'));
        $this->assertSame(0, $page->viewData('stillIn'));
        $this->assertSame(0, array_sum($page->viewData('attendanceData')));
    }

    /** The chart counts people, the way the tiles do — not rows, and not hours. */
    public function test_the_chart_counts_workers_not_records(): void
    {
        $emp = $this->worker('Split Day', false);

        // One worker, one day, two stretches either side of lunch.
        $this->clock($emp, 'time_in', '2026-09-11 08:00:00');
        $this->clock($emp, 'time_out', '2026-09-11 12:00:00');
        $this->clock($emp, 'time_in', '2026-09-11 13:00:00');
        $this->clock($emp, 'time_out', '2026-09-11 17:00:00');

        $data = $this->dashboard('2026-09-11 18:00:00')->viewData('attendanceData');

        $this->assertSame(1, end($data), 'two records, one worker');
    }

    /** Analytics, the assistant and device monitoring count "today" with onWorkday(). */
    public function test_every_today_figure_draws_the_same_line(): void
    {
        $emp = $this->worker('Night Crew', true);
        $this->clock($emp, 'time_in', '2026-09-10 20:00:00');
        $this->clock($emp, 'time_out', '2026-09-11 03:00:00');

        $at = fn (string $m) => Carbon::parse($m, 'Asia/Manila');

        $this->assertSame(1, Attendance::onWorkday($at('2026-09-11 02:00:00'))->count(),
            'mid-shift the night is today');
        $this->assertSame(0, Attendance::onWorkday($at('2026-09-11 10:00:00'))->count(),
            'the morning after, it is not');
        $this->assertSame(1, Attendance::onWorkday($at('2026-09-11 10:00:00')->subDay())->count(),
            'it is yesterday instead, so the trend chip still has it');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
