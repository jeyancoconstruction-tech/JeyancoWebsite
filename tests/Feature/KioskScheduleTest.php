<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Kiosk;
use App\Models\LaborType;
use App\Models\PayrollRate;
use App\Models\Shift;
use App\Models\Site;
use App\Models\SystemSetting;
use App\Services\PayrollService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

/**
 * The working day as the kiosk now keeps it.
 *
 *   Day    08:00–12:00 · 13:00–17:00 · overtime after 17:00
 *   Night  20:00–00:00 · 01:00–05:00 · overtime after 05:00
 *
 * A worker presses TIME IN or TIME OUT and scans. TIME IN is only open around
 * their own shift; TIME IN after a TIME OUT starts a new stretch instead of
 * rewriting the first; a session left open is closed at its end, marked AUTO.
 * Payroll counts the hours inside the sessions as regular and the hours after
 * the shift as overtime.
 */
class KioskScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The GPS gate would refuse every clock in a test with no heartbeat.
        config(['kiosk.enforce_location' => false]);

        $s = SystemSetting::current();
        $s->forceFill(['schedule_rules_from' => '2026-09-01', 'auto_count_overtime' => true])->save();
        SystemSetting::forget();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function day(): Shift
    {
        return Shift::where('crosses_midnight', false)->orderBy('id')->firstOrFail();
    }

    private function night(): Shift
    {
        return Shift::where('crosses_midnight', true)->firstOrFail();
    }

    private function worker(string $name, ?Shift $shift = null, ?string $finger = null): Employee
    {
        $labor = LaborType::firstOrCreate(['name' => 'Laborer'], ['daily_rate' => 800, 'ot_rate' => 125]);

        return Employee::create([
            'name'            => $name,
            'status'          => Employee::STATUS_ACTIVE,
            'employment_type' => Employee::EMPLOYMENT_DAILY,
            'labor_type_id'   => $labor->id,
            'shift_id'        => ($shift ?? $this->day())->id,
            'site_id'         => Kiosk::query()->value('site_id'),
            'rate_per_hour'   => 100,
            'fingerprint_id'  => $finger,
        ]);
    }

    private function at(string $moment): void
    {
        Carbon::setTestNow(Carbon::parse($moment, 'Asia/Manila'));
    }

    private function clock(Employee $e, string $type)
    {
        return $this->postJson('/api/kiosk/attendance', ['employee_id' => $e->id, 'type' => $type])->assertOk();
    }

    // ── The shift window ────────────────────────────────────────────────────

    public function test_the_schedule_is_written_on_both_shifts(): void
    {
        $this->assertSame('08:00:00', substr((string) $this->day()->am_starts_at, 0, 8));
        $this->assertSame('17:00:00', substr((string) $this->day()->pm_ends_at, 0, 8));
        $this->assertSame('20:00:00', substr((string) $this->night()->am_starts_at, 0, 8));
        $this->assertSame('05:00:00', substr((string) $this->night()->pm_ends_at, 0, 8));
    }

    public function test_a_day_worker_cannot_time_in_at_night(): void
    {
        $e = $this->worker('Pedro Reyes');

        $this->at('2026-09-15 21:15:00');
        $this->clock($e, 'time_in')->assertJson(['success' => false, 'code' => 'wrong_shift', 'opens' => '6:00 AM', 'closes' => '5:00 PM']);

        $this->assertSame(0, Attendance::where('employee_id', $e->id)->count());
    }

    public function test_a_night_worker_times_in_in_the_evening_but_not_the_morning(): void
    {
        $e = $this->worker('Carlo Mendoza', $this->night());

        $this->at('2026-09-15 10:05:00');
        $this->clock($e, 'time_in')->assertJson(['success' => false, 'code' => 'wrong_shift']);

        $this->at('2026-09-15 19:55:00');
        $this->clock($e, 'time_in')->assertJson(['success' => true, 'session' => 'AM', 'paid_from' => '8:00 PM']);
    }

    public function test_time_out_is_never_refused_for_the_hour(): void
    {
        $e = $this->worker('Late Worker');

        $this->at('2026-09-15 13:00:00');
        $this->clock($e, 'time_in');

        // Out well past the end of the shift. The clock is always accepted —
        // a rule about hours must never leave somebody recorded as on site.
        $this->at('2026-09-15 20:30:00');
        $answer = $this->clock($e, 'time_out')->assertJson(['success' => true])->json();

        // One in the afternoon to half past eight is seven and a half hours,
        // short of the eight the day buys, so none of it is overtime — not
        // even the part after the shift ended.
        $this->assertArrayNotHasKey('ot_hours', $answer);
    }

    // ── Coming back ─────────────────────────────────────────────────────────

    public function test_time_in_after_a_time_out_opens_a_new_stretch(): void
    {
        $e = $this->worker('Juan Dela Cruz');

        $this->at('2026-09-15 08:00:00');
        $this->clock($e, 'time_in');
        $this->at('2026-09-15 10:00:00');
        $this->clock($e, 'time_out');

        $this->at('2026-09-15 10:10:00');
        $this->clock($e, 'time_in')->assertJson(['success' => true, 'again' => true, 'gap_from' => '10:00 AM']);

        $rows = Attendance::where('employee_id', $e->id)->orderBy('id')->get();
        $this->assertCount(2, $rows);
        $this->assertStringContainsString('08:00:00', (string) $rows[0]->time_in, 'the first stretch is not rewritten');
        $this->assertStringContainsString('10:00:00', (string) $rows[0]->time_out);
    }

    public function test_a_second_time_in_while_in_is_refused(): void
    {
        $e = $this->worker('Twice');

        $this->at('2026-09-15 07:52:00');
        $this->clock($e, 'time_in');
        $this->at('2026-09-15 08:20:00');
        $this->clock($e, 'time_in')->assertJson(['success' => false, 'code' => 'already_in', 'since' => '7:52 AM']);

        $this->assertSame(1, Attendance::where('employee_id', $e->id)->count());
    }

    public function test_time_in_after_a_forgotten_lunch_closes_the_morning(): void
    {
        $e = $this->worker('Forgetful');

        $this->at('2026-09-15 07:52:00');
        $this->clock($e, 'time_in');

        $this->at('2026-09-15 13:02:00');
        $this->clock($e, 'time_in')->assertJson(['success' => true, 'session' => 'PM', 'auto_closed' => ['session' => 'AM', 'at' => '12:00 PM']]);

        $am = Attendance::where('employee_id', $e->id)->where('session', 'AM')->firstOrFail();
        $this->assertStringContainsString('2026-09-15 12:00:00', (string) $am->time_out);
        $this->assertSame('auto', $am->close_type);
        $this->assertTrue($am->needs_review);
    }

    public function test_time_out_without_a_time_in_is_refused(): void
    {
        $e = $this->worker('No In');

        $this->at('2026-09-15 17:05:00');
        $this->clock($e, 'time_out')->assertJson(['success' => false, 'code' => 'no_open']);
    }

    public function test_the_scan_suggests_and_never_turns_a_worker_away(): void
    {
        $e = $this->worker('Scanner', null, '7');
        $scan = fn () => $this->postJson('/api/kiosk/scan-attendance', ['fingerprint_id' => '7'])->assertOk();

        $this->at('2026-09-15 08:00:00');
        $scan()->assertJson(['success' => true, 'type' => 'time_in']);
        $this->clock($e, 'time_in');
        $scan()->assertJson(['success' => true, 'type' => 'time_out']);

        $this->at('2026-09-15 10:00:00');
        $this->clock($e, 'time_out');
        $scan()->assertJson(['success' => true, 'type' => 'time_in']);
    }

    public function test_a_session_left_open_is_closed_at_its_end(): void
    {
        $e = $this->worker('Went Home');

        $this->at('2026-09-15 13:00:00');
        $this->clock($e, 'time_in');

        $this->at('2026-09-15 23:30:00');
        $this->getJson('/api/kiosk/today-attendance')->assertOk();

        $row = Attendance::where('employee_id', $e->id)->firstOrFail();
        $this->assertStringContainsString('2026-09-15 17:00:00', (string) $row->time_out, 'closed at the end of the PM session, no overtime');
        $this->assertSame('auto', $row->close_type);
    }

    public function test_the_board_counts_paid_hours_and_overtime_by_the_clock(): void
    {
        $e = $this->worker('Board');

        $this->at('2026-09-15 07:45:00');
        $this->clock($e, 'time_in');
        $this->at('2026-09-15 12:00:00');
        $this->clock($e, 'time_out');
        $this->at('2026-09-15 13:00:00');
        $this->clock($e, 'time_in');
        $this->at('2026-09-15 18:00:00');
        $this->clock($e, 'time_out');

        $this->getJson('/api/kiosk/today-attendance')
             ->assertOk()
             ->assertJsonFragment(['employee_id' => $e->id, 'total_hours' => 8.0, 'overtime_hours' => 1.0]);
    }

    // ── Payroll ─────────────────────────────────────────────────────────────

    /**
     * One employee's day, computed the way payroll computes a range.
     *
     * A day's regular hours are bought once across its records, so a day
     * that arrives as a morning and an afternoon cannot be checked a record
     * at a time — computeRecord alone cannot know what the morning spent.
     *
     * @return array{regular: float, ot: float, basic: float}
     */
    private function dayTotals(Employee $e, string $date): array
    {
        $regular = $ot = $basic = 0.0;

        foreach (app(PayrollService::class)->computeForRange($date, $date)['days'] as $d) {
            foreach ($d['details'] as $r) {
                if ((int) $r['employee_id'] !== (int) $e->id) {
                    continue;
                }

                $ot      += (float) $r['ot_hours'];
                $regular += (float) $r['hours'] - (float) $r['ot_hours'];
                $basic   += (float) $r['basicPay'];
            }
        }

        return ['regular' => round($regular, 2), 'ot' => round($ot, 2), 'basic' => round($basic, 2)];
    }

    private function compute(Attendance $rec, array $extra = []): array
    {
        $m = new ReflectionMethod(PayrollService::class, 'computeRecord');
        $m->setAccessible(true);

        return $m->invoke(app(PayrollService::class), $rec->load('employee.laborType'), [
            'rateTimeline'   => PayrollRate::timeline(),
            'restDayEnabled' => false,
            'holidayTypeMap' => [],
            'day'            => SystemSetting::current(),
            'shifts'         => Shift::lookup(),
        ] + $extra);
    }

    private function row(Employee $e, string $date, string $session, string $in, string $out): Attendance
    {
        return Attendance::create([
            'employee_id' => $e->id,
            'shift_id'    => $e->shift_id,
            'date'        => $date,
            'session'     => $session,
            'time_in'     => "{$date} {$in}",
            'time_out'    => "{$date} {$out}",
        ]);
    }

    public function test_split_days_now_earn_overtime(): void
    {
        $e = $this->worker('Split Day');
        $this->row($e, '2026-09-15', 'AM', '07:55:00', '12:00:00');
        $this->row($e, '2026-09-15', 'PM', '13:00:00', '19:30:00');

        $t = $this->dayTotals($e, '2026-09-15');

        $this->assertEqualsWithDelta(8.0, $t['regular'], 0.01, 'early arrival is not counted');
        $this->assertEqualsWithDelta(2.5, $t['ot'], 0.01, 'the hours past the eight the day buys');
        $this->assertEqualsWithDelta(800.0, $t['basic'], 0.01, 'eight hours is the daily rate');
    }

    /**
     * The same day, clocked without a break in the middle, has to come to the
     * same money. It did not: the figure a day's rate buys was offered afresh
     * to every record, so clocking out for lunch quietly turned three hours
     * of overtime into three hours at the plain rate.
     */
    public function test_a_day_pays_the_same_whether_or_not_lunch_is_clocked(): void
    {
        $split  = $this->worker('Clocked Lunch');
        $whole  = $this->worker('Straight Through');

        $this->row($split, '2026-09-15', 'AM', '08:00:00', '12:00:00');
        $this->row($split, '2026-09-15', 'PM', '13:00:00', '19:30:00');
        $this->row($whole, '2026-09-15', 'AM', '08:00:00', '19:30:00');

        $a = $this->dayTotals($split, '2026-09-15');
        $b = $this->dayTotals($whole, '2026-09-15');

        $this->assertEqualsWithDelta($b['regular'], $a['regular'], 0.01);
        $this->assertEqualsWithDelta($b['ot'], $a['ot'], 0.01);
        $this->assertEqualsWithDelta(8.0, $a['regular'], 0.01);
        $this->assertEqualsWithDelta(2.5, $a['ot'], 0.01);
    }

    public function test_the_afternoon_is_late_against_one_not_eight(): void
    {
        $e = $this->worker('Afternoon');
        $r = $this->compute($this->row($e, '2026-09-15', 'PM', '13:20:00', '17:00:00'));

        $this->assertSame(20, $r['lateMinutes']);
    }

    public function test_a_second_stretch_is_never_late(): void
    {
        $e      = $this->worker('Came Back');
        $first  = $this->row($e, '2026-09-15', 'AM', '08:00:00', '10:00:00');
        $second = $this->row($e, '2026-09-15', 'AM', '10:10:00', '12:00:00');

        $r = $this->compute($second, ['firstInSession' => [$first->id => 'key']]);

        $this->assertSame(0, $r['lateMinutes']);
        $this->assertEqualsWithDelta(1.83, $r['regular_hours'], 0.01);
    }

    public function test_the_night_shift_counts_across_midnight(): void
    {
        $e = $this->worker('Night Pay', $this->night());

        $first  = Attendance::create(['employee_id' => $e->id, 'shift_id' => $e->shift_id, 'date' => '2026-09-15',
            'session' => 'AM', 'time_in' => '2026-09-15 19:55:00', 'time_out' => '2026-09-16 00:00:00']);
        $second = Attendance::create(['employee_id' => $e->id, 'shift_id' => $e->shift_id, 'date' => '2026-09-15',
            'session' => 'PM', 'time_in' => '2026-09-16 01:00:00', 'time_out' => '2026-09-16 05:30:00']);

        $t = $this->dayTotals($e, '2026-09-15');
        $a = $this->compute($first);
        $b = $this->compute($second);

        $this->assertEqualsWithDelta(8.0, $t['regular'], 0.01);
        $this->assertEqualsWithDelta(0.5, $t['ot'], 0.01, 'the half hour past five in the morning');

        // Night differential is per record and needs no running total, so the
        // two stretches can be read on their own for it.
        $this->assertGreaterThan(0, $a['night_hours'] + $b['night_hours']);
    }

    public function test_days_before_the_new_count_are_paid_as_before(): void
    {
        $e = $this->worker('Old Day');
        $r = $this->compute($this->row($e, '2026-08-31', 'AM', '06:00:00', '16:00:00'));

        $this->assertEqualsWithDelta(8.0, $r['regular_hours'], 0.01);
        $this->assertEqualsWithDelta(2.0, $r['ot_hours'], 0.01, 'the old per-record count still applies');
    }

    // ── The site ────────────────────────────────────────────────────────────

    /** The kiosk can only switch between sites the web has. */
    public function test_sites_b_and_c_come_back_when_missing(): void
    {
        Site::whereIn('name', ['Site B', 'Site C'])->delete();

        $migration = require database_path('migrations/2026_09_11_100000_ensure_sites_a_b_c_exist.php');
        $migration->up();
        $migration->up();   // running it twice adds nothing twice

        $names = collect($this->getJson('/api/kiosk/sites')->assertOk()->json('sites'))->pluck('name');

        $this->assertTrue($names->contains('Site A'));
        $this->assertTrue($names->contains('Site B'));
        $this->assertTrue($names->contains('Site C'));
        $this->assertSame(1, Site::where('name', 'Site B')->count());
    }

    public function test_the_kiosk_reports_the_site_it_was_set_to(): void
    {
        $site = Site::create(['name' => 'Site Zeta']);

        $this->postJson('/api/kiosk/active-site', ['kiosk_code' => 'SITE_A', 'site' => 'site-zeta'])
             ->assertOk()
             ->assertJson(['success' => true, 'site' => ['id' => $site->id, 'slug' => 'site-zeta']]);

        $this->assertSame($site->id, Kiosk::where('code', 'SITE_A')->value('site_id'));
    }
}
