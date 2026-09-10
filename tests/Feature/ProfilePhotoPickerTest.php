<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\LaborType;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Camera and Gallery on the Register Employee form.
 *
 * The regression worth guarding is an ordering one, and it is invisible in the
 * markup: the picker's script used to sit inline in the middle of the body,
 * where `bootstrap` does not exist yet — the bundle loads near the end of
 * layouts.blade.php. `new bootstrap.Modal(...)` threw on the first line of the
 * IIFE, so no listener was ever attached and both buttons did nothing at all.
 * The page looked completely correct while being completely dead.
 */
class ProfilePhotoPickerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
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

    private function site(): Site
    {
        return Site::firstOrCreate(['name' => 'Site A']);
    }

    /** A complete profile, so only the photo is under test. */
    private function profile(array $overrides = []): array
    {
        return array_merge([
            'profile_form'  => 1,
            'first_name'    => 'Juan',
            'middle_name'   => 'Santos',
            'last_name'     => 'Dela Cruz',
            'labor_type_id' => $this->laborType()->id,
            'rate_per_hour' => 100,
            'site_id'       => $this->site()->id,
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
        ], $overrides);
    }

    public function test_the_picker_script_runs_after_bootstrap_is_loaded(): void
    {
        $html = $this->actingAs($this->admin())
            ->get(route('employees.create'))
            ->assertOk()
            ->getContent();

        $bootstrap = strpos($html, 'bootstrap.bundle.min.js');
        $picker    = strpos($html, "getElementById('openCameraBtn')");

        $this->assertNotFalse($bootstrap, 'the Bootstrap bundle should be on the page');
        $this->assertNotFalse($picker, 'the picker script should be on the page');
        $this->assertGreaterThan(
            $bootstrap,
            $picker,
            'the picker script must come after Bootstrap, or new bootstrap.Modal() throws and both buttons go dead'
        );
    }

    public function test_the_photo_input_is_optional_and_inside_the_multipart_form(): void
    {
        $html = $this->actingAs($this->admin())
            ->get(route('employees.create'))
            ->assertOk()
            ->getContent();

        $formAt  = strpos($html, 'enctype="multipart/form-data"');
        $inputAt = strpos($html, 'id="galleryInput"');
        $closeAt = $formAt + strpos(substr($html, $formAt), '</form>');

        $this->assertNotFalse($formAt, 'the form must be multipart or the file is dropped silently');
        $this->assertGreaterThan($formAt, $inputAt);
        $this->assertLessThan($closeAt, $inputAt, 'the file input must be inside the form to be posted');

        // Optional: no `required`, and the accept list matches the server rule.
        $this->assertDoesNotMatchRegularExpression('/id="galleryInput"[^>]*\brequired\b/', $html);
        $this->assertStringContainsString('accept="image/jpeg,image/png"', $html);
    }

    /** The client cap and the message under the box both read the server's. */
    public function test_the_two_megabyte_cap_is_stated_and_enforced_on_the_client(): void
    {
        $html = $this->actingAs($this->admin())
            ->get(route('employees.create'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('max 2 MB', $html);
        $this->assertStringContainsString('MAX_BYTES = 2 * 1024 * 1024', $html);
        $this->assertStringContainsString("TYPES     = ['image/jpeg', 'image/png']", $html);
    }

    public function test_a_worker_registers_without_a_photo(): void
    {
        $this->actingAs($this->admin())
             ->post(route('employees.store'), $this->profile())
             ->assertSessionHasNoErrors();

        $this->assertNull(Employee::firstOrFail()->photo);
    }

    public function test_a_photo_is_stored_when_one_is_chosen(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin())
             ->post(route('employees.store'), $this->profile([
                 // create(), not image(): image() draws a real bitmap and needs
                 // the GD extension, which is not enabled everywhere this suite
                 // runs. The rule under test is mimes/max, not the pixels.
                 'photo' => UploadedFile::fake()->create('worker.jpg', 120, 'image/jpeg'),
             ]))
             ->assertSessionHasNoErrors();

        $photo = Employee::firstOrFail()->photo;

        $this->assertNotNull($photo);
        Storage::disk('public')->assertExists($photo);
    }

    /** The server still refuses what the client-side check is there to catch first. */
    public function test_an_oversized_photo_is_refused(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin())
             ->post(route('employees.store'), $this->profile([
                 'photo' => UploadedFile::fake()->create('huge.jpg', 3000, 'image/jpeg'),
             ]))
             ->assertSessionHasErrors('photo');

        $this->assertSame(0, Employee::count());
    }

    public function test_a_file_that_is_not_an_image_is_refused(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin())
             ->post(route('employees.store'), $this->profile([
                 'photo' => UploadedFile::fake()->create('resume.pdf', 40, 'application/pdf'),
             ]))
             ->assertSessionHasErrors('photo');

        $this->assertSame(0, Employee::count());
    }

    /** Edit shows the photo already on file, so Retake/Change replaces rather than starts blank. */
    public function test_edit_shows_the_photo_already_on_file(): void
    {
        Storage::fake('public');

        $employee = Employee::create([
            'name'          => 'Juan Dela Cruz',
            'position'      => 'Mason',
            'labor_type_id' => $this->laborType()->id,
            'rate_per_hour' => 100,
            'status'        => Employee::STATUS_ACTIVE,
            'photo'         => 'employees/existing.jpg',
        ]);

        $this->actingAs($this->admin())
             ->get(route('employees.edit', $employee->id))
             ->assertOk()
             ->assertSee('employees/existing.jpg', false);
    }
}
