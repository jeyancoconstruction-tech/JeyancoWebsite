<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\LaborType;
use App\Models\User;
use App\Notifications\EmployeeAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The Employee Directory was retired in favour of Register & Manage, which
 * lists the same workers. The directory's Export and Add bonus moved there,
 * and so did the alerts it raised about workers missing a fingerprint or a
 * site. /employees still answers and leads to Register & Manage, so an old
 * link, a bookmark or a notification lands somewhere useful.
 */
class EmployeeDirectoryRetiredTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Retire Admin', 'username' => 'retire.admin', 'password' => 'secret123',
            'role' => User::ROLE_ADMIN, 'is_admin' => true, 'is_active' => true,
        ]);
    }

    public function test_the_old_address_leads_to_register_and_manage(): void
    {
        $this->actingAs($this->admin())->get('/employees')->assertRedirect(route('employees.register'));
    }

    /**
     * One entry for workers in the sidebar. It was called Register & Manage
     * beside the directory; with the directory gone it carries the plain
     * name, and it opens the page that used to be Register & Manage.
     */
    public function test_the_sidebar_has_one_employees_entry_and_it_owns_every_worker_page(): void
    {
        $admin = $this->admin();
        $emp   = Employee::create(['name' => 'Rafael Cruz', 'status' => Employee::STATUS_ACTIVE, 'rate_per_hour' => 100]);

        $html = $this->actingAs($admin)->get('/dashboard')->getContent();
        $this->assertSame(1, substr_count($html, '<span>Employees</span>'));
        $this->assertMatchesRegularExpression(
            '~href="' . preg_quote(route('employees.register'), '~') . '">\s*<i data-lucide="users"></i> <span>Employees</span>~',
            $html
        );
        $this->assertStringNotContainsString('<span>Register &amp; Manage</span>', $html);

        foreach ([route('employees.register'), route('employees.create'), route('employees.edit', $emp), route('employees.show', $emp)] as $url) {
            $page = $this->actingAs($admin)->get($url)->assertOk()->getContent();
            $this->assertMatchesRegularExpression(
                '~<a class="nav-link active" href="' . preg_quote(route('employees.register'), '~') . '">~',
                $page,
                "{$url} should light up Employees"
            );
        }
    }

    public function test_register_and_manage_carries_the_export(): void
    {
        $html = $this->actingAs($this->admin())->get(route('employees.register'))->assertOk()->getContent();

        $this->assertStringContainsString('href="' . route('employees.export') . '"', $html);
        $this->assertStringContainsString('Export to Excel', $html);
    }

    /** Every way back that used to point at the directory points here. */
    public function test_links_that_led_to_the_directory_lead_here(): void
    {
        $admin = $this->admin();
        $emp   = Employee::create(['name' => 'Rafael Cruz', 'status' => Employee::STATUS_ACTIVE, 'rate_per_hour' => 100]);
        $to    = 'href="' . route('employees.register') . '"';

        $this->assertStringContainsString('<a class="kpi" ' . $to . '>', $this->actingAs($admin)->get('/dashboard')->getContent(), 'Active Workers');
        $this->assertStringContainsString($to, $this->actingAs($admin)->get(route('employees.show', $emp))->getContent(), 'the profile');
        $this->assertStringContainsString($to, $this->actingAs($admin)->get(route('employees.edit', $emp))->getContent(), 'the edit form');

        // Saving an edit comes back here too.
        $labor = LaborType::create(['name' => 'Mason', 'daily_rate' => 800, 'ot_rate' => 125]);
        $this->actingAs($admin)
            ->put(route('employees.update', $emp), [
                'first_name' => 'Rafael', 'last_name' => 'Cruz',
                'labor_type_id' => $labor->id, 'rate_per_hour' => 100,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('employees.register'));
    }

    /** The alerts the directory raised are raised by Register & Manage now, and link to it. */
    public function test_the_workforce_alerts_moved_with_it(): void
    {
        $admin = $this->admin();
        Employee::create(['name' => 'No Print', 'status' => Employee::STATUS_ACTIVE, 'rate_per_hour' => 100]);

        $this->actingAs($admin)->get(route('employees.register'))->assertOk();

        $alert = $admin->notifications()->where('type', EmployeeAlert::class)->where('data->key', 'like', 'missing_fingerprint_%')->first();
        $this->assertNotNull($alert, 'a worker with no fingerprint is flagged');
        $this->assertSame('/employees/register', $alert->data['link']);
    }

    /** The per-row shift picker went with the directory; the edit form sets the shift. */
    public function test_the_quick_shift_endpoint_is_gone(): void
    {
        $this->assertFalse(Route::has('employees.shift'));
    }
}
