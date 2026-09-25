<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\LaborType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * A worker is split by how they are PAID, not by how they clock in. A
 * contractual worker is settled against their contract total and never lands
 * on a payslip; only their attendance is tracked.
 *
 * The Employee Directory that showed the split as tabs was retired for
 * Register & Manage. What is left here is the worker's profile page, which
 * still labels the two apart, and the payroll rule behind the split.
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

    private function contractual(string $name, float $contractTotal = 50000): Employee
    {
        return Employee::create([
            'name'            => $name,
            'status'          => Employee::STATUS_ACTIVE,
            'employment_type' => Employee::EMPLOYMENT_CONTRACTUAL,
            'contract_rate'   => $contractTotal,
            // The column is NOT NULL and the form leaves the hourly rate blank
            // for contract work, so a real contractual row stores zero here.
            // That zero is exactly what the directory must not print as a rate.
            'rate_per_hour'   => 0,
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

    public function test_a_contract_amount_is_labelled_as_the_project_total_not_a_daily_rate(): void
    {
        $employee = $this->contractual('Carlo Diaz', 50000);

        // The form asks for "Contract Amount — Total for the whole project".
        // The details page used to print that same number as "kada araw",
        // which reads as ₱50,000 a day against a ₱50,000 contract.
        $this->actingAs($this->admin())
            ->get(route('employees.show', $employee->id))
            ->assertOk()
            ->assertSee('Contract Amount')
            ->assertSee('whole project')
            ->assertDontSee('kada araw')
            ->assertDontSee('per day');
    }

    public function test_the_payroll_rule_behind_the_split_still_holds(): void
    {
        // The whole reason the directory separates them: contract work earns
        // nothing through payroll.
        $this->assertTrue($this->contractual('Carlo Diaz')->isExcludedFromPayroll());
        $this->assertFalse($this->regular('Ana Reyes')->isExcludedFromPayroll());
    }
}
