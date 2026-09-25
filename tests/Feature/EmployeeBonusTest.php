<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Bonus;
use App\Models\Employee;
use App\Models\LaborType;
use App\Models\LeaveRequest;
use App\Models\PayrollRate;
use App\Models\Shift;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\PayrollService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A bonus given from a worker's own row, for the pay period running now.
 *
 * It started on the Employee Directory's row menu, in place of Set Vale and
 * Copy ID (Michael: "remove set vale and copy id, instead make it add bonus
 * for that period"). When the directory was retired it moved to Register &
 * Manage, as a gift button on each active worker's row. The record it
 * writes is the one Payroll Settings has always made — a grant naming one
 * worker on one date — so payroll pays it as it pays any other: onto the net
 * of the week that date falls in, untaxed.
 */
class EmployeeBonusTest extends TestCase
{
    use RefreshDatabase;

    /** Week 38: Monday the 14th to Sunday the 20th. */
    private const WEEK = ['2026-09-14', '2026-09-20'];

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Thursday of week 38, in the afternoon.
        Carbon::setTestNow(Carbon::parse('2026-09-17 14:00:00', 'Asia/Manila'));
        SystemSetting::current()->forceFill(['schedule_rules_from' => '2026-09-01'])->save();

        PayrollRate::create(array_merge(PayrollRate::DEFAULTS, [
            'effective_from' => '2026-01-01', 'created_by' => 'test',
        ]));

        $this->admin = User::create([
            'name' => 'Admin', 'username' => 'admin.bonus', 'password' => 'secret123',
            'role' => User::ROLE_ADMIN, 'is_admin' => true, 'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** ₱800 a day, eight to five, on the days given. */
    private function worker(array $days = [], string $name = 'Lawrence Bernas'): Employee
    {
        $e = Employee::create([
            'name'            => $name,
            'status'          => Employee::STATUS_ACTIVE,
            'employment_type' => Employee::EMPLOYMENT_DAILY,
            'labor_type_id'   => LaborType::create(['name' => 'Mason ' . $name, 'daily_rate' => 800, 'ot_rate' => 125])->id,
            'shift_id'        => Shift::where('crosses_midnight', false)->firstOrFail()->id,
            'rate_per_hour'   => 100,
            'fingerprint_id'  => (string) random_int(1000, 9999),
        ]);

        foreach ($days as $d) {
            Attendance::create([
                'employee_id' => $e->id, 'shift_id' => $e->shift_id, 'date' => $d, 'session' => 'AM',
                'time_in' => "{$d} 08:00:00", 'time_out' => "{$d} 17:00:00",
            ]);
        }

        return $e;
    }

    private function give(Employee $e, $amount = 500, ?string $note = null)
    {
        return $this->actingAs($this->admin)
            ->from(route('employees.register'))
            ->post(route('employees.bonus.store', $e), ['amount' => $amount, 'note' => $note]);
    }

    /** One worker's figures for a range, as Payroll Records computes them. */
    private function totals(Employee $e, string $from, string $to): array
    {
        return collect(app(PayrollService::class)->computeForRange($from, $to)['employees'])
            ->firstWhere('employee_id', $e->id)['totals'] ?? [];
    }

    // ── The menu ─────────────────────────────────────────────────────────

    /** Add bonus is on each active row of Register & Manage; Set Vale and Copy ID stay gone. */
    public function test_each_active_row_offers_add_bonus(): void
    {
        $this->worker();

        $html = $this->actingAs($this->admin)->get(route('employees.register'))->assertOk()->getContent();

        $this->assertStringContainsString('class="rmx-icon-btn rmx-gift js-add-bonus"', $html);
        $this->assertStringContainsString('id="empBonusModal"', $html);
        $this->assertStringContainsString('Add bonus', $html);
        $this->assertStringContainsString('Remove this worker?', $html, 'Remove is still offered');
        // The dialog is built on the first click, after Bootstrap has loaded.
        $this->assertStringContainsString('if (!modal && window.bootstrap) modal = new bootstrap.Modal(modalEl);', $html);

        $this->assertStringNotContainsString('Set Vale', $html);
        $this->assertStringNotContainsString('Copy ID', $html);
        $this->assertStringNotContainsString('js-set-vale', $html);
        $this->assertStringNotContainsString('js-copy-id', $html);
    }

    /** Giving money is for whoever may already give it in Payroll Settings. */
    public function test_a_staff_account_is_offered_no_bonus_and_cannot_give_one(): void
    {
        $e     = $this->worker();
        $staff = User::create([
            'name' => 'Staff', 'username' => 'staff.bonus', 'password' => 'secret123',
            'role' => User::ROLE_STAFF, 'is_active' => true,
        ]);

        // The button and the dialog, not the words: the page's own stylesheet
        // and script mention them whoever is reading.
        $html = $this->actingAs($staff)->get(route('employees.register'))->assertOk()->getContent();

        $this->assertStringNotContainsString('js-add-bonus"', $html);
        $this->assertStringNotContainsString('id="empBonusModal"', $html);

        $this->actingAs($staff)->post(route('employees.bonus.store', $e), ['amount' => 500])->assertForbidden();
        $this->assertSame(0, Bonus::count());
    }

    // ── Giving one ───────────────────────────────────────────────────────

    /** It is written as a grant for this worker, dated today, and said so. */
    public function test_a_bonus_is_given_for_the_period_running_now(): void
    {
        $e = $this->worker(['2026-09-15']);

        $this->give($e, 500, 'Finished the slab early')
            ->assertRedirect(route('employees.register'))
            ->assertSessionHas('success', "₱500.00 bonus added to Lawrence Bernas's pay for Sep 14 – Sep 20, 2026.");

        $bonus = Bonus::sole();

        $this->assertEqualsWithDelta(500.0, (float) $bonus->amount, 0.001);
        $this->assertSame('2026-09-17', $bonus->effective_on->toDateString(), 'dated today, inside this period');
        $this->assertFalse((bool) $bonus->all_employees);
        $this->assertSame('Finished the slab early', $bonus->note);
        $this->assertSame([$e->id], $bonus->employees->pluck('id')->all());

        $this->assertSame(
            "Bonus of ₱500.00 for Lawrence Bernas, pay period Sep 14 – Sep 20, 2026 — Finished the slab early.",
            AuditLog::where('module', 'Payroll')->where('action', 'created')->sole()->description
        );
    }

    /** A bonus of nothing is refused, and a pending registration cannot be given one. */
    public function test_what_cannot_be_given(): void
    {
        $e = $this->worker(['2026-09-15']);

        $this->give($e, 0)->assertSessionHasErrors(['amount' => 'A bonus of nothing is not a bonus.']);
        $this->give($e, -50)->assertSessionHasErrors('amount');

        $pending = $this->worker([], 'Newly Detected');
        $pending->forceFill(['status' => Employee::STATUS_PENDING])->save();

        $this->give($pending)->assertNotFound();
        $this->assertSame(0, Bonus::count());
    }

    // ── Payroll pays it ──────────────────────────────────────────────────

    /** It reaches the week's net, and changes nothing else on the payroll. */
    public function test_payroll_pays_it_in_the_period_it_was_given_for(): void
    {
        $e      = $this->worker(['2026-09-15', '2026-09-16']);
        $before = $this->totals($e, ...self::WEEK);

        $this->give($e, 500)->assertSessionHasNoErrors();

        $after = $this->totals($e, ...self::WEEK);

        $this->assertEqualsWithDelta(500.0, $after['bonus'], 0.001);
        $this->assertEqualsWithDelta($before['net'] + 500, $after['net'], 0.011);
        $this->assertEqualsWithDelta($before['gross'], $after['gross'], 0.001, 'a bonus is not wages');
        $this->assertEqualsWithDelta($before['totalDeductions'], $after['totalDeductions'], 0.001, 'and nothing is withheld on it');

        // Not in the week before or the week after.
        $this->assertEqualsWithDelta(0.0, (float) ($this->totals($e, '2026-09-07', '2026-09-13')['bonus'] ?? 0), 0.001);
    }

    /**
     * Given on a day nobody has clocked in on yet, it is still paid.
     *
     * The grants were loaded by the days somebody worked, so a bonus dated
     * after the last of them — this morning, before anyone clocked in — was
     * never loaded, and the week paid nothing.
     */
    public function test_a_bonus_given_after_the_last_day_worked_is_still_paid(): void
    {
        $e = $this->worker(['2026-09-14']);   // Monday only; today is Thursday

        $this->give($e, 500)->assertSessionHasNoErrors();

        $this->assertEqualsWithDelta(500.0, $this->totals($e, ...self::WEEK)['bonus'], 0.001);
    }

    /** A worker on leave all week gets theirs too. */
    public function test_a_worker_on_leave_all_week_is_paid_the_bonus(): void
    {
        $e = $this->worker();

        LeaveRequest::create([
            'employee_id' => $e->id, 'leave_type' => 'sick', 'starts_on' => '2026-09-16', 'ends_on' => '2026-09-18',
            'days' => 3, 'is_paid' => true, 'filed_by' => $this->admin->id,
        ]);

        $before = $this->totals($e, ...self::WEEK);
        $this->give($e, 500)->assertSessionHasNoErrors();
        $after = $this->totals($e, ...self::WEEK);

        $this->assertEqualsWithDelta(500.0, $after['bonus'], 0.001);
        $this->assertEqualsWithDelta($before['net'] + 500, $after['net'], 0.011);
    }

    /** Payroll Processing and the payslip show it. */
    public function test_it_shows_on_payroll_processing_and_the_payslip(): void
    {
        $e = $this->worker(['2026-09-15', '2026-09-16']);
        $this->give($e, 500);

        $sel = $this->actingAs($this->admin)->get(route('payroll-processing.index', [
            'period' => '2026-09-14_2026-09-20', 'view' => 'workflow', 'employee' => $e->id,
        ]))->assertOk()->viewData('sel');

        $this->assertEqualsWithDelta(500.0, $sel['bonus'], 0.001);

        $slip = $this->actingAs($this->admin)
            ->get(route('payslip.batch', ['from' => '2026-09-14', 'to' => '2026-09-20', 'employee' => $e->id]))
            ->assertOk()->viewData('slips')->first();

        $this->assertEqualsWithDelta(500.0, $slip['bonus'], 0.001);
    }

    // ── Taking one back ──────────────────────────────────────────────────

    /** While the period runs it can be taken back, and payroll drops it. */
    public function test_a_bonus_can_be_taken_back_while_the_period_runs(): void
    {
        $e = $this->worker(['2026-09-15']);
        $this->give($e, 500);

        $bonus = Bonus::sole();

        $this->actingAs($this->admin)
            ->from(route('employees.register'))
            ->delete(route('employees.bonus.destroy', [$e, $bonus]))
            ->assertRedirect(route('employees.register'))
            ->assertSessionHas('success', '₱500.00 bonus for Lawrence Bernas was removed.');

        $this->assertSame(0, Bonus::count());
        $this->assertEqualsWithDelta(0.0, $this->totals($e, ...self::WEEK)['bonus'], 0.001);

        // The page offers it back while it is there, and not once it is gone.
        $this->give($e, 700);
        $this->actingAs($this->admin)->get(route('employees.register'))->assertOk()->assertSee('Given this period');
    }

    /** A period that has closed keeps what it paid, and so does somebody else's grant. */
    public function test_a_closed_period_or_another_grant_is_not_touched(): void
    {
        $e     = $this->worker(['2026-09-08', '2026-09-15']);
        $other = $this->worker(['2026-09-15'], 'Aldrin Sapugay');

        $lastWeek = Bonus::create([
            'amount' => 400, 'effective_on' => '2026-09-10', 'all_employees' => false, 'created_by' => 'test',
        ]);
        $lastWeek->employees()->sync([$e->id]);

        $shared = Bonus::create([
            'amount' => 300, 'effective_on' => '2026-09-17', 'all_employees' => false, 'created_by' => 'test',
        ]);
        $shared->employees()->sync([$e->id, $other->id]);

        $everyone = Bonus::create([
            'amount' => 200, 'effective_on' => '2026-09-17', 'all_employees' => true, 'created_by' => 'test',
        ]);

        foreach ([$lastWeek, $shared, $everyone] as $bonus) {
            $this->actingAs($this->admin)->delete(route('employees.bonus.destroy', [$e, $bonus]))->assertNotFound();
        }

        $this->assertSame(3, Bonus::count());
        $this->assertEqualsWithDelta(400.0, $this->totals($e, '2026-09-07', '2026-09-13')['bonus'], 0.001,
            'the closed week keeps what it paid');
    }
}
