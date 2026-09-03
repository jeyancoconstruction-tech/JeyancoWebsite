<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\LaborType;
use App\Models\PayrollRate;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\PayrollService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use ReflectionMethod;
use Tests\TestCase;

/**
 * The rest day is the seventh day of the working week.
 *
 * It used to be Sunday, written into the payroll math as a constant. That is
 * the right answer for a week that begins on Monday and the wrong one for any
 * other, and the office can set where its week begins — so the day the rest
 * premium falls on now follows that setting instead of being assumed.
 */
class RestDayFollowsWeekTest extends TestCase
{
    use RefreshDatabase;

    private function settings(int $weekStartsOn): SystemSetting
    {
        $s = SystemSetting::current();
        $s->forceFill(['week_starts_on' => $weekStartsOn])->save();
        SystemSetting::forget();

        return SystemSetting::current();
    }

    /** One worked day, computed under a given week start. */
    private function pay(string $date, int $weekStartsOn, ?bool $frozen = null): array
    {
        $system = $this->settings($weekStartsOn);

        $labor = LaborType::firstOrCreate(
            ['name' => 'Mason'],
            ['daily_rate' => 800, 'ot_rate' => 125]
        );

        $employee = Employee::create([
            'name'          => 'Juan Dela Cruz',
            'position'      => 'Mason',
            'labor_type_id' => $labor->id,
            'rate_per_hour' => 100,
            'status'        => Employee::STATUS_ACTIVE,
        ]);

        $rec = Attendance::create([
            'employee_id'      => $employee->id,
            'date'             => $date,
            'session'          => 'whole',
            'time_in'          => '08:00:00',
            'time_out'         => '16:00:00',
            'rest_day_applied' => $frozen,
        ])->load('employee.laborType');

        // config() is avoided: it reaches Holiday::typeMap(), whose MySQL
        // YEAR() has no answer under the SQLite this suite runs on.
        $m = new ReflectionMethod(PayrollService::class, 'computeRecord');
        $m->setAccessible(true);

        return $m->invoke(app(PayrollService::class), $rec, [
            'rateTimeline'   => PayrollRate::timeline(),
            'bonus'          => 0,
            'holidayTypeMap' => [],
            'restDayEnabled' => true,
            'restDayOn'      => $system->restDayOn(),
            'day'            => $system,
            'shifts'         => [],
        ]);
    }

    // ── The derivation ───────────────────────────────────────────────────────

    public function test_a_week_starting_monday_rests_on_sunday(): void
    {
        $s = $this->settings(Carbon::MONDAY);

        $this->assertSame(Carbon::SUNDAY, $s->restDayOn());
        $this->assertSame('Sunday', $s->restDayName());
    }

    public function test_a_week_starting_sunday_rests_on_saturday(): void
    {
        $s = $this->settings(Carbon::SUNDAY);

        $this->assertSame(Carbon::SATURDAY, $s->restDayOn());
        $this->assertSame('Saturday', $s->restDayName());
    }

    /** Every start lands on the day before it — the seventh of its own week. */
    public function test_every_week_start_rests_on_its_seventh_day(): void
    {
        foreach (range(0, 6) as $start) {
            $s = $this->settings($start);

            $this->assertSame(($start + 6) % 7, $s->restDayOn(),
                "a week starting on day {$start} rests on the day before it");
        }
    }

    // ── What it pays ─────────────────────────────────────────────────────────

    /**
     * 2026-09-13 is a Sunday and 2026-09-12 a Saturday. Under a Monday week
     * the premium falls on the Sunday; under a Sunday week it moves to the
     * Saturday, and the Sunday becomes an ordinary working day.
     */
    public function test_the_premium_falls_on_the_seventh_day(): void
    {
        $this->assertGreaterThan(0, $this->pay('2026-09-13', Carbon::MONDAY)['restDayPay'],
            'a Monday week rests on Sunday');

        $this->assertSame(0.0, round($this->pay('2026-09-12', Carbon::MONDAY)['restDayPay'], 2),
            'Saturday is an ordinary day when the week begins on Monday');

        $this->assertGreaterThan(0, $this->pay('2026-09-12', Carbon::SUNDAY)['restDayPay'],
            'a Sunday week rests on Saturday');

        $this->assertSame(0.0, round($this->pay('2026-09-13', Carbon::SUNDAY)['restDayPay'], 2),
            'and its Sunday is an ordinary working day');
    }

