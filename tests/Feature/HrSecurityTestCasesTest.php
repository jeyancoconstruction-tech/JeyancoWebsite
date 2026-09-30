<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Bonus;
use App\Models\Employee;
use App\Models\LaborType;
use App\Models\Shift;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Part III, Security Testing — the HR role on the web (2026-09-30).
 *
 * One test per case in the security test table (HR-S1 to HR-S8), named by
 * its number, so the "Actual Output" column can be read off a green run; a
 * few more checks that are not in the table follow them. The kiosk role's cases
 * (S1–S8) are about the IoT device; these are about what an HR account can
 * and cannot reach in the web system.
 */
class HrSecurityTestCasesTest extends TestCase
{
    use RefreshDatabase;

    private const DENIED = 'Unauthorized. Admin access required.';

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function hr(array $overrides = []): User
    {
        return User::create($overrides + [
            'first_name' => 'Hana', 'last_name' => 'Reyes', 'username' => 'hr.hana', 'email' => 'hana@jeyanco.test',
            'password' => Hash::make('payroll2026'), 'role' => User::ROLE_HR, 'is_active' => true,
        ]);
    }

    private function worker(): Employee
    {
        return Employee::create([
            'name' => 'Juan Dela Cruz', 'status' => Employee::STATUS_ACTIVE, 'rate_per_hour' => 100, 'fingerprint_id' => '5',
            'labor_type_id' => LaborType::firstOrCreate(['name' => 'Mason'], ['daily_rate' => 800])->id,
            'site_id' => Site::firstOrCreate(['name' => 'Site A'])->id,
            'shift_id' => Shift::where('crosses_midnight', false)->firstOrFail()->id,
        ]);
    }

    /** HR-S1 · Unauthorized System Settings access. */
    public function test_hr_s1_system_settings_are_denied(): void
    {
        $this->actingAs($this->hr())->get(route('system-settings.about'))->assertForbidden()->assertSee(self::DENIED);
        $this->get(route('dashboard'))->assertOk()->assertDontSee(route('system-settings.about'), false);
    }

    /** HR-S2 · Unauthorized change to payroll settings. */
    public function test_hr_s2_payroll_settings_cannot_be_changed(): void
    {
        $hr = $this->hr();

        $this->actingAs($hr)->get(route('settings.index'))->assertForbidden();
        $this->put(route('settings.update'), ['grace_period_minutes' => 99])->assertForbidden();
        $this->post(route('labor-types.store'), ['name' => 'Sneaky', 'daily_rate' => 5000])->assertForbidden();

        $this->assertNull(LaborType::where('name', 'Sneaky')->first());
    }

    /** HR-S3 · Unauthorized account creation. */
    public function test_hr_s3_accounts_cannot_be_created(): void
    {
        $this->actingAs($this->hr())->get(route('accounts.create'))->assertForbidden();
        $this->post(route('accounts.store'), [
            'first_name' => 'Ghost', 'last_name' => 'User', 'username' => 'ghost', 'email' => 'ghost@jeyanco.test',
            'login_method' => User::LOGIN_PASSWORD, 'role' => User::ROLE_ADMIN, 'password' => 'payroll2026', 'password_confirmation' => 'payroll2026',
        ])->assertForbidden();

        $this->assertNull(User::where('username', 'ghost')->first());
    }

    /** HR-S4 · Privilege escalation: HR making itself an administrator. */
    public function test_hr_s4_cannot_raise_its_own_role(): void
    {
        $hr = $this->hr();

        $this->actingAs($hr)->patch(route('users-roles.update', $hr), ['role' => User::ROLE_ADMIN])->assertForbidden();
        $this->put(route('accounts.update', $hr), ['role' => User::ROLE_ADMIN])->assertForbidden();

        $this->assertSame(User::ROLE_HR, $hr->fresh()->role);
        $this->assertFalse($hr->fresh()->isAdmin());
    }

