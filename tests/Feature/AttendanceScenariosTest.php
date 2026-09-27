<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\LaborType;
use App\Models\Shift;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\PayrollService;
use App\Support\AttendanceDayView;
use App\Support\WorkSchedule;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Attendance, one real-life scenario at a time (Michael, 2026-09-27): a
 * worker scans at the kiosk the way people actually do — on time, late,
 * forgetting to time out, tapping twice — and each test checks what the
 * system makes of it: what the kiosk answers, what the Attendance page shows,
 * whether the office is asked to review it, and what payroll pays.
 *
 *   Day shift    8:00 AM – 12:00 PM · 1:00 PM – 5:00 PM   (15 min grace, 8 h regular)
 *   Night shift  8:00 PM – 12:00 AM · 1:00 AM – 5:00 AM
 *   Rate         ₱800 a day, ₱100 an hour; night differential +10% from 10 PM to 6 AM
 *
 * Every scenario also adds a line to a report printed when the class ends:
 *
 *   php vendor/bin/phpunit tests/Feature/AttendanceScenariosTest.php
 */
class AttendanceScenariosTest extends TestCase
{
    use RefreshDatabase;

    /** One line per scenario, printed at the end. */
    private static array $report = [];

    private ?User $admin = null;

    protected function setUp(): void
    {
        parent::setUp();
        config(['kiosk.enforce_location' => false]);
        SystemSetting::current()->forceFill(['schedule_rules_from' => '2026-09-01', 'auto_count_overtime' => true])->save();
        SystemSetting::forget();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$report) {
            fwrite(STDERR, "\n\nATTENDANCE SCENARIOS\n" . str_repeat('═', 100) . "\n" . implode("\n" . str_repeat('─', 100) . "\n", self::$report) . "\n" . str_repeat('═', 100) . "\n");
        }
        parent::tearDownAfterClass();
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function worker(string $name, bool $night = false): Employee
    {
        return Employee::create([
            'name'            => $name,
            'status'          => Employee::STATUS_ACTIVE,
            'employment_type' => Employee::EMPLOYMENT_DAILY,
            'labor_type_id'   => LaborType::firstOrCreate(['name' => 'Welder'], ['daily_rate' => 800, 'ot_rate' => 125])->id,
            'shift_id'        => Shift::where('crosses_midnight', $night)->orderBy('id')->firstOrFail()->id,
            'rate_per_hour'   => 100,
        ]);
    }

    private function admin(): User
    {
        return $this->admin ??= User::create([
            'name' => 'Office Admin', 'username' => 'office.admin', 'password' => 'secret123',
            'role' => User::ROLE_ADMIN, 'is_admin' => true, 'is_active' => true,
        ]);
    }

    /**
     * Scan at the kiosk: [moment, type] with type time_in, time_out or auto.
     * Returns what the kiosk said to each scan, in words.
     */
    private function scans(Employee $e, array $steps): array
    {
        $said = [];

        foreach ($steps as [$at, $type]) {
            Carbon::setTestNow(Carbon::parse($at, 'Asia/Manila'));
            $r = $this->postJson('/api/kiosk/attendance', ['employee_id' => $e->id, 'type' => $type])->assertOk()->json();

            $said[] = [
                'at'   => Carbon::parse($at)->format('g:i A'),
                'type' => $type,
                'ok'   => (bool) ($r['success'] ?? false),
                'code' => $r['success'] ?? false ? strtoupper(str_replace('_', ' ', $r['type'] ?? '')) . (isset($r['session']) ? ' · ' . $r['session'] : '') : ($r['code'] ?? 'refused'),
                'text' => $r['message'] ?? '',
            ];
        }

        return $said;
    }

    /** The worker's day as the Attendance page draws it, read at $now. */
    private function row(Employee $e, string $now, string $tab = 'history'): ?AttendanceDayView
    {
        Carbon::setTestNow(Carbon::parse($now, 'Asia/Manila'));
        $page = $this->actingAs($this->admin())
            ->get(route('attendance', $tab === 'history' ? ['tab' => 'history', 'view' => 'all'] : ['view' => 'present']))
            ->assertOk();

        return $page->viewData($tab === 'history' ? 'historyBoard' : 'todayBoard')
            ->first(fn ($d) => $d->day->employee()?->id === $e->id);
    }

