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

    /** --today (Michael, 2026-10-05): the day in progress, for everybody who has not scanned yet. */
    public function test_today_is_written_as_far_as_the_clock_has_gone_and_a_rerun_moves_it_along(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-30 11:03:00', 'Asia/Manila'));

        $crew    = $this->crew(30, $this->day, 'Day');
        $scanned = array_shift($crew);
        $ids     = array_map(fn ($e) => $e->id, $crew);
        Employee::create(['name' => 'Still Pending', 'status' => Employee::STATUS_PENDING, 'rate_per_hour' => 100, 'shift_id' => $this->day->id]);

        $kiosk = Kiosk::firstOrCreate(['code' => 'SITE_A'], ['name' => 'Site A Kiosk', 'is_active' => true]);
        $real  = Attendance::create(['employee_id' => $scanned->id, 'shift_id' => $this->day->id, 'kiosk_id' => $kiosk->id,
            'date' => '2026-09-30', 'session' => 'AM', 'time_in' => '2026-09-30 07:55:00']);

        $this->artisan('attendance:sample-day', ['--today' => true])->assertSuccessful();

        // Eleven in the morning: everybody is in, nobody is out, nothing is ahead of the clock.
        $rows = Attendance::whereIn('employee_id', $ids)->get();
        $this->assertCount(29, $rows);
        $this->assertSame(['AM'], $rows->pluck('session')->unique()->values()->all());
        $this->assertSame(0, $rows->whereNotNull('time_out')->count());
        $this->assertTrue($rows->every(fn ($r) => str_starts_with($r->time_in, '2026-09-30') && $r->time_in <= '2026-09-30 11:03:00'));
        $this->assertSame(29, Attendance::fromWorkday(Carbon::now())->whereNull('kiosk_id')->count(), 'the Attendance page lists them under today');

        // Whoever is present already is not written over, and the pending are not workforce.
        $this->assertSame([$real->id], Attendance::where('employee_id', $scanned->id)->pluck('id')->all());
        $this->assertNull($real->fresh()->time_out);
        $this->assertSame(30, Attendance::count());

        // The same minute again adds nothing.
        $this->artisan('attendance:sample-day', ['--today' => true])->assertSuccessful();
        $this->assertSame(30, Attendance::count());

        // Half past twelve: out for the break, not back yet.
        Carbon::setTestNow(Carbon::parse('2026-09-30 12:30:00', 'Asia/Manila'));
        $this->artisan('attendance:sample-day', ['--today' => true])->assertSuccessful();
        $rows = Attendance::whereIn('employee_id', $ids)->get();
        $this->assertCount(29, $rows);
        $this->assertSame(29, $rows->whereNotNull('time_out')->count());

        // The evening: the whole day, the same one the finished-day run writes.
        Carbon::setTestNow(Carbon::parse('2026-09-30 21:07:00', 'Asia/Manila'));
        $this->artisan('attendance:sample-day', ['--today' => true])->assertSuccessful();
        $day = fn () => Attendance::whereIn('employee_id', $ids)->orderBy('employee_id')->orderBy('session')
            ->get(['employee_id', 'session', 'time_in', 'time_out'])->toArray();
        $moved = $day();
        $this->assertCount(58, $moved);
        $this->assertSame(0, Attendance::whereIn('employee_id', $ids)->whereNull('time_out')->count());
        $this->assertSame(0, Attendance::where('needs_review', true)->count());

        $this->artisan('attendance:sample-day', ['--today' => true, '--undo' => true])->assertSuccessful();
        $this->assertSame([$real->id], Attendance::pluck('id')->all(), 'only the real scan is left');

        $this->artisan('attendance:sample-day')->assertSuccessful();
        $this->assertSame($moved, $day());
    }

    public function test_a_day_begun_with_today_and_closed_by_the_system_is_finished_by_the_next_run(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-30 11:03:00', 'Asia/Manila'));
        [$worker] = $this->crew(1, $this->day, 'Day');

        $this->artisan('attendance:sample-day', ['--today' => true])->assertSuccessful();

        // Nobody ran it again: the next morning the open morning is a missed time out.
        Carbon::setTestNow(Carbon::parse('2026-10-01 09:00:00', 'Asia/Manila'));
        $this->assertSame(1, Attendance::closeStale(null, Carbon::now()));
        $this->assertSame(1, Attendance::where('needs_review', true)->count());

        $this->artisan('attendance:sample-day')->assertSuccessful();

        $rows = Attendance::where('employee_id', $worker->id)->whereDate('date', '2026-09-30')->orderBy('session')->get();
        $this->assertSame(['AM', 'PM'], $rows->pluck('session')->all());
        $this->assertSame(0, $rows->where('needs_review', true)->count());
        $this->assertSame([null, null], $rows->pluck('close_type')->all());
        $this->assertSame([null, null], $rows->pluck('close_reason')->all());
        $this->assertGreaterThanOrEqual('2026-09-30 12:00:00', $rows[0]->time_out);
        $this->assertLessThan('2026-09-30 12:05:00', $rows[0]->time_out, 'the break scan, not the guess at the session end');

        $paid = collect(app(PayrollService::class)->computeForRange('2026-09-30', '2026-09-30')['employees'])
            ->firstWhere('employee_id', $worker->id);
        $this->assertSame(1, $paid['totals']['workdays']);
    }
}
