<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\LaborType;
use App\Models\Shift;
use App\Models\Site;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The Attendance page reads each day the way a shift is laid out — first
 * session in and out, the break, second session in and out — and says where
 * the day stands: working, on break, over the break, waiting on the office,
 * or done. The hours are payroll's, in whole minutes. A time out nobody
 * scanned is settled from under the row.
 *
 * The day shift throughout: in at eight, lunch twelve to one, home at five,
 * eight regular hours, ten minutes' grace.
 */
class AttendanceBoardTest extends TestCase
{
    use RefreshDatabase;

    private Shift $day;
    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();

        $this->at('2026-09-15 10:00:00');
        SystemSetting::current()->forceFill(['schedule_rules_from' => '2026-09-01'])->save();

        $this->site = Site::firstOrCreate(['name' => 'Site A']);

        $this->day = Shift::where('crosses_midnight', false)->firstOrFail();
        $this->day->forceFill(Shift::layOut('08:00', '17:00', '12:00', '13:00')
            + ['regular_minutes' => 480, 'grace_period_minutes' => 10])->save();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function at(string $moment): void
    {
        Carbon::setTestNow(Carbon::parse($moment, 'Asia/Manila'));
    }

    private ?User $admin = null;

    private function admin(): User
    {
        return $this->admin ??= User::create([
            'name'      => 'Office Admin',
            'username'  => 'admin.board',
            'password'  => Hash::make('secret123'),
            'is_admin'  => true,
            'is_active' => true,
        ]);
    }

    private function worker(string $name): Employee
    {
        return Employee::create([
            'name'            => $name,
            'position'        => 'Mason',
            'status'          => Employee::STATUS_ACTIVE,
            'employment_type' => Employee::EMPLOYMENT_DAILY,
            'labor_type_id'   => LaborType::firstOrCreate(['name' => 'Mason'], ['daily_rate' => 800, 'ot_rate' => 125])->id,
            'shift_id'        => $this->day->id,
            'rate_per_hour'   => 100,
        ]);
    }

    private function stretch(Employee $e, string $date, string $session, string $in, ?string $out, array $extra = []): Attendance
    {
        return Attendance::create([
            'employee_id' => $e->id,
            'site_id'     => $this->site->id,
            'shift_id'    => $e->shift_id,
            'date'        => $date,
            'session'     => $session,
            'time_in'     => "{$date} {$in}",
            'time_out'    => $out ? "{$date} {$out}" : null,
        ] + $extra);
    }

    private function page(array $query = [])
    {
        return $this->actingAs($this->admin())->get(route('attendance', $query + ['view' => 'all']))->assertOk();
    }

    /** The one row a worker's name is on, from the table asked for. */
    private function row(string $html, string $name, string $table = 'todayTable'): string
    {
        preg_match('#<table class="atm-table" id="' . $table . '">.*?</table>#s', $html, $t);
        $this->assertNotEmpty($t, "#{$table} should be on the page");

        preg_match('#<tr class="atm-row"(?:(?!</tr>).)*<b>' . preg_quote($name, '#') . '</b>.*?</tr>#s', $t[0], $row);
        $this->assertNotEmpty($row, "{$name} should have a row in #{$table}");

        return $row[0];
    }

    // ── Where the day stands ─────────────────────────────────────────────

    public function test_a_worker_in_the_first_session_is_working(): void
    {
        $this->stretch($this->worker('Ana Working'), '2026-09-15', 'AM', '08:05:00', null);

        $page = $this->page();
        $row  = $this->row($page->getContent(), 'Ana Working');

        $this->assertStringContainsString('Working · AM', $row);
        $this->assertStringContainsString('exp. 12:00 PM', $row, 'the lunch time out is still ahead of her');
        $this->assertSame(1, $page->viewData('clockedIn'));
        $this->assertSame(0, $page->viewData('onBreak'));
    }

