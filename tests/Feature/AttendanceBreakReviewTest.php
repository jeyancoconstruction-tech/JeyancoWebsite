<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\LaborType;
use App\Models\Shift;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\PayrollService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A day scanned in and out with nothing at the break — no 1st session time
 * out, no 2nd session time in — waits in "needs review" (Michael,
 * 2026-09-27). It used to read "Present", worked straight through. The office
 * confirms it was, or enters the break, from under the row.
 *
 *   Day    08:00–12:00 · 13:00–17:00
 *   Night  20:00–00:00 · 01:00–05:00
 */
class AttendanceBreakReviewTest extends TestCase
{
    use RefreshDatabase;

    private ?User $admin = null;

    protected function setUp(): void
    {
        parent::setUp();
        config(['kiosk.enforce_location' => false]);
        SystemSetting::current()->forceFill(['schedule_rules_from' => '2026-09-01'])->save();
        SystemSetting::forget();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

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

    private function clock(Employee $e, string $type, string $at)
    {
        Carbon::setTestNow(Carbon::parse($at, 'Asia/Manila'));

        return $this->postJson('/api/kiosk/attendance', ['employee_id' => $e->id, 'type' => $type])->assertOk();
    }

    /** The day as the history draws it. */
    private function historyRow(Employee $e, string $now, string $view = 'all')
    {
        Carbon::setTestNow(Carbon::parse($now, 'Asia/Manila'));
        $board = $this->actingAs($this->admin())
            ->get(route('attendance', ['tab' => 'history', 'view' => $view]))
            ->assertOk()->viewData('historyBoard');

        return $board->first(fn ($d) => $d->day->employee()?->id === $e->id);
    }

    public function test_in_and_out_with_nothing_at_the_break_waits_for_review(): void
    {
        $e = $this->worker('Straight Through');
        $this->clock($e, 'time_in', '2026-09-15 08:00:00');
        $this->clock($e, 'time_out', '2026-09-15 17:00:00');

        $row = Attendance::where('employee_id', $e->id)->sole();
        $this->assertTrue($row->needs_review);
        $this->assertSame(Attendance::NO_BREAK, $row->close_reason);
        $this->assertNull($row->close_type, 'the time out is still the one the worker scanned');

        $day = $this->historyRow($e, '2026-09-16 10:00:00', 'missed');
        $this->assertNotNull($day, 'listed under Needs review');
        $this->assertSame('review', $day->key());
        $this->assertSame('No break scans', $day->status()['label']);
        $this->assertSame('Missing', $day->tag('bo')['text']);
        $this->assertSame('Missing', $day->tag('bi')['text']);
        $this->assertNull($day->tag('out'), 'the scanned time out is not marked as guessed');
        $this->assertSame('break', $day->fixes()[0]['kind']);

        // And not under Completed.
        $this->assertNull($this->historyRow($e, '2026-09-16 10:00:00', 'done'));
    }

    public function test_a_half_day_and_a_normal_lunch_are_not_flagged(): void
    {
        $half = $this->worker('Half Day');
        $this->clock($half, 'time_in', '2026-09-15 08:00:00');
        $this->clock($half, 'time_out', '2026-09-15 12:00:00');

        $lunch = $this->worker('Took Lunch');
        $this->clock($lunch, 'time_in', '2026-09-15 08:00:00');
        $this->clock($lunch, 'time_out', '2026-09-15 12:00:00');
        $this->clock($lunch, 'time_in', '2026-09-15 13:00:00');
        $this->clock($lunch, 'time_out', '2026-09-15 17:00:00');

        $this->assertSame(0, Attendance::where('needs_review', true)->count());
        $this->assertSame('done', $this->historyRow($lunch, '2026-09-16 10:00:00')->key());
    }

    public function test_coming_back_for_the_second_session_clears_it(): void
    {
        $e = $this->worker('Late Lunch');
        $this->clock($e, 'time_in', '2026-09-15 08:00:00');
        $this->clock($e, 'time_out', '2026-09-15 13:10:00');
        $this->assertTrue(Attendance::where('employee_id', $e->id)->sole()->breakUnscanned());

        $this->clock($e, 'time_in', '2026-09-15 13:20:00')->assertJson(['success' => true, 'session' => 'PM']);
        $this->assertSame(0, Attendance::where('employee_id', $e->id)->where('needs_review', true)->count());
    }

    public function test_the_office_confirms_it_was_worked_through(): void
    {
        $e = $this->worker('Confirmed');
        $this->clock($e, 'time_in', '2026-09-15 08:00:00');
        $this->clock($e, 'time_out', '2026-09-15 17:00:00');
        $row = Attendance::where('employee_id', $e->id)->sole();

        $pay = fn () => collect(app(PayrollService::class)->computeForRange('2026-09-14', '2026-09-20')['employees'])
            ->firstWhere('employee_id', $e->id)['totals']['gross'];
        $before = $pay();

        Carbon::setTestNow(Carbon::parse('2026-09-16 10:00:00', 'Asia/Manila'));
        $this->actingAs($this->admin())->patchJson(route('attendance.break', $row), ['through' => true])
             ->assertOk()->assertJson(['success' => true]);

        $row->refresh();
        $this->assertFalse($row->needs_review);
        $this->assertSame('Worked through the break', $row->close_reason);
        $this->assertSame($this->admin()->id, (int) $row->reviewed_by);
        $this->assertTrue(AuditLog::where('description', 'Confirmed Confirmed worked through the break on 09/15/2026')->exists());
        $this->assertEqualsWithDelta($before, $pay(), 0.001, 'the pay is as it was');

        $day = $this->historyRow($e, '2026-09-16 10:00:00');
        $this->assertSame('done', $day->key());
        $this->assertSame('No break scan', $day->tag('bo')['text']);

        // Settled once; not again.
        $this->patchJson(route('attendance.break', $row), ['through' => true])->assertStatus(422);
    }

    public function test_the_office_enters_the_break(): void
    {
        $e = $this->worker('Break Entered');
        $this->clock($e, 'time_in', '2026-09-15 08:00:00');
        $this->clock($e, 'time_out', '2026-09-15 17:00:00');
        $row = Attendance::where('employee_id', $e->id)->sole();

        Carbon::setTestNow(Carbon::parse('2026-09-16 10:00:00', 'Asia/Manila'));
        $this->actingAs($this->admin())->patchJson(route('attendance.break', $row), ['out' => '18:00', 'in' => '18:30'])
             ->assertStatus(422);

        $this->patchJson(route('attendance.break', $row), ['out' => '12:05', 'in' => '12:55'])
             ->assertOk()->assertJson(['success' => true]);

        $rows = Attendance::where('employee_id', $e->id)->orderBy('time_in')->get();
        $this->assertCount(2, $rows);
        $this->assertSame(['2026-09-15 08:00:00', '2026-09-15 12:05:00'], [(string) $rows[0]->time_in, (string) $rows[0]->time_out]);
        $this->assertSame('admin', $rows[0]->close_type);
        $this->assertFalse($rows[0]->needs_review);
        $this->assertSame(['2026-09-15 12:55:00', '2026-09-15 17:00:00', 'PM'], [(string) $rows[1]->time_in, (string) $rows[1]->time_out, $rows[1]->session]);
        $this->assertTrue(AuditLog::where('description', 'Entered the break for Break Entered on 09/15/2026: 1st session out 12:05 PM, 2nd session in 12:55 PM')->exists());

        $day = $this->historyRow($e, '2026-09-16 10:00:00');
        $this->assertSame('done', $day->key());
        $this->assertSame('12:05 PM', \App\Support\WorkSchedule::label($day->slot('bo')['at']));
        $this->assertSame('12:55 PM', \App\Support\WorkSchedule::label($day->slot('bi')['at']));

        // The typed time in reads as the office's, not as a scan.
        $in = collect($day->scans())->first(fn ($s) => \App\Support\WorkSchedule::label($s['at']) === '12:55 PM');
        $this->assertSame(['Edited', 'Set by Office Admin'], [$in['tag'], $in['where']]);
    }

    public function test_a_night_crew_day_is_flagged_and_its_break_crosses_midnight(): void
    {
        // Aldrin's 09/26: in at 8:23 PM, out at 8:22 AM, nothing between.
        $e = $this->worker('Night Crew', true);
        $this->clock($e, 'time_in', '2026-09-15 20:23:00');
        $this->clock($e, 'time_out', '2026-09-16 08:22:00');
        $row = Attendance::where('employee_id', $e->id)->sole();
        $this->assertTrue($row->breakUnscanned());

        Carbon::setTestNow(Carbon::parse('2026-09-16 12:00:00', 'Asia/Manila'));
        $this->actingAs($this->admin())->patchJson(route('attendance.break', $row), ['out' => '00:00', 'in' => '01:00'])
             ->assertOk();

        $rows = Attendance::where('employee_id', $e->id)->orderBy('time_in')->get();
        $this->assertSame('2026-09-16 00:00:00', (string) $rows[0]->time_out);
        $this->assertSame(['2026-09-16 01:00:00', '2026-09-16 08:22:00'], [(string) $rows[1]->time_in, (string) $rows[1]->time_out]);
        $this->assertSame('2026-09-15', \Carbon\Carbon::parse($rows[1]->date)->toDateString(), 'the same workday');
    }

    public function test_days_already_on_file_are_flagged_by_the_migration(): void
    {
        $e = $this->worker('Before Today');
        $shift = $e->shift_id;
        $mk = fn (string $date, string $in, string $out, string $session = 'AM') => Attendance::create([
            'employee_id' => $e->id, 'shift_id' => $shift, 'date' => $date, 'session' => $session,
            'time_in' => "$date $in", 'time_out' => "$date $out",
        ]);

        // Ordinary days first: the backfill must not stop at the first day it skips.
        $mk('2026-09-11', '08:00:00', '12:00:00');
        $mk('2026-09-11', '13:00:00', '17:00:00', 'PM');
        $half = $mk('2026-09-12', '08:00:00', '12:00:00');
        $through = $mk('2026-09-10', '08:00:00', '17:00:00');

        (require database_path('migrations/2026_09_27_120000_flag_days_with_no_break_scans.php'))->up();

        $this->assertTrue($through->fresh()->breakUnscanned());
        $this->assertFalse($half->fresh()->needs_review);
        $this->assertSame(1, Attendance::where('needs_review', true)->count());
    }

    public function test_a_scanned_time_out_cannot_be_overwritten_as_if_it_were_missing(): void
    {
        $e = $this->worker('Scanned Out');
        $this->clock($e, 'time_in', '2026-09-15 08:00:00');
        $this->clock($e, 'time_out', '2026-09-15 17:00:00');
        $row = Attendance::where('employee_id', $e->id)->sole();

        Carbon::setTestNow(Carbon::parse('2026-09-16 10:00:00', 'Asia/Manila'));
        $this->actingAs($this->admin())->patchJson(route('attendance.time-out', $row), ['time' => '15:00'])
             ->assertStatus(422);
        $this->assertSame('2026-09-15 17:00:00', (string) $row->fresh()->time_out);
    }
}
