<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\Kiosk;
use App\Models\LaborType;
use App\Models\Shift;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The System section: an Audit Log that records everything that happens, and
 * the four screens over it — Users & Roles, Audit Logs, Device Monitoring and
 * System Settings.
 */
class SystemSectionTest extends TestCase
{
    use RefreshDatabase;

    private function admin(string $username = 'admin.sys'): User
    {
        return User::create([
            'name' => 'Aldrin Admin', 'username' => $username, 'password' => Hash::make('secret123'),
            'role' => User::ROLE_ADMIN, 'is_active' => true,
        ]);
    }

    private function staff(string $username = 'staff.sys', string $name = 'Carlo Staff'): User
    {
        return User::create([
            'name' => $name, 'username' => $username, 'password' => Hash::make('secret123'),
            'role' => User::ROLE_HR, 'is_active' => true,
        ]);
    }

    // ── The log records what happens ─────────────────────────────────────────

    public function test_a_change_made_in_a_request_is_logged_field_by_field(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('accounts.store'), [
            'first_name' => 'Maria', 'last_name' => 'Santos', 'login_method' => User::LOGIN_PASSWORD, 'username' => 'maria.santos', 'email' => '',
            'role' => User::ROLE_HR, 'password' => 'payroll2026', 'password_confirmation' => 'payroll2026',
        ])->assertRedirect();

        $created = AuditLog::where('module', 'Users')->where('action', 'created')->latest('id')->first();
        $this->assertNotNull($created, 'creating an account was not logged');
        $this->assertStringContainsString('Maria Santos', $created->description);
        $this->assertSame($admin->id, $created->user_id);

        $maria = User::where('username', 'maria.santos')->firstOrFail();

        $this->actingAs($admin)->put(route('accounts.update', $maria), [
            'first_name' => 'Maria L.', 'last_name' => 'Santos', 'login_method' => User::LOGIN_PASSWORD, 'username' => 'maria.santos', 'email' => '',
            'role' => User::ROLE_HR, 'is_active' => 1,
            'password' => 'brandnew2026', 'password_confirmation' => 'brandnew2026',
        ])->assertRedirect();

