<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\LaborType;
use App\Models\Shift;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The attendance lists are boxed to the screen, and their columns have room.
 *
 * Two complaints, one table. The page grew with the records, so the stat
 * cards, the tabs and the filters scrolled away and the column heads went
 * with them. And the table's own layout handed the widest column everything
 * it had: Time In / Out took 840 of 1,567 pixels while Employee was squeezed
 * to 143 — 100 on a 1366-wide screen, which is not a name.
 *
 * The measuring is done in the browser, which PHPUnit cannot see; what it can
 * hold is the wiring and the rules. Related: the Employee Directory is boxed
 * by the same shared partial.
 */
class AttendanceListLayoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-09-15 10:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private ?User $admin = null;

    private function admin(): User
    {
        return $this->admin ??= User::create([
            'name'      => 'Admin',
            'username'  => 'admin.attlayout',
            'password'  => Hash::make('secret123'),
            'is_admin'  => true,
            'is_active' => true,
        ]);
    }

    private function seedCrew(): void
    {
        $emp = Employee::create([
            'name'          => 'Mark Adrian Gulbe De Leon',
            'position'      => 'Mason',
            'status'        => Employee::STATUS_ACTIVE,
            'labor_type_id' => LaborType::firstOrCreate(['name' => 'Mason'], ['daily_rate' => 800, 'ot_rate' => 125])->id,
            'shift_id'      => Shift::where('crosses_midnight', false)->firstOrFail()->id,
            'rate_per_hour' => 100,
        ]);

        foreach (['2026-09-10', '2026-09-11'] as $on) {
            Attendance::create([
                'employee_id' => $emp->id,
                'site_id'     => Site::firstOrCreate(['name' => 'Site A'])->id,
                'shift_id'    => $emp->shift_id,
                'date'        => $on,
                'session'     => 'AM',
                'time_in'     => '08:00:00',
                'time_out'    => '17:00:00',
            ]);
        }
    }

    private function page(): string
    {
        $this->seedCrew();

        return $this->actingAs($this->admin())
            ->get(route('attendance', ['tab' => 'history']))
            ->assertOk()
            ->getContent();
    }

    // ── Boxed to the screen ──────────────────────────────────────────────

    public function test_both_lists_are_boxed_and_scroll_inside_their_cards(): void
    {
        $html = $this->page();

        // One card per tab, each with the list inside it marked to scroll.
        $this->assertSame(2, substr_count($html, 'class="table-card" data-fill-screen'),
            'Today and History both reach the bottom of the screen');
        $this->assertSame(2, substr_count($html, 'class="table-responsive" data-fill-scroll'),
            'and in each of them it is the list that scrolls');
    }

    public function test_the_shared_fill_screen_script_is_on_the_page(): void
    {
        $html = $this->page();

        $this->assertStringContainsString('[data-fill-screen] [data-fill-scroll]', $html,
            'the shared measuring script should be included');
        $this->assertStringContainsString('fills-screen', $html);
    }

    /**
     * The pane behind the other tab has no height to measure. Sized anyway it
     * came out against a top of zero, so switching tabs asks for a re-measure
     * and the script leaves a hidden list alone until then.
     */
    public function test_switching_tabs_asks_for_a_re_measure(): void
    {
        $html = $this->page();

        $this->assertStringContainsString('fill-screen:refit', $html);
        $this->assertStringContainsString('!wrap.offsetParent', $html,
            'a list on the closed tab is left alone');
    }

    /** Boxing the list is pointless if the heads scroll away with the rows. */
    public function test_the_column_heads_stay_while_the_rows_move(): void
    {
        $html = $this->page();

        $this->assertMatchesRegularExpression(
            '#\.attendance-table thead th \{ position:sticky; top:0;#', $html,
            'the heads have to be pinned to the top of the list'
        );
    }

    // ── Room for the labels ──────────────────────────────────────────────

    /**
     * Every column that holds something of a known size asks for the room it
     * needs; Time In / Out takes what is left over rather than taking it
     * first.
     */
    public function test_every_column_asks_for_the_room_its_label_needs(): void
    {
        $html = $this->page();

        foreach ([
            'att-col-employee' => '190px',
            'att-col-site'     => '160px',
            'att-col-date'     => '112px',
            'att-col-session'  => '120px',
            'att-col-status'   => '170px',
        ] as $class => $min) {
            // The rules are written in a column, so the spacing varies.
            $this->assertMatchesRegularExpression(
                "#\.{$class}\s+\{ min-width:{$min}; \}#", $html,
                "{$class} should hold its width");
        }

        $this->assertMatchesRegularExpression('#\.att-col-time\s+\{ width:100%; min-width:260px; \}#', $html,
            'Time In / Out absorbs the slack instead of claiming it');
    }

    /** A label broken over two lines is taller than its row and reads as two. */
    public function test_the_labels_never_wrap(): void
    {
        $html = $this->page();

        $this->assertStringContainsString('.attendance-table thead th { white-space:nowrap; }', $html);
    }

    /** Both tables label the same columns the same way, or they drift apart. */
    public function test_both_tables_carry_the_column_classes(): void
    {
        $html = $this->page();

        // Employee, Site, Session, Time and Status are on both; Date is on
        // History alone, since Today's Attendance is one date by definition.
        foreach (['att-col-employee', 'att-col-site', 'att-col-session', 'att-col-time', 'att-col-status'] as $class) {
            $this->assertSame(2, substr_count($html, $class . '"'),
                "{$class} belongs to both tables");
        }

        $this->assertSame(1, substr_count($html, 'att-col-date"'), 'only History has a Date column');
    }

    /**
     * A card that ends at the bottom of the screen puts its pager under the
     * floating chat button, which sat on top of the next-page arrow.
     */
    public function test_the_pager_is_kept_clear_of_the_chat_button(): void
    {
        $html = $this->page();

        $this->assertStringContainsString('class="mt-3 att-pager"', $html);
        $this->assertStringContainsString('.att-pager { padding-right:76px; }', $html);
    }
}
