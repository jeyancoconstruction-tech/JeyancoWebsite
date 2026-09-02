<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\LaborType;
use App\Models\PayrollRate;
use App\Models\User;
use App\Services\PayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Dated rates, and the night differential they now carry.
 *
 * The multipliers used to live on the single settings row, so raising one
 * rewrote history: reopening last month's payroll recomputed it at today's
 * numbers and disagreed with the payslips already handed out. A wage order
 * takes effect on a date and does not reach backwards, and these tests are what
 * hold payroll to the same rule — nothing in a single computeRecord() call
 * shows which set of numbers it picked.
 */
class PayrollRateEffectivityTest extends TestCase
{
    use RefreshDatabase;

    /** ₱800/day ÷ 8 = ₱100/hour, so a peso figure reads as a percentage. */
    private const HOURLY = 100.0;

    private function admin(): User
    {
        return User::firstOrCreate(
            ['username' => 'admin.rates'],
            ['name' => 'Admin', 'password' => Hash::make('secret123'), 'role' => User::ROLE_ADMIN, 'is_active' => true]
        );
    }

    private function rate(string $from, array $overrides = []): PayrollRate
    {
        return PayrollRate::create(array_merge(PayrollRate::DEFAULTS, [
            'effective_from' => $from,
            'created_by'     => 'test',
        ], $overrides));
    }

    /** An attendance record, with control over when the shift started. */
    private function record(string $date, string $timeIn, float $hours): Attendance
    {
        $labor = LaborType::firstOrCreate(['name' => 'Mason'], ['daily_rate' => 800, 'ot_rate' => 125]);

        $employee = Employee::create([
            'name'          => 'Juan Dela Cruz',
            'position'      => 'Mason',
            'labor_type_id' => $labor->id,
            'rate_per_hour' => self::HOURLY,
            'status'        => Employee::STATUS_ACTIVE,
        ]);

        // computeRecord() derives hours from these two times, so the out time
        // has to produce exactly the span the test is describing.
        $out = \Carbon\Carbon::parse($timeIn)->addMinutes((int) round($hours * 60))->format('H:i:s');

        return Attendance::create([
            'employee_id' => $employee->id,
            'date'        => $date,
            'session'     => 'whole',
            'time_in'     => $timeIn,
            'time_out'    => $out,
        ])->load('employee.laborType');
    }

    /**
     * The config is assembled here rather than taken from PayrollService, but
     * the rate timeline is the real one — the resolver is what these tests are
     * about. config() itself is avoided because it reaches Holiday::typeMap(),
     * which queries with MySQL's YEAR() and has no answer under the SQLite the
     * suite runs on.
     */
    private function compute(Attendance $rec, array $cfgOverrides = []): array
    {
        $cfg = array_merge([
            'sss' => 0, 'philhealth' => 0, 'pagibig' => 0,
            'rateTimeline'   => PayrollRate::timeline(),
            'sundayRestDay'  => true,
            'holidayTypeMap' => [],
        ], $cfgOverrides);

        $m = new ReflectionMethod(PayrollService::class, 'computeRecord');
        $m->setAccessible(true);

        return $m->invoke(app(PayrollService::class), $rec, $cfg);
    }

    // ── Effectivity ──────────────────────────────────────────────────────────

    public function test_a_day_is_paid_at_the_rate_in_force_that_day(): void
    {
        $this->rate('2026-01-01', ['ot_multiplier' => 1.25]);
        $this->rate('2026-06-01', ['ot_multiplier' => 1.50]);

        // A Wednesday either side of the change. 8 x 100 + 2 x OT.
        $before = $this->compute($this->record('2026-03-04', '06:00:00', 10));
        $after  = $this->compute($this->record('2026-07-01', '06:00:00', 10));

        $this->assertSame(1050.0, round($before['gross'], 2), '2 OT hours at 1.25');
        $this->assertSame(1100.0, round($after['gross'], 2),  '2 OT hours at 1.50');
    }

    public function test_a_new_rate_does_not_reach_backwards(): void
    {
        $this->rate('2026-01-01', ['ot_multiplier' => 1.25]);
        $march = $this->record('2026-03-04', '06:00:00', 10);

        $before = $this->compute($march);

        // The office raises the rate today, effective next month.
        $this->rate('2026-08-01', ['ot_multiplier' => 2.00]);

        $this->assertSame(
            round($before['gross'], 2),
            round($this->compute($march->fresh()->load('employee.laborType'))['gross'], 2),
            'March must still compute at the March numbers'
        );
    }

