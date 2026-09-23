<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\LaborType;
use App\Models\Shift;
use App\Models\Site;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\PayrollService;
use App\Support\AttendanceDay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * A worker's day is one attendance, however many times they touched the kiosk.
 *
 * The morning and the afternoon are two rows in the table, and they have to
 * stay two rows: payroll needs every minute, and the office checks a timesheet
 * against the AM and PM times themselves. What was wrong was the reading —
 * the screens listed the rows, so one man's Tuesday arrived as four entries
 * stacked under each other, each with its own date and its own status, and
 * the day itself was nowhere on the page.
 *
 * So: one line per worker per workday, with the stretches spelled out inside
 * it, and the halves of the day decided by the shift's schedule — which is
 * what Payroll Settings edits — rather than by noon off the wall clock.
 */
class AttendanceDayIsOneRecordTest extends TestCase
{
    use RefreshDatabase;

    private Shift $day;
    private Shift $night;
    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-15 10:00:00'));
        SystemSetting::current()->forceFill(['schedule_rules_from' => '2026-09-01'])->save();

        $this->site = Site::firstOrCreate(['name' => 'Site A']);

        // The day as the office types it in Payroll Settings: in at eight,
        // lunch from twelve to one, home at five.
        $this->day = Shift::where('crosses_midnight', false)->firstOrFail();
        $this->day->forceFill(Shift::layOut('08:00', '17:00', '12:00', '13:00') + ['regular_minutes' => 480])->save();

        $this->night = Shift::where('crosses_midnight', true)->firstOrFail();
        $this->night->forceFill(Shift::layOut('20:00', '08:00', '00:00', '01:00') + ['regular_minutes' => 480])->save();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private ?User $admin = null;

    private function admin(): User
    {
        return $this->admin ??= User::create([
            'name'      => 'Admin',
            'username'  => 'admin.oneday',
            'password'  => Hash::make('secret123'),
            'is_admin'  => true,
            'is_active' => true,
        ]);
    }

    private function worker(string $name, bool $night = false): Employee
    {
        return Employee::create([
            'name'            => $name,
            'position'        => 'Mason',
            'status'          => Employee::STATUS_ACTIVE,
            'employment_type' => Employee::EMPLOYMENT_DAILY,
            'labor_type_id'   => LaborType::firstOrCreate(['name' => 'Mason'], ['daily_rate' => 800, 'ot_rate' => 125])->id,
            'shift_id'        => ($night ? $this->night : $this->day)->id,
            'rate_per_hour'   => 100,
        ]);
    }

    /** A stretch, filed the way the kiosk files one. */
    private function stretch(Employee $e, string $date, string $session, string $in, ?string $out): Attendance
    {
        return Attendance::create([
            'employee_id' => $e->id,
            'site_id'     => $this->site->id,
            'shift_id'    => $e->shift_id,
            'date'        => $date,
            'session'     => $session,
            'time_in'     => $in,
            'time_out'    => $out,
        ]);
    }

    /** A full day: in at eight, out for lunch, back at one, home at five. */
    private function fullDay(Employee $e, string $date): void
    {
        $this->stretch($e, $date, 'AM', '08:00:00', '12:00:00');
        $this->stretch($e, $date, 'PM', '13:00:00', '17:00:00');
    }

    private function history(array $query = [])
    {
        return $this->actingAs($this->admin())
            ->get(route('attendance', $query + ['tab' => 'history']))
            ->assertOk();
    }

    // ── One line per worker per day ──────────────────────────────────────

    public function test_a_morning_and_an_afternoon_are_one_record(): void
    {
        $emp = $this->worker('Juan Dela Cruz');
        $this->fullDay($emp, '2026-09-10');

        $days = $this->history()->viewData('historyDays');

        $this->assertCount(1, $days, 'one worker, one day, one line');
        $this->assertSame(1, $this->history()->viewData('historyAttendances')->total(),
            'and the pager counts days, not clocks');

        $day = $days->first();
        $this->assertSame('Juan Dela Cruz', $day->employee()->name);
        $this->assertSame('2026-09-10', $day->date()->toDateString());
    }