    /** What payroll pays this worker for the week of 14–20 September. */
    private function paid(Employee $e): array
    {
        $row = collect(app(PayrollService::class)->computeForRange('2026-09-14', '2026-09-20')['employees'])
            ->firstWhere('employee_id', $e->id);

        return ['gross' => (float) ($row['totals']['gross'] ?? 0)];
    }

    /** Is the day under the History "Needs review" filter? */
    private function inReview(Employee $e, string $now): bool
    {
        Carbon::setTestNow(Carbon::parse($now, 'Asia/Manila'));

        return Attendance::where('employee_id', $e->id)->missedSignOut()->exists();
    }

    /** One report entry: the scans, the kiosk's answers, the row, the pay. */
    private function report(string $title, array $said, ?AttendanceDayView $d, ?array $pay = null, ?bool $review = null, ?string $then = null): void
    {
        $lines = [$title];

        foreach ($said as $s) {
            $lines[] = sprintf('   scan %-8s %-9s → %s%s', $s['at'], strtoupper(str_replace('_', ' ', $s['type'])),
                $s['ok'] ? $s['code'] : 'REFUSED (' . $s['code'] . ')', $s['ok'] ? '' : ': ' . $s['text']);
        }

        if ($d) {
            $slot = function (string $k) use ($d) {
                $scan = $d->slot($k);
                $tag  = $d->tag($k);

                return ($scan ? WorkSchedule::label($scan['at']) : '—') . ($tag ? ' [' . $tag['text'] . ']' : '');
            };
            $h = $d->hours();

            $lines[] = sprintf('   page: %s  |  1st in %s · 1st out %s · 2nd in %s · 2nd out %s',
                strtoupper($d->status()['label']), $slot('in'), $slot('bo'), $slot('bi'), $slot('out'));
            $lines[] = '   hours: ' . ($h ? WorkSchedule::duration($h['regular']) . ' regular + ' . WorkSchedule::duration($h['ot']) . ' OT = ' . WorkSchedule::duration($h['worked'])
                    : ($review ? '— (held: not paid until the office settles it)' : '— (not priced yet)'))
                . ($review !== null ? '  |  needs review: ' . ($review ? 'YES' : 'no') : '')
                . ($pay !== null ? '  |  paid: ₱' . number_format($pay['gross'], 2) : '');
        }

        if ($then) {
            $lines[] = '   then: ' . $then;
        }

        self::$report[] = implode("\n", $lines);
    }

    // ── 1 · A normal day ─────────────────────────────────────────────────────

    public function test_01_a_complete_day(): void
    {
        $e = $this->worker('Complete Day');
        $said = $this->scans($e, [
            ['2026-09-15 07:55:00', 'time_in'], ['2026-09-15 12:00:00', 'time_out'],
            ['2026-09-15 12:58:00', 'time_in'], ['2026-09-15 17:00:00', 'time_out'],
        ]);
        $d = $this->row($e, '2026-09-16 10:00:00');

        $this->assertSame('Present', $d->status()['label']);
        $this->assertSame(8 * 60, $d->hours()['regular']);
        $this->assertSame(0, $d->hours()['ot']);
        $this->assertFalse($this->inReview($e, '2026-09-16 10:00:00'));
        $this->assertEqualsWithDelta(800, $this->paid($e)['gross'], 0.01);

        $this->report('01 · Complete day — in, out for lunch, back, out', $said, $d, $this->paid($e), false);
    }

    // ── 2 · Came in, never timed out ─────────────────────────────────────────

    public function test_02_timed_in_and_still_on_site(): void
    {
        $e = $this->worker('Still On Site');
        $said = $this->scans($e, [['2026-09-15 07:58:00', 'time_in']]);
        $d = $this->row($e, '2026-09-15 10:30:00', 'today');

        $this->assertSame('work', $d->key(), 'shown as working while the shift runs');
        $this->assertStringStartsWith('Working', $d->status()['label']);
        $this->assertNull($d->hours(), 'nothing to price while it is open');

        $this->report('02 · Timed in, the shift still running (10:30 AM the same day)', $said, $d, null, $this->inReview($e, '2026-09-15 10:30:00'));
    }

