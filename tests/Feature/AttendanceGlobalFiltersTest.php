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
 * Site and Shift filter the whole attendance page, not one tab.
 *
 * The point of putting them above the cards is that the cards follow them. A
 * filter that changed the table underneath while "Present Today" kept counting
 * everybody would be worse than no filter at all — the number and the rows
 * would be describing different sets of records, with nothing on screen saying
 * so.
 */
class AttendanceGlobalFiltersTest extends TestCase
{
    use RefreshDatabase;

    private Site $siteA;
    private Site $siteB;
    private Shift $day;
    private Shift $night;

    protected function setUp(): void
    {
        parent::setUp();

        // firstOrCreate: the migrations seed a default site and the two
        // shifts, so create() would collide on the unique name.
        $this->siteA = Site::firstOrCreate(['name' => 'Site A']);
        $this->siteB = Site::firstOrCreate(['name' => 'Site B']);
        $this->day   = Shift::firstOrCreate(['name' => 'Day'],   ['starts_at' => '06:00:00', 'grace_period_minutes' => 10, 'crosses_midnight' => false]);
        $this->night = Shift::firstOrCreate(['name' => 'Night'], ['starts_at' => '18:00:00', 'grace_period_minutes' => 10, 'crosses_midnight' => true]);
    }

    private ?User $admin = null;

