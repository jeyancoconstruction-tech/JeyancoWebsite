<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\LaborType;
use App\Models\PayrollRate;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\ValeAdvance;
use App\Services\PayrollService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use ReflectionMethod;
use Tests\TestCase;

/**
 * A cash advance, collected a piece at a time.
 *
 * The vale on an attendance row comes out of one day. This is the other kind:
 * a sum handed over once and taken back across several periods, so a worker who
 * borrows a week's wage does not go home with nothing the week after.
 *
 * What is stored is the schedule, not a running balance — an amount, a number
 * of weeks, and the week it starts. Every week's collection follows from those
 * three, which is what lets a period reopened next year still compute to the
 * payslip that went with it.
 */
class ValeAdvanceTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::firstOrCreate(
            ['username' => 'admin.vale'],
            ['name' => 'Admin', 'password' => Hash::make('secret123'), 'role' => User::ROLE_ADMIN, 'is_active' => true]
        );
    }

    private function worker(string $name = 'Juan Dela Cruz'): Employee
    {
        $labor = LaborType::firstOrCreate(
            ['name' => 'Mason'],
            ['daily_rate' => 800, 'ot_rate' => 125]
        );

        return Employee::create([
            'name'          => $name,
            'labor_type_id' => $labor->id,
            'rate_per_hour' => 100,
            'status'        => Employee::STATUS_ACTIVE,
        ]);
    }

    private function advance(array $overrides = [], array $employees = []): ValeAdvance
    {
        $adv = ValeAdvance::create(array_merge([
            'amount'        => 4000,
            'weeks'         => 4,
            'starts_on'     => '2026-09-07',   // a Monday
            'all_employees' => false,
        ], $overrides));

        if ($employees) {
            $adv->employees()->sync($employees);
        }

        return $adv;
    }

    // ── The schedule ─────────────────────────────────────────────────────────

    public function test_the_amount_is_split_evenly_across_the_weeks(): void
    {
        $adv = $this->advance(['amount' => 4000, 'weeks' => 4]);

        foreach (range(0, 3) as $i) {
            $this->assertSame(1000.0, $adv->instalment($i));
        }
    }

    /** A division that does not come out even leaves nothing owed. */
    public function test_the_last_instalment_carries_the_remainder(): void
    {
        $adv = $this->advance(['amount' => 1000, 'weeks' => 3]);

        $this->assertSame(333.33, $adv->instalment(0));
        $this->assertSame(333.33, $adv->instalment(1));
        $this->assertSame(333.34, $adv->instalment(2), 'the last one squares the total');

        $this->assertSame(1000.0, round(array_sum([
            $adv->instalment(0), $adv->instalment(1), $adv->instalment(2),
        ]), 2));
    }

    public function test_nothing_is_due_before_it_starts_or_after_it_is_paid(): void
    {
        $adv = $this->advance(['starts_on' => '2026-09-07', 'weeks' => 2]);

        $this->assertSame(0.0, $adv->dueForWeekOpening('2026-08-31', Carbon::MONDAY), 'the week before');
        $this->assertSame(2000.0, $adv->dueForWeekOpening('2026-09-07', Carbon::MONDAY), 'the first week');
        $this->assertSame(2000.0, $adv->dueForWeekOpening('2026-09-14', Carbon::MONDAY), 'the second');
        $this->assertSame(0.0, $adv->dueForWeekOpening('2026-09-21', Carbon::MONDAY), 'and then it is paid off');
    }

    /** Any date inside the starting period will do — the week is resolved. */
    public function test_the_start_resolves_to_its_own_week(): void
    {
        $adv = $this->advance(['starts_on' => '2026-09-10']);   // a Thursday

        $this->assertSame(1000.0, $adv->dueForWeekOpening('2026-09-07', Carbon::MONDAY),
            'the Monday of the week the start falls in');
    }

    /** The schedule follows the office week, not a fixed Monday. */
    public function test_the_schedule_follows_where_the_week_begins(): void
    {
        $adv = $this->advance(['starts_on' => '2026-09-10', 'weeks' => 1]);

        $this->assertSame(4000.0, $adv->dueForWeekOpening('2026-09-06', Carbon::SUNDAY),
            'a week beginning Sunday opens on the 6th');
    }

    // ── What payroll collects ────────────────────────────────────────────────

    /** One worker, one week, computed the way Payroll Records computes it. */
    private function weekFor(Employee $emp, string $monday): array
    {
        foreach (range(0, 4) as $d) {
            Attendance::create([
                'employee_id' => $emp->id,
                'date'        => Carbon::parse($monday)->addDays($d)->toDateString(),
                'session'     => 'whole',
                'time_in'     => '08:00:00',
                'time_out'    => '16:00:00',
            ]);
        }

        $system = SystemSetting::current();

        $records = Attendance::with('employee.laborType')->get();

        // groupByWeek is reached directly: computeForRange loads holidays with
        // MySQL's YEAR(), which SQLite has no answer for.
        $m = new ReflectionMethod(PayrollService::class, 'groupByWeek');
        $m->setAccessible(true);

        $weeks = $m->invoke(app(PayrollService::class), $records, [
            'rateTimeline'   => PayrollRate::timeline(),
            'holidayTypeMap' => [],
            'restDayEnabled' => true,
            'restDayOn'      => $system->restDayOn(),
            'day'            => $system,
            'shifts'         => [],
            'bonusGrants'    => [],
            'valeAdvances'   => ValeAdvance::upTo('2099-12-31'),
        ]);

        return $weeks[0]['details'][0];
    }

    public function test_an_instalment_is_deducted_from_the_week(): void
    {
        $emp = $this->worker();
        $this->advance(['amount' => 4000, 'weeks' => 4, 'starts_on' => '2026-09-07'], [$emp->id]);

        $row = $this->weekFor($emp, '2026-09-07');

        $this->assertSame(1000.0, $row['vale_advance']);
        $this->assertSame(1000.0, $row['vale'], 'and it shows in the period vale');
    }

    public function test_the_instalment_comes_off_the_net(): void
    {
        $emp = $this->worker();

        $without = $this->weekFor($emp, '2026-09-07');

        Attendance::query()->delete();
        $emp2 = $this->worker('Pedro Santos');
        $this->advance(['amount' => 4000, 'weeks' => 4, 'starts_on' => '2026-09-07'], [$emp2->id]);

        $with = $this->weekFor($emp2, '2026-09-07');

        $this->assertEqualsWithDelta($without['net'] - 1000, $with['net'], 0.01);
    }

    public function test_a_worker_the_advance_does_not_name_pays_nothing(): void
    {
        $emp   = $this->worker();
        $other = $this->worker('Maria Reyes');

        $this->advance(['amount' => 4000, 'weeks' => 4, 'starts_on' => '2026-09-07'], [$other->id]);

        $this->assertSame(0.0, $this->weekFor($emp, '2026-09-07')['vale_advance']);
    }

    /** Everybody means everybody, including whoever is hired next. */
    public function test_an_advance_for_everybody_reaches_a_worker_it_never_named(): void
    {
        $emp = $this->worker();
        $this->advance(['amount' => 4000, 'weeks' => 4, 'starts_on' => '2026-09-07', 'all_employees' => true]);

        $this->assertSame(1000.0, $this->weekFor($emp, '2026-09-07')['vale_advance']);
    }

    /**
     * The ceiling limits what one period may collect, and the instalment
     * answers to it. What it does not take is still owed.
     */
    public function test_the_ceiling_limits_the_instalment(): void
    {
        PayrollRate::create(array_merge(PayrollRate::DEFAULTS, [
            'effective_from'       => '2026-01-01',
            'vale_ceiling_percent' => 10,
            'sss_rate'             => 0,
            'philhealth_rate'      => 0,
            'pagibig_rate'         => 0,
            'created_by'           => 'test',
        ]));

        $emp = $this->worker();
        $this->advance(['amount' => 40000, 'weeks' => 4, 'starts_on' => '2026-09-07'], [$emp->id]);

        $row = $this->weekFor($emp, '2026-09-07');

        $this->assertSame(10000.0, $row['vale_advance_due'], 'the schedule still says ten thousand');
        $this->assertLessThan(10000.0, $row['vale_advance'], 'but the period cannot give it');
        // A tenth of what is left AFTER the statutory deductions, which is
        // the base the ceiling is written against — withholding tax included,
        // even with the contribution rates set to nothing.
        $this->assertEqualsWithDelta(
            round(($row['gross'] - $row['autoDeductions']) * 0.10, 2),
            $row['vale_advance'],
            0.01
        );
    }

    // ── Recording one ────────────────────────────────────────────────────────

    public function test_an_advance_is_recorded_for_the_people_chosen(): void
    {
        $a = $this->worker('A');
        $b = $this->worker('B');
        $this->worker('C');

        $this->actingAs($this->admin())
             ->post(route('vale-advances.store'), [
                 'amount'    => 4000,
                 'weeks'     => 4,
                 'starts_on' => '2026-09-07',
                 'employees' => [$a->id, $b->id],
                 'note'      => 'Hospital bill',
             ])
             ->assertRedirect(route('settings.index', ['tab' => 'bonus']));

        $adv = ValeAdvance::firstOrFail();

        $this->assertSame(4000.0, $adv->amount);
        $this->assertSame(4, $adv->weeks);
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $adv->employees->pluck('id')->all());
    }

    /** An advance naming nobody would collect from no one. */
    public function test_an_advance_naming_nobody_is_refused(): void
    {
        $this->actingAs($this->admin())
             ->post(route('vale-advances.store'), [
                 'amount'    => 4000,
                 'weeks'     => 4,
                 'starts_on' => '2026-09-07',
             ])
             ->assertSessionHasErrors('employees');

        $this->assertSame(0, ValeAdvance::count());
    }

    public function test_zero_weeks_is_refused(): void
    {
        $emp = $this->worker();

        $this->actingAs($this->admin())
             ->post(route('vale-advances.store'), [
                 'amount'    => 4000,
                 'weeks'     => 0,
                 'starts_on' => '2026-09-07',
                 'employees' => [$emp->id],
             ])
             ->assertSessionHasErrors('weeks');
    }

    public function test_an_advance_can_be_removed(): void
    {
        $emp = $this->worker();
        $adv = $this->advance([], [$emp->id]);

        $this->actingAs($this->admin())
             ->delete(route('vale-advances.destroy', $adv))
             ->assertRedirect();

        $this->assertSame(0, ValeAdvance::count());
    }

    public function test_a_guest_cannot_record_one(): void
    {
        $emp = $this->worker();

        $this->post(route('vale-advances.store'), [
            'amount'    => 4000,
            'weeks'     => 4,
            'starts_on' => '2026-09-07',
            'employees' => [$emp->id],
        ])->assertRedirect(route('login'));

        $this->assertSame(0, ValeAdvance::count());
    }

    // ── On screen ────────────────────────────────────────────────────────────

    public function test_the_settings_page_lists_the_advance(): void
    {
        $emp = $this->worker('Nakikita');
        $this->advance(['amount' => 4000, 'weeks' => 4, 'note' => 'Hospital bill'], [$emp->id]);

        $this->actingAs($this->admin())
             ->get(route('settings.index', ['tab' => 'bonus']))
             ->assertOk()
             ->assertSee('Hospital bill')
             ->assertSee('Nakikita')
             ->assertSee('adv_weeks');
    }
}
