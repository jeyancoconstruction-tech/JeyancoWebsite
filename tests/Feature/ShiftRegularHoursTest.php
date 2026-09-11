<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\LaborType;
use App\Models\Shift;
use App\Models\SystemSetting;
use App\Models\User;
use App\Support\WorkSchedule;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Where overtime begins inside a shift.
 *
 * A shift used to be regular for the whole of its two sessions, with
 * overtime only after it ended. That describes an eight-to-five day and
 * stops describing anything else: put a crew on from eight in the morning
 * to eight at night and they are eleven paid hours, every one of them at
 * the plain rate, with overtime unreachable until they had been on site for
 * twelve.
 *
 * A shift now says how many of its paid hours the daily rate buys. Past
 * that figure the time is overtime, whether or not the shift has ended —
 * and the changeover is counted in paid time, so the unpaid break does not
 * drag it earlier in the day.
 */
class ShiftRegularHoursTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        SystemSetting::current()->forceFill(['schedule_rules_from' => '2026-09-01'])->save();
    }

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin', 'username' => 'admin.regular', 'password' => 'secret123',
            'role' => User::ROLE_ADMIN, 'is_admin' => true, 'is_active' => true,
        ]);
    }

    private function day(): Shift
    {
        return Shift::where('crosses_midnight', false)->firstOrFail();
    }

    private function night(): Shift
    {
        return Shift::where('crosses_midnight', true)->firstOrFail();
    }

    private function save(array $override)
    {
        $shifts = [];
        foreach (Shift::orderBy('id')->get() as $s) {
            $start   = substr((string) $s->starts_at, 0, 5);
            [$f, $t] = Shift::breakOffsets($start, $s->breakStartsAt(), $s->breakEndsAt());
            $paid    = (Shift::spanMinutes($start, $s->endsAt()) - ($t - $f)) / 60;

            $shifts[$s->id] = [
                'starts_at'            => $start,
                'ends_at'              => $s->endsAt(),
                'break_from'           => $s->breakStartsAt(),
                'break_to'             => $s->breakEndsAt(),
                'regular_hours'        => min($s->regularHours() ?? $paid, $paid),
                'grace_period_minutes' => $s->grace_period_minutes,
            ];
        }

        foreach ($override as $id => $fields) {
            $shifts[$id] = array_merge($shifts[$id], $fields);
        }

        return $this->actingAs($this->admin())->put(route('settings.attendance.update'), [
            'auto_count_overtime'    => 1,
            'week_starts_on'         => 1,
            'payroll_cycle'          => 'weekly',
            'shifts'                 => $shifts,
        ]);
    }

    private function worker(Shift $shift): Employee
    {
        return Employee::create([
            'name' => 'Crew', 'status' => Employee::STATUS_ACTIVE,
            'employment_type' => Employee::EMPLOYMENT_DAILY,
            'labor_type_id' => LaborType::create(['name' => 'Mason', 'daily_rate' => 800, 'ot_rate' => 125])->id,
            'shift_id' => $shift->id, 'rate_per_hour' => 100,
        ]);
    }

    private function clock(Employee $e, string $type, string $at): array
    {
        Carbon::setTestNow(Carbon::parse($at, 'Asia/Manila'));

        return $this->postJson('/api/kiosk/attendance', ['employee_id' => $e->id, 'type' => $type])
                    ->assertOk()->assertJson(['success' => true])->json();
    }

    private function paidOn(string $date): array
    {
        return app(\App\Services\PayrollService::class)
            ->computeForRange($date, $date)['days'][0]['details'][0];
    }

    public function test_the_card_offers_the_figure(): void
    {
        $this->actingAs($this->admin())
             ->get(route('settings.index', ['tab' => 'attendance']))
             ->assertOk()
             ->assertSee('shifts[' . $this->day()->id . '][regular_hours]', false)
             ->assertSee('Regular (hrs)');
    }

    /** Eight in the morning to eight at night, paying eight of its eleven hours. */
    public function test_the_hours_past_the_figure_are_overtime_before_the_shift_ends(): void
    {
        $this->save([$this->day()->id => [
            'starts_at' => '08:00', 'ends_at' => '20:00', 'regular_hours' => 8,
        ]])->assertSessionHasNoErrors();

        $emp = $this->worker($this->day()->fresh());
        $this->clock($emp, 'time_in', '2026-09-11 08:00:00');
        $out = $this->clock($emp, 'time_out', '2026-09-11 20:00:00');

        $paid = $this->paidOn('2026-09-11');

        $this->assertEqualsWithDelta(8.0, $paid['hours'] - $paid['ot_hours'], 0.01, 'the day buys eight');
        $this->assertEqualsWithDelta(3.0, $paid['ot_hours'], 0.01, 'the other three are overtime');

        // Eight PAID hours from eight in the morning, with an hour of it
        // unpaid in the middle, lands at five — not at four.
        $this->assertSame('5:00 PM', $out['ot_from']);
    }

    /** The divisor for the hourly rate is what the day buys, not the whole shift. */
    public function test_the_daily_rate_buys_the_regular_hours(): void
    {
        $this->save([$this->day()->id => [
            'starts_at' => '08:00', 'ends_at' => '20:00', 'regular_hours' => 8,
        ]])->assertSessionHasNoErrors();

        $schedule = $this->day()->fresh()->schedule();

        $this->assertEqualsWithDelta(8.0, WorkSchedule::paidHours($schedule), 0.01);

        $emp = $this->worker($this->day()->fresh());
        $this->clock($emp, 'time_in', '2026-09-11 08:00:00');
        $this->clock($emp, 'time_out', '2026-09-11 20:00:00');

        $paid = $this->paidOn('2026-09-11');

        $this->assertEqualsWithDelta(100.0, $paid['rate'], 0.01, '800 a day over eight hours');
        $this->assertEqualsWithDelta(800.0, $paid['basicPay'], 0.01);
        $this->assertEqualsWithDelta(375.0, $paid['otPay'], 0.01, 'three hours at 125');
    }

    public function test_leaving_before_the_figure_earns_no_overtime(): void
    {
        $this->save([$this->day()->id => [
            'starts_at' => '08:00', 'ends_at' => '20:00', 'regular_hours' => 8,
        ]])->assertSessionHasNoErrors();

        $emp = $this->worker($this->day()->fresh());
        $this->clock($emp, 'time_in', '2026-09-11 08:00:00');
        $this->clock($emp, 'time_out', '2026-09-11 16:00:00');

        $paid = $this->paidOn('2026-09-11');

        $this->assertEqualsWithDelta(7.0, $paid['hours'], 0.01, 'eight to four less the break');
        $this->assertEqualsWithDelta(0.0, $paid['ot_hours'], 0.01);
    }

    /** The same rule on a shift whose hours run over midnight. */
    public function test_it_holds_for_the_night_crew(): void
    {
        $this->save([$this->night()->id => [
            'starts_at' => '20:00', 'ends_at' => '08:00', 'regular_hours' => 8,
        ]])->assertSessionHasNoErrors();

        $emp = $this->worker($this->night()->fresh());
        $this->clock($emp, 'time_in', '2026-09-11 20:00:00');
        $out = $this->clock($emp, 'time_out', '2026-09-12 08:00:00');

        $paid = $this->paidOn('2026-09-11');

        $this->assertEqualsWithDelta(8.0, $paid['hours'] - $paid['ot_hours'], 0.01);
        $this->assertEqualsWithDelta(3.0, $paid['ot_hours'], 0.01);
        $this->assertSame('5:00 AM', $out['ot_from']);
    }

    /** A day cannot buy more hours than the shift holds. */
    public function test_more_regular_hours_than_the_shift_has_is_refused(): void
    {
        $this->save([$this->day()->id => [
            'starts_at' => '08:00', 'ends_at' => '17:00', 'regular_hours' => 10,
        ]])->assertSessionHasErrors();
    }

    /** Leaving it at the full span is the old behaviour, unchanged. */
    public function test_a_shift_that_buys_all_of_itself_has_no_overtime_until_it_ends(): void
    {
        $this->save([$this->day()->id => [
            'starts_at' => '08:00', 'ends_at' => '20:00', 'regular_hours' => 11,
        ]])->assertSessionHasNoErrors();

        $emp = $this->worker($this->day()->fresh());
        $this->clock($emp, 'time_in', '2026-09-11 08:00:00');
        $this->clock($emp, 'time_out', '2026-09-11 20:00:00');

        $paid = $this->paidOn('2026-09-11');

        $this->assertEqualsWithDelta(11.0, $paid['hours'], 0.01);
        $this->assertEqualsWithDelta(0.0, $paid['ot_hours'], 0.01);
    }

    /**
     * The rule cuts both ways, and this is the half that is easy to miss.
     *
     * Somebody who starts at half past one and leaves at nine has worked
     * under the eight hours the day buys, so none of it is overtime —
     * including the hour after the shift ended. Overtime used to be anything
     * past the end of the shift, which paid a premium to a worker who had
     * not put in a full day.
     *
     * And nothing is taken off for lunch, because lunch was at noon and she
     * was not there for it. A meal period is an hour of the day, not an hour
     * off everybody's total.
     */
    public function test_a_short_day_running_past_the_shift_is_still_not_overtime(): void
    {
        $this->save([$this->day()->id => [
            'starts_at' => '08:00', 'ends_at' => '20:00',
            'break_from' => '12:00', 'break_to' => '13:00', 'regular_hours' => 8,
        ]])->assertSessionHasNoErrors();

        $emp = $this->worker($this->day()->fresh());
        $this->clock($emp, 'time_in', '2026-09-11 13:32:00');
        $this->clock($emp, 'time_out', '2026-09-11 21:00:00');

        $paid = $this->paidOn('2026-09-11');

        $this->assertEqualsWithDelta(7.47, $paid['hours'], 0.01,
            'half past one to nine, whole — she arrived after lunch');
        $this->assertEqualsWithDelta(0.0, $paid['ot_hours'], 0.01,
            'under eight hours is not a full day, so nothing in it is overtime');
    }

    /** Somebody who IS there for lunch loses exactly that hour. */
    public function test_the_break_is_taken_off_only_those_who_were_there_for_it(): void
    {
        $this->save([$this->day()->id => [
            'starts_at' => '08:00', 'ends_at' => '20:00',
            'break_from' => '12:00', 'break_to' => '13:00', 'regular_hours' => 8,
        ]])->assertSessionHasNoErrors();

        $early = $this->worker($this->day()->fresh());
        $this->clock($early, 'time_in', '2026-09-11 08:00:00');
        $this->clock($early, 'time_out', '2026-09-11 16:00:00');

        // Eight to four is eight hours on site; one of them was lunch.
        $this->assertEqualsWithDelta(7.0, $this->paidOn('2026-09-11')['hours'], 0.01);
    }

    /** The hour past the shift is still paid — at the plain rate. */
    public function test_that_hour_is_paid_as_ordinary_time(): void
    {
        $this->save([$this->day()->id => [
            'starts_at' => '08:00', 'ends_at' => '20:00',
            'break_from' => '12:00', 'break_to' => '13:00', 'regular_hours' => 8,
        ]])->assertSessionHasNoErrors();

        $emp = $this->worker($this->day()->fresh());
        $this->clock($emp, 'time_in', '2026-09-11 13:32:00');
        $this->clock($emp, 'time_out', '2026-09-11 21:00:00');

        $paid = $this->paidOn('2026-09-11');

        $this->assertEqualsWithDelta(747.0, $paid['basicPay'], 0.01);
        $this->assertEqualsWithDelta(0.0, $paid['otPay'], 0.01);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
