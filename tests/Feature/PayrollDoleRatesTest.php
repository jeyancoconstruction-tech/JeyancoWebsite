<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\LaborType;
use App\Models\PayrollRate;
use App\Services\PayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Tests\TestCase;

/**
 * The Labor Code's pay factors, pinned to the figure rather than the formula.
 *
 * Overtime carries two different premiums — +25% on an ordinary day, +30% of
 * that day's rate on a rest day, special day or holiday — and payroll used to
 * apply 25% to every overtime hour, then multiply the whole day by the holiday
 * factor. That paid 250% for overtime on a regular holiday where the law says
 * 260%, and 162.5% on a rest day where it says 169%.
 *
 * These are money bugs of a few pesos an hour, which is exactly the kind that
 * survives a reading of the code: every line looks reasonable on its own. So
 * the table is asserted as percentages of the hourly rate, straight from the
 * DOLE Handbook on Workers' Statutory Monetary Benefits, and a reader can check
 * the numbers against the handbook without following the arithmetic.
 *
 * computeRecord() and doleFactors() are private and reached by reflection on
 * purpose: driving this through computeForRange() would tie the assertions to
 * the real Philippine holiday calendar, so a test of the arithmetic would start
 * failing whenever a proclamation moved a date.
 */
class PayrollDoleRatesTest extends TestCase
{
    use RefreshDatabase;

    /** ₱800/day ÷ 8 = ₱100/hour, so a percentage reads straight off the peso. */
    private const HOURLY = 100.0;

    private function cfg(array $overrides = []): array
    {
        return array_merge([
            'sss' => 0, 'philhealth' => 0, 'pagibig' => 0,
            'rateTimeline'   => [['from' => '2000-01-01', 'rates' => PayrollRate::fallbackRates()]],
            'bonus'          => 0,
            'restDayEnabled'  => true,
            'holidayTypeMap' => [],
        ], $overrides);
    }

    private function factors(?string $holidayType, bool $isRestDay, array $rates = null): array
    {
        $m = new ReflectionMethod(PayrollService::class, 'doleFactors');
        $m->setAccessible(true);

        return $m->invoke(app(PayrollService::class), $holidayType, $isRestDay, $rates ?? PayrollRate::DEFAULTS);
    }

    /**
     * The DOLE table. First column is the first eight hours, second is each
     * overtime hour, both as a multiple of the ordinary hourly rate.
     */
    public static function doleTable(): array
    {
        return [
            'ordinary day'                      => [null,      false, 1.00, 1.25],
            'rest day'                          => [null,      true,  1.30, 1.69],
            'special non-working day'           => ['special', false, 1.30, 1.69],
            'special day on a rest day'         => ['special', true,  1.50, 1.95],
            'regular holiday'                   => ['regular', false, 2.00, 2.60],
            'regular holiday on a rest day'     => ['regular', true,  2.60, 3.38],
            'custom holiday reads as a regular' => ['custom',  false, 2.00, 2.60],
        ];
    }

    #[DataProvider('doleTable')]
    public function test_the_dole_pay_factors(?string $type, bool $restDay, float $regular, float $overtime): void
    {
        [$actualRegular, $actualOvertime] = $this->factors($type, $restDay);

        $this->assertSame($regular, round($actualRegular, 4), 'first eight hours');
        $this->assertSame($overtime, round($actualOvertime, 4), 'each overtime hour');
    }

    /**
     * The regressions by name, so a failure says which rate slipped rather than
     * only that a number moved.
     */
    public function test_overtime_on_a_regular_holiday_pays_260_not_250(): void
    {
        $this->assertSame(2.60, round($this->factors('regular', false)[1], 4));
    }

    public function test_overtime_on_a_rest_day_pays_169_not_162_5(): void
    {
        $this->assertSame(1.69, round($this->factors(null, true)[1], 4));
    }

    public function test_a_regular_holiday_on_a_rest_day_pays_260_not_230(): void
    {
        $this->assertSame(2.60, round($this->factors('regular', true)[0], 4));
    }

    /** The factors follow the rate set, and a company may pay above the law. */
    public function test_the_multipliers_come_from_the_rate_set(): void
    {
        $generous = [
            'ot_multiplier'              => 1.50,
            'night_diff_multiplier'      => 1.10,
            'rest_day_multiplier'        => 2.00,
            'regular_holiday_multiplier' => 2.00,
        ];

        $this->assertSame(1.50, round($this->factors(null, false, $generous)[1], 4), 'ordinary-day OT');
        $this->assertSame(4.00, round($this->factors('regular', false, $generous)[1], 4), 'holiday 2.00 x premium-day 2.00');
    }

