<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\LaborType;
use App\Models\PayrollRate;
use App\Models\Shift;
use App\Models\Site;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\PayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Needs review, taken back or taken away (Michael, 2026-10-03).
 *
 * A time out the office settled from "Fix missing scans" can be undone: the
 * stretch goes back to exactly what it was — the system's guess, waiting in
 * Needs review, or no time out at all. And a day still waiting in Needs
 * review can be deleted outright, every scan of it, after asking.
 *
 * The day shift: in at eight, lunch twelve to one, home at five.
 */
class AttendanceUndoAndDeleteTest extends TestCase
{
    use RefreshDatabase;

    private Shift $day;
    private Site $site;
    private ?User $admin = null;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-15 10:00:00', 'Asia/Manila'));
        SystemSetting::current()->forceFill(['schedule_rules_from' => '2026-09-01'])->save();

        $this->site = Site::firstOrCreate(['name' => 'Site A']);
        $this->day  = Shift::where('crosses_midnight', false)->firstOrFail();
        $this->day->forceFill(Shift::layOut('08:00', '17:00', '12:00', '13:00')
            + ['regular_minutes' => 480, 'grace_period_minutes' => 10])->save();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function admin(): User
    {
        return $this->admin ??= User::create([
            'name' => 'Office Admin', 'username' => 'admin.undo', 'password' => Hash::make('secret123'),
            'is_admin' => true, 'is_active' => true,
        ]);
    }

