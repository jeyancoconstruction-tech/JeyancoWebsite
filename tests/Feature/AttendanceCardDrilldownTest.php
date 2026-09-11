<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\LaborType;
use App\Models\Shift;
use App\Models\SystemSetting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The three cards answer "how many". Clicking one has to answer "who".
 *
 * A number on its own is not actionable: the office saw "1 missed sign-out"
 * above a table holding neither of the rows it was counting, because a missed
 * sign-out is past its shift's end and therefore sits in the history. The
 * count was right and there was no way to reach the man behind it.
 *
 * Each card is a link that narrows both tables to its own rows, lands on the
 * tab those rows are actually on, and leaves the counts above alone.
 */
class AttendanceCardDrilldownTest extends TestCase
{
    use RefreshDatabase;

    /** Friday mid-morning: the day crew is at work, last night's is not. */
    private const NOW = '2026-09-11 10:00:00';

    protected function setUp(): void
    {
        parent::setUp();
        SystemSetting::current()->forceFill(['schedule_rules_from' => '2026-09-01'])->save();
    }

    private function worker(string $name): Employee
    {
        return Employee::create([
            'name'            => $name,
            'status'          => Employee::STATUS_ACTIVE,
            'employment_type' => Employee::EMPLOYMENT_DAILY,
            'labor_type_id'   => LaborType::create(['name' => 'Mason ' . $name, 'daily_rate' => 800, 'ot_rate' => 125])->id,
            'shift_id'        => Shift::where('crosses_midnight', false)->firstOrFail()->id,
            'rate_per_hour'   => 100,
        ]);
    }

    private ?User $admin = null;

    private function admin(): User
    {
        return $this->admin ??= User::create([
            'name' => 'Admin', 'username' => 'admin.cards', 'password' => 'secret123',
            'is_admin' => true, 'is_active' => true,
        ]);
    }

    private function clock(Employee $e, string $type, string $at): void
    {
        Carbon::setTestNow(Carbon::parse($at, 'Asia/Manila'));
        $this->postJson('/api/kiosk/attendance', ['employee_id' => $e->id, 'type' => $type])
             ->assertOk()->assertJson(['success' => true]);
    }

    /**
     * Alice is still on site, Ben has gone home, and Carl never signed out on
     * Wednesday.
     */
    private function seedCrew(): void
    {
        $this->clock($this->worker('Alice Still In'), 'time_in', '2026-09-11 08:00:00');

        $ben = $this->worker('Ben Went Home');
        $this->clock($ben, 'time_in', '2026-09-11 08:00:00');
        $this->clock($ben, 'time_out', '2026-09-11 09:30:00');

        $this->clock($this->worker('Carl Forgot'), 'time_in', '2026-09-09 08:00:00');
    }

    private function page(array $query = [])
    {
        Carbon::setTestNow(Carbon::parse(self::NOW, 'Asia/Manila'));

        return $this->actingAs($this->admin())
                    ->get(route('attendance', $query))
                    ->assertOk();
    }

    public function test_the_cards_count_what_they_say(): void
    {
        $this->seedCrew();
        $page = $this->page();

        $this->assertSame(2, $page->viewData('presentToday'), 'Alice and Ben');
        $this->assertSame(1, $page->viewData('clockedIn'), 'Alice');
        $this->assertSame(1, $page->viewData('invalidCount'), 'Carl');
    }

    public function test_clicking_currently_clocked_in_shows_only_who_is_on_site(): void
    {
        $this->seedCrew();
        $page = $this->page(['view' => 'clocked-in']);

        $rows = $page->viewData('todayAttendances');
        $this->assertCount(1, $rows);
        $this->assertSame('Alice Still In', $rows->first()->employee->name);
        $this->assertSame('today', $page->viewData('openTab'));
    }

    public function test_clicking_missed_sign_out_reaches_the_row_behind_the_number(): void
    {
        $this->seedCrew();
        $page = $this->page(['view' => 'missed']);

        $this->assertSame(1, $page->viewData('historyAttendances')->total());
        $this->assertSame('Carl Forgot', $page->viewData('historyAttendances')->first()->employee->name);
        $page->assertSee('Carl Forgot');
    }

    /** The rows a card counts are not always on the tab that opens by default. */
    public function test_it_opens_the_tab_the_rows_are_actually_on(): void
    {
        $this->seedCrew();

        $this->assertSame('history', $this->page(['view' => 'missed'])->viewData('openTab'),
            'a missed sign-out is past its shift, so it lives in the history');
        $this->assertSame('today', $this->page()->viewData('openTab'),
            'with no card clicked the day view opens as before');
    }

    /** Clicking a card must not rewrite the other two. */
    public function test_narrowing_the_tables_leaves_the_counts_alone(): void
    {
        $this->seedCrew();
        $page = $this->page(['view' => 'clocked-in']);

        $this->assertSame(2, $page->viewData('presentToday'));
        $this->assertSame(1, $page->viewData('clockedIn'));
        $this->assertSame(1, $page->viewData('invalidCount'));
    }

    public function test_every_card_is_a_link_and_the_active_one_can_be_cleared(): void
    {
        $this->seedCrew();

        $this->page()
             ->assertSee('view=clocked-in', false)
             ->assertSee('view=missed', false)
             ->assertDontSee('Show all');

        $this->page(['view' => 'missed'])
             ->assertSee('Show all')
             ->assertSee('Showing only missed sign-outs this week');
    }

    /** A card click must not throw away the site and shift already chosen. */
    public function test_the_card_links_carry_the_filters_already_set(): void
    {
        $this->seedCrew();
        $shift = Shift::where('crosses_midnight', false)->firstOrFail();

        $html = $this->page(['shift' => $shift->id])->getContent();

        $this->assertStringContainsString('shift=' . $shift->id, $html);
        $this->assertStringContainsString('view=clocked-in', $html);
    }

    public function test_the_history_pages_keep_the_clicked_card(): void
    {
        $worker = $this->worker('Paginated');
        for ($i = 1; $i <= 20; $i++) {
            $this->clock($worker, 'time_in', Carbon::parse(self::NOW)->subDays($i)->format('Y-m-d') . ' 08:00:00');
        }

        $html = $this->page(['view' => 'missed'])->getContent();

        // The card link carries view=missed too, so look at the paging
        // links themselves rather than at the page as a whole.
        preg_match_all('/href="([^"]*page=2[^"]*)"/', $html, $m);
        $this->assertNotEmpty($m[1], 'there should be a second page of missed sign-outs');
        foreach ($m[1] as $href) {
            $this->assertStringContainsString('view=missed', html_entity_decode($href),
                'page 2 must stay on the same question');
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
