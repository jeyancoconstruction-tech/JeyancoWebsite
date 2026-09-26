<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\Kiosk;
use App\Models\LaborType;
use App\Models\Shift;
use App\Models\SystemSetting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The kiosk without buttons: the worker only scans, and the web decides.
 *
 *   nothing open                         → TIME IN
 *   open, before the lunch cut (12:30)   → TIME OUT
 *   open morning, from the lunch cut on  → the morning closes as AUTO, PM IN
 *   the same worker again within 3 min   → nothing recorded
 *
 * Buttons stays the default, and while it is set the web refuses 'auto'.
 */
class KioskAutoModeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

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

    private function mode(string $mode, int $guard = 180): void
    {
        SystemSetting::current()->forceFill(['kiosk_attendance_mode' => $mode, 'kiosk_repeat_guard_seconds' => $guard])->save();
        SystemSetting::forget();
    }

    private function worker(string $name, bool $night = false, ?string $finger = null): Employee
    {
        $labor = LaborType::firstOrCreate(['name' => 'Laborer'], ['daily_rate' => 800, 'ot_rate' => 125]);
        $shift = Shift::where('crosses_midnight', $night)->orderBy('id')->firstOrFail();

        return Employee::create([
            'name'            => $name,
            'status'          => Employee::STATUS_ACTIVE,
            'employment_type' => Employee::EMPLOYMENT_DAILY,
            'labor_type_id'   => $labor->id,
            'shift_id'        => $shift->id,
            'site_id'         => Kiosk::query()->value('site_id'),
            'rate_per_hour'   => 100,
            'fingerprint_id'  => $finger,
        ]);
    }

    private function at(string $moment): void
    {
        Carbon::setTestNow(Carbon::parse($moment, 'Asia/Manila'));
    }

    private function scan(Employee $e)
    {
        return $this->postJson('/api/kiosk/attendance', ['employee_id' => $e->id, 'type' => 'auto'])->assertOk();
    }

    // ── The setting ─────────────────────────────────────────────────────────

    public function test_buttons_is_the_default_and_refuses_auto(): void
    {
        $this->assertSame('buttons', SystemSetting::current()->kioskMode());

        $e = $this->worker('Pedro Reyes');
        $this->at('2026-09-16 07:52:00');

        $this->scan($e)->assertJson(['success' => false, 'code' => 'mode_buttons']);
        $this->assertSame(0, Attendance::where('employee_id', $e->id)->count());
    }

    public function test_the_kiosk_reads_the_mode_and_the_day_from_settings(): void
    {
        $this->mode('auto', 120);

        $json = $this->getJson('/api/kiosk/settings?kiosk_code=SITE_A')->assertOk()->json('attendance');

        $this->assertSame('auto', $json['mode']);
        $this->assertSame(120, $json['repeat_guard_seconds']);
        $this->assertSame(60, $json['idle_return_seconds']);

        $day = collect($json['shifts'])->firstWhere('night', false);
        $this->assertSame(['08:00', '12:00', '13:00', '17:00', '12:30', '06:00'],
            [$day['am_start'], $day['am_end'], $day['pm_start'], $day['pm_end'], $day['cut'], $day['opens']]);

        $night = collect($json['shifts'])->firstWhere('night', true);
        $this->assertSame('00:30', $night['cut'], 'half past midnight for the night crew');

        $this->assertNotNull(Kiosk::where('code', 'SITE_A')->first()->settingsReadAt(), 'the read is remembered for Settings → Kiosk');
    }

    /**
     * The kiosk asks every few seconds with the version it holds. Nothing
     * changed: a tiny answer. The hours changed on the web: the new hours.
     */
    public function test_new_shift_hours_reach_the_kiosk_on_its_next_question(): void
    {
        $first = $this->getJson('/api/kiosk/settings?kiosk_code=SITE_A')->assertOk();
        $v     = $first->json('v');
        $this->assertNotEmpty($v);

        $this->getJson('/api/kiosk/settings?kiosk_code=SITE_A&v=' . $v)
            ->assertOk()
            ->assertExactJson(['success' => true, 'same' => true, 'v' => $v]);

        // The office moves the day crew to seven to four.
        $day = \App\Models\Shift::where('crosses_midnight', false)->firstOrFail();
        $day->update(\App\Models\Shift::layOut('07:00', '16:00', '12:00', '13:00'));

        $next = $this->getJson('/api/kiosk/settings?kiosk_code=SITE_A&v=' . $v)->assertOk();
        $this->assertNotSame($v, $next->json('v'), 'a new version');
        $this->assertNull($next->json('same'));

        $shift = collect($next->json('attendance.shifts'))->firstWhere('night', false);
        $this->assertSame(['07:00', '12:00', '13:00', '16:00', '05:00'],
            [$shift['am_start'], $shift['am_end'], $shift['pm_start'], $shift['pm_end'], $shift['opens']]);
    }

    // ── A day of scans ──────────────────────────────────────────────────────

    public function test_a_whole_day_is_four_scans(): void
    {
        $this->mode('auto');
        $e = $this->worker('Juan Dela Cruz');

        $this->at('2026-09-16 07:52:00');
        $this->scan($e)->assertJson(['success' => true, 'type' => 'time_in', 'session' => 'AM', 'auto' => true, 'paid_from' => '8:00 AM']);

        $this->at('2026-09-16 12:03:00');
        $this->scan($e)->assertJson(['success' => true, 'type' => 'time_out', 'session' => 'AM']);

        $this->at('2026-09-16 12:56:00');
        $this->scan($e)->assertJson(['success' => true, 'type' => 'time_in', 'session' => 'PM']);

        $this->at('2026-09-16 17:34:00');
        $out = $this->scan($e)->assertJson(['success' => true, 'type' => 'time_out', 'session' => 'PM'])->json();
        $this->assertArrayHasKey('ot_hours', $out, 'past five is overtime, as with the buttons');

        $rows = Attendance::where('employee_id', $e->id)->orderBy('id')->get();
        $this->assertCount(2, $rows);
        $this->assertNull($rows[0]->close_type);
        $this->assertNull($rows[1]->close_type);
    }

    public function test_a_scan_in_the_first_half_of_lunch_closes_the_morning(): void
    {
        $this->mode('auto');
        $e = $this->worker('Late Lunch');

        $this->at('2026-09-16 07:52:00');
        $this->scan($e);

        $this->at('2026-09-16 12:29:00');
        $this->scan($e)->assertJson(['success' => true, 'type' => 'time_out', 'session' => 'AM']);
    }

    public function test_from_the_cut_a_forgotten_morning_closes_as_auto(): void
    {
        $this->mode('auto');
        $e = $this->worker('Forgetful');

        $this->at('2026-09-16 07:52:00');
        $this->scan($e);

        $this->at('2026-09-16 12:41:00');
        $this->scan($e)->assertJson([
            'success' => true, 'type' => 'time_in', 'session' => 'PM',
            'auto_closed' => ['session' => 'AM', 'at' => '12:00 PM'],
        ]);

        $am = Attendance::where('employee_id', $e->id)->where('session', 'AM')->firstOrFail();
        $this->assertSame('auto', $am->close_type);
        $this->assertTrue($am->needs_review);
    }

    public function test_a_worker_who_never_scanned_out_for_lunch_can_still_go_home(): void
    {
        $this->mode('auto');
        $e = $this->worker('Worked Through');

        $this->at('2026-09-16 07:52:00');
        $this->scan($e);

        // TIME IN has closed for the day, so this can only be the way out.
        $this->at('2026-09-16 17:36:00');
        $this->scan($e)->assertJson(['success' => true, 'type' => 'time_out', 'session' => 'AM']);

        $this->assertNull(Attendance::where('employee_id', $e->id)->whereNull('time_out')->first(), 'nobody is left on site');
    }

    public function test_a_repeat_scan_within_the_guard_records_nothing(): void
    {
        $this->mode('auto', 180);
        $e = $this->worker('Twice');

        $this->at('2026-09-16 07:52:00');
        $this->scan($e);

        $this->at('2026-09-16 07:54:30');
        $this->scan($e)->assertJson(['success' => false, 'code' => 'repeat', 'last' => 'time_in', 'since' => '7:52 AM']);
        $this->assertSame(1, Attendance::where('employee_id', $e->id)->count());
        $this->assertNull(Attendance::where('employee_id', $e->id)->value('time_out'), 'the repeat did not become a time out');

        // Past the guard, the next scan is a real one.
        $this->at('2026-09-16 07:56:00');
        $this->scan($e)->assertJson(['success' => true, 'type' => 'time_out']);
    }

    public function test_the_guard_also_covers_a_time_out(): void
    {
        $this->mode('auto', 180);
        $e = $this->worker('Out Twice');

        $this->at('2026-09-16 07:52:00');
        $this->scan($e);
        $this->at('2026-09-16 12:02:00');
        $this->scan($e);

        $this->at('2026-09-16 12:03:00');
        $this->scan($e)->assertJson(['success' => false, 'code' => 'repeat', 'last' => 'time_out']);
        $this->assertSame(1, Attendance::where('employee_id', $e->id)->count(), 'no afternoon opened by the second touch');
    }

    public function test_the_shift_window_still_applies(): void
    {
        $this->mode('auto');
        $e = $this->worker('Evening Visitor');

        $this->at('2026-09-16 21:15:00');
        $this->scan($e)->assertJson(['success' => false, 'code' => 'wrong_shift']);
    }

    public function test_the_night_crew_cuts_at_half_past_midnight(): void
    {
        $this->mode('auto');
        $early = $this->worker('Night Early', true);
        $late  = $this->worker('Night Late', true);

        $this->at('2026-09-16 19:55:00');
        $this->scan($early);
        $this->scan($late);

        $this->at('2026-09-17 00:10:00');
        $this->scan($early)->assertJson(['success' => true, 'type' => 'time_out', 'session' => 'AM']);

        $this->at('2026-09-17 00:45:00');
        $this->scan($late)->assertJson(['success' => true, 'type' => 'time_in', 'session' => 'PM', 'auto_closed' => ['session' => 'AM']]);
    }

    public function test_the_clock_endpoint_takes_auto_by_fingerprint(): void
    {
        $this->mode('auto');
        $e = $this->worker('By Finger', false, '42');

        $this->at('2026-09-16 07:50:00');
        $this->postJson('/api/kiosk/clock', ['fingerprint_id' => '42', 'type' => 'auto'])
             ->assertOk()->assertJson(['success' => true, 'type' => 'time_in', 'auto' => true]);

        $this->assertSame(1, Attendance::where('employee_id', $e->id)->count());
    }

    // ── The board and its summary ───────────────────────────────────────────

    public function test_the_board_knows_lunch_not_back_late_and_counts_it(): void
    {
        $lunch = $this->worker('On Lunch');
        $late  = $this->worker('Came Late');

        $this->at('2026-09-16 07:50:00');
        $this->postJson('/api/kiosk/attendance', ['employee_id' => $lunch->id, 'type' => 'time_in']);
        $this->at('2026-09-16 08:40:00');
        $this->postJson('/api/kiosk/attendance', ['employee_id' => $late->id, 'type' => 'time_in']);
        $this->at('2026-09-16 12:01:00');
        $this->postJson('/api/kiosk/attendance', ['employee_id' => $lunch->id, 'type' => 'time_out']);

        $this->at('2026-09-16 12:30:00');
        $board = $this->getJson('/api/kiosk/today-attendance')->assertOk()->json();
        $row   = collect($board['records'])->firstWhere('employee_id', $lunch->id);
        $this->assertSame('lunch', $row['state']);
        $this->assertSame(1, $board['summary']['on_lunch']);
        $this->assertSame(1, $board['summary']['working']);
        $this->assertSame(1, $board['summary']['late']);
        $this->assertSame(2, $board['summary']['punches']['am_in']);
        $this->assertSame(1, $board['summary']['punches']['am_out']);
        $this->assertSame(['first' => '7:50 AM', 'last' => '8:40 AM'], $board['summary']['punches']['am_in_span']);

        $lateRow = collect($board['records'])->firstWhere('employee_id', $late->id);
        $this->assertGreaterThan(0, $lateRow['late_minutes']);
        $this->assertSame('working', $lateRow['state']);

        $this->at('2026-09-16 13:20:00');
        $row = collect($this->getJson('/api/kiosk/today-attendance')->json('records'))->firstWhere('employee_id', $lunch->id);
        $this->assertSame('notback', $row['state']);
    }

    // ── System Settings → Kiosk ─────────────────────────────────────────────

    private function admin(): User
    {
        return User::create([
            'name' => 'Aldrin Admin', 'username' => 'admin.kiosk', 'password' => Hash::make('secret123'),
            'role' => User::ROLE_ADMIN, 'is_active' => true,
        ]);
    }

    public function test_the_kiosk_tab_saves_and_is_logged_once(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('system-settings.kiosk'))
             ->assertOk()
             ->assertSee('Attendance mode')
             ->assertSee('How Automatic decides')
             ->assertSee('12:30 PM')
             ->assertSee('SITE_A')
             ->assertSee('Buttons · press, then scan');

        $this->actingAs($admin)->put(route('system-settings.kiosk.update'), [
            'kiosk_attendance_mode'      => 'auto',
            'kiosk_repeat_guard_seconds' => 120,
            'kiosk_idle_return_seconds'  => 60,
        ])->assertRedirect(route('system-settings.kiosk'))->assertSessionHasNoErrors();

        SystemSetting::forget();
        $this->assertSame('auto', SystemSetting::current()->kioskMode());
        $this->assertSame(120, SystemSetting::current()->kiosk_repeat_guard_seconds);

        $entries = AuditLog::where('module', 'Settings')->get();
        $this->assertCount(1, $entries);
        $this->assertSame('Kiosk: attendance mode Buttons → Automatic, repeat scans ignored within 180 → 120 s', $entries->first()->description);

        $this->actingAs($admin)->get(route('system-settings.kiosk'))
             ->assertOk()
             ->assertSee('Automatic · scan only')
             ->assertSee('by Aldrin Admin')
             ->assertSee('Kiosk: attendance mode Buttons → Automatic');
    }

    public function test_the_kiosk_tab_refuses_nonsense(): void
    {
        $this->actingAs($this->admin())->put(route('system-settings.kiosk.update'), [
            'kiosk_attendance_mode'      => 'magic',
            'kiosk_repeat_guard_seconds' => 5,
            'kiosk_idle_return_seconds'  => 60,
        ])->assertSessionHasErrors(['kiosk_attendance_mode', 'kiosk_repeat_guard_seconds']);

        SystemSetting::forget();
        $this->assertSame('buttons', SystemSetting::current()->kioskMode());
    }

    public function test_only_an_admin_opens_the_kiosk_tab(): void
    {
        $staff = User::create([
            'name' => 'Carlo Staff', 'username' => 'staff.kiosk', 'password' => Hash::make('secret123'),
            'role' => User::ROLE_STAFF, 'is_active' => true,
        ]);

        $this->actingAs($staff)->get(route('system-settings.kiosk'))->assertStatus(403);
    }
}
