<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Kiosk;
use App\Models\LaborType;
use App\Models\Shift;
use App\Models\Site;
use App\Models\SystemSetting;
use App\Services\PayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * php artisan attendance:sample-day (Michael, 2026-09-30): every active
 * worker gets a normal day on their last finished shift, some late and some
 * on overtime, and --undo takes back exactly those rows.
 */
class SampleAttendanceDayTest extends TestCase
{
    use RefreshDatabase;

    private Shift $day;
    private Shift $night;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-09-30 21:07:00', 'Asia/Manila'));
        SystemSetting::current()->forceFill(['schedule_rules_from' => '2026-09-01'])->save();

        $this->day = Shift::where('crosses_midnight', false)->firstOrFail();
        $this->day->forceFill(Shift::layOut('08:00', '17:00', '12:00', '13:00') + ['regular_minutes' => 480])->save();
        $this->night = Shift::where('crosses_midnight', true)->firstOrFail();
        $this->night->forceFill(Shift::layOut('20:00', '05:00', '00:00', '01:00') + ['regular_minutes' => 480])->save();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function crew(int $n, Shift $shift, string $prefix): array
    {
        $site = Site::firstOrCreate(['name' => 'Site A']);
        $type = LaborType::firstOrCreate(['name' => 'Mason'], ['daily_rate' => 800]);

        return array_map(fn ($i) => Employee::create([
            'name' => "{$prefix} {$i}", 'status' => Employee::STATUS_ACTIVE, 'rate_per_hour' => 100,
            'fingerprint_id' => $prefix . $i, 'shift_id' => $shift->id, 'site_id' => $site->id, 'labor_type_id' => $type->id,
        ]), range(1, $n));
    }

    public function test_every_active_worker_gets_a_varied_day_on_their_last_finished_shift(): void
    {
        $days   = $this->crew(40, $this->day, 'Day');
        $nights = $this->crew(4, $this->night, 'Night');
        Employee::create(['name' => 'Still Pending', 'status' => Employee::STATUS_PENDING, 'rate_per_hour' => 100, 'shift_id' => $this->day->id]);

        $this->artisan('attendance:sample-day')->assertSuccessful();

        // Two sessions each; day crew on today, night crew on last night.
        $this->assertSame(88, Attendance::count());
        $this->assertSame(80, Attendance::whereDate('date', '2026-09-30')->count());
        $this->assertSame(8, Attendance::whereDate('date', '2026-09-29')->count());
        $this->assertSame(0, Attendance::whereHas('employee', fn ($q) => $q->where('status', Employee::STATUS_PENDING))->count());

        // A night's second session runs into the next morning.
        $pm = Attendance::where('employee_id', $nights[0]->id)->where('session', 'PM')->firstOrFail();
        $this->assertStringStartsWith('2026-09-30 0', (string) $pm->time_in);

        // Varied like a real crew: some late, some on overtime.
        $ams = Attendance::whereDate('date', '2026-09-30')->where('session', 'AM')->pluck('time_in');
        $pms = Attendance::whereDate('date', '2026-09-30')->where('session', 'PM')->pluck('time_out');
        $this->assertGreaterThan(0, $ams->filter(fn ($t) => substr($t, 11, 5) > '08:00')->count(), 'somebody is late');
        $this->assertGreaterThan(0, $ams->filter(fn ($t) => substr($t, 11, 5) < '08:00')->count(), 'most come early');
        $this->assertGreaterThan(0, $pms->filter(fn ($t) => substr($t, 11, 5) >= '18:00')->count(), 'somebody stays on');

        // Payroll reads it as ordinary days worked.
        $paid = collect(app(PayrollService::class)->computeForRange('2026-09-30', '2026-09-30')['employees'])
            ->firstWhere('employee_id', $days[0]->id);
        $this->assertSame(1, $paid['totals']['workdays']);
        $this->assertGreaterThan(7, $paid['totals']['hours']);
    }

    public function test_a_day_already_scanned_is_left_alone_and_undo_takes_back_only_the_samples(): void
    {
        [$scanned, $other] = $this->crew(2, $this->day, 'Day');
        $kiosk = Kiosk::firstOrCreate(['code' => 'SITE_A'], ['name' => 'Site A Kiosk', 'is_active' => true]);
        $real  = Attendance::create(['employee_id' => $scanned->id, 'shift_id' => $this->day->id, 'kiosk_id' => $kiosk->id,
            'date' => '2026-09-30', 'session' => 'AM', 'time_in' => '2026-09-30 07:55:00', 'time_out' => '2026-09-30 12:01:00']);

        $this->artisan('attendance:sample-day')->assertSuccessful();
        $this->assertSame(1, Attendance::where('employee_id', $scanned->id)->count(), 'the scanned day is not topped up');
        $this->assertSame(2, Attendance::where('employee_id', $other->id)->count());

        $first = Attendance::where('employee_id', $other->id)->orderBy('session')->pluck('time_in')->all();

        $this->artisan('attendance:sample-day', ['--undo' => true])->assertSuccessful();
        $this->assertSame([$real->id], Attendance::pluck('id')->all(), 'only the real scan is left');

        // The same day comes out the same.
        $this->artisan('attendance:sample-day')->assertSuccessful();
        $this->assertSame($first, Attendance::where('employee_id', $other->id)->orderBy('session')->pluck('time_in')->all());
    }
}
