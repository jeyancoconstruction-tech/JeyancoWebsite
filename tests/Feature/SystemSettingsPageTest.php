<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * System Settings as one page (Michael, 2026-09-26), laid out after
 * jeyanco-settings.html: Company, Appearance, Security, Kiosks and the Audit
 * logs as sections, one save bar for all of them, and Audit Logs no longer an
 * entry of its own in the sidebar. The page itself was checked in Chrome.
 */
class SystemSettingsPageTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Aldrin Admin', 'username' => 'aldrin', 'password' => 'secret123',
            'role' => User::ROLE_ADMIN, 'is_admin' => true, 'is_active' => true,
        ]);
    }

    /** What the page posts when nothing was touched, section by section. */
    private function everything(array $over = []): array
    {
        $s = SystemSetting::current();

        return array_merge([
            'company_name' => $s->company_name, 'company_tagline' => $s->company_tagline, 'company_address' => $s->company_address,
            'default_theme' => $s->default_theme ?: 'dark',
            'session_timeout_minutes' => $s->session_timeout_minutes, 'password_min_length' => $s->password_min_length,
            'max_login_attempts' => $s->max_login_attempts, 'lockout_seconds' => $s->lockout_seconds,
            'kiosk_attendance_mode' => $s->kioskMode(), 'kiosk_repeat_guard_seconds' => $s->kiosk_repeat_guard_seconds ?? 180,
            'kiosk_idle_return_seconds' => $s->kiosk_idle_return_seconds ?? 60,
        ], $over);
    }

    public function test_the_save_bar_saves_each_edited_section_through_its_own_save(): void
    {
        $this->actingAs($this->admin())
             ->put(route('system-settings.update-all'), $this->everything([
                 'company_name' => 'JEYANCO BUILDERS', 'max_login_attempts' => 7,
                 'sections' => ['company', 'security'], 'current' => 'security',
             ]))
             ->assertRedirect(route('system-settings.about', ['section' => 'security']))
             ->assertSessionHasNoErrors()
             ->assertSessionHas('success');

        SystemSetting::forget();
        $this->assertSame('JEYANCO BUILDERS', SystemSetting::current()->company_name);
        $this->assertSame(7, (int) SystemSetting::current()->max_login_attempts);

        // One line per section, as when each had a page of its own.
        $this->assertEqualsCanonicalizing([
            'Company: name “JEYANCO CONSTRUCTION” → “JEYANCO BUILDERS”',
            'Security: failed sign-ins before lockout 5 → 7',
        ], AuditLog::where('module', 'Settings')->pluck('description')->all());
    }

    public function test_a_section_that_was_not_edited_is_not_saved(): void
    {
        // Appearance posts a changed theme, but the bar only named Kiosk.
        $this->actingAs($this->admin())
             ->put(route('system-settings.update-all'), $this->everything([
                 'default_theme' => 'system', 'kiosk_repeat_guard_seconds' => 120, 'sections' => ['kiosk'],
             ]))
             ->assertSessionHasNoErrors();

        SystemSetting::forget();
        $this->assertNotSame('system', SystemSetting::current()->default_theme);
        $this->assertSame(120, (int) SystemSetting::current()->kiosk_repeat_guard_seconds);
        $this->assertSame(1, AuditLog::where('module', 'Settings')->count());
    }

    public function test_one_bad_value_saves_nothing_at_all(): void
    {
        $this->actingAs($this->admin())
             ->from(route('system-settings.about', ['section' => 'company']))
             ->put(route('system-settings.update-all'), $this->everything([
                 'company_name' => 'JEYANCO BUILDERS', 'session_timeout_minutes' => 2,
                 'sections' => ['company', 'security'], 'current' => 'company',
             ]))
             ->assertSessionHasErrors('session_timeout_minutes');

        SystemSetting::forget();
        $this->assertNotSame('JEYANCO BUILDERS', SystemSetting::current()->company_name, 'the valid section waited for the other');
        $this->assertSame(0, AuditLog::where('module', 'Settings')->count());
    }

    public function test_the_theme_saved_from_the_bar_switches_the_saver_too(): void
    {
        $this->actingAs($this->admin())
             ->put(route('system-settings.update-all'), $this->everything(['default_theme' => 'light', 'sections' => ['appearance']]))
             ->assertSessionHas('theme_changed', 'light');
    }

    public function test_the_page_opens_on_the_section_it_is_asked_for(): void
    {
        $admin = $this->admin();

        foreach (['company', 'appearance', 'security', 'kiosk', 'audit'] as $section) {
            $html = $this->actingAs($admin)->get(route('system-settings.about', ['section' => $section]))->assertOk()->getContent();
            $this->assertStringContainsString('data-sec="' . $section . '" >', $html, "$section is open");
            foreach (array_diff(['company', 'appearance', 'security', 'kiosk', 'audit'], [$section]) as $other) {
                $this->assertMatchesRegularExpression('~data-sec="' . $other . '"\s+hidden~', $html, "$section open: $other is closed");
            }
            $this->assertMatchesRegularExpression('~class="ss-si on" data-s="' . $section . '"\s+aria-current="page"~', $html);
        }

        // Anything else falls back to the address's own section.
        $this->actingAs($admin)->get(route('system-settings.security', ['section' => 'nonsense']))
             ->assertOk()->assertViewHas('section', 'security');
    }

    public function test_the_audit_logs_are_a_section_now_and_keep_their_filters(): void
    {
        $admin = $this->admin();
        AuditLog::entry(['user_name' => 'Maria', 'module' => 'Payroll', 'action' => 'approved', 'description' => 'Approved payroll run PR-1']);
        AuditLog::entry(['user_name' => 'Jessa', 'module' => 'Leave', 'action' => 'rejected', 'description' => 'Rejected leave for Noel']);

        $this->actingAs($admin)->get('/audit-logs?q=Noel')
             ->assertRedirect(route('system-settings.about', ['q' => 'Noel', 'section' => 'audit']));

        $page = $this->actingAs($admin)->get(route('system-settings.about', ['section' => 'audit', 'q' => 'Noel']))->assertOk();
        $page->assertSee('Rejected leave for Noel')->assertDontSee('Approved payroll run PR-1')
             ->assertSee('id="auditEntries" data-live="audit"', false)
             // The export takes the filters on screen.
             ->assertSee(route('audit-logs.export', ['q' => 'Noel']), false);
    }

    public function test_pages_of_the_log_stay_on_the_audit_section(): void
    {
        foreach (range(1, 55) as $i) {
            AuditLog::entry(['user_name' => 'Maria', 'module' => 'Payroll', 'action' => 'updated', 'description' => "Entry $i"]);
        }

        $html = $this->actingAs($this->admin())->get(route('system-settings.about', ['section' => 'audit']))->assertOk()->getContent();
        $this->assertStringContainsString(e(route('system-settings.about', ['section' => 'audit', 'page' => 2])), $html);
    }

    public function test_audit_logs_is_gone_from_the_sidebar(): void
    {
        $html = $this->actingAs($this->admin())->get(route('dashboard'))->assertOk()->getContent();
        $rail = substr($html, strpos($html, '<nav class="nav-menu">'));
        $rail = substr($rail, 0, strpos($rail, '</nav>'));

        $this->assertStringNotContainsString('<span>Audit Logs</span>', $rail);
        $this->assertStringNotContainsString(route('audit-logs.index'), $rail);
        $this->assertStringContainsString('<span>System Settings</span>', $rail);
    }
}