    /**
     * Back from lunch inside the grace and still working: on time. Payroll
     * has nothing to price in a stretch still open, and the figure it keeps
     * for one is measured from the start of the whole shift — read here, it
     * made five minutes after one o'clock an overbreak of five hours.
     */
    public function test_an_open_afternoon_is_measured_from_the_afternoon(): void
    {
        $emp = $this->worker('Lito Back');
        $this->stretch($emp, '2026-09-15', 'AM', '08:00:00', '12:00:00');
        $this->stretch($emp, '2026-09-15', 'PM', '13:05:00', null);

        $this->at('2026-09-15 14:00:00');
        $row = $this->row($this->page()->getContent(), 'Lito Back');

        $this->assertStringContainsString('Working · PM', $row);
        $this->assertStringNotContainsString('Overbreak', $row);
        $this->assertStringNotContainsString('Late', $row);
    }

    public function test_out_for_lunch_is_on_break_until_the_grace_runs_out(): void
    {
        $this->stretch($this->worker('Ben Lunch'), '2026-09-15', 'AM', '08:00:00', '12:00:00');

        $this->at('2026-09-15 12:30:00');
        $page = $this->page();
        $row  = $this->row($page->getContent(), 'Ben Lunch');

        $this->assertStringContainsString('On break', $row);
        $this->assertStringContainsString('due 1:10 PM', $row, 'back by the end of the break, grace included');
        $this->assertSame(1, $page->viewData('onBreak'));
        $this->assertSame(0, $page->viewData('overBreak'));

        $this->at('2026-09-15 13:25:00');
        $page = $this->page();

        $this->assertStringContainsString('Overbreak 25m', $this->row($page->getContent(), 'Ben Lunch'),
            'counted from the start of the afternoon, once the grace is spent');
        $this->assertSame(1, $page->viewData('overBreak'));
    }

    /** A history day that stops at lunch is a half day, not a break still running. */
    public function test_a_finished_day_that_stopped_at_lunch_is_not_on_break(): void
    {
        $this->stretch($this->worker('Cora Half'), '2026-09-14', 'AM', '08:00:00', '12:00:00');

        $row = $this->row($this->page(['tab' => 'history'])->getContent(), 'Cora Half', 'historyTable');

        $this->assertStringContainsString('Present', $row);
        $this->assertStringNotContainsString('On break', $row);
    }

    /**
     * In before the shift starts is the same morning, however the time was
     * stored. Measured from the start of the shift, a day crew in at 7:00 for
     * eight o'clock read as arriving the next day — and their lunch time out,
     * earlier than that, as the end of a day worked straight through.
     */
    public function test_an_early_arrival_is_the_same_morning(): void
    {
        $full = $this->worker('Early Full');
        $this->stretch($full, '2026-09-14', 'AM', '07:00:00', '12:00:00');
        $this->stretch($full, '2026-09-14', 'PM', '13:00:00', '17:00:00');

        $bare = $this->worker('Early Bare');
        Attendance::create(['employee_id' => $bare->id, 'shift_id' => $this->day->id, 'site_id' => $this->site->id,
            'date' => '2026-09-14', 'session' => 'AM', 'time_in' => '07:00:00', 'time_out' => '12:00:00']);
        Attendance::create(['employee_id' => $bare->id, 'shift_id' => $this->day->id, 'site_id' => $this->site->id,
            'date' => '2026-09-14', 'session' => 'PM', 'time_in' => '13:00:00', 'time_out' => '17:00:00']);

        $html = $this->page(['tab' => 'history'])->getContent();

        foreach (['Early Full', 'Early Bare'] as $name) {
            $row = $this->row($html, $name, 'historyTable');

            preg_match_all('#<span class="atm-t">\s*([^<]+?)\s*</span>#', $row, $m);
            $this->assertSame(['7:00 AM', '12:00 PM', '1:00 PM', '5:00 PM'], array_map('trim', $m[1]), $name);
            $this->assertStringNotContainsString('No break scan', $row, $name);
        }
    }

    public function test_lateness_is_shown_where_it_happened(): void
    {
        $emp = $this->worker('Dan Late');
        $this->stretch($emp, '2026-09-14', 'AM', '08:25:00', '12:00:00');
        $this->stretch($emp, '2026-09-14', 'PM', '13:20:00', '17:00:00');

        $row = $this->row($this->page(['tab' => 'history'])->getContent(), 'Dan Late', 'historyTable');

        $this->assertStringContainsString('Late 25m', $row, 'under the first time in');
        $this->assertStringContainsString('Overbreak 20m', $row, 'under the time back from the break');
    }

