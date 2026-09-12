<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\LaborType;
use App\Models\LeaveRequest;
use App\Models\Shift;
use App\Models\Site;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\PayrollService;
use App\Support\WorkSchedule;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Analytics & Insights, drawn from the system's own records.
 *
 * Every figure on the page is either counted from attendance or read off the
 * rows payroll computed, and every filter narrows all of them at once. These
 * tests hold a small, known week and check the page against it — and against
 * PayrollService itself wherever hours or money are shown, because a chart
 * that disagreed with a payslip would be worse than no chart.
 *
 * The week is Friday 4 to Thursday 10 September 2026. Sunday the 6th is the
 * rest day, and there is no holiday in it.
 */
class AnalyticsPageTest extends TestCase
{
    use RefreshDatabase;

    private const NOW  = '2026-09-10 21:00:00';
    private const WEEK = ['2026-09-04', '2026-09-05', '2026-09-06', '2026-09-07', '2026-09-08', '2026-09-09', '2026-09-10'];

    private ?User $admin = null;

    protected function setUp(): void
    {
        parent::setUp();

        $pdo = DB::connection()->getPdo();
        if (DB::connection()->getDriverName() === 'sqlite' && method_exists($pdo, 'sqliteCreateFunction')) {
            $pdo->sqliteCreateFunction('YEAR',  fn ($d) => $d ? (int) substr((string) $d, 0, 4) : null, 1);
            $pdo->sqliteCreateFunction('MONTH', fn ($d) => $d ? (int) substr((string) $d, 5, 2) : null, 1);
        }

        SystemSetting::current()->forceFill(['schedule_rules_from' => '2026-09-01'])->save();
        Carbon::setTestNow(Carbon::parse(self::NOW, 'Asia/Manila'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function admin(): User
    {
        return $this->admin ??= User::create([
            'name' => 'Admin', 'username' => 'admin.analytics.page', 'password' => 'secret123',
            'role' => User::ROLE_ADMIN, 'is_active' => true,
        ]);
    }

    private function site(string $name): Site
    {
        return Site::firstOrCreate(['name' => $name], ['location' => 'Naga']);
    }

    private function shift(bool $night): Shift
    {
        return Shift::where('crosses_midnight', $night)->firstOrFail();
    }

    /** Taken on well before the week, so every day of it is one they were due. */
    private function worker(string $name, Site $site, bool $night = false, string $status = Employee::STATUS_ACTIVE): Employee
    {
        $e = Employee::create([
            'name'            => $name,
            'status'          => $status,
            'employment_type' => Employee::EMPLOYMENT_DAILY,
            'labor_type_id'   => LaborType::create(['name' => 'Mason ' . $name, 'daily_rate' => 800, 'ot_rate' => 125])->id,
            'site_id'         => $site->id,
            'shift_id'        => $this->shift($night)->id,
            'rate_per_hour'   => 100,
        ]);
        $e->forceFill(['created_at' => '2026-08-01 08:00:00'])->saveQuietly();

        return $e->fresh();
    }

    /**
     * A stretch on site, stamped as the kiosk stamps it: where, under which
     * shift, and in which session. Left to the model, the session would come
     * from the test's clock — nine at night — and a morning clock-in would be
     * measured against the afternoon.
     */
    private function clocked(Employee $e, Site $site, string $date, string $in, string $out): void
    {
        Attendance::create([
            'employee_id' => $e->id,
            'site_id'     => $site->id,
            'shift_id'    => $e->shift_id,
            'date'        => $date,
            'session'     => WorkSchedule::sessionAt(Shift::findOrFail($e->shift_id)->schedule(), Carbon::parse($in)),
            'time_in'     => $in,
            'time_out'    => $out,
        ]);
    }

    /** The page's own JSON for the week, under whatever filters are given. */
    private function figures(array $query = []): array
    {
        return $this->actingAs($this->admin())
            ->getJson('/analytics/data?' . http_build_query($query + ['range' => '7']))
            ->assertOk()
            ->json();
    }

    private function on(array $series, string $date): int|float
    {
        return $series[array_search($date, self::WEEK, true)];
    }

    /** What payroll itself computed for one worker over the week. */
    private function paid(Employee $e): array
    {
        $rows = collect(app(PayrollService::class)->computeForRange(self::WEEK[0], self::WEEK[6])['days'])
            ->flatMap(fn ($d) => $d['details'])
            ->where('employee_id', $e->id);

        return [
            'hours' => (float) $rows->sum('hours'),
            'ot'    => (float) $rows->sum('ot_hours'),
            'gross' => (float) $rows->sum('gross'),
            'late'  => $rows->filter(fn ($r) => $r['late_minutes'] > 0)->count(),
        ];
    }

    // ── The page ─────────────────────────────────────────────────────────

    public function test_the_page_is_the_reference_layout_filled_from_real_records(): void
    {
        $north = $this->site('North Yard');
        $ana   = $this->worker('Ana', $north);
        $this->clocked($ana, $north, '2026-09-10', '2026-09-10 08:00:00', '2026-09-10 17:00:00');

        $page = $this->actingAs($this->admin())->get('/analytics')->assertOk();

        foreach (['Analytics & insights', 'Live data', 'Date range', 'Site', 'Shift', 'Employee status',
                  'Reset', 'Apply filters', 'Total employees', 'Present', 'Absent', 'Late', 'Overtime',
                  'Hours worked', 'Payroll cost', 'Attendance trends', 'Late / absent trends',
                  'Hours worked by shift', 'Site performance', 'Payroll summary', 'North Yard'] as $text) {
            $page->assertSee($text);
        }

        $cards = $page->viewData('analytics')['cards'];
        $this->assertSame(1, $cards['totalEmp']);
        $this->assertEqualsWithDelta($this->paid($ana)['gross'], $cards['payroll'], 0.01,
            'the payroll card is what payroll computed');
    }

    public function test_the_filters_in_the_address_are_the_ones_drawn(): void
    {
        $north = $this->site('North Yard');
        $night = $this->shift(true);

        $page = $this->actingAs($this->admin())
            ->get('/analytics?range=14&site=' . $north->id . '&shift=' . $night->id . '&status=inactive')
            ->assertOk();

        $this->assertSame(
            ['range' => '14', 'site' => $north->id, 'shift' => $night->id, 'status' => 'inactive'],
            $page->viewData('filters')
        );
        $this->assertCount(14, $page->viewData('analytics')['trend']['labels']);
    }

    // ── Filters ──────────────────────────────────────────────────────────

    public function test_site_and_shift_narrow_every_figure(): void
    {
        $north = $this->site('North Yard');
        $south = $this->site('South Yard');
        $ana   = $this->worker('Ana', $north);                // the day crew
        $ben   = $this->worker('Ben', $south, night: true);   // the night crew

        // Ana starts late and stays past her hours; Ben works the night of the 9th.
        $this->clocked($ana, $north, '2026-09-10', '2026-09-10 09:00:00', '2026-09-10 19:00:00');
        $this->clocked($ben, $south, '2026-09-09', '2026-09-09 20:00:00', '2026-09-10 08:00:00');

        $paidAna = $this->paid($ana);
        $paidBen = $this->paid($ben);
        $this->assertGreaterThan(0, $paidAna['late'], 'the fixture has to contain a late start');

        $all = $this->figures();
        $this->assertSame(1, $this->on($all['trend']['present'], '2026-09-09'));
        $this->assertSame(1, $this->on($all['trend']['present'], '2026-09-10'));
        $this->assertEqualsWithDelta($paidAna['gross'] + $paidBen['gross'], $all['cards']['payroll'], 0.01);
        $this->assertEqualsWithDelta(round($paidAna['ot'] + $paidBen['ot'], 1), $all['cards']['ot'], 0.01);

        // One site: only what was clocked there, on every card and chart.
        $atNorth = $this->figures(['site' => $north->id]);
        $this->assertSame(0, $this->on($atNorth['trend']['present'], '2026-09-09'));
        $this->assertSame(1, $this->on($atNorth['trend']['present'], '2026-09-10'));
        $this->assertSame((int) round($paidAna['hours']), $atNorth['cards']['hours']);
        $this->assertEqualsWithDelta($paidAna['gross'], $atNorth['cards']['payroll'], 0.01);
        $this->assertSame($paidAna['late'], $atNorth['cards']['late']);
        $this->assertEqualsWithDelta($atNorth['cards']['payroll'], array_sum($atNorth['payroll']['gross']), 0.01,
            'the weekly gross adds up to the payroll card');
        $this->assertSame(['North Yard'], array_column($atNorth['sites'], 'name'));

        // One shift: the night crew alone, and only its own series of hours.
        $nights = $this->figures(['shift' => $this->shift(true)->id]);
        $this->assertSame(1, $this->on($nights['trend']['present'], '2026-09-09'));
        $this->assertSame(0, $this->on($nights['trend']['present'], '2026-09-10'));
        $this->assertCount(1, $nights['shiftHours']['datasets']);
        $this->assertSame($this->shift(true)->name, $nights['shiftHours']['datasets'][0]['label']);
        $this->assertEqualsWithDelta($paidBen['hours'], array_sum($nights['shiftHours']['datasets'][0]['data']), 0.1);
        $this->assertEqualsWithDelta($paidBen['gross'], $nights['cards']['payroll'], 0.01);
    }

    public function test_absent_is_who_was_due_and_did_not_come(): void
    {
        $north = $this->site('North Yard');
        $ana   = $this->worker('Ana', $north);
        $carl  = $this->worker('Carl', $north);

        foreach (['2026-09-09', '2026-09-10'] as $d) {
            $this->clocked($ana, $north, $d, "{$d} 08:00:00", "{$d} 17:00:00");
        }
        LeaveRequest::create([
            'employee_id' => $carl->id, 'leave_type' => 'vacation',
            'starts_on' => '2026-09-09', 'ends_on' => '2026-09-09', 'days' => 1,
            'is_paid' => true, 'status' => 'approved',
        ]);

        $f      = $this->figures();
        $absent = $f['trend']['absent'];

        $this->assertSame(2, $this->on($absent, '2026-09-08'), 'a working day nobody came');
        $this->assertSame(0, $this->on($absent, '2026-09-06'), 'Sunday is the rest day');
        $this->assertSame(0, $this->on($absent, '2026-09-09'), 'Ana came, and Carl was on approved leave');
        $this->assertSame(1, $this->on($absent, '2026-09-10'), 'Carl did not come');

        // Six working days. Ana was due on all six and came on two; Carl was
        // due on five, his leave aside, and came on none: 2 of 11.
        $this->assertSame([['name' => 'North Yard', 'rate' => 18]], $f['sites']);
        $this->assertEqualsWithDelta(1.5, $f['cards']['absent'], 0.001, 'nine absences over six working days');
    }

    public function test_a_shift_that_has_not_started_is_not_an_absence(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-10 10:00:00', 'Asia/Manila'));

        $north = $this->site('North Yard');
        $this->worker('Day Crew', $north);
        $this->worker('Night Crew', $north, night: true);

        $absent = $this->figures()['trend']['absent'];

        $this->assertSame(1, $this->on($absent, '2026-09-10'),
            'the day crew is two hours in; the night crew is not due until the evening');
        $this->assertSame(2, $this->on($absent, '2026-09-09'));
    }

    public function test_status_filter_and_pending_registrations(): void
    {
        $north = $this->site('North Yard');
        $ana   = $this->worker('Ana', $north);
        $dan   = $this->worker('Dan', $north, status: Employee::STATUS_ARCHIVED);
        $dan->forceFill(['archived_at' => '2026-09-08 00:00:00'])->saveQuietly();
        $pat   = $this->worker('Pat', $north, status: Employee::STATUS_PENDING);

        $this->clocked($ana, $north, '2026-09-10', '2026-09-10 08:00:00', '2026-09-10 17:00:00');
        $this->clocked($dan, $north, '2026-09-07', '2026-09-07 08:00:00', '2026-09-07 17:00:00');
        // A row from before pending names were refused at the kiosk.
        $this->clocked($pat, $north, '2026-09-10', '2026-09-10 08:00:00', '2026-09-10 17:00:00');

        $all = $this->figures();
        $this->assertSame(2, $all['cards']['totalEmp'], 'a pending name is not an employee');
        $this->assertSame(1, $this->on($all['trend']['present'], '2026-09-10'), 'nor is their clock attendance');
        $this->assertSame(1, $this->on($all['trend']['present'], '2026-09-07'));

        $active = $this->figures(['status' => 'active']);
        $this->assertSame(1, $active['cards']['totalEmp']);
        $this->assertSame(0, $this->on($active['trend']['present'], '2026-09-07'));

        $inactive = $this->figures(['status' => 'inactive']);
        $this->assertSame(1, $inactive['cards']['totalEmp']);
        $this->assertSame(1, $this->on($inactive['trend']['present'], '2026-09-07'));
        $this->assertSame(0, $this->on($inactive['trend']['absent'], '2026-09-09'), 'nobody is absent after they have left');
    }

    public function test_this_month_runs_from_the_first(): void
    {
        $f = $this->figures(['range' => 'month']);

        $this->assertCount(10, $f['trend']['labels']);
        $this->assertSame('Sep 01', $f['trend']['labels'][0]);
        $this->assertSame('September 2026', $f['period']['label']);
        $this->assertSame('This month', $f['period']['tag']);
    }

    public function test_unknown_filters_fall_back_to_everything(): void
    {
        $f = $this->figures(['range' => '99', 'site' => '999999', 'shift' => 'abc', 'status' => 'gone']);

        $this->assertSame(['range' => '30', 'site' => 'all', 'shift' => 'all', 'status' => 'all'], $f['filters']);
        $this->assertCount(30, $f['trend']['labels']);
    }
}
