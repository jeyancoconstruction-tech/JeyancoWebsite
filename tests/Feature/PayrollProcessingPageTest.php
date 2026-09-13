<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\LaborType;
use App\Models\PayrollRate;
use App\Models\PayrollRemittance;
use App\Models\PayrollRun;
use App\Models\Shift;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\PayrollService;
use App\Support\WorkSchedule;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Payroll Processing: one period and one worker at a time — the computation
 * in stages, where each contribution and the net pay have got to, and the
 * payslip.
 */
class PayrollProcessingPageTest extends TestCase
{
    use RefreshDatabase;

    /** Week 37: Monday the 7th to Sunday the 13th. */
    private const WEEK = '2026-09-07_2026-09-13';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Saturday evening, inside week 37.
        Carbon::setTestNow(Carbon::parse('2026-09-12 21:00:00', 'Asia/Manila'));
        SystemSetting::current()->forceFill(['schedule_rules_from' => '2026-09-01'])->save();

        // The standard contribution rates. With none on file they fall back
        // to zero, and there would be no SSS or PhilHealth line to track.
        PayrollRate::create(array_merge(PayrollRate::DEFAULTS, [
            'effective_from' => '2026-01-01', 'created_by' => 'test',
        ]));

        $this->admin = User::create([
            'name' => 'Admin', 'username' => 'admin.processing', 'password' => 'secret123',
            'role' => User::ROLE_ADMIN, 'is_admin' => true, 'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** A worker with one ordinary day in the week: eight to five. */
    private function worker(string $name = 'Day Crew'): Employee
    {
        $e = Employee::create([
            'name'            => $name,
            'status'          => Employee::STATUS_ACTIVE,
            'employment_type' => Employee::EMPLOYMENT_DAILY,
            'labor_type_id'   => LaborType::create(['name' => 'Mason ' . $name, 'daily_rate' => 800, 'ot_rate' => 125])->id,
            'shift_id'        => Shift::where('crosses_midnight', false)->firstOrFail()->id,
            'rate_per_hour'   => 100,
        ]);

        Attendance::create([
            'employee_id' => $e->id, 'shift_id' => $e->shift_id, 'date' => '2026-09-10', 'session' => 'AM',
            'time_in' => '2026-09-10 08:00:00', 'time_out' => '2026-09-10 17:00:00',
        ]);

        return $e;
    }

    private function page(array $query = [])
    {
        return $this->actingAs($this->admin)->get(route('payroll-processing.index', $query))->assertOk();
    }

    private function mark(Employee $e, string $kind, string $action, string $period = self::WEEK)
    {
        return $this->actingAs($this->admin)
            ->post(route('payroll-processing.track', [$e, $kind]), ['period' => $period, 'action' => $action]);
    }

    private function row(array $query, string $kind): array
    {
        return collect($this->page($query + ['view' => 'tracker'])->viewData('track'))->firstWhere('kind', $kind);
    }

    private function process()
    {
        return $this->actingAs($this->admin)->post(route('payroll-processing.store'), [
            'period_start' => '2026-09-07', 'period_end' => '2026-09-13',
        ]);
    }

    // ── The page ─────────────────────────────────────────────────────────

    public function test_it_opens_on_this_week_without_the_run_bar(): void
    {
        $this->worker();

        $page = $this->page()
            ->assertSee('Payroll processing')
            ->assertSee('Week 37 · Sep 07–13, 2026')
            ->assertSee('Day Crew')
            ->assertDontSee('Not processed yet')
            ->assertDontSee('Process payroll')
            ->assertDontSee('Remitted & paid');

        $this->assertSame(self::WEEK, $page->viewData('period')['key']);
    }

    public function test_a_period_with_nobody_in_it_says_so(): void
    {
        $this->page()
            ->assertSee('No attendance in this period.')
            ->assertSee('there is no payroll to process');
    }

    /** A remittance can fall due after its week has dropped off the list. */
    public function test_an_older_range_still_opens_and_a_made_up_one_does_not(): void
    {
        $this->assertSame('2026-06-01_2026-06-07',
            $this->page(['period' => '2026-06-01_2026-06-07'])->viewData('period')['key']);

        $this->assertSame(self::WEEK,
            $this->page(['period' => '2026-02-30_2026-03-06'])->viewData('period')['key'],
            'the 30th of February is not a period');
    }

    // ── The computation ──────────────────────────────────────────────────

    public function test_the_stages_add_up_to_the_net(): void
    {
        $this->worker();

        $page  = $this->page();
        $sel   = $page->viewData('sel');
        $lines = $page->viewData('lines');

        $this->assertEqualsWithDelta($sel['gross'], array_sum(array_column($lines['earn'], 'amount')), 0.011,
            'the earnings lines are the gross');
        $this->assertEqualsWithDelta($sel['deductions'], array_sum(array_column($lines['ded'], 'amount')), 0.011,
            'the deduction lines are the total');
        $this->assertEqualsWithDelta($sel['net'], $sel['gross'] - $sel['deductions'], 0.011);

        // Basic pay says what produced it, in hours and minutes.
        $basic = $lines['earn'][0];
        $this->assertSame(
            WorkSchedule::duration($sel['regular_minutes']) . ' × ₱' . number_format($sel['hourly_rate'], 2) . '/hr',
            $basic['note']
        );
        $this->assertEqualsWithDelta($sel['regular_minutes'] / 60 * $sel['hourly_rate'], $basic['amount'], 0.011);
    }

    /**
     * Three evenings of 2h 10m overtime are 6h 30m. Added up from days rounded
     * to hundredths of an hour (2.17 each) they came to 6.51 — a minute that
     * never happened, taken out of the regular time beside it.
     */
    public function test_overtime_adds_up_in_whole_minutes(): void
    {
        $e = $this->worker();
        Attendance::where('employee_id', $e->id)->update(['time_out' => '2026-09-10 19:10:00']);

        foreach (['2026-09-08', '2026-09-09'] as $d) {
            Attendance::create([
                'employee_id' => $e->id, 'shift_id' => $e->shift_id, 'date' => $d, 'session' => 'AM',
                'time_in' => "{$d} 08:00:00", 'time_out' => "{$d} 19:10:00",
            ]);
        }

        $page = $this->page();
        $sel  = $page->viewData('sel');

        $this->assertSame(390, $sel['ot_minutes'], 'three times 2h 10m');
        $this->assertSame(1440, $sel['regular_minutes'], 'three eight-hour days, whole');
        $this->assertStringStartsWith('24h 00m × ', $page->viewData('lines')['earn'][0]['note']);
    }

    /** What the page shows is what a run would freeze, from one computation. */
    public function test_the_page_shows_what_a_run_would_freeze(): void
    {
        $this->worker();

        $before = $this->page()->viewData('sel');
        $this->process()->assertRedirect(route('payroll-processing.index', ['period' => self::WEEK]));
        $item = PayrollRun::sole()->items()->sole();

        $this->assertEqualsWithDelta($before['gross'], $item->gross_pay, 0.001);
        $this->assertEqualsWithDelta($before['deductions'], $item->total_deductions, 0.001);
        $this->assertEqualsWithDelta($before['net'], $item->net_pay, 0.001);
    }

    /** A finalised run is a fact: the page shows its figures, not today's. */
    public function test_a_finalized_run_shows_its_frozen_figures(): void
    {
        $e = $this->worker();
        $this->process();

        $run = PayrollRun::sole();
        $this->actingAs($this->admin)->post(route('payroll-processing.approve', $run));
        $this->actingAs($this->admin)->post(route('payroll-processing.finalize', $run), ['confirm' => 1]);

        // A day added after the fact does not reach a finalised payslip.
        Attendance::create([
            'employee_id' => $e->id, 'shift_id' => $e->shift_id, 'date' => '2026-09-09', 'session' => 'AM',
            'time_in' => '2026-09-09 08:00:00', 'time_out' => '2026-09-09 17:00:00',
        ]);

        $page = $this->page(['period' => self::WEEK])->assertSee('Final · ' . $run->code);
        $this->assertEqualsWithDelta($run->items()->sole()->gross_pay, $page->viewData('sel')['gross'], 0.001);
    }

    /**
     * A run used to carry contributions and tax as one lump in "other
     * deductions", and a lump cannot be remitted to four agencies.
     */
    public function test_a_run_carries_each_contribution_on_its_own_line(): void
    {
        $e = $this->worker();
        $this->process();

        $item   = PayrollRun::sole()->items()->sole();
        $engine = collect(app(PayrollService::class)->computeForRange('2026-09-07', '2026-09-13')['employees'])
            ->firstWhere('employee_id', $e->id)['totals'];

        $this->assertGreaterThan(0, $item->sss + $item->philhealth + $item->pagibig + $item->tax);
        $this->assertSame(0.0, $item->other_deductions, 'nothing was lumped');
        $this->assertEqualsWithDelta($engine['totalDeductions'], $item->total_deductions, 0.02);
        $this->assertEqualsWithDelta($engine['net'], $item->net_pay, 0.02, 'the run pays what Payroll Records says');
    }

    /**
     * The engine adds a bonus to net, not to gross. A run used to take it back
     * out of basic pay as if it were in gross and then list it as an earning,
     * so the payslip showed a bonus that the net never paid.
     */
    public function test_a_run_pays_the_period_bonus(): void
    {
        PayrollRate::create(array_merge(PayrollRate::DEFAULTS, [
            'effective_from' => '2026-09-01', 'created_by' => 'test', 'bonus' => 500,
        ]));

        $e = $this->worker();
        $this->process();

        $item   = PayrollRun::sole()->items()->sole();
        $engine = collect(app(PayrollService::class)->computeForRange('2026-09-07', '2026-09-13')['employees'])
            ->firstWhere('employee_id', $e->id)['totals'];

        $this->assertEqualsWithDelta(500.0, $item->bonus, 0.001);
        $this->assertEqualsWithDelta(
            $engine['gross'] - $engine['overtime'] - $engine['holidayPay'] - $engine['restDayPay'] - $engine['nightDiffPay'],
            $item->basic_pay, 0.011, 'basic pay is not cut by the bonus'
        );
        $this->assertEqualsWithDelta($engine['net'], $item->net_pay, 0.02, 'and the net carries it');
    }

    // ── The tracker ──────────────────────────────────────────────────────

    /** No run, no approval: the line moves the moment somebody presses it. */
    public function test_a_contribution_is_submitted_then_remitted_straight_away(): void
    {
        $e = $this->worker();

        $this->mark($e, 'sss', 'submit')->assertSessionHas('success');

        $line = PayrollRemittance::sole();
        $this->assertSame('submitted', $line->status);
        $this->assertSame($this->admin->id, $line->submitted_by);
        $this->assertSame('2026-09-07', $line->period_start);
        $this->assertEqualsWithDelta($this->page()->viewData('sel')['sss'], $line->amount, 0.001);

        $this->mark($e, 'sss', 'done')->assertSessionHas('success');
        $this->assertSame('done', $line->fresh()->status);
        $this->assertNotNull($line->fresh()->completed_at);

        $this->page(['view' => 'tracker'])->assertSee('Remitted')->assertSee('by Admin');
    }

    public function test_steps_follow_in_order_and_undo_walks_one_back(): void
    {
        $e = $this->worker();

        $this->mark($e, 'bir', 'done')->assertSessionHas('error');   // not submitted yet
        $this->assertSame(0, PayrollRemittance::count());

        $this->mark($e, 'bir', 'submit');
        $this->mark($e, 'bir', 'done');
        $this->mark($e, 'bir', 'submit')->assertSessionHas('error');  // already remitted

        $this->mark($e, 'bir', 'undo');
        $line = PayrollRemittance::sole();
        $this->assertSame('submitted', $line->status);
        $this->assertNull($line->completed_at, 'undoing takes the signature back off');
        $this->assertNotNull($line->submitted_at);
    }

    public function test_net_pay_is_paid_or_not(): void
    {
        $e = $this->worker();

        $this->mark($e, 'net_pay', 'submit')->assertSessionHas('error');
        $this->mark($e, 'net_pay', 'done')->assertSessionHas('success');

        $this->assertSame('done', PayrollRemittance::where('kind', 'net_pay')->sole()->status);
        $this->assertSame('Paid', $this->row([], 'net_pay')['state']);
    }

    public function test_a_mark_belongs_to_its_worker(): void
    {
        $a = $this->worker('Alpha');
        $b = $this->worker('Bravo');

        $this->mark($a, 'sss', 'submit');

        $this->assertSame('Submitted', $this->row(['employee' => $a->id], 'sss')['state']);
        $this->assertSame('Pending', $this->row(['employee' => $b->id], 'sss')['state']);
    }

    public function test_nothing_due_cannot_be_marked(): void
    {
        $e = $this->worker();

        // Week 36: no attendance, so no pay and nothing on it to remit.
        $this->mark($e, 'sss', 'submit', '2026-08-31_2026-09-06')->assertSessionHas('error');
        $this->assertSame(0, PayrollRemittance::count());
    }

    /** What was remitted is kept, even when the attendance behind it moves. */
    public function test_a_mark_keeps_what_it_came_to_when_the_pay_moves(): void
    {
        $e = $this->worker();
        $this->mark($e, 'sss', 'submit');
        $this->mark($e, 'sss', 'done');
        $remitted = PayrollRemittance::sole()->amount;

        Attendance::create([
            'employee_id' => $e->id, 'shift_id' => $e->shift_id, 'date' => '2026-09-09', 'session' => 'AM',
            'time_in' => '2026-09-09 08:00:00', 'time_out' => '2026-09-09 17:00:00',
        ]);

        $row = $this->row([], 'sss');
        $this->assertSame('Remitted', $row['state']);
        $this->assertEqualsWithDelta($remitted, $row['was'], 0.001);
        $this->assertGreaterThan($remitted, $row['amount']);

        $this->page(['view' => 'tracker'])->assertSee('when marked');
    }

    /** A tracker with nothing on it says why, rather than looking broken. */
    public function test_no_contributions_is_explained(): void
    {
        PayrollRate::query()->delete();   // the rates fall back to zero

        $e = $this->worker();
        Attendance::where('employee_id', $e->id)->update(['time_out' => '2026-09-10 09:00:00']);   // under the tax line too

        $this->page(['view' => 'tracker'])->assertSee('No contributions or tax were taken off this pay');

        $sss = $this->row([], 'sss');
        $this->assertSame('Nothing due', $sss['state']);
        $this->assertSame([], $sss['actions']);

        // The net pay can still be released.
        $this->assertSame('Mark paid', $this->row([], 'net_pay')['actions'][0]['label']);
    }

    public function test_an_unknown_line_or_period_is_not_found(): void
    {
        $e = $this->worker();

        $this->mark($e, 'gcash', 'submit')->assertNotFound();
        $this->mark($e, 'sss', 'submit', '2026-02-30_2026-03-06')->assertNotFound();
    }

    // ── The payslip ──────────────────────────────────────────────────────

    /** The same slip Payroll Records hands out, and the same page to print it from. */
    public function test_the_payslip_uses_the_payroll_records_format(): void
    {
        $e = $this->worker();

        $page = $this->page(['view' => 'payslip'])
            ->assertSee('PAYSLIP')
            ->assertSee('Regular pay (1d)')
            ->assertSee('SSS (5.00%)')
            ->assertSee('− Deductions')
            ->assertSee('+ Bonus')
            ->assertSee('NET PAY')
            ->assertSee(e(route('payslip.batch', ['from' => '2026-09-07', 'to' => '2026-09-13', 'employee' => $e->id])), false);

        $slip = $page->viewData('slip');

        $this->assertStringStartsWith('₱800.00/day · ₱100.00/hr · 1 day worked', $slip['basis']);
        $this->assertEqualsWithDelta($slip['gross'], array_sum(array_column($slip['earn'], 1)), 0.011, 'the earnings are the gross');
        $this->assertEqualsWithDelta($slip['deductions'], array_sum(array_column($slip['ded'], 1)), 0.011, 'the deductions are the total');
        $this->assertEqualsWithDelta($slip['net'], $slip['gross'] - $slip['deductions'] + $slip['bonus'], 0.011);
    }

    /** The bonus sits below the line, as Payroll Records puts it: not wages. */
    public function test_the_payslip_adds_the_bonus_to_net_not_to_gross(): void
    {
        PayrollRate::create(array_merge(PayrollRate::DEFAULTS, [
            'effective_from' => '2026-09-01', 'created_by' => 'test', 'bonus' => 500,
        ]));

        $this->worker();

        $sel  = $this->page(['view' => 'payslip'])->viewData('sel');
        $slip = $this->page(['view' => 'payslip'])->viewData('slip');

        $this->assertEqualsWithDelta(500.0, $slip['bonus'], 0.001);
        $this->assertEqualsWithDelta($sel['gross'] - 500, $slip['gross'], 0.011);
        $this->assertEqualsWithDelta($sel['net'], $slip['net'], 0.001);
    }
}
