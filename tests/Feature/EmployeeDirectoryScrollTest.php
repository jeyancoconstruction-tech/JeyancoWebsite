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
 * The directory list is boxed to the screen and scrolls inside its card.
 *
 * The whole page used to grow with the workforce: thirty people made a 2,300px
 * page, so the heading, the total, the tabs and the filters scrolled away —
 * and the column heads went with them, leaving a reader to guess which column
 * a badge belonged to. Now the card reaches the bottom of the screen and only
 * the rows move, under heads that stay.
 *
 * The measuring is done in the browser, which PHPUnit cannot see. What it can
 * hold is the wiring: the card marked, the list marked, and the shared script
 * on the page — miss any one and the list silently goes back to stretching the
 * page. Related: the same partial does this for Leave & Advances.
 */
class EmployeeDirectoryScrollTest extends TestCase
{
    use RefreshDatabase;

    private ?User $admin = null;

    private function admin(): User
    {
        return $this->admin ??= User::create([
            'name'      => 'Admin',
            'username'  => 'admin.dirscroll',
            'password'  => Hash::make('secret123'),
            'is_admin'  => true,
            'is_active' => true,
        ]);
    }

    private function worker(string $name): Employee
    {
        return Employee::create([
            'name'          => $name,
            'position'      => 'Mason',
            'labor_type_id' => LaborType::firstOrCreate(['name' => 'Mason'], ['daily_rate' => 800, 'ot_rate' => 125])->id,
            'rate_per_hour' => 100,
            'status'        => Employee::STATUS_ACTIVE,
        ]);
    }

    private function page(): string
    {
        return $this->actingAs($this->admin())
            ->get(route('employees.index'))
            ->assertOk()
            ->getContent();
    }

    public function test_the_card_is_sized_to_the_screen_and_the_list_scrolls_in_it(): void
    {
        $this->worker('Ana Reyes');
        $html = $this->page();

        $this->assertStringContainsString('class="emp-card" data-fill-screen', $html,
            'the card should reach the bottom of the screen');
        $this->assertStringContainsString('data-fill-scroll', $html,
            'the list is the part that scrolls');

        // Marked on the list, not on something else in the card.
        $this->assertMatchesRegularExpression(
            '#<div class="table-responsive" id="empTableWrap" data-fill-scroll#', $html
        );
    }

    /** The marks do nothing without the script that measures them. */
    public function test_the_shared_fill_screen_script_is_on_the_page(): void
    {
        $this->worker('Ben Cruz');
        $html = $this->page();

        $this->assertStringContainsString('[data-fill-screen] [data-fill-scroll]', $html,
            'the shared script should be included');
        $this->assertStringContainsString('fills-screen', $html);
        $this->assertStringContainsString("fill-screen:refit", $html,
            'filtering to no matches has to re-measure the card');
    }

    /**
     * The heads only stay if the list is what scrolls, so the table keeps its
     * sticky thead.
     */
    public function test_the_column_heads_are_sticky(): void
    {
        $this->worker('Carlo Diaz');
        $html = $this->page();

        $this->assertMatchesRegularExpression(
            '#\.emp-table thead th \{[^}]*position: sticky#s', $html,
            'the column heads have to stay while the rows move'
        );
    }

    /**
     * One row each, and every row filled out to the same columns as the head.
     * A row short of a cell shifts every badge after it into the wrong column.
     */
    public function test_every_employee_gets_one_row_with_a_cell_per_column(): void
    {
        foreach (['Dina Flores', 'Elmo Garcia', 'Fina Herrera'] as $name) {
            $this->worker($name);
        }

        $html = $this->page();

        preg_match('#<table class="emp-table" id="empTable">.*?</table>#s', $html, $table);
        $this->assertNotEmpty($table, 'the directory table should be on the page');

        preg_match_all('#<th[ >]#', $table[0], $heads);
        preg_match_all('#<tr data-site=.*?</tr>#s', $table[0], $rows);

        $this->assertCount(3, $rows[0], 'one row per employee, no more');

        foreach ($rows[0] as $row) {
            preg_match_all('#<td[ >]#', $row, $cells);
            $this->assertCount(count($heads[0]), $cells[0],
                'every row needs a cell for every column, or the columns shift');
        }
    }

    /**
     * The rate cell is a table cell, and must not borrow the name of the Add
     * Employee form's rate box.
     *
     * _modal_styles is included on this page and gives .emp-rate a height, a
     * border and display:flex — a form field. Applied to a <td> it dropped the
     * vertical-align that centres a cell, so every rate sat above the row it
     * belonged to, boxed, while the rest of the row sat level.
     */
    public function test_the_rate_cell_does_not_reuse_the_form_field_class(): void
    {
        $this->worker('Gabo Ibarra');
        $html = $this->page();

        $this->assertStringContainsString('<td class="emp-rate-cell" data-label="Rate">', $html);
        $this->assertStringNotContainsString('<td class="emp-rate"', $html,
            'the cell must not take the modal rate box\'s styling');

        // And the modal's own box still has it.
        $this->assertStringContainsString('.emp-rate {', $html,
            'the form field keeps its own rule');
    }

    /** Boxing the list must not cost the grid view or the site filter. */
    public function test_the_other_views_still_render(): void
    {
        Site::firstOrCreate(['name' => 'Site A']);
        $this->worker('Hector Onsite');

        $html = $this->page();

        $this->assertStringContainsString('data-view="grid"', $html);
        $this->assertStringContainsString('id="siteFilter"', $html);
        $this->assertStringContainsString('id="empSearch"', $html);
    }
}