    /** Memoised: some tests act as the admin twice, and username is unique. */
    private function admin(): User
    {
        return $this->admin ??= User::create([
            'name'      => 'Admin',
            'username'  => 'admin.attfilter',
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

    /**
     * The site and shift are recorded on the attendance, not read off the
     * employee: one kiosk is moved between sites, and a worker's shift can
     * change, so the record has to answer for the day it is about.
     */
    private function attendance(Employee $e, Site $site, Shift $shift, string $date, ?string $out = '15:00:00'): Attendance
    {
        return Attendance::create([
            'employee_id' => $e->id,
            'site_id'     => $site->id,
            'shift_id'    => $shift->id,
            'date'        => $date,
            'session'     => 'AM',
            'time_in'     => '06:00:00',
            'time_out'    => $out,
        ]);
    }

    private function seedTodayAndHistory(): void
    {
        $today     = Carbon::today()->toDateString();
        $yesterday = Carbon::yesterday()->toDateString();

        $this->attendance($this->worker('Ana Day A'),   $this->siteA, $this->day,   $today);
        $this->attendance($this->worker('Ben Night A'), $this->siteA, $this->night, $today);
        $this->attendance($this->worker('Carl Day B'),  $this->siteB, $this->day,   $today);

        $this->attendance($this->worker('Dina Hist A'), $this->siteA, $this->day,   $yesterday);
        $this->attendance($this->worker('Elmo Hist B'), $this->siteB, $this->night, $yesterday);
    }

    public function test_the_page_offers_all_sites_and_all_shifts(): void
    {
        $this->actingAs($this->admin())
             ->get(route('attendance'))
             ->assertOk()
             ->assertSee('All sites')      // the site dropdown
             ->assertSee('All shifts')     // the first segment of the shift control
             ->assertSee('Site A')
             ->assertSee('Site B')
             ->assertSee('Day')
             ->assertSee('Night');
    }

    public function test_unfiltered_shows_everyone(): void
    {
        $this->seedTodayAndHistory();

        $this->actingAs($this->admin())
             ->get(route('attendance'))
             ->assertOk()
             ->assertSee('Ana Day A')
             ->assertSee('Ben Night A')
             ->assertSee('Carl Day B');
    }

    public function test_the_site_filter_reaches_both_tabs(): void
    {
        $this->seedTodayAndHistory();

        $this->actingAs($this->admin())
             ->get(route('attendance', ['site' => $this->siteA->id]))
             ->assertOk()
             // Today
             ->assertSee('Ana Day A')
             ->assertSee('Ben Night A')
             ->assertDontSee('Carl Day B')
             // History, on the same page
             ->assertSee('Dina Hist A')
             ->assertDontSee('Elmo Hist B');
    }

    public function test_the_shift_filter_reaches_both_tabs(): void
    {
        $this->seedTodayAndHistory();

        $this->actingAs($this->admin())
             ->get(route('attendance', ['shift' => $this->night->id]))
             ->assertOk()
             ->assertSee('Ben Night A')
             ->assertDontSee('Ana Day A')
             ->assertDontSee('Carl Day B')
             ->assertSee('Elmo Hist B')
             ->assertDontSee('Dina Hist A');
    }

    public function test_the_two_filters_combine(): void
    {
        $this->seedTodayAndHistory();

        $this->actingAs($this->admin())
             ->get(route('attendance', ['site' => $this->siteA->id, 'shift' => $this->day->id]))
             ->assertOk()
             ->assertSee('Ana Day A')
             ->assertDontSee('Ben Night A')   // right site, wrong shift
             ->assertDontSee('Carl Day B');   // right shift, wrong site
    }

    /** The whole reason the bar sits above the cards. */
    public function test_the_stat_cards_follow_the_filter(): void
    {
        $this->seedTodayAndHistory();

        $all = $this->actingAs($this->admin())->get(route('attendance'))->assertOk();
        $this->assertSame(3, $all->viewData('presentToday'));

        $filtered = $this->actingAs($this->admin())
            ->get(route('attendance', ['site' => $this->siteB->id]))
            ->assertOk();

        $this->assertSame(1, $filtered->viewData('presentToday'), 'Present Today should count the filtered site only');
    }

    /** Still clocked in, on one site: the middle card has to follow too. */
    public function test_the_clocked_in_card_follows_the_filter(): void
    {
        $this->attendance($this->worker('Open A'), $this->siteA, $this->day, Carbon::today()->toDateString(), null);
        $this->attendance($this->worker('Open B'), $this->siteB, $this->day, Carbon::today()->toDateString(), null);

        $res = $this->actingAs($this->admin())
            ->get(route('attendance', ['site' => $this->siteA->id]))
            ->assertOk();

        $this->assertSame(1, $res->viewData('clockedIn'));
        $this->assertSame(1, $res->viewData('presentToday'));
    }

    /**
     * A stale bookmark pointing at a deleted site should show the whole page,
     * not an empty one with nothing to say why it is empty.
     */
    public function test_an_unknown_site_or_shift_is_ignored(): void
    {
        $this->seedTodayAndHistory();

        $res = $this->actingAs($this->admin())
            ->get(route('attendance', ['site' => 9999, 'shift' => 9999]))
            ->assertOk()
            ->assertSee('Ana Day A')
            ->assertSee('Carl Day B');

        $this->assertNull($res->viewData('siteId'));
        $this->assertNull($res->viewData('shiftId'));
    }

    /** Page 2 of History must keep the filter, and come back to History. */
    public function test_history_pagination_keeps_the_filter_and_the_tab(): void
    {
        $worker = $this->worker('Paginated');
        for ($i = 1; $i <= 20; $i++) {
            $this->attendance($worker, $this->siteA, $this->day, Carbon::today()->subDays($i)->toDateString());
        }

        $html = $this->actingAs($this->admin())
            ->get(route('attendance', ['site' => $this->siteA->id]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('site=' . $this->siteA->id, $html, 'pagination should carry the filter');
        $this->assertStringContainsString('tab=history', $html, 'pagination should come back to History');
    }

    /**
     * The alert is about the whole workforce. Firing it off a filtered count
     * would mean "no invalid attendance" purely because a site is selected.
     */
    public function test_the_invalid_card_is_filtered_but_the_alert_is_not(): void
    {
        // Clocked in yesterday, never clocked out — invalid, on Site B.
        $this->attendance($this->worker('Forgot B'), $this->siteB, $this->day, Carbon::yesterday()->toDateString(), null);

        $res = $this->actingAs($this->admin())
            ->get(route('attendance', ['site' => $this->siteA->id]))
            ->assertOk();

        // The card follows the filter: nothing invalid on Site A.
        $this->assertSame(0, $res->viewData('invalidCount'));

        // The notification still sees it.
        $this->assertDatabaseHas('notifications', ['type' => \App\Notifications\AttendanceAlert::class]);
    }
}
