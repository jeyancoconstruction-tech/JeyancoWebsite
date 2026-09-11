<?php

namespace Tests\Feature;

use App\Models\LaborType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Choosing a labor type fills in Rate Per Hour and Position.
 *
 * The rate half broke because it was not its own thing: the listener lived
 * inside the New Site script, so removing that button removed the rate
 * auto-fill with it, silently. The form still said "Auto-filled from the
 * labor type" under a box that stayed at 0.00.
 *
 * These tests read the rendered page rather than click anything — no browser
 * here — so they pin the wiring the browser needs: the daily rate on each
 * option, a listener on the select, the divide by eight, and the two jobs
 * living in two places so one can be removed without taking the other.
 */
class LaborTypeAutofillTest extends TestCase
{
    use RefreshDatabase;

    private ?User $admin = null;

    private function admin(): User
    {
        return $this->admin ??= User::create([
            'name'      => 'Admin',
            'username'  => 'admin.autofill',
            'password'  => Hash::make('secret123'),
            'is_admin'  => true,
            'is_active' => true,
        ]);
    }

    private function page(): string
    {
        LaborType::firstOrCreate(['name' => 'Electrician'], ['daily_rate' => 1200, 'ot_rate' => 187.5]);

        return $this->actingAs($this->admin())
            ->get(route('employees.create'))
            ->assertOk()
            ->getContent();
    }

    /** The same form, opened on somebody who already exists. */
    private function editPage(): string
    {
        $labor = LaborType::firstOrCreate(['name' => 'Electrician'], ['daily_rate' => 1200, 'ot_rate' => 187.5]);

        $employee = \App\Models\Employee::create([
            'name'            => 'Mark Lloyd',
            'status'          => \App\Models\Employee::STATUS_ACTIVE,
            'employment_type' => \App\Models\Employee::EMPLOYMENT_DAILY,
            'labor_type_id'   => $labor->id,
            'rate_per_hour'   => 150,
        ]);

        return $this->actingAs($this->admin())
            ->get(route('employees.edit', $employee->id))
            ->assertOk()
            ->getContent();
    }

    /** Without data-daily on the option there is nothing to compute from. */
    public function test_each_labor_type_carries_its_daily_rate(): void
    {
        $this->assertMatchesRegularExpression('/data-daily="1200(\.00)?"/', $this->page());
    }

    public function test_the_rate_is_wired_to_the_labor_type(): void
    {
        $html = $this->page();

        $this->assertStringContainsString("getElementById('labor_type_selector')", $html);
        $this->assertStringContainsString("getElementById('rate_per_hour')", $html);
        $this->assertStringContainsString("ltSelector.addEventListener('change'", $html);

        // The hour is the standard eight, not the span of the shift.
        $this->assertStringContainsString('/ 8).toFixed(2)', $html);
    }

    /**
     * A failed validation brings the labor type back in the select. Without
     * the replayed change event the rate beside it comes back empty.
     */
    public function test_the_rate_is_recomputed_on_load_when_a_type_is_already_chosen(): void
    {
        $this->assertStringContainsString(
            "if (ltSelector.value) ltSelector.dispatchEvent(new Event('change'));",
            $this->page()
        );
    }

    /**
     * The regression itself: the rate listener must not share a script with
     * anything else, or removing that other thing takes it along.
     */
    public function test_the_rate_autofill_is_not_tangled_with_another_feature(): void
    {
        $html = $this->page();

        $this->assertStringNotContainsString('newSiteBtn', $html, 'New Site is gone and must stay gone');
        $this->assertStringNotContainsString('JeyancoSiteMap', $html);

        // Position is filled from the same select, in its own file.
        $this->assertStringContainsString('fillPosition', $html);
    }

    /**
     * Edit carried the same tangle, and it was removed the same way — so the
     * same two things have to be true of it: no New Site, and a rate that
     * still fills itself in.
     */
    public function test_the_edit_form_has_no_new_site_either(): void
    {
        $html = $this->editPage();

        $this->assertStringNotContainsString('newSiteBtn', $html);
        $this->assertStringNotContainsString('newSitePanel', $html);
        $this->assertStringNotContainsString('JeyancoSiteMap', $html,
            'and the map script it needed is not loaded for nothing');
        $this->assertStringNotContainsString('site-location-picker.js', $html);
    }

    public function test_the_edit_form_still_fills_the_rate_from_the_labor_type(): void
    {
        $html = $this->editPage();

        $this->assertMatchesRegularExpression('/data-daily="1200(\.00)?"/', $html);
        $this->assertStringContainsString("ltSelect.addEventListener('change'", $html);
        $this->assertStringContainsString('dataset.daily', $html);
    }

    /** A site is still chosen there — it is only the making of one that went. */
    public function test_the_edit_form_still_offers_the_sites_that_exist(): void
    {
        $this->assertStringContainsString('name="site_id"', $this->editPage());
    }
}
