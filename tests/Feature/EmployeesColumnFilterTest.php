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
 * The Site and Labor type headings on Employees are filters (Michael,
 * 2026-09-30): a click lists what is in the column with how many workers
 * each holds, and picking one shows only those workers. The picking happens
 * in the browser; what PHPUnit can hold is the wiring it reads, and the rule
 * that a hidden worker can never be selected for a bulk action.
 */
class EmployeesColumnFilterTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin', 'username' => 'admin.filter', 'password' => Hash::make('secret123'),
            'is_admin' => true, 'is_active' => true,
        ]);
    }

    private function worker(string $name, ?Site $site, ?LaborType $type, string $status = Employee::STATUS_ACTIVE): Employee
    {
        return Employee::create([
            'name' => $name, 'status' => $status, 'site_id' => $site?->id,
            'labor_type_id' => $type?->id, 'rate_per_hour' => 100, 'position' => $type?->name ?? 'Worker',
        ]);
    }

    private function page(): string
    {
        return $this->actingAs($this->admin())->get(route('employees.register'))->assertOk()->getContent();
    }

    /** The pane's markup, from its opening tag to the next pane's. */
    private function pane(string $html, string $name): string
    {
        $from = strpos($html, 'data-pane="' . $name . '"');
        $next = strpos($html, 'class="rm-pane', $from + 1);

        return substr($html, $from, $next === false ? null : $next - $from);
    }

    public function test_the_headings_are_filter_buttons(): void
    {
        $html = $this->page();

        foreach (['active', 'removed'] as $name) {
            $pane = $this->pane($html, $name);
            $this->assertStringContainsString('class="rmx-fbtn" data-filter="site"', $pane, "{$name}: Site");
            $this->assertStringContainsString('class="rmx-fbtn" data-filter="labor"', $pane, "{$name}: Labor type");
        }

        // Pending has no labor type column, so only Site.
        $pending = $this->pane($html, 'pending');
        $this->assertStringContainsString('class="rmx-fbtn" data-filter="site"', $pending);
        $this->assertStringNotContainsString('data-filter="labor"', $pending);
    }

    public function test_each_row_says_what_it_is_filtered_on(): void
    {
        $a     = Site::firstOrCreate(['name' => 'Site A']);
        $weld  = LaborType::create(['name' => 'Welder', 'daily_rate' => 1200]);
        $this->worker('Aldrin Sapugay', $a, $weld);
        $this->worker('Lito Kupal', null, null);
        $gone = $this->worker('Removed Welder', $a, $weld);
        $gone->delete();
        $this->worker('Kiosk Walk-in', $a, null, Employee::STATUS_PENDING);

        $html = $this->page();

        $this->assertStringContainsString('data-site="Site A" data-labor="Welder"', $this->pane($html, 'active'));
        $this->assertStringContainsString('data-site="" data-labor=""', $this->pane($html, 'active'), 'blank is "No site" / "No labor type"');
        $this->assertStringContainsString('<tr data-site="Site A" data-labor="Welder">', $this->pane($html, 'removed'));
        $this->assertStringContainsString('<tr data-site="Site A">', $this->pane($html, 'pending'));
    }

    public function test_a_hidden_row_is_never_selected(): void
    {
        $html = $this->page();

        // Select all, the selected count and every bulk action read boxesIn().
        $this->assertStringContainsString(
            "const boxesIn  = pane => Array.from(pane.querySelectorAll('tbody tr:not([hidden]) .rmx-check'));",
            $html
        );
        // Filtered-out rows stay hidden even as phone cards.
        $this->assertStringContainsString('.rmx-table tbody tr[hidden] { display: none !important; }', $html);
        // The summary beside the list's count, with its clear button.
        $this->assertStringContainsString('id="rmxFilterSum" hidden', $html);
    }
}
