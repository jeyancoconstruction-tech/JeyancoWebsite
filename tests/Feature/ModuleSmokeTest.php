<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\LaborType;
use App\Models\PayrollRun;
use App\Models\Shift;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Two jobs:
 *   1. every NEW route renders for a signed-in admin;
 *   2. every EXISTING route still renders exactly as before.
 *
 * The second is the point. Adding eleven modules to a live payroll system is
 * only safe if the screens that were working yesterday are provably still
 * working today.
 */
class ModuleSmokeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Employee $employee;
    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();

        // The app targets MySQL and Holiday.php uses YEAR() in raw SQL, which
        // SQLite has no equivalent for. That is existing production code, not
        // something this extension introduced — so rather than change it, teach
        // the in-memory test database the two functions it is missing. This is
        // test scaffolding only; nothing in app/ is aware of it.
        $pdo = \DB::connection()->getPdo();
        if (\DB::connection()->getDriverName() === 'sqlite' && method_exists($pdo, 'sqliteCreateFunction')) {
            $pdo->sqliteCreateFunction('YEAR',  fn ($d) => $d ? (int) date('Y', strtotime($d)) : null, 1);
            $pdo->sqliteCreateFunction('MONTH', fn ($d) => $d ? (int) date('n', strtotime($d)) : null, 1);
        }

        $this->admin = User::create([
            'name' => 'Test Admin', 'username' => 'testadmin',
            'password' => 'secret123', 'role' => User::ROLE_ADMIN, 'is_active' => true,
        ]);

        // The migrations already seed default sites, labor types and shifts, so
        // take what is there rather than colliding with it.
        $this->site = Site::firstOrCreate(['name' => 'Site A'], ['location' => 'Naga']);

        $labor = LaborType::firstOrCreate(['name' => 'Mason'], ['daily_rate' => 1000, 'ot_rate' => 0]);

        $shift = Shift::where('crosses_midnight', false)->first()
            ?? Shift::create([
                'name' => 'Day', 'starts_at' => '08:00', 'ends_at' => '17:00',
                'crosses_midnight' => false,
            ]);

        $this->employee = Employee::create([
            'name' => 'Juan Dela Cruz', 'position' => 'Mason',
            'rate_per_hour' => 125, 'labor_type_id' => $labor->id,
            'site_id' => $this->site->id, 'shift_id' => $shift->id,
            'status' => 'active', 'fingerprint_id' => '1',
        ]);

        // A day the kiosk would have recorded. time_in/time_out are timestamp
        // columns, not time columns — SQLite accepts a bare 'HH:MM:SS' but
        // MySQL rejects it, so write what the kiosk actually writes.
        $day = now()->subDay()->startOfDay();
        Attendance::create([
            'employee_id' => $this->employee->id,
            'site_id'     => $this->site->id,
            'shift_id'    => $shift->id,
            'date'        => $day->toDateString(),
            'time_in'     => $day->copy()->setTime(8, 0)->toDateTimeString(),
            'time_out'    => $day->copy()->setTime(17, 0)->toDateTimeString(),
        ]);
    }

    /** Every screen the extension adds. */
    public function test_new_module_pages_render(): void
    {
        $urls = [
            '/leave-advances',
            '/leave-advances?tab=advances',
            '/payslips',
            '/payroll-reports',
            '/payroll-reports?report=employee',
            '/payroll-reports?report=site',
            '/payroll-reports?report=overtime',
            '/payroll-reports?report=deductions',
            '/payroll-reports?report=advances',
            '/users-roles',
            '/audit-logs',
            '/device-monitoring',
        ];

        foreach ($urls as $url) {
            $res = $this->actingAs($this->admin)->get($url);
            $this->assertSame(200, $res->getStatusCode(), "NEW route failed: {$url}");
        }
    }

    /**
     * The regression guard. Every one of these worked before the extension and
     * must still work after it.
     */
    public function test_existing_pages_still_render(): void
    {
        $urls = [
            '/dashboard',
            '/employees/create',
            '/employees/register',
            '/attendance',
            '/sites',
            '/payroll-records',
            '/analytics',
            '/ai-assistant',
            '/accounts/create',
            '/settings',
            '/system-settings',
            '/system-settings/security',
            '/system-settings/appearance',
        ];

        foreach ($urls as $url) {
            $res = $this->actingAs($this->admin)->get($url);
            $this->assertSame(200, $res->getStatusCode(), "EXISTING route broke: {$url}");
        }
    }

    /** The kiosk's own API is what records attendance. It must be untouched. */
    public function test_kiosk_api_still_answers(): void
    {
        foreach (['/api/kiosk/employees', '/api/kiosk/sites', '/api/kiosk/labor-types',
                  '/api/kiosk/settings', '/api/kiosk/today-attendance'] as $url) {
            $res = $this->getJson($url);
            $this->assertLessThan(500, $res->getStatusCode(), "KIOSK API broke: {$url}");
        }
    }

    /**
     * Payroll Processing, and with it the way to create, approve and
     * finalise a run, was removed on 2026-09-26. The runs already on file are
     * untouched, and their payslips still open off the figures they froze.
     */
    public function test_payslips_still_open_off_a_run_on_file(): void
    {
        $run = PayrollRun::create([
            'code' => PayrollRun::nextCode(), 'period_start' => now()->subWeek()->toDateString(),
            'period_end' => now()->toDateString(), 'status' => 'finalized',
        ]);
        $item = \App\Models\PayrollRunItem::create([
            'payroll_run_id' => $run->id, 'employee_id' => $this->employee->id,
            'employee_name' => $this->employee->name, 'gross_pay' => 1600, 'net_pay' => 1600,
        ]);

        $this->actingAs($this->admin)->get('/payslips?run=' . $run->id)->assertOk();
        $this->actingAs($this->admin)->get('/payslips/' . $item->id)->assertOk();
        $this->actingAs($this->admin)->get('/payslips/' . $item->id . '/print')->assertOk();
        $this->actingAs($this->admin)->get('/payslips/run/' . $run->id . '/print')->assertOk();
    }

    /** The permission map must actually close doors, not just decorate them. */
    public function test_module_access_is_enforced(): void
    {
        // HR, as Table 4.5 of Chapter 4 lists it: everything but user
        // administration and the audit trail.
        $hr = User::create([
            'name' => 'People Office', 'username' => 'hr1', 'password' => 'secret123',
            'role' => User::ROLE_HR, 'is_active' => true,
        ]);

        foreach (['/leave-advances', '/leave-advances?tab=advances',
                  '/payroll-reports', '/device-monitoring'] as $url) {
            $this->assertSame(200, $this->actingAs($hr)->get($url)->getStatusCode(), "HR lost {$url}");
        }
        $this->assertTrue($hr->canAccessModule(\App\Support\Modules::PAYSLIPS));

        // Users & Roles and Audit Logs stay behind the existing admin guard.
        $this->actingAs($hr)->get('/users-roles')->assertForbidden();
        $this->actingAs($hr)->get('/audit-logs')->assertForbidden();
    }

    /** HR opens every everyday screen the Staff role used to open. */
    public function test_hr_keeps_the_everyday_screens(): void
    {
        $staff = User::create([
            'name' => 'Staff', 'username' => 'staff1', 'password' => 'secret123',
            'role' => User::ROLE_HR, 'is_active' => true,
        ]);

        foreach (['/dashboard', '/employees/register', '/attendance', '/sites',
                  '/payroll-records', '/analytics'] as $url) {
            $this->assertSame(200, $this->actingAs($staff)->get($url)->getStatusCode(),
                "staff lost access to {$url}");
        }
    }

    /** Losing the last administrator would lock everyone out. */
    public function test_last_admin_cannot_be_demoted(): void
    {
        $this->actingAs($this->admin)
            ->patch('/users-roles/' . $this->admin->id . '/role', ['role' => User::ROLE_HR]);

        $this->assertTrue($this->admin->fresh()->isAdmin(), 'the only admin was demoted');
    }
}
