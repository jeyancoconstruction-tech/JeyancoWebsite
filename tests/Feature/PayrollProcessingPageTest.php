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
 * in stages, where each deduction went, and the payslip — on top of the run
 * workflow that was already there.
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

    private function process()
    {
        return $this->actingAs($this->admin)->post(route('payroll-processing.store'), [
            'period_start' => '2026-09-07', 'period_end' => '2026-09-13',
        ]);
    }

    private function mark(string $kind, string $action)
    {
        $item = PayrollRun::sole()->items()->sole();

        return $this->actingAs($this->admin)
            ->post(route('payroll-processing.track', [$item, $kind]), ['action' => $action]);
    }

    private function finalize(): PayrollRun
    {
        $run = PayrollRun::sole();
        $this->actingAs($this->admin)->post(route('payroll-processing.approve', $run));
        $this->actingAs($this->admin)->post(route('payroll-processing.finalize', $run), ['confirm' => 1]);

        return tap($run->fresh(), fn ($r) => $this->assertSame('finalized', $r->status));
    }

    // ── The period ───────────────────────────────────────────────────────

    public function test_it_opens_on_this_week(): void
    {
        $this->worker();

        $page = $this->page()
            ->assertSee('Payroll processing')
            ->assertSee('Week 37 · Sep 07–13, 2026')
            ->assertSee('Not processed yet')
            ->assertSee('Process payroll')
            ->assertSee('Day Crew');

        $this->assertSame(self::WEEK, $page->viewData('period')['key']);
    }

    public function test_a_period_with_nobody_in_it_says_so(): void
    {
        $this->page()
            ->assertSee('No attendance in this period.')
            ->assertSee('there is no payroll to process');
    }

    /** What the page shows before processing is what processing freezes. */
    public function test_the_preview_is_what_gets_frozen(): void
    {
        $e = $this->worker();

        $before = $this->page()->viewData('sel');
        $this->assertSame($e->id, $before['employee_id']);
        $this->assertNull($before['item'], 'nothing is frozen yet');

        $this->process()->assertRedirect(route('payroll-processing.index', ['period' => self::WEEK]));

        $run  = PayrollRun::sole();
        $item = $run->items()->sole();

        $this->assertSame('calculated', $run->status);
        $this->assertEqualsWithDelta($before['gross'], $item->gross_pay, 0.001);
        $this->assertEqualsWithDelta($before['deductions'], $item->total_deductions, 0.001);
        $this->assertEqualsWithDelta($before['net'], $item->net_pay, 0.001);

        $this->page()->assertSee($run->code)->assertSee('Approve')->assertDontSee('Process payroll');
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

    public function test_nothing_is_marked_before_the_run_is_final(): void
    {
        $this->worker();
        $this->process();

        $this->mark('sss', 'submit')->assertSessionHas('error');
        $this->assertSame(0, PayrollRemittance::count());

        $this->page(['period' => self::WEEK, 'view' => 'tracker'])
            ->assertSee('Remittances are tracked once this run is finalized')
            ->assertSee('Awaiting finalization');
    }

    public function test_a_contribution_is_submitted_then_remitted(): void
    {
        $this->worker();
        $this->process();
        $this->finalize();

        $this->mark('sss', 'submit')->assertSessionHas('success');

        $line = PayrollRemittance::sole();
        $this->assertSame('submitted', $line->status);
        $this->assertSame($this->admin->id, $line->submitted_by);
        $this->assertEqualsWithDelta(PayrollRun::sole()->items()->sole()->sss, $line->amount, 0.001);

        $this->mark('sss', 'done')->assertSessionHas('success');
        $this->assertSame('done', $line->fresh()->status);
        $this->assertNotNull($line->fresh()->completed_at);

        $this->page(['period' => self::WEEK, 'view' => 'tracker'])
            ->assertSee('Remitted')
            ->assertSee('by Admin');
    }

    public function test_steps_follow_in_order_and_undo_walks_one_back(): void
    {
        $this->worker();
        $this->process();
        $this->finalize();

        $this->mark('bir', 'done')->assertSessionHas('error');   // not submitted yet
        $this->assertSame(0, PayrollRemittance::count());

        $this->mark('bir', 'submit');
        $this->mark('bir', 'done');
        $this->mark('bir', 'submit')->assertSessionHas('error');  // already remitted

        $this->mark('bir', 'undo');
        $line = PayrollRemittance::sole();
        $this->assertSame('submitted', $line->status);
        $this->assertNull($line->completed_at, 'undoing takes the signature back off');
        $this->assertNotNull($line->submitted_at);
    }

    public function test_net_pay_is_paid_or_not(): void
    {
        $this->worker();
        $this->process();
        $this->finalize();

        $this->mark('net_pay', 'submit')->assertSessionHas('error');
        $this->mark('net_pay', 'done')->assertSessionHas('success');

        $this->assertSame('done', PayrollRemittance::where('kind', 'net_pay')->sole()->status);
        $this->page(['period' => self::WEEK, 'view' => 'tracker'])->assertSee('Paid');
    }

    public function test_the_last_stage_counts_what_has_settled(): void
    {
        $this->worker();
        $this->process();
        $this->finalize();

        $item = PayrollRun::sole()->items()->sole();
        $due  = collect(array_keys(PayrollRemittance::KINDS))
            ->filter(fn ($k) => PayrollRemittance::amountFor($item, $k) > 0)
            ->count();

        $this->mark('net_pay', 'done');

        $last = collect($this->page(['period' => self::WEEK])->viewData('stages'))->last();
        $this->assertSame("1 of {$due} settled", $last['note']);
        $this->assertSame('now', $last['state']);
    }

    public function test_an_unknown_line_is_not_found(): void
    {
        $this->worker();
        $this->process();

        $this->mark('gcash', 'submit')->assertNotFound();
    }

    // ── The payslip ──────────────────────────────────────────────────────

    public function test_the_payslip_is_a_draft_until_the_run_is_final(): void
    {
        $this->worker();

        $this->page(['view' => 'payslip'])->assertSee('DRAFT')->assertSee('Approval pending');

        $this->process();
        $this->finalize();

        $this->page(['period' => self::WEEK, 'view' => 'payslip'])
            ->assertDontSee('DRAFT')
            ->assertSee('Approved by Admin');
    }
}
