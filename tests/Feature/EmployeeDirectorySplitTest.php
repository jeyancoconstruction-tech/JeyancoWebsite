<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\LaborType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The directory splits the workforce by how a worker is PAID, not by how they
 * clock in. A contractual worker is settled against their contract total and
 * never lands on a payslip — only their attendance is tracked — so the office
 * needs the two apart without opening each record.
 *
 * Worth pinning down: the split lives in three places that cannot see each
 * other — the counts in EmployeeController, the tab markup, and the per-row
 * data-type the filter reads. A change to any one of them silently stops the
 * tabs agreeing with the rows.
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

    public function test_the_directory_counts_regular_and_contractual_apart(): void
    {
        $this->regular('Ana Reyes');
        $this->regular('Ben Cruz');
        $this->contractual('Carlo Diaz');

        $this->actingAs($this->admin())
            ->get(route('employees.index'))
            ->assertOk()
            ->assertViewHas('stats', fn (array $stats) => $stats['total'] === 3
                && $stats['regular'] === 2
                && $stats['contractual'] === 1);
    }

    public function test_every_row_carries_the_type_the_tabs_filter_on(): void
    {
        $this->regular('Ana Reyes');
        $this->contractual('Carlo Diaz');

        $html = $this->actingAs($this->admin())
            ->get(route('employees.index'))
            ->assertOk()
            ->getContent();

        // The tab sets dirScope to these exact strings, so the rows must spell
        // them the same way or a tab filters everything out.
        $this->assertStringContainsString('data-type="regular"', $html);
        $this->assertStringContainsString('data-type="contractual"', $html);
    }

    public function test_a_contractual_worker_shows_the_contract_total_not_a_zero_hourly_rate(): void
    {
        $this->contractual('Carlo Diaz', 50000);

        $html = $this->actingAs($this->admin())
            ->get(route('employees.index'))
            ->assertOk()
            ->getContent();

        // rate_per_hour is 0 for contract work. Printing it in the rate column
        // would read as "unpaid" rather than "paid outside payroll", so the
        // cell carries the contract total and says what the figure is.
        // (A bare ₱0.00 check would not do — the vale column prints one.)
        $this->assertStringContainsString('<span class="emp-rate-contract">₱50,000.00</span>', $html);
        $this->assertStringContainsString('<span class="emp-rate-note">contract</span>', $html);
    }

    public function test_the_toolbar_offers_both_layouts(): void
    {
        $this->regular('Ana Reyes');

        $html = $this->actingAs($this->admin())
            ->get(route('employees.index'))
            ->assertOk()
            ->getContent();

        // The script toggles on these exact values.
        $this->assertStringContainsString('data-view="table"', $html);
        $this->assertStringContainsString('data-view="grid"', $html);
    }

    public function test_the_directory_carries_no_bulk_selection_machinery(): void
    {
        $this->regular('Ana Reyes');

        $html = $this->actingAs($this->admin())
            ->get(route('employees.index'))
            ->assertOk()
            ->getContent();

        // Bulk select was removed from this page; deleting is one row at a
        // time through the Remove modal. Half-removing it once left the grid
        // showing checkboxes nothing could act on, so the absence is pinned.
        $this->assertStringNotContainsString('selectionModeBtn', $html);
        $this->assertStringNotContainsString('bulkActionBar', $html);
        $this->assertStringNotContainsString('emp-row-check', $html);
    }

    public function test_a_row_opens_the_record_before_it_offers_to_change_it(): void
    {
        $employee = $this->regular('Ana Reyes');

        $html = $this->actingAs($this->admin())
            ->get(route('employees.index'))
            ->assertOk()
            ->getContent();

        // The way into a record is spelled out, not drawn as an eye.
        $this->assertStringContainsString('View Details', $html);
        $this->assertStringNotContainsString('fa-eye', $html);

        // And editing is not offered from the row: it starts on the details
        // page, where the whole record is in front of you first.
        $this->assertStringNotContainsString(
            route('employees.edit', $employee->id),
            $html
        );
    }

    public function test_the_details_page_is_where_editing_starts(): void
    {
        $employee = $this->regular('Ana Reyes');

        // The directory now leans on this button existing. If it ever goes,
        // there is no route to editing left anywhere.
        $this->actingAs($this->admin())
            ->get(route('employees.show', $employee->id))
            ->assertOk()
            ->assertSee(route('employees.edit', $employee->id), false);
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
        ]);

        $this->actingAs($this->admin())
            ->get(route('employees.show', $employee->id))
            ->assertOk()
            ->assertSee('Personal na impormasyon')
            ->assertSee('Female')
            ->assertSee('Naga City')
            ->assertSee('Pili, Camarines Sur');
    }

    public function test_a_blank_personal_field_is_left_out_rather_than_drawn_as_a_dash(): void
    {
        $employee = $this->regular('Ana Reyes');
        $employee->update(['gender' => 'Female']);

        $html = $this->actingAs($this->admin())
            ->get(route('employees.show', $employee->id))
            ->assertOk()
            ->getContent();

        // A column of empty labels says nothing and buries the one fact that
        // is on file, so only what is filled gets a row.
        $this->assertStringContainsString('Gender', $html);
        $this->assertStringNotContainsString('Birthplace', $html);
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
            ->assertSee('Contract amount')
            ->assertSee('buong proyekto')
            ->assertDontSee('kada araw');
    }

    public function test_the_payroll_rule_behind_the_split_still_holds(): void
    {
        // The whole reason the directory separates them: contract work earns
        // nothing through payroll.
        $this->assertTrue($this->contractual('Carlo Diaz')->isExcludedFromPayroll());
        $this->assertFalse($this->regular('Ana Reyes')->isExcludedFromPayroll());
    }
}
