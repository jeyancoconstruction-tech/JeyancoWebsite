<?php

namespace Tests\Feature;

use App\Http\Controllers\PayrollReportController;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\LaborType;
use App\Models\Shift;
use App\Models\Site;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The totals row of Payroll Records and of Payroll Reports sits on the
 * list's bottom edge (Michael, 2026-10-15: "kapag hindi pa puno or kulang pa
 * record yung total is nasa baba … kahit nag s-scroll is hindi na nakakasama").
 *
 * Both lists are boxed to the screen (modules/_fill_screen). A list that fills
 * its box keeps the totals there with a sticky foot. A list too short to fill
 * it has an empty row above the foot, which the script stretches to the room
 * left over. The row is written by the server, so a live refresh, which
 * rebuilds the list as the server wrote it, keeps it in place. The heights
 * were checked in Chrome at 1916, 1536 and 1366 wide, few rows and many,
 * scrolled and not; this holds the markup and the script that makes them.
 */
class TotalsOnTheBottomEdgeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $pdo = DB::connection()->getPdo();
        if (DB::connection()->getDriverName() === 'sqlite' && method_exists($pdo, 'sqliteCreateFunction')) {
            $pdo->sqliteCreateFunction('YEAR', fn ($d) => $d ? (int) substr((string) $d, 0, 4) : null, 1);
            $pdo->sqliteCreateFunction('MONTH', fn ($d) => $d ? (int) substr((string) $d, 5, 2) : null, 1);
        }

        Carbon::setTestNow(Carbon::parse('2026-09-26 09:00:00', 'Asia/Manila'));

        $this->admin = User::create([
            'name' => 'Totals Admin', 'username' => 'totals.admin', 'password' => 'secret123',
            'role' => User::ROLE_ADMIN, 'is_admin' => true, 'is_active' => true,
        ]);

        $site   = Site::firstOrCreate(['name' => 'Site A'], ['location' => 'Naga']);
        $labor  = LaborType::firstOrCreate(['name' => 'Mason'], ['daily_rate' => 800, 'ot_rate' => 100]);
        $shift  = Shift::where('crosses_midnight', false)->firstOrFail();
        $worker = Employee::create([
            'name' => 'Juan Dela Cruz', 'position' => 'Mason', 'rate_per_hour' => 100,
            'labor_type_id' => $labor->id, 'site_id' => $site->id, 'shift_id' => $shift->id,
            'status' => Employee::STATUS_ACTIVE, 'fingerprint_id' => '1',
        ]);

        foreach (['2026-09-08', '2026-09-09'] as $day) {
            Attendance::create([
                'employee_id' => $worker->id, 'site_id' => $site->id, 'shift_id' => $shift->id,
                'date' => $day, 'time_in' => $day . ' 08:00:00', 'time_out' => $day . ' 17:00:00',
            ]);
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function html(string $route, array $query): string
    {
        return $this->actingAs($this->admin)->get(route($route, $query))->assertOk()->getContent();
    }

    /**
     * The list's card is boxed, the wrap scrolls, and right above the foot is
     * the empty row, one cell across every column.
     */
    private function assertTotalsOnTheEdge(string $html, string $table, string $where): void
    {
        $this->assertMatchesRegularExpression('/<section class="[^"]*"[^>]*\bdata-fill-screen\b/', $html, "{$where}: the card is boxed to the screen");
        $this->assertMatchesRegularExpression('/<div class="[^"]*" data-fill-scroll>\s*<table class="' . $table . '"/', $html, "{$where}: the table scrolls in its wrap");

        $this->assertSame(1, preg_match('#<table class="' . $table . '"[^>]*>(.*?)</table>#s', $html, $t), "{$where}: the table");
        $this->assertSame(1, preg_match('#<thead>(.*?)</thead>#s', $t[1], $head));
        $columns = preg_match_all('/<th\b/', $head[1]);

        $this->assertMatchesRegularExpression(
            '#</tbody>\s*(?:\{\{--.*?--\}\}\s*)?<tbody data-fill-gap aria-hidden="true"><tr><td colspan="' . $columns . '"></td></tr></tbody>\s*<tfoot>#s',
            $t[1], "{$where}: an empty row across all {$columns} columns sits right above the totals");
        $this->assertSame(1, substr_count($t[1], 'data-fill-gap'), "{$where}: once");

        // The script that stretches it, and keeps it stretched after a live
        // refresh puts the list back as the server wrote it.
        $this->assertStringContainsString("wrap.querySelector(':scope > table > [data-fill-gap] td')", $html, "{$where}: the script");
        $this->assertStringContainsString("document.addEventListener('live:updated', refit);", $html, "{$where}: refits after a live refresh");
        $this->assertStringContainsString('[data-fill-gap] td { height: 0; padding: 0 !important; border: 0 !important;', $html, "{$where}: the row is only space");

        // The chat button starts in the corner, right over the last total;
        // while the list is boxed it starts just above the totals row, and
        // its window above it.
        $this->assertStringContainsString('html.fills-screen:has([data-fill-gap]) .chatbot-fab { bottom: 64px; }', $html, "{$where}: the chat button clears the totals");
        $this->assertStringContainsString('html.fills-screen:has([data-fill-gap]) .chatbot-window { bottom: 130px; }', $html, "{$where}: and its window follows");
    }

    public function test_payroll_records_keeps_the_weeks_totals_on_the_bottom_edge(): void
    {
        $html = $this->html('payroll-records', ['mode' => 'weekly', 'week' => '2026-W37']);

        $this->assertTotalsOnTheEdge($html, 'prx-table', 'week');
        $this->assertMatchesRegularExpression('/\.prx-table tfoot td \{\s*position: sticky; bottom: 0;/', $html, 'and holds them there while the rows scroll');
    }

    public function test_payroll_records_keeps_the_days_totals_on_the_bottom_edge(): void
    {
        $html = $this->html('payroll-records', ['mode' => 'daily', 'date' => '2026-09-08']);

        $this->assertStringContainsString('Day total before deductions &amp; bonus', $html);
        $this->assertTotalsOnTheEdge($html, 'prx-table', 'day');
    }

    public function test_an_empty_period_has_no_totals_and_no_stretch(): void
    {
        foreach ([['mode' => 'weekly', 'week' => '2026-W30'], ['mode' => 'daily', 'date' => '2026-07-21']] as $query) {
            $html = $this->html('payroll-records', $query);

            $this->assertStringContainsString('class="prx-empty"', $html);
            $this->assertStringNotContainsString('<tfoot>', $html);
            $this->assertStringNotContainsString('<tbody data-fill-gap', $html);
        }
    }

    public function test_every_report_keeps_its_totals_on_the_bottom_edge(): void
    {
        foreach (array_keys(PayrollReportController::REPORTS) as $report) {
            $html = $this->html('payroll-reports.index', ['report' => $report, 'span' => 'week', 'at' => '2026-09-08']);

            // Advances lists cash advances, and this worker has none.
            if (! str_contains($html, '<tfoot>')) {
                $this->assertSame('advances', $report, "{$report}: has rows");
                $this->assertStringNotContainsString('<tbody data-fill-gap', $html);
                continue;
            }

            $this->assertTotalsOnTheEdge($html, 'rp-table', $report);
            $this->assertStringContainsString('.rp-table tfoot td { position: sticky; bottom: 0;', $html, "{$report}: held there while the rows scroll");
            // On paper the list runs its length, with no box and no stretch.
            $this->assertMatchesRegularExpression('/@media print \{\s*\[data-fill-scroll\] \{ height: auto !important; \}\s*\[data-fill-gap\] \{ display: none !important; \}/', $html, "{$report}: printing");
        }

        // The chart card went (UI revision, 2026-10-19): it pushed the table and
        // its totals below the screen. Shares now sit in the rows as bars.
        $html = $this->html('payroll-reports.index', ['report' => 'employee', 'span' => 'week', 'at' => '2026-09-08']);
        $this->assertStringNotContainsString('class="rp-hbars"', $html);
        $this->assertStringContainsString('id="rpTable"', $html);
    }

    public function test_a_page_without_totals_keeps_the_chat_button_in_its_corner(): void
    {
        // Leave & Advances boxes its lists too, but they have no totals row:
        // nothing there for the lifted button rule to match.
        $html = $this->actingAs($this->admin)->get(route('leave.index'))->assertOk()->getContent();

        $this->assertStringContainsString('data-fill-screen', $html);
        $this->assertStringNotContainsString('<tbody data-fill-gap', $html);
    }
}
