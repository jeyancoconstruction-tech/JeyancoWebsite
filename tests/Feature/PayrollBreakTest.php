<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\LaborType;
use App\Models\PayrollRate;
use App\Models\SystemSetting;
use App\Services\PayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

/**
 * The meal period inside a working day.
 *
 * The office sets the day as it is actually worked — six in the morning to
 * three in the afternoon is nine hours — and sets the hour of that which is
 * lunch. What the daily rate buys is the difference, so the divisor for the
 * hourly rate and the line where overtime begins both move with the break
 * rather than staying at whatever number was typed into standard hours.
 *
 * Without this, setting nine hours would have quietly cut the hourly rate: the
 * same daily wage divided by nine instead of eight, for the same work.
 */
class PayrollBreakTest extends TestCase
{
    use RefreshDatabase;

    private function day(SystemSetting $system, string $in, string $out, float $daily = 900): array
    {
        $labor = LaborType::firstOrCreate(
            ['name' => 'Mason ' . $daily],
            ['daily_rate' => $daily, 'ot_rate' => round($daily / 8 * 1.25, 2)]
        );

        $employee = Employee::create([
            'name'          => 'Juan Dela Cruz',
            'position'      => 'Mason',
            'labor_type_id' => $labor->id,
            'rate_per_hour' => $daily / 8,
            'status'        => Employee::STATUS_ACTIVE,
        ]);

        $rec = Attendance::create([
            'employee_id' => $employee->id,
            'date'        => '2026-09-07',
            'session'     => 'whole',
            'time_in'     => $in,
            'time_out'    => $out,
        ])->load('employee.laborType');

        // config() is avoided: it reaches Holiday::typeMap(), whose MySQL
        // YEAR() has no answer under the SQLite this suite runs on.
        $m = new ReflectionMethod(PayrollService::class, 'computeRecord');
        $m->setAccessible(true);

        return $m->invoke(app(PayrollService::class), $rec, [
            'rateTimeline'   => PayrollRate::timeline(),
            'bonus'          => 0,
            'sundayRestDay'  => false,
            'holidayTypeMap' => [],
            'day'            => $system,
            'shifts'         => [],
        ]);
    }

    private function settings(float $standard, int $break): SystemSetting
    {
        $s = SystemSetting::current();
        $s->forceFill([
            'standard_hours_per_day' => $standard,
            'unpaid_break_minutes'   => $break,
            'auto_count_overtime'    => true,
        ]);

        return $s;
    }

    /** Nine hours on site with an hour for lunch is an ordinary eight-hour day. */
    public function test_a_nine_hour_day_with_an_hour_of_lunch_pays_eight(): void
    {
        $r = $this->day($this->settings(9, 60), '06:00:00', '15:00:00');

        $this->assertEqualsWithDelta(8.0, $r['regular_hours'], 0.01);
        $this->assertEqualsWithDelta(0.0, $r['ot_hours'], 0.01, 'a normal day is not overtime');
    }

    /** ₱900 buys the eight hours worked, not the nine hours on site. */
    public function test_the_hourly_rate_divides_by_the_paid_hours(): void
    {
        $r = $this->day($this->settings(9, 60), '06:00:00', '15:00:00');

        $this->assertEqualsWithDelta(900.0, $r['basicPay'], 0.01,
            'the daily rate is what a normal day pays, break or no break');
    }

    /** Past three in the afternoon is overtime, and only the part past it. */
    public function test_overtime_begins_after_the_paid_hours(): void
    {
        $r = $this->day($this->settings(9, 60), '06:00:00', '17:00:00');

        $this->assertEqualsWithDelta(8.0, $r['regular_hours'], 0.01);
        $this->assertEqualsWithDelta(2.0, $r['ot_hours'], 0.01, 'eleven on site, one of them lunch');
    }

    /**
     * A crew that clocks out for lunch has already left it out. Taking the hour
     * off again would charge them for the same lunch twice.
     */
    public function test_a_short_stretch_loses_no_break(): void
    {
        $morning = $this->day($this->settings(9, 60), '06:00:00', '11:00:00');

        $this->assertEqualsWithDelta(5.0, $morning['regular_hours'], 0.01);
    }

    /** The setting off is the setting absent: nothing about a day changes. */
    public function test_no_break_leaves_the_day_alone(): void
    {
        $r = $this->day($this->settings(8, 0), '06:00:00', '14:00:00');

        $this->assertEqualsWithDelta(8.0, $r['regular_hours'], 0.01);
        $this->assertEqualsWithDelta(900.0, $r['basicPay'], 0.01);
    }

    /** A longer day can never come out as a smaller wage. */
    public function test_a_longer_day_never_pays_less(): void
    {
        $cfg  = $this->settings(9, 60);
        $last = -1.0;

        foreach (['10:00:00', '11:00:00', '11:30:00', '12:00:00', '13:00:00', '15:00:00', '17:00:00'] as $out) {
            $pay = $this->day($cfg, '06:00:00', $out)['gross'];
            $this->assertGreaterThanOrEqual($last - 0.001, $pay, "a day ending {$out} pays less than a shorter one");
            $last = $pay;
        }
    }
}