    // ── End to end: the factors reaching actual pesos ────────────────────────

    private function record(string $date, float $hours): Attendance
    {
        $labor = LaborType::create(['name' => 'Mason', 'daily_rate' => 800, 'ot_rate' => 125]);

        $employee = Employee::create([
            'name'          => 'Juan Dela Cruz',
            'position'      => 'Mason',
            'labor_type_id' => $labor->id,
            'rate_per_hour' => self::HOURLY,
            'status'        => Employee::STATUS_ACTIVE,
        ]);

        $out = sprintf('%02d:%02d:00', 6 + (int) $hours, round(($hours - (int) $hours) * 60));

        return Attendance::create([
            'employee_id' => $employee->id,
            'date'        => $date,
            'session'     => 'whole',
            'time_in'     => '06:00:00',
            'time_out'    => $out,
        ])->load('employee.laborType');
    }

    private function compute(Attendance $rec, array $cfg): array
    {
        $m = new ReflectionMethod(PayrollService::class, 'computeRecord');
        $m->setAccessible(true);

        return $m->invoke(app(PayrollService::class), $rec, $cfg);
    }

    public function test_ten_hours_on_an_ordinary_day(): void
    {
        // 8 x 100 + 2 x 125 = 1,050
        $out = $this->compute($this->record('2026-09-02', 10), $this->cfg());

        $this->assertSame(1050.0, round($out['gross'], 2));
        $this->assertSame(0.0, $out['holidayPay']);
        $this->assertSame(0.0, $out['restDayPay']);
    }

    public function test_ten_hours_on_a_regular_holiday(): void
    {
        // 8 x 200 + 2 x 260 = 2,120. The old math gave 8 x 200 + 2 x 250 = 2,100.
        $cfg = $this->cfg(['holidayTypeMap' => ['2026-09-02' => 'regular']]);
        $out = $this->compute($this->record('2026-09-02', 10), $cfg);

        $this->assertSame(2120.0, round($out['gross'], 2));
        $this->assertSame(1070.0, round($out['holidayPay'], 2), 'everything above the ordinary-day 1,050');
    }

    public function test_ten_hours_on_a_sunday_rest_day(): void
    {
        // 2026-09-06 is a Sunday. 8 x 130 + 2 x 169 = 1,378.
        $out = $this->compute($this->record('2026-09-06', 10), $this->cfg());

        $this->assertSame(1378.0, round($out['gross'], 2));
        $this->assertSame(328.0, round($out['restDayPay'], 2));
        $this->assertSame(0.0, $out['holidayPay']);
    }

    /**
     * A day that is both is one rate, not two premiums stacked. The premium is
     * attributed to the holiday because that is the rate being applied.
     */
    public function test_a_regular_holiday_falling_on_a_sunday(): void
    {
        // 8 x 260 + 2 x 338 = 2,756. Stacking 200% and 30% would have given 2,415.
        $cfg = $this->cfg(['holidayTypeMap' => ['2026-09-06' => 'regular']]);
        $out = $this->compute($this->record('2026-09-06', 10), $cfg);

        $this->assertSame(2756.0, round($out['gross'], 2));
        $this->assertSame(1706.0, round($out['holidayPay'], 2));
        $this->assertSame(0.0, $out['restDayPay'], 'not counted twice');
    }

    /** Turning the rest-day setting off makes a Sunday an ordinary day. */
    public function test_a_sunday_with_the_rest_day_setting_off(): void
    {
        $out = $this->compute($this->record('2026-09-06', 10), $this->cfg(['restDayEnabled' => false]));

        $this->assertSame(1050.0, round($out['gross'], 2));
        $this->assertSame(0.0, $out['restDayPay']);
    }

    /** A contractual worker is settled against their contract, not here. */
    public function test_a_contractual_worker_still_earns_nothing(): void
    {
        $rec = $this->record('2026-09-06', 10);
        $rec->employee->update([
            'employment_type' => Employee::EMPLOYMENT_CONTRACTUAL,
            'labor_type_id'   => null,
            'rate_per_hour'   => 0,
        ]);
        $rec->load('employee.laborType');

        $cfg = $this->cfg(['holidayTypeMap' => ['2026-09-06' => 'regular']]);
        $out = $this->compute($rec, $cfg);

        $this->assertSame(0.0, round($out['gross'], 2));
        $this->assertSame(0.0, $out['holidayPay']);
    }
}
