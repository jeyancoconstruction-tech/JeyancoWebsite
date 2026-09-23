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
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * How far back the Attendance History table reaches.
 *
 * The other filters on that row — Site, Shift, and the status the stat cards
 * set — narrow the whole page. This one deliberately does not: Today's
 * Attendance is a single workday and the cards count today and this week, so
 * "last 6 months" has nothing to say about either. It narrows History, and it
 * has to do that without clearing anything else the reader has already chosen.
 */
class AttendanceHistoryDateRangeTest extends TestCase
{
    use RefreshDatabase;

    private Site $siteA;
    private Site $siteB;
    private Shift $day;
    private Shift $night;

    protected function setUp(): void
    {
        parent::setUp();

        // Pinned mid-morning, well inside a workday, so "history" means the
        // same thing for both crews however the suite is scheduled. Every
        // date below is counted from this Tuesday.
        Carbon::setTestNow(Carbon::parse('2026-09-15 10:00:00'));

        $this->siteA = Site::firstOrCreate(['name' => 'Site A']);
        $this->siteB = Site::firstOrCreate(['name' => 'Site B']);
        $this->day   = Shift::firstOrCreate(['name' => 'Day'],   ['starts_at' => '06:00:00', 'grace_period_minutes' => 10, 'crosses_midnight' => false]);
        $this->night = Shift::firstOrCreate(['name' => 'Night'], ['starts_at' => '18:00:00', 'grace_period_minutes' => 10, 'crosses_midnight' => true]);
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
            'username'  => 'admin.attrange',
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

    private function attendance(string $name, string $date, ?Site $site = null, ?Shift $shift = null): Attendance
    {
        return Attendance::create([
            'employee_id' => $this->worker($name)->id,
            'site_id'     => ($site ?? $this->siteA)->id,
            'shift_id'    => ($shift ?? $this->day)->id,
            'date'        => $date,
            'session'     => 'AM',
            'time_in'     => '06:00:00',
            'time_out'    => '15:00:00',
        ]);
    }

    /**
     * One worker per bucket, each just inside the range it belongs to and
     * just outside the next one down — so every assertion below names the
     * boundary it is actually testing.
     */
    private function seedLadder(): void
    {
        $this->attendance('Wina Week',   '2026-09-12'); // 3 days back
        $this->attendance('Monty Month', '2026-09-05'); // 10 days back
        $this->attendance('Quinn Quart', '2026-07-17'); // 60 days back
        $this->attendance('Hally Half',  '2026-05-15'); // 4 months back
        $this->attendance('Yuri Year',   '2026-01-15'); // 8 months back, same year
        $this->attendance('Otto Older',  '2025-11-20'); // last year
    }

    public function test_the_page_offers_every_range(): void
    {
        $this->actingAs($this->admin())
             ->get(route('attendance'))
             ->assertOk()
             ->assertSee('Last 7 Days')
             ->assertSee('Last 30 Days')
             ->assertSee('Last 3 Months')
             ->assertSee('Last 6 Months')
             ->assertSee('This Year')
             ->assertSee('All Time');
    }

    /** No range asked for is the whole history, the way the page read before. */
    public function test_the_default_is_all_time(): void
    {
        $this->seedLadder();

        $res = $this->actingAs($this->admin())->get(route('attendance'))->assertOk();

        $this->assertSame('all', $res->viewData('range'));
        $res->assertSee('Wina Week')->assertSee('Otto Older');
    }

    public function test_last_7_days(): void
    {
        $this->seedLadder();

        $this->actingAs($this->admin())
             ->get(route('attendance', ['range' => '7', 'tab' => 'history']))
             ->assertOk()
             ->assertSee('Wina Week')
             ->assertDontSee('Monty Month')
             ->assertDontSee('Otto Older');
    }

    public function test_last_30_days(): void
    {
        $this->seedLadder();

        $this->actingAs($this->admin())
             ->get(route('attendance', ['range' => '30', 'tab' => 'history']))
             ->assertOk()
             ->assertSee('Wina Week')
             ->assertSee('Monty Month')
             ->assertDontSee('Quinn Quart');
    }

    public function test_last_3_months(): void
    {
        $this->seedLadder();

        $this->actingAs($this->admin())
             ->get(route('attendance', ['range' => '3m', 'tab' => 'history']))
             ->assertOk()
             ->assertSee('Quinn Quart')
             ->assertDontSee('Hally Half');
    }

    public function test_last_6_months(): void
    {
        $this->seedLadder();

        $this->actingAs($this->admin())
             ->get(route('attendance', ['range' => '6m', 'tab' => 'history']))
             ->assertOk()
             ->assertSee('Hally Half')
             ->assertDontSee('Yuri Year');
    }

    /** January 1st of the current year, not twelve months back. */
    public function test_this_year(): void
    {
        $this->seedLadder();

        $this->actingAs($this->admin())
             ->get(route('attendance', ['range' => 'year', 'tab' => 'history']))
             ->assertOk()
             ->assertSee('Yuri Year')
             ->assertDontSee('Otto Older');
    }

    public function test_all_time_reaches_last_year(): void
    {
        $this->seedLadder();

        $this->actingAs($this->admin())
             ->get(route('attendance', ['range' => 'all', 'tab' => 'history']))
             ->assertOk()
             ->assertSee('Otto Older');
    }

    /**
     * The whole point of hanging it in the same form: choosing a range must
     * not quietly put Site and Shift back to All.
     */
    public function test_it_combines_with_site_and_shift_without_clearing_them(): void
    {
        $this->attendance('Keep Me',     '2026-09-12', $this->siteA, $this->day);
        $this->attendance('Wrong Site',  '2026-09-12', $this->siteB, $this->day);
        $this->attendance('Wrong Shift', '2026-09-12', $this->siteA, $this->night);
        $this->attendance('Too Old',     '2026-05-15', $this->siteA, $this->day);

        $res = $this->actingAs($this->admin())->get(route('attendance', [
            'site'  => $this->siteA->id,
            'shift' => $this->day->id,
            'range' => '7',
            'tab'   => 'history',
        ]))->assertOk();

        $res->assertSee('Keep Me')
            ->assertDontSee('Wrong Site')
            ->assertDontSee('Wrong Shift')
            ->assertDontSee('Too Old');

        // Still in force afterwards, not reset by the range.
        $this->assertSame($this->siteA->id, $res->viewData('siteId'));
        $this->assertSame($this->day->id,   $res->viewData('shiftId'));
        $this->assertSame('7',              $res->viewData('range'));
    }

    /** It narrows the status filter's rows too, rather than fighting it. */
    public function test_it_combines_with_the_status_filter(): void
    {
        // Missed sign-outs: clocked in, never out.
        Attendance::create([
            'employee_id' => $this->worker('Recent Miss')->id,
            'site_id'     => $this->siteA->id,
            'shift_id'    => $this->day->id,
            'date'        => '2026-09-12',
            'session'     => 'AM',
            'time_in'     => '06:00:00',
            'time_out'    => null,
        ]);
        Attendance::create([
            'employee_id' => $this->worker('Ancient Miss')->id,
            'site_id'     => $this->siteA->id,
            'shift_id'    => $this->day->id,
            'date'        => '2026-05-15',
            'session'     => 'AM',
            'time_in'     => '06:00:00',
            'time_out'    => null,
        ]);

        $this->actingAs($this->admin())
             ->get(route('attendance', ['view' => 'missed', 'range' => '7', 'tab' => 'history']))
             ->assertOk()
             ->assertSee('Recent Miss')
             ->assertDontSee('Ancient Miss');
    }

    /**
     * Today's Attendance is one workday and the cards count today and this
     * week. A range that reached them would be answering a question nobody
     * asked — "Present today: 0" under Last 6 Months would simply be wrong.
     */
    public function test_the_day_view_and_the_cards_ignore_the_range(): void
    {
        $this->attendance('On Site Now', Carbon::today()->toDateString());
        $this->seedLadder();

        $res = $this->actingAs($this->admin())
            ->get(route('attendance', ['range' => '7']))
            ->assertOk();

        $res->assertSee('On Site Now');
        $this->assertSame(1, $res->viewData('presentToday'));
    }

    /** A stale link should show the whole history, not an empty table. */
    public function test_an_unknown_range_falls_back_to_all_time(): void
    {
        $this->seedLadder();

        $res = $this->actingAs($this->admin())
            ->get(route('attendance', ['range' => 'last-tuesday', 'tab' => 'history']))
            ->assertOk();

        $this->assertSame('all', $res->viewData('range'));
        $res->assertSee('Otto Older');
    }

    /** Page 2 has to stay inside the range, and keep the other filters. */
    public function test_pagination_keeps_the_range(): void
    {
        for ($i = 1; $i <= 20; $i++) {
            $this->attendance("Paged {$i}", Carbon::today()->subDays($i)->toDateString());
        }

        $html = $this->actingAs($this->admin())
            ->get(route('attendance', ['range' => '30', 'site' => $this->siteA->id]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('range=30', $html, 'pagination should carry the range');
        $this->assertStringContainsString('site=' . $this->siteA->id, $html, 'and the site with it');
    }

    /**
     * The chosen range comes back selected, so the control says what is on.
     *
     * Every value, not one of them. The numeric two are the ones that broke:
     * PHP casts the numeric keys of the option array to int, a strict compare
     * against the string from the query string missed, no option was marked,
     * and the browser fell back to displaying the first — so the table showed
     * the last 30 days under a control reading "Last 7 Days".
     */
    #[DataProvider('ranges')]
    public function test_the_chosen_range_stays_selected(string $value, string $label): void
    {
        $html = $this->actingAs($this->admin())
            ->get(route('attendance', ['range' => $value, 'tab' => 'history']))
            ->assertOk()
            ->getContent();

        // Blade's @selected echoes the bare attribute, not selected="selected".
        $this->assertStringContainsString(
            'value="' . $value . '" selected>' . $label,
            $html,
            $label . ' should come back as the chosen option'
        );

        // And exactly one option is marked, or the browser picks for itself.
        $this->assertSame(1, substr_count($html, 'selected>Last ')
                           + substr_count($html, 'selected>This Year')
                           + substr_count($html, 'selected>All Time'));
    }

    public static function ranges(): array
    {
        return [
            'last 7 days'   => ['7', 'Last 7 Days'],
            'last 30 days'  => ['30', 'Last 30 Days'],
            'last 3 months' => ['3m', 'Last 3 Months'],
            'last 6 months' => ['6m', 'Last 6 Months'],
            'this year'     => ['year', 'This Year'],
            'all time'      => ['all', 'All Time'],
        ];
    }

    /**
     * "Nothing here" and "nothing here lately" are different answers, and a
     * reader who forgot the range is on would read the first as the second.
     */
    public function test_an_empty_range_says_so(): void
    {
        $this->attendance('Long Ago', '2026-01-15');

        $this->actingAs($this->admin())
             ->get(route('attendance', ['range' => '7', 'tab' => 'history']))
             ->assertOk()
             ->assertSee('No attendance records in this date range.');

        $this->actingAs($this->admin())
             ->get(route('attendance', ['range' => 'all', 'tab' => 'history']))
             ->assertOk()
             ->assertDontSee('No attendance records in this date range.');
    }
}