    public function test_03_timed_in_and_never_timed_out(): void
    {
        $e = $this->worker('Forgot Time Out');
        $said = $this->scans($e, [['2026-09-15 07:58:00', 'time_in']]);

        // The next morning: the shift is long over and nobody timed out.
        $d   = $this->row($e, '2026-09-16 10:00:00');
        $row = Attendance::where('employee_id', $e->id)->sole();

        $this->assertSame('review', $d->key());
        $this->assertSame('No time out', $d->status()['label']);
        $this->assertSame('auto', $row->close_type, 'the system closed it');
        $this->assertTrue((bool) $row->needs_review);
        $this->assertSame('12:00 PM', WorkSchedule::label($d->slot('bo')['at']), 'at the end of the session he was in — a guess, never overtime');
        $this->assertSame('Not scanned', $d->tag('bo')['text']);
        $this->assertTrue($this->inReview($e, '2026-09-16 10:00:00'));
        $waiting = $this->paid($e);
        $this->assertEqualsWithDelta(0, $waiting['gross'], 0.01, 'not paid while it waits for review');

        // The office enters the time he actually left: the day is paid.
        $this->actingAs($this->admin())->patchJson(route('attendance.time-out', $row), ['time' => '17:00'])->assertOk();
        $settled = $this->paid($e)['gross'];
        $this->assertEqualsWithDelta(800, $settled, 0.01);

        $this->report('03 · Timed in at 7:58 AM and never timed out (read the next morning)', $said, $d, $waiting, true,
            'the office sets the time out to 5:00 PM under the row → paid ₱' . number_format($settled, 2));
    }

    public function test_04_night_crew_timed_in_and_never_timed_out(): void
    {
        $e = $this->worker('Night No Out', true);
        $said = $this->scans($e, [['2026-09-15 19:55:00', 'time_in']]);
        $d = $this->row($e, '2026-09-16 18:00:00');

        $this->assertSame('No time out', $d->status()['label']);
        $this->assertSame('12:00 AM', WorkSchedule::label($d->slot('bo')['at']));
        $this->assertTrue($this->inReview($e, '2026-09-16 18:00:00'));
        $this->assertEqualsWithDelta(0, $this->paid($e)['gross'], 0.01, 'not paid while it waits for review');

        $this->report('04 · Night crew timed in at 7:55 PM and never timed out', $said, $d, $this->paid($e), true);
    }

    public function test_05_forgot_to_time_out_for_lunch(): void
    {
        $e = $this->worker('Forgot Lunch Out');
        $said = $this->scans($e, [
            ['2026-09-15 07:58:00', 'time_in'],
            ['2026-09-15 13:00:00', 'time_in'],     // back from lunch, never timed out for it
            ['2026-09-15 17:00:00', 'time_out'],
        ]);
        $d = $this->row($e, '2026-09-16 10:00:00');

        $this->assertSame('No 1st session out', $d->status()['label']);
        $this->assertSame('Not scanned', $d->tag('bo')['text']);
        $this->assertSame('12:00 PM', WorkSchedule::label($d->slot('bo')['at']));
        $this->assertSame('1:00 PM', WorkSchedule::label($d->slot('bi')['at']));
        $this->assertTrue($this->inReview($e, '2026-09-16 10:00:00'));
        $this->assertEqualsWithDelta(0, $this->paid($e)['gross'], 0.01, 'the whole day waits, the afternoon too');

        $this->report('05 · Forgot to time out for lunch — in 7:58, in again 1:00 PM, out 5:00 PM', $said, $d, $this->paid($e), true);
    }

    public function test_06_in_and_out_with_nothing_at_the_break(): void
    {
        $e = $this->worker('No Break Scans');
        $said = $this->scans($e, [['2026-09-15 07:58:00', 'time_in'], ['2026-09-15 17:00:00', 'time_out']]);
        $d = $this->row($e, '2026-09-16 10:00:00');

        $this->assertSame('No break scans', $d->status()['label']);
        $this->assertSame('Missing', $d->tag('bo')['text']);
        $this->assertSame('Missing', $d->tag('bi')['text']);
        $this->assertTrue($this->inReview($e, '2026-09-16 10:00:00'));
        $waiting = $this->paid($e);
        $this->assertEqualsWithDelta(0, $waiting['gross'], 0.01, 'not paid while it waits for review');

        $this->actingAs($this->admin())->patchJson(route('attendance.break', Attendance::where('employee_id', $e->id)->sole()), ['decision' => 'accept'])->assertOk();
        $settled = $this->paid($e)['gross'];
        $this->assertEqualsWithDelta(800, $settled, 0.01);

        $this->report('06 · In at 7:58 AM and out at 5:00 PM, nothing at the break', $said, $d, $waiting, true,
            'the office presses Accept → paid ₱' . number_format($settled, 2) . ' (Decline would leave it at ₱0.00, Not recorded)');
    }