    /** HR-S5 · Audit log protection. */
    public function test_hr_s5_audit_logs_are_denied(): void
    {
        $this->actingAs($this->hr())->get('/audit-logs')->assertForbidden();
        $this->get('/audit-logs/export')->assertForbidden();
    }

    /** Also checked: HR cannot put a bonus on a worker's pay. */
    public function test_hr_cannot_give_a_bonus(): void
    {
        $e = $this->worker();

        $this->actingAs($this->hr())->get(route('employees.register'))->assertOk()->assertDontSee('class="rmx-icon-btn rmx-gift js-add-bonus"', false);
        $this->post(route('employees.bonus.store', $e), ['amount' => 5000, 'note' => 'x'])->assertForbidden();

        $this->assertSame(0, Bonus::count());
    }

    /** Also checked: a correction HR is allowed to make is written to the audit trail under their name. */
    public function test_hr_attendance_corrections_are_logged(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-15 10:00:00'));
        $e   = $this->worker();
        $row = Attendance::create(['employee_id' => $e->id, 'site_id' => $e->site_id, 'shift_id' => $e->shift_id,
            'date' => '2026-09-14', 'session' => 'AM', 'time_in' => '08:00:00']);
        $hr = $this->hr();

        $this->actingAs($hr)->patchJson(route('attendance.time-out', $row), ['time' => '17:00'])->assertOk();

        $log = AuditLog::where('module', 'Attendance')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame($hr->id, $log->user_id);
        $this->assertStringContainsString('Juan Dela Cruz', $log->description);
    }

    /** HR-S6 · Brute-force sign-in is locked out. */
    public function test_hr_s6_repeated_wrong_passwords_lock_the_sign_in(): void
    {
        $this->hr();

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login.post'), ['username' => 'hr.hana', 'password' => 'wrong-' . $i]);
        }
        $this->post(route('login.post'), ['username' => 'hr.hana', 'password' => 'payroll2026'])
             ->assertSessionHasErrors('username');
        $this->assertStringStartsWith('Too many failed attempts.', session('errors')->first('username'));
        $this->assertGuest();
    }

    /** Also checked: a deactivated HR account cannot get in, and an open session ends. */
    public function test_a_deactivated_hr_account_is_refused(): void
    {
        $hr = $this->hr(['is_active' => false]);

        $this->post(route('login.post'), ['username' => 'hr.hana', 'password' => 'payroll2026'])
             ->assertSessionHasErrors(['username' => 'This account has been deactivated. Please contact your administrator.']);
        $this->assertGuest();

        $this->actingAs($hr)->get(route('attendance'))->assertRedirect(route('login'));
    }

    /** Also checked: records cannot be opened without signing in. */
    public function test_no_page_opens_without_signing_in(): void
    {
        foreach ([route('attendance'), route('payroll-records'), route('employees.register'), route('payslip.batch')] as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }
    }

    /** HR-S7 · The assistant declines what HR may not see. */
    public function test_hr_s7_the_assistant_declines_account_details(): void
    {
        User::create(['name' => 'Boss', 'username' => 'boss', 'email' => 'boss@jeyanco.test', 'password' => Hash::make('x1234567'),
            'role' => User::ROLE_ADMIN, 'is_active' => true]);

        $reply = $this->actingAs($this->hr())->postJson(route('ai.chat'), ['message' => 'list users'])->json('reply');

        $this->assertStringContainsString('for administrators only', $reply);
        $this->assertStringNotContainsString('boss@jeyanco.test', $reply);
    }

    /** HR-S8 · The assistant answers inside HR's role. */
    public function test_hr_s8_the_assistant_answers_within_the_role(): void
    {
        $this->worker();

        $reply = $this->actingAs($this->hr())->postJson(route('ai.chat'), ['message' => 'how many employees'])->json('reply');

        $this->assertMatchesRegularExpression('/\b1\b/', $reply);
        $this->assertStringNotContainsString('for administrators only', $reply);
    }
}
