<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\LaborType;
use App\Models\LeaveRequest;
use App\Models\Loan;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\User;
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
     * Finalising takes an advance's instalment from the advance, even when an
     * older loan for the same worker is still open. Collected oldest-first
     * regardless of kind, it would have paid the loan down instead.
     */
    public function test_finalising_takes_an_advance_instalment_from_the_advance_not_an_old_loan(): void
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
            'advance_deduction' => 300, 'total_deductions' => 300,
        ]);

        $this->actingAs($this->admin())
             ->post("/payroll-processing/{$run->id}/finalize", ['confirm' => 1]);

        $this->assertSame('finalized', $run->fresh()->status);
        $this->assertSame(700.0, (float) $advance->fresh()->balance, 'the advance was not collected');
        $this->assertSame(2000.0, (float) $loan->fresh()->balance, 'the advance was taken off the old loan');
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
}