    public function test_07_half_day(): void
    {
        $e = $this->worker('Half Day');
        $said = $this->scans($e, [['2026-09-15 07:58:00', 'time_in'], ['2026-09-15 12:00:00', 'time_out']]);
        $d = $this->row($e, '2026-09-16 10:00:00');

        $this->assertSame('Present', $d->status()['label']);
        $this->assertSame(4 * 60, $d->hours()['worked']);
        $this->assertFalse($this->inReview($e, '2026-09-16 10:00:00'));
        $this->assertEqualsWithDelta(400, $this->paid($e)['gross'], 0.01);

        $this->report('07 · Half day — in 7:58 AM, out 12:00 PM, no afternoon', $said, $d, $this->paid($e), false);
    }

    // ── 3 · Late, overbreak, undertime, overtime ─────────────────────────────

    public function test_08_late_overbreak_undertime(): void
    {
        $e = $this->worker('Late And Early');
        $said = $this->scans($e, [
            ['2026-09-15 08:23:00', 'time_in'], ['2026-09-15 12:00:00', 'time_out'],
            ['2026-09-15 13:30:00', 'time_in'], ['2026-09-15 16:00:00', 'time_out'],
        ]);
        $d = $this->row($e, '2026-09-16 10:00:00');

        $this->assertSame('Late 23m', $d->tag('in')['text']);
        $this->assertSame('Overbreak 30m', $d->tag('bi')['text']);
        $this->assertStringStartsWith('Undertime 1h', str_replace("\u{00A0}", ' ', $d->tag('out')['text']));
        $this->assertLessThan(8 * 60, $d->hours()['worked']);

        $this->report('08 · Late 8:23 AM, back from lunch 1:30 PM, left 4:00 PM', $said, $d, $this->paid($e), $this->inReview($e, '2026-09-16 10:00:00'));
    }

    public function test_09_overtime(): void
    {
        $e = $this->worker('Overtime');
        $said = $this->scans($e, [
            ['2026-09-15 07:55:00', 'time_in'], ['2026-09-15 12:00:00', 'time_out'],
            ['2026-09-15 12:55:00', 'time_in'], ['2026-09-15 19:00:00', 'time_out'],
        ]);
        $d = $this->row($e, '2026-09-16 10:00:00');

        $this->assertSame(8 * 60, $d->hours()['regular']);
        $this->assertSame(2 * 60, $d->hours()['ot']);
        $this->assertGreaterThan(800, $this->paid($e)['gross']);

        $this->report('09 · Overtime — out at 7:00 PM instead of 5:00 PM', $said, $d, $this->paid($e), false);
    }

    // ── 4 · What the kiosk turns away ────────────────────────────────────────

    public function test_10_double_taps_are_turned_away(): void
    {
        $e = $this->worker('Double Tap');
        $said = $this->scans($e, [
            ['2026-09-15 07:58:00', 'time_in'],
            ['2026-09-15 07:58:20', 'time_in'],      // pressed TIME IN again
            ['2026-09-15 07:58:40', 'time_out'],     // finger read twice
            ['2026-09-15 12:00:00', 'time_out'],
            ['2026-09-15 12:00:30', 'time_in'],      // second read as they leave for lunch
        ]);

        $this->assertSame(['already_in', 'just_timed_in'], [$said[1]['code'], $said[2]['code']]);
        $this->assertSame('just_timed_out', $said[4]['code']);
        $this->assertSame(1, Attendance::where('employee_id', $e->id)->count(), 'one record, not four');

        $this->report('10 · Double taps — TIME IN twice, a finger read twice, a second read at lunch', $said, $this->row($e, '2026-09-16 10:00:00'));
    }

