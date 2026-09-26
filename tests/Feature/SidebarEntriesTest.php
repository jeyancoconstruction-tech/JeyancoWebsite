<?php

namespace Tests\Feature;

use App\Models\PayrollRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What the rail offers.
 *
 * Payroll Processing leads the Payroll group, above Payroll Records. Payslips
 * are off the rail for everyone: they open from their payroll run, and workers
 * — who would have been the one reason to keep them there — have no web
 * account at all. They use the kiosk.
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

    /** Payroll Processing was removed on 2026-09-26; Payroll Records leads the group. */
    public function test_payroll_records_leads_the_payroll_group_on_the_office_rail(): void
    {
        foreach ([User::ROLE_ADMIN, User::ROLE_HR] as $i => $role) {
            $rail = $this->rail($this->user($role, 'rail.office' . $i));

            $this->assertStringContainsString('href="' . url('/payroll-records') . '"', $rail, "{$role} lost Payroll Records");
            $this->assertStringNotContainsString('payroll-processing', $rail, "{$role} still has Payroll Processing");
            $this->assertStringNotContainsString('Payroll Processing', $rail);
            $this->assertStringNotContainsString(route('payslips.index'), $rail, "{$role} still has Payslips");
        }
    }

    /** Workers use the kiosk; there is no web role to give them. */
    public function test_workers_have_no_web_role_and_payslips_stay_off_every_rail(): void
    {
        $this->assertArrayNotHasKey('employee', User::ROLES);

        $this->assertSame(['admin', 'hr'], array_keys(User::ROLES), 'two roles: Administrator and HR');

        $rail = $this->rail($this->user(User::ROLE_HR, 'rail.hr'));

        $this->assertStringNotContainsString(route('payslips.index'), $rail);
    }

    public function test_payroll_processing_is_gone_and_its_address_opens_payroll_records(): void
    {
        $admin = $this->user(User::ROLE_ADMIN, 'rail.admin');
        $run   = PayrollRun::create([
            'code' => PayrollRun::nextCode(), 'period_start' => now()->subWeek(),
            'period_end' => now(), 'status' => 'calculated',
        ]);

        // A bookmark lands on the page it was drawn from, not on a 404.
        $this->actingAs($admin)->get('/payroll-processing')
             ->assertRedirect(route('payroll-records'));

        // Its run pages, run actions and remittance tracker went with it.
        $this->actingAs($admin)->get('/payroll-processing/' . $run->id)->assertNotFound();
        $this->actingAs($admin)->post('/payroll-processing/' . $run->id . '/finalize', ['confirm' => 1])->assertNotFound();
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('payroll-processing.index'));
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('payroll-processing.track-many'));

        // The runs themselves are untouched: their payslips still open.
        $this->assertModelExists($run);
        $this->assertNotContains('payroll-processing', \App\Support\Modules::all());
    }

    /** Off the rail is not off the system. */
    public function test_payslips_still_open(): void
    {
        $this->actingAs($this->user(User::ROLE_ADMIN, 'rail.admin2'))
             ->get(route('payslips.index'))
             ->assertOk();
    }
}
