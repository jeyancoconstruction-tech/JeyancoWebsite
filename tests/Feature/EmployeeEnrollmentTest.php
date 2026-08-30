<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\LaborType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * A worker registered on the web is not part of the workforce yet. They wait
 * under Pending until the kiosk reads their finger, and that enrolment is what
 * activates them.
 *
 * Worth pinning down: the status transition is spread across two controllers
 * that never call each other — the web registration in EmployeeController and
 * the enrolment in KioskController — so nothing local to either one shows that
 * the handover works.
 */
class EmployeeEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    private ?User $admin = null;

    private function admin(): User
    {
        // Reused across calls within a test — a second User::create would
        // collide on the unique username.
        return $this->admin ??= User::create([
            'name'      => 'Admin',
            'username'  => 'admin.enroll',
            'password'  => Hash::make('secret123'),
            'is_admin'  => true,
            'is_active' => true,
        ]);
    }

    private function laborType(): LaborType
    {
        return LaborType::create(['name' => 'Mason', 'daily_rate' => 800, 'ot_rate' => 125]);
    }

    private function register(array $overrides = [])
    {
        return $this->actingAs($this->admin())->post(route('employees.store'), array_merge([
            'first_name'      => 'Juan',
            'middle_name'     => 'Santos',
            'last_name'       => 'Dela Cruz',
            'employment_type' => Employee::EMPLOYMENT_DAILY,
            'labor_type_id'   => $this->laborType()->id,
            'rate_per_hour'   => 100,
        ], $overrides));
    }

    public function test_a_web_registration_lands_in_pending_without_a_fingerprint(): void
    {
        $this->register();

        $employee = Employee::firstOrFail();

        $this->assertSame(Employee::STATUS_PENDING, $employee->status);
        $this->assertNull($employee->fingerprint_id);
        $this->assertSame('Juan Santos Dela Cruz', $employee->name);

        $this->assertTrue(Employee::pending()->whereKey($employee->id)->exists());
        $this->assertFalse(Employee::active()->whereKey($employee->id)->exists());
    }

    public function test_enrolling_a_fingerprint_at_the_kiosk_activates_the_worker(): void
    {
        $this->register();
        $employee = Employee::firstOrFail();

        $response = $this->postJson('/api/kiosk/save-fingerprint', [
            'employee_id'    => $employee->id,
            'fingerprint_id' => '4242',
        ]);

        $response->assertOk()
                 ->assertJson(['success' => true, 'activated' => true]);

        $employee->refresh();

        $this->assertSame(Employee::STATUS_ACTIVE, $employee->status);
        $this->assertSame('4242', $employee->fingerprint_id);

        $this->assertTrue(Employee::active()->whereKey($employee->id)->exists());
        $this->assertFalse(Employee::pending()->whereKey($employee->id)->exists());
    }

    public function test_an_archived_worker_re_enrolling_is_not_quietly_revived(): void
    {
        $employee = Employee::create([
            'name'          => 'Old Leaver',
            'position'      => 'Mason',
            'rate_per_hour' => 100,
            'labor_type_id' => $this->laborType()->id,
            'status'        => Employee::STATUS_ARCHIVED,
        ]);

        $this->postJson('/api/kiosk/save-fingerprint', [
            'employee_id'    => $employee->id,
            'fingerprint_id' => '5150',
        ])->assertOk()->assertJson(['success' => true, 'activated' => false]);

        $this->assertSame(Employee::STATUS_ARCHIVED, $employee->fresh()->status);
    }

    public function test_an_admin_who_supplies_a_fingerprint_activates_immediately(): void
    {
        $this->register(['fingerprint_id' => '77']);

        $employee = Employee::firstOrFail();

        $this->assertSame(Employee::STATUS_ACTIVE, $employee->status);
        $this->assertSame('77', $employee->fingerprint_id);
    }

    public function test_completing_details_alone_does_not_activate_a_worker(): void
    {
        // A worker registered on the web, then edited through the quick modal
        // before anyone enrolled their finger: details are complete, but there
        // is still nothing to clock in with.
        $this->register();
        $employee = Employee::firstOrFail();

        $this->actingAs($this->admin())->post(route('employees.complete', $employee->id), [
            'name'          => 'Juan Santos Dela Cruz',
            'labor_type_id' => $employee->labor_type_id,
            'rate_per_hour' => 100,
        ]);

        $this->assertSame(Employee::STATUS_PENDING, $employee->fresh()->status);
    }

    public function test_completing_a_kiosk_detection_that_has_a_finger_does_activate(): void
    {
        $employee = Employee::create([
            'name'           => 'Unregistered Worker',
            'position'       => 'Worker',
            'rate_per_hour'  => 0,
            'fingerprint_id' => '31',
            'status'         => Employee::STATUS_PENDING,
        ]);

        $this->actingAs($this->admin())->post(route('employees.complete', $employee->id), [
            'name'          => 'Pedro Reyes',
            'labor_type_id' => $this->laborType()->id,
            'rate_per_hour' => 100,
        ]);

        $this->assertSame(Employee::STATUS_ACTIVE, $employee->fresh()->status);
    }
}
