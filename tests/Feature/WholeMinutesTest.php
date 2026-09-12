<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\LaborType;
use App\Models\Shift;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\PayrollService;
use App\Support\WorkSchedule;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Time is paid in whole minutes.
 *
 * The kiosk stores the second of every scan, and payroll used to count them:
 * a stretch that read "8:00 PM – 8:01 PM" on Attendance was paid as the minute
 * and fifty seconds it really was, and showed in Payroll Records as 0.03 hours
 * beside an Attendance page that said one minute. The screens show minutes, so
 * minutes are what is counted — and what is priced, and what is written.
 */
class WholeMinutesTest extends TestCase
{
    use RefreshDatabase;

    private function worker(bool $night = false): Employee
    {
        return Employee::create([
            'name'            => $night ? 'Night Crew' : 'Day Crew',
            'status'          => Employee::STATUS_ACTIVE,
            'employment_type' => Employee::EMPLOYMENT_DAILY,
            'labor_type_id'   => LaborType::create(['name' => 'Mason ' . ($night ? 'N' : 'D'), 'daily_rate' => 800, 'ot_rate' => 125])->id,
            'shift_id'        => Shift::where('crosses_midnight', $night)->firstOrFail()->id,
            'rate_per_hour'   => 100,
        ]);
    }

    private function row(Employee $e, string $date, string $in, string $out): void
    {
        Attendance::create([
            'employee_id' => $e->id, 'shift_id' => $e->shift_id, 'date' => $date, 'session' => 'AM',
            'time_in' => "{$date} {$in}", 'time_out' => "{$date} {$out}",
        ]);
    }

    private function paidDay(Employee $e, string $date): array
    {
        return collect(app(PayrollService::class)->computeForRange($date, $date)['days'])
            ->flatMap(fn ($d) => $d['details'])
            ->firstWhere('employee_id', $e->id);
    }

    public function test_seconds_are_not_counted_under_the_shift_rules(): void
    {
        SystemSetting::current()->forceFill(['schedule_rules_from' => '2026-09-01'])->save();
        $e = $this->worker();

        // Reads 8:00 AM – 10:00 AM on Attendance; 1h 59m 02s by the seconds.
        $this->row($e, '2026-09-10', '08:00:59', '10:00:01');

        $this->assertSame(120, $this->paidDay($e, '2026-09-10')['minutes'],
            'eight o\'clock to ten is two hours, whatever the seconds say');
    }

    public function test_seconds_are_not_counted_on_days_before_the_shift_rules_either(): void
    {
        SystemSetting::current()->forceFill(['schedule_rules_from' => '2026-09-20'])->save();
        $e = $this->worker();

        $this->row($e, '2026-09-10', '08:00:59', '10:00:01');

        $this->assertSame(120, $this->paidDay($e, '2026-09-10')['minutes']);
    }

    public function test_one_minute_on_the_clock_is_one_minute_worked(): void
    {
        SystemSetting::current()->forceFill(['schedule_rules_from' => '2026-09-01'])->save();
        $e = $this->worker();

        // Reads 8:00 AM – 8:01 AM on Attendance; 1m 50s by the seconds.
        $this->row($e, '2026-09-10', '08:00:05', '08:01:55');

        $this->assertSame(1, $this->paidDay($e, '2026-09-10')['minutes']);
    }

    /**
     * And paid as one minute. The hours were rounded to two decimals before
     * they were priced, so one minute went in as 0.02 of an hour — a minute
     * and twelve seconds' pay.
     */
    public function test_one_minute_is_paid_as_one_minute(): void
    {
        SystemSetting::current()->forceFill(['schedule_rules_from' => '2026-09-01'])->save();
        $e = $this->worker();

        $this->row($e, '2026-09-10', '08:29:00', '08:30:00');
        $day = $this->paidDay($e, '2026-09-10');

        $this->assertEqualsWithDelta($day['rate'] / 60, $day['gross'], 0.006,
            'a minute of the hourly rate, not 0.02 of an hour of it');
    }

    /** Payroll Records writes it as a minute, not as a 0.02 that reads like two. */
    public function test_payroll_records_writes_time_worked_in_minutes(): void
    {
        SystemSetting::current()->forceFill(['schedule_rules_from' => '2026-09-01'])->save();
        $e = $this->worker();
        $this->row($e, '2026-09-10', '08:29:00', '08:30:00');

        $admin = User::create([
            'name' => 'Admin', 'username' => 'admin.minutes', 'password' => 'secret123',
            'role' => User::ROLE_ADMIN, 'is_admin' => true, 'is_active' => true,
        ]);

        // The Excel export keeps decimal hours, which a spreadsheet can add up;
        // the breakdown on screen is for reading.
        $this->actingAs($admin)->get('/payroll-records?mode=weekly&week=2026-W37')
            ->assertOk()
            ->assertSee('<td class="text-end">1m</td>', false)
            ->assertSee('&#8369;1.67', false);
    }

    public function test_a_duration_is_written_in_hours_and_minutes(): void
    {
        $this->assertSame('0m', WorkSchedule::duration(0));
        $this->assertSame('1m', WorkSchedule::duration(1));
        $this->assertSame('59m', WorkSchedule::duration(59));
        $this->assertSame('1h 00m', WorkSchedule::duration(60));
        $this->assertSame('8h 05m', WorkSchedule::duration(485));
    }

    /** The kiosk's double-read guard still counts to the second. */
    public function test_a_double_read_is_still_caught_to_the_second(): void
    {
        SystemSetting::current()->forceFill(['schedule_rules_from' => '2026-09-01'])->save();
        $e = $this->worker(true);

        $clock = function (string $type, string $at) use ($e): array {
            Carbon::setTestNow(Carbon::parse($at, 'Asia/Manila'));

            return $this->postJson('/api/kiosk/attendance', ['employee_id' => $e->id, 'type' => $type])
                ->assertOk()
                ->json();
        };

        // Rounded to the minute these would be 55 seconds apart and let through.
        $clock('time_in', '2026-09-10 20:00:50');
        $answer = $clock('time_out', '2026-09-10 20:00:55');

        $this->assertFalse($answer['success'], 'five seconds after the time-in is a double read');
        $this->assertSame('just_timed_in', $answer['code']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
