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
        // Each row also carries the key a live patch finds it by.
        $this->assertMatchesRegularExpression('/<tr data-live-key="emp-\d+" data-site="Site A" data-labor="Welder">/', $this->pane($html, 'removed'));
        $this->assertMatchesRegularExpression('/<tr data-live-key="emp-\d+" data-site="Site A">/', $this->pane($html, 'pending'));
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

    /**
     * The list closes when the page scrolls under it, and that listener hears
     * the list's own scrolling too: a long Labor type list shut the moment it
     * was wheeled, so the types past the fold could not be reached (Michael,
     * 2026-10-01).
     */
    public function test_a_long_list_scrolls_without_closing(): void
    {
        $html = $this->page();

        $this->assertStringContainsString("window.addEventListener('scroll', function (e) { if (e.target !== menu) closeMenu(); }, true);", $html);
        $this->assertStringNotContainsString("window.addEventListener('scroll', closeMenu, true);", $html);
        // Reaching the end of the list does not hand the wheel to the page.
        $this->assertMatchesRegularExpression('/\.rmx-fmenu \{[^}]*overflow-y: auto; overscroll-behavior: contain;/', $html);
    }

    /**
     * A search box before Select (Michael, 2026-10-02): it finds a worker in
     * the list on screen by name or number. It narrows the rows the same way
     * the headings do — by hiding them — so the rule above covers it too.
     */
    public function test_a_search_box_sits_before_select(): void
    {
        $html = $this->page();

        $box    = strpos($html, 'id="rmxSearch"');
        $select = strpos($html, 'id="rmxSelect"');
        $this->assertNotFalse($box);
        $this->assertLessThan($select, $box, 'the search box comes before Select');
        $this->assertStringContainsString('placeholder="Search employee"', $html);

        // Read from the name and number each row already shows, so the rows
        // the live refresh brings in are searched as well.
        $this->assertStringContainsString("tr.querySelector('.rmx-who')", $html);
        // One test for the headings and the search alike: a row is on screen
        // only when it passes both.
        $this->assertStringContainsString('const matches = (tr, f, skip) => found(tr) && ', $html);
    }

    /** Shift, beside Labor type on the Active list (2026-09-30). */
    public function test_the_active_list_has_a_shift_column_to_filter_on(): void
    {
        $shift = \App\Models\Shift::where("crosses_midnight", true)->firstOrFail();
        $e = $this->worker("Night Owl", null, null);
        $e->forceFill(["shift_id" => $shift->id])->save();

        $active = $this->pane($this->page(), "active");

        $this->assertStringContainsString("class=\"rmx-fbtn\" data-filter=\"shift\"", $active);
        $this->assertStringContainsString("data-shift=\"" . e($shift->name) . "\"", $active);
        $this->assertStringContainsString("ti ti-moon", $active, "a night shift reads as one");
    }

    /**
     * The Removed card counted workers deleted for good whenever the page
     * refreshed itself (a kiosk enrolment sets that off), and was right again
     * after a reload. The live count is the page\x27s count.
     */
    public function test_the_live_refresh_counts_removed_as_the_page_does(): void
    {
        $gone = $this->worker("Deleted For Good", null, null);
        $gone->delete();
        $gone->deleteForGood();
        $kept = $this->worker("Just Removed", null, null);
        $kept->delete();

        $this->actingAs($this->admin())->getJson(route("employees.register.live"))
             ->assertOk()->assertJsonPath("counts.removed", 1);
    }
}
