<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\LaborType;
use App\Models\Shift;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Clocking out of a shift that ran past midnight.
 *
 * Attendance is filed by calendar date and by half of the day: a row is found
 * again by today's date and whether the clock reads before or after noon. That
 * holds for a day shift, whose in and out land in the same half of the same
 * date. It does not hold for a night shift, which times in at 10pm on one date
 * and out at 6am on the next — a different date and the other half. Before this
 * the lookup pointed at an empty slot and the worker was told they had never
 * timed in, so a night shift could not close its own day.
 */
class NightShiftAttendanceTest extends TestCase
{
    use RefreshDatabase;

    private function worker(string $name, ?int $shiftId): Employee
    {
        $labor = LaborType::create(['name' => 'Mason ' . $name, 'daily_rate' => 800, 'ot_rate' => 125]);

        return Employee::create([
            'name'            => $name,
            'status'          => Employee::STATUS_ACTIVE,
            'employment_type' => Employee::EMPLOYMENT_DAILY,
            'labor_type_id'   => $labor->id,
            'shift_id'        => $shiftId,
            'rate_per_hour'   => 100,
        ]);
    }

    private function night(): Shift
    {
        return Shift::where('crosses_midnight', true)->firstOrFail();
    }

    private function day(): Shift
    {
        return Shift::where('crosses_midnight', false)->firstOrFail();
    }

    public function test_a_night_shift_clocks_out_into_the_row_it_opened(): void
    {
        $emp = $this->worker('Night Crew', $this->night()->id);

        Carbon::setTestNow(Carbon::parse('2026-09-03 22:00:00'));
        $in = Attendance::create([
            'employee_id' => $emp->id,
            'date'        => '2026-09-03',
            'time_in'     => Carbon::now(),
        ]);

        $this->assertSame('PM', $in->session, 'a 10pm arrival files under the afternoon half');
        $this->assertSame($this->night()->id, $in->shift_id, 'the shift is stamped on the row');

        // Morning: a different date, and the other half of the day.
        $found = Attendance::openForTimeOut($emp->id, Carbon::parse('2026-09-04 06:00:00'));

        $this->assertNotNull($found, 'the night shift must find the row it opened');
        $this->assertSame($in->id, $found->id);
    }

    public function test_a_day_shift_still_closes_its_own_row(): void
    {
        $emp = $this->worker('Day Crew', $this->day()->id);

        Carbon::setTestNow(Carbon::parse('2026-09-03 06:00:00'));
        $in = Attendance::create([
            'employee_id' => $emp->id,
            'date'        => '2026-09-03',
            'time_in'     => Carbon::now(),
        ]);

        $found = Attendance::openForTimeOut($emp->id, Carbon::parse('2026-09-03 11:00:00'));

        $this->assertSame($in->id, $found->id);
    }

    public function test_a_forgotten_day_row_is_not_closed_the_next_morning(): void
    {
        $emp = $this->worker('Forgetful', $this->day()->id);

        Carbon::setTestNow(Carbon::parse('2026-09-03 06:00:00'));
        Attendance::create([
            'employee_id' => $emp->id,
            'date'        => '2026-09-03',
            'time_in'     => Carbon::now(),
        ]);

        // Somebody who forgot to clock out yesterday would have that day
        // silently closed at whatever time they touched the kiosk today.
        $found = Attendance::openForTimeOut($emp->id, Carbon::parse('2026-09-04 06:00:00'));

        $this->assertNull($found, 'a day shift never reaches back into yesterday');
    }

    public function test_the_reach_back_stops_after_eighteen_hours(): void
    {
        $emp = $this->worker('Stale', $this->night()->id);

        Carbon::setTestNow(Carbon::parse('2026-09-03 22:00:00'));
        Attendance::create([
            'employee_id' => $emp->id,
            'date'        => '2026-09-03',
            'time_in'     => Carbon::now(),
        ]);

        // A night shift that was never closed is a broken record for the office
        // to fix, not something to close two days later at the wrong hour.
        $found = Attendance::openForTimeOut($emp->id, Carbon::parse('2026-09-05 06:00:00'));

        $this->assertNull($found);
    }

    public function test_an_already_closed_night_row_is_not_reopened(): void
    {
        $emp = $this->worker('Closed', $this->night()->id);

        Carbon::setTestNow(Carbon::parse('2026-09-03 22:00:00'));
        Attendance::create([
            'employee_id' => $emp->id,
            'date'        => '2026-09-03',
            'time_in'     => Carbon::now(),
            'time_out'    => Carbon::parse('2026-09-04 06:00:00'),
        ]);

        $found = Attendance::openForTimeOut($emp->id, Carbon::parse('2026-09-04 06:30:00'));

        $this->assertNull($found, 'a finished day stays finished');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
