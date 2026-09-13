<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\LaborType;
use App\Models\Shift;
use App\Models\SystemSetting;
use App\Services\PayrollService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The grace period, both halves of it.
 *
 * A worker who comes in inside the shift's grace period after the start is
 * not late — payroll never reported it as lateness — and is not paid as late
 * either: the pay runs from the start of the shift, as if they had been at
 * the gate on the minute. Past the grace the clock stands, and so does the
 * lateness, counted from the start.
 */
class GracePeriodTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        SystemSetting::current()->forceFill(['schedule_rules_from' => '2026-09-01'])->save();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Lay a shift out — eight paid hours, an hour's lunch — and hand back its worker. */
    private function worker(bool $night = false, int $grace = 15): Employee
    {
        $shift = Shift::where('crosses_midnight', $night)->firstOrFail();

        $shift->forceFill(($night
                ? Shift::layOut('20:00', '05:00', '00:00', '01:00')
                : Shift::layOut('08:00', '17:00', '12:00', '13:00'))
            + ['regular_minutes' => 480, 'grace_period_minutes' => $grace])->save();

        return Employee::create([
            'name' => $night ? 'Night Crew' : 'Day Crew', 'status' => Employee::STATUS_ACTIVE,
            'employment_type' => Employee::EMPLOYMENT_DAILY,
            'labor_type_id' => LaborType::create(['name' => 'Mason', 'daily_rate' => 800, 'ot_rate' => 125])->id,
            'shift_id' => $shift->id, 'rate_per_hour' => 100,
        ]);
    }

    private function row(Employee $e, string $date, string $in, string $out, string $session = 'AM'): void
    {
        Attendance::create([
            'employee_id' => $e->id, 'shift_id' => $e->shift_id, 'date' => $date, 'session' => $session,
            'time_in' => $in, 'time_out' => $out,
        ]);
    }

    /** The day as payroll pays it: minutes worked, and minutes late. */
    private function paid(Employee $e, string $date): array
    {
        $rows = collect(app(PayrollService::class)->computeForRange($date, $date)['days'])
            ->flatMap(fn ($d) => $d['details'])
            ->where('employee_id', $e->id);

        return ['minutes' => (int) $rows->sum('minutes'), 'late' => (int) $rows->sum('late_minutes')];
    }

    public function test_ten_minutes_into_the_grace_is_paid_from_the_start(): void
    {
        $e = $this->worker();
        $this->row($e, '2026-09-10', '2026-09-10 08:10:00', '2026-09-10 17:00:00');

        $this->assertSame(['minutes' => 480, 'late' => 0], $this->paid($e, '2026-09-10'),
            'eight to five less the hour for lunch — not from ten past eight');
    }

    public function test_the_last_minute_of_the_grace_still_counts(): void
    {
        $e = $this->worker();
        $this->row($e, '2026-09-10', '2026-09-10 08:15:00', '2026-09-10 17:00:00');

        $this->assertSame(['minutes' => 480, 'late' => 0], $this->paid($e, '2026-09-10'));
    }

    public function test_past_the_grace_the_clock_stands_and_so_does_the_lateness(): void
    {
        $e = $this->worker();
        $this->row($e, '2026-09-10', '2026-09-10 08:16:00', '2026-09-10 17:00:00');

        $this->assertSame(['minutes' => 464, 'late' => 16], $this->paid($e, '2026-09-10'),
            'paid from 8:16 and sixteen minutes late, counted from eight');
    }

    public function test_it_holds_after_lunch_too(): void
    {
        $e = $this->worker();
        $this->row($e, '2026-09-10', '2026-09-10 08:00:00', '2026-09-10 12:00:00', 'AM');
        $this->row($e, '2026-09-10', '2026-09-10 13:10:00', '2026-09-10 17:00:00', 'PM');

        $this->assertSame(['minutes' => 480, 'late' => 0], $this->paid($e, '2026-09-10'));
    }

    /** Only the first time in of a session is forgiven. */
    public function test_a_second_time_in_is_not_paid_back_to_the_start(): void
    {
        $e = $this->worker();
        $this->row($e, '2026-09-10', '2026-09-10 08:05:00', '2026-09-10 08:07:00');   // a mistaken time-out
        $this->row($e, '2026-09-10', '2026-09-10 08:10:00', '2026-09-10 17:00:00');

        // 8:00–8:07, then 8:10–12:00 and 1:00–5:00. The three minutes spent
        // clocked out are not paid, and 8:00–8:07 is not paid twice.
        $this->assertSame(477, $this->paid($e, '2026-09-10')['minutes']);
    }

    public function test_no_grace_means_the_clock_stands(): void
    {
        $e = $this->worker(grace: 0);
        $this->row($e, '2026-09-10', '2026-09-10 08:05:00', '2026-09-10 17:00:00');

        $this->assertSame(['minutes' => 475, 'late' => 5], $this->paid($e, '2026-09-10'));
    }

    public function test_it_holds_for_the_night_shift(): void
    {
        $e = $this->worker(night: true);
        $this->row($e, '2026-09-10', '2026-09-10 20:10:00', '2026-09-11 05:00:00');

        $this->assertSame(['minutes' => 480, 'late' => 0], $this->paid($e, '2026-09-10'));
    }

    /**
     * The kiosk tells the worker the overtime payroll will pay. Eight regular
     * hours are bought by five o'clock when the day counts from eight, so a
     * six o'clock time-out is one hour over — not fifty minutes.
     */
    public function test_the_kiosk_counts_overtime_from_the_same_start(): void
    {
        $e = $this->worker();

        $clock = function (string $type, string $at) use ($e): array {
            Carbon::setTestNow(Carbon::parse($at, 'Asia/Manila'));

            return $this->postJson('/api/kiosk/attendance', ['employee_id' => $e->id, 'type' => $type])
                ->assertOk()
                ->json();
        };

        $clock('time_in', '2026-09-10 08:10:00');
        $answer = $clock('time_out', '2026-09-10 18:00:00');

        $this->assertEqualsWithDelta(1.0, $answer['ot_hours'], 0.001);
        $this->assertSame(['minutes' => 540, 'late' => 0], $this->paid($e, '2026-09-10'));
    }
}