    /** Working the rest day is what earns the premium — the day alone does not. */
    public function test_the_premium_is_the_rest_day_multiplier(): void
    {
        $out = $this->pay('2026-09-13', Carbon::MONDAY);

        $this->assertTrue($out['onRestDay']);
        $this->assertEqualsWithDelta(
            $out['dayEarnings'] * ($out['rates']['rest_day_multiplier'] - 1),
            $out['restDayPay'],
            0.01
        );
    }

    /**
     * A day already settled keeps the answer it was settled under. Otherwise
     * changing where the week starts would reach back and re-price periods
     * that have already been paid out.
     */
    public function test_a_settled_day_does_not_move_with_the_week(): void
    {
        // Frozen as a rest day, then the week moves so it no longer is one.
        $this->assertGreaterThan(0, $this->pay('2026-09-13', Carbon::SUNDAY, true)['restDayPay'],
            'a day frozen as rest keeps its premium');

        // And the reverse: frozen as an ordinary day, and left ordinary.
        $this->assertSame(0.0, round($this->pay('2026-09-13', Carbon::MONDAY, false)['restDayPay'], 2),
            'a day frozen as ordinary does not gain one');
    }

    // ── Moving the week ──────────────────────────────────────────────────────

    private function admin(): User
    {
        return User::firstOrCreate(
            ['username' => 'admin.restday'],
            ['name' => 'Admin', 'password' => Hash::make('secret123'), 'role' => User::ROLE_ADMIN, 'is_active' => true]
        );
    }

    private function moveWeekTo(int $weekStartsOn): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin())
                    ->put(route('settings.attendance.update'), [
                        'standard_hours_per_day' => 9,
                        'unpaid_break_minutes'   => 60,
                        'week_starts_on'         => $weekStartsOn,
                        'payroll_cycle'          => 'weekly',
                    ]);
    }

    private function pastDay(string $date): Attendance
    {
        $labor = LaborType::firstOrCreate(
            ['name' => 'Mason'],
            ['daily_rate' => 800, 'ot_rate' => 125]
        );

        $employee = Employee::create([
            'name'          => 'Juan Dela Cruz',
            'labor_type_id' => $labor->id,
            'rate_per_hour' => 100,
            'status'        => Employee::STATUS_ACTIVE,
        ]);

        return Attendance::create([
            'employee_id' => $employee->id,
            'date'        => $date,
            'session'     => 'whole',
            'time_in'     => '08:00:00',
            'time_out'    => '16:00:00',
        ]);
    }

    /**
     * The endpoint has to survive the save, not just write the row.
     *
     * The freeze filters on the day of the week, and there is no portable SQL
     * for that — raw DAYOFWEEK threw on every test run here while the tests
     * went on passing, because nothing looked at the response status.
     */
    public function test_moving_the_week_does_not_break_the_save(): void
    {
        $this->settings(Carbon::MONDAY);
        $this->pastDay('2026-08-16');   // a Sunday, well before now

        $this->moveWeekTo(Carbon::SUNDAY)
             ->assertRedirect(route('settings.index', ['tab' => 'attendance']))
             ->assertSessionHasNoErrors();
    }

    /** A past rest day keeps its premium when the week moves out from under it. */
    public function test_a_past_rest_day_is_written_down_before_the_week_moves(): void
    {
        $this->settings(Carbon::MONDAY);
        $sunday = $this->pastDay('2026-08-16');

        $this->assertNull($sunday->rest_day_applied, 'nothing settled yet');

        $this->moveWeekTo(Carbon::SUNDAY)->assertRedirect();

        $this->assertTrue((bool) $sunday->fresh()->rest_day_applied,
            'the Sunday that was the rest day keeps it');
    }

    /** And a past ordinary day does not gain one it was never paid. */
    public function test_a_past_ordinary_day_does_not_become_a_rest_day(): void
    {
        $this->settings(Carbon::MONDAY);
        $saturday = $this->pastDay('2026-08-15');

        $this->moveWeekTo(Carbon::SUNDAY)->assertRedirect();

        $this->assertFalse((bool) $saturday->fresh()->rest_day_applied,
            'the Saturday about to become the rest day is marked as not one');
    }

    // ── On screen ────────────────────────────────────────────────────────────

    /** The office should not have to work out which day it just chose. */
    public function test_the_settings_page_names_the_rest_day(): void
    {
        $this->settings(Carbon::SUNDAY);

        $this->actingAs($this->admin())
             ->get(route('settings.index', ['tab' => 'attendance']))
             ->assertOk()
             ->assertSee('Saturday');
    }
}
