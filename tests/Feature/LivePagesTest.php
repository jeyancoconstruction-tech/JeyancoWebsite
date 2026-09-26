<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\LaborType;
use App\Models\Loan;
use App\Models\Shift;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every screen says which parts of itself are live.
 *
 * A page keeps itself current by marking the parts that show data — a table, a
 * strip of figures, a list — with the topics they are drawn from. A page that
 * marks nothing goes stale in front of whoever is watching it, silently and
 * without any error to notice, which is the failure this guards against.
 *
 * The ids matter as much as the topics: the fresh copy of a region is found in
 * the re-rendered page by id, so a region without one can never be patched.
 */
class LivePagesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Holiday.php uses MySQL's YEAR() in raw SQL; the in-memory test
        // database is taught it the same way ModuleSmokeTest does.
        $pdo = \DB::connection()->getPdo();
        if (\DB::connection()->getDriverName() === 'sqlite' && method_exists($pdo, 'sqliteCreateFunction')) {
            $pdo->sqliteCreateFunction('YEAR', fn ($d) => $d ? (int) date('Y', strtotime($d)) : null, 1);
            $pdo->sqliteCreateFunction('MONTH', fn ($d) => $d ? (int) date('n', strtotime($d)) : null, 1);
        }

        $this->admin = User::create([
            'name' => 'Live Admin', 'username' => 'live.admin', 'password' => 'secret123',
            'role' => User::ROLE_ADMIN, 'is_admin' => true, 'is_active' => true,
        ]);

        $site  = Site::firstOrCreate(['name' => 'Site A'], ['location' => 'Naga']);
        $labor = LaborType::firstOrCreate(['name' => 'Mason'], ['daily_rate' => 800, 'ot_rate' => 100]);
        $shift = Shift::where('crosses_midnight', false)->firstOrFail();

        $worker = Employee::create([
            'name' => 'Juan Dela Cruz', 'position' => 'Mason', 'rate_per_hour' => 100,
            'labor_type_id' => $labor->id, 'site_id' => $site->id, 'shift_id' => $shift->id,
            'status' => Employee::STATUS_ACTIVE, 'fingerprint_id' => '1',
        ]);

        $day = now()->subDay()->startOfDay();
        Attendance::create([
            'employee_id' => $worker->id, 'site_id' => $site->id, 'shift_id' => $shift->id,
            'date' => $day->toDateString(),
            'time_in' => $day->copy()->setTime(8, 0)->toDateTimeString(),
            'time_out' => $day->copy()->setTime(17, 0)->toDateTimeString(),
        ]);

        Loan::create([
            'employee_id' => $worker->id, 'type' => Loan::ADVANCE,
            'principal' => 2000, 'balance' => 2000, 'installment' => 500,
            'schedule' => 'per_payroll', 'issued_on' => now()->subWeek()->toDateString(),
            'status' => 'active',
        ]);
    }

    /**
     * Page => the regions it must carry, and what each of them watches.
     *
     * @return array<string, array<string, list<string>>>
     */
    public static function pages(): array
    {
        return [
            '/dashboard' => [
                'dash-kpis'            => ['attendance', 'employees', 'payroll'],
                'dash-live-attendance' => ['attendance'],
                'dash-chart'           => ['attendance'],
            ],
            '/attendance' => [
                'attStats'        => ['attendance'],
                'attTodayList'    => ['attendance'],
                'attHistoryList'  => ['attendance'],
                'attHistoryPager' => ['attendance'],
                'attHistCount'    => ['attendance'],
            ],
            '/payroll-records' => [
                'prSummary'   => ['payroll', 'attendance'],
                'prBreakdown' => ['payroll', 'attendance'],
            ],
            '/leave-advances' => [
                'leaveList' => ['leave'],
            ],
            '/leave-advances?tab=advances' => [
                'advanceStats'   => ['advances'],
                'advanceList'    => ['advances', 'payroll'],
                'advanceDialogs' => ['advances'],
            ],
            '/payslips' => [
                'payslipList' => ['payroll'],
            ],
            '/payroll-reports' => [
                'reportList' => ['payroll', 'attendance'],
            ],
            '/audit-logs' => [
                'auditEntries' => ['audit'],
            ],
            '/users-roles' => [
                'roleList' => ['accounts'],
            ],
        ];
    }

    public function test_every_page_marks_what_is_live_on_it(): void
    {
        foreach (self::pages() as $url => $regions) {
            $page = $this->actingAs($this->admin)->get($url);
            $this->assertSame(200, $page->getStatusCode(), "{$url} did not render");
            $html = $page->getContent();

            foreach ($regions as $id => $topics) {
                $this->assertMatchesRegularExpression(
                    '/id="' . preg_quote($id, '/') . '"[^>]*data-live="([^"]*)"|data-live="([^"]*)"[^>]*id="' . preg_quote($id, '/') . '"/',
                    $html,
                    "{$url}: #{$id} is not a live region"
                );

                preg_match('/id="' . preg_quote($id, '/') . '"[^>]*data-live="([^"]*)"|data-live="([^"]*)"[^>]*id="' . preg_quote($id, '/') . '"/', $html, $m);
                $declared = preg_split('/[\s,]+/', trim(($m[1] ?? '') . ' ' . ($m[2] ?? '')));

                foreach ($topics as $topic) {
                    $this->assertContains($topic, $declared, "{$url}: #{$id} does not watch {$topic}");
                }
            }
        }
    }

    /** Every topic a page names has to be one the feed actually raises. */
    public function test_no_page_watches_a_topic_that_is_never_raised(): void
    {
        foreach (array_keys(self::pages()) as $url) {
            $html = $this->actingAs($this->admin)->get($url)->getContent();

            preg_match_all('/data-live="([^"]+)"/', $html, $all);

            foreach ($all[1] as $declared) {
                foreach (preg_split('/[\s,]+/', trim($declared)) as $topic) {
                    $this->assertContains($topic, \App\Support\Live::TOPICS, "{$url} watches unknown topic '{$topic}'");
                }
            }
        }
    }

    /**
     * The pages that used to ask again on a timer now wait to be told. A
     * timer left in place would go on asking for a page that no longer
     * needs asking for, which is the cost this whole thing exists to remove.
     */
    public function test_the_pages_that_polled_now_listen(): void
    {
        $gone = [
            '/analytics'        => 'setInterval(() => { if (!document.hidden && !inflight) refresh(); }, 60000)',
            '/employees/register' => 'setInterval(poll, 5000)',
        ];

        foreach ($gone as $url => $timer) {
            $html = $this->actingAs($this->admin)->get($url)->assertOk()->getContent();

            $this->assertStringNotContainsString($timer, $html, "{$url} still polls on a timer");
            $this->assertStringContainsString('Live.on(', $html, "{$url} does not listen for changes");
        }

        // The bell is in the layout, so it is on every page.
        $this->assertStringNotContainsString(
            'setInterval(pollBadge, 60000)',
            $this->actingAs($this->admin)->get('/dashboard')->getContent()
        );
    }
}
