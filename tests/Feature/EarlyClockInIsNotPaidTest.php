<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\LaborType;
use App\Models\Shift;
use App\Models\SystemSetting;
use App\Support\WorkSchedule;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Turning up early is not working early.
 *
 * A crew arrives before the gate opens, and the kiosk lets them clock in —
 * TIME IN opens a couple of hours ahead of the shift precisely so nobody is
 * standing outside pressing a button that refuses them. None of that waiting
 * is paid. The day begins when the shift begins, and it begins there whether
 * the worker arrived at ten to or at dawn.
 *
 * Nor is it overtime. Overtime is the far end of a day — past the hours the
 * daily rate buys, and past the shift — never the near end. Time before the
 * shift is paid only when somebody approves an overtime claim for it, which
 * is a decision a supervisor makes, not something a turnstile decides.
 */
class EarlyClockInIsNotPaidTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        SystemSetting::current()->forceFill(['schedule_rules_from' => '2026-09-01'])->save();
    }

    /** Lay a shift out and hand back its schedule. */
    private function shape(bool $night, string $start, string $end, int $break, float $regular): array
    {
        $shift = Shift::where('crosses_midnight', $night)->firstOrFail();

        $shift->forceFill(Shift::layOut($start, $end, $break) + [
            'break_minutes'   => $break,
            'regular_minutes' => (int) round($regular * 60),
        ])->save();

        return $shift->fresh()->schedule();
    }

    private function worker(bool $night): Employee
    {
        return Employee::create([
            'name' => 'Early Bird', 'status' => Employee::STATUS_ACTIVE,
            'employment_type' => Employee::EMPLOYMENT_DAILY,
            'labor_type_id' => LaborType::create(['name' => 'Mason', 'daily_rate' => 800, 'ot_rate' => 125])->id,
            'shift_id' => Shift::where('crosses_midnight', $night)->firstOrFail()->id,
            'rate_per_hour' => 100,
        ]);
    }

    private function clock(Employee $e, string $type, string $at): array
    {
        Carbon::setTestNow(Carbon::parse($at, 'Asia/Manila'));

        return $this->postJson('/api/kiosk/attendance', ['employee_id' => $e->id, 'type' => $type])
                    ->assertOk()->json();
    }

    private function paidOn(string $date): array
    {
        return app(\App\Services\PayrollService::class)
            ->computeForRange($date, $date)['days'][0]['details'][0];
    }

    // ── The hours ────────────────────────────────────────────────────────

    public function test_the_day_starts_when_the_shift_does_however_early_they_arrived(): void
    {
        $s = $this->shape(false, '08:00', '20:00', 60, 8);

        $early = WorkSchedule::split($s, Carbon::parse('2026-09-11 06:00'), Carbon::parse('2026-09-11 20:00'), '2026-09-11');
        $onTime = WorkSchedule::split($s, Carbon::parse('2026-09-11 08:00'), Carbon::parse('2026-09-11 20:00'), '2026-09-11');

        $this->assertEqualsWithDelta($onTime['regular'], $early['regular'], 0.01);
        $this->assertEqualsWithDelta($onTime['ot'], $early['ot'], 0.01);
        $this->assertSame('08:00', $early['segments'][0][0]->format('H:i'),
            'the first paid minute is the shift, not the arrival');
    }

    public function test_two_hours_early_earn_nothing(): void
    {
        $this->shape(false, '08:00', '20:00', 60, 8);
        $emp = $this->worker(false);

        $this->clock($emp, 'time_in', '2026-09-11 06:00:00');
        $this->clock($emp, 'time_out', '2026-09-11 20:00:00');

        $paid = $this->paidOn('2026-09-11');

        $this->assertEqualsWithDelta(11.0, $paid['hours'], 0.01, 'eight to eight, less the break — not ten to eight');
        $this->assertEqualsWithDelta(3.0, $paid['ot_hours'], 0.01);
        $this->assertEqualsWithDelta(800.0, $paid['basicPay'], 0.01);
    }

    /** Arriving early is not overtime at the wrong end of the day. */
    public function test_time_wholly_before_the_shift_is_not_overtime(): void
    {
        $this->shape(false, '08:00', '20:00', 60, 8);
        $emp = $this->worker(false);

        $this->clock($emp, 'time_in', '2026-09-11 06:00:00');
        $this->clock($emp, 'time_out', '2026-09-11 07:30:00');

        $paid = $this->paidOn('2026-09-11');

        $this->assertEqualsWithDelta(0.0, $paid['hours'], 0.01);
        $this->assertEqualsWithDelta(0.0, $paid['ot_hours'], 0.01);
        $this->assertEqualsWithDelta(0.0, $paid['gross'], 0.01, 'an hour and a half in the dark is not a wage');
    }

    /** The early minutes must not eat into the hours the day's rate buys. */
    public function test_arriving_early_does_not_bring_overtime_forward(): void
    {
        $s = $this->shape(false, '08:00', '20:00', 60, 8);

        $split = WorkSchedule::split($s, Carbon::parse('2026-09-11 06:00'), Carbon::parse('2026-09-11 20:00'), '2026-09-11');

        $this->assertSame('17:00', WorkSchedule::overtimeStart($split)->format('H:i'),
            'eight paid hours still run out at five, not at three');
        $this->assertEqualsWithDelta(3.0, $split['ot'], 0.01);
    }

    public function test_it_holds_for_a_shift_that_crosses_midnight(): void
    {
        $this->shape(true, '20:00', '08:00', 60, 8);
        $emp = $this->worker(true);

        $this->clock($emp, 'time_in', '2026-09-11 18:30:00');
        $this->clock($emp, 'time_out', '2026-09-12 08:00:00');

        $paid = $this->paidOn('2026-09-11');

        $this->assertEqualsWithDelta(11.0, $paid['hours'], 0.01, 'eight in the evening, not half past six');
        $this->assertEqualsWithDelta(3.0, $paid['ot_hours'], 0.01);
    }

    /** Waiting in the dark does not earn the night differential either. */
    public function test_early_night_hours_earn_no_differential(): void
    {
        $this->shape(false, '06:00', '15:00', 60, 8);
        $emp = $this->worker(false);

        // Four in the morning is inside the 10pm–6am window; six is not.
        $this->clock($emp, 'time_in', '2026-09-11 04:00:00');
        $this->clock($emp, 'time_out', '2026-09-11 15:00:00');

        $paid = $this->paidOn('2026-09-11');

        $this->assertEqualsWithDelta(8.0, $paid['hours'], 0.01);
        $this->assertEqualsWithDelta(0.0, $paid['nightDiffPay'], 0.01,
            'two hours sitting in the yard before six are not night work');
    }

    // ── And the worker is told ───────────────────────────────────────────

    public function test_the_kiosk_says_when_the_pay_starts(): void
    {
        $this->shape(false, '08:00', '20:00', 60, 8);
        $emp = $this->worker(false);

        $answer = $this->clock($emp, 'time_in', '2026-09-11 06:30:00');

        $this->assertTrue($answer['success'], 'they are let in — they just are not paid yet');
        $this->assertSame('8:00 AM', $answer['paid_from']);
    }

    public function test_arriving_on_time_says_nothing_about_pay_starting_later(): void
    {
        $this->shape(false, '08:00', '20:00', 60, 8);
        $emp = $this->worker(false);

        $answer = $this->clock($emp, 'time_in', '2026-09-11 08:00:00');

        $this->assertArrayNotHasKey('paid_from', $answer);
    }

    /** Early is not late, and must not be reported as either. */
    public function test_arriving_early_is_not_lateness(): void
    {
        $this->shape(false, '08:00', '20:00', 60, 8);
        $emp = $this->worker(false);

        $this->clock($emp, 'time_in', '2026-09-11 06:00:00');
        $this->clock($emp, 'time_out', '2026-09-11 20:00:00');

        $this->assertSame(0, $this->paidOn('2026-09-11')['late_minutes']);
    }

    // ── The screens that report it ───────────────────────────────────────

    /**
     * Analytics measured overtime itself: clock-in to clock-out, less a
     * hardcoded eight. Every part of that was wrong once shifts had their
     * own hours — it counted the wait at the gate, ignored the break, and
     * did not know what the day's rate buys.
     */
    public function test_the_analytics_overtime_figure_matches_what_is_paid(): void
    {
        $this->shape(false, '08:00', '20:00', 60, 8);
        $emp = $this->worker(false);

        $today = Carbon::today()->toDateString();
        $this->clock($emp, 'time_in', $today . ' 06:00:00');
        $this->clock($emp, 'time_out', $today . ' 20:00:00');

        Carbon::setTestNow(Carbon::parse($today . ' 21:00:00', 'Asia/Manila'));

        $admin = \App\Models\User::create([
            'name' => 'Admin', 'username' => 'admin.analytics', 'password' => 'secret123',
            'role' => \App\Models\User::ROLE_ADMIN, 'is_admin' => true, 'is_active' => true,
        ]);

        $page = $this->actingAs($admin)->get('/analytics')->assertOk();

        $this->assertEqualsWithDelta(3.0, $page->viewData('overtimeHours'), 0.01,
            'three hours past the eight the day buys — not six counted from six in the morning');
    }

    /** A day is one worker on one workday, however many rows it took. */
    public function test_the_attendance_rate_counts_days_not_rows(): void
    {
        $this->shape(false, '08:00', '20:00', 60, 8);
        $emp = $this->worker(false);

        $today = Carbon::today()->toDateString();
        $this->clock($emp, 'time_in', $today . ' 08:00:00');
        $this->clock($emp, 'time_out', $today . ' 11:00:00');
        $this->clock($emp, 'time_in', $today . ' 12:00:00');
        $this->clock($emp, 'time_out', $today . ' 20:00:00');

        Carbon::setTestNow(Carbon::parse($today . ' 21:00:00', 'Asia/Manila'));

        $admin = \App\Models\User::create([
            'name' => 'Admin', 'username' => 'admin.rate', 'password' => 'secret123',
            'role' => \App\Models\User::ROLE_ADMIN, 'is_admin' => true, 'is_active' => true,
        ]);

        $page = $this->actingAs($admin)->get('/analytics')->assertOk();

        // One worker, one day present out of the month so far. Two rows must
        // not read as two days — which is what pushed the rate over 100% and
        // left a clamp standing in for the fix.
        $daysSoFar = Carbon::today()->day;
        $this->assertEqualsWithDelta(round(100 / $daysSoFar, 1), $page->viewData('attendanceRate'), 0.11);
    }

    /**
     * The kiosk assistant answers a worker asking about their own overtime.
     * It measured it itself — the stretch, less a flat eight — so it would
     * have told the man who arrived at six that he had six hours of it.
     */
    public function test_the_kiosk_assistant_reports_what_payroll_pays(): void
    {
        $this->shape(false, '08:00', '20:00', 60, 8);
        $emp = $this->worker(false);

        $monday = Carbon::today()->startOfWeek(Carbon::MONDAY)->addDay();
        $this->clock($emp, 'time_in', $monday->format('Y-m-d') . ' 06:00:00');
        $this->clock($emp, 'time_out', $monday->format('Y-m-d') . ' 20:00:00');

        Carbon::setTestNow($monday->copy()->setTime(21, 0));

        $build = new \ReflectionMethod(\App\Http\Controllers\KioskAiController::class, 'buildContext');
        $build->setAccessible(true);

        $context = $build->invoke(
            app(\App\Http\Controllers\KioskAiController::class),
            $emp->fresh(),
            app(\App\Services\PayrollService::class),
        );

        $row = $context['current_cutoff']['attendance'][0];

        $this->assertEqualsWithDelta(3.0, $row['ot_hours'], 0.01,
            'three hours past the eight the day buys — not six counted from the gate');
        $this->assertEqualsWithDelta(11.0, $row['total_hours'], 0.01);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
