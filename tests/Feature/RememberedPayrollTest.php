<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\LaborType;
use App\Models\Shift;
use App\Models\User;
use App\Services\PayrollService;
use App\Support\Live;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The dashboard and Analytics keep a week's payroll between opens instead of
 * pricing it again every time — and the promise that makes that safe: the
 * moment anything payroll reads is written, the next read prices it afresh.
 */
class RememberedPayrollTest extends TestCase
{
    use RefreshDatabase;

    private const FROM = '2026-09-14';
    private const TO   = '2026-09-20';

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-18 15:00:00', 'Asia/Manila'));
        Live::listenAgain();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_a_week_is_kept_until_something_it_reads_changes(): void
    {
        $worker = $this->worker();
        $this->clock($worker, '2026-09-15', 'AM', '08:00', '12:00');

        $first = $this->net();
        $this->assertGreaterThan(0, $first);

        // Asked again with nothing written: the same figure, not priced again.
        $this->assertSame($first, $this->net());
        $this->assertFalse($this->priced(fn () => $this->net()), 'the week was priced again with nothing changed');

        // A clock-out filed at the kiosk is a new figure.
        $this->clock($worker, '2026-09-15', 'PM', '13:00', '17:00');
        $afterClock = $this->net();
        $this->assertGreaterThan($first, $afterClock);

        // So is a rate changed in Payroll Settings.
        $worker->laborType->update(['daily_rate' => 1200]);
        $this->assertGreaterThan($afterClock, $this->net());
    }

    public function test_a_new_day_prices_the_week_afresh(): void
    {
        $this->clock($this->worker(), '2026-09-15', 'AM', '08:00', '12:00');
        $this->net();

        Carbon::setTestNow(Carbon::parse('2026-09-19 09:00:00', 'Asia/Manila'));

        $this->assertTrue($this->priced(fn () => $this->net()), 'a new day read yesterday\'s figure');
    }

    public function test_the_dashboard_shows_the_new_payout_after_a_clock_in(): void
    {
        $admin = User::create([
            'name' => 'Admin', 'username' => 'admin.remembered', 'password' => 'secret123',
            'role' => User::ROLE_ADMIN, 'is_admin' => true, 'is_active' => true,
        ]);
        $worker = $this->worker();
        $this->clock($worker, '2026-09-15', 'AM', '08:00', '12:00');

        $before = $this->actingAs($admin)->get('/dashboard')->assertOk()->viewData('weeklyPayroll');

        $this->clock($worker, '2026-09-16', 'AM', '08:00', '12:00');

        $after = $this->actingAs($admin)->get('/dashboard')->assertOk()->viewData('weeklyPayroll');

        $this->assertGreaterThan($before, $after);
        $this->assertEqualsWithDelta($this->fresh(), $after, 0.001);
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function net(): float
    {
        return app(PayrollService::class)->remembered('test.week', self::FROM, self::TO, fn (array $c) =>
            (float) collect($c['employees'])->sum(fn ($e) => $e['totals']['net']));
    }

    /** The same figure, worked out from scratch. */
    private function fresh(): float
    {
        return (float) collect(app(PayrollService::class)->computeForRange(self::FROM, self::TO)['employees'])
            ->sum(fn ($e) => $e['totals']['net']);
    }

    /** Whether running $fn priced a range — the rate timeline is read only then. */
    private function priced(callable $fn): bool
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $fn();
        $log = DB::getQueryLog();
        DB::disableQueryLog();

        return collect($log)->contains(fn ($q) => str_contains($q['query'], 'payroll_rates'));
    }

    private function worker(): Employee
    {
        return Employee::create([
            'name'            => 'Rene Villanueva',
            'status'          => Employee::STATUS_ACTIVE,
            'employment_type' => Employee::EMPLOYMENT_DAILY,
            'labor_type_id'   => LaborType::create(['name' => 'Mason', 'daily_rate' => 800, 'ot_rate' => 125])->id,
            'shift_id'        => Shift::where('crosses_midnight', false)->firstOrFail()->id,
            'rate_per_hour'   => 100,
            'fingerprint_id'  => '4411',
        ]);
    }

    private function clock(Employee $e, string $date, string $session, string $in, string $out): void
    {
        Attendance::create([
            'employee_id' => $e->id,
            'shift_id'    => $e->shift_id,
            'date'        => $date,
            'session'     => $session,
            'time_in'     => "{$date} {$in}:00",
            'time_out'    => "{$date} {$out}:00",
        ]);
    }
}
