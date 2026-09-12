<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\LaborType;
use App\Models\Loan;
use App\Models\LeaveRequest;
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
            '/leave-loans',
            '/leave-loans?tab=loans',
            '/project-assignments',
            '/payroll-processing',
            '/payslips',
            '/payroll-reports',
            '/payroll-reports?report=employee',
            '/payroll-reports?report=site',
            '/payroll-reports?report=overtime',
            '/payroll-reports?report=deductions',
            '/payroll-reports?report=loans',
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
            '/employees',
            '/employees/create',
            '/employees/register',
            '/attendance',
            '/sites',
            '/payroll-records',
            '/analytics',
            '/ai-assistant',
            '/accounts',
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

    /** The whole chain: leave + a loan, through a run, to a payslip — and no overtime claim. */
    public function test_payroll_run_computes_approves_and_finalises(): void
    {
        $from = now()->subDays(7)->toDateString();
        $to   = now()->toDateString();

        LeaveRequest::create([
            'employee_id' => $this->employee->id, 'leave_type' => 'vacation',
            'starts_on' => $from, 'ends_on' => $from, 'days' => 1,
            'is_paid' => true, 'status' => 'approved',
        ]);

        // A claim left on file from when overtime was filed by hand. Overtime
        // is counted from attendance now, so this must not be paid on top.
        \DB::table('overtime_requests')->insert([
            'employee_id' => $this->employee->id, 'site_id' => $this->site->id,
            'date' => $from, 'hours' => 3, 'hourly_rate' => 125,
            'multiplier' => 1.25, 'amount' => 468.75, 'status' => 'approved',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $loan = Loan::create([
            'employee_id' => $this->employee->id, 'type' => 'loan',
            'principal' => 2000, 'balance' => 2000, 'installment' => 500,
            'schedule' => 'per_payroll', 'issued_on' => $from, 'status' => 'active',
        ]);

        // Create — the controller calculates immediately.
        $this->actingAs($this->admin)->post('/payroll-processing', [
            'period_start' => $from, 'period_end' => $to,
        ])->assertRedirect();

        $run = PayrollRun::latest('id')->first();
        $this->assertNotNull($run, 'run was not created');
        $this->assertSame('calculated', $run->status);
        $this->assertGreaterThan(0, $run->items()->count(), 'run produced no lines');

        $item = $run->items()->first();

        $computed = app(\App\Services\PayrollService::class)->computeForRange($from, $to);
        $engine   = collect($computed['employees'])->firstWhere('employee_id', $this->employee->id);
        $otHours  = collect($computed['days'])->flatMap(fn ($d) => $d['details'] ?? [])
            ->where('employee_id', $this->employee->id)->sum('ot_hours');

        $this->assertEqualsWithDelta((float) data_get($engine, 'totals.overtime', 0), $item->overtime_pay, 0.01,
            'overtime is what attendance counted — the old claim must not be added');
        $this->assertEqualsWithDelta((float) $otHours, $item->ot_hours, 0.01,
            'the overtime hours on the line are the ones attendance counted');
        $this->assertGreaterThan(0, $item->leave_pay, 'approved paid leave was not credited');
        $this->assertSame(500.0, (float) $item->loan_deduction, 'loan instalment was not charged');

        // Recalculating must not collect the loan twice.
        $this->actingAs($this->admin)->post("/payroll-processing/{$run->id}/calculate");
        $this->assertSame(2000.0, (float) $loan->fresh()->balance, 'balance moved before finalisation');

        // Finalising without confirmation must fail.
        $this->actingAs($this->admin)->post("/payroll-processing/{$run->id}/approve");
        $this->actingAs($this->admin)
            ->post("/payroll-processing/{$run->id}/finalize", [])
            ->assertSessionHasErrors('confirm');
        $this->assertSame('approved', $run->fresh()->status, 'run finalised without confirmation');

        // With confirmation it finalises, and collects exactly once.
        $this->actingAs($this->admin)
            ->post("/payroll-processing/{$run->id}/finalize", ['confirm' => 1]);

        $this->assertSame('finalized', $run->fresh()->status);
        $this->assertSame(1500.0, (float) $loan->fresh()->balance, 'loan was not collected once');

        // Re-read the line: recalculating replaces a run's items wholesale, so
        // the row captured before that call no longer exists. A 404 on the old
        // id is the right answer — the test was holding a stale one.
        $item = $run->fresh()->items()->first();

        // And the payslip renders off the frozen figures.
        $this->actingAs($this->admin)->get('/payslips?run=' . $run->id)->assertOk();
        $this->actingAs($this->admin)->get('/payslips/' . $item->id)->assertOk();
        $this->actingAs($this->admin)->get('/payslips/' . $item->id . '/print')->assertOk();
        $this->actingAs($this->admin)->get('/payslips/run/' . $run->id . '/print')->assertOk();
    }

    /** A finalised run is history and must refuse to move. */
    public function test_finalised_run_cannot_be_recalculated_or_deleted(): void
    {
        $run = PayrollRun::create([
            'code' => PayrollRun::nextCode(), 'period_start' => now()->subWeek(),
            'period_end' => now(), 'status' => 'finalized',
        ]);

        $this->actingAs($this->admin)->post("/payroll-processing/{$run->id}/calculate");
        $this->actingAs($this->admin)->delete("/payroll-processing/{$run->id}");

        $this->assertNotNull(PayrollRun::find($run->id), 'a finalised run was deleted');
        $this->assertSame('finalized', $run->fresh()->status);
    }

    /** The permission map must actually close doors, not just decorate them. */
    public function test_module_access_is_enforced(): void
    {
        $employee = User::create([
            'name' => 'Worker', 'username' => 'worker1', 'password' => 'secret123',
            'role' => User::ROLE_EMPLOYEE, 'is_active' => true,
        ]);

        // An Employee may see their payslips and leave...
        $this->actingAs($employee)->get('/payslips')->assertOk();
        $this->actingAs($employee)->get('/leave-loans')->assertOk();

        // ...and nothing else the extension added — the loans tab included.
        foreach (['/payroll-processing', '/loans', '/leave-loans?tab=loans', '/payroll-reports',
                  '/project-assignments'] as $url) {
            $this->actingAs($employee)->get($url)->assertForbidden();
        }

        // Users & Roles and Audit Logs stay behind the existing admin guard.
        $this->actingAs($employee)->get('/users-roles')->assertForbidden();
        $this->actingAs($employee)->get('/audit-logs')->assertForbidden();
    }

    /** Existing 'staff' accounts must not lose anything they had. */
    public function test_staff_keeps_its_existing_access(): void
    {
        $staff = User::create([
            'name' => 'Staff', 'username' => 'staff1', 'password' => 'secret123',
            'role' => User::ROLE_STAFF, 'is_active' => true,
        ]);

        foreach (['/dashboard', '/employees', '/attendance', '/sites',
                  '/payroll-records', '/analytics'] as $url) {
            $this->assertSame(200, $this->actingAs($staff)->get($url)->getStatusCode(),
                "staff lost access to {$url}");
        }
    }

    /** Losing the last administrator would lock everyone out. */
    public function test_last_admin_cannot_be_demoted(): void
    {
        $this->actingAs($this->admin)
            ->patch('/users-roles/' . $this->admin->id . '/role', ['role' => User::ROLE_STAFF]);

        $this->assertTrue($this->admin->fresh()->isAdmin(), 'the only admin was demoted');
    }
}
