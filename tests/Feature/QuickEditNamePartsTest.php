<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\LaborType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The quick-edit modal on Register & Manage used to take the worker's name in
 * one box and write the `name` column straight, leaving first_name,
 * middle_name and last_name untouched. A correction made there and the same
 * correction made on Register Employee then disagreed: the directory showed
 * one spelling and the profile page another, with nothing on screen to say
 * why.
 *
 * It now posts the three parts, like the full form, and both endpoints behind
 * it compose `name` from them. `name` on its own is still accepted, because
 * the kiosk and anything else posting to complete() never sent parts.
 */
class QuickEditNamePartsTest extends TestCase
{
    use RefreshDatabase;

    private ?User $admin = null;

    private function admin(): User
    {
        return $this->admin ??= User::create([
            'name'      => 'Admin',
            'username'  => 'admin.quickedit',
            'password'  => Hash::make('secret123'),
            'is_admin'  => true,
            'is_active' => true,
        ]);
    }

    private function laborType(): LaborType
    {
        return LaborType::firstOrCreate(
            ['name' => 'Mason'],
            ['daily_rate' => 800, 'ot_rate' => 125]
        );
    }

    private function worker(string $name = 'Pedro Reyes'): Employee
    {
        return Employee::create([
            'name'          => $name,
            'position'      => 'Mason',
            'labor_type_id' => $this->laborType()->id,
            'rate_per_hour' => 100,
            'status'        => Employee::STATUS_ACTIVE,
        ]);
    }

    /** The pay fields the modal always posts, whatever the name looks like. */
    private function payFields(): array
    {
        return [
            'labor_type_id' => $this->laborType()->id,
            'rate_per_hour' => 100,
        ];
    }

    public function test_editing_with_parts_fills_the_name_and_the_three_columns(): void
    {
        $employee = $this->worker();

        $this->actingAs($this->admin())
             ->put(route('employees.update', $employee->id), $this->payFields() + [
                 'first_name'  => 'Pedro',
                 'middle_name' => 'Santos',
                 'last_name'   => 'Reyes',
             ])
             ->assertSessionHasNoErrors();

        $employee->refresh();

        $this->assertSame('Pedro Santos Reyes', $employee->name);
        $this->assertSame('Pedro',  $employee->first_name);
        $this->assertSame('Santos', $employee->middle_name);
        $this->assertSame('Reyes',  $employee->last_name);
    }

    /** A worker with no middle name is not a validation failure. */
    public function test_the_middle_name_may_be_left_out(): void
    {
        $employee = $this->worker();

        $this->actingAs($this->admin())
             ->put(route('employees.update', $employee->id), $this->payFields() + [
                 'first_name'  => 'Ana',
                 'middle_name' => '',
                 'last_name'   => 'Cruz',
             ])
             ->assertSessionHasNoErrors();

        $employee->refresh();

        $this->assertSame('Ana Cruz', $employee->name);
        $this->assertNull($employee->middle_name);
    }

    public function test_a_first_name_without_a_last_name_is_refused(): void
    {
        $employee = $this->worker();

        $this->actingAs($this->admin())
             ->put(route('employees.update', $employee->id), $this->payFields() + [
                 'first_name' => 'Ana',
                 'last_name'  => '',
             ])
             ->assertSessionHasErrors('last_name');

        $this->assertSame('Pedro Reyes', $employee->refresh()->name);
    }

    /**
     * complete() is the kiosk path. It hard-required `name` before, so posting
     * parts to it failed with "the name field is required" — which is what the
     * modal now posts for a kiosk-detected worker.
     */
    public function test_the_kiosk_complete_endpoint_accepts_parts(): void
    {
        $employee = $this->worker('Unknown');

        $this->actingAs($this->admin())
             ->post(route('employees.complete', $employee->id), $this->payFields() + [
                 'first_name'  => 'Maria',
                 'middle_name' => 'Dela',
                 'last_name'   => 'Cruz',
             ])
             ->assertSessionHasNoErrors();

        $employee->refresh();

        $this->assertSame('Maria Dela Cruz', $employee->name);
        $this->assertSame('Maria', $employee->first_name);
        $this->assertSame('Cruz',  $employee->last_name);
    }

    /** And still accepts a bare name, for anything that has not been updated. */
    public function test_the_kiosk_complete_endpoint_still_accepts_a_bare_name(): void
    {
        $employee = $this->worker('Unknown');

        $this->actingAs($this->admin())
             ->post(route('employees.complete', $employee->id), $this->payFields() + [
                 'name' => 'Jose Rizal',
             ])
             ->assertSessionHasNoErrors();

        $this->assertSame('Jose Rizal', $employee->refresh()->name);
    }

    /**
     * The row hands the modal parts it can fill three boxes with. A worker the
     * kiosk created carries only a bare name, so the model splits it rather
     * than dropping the whole thing into First.
     */
    public function test_a_row_offers_the_name_already_split(): void
    {
        $this->worker('Juan Santos Cruz');

        $this->actingAs($this->admin())
             ->get(route('employees.register'))
             ->assertOk()
             ->assertSee('data-first="Juan"', false)
             ->assertSee('data-middle="Santos"', false)
             ->assertSee('data-last="Cruz"', false);
    }

    /**
     * splitName takes the last word as the surname, so "Dela Cruz" arrives as
     * middle "Santos Dela" and last "Cruz". That is wrong for a compound
     * surname and cannot be guessed right — which is the reason the split is
     * only ever offered in editable boxes and never written to the record on
     * its own. Pinned here so the behaviour is a decision, not a surprise.
     */
    public function test_a_compound_surname_lands_in_the_middle_box_for_the_admin_to_fix(): void
    {
        $this->worker('Juan Santos Dela Cruz');

        $this->actingAs($this->admin())
             ->get(route('employees.register'))
             ->assertOk()
             ->assertSee('data-middle="Santos Dela"', false)
             ->assertSee('data-last="Cruz"', false);

        // Nothing was written: the record still holds the one name it had.
        $this->assertSame('Juan Santos Dela Cruz', Employee::firstOrFail()->name);
        $this->assertNull(Employee::firstOrFail()->first_name);
    }
}
