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
 * On Register Employee and Edit Employee only Employment & Pay is required
 * (Michael, 2026-09-30): the first and last name, the labor type and rate,
 * the position and the site. (There was an employee type too, and a
 * contract in place of the labor type; Contractual went on 2026-10-03.) The
 * middle name and the date hired are optional (the same day). Personal
 * Information, Address, Contact Information and Government IDs may be left
 * blank and filled in later. Until then the first three were required too.
 *
 * Even that rule cannot simply be "these columns are required", because store() and
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
    public function test_every_employment_and_pay_field_is_required(): void
    {
        $required = ['first_name', 'last_name', 'job_title', 'site_id', 'labor_type_id', 'rate_per_hour'];

        foreach ($required as $field) {
            $this->actingAs($this->admin())
                 ->post(route('employees.store'), $this->completeProfile([$field => '']))
                 ->assertSessionHasErrors($field);   // the failure message names the field
        }

        $this->assertSame(0, Employee::count(), 'no rejected submission should have been saved');
    }

    /** Every other section may be left blank and filled in later. */
    public function test_the_other_sections_may_be_left_blank(): void
    {
        $blank = array_fill_keys([
            'birth_date', 'birth_place', 'gender', 'civil_status', 'nationality', 'blood_type',
            'phone', 'email', 'emergency_contact_name', 'emergency_contact_relation', 'emergency_contact_phone',
            'address_province', 'address_city', 'address_barangay', 'address_street', 'address_postal',
            'sss_number', 'philhealth_number', 'pagibig_number', 'tin_number',
        ], '');

        $this->actingAs($this->admin())
             ->post(route('employees.store'), $this->completeProfile($blank))
             ->assertSessionHasNoErrors();

        $employee = Employee::firstOrFail();

        $this->assertSame('Juan Santos Dela Cruz', $employee->name);
        $this->assertNull($employee->birth_date);
        $this->assertNull($employee->address_province);
        $this->assertNull($employee->emergency_contact_name);
        $this->assertNull($employee->sss_number);
        $this->assertNull($employee->photo);
    }

    public function test_the_middle_name_and_date_hired_may_be_left_blank(): void
    {
        $this->actingAs($this->admin())
             ->post(route('employees.store'), $this->completeProfile(['middle_name' => '', 'date_hired' => '']))
             ->assertSessionHasNoErrors();

        $employee = Employee::firstOrFail();

        $this->assertSame('Juan Dela Cruz', $employee->name);
        $this->assertNull($employee->middle_name);
        $this->assertNull($employee->date_hired);
    }

    /** The form marks only Employment & Pay as required. */
    public function test_the_form_marks_only_employment_and_pay(): void
    {
        $html = $this->actingAs($this->admin())->get(route('employees.create'))->assertOk()->getContent();

        foreach (['middle_name', 'date_hired', 'birth_date', 'gender', 'phone', 'emergency_contact_name', 'address_province', 'address_postal'] as $field) {
            $this->assertDoesNotMatchRegularExpression('/<(input|select)[^>]*name="' . $field . '"[^>]*\srequired[\s>]/', $html, "{$field} is optional");
        }
        foreach (['first_name', 'last_name', 'job_title', 'rate_per_hour'] as $field) {
            $this->assertMatchesRegularExpression('/<input[^>]*name="' . $field . '"[^>]*\srequired[\s>]/', $html, "{$field} is required");
        }
        foreach (['labor_type_id', 'site_id'] as $field) {
            $this->assertMatchesRegularExpression('/<select[^>]*name="' . $field . '"[^>]*\srequired[\s>]/', $html, "{$field} is required");
        }
        $this->assertStringContainsString('Only Employment &amp; Pay is required', $html);
    }

    /**
     * Contractual was removed (Michael, 2026-10-03). Neither form offers an
     * employee type or a contract any more, and the labor type and rate are
     * marked required in the markup itself, since no script switches them.
     */
    public function test_neither_form_offers_an_employee_type_or_a_contract(): void
    {
        $employee = Employee::create([
            'name'          => 'Ana Reyes',
            'position'      => 'Mason',
            'labor_type_id' => $this->laborType()->id,
            'rate_per_hour' => 100,
            'status'        => Employee::STATUS_ACTIVE,
        ]);

        foreach ([route('employees.create'), route('employees.edit', $employee->id)] as $url) {
            $html = $this->actingAs($this->admin())->get($url)->assertOk()->getContent();

            foreach (['name="employment_type"', 'name="contract_rate"', 'name="end_of_contract"', 'Contractual', 'Employee Type'] as $gone) {
                $this->assertStringNotContainsString($gone, $html, "{$url} still shows {$gone}");
            }
            $this->assertMatchesRegularExpression('/<select[^>]*name="labor_type_id"[^>]*\srequired[\s>]/', $html);
        }
    }

    /** A post that still says contractual is held to the labor type and rate. */
    public function test_a_contractual_post_still_needs_a_labor_type_and_rate(): void
    {
        $this->actingAs($this->admin())
             ->post(route('employees.store'), $this->completeProfile([
                 'employment_type' => 'contractual',
                 'labor_type_id'   => null,
                 'rate_per_hour'   => null,
                 'contract_rate'   => 300000,
                 'end_of_contract' => '2026-12-31',
             ]))
             ->assertSessionHasErrors(['labor_type_id', 'rate_per_hour']);

        $this->assertSame(0, Employee::count());
    }

    /**
     * Correcting a rate on a worker whose profile was never filled in takes one
     * save again. Before 2026-09-30 the Edit page asked for fourteen profile
     * fields first; now only Employment & Pay has to be complete.
     */
    public function test_editing_an_incomplete_record_needs_only_employment_and_pay(): void
    {
        $employee = Employee::create([
            'name'          => 'Blank Profile',
            'position'      => 'Mason',
            'labor_type_id' => $this->laborType()->id,
            'rate_per_hour' => 100,
            'status'        => Employee::STATUS_ACTIVE,
        ]);

        $this->actingAs($this->admin())
             ->put(route('employees.update', $employee->id), [
                 'profile_form'  => 1,
                 'first_name'    => 'Blank',
                 'middle_name'   => 'X',
                 'last_name'     => 'Profile',
                 'labor_type_id' => $this->laborType()->id,
                 'rate_per_hour' => 125,
                 'site_id'       => $this->site()->id,
                 'job_title'     => 'Mason',
                 'date_hired'    => '2026-09-10',
             ])
             ->assertSessionHasNoErrors();

        $this->assertSame(125.0, (float) $employee->refresh()->rate_per_hour);
        $this->assertNull($employee->birth_date);
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
     * Position follows Labor Type. The form fills it and
     * locks it, so this is about the other door: a post that went around the
     * browser must not be able to store a title the labor type denies.
     */
    public function test_a_regular_workers_position_is_taken_from_the_labor_type(): void
    {
        $this->actingAs($this->admin())
             ->post(route('employees.store'), $this->completeProfile([
                 'job_title' => 'Something Else Entirely',
             ]))
             ->assertSessionHasNoErrors();

        $employee = Employee::firstOrFail();

        $this->assertSame('Mason', $employee->job_title);
        $this->assertSame('Mason', $employee->position, 'position and job title must agree');
    }

    /**
     * The modal posts a labor type but has no Position field. It must not have
     * one invented for it — that is the same overreach profileData() exists to
     * prevent everywhere else.
     */
    public function test_the_quick_edit_modal_does_not_rewrite_the_position(): void
    {
        $this->actingAs($this->admin())
             ->post(route('employees.store'), $this->completeProfile());

        $employee = Employee::firstOrFail();
        $employee->update(['job_title' => 'Kept By Hand']);

        $this->actingAs($this->admin())
             ->put(route('employees.update', $employee->id), [
                 'name'          => $employee->name,
                 'labor_type_id' => $this->laborType()->id,
                 'rate_per_hour' => 150,
             ])
             ->assertSessionHasNoErrors();

        $this->assertSame('Kept By Hand', $employee->fresh()->job_title);
    }
}
