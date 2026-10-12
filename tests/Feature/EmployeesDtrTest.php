<?php

namespace Tests\Feature;

use App\Http\Controllers\PayrollReportController;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\LaborType;
use App\Models\LeaveRequest;
use App\Models\Shift;
use App\Models\Site;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\DtrService;
use App\Services\PayrollService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Employees DTR (Michael, 2026-10-12): the tab that took Payroll Summary's
 * place in Payroll Reports. Everybody by the week or the month, from the
 * scans; a worker's printable page in a pop-up, as a PDF and for Excel.
 *
 * Nothing is counted for it alone: the scans are laid out as the Attendance
 * page lays them out, the hours are payroll's, an absence is Analytics'.
 *
 * The day shift throughout: in at eight, lunch twelve to one, home at five,
 * eight regular hours, ten minutes' grace. The week is Monday 14 to Sunday
 * 20 September 2026, read on the Friday evening.
 */
class EmployeesDtrTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Shift $day;
    private Site $site;
    private Employee $ana;
    private Employee $ben;

    protected function setUp(): void
    {
        parent::setUp();

        $pdo = DB::connection()->getPdo();
        if (DB::connection()->getDriverName() === 'sqlite' && method_exists($pdo, 'sqliteCreateFunction')) {
            $pdo->sqliteCreateFunction('YEAR', fn ($d) => $d ? (int) substr((string) $d, 0, 4) : null, 1);
            $pdo->sqliteCreateFunction('MONTH', fn ($d) => $d ? (int) substr((string) $d, 5, 2) : null, 1);
        }

        // Everybody was taken on before the week began.
        Carbon::setTestNow(Carbon::parse('2026-09-01 08:00:00', 'Asia/Manila'));
        SystemSetting::current()->forceFill(['schedule_rules_from' => '2026-09-01'])->save();

        $this->admin = User::create([
            'name' => 'Report Admin', 'username' => 'dtr.admin', 'password' => 'secret123',
            'role' => User::ROLE_ADMIN, 'is_admin' => true, 'is_active' => true,
        ]);

        $this->site = Site::firstOrCreate(['name' => 'Site A']);
        $this->day  = Shift::where('crosses_midnight', false)->firstOrFail();
        $this->day->forceFill(Shift::layOut('08:00', '17:00', '12:00', '13:00')
            + ['regular_minutes' => 480, 'grace_period_minutes' => 10])->save();

        $this->ana = $this->worker('Ana Reyes Cruz');
        $this->ben = $this->worker('Ben Dela Torre');

        // Ana: a full day, a late one, a long one, a day missed, a short one.
        $this->scan($this->ana, '2026-09-14', 'AM', '08:00', '12:00');
        $this->scan($this->ana, '2026-09-14', 'PM', '13:00', '17:00');
        $this->scan($this->ana, '2026-09-15', 'AM', '08:30', '12:00');
        $this->scan($this->ana, '2026-09-15', 'PM', '13:00', '17:00');
        $this->scan($this->ana, '2026-09-16', 'AM', '08:00', '12:00');
        $this->scan($this->ana, '2026-09-16', 'PM', '13:00', '19:00');
        $this->scan($this->ana, '2026-09-18', 'AM', '08:00', '12:00');
        $this->scan($this->ana, '2026-09-18', 'PM', '13:00', '16:00');

        // Ben: a time out nobody scanned, then a day of approved leave.
        $this->scan($this->ben, '2026-09-14', 'AM', '08:00', '12:00');
        $this->scan($this->ben, '2026-09-14', 'PM', '13:00', '17:00',
            ['close_type' => 'auto', 'needs_review' => true, 'close_reason' => 'No time-out']);
        LeaveRequest::create([
            'employee_id' => $this->ben->id, 'leave_type' => 'vacation',
            'starts_on' => '2026-09-15', 'ends_on' => '2026-09-15', 'days' => 1,
            'is_paid' => true, 'status' => 'approved',
        ]);

        Carbon::setTestNow(Carbon::parse('2026-09-18 18:00:00', 'Asia/Manila'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function worker(string $name, array $with = []): Employee
    {
        return Employee::create($with + [
            'name'            => $name,
            'position'        => 'Mason',
            'status'          => Employee::STATUS_ACTIVE,
            'employment_type' => Employee::EMPLOYMENT_DAILY,
            'labor_type_id'   => LaborType::firstOrCreate(['name' => 'Mason'], ['daily_rate' => 800, 'ot_rate' => 125])->id,
            'shift_id'        => $this->day->id,
            'site_id'         => $this->site->id,
            'rate_per_hour'   => 100,
        ]);
    }

    private function scan(Employee $e, string $date, string $session, string $in, ?string $out, array $extra = []): Attendance
    {
        return Attendance::create([
            'employee_id' => $e->id,
            'site_id'     => $this->site->id,
            'shift_id'    => $e->shift_id,
            'date'        => $date,
            'session'     => $session,
            'time_in'     => "{$date} {$in}:00",
            'time_out'    => $out ? "{$date} {$out}:00" : null,
        ] + $extra);
    }

    private function page(array $query = [])
    {
        return $this->actingAs($this->admin)->get(route('payroll-reports.index', $query))->assertOk();
    }

    /** A worker's days, by date. */
    private function days(Employee $e, string $from = '2026-09-14', string $to = '2026-09-20'): array
    {
        return collect(app(DtrService::class)->range($from, $to)[$e->id]['days'])->keyBy('date')->all();
    }

    private function row(array $rows, Employee $e): array
    {
        return collect($rows)->firstWhere('_id', $e->id);
    }

    // ── The tab ──────────────────────────────────────────────────────────

    public function test_it_took_payroll_summarys_place_and_the_other_tabs_stayed(): void
    {
        $this->assertSame(
            ['dtr', 'employee', 'site', 'overtime', 'deductions', 'advances'],
            array_keys(PayrollReportController::REPORTS)
        );

        $page = $this->page();
        $this->assertSame('dtr', $page->viewData('report'), 'it is what Payroll Reports opens on');

        $html = $page->getContent();
        $tabs = substr($html, strpos($html, '<nav class="rp-tabs"'));
        $tabs = substr($tabs, 0, strpos($tabs, '</nav>'));

        $this->assertStringContainsString('Employees DTR', $tabs);
        $this->assertStringNotContainsString('Payroll Summary', $html);
        foreach (['Payroll by Employee', 'Labor Cost by Site', 'Overtime', 'Deductions', 'Cash Advances'] as $kept) {
            $this->assertStringContainsString($kept, $tabs);
        }

        // An old link to the summary opens the tab that replaced it.
        $this->assertSame('dtr', $this->page(['report' => 'summary'])->viewData('report'));
    }

    // ── The period ───────────────────────────────────────────────────────

    public function test_weekly_moves_a_week_at_a_time_and_never_ahead_of_today(): void
    {
        $now = $this->page();
        $dtr = $now->viewData('dtr');

        $this->assertSame('week', $dtr['span'], 'weekly is how it opens');
        $this->assertSame(['2026-09-14', '2026-09-20'], [$now->viewData('from'), $now->viewData('to')]);
        $this->assertSame('Sep 14 – 20, 2026', $dtr['label']);
        $this->assertSame(['span' => 'week', 'at' => '2026-09-07'], $dtr['prev']);
        $this->assertNull($dtr['next'], 'nothing after the week running now');

        $now->assertSee('Weekly')->assertSee('Monthly')->assertSee('Previous')->assertSee('Next')
            ->assertSee('id="rpNext" aria-disabled="true"', false);

        $before = $this->page($dtr['prev']);
        $this->assertSame(['2026-09-07', '2026-09-13'], [$before->viewData('from'), $before->viewData('to')]);
        $this->assertSame(['span' => 'week', 'at' => '2026-09-14'], $before->viewData('dtr')['next']);

        // The date fields follow the period.
        $before->assertSee('name="from" value="2026-09-07"', false)->assertSee('name="to" value="2026-09-13"', false);

        // A week that straddles two months names both.
        $this->assertSame('Aug 31 – Sep 6, 2026', $this->page(['span' => 'week', 'at' => '2026-09-02'])->viewData('dtr')['label']);

        // Asked for a week still to come: the one running now.
        $this->assertSame('2026-09-14', $this->page(['span' => 'week', 'at' => '2026-12-01'])->viewData('from'));
    }

    public function test_monthly_is_the_calendar_month_and_moves_a_month_at_a_time(): void
    {
        $month = $this->page(['span' => 'month']);
        $dtr   = $month->viewData('dtr');

        $this->assertSame(['2026-09-01', '2026-09-30'], [$month->viewData('from'), $month->viewData('to')]);
        $this->assertSame('September 2026', $dtr['label']);
        $this->assertSame(['span' => 'month', 'at' => '2026-08-01'], $dtr['prev']);
        $this->assertNull($dtr['next']);

        $august = $this->page($dtr['prev']);
        $this->assertSame(['2026-08-01', '2026-08-31'], [$august->viewData('from'), $august->viewData('to')]);
        $this->assertSame('August 2026', $august->viewData('dtr')['label']);
        $this->assertSame(['span' => 'month', 'at' => '2026-09-01'], $august->viewData('dtr')['next']);

        // Weekly and Monthly keep to the days on screen.
        $this->assertSame(['span' => 'week', 'at' => '2026-09-18'], $dtr['week'], "this month's week is today's");
        $this->assertSame(['span' => 'week', 'at' => '2026-08-01'], $august->viewData('dtr')['week']);
    }

    public function test_a_range_set_by_hand_is_used_as_it_was_given(): void
    {
        // Not widened to pay weeks, as the payroll reports' periods are.
        $custom = $this->page(['from' => '2026-09-16', 'to' => '2026-09-10']);
        $dtr    = $custom->viewData('dtr');

        $this->assertSame('custom', $dtr['span']);
        $this->assertSame(['2026-09-10', '2026-09-16'], [$custom->viewData('from'), $custom->viewData('to')]);
        $this->assertSame('Sep 10 – 16, 2026', $dtr['label']);
        $this->assertSame(['from' => '2026-09-03', 'to' => '2026-09-09'], $dtr['prev'], 'back by its own length');
        $this->assertSame(['from' => '2026-09-17', 'to' => '2026-09-23'], $dtr['next']);

        // The span the form carries wins over the dates in its fields, so a
        // site or a name picked on a week keeps the week.
        $kept = $this->page(['span' => 'week', 'at' => '2026-09-07', 'from' => '2026-09-01', 'to' => '2026-09-30']);
        $this->assertSame(['2026-09-07', '2026-09-13'], [$kept->viewData('from'), $kept->viewData('to')]);

        // Cleared — which is what picking a date does — the dates are read.
        $cleared = $this->page(['span' => '', 'at' => '2026-09-07', 'from' => '2026-09-01', 'to' => '2026-09-03']);
        $this->assertSame(['2026-09-01', '2026-09-03'], [$cleared->viewData('from'), $cleared->viewData('to')]);
    }

    // ── Each day ─────────────────────────────────────────────────────────

    public function test_a_day_reads_as_the_scans_and_payroll_have_it(): void
    {
        $ana = $this->days($this->ana);

        // A full day: four scans, eight hours, nothing else to say.
        $mon = $ana['2026-09-14'];
        $this->assertSame(['8:00 AM', '12:00 PM', '1:00 PM', '5:00 PM'], [$mon['in1'], $mon['out1'], $mon['in2'], $mon['out2']]);
        $this->assertSame([480, 480, 0, 0, 0], [$mon['worked'], $mon['regular'], $mon['ot'], $mon['late'], $mon['under']]);
        $this->assertSame(DtrService::COMPLETE, $mon['remark']);

        // Half an hour late.
        $this->assertSame(30, $ana['2026-09-15']['late']);
        $this->assertSame(450, $ana['2026-09-15']['worked']);

        // Two hours past the regular eight.
        $this->assertSame([600, 480, 120], [$ana['2026-09-16']['worked'], $ana['2026-09-16']['regular'], $ana['2026-09-16']['ot']]);

        // Due and never scanned.
        $this->assertSame([DtrService::ABSENT, 'bad', null], [$ana['2026-09-17']['remark'], $ana['2026-09-17']['tone'], $ana['2026-09-17']['worked']]);

        // Home an hour early: the Attendance page's undertime.
        $this->assertSame([420, 60], [$ana['2026-09-18']['worked'], $ana['2026-09-18']['under']]);
        $this->assertSame(DtrService::COMPLETE, $ana['2026-09-18']['remark']);

        // Tomorrow has not happened; Sunday is the rest day.
        $this->assertSame('', $ana['2026-09-19']['remark']);
        $this->assertSame(DtrService::REST, $ana['2026-09-20']['remark']);
    }

    public function test_the_hours_are_the_ones_payroll_counted(): void
    {
        $paid = app(PayrollService::class)->computeForRange('2026-09-14', '2026-09-20');
        $mine = ['minutes' => 0, 'ot_minutes' => 0, 'late_minutes' => 0];

        foreach ($paid['days'] as $day) {
            foreach ($day['details'] as $d) {
                if ((int) $d['employee_id'] === $this->ana->id && is_int($d['id'])) {
                    foreach ($mine as $k => $v) {
                        $mine[$k] += (int) $d[$k];
                    }
                }
            }
        }

        $totals = app(DtrService::class)->range('2026-09-14', '2026-09-20')[$this->ana->id]['totals'];

        $this->assertGreaterThan(0, $mine['minutes']);
        $this->assertSame($mine['minutes'], $totals['worked']);
        $this->assertSame($mine['ot_minutes'], $totals['ot']);
        $this->assertSame($mine['late_minutes'], $totals['late']);
        $this->assertSame($totals['worked'] - $totals['ot'], $totals['regular']);
        $this->assertSame([4, 1, 0, 60], [$totals['days'], $totals['absent'], $totals['missing'], $totals['under']]);
    }

    public function test_a_time_out_nobody_scanned_is_a_missing_scan_with_no_hours_yet(): void
    {
        $ben = $this->days($this->ben);
        $mon = $ben['2026-09-14'];

        $this->assertSame(DtrService::MISSING, $mon['remark']);
        $this->assertSame('No 2nd session out', $mon['note']);
        $this->assertSame(['out2'], $mon['miss'], 'the time the system closed it at is not a scan');
        $this->assertNull($mon['out2']);
        $this->assertSame('8:00 AM', $mon['in1']);
        $this->assertNull($mon['worked'], 'held from payroll until the office settles it');

        // Approved leave is not an absence; the days after it are.
        $this->assertSame(DtrService::LEAVE, $ben['2026-09-15']['remark']);
        $this->assertSame(DtrService::ABSENT, $ben['2026-09-16']['remark']);

        $totals = app(DtrService::class)->range('2026-09-14', '2026-09-20')[$this->ben->id]['totals'];
        $this->assertSame([1, 1, 3], [$totals['days'], $totals['missing'], $totals['absent']]);
    }

    public function test_nobody_is_absent_before_their_shift_has_started(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-18 07:30:00', 'Asia/Manila'));
        $this->assertSame('', $this->days($this->ben)['2026-09-18']['remark']);

        Carbon::setTestNow(Carbon::parse('2026-09-18 08:11:00', 'Asia/Manila'));
        $this->assertSame(DtrService::ABSENT, $this->days($this->ben)['2026-09-18']['remark'], 'past the grace');
    }

    public function test_a_day_still_running_is_in_progress(): void
    {
        $cara = $this->worker('Cara Lim');
        $this->scan($cara, '2026-09-18', 'AM', '08:00', '12:00');
        $this->scan($cara, '2026-09-18', 'PM', '13:00', null);

        Carbon::setTestNow(Carbon::parse('2026-09-18 15:00:00', 'Asia/Manila'));
        $today = $this->days($cara)['2026-09-18'];

        $this->assertSame(DtrService::RUNNING, $today['remark']);
        $this->assertSame('1:00 PM', $today['in2']);
        $this->assertNull($today['out2']);
        $this->assertSame([], $today['miss']);
        $this->assertNull($today['worked'], 'nothing to price until she is out');
    }

    public function test_pending_workers_are_left_out_and_removed_ones_keep_their_days(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-01 08:00:00', 'Asia/Manila'));
        $pending = $this->worker('Still Pending', ['status' => Employee::STATUS_PENDING]);
        $gone    = $this->worker('Gone Midweek');
        $this->scan($gone, '2026-09-14', 'AM', '08:00', '12:00');
        $this->scan($gone, '2026-09-14', 'PM', '13:00', '17:00');

        Carbon::setTestNow(Carbon::parse('2026-09-16 09:00:00', 'Asia/Manila'));
        $gone->delete();

        Carbon::setTestNow(Carbon::parse('2026-09-18 18:00:00', 'Asia/Manila'));
        $all = app(DtrService::class)->range('2026-09-14', '2026-09-20');

        $this->assertArrayNotHasKey($pending->id, $all);
        $this->assertArrayHasKey($gone->id, $all, 'the day worked still happened');

        $days = collect($all[$gone->id]['days'])->keyBy('date');
        $this->assertSame(DtrService::COMPLETE, $days['2026-09-14']['remark']);
        $this->assertSame(DtrService::ABSENT, $days['2026-09-15']['remark'], 'still on the list that day');
        $this->assertSame('', $days['2026-09-16']['remark'], 'not due from the day they were removed');
        $this->assertSame('', $days['2026-09-20']['remark'], 'nor on its rest day');

        // The week after, there is nothing of theirs to list.
        Carbon::setTestNow(Carbon::parse('2026-09-25 18:00:00', 'Asia/Manila'));
        $this->assertArrayNotHasKey($gone->id, app(DtrService::class)->range('2026-09-21', '2026-09-27'));

        $this->actingAs($this->admin)
            ->get(route('payroll-reports.dtr', ['employee' => $pending->id, 'from' => '2026-09-14', 'to' => '2026-09-20']))
            ->assertNotFound();
    }

    // ── The list ─────────────────────────────────────────────────────────

    public function test_the_list_and_the_cards_add_the_days_up(): void
    {
        $page = $this->page();
        $rows = $page->viewData('rows');

        $this->assertCount(2, $rows);
        $ana = $this->row($rows, $this->ana);
        $this->assertSame('Ana Reyes Cruz', $ana['employee']['t']);
        $this->assertSame('#' . str_pad((string) $this->ana->id, 4, '0', STR_PAD_LEFT), $ana['employee']['sub']);
        $this->assertSame(['Site A', $this->day->name], [$ana['place']['top'], $ana['place']['sub']]);
        $this->assertSame([4, 1830, 120, 30, 60, 1, 0],
            [$ana['days'], $ana['regular'], $ana['ot'], $ana['late'], $ana['under'], $ana['absent'], $ana['missing']]);

        $cards = collect($page->viewData('kpis'))->mapWithKeys(fn ($k) => [$k[0] => $k[1]]);
        $this->assertSame(['Employees', 'Days worked', 'Regular hours', 'Overtime', 'Late', 'Absences', 'Missing scans'], $cards->keys()->all());
        $this->assertSame(['2', '5', '30h 30m', '2h 00m', '30m', '4', '1'], $cards->values()->all());

        $this->assertNull($page->viewData('chart'), 'cards, then the list');

        // On the page: whole minutes, a total row, and each figure's colour.
        $html = $page->getContent();
        $this->assertStringContainsString('class="rp-kpis is-7"', $html);
        $this->assertStringContainsString('Total · 2', $html);
        $this->assertMatchesRegularExpression('~<td class="tone-ot" data-v="120">2h 00m</td>~', $html);
        $this->assertMatchesRegularExpression('~<td class="tone-late" data-v="30">30m</td>~', $html);
        $this->assertMatchesRegularExpression('~<td class="tone-bad" data-v="1">1</td>~', $html);
        $this->assertMatchesRegularExpression('~<tr\s+data-dtr="' . $this->ana->id . '" tabindex="0" role="button"~', $html);
    }

    public function test_site_and_name_narrow_the_list(): void
    {
        $other = Site::create(['name' => 'Site DTR Two']);
        $this->ben->forceFill(['site_id' => $other->id])->save();

        $this->assertSame([$this->ben->id], array_column($this->page(['site' => $other->id])->viewData('rows'), '_id'));
        $this->assertSame([$this->ana->id], array_column($this->page(['q' => 'reyes'])->viewData('rows'), '_id'));
        $this->assertSame([$this->ana->id], array_column($this->page(['q' => '#' . $this->ana->id])->viewData('rows'), '_id'));
        $this->page(['q' => 'nobody at all'])->assertSee('No employees for these filters.');
    }

    // ── A worker's page ──────────────────────────────────────────────────

    public function test_a_workers_page_says_who_where_and_every_date(): void
    {
        $html = $this->actingAs($this->admin)
            ->get(route('payroll-reports.dtr', ['employee' => $this->ben->id, 'from' => '2026-09-14', 'to' => '2026-09-20']))
            ->assertOk()->getContent();

        foreach ([
            SystemSetting::current()->company_name, 'Daily Time Record', 'Ben Dela Torre',
            '#' . str_pad((string) $this->ben->id, 4, '0', STR_PAD_LEFT), 'Labor type', 'Mason', 'Site A',
            'Shift schedule', '8:00 AM – 5:00 PM · break 12:00 PM – 1:00 PM', 'Sep 14 – 20, 2026',
            '1st session', '2nd session', 'Undertime', 'Night diff', 'Remarks',
            'Missing scan', 'No scan', 'On leave', 'Absent', 'Rest day', 'Verified by',
            '3 absences · 1 missing scan',
        ] as $needle) {
            $this->assertStringContainsString($needle, $html);
        }

        // Every date of the period has a row, and the page is a piece of one:
        // the pop-up puts it in, so it brings no layout of its own.
        $this->assertSame(7, substr_count($html, '<td class="l date">'));
        $this->assertStringNotContainsString('<html', $html);

        $ana = $this->actingAs($this->admin)
            ->get(route('payroll-reports.dtr', ['employee' => $this->ana->id, 'from' => '2026-09-14', 'to' => '2026-09-20']))
            ->assertOk()->getContent();
        $this->assertStringContainsString('Complete', $ana);
        $this->assertStringContainsString('<td class="ot">2h 00m</td>', $ana);
        $this->assertStringContainsString('<td class="late">30m</td>', $ana);
        $this->assertStringContainsString('Total · 4 days worked', $ana);

        $this->actingAs($this->admin)
            ->get(route('payroll-reports.dtr', ['employee' => 999999, 'from' => '2026-09-14', 'to' => '2026-09-20']))
            ->assertNotFound();
    }

    public function test_it_downloads_as_a_pdf_and_for_excel(): void
    {
        $for = ['employee' => $this->ana->id, 'from' => '2026-09-14', 'to' => '2026-09-20'];

        $pdf = $this->actingAs($this->admin)->get(route('payroll-reports.dtr.pdf', $for))->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment; filename="DTR_ana-reyes-cruz_2026-09-14_to_2026-09-20.pdf"', $pdf->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());

        $xls = $this->actingAs($this->admin)->get(route('payroll-reports.dtr.excel', $for))->assertOk();
        $this->assertStringContainsString('application/vnd.ms-excel', $xls->headers->get('Content-Type'));
        $this->assertStringContainsString('DTR_ana-reyes-cruz_2026-09-14_to_2026-09-20.xls', $xls->headers->get('Content-Disposition'));
        foreach (['Ana Reyes Cruz', 'Daily Time Record', '1st session in', '2nd session out', 'Sep 16, 2026', '2h 00m', 'Absent', 'Verified by'] as $needle) {
            $this->assertStringContainsString($needle, $xls->getContent());
        }

        // And the list itself, from the button at the top of the page.
        $list = $this->actingAs($this->admin)->get(route('payroll-reports.export', ['report' => 'dtr']))->assertOk();
        $this->assertStringContainsString('payroll-report_dtr_2026-09-14_to_2026-09-20.xls', $list->headers->get('Content-Disposition'));
        $this->assertStringContainsString('Employees DTR', $list->getContent());
        $this->assertStringContainsString('Ben Dela Torre', $list->getContent());
        $this->assertStringNotContainsString('pay weeks', $list->getContent());
    }

    public function test_the_pop_up_is_on_the_dtr_tab_only(): void
    {
        $this->page()->assertSee('id="dtrModal"', false)
            ->assertSee('id="dtrPdf"', false)->assertSee('Download PDF')
            ->assertSee('id="dtrXls"', false)->assertSee('Download Excel')
            ->assertSee('Previous employee')->assertSee('Next employee')
            ->assertSee('data-bs-dismiss="modal" aria-label="Close"', false);

        $this->page(['report' => 'employee'])->assertDontSee('id="dtrModal"', false)->assertDontSee('id="dtrPdf"', false);
    }
}
