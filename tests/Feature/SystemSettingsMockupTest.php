<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Shift;
use App\Models\SystemSetting;
use App\Models\User;
use App\Notifications\AlertEmail;
use App\Notifications\AttendanceAlert;
use App\Notifications\PayrollNotification;
use App\Services\RemittanceTracker;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Michael asked (2026-09-27) why System Settings did not follow
 * jeyanco-settings.html itself. It now has every row of it, and each one
 * does something: the TIN, the accent colour, table density, the sign-in
 * intro, Google sign-in, "sign out everyone", the kiosk rules and the
 * Notifications section. The page was checked in Chrome as well.
 */
class SystemSettingsMockupTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function admin(string $username = 'aldrin', string $email = 'aldrin@example.com'): User
    {
        return User::create([
            'name' => 'Aldrin Admin', 'username' => $username, 'email' => $email, 'password' => 'secret123',
            'role' => User::ROLE_ADMIN, 'is_admin' => true, 'is_active' => true,
        ]);
    }

    private function set(array $values): void
    {
        $row = SystemSetting::first() ?? new SystemSetting(SystemSetting::DEFAULTS);
        $row->forceFill($values)->save();
        SystemSetting::forget();
    }

    // ── The page is the mockup's ─────────────────────────────────────────────

    public function test_every_row_of_the_mockup_is_on_the_page(): void
    {
        $page = $this->actingAs($this->admin())->get(route('system-settings.about'))->assertOk();

        foreach ([
            // Company
            'Company name', 'Line under the name', 'Address', 'TIN', 'Company logo', 'Live preview', 'Payslip', 'Sidebar', 'Sign-in',
            // Appearance
            'Default theme', 'Accent color', 'Table density', 'Comfortable', 'Compact', 'Intro animation',
            // Security
            'Session length', 'Failed sign-in limit', 'Google sign-in', 'Sign out all sessions',
            // Kiosks
            'Scan mode', 'Worker picks', 'Kiosk opens before shift',
            // Notifications
            'Missing scans', 'Remittance reminders', 'Payroll ready', 'Also send by email',
            // Audit logs and the bar
            'Search activity', 'All areas', 'You have unsaved changes', 'Discard', 'Save changes',
        ] as $text) {
            $page->assertSee($text);
        }

        $page->assertSee('data-s="notif"', false)->assertSee('data-sec="notif"', false);
    }

    // ── Company: the TIN reaches the remittance report ──────────────────────

    public function test_the_tin_is_printed_on_the_remittance_report(): void
    {
        $this->actingAs($this->admin())->put(route('system-settings.about.update'), [
            'company_name' => 'JEYANCO BUILDERS', 'company_tagline' => 'General Contractor', 'company_tin' => '123-456-789-000',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(AuditLog::where('description', 'like', '%TIN — → “123-456-789-000”%')->exists());

        $this->get(route('remittances.report'))->assertOk()
             ->assertSee('Jeyanco Builders — Remittances')
             ->assertSee('Employer TIN')
             ->assertSee('123-456-789-000');

        $this->put(route('system-settings.about.update'), [
            'company_name' => 'JEYANCO BUILDERS', 'company_tagline' => 'General Contractor', 'company_tin' => 'not a tin',
        ])->assertSessionHasErrors('company_tin');
    }

    // ── Appearance: accent colour and density are on every page ─────────────

    public function test_the_accent_and_density_follow_the_saved_choice(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('dashboard'))->assertOk()
             ->assertDontSee('id="accentTokens"', false)
             ->assertDontSee('density-compact', false);

        $this->put(route('system-settings.appearance.update'), [
            'default_theme' => 'light', 'accent_color' => 'teal', 'table_density' => 'compact', 'signin_intro' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(AuditLog::where('description', 'like', '%accent colour blue → teal%table density comfortable → compact%')->exists());

        $html = $this->get(route('dashboard'))->assertOk()->getContent();
        $this->assertStringContainsString('<style id="accentTokens">', $html);
        $this->assertStringContainsString('--brand:#0D9488', $html);
        $this->assertMatchesRegularExpression('/<body class="bg-light density-compact">/', $html);

        $this->put(route('system-settings.appearance.update'), ['default_theme' => 'light', 'accent_color' => 'pink'])
             ->assertSessionHasErrors('accent_color');
    }

    public function test_the_sign_in_intro_can_be_switched_off(): void
    {
        $this->get(route('login'))->assertOk()->assertSee("!still && !false", false);

        $this->set(['signin_intro' => false]);

        $this->get(route('login'))->assertOk()->assertSee("!still && !true", false);
    }

    // ── Security ────────────────────────────────────────────────────────────

    public function test_google_sign_in_can_be_switched_off(): void
    {
        config(['services.google.client_id' => 'id', 'services.google.client_secret' => 'secret']);

        $this->get(route('login'))->assertOk()->assertSee(route('login.google'), false);

        $this->set(['google_sign_in' => false]);

        $this->get(route('login'))->assertOk()->assertDontSee(route('login.google'), false);
        $this->get(route('login.google'))->assertRedirect(route('login'))
             ->assertSessionHasErrors(['username' => 'Sign in with Google is turned off. Use your username and password.']);
    }

    public function test_sign_out_everyone_ends_every_other_session_but_not_yours(): void
    {
        $admin = $this->admin();
        $other = $this->admin('maria', 'maria@example.com');
        $other->forceFill(['remember_token' => 'token'])->save();

        // Maria signed in an hour ago.
        $this->actingAs($other)->withSession(['signed_in_at' => now()->subHour()->timestamp])
             ->get(route('dashboard'))->assertOk();

        $this->actingAs($admin)->withSession(['signed_in_at' => now()->subHour()->timestamp])
             ->post(route('system-settings.sign-out-all'))
             ->assertRedirect(route('system-settings.about', ['section' => 'security']))
             ->assertSessionHas('signed_in_at');

        $this->assertNotNull(SystemSetting::first()->sessions_revoked_at);
        $this->assertNull($other->fresh()->remember_token, 'a remember-me cookie cannot bring her back');
        $this->assertTrue(AuditLog::where('description', 'Security: signed out every other session')->exists());

        // Her session began before the sign-out: it ends on its next click.
        $this->actingAs($other)->withSession(['signed_in_at' => now()->subHour()->timestamp])
             ->get(route('dashboard'))
             ->assertRedirect(route('login'))
             ->assertSessionHasErrors('username');

        // One that begins afterwards is left alone.
        $this->actingAs($other)->withSession(['signed_in_at' => now()->addSecond()->timestamp])
             ->get(route('dashboard'))->assertOk();
    }

    // ── Kiosks ──────────────────────────────────────────────────────────────

    public function test_the_kiosk_opening_is_written_to_every_shift(): void
    {
        Shift::query()->update(['time_in_opens_minutes' => 120]);

        $this->actingAs($this->admin())->get(route('system-settings.kiosk'))->assertOk()
             ->assertSee('<option value="120" selected>120 min</option>', false);

        $this->put(route('system-settings.kiosk.update'), [
            'kiosk_attendance_mode' => 'buttons', 'kiosk_idle_return_seconds' => 60,
            'kiosk_opens_minutes' => 60,
        ])->assertSessionHasNoErrors();

        $this->assertSame([60], Shift::query()->pluck('time_in_opens_minutes')->unique()->values()->all());
        $this->assertTrue(AuditLog::where('description', 'like', '%kiosk opens before shift 120 → 60 min%')->exists());

        // No duplicate-scan setting any more; the page does not offer one.
        $this->get(route('system-settings.kiosk'))->assertDontSee('Ignore duplicate scans');
    }

    /**
     * Unknown fingerprints and Offline alert left the page on 2026-09-30
     * (Michael). Both alerts keep running on their saved values, and a save
     * cannot change them any more.
     */
    public function test_the_unknown_finger_and_offline_alert_rows_are_gone(): void
    {
        $this->actingAs($this->admin())->get(route('system-settings.kiosk'))->assertOk()
             ->assertDontSee('Unknown fingerprints')
             ->assertDontSee('Offline alert')
             ->assertDontSee('name="kiosk_unknown_alert"', false)
             ->assertDontSee('name="kiosk_offline_alert_minutes"', false);

        $this->put(route('system-settings.kiosk.update'), [
            'kiosk_attendance_mode' => 'buttons', 'kiosk_idle_return_seconds' => 60,
            'kiosk_unknown_alert' => '0', 'kiosk_offline_alert_minutes' => 60,
        ])->assertSessionHasNoErrors();

        SystemSetting::forget();
        $this->assertTrue(SystemSetting::current()->enabled('kiosk_unknown_alert'));
        $this->assertSame(600, SystemSetting::current()->kioskOfflineAlertSeconds());
    }

    public function test_an_unknown_finger_is_reported_once_a_day_when_asked(): void
    {
        $admin = $this->admin();

        $this->postJson('/api/kiosk/clock', ['fingerprint_id' => '999', 'type' => 'time_in'])->assertJson(['not_found' => true]);
        $this->postJson('/api/kiosk/clock', ['fingerprint_id' => '999', 'type' => 'time_in']);

        $this->assertSame(1, $admin->notifications()->where('data->subtype', 'kiosk_unknown')->count());

        $this->set(['kiosk_unknown_alert' => false]);
        $this->postJson('/api/kiosk/clock', ['fingerprint_id' => '1000', 'type' => 'time_in']);
        $this->assertSame(1, $admin->notifications()->where('data->subtype', 'kiosk_unknown')->count());
    }

    public function test_the_offline_alert_waits_as_long_as_it_is_told(): void
    {
        $admin = $this->admin();
        $this->set(['kiosk_offline_alert_minutes' => 30]);

        Carbon::setTestNow('2026-09-27 08:00:00');
        $this->postJson('/api/location', ['kiosk_id' => 'k-test', 'lat' => 13.6, 'lng' => 123.2, 'status' => 'fix'])->assertOk();

        // Quiet for ten minutes: shown offline on the map, but nobody is told yet.
        Carbon::setTestNow('2026-09-27 08:10:00');
        $this->getJson('/api/location/k-test/status')->assertOk();
        $this->assertSame(0, $admin->notifications()->where('data->subtype', 'kiosk_offline')->count());

        // Past thirty: told once.
        Carbon::setTestNow('2026-09-27 08:31:00');
        $this->getJson('/api/location/k-test/status');
        $this->getJson('/api/location/k-test/status');
        $this->assertSame(1, $admin->notifications()->where('data->subtype', 'kiosk_offline')->count());

        Cache::flush();
    }

    // ── Notifications ───────────────────────────────────────────────────────

    public function test_remittance_reminders_reach_the_bell_once_and_can_be_switched_off(): void
    {
        $admin   = $this->admin();
        $tracker = app(RemittanceTracker::class);

        $this->actingAs($admin);
        $tracker->remind($admin, 2);
        session()->forget('remittance_reminded');
        $tracker->remind($admin, 2);
        $this->assertSame(1, $admin->notifications()->where('data->subtype', 'remittance_due')->count());

        $this->set(['notify_remittances' => false]);
        session()->forget('remittance_reminded');
        $tracker->remind($admin, 3);
        $this->assertSame(1, $admin->notifications()->where('data->subtype', 'remittance_due')->count());
    }

    public function test_the_notification_switches_save_and_are_logged(): void
    {
        $this->actingAs($this->admin())->put(route('system-settings.update-all'), [
            'sections' => ['notif'], 'current' => 'notif',
            'notify_missing_scans' => '0', 'notify_remittances' => '1', 'notify_payroll' => '1', 'notify_email' => '1',
        ])->assertRedirect(route('system-settings.about', ['section' => 'notif']))->assertSessionHasNoErrors();

        SystemSetting::forget();
        $this->assertFalse(SystemSetting::current()->enabled('notify_missing_scans'));
        $this->assertTrue(SystemSetting::current()->enabled('notify_email'));
        $this->assertTrue(AuditLog::where('description', 'Notifications: missing scans on → off, email copies off → on')->exists());
    }

    public function test_email_copies_go_to_admins_only_when_switched_on(): void
    {
        $admin = $this->admin();
        $alert = new AttendanceAlert('invalid_clock_in', 'Invalid Attendance Detected', '2 employees clocked in but never clocked out.');

        Notification::fake();
        AlertEmail::copy(new NotificationSent($admin, $alert, 'database'));
        $this->app->terminate();
        Notification::assertNothingSent();

        $this->set(['notify_email' => true]);
        AlertEmail::copy(new NotificationSent($admin, $alert, 'database'));
        AlertEmail::copy(new NotificationSent($admin, new PayrollNotification('period_computed', 'Payroll Computed', 'x'), 'mail'));
        $this->app->terminate();

        Notification::assertSentOnDemandTimes(AlertEmail::class, 1);
    }

    public function test_missing_scan_alerts_follow_their_switch(): void
    {
        $admin  = $this->admin();
        $worker = \App\Models\Employee::create(['site_id' => \App\Models\Kiosk::resolve()?->site_id, 'name' => 'No Time Out', 'status' => 'active', 'fingerprint_id' => '7', 'rate_per_hour' => 100]);
        $day    = now()->subDay()->toDateString();
        \App\Models\Attendance::create(['employee_id' => $worker->id, 'date' => $day, 'time_in' => $day . ' 08:00:00']);
        $alerts = fn () => $admin->notifications()->where('type', AttendanceAlert::class)->where('data->subtype', 'invalid_clock_in')->count();

        $this->actingAs($admin)->get(route('attendance'))->assertOk();
        $this->assertSame(1, $alerts(), 'on: the missed time out is reported');

        $admin->notifications()->delete();
        $this->set(['notify_missing_scans' => false]);
        $this->get(route('attendance'))->assertOk();
        $this->assertSame(0, $alerts(), 'off: nothing is said');
    }
}
