<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What the rail offers.
 *
 * Payroll Processing and Payslips came off it. A run is started from the
 * Dashboard and its payslips are opened from the run, so for the office they
 * were two more rows on a rail already short of room. A worker's own account
 * keeps Payslips: it has no run to open them from.
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

    public function test_the_office_rail_has_neither_payroll_processing_nor_payslips(): void
    {
        foreach ([User::ROLE_ADMIN, User::ROLE_STAFF, User::ROLE_PAYROLL] as $i => $role) {
            $rail = $this->rail($this->user($role, 'rail.office' . $i));

            $this->assertStringNotContainsString(route('payroll-processing.index'), $rail, "{$role} still has Payroll Processing");
            $this->assertStringNotContainsString(route('payslips.index'), $rail, "{$role} still has Payslips");
            $this->assertStringContainsString(url('/payroll-records'), $rail, "{$role} lost Payroll Records");
        }
    }

    public function test_a_workers_own_account_keeps_payslips(): void
    {
        $rail = $this->rail($this->user(User::ROLE_EMPLOYEE, 'rail.worker'));

        $this->assertStringContainsString(route('payslips.index'), $rail);
    }

    /** Off the rail is not off the system. */
    public function test_the_pages_themselves_stay_open(): void
    {
        $admin = $this->user(User::ROLE_ADMIN, 'rail.admin');

        $this->actingAs($admin)->get(route('payroll-processing.index'))->assertOk();
        $this->actingAs($admin)->get(route('payslips.index'))->assertOk();
    }
}
