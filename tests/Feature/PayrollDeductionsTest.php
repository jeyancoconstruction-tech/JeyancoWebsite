<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\LaborType;
use App\Models\PayrollRate;
use App\Services\PayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

/**
 * The money taken off a day, and the wage floor put under it.
 *
 * The contribution rates used to live on the single settings row, so a new SSS
 * circular rewrote every payslip already issued. They are dated now, like the
 * premiums. Withholding tax is not dated and not configurable: it is the BIR
 * table, which is the law rather than an office preference.
 */
class PayrollDeductionsTest extends TestCase
{
    use RefreshDatabase;

    /** A rate set, on top of the opening row the migration seeds. */
    private function rate(string $from, array $overrides = []): PayrollRate
    {
        return PayrollRate::create(array_merge(PayrollRate::DEFAULTS, [
            'effective_from'  => $from,
            'sss_rate'        => 0,
            'philhealth_rate' => 0,
            'pagibig_rate'    => 0,
            'created_by'      => 'test',
        ], $overrides));
    }

    /** One eight-hour day for a worker on $daily per day. */
    private function record(string $date, float $daily): Attendance
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

        return Attendance::create([
            'employee_id' => $employee->id,
            'date'        => $date,
            'session'     => 'whole',
            'time_in'     => '06:00:00',
            'time_out'    => '14:00:00',
        ])->load('employee.laborType');
    }

    /**
     * config() is avoided here: it reaches Holiday::typeMap(), which queries
     * with MySQL's YEAR() and has no answer under the SQLite the suite runs on.
     * The rate timeline is the real one — resolving it is what is under test.
     */
    private function compute(Attendance $rec): array
    {
        $cfg = [
            'rateTimeline'   => PayrollRate::timeline(),
            'bonus'          => 0,
            'sundayRestDay'  => true,
            'holidayTypeMap' => [],
        ];

        $m = new ReflectionMethod(PayrollService::class, 'computeRecord');
        $m->setAccessible(true);

        return $m->invoke(app(PayrollService::class), $rec, $cfg);
    }

    // ── Contributions ────────────────────────────────────────────────────────

    public function test_contributions_are_the_percentages_of_the_set_in_force(): void
    {
        $this->rate('2026-01-01', [
            'sss_rate' => 5.00, 'philhealth_rate' => 2.50, 'pagibig_rate' => 2.00,
        ]);

        $out = $this->compute($this->record('2026-03-04', 800));

        $this->assertSame(800.0, round($out['gross'], 2));
        $this->assertSame(40.0, round($out['sssDeduction'], 2), '5% of 800');
        $this->assertSame(20.0, round($out['philhealthDeduction'], 2), '2.5% of 800');
        $this->assertSame(16.0, round($out['pagibigDeduction'], 2), '2% of 800');

        // 800 less 76 leaves 724 taxable, 39 into the first taxed bracket.
        $this->assertSame(5.85, round($out['withholdingTax'], 2), '15% of 39');
        $this->assertSame(81.85, round($out['autoDeductions'], 2));
        $this->assertSame(718.15, round($out['net'], 2));
    }

    public function test_a_new_contribution_rate_does_not_reach_backwards(): void
    {
        $this->rate('2026-01-01', ['sss_rate' => 5.00]);
        $this->rate('2026-06-01', ['sss_rate' => 10.00]);

        $before = $this->compute($this->record('2026-03-04', 800));
        $after  = $this->compute($this->record('2026-07-01', 800));

        $this->assertSame(40.0, round($before['sssDeduction'], 2), 'March is still on 5%');
        $this->assertSame(80.0, round($after['sssDeduction'], 2), 'July is on 10%');
    }

    public function test_an_office_that_deducts_nothing_keeps_deducting_nothing(): void
    {
        // 0 is a real answer, not a missing one, so it must not be filled in
        // with the statutory default.
        $this->rate('2026-01-01');

        $out = $this->compute($this->record('2026-03-04', 600));

        $this->assertSame(0.0, round($out['sssDeduction'], 2));
        $this->assertSame(0.0, round($out['philhealthDeduction'], 2));
        $this->assertSame(0.0, round($out['pagibigDeduction'], 2));
    }

    // ── The wage floor ───────────────────────────────────────────────────────

    public function test_the_wage_floor_lifts_a_labour_type_below_it(): void
    {
        $this->rate('2026-01-01', ['daily_rate' => 645]);

        $out = $this->compute($this->record('2026-03-04', 500));

        $this->assertSame(645.0, round($out['dailyRate'], 2));
        $this->assertSame(645.0, round($out['gross'], 2), 'eight hours at the floor');
    }

    public function test_a_labour_type_above_the_floor_is_left_alone(): void
    {
        $this->rate('2026-01-01', ['daily_rate' => 645]);

        $out = $this->compute($this->record('2026-03-04', 800));

        $this->assertSame(800.0, round($out['dailyRate'], 2));
        $this->assertSame(800.0, round($out['gross'], 2));
    }

    public function test_no_floor_on_file_leaves_every_rate_as_configured(): void
    {
        $this->rate('2026-01-01');   // daily_rate stays null

        $out = $this->compute($this->record('2026-03-04', 500));

        $this->assertSame(500.0, round($out['gross'], 2));
    }

    public function test_a_floor_raised_later_does_not_reach_backwards(): void
    {
        $this->rate('2026-01-01', ['daily_rate' => 500]);
        $this->rate('2026-06-01', ['daily_rate' => 645]);

        $before = $this->compute($this->record('2026-03-04', 400));
        $after  = $this->compute($this->record('2026-07-01', 400));

        $this->assertSame(500.0, round($before['gross'], 2));
        $this->assertSame(645.0, round($after['gross'], 2));
    }

    // ── Withholding tax ──────────────────────────────────────────────────────

    public function test_a_day_under_the_exemption_is_not_taxed(): void
    {
        $this->rate('2026-01-01');

        // 600 a day is under the daily bracket floor of 685 — most of a
        // construction payroll sits here.
        $out = $this->compute($this->record('2026-03-04', 600));

        $this->assertSame(0.0, round($out['withholdingTax'], 2));
        $this->assertSame(600.0, round($out['net'], 2));
    }

    public function test_the_bir_brackets_are_applied_to_the_day(): void
    {
        $this->rate('2026-01-01');

        // 2,000 taxable: 61.65 fixed at the 1,096 floor, plus 20% of the 904
        // above it.
        $out = $this->compute($this->record('2026-03-04', 2000));

        $this->assertSame(242.45, round($out['withholdingTax'], 2));
        $this->assertSame(1757.55, round($out['net'], 2));
    }

    public function test_tax_is_charged_on_pay_net_of_contributions(): void
    {
        $this->rate('2026-01-01', ['sss_rate' => 5.00]);

        // 2,000 gross less 100 SSS leaves 1,900 taxable: 61.65 + 20% of 804.
        $out = $this->compute($this->record('2026-03-04', 2000));

        $this->assertSame(100.0, round($out['sssDeduction'], 2));
        $this->assertSame(222.45, round($out['withholdingTax'], 2));
    }

    public function test_a_contractual_worker_is_not_deducted_from(): void
    {
        $this->rate('2026-01-01', ['sss_rate' => 5.00]);

        $rec = $this->record('2026-03-04', 2000);
        $rec->employee->update(['employment_type' => Employee::EMPLOYMENT_CONTRACTUAL]);
        $rec->load('employee.laborType');

        $out = $this->compute($rec);

        $this->assertSame(0.0, round($out['gross'], 2));
        $this->assertSame(0.0, round($out['sssDeduction'], 2));
        $this->assertSame(0.0, round($out['withholdingTax'], 2));
    }
}