    public function test_the_later_row_wins_when_two_share_a_date(): void
    {
        // Correcting a rate you just entered: same effectivity, saved again.
        $this->rate('2026-05-01', ['ot_multiplier' => 1.25]);
        $this->rate('2026-05-01', ['ot_multiplier' => 1.75]);

        $out = $this->compute($this->record('2026-05-06', '06:00:00', 10));

        $this->assertSame(1150.0, round($out['gross'], 2), '2 OT hours at the corrected 1.75');
    }

    public function test_the_resolver_falls_back_to_the_statutory_minimums(): void
    {
        // No rate set covers this date at all.
        $this->rate('2030-01-01');

        $out = $this->compute($this->record('2026-03-04', '06:00:00', 10));

        $this->assertSame(1050.0, round($out['gross'], 2), 'DOLE defaults, not zero');
    }

    // ── Night differential ───────────────────────────────────────────────────

    public function test_a_day_shift_earns_no_night_differential(): void
    {
        $this->rate('2026-01-01');

        $out = $this->compute($this->record('2026-03-04', '06:00:00', 10));

        $this->assertSame(0.0, round($out['night_hours'], 2));
        $this->assertSame(0.0, round($out['nightDiffPay'], 2));
    }

    public function test_a_full_night_shift_earns_ten_percent_on_every_hour(): void
    {
        $this->rate('2026-01-01');

        // 10 PM to 6 AM: eight hours, all of them night hours.
        $out = $this->compute($this->record('2026-03-04', '22:00:00', 8));

        $this->assertSame(8.0, round($out['night_hours'], 2));
        $this->assertSame(80.0, round($out['nightDiffPay'], 2), '8 x 100 x 10%');
        $this->assertSame(880.0, round($out['gross'], 2));
    }

    /**
     * A night hour is uplifted from the rate it already earns, so an overtime
     * hour at 2 AM is 10% of the overtime rate, not of the plain one.
     */
    public function test_night_overtime_is_uplifted_from_the_overtime_rate(): void
    {
        $this->rate('2026-01-01');

        // 6 PM to 4 AM: ten hours. Night hours run 10 PM to 4 AM — six of them,
        // four inside the first eight hours and two in overtime.
        $out = $this->compute($this->record('2026-03-04', '18:00:00', 10));

        $this->assertSame(6.0, round($out['night_hours'], 2));

        // (4 x 100 x 1.00 + 2 x 100 x 1.25) x 10% = 65
        $this->assertSame(65.0, round($out['nightDiffPay'], 2));
        $this->assertSame(1115.0, round($out['gross'], 2), '800 basic + 250 OT + 65 night');
    }

    public function test_the_night_multiplier_follows_the_rate_set(): void
    {
        $this->rate('2026-01-01', ['night_diff_multiplier' => 1.20]);

        $out = $this->compute($this->record('2026-03-04', '22:00:00', 8));

        $this->assertSame(160.0, round($out['nightDiffPay'], 2), '8 x 100 x 20%');
    }

