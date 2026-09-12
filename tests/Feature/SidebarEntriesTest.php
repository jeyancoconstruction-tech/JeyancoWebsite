<?php

namespace Tests\Feature;

use App\Models\PayrollRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What the rail offers.
 *
 * Payroll Processing leads the Payroll group, above Payroll Records, and its
 * page is empty for now while it is redrawn. Payslips are off the office rail;
 * a worker's own account keeps them, having no payroll run to open them from.
 */
class SidebarEntriesTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, string $username): User
    {
        return User::create([
            'name' => ucfirst($role), 'username' => $username, 'password' => 'secret123',
            'role' => $role, 'is_active' => true,
        ]);
    }

    /** Only the rail, not the page: a page may link the same routes itself. */
    private function rail(User $user): string
    {
        $html  = $this->actingAs($user)->get('/leave-advances')->assertOk()->getContent();
        $start = strpos($html, '<nav class="nav-menu">');
        $this->assertNotFalse($start, 'sidebar nav not found in the response');

        return substr($html, $start, strpos($html, '</nav>', $start) - $start);
    }

    public function test_payroll_processing_leads_the_payroll_group_on_the_office_rail(): void
    {
        foreach ([User::ROLE_ADMIN, User::ROLE_STAFF, User::ROLE_PAYROLL] as $i => $role) {
            $rail = $this->rail($this->user($role, 'rail.office' . $i));

            $processing = strpos($rail, 'href="' . route('payroll-processing.index') . '"');
            $records    = strpos($rail, 'href="' . url('/payroll-records') . '"');

            $this->assertNotFalse($processing, "{$role} has no Payroll Processing");
            $this->assertNotFalse($records, "{$role} lost Payroll Records");
            $this->assertLessThan($records, $processing, 'Payroll Processing sits above Payroll Records');
            $this->assertStringNotContainsString(route('payslips.index'), $rail, "{$role} still has Payslips");
        }
    }

    public function test_a_workers_own_account_keeps_payslips(): void
    {
        $rail = $this->rail($this->user(User::ROLE_EMPLOYEE, 'rail.worker'));

        $this->assertStringContainsString(route('payslips.index'), $rail);
        $this->assertStringNotContainsString(route('payroll-processing.index'), $rail);
    }

    public function test_the_payroll_processing_page_is_empty_for_now(): void
    {
        $admin = $this->user(User::ROLE_ADMIN, 'rail.admin');
        $run   = PayrollRun::create([
            'code' => PayrollRun::nextCode(), 'period_start' => now()->subWeek(),
            'period_end' => now(), 'status' => 'calculated',
        ]);

        $this->actingAs($admin)->get(route('payroll-processing.index'))
             ->assertOk()
             ->assertDontSee($run->code)
             ->assertDontSee('New Payroll Run');

        // The runs themselves are untouched, and each still opens.
        $this->actingAs($admin)->get(route('payroll-processing.show', $run))
             ->assertOk()
             ->assertSee($run->code);
    }

    /** Off the rail is not off the system. */
    public function test_payslips_still_open(): void
    {
        $this->actingAs($this->user(User::ROLE_ADMIN, 'rail.admin2'))
             ->get(route('payslips.index'))
             ->assertOk();
    }
}
