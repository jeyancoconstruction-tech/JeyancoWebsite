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
 * The hours on the Work schedule card are the hours that get run.
 *
 * They were not. The card offered a Starts time and saved it to `starts_at`,
 * a column nothing reads: lateness, the two sessions, where overtime begins
 * and what the daily rate buys all come from the four session boundaries,
 * and no screen could reach those. Moving the day crew to 7am saved cleanly,
 * redrew the summary as "7:00 am to 4:00 pm", and changed nothing whatever —
 * the crew's day was still eight to five everywhere it counted.
 *
 * The card now carries both ends of the shift and lays the sessions out from
 * them, putting the break in the middle where it already was.
 */
class ShiftHoursAreEditableTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        SystemSetting::current()->forceFill(['schedule_rules_from' => '2026-09-01'])->save();
    }

    private ?User $admin = null;

    /** Memoised: some tests save twice, and username is unique. */
    private function admin(): User
    {
        return $this->admin ??= User::create([
            'name' => 'Admin', 'username' => 'admin.hours', 'password' => 'secret123',
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

    /** Save the card, keeping every shift's current hours except the ones given. */
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
                // Clamped the way the form clamps it: a stored figure can
                // outgrow its shift when the hours or the break change.
                'regular_hours'        => min($s->regularHours() ?? $paid, $paid),
                'grace_period_minutes' => $s->grace_period_minutes,
            ];
        }

        foreach ($override as $id => $fields) {
            $shifts[$id] = array_merge($shifts[$id], $fields);
        }

        return $this->actingAs($this->admin())->put(route('settings.attendance.update'), [
            // The real form posts this box; leaving it out is the off
            // answer and would quietly stop overtime being counted.
            'auto_count_overtime'     => 1,
            'week_starts_on'         => 1,
            'payroll_cycle'          => 'weekly',
            'shifts'                 => $shifts,
        ]);
    }

    public function test_the_card_offers_both_ends_of_each_shift(): void
    {
        $page = $this->actingAs($this->admin())
                     ->get(route('settings.index', ['tab' => 'attendance']))
                     ->assertOk();

        foreach ([$this->day(), $this->night()] as $shift) {
            $page->assertSee('shifts[' . $shift->id . '][ends_at]', false);
            $page->assertSee('value="' . $shift->endsAt() . '"', false);
        }
    }

    public function test_the_hours_typed_become_the_sessions_that_are_run(): void
    {
        $this->save([$this->day()->id => ['starts_at' => '07:00', 'ends_at' => '16:00', 'regular_hours' => 8]])
             ->assertSessionHasNoErrors();

        $day = $this->day()->fresh();

        $this->assertStringStartsWith('07:00', (string) $day->am_starts_at);
        $this->assertStringStartsWith('12:00', (string) $day->am_ends_at, 'lunch is where the office put it');
        $this->assertStringStartsWith('13:00', (string) $day->pm_starts_at);
        $this->assertStringStartsWith('16:00', (string) $day->pm_ends_at);

        // And the arithmetic everything else reads agrees with the card.
        $this->assertEqualsWithDelta(8.0, WorkSchedule::paidHours($day->schedule()), 0.01);
    }

    public function test_an_end_before_the_start_is_a_shift_that_crosses_midnight(): void
    {
        $day = $this->day();

        $this->save([$day->id => ['starts_at' => '22:00', 'ends_at' => '07:00',
                                   'break_from' => '02:00', 'break_to' => '03:00', 'regular_hours' => 8]])
             ->assertSessionHasNoErrors();

        $moved = $day->fresh();

        $this->assertTrue((bool) $moved->crosses_midnight,
            'nothing else can decide this — it is what the two times mean');
        $this->assertStringStartsWith('02:00', (string) $moved->am_ends_at);
        $this->assertStringStartsWith('03:00', (string) $moved->pm_starts_at);
        $this->assertStringStartsWith('07:00', (string) $moved->pm_ends_at);
    }

    public function test_the_night_shift_keeps_its_shape_when_saved_untouched(): void
    {
        $this->save([])->assertSessionHasNoErrors();

        $night = $this->night()->fresh();

        $this->assertStringStartsWith('20:00', (string) $night->am_starts_at);
        $this->assertStringStartsWith('00:00', (string) $night->am_ends_at);
        $this->assertStringStartsWith('01:00', (string) $night->pm_starts_at);
        $this->assertStringStartsWith('05:00', (string) $night->pm_ends_at);
        $this->assertTrue((bool) $night->crosses_midnight);
    }

    /** A shift has to be long enough to be a shift. */
    public function test_a_shift_with_no_paid_time_left_is_refused(): void
    {
        $this->save([$this->day()->id => ['starts_at' => '08:00', 'ends_at' => '08:45', 'regular_hours' => 0.25]])
             ->assertSessionHasErrors();

        $this->assertStringStartsWith('08:00', (string) $this->day()->fresh()->am_starts_at,
            'a refused save changes nothing');
    }

    /** The point of all of it: shorten the shift, and overtime starts earlier. */
    public function test_moving_the_end_moves_where_overtime_begins(): void
    {
        $this->save([$this->day()->id => ['starts_at' => '08:00', 'ends_at' => '15:00', 'regular_hours' => 6]])
             ->assertSessionHasNoErrors();

        $emp = Employee::create([
            'name' => 'Day Crew', 'status' => Employee::STATUS_ACTIVE,
            'employment_type' => Employee::EMPLOYMENT_DAILY,
            'labor_type_id' => LaborType::create(['name' => 'Mason', 'daily_rate' => 800, 'ot_rate' => 125])->id,
            'shift_id' => $this->day()->id, 'rate_per_hour' => 100,
        ]);

        Carbon::setTestNow(Carbon::parse('2026-09-11 08:00:00', 'Asia/Manila'));
        $this->postJson('/api/kiosk/attendance', ['employee_id' => $emp->id, 'type' => 'time_in'])
             ->assertOk()->assertJson(['success' => true]);

        Carbon::setTestNow(Carbon::parse('2026-09-11 17:00:00', 'Asia/Manila'));
        $this->postJson('/api/kiosk/attendance', ['employee_id' => $emp->id, 'type' => 'time_out'])
             ->assertOk()->assertJson(['success' => true]);

        $paid = app(\App\Services\PayrollService::class)
            ->computeForRange('2026-09-11', '2026-09-11')['days'][0]['details'][0];

        $this->assertEqualsWithDelta(6.0, $paid['hours'] - $paid['ot_hours'], 0.01,
            'eight to three, less the break, is six paid hours');
        $this->assertEqualsWithDelta(2.0, $paid['ot_hours'], 0.01,
            'the two hours past three in the afternoon are overtime now');
    }

    // ── The meal period belongs to the shift ─────────────────────────────

    public function test_the_break_is_asked_per_shift_not_once_for_the_office(): void
    {
        $page = $this->actingAs($this->admin())
                     ->get(route('settings.index', ['tab' => 'attendance']))
                     ->assertOk();

        $page->assertDontSee('name="unpaid_break_minutes"', false);

        foreach ([$this->day(), $this->night()] as $shift) {
            $page->assertSee('shifts[' . $shift->id . '][break_from]', false);
        }
    }

    /** Two crews, two meal periods, each where its own office put it. */
    public function test_each_shift_keeps_its_own_break(): void
    {
        $this->save([
            $this->day()->id   => ['starts_at' => '08:00', 'ends_at' => '17:00',
                                   'break_from' => '12:00', 'break_to' => '13:00', 'regular_hours' => 8],
            $this->night()->id => ['starts_at' => '20:00', 'ends_at' => '05:00',
                                   'break_from' => '23:30', 'break_to' => '00:00', 'regular_hours' => 8.5],
        ])->assertSessionHasNoErrors();

        $day   = $this->day()->fresh();
        $night = $this->night()->fresh();

        $this->assertSame(60, $day->break_minutes, 'worked out from the two times');
        $this->assertStringStartsWith('12:00', (string) $day->am_ends_at);
        $this->assertStringStartsWith('13:00', (string) $day->pm_starts_at);

        $this->assertSame(30, $night->break_minutes);
        $this->assertStringStartsWith('23:30', (string) $night->am_ends_at);
        $this->assertStringStartsWith('00:00', (string) $night->pm_starts_at, 'a break can cross midnight too');

        $this->assertEqualsWithDelta(8.0, $day->paidHours(), 0.01);
        $this->assertEqualsWithDelta(8.5, $night->paidHours(), 0.01, 'a shorter lunch is a longer paid day');
    }

    /** Lengthening the break shortens the paid day. */
    public function test_a_longer_break_leaves_less_paid_time(): void
    {
        $this->save([$this->day()->id => [
            'starts_at' => '08:00', 'ends_at' => '17:00',
            'break_from' => '11:00', 'break_to' => '13:00', 'regular_hours' => 7,
        ]])->assertSessionHasNoErrors();

        $this->assertEqualsWithDelta(7.0, $this->day()->fresh()->paidHours(), 0.01);
    }

    /** A meal period outside the shift is not a meal period. */
    public function test_a_break_that_falls_outside_the_shift_is_refused(): void
    {
        $this->save([$this->day()->id => [
            'starts_at' => '08:00', 'ends_at' => '17:00',
            'break_from' => '19:00', 'break_to' => '20:00', 'regular_hours' => 8,
        ]])->assertSessionHasErrors();

        $this->save([$this->day()->id => [
            'starts_at' => '08:00', 'ends_at' => '17:00',
            'break_from' => '08:00', 'break_to' => '09:00', 'regular_hours' => 7,
        ]])->assertSessionHasErrors('shifts.' . $this->day()->id . '.break_from');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
