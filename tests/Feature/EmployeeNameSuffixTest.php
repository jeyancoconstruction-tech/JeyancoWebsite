<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\LaborType;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Jr., Sr., II… beside the last name on Register and Edit Employee (Michael,
 * 2026-09-30). The suffix is kept in its own column, so "Dela Cruz" stays the
 * surname, and it goes on the end of the name the rest of the app reads.
 */
class EmployeeNameSuffixTest extends TestCase
{
    use RefreshDatabase;

    private ?User $admin = null;

    private function admin(): User
    {
        return $this->admin ??= User::create([
            'name' => 'Admin', 'username' => 'admin.suffix', 'password' => Hash::make('secret123'),
            'is_admin' => true, 'is_active' => true,
        ]);
    }

    private function form(array $overrides = []): array
    {
        return array_merge([
            'profile_form'    => '1',
            'first_name'      => 'Juan',
            'middle_name'     => 'Santos',
            'last_name'       => 'Dela Cruz',
            'job_title'       => 'Mason',
            'employment_type' => Employee::EMPLOYMENT_DAILY,
            'labor_type_id'   => LaborType::firstOrCreate(['name' => 'Mason'], ['daily_rate' => 800])->id,
            'rate_per_hour'   => 100,
            'site_id'         => Site::firstOrCreate(['name' => 'Site A'])->id,
        ], $overrides);
    }

    public function test_the_suffix_goes_on_the_end_of_the_name(): void
    {
        $this->actingAs($this->admin())
             ->post(route('employees.store'), $this->form(['name_suffix' => 'Jr.']))
             ->assertSessionHasNoErrors();

        $employee = Employee::firstOrFail();
        $this->assertSame('Juan Santos Dela Cruz Jr.', $employee->name);
        $this->assertSame('Dela Cruz', $employee->last_name);
        $this->assertSame('Jr.', $employee->name_suffix);
    }

    public function test_only_the_listed_suffixes_are_taken(): void
    {
        $this->actingAs($this->admin())
             ->post(route('employees.store'), $this->form(['name_suffix' => 'Esq.']))
             ->assertSessionHasErrors('name_suffix');

        $this->assertSame(0, Employee::count());
    }

    public function test_both_forms_offer_it_beside_the_last_name(): void
    {
        $this->actingAs($this->admin())->post(route('employees.store'), $this->form(['name_suffix' => 'Sr.']));
        $employee = Employee::firstOrFail();

        $this->get(route('employees.create'))->assertOk()
             ->assertSee('name="name_suffix"', false)
             ->assertSeeInOrder(['name="last_name"', 'name="name_suffix"', '>Jr.</option>', '>Sr.</option>', '>II</option>'], false);

        $this->get(route('employees.edit', $employee->id))->assertOk()
             ->assertSee('<option value="Sr." selected>Sr.</option>', false);
    }

    public function test_the_suffix_can_be_changed_or_taken_off(): void
    {
        $this->actingAs($this->admin())->post(route('employees.store'), $this->form(['name_suffix' => 'Jr.']));
        $employee = Employee::firstOrFail();

        $this->put(route('employees.update', $employee->id), $this->form(['name_suffix' => 'III']))->assertSessionHasNoErrors();
        $this->assertSame('Juan Santos Dela Cruz III', $employee->fresh()->name);

        $this->put(route('employees.update', $employee->id), $this->form(['name_suffix' => '']))->assertSessionHasNoErrors();
        $this->assertSame('Juan Santos Dela Cruz', $employee->fresh()->name);
        $this->assertNull($employee->fresh()->name_suffix);
    }

    /** The quick-edit modal posts the name in parts but no suffix; it must not drop it. */
    public function test_a_save_without_the_suffix_keeps_it(): void
    {
        $this->actingAs($this->admin())->post(route('employees.store'), $this->form(['name_suffix' => 'Jr.']));
        $employee = Employee::firstOrFail();

        $this->put(route('employees.update', $employee->id), [
            'first_name' => 'Juan', 'middle_name' => 'Santos', 'last_name' => 'Dela Cruz',
            'labor_type_id' => $employee->labor_type_id, 'rate_per_hour' => 150,
        ])->assertSessionHasNoErrors();

        $this->assertSame('Juan Santos Dela Cruz Jr.', $employee->fresh()->name);
        $this->assertSame(150.0, $employee->fresh()->rate_per_hour);
    }

    /** A worker registered before the parts existed: a trailing Jr is the suffix, not the surname. */
    public function test_an_old_single_name_is_split_with_its_suffix(): void
    {
        $this->assertSame(
            ['name_suffix' => 'Jr.', 'first_name' => 'Pedro', 'last_name' => 'Reyes', 'middle_name' => ''],
            Employee::splitName('Pedro Reyes jr')
        );
        $this->assertSame('', Employee::splitName('Pedro Reyes')['name_suffix']);
        $this->assertSame('Jr', Employee::splitName('Jr')['first_name'], 'a lone word is a name');
    }
}
