<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\Site;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\PayrollService;
use App\Support\WorkSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * A shift can run longer than the day's rate buys: the live crew is on
 * 8 AM–8 PM with lunch at twelve, and eight hours regular. Michael,
 * 2026-09-30: "kailangan maka lampas muna sila ng 8 hours bago yun maging OT".
 * Out at 5 PM is a full day, not undertime; past 5 PM is overtime; and the
 * sample day puts a normal worker home at 5 PM.
 */
class RegularHoursEndTest extends TestCase
{
    use RefreshDatabase;

    private Shift $day;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-09-30 21:07:00', 'Asia/Manila'));
        SystemSetting::current()->forceFill(['schedule_rules_from' => '2026-09-01'])->save();

        $this->day = Shift::where('crosses_midnight', false)->firstOrFail();
        $this->day->forceFill(Shift::layOut('08:00', '20:00', '12:00', '13:00') + ['regular_minutes' => 480])->save();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function worker(string $name = 'Marvin Resare'): Employee
    {
        return Employee::create(['name' => $name, 'status' => Employee::STATUS_ACTIVE, 'rate_per_hour' => 100,
            'fingerprint_id' => (string) crc32($name), 'shift_id' => $this->day->id,
            'site_id' => Site::firstOrCreate(['name' => 'Site A'])->id]);
    }

    private function dayOf(Employee $e, string $out): void
    {
        foreach ([['AM', '07:55:00', '12:01:00'], ['PM', '12:55:00', $out]] as [$session, $in, $o]) {
            Attendance::create(['employee_id' => $e->id, 'shift_id' => $this->day->id, 'date' => '2026-09-30',
                'session' => $session, 'time_in' => "2026-09-30 {$in}", 'time_out' => "2026-09-30 {$o}"]);
        }
    }

    private function historyHtml(): string
    {
        $admin = User::create(['name' => 'Admin', 'username' => 'admin.reg', 'password' => Hash::make('x1234567'),
            'role' => User::ROLE_ADMIN, 'is_active' => true]);

        return $this->actingAs($admin)->get(route('attendance', ['view' => 'all']))->assertOk()->getContent();
    }

    public function test_the_regular_hours_end_at_five_on_an_eight_to_eight_shift(): void
    {
        $end = WorkSchedule::regularEnd($this->day->schedule(), '2026-09-30');
        $this->assertSame('2026-09-30 17:00', $end->format('Y-m-d H:i'));

        // Where the sessions hold no more than the rate buys, it is the shift's end.
        $this->day->forceFill(Shift::layOut('08:00', '17:00', '12:00', '13:00') + ['regular_minutes' => 480])->save();
        $this->assertSame('17:00', WorkSchedule::regularEnd($this->day->fresh()->schedule(), '2026-09-30')->format('H:i'));
    }

    public function test_out_at_five_is_a_full_day_and_after_five_is_overtime(): void
    {
        $full = $this->worker('Full Day');
        $ot   = $this->worker('Stayed On');
        $this->dayOf($full, '17:00:40');
        $this->dayOf($ot, '18:30:00');

        $paid = collect(app(PayrollService::class)->computeForRange('2026-09-30', '2026-09-30')['employees'])->keyBy('name');
        $this->assertEqualsWithDelta(8.0, $paid['Full Day']['totals']['hours'], 0.1);
        $this->assertEqualsWithDelta(0.0, $paid['Full Day']['totals']['overtime'], 0.01, 'eight hours buys no overtime');
        $this->assertGreaterThan(0, $paid['Stayed On']['totals']['overtime'], 'past 5 PM is overtime');
    }

    public function test_the_board_does_not_call_a_five_oclock_time_out_undertime(): void
    {
        $this->dayOf($this->worker('Full Day'), '17:02:00');
        $this->dayOf($this->worker('Went Early'), '16:30:00');

        $html = $this->historyHtml();

        $this->assertStringNotContainsString('Undertime 2h', $html);
        $this->assertStringNotContainsString('Undertime 3h', $html);
        $this->assertStringContainsString('Undertime 30m', $html, 'leaving before the eight hours still is');
    }

    public function test_the_sample_day_puts_a_normal_worker_home_at_five(): void
    {
        $crew = array_map(fn ($i) => $this->worker("Worker {$i}"), range(1, 40));

        $this->artisan('attendance:sample-day')->assertSuccessful();

        $outs = Attendance::whereDate('date', '2026-09-30')->where('session', 'PM')->pluck('time_out')->map(fn ($t) => substr($t, 11, 5));
        $this->assertCount(40, $outs, 'today, since 5 PM and its overtime are over');
        $this->assertGreaterThan(20, $outs->filter(fn ($t) => $t === '17:00')->count(), 'most go home at five');
        $this->assertGreaterThan(0, $outs->filter(fn ($t) => $t >= '18:00')->count(), 'a few stay on');

        $paid = collect(app(PayrollService::class)->computeForRange('2026-09-30', '2026-09-30')['employees']);
        $this->assertLessThan(15, $paid->filter(fn ($e) => $e['totals']['overtime'] > 0)->count(), 'overtime is the few, not everybody');
    }
}