    private function worker(string $name): Employee
    {
        return Employee::create([
            'name'          => $name,
            'position'      => 'Mason',
            'status'        => Employee::STATUS_ACTIVE,
            'labor_type_id' => LaborType::firstOrCreate(['name' => 'Mason'], ['daily_rate' => 800, 'ot_rate' => 125])->id,
            'shift_id'      => $this->day->id,
            'rate_per_hour' => 100,
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

    private function guessed(Employee $e, string $out = '17:00:00'): Attendance
    {
        return $this->stretch($e, '2026-09-14', 'AM', '08:00:00', $out, [
            'close_type' => 'auto', 'needs_review' => true, 'close_reason' => 'No time-out — closed at the end of the session',
        ]);
    }

    private function history(): string
    {
        return $this->actingAs($this->admin())
            ->get(route('attendance', ['tab' => 'history', 'view' => 'all']))->assertOk()->getContent();
    }

    private function out(Attendance $row): ?string
    {
        $row->refresh();

        return $row->time_out ? Carbon::parse($row->time_out)->format('Y-m-d H:i:s') : null;
    }

    // ── Undo ─────────────────────────────────────────────────────────────

    public function test_a_settled_guess_is_undone_back_to_the_guess(): void
    {
        $row = $this->guessed($this->worker('Gil Guessed'));

        $this->actingAs($this->admin())->patchJson(route('attendance.time-out', $row), ['time' => '16:30'])->assertOk();
        $this->assertSame('admin', $row->refresh()->close_type);

        // Settled: the drawer offers Undo.
        $this->assertStringContainsString('data-undo-out="' . route('attendance.time-out.undo', $row) . '"', $this->history());

        $this->actingAs($this->admin())
             ->patchJson(route('attendance.time-out.undo', $row))
             ->assertOk()
             ->assertJson(['success' => true]);

        $this->assertSame('2026-09-14 17:00:00', $this->out($row), 'the time the system guessed');
        $this->assertSame('auto', $row->close_type);
        $this->assertTrue($row->needs_review, 'back in Needs review');
        $this->assertSame('No time-out — closed at the end of the session', $row->close_reason);
        $this->assertNull($row->reviewed_by);
        $this->assertNull($row->settled_from);

        $this->assertTrue(AuditLog::where('module', 'Attendance')
            ->where('description', 'like', 'Undid the time out set for Gil Guessed%4:30 PM%Needs review%')->exists());

        // And the fix is offered again, with no Undo.
        $html = $this->history();
        $this->assertStringContainsString('data-fix="' . route('attendance.time-out', $row) . '"', $html);
        $this->assertStringNotContainsString('data-undo-out=', $html);
    }

    /** Undone, the day is held out of payroll again until it is settled. */
    public function test_an_undone_day_is_held_from_payroll_again(): void
    {
        PayrollRate::create(array_merge(PayrollRate::DEFAULTS, ['effective_from' => '2026-01-01', 'created_by' => 'test']));
        $emp = $this->worker('Hana Held');
        $row = $this->guessed($emp);

        $held = fn () => app(PayrollService::class)->computeForRange('2026-09-14', '2026-09-14')['held'][$emp->id] ?? [];

        $this->assertNotEmpty($held(), 'held while in review');

        $this->actingAs($this->admin())->patchJson(route('attendance.time-out', $row), ['time' => '17:00'])->assertOk();
        $this->assertEmpty($held(), 'paid once settled');

        $this->actingAs($this->admin())->patchJson(route('attendance.time-out.undo', $row))->assertOk();
        $this->assertNotEmpty($held(), 'held again once undone');
    }

    /** A stretch settled before the system closed it goes back to having no time out. */
    public function test_a_settled_open_stretch_is_undone_back_to_open(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-14 19:00:00', 'Asia/Manila'));
        $row = $this->stretch($this->worker('Ola Open'), '2026-09-14', 'AM', '08:00:00', null);

        $this->actingAs($this->admin())->patchJson(route('attendance.time-out', $row), ['time' => '17:00'])->assertOk();
        $this->actingAs($this->admin())->patchJson(route('attendance.time-out.undo', $row))->assertOk();

        $this->assertNull($this->out($row));
        $this->assertNull($row->close_type);
        $this->assertFalse($row->needs_review);
        $this->assertTrue($row->signOutOverdue(), 'still waiting on the office');
    }

    /**
     * Settled before anything was kept to go back to: the system's guess is
     * made again — the end of the session, never past the next time in.
     */
    public function test_an_older_settled_time_out_is_undone_to_the_systems_guess(): void
    {
        $emp = $this->worker('Lea Legacy');
        $am  = $this->stretch($emp, '2026-09-14', 'AM', '08:00:00', '11:00:00', [
            'close_type' => 'admin', 'needs_review' => false, 'close_reason' => 'No time-out — closed at the end of the session',
        ]);
        $this->stretch($emp, '2026-09-14', 'PM', '13:00:00', '17:00:00');

        $this->actingAs($this->admin())->patchJson(route('attendance.time-out.undo', $am))->assertOk();

        $this->assertSame('2026-09-14 12:00:00', $this->out($am), 'the end of the 1st session');
        $this->assertSame('auto', $am->close_type);
        $this->assertTrue($am->needs_review);
    }

    public function test_a_scanned_time_out_has_nothing_to_undo(): void
    {
        $row = $this->stretch($this->worker('Sam Scanned'), '2026-09-14', 'AM', '08:00:00', '17:00:00');

        $this->actingAs($this->admin())
             ->patchJson(route('attendance.time-out.undo', $row))
             ->assertStatus(422)
             ->assertJson(['success' => false]);

        $this->assertSame('2026-09-14 17:00:00', $this->out($row));
        $this->assertNull($row->close_type);
    }

    // ── Delete ───────────────────────────────────────────────────────────

    public function test_a_day_in_review_is_deleted_whole(): void
    {
        $emp   = $this->worker('Del Mistake');
        $am    = $this->stretch($emp, '2026-09-14', 'AM', '08:00:00', '12:00:00');
        $pm    = $this->stretch($emp, '2026-09-14', 'PM', '13:00:00', '17:00:00', [
            'close_type' => 'auto', 'needs_review' => true, 'close_reason' => 'No time-out',
        ]);
        $other = $this->stretch($emp, '2026-09-13', 'AM', '08:00:00', '17:00:00');
        $peer  = $this->stretch($this->worker('Pia Peer'), '2026-09-14', 'AM', '08:00:00', '17:00:00');

        $this->assertStringContainsString('data-delete-day="' . route('attendance.day.destroy', $pm) . '"', $this->history());

        $this->actingAs($this->admin())
             ->deleteJson(route('attendance.day.destroy', $pm))
             ->assertOk()
             ->assertJson(['success' => true]);

        $this->assertNull(Attendance::find($am->id), 'every scan of the day');
        $this->assertNull(Attendance::find($pm->id));
        $this->assertNotNull(Attendance::find($other->id), 'not the day before');
        $this->assertNotNull(Attendance::find($peer->id), 'not another worker');

        $this->assertTrue(AuditLog::where('module', 'Attendance')->where('action', 'deleted')
            ->where('description', 'like', "Deleted Del Mistake's 09/14/2026 from Needs review (8:00 AM – 12:00 PM, 1:00 PM – 5:00 PM (guessed))")->exists());

        $review = $this->actingAs($this->admin())->get(route('attendance', ['tab' => 'history', 'view' => 'missed']))->getContent();
        $this->assertStringNotContainsString('<b>Del Mistake</b>', $review, 'gone from Needs review');
    }

    /** A day left open past its shift waits on the office too, and may go. */
    public function test_an_open_overdue_day_may_be_deleted(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-14 19:00:00', 'Asia/Manila'));
        $row = $this->stretch($this->worker('Ozzy Overdue'), '2026-09-14', 'AM', '08:00:00', null);

        $this->actingAs($this->admin())->deleteJson(route('attendance.day.destroy', $row))->assertOk();

        $this->assertNull(Attendance::find($row->id));
    }

    /** A finished or settled day is not deleted from here. */
    public function test_a_day_not_in_review_is_not_deleted(): void
    {
        $clean   = $this->stretch($this->worker('Cy Clean'), '2026-09-14', 'AM', '08:00:00', '17:00:00');
        $settled = $this->guessed($this->worker('Sy Settled'));
        $this->actingAs($this->admin())->patchJson(route('attendance.time-out', $settled), ['time' => '17:00'])->assertOk();

        foreach ([$clean, $settled] as $row) {
            $this->actingAs($this->admin())
                 ->deleteJson(route('attendance.day.destroy', $row))
                 ->assertStatus(422)
                 ->assertJson(['success' => false]);

            $this->assertNotNull(Attendance::find($row->id));
        }

        $this->assertStringNotContainsString('data-delete-day=', $this->history());
    }
}
