<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\LaborType;
use App\Models\Loan;
use App\Models\LoanDeduction;
use App\Models\PayrollRate;
use App\Models\Shift;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\PayrollService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A cash advance is collected by payroll on the instalment its application
 * asked for.
 *
 * Before this, the application on Leave & Advances and the deduction payroll
 * took were not connected at all: the only collector ran at payroll-run
 * finalisation, which has had no button since the run bar came off Payroll
 * Processing, so an approved advance sat at 0% paid for ever and never reached
 * a payslip.
 *
 * What a week collects is worked out from three things that do not move — the
 * sum issued, the instalment, and the payments recorded — so a week recomputed
 * later comes out at the figure that was on the payslip.
 */
class CashAdvanceCollectionTest extends TestCase
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
            'name' => 'Admin', 'username' => 'admin.cashadvance', 'password' => 'secret123',
            'role' => User::ROLE_ADMIN, 'is_admin' => true, 'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** A worker who puts in one ordinary day in each of the weeks given. */
    private function worker(array $days = ['2026-09-10'], string $name = 'Day Crew'): Employee
    {
        $e = Employee::create([
            'name'            => $name,
            'status'          => Employee::STATUS_ACTIVE,
            'employment_type' => Employee::EMPLOYMENT_DAILY,
            'labor_type_id'   => LaborType::create(['name' => 'Mason ' . $name, 'daily_rate' => 800, 'ot_rate' => 125])->id,
            'shift_id'        => Shift::where('crosses_midnight', false)->firstOrFail()->id,
            'rate_per_hour'   => 100,
        ]);

        foreach ($days as $d) {
            Attendance::create([
                'employee_id' => $e->id, 'shift_id' => $e->shift_id, 'date' => $d, 'session' => 'AM',
                'time_in' => "{$d} 08:00:00", 'time_out' => "{$d} 17:00:00",
            ]);
        }

        return $e;
    }

    private function advance(Employee $e, float $principal, float $instalment, string $startsOn = '2026-09-07'): Loan
    {
        return Loan::create([
            'employee_id' => $e->id,
            'type'        => Loan::ADVANCE,
            'principal'   => $principal,
            'balance'     => $principal,
            'installment' => $instalment,
            'schedule'    => 'per_payroll',
            'issued_on'   => $startsOn,
            'starts_on'   => $startsOn,
            'status'      => 'active',
            'created_by'  => $this->admin->id,
        ]);
    }

    /** One worker's figures for a range, as Payroll Records computes them. */
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
     * What the range collected against advances. It is itemised on the weeks
     * rather than the totals, which is how the payslip and Payroll Processing
     * read it too.
     */
    private function advanceTaken(Employee $e, string $from, string $to): float
    {
        return round((float) collect($this->figures($e, $from, $to)['periods'] ?? [])->sum('vale_advance'), 2);
    }

    /** The whole vale line — the day vale and the advance instalment together. */
    private function valeTaken(Employee $e, string $from, string $to): float
    {
        return round((float) collect($this->figures($e, $from, $to)['periods'] ?? [])->sum('vale'), 2);
    }

    // ── What payroll takes ───────────────────────────────────────────────

    /** The instalment the application asked for is the instalment payroll takes. */
    public function test_payroll_deducts_the_instalment_the_application_asked_for(): void
    {
        $e = $this->worker();
        $this->advance($e, 5000, 750);

        $before = $this->advanceTaken($e, ...self::WEEK);
        $this->advance($e, 1000, 400, '2030-01-01');   // a second, not yet started

        $this->assertEqualsWithDelta(750.0, $before, 0.001, 'the week takes the instalment');
        $this->assertEqualsWithDelta(750.0, $this->valeTaken($e, ...self::WEEK), 0.001,
            'and it lands on the vale line, which is what the payslip itemises');
        $this->assertEqualsWithDelta($before, $this->advanceTaken($e, ...self::WEEK), 0.001,
            'an advance that has not started yet takes nothing');
    }

    /** It reaches the deductions and the net, not just a field of its own. */
    public function test_the_deduction_reaches_total_deductions_and_the_net(): void
    {
        $e = $this->worker();

        $clean = $this->totals($e, ...self::WEEK);
        $this->advance($e, 5000, 750);
        $with = $this->totals($e, ...self::WEEK);

        $this->assertEqualsWithDelta($clean['totalDeductions'] + 750, $with['totalDeductions'], 0.011);
        $this->assertEqualsWithDelta($clean['net'] - 750, $with['net'], 0.011);
        $this->assertEqualsWithDelta($clean['gross'], $with['gross'], 0.001, 'an advance is not wages');
    }

    /** Nothing before the payroll it was set to start from. */
    public function test_collection_waits_for_the_starting_payroll(): void
    {
        $e = $this->worker(['2026-09-03', '2026-09-10']);
        $this->advance($e, 5000, 750, '2026-09-07');

        $this->assertEqualsWithDelta(0.0,
            $this->advanceTaken($e, '2026-08-31', '2026-09-06'), 0.001, 'the week before');
        $this->assertEqualsWithDelta(750.0,
            $this->advanceTaken($e, ...self::WEEK), 0.001, 'the week it starts');
    }

    /**
     * The last instalment is whatever is left, so the schedule collects the
     * sum issued exactly — never a peso more.
     */
    public function test_the_last_instalment_is_the_remainder(): void
    {
        $e = $this->worker(['2026-09-07', '2026-09-14', '2026-09-21', '2026-09-28']);
        $advance = $this->advance($e, 500, 200);

        $weeks = [
            ['2026-09-07', '2026-09-13', 200.0],
            ['2026-09-14', '2026-09-20', 200.0],
            ['2026-09-21', '2026-09-27', 100.0],   // the remainder
            ['2026-09-28', '2026-10-04', 0.0],     // settled
        ];

        $collected = 0.0;

        foreach ($weeks as [$from, $to, $expected]) {
            $took = $this->advanceTaken($e, $from, $to);
            $this->assertEqualsWithDelta($expected, $took, 0.001, "week of {$from}");
            $collected += $took;
        }

        $this->assertEqualsWithDelta(500.0, $collected, 0.001, 'exactly what was handed over');
        $this->assertEqualsWithDelta(0.0,
            $advance->fresh()->outstandingOn('2026-10-04', Carbon::MONDAY), 0.001,
            'and nothing is left once those weeks have gone by');
    }

    /** Two advances for one worker are both collected, on their own instalments. */
    public function test_two_advances_are_collected_side_by_side(): void
    {
        $e = $this->worker();
        $this->advance($e, 5000, 750);
        $this->advance($e, 500, 100);

        $this->assertEqualsWithDelta(850.0, $this->advanceTaken($e, ...self::WEEK), 0.001);
    }

    /** One worker's advance is not collected from another's pay. */
    public function test_an_advance_is_collected_from_its_own_worker(): void
    {
        $a = $this->worker(['2026-09-10'], 'Alpha');
        $b = $this->worker(['2026-09-10'], 'Bravo');

        $this->advance($a, 5000, 750);

        $this->assertEqualsWithDelta(750.0, $this->advanceTaken($a, ...self::WEEK), 0.001);
        $this->assertEqualsWithDelta(0.0, $this->advanceTaken($b, ...self::WEEK), 0.001);
    }

    /** A cancelled advance is not collected at all. */
    public function test_a_cancelled_advance_collects_nothing(): void
    {
        $e = $this->worker();
        $this->advance($e, 5000, 750)->forceFill(['status' => 'cancelled'])->save();

        $this->assertEqualsWithDelta(0.0, $this->advanceTaken($e, ...self::WEEK), 0.001);
    }

    // ── The balance, and what settles it ─────────────────────────────────

    /** The balance follows what payroll has taken, without anybody pressing anything. */
    public function test_the_balance_follows_the_payrolls_that_have_passed(): void
    {
        $e = $this->worker();
        $advance = $this->advance($e, 500, 200);

        // Saturday of the first week: one instalment has been taken.
        $this->assertEqualsWithDelta(300.0, $advance->outstanding, 0.001);
        $this->assertSame(40, $advance->progress);

        Carbon::setTestNow(Carbon::parse('2026-09-26 12:00:00', 'Asia/Manila'));
        $advance->refresh();

        $this->assertEqualsWithDelta(0.0, $advance->outstanding, 0.001, 'three weeks settles ₱500 at ₱200');
        $this->assertTrue($advance->settled);
    }

    /** Nothing left, and the row says Fully Paid — saved, so the filter finds it. */
    public function test_it_marks_itself_fully_paid_once_nothing_is_left(): void
    {
        $e = $this->worker();
        $advance = $this->advance($e, 500, 200);

        Carbon::setTestNow(Carbon::parse('2026-09-26 12:00:00', 'Asia/Manila'));
        $advance->refresh();

        $this->assertSame('Fully Paid', $advance->status_label);
        $this->assertTrue($advance->syncSettlement());

        $advance->refresh();
        $this->assertSame('paid', $advance->status);
        $this->assertEqualsWithDelta(0.0, (float) $advance->balance, 0.001);
    }

    /** A payment at the office comes off the balance and off what payroll takes next. */
    public function test_a_payment_reduces_the_balance_and_the_next_deduction(): void
    {
        $e = $this->worker(['2026-09-10', '2026-09-17']);
        $advance = $this->advance($e, 500, 200);

        $this->actingAs($this->admin)
            ->post(route('loans.payment', $advance), [
                'amount' => 250, 'deducted_on' => '2026-09-11', 'note' => 'Cash at the office',
            ])
            ->assertSessionHas('success');

        $advance->refresh();

        // ₱250 handed in, then week 37's ₱200 instalment: ₱50 is left, so the
        // week after takes that and no more.
        $this->assertEqualsWithDelta(50.0, $advance->outstanding, 0.001);
        $this->assertEqualsWithDelta(200.0, $this->advanceTaken($e, ...self::WEEK), 0.001,
            'the instalment for a week is still the instalment');
        $this->assertEqualsWithDelta(50.0, $this->advanceTaken($e, '2026-09-14', '2026-09-20'), 0.001);
    }

    /** A payment that settles it marks it Fully Paid there and then. */
    public function test_a_payment_that_clears_the_balance_settles_the_advance(): void
    {
        $e = $this->worker();
        $advance = $this->advance($e, 500, 200);

        $this->actingAs($this->admin)
            ->post(route('loans.payment', $advance), ['amount' => 300, 'deducted_on' => '2026-09-12'])
            ->assertSessionHas('success');

        $advance->refresh();

        $this->assertSame('paid', $advance->status);
        $this->assertEqualsWithDelta(0.0, $advance->outstanding, 0.001);
        $this->assertEqualsWithDelta(0.0,
            $this->advanceTaken($e, '2026-09-14', '2026-09-20'), 0.001, 'and payroll stops');
    }

    /** More than is left cannot be paid, and a settled advance takes nothing more. */
    public function test_a_payment_cannot_exceed_what_is_left(): void
    {
        $e = $this->worker();
        $advance = $this->advance($e, 500, 200);   // ₱300 left after week one

        $this->actingAs($this->admin)
            ->post(route('loans.payment', $advance), ['amount' => 400, 'deducted_on' => '2026-09-12'])
            ->assertSessionHasErrors('amount');

        $this->assertSame(0, LoanDeduction::count());

        $this->actingAs($this->admin)
            ->post(route('loans.payment', $advance), ['amount' => 300, 'deducted_on' => '2026-09-12']);

        $this->actingAs($this->admin)
            ->post(route('loans.payment', $advance->fresh()), ['amount' => 1, 'deducted_on' => '2026-09-12'])
            ->assertSessionHas('error');

        $this->assertSame(1, LoanDeduction::count());
    }

    // ── The history ──────────────────────────────────────────────────────

    /**
     * Every instalment and every payment, in order, each with what was left
     * after it — and the total never more than was handed over.
     */
    public function test_the_history_lists_every_deduction_and_payment_with_the_balance(): void
    {
        $e = $this->worker();
        $advance = $this->advance($e, 500, 200);

        LoanDeduction::create([
            'loan_id' => $advance->id, 'amount' => 120,
            'deducted_on' => '2026-09-16', 'note' => 'Cash at the office',
        ]);

        $lines = $advance->fresh()->walk('2026-09-30', Carbon::MONDAY)['lines'];

        $this->assertSame(
            [
                ['2026-09-13', 'payroll', 200.0, 300.0],   // week 37's instalment
                ['2026-09-16', 'payment', 120.0, 180.0],   // handed in during week 38
                ['2026-09-20', 'payroll', 180.0,   0.0],   // week 38 takes the rest and finishes it
            ],
            array_map(fn ($l) => [$l['date'], $l['type'], $l['amount'], $l['balance']], $lines)
        );

        $this->assertEqualsWithDelta(500.0, array_sum(array_column($lines, 'amount')), 0.001);
    }

    /**
     * A collection an old payroll run posted is that week's instalment
     * already taken, not an extra on top of it. Runs used to settle advances
     * at finalisation; payroll takes them from the schedule now, and the two
     * must not both charge the same week.
     */
    public function test_a_collection_an_old_run_posted_is_not_charged_twice(): void
    {
        $e = $this->worker();
        $advance = $this->advance($e, 500, 200);

        LoanDeduction::create([
            'loan_id' => $advance->id, 'payroll_run_id' => 9001, 'amount' => 200,
            'deducted_on' => '2026-09-13', 'note' => 'Collected by payroll run PR-0001',
        ]);

        $lines = $advance->fresh()->walk('2026-09-13', Carbon::MONDAY)['lines'];

        $this->assertCount(1, $lines, 'the run posted week 37; the schedule does not post it again');
        $this->assertEqualsWithDelta(200.0, $lines[0]['amount'], 0.001);
        $this->assertEqualsWithDelta(300.0, $advance->fresh()->outstanding, 0.001);
    }

    /** Before the first payroll there is nothing to show, and the page says so. */
    public function test_an_advance_that_has_not_started_has_an_empty_history(): void
    {
        $e = $this->worker();
        $advance = $this->advance($e, 500, 200, '2026-10-05');

        $this->assertSame([], $advance->walk('2026-09-30', Carbon::MONDAY)['lines']);
        $this->assertEqualsWithDelta(500.0, $advance->outstanding, 0.001);
    }

    // ── The page ─────────────────────────────────────────────────────────

    /** The row offers both actions, and shows the balance payroll agrees with. */
    public function test_the_page_offers_the_menu_and_the_worked_out_balance(): void
    {
        $e = $this->worker();
        $this->advance($e, 500, 200);

        $page = $this->actingAs($this->admin)
            ->get(route('leave.index', ['tab' => 'advances']))
            ->assertOk();

        $page->assertSee('Add payment')
             ->assertSee('Payment history')
             ->assertSee('₱300.00')            // what is actually left, not ₱500
             ->assertSee('40% paid');

        $this->assertEqualsWithDelta(300.0, $page->viewData('summary')['outstanding'], 0.001);
        $this->assertEqualsWithDelta(200.0, $page->viewData('summary')['collected'], 0.001);
        $this->assertSame(1, $page->viewData('summary')['active']);
    }

    /**
     * Correcting the instalment re-works the schedule from the first payroll
     * — which is the point: the two advances on file were entered at ₱5 a
     * payroll, which would have taken a thousand of them.
     */
    public function test_the_instalment_can_be_corrected_and_the_schedule_follows(): void
    {
        $e = $this->worker();
        $advance = $this->advance($e, 5000, 5);

        $this->assertEqualsWithDelta(5.0, $this->advanceTaken($e, ...self::WEEK), 0.001);

        $this->actingAs($this->admin)
            ->put(route('loans.update', $advance), ['installment' => 500])
            ->assertSessionHas('success');

        $this->assertEqualsWithDelta(500.0, (float) $advance->fresh()->installment, 0.001);
        $this->assertEqualsWithDelta(500.0, $this->advanceTaken($e, ...self::WEEK), 0.001,
            'the corrected figure is what payroll takes');
        $this->assertEqualsWithDelta(4500.0, $advance->fresh()->outstanding, 0.001);
    }

    /** An instalment bigger than the advance would collect more than was issued. */
    public function test_the_instalment_cannot_exceed_the_advance(): void
    {
        $e = $this->worker();
        $advance = $this->advance($e, 500, 200);

        $this->actingAs($this->admin)
            ->put(route('loans.update', $advance), ['installment' => 900])
            ->assertSessionHasErrors('installment');

        $this->assertEqualsWithDelta(200.0, (float) $advance->fresh()->installment, 0.001);
    }

    /** The row offers the correction, and a settled one does not. */
    public function test_the_menu_offers_editing_the_instalment(): void
    {
        $e = $this->worker();
        $this->advance($e, 500, 200);

        $this->actingAs($this->admin)
            ->get(route('leave.index', ['tab' => 'advances']))
            ->assertOk()
            ->assertSee('Edit instalment');
    }

    /** A settled advance keeps its history but is not offered a payment. */
    public function test_a_settled_advance_shows_history_but_no_payment(): void
    {
        $e = $this->worker();
        $this->advance($e, 500, 200);

        Carbon::setTestNow(Carbon::parse('2026-09-26 12:00:00', 'Asia/Manila'));

        $this->actingAs($this->admin)
            ->get(route('leave.index', ['tab' => 'advances']))
            ->assertOk()
            ->assertSee('Payment history')
            ->assertSee('Fully Paid')
            ->assertDontSee('Add payment')
            ->assertDontSee('Edit instalment');
    }
}
