<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password;
use Tests\TestCase;

/**
 * The settings that are not payroll.
 *
 * The point of the page is that the numbers on it reach something. A settings
 * screen whose values nothing reads is worse than no screen at all — it says
 * the office has made a decision that was never applied.
 */
class SystemSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::firstOrCreate(
            ['username' => 'admin.system'],
            ['name' => 'Admin', 'password' => Hash::make('secret123'), 'role' => User::ROLE_ADMIN, 'is_active' => true]
        );
    }

    private function staff(): User
    {
        return User::firstOrCreate(
            ['username' => 'staff.system'],
            ['name' => 'Staff', 'password' => Hash::make('secret123'), 'role' => User::ROLE_STAFF, 'is_active' => true]
        );
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'session_timeout_minutes' => 120,
            'password_min_length'     => 8,
            'max_login_attempts'      => 5,
            'lockout_seconds'         => 60,
        ], $overrides);
    }

    // ── The row ──────────────────────────────────────────────────────────────

    /** With no row, the values are the ones that were hardcoded before it. */
    public function test_the_defaults_stand_in_for_a_missing_row(): void
    {
        $this->assertSame(0, SystemSetting::count());

        $s = SystemSetting::current();

        $this->assertFalse($s->exists);
        $this->assertSame('JEYANCO CONSTRUCTION', $s->company_name);
        $this->assertSame(8, $s->password_min_length);
        $this->assertSame(5, $s->max_login_attempts);
    }

    /** An office that has uploaded nothing keeps the bundled logo. */
    public function test_the_logo_falls_back_to_the_bundled_file(): void
    {
        $this->assertStringContainsString('images/JeyancoLogo.png', SystemSetting::current()->logoUrl());

        SystemSetting::create(['logo_path' => 'branding/x.png'] + SystemSetting::DEFAULTS);
        SystemSetting::forget();

        $this->assertStringContainsString('storage/branding/x.png', SystemSetting::current()->logoUrl());
    }

    // ── The page ─────────────────────────────────────────────────────────────

    public function test_an_admin_can_open_about(): void
    {
        $this->actingAs($this->admin())
             ->get(route('system-settings.about'))
             ->assertOk()
             ->assertSee('Company identity');
    }

    public function test_an_admin_can_open_security(): void
    {
        $this->actingAs($this->admin())
             ->get(route('system-settings.security'))
             ->assertOk()
             ->assertSee('Account &amp; security', false);
    }

    public function test_staff_cannot(): void
    {
        $this->actingAs($this->staff())
             ->get(route('system-settings.about'))
             ->assertStatus(403);
    }

    /**
     * The two tabs are separate actions on one row, so each has to write its own
     * half without disturbing the other's.
     */
    public function test_about_writes_the_identity_and_leaves_security_alone(): void
    {
        SystemSetting::create(SystemSetting::DEFAULTS)->update(['max_login_attempts' => 7]);
        SystemSetting::forget();

        $this->actingAs($this->admin())
             ->put(route('system-settings.about.update'), [
                 'company_name'    => 'JEYANCO BUILDERS',
                 'company_tagline' => 'Payroll Dept. · Panganiban, PH',
                 'company_address' => 'Panganiban, Camarines Sur',
             ])
             ->assertSessionHasNoErrors();

        SystemSetting::forget();
        $s = SystemSetting::current();

        $this->assertSame('JEYANCO BUILDERS', $s->company_name);
        $this->assertSame('Panganiban, Camarines Sur', $s->company_address);
        $this->assertSame(7, $s->max_login_attempts, 'the security half is untouched');
        $this->assertSame(1, SystemSetting::count(), 'one row, edited, not appended');
    }

    public function test_security_writes_its_own_half_and_leaves_the_identity_alone(): void
    {
        SystemSetting::create(SystemSetting::DEFAULTS)->update(['company_name' => 'JEYANCO BUILDERS']);
        SystemSetting::forget();

        $this->actingAs($this->admin())
             ->put(route('system-settings.security.update'), $this->payload(['max_login_attempts' => 9]))
             ->assertSessionHasNoErrors();

        SystemSetting::forget();
        $s = SystemSetting::current();

        $this->assertSame(9, $s->max_login_attempts);
        $this->assertSame('JEYANCO BUILDERS', $s->company_name, 'the identity is untouched');
    }


    /** A session of a minute logs the office out mid-payroll. */
    public function test_an_absurd_session_timeout_is_refused(): void
    {
        $this->actingAs($this->admin())
             ->put(route('system-settings.security.update'), $this->payload(['session_timeout_minutes' => 2]))
             ->assertSessionHasErrors('session_timeout_minutes');

        $this->assertSame(0, SystemSetting::count());
    }

    /** Below eight is weaker than every password already on file was made to meet. */
    public function test_a_shorter_password_minimum_is_refused(): void
    {
        $this->actingAs($this->admin())
             ->put(route('system-settings.security.update'), $this->payload(['password_min_length' => 4]))
             ->assertSessionHasErrors('password_min_length');
    }

    /**
     * The company name is what the printed payslip says, not a constant.
     *
     * The view is rendered directly rather than through its route: the route
     * computes payroll, which reaches MySQL's YEAR() and has no answer under
     * the SQLite this suite runs on. The composer that supplies the identity
     * fires either way, which is the part under test.
     */
    public function test_the_payslip_prints_the_configured_company(): void
    {
        SystemSetting::create(SystemSetting::DEFAULTS)->update(['company_name' => 'JEYANCO BUILDERS']);
        SystemSetting::forget();

        $html = view('payslips-batch', [
            'slips'       => collect([[
                'employee_id' => 1, 'name' => 'Juan Dela Cruz', 'position' => 'Mason',
                'workdays' => 5, 'hours' => 40, 'regular' => 4000, 'overtime' => 0,
                'holidayPay' => 0, 'restDayPay' => 0, 'bonus' => 0, 'gross' => 4000,
                'ded' => ['sss' => 0, 'philhealth' => 0, 'pagibig' => 0, 'tax' => 0, 'vale' => 0, 'other' => 0],
                'totalDeductions' => 0, 'net' => 4000,
            ]]),
            'periodLabel' => '03/02/2026 – 03/08/2026',
            'from'        => '2026-03-02',
            'to'          => '2026-03-08',
        ])->render();

        $this->assertStringContainsString('JEYANCO BUILDERS', $html);
        $this->assertStringNotContainsString('JEYANCO CONSTRUCTION', $html);
    }

    /** The account form's password rule follows the setting. */
    public function test_the_password_minimum_is_applied_to_a_new_account(): void
    {
        SystemSetting::create(SystemSetting::DEFAULTS)->update(['password_min_length' => 12]);
        SystemSetting::forget();

        $this->actingAs($this->admin())
             ->post(route('accounts.store'), [
                 'name'                  => 'New Person',
                 'username'              => 'new.person',
                 'role'                  => User::ROLE_STAFF,
                 'password'              => 'short1pass',      // 10 characters
                 'password_confirmation' => 'short1pass',
             ])
             ->assertSessionHasErrors('password');

        $this->assertNull(User::where('username', 'new.person')->first());
    }

    /** And the login throttle follows its own. */
    public function test_the_lockout_follows_the_configured_attempts(): void
    {
        SystemSetting::create(SystemSetting::DEFAULTS)->update(['max_login_attempts' => 3]);
        SystemSetting::forget();
        RateLimiter::clear('locked.out|127.0.0.1');

        User::create([
            'username' => 'locked.out', 'name' => 'Locked Out',
            'password' => Hash::make('secret123'), 'role' => User::ROLE_STAFF, 'is_active' => true,
        ]);

        for ($i = 0; $i < 3; $i++) {
            $this->post(route('login'), ['username' => 'locked.out', 'password' => 'wrong']);
        }

        $this->post(route('login'), ['username' => 'locked.out', 'password' => 'secret123'])
             ->assertSessionHasErrors();

        $this->assertGuest();
    }

    // ── Accounts as a tab ────────────────────────────────────────────────────

    /**
     * Every settings page carries the same category nav down its left. They are
     * links rather than panes — the forms post and the accounts list paginates,
     * and both need a real address to come back to — so each page has to render
     * the whole nav or the one you are on loses its way back.
     */
    public function test_every_settings_page_carries_the_category_nav(): void
    {
        $pages = [
            route('system-settings.about'),
            route('system-settings.security'),
            route('system-settings.appearance'),
            route('accounts.index'),
        ];

        foreach ($pages as $page) {
            $response = $this->actingAs($this->admin())->get($page)->assertOk();

            foreach ($pages as $link) {
                $response->assertSee($link, false);
            }
        }
    }

    /** And the sidebar keeps one entry lit on any of them. */
    public function test_the_sidebar_entry_covers_them_all(): void
    {
        $this->actingAs($this->admin())
             ->get(route('accounts.index'))
             ->assertOk()
             ->assertSee('System Settings')
             ->assertDontSee('<span>Accounts</span>', false);
    }

    // ── Attendance ───────────────────────────────────────────────────────────

    public function test_saving_attendance_writes_the_day_shape(): void
    {
        $this->actingAs($this->admin())
             ->put(route('settings.attendance.update'), [
                 'expected_time_in'       => '07:30',
                 'grace_period_minutes'   => 10,
                 'standard_hours_per_day' => 10,
                 'week_starts_on'         => 0,
                 'payroll_cycle'          => 'daily',
             ])
             ->assertSessionHasNoErrors();

        SystemSetting::forget();
        $s = SystemSetting::current();

        $this->assertSame(10, $s->grace_period_minutes);
        $this->assertSame(10.0, $s->standard_hours_per_day);
        $this->assertSame(0, $s->week_starts_on);
        $this->assertSame('daily', $s->payroll_cycle);
        $this->assertFalse($s->auto_count_overtime, 'an unticked box is the off answer');
    }

    /** A day of no hours would divide the daily rate by nothing. */
    public function test_a_zero_hour_day_is_refused(): void
    {
        $this->actingAs($this->admin())
             ->put(route('settings.attendance.update'), [
                 'expected_time_in'       => '08:00',
                 'grace_period_minutes'   => 15,
                 'standard_hours_per_day' => 0,
                 'week_starts_on'         => 1,
                 'payroll_cycle'          => 'weekly',
             ])
             ->assertSessionHasErrors('standard_hours_per_day');
    }

    /**
     * Saving Attendance comes back to the tab it was saved from. Landing on
     * Multipliers after saving the second tab reads as the save being lost.
     */
    public function test_saving_attendance_returns_to_its_tab(): void
    {
        $this->actingAs($this->admin())
             ->put(route('settings.attendance.update'), [
                 'expected_time_in'       => '08:00',
                 'grace_period_minutes'   => 15,
                 'standard_hours_per_day' => 8,
                 'week_starts_on'         => 1,
                 'payroll_cycle'          => 'weekly',
             ])
             ->assertRedirect(route('settings.index', ['tab' => 'attendance']));
    }

    // ── Appearance ───────────────────────────────────────────────────────────

    /**
     * The theme a screen opens on before anybody has chosen. The boot script
     * had 'dark' written into it, so a setting that does not reach the script
     * would be a preference nobody's browser ever sees.
     */
    public function test_the_default_theme_reaches_the_boot_script(): void
    {
        SystemSetting::create(SystemSetting::DEFAULTS)->update(['default_theme' => 'light']);
        SystemSetting::forget();

        $this->actingAs($this->admin())
             ->get(route('system-settings.appearance'))
             ->assertOk()
             ->assertSee('var fallback = "light"', false);
    }

    public function test_the_theme_falls_back_to_dark_with_no_row(): void
    {
        $this->actingAs($this->admin())
             ->get(route('system-settings.appearance'))
             ->assertOk()
             ->assertSee('var fallback = "dark"', false);
    }

    public function test_an_unknown_theme_is_refused(): void
    {
        $this->actingAs($this->admin())
             ->put(route('system-settings.appearance.update'), ['default_theme' => 'neon'])
             ->assertSessionHasErrors('default_theme');
    }

    /**
     * Saving Light has to make the page light for the person who saved it.
     * Their own theme is in their own browser and outranks the default, so
     * without this the screen is unchanged and the save reads as having failed.
     */
    public function test_saving_the_theme_switches_the_saver_too(): void
    {
        $this->actingAs($this->admin())
             ->put(route('system-settings.appearance.update'), ['default_theme' => 'light'])
             ->assertSessionHas('theme_changed', 'light');

        $this->actingAs($this->admin())
             ->get(route('system-settings.appearance'))
             ->assertOk()
             ->assertSee("localStorage.setItem('jeyanco-theme', picked)", false);
    }
}
