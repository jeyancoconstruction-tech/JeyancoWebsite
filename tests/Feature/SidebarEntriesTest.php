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

    /** Removed on 2026-09-26 at Michael's request, the same way. */
    public function test_project_assignment_is_gone_and_its_address_opens_sites(): void
    {
        foreach ([User::ROLE_ADMIN, User::ROLE_HR] as $i => $role) {
            $rail = $this->rail($this->user($role, 'rail.project' . $i));

            $this->assertStringNotContainsString('project-assignments', $rail, "{$role} still has Project Assignment");
            $this->assertStringNotContainsString('Project Assignment', $rail);
            $this->assertStringContainsString('href="' . route('sites.index') . '"', $rail, "{$role} lost Sites");
        }

        $admin = $this->user(User::ROLE_ADMIN, 'rail.project.admin');

        // A bookmark lands on the page it sat under, not on a 404.
        $this->actingAs($admin)->get('/project-assignments')
             ->assertRedirect(route('sites.index'));

        // Its actions and its place in the permissions went with it.
        $this->actingAs($admin)->patch('/project-assignments/1/end')->assertNotFound();
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('assignments.index'));
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('assignments.store'));
        $this->assertNotContains('assignments', \App\Support\Modules::all());
        $this->assertNotContains('assignments', \App\Support\Live::TOPICS);

        // The assignments already on file stay in the database.
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('project_assignments'));
    }

    /** Michael, 2026-09-26: Payroll Settings' four tabs became its sub-items. */
    public function test_payroll_settings_carries_its_four_sections_like_payroll_records(): void
    {
        $admin = $this->user(User::ROLE_ADMIN, 'rail.settings');

        $html = $this->actingAs($admin)->get(route('settings.index', ['tab' => 'labor']))->assertOk()->getContent();
        $rail = substr($html, strpos($html, '<nav class="nav-menu">'));
        $rail = substr($rail, 0, strpos($rail, '</nav>'));

        $this->assertStringContainsString('<button type="button" class="nav-link nav-parent has-on" id="navSettingsBtn" aria-controls="navSubSettings" aria-expanded="true">', $rail);
        foreach (['payroll' => 'Multipliers &amp; Deductions', 'attendance' => 'Work Schedule', 'labor' => 'Labor Types', 'holiday' => 'Holidays'] as $key => $name) {
            $this->assertStringContainsString('href="' . route('settings.index', ['tab' => $key]) . '" data-settings-pane="' . $key . '"', $rail);
            $this->assertStringContainsString($name, $rail);
        }
        $this->assertMatchesRegularExpression('~class="nav-sub-link on" href="' . preg_quote(route('settings.index', ['tab' => 'labor']), '~') . '" data-settings-pane="labor"\s+aria-current="page"~', $rail);
        $this->assertStringContainsString('<h1 class="page-head-title">Labor Types</h1>', $html);

        // Elsewhere they are folded, and Payroll Records is not lit by them.
        $rail = $this->rail($admin);
        $this->assertStringContainsString('<div class="nav-sub folded" id="navSubSettings">', $rail);
        $this->assertStringContainsString('aria-controls="navSubSettings" aria-expanded="false"', $rail);

        // HR has no Payroll Settings at all.
        $this->assertStringNotContainsString('navSubSettings', $this->rail($this->user(User::ROLE_HR, 'rail.settings.hr')));
    }

    /** Off the rail is not off the system. */
    public function test_payslips_still_open(): void
    {
        $this->actingAs($this->user(User::ROLE_ADMIN, 'rail.admin2'))
             ->get(route('payslips.index'))
             ->assertOk();
    }
}
