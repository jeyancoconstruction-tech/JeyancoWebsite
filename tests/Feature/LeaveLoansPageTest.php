<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\LaborType;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Leave & Loans: leave and loans on one page, and overtime on neither.
 *
 * Overtime used to be filed by hand as a claim, on a tab beside leave, and
 * paid on top of the day. It is counted from attendance now — the time past
 * the shift's regular hours — so the tab went, and Loans & Advances, which had
 * a sidebar entry of its own, moved into its place.
 */
class LeaveLoansPageTest extends TestCase
{
    use RefreshDatabase;

    private ?User $admin = null;

    /** Memoised: several requests in one test, and username is unique. */
    private function admin(): User
    {
        return $this->admin ??= User::create([
            'name' => 'Admin', 'username' => 'admin.leaveloans', 'password' => 'secret123',
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

    public function test_the_page_holds_leave_and_loans_and_no_overtime(): void
    {
        $page = $this->actingAs($this->admin())->get('/leave-loans')->assertOk();

        $page->assertSee('Leave & Loans')
             ->assertSee('File Leave')
             ->assertSee(route('leave.index', ['tab' => 'loans']), false)
             ->assertDontSee('File Overtime')
             ->assertDontSee('No overtime filed')
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

    public function test_the_loans_tab_lists_loans_and_a_new_one_returns_to_it(): void
    {
        $emp   = $this->worker('Pedro');
        $admin = $this->admin();
        $tab   = route('leave.index', ['tab' => 'loans']);

        $this->actingAs($admin)
             ->from($tab)
             ->post(route('loans.store'), [
                 'employee_id' => $emp->id, 'type' => 'loan', 'principal' => 3000,
                 'installment' => 500, 'schedule' => 'per_payroll', 'issued_on' => '2026-09-12',
             ])
             ->assertRedirect($tab);

        $page = $this->actingAs($admin)->get($tab)->assertOk();

        $this->assertSame('loans', $page->viewData('tab'));
        $this->assertCount(1, $page->viewData('loans'));
        $page->assertSee('Pedro')->assertSee('New Loan / Advance')->assertSee('₱3,000.00');
    }

    public function test_the_old_addresses_land_on_the_merged_page(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get('/loans')
             ->assertRedirect(route('leave.index', ['tab' => 'loans']));
        $this->actingAs($admin)->get('/leave-overtime')
             ->assertRedirect('/leave-loans');

        // A bookmark to the overtime tab opens on leave rather than failing.
        $this->actingAs($admin)->get('/leave-loans?tab=overtime')
             ->assertOk()
             ->assertViewHas('tab', 'leave');
    }

    public function test_a_role_without_loans_sees_the_leave_tab_only(): void
    {
        $supervisor = User::create([
            'name' => 'Supervisor', 'username' => 'sup.leaveloans', 'password' => 'secret123',
            'role' => User::ROLE_SUPERVISOR, 'is_active' => true,
        ]);

        $page = $this->actingAs($supervisor)->get('/leave-loans')->assertOk();
        $this->assertFalse($page->viewData('canLoans'));
        $page->assertDontSee(route('leave.index', ['tab' => 'loans']), false);

        $this->actingAs($supervisor)->get('/leave-loans?tab=loans')->assertForbidden();
        $this->actingAs($supervisor)->get('/loans')->assertForbidden();
    }

    public function test_the_sidebar_has_one_entry_for_both(): void
    {
        $page = $this->actingAs($this->admin())->get('/leave-loans')->assertOk();

        $page->assertSee('href="' . route('leave.index') . '"', false)
             ->assertDontSee('href="' . route('loans.index') . '"', false)
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
}
