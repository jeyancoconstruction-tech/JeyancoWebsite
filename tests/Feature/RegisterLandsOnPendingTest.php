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
 * Saving a worker lands on the Pending tab, with them in it.
 *
 * It used to redirect to …/employees/register#pending and let the hub's own
 * script read location.hash. A fragment never reaches the server, so the page
 * rendered with Active open and moved a moment after paint — you saw the wrong
 * tab first, and a slow or blocked script left you on it. The tab is chosen in
 * the markup now.
 *
 * This also replaces the paragraph that used to sit at the bottom of the form
 * explaining that a new worker is saved as Pending. Landing on the Pending tab
 * with them listed shows it rather than describing it in advance.
 */
class RegisterLandsOnPendingTest extends TestCase
{
    use RefreshDatabase;

    private ?User $admin = null;

    private function admin(): User
    {
        return $this->admin ??= User::create([
            'name'      => 'Admin',
            'username'  => 'admin.landing',
            'password'  => Hash::make('secret123'),
            'is_admin'  => true,
            'is_active' => true,
        ]);
    }

    private function profile(): array
    {
        return [
            'profile_form'  => 1,
            'first_name'    => 'Juan',
            'middle_name'   => 'Santos',
            'last_name'     => 'Dela Cruz',
            'labor_type_id' => LaborType::firstOrCreate(['name' => 'Mason'], ['daily_rate' => 800, 'ot_rate' => 125])->id,
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
        ];
    }

    public function test_registering_redirects_to_the_pending_tab(): void
    {
        $this->actingAs($this->admin())
             ->post(route('employees.store'), $this->profile())
             ->assertSessionHasNoErrors()
             ->assertRedirect(route('employees.register', ['tab' => 'pending']));
    }

    /** The redirect is only worth anything if the page opens there. */
    public function test_the_pending_tab_is_open_when_that_page_loads(): void
    {
        $this->actingAs($this->admin())
             ->post(route('employees.store'), $this->profile());

        $html = $this->actingAs($this->admin())
            ->get(route('employees.register', ['tab' => 'pending']))
            ->assertOk()
            ->getContent();

        // Chosen in the markup, not by a script after paint.
        $this->assertStringContainsString('class="rm-pane active" data-pane="pending"', $html);
        $this->assertStringNotContainsString('class="rm-pane active" data-pane="active"', $html);
        $this->assertStringContainsString('Juan Santos Dela Cruz', $html);
    }

    /** With nothing asked for, the common case leads. */
    public function test_active_leads_when_no_tab_is_asked_for(): void
    {
        Employee::create([
            'name'           => 'Already Active',
            'position'       => 'Mason',
            'labor_type_id'  => LaborType::firstOrCreate(['name' => 'Mason'], ['daily_rate' => 800, 'ot_rate' => 125])->id,
            'rate_per_hour'  => 100,
            'fingerprint_id' => '7',
            'status'         => Employee::STATUS_ACTIVE,
        ]);

        $html = $this->actingAs($this->admin())
            ->get(route('employees.register'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('class="rm-pane active" data-pane="active"', $html);
    }

    /**
     * A fresh system has nobody active and everybody pending. Opening on an
     * empty Active table would read as the registration having failed.
     */
    public function test_pending_leads_on_a_system_with_nobody_active(): void
    {
        $this->actingAs($this->admin())
             ->post(route('employees.store'), $this->profile());

        $html = $this->actingAs($this->admin())
            ->get(route('employees.register'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('class="rm-pane active" data-pane="pending"', $html);
    }

    public function test_the_form_no_longer_explains_the_pending_state(): void
    {
        $this->actingAs($this->admin())
             ->get(route('employees.create'))
             ->assertOk()
             ->assertDontSee('This worker is saved as');
    }
}