    // ── All: the whole roster ────────────────────────────────────────────

    /**
     * All is everybody on the roster, whether they scanned or not, each with
     * where they stand: expected later, not in yet, absent — or not expected
     * today at all. Nobody pending or archived.
     */
    public function test_all_lists_everybody_with_where_they_stand(): void
    {
        $night = Shift::where('crosses_midnight', true)->firstOrFail();
        $night->forceFill(Shift::layOut('20:00', '05:00', '00:00', '01:00') + ['regular_minutes' => 480])->save();

        $this->stretch($this->worker('In On Time'), '2026-09-15', 'AM', '08:00:00', null);
        $this->worker('Not Yet Here');
        $this->worker('Night Later')->forceFill(['shift_id' => $night->id])->save();

        $away = $this->worker('Away On Leave');
        \App\Models\LeaveRequest::create([
            'employee_id' => $away->id, 'leave_type' => 'vacation',
            'starts_on' => '2026-09-15', 'ends_on' => '2026-09-16', 'days' => 2,
            'is_paid' => true, 'status' => 'approved',
        ]);

        $this->worker('Still Pending')->forceFill(['status' => Employee::STATUS_PENDING])->save();
        $this->worker('Long Gone')->forceFill(['status' => Employee::STATUS_ARCHIVED])->save();

        $this->at('2026-09-15 09:00:00');
        $html = $this->page(['view' => 'all'])->getContent();

        $this->assertStringContainsString('Working · AM', $this->row($html, 'In On Time'));

        $notYet = $this->row($html, 'Not Yet Here');
        $this->assertStringContainsString('Not in yet', $notYet);
        $this->assertStringContainsString('exp. 8:00 AM', $notYet);

        $night = $this->row($html, 'Night Later');
        $this->assertStringContainsString('Scheduled', $night);
        $this->assertStringContainsString('exp. 8:00 PM', $night);

        $this->assertStringContainsString('On leave', $this->row($html, 'Away On Leave'));

        $this->assertStringNotContainsString('Still Pending', $html);
        $this->assertStringNotContainsString('Long Gone', $html);

        // Two hours past the start with no scan is absent.
        $this->at('2026-09-15 10:30:00');
        $absent = $this->row($this->page(['view' => 'all'])->getContent(), 'Not Yet Here');
        $this->assertStringContainsString('Absent', $absent);
        $this->assertStringContainsString('No scan', $absent);
    }

    /** The roster follows the site, shift and search like everything else on the list. */
    public function test_the_roster_follows_the_filters(): void
    {
        $other = Site::firstOrCreate(['name' => 'Site B']);

        $this->worker('Here At A')->forceFill(['site_id' => $this->site->id])->save();
        $this->worker('Over At B')->forceFill(['site_id' => $other->id])->save();

        $html = $this->page(['view' => 'all', 'site' => $this->site->id])->getContent();
        $this->assertStringContainsString('<b>Here At A</b>', $html);
        $this->assertStringNotContainsString('<b>Over At B</b>', $html);

        $html = $this->page(['view' => 'all', 'q' => 'over'])->getContent();
        $this->assertStringContainsString('<b>Over At B</b>', $html);
        $this->assertStringNotContainsString('<b>Here At A</b>', $html);
    }

    /** Nobody who has not scanned is on any list but All. */
    public function test_only_all_lists_those_who_have_not_scanned(): void
    {
        $this->worker('Not Yet Here');

        foreach (['present', 'clocked-in', 'break', 'missed', 'done'] as $view) {
            $this->assertStringNotContainsString('<b>Not Yet Here</b>',
                $this->page(['view' => $view])->getContent(), $view);
        }
    }

    // ── History ──────────────────────────────────────────────────────────

