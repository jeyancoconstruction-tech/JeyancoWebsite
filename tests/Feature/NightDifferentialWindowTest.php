<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\LaborType;
use App\Models\Shift;
use App\Models\SystemSetting;
use App\Support\WorkSchedule;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The night differential runs from ten in the evening to six in the morning.
 *
 * The Labor Code fixes that window (Art. 86), so it is not a setting and not
 * a property of a shift — a night crew and a day crew who both work past ten
 * are owed it on the same hours. It was written out twice, once for the
 * count by the shift's sessions and once for the flat count that governs
 * older days, and nothing pinned either. Two copies of a rule are two rules
 * waiting to disagree, so both now read the same constants and the edges are
 * checked here.
 */
class NightDifferentialWindowTest extends TestCase
{
    use RefreshDatabase;

    private function hours(string $in, string $out): float
    {
        $a = Carbon::parse("2026-09-11 {$in}");
        $b = Carbon::parse("2026-09-11 {$out}");

        if ($b->lessThanOrEqualTo($a)) {
            $b->addDay();
        }

        return round(WorkSchedule::nightHoursIn($a, $b), 2);
    }

    public function test_the_window_is_ten_at_night_until_six_in_the_morning(): void
    {
        $this->assertSame(22, WorkSchedule::NIGHT_FROM_HOUR);
        $this->assertSame(6, WorkSchedule::NIGHT_TO_HOUR);
        $this->assertEqualsWithDelta(8.0, $this->hours('22:00', '06:00'), 0.01, 'the whole of it');
    }

    public function test_nothing_before_ten_counts(): void
    {
        $this->assertEqualsWithDelta(0.0, $this->hours('18:00', '22:00'), 0.01,
            'a stretch ending exactly at ten earns none of it');
        $this->assertEqualsWithDelta(0.0, $this->hours('13:00', '21:59'), 0.01);
    }

    public function test_nothing_after_six_counts(): void
    {
        $this->assertEqualsWithDelta(0.0, $this->hours('06:00', '15:00'), 0.01,
            'a stretch starting exactly at six earns none of it');
        $this->assertEqualsWithDelta(0.0, $this->hours('07:00', '19:00'), 0.01);
    }

    public function test_only_the_part_inside_the_window_counts(): void
    {
        $this->assertEqualsWithDelta(1.0, $this->hours('21:00', '23:00'), 0.01, 'ten to eleven');
        $this->assertEqualsWithDelta(1.0, $this->hours('05:00', '07:00'), 0.01, 'five to six');
        $this->assertEqualsWithDelta(3.0, $this->hours('00:00', '03:00'), 0.01, 'the small hours are all of it');
    }

    public function test_a_long_stretch_is_capped_at_the_window(): void
    {
        $this->assertEqualsWithDelta(8.0, $this->hours('20:00', '08:00'), 0.01,
            'twelve hours over one night still hold only eight of night');
    }

    /** Two nights in one stretch each contribute their own eight. */
    public function test_it_counts_each_night_it_crosses(): void
    {
        $a = Carbon::parse('2026-09-11 20:00');
        $b = Carbon::parse('2026-09-13 08:00');

        $this->assertEqualsWithDelta(16.0, WorkSchedule::nightHoursIn($a, $b), 0.01);
    }

    /** And it reaches payroll: a day crew working late is owed it too. */
    public function test_a_day_crew_working_past_ten_earns_it(): void
    {
        SystemSetting::current()->forceFill([
            'schedule_rules_from' => '2026-09-01', 'auto_count_overtime' => true,
        ])->save();

        $day = Shift::where('crosses_midnight', false)->firstOrFail();
        $day->forceFill(Shift::layOut('08:00', '20:00', '12:00', '13:00') + [
            'regular_minutes' => 480,
        ])->save();

        $emp = Employee::create([
            'name' => 'Late Finisher', 'status' => Employee::STATUS_ACTIVE,
            'employment_type' => Employee::EMPLOYMENT_DAILY,
            'labor_type_id' => LaborType::create(['name' => 'Mason', 'daily_rate' => 800, 'ot_rate' => 125])->id,
            'shift_id' => $day->id, 'rate_per_hour' => 100,
        ]);

        Carbon::setTestNow(Carbon::parse('2026-09-11 08:00:00', 'Asia/Manila'));
        $this->postJson('/api/kiosk/attendance', ['employee_id' => $emp->id, 'type' => 'time_in'])->assertOk();

        Carbon::setTestNow(Carbon::parse('2026-09-11 23:00:00', 'Asia/Manila'));
        $this->postJson('/api/kiosk/attendance', ['employee_id' => $emp->id, 'type' => 'time_out'])->assertOk();

        $paid = app(\App\Services\PayrollService::class)
            ->computeForRange('2026-09-11', '2026-09-11')['days'][0]['details'][0];

        // One night hour only — ten to eleven — and it falls in the
        // overtime band, so it is uplifted from the overtime rate:
        // 1h x P100 x 1.25 x 10%.
        $this->assertEqualsWithDelta(12.50, $paid['nightDiffPay'], 0.01,
            'ten to eleven at night, and not the fourteen hours before it');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