    public function test_11_one_time_in_and_out_per_session(): void
    {
        $e = $this->worker('Came Back');
        $said = $this->scans($e, [
            ['2026-09-15 07:58:00', 'time_in'], ['2026-09-15 10:00:00', 'time_out'],
            ['2026-09-15 10:15:00', 'time_in'],     // back the same morning
        ]);

        $this->assertSame('session_done', $said[2]['code']);
        $this->assertSame(1, Attendance::where('employee_id', $e->id)->count());

        $this->report('11 · Timed out at 10:00 AM, tried to time in again at 10:15 AM', $said, $this->row($e, '2026-09-16 10:00:00'));
    }

    public function test_12_time_out_without_a_time_in(): void
    {
        $e = $this->worker('No Time In');
        $said = $this->scans($e, [['2026-09-15 17:00:00', 'time_out']]);

        $this->assertSame('no_open', $said[0]['code']);
        $this->assertSame(0, Attendance::where('employee_id', $e->id)->count());

        $this->report('12 · TIME OUT with no time in that day', $said, null);
    }

    public function test_13_time_in_outside_the_shift(): void
    {
        $e = $this->worker('Wrong Shift');
        $said = $this->scans($e, [['2026-09-15 21:00:00', 'time_in']]);

        $this->assertSame('wrong_shift', $said[0]['code']);
        $this->assertSame(0, Attendance::where('employee_id', $e->id)->count());

        $this->report('13 · Day-shift worker tries to time in at 9:00 PM', $said, null);
    }

    // ── 5 · The night crew, and the kiosk without buttons ────────────────────

    public function test_14_a_complete_night(): void
    {
        $e = $this->worker('Night Complete', true);
        $said = $this->scans($e, [
            ['2026-09-15 19:55:00', 'time_in'], ['2026-09-16 00:00:00', 'time_out'],
            ['2026-09-16 00:58:00', 'time_in'], ['2026-09-16 05:00:00', 'time_out'],
        ]);
        $d = $this->row($e, '2026-09-16 18:00:00');

        $this->assertSame('Present', $d->status()['label']);
        $this->assertSame(8 * 60, $d->hours()['regular']);
        // ₱800 for the day plus the night differential: 10% of the hourly rate
        // for each paid hour between 10 PM and 6 AM — six of them here.
        $this->assertEqualsWithDelta(800 + 6 * 100 * 0.10, $this->paid($e)['gross'], 0.01);

        $this->report('14 · Night crew, complete — 7:55 PM to 5:00 AM with the break scanned', $said, $d, $this->paid($e), false);
    }

    public function test_15_automatic_mode_a_normal_day_and_a_forgotten_lunch(): void
    {
        SystemSetting::current()->forceFill(['kiosk_attendance_mode' => SystemSetting::KIOSK_AUTO])->save();
        SystemSetting::forget();

        $ok = $this->worker('Auto Normal');
        $said = $this->scans($ok, [
            ['2026-09-15 07:55:00', 'auto'], ['2026-09-15 12:02:00', 'auto'],
            ['2026-09-15 12:58:00', 'auto'], ['2026-09-15 17:01:00', 'auto'],
        ]);
        $d = $this->row($ok, '2026-09-16 10:00:00');
        $this->assertSame('Present', $d->status()['label']);
        $this->report('15a · Automatic kiosk (scan only) — four scans, a normal day', $said, $d, $this->paid($ok), false);

        $forgot = $this->worker('Auto Forgot Lunch');
        $said = $this->scans($forgot, [
            ['2026-09-15 07:55:00', 'auto'],
            ['2026-09-15 13:05:00', 'auto'],        // after the 12:30 cut-off: the afternoon's TIME IN
            ['2026-09-15 17:01:00', 'auto'],
        ]);
        $d = $this->row($forgot, '2026-09-16 10:00:00');
        $this->assertSame('No 1st session out', $d->status()['label']);
        $this->assertEqualsWithDelta(0, $this->paid($forgot)['gross'], 0.01, 'not paid while it waits for review');
        $this->report('15b · Automatic kiosk — no scan at lunch; the 1:05 PM scan opens the afternoon', $said, $d, $this->paid($forgot), true);
    }
}