    // ── Saving a rate ────────────────────────────────────────────────────────

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'effective_from'        => '2026-10-01',
            'ot_multiplier'         => 1.25,
            'night_diff_multiplier' => 1.10,
            'rest_day_multiplier'   => 1.30,
            'sss_rate'              => 5.00,
            'philhealth_rate'       => 2.50,
            'pagibig_rate'          => 2.00,
        ], $overrides);
    }

    public function test_saving_adds_a_row_and_leaves_the_old_one_alone(): void
    {
        $existing = $this->rate('2026-01-01', ['ot_multiplier' => 1.25]);
        // The migration seeds an opening row, so count from where we are.
        $before = PayrollRate::count();

        $this->actingAs($this->admin())
             ->post(route('payroll-rates.store'), $this->payload(['ot_multiplier' => 1.50]))
             ->assertSessionHasNoErrors();

        $this->assertSame($before + 1, PayrollRate::count(), 'a new row, not an edit');
        $this->assertSame(1.25, (float) $existing->fresh()->ot_multiplier, 'the old row is untouched');

        // The saved row is dated ahead, so it is the newest set — not yet the
        // one in force. current() would still answer with the older row, which
        // is the whole point of dating them.
        $saved = PayrollRate::newestFirst()->first();
        $this->assertSame(1.50, (float) $saved->ot_multiplier);
        $this->assertSame(1.25, (float) PayrollRate::current()->ot_multiplier, 'today still pays the old rate');
    }

    public function test_a_multiplier_below_one_is_refused(): void
    {
        $before = PayrollRate::count();

        $this->actingAs($this->admin())
             ->post(route('payroll-rates.store'), $this->payload(['ot_multiplier' => 0.9]))
             ->assertSessionHasErrors('ot_multiplier');

        $this->assertSame($before, PayrollRate::count(), 'nothing was written');
    }

    public function test_an_effectivity_date_is_required(): void
    {
        $this->actingAs($this->admin())
             ->post(route('payroll-rates.store'), $this->payload(['effective_from' => '']))
             ->assertSessionHasErrors('effective_from');
    }

    /**
     * Nothing sets the wage floor from a form any more — a labour type carries
     * its own daily wage. A rate row still holds one, so saving the premiums
     * has to copy the wage in force onto the new row: otherwise raising the
     * overtime multiplier would silently drop a wage order still on file.
     */
    public function test_saving_the_premiums_keeps_the_wage_in_force(): void
    {
        $this->rate('2026-09-01', ['daily_rate' => 645]);

        $this->actingAs($this->admin())
             ->post(route('payroll-rates.store'), $this->payload([
                 'effective_from' => '2026-10-01',
                 'ot_multiplier'  => 1.50,
             ]))
             ->assertSessionHasNoErrors();

        $saved = PayrollRate::newestFirst()->first();
        $this->assertSame(1.50, (float) $saved->ot_multiplier);
        $this->assertSame(645.0, (float) $saved->daily_rate, 'the wage rode along');
    }

    // ── The period bonus ─────────────────────────────────────────────────────

    /** The week's summary, straight from the grouping the payroll page uses. */
    private function week(Attendance $rec): array
    {
        $m = new ReflectionMethod(PayrollService::class, 'groupByWeek');
        $m->setAccessible(true);

        $weeks = $m->invoke(app(PayrollService::class), collect([$rec]), [
            'sss' => 0, 'philhealth' => 0, 'pagibig' => 0,
            'rateTimeline'   => PayrollRate::timeline(),
            'sundayRestDay'  => true,
            'holidayTypeMap' => [],
        ]);

        return $weeks[0]['details'][0];
    }

    public function test_the_period_bonus_comes_from_the_rate_set(): void
    {
        $this->rate('2026-01-01', ['bonus' => 500]);

        $out = $this->week($this->record('2026-03-04', '08:00:00', 8));

        $this->assertSame(500.0, round($out['bonus'], 2));
        $this->assertSame(round($out['gross'] - $out['totalDeductions'] + 500, 2), round($out['net'], 2), 'the bonus lands in net');
    }

    /**
     * The reason the bonus moved onto the dated row at all: raising it used to
     * rewrite every payslip already issued.
     */
    public function test_a_later_bonus_does_not_reach_a_period_already_paid(): void
    {
        $this->rate('2026-01-01', ['bonus' => 500]);
        $this->rate('2026-06-01', ['bonus' => 900]);

        $march = $this->week($this->record('2026-03-04', '08:00:00', 8));
        $this->assertSame(500.0, round($march['bonus'], 2), 'March was paid at the old bonus');

        $july = $this->week($this->record('2026-07-01', '08:00:00', 8));
        $this->assertSame(900.0, round($july['bonus'], 2));
    }

    /** A bonus is granted for the period, so it resolves on the day it is paid. */
    public function test_the_bonus_resolves_on_the_last_day_of_the_week(): void
    {
        $this->rate('2026-01-01', ['bonus' => 500]);
        // Effective Saturday, mid-week: the period ends Sunday, so it counts.
        $this->rate('2026-03-07', ['bonus' => 900]);

        $out = $this->week($this->record('2026-03-04', '08:00:00', 8));

        $this->assertSame(900.0, round($out['bonus'], 2));
    }

    public function test_no_bonus_is_added_when_none_is_set(): void
    {
        $this->rate('2026-01-01');

        $out = $this->week($this->record('2026-03-04', '08:00:00', 8));

        $this->assertSame(0.0, round($out['bonus'], 2));
        $this->assertSame(round($out['gross'] - $out['totalDeductions'], 2), round($out['net'], 2));
    }

    /**
     * The bonus is off the form — it is moving to the payroll records — so a
     * rate save has to carry the one in force onto the new row. Dropping it
     * would quietly stop paying a bonus people are owed.
     */
    public function test_saving_the_premiums_keeps_the_bonus_in_force(): void
    {
        $this->rate('2026-09-01', ['bonus' => 750]);

        $this->actingAs($this->admin())
             ->post(route('payroll-rates.store'), $this->payload([
                 'effective_from' => '2026-10-01',
                 'bonus'          => 999,   // ignored: there is no such field now
             ]))
             ->assertSessionHasNoErrors();

        $this->assertSame(750.0, (float) PayrollRate::newestFirst()->first()->bonus);
    }

    /** With none on file the column takes 0, not the null it will not hold. */
    public function test_a_rate_saved_with_no_bonus_on_file_stores_zero(): void
    {
        $this->actingAs($this->admin())
             ->post(route('payroll-rates.store'), $this->payload(['effective_from' => '2000-01-02']))
             ->assertSessionHasNoErrors();

        $this->assertSame(0.0, (float) PayrollRate::newestFirst()->first()->bonus);
    }


    // ── Rate history ─────────────────────────────────────────────────────────

    /**
     * Every change is an insert, so the list grows for the life of the system.
     * The card shows the newest five and says how many there are, because a
     * truncated audit list that does not admit to being truncated reads as rows
     * somebody deleted.
     */
    public function test_the_rate_history_shows_the_newest_five(): void
    {
        foreach (['2026-01-01', '2026-02-01', '2026-03-01', '2026-04-01', '2026-05-01', '2026-06-01'] as $from) {
            $this->rate($from);
        }

        $total = PayrollRate::count();

        $response = $this->actingAs($this->admin())->get(route('settings.index'));
        $response->assertOk();

        $shown = $response->viewData('payrollRates');

        $this->assertCount(5, $shown, 'the table is capped at five');
        $this->assertSame($total, $response->viewData('payrollRateTotal'), 'the full count is still reported');
        $this->assertSame('2026-06-01', $shown->first()->effective_from->toDateString(), 'newest first');
    }

    // ── The statutory defaults switch ────────────────────────────────────────

    public function test_saving_on_defaults_records_the_statutory_figures(): void
    {
        $this->actingAs($this->admin())
             ->post(route('payroll-rates.store'), [
                 'effective_from' => '2026-10-01',
                 'uses_defaults'  => 1,
             ])
             ->assertSessionHasNoErrors();

        $saved = PayrollRate::newestFirst()->first();

        $this->assertTrue($saved->uses_defaults);
        $this->assertSame(1.25, (float) $saved->ot_multiplier);
        $this->assertSame(5.00, (float) $saved->sss_rate);
    }

    /** The rate fields are locked on screen, so they are not validated. */
    public function test_on_defaults_the_rate_fields_are_not_required(): void
    {
        $this->actingAs($this->admin())
             ->post(route('payroll-rates.store'), [
                 'effective_from' => '2026-10-01',
                 'uses_defaults'  => 1,
             ])
             ->assertSessionHasNoErrors();
    }

    /** Off defaults they are, or a blank form would save a row of nothing. */
    public function test_off_defaults_the_rate_fields_are_still_required(): void
    {
        $before = PayrollRate::count();

        $this->actingAs($this->admin())
             ->post(route('payroll-rates.store'), ['effective_from' => '2026-10-01'])
             ->assertSessionHasErrors(['ot_multiplier', 'sss_rate']);

        $this->assertSame($before, PayrollRate::count());
    }

    /**
     * The point of the switch: a row on it reads the constants at compute time,
     * so a statutory figure that moves reaches it without anyone retyping.
     */
    public function test_a_row_on_defaults_answers_with_the_constants(): void
    {
        $row = $this->rate('2026-01-01', [
            'uses_defaults'       => true,
            'ot_multiplier'       => 9.99,   // stale columns the flag overrides
            'sss_rate'            => 99.00,
        ]);

        $this->assertSame(1.25, $row->toMultipliers()['ot_multiplier']);
        $this->assertSame(5.00, $row->toDeductionRates()['sss_rate']);
    }

    /** Off it, the office's own numbers are the answer — even a deliberate 0%. */
    public function test_a_row_off_defaults_keeps_its_own_numbers(): void
    {
        $row = $this->rate('2026-01-01', ['ot_multiplier' => 1.50, 'sss_rate' => 0]);

        $this->assertSame(1.50, $row->toMultipliers()['ot_multiplier']);
        $this->assertSame(0.0, $row->toDeductionRates()['sss_rate']);
    }

    /** And payroll pays at them, not at the columns the row is carrying. */
    public function test_payroll_pays_a_defaults_row_at_the_statutory_premium(): void
    {
        $this->rate('2026-01-01', [
            'uses_defaults' => true,
            'ot_multiplier' => 5.00,
            'sss_rate'      => 0, 'philhealth_rate' => 0, 'pagibig_rate' => 0,
        ]);

        $out = $this->compute($this->record('2026-03-04', '08:00:00', 10));

        // 2 OT hours at 100/hr x 1.25 — not the 5.00 sitting in the column.
        $this->assertSame(250.0, round($out['otPay'], 2));
    }

    // ── The withholding tax switch ───────────────────────────────────────────

    /** The tax table is the BIR's; whether the office withholds is not. */
    public function test_the_tax_is_withheld_by_default(): void
    {
        $this->rate('2026-01-01', ['sss_rate' => 0, 'philhealth_rate' => 0, 'pagibig_rate' => 0]);

        $out = $this->compute($this->record('2026-03-04', '08:00:00', 8));

        $this->assertGreaterThan(0, $out['withholdingTax'], '₱800 a day is above the exempt floor');
    }

    public function test_switching_the_tax_off_stops_withholding(): void
    {
        $this->rate('2026-01-01', [
            'withholding_tax' => false,
            'sss_rate' => 0, 'philhealth_rate' => 0, 'pagibig_rate' => 0,
        ]);

        $out = $this->compute($this->record('2026-03-04', '08:00:00', 8));

        $this->assertSame(0.0, round($out['withholdingTax'], 2));
        $this->assertSame(800.0, round($out['net'], 2), 'nothing is deducted at all');
    }

    /** Dated like the rest: switching it off does not un-withhold a paid week. */
    public function test_switching_the_tax_off_does_not_reach_backwards(): void
    {
        $this->rate('2026-01-01', ['sss_rate' => 0, 'philhealth_rate' => 0, 'pagibig_rate' => 0]);
        $this->rate('2026-06-01', [
            'withholding_tax' => false,
            'sss_rate' => 0, 'philhealth_rate' => 0, 'pagibig_rate' => 0,
        ]);

        $march = $this->compute($this->record('2026-03-04', '08:00:00', 8));
        $july  = $this->compute($this->record('2026-07-01', '08:00:00', 8));

        $this->assertGreaterThan(0, $march['withholdingTax'], 'March was withheld and remitted');
        $this->assertSame(0.0, round($july['withholdingTax'], 2));
    }

    /** On statutory defaults the answer is yes, whatever the column says. */
    public function test_a_row_on_defaults_withholds_regardless(): void
    {
        $row = $this->rate('2026-01-01', [
            'uses_defaults'   => true,
            'withholding_tax' => false,
        ]);

        $this->assertTrue($row->toRates()['withholding_tax']);
    }

    public function test_the_form_saves_the_tax_switch(): void
    {
        $this->actingAs($this->admin())
             ->post(route('payroll-rates.store'), $this->payload())
             ->assertSessionHasNoErrors();

        $this->assertFalse(PayrollRate::newestFirst()->first()->withholding_tax,
            'an unticked box posts nothing, which is the off answer');

        $this->actingAs($this->admin())
             ->post(route('payroll-rates.store'), $this->payload(['withholding_tax' => 1]))
             ->assertSessionHasNoErrors();

        $this->assertTrue(PayrollRate::newestFirst()->first()->withholding_tax);
    }

    public function test_saving_on_defaults_forces_the_tax_on(): void
    {
        $this->actingAs($this->admin())
             ->post(route('payroll-rates.store'), [
                 'effective_from' => '2026-10-01',
                 'uses_defaults'  => 1,
             ])
             ->assertSessionHasNoErrors();

        $this->assertTrue(PayrollRate::newestFirst()->first()->withholding_tax);
    }
}
