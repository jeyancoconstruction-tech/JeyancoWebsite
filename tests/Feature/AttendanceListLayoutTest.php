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
            'att-col-shift'    => '130px',
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

    /**
     * Both tables label the same columns the same way, in the same order.
     *
     * Today's Attendance had no Date column, so from the third column on the
     * two disagreed: Session and Time In / Out sat 112px apart between the
     * tabs and the whole grid jumped sideways when you switched. Date earns
     * its place on that tab too — a row is filed under the workday it opened,
     * and the night crew's opened last night, so theirs reads yesterday.
     */
    public function test_both_tables_carry_the_same_columns_in_the_same_order(): void
    {
        $html = $this->page();

        foreach (['att-col-employee', 'att-col-site', 'att-col-shift', 'att-col-date',
                  'att-col-session', 'att-col-time', 'att-col-status'] as $class) {
            $this->assertSame(2, substr_count($html, $class . '"'),
                "{$class} belongs to both tables");
        }

        // In order, and the same order in each.
        preg_match_all('#<table class="attendance-table[^>]*>.*?</thead>#s', $html, $heads);
        $this->assertCount(2, $heads[0], 'both tables should be on the page');

        $order = array_map(function ($head) {
            preg_match_all('#att-col-([a-z]+)"#', $head, $m);
            return $m[1];
        }, $heads[0]);

        $this->assertSame(
            ['employee', 'site', 'shift', 'date', 'session', 'time', 'status'],
            $order[0]
        );
        $this->assertSame($order[0], $order[1], 'the tabs must not shuffle the columns between them');
    }

    /** A column added to one table needs a cell under it, or the row shifts. */
    public function test_todays_rows_are_filled_out_to_the_new_column(): void
    {
        $html = $this->page();

        preg_match('#<table class="attendance-table w-100">.*?</table>#s', $html, $today);
        $this->assertNotEmpty($today, "Today's table should be on the page");

        preg_match_all('#<th[ >]#', $today[0], $th);
        $this->assertCount(7, $th[0], 'Employee, Site, Shift, Date, Session, Time, Status');

        // The empty state has to reach across all of them.
        $this->assertStringContainsString('colspan="7"', $today[0]);
    }

    /**
     * The Shift column names the shift the day was worked under, which is
     * stamped on the record — not the worker's shift today. Moving a man to
     * the night crew must not rewrite last week's day shifts.
     */
    public function test_the_shift_column_reads_the_record_not_the_worker(): void
    {
        $this->seedCrew();

        $day   = Shift::where('crosses_midnight', false)->firstOrFail();
        $night = Shift::firstOrCreate(['name' => 'Night Crew'],
            ['starts_at' => '18:00:00', 'grace_period_minutes' => 10, 'crosses_midnight' => true]);

        Employee::query()->update(['shift_id' => $night->id]);

        $html = $this->actingAs($this->admin())
            ->get(route('attendance', ['tab' => 'history']))
            ->assertOk()
            ->getContent();

        preg_match('#<table class="attendance-table w-100" id="historyTable">.*?</table>#s', $html, $history);
        $this->assertNotEmpty($history, 'the History table should be on the page');

        $this->assertMatchesRegularExpression(
            '#<span class="att-shift"><i class="fas fa-sun"></i> ' . preg_quote(e($day->name), '#') . '</span>#',
            $history[0], 'each day names the shift it was worked under');
        $this->assertStringNotContainsString('Night Crew', $history[0],
            "the worker's shift today is not the shift those days were worked");
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
