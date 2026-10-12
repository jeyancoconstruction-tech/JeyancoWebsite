<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Kiosk;
use App\Models\LaborType;
use App\Models\Shift;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Register Employee is a step-by-step pop-up on the Employees page
 * (Michael's mockup, jeyanco-register-wizard.html, 2026-10-12).
 *
 * It was a page of its own with every section on it at once. His words:
 * copy only the pop-up, and keep the existing fields, saving and functions.
 * So the fields and their names are the ones the page had — the same ones
 * Edit Employee still has — the form posts to the same place under the same
 * rules, and the labor type still fills the rate and the position. What is
 * new is the order: Employment type and Employment & pay, the two that are
 * required, then four steps that can be skipped, then a review, then who was
 * registered.
 *
 * The first step — Regular or Contractual, the date hired, the contract's
 * months — came the same day from a second pair of screenshots ("add on 1st
 * page and use its functions"). What a contract then does is in
 * EmployeeContractTest.
 *
 * How it looks and walks is checked in a browser; this holds what the page
 * carries and what saving answers.
 */
class RegisterWizardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private LaborType $mason;
    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin', 'username' => 'admin.wizard', 'password' => 'secret123',
            'role' => User::ROLE_ADMIN, 'is_admin' => true, 'is_active' => true,
        ]);
        $this->mason = LaborType::create(['name' => 'Wizard Mason', 'daily_rate' => 800, 'ot_rate' => 125]);
        $this->site  = Site::create(['name' => 'Wizard Yard']);   // a site of its own: the seeded ones may have a kiosk
    }

    private function page(?User $as = null): string
    {
        return $this->actingAs($as ?? $this->admin)->get(route('employees.register'))->assertOk()->getContent();
    }

    /** The pop-up's own markup, cut out of the page. */
    private function wizard(?string $page = null): string
    {
        return Str::between($page ?? $this->page(), '<div class="modal fade" id="regWizard"', '<script src=');
    }

    /** Every field a form posts, by name. */
    private function names(string $html): array
    {
        preg_match_all('/<(?:input|select|textarea)\b[^>]*\sname="([^"]+)"/', $html, $m);

        return collect($m[1])->reject(fn ($n) => in_array($n, ['_token', '_method'], true))->unique()->sort()->values()->all();
    }

    /** What the pop-up posts when only the two required steps are filled in. */
    private function required(array $with = []): array
    {
        return $with + [
            'profile_form'    => '1',
            'employment_type' => Employee::EMPLOYMENT_REGULAR,
            'first_name'    => 'Juan',
            'last_name'     => 'Dela Cruz',
            'labor_type_id' => $this->mason->id,
            'job_title'     => 'Wizard Mason',
            'site_id'       => $this->site->id,
            'shift_id'      => Shift::where('crosses_midnight', false)->value('id'),
            'rate_per_hour' => '100.00',
            'date_hired'    => '2026-10-12',
            'nationality'   => 'Filipino',
        ];
    }

    private function save(array $fields, ?User $as = null)
    {
        return $this->actingAs($as ?? $this->admin)->postJson(route('employees.store'), $fields);
    }

    // ── The pop-up ───────────────────────────────────────────────────────

    public function test_the_employees_page_carries_the_pop_up(): void
    {
        $page   = $this->page();
        $wizard = $this->wizard($page);

        // Opened by the Register employee button, which is still a link.
        $this->assertMatchesRegularExpression(
            '~<a href="' . preg_quote(e(route('employees.register', ['register' => 1])), '~') . '" class="rmx-primary" id="rmAddBtn" data-register-open~',
            $page
        );

        // One form, posting where Register Employee always posted, as a full profile.
        $this->assertStringContainsString('id="regWizardForm" action="' . route('employees.store') . '" method="POST" novalidate', $wizard);
        $this->assertStringContainsString('<input type="hidden" name="profile_form" value="1">', $wizard);

        // Seven steps and the last word, one on screen at a time.
        foreach (range(1, 8) as $n) {
            $this->assertSame(1, substr_count($wizard, '<section data-step="' . $n . '"'));
        }
        $this->assertSame(1, substr_count($wizard, '<section data-step="1">'), 'the first step is the one showing');
        $this->assertSame(7, preg_match_all('/<section data-step="\d" hidden>/', $wizard), 'every step but the first starts hidden');
        $this->assertStringContainsString('Step 1 of 7', $wizard);

        foreach ([
            'Employment type', 'Choose how this worker is hired.',
            'Employment &amp; pay', 'Needed to put the worker on the payroll.',
            'Personal information', 'Address', 'Government IDs', 'Needed for SSS, PhilHealth and Pag-IBIG deductions.',
            'Contact information', 'In case of emergency', 'Review', 'Check the details before registering.',
            'Steps 1 and 2 are required.', 'Skip for now', 'Discard this registration? What you typed will be lost.', 'Keep editing',
        ] as $said) {
            $this->assertStringContainsString($said, $wizard);
        }
        foreach (['Type', 'Employment type', 'Employment', 'Personal', 'Address', 'Gov’t IDs', 'Contact', 'Review', 'You can skip this and fill it in later.', 'Register another'] as $said) {
            $this->assertStringContainsString(json_encode($said), $page, "the steps script says {$said}");
        }

        // Outside and Escape are answered by the pop-up, which asks before it loses anything.
        $this->assertStringContainsString('data-bs-backdrop="static" data-bs-keyboard="false"', $wizard);
    }

    /** The same fields Edit Employee has: neither form has one the other lacks. */
    public function test_it_asks_for_the_fields_the_form_always_had(): void
    {
        $employee = Employee::create([
            'name' => 'Ana Reyes', 'position' => 'Wizard Mason', 'labor_type_id' => $this->mason->id,
            'rate_per_hour' => 100, 'status' => Employee::STATUS_ACTIVE, 'site_id' => $this->site->id,
        ]);

        $edit   = $this->actingAs($this->admin)->get(route('employees.edit', $employee))->assertOk()->getContent();
        $edit   = $this->names(Str::between($edit, 'id="rgxForm"', '</form>'));
        $wizard = $this->names($this->wizard());

        // Edit carries the finger the kiosk enrolled; a new worker has none yet.
        $this->assertSame(array_values(array_diff($edit, ['fingerprint_id'])), $wizard);

        foreach (Employee::IDENTITY_FIELDS as $field) {
            $this->assertContains($field, $wizard);
        }
        // Every profile column that has an input anywhere has one here.
        foreach (array_diff(Employee::PROFILE_FIELDS, ['religion', 'education', 'work_experience', 'skills', 'notes']) as $field) {
            $this->assertContains($field, $wizard, "{$field} is on the profile and missing from the pop-up");
        }
    }

    /** The first step: Regular or Contractual, the date hired, and a contract's months. */
    public function test_the_first_step_asks_how_the_worker_is_hired(): void
    {
        $first = Str::between($this->wizard(), '<section data-step="1">', '<section data-step="2"');

        // Two cards to choose between, Regular chosen. Stored as 'daily',
        // which is what every worker already on file is.
        $this->assertStringContainsString('<input type="radio" name="employment_type" value="daily" checked>', $first);
        $this->assertStringContainsString('<input type="radio" name="employment_type" value="contractual">', $first);
        foreach ([
            '<b>Regular</b>', 'Stays on the Active list until removed.',
            '<b>Contractual</b>', 'Hired for a set number of months. Leaves the Active list when the contract ends.',
            'Contract length', '1 to 36 months.', 'Contract ends', 'Set the contract length', 'Leaves the Active list after this date.',
        ] as $said) {
            $this->assertStringContainsString($said, $first);
        }

        // Today is the day hired unless somebody says otherwise, and it is asked for.
        $this->assertMatchesRegularExpression('/<input type="date"[^>]*name="date_hired" value="' . now()->toDateString() . '" required>/', $first);

        // The months are off, and so not sent, until Contractual is chosen;
        // the end date is shown and never sent: the server works it out.
        $this->assertMatchesRegularExpression('/<input type="number"[^>]*name="contract_months" min="1" max="36"[^>]*\sdisabled>/', $first);
        $this->assertMatchesRegularExpression('/<input type="text"[^>]*id="rwEnds"[^>]*readonly/', $first);
        $this->assertStringNotContainsString('name="end_of_contract"', $first);
        $this->assertStringNotContainsString('name="contract_rate"', $first, 'no contract amount: a contractual worker is paid like anybody');
    }

    public function test_the_second_step_holds_the_rest_of_what_is_required(): void
    {
        $wizard = $this->wizard();
        $first  = Str::between($wizard, '<section data-step="2" hidden>', '<section data-step="3"');
        $rest   = Str::after($wizard, '<section data-step="3"');

        foreach (['first_name', 'last_name', 'labor_type_id', 'job_title', 'site_id', 'rate_per_hour'] as $field) {
            $this->assertMatchesRegularExpression('/<(input|select)[^>]*name="' . $field . '"[^>]*\srequired[\s>]/', $first, "{$field} is required");
        }
        $this->assertDoesNotMatchRegularExpression('/<(input|select)[^>]*\srequired[\s>]/', $rest, 'nothing after the second step is required');

        // The shift is two buttons, the day shift chosen, posted as shift_id.
        $day = Shift::where('crosses_midnight', false)->firstOrFail();
        $this->assertStringContainsString('<input type="hidden" name="shift_id" id="shift_select" value="' . $day->id . '">', $first);
        $this->assertMatchesRegularExpression('/<button type="button" data-v="' . $day->id . '"[^>]*\n?\s*class="on" aria-pressed="true">/', $first);
        $this->assertSame(Shift::count(), substr_count($first, '<button type="button" data-v="'));

    }

    /** The office's own labor types and sites, and the functions the page had. */
    public function test_it_keeps_the_forms_functions(): void
    {
        $page   = $this->page();
        $wizard = $this->wizard($page);

        $this->assertMatchesRegularExpression('/<option value="' . $this->mason->id . '" data-name="Wizard Mason" data-daily="800(\.00)?"[^>]*>Wizard Mason<\/option>/', $wizard);
        $this->assertStringContainsString('<option value="' . $this->site->id . '">Wizard Yard</option>', $wizard);

        // The labor type fills the rate (its own script) and the position.
        $this->assertStringContainsString("getElementById('labor_type_selector')", $page);
        $this->assertStringContainsString('/ 8).toFixed(2)', $page);
        $this->assertStringContainsString('fillPosition', $page);

        // Province, city and barangay from the PSGC lists.
        $this->assertStringContainsString(asset('js/address-picker.js'), $page);
        $this->assertStringContainsString('JeyancoAddress.init(', $page);
        foreach (['address_province', 'address_city', 'address_barangay'] as $id) {
            $this->assertMatchesRegularExpression('/<div class="ap-field"><input[^>]*id="' . $id . '"/', $wizard);
        }

        // Nothing here shares an id with the rest of the page.
        foreach (['first_name', 'last_name', 'email', 'phone', 'gender', 'job_title', 'rate_per_hour', 'site_select'] as $id) {
            $this->assertSame(1, substr_count($page, ' id="' . $id . '"'), "#{$id} is on the page once");
        }
    }

    // ── The old address ──────────────────────────────────────────────────

    public function test_the_old_address_opens_the_employees_page_with_the_pop_up_up(): void
    {
        $this->actingAs($this->admin)->get(route('employees.create'))
            ->assertRedirect(route('employees.register', ['register' => 1]));

        $page = $this->actingAs($this->admin)->get(route('employees.register', ['register' => 1]))->assertOk()->getContent();

        // The page opens it when the address asks, and takes the mark out.
        $this->assertStringContainsString("url.searchParams.has('register')", $page);
        $this->assertStringContainsString("url.searchParams.delete('register')", $page);

        $this->assertFileDoesNotExist(resource_path('views/employees/create.blade.php'), 'there is one registration form, and it is the pop-up');
    }

    public function test_the_search_leads_to_it(): void
    {
        $found = $this->actingAs($this->admin)->getJson(route('search.suggestions', ['q' => 'add employee']))->assertOk()->getContent();

        $this->assertStringContainsString(json_encode(route('employees.register', ['register' => 1])), $found);
    }

    // ── Saving ───────────────────────────────────────────────────────────

    /** The same save, answered to the pop-up instead of by a new page. */
    public function test_it_registers_with_only_the_required_steps(): void
    {
        $this->save($this->required(['middle_name' => 'Santos', 'name_suffix' => 'Jr.']))
            ->assertOk()
            ->assertExactJson([
                'success' => true,
                'id'      => ($id = Employee::sole()->id),
                'number'  => '#' . str_pad((string) $id, 4, '0', STR_PAD_LEFT),
                'name'    => 'Juan Santos Dela Cruz Jr.',
                'labor'   => 'Wizard Mason',
                'site'    => 'Wizard Yard',
                'kiosk'   => false,
                'pending' => true,
                'contract_ends' => null,
            ]);

        $worker = Employee::sole();

        $this->assertSame(Employee::EMPLOYMENT_REGULAR, $worker->employment_type);
        $this->assertNull($worker->end_of_contract, 'a regular worker has no end');
        $this->assertSame(Employee::STATUS_PENDING, $worker->status, 'pending until the kiosk enrols a finger, as before');
        $this->assertSame('Wizard Mason', $worker->position);
        $this->assertSame('Wizard Mason', $worker->job_title);
        $this->assertSame($this->mason->id, $worker->labor_type_id);
        $this->assertEqualsWithDelta(100.0, $worker->rate_per_hour, 0.001);
        $this->assertSame($this->site->id, $worker->site_id);
        $this->assertSame('2026-10-12', $worker->date_hired->toDateString());
        $this->assertNull($worker->fingerprint_id);
        $this->assertNull($worker->birth_date);
        $this->assertNull($worker->phone);
    }

    public function test_it_saves_every_step_that_was_filled_in(): void
    {
        $night = Shift::where('crosses_midnight', true)->firstOrFail();

        $this->save($this->required([
            'shift_id' => $night->id,
            'birth_date' => '1990-05-17', 'birth_place' => 'Naga City, Camarines Sur', 'gender' => 'Male', 'civil_status' => 'Married', 'blood_type' => 'O+',
            'address_province' => 'Camarines Sur', 'address_city' => 'City of Naga', 'address_barangay' => 'San Felipe', 'address_postal' => '4400', 'address_street' => '123 Rizal St.',
            'sss_number' => '34-1234567-8', 'philhealth_number' => '12-345678901-2', 'pagibig_number' => '1234-5678-9012', 'tin_number' => '123-456-789-000',
            'phone' => '0917 123 4567', 'email' => 'juan@example.com',
            'emergency_contact_name' => 'Maria Dela Cruz', 'emergency_contact_relation' => 'Spouse', 'emergency_contact_phone' => '0918 765 4321',
        ]))->assertOk()->assertJson(['success' => true]);

        $worker = Employee::sole();

        $this->assertSame($night->id, $worker->shift_id);
        $this->assertSame('1990-05-17', $worker->birth_date->toDateString());
        $this->assertSame(
            ['Naga City, Camarines Sur', 'Male', 'Married', 'O+', 'Filipino'],
            [$worker->birth_place, $worker->gender, $worker->civil_status, $worker->blood_type, $worker->nationality]
        );
        $this->assertSame('123 Rizal St., San Felipe, City of Naga, Camarines Sur, 4400', $worker->full_address);
        $this->assertSame(
            ['34-1234567-8', '12-345678901-2', '1234-5678-9012', '123-456-789-000'],
            [$worker->sss_number, $worker->philhealth_number, $worker->pagibig_number, $worker->tin_number]
        );
        $this->assertSame(
            ['0917 123 4567', 'juan@example.com', 'Maria Dela Cruz', 'Spouse', '0918 765 4321'],
            [$worker->phone, $worker->email, $worker->emergency_contact_name, $worker->emergency_contact_relation, $worker->emergency_contact_phone]
        );
    }

    /** The last step says whether there is a kiosk at their site to enrol on. */
    public function test_it_says_whether_the_site_has_a_kiosk(): void
    {
        Kiosk::create(['name' => 'Gate kiosk', 'code' => 'K-WIZ', 'site_id' => $this->site->id, 'is_active' => true]);

        $this->save($this->required())->assertOk()->assertJson(['kiosk' => true, 'site' => 'Wizard Yard']);
    }

    /** What the server refuses comes back by field, for the pop-up to show on the step it is on. */
    public function test_a_refused_save_names_the_fields(): void
    {
        $this->save($this->required(['first_name' => '', 'labor_type_id' => '', 'site_id' => '', 'job_title' => '', 'rate_per_hour' => '']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['first_name', 'labor_type_id', 'site_id', 'job_title', 'rate_per_hour']);

        $this->save($this->required(['birth_date' => now()->addDay()->toDateString(), 'email' => 'not-an-email', 'name_suffix' => 'Esq.']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['birth_date', 'email', 'name_suffix']);

        $this->assertSame(0, Employee::count());
    }

    /** Posted the ordinary way it still answers the ordinary way: on Pending, with the toast. */
    public function test_a_plain_post_still_lands_on_pending(): void
    {
        $this->actingAs($this->admin)->post(route('employees.store'), $this->required())
            ->assertRedirect(route('employees.register', ['tab' => 'pending']))
            ->assertSessionHas('success', 'Juan Dela Cruz is registered. They will become active once their fingerprint is enrolled at the kiosk.');
    }

    public function test_hr_registers_workers_too(): void
    {
        $hr = User::create([
            'name' => 'HR', 'username' => 'hr.wizard', 'password' => 'secret123',
            'role' => User::ROLE_HR, 'is_active' => true,
        ]);

        $this->assertStringContainsString('id="regWizardForm"', $this->page($hr));
        $this->save($this->required(), $hr)->assertOk()->assertJson(['success' => true, 'name' => 'Juan Dela Cruz']);
    }
}