    /** The whole point of keeping the rows: both halves are still readable. */
    public function test_the_am_and_pm_times_are_both_kept(): void
    {
        $emp = $this->worker('Juan Dela Cruz');
        $this->fullDay($emp, '2026-09-10');

        $day = $this->history()->viewData('historyDays')->first();

        $this->assertCount(2, $day->stretches());
        $this->assertSame(['AM', 'PM'], $day->sessions());

        // The day reads from the first clock in to the last clock out.
        $this->assertSame('08:00 AM', $day->firstIn()->format('h:i A'));
        $this->assertSame('05:00 PM', $day->lastOut()->format('h:i A'));
    }

    /** And the page says so, rather than the object alone. */
    public function test_the_page_shows_the_day_and_its_two_halves(): void
    {
        $emp = $this->worker('Juan Dela Cruz');
        $this->fullDay($emp, '2026-09-10');

        $html = $this->history()->getContent();

        // One row for the day…
        $this->assertSame(1, substr_count($html, 'Juan Dela Cruz'),
            'the name appears once — one row, not one per clock');

        // …carrying the span and both stretches.
        $this->assertStringContainsString('08:00 AM', $html);
        $this->assertStringContainsString('05:00 PM', $html);
        $this->assertStringContainsString('class="att-stretches"', $html, 'the halves are spelled out');
        $this->assertStringContainsString('12:00 PM', $html, 'the morning ends where it ended');
        $this->assertStringContainsString('01:00 PM', $html, 'the afternoon starts where it started');
    }

    /** A day worked in one go says so plainly, with nothing to spell out. */
    public function test_a_single_stretch_is_not_broken_up(): void
    {
        $emp = $this->worker('Straight Through');
        $this->stretch($emp, '2026-09-10', 'AM', '08:00:00', '17:00:00');

        $html = $this->history()->getContent();

        $this->assertStringNotContainsString('class="att-stretches"', $html,
            'one stretch has no parts worth listing');
        $this->assertStringContainsString('08:00 AM', $html);
    }

    public function test_two_workers_on_one_date_are_two_records(): void
    {
        $this->fullDay($this->worker('Juan Dela Cruz'), '2026-09-10');
        $this->fullDay($this->worker('Pedro Santos'), '2026-09-10');

        $this->assertCount(2, $this->history()->viewData('historyDays'));
    }

    public function test_one_worker_on_two_dates_is_two_records(): void
    {
        $emp = $this->worker('Juan Dela Cruz');
        $this->fullDay($emp, '2026-09-09');
        $this->fullDay($emp, '2026-09-10');

        $days = $this->history()->viewData('historyDays');

        $this->assertCount(2, $days);
        $this->assertSame(['2026-09-10', '2026-09-09'],
            $days->map(fn ($d) => $d->date()->toDateString())->all(),
            'newest workday first');
    }

    /** Today's Attendance is the same day, read the same way. */
    public function test_the_day_view_gathers_the_same_way(): void
    {
        $emp = $this->worker('On Site');
        $this->stretch($emp, '2026-09-15', 'AM', '08:00:00', '12:00:00');
        $this->stretch($emp, '2026-09-15', 'PM', '13:00:00', null);

        $days = $this->actingAs($this->admin())->get(route('attendance'))->assertOk()
            ->viewData('todayAttendances');

        $this->assertCount(1, $days);
        $this->assertTrue($days->first()->isOpen(), 'the afternoon is still running');
        $this->assertSame('active', $days->first()->status(),
            'a closed morning does not settle an open afternoon');
    }

    // ── The night crew, whose day is not in clock order ──────────────────