    /**
     * History has no Working or On break — every day there is over — and
     * nothing to delete with: no Mark for Deletion, no checkboxes.
     */
    public function test_history_offers_only_what_applies_to_a_finished_day(): void
    {
        $this->stretch($this->worker('Past Day'), '2026-09-14', 'AM', '08:00:00', '17:00:00');

        $html = $this->page(['tab' => 'history'])->getContent();

        $this->assertMatchesRegularExpression('#<label data-today-only hidden>\s*<input type="radio" name="view" value="clocked-in"#', $html);
        $this->assertMatchesRegularExpression('#<label data-today-only hidden>\s*<input type="radio" name="view" value="break"#', $html);
        $this->assertStringNotContainsString('Mark for Deletion', $html);
        $this->assertStringNotContainsString('type="checkbox"', $html);

        // On today's tab they are there.
        $today = $this->page()->getContent();
        $this->assertMatchesRegularExpression('#<label data-today-only>\s*<input type="radio" name="view" value="clocked-in"#', $today);
    }

    /** A Working or On break carried over to History reads as everybody there, not as nobody. */
    public function test_history_reads_working_and_on_break_as_everybody(): void
    {
        $this->stretch($this->worker('Past Day'), '2026-09-14', 'AM', '08:00:00', '17:00:00');

        foreach (['clocked-in', 'break'] as $view) {
            $page = $this->actingAs($this->admin())
                ->get(route('attendance', ['tab' => 'history', 'view' => $view]))->assertOk();

            $this->assertSame(1, $page->viewData('historyAttendances')->total(), $view);
            $this->assertStringContainsString('name="view" value="all" checked', $page->getContent(), $view);
        }
    }

    // ── The timeline ─────────────────────────────────────────────────────

    /**
     * The times under the timeline are the shift's own, as Payroll Settings
     * has them — start, break, end — not the hour of room either side of it.
     * Changing the shift there changes them here.
     */
    public function test_the_timeline_is_labelled_with_the_shift_from_payroll_settings(): void
    {
        $this->stretch($this->worker('Tim Line'), '2026-09-14', 'AM', '08:00:00', '17:00:00');

        $labels = function () {
            $row = $this->row($this->page(['tab' => 'history'])->getContent(), 'Tim Line', 'historyTable');
            preg_match('#<div class="atm-tlax">(.*?)</div>#s', $row, $axis);
            preg_match_all('#<span class="is-(?:start|mid|end)"[^>]*>([^<]+)</span>#', $axis[1] ?? '', $m);

            return $m[1];
        };

        $this->assertSame(['8:00 AM', '12:00–1:00 PM', '5:00 PM'], $labels());

        // The office moves the shift in Payroll Settings.
        $this->day->forceFill(Shift::layOut('07:30', '16:30', '11:30', '12:30'))->save();

        $this->assertSame(['7:30 AM', '11:30 AM–12:30 PM', '4:30 PM'], $labels());
    }

    /**
     * A note under a scan may wrap to fit a narrow screen, but after its word
     * — "Undertime", then "1h 30m" — never inside the figure.
     */
    public function test_a_long_note_wraps_after_its_word_not_inside_the_figure(): void
    {
        $this->stretch($this->worker('Early Leaver'), '2026-09-14', 'AM', '08:00:00', '15:30:00');

        $row = $this->row($this->page(['tab' => 'history'])->getContent(), 'Early Leaver', 'historyTable');

        $this->assertStringContainsString("Undertime 1h\u{00A0}30m", $row);
    }

    // ── Hours are payroll's ──────────────────────────────────────────────

    /**
     * Four hours, four hours and one after five: eight regular and one of
     * overtime, written in whole minutes — the figure the payslip carries.
     */
    public function test_the_hours_are_what_payroll_makes_of_the_day(): void
    {
        $emp = $this->worker('Eve Overtime');
        $this->stretch($emp, '2026-09-14', 'AM', '08:00:00', '12:00:00');
        $this->stretch($emp, '2026-09-14', 'PM', '13:00:00', '18:00:00');

        $html = $this->page(['tab' => 'history'])->getContent();
        $row  = $this->row($html, 'Eve Overtime', 'historyTable');

        $this->assertMatchesRegularExpression('#8h 00m <small class="d-inline">reg</small>#', $row);
        $this->assertStringContainsString('+1h 00m OT', $row);
        $this->assertStringContainsString('9h 00m worked', $row);
        $this->assertStringNotContainsString('9.00', $row, 'never decimal hours');
    }

