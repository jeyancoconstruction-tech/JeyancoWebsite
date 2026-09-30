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
 * Whatever is done on the Pending tab lands back on the Pending tab (Michael,
 * 2026-09-30). Before, Confirm sent the page to Active, Edit → Save and
 * Cancel registration did too: the tab lived after a # in the address,
 * which never reaches the server, so every redirect opened the default tab.
 */
class PendingActionsStayOnPendingTest extends TestCase
{
    use RefreshDatabase;

    private ?User $admin = null;

    private function admin(): User
    {
        return $this->admin ??= User::create([
            'name' => 'Admin', 'username' => 'admin.pending', 'password' => Hash::make('secret123'),
            'is_admin' => true, 'is_active' => true,
        ]);
    }

    private function pending(?string $finger = '9'): Employee
    {
        return Employee::create([
            'name' => 'Kiosk Walk-in', 'status' => Employee::STATUS_PENDING, 'fingerprint_id' => $finger,
            'labor_type_id' => LaborType::firstOrCreate(['name' => 'Mason'], ['daily_rate' => 800])->id,
            'rate_per_hour' => 100, 'site_id' => Site::firstOrCreate(['name' => 'Site A'])->id,
        ]);
    }

    private function pendingTab(): string
    {
        return route('employees.register', ['tab' => 'pending']);
    }

    public function test_confirming_a_worker_stays_on_pending_even_when_they_go_active(): void
    {
        $e = $this->pending('9');

        $this->actingAs($this->admin())->post(route('employees.complete', $e->id), [
            'first_name' => 'Juan', 'last_name' => 'Dela Cruz',
            'labor_type_id' => $e->labor_type_id, 'rate_per_hour' => 120, 'fingerprint_id' => '9',
        ])->assertSessionHasNoErrors()->assertRedirect($this->pendingTab());

        $this->assertTrue($e->fresh()->isActive(), 'they did go active; the page stays where the office was');
    }

    public function test_saving_a_pending_worker_from_edit_goes_back_to_pending(): void
    {
        $e = $this->pending(null);

        $this->actingAs($this->admin())->put(route('employees.update', $e->id), [
            'first_name' => 'Juan', 'last_name' => 'Dela Cruz',
            'labor_type_id' => $e->labor_type_id, 'rate_per_hour' => 110,
        ])->assertSessionHasNoErrors()->assertRedirect($this->pendingTab());

        $this->get(route('employees.edit', $e->id))->assertOk()
             ->assertSee('href="' . e($this->pendingTab()) . '"', false);
    }

    public function test_cancelling_a_registration_goes_back_to_the_tab_it_came_from(): void
    {
        $e = $this->pending();

        $this->actingAs($this->admin())->from($this->pendingTab())
             ->delete(route('employees.destroy', $e->id))
             ->assertRedirect($this->pendingTab());
    }

    public function test_switching_tabs_writes_the_tab_into_the_address(): void
    {
        // What makes every back() above land on the right tab: the address
        // the page redirects back to carries ?tab=, not #tab.
        $html = $this->actingAs($this->admin())->get(route('employees.register'))->assertOk()->getContent();

        $this->assertStringContainsString("url.searchParams.set('tab', name);", $html);
        $this->assertStringNotContainsString("history.replaceState(null, '', '#' + name)", $html);
    }
}
