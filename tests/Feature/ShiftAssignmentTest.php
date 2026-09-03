<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Kiosk;
use App\Models\LaborType;
use App\Models\Shift;
use App\Models\SystemSetting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Putting a worker on a crew.
 *
 * The shifts arrived with nobody on them: every worker had a null shift, both
 * settings cards read "0 workers", and payroll quietly fell back to the office
 * default for the whole workforce. A tag nobody has applied and nothing shows
 * is the same as no tag at all, so this covers the applying and the showing.
 */
class ShiftAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::firstOrCreate(
            ['username' => 'admin.shift'],
            ['name' => 'Admin', 'password' => Hash::make('secret123'), 'role' => User::ROLE_ADMIN, 'is_active' => true]
        );
    }

    private function worker(string $name, ?int $shiftId = null): Employee
    {
        $labor = LaborType::firstOrCreate(
            ['name' => 'Mason ' . $name],
            ['daily_rate' => 900, 'ot_rate' => 140]
        );

        return Employee::create([
            'name'            => $name,
            'status'          => Employee::STATUS_ACTIVE,
            'employment_type' => Employee::EMPLOYMENT_DAILY,
            'labor_type_id'   => $labor->id,
            'shift_id'        => $shiftId,
            'site_id'         => $this->kioskSite(),
            'rate_per_hour'   => 112.5,
        ]);
    }

    /**
     * Where the kiosk is standing.
     *
     * Both kiosk endpoints scope to the device's own site — without this the
     * Site B board listed everyone who clocked in anywhere. A worker filed
     * under no site is invisible to them, so the fixtures have to stand where
     * the kiosk does.
     */
    private function kioskSite(): ?int
    {
        return Kiosk::query()->value('site_id');
    }

    private function day(): Shift
    {
        return Shift::where('crosses_midnight', false)->orderBy('id')->firstOrFail();
    }

    private function night(): Shift
    {
        return Shift::where('crosses_midnight', true)->firstOrFail();
    }

    // ── Assigning ────────────────────────────────────────────────────────────

    public function test_a_worker_is_moved_between_shifts(): void
    {
        $emp = $this->worker('Juan', $this->day()->id);

        $this->actingAs($this->admin())
             ->patch(route('employees.shift', $emp), ['shift_id' => $this->night()->id])
             ->assertOk()
             ->assertJson(['success' => true, 'name' => $this->night()->name]);

        $this->assertSame($this->night()->id, $emp->fresh()->shift_id);
    }

    /** A shift that does not exist is a typo or a stale page, not an assignment. */
    public function test_an_unknown_shift_is_refused(): void
    {
        $emp = $this->worker('Pedro', $this->day()->id);

        $this->actingAs($this->admin())
             ->patchJson(route('employees.shift', $emp), ['shift_id' => 9999])
             ->assertStatus(422);

        $this->assertSame($this->day()->id, $emp->fresh()->shift_id);
    }

    public function test_a_guest_cannot_move_anybody(): void
    {
        $emp = $this->worker('Maria', $this->day()->id);

        $this->patch(route('employees.shift', $emp), ['shift_id' => $this->night()->id])
             ->assertRedirect();

        $this->assertSame($this->day()->id, $emp->fresh()->shift_id);
    }

    /**
     * Moving somebody takes effect from the next day worked. Every day already
     * recorded keeps the shift it was stamped with, so last month's arrivals do
     * not become late because somebody joined the night crew today.
     */
    public function test_moving_a_worker_leaves_recorded_days_alone(): void
    {
        $emp = $this->worker('Ramon', $this->day()->id);

        $rec = Attendance::create([
            'employee_id' => $emp->id,
            'site_id'     => $this->kioskSite(),
            'date'        => '2026-09-07',
            'time_in'     => '2026-09-07 06:00:00',
            'time_out'    => '2026-09-07 15:00:00',
        ]);

        $this->assertSame($this->day()->id, $rec->shift_id);

        $this->actingAs($this->admin())
             ->patch(route('employees.shift', $emp), ['shift_id' => $this->night()->id])
             ->assertOk();

        $this->assertSame($this->day()->id, $rec->fresh()->shift_id,
            'a day already worked keeps the shift it was worked under');
    }

    // ── Nobody arrives untagged ──────────────────────────────────────────────

    /** A worker registered on the web with no shift chosen still lands on one. */
    public function test_a_new_worker_defaults_to_the_day_shift(): void
    {
        $labor = LaborType::create(['name' => 'Welder', 'daily_rate' => 900, 'ot_rate' => 140]);

        $this->actingAs($this->admin())
             ->post(route('employees.store'), [
                 'name'            => 'Bagong Tao',
                 'employment_type' => Employee::EMPLOYMENT_DAILY,
                 'labor_type_id'   => $labor->id,
                 'rate_per_hour'   => 112.5,
             ])
             ->assertSessionHasNoErrors();

        $this->assertSame($this->day()->id, Employee::where('name', 'Bagong Tao')->value('shift_id'));
    }

    public function test_the_default_is_the_shift_that_does_not_cross_midnight(): void
    {
        $this->assertSame($this->day()->id, Shift::defaultForNewHire());
    }

    // ── Showing it ───────────────────────────────────────────────────────────

    /** A tag nothing displays is a tag nobody can act on. */
    public function test_the_directory_shows_the_shift(): void
    {
        $this->worker('Nakikita', $this->night()->id);

        $this->actingAs($this->admin())
             ->get(route('employees.index'))
             ->assertOk()
             ->assertSee('emp-shift')
             ->assertSee('shiftFilter')
             ->assertSee($this->night()->name);
    }

    public function test_the_profile_shows_the_shift(): void
    {
        $emp = $this->worker('Profile', $this->night()->id);

        $this->actingAs($this->admin())
             ->get(route('employees.show', $emp))
             ->assertOk()
             ->assertSee($this->night()->name);
    }

    /**
     * The kiosk is a separate application — it can only know about the crews
     * if the API names them.
     */
    public function test_the_kiosk_roster_names_the_shift(): void
    {
        $emp = $this->worker('Kiosk', $this->night()->id);

        $this->getJson('/api/kiosk/roster')
             ->assertOk()
             ->assertJsonFragment(['crosses_midnight' => true])
             ->assertJsonFragment(['id' => $emp->id, 'name' => $emp->name]);
    }

    // ── The board ────────────────────────────────────────────────────────────

    /**
     * A night shift clocks in the evening before and is still on site at one in
     * the morning, on a row dated yesterday. The board filtered on today's date
     * alone, so it went blank on exactly the crew still working.
     */
    public function test_the_board_shows_a_night_shift_still_on_site(): void
    {
        $emp = $this->worker('Gabi', $this->night()->id);

        Carbon::setTestNow(Carbon::parse('2026-09-07 18:00:00', 'Asia/Manila'));
        Attendance::create([
            'employee_id' => $emp->id,
            'site_id'     => $this->kioskSite(),
            'date'        => '2026-09-07',
            'time_in'     => Carbon::now(),
        ]);

        Carbon::setTestNow(Carbon::parse('2026-09-08 01:00:00', 'Asia/Manila'));

        $this->getJson('/api/kiosk/today-attendance')
             ->assertOk()
             ->assertJsonFragment(['employee_id' => $emp->id, 'status' => 'working']);
    }

    /** Overtime on the board starts where payroll says it does. */
    public function test_the_board_counts_overtime_from_the_paid_standard(): void
    {
        $s = SystemSetting::current();
        $s->forceFill(['standard_hours_per_day' => 10, 'unpaid_break_minutes' => 60])->save();
        SystemSetting::forget();

        $emp = $this->worker('Araw', $this->day()->id);

        Carbon::setTestNow(Carbon::parse('2026-09-07 16:00:00', 'Asia/Manila'));
        Attendance::create([
            'employee_id' => $emp->id,
            'site_id'     => $this->kioskSite(),
            'date'        => '2026-09-07',
            'time_in'     => Carbon::parse('2026-09-07 06:00:00', 'Asia/Manila'),
            'time_out'    => Carbon::parse('2026-09-07 16:00:00', 'Asia/Manila'),
        ]);

        // Ten hours on site against a paid standard of nine: one hour over.
        // The old hardcoded 8 would have called this two.
        $this->getJson('/api/kiosk/today-attendance')
             ->assertOk()
             ->assertJsonFragment(['employee_id' => $emp->id, 'overtime_hours' => 1.0]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
