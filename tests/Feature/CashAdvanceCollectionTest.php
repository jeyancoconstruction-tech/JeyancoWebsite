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

    /**
     * Collection starts with the payroll the application named, even when the
     * date falls mid-week and the worker's days that week are all before it.
     *
     * The advances were loaded by the last date anybody worked, so a week
     * whose attendance stopped on the Tuesday never saw an advance starting
     * on the Wednesday — payroll took nothing while the balance on Leave &
     * Advances counted the week as collected.
     */
    public function test_it_starts_on_the_named_payroll_even_when_the_week_ends_early(): void
    {
        // Week 38 is Mon 14th to Sun 20th; this worker is in on the Monday
        // and Tuesday only, and collection is set to start on the Wednesday.
        Carbon::setTestNow(Carbon::parse('2026-09-18 12:00:00', 'Asia/Manila'));

        $e = $this->worker(['2026-09-14', '2026-09-15']);
        $advance = $this->advance($e, 5000, 750, '2026-09-16');

        $this->assertEqualsWithDelta(750.0, $this->advanceTaken($e, '2026-09-14', '2026-09-20'), 0.001,
            'the payroll for the week the advance starts in must collect it');

        $this->assertEqualsWithDelta(750.0, $advance->fresh()->paid_amount, 0.001,
            'and the balance must agree with what payroll took');
    }

    /** The payroll before the starting date still collects nothing. */
    public function test_the_payroll_before_the_starting_one_collects_nothing(): void
    {
        $e = $this->worker(['2026-09-10', '2026-09-16']);
        $this->advance($e, 5000, 750, '2026-09-16');

        $this->assertEqualsWithDelta(0.0, $this->advanceTaken($e, '2026-09-07', '2026-09-13'), 0.001);
        $this->assertEqualsWithDelta(750.0, $this->advanceTaken($e, '2026-09-14', '2026-09-20'), 0.001);
    }

    /** With no starting date given, it collects from the payroll it was issued in. */
    public function test_with_no_starting_date_it_starts_from_the_payroll_it_was_issued_in(): void
    {
        $e = $this->worker(['2026-09-10']);

        Loan::create([
            'employee_id' => $e->id, 'type' => Loan::ADVANCE,
            'principal' => 5000, 'balance' => 5000, 'installment' => 750,
            'schedule' => 'per_payroll', 'issued_on' => '2026-09-09', 'starts_on' => null,
            'status' => 'active', 'created_by' => $this->admin->id,
        ]);

        $this->assertEqualsWithDelta(750.0, $this->advanceTaken($e, ...self::WEEK), 0.001);
    }

    // ── Where it has to show ─────────────────────────────────────────────

    /** Payroll Records itemises it on the vale line, with the instalment named. */
    public function test_it_shows_on_payroll_records(): void
    {
        $e = $this->worker(['2026-09-10']);
        $this->advance($e, 5000, 750);

        $row = collect($this->actingAs($this->admin)
            ->get('/payroll-records?mode=weekly&week=2026-W37')
            ->assertOk()
            ->viewData('employees'))->firstWhere('employee_id', $e->id);

        $this->assertEqualsWithDelta(750.0, collect($row['periods'])->sum('vale_advance'), 0.001);
        $this->assertEqualsWithDelta(750.0, collect($row['periods'])->sum('vale'), 0.001);
    }

    /** And on the payslip, in the deductions and in the net. */
    public function test_it_shows_on_the_payslip(): void
    {
        $e = $this->worker(['2026-09-10']);

        $clean = $this->totals($e, ...self::WEEK)['net'];
        $this->advance($e, 5000, 750);

        $this->actingAs($this->admin)
            ->get(route('payslip.batch', ['from' => '2026-09-07', 'to' => '2026-09-13', 'employee' => $e->id]))
            ->assertOk()
            ->assertSee('750.00');

        $this->assertEqualsWithDelta($clean - 750, $this->totals($e, ...self::WEEK)['net'], 0.011,
            'and it comes off the net the worker is handed');
    }

    /** One payroll period takes one instalment, however often the page is opened. */
    public function test_a_period_collects_the_instalment_once(): void
    {
        $e = $this->worker(['2026-09-08', '2026-09-09', '2026-09-10']);
        $this->advance($e, 5000, 750);

        // Three days in the week, and the week still collects one instalment.
        $this->assertEqualsWithDelta(750.0, $this->advanceTaken($e, ...self::WEEK), 0.001);

        // Reading it again does not collect again.
        $this->assertEqualsWithDelta(750.0, $this->advanceTaken($e, ...self::WEEK), 0.001);
        $this->assertEqualsWithDelta(750.0, $this->advanceTaken($e, ...self::WEEK), 0.001);
        $this->assertSame(0, LoanDeduction::count(), 'reading payroll writes nothing');
    }

    // ── The balance, and what settles it ─────────────────────────────────

    /** The balance follows what payroll has taken, without anybody pressing anything. */
    public function test_the_balance_follows_the_payrolls_that_have_passed(): void
    {
        // A day worked in each of the three weeks, so each has a payroll to
        // take its instalment out of.
        $e = $this->worker(['2026-09-10', '2026-09-17', '2026-09-24']);
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
        $e = $this->worker(['2026-09-10', '2026-09-17', '2026-09-24']);
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
        $e = $this->worker(['2026-09-10', '2026-09-17']);
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
        $e = $this->worker(['2026-09-10', '2026-09-17', '2026-09-24']);
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

    // ── Payments made before collection starts ───────────────────────────

    /**
     * An advance set to start collecting at a later payroll, with ₱3,000
     * issued this week — the shape Aldrin Sapugay's was in.
     */
    private function startsLater(Employee $e): Loan
    {
        return Loan::create([
            'employee_id' => $e->id, 'type' => Loan::ADVANCE,
            'principal' => 3000, 'balance' => 3000, 'installment' => 500, 'schedule' => 'per_payroll',
            'issued_on' => '2026-09-08', 'starts_on' => '2026-09-21',
            'status' => 'active', 'created_by' => $this->admin->id,
        ]);
    }

    /**
     * A payment handed in before the first collecting payroll comes off the
     * balance straight away.
     *
     * Reported for Aldrin Sapugay: adding a payment "was not working". It was
     * saved — and then never reached. Payments before collection started were
     * moved forward into the first collecting week, and the walk stops at
     * today's; so the balance did not move, the history stayed empty, and the
     * page said "Payment recorded. ₱3,000.00 left" over a row that would sit
     * unread until the 21st.
     */
    public function test_a_payment_before_collection_starts_comes_off_the_balance_at_once(): void
    {
        $e       = $this->worker(['2026-09-10', '2026-09-15', '2026-09-22'], 'Aldrin Sapugay');
        $advance = $this->startsLater($e);

        $this->actingAs($this->admin)
            ->from(route('leave.index', ['tab' => 'advances']))
            ->post(route('loans.payment', $advance), [
                'amount' => 1000, 'deducted_on' => '2026-09-11', 'note' => 'OR #1042, cash at the office',
            ])
            ->assertSessionHas('success', 'Payment recorded. ₱2,000.00 left to collect.');

        $advance->refresh();

        $this->assertEqualsWithDelta(2000.0, $advance->outstanding, 0.001, 'the balance moves the moment it is paid');
        $this->assertEqualsWithDelta(2000.0, $advance->balance, 0.001, 'and so does the stored figure');
        $this->assertSame('active', $advance->status);

        // In the history, on the day it was handed in, with what it was.
        $lines = $advance->walk('2026-09-12', Carbon::MONDAY)['lines'];

        $this->assertSame(
            [['2026-09-11', 'payment', 1000.0, 2000.0, 'OR #1042, cash at the office']],
            array_map(fn ($l) => [$l['date'], $l['type'], $l['amount'], $l['balance'], $l['note']], $lines)
        );

        $this->actingAs($this->admin)->get(route('leave.index', ['tab' => 'advances']))
            ->assertOk()
            ->assertSee('OR #1042, cash at the office')
            ->assertSee('₱2,000.00');

        // Payroll: nothing before the payroll it was set to start on, then the
        // instalment against what is left.
        $this->assertEqualsWithDelta(0.0, $this->advanceTaken($e, ...self::WEEK), 0.001);
        $this->assertEqualsWithDelta(0.0, $this->advanceTaken($e, '2026-09-14', '2026-09-20'), 0.001);
        $this->assertEqualsWithDelta(500.0, $this->advanceTaken($e, '2026-09-21', '2026-09-27'), 0.001);

        Carbon::setTestNow(Carbon::parse('2026-09-27 12:00:00', 'Asia/Manila'));
        $this->assertEqualsWithDelta(1500.0, $advance->fresh()->outstanding, 0.001, 'one instalment on from the payment');
    }

    /** Paying the whole sum before collection starts settles it: Fully Paid, and payroll never takes a peso. */
    public function test_paying_it_all_before_collection_starts_marks_it_fully_paid(): void
    {
        $e       = $this->worker(['2026-09-10', '2026-09-22'], 'Aldrin Sapugay');
        $advance = $this->startsLater($e);

        $this->actingAs($this->admin)
            ->post(route('loans.payment', $advance), ['amount' => 3000, 'deducted_on' => '2026-09-12'])
            ->assertSessionHas('success', 'Payment recorded — Aldrin Sapugay\'s cash advance is now fully paid.');

        $advance->refresh();

        $this->assertSame('paid', $advance->status);
        $this->assertSame('Fully Paid', $advance->status_label);
        $this->assertEqualsWithDelta(0.0, $advance->outstanding, 0.001);
        $this->assertEqualsWithDelta(0.0, $this->advanceTaken($e, '2026-09-21', '2026-09-27'), 0.001,
            'payroll takes nothing from a settled advance');

        $this->actingAs($this->admin)->get(route('leave.index', ['tab' => 'advances']))
            ->assertOk()
            ->assertSee('Fully Paid')
            ->assertDontSee('Add payment');
    }

    // ── The payment itself ───────────────────────────────────────────────

    /**
     * A payment is dated between the day the advance was issued and today.
     * One dated ahead used to be saved and then ignored until its week came.
     */
    public function test_a_payment_cannot_be_dated_ahead_or_before_the_advance(): void
    {
        $e       = $this->worker();
        $advance = $this->advance($e, 3000, 500);   // issued the 7th; today is the 12th

        $this->actingAs($this->admin)
            ->post(route('loans.payment', $advance), ['amount' => 500, 'deducted_on' => '2026-09-15'])
            ->assertSessionHasErrors(['deducted_on' => 'The payment date cannot be in the future.']);

        $this->actingAs($this->admin)
            ->post(route('loans.payment', $advance), ['amount' => 500, 'deducted_on' => '2026-09-06'])
            ->assertSessionHasErrors('deducted_on');

        $this->assertSame(0, LoanDeduction::count());

        // Both ends are allowed.
        foreach (['2026-09-07', '2026-09-12'] as $on) {
            $this->actingAs($this->admin)
                ->post(route('loans.payment', $advance->fresh()), ['amount' => 100, 'deducted_on' => $on])
                ->assertSessionHasNoErrors();
        }

        $this->assertSame(2, LoanDeduction::count());
    }

    /**
     * The same payment sent twice in a moment — a double click, a refresh that
     * resubmits — is written once. The same amount paid again later is a
     * second payment, and is written.
     */
    public function test_the_same_payment_sent_twice_is_recorded_once(): void
    {
        $e       = $this->worker();
        $advance = $this->advance($e, 3000, 500);
        $pay     = fn () => $this->actingAs($this->admin)
            ->post(route('loans.payment', $advance->fresh()), ['amount' => 400, 'deducted_on' => '2026-09-12']);

        $pay()->assertSessionHas('success');
        $pay()->assertSessionHas('error', 'That payment was already recorded a moment ago — it was not added twice.');

        $this->assertSame(1, LoanDeduction::count());
        $this->assertEqualsWithDelta(2100.0, $advance->fresh()->outstanding, 0.001, 'taken off once');

        Carbon::setTestNow(Carbon::parse('2026-09-12 21:05:00', 'Asia/Manila'));

        $pay()->assertSessionHas('success');

        $this->assertSame(2, LoanDeduction::count());
        $this->assertEqualsWithDelta(1700.0, $advance->fresh()->outstanding, 0.001);
    }

    // ── A finalised run ──────────────────────────────────────────────────

    /**
     * A payroll run takes the instalment once, and sees payments.
     *
     * The engine's vale already carries the week's instalment. The run worked
     * it out again from the stored balance and added it on top, so every
     * finalised payslip charged the advance twice — ₱500 under "Vale" and
     * ₱500 more under "Cash Advance" — and never saw a payment made at the
     * office.
     */
    public function test_a_payroll_run_charges_the_instalment_once(): void
    {
        $e = $this->worker(['2026-09-08', '2026-09-09', '2026-09-10']);
        $this->advance($e, 3000, 500);

        $this->actingAs($this->admin)->post(route('payroll-processing.store'), [
            'period_start' => '2026-09-07', 'period_end' => '2026-09-13',
        ]);

        $item   = \App\Models\PayrollRun::sole()->items()->sole();
        $engine = $this->totals($e, ...self::WEEK);

        $this->assertEqualsWithDelta(500.0, (float) $item->advance_deduction, 0.001, 'the instalment, on its own line');
        $this->assertEqualsWithDelta(0.0, (float) $item->vale, 0.001, 'and not in the vale as well');
        $this->assertEqualsWithDelta($engine['totalDeductions'], (float) $item->total_deductions, 0.011);
        $this->assertEqualsWithDelta($engine['net'], (float) $item->net_pay, 0.011, 'the run pays what Payroll Records says');
    }

    /** A run for a week after the advance was paid off at the office takes nothing for it. */
    public function test_a_payroll_run_after_a_payment_in_full_takes_nothing(): void
    {
        $e       = $this->worker(['2026-09-10', '2026-09-22'], 'Aldrin Sapugay');
        $advance = $this->startsLater($e);

        $this->actingAs($this->admin)
            ->post(route('loans.payment', $advance), ['amount' => 3000, 'deducted_on' => '2026-09-12']);

        Carbon::setTestNow(Carbon::parse('2026-09-27 21:00:00', 'Asia/Manila'));

        $this->actingAs($this->admin)->post(route('payroll-processing.store'), [
            'period_start' => '2026-09-21', 'period_end' => '2026-09-27',
        ]);

        $item = \App\Models\PayrollRun::sole()->items()->where('employee_id', $e->id)->sole();

        $this->assertEqualsWithDelta(0.0, (float) $item->advance_deduction, 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $item->vale, 0.001);
        $this->assertEqualsWithDelta(3000.0, (float) LoanDeduction::sum('amount'), 0.001,
            'the one payment, and nothing collected on top of it');
    }

    // ── The ₱30,000 limit ────────────────────────────────────────────────

    /** Record a new advance through the form's own route. */
    private function issue(Employee $e, float $amount)
    {
        return $this->actingAs($this->admin)
            ->from(route('leave.index', ['tab' => 'advances']))
            ->post(route('loans.store'), [
                'employee_id' => $e->id, 'principal' => $amount, 'installment' => 1000,
                'schedule' => 'per_payroll', 'issued_on' => '2026-09-12',
            ]);
    }

    /**
     * Michael: "the limit amount we offer in employee for cash advance is only
     * 30,000."
     */
    public function test_one_advance_is_at_most_thirty_thousand(): void
    {
        $e = $this->worker([], 'Mark Adrian Gulbe De Leon');

        $this->issue($e, 30000.01)
            ->assertSessionHasErrors(['principal' => 'A cash advance cannot be more than ₱30,000.00.']);

        $this->assertSame(0, Loan::count());

        $this->issue($e, 30000)->assertSessionHasNoErrors();

        $this->assertEqualsWithDelta(30000.0, (float) Loan::sole()->principal, 0.001, 'the limit itself is allowed');
    }

    /**
     * The limit is on what a worker owes, not on one application. Someone
     * still paying back ₱20,000 can be advanced ₱10,000 more — and nobody
     * else's balance comes into it.
     */
    public function test_the_limit_counts_what_the_worker_still_owes(): void
    {
        $e = $this->worker([], 'Mark Adrian Gulbe De Leon');
        $this->advance($e, 20000, 2000, '2026-09-21');   // nothing collected yet

        $this->assertEqualsWithDelta(20000.0, Loan::owedBy($e->id), 0.001);
        $this->assertEqualsWithDelta(10000.0, Loan::roomFor($e->id), 0.001);

        $this->issue($e, 10000.01)->assertSessionHasErrors(['principal' =>
            'Cash advances are limited to ₱30,000.00 per employee. Mark Adrian Gulbe De Leon still owes ₱20,000.00, '
            . 'so at most ₱10,000.00 more can be advanced.']);

        $this->issue($e, 10000)->assertSessionHasNoErrors();
        $this->assertSame(2, Loan::where('employee_id', $e->id)->count());

        // Another worker starts from the whole ₱30,000.
        $this->issue($this->worker([], 'Aldrin Sapugay'), 30000)->assertSessionHasNoErrors();
    }

    /** At the limit, nothing more — until a payment makes room, and then only that much. */
    public function test_paying_down_makes_room_again(): void
    {
        $e = $this->worker([], 'Mark Adrian Gulbe De Leon');

        $owed = Loan::create([
            'employee_id' => $e->id, 'type' => Loan::ADVANCE, 'principal' => 30000, 'balance' => 30000,
            'installment' => 2000, 'schedule' => 'per_payroll', 'issued_on' => '2026-09-08', 'starts_on' => '2026-09-21',
            'status' => 'active', 'created_by' => $this->admin->id,
        ]);

        $this->issue($e, 1)->assertSessionHasErrors(['principal' =>
            'Cash advances are limited to ₱30,000.00 per employee. Mark Adrian Gulbe De Leon still owes ₱30,000.00, '
            . 'so nothing more can be advanced until it is paid down.']);

        $this->actingAs($this->admin)
            ->post(route('loans.payment', $owed), ['amount' => 5000, 'deducted_on' => '2026-09-12'])
            ->assertSessionHas('success');

        $this->assertEqualsWithDelta(5000.0, Loan::roomFor($e->id), 0.001);

        $this->issue($e, 5000.01)->assertSessionHasErrors('principal');
        $this->issue($e, 5000)->assertSessionHasNoErrors();
    }

    /** A cancelled advance is owed by nobody, and takes none of the limit. */
    public function test_a_cancelled_advance_takes_none_of_the_limit(): void
    {
        $e = $this->worker([], 'Mark Adrian Gulbe De Leon');
        $this->advance($e, 30000, 2000, '2026-09-21')->forceFill(['status' => 'cancelled'])->save();

        $this->assertEqualsWithDelta(0.0, Loan::owedBy($e->id), 0.001);
        $this->issue($e, 30000)->assertSessionHasNoErrors();
    }

    /**
     * The form meets the limit while it is being filled in: the Amount box is
     * capped at ₱30,000, and it knows what each worker already owes, so
     * choosing one can say how much is left for them.
     */
    public function test_the_form_knows_the_limit_and_what_each_worker_owes(): void
    {
        $owing = $this->worker([], 'Mark Adrian Gulbe De Leon');
        $clear = $this->worker([], 'Aldrin Sapugay');
        $this->advance($owing, 20000, 2000, '2026-09-21');

        $html = $this->actingAs($this->admin)
            ->get(route('leave.index', ['tab' => 'advances']))->assertOk()->getContent();

        $at    = strpos($html, 'id="ca_amt"');
        $field = substr($html, $at, strpos($html, '</' . 'span>', $at) - $at);

        $this->assertStringContainsString('max="30000"', $field);
        $this->assertStringContainsString('Up to ₱30,000.00 per employee, less what they still owe.', $field);

        preg_match('/data-owed="([^"]*)"/', $field, $m);
        $owed = json_decode(html_entity_decode($m[1] ?? ''), true);

        $this->assertEqualsWithDelta(20000.0, (float) ($owed[(string) $owing->id] ?? 0), 0.001);
        $this->assertArrayNotHasKey((string) $clear->id, $owed, 'a worker owing nothing has the whole limit');
    }

    // ── The tab and payroll agree ────────────────────────────────────────

    /**
     * A week of leave collects the instalment, and every screen shows it.
     *
     * Reported for Lawrence Bernas: the Cash Advances tab showed his ₱5,000
     * advance 20% paid — this week's ₱1,000 instalment taken — while Payroll
     * Records and Payroll Processing showed no deduction at all. He was on
     * paid leave all week with no attendance, and the payroll row a week of
     * leave gets carried the advance at zero while the schedule counted it.
     */
    public function test_a_week_of_leave_collects_the_instalment_on_every_screen(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-17 14:00:00', 'Asia/Manila'));

        $e = $this->worker([], 'Lawrence Bernas');

        \App\Models\LeaveRequest::create([
            'employee_id' => $e->id, 'leave_type' => 'sick', 'starts_on' => '2026-09-16', 'ends_on' => '2026-09-18',
            'days' => 3, 'is_paid' => true, 'filed_by' => $this->admin->id,
        ]);

        $net = fn () => (float) $this->totals($e, '2026-09-14', '2026-09-20')['net'];
        $before = $net();

        $advance = Loan::create([
            'employee_id' => $e->id, 'type' => Loan::ADVANCE, 'principal' => 5000, 'balance' => 5000,
            'installment' => 1000, 'schedule' => 'per_payroll', 'issued_on' => '2026-09-17',
            'status' => 'active', 'created_by' => $this->admin->id,
        ]);

        // The tab: this week's instalment taken.
        $this->assertEqualsWithDelta(4000.0, $advance->fresh()->outstanding, 0.001);
        $this->assertSame(20, $advance->fresh()->progress);

        // Payroll Records: the same ₱1,000, off the net.
        $this->assertEqualsWithDelta(1000.0, $this->advanceTaken($e, '2026-09-14', '2026-09-20'), 0.001);
        $this->assertEqualsWithDelta($before - 1000, $net(), 0.011, 'the net pay is ₱1,000 less');

        // Payroll Processing.
        $sel = $this->actingAs($this->admin)->get(route('payroll-processing.index', [
            'period' => '2026-09-14_2026-09-20', 'view' => 'workflow', 'employee' => $e->id,
        ]))->assertOk()->viewData('sel');

        $this->assertEqualsWithDelta(1000.0, $sel['advance'], 0.001, 'named as the advance instalment');
        $this->assertEqualsWithDelta(1000.0, $sel['vale'], 0.001, 'on the vale / cash advance line');
        $this->assertEqualsWithDelta($before - 1000, $sel['net'], 0.011);

        // The payslip.
        $slip = $this->actingAs($this->admin)
            ->get(route('payslip.batch', ['from' => '2026-09-14', 'to' => '2026-09-20', 'employee' => $e->id]))
            ->assertOk()->viewData('slips')->first();

        $this->assertEqualsWithDelta(1000.0, $slip['ded']['vale'], 0.001, 'in the payslip deductions');
        $this->assertEqualsWithDelta($before - 1000, $slip['net'], 0.011);

        // And a run for the week charges it once.
        $this->actingAs($this->admin)->post(route('payroll-processing.store'), [
            'period_start' => '2026-09-14', 'period_end' => '2026-09-20',
        ]);

        $item = \App\Models\PayrollRun::sole()->items()->where('employee_id', $e->id)->sole();

        $this->assertEqualsWithDelta(1000.0, (float) $item->advance_deduction, 0.001);
        $this->assertEqualsWithDelta($before - 1000, (float) $item->net_pay, 0.011);
    }

    /**
     * A week with no pay collects nothing — on the tab as in payroll.
     *
     * There is nothing to take an instalment out of: no attendance, no paid
     * leave, no payroll row. The schedule used to charge the week anyway, so
     * the tab showed a deduction no payroll had made. What is not taken stays
     * owed, and the next week with pay takes its instalment then.
     */
    public function test_a_week_with_no_pay_collects_nothing_anywhere(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-17 14:00:00', 'Asia/Manila'));

        $e       = $this->worker([], 'Lawrence Bernas');
        $advance = $this->advance($e, 5000, 1000, '2026-09-14');

        $this->assertEqualsWithDelta(5000.0, $advance->fresh()->outstanding, 0.001, 'nothing taken');
        $this->assertSame(0, $advance->fresh()->progress);
        $this->assertSame([], $advance->fresh()->walk('2026-09-17', Carbon::MONDAY)['lines']);
        $this->assertSame([], $this->figures($e, '2026-09-14', '2026-09-20'), 'and no payroll row to take it from');

        // He clocks in on the Friday: the week has pay, and both sides take it.
        Carbon::setTestNow(Carbon::parse('2026-09-18 18:00:00', 'Asia/Manila'));

        Attendance::create([
            'employee_id' => $e->id, 'shift_id' => $e->shift_id, 'date' => '2026-09-18', 'session' => 'AM',
            'time_in' => '2026-09-18 08:00:00', 'time_out' => '2026-09-18 17:00:00',
        ]);

        $this->assertEqualsWithDelta(4000.0, $advance->fresh()->outstanding, 0.001);
        $this->assertEqualsWithDelta(1000.0, $this->advanceTaken($e, '2026-09-14', '2026-09-20'), 0.001);
    }

    /**
     * Week by week, what the tab says each payroll took is what that payroll
     * took — across a week worked, a week off, a week of leave and a week
     * worked again — and the total never more than was advanced.
     */
    public function test_the_tab_and_payroll_agree_week_by_week(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-05 09:00:00', 'Asia/Manila'));

        $e = $this->worker(['2026-09-10', '2026-10-01'], 'Lawrence Bernas');   // weeks 37 and 40

        \App\Models\LeaveRequest::create([
            'employee_id' => $e->id, 'leave_type' => 'vacation', 'starts_on' => '2026-09-22', 'ends_on' => '2026-09-23',
            'days' => 2, 'is_paid' => true, 'filed_by' => $this->admin->id,
        ]);                                                                      // week 39

        $advance = $this->advance($e, 2500, 1000, '2026-09-07');

        $weeks = [
            ['2026-09-07', '2026-09-13', 1000.0],   // worked
            ['2026-09-14', '2026-09-20', 0.0],      // nothing
            ['2026-09-21', '2026-09-27', 1000.0],   // leave
            ['2026-09-28', '2026-10-04', 500.0],    // worked — the remainder
        ];

        $lines = collect($advance->fresh()->walk('2026-10-05', Carbon::MONDAY)['lines'])->keyBy('week');

        foreach ($weeks as [$opens, $closes, $expected]) {
            $this->assertEqualsWithDelta($expected, (float) ($lines[$opens]['amount'] ?? 0), 0.001, "the tab, week of {$opens}");
            $this->assertEqualsWithDelta($expected, $this->advanceTaken($e, $opens, $closes), 0.001, "payroll, week of {$opens}");
        }

        $this->assertEqualsWithDelta(0.0, $advance->fresh()->outstanding, 0.001);
        $this->assertEqualsWithDelta(2500.0, $this->advanceTaken($e, '2026-09-07', '2026-10-04'), 0.001,
            'all of it, and not a peso more');
    }

    // ── The instalment on a new advance ──────────────────────────────────

    /** The New Cash Advance form, as the browser sends it. */
    private function applyFor(Employee $e, $amount, $instalment)
    {
        return $this->actingAs($this->admin)
            ->from(route('leave.index', ['tab' => 'advances']))
            ->post(route('loans.store'), [
                '_form' => 'advance', 'employee_id' => $e->id, 'principal' => $amount, 'installment' => $instalment,
                'schedule' => 'per_payroll', 'issued_on' => '2026-09-12', 'reference' => 'CA-7', 'notes' => 'Tuition',
            ]);
    }

    /**
     * An instalment above the amount borrowed is refused, and says why.
     *
     * It used to be lowered to the amount without a word and saved, so the
     * office typed one figure and the advance collected another.
     */
    public function test_an_instalment_above_the_amount_is_refused_not_quietly_lowered(): void
    {
        $e = $this->worker([], 'Lawrence Bernas');

        $this->applyFor($e, 5000, 6000)
            ->assertRedirect(route('leave.index', ['tab' => 'advances']))
            ->assertSessionHasErrors(['installment' =>
                'The instalment cannot be more than the amount borrowed (₱5,000.00). Enter an instalment equal to or lower than it.']);

        $this->applyFor($e, 5000, 5000.01)->assertSessionHasErrors('installment');

        $this->assertSame(0, Loan::count(), 'nothing is saved until the instalment is corrected');

        // Equal to the amount is allowed, and saved as typed.
        $this->applyFor($e, 5000, 5000)->assertSessionHasNoErrors();
        $this->assertEqualsWithDelta(5000.0, (float) Loan::sole()->installment, 0.001);

        // And lower, of course.
        $this->applyFor($this->worker([], 'Aldrin Sapugay'), 5000, 750)->assertSessionHasNoErrors();
        $this->assertEqualsWithDelta(750.0, (float) Loan::latest('id')->first()->installment, 0.001);
    }

    /**
     * Refused, the form comes back open with everything typed and the reason
     * under the instalment — only the wrong figure needs touching.
     */
    public function test_a_refused_advance_reopens_the_form_with_what_was_typed(): void
    {
        $e = $this->worker([], 'Lawrence Bernas');

        $this->applyFor($e, 5000, 6000);

        $html = $this->actingAs($this->admin)
            ->withSession(['_old_input' => session('_old_input'), 'errors' => session('errors')])
            ->get(route('leave.index', ['tab' => 'advances']))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/id="advanceModal"[^>]*data-reopen/', $html, 'the form opens again');

        $at   = strpos($html, 'id="advanceForm"');
        $form = substr($html, $at, strpos($html, '</' . 'form>', $at) - $at);

        $this->assertStringContainsString('value="5000"', $form, 'the amount is kept');
        $this->assertStringContainsString('value="6000"', $form, 'and the instalment');
        $this->assertStringContainsString('value="CA-7"', $form);
        $this->assertStringContainsString('Tuition', $form);
        $this->assertMatchesRegularExpression('/<option value="' . $e->id . '"\s+selected/', $form, 'and the worker');
        $this->assertStringContainsString(
            'The instalment cannot be more than the amount borrowed (₱5,000.00). Enter an instalment equal to or lower than it.', $form);
        $this->assertDoesNotMatchRegularExpression('/id="ca_inst_err" role="alert"\s+hidden/', $form, 'the reason is showing');
    }

    /**
     * A refused Edit instalment is its own form's business: it posts an
     * "installment" too, and must not open New Cash Advance or leave its
     * figure and its error in there.
     */
    public function test_a_refused_edit_does_not_spill_into_the_new_advance_form(): void
    {
        $advance = $this->advance($this->worker([], 'Lawrence Bernas'), 500, 200);

        $this->actingAs($this->admin)
            ->from(route('leave.index', ['tab' => 'advances']))
            ->put(route('loans.update', $advance), ['installment' => 900])
            ->assertSessionHasErrors('installment');

        $html = $this->actingAs($this->admin)
            ->withSession(['_old_input' => session('_old_input'), 'errors' => session('errors')])
            ->get(route('leave.index', ['tab' => 'advances']))->assertOk()->getContent();

        $this->assertDoesNotMatchRegularExpression('/id="advanceModal"[^>]*data-reopen/', $html);

        $at   = strpos($html, 'id="advanceForm"');
        $form = substr($html, $at, strpos($html, '</' . 'form>', $at) - $at);

        $this->assertStringNotContainsString('value="900"', $form);
        $this->assertMatchesRegularExpression('/id="ca_inst_err" role="alert"\s+hidden/', $form, 'no error of its own showing');
    }

    // ── The order of the list ────────────────────────────────────────────

    /** The advances as the list shows them, by worker name. */
    private function listed(): array
    {
        return $this->actingAs($this->admin)
            ->get(route('leave.index', ['tab' => 'advances']))->assertOk()
            ->viewData('advances')->map(fn (Loan $l) => $l->employee->name)->all();
    }

    /**
     * The most recently added or changed advance is at the top: a new one, an
     * edited instalment, a payment recorded — not wherever its issue date
     * happens to sort it.
     */
    public function test_the_most_recently_added_or_changed_advance_is_listed_first(): void
    {
        $at = fn (string $when) => Carbon::setTestNow(Carbon::parse("2026-09-12 {$when}", 'Asia/Manila'));

        $at('09:00:00');
        $first = $this->advance($this->worker([], 'Aldrin Sapugay'), 5000, 500, '2026-09-10');
        $at('10:00:00');
        $second = $this->advance($this->worker([], 'Mark Adrian Gulbe De Leon'), 5000, 500, '2026-09-01');
        $at('11:00:00');
        $this->advance($this->worker([], 'Lawrence Bernas'), 5000, 500, '2026-08-20');   // issued earliest, added last

        $this->assertSame(['Lawrence Bernas', 'Mark Adrian Gulbe De Leon', 'Aldrin Sapugay'], $this->listed(),
            'newest added first, whatever the issue date');

        // Editing the oldest brings it to the top.
        $at('12:00:00');
        $this->actingAs($this->admin)->put(route('loans.update', $first), ['installment' => 400])->assertSessionHasNoErrors();

        $this->assertSame('Aldrin Sapugay', $this->listed()[0]);

        // So does a payment.
        $at('13:00:00');
        $this->actingAs($this->admin)
            ->post(route('loans.payment', $second), ['amount' => 100, 'deducted_on' => '2026-09-12'])
            ->assertSessionHas('success');

        $this->assertSame(['Mark Adrian Gulbe De Leon', 'Aldrin Sapugay', 'Lawrence Bernas'], $this->listed());
    }

    /**
     * The page keeping balances in step with the calendar is not a change to
     * an advance, and does not reorder the list. A week passing and a day
     * worked move a balance; neither is somebody editing the advance.
     */
    public function test_a_balance_moving_on_its_own_does_not_reorder_the_list(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-12 09:00:00', 'Asia/Manila'));
        $older = $this->advance($this->worker(['2026-09-10', '2026-09-17'], 'Aldrin Sapugay'), 5000, 500, '2026-09-07');

        Carbon::setTestNow(Carbon::parse('2026-09-12 10:00:00', 'Asia/Manila'));
        $this->advance($this->worker([], 'Lawrence Bernas'), 5000, 500, '2026-09-07');

        $this->assertSame(['Lawrence Bernas', 'Aldrin Sapugay'], $this->listed());

        // A week on: Aldrin's second instalment comes round and the page
        // writes his balance down — without stamping it as changed.
        Carbon::setTestNow(Carbon::parse('2026-09-20 09:00:00', 'Asia/Manila'));

        $this->assertSame(['Lawrence Bernas', 'Aldrin Sapugay'], $this->listed());
        $this->assertEqualsWithDelta(4000.0, (float) $older->fresh()->balance, 0.001, 'the balance did move');
        $this->assertSame('2026-09-12 09:00:00', $older->fresh()->updated_at->format('Y-m-d H:i:s'));
    }
    /**
     * Every row on the page is brought up to date, not only the rows before
     * the first one that already was.
     *
     * The list synced balances with each() and an arrow function, and each()
     * stops at the first callback that returns false — which syncSettlement()
     * does for a row with nothing to write. Every row after it kept a stale
     * balance and status: an advance read "Fully Paid" in the blue of an
     * active one, because the label is worked out live and the colour read
     * the column.
     */
    public function test_every_row_on_the_page_is_brought_up_to_date(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-12 09:00:00', 'Asia/Manila'));
        $settled = $this->advance($this->worker([], 'Aldrin Sapugay'), 500, 500, '2026-09-07');

        Carbon::setTestNow(Carbon::parse('2026-09-12 10:00:00', 'Asia/Manila'));
        $current = $this->advance($this->worker([], 'Lawrence Bernas'), 5000, 500, '2026-09-07');   // listed first, nothing to write

        // Aldrin paid it all at the office, written straight to the ledger —
        // as older payments were — so nothing has synced his row yet.
        LoanDeduction::create(['loan_id' => $settled->id, 'amount' => 500, 'deducted_on' => '2026-09-11']);

        $html = $this->actingAs($this->admin)
            ->get(route('leave.index', ['tab' => 'advances']))->assertOk()->getContent();

        $this->assertSame('paid', $settled->fresh()->status, 'the second row was synced too');
        $this->assertEqualsWithDelta(0.0, (float) $settled->fresh()->balance, 0.001);
        $this->assertSame('active', $current->fresh()->status);

        $this->assertStringContainsString('mod-badge ok"><span class="dot"></span>Fully Paid', $html,
            'Fully Paid in the green of a settled advance');
        $this->assertStringNotContainsString('mod-badge info"><span class="dot"></span>Fully Paid', $html);
    }

    // ── Deleting an advance ──────────────────────────────────────────────

    /**
     * Deleting an advance removes it and its payments, payroll stops
     * deducting it, and the Audit Log keeps what it was.
     */
    public function test_an_advance_can_be_deleted_with_everything_recorded_against_it(): void
    {
        $e       = $this->worker(['2026-09-10'], 'Lawrence Bernas');
        $advance = $this->advance($e, 5000, 1000);
        $advance->forceFill(['reference' => 'CA-7'])->save();

        $this->actingAs($this->admin)
            ->post(route('loans.payment', $advance), ['amount' => 500, 'deducted_on' => '2026-09-11'])
            ->assertSessionHas('success');

        $this->assertEqualsWithDelta(1000.0, $this->advanceTaken($e, ...self::WEEK), 0.001, 'payroll was deducting it');

        $this->actingAs($this->admin)
            ->from(route('leave.index', ['tab' => 'advances']))
            ->delete(route('loans.destroy', $advance))
            ->assertRedirect(route('leave.index', ['tab' => 'advances']))
            ->assertSessionHas('success', "Lawrence Bernas's cash advance of ₱5,000.00 was deleted.");

        $this->assertNull(Loan::find($advance->id));
        $this->assertSame(0, LoanDeduction::where('loan_id', $advance->id)->count(), 'its payments went with it');

        $this->assertEqualsWithDelta(0.0, $this->advanceTaken($e, ...self::WEEK), 0.001, 'and payroll no longer deducts it');

        $log = \App\Models\AuditLog::where('module', 'Loans')->where('action', 'deleted')->sole();

        $this->assertSame(
            "Deleted Lawrence Bernas's cash advance of ₱5,000.00 — issued Sep 07, 2026, ₱1,000.00 per payroll, ref CA-7; "
            . 'payroll had taken ₱1,000.00, and 1 payment recorded at the office was removed with it.',
            $log->description
        );
        $this->assertSame($this->admin->id, $log->user_id);

        $this->actingAs($this->admin)->get(route('leave.index', ['tab' => 'advances']))
            ->assertOk()
            ->assertDontSee('₱5,000.00');
    }

    /** The menu offers Delete on every advance, and warns what it does to payroll first. */
    public function test_the_menu_offers_delete_and_says_what_it_does_to_payroll(): void
    {
        $running = $this->advance($this->worker(['2026-09-10'], 'Lawrence Bernas'), 5000, 1000);
        $unpaid  = $this->advance($this->worker([], 'Aldrin Sapugay'), 3000, 500, '2026-09-21');

        $html = $this->actingAs($this->admin)
            ->get(route('leave.index', ['tab' => 'advances']))->assertOk()->getContent();

        $form = function (Loan $l) use ($html): string {
            $at = strpos($html, 'action="' . route('loans.destroy', $l) . '"');
            $this->assertNotFalse($at, 'Delete is offered');

            return html_entity_decode(substr($html, $at, strpos($html, '</' . 'form>', $at) - $at), ENT_QUOTES);
        };

        $this->assertStringContainsString('data-confirm-tone="danger"', $form($running));
        $this->assertStringContainsString(
            "Lawrence Bernas's ₱5,000.00 cash advance and its payment history are removed for good. Payroll stops deducting it, "
            . "and the ₱1,000.00 it has already taken goes back into those weeks' net pay in Payroll Records.",
            $form($running));
        $this->assertStringContainsString('Payroll has not deducted anything from it yet.', $form($unpaid));

        // Settled advances can be deleted too.
        Carbon::setTestNow(Carbon::parse('2026-09-26 12:00:00', 'Asia/Manila'));
        $settled = $this->advance($this->worker(['2026-09-24'], 'Mark Adrian Gulbe De Leon'), 500, 500, '2026-09-21');

        $html = $this->actingAs($this->admin)
            ->get(route('leave.index', ['tab' => 'advances']))->assertOk()->getContent();

        $this->assertStringContainsString('action="' . route('loans.destroy', $settled) . '"', $html);
    }

    /** An old loan on file is history, and is not deleted from here. */
    public function test_an_old_loan_cannot_be_deleted(): void
    {
        $loan = Loan::create([
            'employee_id' => $this->worker()->id, 'type' => 'loan', 'principal' => 2000, 'balance' => 2000,
            'installment' => 500, 'schedule' => 'per_payroll', 'issued_on' => '2026-09-01', 'status' => 'active',
        ]);

        $this->actingAs($this->admin)->delete(route('loans.destroy', $loan))->assertNotFound();
        $this->assertNotNull(Loan::find($loan->id));
    }

    /** A role without the advances module cannot delete one. */
    public function test_a_role_without_advances_cannot_delete_one(): void
    {
        $advance = $this->advance($this->worker(), 5000, 1000);

        $supervisor = User::create([
            'name' => 'Supervisor', 'username' => 'sup.cashadvance', 'password' => 'secret123',
            'role' => User::ROLE_SUPERVISOR, 'is_active' => true,
        ]);

        $this->actingAs($supervisor)->delete(route('loans.destroy', $advance))->assertForbidden();
        $this->assertNotNull(Loan::find($advance->id));
    }
}