    /**
     * A night is filed under the evening it opened, so its second half reads
     * 1:00 AM and belongs AFTER its first half at 8:00 PM.
     *
     * Ordered by the clock face the day came out backwards: the stretches
     * listed PM before AM and the span read "1:00 AM – 12:00 AM +1", a day
     * run in reverse. Rows from before the kiosk stored full timestamps keep
     * the time only, which is where the reading goes wrong, so that is what
     * this files.
     */
    public function test_a_night_reads_in_the_order_it_was_worked(): void
    {
        $emp = $this->worker('Night Crew', true);

        // In at eight in the evening, out at midnight; back at one, home at
        // eight the next morning. All one workday, filed under the 10th.
        $this->stretch($emp, '2026-09-10', 'AM', '20:00:00', '00:00:00');
        $this->stretch($emp, '2026-09-10', 'PM', '01:00:00', '08:00:00');

        $day = $this->history()->viewData('historyDays')->first();

        $this->assertSame(['AM', 'PM'], $day->sessions(), 'the evening half comes first');
        $this->assertSame('08:00 PM', $day->firstIn()->format('h:i A'), 'the night opens at eight');
        $this->assertSame('08:00 AM', $day->lastOut()->format('h:i A'), 'and ends the next morning');
        $this->assertSame(1, $day->outDaysLater(), 'which is the following day');

        // And each stretch resolves to the day it was actually worked on.
        [$firstIn, $firstOut]   = $day->partsOf($day->stretches()[0]);
        [$secondIn, $secondOut] = $day->partsOf($day->stretches()[1]);

        $this->assertSame('2026-09-10 20:00', $firstIn->format('Y-m-d H:i'));
        $this->assertSame('2026-09-11 00:00', $firstOut->format('Y-m-d H:i'));
        $this->assertSame('2026-09-11 01:00', $secondIn->format('Y-m-d H:i'));
        $this->assertSame('2026-09-11 08:00', $secondOut->format('Y-m-d H:i'));
    }

    // ── Paging, which is where a day could be cut in half ────────────────

    /**
     * Fifteen to a page, counted in days. Paged by row, a worker's morning
     * would sit at the foot of one page and their afternoon at the head of
     * the next — exactly the reading this change exists to stop.
     */
    public function test_a_day_is_never_split_across_pages(): void
    {
        // Twenty days, each worked in two stretches: forty rows, twenty days.
        $emp = $this->worker('Juan Dela Cruz');
        for ($i = 1; $i <= 20; $i++) {
            $this->fullDay($emp, Carbon::parse('2026-09-10')->subDays($i)->toDateString());
        }

        $first = $this->history();
        $this->assertSame(20, $first->viewData('historyAttendances')->total(), 'twenty days, not forty rows');
        $this->assertCount(15, $first->viewData('historyDays'));

        foreach ($first->viewData('historyDays') as $day) {
            $this->assertCount(2, $day->stretches(), 'every day on the page arrives whole');
        }

        $second = $this->history(['page' => 2]);
        $this->assertCount(5, $second->viewData('historyDays'));

        foreach ($second->viewData('historyDays') as $day) {
            $this->assertCount(2, $day->stretches());
        }
    }

    // ── Deleting a day takes the day ─────────────────────────────────────

    public function test_the_checkbox_carries_every_row_behind_the_day(): void
    {
        $emp = $this->worker('Juan Dela Cruz');
        $this->fullDay($emp, '2026-09-10');

        $ids  = Attendance::where('employee_id', $emp->id)->orderBy('id')->pluck('id')->all();
        $html = $this->history()->getContent();

        $this->assertStringContainsString('value="' . implode(',', $ids) . '"', $html,
            'one checkbox, both rows — or deleting a Tuesday leaves half of it behind');
    }

    public function test_deleting_a_day_removes_both_of_its_stretches(): void
    {
        $emp = $this->worker('Juan Dela Cruz');
        $this->fullDay($emp, '2026-09-10');

        $ids = Attendance::where('employee_id', $emp->id)->pluck('id')->all();

        $this->actingAs($this->admin())
             ->deleteJson(route('attendance.history.bulk-delete'), ['ids' => $ids])
             ->assertOk()
             ->assertJson(['success' => true, 'deleted' => 2]);

        $this->assertSame(0, Attendance::where('employee_id', $emp->id)->count());
    }

    // ── The halves come from Payroll Settings, not from noon ─────────────

