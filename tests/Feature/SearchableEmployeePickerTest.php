<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\LaborType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Employee box in File Leave and New Cash Advance takes a typed name or
 * ID (Michael, 2026-10-15: "make it searchable employee both cash advance and
 * file leave"). With the whole crew in it, scrolling a plain dropdown for one
 * name was slow.
 *
 * public/js/select-search.js does the work on any <select data-searchable>.
 * The select stays in the form and is what is sent, so the server and the
 * page's own scripts (the cash advance limit) are unchanged, and without the
 * script it is still an ordinary, required dropdown. It was driven in Chrome:
 * typing, arrows and Enter, an ID, Escape, an empty Record held back, and an
 * advance and a leave recorded for the worker picked, in light and dark and
 * on a phone. This holds the markup and the script's contract.
 */
class SearchableEmployeePickerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Picker Admin', 'username' => 'picker.admin', 'password' => 'secret123',
            'role' => User::ROLE_ADMIN, 'is_admin' => true, 'is_active' => true,
        ]);

        $labor = LaborType::create(['name' => 'Mason', 'daily_rate' => 800, 'ot_rate' => 100]);
        foreach (['Emilio Nuñez Sr.' => '11', 'Roy Ersolada' => '12'] as $name => $finger) {
            Employee::create([
                'name' => $name, 'status' => Employee::STATUS_ACTIVE, 'fingerprint_id' => $finger,
                'employment_type' => Employee::EMPLOYMENT_DAILY, 'labor_type_id' => $labor->id, 'rate_per_hour' => 100,
            ]);
        }
    }

    private function tab(string $tab): string
    {
        return $this->actingAs($this->admin)->get(route('leave.index', ['tab' => $tab]))->assertOk()->getContent();
    }

    public function test_both_employee_boxes_are_searchable_and_still_plain_required_selects(): void
    {
        foreach (['advances' => ['ca_emp', 'Choose the employee taking the advance.'],
                  'leave'    => ['lv_emp', 'Choose the employee filing the leave.']] as $tab => [$id, $message]) {
            $html = $this->tab($tab);

            $this->assertMatchesRegularExpression(
                '/<select class="form-select ?" id="' . $id . '" name="employee_id" required data-searchable data-search-required="' . preg_quote($message, '/') . '">/',
                $html, "{$tab}: the select is marked, and keeps its name and required");

            // Every worker is still an option, by ID, which is what the box
            // searches and what the form sends.
            foreach (Employee::all() as $e) {
                $this->assertMatchesRegularExpression('/<option value="' . $e->id . '"\s*>' . preg_quote(e($e->name), '/') . '<\/option>/', $html, "{$tab}: {$e->name}");
            }

            // The script, once, and its styles.
            $this->assertSame(1, substr_count($html, '/js/select-search.js?v='), "{$tab}: the script is loaded once");
            $this->assertStringContainsString('.ss-list {', $html, "{$tab}: the list is styled");
        }
    }

    public function test_the_script_keeps_the_select_in_charge(): void
    {
        $js = file_get_contents(public_path('js/select-search.js'));

        // The select is still what is sent and what the page listens to.
        $this->assertStringContainsString("select.dispatchEvent(new Event('change', { bubbles: true }))", $js);
        $this->assertStringContainsString("select.addEventListener('change', show)", $js, 'a value set from elsewhere shows in the box');
        // The box, not the hidden select, holds the form back while empty.
        $this->assertStringContainsString('select.required = false;', $js);
        $this->assertStringContainsString('input.setCustomValidity(needed && !select.value ? message : \'\')', $js);
        // Enter picks and never sends the form; Escape shuts the list, not the dialog.
        $this->assertStringContainsString('// Enter picks; it never sends the form from here.', $js);
        $this->assertStringContainsString('e.stopPropagation();', $js);
        // Names and IDs, accents ignored.
        $this->assertStringContainsString("normalize('NFD')", $js);
        $this->assertStringContainsString("'#' + code", $js);
    }

    public function test_a_refused_advance_comes_back_with_the_worker_still_picked(): void
    {
        $roy = Employee::where('name', 'Roy Ersolada')->first();

        // An instalment over the amount is refused, and the form reopens.
        $this->actingAs($this->admin)
            ->from(route('leave.index', ['tab' => 'advances']))
            ->post(route('loans.store'), [
                '_form' => 'advance', 'employee_id' => $roy->id, 'principal' => 500,
                'installment' => 900, 'schedule' => 'per_payroll', 'issued_on' => now()->toDateString(),
            ])->assertSessionHasErrors('installment');

        // The select comes back with Roy selected; the box shows the selected
        // option's name when the script starts.
        $html = $this->actingAs($this->admin)->get(route('leave.index', ['tab' => 'advances']))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<option value="' . $roy->id . '" selected(="selected")?>Roy Ersolada<\/option>/', $html);
    }
}
