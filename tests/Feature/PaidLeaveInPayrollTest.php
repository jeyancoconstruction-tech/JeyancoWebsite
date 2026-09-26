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

    /**
     * One itemised deduction for the range. They are carried on the weeks
     * rather than the totals, which is how every screen reads them.
     */
    private function deduction(Employee $e, string $key, string $from, string $to): float
    {
        return round((float) collect($this->figures($e, $from, $to)['periods'] ?? [])->sum($key), 2);
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

        // It reaches the net less what is contributed and withheld on it,
        // exactly as a day worked would.
        $onLeave = round($t['totalDeductions'] - $before['totalDeductions'], 2);

        $this->assertGreaterThan(0, $onLeave, 'income is contributed on');
        $this->assertEqualsWithDelta($before['net'] + 1600 - $onLeave, $t['net'], 0.02);
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

    /** A cancelled leave pays nothing, and restoring it pays it again. */
    public function test_cancelled_leave_pays_nothing(): void
    {
        $e     = $this->worker(['2026-09-10']);
        $leave = $this->leave($e, '2026-09-08', '2026-09-09');

        $leave->forceFill(['status' => 'cancelled'])->save();
        $this->assertEqualsWithDelta(0.0, $this->totals($e, ...self::WEEK)['leavePay'], 0.001);

        $leave->forceFill(['status' => 'approved'])->save();
        $this->assertEqualsWithDelta(1600.0, $this->totals($e, ...self::WEEK)['leavePay'], 0.001);
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

    /**
     * A day off is paid when it comes round, not the moment it is approved.
     *
     * A week still running counts the days worked so far; leave signed off to
     * the Friday must not already be paid in full on the Wednesday, or the
     * week's pay reads as far bigger than it is.
     */
    public function test_leave_still_to_come_is_not_paid_yet(): void
    {
        // Wednesday of week 38, with leave signed off Wednesday to Friday.
        Carbon::setTestNow(Carbon::parse('2026-09-16 09:00:00', 'Asia/Manila'));

        $this->worker(['2026-09-14'], 'Somebody Else');

        $off = $this->worker([], 'Lawrence On Leave');
        $this->leave($off, '2026-09-16', '2026-09-18');

        $t = $this->totals($off, '2026-09-14', '2026-09-20');

        $this->assertEqualsWithDelta(1.0, $t['leaveDays'], 0.001, 'only the day that has come round');
        $this->assertEqualsWithDelta(800.0, $t['leavePay'], 0.001);

        // By the Friday the whole of it has been reached.
        Carbon::setTestNow(Carbon::parse('2026-09-18 18:00:00', 'Asia/Manila'));

        $this->assertEqualsWithDelta(2400.0,
            $this->totals($off, '2026-09-14', '2026-09-20')['leavePay'], 0.001);
    }

    /**
     * Leave wholly in a week still ahead pays nothing yet.
     *
     * Note the crew mate: a week exists in the figures because somebody
     * clocked in during it. A week nobody worked at all is not a week payroll
     * has anything to say about.
     */
    public function test_leave_in_a_later_week_is_not_paid_in_this_one(): void
    {
        $this->worker(['2026-09-10', '2026-09-15'], 'Crew Mate');

        $e = $this->worker(['2026-09-10']);
        $this->leave($e, '2026-09-20', '2026-09-23');   // starts on the last day of week 38

        $this->assertEqualsWithDelta(0.0, $this->totals($e, ...self::WEEK)['leavePay'], 0.001,
            'the leave is not in this week at all');

        // Nothing worked and nothing reached in week 38, so there is no row
        // for them yet — it appears when the 20th comes round.
        $this->assertSame([], $this->totals($e, '2026-09-14', '2026-09-20'));

        Carbon::setTestNow(Carbon::parse('2026-09-20 18:00:00', 'Asia/Manila'));

        $this->assertEqualsWithDelta(800.0,
            $this->totals($e, '2026-09-14', '2026-09-20')['leavePay'], 0.001,
            'and on the day itself, one day of it is paid');
    }

    /** Only the days inside the period are paid, not the whole filing. */
    public function test_only_the_days_inside_the_period_are_paid(): void
    {
        // Looking back at both weeks once they have gone by.
        Carbon::setTestNow(Carbon::parse('2026-09-30 09:00:00', 'Asia/Manila'));

        $e = $this->worker(['2026-09-10', '2026-09-17']);

        // Filed Friday the 11th to Tuesday the 15th: the 11th, 12th and 13th
        // fall in week 37, the 14th and 15th in week 38.
        $this->leave($e, '2026-09-11', '2026-09-15');

        $this->assertEqualsWithDelta(2400.0, $this->totals($e, ...self::WEEK)['leavePay'], 0.011,
            'three days of the filing are in week 37');
        $this->assertEqualsWithDelta(1600.0, $this->totals($e, '2026-09-14', '2026-09-20')['leavePay'], 0.011,
            'and the other two in week 38');
    }

    // ── Contributions on it ──────────────────────────────────────────────

    /**
     * A paid day off is income, so it is contributed and withheld on exactly
     * as a day worked is. It must not reach the worker whole.
     */
    public function test_leave_carries_its_own_contributions(): void
    {
        $e = $this->worker(['2026-09-10']);

        $before = [
            'sss'   => $this->deduction($e, 'sssDeduction', ...self::WEEK),
            'phil'  => $this->deduction($e, 'philhealthDeduction', ...self::WEEK),
            'pag'   => $this->deduction($e, 'pagibigDeduction', ...self::WEEK),
            'total' => $this->totals($e, ...self::WEEK)['totalDeductions'],
            'net'   => $this->totals($e, ...self::WEEK)['net'],
        ];

        $this->leave($e, '2026-09-08', '2026-09-09');
        $t = $this->totals($e, ...self::WEEK);

        $rates = PayrollRate::effectiveOn('2026-09-13')->toRates();

        // Two days at ₱800, each contributed on at the day's own rate.
        $this->assertEqualsWithDelta($before['sss'] + 800 * $rates['sss_rate'] / 100 * 2,
            $this->deduction($e, 'sssDeduction', ...self::WEEK), 0.02, 'SSS');
        $this->assertEqualsWithDelta($before['phil'] + 800 * $rates['philhealth_rate'] / 100 * 2,
            $this->deduction($e, 'philhealthDeduction', ...self::WEEK), 0.02, 'PhilHealth');
        $this->assertEqualsWithDelta($before['pag'] + 800 * $rates['pagibig_rate'] / 100 * 2,
            $this->deduction($e, 'pagibigDeduction', ...self::WEEK), 0.02, 'Pag-IBIG');

        $this->assertGreaterThan($before['total'], $t['totalDeductions'],
            'the deductions grow with the leave');

        // The net is the leave less what came off it, not the leave whole.
        $onLeave = round($t['net'] - $before['net'], 2);
        $this->assertLessThan(1600.0, $onLeave, 'the leave does not reach the worker whole');
        $this->assertEqualsWithDelta(1600 - ($t['totalDeductions'] - $before['total']), $onLeave, 0.02);
    }

    /** Unpaid leave is not income, so nothing comes off it. */
    public function test_unpaid_leave_carries_no_contributions(): void
    {
        $e = $this->worker(['2026-09-10']);

        $before = $this->totals($e, ...self::WEEK);
        $this->leave($e, '2026-09-08', '2026-09-09', paid: false);
        $t = $this->totals($e, ...self::WEEK);

        $this->assertEqualsWithDelta($before['totalDeductions'], $t['totalDeductions'], 0.001);
    }

    /** A week that is nothing but leave still remits on what it paid. */
    public function test_a_leave_only_week_still_contributes(): void
    {
        $this->worker(['2026-09-10'], 'Somebody Else');

        $off = $this->worker([], 'Lawrence On Leave');
        $this->leave($off, '2026-09-07', '2026-09-09');

        $t = $this->totals($off, ...self::WEEK);

        $this->assertEqualsWithDelta(2400.0, $t['gross'], 0.001);
        $this->assertGreaterThan(0, $this->deduction($off, 'sssDeduction', ...self::WEEK), 'SSS is still due');
        $this->assertGreaterThan(0, $this->deduction($off, 'philhealthDeduction', ...self::WEEK));
        $this->assertGreaterThan(0, $this->deduction($off, 'pagibigDeduction', ...self::WEEK));
        $this->assertEqualsWithDelta($t['gross'] - $t['totalDeductions'], $t['net'], 0.02,
            'and the net is what is left after them');
        $this->assertLessThan(2400.0, $t['net']);
    }

    /** Contract workers are outside the statutory scheme, on leave as at work. */
    public function test_a_contract_worker_on_leave_contributes_nothing(): void
    {
        $e = $this->worker(['2026-09-10'], 'On Contract');
        $e->forceFill(['employment_type' => Employee::EMPLOYMENT_CONTRACTUAL])->save();

        $before = $this->totals($e, ...self::WEEK);
        $this->leave($e, '2026-09-08', '2026-09-09');
        $t = $this->totals($e, ...self::WEEK);

        $this->assertEqualsWithDelta($before['totalDeductions'], $t['totalDeductions'], 0.001);
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
        $this->assertEqualsWithDelta(2400.0 - $t['totalDeductions'], $t['net'], 0.02,
            'less the contributions due on it');
        $this->assertSame(0, $t['workdays'], 'and no day is counted as worked');
        $this->assertSame(0, $t['minutes']);
    }

    /**
     * The weekly table reads the rate and the shift off a day worked, and a
     * week that is all leave has none — so the week carries them itself,
     * rather than showing a worker paid ₱2,400 at ₱0.00 a day.
     */
    public function test_a_leave_only_week_still_says_what_a_day_is_worth(): void
    {
        $this->worker(['2026-09-10'], 'Somebody Else');

        $off = $this->worker([], 'Lawrence On Leave');
        $this->leave($off, '2026-09-07', '2026-09-09');

        $week = collect($this->figures($off, ...self::WEEK)['periods'])->first();

        $this->assertEqualsWithDelta(800.0, $week['dailyRate'], 0.001);

        $this->actingAs($this->admin)
            ->get('/payroll-records?mode=weekly&week=2026-W37')
            ->assertOk()
            ->assertSee('Lawrence On Leave')
            ->assertSee('2,400.00');
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

    // ── A day off is a record for that day ───────────────────────────────

    /** One worker's row on one date of the day-by-day breakdown. */
    private function dayRow(Employee $e, string $on, string $from, string $to): ?array
    {
        $day = collect(app(PayrollService::class)->computeForRange($from, $to)['days'])
            ->firstWhere('date', $on);

        return collect($day['details'] ?? [])->firstWhere('employee_id', $e->id);
    }

    /**
     * The day a leave falls on has a payroll record on it.
     *
     * Reported from the office: a worker with an approved paid leave for
     * today was simply not on today's payroll. The day view is built from
     * attendance, and leave writes no attendance row — nothing ever will —
     * so the one screen that answers "what is this person owed for today"
     * had nothing to show, and a paid day off read as an absence.
     */
    public function test_a_day_of_leave_is_a_record_for_that_day(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-17 14:00:00', 'Asia/Manila'));

        $this->worker(['2026-09-17'], 'Crew Mate');

        $off = $this->worker([], 'Lawrence Bernas');
        $this->leave($off, '2026-09-17', '2026-09-17');

        $row = $this->dayRow($off, '2026-09-17', '2026-09-17', '2026-09-17');

        $this->assertNotNull($row, 'the day off is on the day');
        $this->assertTrue($row['leave']);
        $this->assertEqualsWithDelta(800.0, $row['leavePay'], 0.001);
        $this->assertEqualsWithDelta(800.0, $row['gross'], 0.001, 'a paid day off is wages');
        $this->assertEqualsWithDelta(0.0, $row['minutes'], 0.001, 'and no hours at all');
        $this->assertGreaterThan(0, $row['totalDeductions'], 'income is contributed on');
        $this->assertEqualsWithDelta(800.0, $row['dailyRate'], 0.001);
    }

    /**
     * Nobody worked that day, and the leave is still paid.
     *
     * The weeks came off attendance, so a range nobody clocked in on had no
     * weeks in it at all — and everyone on leave through it vanished from
     * payroll rather than being paid for it.
     */
    public function test_leave_is_paid_on_a_day_nobody_worked(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-17 14:00:00', 'Asia/Manila'));

        $off = $this->worker([], 'Lawrence Bernas');
        $this->leave($off, '2026-09-17', '2026-09-17');

        $t = $this->totals($off, '2026-09-17', '2026-09-17');

        $this->assertEqualsWithDelta(1.0, $t['leaveDays'], 0.001);
        $this->assertEqualsWithDelta(800.0, $t['leavePay'], 0.001);
        $this->assertNotNull($this->dayRow($off, '2026-09-17', '2026-09-17', '2026-09-17'));

        // And over the whole week, still nobody else having worked.
        $this->assertEqualsWithDelta(800.0,
            $this->totals($off, '2026-09-14', '2026-09-20')['leavePay'], 0.001);
    }

    /**
     * One day's view pays one day of a week-long leave.
     *
     * Leave was bounded by the week it falls in and by nothing else, so a
     * single date asked about answered with every day of the leave the week
     * had reached — four days of pay against one day.
     */
    public function test_a_days_view_credits_only_that_day(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-17 14:00:00', 'Asia/Manila'));

        $off = $this->worker([], 'Lawrence Bernas');
        $this->leave($off, '2026-09-14', '2026-09-18');

        $day = $this->totals($off, '2026-09-17', '2026-09-17');

        $this->assertEqualsWithDelta(1.0, $day['leaveDays'], 0.001);
        $this->assertEqualsWithDelta(800.0, $day['leavePay'], 0.001);

        // The week it sits in still credits every day of it that has come
        // round — Monday to Thursday, with the Friday still ahead.
        $week = $this->totals($off, '2026-09-14', '2026-09-20');

        $this->assertEqualsWithDelta(4.0, $week['leaveDays'], 0.001);
        $this->assertEqualsWithDelta(3200.0, $week['leavePay'], 0.001);
    }

    /** Payroll Records shows the day, and says it is leave rather than hours. */
    public function test_payroll_records_shows_the_day_off(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-17 14:00:00', 'Asia/Manila'));

        $off = $this->worker([], 'Lawrence Bernas');
        $this->leave($off, '2026-09-17', '2026-09-17');

        $this->actingAs($this->admin)
            ->get(route('payroll-records', ['mode' => 'daily', 'date' => '2026-09-17']))
            ->assertOk()
            ->assertSee('Lawrence Bernas')
            ->assertSee('Sick Leave');
    }

    /**
     * The printed payslip gives it a line of its own.
     *
     * It had none at all, and "Regular" quietly absorbed it — the slip
     * claimed the worker had earned it by the hour on days they were not
     * there.
     */
    public function test_the_printed_payslip_itemises_the_leave(): void
    {
        $e = $this->worker(['2026-09-10']);
        $this->leave($e, '2026-09-08', '2026-09-09');

        $slips = $this->actingAs($this->admin)
            ->get(route('payslip.batch', ['from' => '2026-09-07', 'to' => '2026-09-13', 'employee' => $e->id]))
            ->assertOk()
            ->assertSee('Paid Leave')
            ->viewData('slips');

        $s = $slips->first();

        $this->assertEqualsWithDelta(1600.0, $s['leavePay'], 0.001);
        $this->assertEqualsWithDelta(800.0, $s['regular'], 0.011, 'one day worked, and only that');
        $this->assertEqualsWithDelta($s['gross'],
            $s['regular'] + $s['overtime'] + $s['holidayPay'] + $s['restDayPay']
            + $s['nightDiffPay'] + $s['leavePay'], 0.011, 'the earnings are the gross');
    }

    /**
     * Completed is only what the row shows. A leave that is over is still
     * paid for the week it fell in — it has to be, or last week's payroll
     * would shrink the morning after someone's leave ended.
     */
    public function test_a_completed_leave_is_still_paid(): void
    {
        $e     = $this->worker(['2026-09-10']);
        $leave = $this->leave($e, '2026-09-08', '2026-09-09');   // it is the 12th: over

        $this->assertSame('completed', $leave->display_status);
        $this->assertEqualsWithDelta(1600.0, $this->totals($e, ...self::WEEK)['leavePay'], 0.001);
    }
}
