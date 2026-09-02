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
     * Accounts is a tab of System Settings now. The tabs are links rather than
     * panes — the accounts list filters and paginates through the query string,
     * which a hidden pane would fight — so both pages have to carry the strip
     * or the tab you are on disappears when you use it.
     */
    public function test_both_pages_carry_the_tab_strip(): void
    {
        $this->actingAs($this->admin())
             ->get(route('system-settings.about'))
             ->assertOk()
             ->assertSee(route('accounts.index'), false);

        $this->actingAs($this->admin())
             ->get(route('accounts.index'))
             ->assertOk()
             ->assertSee(route('system-settings.about'), false);
    }

    /** And the sidebar keeps one entry lit on either of them. */
    public function test_the_sidebar_entry_covers_both(): void
    {
        $this->actingAs($this->admin())
             ->get(route('accounts.index'))
             ->assertOk()
             ->assertSee('System Settings')
             ->assertDontSee('<span>Accounts</span>', false);
    }
}
