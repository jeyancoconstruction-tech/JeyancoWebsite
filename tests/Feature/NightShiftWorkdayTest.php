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
 * One workday, whichever shift worked it.
 *
 * A night shift times in at 8 in the evening and out at 5 the next morning.
 * Those are two calendar dates and one day's work, and everything that counts
 * attendance — the day view, the status badge, the missed-sign-out card, the
 * history, payroll — has to agree on which day that was: the evening it
 * started, not the morning it ended.
 *
 * The writing side already did. `date` is stamped from the shift, so the row
 * the kiosk opens at 8pm keeps that date when it closes at 5am. The reading
 * side asked the calendar instead, and at midnight the calendar changes under
 * a crew who have not moved: the night shift dropped out of Today, reappeared
 * in History, and was counted as having failed to clock out — all while they
 * were standing on site. Clearing the history then deleted the shift in
 * progress, because to the calendar it was already yesterday.
 *
 * @see \App\Support\WorkSchedule::shiftDayFor()
 * @see \App\Models\Attendance::workdaysAt()
 */
class NightShiftWorkdayTest extends TestCase
{
    use RefreshDatabase;

    /** The evening the night shift starts, and the morning it ends. */
    private const EVENING = '2026-09-11 20:00:00';
    private const WORKDAY = '2026-09-11';

    protected function setUp(): void
    {
        parent::setUp();

        // Count hours by the shift's own sessions rather than the older
        // flat-hours rule, which is what every day from here on is paid by.
        SystemSetting::current()->forceFill(['schedule_rules_from' => '2026-09-01'])->save();
    }

    private function shift(bool $night): Shift
    {
        return Shift::where('crosses_midnight', $night)->firstOrFail();
    }

    private function worker(string $name, bool $night): Employee
    {
        return Employee::create([
            'name'            => $name,
            'status'          => Employee::STATUS_ACTIVE,
            'employment_type' => Employee::EMPLOYMENT_DAILY,
            'labor_type_id'   => LaborType::create(['name' => 'Mason ' . $name, 'daily_rate' => 800, 'ot_rate' => 125])->id,
            'shift_id'        => $this->shift($night)->id,
            'rate_per_hour'   => 100,
        ]);
    }

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin', 'username' => 'admin.workday', 'password' => 'secret123',
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

    private function at(string $moment): void
    {
        Carbon::setTestNow(Carbon::parse($moment, 'Asia/Manila'));
    }

    /** One employee's day out of a payroll run. */
    private function payrollDay(string $from, string $to, string $name): ?array
    {
        foreach (app(\App\Services\PayrollService::class)->computeForRange($from, $to)['days'] as $day) {
            foreach ($day['details'] as $row) {
                if ($row['name'] === $name) {
                    return $row + ['date' => $day['date']];
                }
            }
        }

        return null;
    }

    // ── The record itself ────────────────────────────────────────────────

    public function test_a_night_shift_clocking_out_the_next_morning_keeps_the_evening_workday(): void
    {
        $emp = $this->worker('Night Crew', true);

        $this->clock($emp, 'time_in', self::EVENING);
        $this->clock($emp, 'time_out', '2026-09-12 05:00:00');

        $row = Attendance::where('employee_id', $emp->id)->sole();

        $this->assertSame(self::WORKDAY, Carbon::parse($row->date)->toDateString(),
            'the day belongs to the evening it started, not the morning it ended');
        $this->assertSame('2026-09-12 05:00:00', Carbon::parse($row->time_out)->toDateTimeString(),
            'the time-out keeps its own date — the stretch really did cross midnight');
    }

    public function test_timing_in_after_midnight_still_files_under_the_evening_before(): void
    {
        $emp = $this->worker('Late Starter', true);

        // The second half of the night shift begins at 1am, on the next date.
        $this->clock($emp, 'time_in', '2026-09-12 01:00:00');

        $row = Attendance::where('employee_id', $emp->id)->sole();

        $this->assertSame(self::WORKDAY, Carbon::parse($row->date)->toDateString());
        $this->assertSame('PM', $row->session, 'one in the morning is the second half of the night');
    }

    // ── What the office sees while they are still working ────────────────

    public function test_a_night_shift_mid_stretch_reads_as_active_not_invalid(): void
    {
        $emp = $this->worker('Still Working', true);
        $this->clock($emp, 'time_in', self::EVENING);

        $this->at('2026-09-12 02:00:00');
        $row = Attendance::with('shift')->where('employee_id', $emp->id)->sole();

        $this->assertSame('active', $row->status,
            'two in the morning is the middle of their day, not a missed sign-out');
    }

    public function test_the_day_view_holds_the_night_crew_after_midnight(): void
    {
        $night = $this->worker('Night Crew', true);
        $this->clock($night, 'time_in', self::EVENING);

        $this->at('2026-09-12 02:00:00');
        $page = $this->actingAs($this->admin())->get(route('attendance'))->assertOk();

        $this->assertCount(1, $page->viewData('todayAttendances'),
            'the crew on site has to be on the day view');
        $this->assertSame(0, $page->viewData('historyAttendances')->total(),
            'a day still being worked is not history');
        $this->assertSame(1, $page->viewData('clockedIn'));
    }

