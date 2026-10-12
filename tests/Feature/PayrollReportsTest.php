<?php

namespace Tests\Feature;

use App\Http\Controllers\PayrollReportController;
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

        // Asked for by a day in it, answered by the whole pay week. (Payroll
        // Summary, which this used to be read off, made way for Employees
        // DTR on 2026-10-12 — EmployeesDtrTest has that tab.)
        $week = ['span' => 'week', 'at' => '2026-09-16'];

        $one  = $this->report(['report' => 'employee'] + $week);
        $rows = $one->viewData('rows');
        $this->assertCount(1, $rows);
        $this->assertSame(['2026-09-14', '2026-09-20'], [$one->viewData('from'), $one->viewData('to')], 'whole pay weeks');
        $this->assertSame('Aldrin Santos Sapugay', $rows[0]['employee']['t']);
        $this->assertSame('#' . str_pad((string) $this->worker->id, 4, '0', STR_PAD_LEFT) . ' · Welder · Site A', $rows[0]['employee']['sub']);
        $this->assertEqualsWithDelta($mine['gross'], $rows[0]['gross'], 0.001);
        $this->assertEqualsWithDelta($mine['net'], $rows[0]['net'], 0.001);
        $this->assertEqualsWithDelta($mine['totalDeductions'], $rows[0]['ded'], 0.001);

        $site = $this->report(['report' => 'site'] + $week)->viewData('rows');
        $this->assertSame('Site A', $site[0]['site']['t']);
        $this->assertEqualsWithDelta($mine['gross'] + $mine['bonus'], $site[0]['cost'], 0.001, 'labor cost is gross plus bonus');

        $ded = $this->report(['report' => 'deductions'] + $week)->viewData('rows');
        $this->assertEqualsWithDelta($mine['totalDeductions'], $ded[0]['ded'], 0.001);
    }

    /**
     * One bar picks the period on every tab (Michael, 2026-10-12: "alisin mo
     * na yung may petsa, then use it to all sections"): Weekly or Monthly,
     * Previous and Next. No date fields and no presets.
     */
    public function test_every_tab_picks_its_period_with_the_same_bar_and_no_date_fields(): void
    {
        foreach (array_keys(PayrollReportController::REPORTS) as $key) {
            $page = $this->report(['report' => $key]);

            $page->assertSee('Weekly')->assertSee('Monthly')->assertSee('Previous')
                 ->assertSee('id="rpWhen"', false)->assertSee('id="rpNext"', false)
                 ->assertDontSee('type="date"', false)
                 ->assertDontSee('name="from"', false)->assertDontSee('name="preset"', false)
                 ->assertDontSee('Year to date')->assertDontSee('Last week');

            $this->assertContains($page->viewData('period')['span'], ['week', 'month']);
        }

        // The week or the month on screen follows to the other tabs.
        $html = $this->report(['report' => 'overtime', 'span' => 'week', 'at' => '2026-09-14', 'q' => 'aldrin'])->getContent();
        foreach (['dtr', 'employee', 'site', 'deductions', 'advances'] as $key) {
            $this->assertStringContainsString(
                e(route('payroll-reports.index', ['q' => 'aldrin', 'span' => 'week', 'at' => '2026-09-14', 'report' => $key])),
                $html
            );
        }
    }

    public function test_weekly_is_a_pay_week_and_moves_one_at_a_time(): void
    {
        $pay = ['report' => 'employee'];

        // Monday 21 to Sunday 27 September, read on the Sunday.
        $week   = $this->report($pay + ['span' => 'week']);
        $period = $week->viewData('period');
        $this->assertSame(['2026-09-21', '2026-09-27'], [$week->viewData('from'), $week->viewData('to')]);
        $this->assertSame(1, $week->viewData('weeks'));
        $this->assertSame('Sep 21 – 27, 2026', $period['label']);
        $this->assertSame(['span' => 'week', 'at' => '2026-09-14'], $period['prev']);
        $this->assertNull($period['next'], 'nothing after the week running now');
        $week->assertSee('id="rpNext" aria-disabled="true"', false);

        $before = $this->report($pay + $period['prev']);
        $this->assertSame(['2026-09-14', '2026-09-20'], [$before->viewData('from'), $before->viewData('to')]);
        $this->assertSame(['span' => 'week', 'at' => '2026-09-21'], $before->viewData('period')['next']);

        // Asked for a week still to come: the one running now.
        $this->assertSame('2026-09-21', $this->report($pay + ['span' => 'week', 'at' => '2026-12-01'])->viewData('from'));
    }

    public function test_monthly_is_the_pay_weeks_that_end_in_the_month(): void
    {
        $pay = ['report' => 'employee'];

        // What the payroll tabs open on: the pay weeks that end in September.
        $month  = $this->report($pay);
        $period = $month->viewData('period');
        $this->assertSame('month', $period['span']);
        $this->assertSame('September 2026', $period['label']);
        $this->assertSame(['2026-08-31', '2026-09-27'], [$month->viewData('from'), $month->viewData('to')]);
        $this->assertSame(4, $month->viewData('weeks'));
        $this->assertSame(['span' => 'month', 'at' => '2026-08-01'], $period['prev']);
        $this->assertNull($period['next'], "October's first pay week has not begun");
        // The dates are written out, since the name alone does not say them.
        $month->assertSee('Aug 31 – Sep 27, 2026');

        $august = $this->report($pay + $period['prev']);
        $this->assertSame(['2026-07-27', '2026-08-30'], [$august->viewData('from'), $august->viewData('to')]);
        $this->assertSame(5, $august->viewData('weeks'));
        $this->assertSame(['span' => 'month', 'at' => '2026-09-01'], $august->viewData('period')['next']);

        // Weekly, from a month: today's week when it is in it, else its first.
        $this->assertSame(['span' => 'week', 'at' => '2026-09-27'], $period['week']);
        $this->assertSame(['span' => 'week', 'at' => '2026-07-27'], $august->viewData('period')['week']);

        // Monthly, from a week: the month that week ends in. Monday 31 August
        // to Sunday 6 September is September's first.
        $this->assertSame(['span' => 'month', 'at' => '2026-09-06'],
            $this->report($pay + ['span' => 'week', 'at' => '2026-08-31'])->viewData('period')['month']);

        // The week running now can belong to a month that has not begun: on
        // Monday 30 November it ends on 6 December, so December is open.
        Carbon::setTestNow(Carbon::parse('2026-11-30 10:00:00', 'Asia/Manila'));
        $november = $this->report($pay);
        $this->assertSame(['2026-10-26', '2026-11-29'], [$november->viewData('from'), $november->viewData('to')]);
        $this->assertSame(['span' => 'month', 'at' => '2026-12-01'], $november->viewData('period')['next']);

        $december = $this->report($pay + ['span' => 'month', 'at' => '2026-12-01']);
        $this->assertSame('December 2026', $december->viewData('period')['label']);
        $this->assertSame(['2026-11-30', '2026-12-06'], [$december->viewData('from'), $december->viewData('to')], 'never past the week running now');
        $this->assertNull($december->viewData('period')['next']);
    }

    /** A bookmark from before the bar: a preset, or the two dates the fields sent. */
    public function test_an_old_link_opens_the_week_or_the_month_it_meant(): void
    {
        $pay = ['report' => 'employee'];
        $at  = fn (array $query) => array_intersect_key($this->report($pay + $query)->viewData('period'), ['span' => 1, 'at' => 1]);

        $this->assertSame(['span' => 'week', 'at' => '2026-09-21'], $at(['preset' => 'week']));
        $this->assertSame(['span' => 'week', 'at' => '2026-09-14'], $at(['preset' => 'lweek']));
        $this->assertSame(['span' => 'month', 'at' => '2026-09-01'], $at(['preset' => 'month']));
        $this->assertSame(['span' => 'month', 'at' => '2026-08-01'], $at(['preset' => 'lmonth']));
        $this->assertSame(['span' => 'month', 'at' => '2026-09-01'], $at(['preset' => 'ytd']));
        $this->assertSame(['span' => 'week', 'at' => '2026-09-14'], $at(['from' => '2026-09-15', 'to' => '2026-09-17']));
        $this->assertSame(['span' => 'month', 'at' => '2026-08-01'], $at(['from' => '2026-08-01', 'to' => '2026-08-31']));
    }

    public function test_excel_downloads_the_report_on_screen(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('payroll-reports.export', ['report' => 'employee', 'span' => 'week', 'at' => '2026-09-14']))
            ->assertOk();

        $this->assertStringContainsString('application/vnd.ms-excel', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('payroll-report_employee_2026-09-14_to_2026-09-20.xls', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('Aldrin Santos Sapugay', $response->getContent());
        $this->assertStringContainsString('Payroll by Employee', $response->getContent());
    }

    public function test_every_report_renders(): void
    {
        foreach (array_keys(PayrollReportController::REPORTS) as $key) {
            foreach ([['span' => 'week'], ['span' => 'week', 'at' => '2026-09-14'], ['span' => 'month'], ['span' => 'month', 'at' => '2026-08-01'], ['span' => 'month', 'at' => '2026-01-01']] as $period) {
                $this->report(['report' => $key] + $period)
                     ->assertSee('Payroll Reports')
                     ->assertSee('id="rpTable"', false);
            }
        }
    }
}
