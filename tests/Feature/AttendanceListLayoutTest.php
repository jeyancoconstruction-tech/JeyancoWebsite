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
 * The attendance lists: one card, boxed to the screen, with the day laid out
 * the way a shift is — first session in and out, second session in and out —
 * beside a timeline, the hours and where the day stands.
 *
 * The measuring is done in the browser, which PHPUnit cannot see; what it can
 * hold is the wiring and the rules.
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

    // ── In the page ──────────────────────────────────────────────────────

    /**
     * One card holds the tabs, the filters and both lists, and it sits in the
     * page: the whole section scrolls up together, as the design has it,
     * rather than the list scrolling in a box fitted to the screen.
     */
    public function test_the_whole_section_scrolls_with_the_page(): void
    {
        $html = $this->page();

        $this->assertSame(1, substr_count($html, '<section class="atm-card" aria-label='), 'one card');
        $this->assertStringNotContainsString('data-fill-screen', $html, 'not boxed to the screen');
        $this->assertStringNotContainsString('data-fill-scroll', $html);
        $this->assertStringNotContainsString('position:sticky', $html, 'nothing pinned inside the list');

        // Each list is still its own live region.
        $this->assertMatchesRegularExpression('#class="atm-scroll" id="attTodayList" data-live="#', $html);
        $this->assertMatchesRegularExpression('#class="atm-scroll" id="attHistoryList" data-live="#', $html);
    }

    // ── The columns ──────────────────────────────────────────────────────

    /**
     * Both tables label the same columns the same way, in the same order, so
     * the grid does not jump sideways when the tab changes — the two sessions
     * grouped under their own heads.
     */
    public function test_both_tables_carry_the_same_columns_in_the_same_order(): void
    {
        $html = $this->page();

        preg_match_all('#<table class="atm-table" id="(todayTable|historyTable)">\s*<thead>(.*?)</thead>#s', $html, $heads);
        $this->assertCount(2, $heads[0], 'both tables should be on the page');

        $labels = array_map(function ($head) {
            preg_match_all('#<th(?: class="(?!att-check-col)[^"]*")?>([^<]+)</th>#', $head, $m);
            return array_map('trim', $m[1]);
        }, $heads[2]);

        $this->assertSame(
            ['Employee', 'Shift', 'Time in', 'Time out', 'Time in', 'Time out', 'Timeline', 'Hours', 'Status'],
            $labels[0]
        );
        $this->assertSame($labels[0], $labels[1], 'the tabs must not shuffle the columns between them');

        foreach ($heads[2] as $head) {
            $this->assertStringContainsString('<span>1st session</span>', $head);
            $this->assertStringContainsString('<span>2nd session</span>', $head);
        }
    }

    /**
     * An empty list keeps its column heads and says so under them, in the
     * middle of the room the card has — not as a row squeezed into the table.
     */
    public function test_an_empty_list_says_so_under_its_heads(): void
    {
        preg_match('#id="attTodayList".*?</table>\s*(.*?)</div>\s*</div>#s', $this->page(), $today);
        $this->assertNotEmpty($today, "Today's list should be on the page");
        $this->assertStringContainsString('<div class="atm-empty-state">', $today[1]);
        $this->assertStringContainsString('Nobody is clocked in right now.', $today[1]);
    }

    /**
     * The card reaches the bottom of the screen however little is in it, as
     * a minimum height — the page still scrolls as a whole when the list is
     * long.
     */
    public function test_the_card_reaches_the_bottom_of_the_screen(): void
    {
        $html = $this->page();

        $this->assertStringContainsString('function fillDown()', $html);
        $this->assertStringContainsString('card.style.minHeight', $html);
        $this->assertStringNotContainsString('data-fill-screen', $html, 'a minimum, not a box');
    }

    /**
     * The history reads as days under their dates, and each day as one row:
     * the two stretches of the 10th are not two lines.
     */
    public function test_history_is_grouped_under_its_dates(): void
    {
        $html = $this->page();

        $this->assertStringContainsString('Friday, 09/11/2026', $html);
        $this->assertStringContainsString('Thursday, 09/10/2026', $html);
        $this->assertSame(2, substr_count($html, '<b>Mark Adrian Gulbe De Leon</b>'), 'one row per day');
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

        preg_match('#<table class="atm-table" id="historyTable">.*?</table>#s', $html, $history);
        $this->assertNotEmpty($history, 'the History table should be on the page');

        $this->assertMatchesRegularExpression(
            '#<span class="atm-shift day">\s*<i class="fas fa-sun"></i>' . preg_quote(e($day->name), '#') . '\s*</span>#',
            $history[0], 'each day names the shift it was worked under');
        $this->assertStringNotContainsString('Night Crew', $history[0],
            "the worker's shift today is not the shift those days were worked");
    }

    /** What the timeline's marks mean is said once, under the list. */
    public function test_the_list_carries_its_key(): void
    {
        $html = $this->page();

        foreach (['Worked', 'Break window', 'On break', 'Overbreak', 'Missing or guessed scan', 'Late'] as $label) {
            $this->assertStringContainsString($label . '</span>', $html);
        }
    }

    /**
     * A card that ends at the bottom of the screen puts its pager under the
     * floating chat button, which sat on top of the next-page arrow.
     */
    public function test_the_pager_is_kept_clear_of_the_chat_button(): void
    {
        $html = $this->page();

        $this->assertStringContainsString('class="att-pager" id="attHistoryPager"', $html);
        $this->assertStringContainsString('.att-pager { padding:0 76px 0 16px; }', $html);
    }
}
