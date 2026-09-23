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

    /** Step two: the roster for one of the three options, with nobody opened. */
    private function people(string $view = 'workflow', array $query = [])
    {
        return $this->page($query + ['view' => $view]);
    }

    /**
     * Step three: one worker's salary computation, tracker or payslip. Both
     * the option and the employee have to be asked for — the page chooses
     * neither on the user's behalf.
     */
    private function open(Employee $e, string $view = 'workflow', array $query = [])
    {
        return $this->page($query + ['view' => $view, 'employee' => $e->id]);
    }

    private function row(Employee $e, string $kind, array $query = []): array
    {
        return collect($this->open($e, 'tracker', $query)->viewData('track'))->firstWhere('kind', $kind);
    }

    private function process()
    {
        return $this->actingAs($this->admin)->post(route('payroll-processing.store'), [
            'period_start' => '2026-09-07', 'period_end' => '2026-09-13',
        ]);
    }

    // ── The page ─────────────────────────────────────────────────────────

    /** The first page is the three options — and nobody is chosen on it. */
    public function test_it_opens_on_the_three_options_without_the_run_bar(): void
    {
        $this->worker();

        $page = $this->page()
            ->assertSee('Payroll processing')
            ->assertSee('Week 37 · Sep 07–13, 2026')
            ->assertSee('Salary computation')
            ->assertSee('Remittance tracker')
            ->assertSee('View payslip')
            ->assertDontSee('Day Crew')
            ->assertDontSee('Not processed yet')
            ->assertDontSee('Process payroll');

        $this->assertSame(self::WEEK, $page->viewData('period')['key']);
        $this->assertSame('options', $page->viewData('step'));
        $this->assertNull($page->viewData('sel'), 'no worker is opened until one is picked');
    }

    /**
     * Each option goes to the roster first, and only then to the worker. It
     * is the same two steps whichever of the three was chosen.
     */
    public function test_an_option_lists_the_employees_before_opening_one(): void
    {
        $e = $this->worker();

        foreach (['workflow', 'tracker', 'payslip'] as $view) {
            $list = $this->people($view)->assertSee('Day Crew');

            $this->assertSame('people', $list->viewData('step'), $view);
            $this->assertNull($list->viewData('sel'), $view . ': the list picks nobody');
            $this->assertSame([], $list->viewData('track'), $view . ': nothing is worked out yet');
        }

        $open = $this->open($e, 'tracker');
        $this->assertSame('detail', $open->viewData('step'));
        $this->assertSame('Day Crew', $open->viewData('sel')['name']);
    }

    /** The option chosen is the one that opens — not all three at once. */
    public function test_each_option_opens_only_its_own_function(): void
    {
        $e = $this->worker();

        $this->open($e, 'workflow')
            ->assertSee('Salary computation workflow')
            ->assertDontSee('Deduction &amp; remittance tracker', false)
            ->assertDontSee('NET PAY');

        $this->open($e, 'tracker')
            ->assertSee('Deduction &amp; remittance tracker', false)
            ->assertDontSee('Salary computation workflow')
            ->assertDontSee('NET PAY');

        $this->open($e, 'payslip')
            ->assertSee('NET PAY')
            ->assertDontSee('Salary computation workflow')
            ->assertDontSee('Deduction &amp; remittance tracker', false);
    }

    /** An employee who is not on the period falls back to the list, not to somebody else. */
    public function test_an_unknown_employee_falls_back_to_the_list(): void
    {
        $this->worker();

        $page = $this->page(['view' => 'payslip', 'employee' => 424242]);

        $this->assertSame('people', $page->viewData('step'));
        $this->assertNull($page->viewData('sel'));
    }

    public function test_a_period_with_nobody_in_it_says_so(): void
    {
        $this->page()->assertSee('0 of 0 with pay this period');

        $this->people('workflow')->assertSee('Nobody is on the payroll for Sep 07–13, 2026');
    }

    /** A worker just registered, or off all week, is listed — with nothing to pay. */
    public function test_a_worker_with_no_attendance_is_listed_at_zero(): void
    {
        $this->worker('Day Crew');

        $new = Employee::create([
            'name' => 'Aaron New Hire', 'status' => Employee::STATUS_ACTIVE,
            'employment_type' => Employee::EMPLOYMENT_DAILY,
            'labor_type_id' => LaborType::create(['name' => 'Helper', 'daily_rate' => 640, 'ot_rate' => 125])->id,
            'shift_id' => Shift::where('crosses_midnight', false)->firstOrFail()->id,
            'rate_per_hour' => 80,
        ]);

        $page = $this->people('workflow')->assertSee('Aaron New Hire')->assertSee('no attendance');

        $row = $page->viewData('rows')->firstWhere('employee_id', $new->id);
        $this->assertFalse($row['worked']);
        $this->assertSame(0.0, $row['net']);
        $this->assertEqualsWithDelta(640.0, $row['daily_rate'], 0.001, 'the rate is still theirs to show');

        $this->open($new, 'tracker')
            ->assertSee('No attendance for Aaron New Hire in this period');
    }

    /**
     * A stretch still open is not in the pay — payroll counts it when it is
     * timed out, here as in Payroll Records — but a worker on the clock is
     * shown as one, not as a worker with nothing.
     */
    public function test_a_worker_still_clocked_in_is_shown_on_the_clock(): void
    {
        $e = $this->worker();

        // Clocked in half an hour before "now" (Saturday, 9 PM), not out yet.
        Attendance::create([
            'employee_id' => $e->id, 'shift_id' => $e->shift_id, 'date' => '2026-09-12', 'session' => 'AM',
            'time_in' => '2026-09-12 20:30:00', 'time_out' => null,
        ]);

        $before = collect(app(PayrollService::class)->computeForRange('2026-09-07', '2026-09-13')['employees'])
            ->firstWhere('employee_id', $e->id)['totals'];

        $this->people('workflow')->assertSee('on the clock');

        $page = $this->open($e)->assertSee('is clocked in now — since 8:30 PM');
        $sel  = $page->viewData('sel');
        $this->assertSame(30, $sel['on_clock']['minutes']);
        $this->assertEqualsWithDelta($before['gross'], $sel['gross'], 0.001, 'the open stretch is not paid until it closes');
    }

    /** Somebody clocked in with nothing closed yet is told so, not "no contributions". */
    public function test_the_tracker_says_why_a_worker_on_the_clock_has_nothing_yet(): void
    {
        $e = Employee::create([
            'name' => 'Jason On The Clock', 'status' => Employee::STATUS_ACTIVE,
            'employment_type' => Employee::EMPLOYMENT_DAILY,
            'labor_type_id' => LaborType::create(['name' => 'Welder', 'daily_rate' => 1200, 'ot_rate' => 125])->id,
            'shift_id' => Shift::where('crosses_midnight', false)->firstOrFail()->id,
            'rate_per_hour' => 150,
        ]);

        Attendance::create([
            'employee_id' => $e->id, 'shift_id' => $e->shift_id, 'date' => '2026-09-12', 'session' => 'AM',
            'time_in' => '2026-09-12 20:55:00', 'time_out' => null,
        ]);

        $this->open($e, 'tracker')
            ->assertSee('Jason On The Clock is still clocked in, since 8:55 PM')
            ->assertDontSee('No contributions or tax were taken off this pay');
    }

    /** Pending registrations stay off the payroll here as everywhere; so does somebody who has left. */
    public function test_pending_and_archived_workers_without_pay_are_not_listed(): void
    {
        $this->worker();

        Employee::create(['name' => 'Waiting For A Finger', 'status' => Employee::STATUS_PENDING, 'employment_type' => Employee::EMPLOYMENT_DAILY, 'rate_per_hour' => 100]);
        Employee::create(['name' => 'Long Gone', 'status' => Employee::STATUS_ARCHIVED, 'employment_type' => Employee::EMPLOYMENT_DAILY, 'rate_per_hour' => 100]);

        $this->people('workflow')->assertDontSee('Waiting For A Finger')->assertDontSee('Long Gone');
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
        $e = $this->worker();

        $page  = $this->open($e);
        $sel   = $page->viewData('sel');
        $lines = $page->viewData('lines');

        $this->assertEqualsWithDelta($sel['gross'], array_sum(array_column($lines['earn'], 'amount')), 0.011,
            'the earnings lines are the gross');
        $this->assertEqualsWithDelta($sel['deductions'], array_sum(array_column($lines['ded'], 'amount')), 0.011,
            'the deduction lines are the total');
        $this->assertEqualsWithDelta($sel['net'], $sel['gross'] - $sel['deductions'] + $sel['bonus'], 0.011);

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

        $page = $this->open($e);
        $sel  = $page->viewData('sel');

        $this->assertSame(390, $sel['ot_minutes'], 'three times 2h 10m');
        $this->assertSame(1440, $sel['regular_minutes'], 'three eight-hour days, whole');
        $this->assertStringStartsWith('24h 00m × ', $page->viewData('lines')['earn'][0]['note']);
    }

    /**
     * It is Payroll Records looked at one worker at a time: the same figures
     * for the same week, to the centavo, with Payroll Settings behind both.
     */
    public function test_it_shows_exactly_what_payroll_records_shows(): void
    {
        PayrollRate::create(array_merge(PayrollRate::DEFAULTS, [
            'effective_from' => '2026-09-01', 'created_by' => 'test', 'bonus' => 250,
        ]));

        $a = $this->worker('Alpha');
        $b = $this->worker('Bravo');
        Attendance::where('employee_id', $b->id)->update(['time_out' => '2026-09-10 19:10:00']);   // overtime too

        $records = collect($this->actingAs($this->admin)->get('/payroll-records?mode=weekly&week=2026-W37')
            ->assertOk()->viewData('employees'))->keyBy('employee_id');

        foreach ([$a, $b] as $e) {
            $sel = $this->open($e)->viewData('sel');
            $t   = $records[$e->id]['totals'];

            $this->assertEqualsWithDelta($t['gross'], $sel['gross'], 0.001, "{$e->name}: gross");
            $this->assertEqualsWithDelta($t['overtime'], $sel['overtime'], 0.001, "{$e->name}: overtime");
            $this->assertEqualsWithDelta($t['totalDeductions'], $sel['deductions'], 0.001, "{$e->name}: deductions");
            $this->assertEqualsWithDelta($t['bonus'], $sel['bonus'], 0.001, "{$e->name}: bonus");
            $this->assertEqualsWithDelta($t['net'], $sel['net'], 0.001, "{$e->name}: net");
        }
    }

    /**
     * A payroll run is not a second set of books. One cut for the week and
     * finalised does not replace what Payroll Records says about it.
     */
    public function test_a_payroll_run_does_not_override_payroll_records(): void
    {
        $e = $this->worker();
        $this->process();

        $run = PayrollRun::sole();
        $this->actingAs($this->admin)->post(route('payroll-processing.approve', $run));
        $this->actingAs($this->admin)->post(route('payroll-processing.finalize', $run), ['confirm' => 1]);

        Attendance::create([
            'employee_id' => $e->id, 'shift_id' => $e->shift_id, 'date' => '2026-09-09', 'session' => 'AM',
            'time_in' => '2026-09-09 08:00:00', 'time_out' => '2026-09-09 17:00:00',
        ]);

        $page = $this->open($e, 'workflow', ['period' => self::WEEK])->assertDontSee($run->code);
        $live = collect(app(PayrollService::class)->computeForRange('2026-09-07', '2026-09-13')['employees'])
            ->firstWhere('employee_id', $e->id)['totals'];

        $this->assertEqualsWithDelta($live['gross'], $page->viewData('sel')['gross'], 0.001);
        $this->assertGreaterThan($run->items()->sole()->gross_pay, $page->viewData('sel')['gross']);
    }

    /** The weeks on offer are Payroll Records' weeks, Monday to Sunday, and nothing else. */
    public function test_the_weeks_on_offer_are_payroll_records_weeks(): void
    {
        $periods = $this->page()->assertDontSee('(monthly)')->viewData('periods');

        foreach ($periods as $p) {
            $from = Carbon::parse($p['from']);
            $this->assertTrue($from->isMonday(), $p['label']);
            $this->assertSame($from->copy()->addDays(6)->toDateString(), $p['to'], $p['label']);
        }

        // A range that is not one of those weeks is not a period here.
        $this->assertSame(self::WEEK, $this->page(['period' => '2026-09-01_2026-09-30'])->viewData('period')['key']);
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

    /** No run, no approval, and no submit step: one press settles the line. */
    public function test_a_contribution_is_marked_done_in_one_press(): void
    {
        $e = $this->worker();

        $this->mark($e, 'sss', 'done')->assertSessionHas('success');

        $line = PayrollRemittance::sole();
        $this->assertSame('done', $line->status);
        $this->assertSame($this->admin->id, $line->completed_by);
        $this->assertNotNull($line->completed_at);
        $this->assertSame('2026-09-07', $line->period_start);
        $this->assertEqualsWithDelta($this->open($e)->viewData('sel')['sss'], $line->amount, 0.001);

        $this->open($e, 'tracker')->assertSee('Remitted')->assertSee('by Admin');
    }

    /** The Submit button is gone; a pending line offers Mark done instead. */
    public function test_a_pending_line_offers_done_and_not_submit(): void
    {
        $e = $this->worker();

        $this->assertSame('Mark done', $this->row($e, 'sss')['actions'][0]['label']);
        $this->assertSame('done', $this->row($e, 'sss')['actions'][0]['action']);
        $this->assertSame('Mark paid', $this->row($e, 'net_pay')['actions'][0]['label']);

        $this->open($e, 'tracker')->assertDontSee('>Submit<', false);

        // And the action itself is no longer accepted.
        $this->mark($e, 'sss', 'submit')->assertSessionHasErrors('action');
        $this->assertSame(0, PayrollRemittance::count());
    }

    public function test_done_cannot_repeat_and_undo_walks_it_back(): void
    {
        $e = $this->worker();

        $this->mark($e, 'bir', 'done')->assertSessionHas('success');
        $this->mark($e, 'bir', 'done')->assertSessionHas('error');   // already remitted

        $this->mark($e, 'bir', 'undo');
        $line = PayrollRemittance::sole();
        $this->assertSame('pending', $line->status, 'undo goes back to pending, not to a middle step');
        $this->assertNull($line->completed_at, 'undoing takes the signature back off');
    }

    /**
     * Rows marked before the submit step was dropped still read Processing
     * and can still be settled — the state is understood, just unreachable.
     */
    public function test_a_line_left_processing_can_still_be_finished(): void
    {
        $e = $this->worker();

        PayrollRemittance::create([
            'employee_id' => $e->id, 'period_start' => '2026-09-07', 'period_end' => '2026-09-13',
            'kind' => 'sss', 'status' => 'submitted', 'amount' => 1.23,
            'submitted_by' => $this->admin->id, 'submitted_at' => now(),
        ]);

        $this->assertSame('Processing', $this->row($e, 'sss')['state']);

        $this->mark($e, 'sss', 'done')->assertSessionHas('success');
        $this->assertSame('done', PayrollRemittance::sole()->status);
    }

    public function test_net_pay_is_paid_or_not(): void
    {
        $e = $this->worker();

        $this->mark($e, 'net_pay', 'submit')->assertSessionHasErrors('action');
        $this->mark($e, 'net_pay', 'done')->assertSessionHas('success');

        $this->assertSame('done', PayrollRemittance::where('kind', 'net_pay')->sole()->status);
        $row = $this->row($e, 'net_pay');
        $this->assertSame('Done', $row['state'], 'the status reads Done');
        $this->assertSame('Paid', $row['done_text'], 'and the line still says which kind of done');
    }

    public function test_a_mark_belongs_to_its_worker(): void
    {
        $a = $this->worker('Alpha');
        $b = $this->worker('Bravo');

        $this->mark($a, 'sss', 'done');

        $this->assertSame('Done', $this->row($a, 'sss')['state']);
        $this->assertSame('Pending', $this->row($b, 'sss')['state']);
    }

    public function test_nothing_due_cannot_be_marked(): void
    {
        $e = $this->worker();

        // Week 36: no attendance, so no pay and nothing on it to remit.
        $this->mark($e, 'sss', 'done', '2026-08-31_2026-09-06')->assertSessionHas('error');
        $this->assertSame(0, PayrollRemittance::count());
    }

    // ── Marking several at once ──────────────────────────────────────────

    private function markMany(array $employees, string $period = self::WEEK)
    {
        return $this->actingAs($this->admin)->post(route('payroll-processing.track-many'), [
            'period'    => $period,
            'employees' => collect($employees)->map(fn ($e) => $e->id)->all(),
        ]);
    }

    /**
     * The whole point of the tick boxes: a week's remittances for several
     * workers settled in one press, instead of a line at a time.
     */
    public function test_several_workers_are_marked_done_in_one_action(): void
    {
        $a = $this->worker('Alpha');
        $b = $this->worker('Bravo');
        $c = $this->worker('Charlie');

        $this->markMany([$a, $b])->assertSessionHas('success');

        // Every line either of them owed — the contributions, the tax and the
        // net pay — is done, and signed.
        foreach ([$a, $b] as $e) {
            $lines = PayrollRemittance::where('employee_id', $e->id)->get();

            $this->assertSame(5, $lines->count(), $e->name . ': every kind was settled');
            $this->assertTrue($lines->every(fn ($l) => $l->status === 'done'), $e->name);
            $this->assertTrue($lines->every(fn ($l) => $l->completed_by === $this->admin->id), $e->name);
            $this->assertTrue($lines->every(fn ($l) => $l->amount > 0), $e->name . ': the amount is kept');
        }

        // Nobody who was not ticked is touched.
        $this->assertSame(0, PayrollRemittance::where('employee_id', $c->id)->count());
        $this->assertSame('Pending', $this->row($c, 'sss')['state']);
    }

    /** The list stops offering the ones already settled, and says so. */
    public function test_the_list_counts_what_is_left_to_settle(): void
    {
        $a = $this->worker('Alpha');
        $b = $this->worker('Bravo');

        $before = $this->people('tracker');
        $this->assertSame(10, $before->viewData('pending')['total'], 'five lines each');
        $before->assertSee('5 pending');

        $this->markMany([$a]);

        $after = $this->people('tracker');
        $this->assertSame(5, $after->viewData('pending')['total']);
        $this->assertSame([$b->id => 5], $after->viewData('pending')['by']);
        $after->assertSee('All settled');
    }

    /** Marking the same selection twice does not re-sign what is already done. */
    public function test_marking_again_changes_nothing_and_says_so(): void
    {
        $e = $this->worker();

        $this->markMany([$e])->assertSessionHas('success');
        $signed = PayrollRemittance::where('kind', 'sss')->sole()->completed_at;

        $this->markMany([$e])->assertSessionHas('error');

        $this->assertEquals($signed, PayrollRemittance::where('kind', 'sss')->sole()->completed_at);
        $this->assertSame(5, PayrollRemittance::count(), 'no second set of lines');
    }

    /** A worker with nothing on the week has nothing to mark, and no rows are invented. */
    public function test_a_worker_with_nothing_due_is_not_given_lines(): void
    {
        $idle = Employee::create([
            'name' => 'Nothing Doing', 'status' => Employee::STATUS_ACTIVE,
            'employment_type' => Employee::EMPLOYMENT_DAILY,
            'labor_type_id' => LaborType::create(['name' => 'Helper', 'daily_rate' => 640, 'ot_rate' => 125])->id,
            'shift_id' => Shift::where('crosses_midnight', false)->firstOrFail()->id,
            'rate_per_hour' => 80,
        ]);

        $this->markMany([$idle])->assertSessionHas('error');
        $this->assertSame(0, PayrollRemittance::count());
    }

    /** The tick boxes are the tracker's; the other two lists do not offer them. */
    public function test_only_the_tracker_list_offers_the_tick_boxes(): void
    {
        $this->worker();

        // The tick box itself, not the script's selector for it.
        $box = 'type="checkbox" name="employees[]"';

        $this->people('tracker')
            ->assertSee('Select all outstanding')
            ->assertSee($box, false);

        foreach (['workflow', 'payslip'] as $view) {
            $this->people($view)
                ->assertDontSee('Select all outstanding')
                ->assertDontSee($box, false);
        }
    }

    /** Nothing left outstanding, nothing to tick. */
    public function test_the_bulk_bar_goes_away_once_the_week_is_settled(): void
    {
        $e = $this->worker();

        $this->people('tracker')->assertSee('Select all outstanding');

        $this->markMany([$e]);

        $this->people('tracker')
            ->assertDontSee('Select all outstanding')
            ->assertSee('All settled');
    }

    /** A selection is still checked against the period and the roster. */
    public function test_marking_many_needs_a_real_period_and_somebody_to_mark(): void
    {
        $e = $this->worker();

        $this->actingAs($this->admin)
            ->post(route('payroll-processing.track-many'), ['period' => self::WEEK, 'employees' => []])
            ->assertSessionHasErrors('employees');

        $this->actingAs($this->admin)
            ->post(route('payroll-processing.track-many'), ['period' => '2026-02-30_2026-03-06', 'employees' => [$e->id]])
            ->assertNotFound();

        $this->assertSame(0, PayrollRemittance::count());
    }

    /** What was remitted is kept, even when the attendance behind it moves. */
    public function test_a_mark_keeps_what_it_came_to_when_the_pay_moves(): void
    {
        $e = $this->worker();
        $this->mark($e, 'sss', 'done');
        $remitted = PayrollRemittance::sole()->amount;

        Attendance::create([
            'employee_id' => $e->id, 'shift_id' => $e->shift_id, 'date' => '2026-09-09', 'session' => 'AM',
            'time_in' => '2026-09-09 08:00:00', 'time_out' => '2026-09-09 17:00:00',
        ]);

        $row = $this->row($e, 'sss');
        $this->assertSame('Done', $row['state']);
        $this->assertSame('Remitted', $row['done_text']);
        $this->assertEqualsWithDelta($remitted, $row['was'], 0.001);
        $this->assertGreaterThan($remitted, $row['amount']);

        $this->open($e, 'tracker')->assertSee('when marked');
    }

    /** A tracker with nothing on it says why, rather than looking broken. */
    public function test_no_contributions_is_explained(): void
    {
        PayrollRate::query()->delete();   // the rates fall back to zero

        $e = $this->worker();
        Attendance::where('employee_id', $e->id)->update(['time_out' => '2026-09-10 09:00:00']);   // under the tax line too

        $this->open($e, 'tracker')->assertSee('No contributions or tax were taken off this pay');

        $sss = $this->row($e, 'sss');
        $this->assertSame('Nothing due', $sss['state']);
        $this->assertSame([], $sss['actions']);

        // The net pay can still be released.
        $this->assertSame('Mark paid', $this->row($e, 'net_pay')['actions'][0]['label']);
    }

    /** Whoever files the remittance needs the worker's number with each agency. */
    public function test_the_tracker_shows_the_workers_agency_numbers(): void
    {
        $e = $this->worker();
        $e->forceFill([
            'sss_number'        => '34-1234567-8',
            'philhealth_number' => '12-345678901-2',
            'pagibig_number'    => '1234-5678-9012',
            'tin_number'        => '123-456-789-000',
        ])->save();

        $this->open($e, 'tracker')
            ->assertSee('SSS No.')->assertSee('34-1234567-8')
            ->assertSee('PhilHealth No.')->assertSee('12-345678901-2')
            ->assertSee('Pag-IBIG MID No.')->assertSee('1234-5678-9012')
            ->assertSee('TIN')->assertSee('123-456-789-000')
            ->assertDontSee('number on file');
    }

    /** A number the form never got is flagged, not left blank. */
    public function test_a_missing_agency_number_is_flagged(): void
    {
        $e = $this->worker();

        $this->open($e, 'tracker')
            ->assertSee('No SSS number on file')
            ->assertSee('No PhilHealth number on file')
            ->assertSee('No Pag-IBIG MID number on file')
            ->assertSee('No TIN on file');
    }

    public function test_an_unknown_line_or_period_is_not_found(): void
    {
        $e = $this->worker();

        $this->mark($e, 'gcash', 'done')->assertNotFound();
        $this->mark($e, 'sss', 'done', '2026-02-30_2026-03-06')->assertNotFound();
    }

    // ── The payslip ──────────────────────────────────────────────────────

    /**
     * The same slip Payroll Records hands out, printed from this page.
     *
     * It used to link out to the batch-print URL, and this test still asked
     * for that link long after both Print buttons became window.print() over
     * the sheet already rendered here — no window opened, so none left to
     * close. The rule now is that the slip and its Print button are both on
     * the page.
     */
    public function test_the_payslip_uses_the_payroll_records_format(): void
    {
        $e = $this->worker();

        $page = $this->open($e, 'payslip')
            ->assertSee('PAYSLIP')
            ->assertSee('Regular pay (1d)')
            ->assertSee('SSS (5.00%)')
            ->assertSee('− Deductions')
            ->assertSee('+ Bonus')
            ->assertSee('NET PAY')
            ->assertSee('Print / Save as PDF')
            ->assertSee('id="ppPrintOne"', false);

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

        $e = $this->worker();

        $sel  = $this->open($e, 'payslip')->viewData('sel');
        $slip = $this->open($e, 'payslip')->viewData('slip');

        $this->assertEqualsWithDelta(500.0, $slip['bonus'], 0.001);
        $this->assertEqualsWithDelta($sel['gross'], $slip['gross'], 0.001, 'the gross is wages; the bonus is not in it');
        $this->assertEqualsWithDelta($slip['gross'] - $slip['deductions'] + 500, $slip['net'], 0.011);
    }
}