        $updated = AuditLog::where('module', 'Users')->where('action', 'updated')->latest('id')->first();
        $this->assertNotNull($updated, 'editing an account was not logged');
        $this->assertStringContainsString('name Maria Santos → Maria L. Santos', $updated->description);
        $this->assertStringContainsString('password changed', $updated->description);
        $this->assertStringNotContainsString('brandnew2026', $updated->description, 'a password must never be written into the log');
    }

    public function test_an_explicit_entry_is_not_repeated_by_the_model_event(): void
    {
        $admin = $this->admin();
        $staff = $this->staff();

        $this->actingAs($admin)->patch(route('users-roles.update', $staff), ['role' => User::ROLE_ADMIN]);

        $about = AuditLog::where('subject_type', 'User')->where('subject_id', $staff->id)->get();
        $this->assertCount(1, $about, 'the role change was logged twice');
        $this->assertSame('Changed Carlo Staff from HR to Administrator', $about->first()->description);
    }

    public function test_sign_ins_are_logged_and_the_password_never_is(): void
    {
        $this->staff('jo.staff', 'Jo Staff');

        $this->post(route('login.post'), ['username' => 'jo.staff', 'password' => 'wrongpass1']);
        $this->post(route('login.post'), ['username' => 'jo.staff', 'password' => 'secret123'])->assertRedirect();

        $this->assertTrue(AuditLog::where('action', 'failed')->where('description', 'like', '%jo.staff%')->exists(), 'the failed sign-in was not logged');
        $this->assertTrue(AuditLog::where('action', 'signed in')->where('user_name', 'Jo Staff')->exists(), 'the sign-in was not logged');
        $this->assertFalse(AuditLog::where('description', 'like', '%wrongpass1%')->exists(), 'a typed password reached the log');
    }

    public function test_a_bulk_delete_that_no_model_sees_is_logged_by_its_route(): void
    {
        $admin  = $this->admin();
        $site   = Site::firstOrCreate(['name' => 'Site A'], ['location' => 'Naga']);
        $labor  = LaborType::firstOrCreate(['name' => 'Mason'], ['daily_rate' => 1000]);
        $shift  = Shift::where('crosses_midnight', false)->first();
        $worker = Employee::create([
            'name' => 'Juan Dela Cruz', 'position' => 'Mason', 'rate_per_hour' => 125,
            'labor_type_id' => $labor->id, 'site_id' => $site->id, 'shift_id' => $shift?->id,
            'status' => 'active', 'fingerprint_id' => '77',
        ]);

        $this->actingAs($admin)->deleteJson(route('employees.bulk-delete'), ['ids' => [$worker->id]])->assertOk();

        $entry = AuditLog::where('module', 'Employees')->latest('id')->first();
        $this->assertNotNull($entry, 'the bulk delete left no trace');
        $this->assertSame('Moved selected employees to Removed (1 selected)', $entry->description);
        $this->assertSame($admin->id, $entry->user_id);
    }

    public function test_opening_a_page_writes_nothing(): void
    {
        $admin  = $this->admin();
        $before = AuditLog::count();

        $this->actingAs($admin)->get(route('audit-logs.index'))->assertOk();
        $this->actingAs($admin)->get(route('users-roles.index'))->assertOk();
        $this->actingAs($admin)->get(route('system-settings.about'))->assertOk();

        $this->assertSame($before, AuditLog::count());
    }

    public function test_a_settings_save_is_logged_once_as_old_to_new(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->put(route('system-settings.security.update'), [
            'session_timeout_minutes' => 60, 'password_min_length' => 8,
            'max_login_attempts' => 5, 'lockout_seconds' => 60,
        ])->assertSessionHasNoErrors();

        $entries = AuditLog::where('module', 'Settings')->get();
        $this->assertCount(1, $entries, 'the save was logged more than once');
        $this->assertSame('Security: session timeout 120 → 60 min', $entries->first()->description);

        $this->actingAs($admin)->get(route('system-settings.security'))
            ->assertOk()
            ->assertSee('Recent changes to these rules')
            ->assertSee('by Aldrin Admin');
    }

    // ── The screens ──────────────────────────────────────────────────────────

    public function test_the_accounts_list_forwards_to_users_and_roles(): void
    {
        $this->actingAs($this->admin())
            ->get(route('accounts.index', ['status' => 'inactive']))
            ->assertRedirect(route('users-roles.index', ['status' => 'disabled']));
    }

    public function test_users_and_roles_inspects_an_account(): void
    {
        $admin = $this->admin();
        $staff = $this->staff();

        $this->actingAs($admin)->get(route('users-roles.index', ['account' => $staff->id]))
            ->assertOk()
            ->assertSee('Selected account')
            ->assertSee('Can open · 5 of 7')
            ->assertSee('Access matrix')
            ->assertSee(route('accounts.edit', $staff), false);
    }

    public function test_the_audit_log_filters_and_exports(): void
    {
        $admin = $this->admin();
        AuditLog::entry(['user_name' => 'Maria', 'module' => 'Payroll', 'action' => 'approved', 'description' => 'Approved payroll run PR-1']);
        AuditLog::entry(['user_name' => 'Jessa', 'module' => 'Leave', 'action' => 'rejected', 'description' => 'Rejected leave for Noel']);

        $this->actingAs($admin)->get(route('audit-logs.index', ['module' => ['Payroll']]))
            ->assertOk()
            ->assertSee('Approved payroll run PR-1')
            ->assertDontSee('Rejected leave for Noel');

        $this->actingAs($admin)->get(route('audit-logs.index', ['quick' => 'sensitive']))
            ->assertOk()
            ->assertSee('Rejected leave for Noel')
            ->assertDontSee('Approved payroll run PR-1');

        $csv = $this->actingAs($admin)->get(route('audit-logs.export', ['module' => ['Leave']]));
        $csv->assertOk();
        $body = $csv->streamedContent();

        $this->assertStringContainsString('When,Person,Module,Action,Description', $body);
        $this->assertStringContainsString('Rejected leave for Noel', $body);
        $this->assertStringNotContainsString('PR-1', $body);
        $this->assertTrue(AuditLog::where('action', 'exported')->exists(), 'the export itself was not logged');
    }

    public function test_device_monitoring_reads_the_heartbeat_into_three_tiers(): void
    {
        $admin = $this->admin();
        Kiosk::create(['name' => 'Late Kiosk', 'code' => 'TEST_LATE']);
        Kiosk::create(['name' => 'Quiet Kiosk', 'code' => 'TEST_QUIET']);

        Cache::put('kiosk_location_TEST_LATE', [
            'last_seen' => now()->subSeconds(120)->toIso8601String(),
            'status'    => 'fix', 'lat' => 14.2, 'lng' => 121.1,
        ], 600);

        $this->actingAs($admin)->get(route('devices.index'))
            ->assertOk()
            ->assertSee('Online · late')
            ->assertSee('Offline')
            ->assertSee('No heartbeat received from this kiosk yet');
    }

    // ── Appearance can follow the device ────────────────────────────────────

    public function test_the_default_theme_can_follow_the_device(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->put(route('system-settings.appearance.update'), ['default_theme' => 'system'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('theme_changed', 'system');

        \App\Models\SystemSetting::forget();
        $this->assertSame('system', \App\Models\SystemSetting::current()->default_theme);
        $this->assertTrue(AuditLog::where('description', 'Appearance: default theme “dark” → “system”')->exists());

        $this->actingAs($admin)->get(route('system-settings.appearance'))
            ->assertOk()
            ->assertSee('var fallback = "system"', false)
            ->assertSee('prefers-color-scheme: dark', false)
            ->assertSee('Follows the device setting');
    }

    // ── No worker role ───────────────────────────────────────────────────────

    public function test_the_employee_role_can_no_longer_be_given(): void
    {
        $this->assertArrayNotHasKey('employee', User::ROLES);

        $admin = $this->admin();
        $staff = $this->staff();

        $this->actingAs($admin)->patch(route('users-roles.update', $staff), ['role' => 'employee'])
            ->assertSessionHasErrors('role');

        $this->assertSame(User::ROLE_HR, $staff->fresh()->role);

        $this->actingAs($admin)->get(route('users-roles.index'))
            ->assertOk()
            ->assertSee('in 2 roles');
    }

    public function test_accounts_that_had_the_employee_role_are_kept_but_cannot_sign_in(): void
    {
        $worker = User::create([
            'name' => 'Noel Garcia', 'username' => 'ngarcia', 'password' => Hash::make('secret123'),
            'role' => 'employee', 'is_active' => true,
        ]);

        $migration = require database_path('migrations/2026_09_15_120000_retire_the_employee_role.php');
        $migration->up();

        $worker->refresh();
        $this->assertSame(User::ROLE_STAFF, $worker->role, 'the account is kept, as Staff');
        $this->assertFalse($worker->is_active, 'and it can no longer sign in');
        $this->assertTrue(
            AuditLog::where('description', 'like', 'Employee role retired%Noel Garcia%')->exists(),
            'the change is written to the Audit Log'
        );

        $this->post(route('login.post'), ['username' => 'ngarcia', 'password' => 'secret123'])
            ->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_every_settings_tab_renders_the_new_hub_and_save_bar(): void
    {
        $admin = $this->admin();

        foreach ([
            'system-settings.about'      => 'Company identity',
            'system-settings.security'   => 'In plain words',
            'system-settings.appearance' => 'Default theme',
        ] as $route => $text) {
            $this->actingAs($admin)->get(route($route))
                ->assertOk()
                ->assertSee($text)
                ->assertSee('All changes saved')
                ->assertSee('Accounts &amp; roles', false);
        }
    }

    // ── Two roles: Administrator and HR ──────────────────────────────────────

    public function test_the_narrow_roles_are_folded_into_hr_and_nothing_is_lost(): void
    {
        $made = [];
        foreach (['staff' => 'Carla Cruz', 'payroll_officer' => 'Pia Reyes', 'site_supervisor' => 'Sam Uy'] as $role => $name) {
            $made[$role] = User::create([
                'name' => $name, 'username' => str_replace(' ', '.', strtolower($name)),
                'password' => Hash::make('secret123'), 'role' => $role, 'is_active' => $role !== 'site_supervisor',
            ]);
        }
        $admin = $this->admin();

        $migration = require database_path('migrations/2026_09_22_090000_fold_the_narrow_roles_into_hr.php');
        $migration->up();

        foreach ($made as $role => $user) {
            $user->refresh();
            $this->assertSame(User::ROLE_HR, $user->role, "{$role} became HR");
            $this->assertFalse($user->is_admin);
        }
        $this->assertFalse($made['site_supervisor']->is_active, 'a disabled account stays disabled');
        $this->assertSame(User::ROLE_ADMIN, $admin->fresh()->role, 'administrators are untouched');
        $this->assertTrue(
            AuditLog::where('description', 'like', 'Roles reduced to Administrator and HR%Pia Reyes%')->exists(),
            'who was moved is written to the Audit Log'
        );
    }

    public function test_the_role_screens_offer_administrator_and_hr_only(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('accounts.create'))
            ->assertOk()
            ->assertSee('data-role="hr"', false)
            ->assertSee('data-role="admin"', false)
            ->assertDontSee('data-role="staff"', false)
            ->assertSee('CAN OPEN');

        $this->actingAs($admin)->post(route('accounts.store'), [
            'first_name' => 'Old', 'last_name' => 'Role', 'login_method' => User::LOGIN_PASSWORD, 'username' => 'old.role', 'role' => 'payroll_officer',
            'password' => 'payroll2026', 'password_confirmation' => 'payroll2026',
        ])->assertSessionHasErrors('role');

        $this->actingAs($admin)->get(route('users-roles.index'))
            ->assertOk()
            ->assertDontSee('Site Supervisor')
            ->assertDontSee('Payroll Officer');
    }

    public function test_the_inspector_folds_its_long_sections(): void
    {
        $admin = $this->admin();
        $hr    = $this->staff();

        $page = $this->actingAs($admin)->get(route('users-roles.index', ['account' => $hr->id]))->assertOk();
        $page->assertSee('data-fold="access"', false)
             ->assertSee('data-fold="history"', false)
             ->assertSee('fold-arrow', false);
    }
}
