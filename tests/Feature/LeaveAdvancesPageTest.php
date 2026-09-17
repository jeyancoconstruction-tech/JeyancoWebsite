<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\LaborType;
use App\Models\LeaveRequest;
use App\Models\Loan;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Leave & Advances: leave and cash advances on one page, and nothing else.
 *
 * The page shed two things in a day. Overtime was a tab, filed by hand as a
 * claim and paid on top of the day; it is counted from attendance now. Loans
 * sat beside the cash advances; they are no longer issued. What is on file of
 * either stays on file, but none of it is paid, charged or listed.
 */
class LeaveAdvancesPageTest extends TestCase
{
    use RefreshDatabase;

    private ?User $admin = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Payroll Processing reaches Holiday::typeMap(), which is MySQL's
        // YEAR(); SQLite has to be taught it before a run can be calculated.
        $pdo = DB::connection()->getPdo();
        if (DB::connection()->getDriverName() === 'sqlite' && method_exists($pdo, 'sqliteCreateFunction')) {
            $pdo->sqliteCreateFunction('YEAR',  fn ($d) => $d ? (int) substr((string) $d, 0, 4) : null, 1);
            $pdo->sqliteCreateFunction('MONTH', fn ($d) => $d ? (int) substr((string) $d, 5, 2) : null, 1);
        }
    }

    /** Memoised: several requests in one test, and username is unique. */
    private function admin(): User
    {
        return $this->admin ??= User::create([
            'name' => 'Admin', 'username' => 'admin.leaveadvances', 'password' => 'secret123',
            'role' => User::ROLE_ADMIN, 'is_active' => true,
        ]);
    }

    private function worker(string $name): Employee
    {
        return Employee::create([
            'name'            => $name,
            'status'          => Employee::STATUS_ACTIVE,
            'employment_type' => Employee::EMPLOYMENT_DAILY,
            'labor_type_id'   => LaborType::create(['name' => 'Mason ' . $name, 'daily_rate' => 800, 'ot_rate' => 125])->id,
            'rate_per_hour'   => 100,
        ]);
    }

    private function onFile(Employee $e, string $type, float $principal, float $installment, string $issued): Loan
    {
        return Loan::create([
            'employee_id' => $e->id, 'type' => $type, 'principal' => $principal, 'balance' => $principal,
            'installment' => $installment, 'schedule' => 'per_payroll', 'issued_on' => $issued, 'status' => 'active',
        ]);
    }

    private function advanceTab(): string
    {
        return route('leave.index', ['tab' => 'advances']);
    }

    public function test_the_page_holds_leave_and_cash_advances_only(): void
    {
        $page = $this->actingAs($this->admin())->get('/leave-advances')->assertOk();

        $page->assertSee('Leave & Advances')
             ->assertSee('File Leave')
             ->assertSee($this->advanceTab(), false)
             ->assertDontSee('File Overtime')
             ->assertDontSee('Loans & Advances')
             ->assertDontSee('tab=overtime', false);
    }

    public function test_overtime_can_no_longer_be_filed(): void
    {
        $emp = $this->worker('Juan');

        $this->assertFalse(Route::has('overtime.store'));

        $this->actingAs($this->admin())
             ->post('/leave-overtime/overtime', ['employee_id' => $emp->id, 'date' => '2026-09-12', 'hours' => 3])
             ->assertNotFound();

        $this->assertSame(0, DB::table('overtime_requests')->count());
    }

    public function test_a_new_cash_advance_is_listed_on_its_tab(): void
    {
        $emp   = $this->worker('Pedro');
        $admin = $this->admin();

        $this->actingAs($admin)
             ->from($this->advanceTab())
             ->post(route('loans.store'), [
                 'employee_id' => $emp->id, 'principal' => 3000, 'installment' => 500,
                 'schedule' => 'per_payroll', 'issued_on' => '2026-09-12',
             ])
             ->assertRedirect($this->advanceTab());

        $this->assertSame(Loan::ADVANCE, Loan::sole()->type);

        $page = $this->actingAs($admin)->get($this->advanceTab())->assertOk();

        $this->assertSame('advances', $page->viewData('tab'));
        $this->assertCount(1, $page->viewData('advances'));
        $page->assertSee('Pedro')->assertSee('New Cash Advance')->assertSee('₱3,000.00');
    }

    public function test_a_loan_can_no_longer_be_issued(): void
    {
        $emp = $this->worker('Maria');

        // Whatever an old form, or a crafted request, says it is.
        $this->actingAs($this->admin())->post(route('loans.store'), [
            'employee_id' => $emp->id, 'type' => 'loan', 'principal' => 5000, 'installment' => 1000,
            'schedule' => 'per_payroll', 'issued_on' => '2026-09-12',
        ])->assertRedirect();

        $this->assertSame(0, Loan::where('type', 'loan')->count());
        $this->assertSame(1, Loan::advances()->count());
    }

    public function test_an_old_loan_is_neither_listed_nor_charged_nor_editable(): void
    {
        $emp   = $this->worker('Old Debtor');
        $admin = $this->admin();
        $loan  = $this->onFile($emp, 'loan', 2000, 500, '2026-09-01');

        $this->assertCount(0, $this->actingAs($admin)->get($this->advanceTab())->viewData('advances'));

        // A payroll line for the worker, carried by a day of paid leave.
        LeaveRequest::create([
            'employee_id' => $emp->id, 'leave_type' => 'vacation',
            'starts_on' => '2026-09-08', 'ends_on' => '2026-09-08', 'days' => 1,
            'is_paid' => true, 'status' => 'approved',
        ]);

        $this->actingAs($admin)->post('/payroll-processing', [
            'period_start' => '2026-09-07', 'period_end' => '2026-09-13',
        ])->assertRedirect();

        $item = PayrollRun::latest('id')->first()->items()->where('employee_id', $emp->id)->sole();
        $this->assertSame(0.0, (float) $item->loan_deduction, 'an old loan was charged');

        // Nor can it be paid against or edited through the advance forms.
        $this->actingAs($admin)
             ->post(route('loans.payment', $loan), ['amount' => 100, 'deducted_on' => '2026-09-12'])
             ->assertNotFound();
        $this->actingAs($admin)
             ->put(route('loans.update', $loan), ['status' => 'paid'])
             ->assertNotFound();

        $this->assertSame(2000.0, (float) $loan->fresh()->balance);
        $this->assertSame('active', $loan->fresh()->status);
    }

    /**
     * Finalising does not collect a cash advance any more.
     *
     * Payroll takes an advance's instalment from the application's own
     * schedule now, every period — see CashAdvanceCollectionTest — so posting
     * a second collection here would charge the worker twice. An old loan is
     * not on that schedule, and still settles the way it did.
     */
    public function test_finalising_settles_an_old_loan_and_leaves_the_advance_to_the_schedule(): void
    {
        $emp     = $this->worker('Both Kinds');
        $loan    = $this->onFile($emp, 'loan', 2000, 500, '2026-08-01');
        $advance = $this->onFile($emp, Loan::ADVANCE, 1000, 300, '2026-09-01');

        $run = PayrollRun::create([
            'code' => PayrollRun::nextCode(), 'period_start' => '2026-09-07',
            'period_end' => '2026-09-13', 'status' => 'approved',
        ]);
        PayrollRunItem::create([
            'payroll_run_id' => $run->id, 'employee_id' => $emp->id, 'employee_name' => $emp->name,
            'loan_deduction' => 500, 'advance_deduction' => 300, 'total_deductions' => 800,
        ]);

        $this->actingAs($this->admin())
             ->post("/payroll-processing/{$run->id}/finalize", ['confirm' => 1]);

        $this->assertSame('finalized', $run->fresh()->status);
        $this->assertSame(1500.0, (float) $loan->fresh()->balance, 'the old loan still settles here');
        $this->assertSame(0, $advance->deductions()->count(),
            'and the advance is left to its own schedule, so it is not collected twice');
    }

    public function test_the_old_addresses_land_on_the_merged_page(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get('/loans')->assertRedirect($this->advanceTab());
        $this->actingAs($admin)->get('/leave-loans')->assertRedirect('/leave-advances');
        $this->actingAs($admin)->get('/leave-overtime')->assertRedirect('/leave-advances');

        // A bookmark to a tab that is gone opens on leave rather than failing.
        foreach (['overtime', 'loans'] as $gone) {
            $this->actingAs($admin)->get('/leave-advances?tab=' . $gone)
                 ->assertOk()
                 ->assertViewHas('tab', 'leave');
        }
    }

    public function test_a_role_without_advances_sees_the_leave_tab_only(): void
    {
        $supervisor = User::create([
            'name' => 'Supervisor', 'username' => 'sup.leaveadvances', 'password' => 'secret123',
            'role' => User::ROLE_SUPERVISOR, 'is_active' => true,
        ]);

        $page = $this->actingAs($supervisor)->get('/leave-advances')->assertOk();
        $this->assertFalse($page->viewData('canAdvances'));
        $page->assertDontSee($this->advanceTab(), false);

        $this->actingAs($supervisor)->get('/leave-advances?tab=advances')->assertForbidden();
        $this->actingAs($supervisor)->get('/loans')->assertForbidden();
    }

    public function test_the_sidebar_has_one_entry_for_both(): void
    {
        $page = $this->actingAs($this->admin())->get('/leave-advances')->assertOk();

        $page->assertSee('href="' . route('leave.index') . '"', false)
             ->assertDontSee('href="' . route('loans.index') . '"', false)
             ->assertDontSee('Leave & Loans')
             ->assertDontSee('Leave & Overtime');
    }

    /** The Overtime Report reads what payroll paid, not a list of claims. */
    public function test_the_overtime_report_shows_overtime_as_payroll_paid_it(): void
    {
        $emp = $this->worker('Overtime Man');

        $run = PayrollRun::create([
            'code' => PayrollRun::nextCode(), 'period_start' => '2026-09-07',
            'period_end' => '2026-09-13', 'status' => 'finalized',
        ]);
        PayrollRunItem::create([
            'payroll_run_id' => $run->id, 'employee_id' => $emp->id, 'employee_name' => $emp->name,
            'ot_hours' => 2, 'overtime_pay' => 312.5, 'gross_pay' => 1112.5, 'net_pay' => 1112.5,
        ]);

        // A claim still on file is not what was paid, so it is not reported.
        DB::table('overtime_requests')->insert([
            'employee_id' => $emp->id, 'date' => '2026-09-08', 'hours' => 5, 'hourly_rate' => 100,
            'multiplier' => 1.25, 'amount' => 625, 'status' => 'approved',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $rows = $this->actingAs($this->admin())
            ->get('/payroll-reports?report=overtime&run=' . $run->id)
            ->assertOk()
            ->viewData('rows');

        $this->assertCount(1, $rows);
        $this->assertSame('Overtime Man', $rows[0]['label']);
        $this->assertEqualsWithDelta(2.0, $rows[0]['days'], 0.001, 'the hours column');
        $this->assertEqualsWithDelta(312.5, $rows[0]['gross'], 0.001);
    }

    public function test_the_advances_report_lists_cash_advances_only(): void
    {
        $emp     = $this->worker('Reported');
        $advance = $this->onFile($emp, Loan::ADVANCE, 1000, 200, '2026-09-01');
        $advance->update(['balance' => 600]);
        $this->onFile($emp, 'loan', 5000, 1000, '2026-08-01');

        $rows = $this->actingAs($this->admin())
            ->get('/payroll-reports?report=advances')
            ->assertOk()
            ->viewData('rows');

        $this->assertCount(1, $rows, 'an old loan is not a cash advance');
        $this->assertEqualsWithDelta(1000.0, $rows[0]['gross'], 0.001);
        $this->assertEqualsWithDelta(600.0, $rows[0]['net'], 0.001);
    }

    /**
     * Every form on this page is laid out the same way.
     *
     * Michael pointed at the New Cash Advance modal: "kung ano yung format
     * kapag nag a-add ng cash advances, same format din dapat kapag mag
     * e-edit ng installment." The two forms behind a row's menu were built
     * narrower, hinted with a different class and missing the required-field
     * note in the footer, so the same instalment looked like a different kind
     * of thing depending on which way you reached it.
     */
    public function test_the_advance_forms_share_one_layout(): void
    {
        $e    = $this->worker('Lawrence Bernas');
        $loan = $this->onFile($e, Loan::ADVANCE, 3000, 500, '2026-09-14');

        $html = $this->actingAs($this->admin())->get($this->advanceTab())->assertOk()->getContent();

        // One form's markup, from its id to the end of it.
        $form = function (string $id) use ($html): string {
            $at = strpos($html, 'id="' . $id . '"');
            $this->assertNotFalse($at, $id . ' is on the page');

            return substr($html, $at, strpos($html, '</' . 'form>', $at) - $at);
        };

        // Recording an advance, correcting its instalment, and handing a
        // payment in at the office: one layout between the three of them.
        foreach (['advanceModal', 'instModal' . $loan->id, 'payModal' . $loan->id] as $id) {
            $markup = $form($id);

            $this->assertStringContainsString('mod-form-grid', $markup, $id . ' lays its fields on the grid');
            $this->assertStringContainsString('emp-foot-note', $markup, $id . ' says which fields are required');
            $this->assertStringNotContainsString('max-width:460px', $markup, $id . ' is not sized by hand');
            $this->assertStringNotContainsString('mod-person-sub', $markup, $id . ' hints with the field style');
        }

        $this->assertStringContainsString('payrolls to collect.', $form('instModal' . $loan->id));
    }

    // ── Leave has no approval step ───────────────────────────────────────

    /**
     * Filed is decided.
     *
     * Michael: "remove pending, rejected since the one who editing of this
     * system is the owner and hr and staff only that's why there's no reason
     * to put approve right there." The people filing leave are the ones who
     * would approve it, so a request that sat at Pending only kept a signed-off
     * day off out of payroll until somebody pressed a second button.
     */
    public function test_filed_leave_counts_straight_away(): void
    {
        $emp = $this->worker('Lawrence Bernas');

        $this->actingAs($this->admin())
             ->from(route('leave.index'))
             ->post(route('leave.store'), [
                 'employee_id' => $emp->id, 'leave_type' => 'sick',
                 'starts_on' => '2026-09-16', 'ends_on' => '2026-09-18', 'is_paid' => 1,
             ])
             ->assertRedirect(route('leave.index'))
             ->assertSessionHas('success', 'Leave filed.');

        $leave = LeaveRequest::sole();

        $this->assertSame('approved', $leave->status);
        $this->assertSame($this->admin()->id, $leave->filed_by);
        $this->assertSame($this->admin()->id, $leave->approved_by, 'whoever filed it decided it');
        $this->assertNotNull($leave->approved_at);
        $this->assertTrue(LeaveRequest::approved()->whereKey($leave->id)->exists(), 'so payroll reads it');
    }

    /** A leave written without a status is approved, not stranded on the old default. */
    public function test_a_leave_written_without_a_status_is_approved(): void
    {
        $leave = LeaveRequest::create([
            'employee_id' => $this->worker('Somebody')->id, 'leave_type' => 'vacation',
            'starts_on' => '2026-09-16', 'ends_on' => '2026-09-16', 'days' => 1, 'is_paid' => true,
        ]);

        $this->assertSame('approved', $leave->fresh()->status);
    }

    /** Nothing on the page offers Pending, Rejected, Approve or Reject. */
    public function test_there_is_no_pending_or_rejected_left_to_choose(): void
    {
        $this->assertSame(['approved', 'cancelled'], array_keys(LeaveRequest::STATUSES));

        LeaveRequest::create([
            'employee_id' => $this->worker('Lawrence Bernas')->id, 'leave_type' => 'sick',
            'starts_on' => '2026-09-16', 'ends_on' => '2026-09-18', 'days' => 3, 'is_paid' => true,
        ]);

        $html = $this->actingAs($this->admin())->get(route('leave.index'))->assertOk()->getContent();

        // The status filter, on its own: "Pending" is a word other parts of
        // the page may use about registrations, which is a different thing.
        $at     = strpos($html, 'id="lstatus"');
        $filter = substr($html, $at, strpos($html, '</' . 'select>', $at) - $at);

        $this->assertStringContainsString('Approved', $filter);
        $this->assertStringContainsString('Cancelled', $filter);
        $this->assertStringNotContainsString('Pending', $filter);
        $this->assertStringNotContainsString('Rejected', $filter);

        $this->assertStringNotContainsString('value="rejected"', $html);
        $this->assertStringNotContainsString('> Approve<', $html);
        $this->assertStringNotContainsString('> Reject<', $html);
        $this->assertStringNotContainsString('awaiting approval', $html);
        $this->assertStringContainsString('Filed by', $html);
        $this->assertStringContainsString('value="cancelled"', $html, 'a filed leave can still be called off');
    }

    /**
     * Rejecting was the only way to take back a leave filed in error, and it
     * went with the approval step. Cancelling is that way now — and a leave
     * cancelled by mistake can be put back.
     */
    public function test_a_leave_can_be_cancelled_and_restored_but_not_rejected(): void
    {
        $leave = LeaveRequest::create([
            'employee_id' => $this->worker('Lawrence Bernas')->id, 'leave_type' => 'sick',
            'starts_on' => '2026-09-16', 'ends_on' => '2026-09-18', 'days' => 3, 'is_paid' => true,
            'filed_by' => $this->admin()->id, 'approved_by' => $this->admin()->id, 'approved_at' => now(),
        ]);

        $decide = fn (string $decision) => $this->actingAs($this->admin())
            ->from(route('leave.index'))
            ->patch(route('leave.decide', ['id' => $leave->id]), ['decision' => $decision]);

        $decide('cancelled')->assertSessionHas('success', 'Leave cancelled.');
        $this->assertSame('cancelled', $leave->fresh()->status);
        $this->assertSame($this->admin()->id, $leave->fresh()->approved_by, 'who filed it is kept');

        $decide('approved')->assertSessionHas('success', 'Leave restored.');
        $this->assertSame('approved', $leave->fresh()->status);

        foreach (['rejected', 'pending'] as $retired) {
            $decide($retired)->assertSessionHasErrors('decision');
            $this->assertSame('approved', $leave->fresh()->status, "{$retired} is not a decision any more");
        }
    }

    /** Rows already on the retired statuses are moved off them, not stranded. */
    public function test_the_migration_moves_old_rows_off_the_retired_statuses(): void
    {
        $emp   = $this->worker('Lawrence Bernas');
        $filer = $this->admin();
        $row   = fn (string $status) => DB::table('leave_requests')->insertGetId([
            'employee_id' => $emp->id, 'leave_type' => 'sick', 'starts_on' => '2026-09-16',
            'ends_on' => '2026-09-16', 'days' => 1, 'is_paid' => true, 'status' => $status,
            'filed_by' => $filer->id, 'created_at' => '2026-09-15 10:00:00', 'updated_at' => '2026-09-15 10:00:00',
        ]);

        $pending   = $row('pending');
        $rejected  = $row('rejected');
        $cancelled = $row('cancelled');

        (require database_path('migrations/2026_09_17_150000_leave_has_no_approval_step.php'))->up();

        $status = fn (int $id) => DB::table('leave_requests')->where('id', $id)->value('status');

        $this->assertSame('approved', $status($pending), 'pending is approved, as if filed today');
        $this->assertSame($filer->id, (int) DB::table('leave_requests')->where('id', $pending)->value('approved_by'),
            'credited to whoever filed it');
        $this->assertNotNull(DB::table('leave_requests')->where('id', $pending)->value('approved_at'));

        $this->assertSame('cancelled', $status($rejected), 'rejected is cancelled — it still pays nothing');
        $this->assertSame('cancelled', $status($cancelled), 'and what was already settled is left alone');
    }

    /**
     * A leave whose days have all gone by reads Completed.
     *
     * Michael: "after matapos ang leave sa calendar like lahat ng araw ng
     * leave nya dapat may option den sa status like tapos na."
     *
     * Read off the calendar, never written. Payroll pays leave by its stored
     * "approved", so a finished leave written as anything else would drop out
     * of the weeks it was paid in.
     */
    public function test_a_leave_whose_days_have_all_passed_reads_completed(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-17 14:00:00', 'Asia/Manila'));

        $emp   = $this->worker('Lawrence Bernas');
        $filed = fn (string $from, string $to, string $status = 'approved') => LeaveRequest::create([
            'employee_id' => $emp->id, 'leave_type' => 'sick', 'starts_on' => $from, 'ends_on' => $to,
            'days' => 1, 'is_paid' => true, 'status' => $status,
        ]);

        $over      = $filed('2026-09-14', '2026-09-16');   // ended yesterday
        $lastDay   = $filed('2026-09-15', '2026-09-17');   // today is its last day
        $running   = $filed('2026-09-16', '2026-09-18');
        $ahead     = $filed('2026-09-21', '2026-09-22');
        $calledOff = $filed('2026-09-01', '2026-09-02', 'cancelled');

        $this->assertSame('completed', $over->display_status);
        $this->assertSame('Completed', $over->status_label);
        $this->assertSame('approved', $lastDay->display_status, 'not over until its last day is');
        $this->assertSame('approved', $running->display_status);
        $this->assertSame('approved', $ahead->display_status);
        $this->assertSame('cancelled', $calledOff->display_status, 'called off stays called off, however long ago');

        $this->assertSame('approved', $over->fresh()->status, 'nothing is written');

        // Each row is under exactly one option of the filter.
        $ids = fn (string $status) => $this->actingAs($this->admin())
            ->get(route('leave.index', ['tab' => 'leave', 'status' => $status]))->assertOk()
            ->viewData('leave')->pluck('id')->sort()->values()->all();

        $this->assertSame([$over->id], $ids('completed'));
        $this->assertSame([$lastDay->id, $running->id, $ahead->id], $ids('approved'));
        $this->assertSame([$calledOff->id], $ids('cancelled'));

        $html = $this->actingAs($this->admin())->get(route('leave.index'))->assertOk()->getContent();
        $at   = strpos($html, 'id="lstatus"');

        $this->assertStringContainsString('Completed',
            substr($html, $at, strpos($html, '</' . 'select>', $at) - $at), 'it is an option in the filter');
        $this->assertStringContainsString('mod-badge ok"><span class="dot"></span>Completed', $html,
            'and the row says so, in the colour a settled advance uses');

        // Come the next day, the one that ended today is over too.
        Carbon::setTestNow(Carbon::parse('2026-09-18 00:05:00', 'Asia/Manila'));

        $this->assertSame('completed', $lastDay->fresh()->display_status);
    }
}
