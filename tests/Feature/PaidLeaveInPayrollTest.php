<?php

namespace Tests\Feature;

use App\Models\Attendance;
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
 * Approved paid leave is wages, and has to be paid.
 *
 * It was credited only by a payroll run, and the run bar came off Payroll
 * Processing, so an approved paid leave reached nothing the office actually
 * looks at: not Payroll Records, not the payslip, not the net. A worker on
 * leave for a whole week did not appear in the week at all.
 *
 * A paid day is credited at the rate the week priced a worked day at, which
 * the engine has already raised to the wage order's floor — so a day off is
 * never worth less than a day on, and never less than the minimum wage.
 */
class PaidLeaveInPayrollTest extends TestCase
{
    use RefreshDatabase;

    /** Week 37: Monday the 7th to Sunday the 13th. */
    private const WEEK = ['2026-09-07', '2026-09-13'];

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-12 21:00:00', 'Asia/Manila'));
        SystemSetting::current()->forceFill(['schedule_rules_from' => '2026-09-01'])->save();

        PayrollRate::create(array_merge(PayrollRate::DEFAULTS, [
            'effective_from' => '2026-01-01', 'created_by' => 'test',
        ]));

        $this->admin = User::create([
            'name' => 'Admin', 'username' => 'admin.paidleave', 'password' => 'secret123',
            'role' => User::ROLE_ADMIN, 'is_admin' => true, 'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** ₱800 a day, eight to five. */
    private function worker(array $days = ['2026-09-10'], string $name = 'Day Crew', float $daily = 800): Employee
    {
        $e = Employee::create([
            'name'            => $name,
            'status'          => Employee::STATUS_ACTIVE,
            'employment_type' => Employee::EMPLOYMENT_DAILY,
            'labor_type_id'   => LaborType::create(['name' => 'Mason ' . $name, 'daily_rate' => $daily, 'ot_rate' => 125])->id,
            'shift_id'        => Shift::where('crosses_midnight', false)->firstOrFail()->id,
            'rate_per_hour'   => $daily / 8,
        ]);

        foreach ($days as $d) {
            Attendance::create([
                'employee_id' => $e->id, 'shift_id' => $e->shift_id, 'date' => $d, 'session' => 'AM',
                'time_in' => "{$d} 08:00:00", 'time_out' => "{$d} 17:00:00",
            ]);
        }

        return $e;
    }

    private function leave(Employee $e, string $from, string $to, bool $paid = true, string $type = 'sick'): LeaveRequest
    {
        return LeaveRequest::create([
            'employee_id' => $e->id,
            'leave_type'  => $type,
            'starts_on'   => $from,
            'ends_on'     => $to,
            'days'        => Carbon::parse($from)->diffInDays(Carbon::parse($to)) + 1,
            'is_paid'     => $paid,
            'status'      => 'approved',
            'approved_by' => $this->admin->id,
            'approved_at' => now(),
        ]);
    }

    private function figures(Employee $e, string $from, string $to): array
    {
        return collect(app(PayrollService::class)->computeForRange($from, $to)['employees'])
            ->firstWhere('employee_id', $e->id) ?? [];
    }

    private function totals(Employee $e, string $from, string $to): array
    {
        return $this->figures($e, $from, $to)['totals'] ?? [];
    }

    // ── Paid at the day rate ─────────────────────────────────────────────

    /** Two paid days off are two days' pay, on top of the day worked. */
    public function test_paid_leave_is_credited_at_the_daily_rate(): void
    {
        $e = $this->worker(['2026-09-10']);

        $before = $this->totals($e, ...self::WEEK);
        $this->leave($e, '2026-09-08', '2026-09-09');

        $t = $this->totals($e, ...self::WEEK);

        $this->assertEqualsWithDelta(2.0, $t['leaveDays'], 0.001);
        $this->assertEqualsWithDelta(1600.0, $t['leavePay'], 0.001, 'two days at ₱800');
        $this->assertEqualsWithDelta($before['gross'] + 1600, $t['gross'], 0.011, 'leave is wages, so it is in the gross');
        $this->assertEqualsWithDelta($before['net'] + 1600, $t['net'], 0.011, 'and it reaches the net');
    }

    /** Leave filed unpaid is counted, and pays nothing. */
    public function test_unpaid_leave_pays_nothing(): void
    {
        $e = $this->worker(['2026-09-10']);

        $before = $this->totals($e, ...self::WEEK);
        $this->leave($e, '2026-09-08', '2026-09-09', paid: false);

        $t = $this->totals($e, ...self::WEEK);

        $this->assertEqualsWithDelta(2.0, $t['leaveDays'], 0.001, 'the days off are still counted');
        $this->assertEqualsWithDelta(0.0, $t['leavePay'], 0.001);
        $this->assertEqualsWithDelta($before['net'], $t['net'], 0.011);
    }

    /** Leave still waiting on a decision pays nothing. */
    public function test_leave_that_is_not_approved_pays_nothing(): void
    {
        $e = $this->worker(['2026-09-10']);
        $this->leave($e, '2026-09-08', '2026-09-09')->forceFill(['status' => 'pending'])->save();

        $this->assertEqualsWithDelta(0.0, $this->totals($e, ...self::WEEK)['leavePay'], 0.001);
    }

    /**
     * A day off is never worth less than the minimum. A labour type left
     * behind a wage order is paid at the floor for a worked day, and a leave
     * day follows it.
     */
    public function test_a_leave_day_is_never_below_the_wage_floor(): void
    {
        PayrollRate::create(array_merge(PayrollRate::DEFAULTS, [
            'effective_from' => '2026-09-01', 'created_by' => 'test', 'daily_rate' => 645,
        ]));

        // The labour type is still on ₱500, under the order.
        $e = $this->worker(['2026-09-10'], 'Behind The Order', 500);
        $this->leave($e, '2026-09-08', '2026-09-08');

        $t = $this->totals($e, ...self::WEEK);

        $this->assertEqualsWithDelta(645.0, $t['leavePay'], 0.001, 'the day off is paid at the floor');
    }

    /** Only the days inside the period are paid, not the whole filing. */
    public function test_only_the_days_inside_the_period_are_paid(): void
    {
        $e = $this->worker(['2026-09-10', '2026-09-17']);

        // Filed Friday the 11th to Tuesday the 15th: the 11th, 12th and 13th
        // fall in week 37, the 14th and 15th in week 38.
        $this->leave($e, '2026-09-11', '2026-09-15');

        $this->assertEqualsWithDelta(2400.0, $this->totals($e, ...self::WEEK)['leavePay'], 0.011,
            'three days of the filing are in week 37');
        $this->assertEqualsWithDelta(1600.0, $this->totals($e, '2026-09-14', '2026-09-20')['leavePay'], 0.011,
            'and the other two in week 38');
    }

    // ── A week that is nothing but leave ─────────────────────────────────

    /**
     * Signed off for the whole week, so there is no attendance to group. The
     * worker still has to appear, and still has to be paid.
     */
    public function test_a_worker_on_leave_all_week_is_still_on_the_payroll(): void
    {
        // Somebody else works, so the week exists at all.
        $this->worker(['2026-09-10'], 'Somebody Else');

        $off = $this->worker([], 'Lawrence On Leave');
        $this->leave($off, '2026-09-07', '2026-09-09');

        $t = $this->totals($off, ...self::WEEK);

        $this->assertNotSame([], $t, 'the worker must appear in the week they were signed off for');
        $this->assertEqualsWithDelta(3.0, $t['leaveDays'], 0.001);
        $this->assertEqualsWithDelta(2400.0, $t['leavePay'], 0.001, 'three days at ₱800');
        $this->assertEqualsWithDelta(2400.0, $t['gross'], 0.001);
        $this->assertEqualsWithDelta(2400.0, $t['net'], 0.001);
        $this->assertSame(0, $t['workdays'], 'and no day is counted as worked');
        $this->assertSame(0, $t['minutes']);
    }

    /** A pending registration is not on the payroll, leave or no leave. */
    public function test_a_pending_registration_on_leave_stays_off_the_payroll(): void
    {
        $this->worker(['2026-09-10'], 'Somebody Else');

        $pending = Employee::create([
            'name' => 'Waiting For A Finger', 'status' => Employee::STATUS_PENDING,
            'employment_type' => Employee::EMPLOYMENT_DAILY, 'rate_per_hour' => 100,
        ]);
        $this->leave($pending, '2026-09-07', '2026-09-09');

        $this->assertSame([], $this->totals($pending, ...self::WEEK));
    }

    // ── Where it has to show ─────────────────────────────────────────────

    /** Payroll Records carries it, and keeps it out of regular pay. */
    public function test_it_shows_on_payroll_records_as_leave(): void
    {
        $e = $this->worker(['2026-09-10']);
        $this->leave($e, '2026-09-08', '2026-09-09');

        $row = collect($this->actingAs($this->admin)
            ->get('/payroll-records?mode=weekly&week=2026-W37')
            ->assertOk()
            ->viewData('employees'))->firstWhere('employee_id', $e->id);

        $t = $row['totals'];

        $this->assertEqualsWithDelta(1600.0, $t['leavePay'], 0.001);

        // Regular pay is the gross less the premiums and less the leave — the
        // receipt's own arithmetic, which must not fold a day off into a day
        // worked.
        $regular = round($t['gross'] - $t['overtime'] - $t['holidayPay']
                       - $t['restDayPay'] - $t['nightDiffPay'] - $t['leavePay'], 2);

        $this->assertEqualsWithDelta(800.0, $regular, 0.011, 'one day worked, at ₱800');
    }

    /** Payroll Processing itemises it, and does not call it regular pay. */
    public function test_it_shows_on_payroll_processing(): void
    {
        $e = $this->worker(['2026-09-10']);
        $this->leave($e, '2026-09-08', '2026-09-09');

        $page = $this->actingAs($this->admin)->get(route('payroll-processing.index', [
            'period' => '2026-09-07_2026-09-13', 'view' => 'workflow', 'employee' => $e->id,
        ]))->assertOk();

        $sel = $page->viewData('sel');

        $this->assertEqualsWithDelta(1600.0, $sel['leave'], 0.001);
        $this->assertEqualsWithDelta(2.0, $sel['leave_days'], 0.001);
        $this->assertEqualsWithDelta(800.0, $sel['basic'], 0.011, 'the day off is not regular pay');

        $page->assertSee('Paid leave');

        // The earnings still add up to the gross with the leave line in them.
        $earn = $page->viewData('lines')['earn'];
        $this->assertEqualsWithDelta($sel['gross'], array_sum(array_column($earn, 'amount')), 0.011);
    }

    /** And the payslip shows it, and adds up. */
    public function test_it_shows_on_the_payslip(): void
    {
        $e = $this->worker(['2026-09-10']);
        $this->leave($e, '2026-09-08', '2026-09-09');

        $slip = $this->actingAs($this->admin)->get(route('payroll-processing.index', [
            'period' => '2026-09-07_2026-09-13', 'view' => 'payslip', 'employee' => $e->id,
        ]))->assertOk()->assertSee('Paid leave')->viewData('slip');

        $this->assertEqualsWithDelta($slip['gross'], array_sum(array_column($slip['earn'], 1)), 0.011,
            'the earnings are still the gross');

        $this->actingAs($this->admin)
            ->get(route('payslip.batch', ['from' => '2026-09-07', 'to' => '2026-09-13', 'employee' => $e->id]))
            ->assertOk();
    }
}