    /**
     * AM and PM are where the shift's meal period falls. For the night crew
     * that is nowhere near noon, and stamping it from the wall clock put the
     * start of a night in its second session — so its lateness was measured
     * against the wrong boundary.
     */
    public function test_the_session_follows_the_shift_schedule_not_the_clock(): void
    {
        $night = $this->worker('Night Crew', true);

        // 8 PM is the night crew's first half, though the clock says PM.
        $opens = Attendance::create([
            'employee_id' => $night->id,
            'date'        => '2026-09-10',
            'time_in'     => '20:00:00',
        ]);
        $this->assertSame('AM', $opens->session, 'the first half of the night');

        // And 2 AM is their second, though the clock says AM.
        $back = Attendance::create([
            'employee_id' => $night->id,
            'date'        => '2026-09-10',
            'time_in'     => '02:00:00',
        ]);
        $this->assertSame('PM', $back->session, 'the second half of the night');
    }

    /** The day crew's halves follow the same rule, off the same settings. */
    public function test_moving_the_lunch_hour_moves_the_halves(): void
    {
        $emp = $this->worker('Juan Dela Cruz');

        // Lunch at ten: eleven o'clock is then the afternoon session.
        $this->day->forceFill(Shift::layOut('08:00', '17:00', '10:00', '11:00'))->save();

        $late = Attendance::create([
            'employee_id' => $emp->id,
            'date'        => '2026-09-10',
            'time_in'     => '11:30:00',
        ]);

        $this->assertSame('PM', $late->session,
            'the afternoon starts when the office says lunch ends, not at noon');
    }

    // ── Payroll counts the day once ──────────────────────────────────────

    /**
     * Not changed by any of this, and worth holding still: payroll has always
     * bought a day's regular hours once, whatever number of rows the day
     * arrived in.
     */
    public function test_payroll_reads_the_two_stretches_as_one_day(): void
    {
        $emp = $this->worker('Juan Dela Cruz');
        $this->fullDay($emp, '2026-09-10');

        $paid = collect(app(PayrollService::class)->computeForRange('2026-09-10', '2026-09-10')['employees'])
            ->firstWhere('employee_id', $emp->id);

        $this->assertSame(1, $paid['totals']['workdays'], 'a morning and an afternoon are one day worked');
        $this->assertEqualsWithDelta(8.0, $paid['totals']['hours'], 0.01, 'eight to five, less the hour of lunch');
        $this->assertEqualsWithDelta(0.0, $paid['totals']['overtime'], 0.01,
            'the day buys eight regular hours once, not eight per stretch');
    }

    /** The same day clocked straight through comes to the same money. */
    public function test_a_broken_day_and_a_straight_one_pay_the_same(): void
    {
        $broken = $this->worker('Broken Day');
        $this->fullDay($broken, '2026-09-10');

        $straight = $this->worker('Straight Day');
        $this->stretch($straight, '2026-09-10', 'AM', '08:00:00', '17:00:00');

        $all = collect(app(PayrollService::class)->computeForRange('2026-09-10', '2026-09-10')['employees']);

        $a = $all->firstWhere('employee_id', $broken->id);
        $b = $all->firstWhere('employee_id', $straight->id);

        $this->assertEqualsWithDelta($b['totals']['hours'], $a['totals']['hours'], 0.01);
        $this->assertEqualsWithDelta($b['totals']['gross'], $a['totals']['gross'], 0.01);
    }

    // ── The gathering itself ─────────────────────────────────────────────

    public function test_gather_keeps_workers_and_workdays_apart(): void
    {
        $a = $this->worker('Juan Dela Cruz');
        $b = $this->worker('Pedro Santos');

        $this->fullDay($a, '2026-09-10');
        $this->fullDay($a, '2026-09-09');
        $this->fullDay($b, '2026-09-10');

        $days = AttendanceDay::gather(Attendance::with('employee')->get());

        $this->assertCount(3, $days, 'two workers, three workdays between them');

        foreach ($days as $day) {
            $this->assertCount(2, $day->stretches());
            $this->assertCount(1, $day->rows->pluck('employee_id')->unique());
            $this->assertCount(1, $day->rows->pluck('date')->unique());
        }
    }
}
