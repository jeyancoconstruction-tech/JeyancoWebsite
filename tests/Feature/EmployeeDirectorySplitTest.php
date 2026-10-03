<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\LaborType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The worker's profile page (View Details).
 *
 * The file is named for the Regular / Contractual split the old Employee
 * Directory showed as tabs. The directory was retired, and Contractual was
 * removed on 2026-10-03 (Michael): every worker is paid by the day now, so
 * the profile shows no employee type and no contract.
 */
class EmployeeDirectorySplitTest extends TestCase
{
    use RefreshDatabase;

    private ?User $admin = null;

    private function admin(): User
    {
        // Reused across calls within a test — a second User::create would
        // collide on the unique username.
        return $this->admin ??= User::create([
            'name'      => 'Admin',
            'username'  => 'admin.directory',
            'password'  => Hash::make('secret123'),
            'is_admin'  => true,
            'is_active' => true,
        ]);
    }

    private function regular(string $name): Employee
    {
        $labor = LaborType::create(['name' => 'Mason ' . $name, 'daily_rate' => 800, 'ot_rate' => 125]);

        return Employee::create([
            'name'            => $name,
            'status'          => Employee::STATUS_ACTIVE,
            'employment_type' => Employee::EMPLOYMENT_DAILY,
            'labor_type_id'   => $labor->id,
            'rate_per_hour'   => 100,
        ]);
    }

    public function test_the_details_page_is_where_editing_starts(): void
    {
        $employee = $this->regular('Ana Reyes');

        // The profile's way into editing.
        $this->actingAs($this->admin())
            ->get(route('employees.show', $employee->id))
            ->assertOk()
            ->assertSee(route('employees.edit', $employee->id), false);
    }

    /**
     * View Details is laid out like Register Employee — the same sections in
     * the same order — so that everything the form collects has somewhere to
     * be read. Before this, six personal fields were shown out of the twenty
     * the form asks for, and the rest were entered and then invisible.
     */
    public function test_the_details_page_uses_the_same_sections_as_the_registration_form(): void
    {
        $employee = $this->regular('Ana Reyes');

        $this->actingAs($this->admin())
            ->get(route('employees.show', $employee->id))
            ->assertOk()
            ->assertSee('Employment & Pay')
            ->assertSee('Personal Information')
            ->assertSee('Contact Information')
            ->assertSee('Address')
            ->assertSee('Government IDs');
    }

    public function test_the_details_page_shows_the_personal_facts_the_office_asks_for(): void
    {
        $employee = $this->regular('Ana Reyes');
        $employee->update([
            'gender'           => 'Female',
            'birth_date'       => '1995-03-04',
            'birth_place'      => 'Naga City',
            'address_city'     => 'Pili',
            'address_province' => 'Camarines Sur',
            'civil_status'     => 'Single',
            'email'            => 'ana@example.com',
            'sss_number'       => '00-1234567-8',
        ]);

        $this->actingAs($this->admin())
            ->get(route('employees.show', $employee->id))
            ->assertOk()
            ->assertSee('Personal Information')
            ->assertSee('Female')
            ->assertSee('Naga City')
            // City and province are their own fields here, as they are on the
            // form, rather than one line reading "Pili, Camarines Sur".
            ->assertSee('Pili')
            ->assertSee('Camarines Sur')
            // The two that used to be collected and never shown anywhere.
            ->assertSee('ana@example.com')
            ->assertSee('00-1234567-8');
    }

    /**
     * The opposite of what this page used to do. Dropping empty rows kept the
     * card short, but it also meant a field nobody had filled in looked
     * exactly like a field that does not exist — and the page no longer
     * matched the form it mirrors.
     */
    public function test_a_blank_field_says_it_is_blank_rather_than_disappearing(): void
    {
        $employee = $this->regular('Ana Reyes');
        $employee->update(['gender' => 'Female', 'birth_place' => null]);

        $html = $this->actingAs($this->admin())
            ->get(route('employees.show', $employee->id))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Gender', $html);
        $this->assertStringContainsString('Place of Birth', $html);
        $this->assertStringContainsString('Not recorded', $html);
        $this->assertStringContainsString('Not issued yet', $html);
    }

    /** Contractual is gone: the profile names no employee type or contract. */
    public function test_the_profile_shows_no_employee_type_or_contract(): void
    {
        $employee = $this->regular('Ana Reyes');

        $this->actingAs($this->admin())
            ->get(route('employees.show', $employee->id))
            ->assertOk()
            ->assertSee('Rate Per Hour')
            ->assertDontSee('Employee Type')
            ->assertDontSee('Contractual')
            ->assertDontSee('Contract Amount')
            ->assertDontSee('End of Contract');
    }

    /**
     * A row still marked contractual from before the removal is an ordinary
     * worker now: paid off its labor type like everybody else.
     */
    public function test_a_leftover_contractual_row_is_paid_like_everybody(): void
    {
        $employee = $this->regular('Old Contract');
        $employee->forceFill(['employment_type' => 'contractual', 'contract_rate' => 50000])->save();

        \App\Models\PayrollRate::create(array_merge(\App\Models\PayrollRate::DEFAULTS, [
            'effective_from' => '2026-01-01', 'created_by' => 'test',
        ]));
        \App\Models\Attendance::create([
            'employee_id' => $employee->id, 'date' => '2026-09-09', 'session' => 'AM',
            'time_in' => '2026-09-09 08:00:00', 'time_out' => '2026-09-09 17:00:00',
        ]);

        $row = collect(app(\App\Services\PayrollService::class)
            ->computeForRange('2026-09-07', '2026-09-13')['employees'])
            ->firstWhere('employee_id', $employee->id);

        $this->assertGreaterThan(0, $row['totals']['gross'] ?? 0, 'the day is paid');
    }
}