    // ── Filters ──────────────────────────────────────────────────────────

    public function test_the_search_narrows_both_lists_and_leaves_the_cards(): void
    {
        $this->stretch($this->worker('Ana Search'), '2026-09-15', 'AM', '08:00:00', null);
        $this->stretch($this->worker('Ben Other'),  '2026-09-15', 'AM', '08:00:00', null);
        $this->stretch(Employee::where('name', 'Ana Search')->first(), '2026-09-14', 'AM', '08:00:00', '17:00:00');
        $this->stretch(Employee::where('name', 'Ben Other')->first(),  '2026-09-14', 'AM', '08:00:00', '17:00:00');

        $page = $this->page(['q' => 'ana']);
        $html = $page->getContent();

        $this->assertSame(2, substr_count($html, '<b>Ana Search</b>'), 'today and history');
        $this->assertStringNotContainsString('<b>Ben Other</b>', $html);
        $this->assertSame(2, $page->viewData('presentToday'), 'the cards still count the whole crew');
    }

    public function test_the_status_control_narrows_the_lists(): void
    {
        $this->stretch($this->worker('On Site'), '2026-09-15', 'AM', '08:00:00', null);
        $this->stretch($this->worker('At Lunch'), '2026-09-15', 'AM', '07:00:00', '09:30:00');
        $this->day->forceFill(Shift::layOut('08:00', '17:00', '09:30', '11:00') + ['regular_minutes' => 480])->save();

        $html = $this->page(['view' => 'break'])->getContent();
        $this->assertStringContainsString('<b>At Lunch</b>', $html);
        $this->assertStringNotContainsString('<b>On Site</b>', $html);

        $html = $this->page(['view' => 'clocked-in'])->getContent();
        $this->assertStringContainsString('<b>On Site</b>', $html);
        $this->assertStringNotContainsString('<b>At Lunch</b>', $html);
    }

    /** Completed is about the whole day: nothing left open, nothing the system had to close. */
    public function test_completed_leaves_out_a_day_waiting_on_the_office(): void
    {
        $this->stretch($this->worker('Clean Day'), '2026-09-14', 'AM', '08:00:00', '17:00:00');
        $this->stretch($this->worker('Guessed Day'), '2026-09-14', 'AM', '08:00:00', '12:00:00',
            ['close_type' => 'auto', 'needs_review' => true, 'close_reason' => 'No time-out']);

        $done = $this->page(['tab' => 'history', 'view' => 'done'])->getContent();
        $this->assertStringContainsString('<b>Clean Day</b>', $done);
        $this->assertStringNotContainsString('<b>Guessed Day</b>', $done);

        $missed = $this->page(['tab' => 'history', 'view' => 'missed'])->getContent();
        $this->assertStringContainsString('<b>Guessed Day</b>', $missed);
        $this->assertStringNotContainsString('<b>Clean Day</b>', $missed);
    }

    // ── Settling a time out nobody scanned ───────────────────────────────

    public function test_a_guessed_time_out_is_offered_for_fixing(): void
    {
        $emp = $this->worker('Fay Forgot');
        $row = $this->stretch($emp, '2026-09-14', 'AM', '08:00:00', '12:00:00',
            ['close_type' => 'auto', 'needs_review' => true, 'close_reason' => 'No time-out']);

        $html = $this->page(['tab' => 'history'])->getContent();
        $tr   = $this->row($html, 'Fay Forgot', 'historyTable');

        $this->assertStringContainsString('No time out', $tr);
        $this->assertStringContainsString('Not scanned', $tr);
        $this->assertStringContainsString('data-fix="' . route('attendance.time-out', $row) . '"', $html);
        $this->assertStringContainsString('The system closed it at 12:00 PM', $html);
    }

