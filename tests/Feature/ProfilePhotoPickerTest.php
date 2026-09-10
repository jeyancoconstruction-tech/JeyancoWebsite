<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\LaborType;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * There is no photo upload any more.
 *
 * The field worked — camera, gallery, preview, a 2 MB check — and it still
 * lost every picture put through it. Railway serves this app from a container
 * whose filesystem is replaced on each deploy, and there is no volume behind
 * storage/app/public, so an uploaded photo was certain to disappear; it only
 * needed a deploy to do it. A volume is the one real fix and was declined on
 * cost, so the field went instead. A control that silently loses what you give
 * it is worse than not having the control.
 *
 * What stays: the `photo` column, the three rows that still carry a path, and
 * the server-side handling. Nothing has to be rebuilt if a volume is ever
 * attached — the forms just stop offering it today.
 */
class ProfilePhotoPickerTest extends TestCase
{
    use RefreshDatabase;

    private ?User $admin = null;

    private function admin(): User
    {
        return $this->admin ??= User::create([
            'name'      => 'Admin',
            'username'  => 'admin.photo',
            'password'  => Hash::make('secret123'),
            'is_admin'  => true,
            'is_active' => true,
        ]);
    }

    private function laborType(): LaborType
    {
        return LaborType::firstOrCreate(['name' => 'Mason'], ['daily_rate' => 800, 'ot_rate' => 125]);
    }

    private function employee(?string $photo = null): Employee
    {
        return Employee::create([
            'name'          => 'Juan Dela Cruz',
            'position'      => 'Mason',
            'labor_type_id' => $this->laborType()->id,
            'rate_per_hour' => 100,
            'status'        => Employee::STATUS_ACTIVE,
            'photo'         => $photo,
        ]);
    }

    public function test_register_employee_no_longer_offers_a_photo(): void
    {
        $html = $this->actingAs($this->admin())
            ->get(route('employees.create'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('name="photo"', $html);
        $this->assertStringNotContainsString('id="openCameraBtn"', $html);
        $this->assertStringNotContainsString('id="galleryInput"', $html);
        $this->assertStringNotContainsString('Profile Photo', $html);
    }

    public function test_edit_employee_no_longer_offers_a_photo(): void
    {
        $html = $this->actingAs($this->admin())
            ->get(route('employees.edit', $this->employee()->id))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('name="photo"', $html);
        $this->assertStringNotContainsString('id="galleryInput"', $html);
    }

    /** The quick-edit and kiosk-complete modal carried its own picker. */
    public function test_the_register_and_manage_modal_no_longer_offers_a_photo(): void
    {
        $html = $this->actingAs($this->admin())
            ->get(route('employees.register'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('name="photo"', $html);
        $this->assertStringNotContainsString('id="empPhoto"', $html);

        // Leaving the handlers behind would have been worse than useless:
        // addEventListener on an element that is gone throws, and the throw
        // takes the whole modal script down with it.
        $this->assertStringNotContainsString('photoClr.addEventListener', $html);
        $this->assertStringNotContainsString('clearPhoto()', $html);
    }

    public function test_the_picker_partial_and_its_styles_are_gone(): void
    {
        $this->assertFileDoesNotExist(resource_path('views/employees/_photo_picker.blade.php'));

        $styles = File::get(resource_path('views/employees/_profile_styles.blade.php'));
        $this->assertStringNotContainsString('.ep-photo {', $styles);
        $this->assertStringNotContainsString('.ep-cam {', $styles);
    }

    public function test_registration_still_works_without_the_field(): void
    {
        $this->actingAs($this->admin())
             ->post(route('employees.store'), [
                 'profile_form'  => 1,
                 'first_name'    => 'Juan',
                 'middle_name'   => 'Santos',
                 'last_name'     => 'Dela Cruz',
                 'labor_type_id' => $this->laborType()->id,
                 'rate_per_hour' => 100,
                 'site_id'       => Site::firstOrCreate(['name' => 'Site A'])->id,
                 'job_title'     => 'Mason',
                 'date_hired'    => '2026-09-10',
                 'employment_type' => Employee::EMPLOYMENT_DAILY,
                 'birth_date'    => '1995-03-04',
                 'birth_place'   => 'Naga City',
                 'gender'        => 'Male',
                 'civil_status'  => 'Single',
                 'nationality'   => 'Filipino',
                 'phone'         => '09170001111',
                 'emergency_contact_name'     => 'Maria Dela Cruz',
                 'emergency_contact_relation' => 'Spouse',
                 'emergency_contact_phone'    => '09182223333',
                 'address_province' => 'Camarines Sur',
                 'address_city'     => 'City of Naga',
                 'address_barangay' => 'Abella',
                 'address_street'   => '123 Rizal St.',
                 'address_postal'   => '4400',
             ])
             ->assertSessionHasNoErrors();

        $this->assertNull(Employee::firstOrFail()->photo);
    }

    /**
     * Three rows still hold a path whose file a deploy wiped. Every place that
     * draws an avatar has to survive that — a broken image with the name
     * spilling out of it is the failure mode this guards.
     */
    public function test_a_row_with_a_dead_photo_path_still_falls_back(): void
    {
        $employee = $this->employee('employees/gone-with-a-deploy.jpg');

        foreach ([
            route('employees.index'),
            route('employees.register'),
            route('employees.show', $employee->id),
        ] as $url) {
            $html = $this->actingAs($this->admin())->get($url)->assertOk()->getContent();

            $this->assertStringContainsString(
                'onerror=',
                $html,
                "an avatar on {$url} should fall back when the file is missing"
            );
        }
    }
}
