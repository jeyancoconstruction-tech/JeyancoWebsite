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
 * The office wants a registration filled in completely — every field on the
 * Register Employee and Edit Employee pages except the photo and the four
 * Government ID numbers.
 *
 * The rule cannot simply be "these columns are required", because store() and
 * update() are also reached by the quick-edit modal on Register & Manage, which
 * posts five pay fields, and by the kiosk's complete endpoint, which posts what
 * it read off a finger. Requiring a birthday there would stop an admin
 * correcting a rate and stall an enrolment over something unrelated. So the
 * strictness hangs on a `profile_form` flag that only the two full forms post,
 * and that split is what these tests pin down — nothing in either controller
 * method shows it on its own.
 */
class EmployeeProfileRequiredTest extends TestCase
{
    use RefreshDatabase;

    private ?User $admin = null;

    private function admin(): User
    {
        return $this->admin ??= User::create([
            'name'      => 'Admin',
            'username'  => 'admin.profile',
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

    private function site(): Site
    {
        return Site::firstOrCreate(['name' => 'Site A']);
    }

    /** Everything the full form posts when it is properly filled in. */
    private function completeProfile(array $overrides = []): array
    {
        return array_merge([
            'profile_form'    => '1',
            'first_name'      => 'Juan',
            'middle_name'     => 'Santos',
            'last_name'       => 'Dela Cruz',
            'job_title'       => 'Mason',
            'date_hired'      => '2026-01-15',
            'employment_type' => Employee::EMPLOYMENT_DAILY,
            'labor_type_id'   => $this->laborType()->id,
            'rate_per_hour'   => 100,
            'site_id'         => $this->site()->id,

            'birth_date'   => '1990-05-04',
            'birth_place'  => 'Naga City, Camarines Sur',
            'gender'       => 'Male',
            'civil_status' => 'Single',
            'blood_type'   => 'O+',
            'nationality'  => 'Filipino',

            'phone' => '09171234567',
            'email' => 'juan@example.com',
            'emergency_contact_name'     => 'Maria Dela Cruz',
            'emergency_contact_relation' => 'Spouse',
            'emergency_contact_phone'    => '09181234567',

            'address_province' => 'Camarines Sur',
            'address_city'     => 'City of Naga',
            'address_barangay' => 'Abella',
            'address_street'   => '123 Rizal St.',
            'address_postal'   => '4400',
        ], $overrides);
    }

    public function test_a_fully_filled_form_registers_the_worker(): void
    {
        $this->actingAs($this->admin())
             ->post(route('employees.store'), $this->completeProfile())
             ->assertSessionHasNoErrors();

        $this->assertSame(1, Employee::count());
    }

    /**
     * Each field on its own, so a failure names the one that stopped being
     * required rather than reporting "the form rejects a blank submission".
     */
    public function test_every_field_on_the_full_form_is_required(): void
    {
        $required = [
            'middle_name', 'job_title', 'date_hired', 'site_id',
            'birth_date', 'birth_place', 'gender', 'civil_status', 'blood_type', 'nationality',
            'phone', 'email',
            'emergency_contact_name', 'emergency_contact_relation', 'emergency_contact_phone',
            'address_province', 'address_city', 'address_barangay', 'address_street', 'address_postal',
        ];

        foreach ($required as $field) {
            $this->actingAs($this->admin())
                 ->post(route('employees.store'), $this->completeProfile([$field => '']))
                 ->assertSessionHasErrors($field);   // the failure message names the field
        }

        $this->assertSame(0, Employee::count(), 'no rejected submission should have been saved');
    }

    /** The photo and the four ID numbers are the standing exception. */
    public function test_the_photo_and_government_ids_stay_optional(): void
    {
        $this->actingAs($this->admin())
             ->post(route('employees.store'), $this->completeProfile([
                 'sss_number'        => '',
                 'philhealth_number' => '',
                 'pagibig_number'    => '',
                 'tin_number'        => '',
             ]))
             ->assertSessionHasNoErrors();

        $employee = Employee::firstOrFail();

        $this->assertNull($employee->sss_number);
        $this->assertNull($employee->photo);
    }

    /**
     * The regression this whole design exists to prevent: the quick-edit modal
     * posts no profile at all, and must still be able to correct a rate.
     */
    public function test_the_quick_edit_modal_can_still_save_without_a_profile(): void
    {
        $employee = Employee::create([
            'name'          => 'Pedro Reyes',
            'position'      => 'Mason',
            'labor_type_id' => $this->laborType()->id,
            'rate_per_hour' => 100,
            'status'        => Employee::STATUS_ACTIVE,
        ]);

        $this->actingAs($this->admin())
             ->put(route('employees.update', $employee->id), [
                 'name'          => 'Pedro Reyes',
                 'labor_type_id' => $this->laborType()->id,
                 'rate_per_hour' => 150,
             ])
             ->assertSessionHasNoErrors();

        $this->assertSame(150.0, $employee->fresh()->rate_per_hour);
    }

    /** And the modal must not blank the profile it never posted. */
    public function test_a_modal_save_leaves_an_existing_profile_alone(): void
    {
        $this->actingAs($this->admin())
             ->post(route('employees.store'), $this->completeProfile());

        $employee = Employee::firstOrFail();

        $this->actingAs($this->admin())
             ->put(route('employees.update', $employee->id), [
                 'name'          => $employee->name,
                 'labor_type_id' => $this->laborType()->id,
                 'rate_per_hour' => 175,
             ])
             ->assertSessionHasNoErrors();

        $employee->refresh();

        $this->assertSame(175.0, $employee->rate_per_hour);
        $this->assertSame('Camarines Sur', $employee->address_province);
        $this->assertSame('Maria Dela Cruz', $employee->emergency_contact_name);
    }

    /**
     * A contractual worker has no labor type or hourly rate, and the form
     * hides both — so the contract fields take their place as required.
     */
    public function test_a_contractual_worker_must_supply_the_contract_instead(): void
    {
        $contractual = $this->completeProfile([
            'employment_type' => Employee::EMPLOYMENT_CONTRACTUAL,
            'labor_type_id'   => null,
            'rate_per_hour'   => null,
            'contract_rate'   => 300000,
            'end_of_contract' => '2026-12-31',
        ]);

        $this->actingAs($this->admin())
             ->post(route('employees.store'), $contractual)
             ->assertSessionHasNoErrors();

        foreach (['contract_rate', 'end_of_contract'] as $field) {
            $this->actingAs($this->admin())
                 ->post(route('employees.store'), array_merge($contractual, [$field => '']))
                 ->assertSessionHasErrors($field);
        }
    }
}