    public function test_the_office_sets_the_time_out_and_it_is_logged(): void
    {
        $emp = $this->worker('Gil Fixed');
        $row = $this->stretch($emp, '2026-09-14', 'AM', '08:00:00', '12:00:00',
            ['close_type' => 'auto', 'needs_review' => true, 'close_reason' => 'No time-out']);

        $this->actingAs($this->admin())
             ->patchJson(route('attendance.time-out', $row), ['time' => '17:00'])
             ->assertOk()
             ->assertJson(['success' => true]);

        $row->refresh();
        $this->assertSame('2026-09-14 17:00:00', Carbon::parse($row->time_out)->format('Y-m-d H:i:s'));
        $this->assertSame('admin', $row->close_type);
        $this->assertFalse($row->needs_review);
        $this->assertSame($this->admin()->id, (int) $row->reviewed_by);

        $this->assertTrue(AuditLog::where('module', 'Attendance')->where('action', 'updated')
            ->where('description', 'like', '%Gil Fixed%5:00 PM%')->exists(), 'the change is on the audit log');

        $page = $this->page(['tab' => 'history']);
        $tr   = $this->row($page->getContent(), 'Gil Fixed', 'historyTable');

        $this->assertStringContainsString('Present', $tr);
        $this->assertStringContainsString('class="atm-edited"', $tr, 'marked as set by the office');
        $this->assertStringContainsString('8h 00m', $tr, 'and paid as the day it was, the break left out');
        $this->assertSame(0, $page->viewData('invalidCount'));
    }

    /** A night crew's time out the next morning is read as the next morning. */
    public function test_a_time_earlier_than_the_time_in_is_the_next_morning(): void
    {
        $night = Shift::where('crosses_midnight', true)->firstOrFail();
        $night->forceFill(Shift::layOut('20:00', '05:00', '00:00', '01:00') + ['regular_minutes' => 480])->save();

        $emp = $this->worker('Hal Night');
        $emp->forceFill(['shift_id' => $night->id])->save();

        $row = $this->stretch($emp, '2026-09-13', 'PM', '01:00:00', null);
        $row->forceFill(['time_in' => '2026-09-14 01:00:00'])->save();

        $this->actingAs($this->admin())
             ->patchJson(route('attendance.time-out', $row), ['time' => '05:00'])
             ->assertOk();

        $this->assertSame('2026-09-14 05:00:00', Carbon::parse($row->refresh()->time_out)->format('Y-m-d H:i:s'));
    }

    public function test_a_time_out_that_has_not_happened_yet_is_refused(): void
    {
        $row = $this->stretch($this->worker('Ivy Early'), '2026-09-15', 'AM', '08:00:00', null);

        $this->actingAs($this->admin())
             ->patchJson(route('attendance.time-out', $row), ['time' => '11:00'])
             ->assertStatus(422)
             ->assertJson(['success' => false]);

        $this->assertNull($row->refresh()->time_out);
    }

    public function test_a_time_out_may_not_run_into_the_next_stretch(): void
    {
        $emp = $this->worker('Jon Overlap');
        $am  = $this->stretch($emp, '2026-09-14', 'AM', '08:00:00', '12:00:00',
            ['close_type' => 'auto', 'needs_review' => true]);
        $this->stretch($emp, '2026-09-14', 'PM', '13:00:00', '17:00:00');

        $this->actingAs($this->admin())
             ->patchJson(route('attendance.time-out', $am), ['time' => '14:00'])
             ->assertStatus(422)
             ->assertJsonFragment(['success' => false]);

        $this->assertTrue($am->refresh()->needs_review, 'nothing was saved');
    }

    /** A time out the worker scanned is theirs; this page does not rewrite it. */
    public function test_a_scanned_time_out_is_not_rewritten(): void
    {
        $row = $this->stretch($this->worker('Kim Scanned'), '2026-09-14', 'AM', '08:00:00', '17:00:00');

        $this->actingAs($this->admin())
             ->patchJson(route('attendance.time-out', $row), ['time' => '18:00'])
             ->assertStatus(422);

        $this->assertSame('2026-09-14 17:00:00', Carbon::parse($row->refresh()->time_out)->format('Y-m-d H:i:s'));
    }
}
