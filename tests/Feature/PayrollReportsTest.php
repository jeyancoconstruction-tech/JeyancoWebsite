<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\LaborType;
use App\Models\Site;
use App\Models\User;
use App\Services\PayrollService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Payroll Reports (Michael, 2026-09-26): laid out after
 * jeyanco-payroll-reports.html, a sub-item of Payroll Records rather than an
 * entry under Insights, and reading the payroll computation live — the same
 * figures as Payroll Records — instead of payroll runs, which are no longer
 * made. The page itself was checked in Chrome, light and dark.
 */
class PayrollReportsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Employee $worker;

    protected function setUp(): void
    {
        parent::setUp();

        $pdo = DB::connection()->getPdo();
        if (DB::connection()->getDriverName() === 'sqlite' && method_exists($pdo, 'sqliteCreateFunction')) {
            $pdo->sqliteCreateFunction('YEAR', fn ($d) => $d ? (int) substr((string) $d, 0, 4) : null, 1);
            $pdo->sqliteCreateFunction('MONTH', fn ($d) => $d ? (int) substr((string) $d, 5, 2) : null, 1);
        }

        Carbon::setTestNow(Carbon::parse('2026-09-27 10:00:00', 'Asia/Manila'));

        $this->admin = User::create([
            'name' => 'Report Admin', 'username' => 'report.admin', 'password' => 'secret123',
            'role' => User::ROLE_ADMIN, 'is_admin' => true, 'is_active' => true,
        ]);

        $site  = Site::firstOrCreate(['name' => 'Site A']);
        $labor = LaborType::create(['name' => 'Welder', 'daily_rate' => 1200, 'ot_rate' => 125]);
        $this->worker = Employee::create([
            'name' => 'Aldrin Santos Sapugay', 'status' => 'active', 'fingerprint_id' => '1',
            'rate_per_hour' => 150, 'labor_type_id' => $labor->id, 'site_id' => $site->id,
        ]);

        // The week of 14–20 September, one long day in it.
        foreach (['2026-09-14' => '17:00:00', '2026-09-15' => '19:00:00', '2026-09-16' => '17:00:00'] as $d => $out) {
            Attendance::create([
                'employee_id' => $this->worker->id, 'site_id' => $site->id, 'date' => $d,
                'time_in' => $d . ' 08:00:00', 'time_out' => $d . ' ' . $out,
            ]);
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function report(array $query)
    {
        return $this->actingAs($this->admin)->get(route('payroll-reports.index', $query))->assertOk();
    }

    public function test_it_sits_under_payroll_records_not_under_insights(): void
    {
        $html = $this->report([])->getContent();
        $rail = substr($html, strpos($html, '<nav class="nav-menu">'));
        $rail = substr($rail, 0, strpos($rail, '</nav>'));

        // The third page of Payroll Records, lit, with the group open.
        $this->assertStringContainsString('aria-controls="navSubRecords" aria-expanded="true"', $rail);
        $tracker = strpos($rail, 'href="' . route('remittances.index') . '"');
        $reports = strpos($rail, 'class="nav-sub-link on" href="' . route('payroll-reports.index') . '"');
        $this->assertNotFalse($reports, 'Reports is a lit sub-item');
        $this->assertGreaterThan($tracker, $reports, 'below the Remittance tracker');

        // And no longer a row of its own under Insights.
        $this->assertDoesNotMatchRegularExpression('~<a class="nav-link[^"]*" href="' . preg_quote(route('payroll-reports.index'), '~') . '"~', $rail);
        $this->assertStringNotContainsString('<span>Payroll Reports</span>', $rail);
    }

    /** Every figure is the payroll computation's, for the same whole weeks. */
    public function test_the_figures_are_the_ones_payroll_records_shows(): void
    {
        $paid = app(PayrollService::class)->computeForRange('2026-09-14', '2026-09-20');
        $mine = collect($paid['employees'])->firstWhere('employee_id', $this->worker->id)['totals'];
        $this->assertGreaterThan(0, $mine['net']);

        $summary = $this->report(['report' => 'summary', 'from' => '2026-09-15', 'to' => '2026-09-17']);
        $rows    = $summary->viewData('rows');
        $this->assertCount(1, $rows, 'one pay week');
        $this->assertSame('2026-09-14', $summary->viewData('from'), 'whole pay weeks');
        $this->assertSame('2026-09-20', $summary->viewData('to'));
        $this->assertEqualsWithDelta($mine['gross'], $rows[0]['gross'], 0.001);
        $this->assertEqualsWithDelta($mine['net'], $rows[0]['net'], 0.001);
        $this->assertSame('Closed', $rows[0]['status']['t']);

        $emp = $this->report(['report' => 'employee', 'from' => '2026-09-14', 'to' => '2026-09-20'])->viewData('rows');
        $this->assertSame('Aldrin Santos Sapugay', $emp[0]['employee']['t']);
        $this->assertSame('#' . str_pad((string) $this->worker->id, 4, '0', STR_PAD_LEFT) . ' · Welder · Site A', $emp[0]['employee']['sub']);
        $this->assertEqualsWithDelta($mine['net'], $emp[0]['net'], 0.001);
        $this->assertEqualsWithDelta($mine['totalDeductions'], $emp[0]['ded'], 0.001);

        $site = $this->report(['report' => 'site', 'from' => '2026-09-14', 'to' => '2026-09-20'])->viewData('rows');
        $this->assertSame('Site A', $site[0]['site']['t']);
        $this->assertEqualsWithDelta($mine['gross'] + $mine['bonus'], $site[0]['cost'], 0.001, 'labor cost is gross plus bonus');

        $ded = $this->report(['report' => 'deductions', 'from' => '2026-09-14', 'to' => '2026-09-20'])->viewData('rows');
        $this->assertEqualsWithDelta($mine['totalDeductions'], $ded[0]['ded'], 0.001);
    }

    public function test_the_periods_are_whole_pay_weeks_and_never_ahead_of_today(): void
    {
        // This week: Monday 21 to Sunday 27 September.
        $week = $this->report(['preset' => 'week']);
        $this->assertSame(['2026-09-21', '2026-09-27'], [$week->viewData('from'), $week->viewData('to')]);

        // This month (the default): the pay weeks that end in September.
        $month = $this->report([]);
        $this->assertSame('month', $month->viewData('preset'));
        $this->assertSame(['2026-08-31', '2026-09-27'], [$month->viewData('from'), $month->viewData('to')]);
        $this->assertSame(4, $month->viewData('weeks'));

        // A range reaching past this week stops at its end.
        $ahead = $this->report(['from' => '2026-09-20', 'to' => '2026-10-20']);
        $this->assertSame(['2026-09-14', '2026-09-27'], [$ahead->viewData('from'), $ahead->viewData('to')]);
    }

    public function test_excel_downloads_the_report_on_screen(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('payroll-reports.export', ['report' => 'employee', 'from' => '2026-09-14', 'to' => '2026-09-20']))
            ->assertOk();

        $this->assertStringContainsString('application/vnd.ms-excel', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('payroll-report_employee_2026-09-14_to_2026-09-20.xls', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('Aldrin Santos Sapugay', $response->getContent());
        $this->assertStringContainsString('Payroll by Employee', $response->getContent());
    }

    public function test_every_report_renders(): void
    {
        foreach (array_keys(\App\Http\Controllers\PayrollReportController::REPORTS) as $key) {
            foreach (['week', 'lweek', 'month', 'lmonth', 'ytd'] as $preset) {
                $this->report(['report' => $key, 'preset' => $preset])
                     ->assertSee('Payroll Reports')
                     ->assertSee('id="rpTable"', false);
            }
        }
    }
}