    public function test_a_running_night_shift_is_not_counted_as_a_missed_sign_out(): void
    {
        $emp = $this->worker('Night Crew', true);
        $this->clock($emp, 'time_in', self::EVENING);

        $this->at('2026-09-12 02:00:00');
        $page = $this->actingAs($this->admin())->get(route('attendance'))->assertOk();

        $this->assertSame(0, $page->viewData('invalidCount'),
            'they have not failed to clock out — they have not finished');
    }

    public function test_clearing_the_history_leaves_a_running_night_shift_alone(): void
    {
        $emp = $this->worker('Night Crew', true);
        $this->clock($emp, 'time_in', self::EVENING);

        // Two in the morning: to the calendar this row is already yesterday's.
        $this->at('2026-09-12 02:00:00');
        $this->actingAs($this->admin())
             ->deleteJson(route('attendance.history.delete-all'))
             ->assertOk()
             ->assertJson(['deleted' => 0]);

        $this->assertDatabaseHas('attendances', ['employee_id' => $emp->id, 'date' => self::WORKDAY]);
    }

    public function test_the_day_crew_still_falls_into_history_once_their_day_is_over(): void
    {
        $day = $this->worker('Day Crew', false);
        $this->clock($day, 'time_in', '2026-09-11 08:00:00');
        $this->clock($day, 'time_out', '2026-09-11 17:00:00');

        // Their next day has opened; the finished one is history.
        $this->at('2026-09-12 09:00:00');
        $page = $this->actingAs($this->admin())->get(route('attendance'))->assertOk();

        $this->assertCount(0, $page->viewData('todayAttendances'));
        $this->assertSame(1, $page->viewData('historyAttendances')->total());
    }

    /** A clock time alone cannot say the out was the following morning. */
    public function test_a_stretch_over_midnight_is_marked_as_ending_the_next_day(): void
    {
        $emp = $this->worker('Night Crew', true);
        $this->clock($emp, 'time_in', self::EVENING);
        $this->clock($emp, 'time_out', '2026-09-12 05:00:00');

        $this->assertSame(1, Attendance::where('employee_id', $emp->id)->sole()->out_days_later);

        $day = $this->worker('Day Crew', false);
        $this->clock($day, 'time_in', '2026-09-11 08:00:00');
        $this->clock($day, 'time_out', '2026-09-11 17:00:00');

        $this->assertSame(0, Attendance::where('employee_id', $day->id)->sole()->out_days_later,
            'an ordinary day is not marked');
    }

    // ── Payroll ──────────────────────────────────────────────────────────

    public function test_payroll_pays_the_night_on_the_workday_it_began(): void
    {
        $emp = $this->worker('Night Crew', true);
        $this->clock($emp, 'time_in', self::EVENING);
        $this->clock($emp, 'time_out', '2026-09-12 05:00:00');

        $this->at('2026-09-12 09:00:00');

        $paid = $this->payrollDay(self::WORKDAY, self::WORKDAY, 'Night Crew');
        $this->assertNotNull($paid, 'the night has to be paid on the day it started');
        $this->assertSame(self::WORKDAY, $paid['date']);

        // 8pm to 5am is nine hours on site; one of them is the unpaid break
        // between the two sessions, and the shift ends exactly at 5.
        $this->assertEqualsWithDelta(8.0, $paid['hours'], 0.01);
        $this->assertEqualsWithDelta(0.0, $paid['ot_hours'], 0.01);

        $this->assertNull($this->payrollDay('2026-09-12', '2026-09-12', 'Night Crew'),
            'nothing lands on the calendar day the shift happened to end on');
    }

    public function test_a_night_running_past_its_end_earns_overtime_on_the_same_workday(): void
    {
        $emp = $this->worker('Night Crew', true);
        $this->clock($emp, 'time_in', self::EVENING);
        $this->clock($emp, 'time_out', '2026-09-12 07:00:00');

        $this->at('2026-09-12 09:00:00');
        $paid = $this->payrollDay(self::WORKDAY, self::WORKDAY, 'Night Crew');

        $this->assertSame(self::WORKDAY, $paid['date']);
        $this->assertEqualsWithDelta(10.0, $paid['hours'], 0.01, '8 paid hours plus the two past 5am');
        $this->assertEqualsWithDelta(2.0, $paid['ot_hours'], 0.01);
    }

    public function test_both_crews_land_on_the_one_workday(): void
    {
        $night = $this->worker('Night Crew', true);
        $day   = $this->worker('Day Crew', false);

        $this->clock($day, 'time_in', '2026-09-11 08:00:00');
        $this->clock($day, 'time_out', '2026-09-11 17:00:00');
        $this->clock($night, 'time_in', self::EVENING);
        $this->clock($night, 'time_out', '2026-09-12 05:00:00');

        $this->at('2026-09-12 09:00:00');
        $days = app(\App\Services\PayrollService::class)
            ->computeForRange(self::WORKDAY, self::WORKDAY)['days'];

        $this->assertCount(1, $days, 'one workday, both shifts');
        $this->assertSame(self::WORKDAY, $days[0]['date']);
        $this->assertEqualsWithDelta(
            8.0, collect($days[0]['details'])->firstWhere('name', 'Day Crew')['hours'], 0.01);
        $this->assertEqualsWithDelta(
            8.0, collect($days[0]['details'])->firstWhere('name', 'Night Crew')['hours'], 0.01);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
