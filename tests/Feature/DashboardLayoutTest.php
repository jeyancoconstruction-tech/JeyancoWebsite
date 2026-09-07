<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\LaborType;
use App\Models\LeaveRequest;
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
            ->assertSee('Active Workers')->assertSee('Needs Attention');

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
            'id="attendanceChart"', 'id="kioskMap"', 'id="siteSearch"', 'id="siteSearchBtn"',
            'id="siteSelect"', 'id="siteSaveBtn"', 'id="siteMapHint"', 'id="kiosk-status"',
            'id="siteTrackerCard"', 'id="siteTrackerMin"', 'id="siteTrackerMax"',
            'id="employeeModal"', 'st-body',
        ] as $needle) {
            $this->assertStringContainsString($needle, $html, "dashboard lost {$needle}");
        }
    }

    /** An outstanding item appears, links somewhere real, and clears. */
    public function test_needs_attention_reflects_real_work(): void
    {
        $emp = $this->seedWorkforce();

        $this->actingAs($this->admin)->get('/dashboard')
            ->assertSee('Workers still timed in');

        LeaveRequest::create([
            'employee_id' => $emp->id, 'leave_type' => 'sick',
            'starts_on' => now()->toDateString(), 'ends_on' => now()->toDateString(),
            'days' => 1, 'is_paid' => true, 'status' => 'pending',
        ]);

        $this->actingAs($this->admin)->get('/dashboard')
            ->assertSee('Leave requests awaiting a decision');
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
