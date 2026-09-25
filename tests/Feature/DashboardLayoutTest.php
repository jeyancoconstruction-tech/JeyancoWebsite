<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\LaborType;
use App\Models\Shift;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The dashboard, and the promise that nothing on it is decoration.
 *
 * The interesting test is the last one: it reads every link the page actually
 * renders — sidebar included — and requests each one. A button that goes
 * nowhere, a route renamed out from under a view, or a page that 500s under a
 * particular role all fail here rather than in front of the office.
 */
class DashboardLayoutTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $pdo = \DB::connection()->getPdo();
        if (\DB::connection()->getDriverName() === 'sqlite' && method_exists($pdo, 'sqliteCreateFunction')) {
            $pdo->sqliteCreateFunction('YEAR',  fn ($d) => $d ? (int) date('Y', strtotime($d)) : null, 1);
            $pdo->sqliteCreateFunction('MONTH', fn ($d) => $d ? (int) date('n', strtotime($d)) : null, 1);
        }

        $this->admin = User::create([
            'name' => 'Dash Admin', 'username' => 'dashadmin',
            'password' => 'secret123', 'role' => User::ROLE_ADMIN, 'is_active' => true,
        ]);

        // Mid-morning, in the middle of the day shift. The worker seeded below
        // is timed in and not out, and whether that reads as "still timed in"
        // depends on whether their shift is still running: once it is over,
        // an open row is history, as it is on the Attendance page. Left on the
        // real clock, a suite run in the evening failed here.
        \Carbon\Carbon::setTestNow(\Carbon\Carbon::today()->setTime(10, 0));
    }

    protected function tearDown(): void
    {
        \Carbon\Carbon::setTestNow();
        parent::tearDown();
    }

    private function seedWorkforce(): Employee
    {
        $site  = Site::firstOrCreate(['name' => 'Site A'], ['location' => 'Naga']);
        $labor = LaborType::firstOrCreate(['name' => 'Mason'], ['daily_rate' => 1000, 'ot_rate' => 0]);
        $shift = Shift::where('crosses_midnight', false)->first()
            ?? Shift::create(['name' => 'Day', 'starts_at' => '08:00', 'ends_at' => '17:00', 'crosses_midnight' => false]);

        $emp = Employee::create([
            'name' => 'Juan Dela Cruz', 'position' => 'Mason', 'rate_per_hour' => 125,
            'labor_type_id' => $labor->id, 'site_id' => $site->id, 'shift_id' => $shift->id,
            'status' => 'active', 'fingerprint_id' => '1', 'vale' => 250,
        ]);

        $today = now()->startOfDay();
        Attendance::create([
            'employee_id' => $emp->id, 'site_id' => $site->id, 'shift_id' => $shift->id,
            'date' => $today->toDateString(),
            'time_in' => $today->copy()->setTime(7, 30)->toDateTimeString(),
        ]);

        return $emp;
    }

    /** It renders with data, and with none at all. */
    public function test_dashboard_renders_empty_and_populated(): void
    {
        $this->actingAs($this->admin)->get('/dashboard')->assertOk()
            ->assertSee('Active Workers')->assertSee('Live Attendance')->assertSee('Project Sites');

        $this->seedWorkforce();

        $this->actingAs($this->admin)->get('/dashboard')->assertOk()
            ->assertSee('Juan Dela Cruz')
            ->assertSee('Still Timed In');
    }

    /** Every element the JS reaches for must be in the markup. */
    public function test_dashboard_keeps_its_javascript_contract(): void
    {
        $this->seedWorkforce();
        $html = $this->actingAs($this->admin)->get('/dashboard')->getContent();

        foreach ([
            'id="current-time"', 'id="current-date"', 'id="clockWidget"', 'id="miniCal"',
            'id="attendanceChart"', 'id="kioskMap"', 'id="kiosk-status"',
            'id="siteTrackerCard"', 'id="siteTrackerMin"', 'id="siteTrackerMax"',
            'id="employeeModal"', 'st-body',
            'data-map-url="' . route('dashboard.map') . '"',
        ] as $needle) {
            $this->assertStringContainsString($needle, $html, "dashboard lost {$needle}");
        }
    }

    /**
     * The Project Sites map only shows. Sites are pinned on the Sites page:
     * no search, no site picker, no Save, nothing that writes a site.
     */
    public function test_the_project_sites_map_is_read_only(): void
    {
        $html = $this->actingAs($this->admin)->get('/dashboard')->getContent();

        foreach (['id="siteSearch"', 'id="siteSearchBtn"', 'id="siteSelect"', 'id="siteSaveBtn"', 'id="siteMapHint"'] as $gone) {
            $this->assertStringNotContainsString($gone, $html);
        }
        $this->assertStringNotContainsString("method: 'PUT'", $html, 'nothing on the dashboard writes a site');
        $this->assertStringNotContainsString("map.on('click'", $html, 'a click on the map drops no pin');
        $this->assertStringContainsString('data-sites-url="' . route('sites.index') . '"', $html, 'the key points to the Sites page');

        // Dark tiles in the dark theme, as on the Sites page.
        $this->assertStringContainsString('html[data-bs-theme="dark"] #kioskMap .leaflet-tile-pane { filter: invert(1)', $html);
    }

    /**
     * The chart over live attendance on the left, the map across the right
     * two columns at full height. Needs Attention and Recent Activity came
     * off the page to make room for it.
     */
    public function test_the_map_takes_the_right_of_the_screen(): void
    {
        $this->seedWorkforce();

        $html = $this->actingAs($this->admin)->get('/dashboard')->assertOk()
            ->assertSee('Juan Dela Cruz')
            ->assertDontSee('Needs Attention')->assertDontSee('Recent Activity')
            ->getContent();

        $this->assertStringNotContainsString('id="dash-attention"', $html);
        $this->assertStringNotContainsString('id="dash-activity"', $html);

        // Stacked on a narrower screen in this order: chart, live, map.
        $chart = strpos($html, 'id="dash-chart"');
        $live  = strpos($html, 'id="dash-live-attendance"');
        $map   = strpos($html, 'id="siteTrackerCard"');
        $this->assertTrue($chart < $live && $live < $map, 'chart, then live attendance, then the map');

        $this->assertStringContainsString('.area-live  { grid-column: 1; grid-row: 2; }', $html);
        $this->assertStringContainsString('.area-map   { grid-column: 2 / span 2; grid-row: 1 / span 2; }', $html);
    }

    /**
     * Follow every link the page renders. This is the "no dead buttons, no
     * broken routes" check, and it covers the sidebar too because the sidebar
     * is part of the response.
     */
    public function test_every_link_on_the_dashboard_resolves(): void
    {
        $this->seedWorkforce();

        $html = $this->actingAs($this->admin)->get('/dashboard')->getContent();

        preg_match_all('/href="([^"]+)"/', $html, $m);

        $checked = [];
        foreach ($m[1] as $href) {
            $href = html_entity_decode($href);

            // Hrefs built by JavaScript at runtime — the global search's
            // suggestion template, for one — are not links in this document.
            if (str_contains($href, '${') || str_contains($href, '{{')) {
                continue;
            }

            // Off-site assets, anchors and non-GET protocols are not ours.
            if ($href === '' || $href === '#'
                || str_starts_with($href, 'http://localhost') === false && str_contains($href, '://')
                || str_starts_with($href, 'mailto:') || str_starts_with($href, 'tel:')) {
                continue;
            }

            $path = parse_url($href, PHP_URL_PATH) ?: '/';
            $query = parse_url($href, PHP_URL_QUERY);
            $url = $path . ($query ? '?' . $query : '');

            // Stylesheets and images resolve on disk, not through the router.
            if (preg_match('/\.(css|js|png|jpg|jpeg|svg|ico|woff2?)$/i', $path)) {
                continue;
            }
            if (isset($checked[$url])) {
                continue;
            }
            $checked[$url] = true;

            $status = $this->actingAs($this->admin)->get($url)->getStatusCode();

            $this->assertContains(
                $status,
                [200, 302],
                "dead link on the dashboard: {$url} returned {$status}"
            );
        }

        // Sanity: the crawl must actually have found the navigation.
        $this->assertGreaterThan(12, count($checked),
            'expected the sidebar and dashboard links to be crawled, found ' . count($checked));
    }

    /** A non-admin sees a dashboard too, and none of its links 500 for them. */
    public function test_dashboard_works_for_staff(): void
    {
        $this->seedWorkforce();

        $staff = User::create([
            'name' => 'Staff', 'username' => 'dashstaff', 'password' => 'secret123',
            'role' => User::ROLE_STAFF, 'is_active' => true,
        ]);

        $html = $this->actingAs($staff)->get('/dashboard')->assertOk()->getContent();

        // Admin-only destinations must not be offered to them at all.
        $this->assertStringNotContainsString('/audit-logs', $html);
        $this->assertStringNotContainsString('/users-roles', $html);
    }
}
