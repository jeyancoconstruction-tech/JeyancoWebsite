<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Setting;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\Loan;
use App\Models\PayrollRate;
use App\Models\Bonus;
use App\Models\Shift;
use App\Models\ValeAdvance;
use App\Models\SystemSetting;
use App\Support\WorkSchedule;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Single source of truth for payroll calculations.
 *
 * The per-record breakdown (regular pay, overtime, holiday premium, deductions,
 * net) lives in computeRecord(). computeForRange() groups the same records three
 * ways — by week, by day, and by employee — so both the period-centric payroll
 * page and the employee-centric Payroll Records page stay perfectly consistent.
 */
class PayrollService
{
    /**
     * Work past this many hours in a stretch takes a meal period.
     *
     * Article 85 of the Labor Code requires a meal break of at least an hour
     * for work exceeding five hours, so a stretch shorter than this never had
     * a break to lose.
     */
    private const MEAL_PERIOD_AFTER_HOURS = 5.0;

    /**
     * Resolve the configurable payroll settings + holiday overlay once.
     */
    public function config(): array
    {
        $settings = Setting::first();
        $system   = SystemSetting::current();

        return [
            // Multipliers, the wage floor, the period bonus and the
            // contribution rates are all dated. A change adds a row rather than
            // editing one, so a period reopened next year still computes at the
            // numbers that applied then. Loaded once as a timeline and resolved
            // per attendance date — a query per record would be thousands.
            'rateTimeline'   => PayrollRate::timeline(),
            'restDayEnabled' => $settings?->sunday_rest_day_enabled ?? true,

            // Which day of the week is the rest day. Not a separate setting:
            // it is the seventh day of the week the office actually runs, so
            // moving the week's start moves the rest day with it. A week that
            // begins on Monday rests on Sunday; one that begins on Sunday rests
            // on Saturday.
            'restDayOn' => $system->restDayOn(),
            // Day-type multipliers are fixed by PH labor law, not configurable.
            // See doleFactors() for the whole table.
            'holidayTypeMap' => Holiday::typeMap(),   // 'Y-m-d' => 'regular'|'special'|'custom'

            // The shape of a working day. Not dated: these describe how the
            // office runs rather than what a circular says it must pay, and a
            // shift that starts at 8 has always started at 8 as far as payroll
            // is concerned.
            'day' => $system,

            // Every shift as a lookup. Payroll resolves one per attendance
            // record, and a query per row would be thousands.
            'shifts' => Shift::lookup(),
        ];
    }

    /**
     * The multipliers in force on a date — the newest set that had already
     * taken effect. Falls back to the statutory minimums when a date predates
     * every rate set, which only happens if the opening row is deleted.
     *
     * @return array<string, float>
     */
    private function ratesOn(string $date, array $cfg): array
    {
        // The timeline is newest first, so the first match is the answer.
        foreach ($cfg['rateTimeline'] ?? [] as $entry) {
            if ($entry['from'] <= $date) {
                return $entry['rates'];
            }
        }

        return PayrollRate::fallbackRates();
    }

    /**
     * Hours inside the night window, which carry the night differential.
     *
     * The flat count, for days before the schedule rules. Same window as
     * WorkSchedule uses, from the same constants, so the two cannot drift.
     *
     * Measured against the shift as a duration from time-in rather than against
     * the stored time-out: attendance keeps times without dates, so a shift
     * crossing midnight has a time-out that reads as earlier the same day. The
     * hours total is already derived that way, and a night figure that
     * disagreed with it would be worse than a rough one.
     *
     * @return array{0: float, 1: float}  [night hours in the first 8, night hours in overtime]
     */
    private function nightHours(Carbon $start, float $hours): array
    {
        $night = function (float $fromHour, float $toHour) use ($start): float {
            if ($toHour <= $fromHour) return 0.0;

            $minutes = 0;
            $cursor  = $start->copy()->addMinutes((int) round($fromHour * 60));
            $end     = $start->copy()->addMinutes((int) round($toHour * 60));

            // Minute by minute is slow; walk it in whole minutes only across the
            // segment, which is at most a day's worth.
            while ($cursor < $end) {
                $h = (int) $cursor->format('G');
                if ($h >= WorkSchedule::NIGHT_FROM_HOUR || $h < WorkSchedule::NIGHT_TO_HOUR) $minutes++;
                $cursor->addMinute();
            }

            return $minutes / 60;
        };

        $regularSpan = min(8.0, $hours);

        return [$night(0, $regularSpan), $night($regularSpan, $hours)];
    }

    /**
     * The DOLE pay factors for one day: what an hour is worth, and what an
     * overtime hour is worth.
     *
     *   Day type                          first 8h    overtime
     *   ─────────────────────────────────────────────────────────
     *   Ordinary day                        100%        125%
     *   Rest day OR special non-working     130%        169%
     *   Special day falling on a rest day   150%        195%
     *   Regular holiday                     200%        260%
     *   Regular holiday on a rest day       260%        338%
     *
     * The overtime column is not a separate table: it is the day's own factor
     * times the overtime premium — the OT multiplier on an ordinary day, the
     * rest-day multiplier on any premium day, because the law's "+30% for
     * overtime on a premium day" is the same 30% the rest day itself carries.
     * That distinction is what this method exists for. Payroll used to apply
     * 1.25 to every overtime hour and then multiply the whole day by the
     * holiday factor, which paid 250% for overtime on a regular holiday and
     * 162.5% on a rest day, both short of the law.
     *
     * A "custom" holiday is treated as a regular holiday, matching how the rest
     * of the app reads that flag. A special day falling on a rest day is 150% —
     * a figure the law states outright rather than one derived from the others,
     * so it is the one constant here.
     *
     * @param  array<string, float>  $rates  as resolved for this day
     * @return array{0: float, 1: float}  [regular-hour factor, overtime factor]
     */
    private function doleFactors(?string $holidayType, bool $isRestDay, array $rates): array
    {
        $rest    = $rates['rest_day_multiplier'];
        $holiday = $rates['regular_holiday_multiplier'];

        $regular = match (true) {
            $holidayType === 'special' && $isRestDay => 1.50,
            $holidayType === 'special'               => $rest,
            $holidayType !== null && $isRestDay      => $holiday * $rest,  // regular / custom holiday on a rest day
            $holidayType !== null                    => $holiday,
            $isRestDay                               => $rest,
            default                                  => 1.00,
        };

        $premiumDay = $holidayType !== null || $isRestDay;
        $overtime   = $regular * ($premiumDay ? $rest : $rates['ot_multiplier']);

        return [$regular, $overtime];
    }

    /**
     * The BIR graduated withholding table, daily column (RR 11-2018, the rates
     * in force from 2023 onward).
     *
     * Each row is [floor, fixed tax at that floor, rate on the excess]. Nothing
     * here is configurable, which is the point: the table is the law, not an
     * office preference, and an admin who could edit it could quietly withhold
     * the wrong amount from everyone.
     *
     * The daily column is the one that applies because attendance is recorded
     * and paid by the day here. A worker whose day lands under ₱685 has no tax
     * withheld, which is most of a construction payroll.
     */
    private const WITHHOLDING_DAILY = [
        [21_918.0, 6_033.10, 0.35],
        [ 5_479.0, 1_102.60, 0.30],
        [ 2_192.0,   280.85, 0.25],
        [ 1_096.0,    61.65, 0.20],
        [   685.0,     0.00, 0.15],
    ];

    /**
     * Withholding tax on one day's taxable compensation — gross less the
     * mandatory contributions, which the table is defined net of.
     */
    private function withholdingTaxOn(float $taxable): float
    {
        if ($taxable <= 0) {
            return 0.0;
        }

        foreach (self::WITHHOLDING_DAILY as [$floor, $fixed, $rate]) {
            if ($taxable > $floor) {
                return round($fixed + (($taxable - $floor) * $rate), 2);
            }
        }

        return 0.0;
    }

    /**
     * Compute payroll for a date range (inclusive). Null bounds mean "no limit"
     * on that side, preserving the original "all records" behaviour.
     *
     * @return array{weeks: array, days: array, employees: array}
     */
    public function computeForRange(?string $from = null, ?string $to = null): array
    {
        $cfg = $this->config();

        // A day nobody closed is closed at the end of its session before it is
        // paid — at a guessed time, flagged for review, never with overtime.
        Attendance::closeStale(null, Carbon::now('Asia/Manila'));

        // A pending registration is not on the payroll. Their rows are left
        // out here rather than zeroed later, so they cannot reach a payslip,
        // a report or a weekly total by any route.
        $query = Attendance::with(['employee', 'shift'])->ofRegistered();
        if ($from && $to) {
            $query->whereBetween('date', [$from, $to]);
        } elseif ($from) {
            $query->where('date', '>=', $from);
        } elseif ($to) {
            $query->where('date', '<=', $to);
        }
        $records = $query->get();

        // Lateness is measured once per session — on the first time in. A
        // worker back from a mistaken time-out is not late for the second one.
        $cfg['firstInSession'] = $records->filter(fn ($r) => $r->time_in)
            ->groupBy(fn ($r) => $r->employee_id . '|' . Carbon::parse($r->date)->toDateString() . '|' . $r->session)
            ->map(fn ($g) => $g->sortBy(fn ($r) => (string) $r->time_in)->first()->id)
            ->flip()
            ->all();

        // A day's regular hours are bought once, not once per record.
        //
        // The bands are fixed on the clock, so the hours inside the sessions
        // add up across a day's records without counting a minute twice. The
        // figure a shift's rate buys does not work that way: applied afresh
        // to each record, a crew that clocks out for lunch would collect
        // eleven regular hours where the same day clocked unbroken collects
        // eight and three of overtime. So the running total is worked out
        // here, in time order, and handed to each record.
        $cfg['regularUsedBefore'] = [];
        $rulesFrom = $cfg['day']?->schedule_rules_from
            ? Carbon::parse($cfg['day']->schedule_rules_from)->toDateString()
            : null;

        $byDay = $records->filter(fn ($r) => $r->time_in && $r->time_out)
            ->groupBy(fn ($r) => $r->employee_id . '|' . Carbon::parse($r->date)->toDateString());

        foreach ($byDay as $rows) {
            $used = 0;

            foreach ($rows->sortBy(fn ($r) => (string) $r->time_in) as $rec) {
                $cfg['regularUsedBefore'][$rec->id] = $used;

                $date  = Carbon::parse($rec->date)->toDateString();
                $sched = $cfg['shifts'][$rec->shift_id] ?? null;

                if (! $rulesFrom || $date < $rulesFrom || ! WorkSchedule::has($sched)) {
                    continue;
                }

                // Not $from: that is the range this whole call was asked for,
                // and overwriting it here left everything below working off a
                // clock time instead of a date.
                [$in, $out] = WorkSchedule::stretch($rec->time_in, $rec->time_out, $date);
                $paidFrom = WorkSchedule::paidFrom($sched, $in, $date,
                    WorkSchedule::sessionOf($sched, $rec->session, $in), isset($cfg['firstInSession'][$rec->id]));
                $used += (int) round(WorkSchedule::split($sched, $paidFrom, $out, $date, $used)['regular'] * 60);
            }
        }

        // The one-off grants that land anywhere in this range, loaded once. A
        // query per employee per week would be thousands for a month of a full
        // crew. The dates come from the records rather than the arguments,
        // which may be open-ended.
        $dates = $records->pluck('date')->map(fn ($d) => Carbon::parse($d)->toDateString());

        $cfg['bonusGrants'] = $dates->isEmpty()
            ? []
            : Bonus::inRange($dates->min(), $dates->max());

        // Advances are filtered on where their schedule begins rather than on
        // the range: one started months ago may still have instalments left to
        // collect inside it. A week outside an advance's run is answered zero.
        //
        // The cut-off is the end of the week the last worked day falls in, not
        // that day itself. Payroll collects by the week, so an advance that
        // starts on the Wednesday belongs to a week whose attendance may well
        // stop on the Tuesday — asked only up to the Tuesday, it was never
        // loaded, and that week collected nothing while the balance on Leave &
        // Advances counted it as taken.
        //
        // And the end of the week the range closes in, when there is one. A
        // range nobody clocked in on still has leave in it, and a week of
        // leave collects its instalment like any other — sized off attendance
        // alone, it loaded no advances at all.
        $advancesTo = collect([
            $dates->isEmpty() ? null : Carbon::parse($dates->max())->addDays(6)->toDateString(),
            $to ? Carbon::parse($to)->addDays(6)->toDateString() : null,
        ])->filter()->max();

        $cfg['valeAdvances'] = $advancesTo === null ? [] : ValeAdvance::upTo($advancesTo);

        // The cash advances issued on Leave & Advances, collected on the
        // instalment their application asked for. Same instrument as the one
        // above and taken the same way; the difference is only where it was
        // entered, so both land on the one advance line.
        $cfg['cashAdvances'] = $advancesTo === null ? [] : Loan::upTo($advancesTo);

        // Approved leave touching the range. A paid day off is wages, so it
        // belongs in the figures every screen reads, not only in a payroll
        // run — and a worker on leave for a whole week has to appear at all,
        // which is why it is loaded for the range asked for rather than for
        // the days somebody happened to clock in on.
        $cfg['leave'] = $from && $to
            ? LeaveRequest::approved()
                ->overlapping($from, $to)
                ->whereHas('employee', fn ($q) => $q->registered())
                ->with('employee.laborType', 'employee.shift')
                ->get()
                ->groupBy('employee_id')
            : collect();

        // The range as it was asked for. Leave is grouped by the pay week, but
        // it may only ever be credited for the days actually asked about — a
        // single day's view of a week-long leave showed the whole week's pay
        // against that one day, because the week was the only bound on it.
        $cfg['range'] = ['from' => $from, 'to' => $to];

        $weeks = $this->groupByWeek($records, $cfg);

        return [
            'weeks'     => $weeks,
            'days'      => $this->groupByDay($records, $cfg),
            'employees' => $this->pivotByEmployee($weeks),
        ];
    }

    /**
     * What comes off a stretch of paid leave.
     *
     * Leave is income, so it is contributed and withheld on like any other
     * wage. Worked out per day at the day's own rate and then multiplied,
     * because the BIR table computeRecord() applies is the daily column — a
     * week's leave taxed as one lump would land in the wrong band.
     *
     * @return array{sss: float, philhealth: float, pagibig: float, tax: float, total: float}
     */
    private function leaveDeductions(float $dayRate, float $paidDays, array $rates, bool $onContract): array
    {
        $zero = ['sss' => 0.0, 'philhealth' => 0.0, 'pagibig' => 0.0, 'tax' => 0.0, 'total' => 0.0];

        if ($onContract || $dayRate <= 0 || $paidDays <= 0) {
            return $zero;
        }

        $sss  = $dayRate * ($rates['sss_rate'] ?? 0) / 100;
        $phil = $dayRate * ($rates['philhealth_rate'] ?? 0) / 100;
        $pag  = $dayRate * ($rates['pagibig_rate'] ?? 0) / 100;

        $tax = ($rates['withholding_tax'] ?? true)
            ? $this->withholdingTaxOn($dayRate - ($sss + $phil + $pag))
            : 0.0;

        $out = [
            'sss'        => round($sss  * $paidDays, 2),
            'philhealth' => round($phil * $paidDays, 2),
            'pagibig'    => round($pag  * $paidDays, 2),
            'tax'        => round($tax  * $paidDays, 2),
        ];

        return $out + ['total' => round(array_sum($out), 2)];
    }

    /**
     * What one day of this worker's is priced at.
     *
     * Read off a day the week already priced where there is one, so a leave
     * day and a worked day in the same week are never worth different
     * amounts. With no day to read — a week that is nothing but leave — it is
     * worked out the way computeRecord() works it out: the labour type's
     * daily rate, raised to the wage order's floor when the type is behind
     * it, and the stored hourly rate over a standard day only when there is
     * no labour type at all.
     *
     * @param  float  $priced  the rate the week already priced a day at, or 0
     */
    private function dayRateOf($employee, array $rates, float $priced = 0.0): float
    {
        if ($priced > 0) {
            return $priced;
        }

        $configured = $employee->laborType?->daily_rate;
        $schedule   = $employee->shift?->schedule();
        $standard   = $schedule && WorkSchedule::has($schedule) ? max(1.0, WorkSchedule::paidHours($schedule)) : 8.0;

        $daily = $configured !== null
            ? (float) $configured
            : ((float) ($employee->rate_per_hour ?? 0)) * $standard;

        $floor = $rates['daily_rate'] ?? null;

        return $floor !== null && (float) $floor > $daily ? (float) $floor : $daily;
    }

    /** Hours as a whole number of minutes, without the float noise of adding them up. */
    private static function wholeMinutes(float $hours): float
    {
        return round($hours * 60) / 60;
    }

    /**
     * Per-record payroll breakdown — identical math used by every grouping.
     */
    private function computeRecord($rec, array $cfg): array
    {
        $employee = $rec->employee;

        // Total hours worked from time_in/time_out (includes minutes)
        $hours = 0;
        if ($rec->time_in && $rec->time_out) {
            try {
                // Whole minutes, as the screens show them; see WorkSchedule::stretch().
                $timeIn  = Carbon::parse($rec->time_in)->startOfMinute();
                $timeOut = Carbon::parse($rec->time_out)->startOfMinute();

                // Attendance stores clock times without a date, so a shift that
                // runs past midnight comes back with a time-out that reads as
                // EARLIER the same day. This used to be abs()'d, which turned
                // 10 PM–6 AM into sixteen hours instead of eight and paid eight
                // hours of overtime that were never worked. Rolling the end
                // forward a day is what the times actually mean.
                if ($timeOut <= $timeIn) {
                    $timeOut = $timeOut->copy()->addDay();
                }

                $hours = $timeIn->diffInMinutes($timeOut) / 60;
            } catch (\Exception $e) {
                Log::warning("Payroll: Failed to parse time for attendance {$rec->id}", [
                    'time_in'  => $rec->time_in,
                    'time_out' => $rec->time_out,
                    'error'    => $e->getMessage(),
                ]);
                $hours = 0;
            }
        }

        // The hours a daily rate buys, and where overtime begins. Both were the
        // bare number 8 — right for this office, but not a decision anybody
        // could see. An office that has turned auto-overtime off pays the extra
        // hours at the plain rate: the hours are worked either way, but they are
        // not overtime until somebody says so.
        $day        = $cfg['day'] ?? null;
        $standard   = max(1.0, (float) ($day->standard_hours_per_day ?? 8));
        $autoOt     = (bool) ($day->auto_count_overtime ?? true);

        // The meal period sits inside the standard day. Nine hours on site with
        // an hour for lunch is eight hours of wage, so the daily rate buys the
        // standard hours less the break, and overtime starts there too.
        $breakHours   = max(0.0, (float) ($day->unpaid_break_minutes ?? 0) / 60);
        $paidStandard = max(1.0, $standard - $breakHours);

        $hours = self::wholeMinutes(max(0, $hours));

        // A meal period is only taken on a stretch long enough to contain one —
        // the Labor Code requires it of work exceeding five hours, and that is
        // the line used here. It keeps the arithmetic honest whichever way the
        // crew clocks: one straight 6am–3pm record loses the hour, while a
        // morning and an afternoon record clocked either side of lunch are each
        // short of the line and lose nothing, because the break is already out.
        // Never below the line itself, so a longer day is never a smaller wage.
        if ($breakHours > 0 && $hours > self::MEAL_PERIOD_AFTER_HOURS) {
            $hours = self::wholeMinutes(max(self::MEAL_PERIOD_AFTER_HOURS, $hours - $breakHours));
        }

        // Priced by the minute. The hours used to be rounded to two decimals
        // first, so that hours × rate on screen came to the gross exactly —
        // but a hundredth of an hour is thirty-six seconds, and one minute
        // went in as 0.02 of an hour: a minute and twelve seconds' pay.
        $regular_hours = $autoOt ? min($paidStandard, $hours) : $hours;
        $ot_hours      = $autoOt ? max(0, $hours - $paidStandard) : 0.0;

        // The multipliers in force on the day being computed, not today's.
        $dateStr = Carbon::parse($rec->date)->toDateString();
        $rates   = $this->ratesOn($dateStr, $cfg);

        [$nightRegularHours, $nightOtHours] = ($hours > 0 && $rec->time_in)
            ? $this->nightHours(Carbon::parse($rec->time_in)->startOfMinute(), $hours)
            : [0.0, 0.0];
        $night_hours = round($nightRegularHours + $nightOtHours, 2);

        // ── Counting by the shift's sessions ─────────────────────────────────
        // From the office's chosen date, a day worked under a shift with a
        // schedule is counted by the clock rather than by its length: the part
        // inside the two sessions is regular, the part after the shift ends is
        // overtime, and arriving early or working through the break is neither.
        //
        // Overtime used to be "past the standard in one record". A crew that
        // clocks the morning and the afternoon as two records never had one
        // that long, so ten hours on site paid ten hours at the plain rate.
        // Per record is still right for the bands, which are fixed on the
        // clock and so add up across a day without counting any minute twice
        // — but not for the figure the rate buys, which is why the day's
        // running total is passed in.
        $schedShift = $cfg['shifts'][$rec->shift_id] ?? null;
        $rulesFrom  = $day?->schedule_rules_from ? Carbon::parse($day->schedule_rules_from)->toDateString() : null;
        $scheduled  = $rulesFrom && $dateStr >= $rulesFrom && WorkSchedule::has($schedShift)
                      && $rec->time_in && $rec->time_out;

        if ($scheduled) {
            [$in, $out] = WorkSchedule::stretch($rec->time_in, $rec->time_out, $dateStr);

            // Inside the grace period the pay runs from the session's start
            // (WorkSchedule::paidFrom). $in stays the clock's: lateness below
            // is measured against it.
            $paidIn     = WorkSchedule::paidFrom($schedShift, $in, $dateStr,
                              WorkSchedule::sessionOf($schedShift, $rec->session, $in),
                              ! isset($cfg['firstInSession']) || isset($cfg['firstInSession'][$rec->id]));
            $split      = WorkSchedule::split($schedShift, $paidIn, $out, $dateStr,
                              (int) ($cfg['regularUsedBefore'][$rec->id] ?? 0));

            // What the daily rate buys is the two sessions, so that is the divisor.
            $paidStandard  = max(1.0, WorkSchedule::paidHours($schedShift));
            $regular_hours = self::wholeMinutes($split['regular']);

            // With auto-overtime off, the hours after the shift wait for an
            // approved overtime request instead of being paid here.
            $ot_hours = $autoOt ? self::wholeMinutes($split['ot']) : 0.0;
            $hours    = $regular_hours + $ot_hours;

            $nightRegularHours = $nightOtHours = 0.0;
            foreach ($split['segments'] as [$from, $to, $isOt]) {
                if ($isOt && ! $autoOt) {
                    continue;
                }
                $n = WorkSchedule::nightHoursIn($from, $to);
                $isOt ? $nightOtHours += $n : $nightRegularHours += $n;
            }
            $night_hours = round($nightRegularHours + $nightOtHours, 2);
        }

        // Hourly rate comes from the CONFIGURED labor-type daily rate (÷ 8),
        // which is the source of truth. We fall back to the stored
        // rate_per_hour only for employees without a labor type. This keeps
        // gross aligned with the rate configured in Settings even if an
        // employee's cached rate_per_hour has drifted.
        $configured = $employee->laborType?->daily_rate;
        $dailyRate  = $configured !== null
            ? (float) $configured
            : ((float) ($employee->rate_per_hour ?? 0)) * $paidStandard;

        // The wage order's daily floor for that day, if one is on file. A
        // labour type still carrying last year's rate is paid at the floor
        // rather than below it. The floor is dated like everything else, so
        // raising it never reaches back into a period already paid.
        $wageFloor = $rates['daily_rate'] ?? null;
        if ($wageFloor !== null && $wageFloor > $dailyRate) {
            $dailyRate = (float) $wageFloor;
        }

        $rate    = $dailyRate / $paidStandard;
        $ot_rate = $rate * $rates['ot_multiplier'];

        // Contractual workers are settled against their contract, not through
        // this payroll. Their hours are still measured and reported — the
        // office wants to see who was on site — but no money is computed for
        // them here, and no rate is implied. Everything below multiplies out
        // to zero from these.
        $onContract = (bool) $employee?->isExcludedFromPayroll();

        if ($onContract) {
            $rate     = 0.0;
            $ot_rate  = 0.0;
            $basicPay = 0.0;
            $otPay    = 0.0;
        } else {
            $basicPay = $regular_hours * $rate;
            $otPay    = $ot_hours * $ot_rate;
        }

        // What the day would pay if it were an ordinary working day. Everything
        // above this is premium, and is reported separately on the payslip.
        $dayEarnings = $basicPay + $otPay;

        $holidayType = $cfg['holidayTypeMap'][$dateStr] ?? null;
        $isHoliday   = $holidayType !== null;

        // The rest day is the seventh day of the working week, so it follows
        // where that week begins rather than being fixed to Sunday. A frozen
        // per-record decision (rest_day_applied) still wins, so a day already
        // settled does not recalculate when the office changes its week; null
        // means "follow the current setting" — this week and future ones.
        $restDayOn = (int) ($cfg['restDayOn'] ?? Carbon::SUNDAY);
        $onRestDay = Carbon::parse($rec->date)->dayOfWeek === $restDayOn;

        // rest_day_applied is the whole answer, not half of it. It used to be
        // ANDed with the weekday, which was enough while the rest day was
        // always Sunday — a frozen Sunday stayed a Sunday. Now that the day
        // moves with the week, the weekday is exactly what the freeze has to
        // survive: a settled day keeps what it was settled as, whichever day of
        // the week it has since become.
        $isRestDay = $rec->rest_day_applied !== null
            ? (bool) $rec->rest_day_applied
            : ($onRestDay && ($cfg['restDayEnabled'] ?? true));

        [$hMultiplier, $otFactor] = $this->doleFactors($holidayType, $isRestDay, $rates);

        // The law states one combined figure for a day that is both — a regular
        // holiday on a rest day pays 260%, not 200% plus 30% — so the premium is
        // computed once and then attributed, rather than added up from parts
        // that would double-count. The holiday is named as the cause when there
        // is one, because that is the rate being applied.
        $doleGross = $onContract ? 0.0
            : ($regular_hours * $rate * $hMultiplier) + ($ot_hours * $rate * $otFactor);
        $premium   = $doleGross - $dayEarnings;

        $holidayPay = $isHoliday ? $premium : 0.0;
        $restDayPay = (! $isHoliday && $isRestDay) ? $premium : 0.0;

        // Night differential: 10% on top of whatever each night hour already
        // earns, so an overtime hour at 1 AM on a holiday is uplifted from its
        // own rate rather than from the plain one. It stacks with everything
        // above rather than replacing any of it.
        $nightDiffPay = $onContract ? 0.0 : (
            ($nightRegularHours * $rate * $hMultiplier) + ($nightOtHours * $rate * $otFactor)
        ) * ($rates['night_diff_multiplier'] - 1);

        $gross       = $dayEarnings + $holidayPay + $restDayPay + $nightDiffPay;

        // Statutory deductions are computed on GROSS pay (not the daily rate),
        // and do not apply to contract work. Vale and manual deductions still
        // do — those are advances and adjustments, not statutory contributions.
        $sssDeduction        = $onContract ? 0.0 : ($gross * $rates['sss_rate']) / 100;
        $philhealthDeduction = $onContract ? 0.0 : ($gross * $rates['philhealth_rate']) / 100;
        $pagibigDeduction    = $onContract ? 0.0 : ($gross * $rates['pagibig_rate']) / 100;
        $contributions       = $sssDeduction + $philhealthDeduction + $pagibigDeduction;

        // Withholding tax is not a rate anyone sets: it is the BIR graduated
        // table, applied to what is left after the mandatory contributions —
        // which is the base the table is written against. Attendance here is
        // daily, so the daily column is the one that applies.
        //
        // Whether to withhold at all is a decision, and it is dated like the
        // rest: switching it off does not go back and un-withhold a period
        // already paid and already remitted.
        $withholdingTax = ($onContract || ! ($rates['withholding_tax'] ?? true))
            ? 0.0
            : $this->withholdingTaxOn($gross - $contributions);

        $autoDeductions = $contributions + $withholdingTax;

        // An excluded worker earns nothing here, so deducting an advance from
        // it would report a negative net for someone this payroll does not pay.
        // Advances against a contract are settled with the contract.
        $vale             = $onContract ? 0 : (is_numeric($rec->vale) ? $rec->vale : 0);
        $manualDeductions = $onContract ? 0 : (is_numeric($rec->deductions) ? $rec->deductions : 0);

        // A vale is a loan against wages, and taking all of it can send a
        // worker home with nothing. The ceiling caps what one period may
        // collect, as a share of what is left after the statutory deductions.
        // What it does not take is still owed — the balance is untouched, only
        // this period's collection is limited.
        $valeCeiling = (int) ($rates['vale_ceiling_percent'] ?? 100);

        if ($valeCeiling < 100) {
            $afterStatutory = max(0, $gross - $autoDeductions);
            $vale = min($vale, round($afterStatutory * $valeCeiling / 100, 2));
        }

        $totalDeductions = $autoDeductions + $vale + $manualDeductions;
        $net             = $gross - $totalDeductions;

        // How late the shift started, past the grace period. Reported, not
        // deducted: nothing in payroll has ever docked pay for it, and turning
        // a new figure into a deduction would quietly cut wages the day the
        // setting was saved. A worker is already paid only for hours worked.
        $lateMinutes = 0;

        // The shift this day was worked under, if one was stamped on it. A
        // record from before shifts existed has none, and falls back to the
        // office setting it was computed under — so nothing already paid moves.
        $shift = $cfg['shifts'][$rec->shift_id] ?? null;

        // Days before the new count keep the start they were late against; the
        // shift's start moved when its sessions were written down.
        $startsAt = ($shift['legacy_starts_at'] ?? null)
            ?: ($shift['starts_at'] ?? (string) ($day->expected_time_in ?? '08:00:00'));
        $grace    = $shift['grace']     ?? (int) ($day->grace_period_minutes ?? 15);
        $crosses  = $shift['crosses']   ?? (($day->shift ?? 'day') === 'night');

        if ($scheduled) {
            // Late against the start of the session this stretch opened — 8:00
            // for the morning, 1:00 for the afternoon — and only on the first
            // time in. The afternoon used to be measured from 8:00, so every
            // afternoon record read five hours late.
            $isFirst = ! isset($cfg['firstInSession']) || isset($cfg['firstInSession'][$rec->id]);
            $sess    = in_array($rec->session, ['AM', 'PM'], true) ? $rec->session : WorkSchedule::sessionAt($schedShift, $in);
            $starts  = WorkSchedule::sessionStart($schedShift, $sess, $dateStr);

            if ($isFirst
                && $in->greaterThan($starts->copy()->addMinutes($grace))
                && $in->lessThan(WorkSchedule::sessionEnd($schedShift, $sess, $dateStr))) {
                $lateMinutes = (int) round(abs($starts->diffInMinutes($in)));
            }
        } elseif ($rec->time_in && ($shift || $day)) {
            $in       = Carbon::parse($rec->time_in);
            $expected = Carbon::parse($rec->date)->setTimeFromTimeString($startsAt);
            $allowed  = $expected->copy()->addMinutes($grace);

            $actual = $expected->copy()->setTime((int) $in->format('G'), (int) $in->format('i'), 0);

            // A shift that crosses midnight puts a clock-in long before its
            // start in the small hours of the next morning, not most of a day
            // early. Twelve hours is the line: fifteen minutes early is still
            // early, 12:30 AM against a 10 PM start is two and a half hours late.
            if ($crosses && $expected->diffInHours($actual, false) < -12) {
                $actual->addDay();
            }

            if ($actual->greaterThan($allowed)) {
                $lateMinutes = (int) round($expected->diffInMinutes($actual));
            }
        }

        return compact(
            'hours', 'regular_hours', 'ot_hours', 'night_hours', 'lateMinutes', 'shift', 'rate', 'ot_rate', 'basicPay', 'otPay',
            'dayEarnings', 'isHoliday', 'holidayType', 'hMultiplier', 'holidayPay', 'onRestDay', 'restDayPay',
            'nightDiffPay', 'rates',
            'gross', 'dailyRate',
            'sssDeduction', 'philhealthDeduction', 'pagibigDeduction', 'withholdingTax', 'autoDeductions',
            'vale', 'manualDeductions', 'totalDeductions', 'net'
        );
    }

    /**
     * What one worker's advances collect in the week opening on a date — the
     * Payroll Settings vale advances that name them, and their own cash
     * advances — as [due, taken].
     *
     * Due is what each advance's own schedule says the week takes. Taken is
     * that, held to the vale ceiling: the ceiling limits what one period may
     * collect, so the instalment answers to it too, and to what the day vale
     * has already taken under it. What it does not collect stays owed; only
     * this period is limited.
     *
     * One place for a worked week and a week of leave alike, so neither can
     * collect differently from the other or from the schedule the Cash
     * Advances tab shows.
     *
     * @return array{0: float, 1: float}
     */
    private function advancesFor(int $empId, string $weekOpens, int $weekStart, array $cfg, array $weekRates,
                                 float $gross, float $auto, float $valeSoFar): array
    {
        $due = 0.0;

        foreach ($cfg['valeAdvances'] ?? [] as $adv) {
            if ($adv['all'] || in_array($empId, $adv['employees'])) {
                $due += $adv['advance']->dueForWeekOpening($weekOpens, $weekStart);
            }
        }

        foreach ($cfg['cashAdvances'] ?? [] as $adv) {
            if ($adv['employee_id'] === $empId) {
                $due += $adv['advance']->dueForWeekOpening($weekOpens, $weekStart);
            }
        }

        $taken   = $due;
        $ceiling = (int) ($weekRates['vale_ceiling_percent'] ?? 100);

        if ($ceiling < 100 && $due > 0) {
            $allowance = round(max(0, $gross - $auto) * $ceiling / 100, 2);
            $taken     = max(0, min($due, round($allowance - $valeSoFar, 2)));
        }

        return [$due, $taken];
    }

    /**
     * The stretch of a week that leave may be credited for.
     *
     * Three bounds, and the narrowest of them wins. The week, because pay is
     * grouped by the week. Today, because a day off is paid when it comes
     * round and not when it is signed off. And the range that was asked for,
     * because a day's view of a week-long leave must answer for that day —
     * missing that bound, one date asked about was answered with every day of
     * the leave the week had reached.
     *
     * @return array{0: string, 1: string}  [first day, last day]; first > last means none
     */
    private function leaveWindow(array $cfg, string $weekOpens, string $weekCloses): array
    {
        $from = (string) ($cfg['range']['from'] ?? '');
        $to   = (string) ($cfg['range']['to'] ?? '');

        return [
            $from !== '' ? max($weekOpens, $from) : $weekOpens,
            min(
                $to !== '' ? min($weekCloses, $to) : $weekCloses,
                Carbon::now('Asia/Manila')->toDateString()
            ),
        ];
    }

    /**
     * The weeks approved leave falls in, keyed and bounded exactly as the
     * weeks attendance produces — so a week that has both is one week.
     *
     * Only the leave that has come round is counted, or a leave filed for
     * next month would raise an empty week now.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    private function weeksWithLeave(array $cfg, int $weekStart, int $weekEnd): array
    {
        $from = (string) ($cfg['range']['from'] ?? '');
        $to   = (string) ($cfg['range']['to'] ?? '');

        if ($from === '' || $to === '') {
            return [];
        }

        $today = Carbon::now('Asia/Manila')->toDateString();
        $weeks = [];

        foreach (($cfg['leave'] ?? []) as $filed) {
            foreach ($filed as $row) {
                $first = max($row->starts_on->toDateString(), $from);
                $last  = min($row->ends_on->toDateString(), $to, $today);

                if ($first > $last) {
                    continue;
                }

                $cursor = Carbon::parse($first)->startOfWeek($weekStart);
                $end    = Carbon::parse($last);

                while ($cursor->lessThanOrEqualTo($end)) {
                    $closes = $cursor->copy()->endOfWeek($weekEnd);

                    $weeks[$cursor->format('m/d/Y') . ' - ' . $closes->format('m/d/Y')]
                        = [$cursor->toDateString(), $closes->toDateString()];

                    $cursor->addWeek();
                }
            }
        }

        return $weeks;
    }

    /**
     * Group records by week, then by employee within the week. The week opens
     * on the day System Settings names.
     * Output shape matches the original $payrollWeeks exactly.
     */
    private function groupByWeek($records, array $cfg): array
    {
        // Which day a pay week opens on is a setting; it was Monday everywhere
        // as a bare constant. The last day is whatever comes six days later, so
        // the two can never drift apart.
        $weekStart = (int) ($cfg['day']->week_starts_on ?? Carbon::MONDAY);
        $weekEnd   = ($weekStart + 6) % 7;

        $recordsByWeek = $records->groupBy(function ($item) use ($weekStart, $weekEnd) {
            $start = Carbon::parse($item->date)->startOfWeek($weekStart)->format('m/d/Y');
            $end   = Carbon::parse($item->date)->endOfWeek($weekEnd)->format('m/d/Y');
            return "$start - $end";
        });

        // Which weeks payroll has something to say about.
        //
        // They came off attendance alone, so a week nobody clocked in on did
        // not exist — and a worker signed off for approved leave through such
        // a week was not paid for it, nor even listed. That is not an edge
        // case: one day's view of a day nobody worked is exactly the screen
        // the office opens to ask where somebody on leave has gone. Leave
        // makes a week of its own now, and the weeks are in date order
        // whichever of the two put them there.
        $weekBounds = [];

        foreach ($recordsByWeek as $weekRange => $weekGroup) {
            $on = Carbon::parse($weekGroup->first()->date);

            $weekBounds[$weekRange] = [
                $on->copy()->startOfWeek($weekStart)->toDateString(),
                $on->copy()->endOfWeek($weekEnd)->toDateString(),
            ];
        }

        foreach ($this->weeksWithLeave($cfg, $weekStart, $weekEnd) as $weekRange => $bounds) {
            $weekBounds[$weekRange] ??= $bounds;
        }

        uasort($weekBounds, fn (array $a, array $b) => $a[0] <=> $b[0]);

        $payrollWeeks = [];

        foreach ($weekBounds as $weekRange => [$weekOpens, $weekCloses]) {
            $weekGroup         = $recordsByWeek->get($weekRange, collect());
            $weeklyTotalSalary = 0;
            $employeeSummaries = [];

            // The bonus is a figure for the whole period, not for a day, so it
            // resolves once — on the last day of the week, the day the period
            // is paid. A bonus raised mid-week takes effect on the period that
            // ends after it, and never on one already paid.
            $weekRates = $this->ratesOn($weekCloses, $cfg);
            $weekBonus = $weekRates['bonus'] ?? 0;

            // Leave is credited only as far as the week has actually got, and
            // only for the days that were asked about.
            //
            // A day worked is paid when it is worked; a day off has to be the
            // same, or a week still running shows the whole of a leave signed
            // off to the Friday as already paid on the Wednesday. And the
            // range bounds it as well as the week does: asked for one day, a
            // week-long leave answered with the week's pay against that day.
            [$leaveOpens, $leaveThrough] = $this->leaveWindow($cfg, $weekOpens, $weekCloses);

            // The one-off grants that land inside this week. A grant names its
            // people, or says everybody — which is not the same as listing them,
            // because a list goes stale the day somebody is hired.
            $grants = array_filter(
                $cfg['bonusGrants'] ?? [],
                fn ($g) => $g['on'] >= $weekOpens && $g['on'] <= $weekCloses
            );

            $employeeGroups = $weekGroup->groupBy(fn ($item) => $item->employee_id);

            $empWeekRecords = null;
            foreach ($employeeGroups as $empId => $empWeekRecords) {
                $employee = null;
                $sumHours = $sumGross = $sumOvertime = $sumHoliday = $sumRestDay = $sumNightDiff = 0;
                $sumSss = $sumPhil = $sumPagibig = $sumTax = $sumAuto = 0;
                $sumVale = $sumManual = $sumNet = 0;
                $sumLate = 0;
                $empDates = [];
                $pricedDaily = 0.0;

                foreach ($empWeekRecords as $rec) {
                    if (!$rec->employee) continue;
                    $employee = $rec->employee;
                    if ($rec->time_in) {
                        $empDates[] = Carbon::parse($rec->date)->toDateString();
                    }
                    $r = $this->computeRecord($rec, $cfg);
                    $pricedDaily = $pricedDaily ?: (float) ($r["dailyRate"] ?? 0);

                    $sumHours    += $r['hours'];
                    $sumGross    += $r['gross'];
                    $sumOvertime += $r['otPay'];
                    $sumHoliday  += $r['holidayPay'];
                    $sumRestDay  += $r['restDayPay'];
                    $sumNightDiff += $r['nightDiffPay'];
                    $sumSss      += $r['sssDeduction'];
                    $sumPhil     += $r['philhealthDeduction'];
                    $sumPagibig  += $r['pagibigDeduction'];
                    $sumTax      += $r['withholdingTax'];
                    $sumAuto     += $r['autoDeductions'];
                    $sumVale     += $r['vale'];
                    $sumLate     += $r['lateMinutes'];
                    $sumManual   += $r['manualDeductions'];
                    $sumNet      += $r['net'];
                }

                if ($employee) {
                    // Approved leave landing in this week. Paid leave is
                    // wages: it is credited at the rate the week priced a
                    // worked day at — which the engine has already raised to
                    // the wage order's floor — so a day off is never worth
                    // less than a day on, and never less than the minimum.
                    // Unpaid leave is counted but pays nothing.
                    $leaveDays = $paidLeaveDays = 0.0;

                    foreach (($cfg['leave'][$empId] ?? []) as $filed) {
                        $d = $filed->daysWithin($leaveOpens, $leaveThrough);
                        $leaveDays += $d;
                        $paidLeaveDays += $filed->is_paid ? $d : 0;
                    }

                    $leaveRate = $this->dayRateOf($employee, $weekRates, $pricedDaily);
                    $leavePay  = round($paidLeaveDays * $leaveRate, 2);

                    // Income is income: a paid day off is contributed and
                    // withheld on exactly as a day worked is, so the leave
                    // carries its own share of SSS, PhilHealth, Pag-IBIG and
                    // tax rather than reaching the worker whole.
                    $leaveDed = $this->leaveDeductions(
                        $leaveRate, $paidLeaveDays, $weekRates,
                        (bool) $employee->isExcludedFromPayroll()
                    );

                    $sumSss     += $leaveDed['sss'];
                    $sumPhil    += $leaveDed['philhealth'];
                    $sumPagibig += $leaveDed['pagibig'];
                    $sumTax     += $leaveDed['tax'];
                    $sumAuto    += $leaveDed['total'];

                    $sumGross += $leavePay;
                    $sumNet   += $leavePay - $leaveDed['total'];

                    // Cash advances being collected this period. The vale
                    // summed above came off the days themselves; this is the
                    // instalment on a sum already handed over, so it is a
                    // figure for the week rather than for any one day.
                    [$advanceDue, $advanceTaken] = $this->advancesFor(
                        (int) $empId, $weekOpens, $weekStart, $cfg, $weekRates, $sumGross, $sumAuto, $sumVale
                    );

                    $sumVale += $advanceTaken;
                    $sumNet  -= $advanceTaken;

                    $totalDeductions = $sumAuto + $sumVale + $sumManual;

                    // The standing bonus, plus whatever this worker was granted
                    // for this period by name. Both are paid once per period,
                    // and both are added to net rather than to gross — a bonus
                    // is not wages, so nothing is withheld on it.
                    $empBonus = $weekBonus;

                    foreach ($grants as $grant) {
                        if ($grant['all'] || in_array($empId, $grant['employees'])) {
                            $empBonus += $grant['amount'];
                        }
                    }

                    $sumNet += $empBonus;

                    $employeeSummaries[] = [
                        'employee_id'         => $empId,
                        'shift'               => $empWeekRecords->first()->shift?->name,
                        'name'                => $employee->name,
                        'position'            => $employee->position ?? '',
                        'workdays'            => count(array_unique($empDates)),
                        'hours'               => round($sumHours, 2),
                        // Exact, where the hours above are rounded: screens
                        // write time worked from this.
                        'minutes'             => (int) round($sumHours * 60),
                        'gross'               => round($sumGross, 2),
                        'overtime'            => round($sumOvertime, 2),
                        'holidayPay'          => round($sumHoliday, 2),
                        'restDayPay'          => round($sumRestDay, 2),
                        'nightDiffPay'        => round($sumNightDiff, 2),
                        // What the week priced a day of theirs at. The weekly
                        // tables read this off a day worked; a week that is
                        // all leave has none, so the week carries it too.
                        'dailyRate'           => round($this->dayRateOf($employee, $weekRates, $pricedDaily), 2),
                        'leaveDays'           => round($leaveDays, 2),
                        'paidLeaveDays'       => round($paidLeaveDays, 2),
                        'leavePay'            => round($leavePay, 2),
                        'bonus'               => round($empBonus, 2),
                        'sssDeduction'        => round($sumSss, 2),
                        'philhealthDeduction' => round($sumPhil, 2),
                        'pagibigDeduction'    => round($sumPagibig, 2),
                        'withholdingTax'      => round($sumTax, 2),
                        'autoDeductions'      => round($sumAuto, 2),
                        'late_minutes'        => $sumLate,
                        'vale'                => round($sumVale, 2),
                        'vale_advance'        => round($advanceTaken, 2),
                        'vale_advance_due'    => round($advanceDue, 2),
                        'manualDeductions'    => round($sumManual, 2),
                        'totalDeductions'     => round($totalDeductions, 2),
                        'net'                 => round($sumNet, 2),
                    ];

                    $weeklyTotalSalary += $sumNet;
                }
            }

            // A worker on leave for the whole week has no attendance to group,
            // so nothing above reaches them — and a paid week off still has to
            // be paid. Their row is the leave and every other figure zero, so
            // they appear in Payroll Records where the office expects them
            // rather than vanishing from the week they were signed off for.
            foreach (($cfg['leave'] ?? []) as $leaveEmpId => $filed) {
                if ($employeeGroups->has($leaveEmpId)) {
                    continue;
                }

                $onLeave = $filed->first()?->employee;

                if (! $onLeave) {
                    continue;
                }

                $days = $paidDays = 0.0;

                foreach ($filed as $row) {
                    $d = $row->daysWithin($leaveOpens, $leaveThrough);
                    $days += $d;
                    $paidDays += $row->is_paid ? $d : 0;
                }

                if ($days <= 0) {
                    continue;
                }

                $dayRate = $this->dayRateOf($onLeave, $weekRates);
                $pay     = round($paidDays * $dayRate, 2);

                // The same contributions a worked week would carry: the week
                // pays them, so the week deducts on it.
                $ded = $this->leaveDeductions(
                    $dayRate, $paidDays, $weekRates,
                    (bool) $onLeave->isExcludedFromPayroll()
                );

                // A cash advance is collected from a week of leave the same as
                // from a week worked. It was not: this row carried the advance
                // at zero, while the schedule on the advance counted the week's
                // instalment as taken — so Leave & Advances showed a deduction
                // that Payroll Records, Payroll Processing and the payslip never
                // made. Both ask the same schedule now.
                [$advanceDue, $advanceTaken] = $this->advancesFor(
                    (int) $leaveEmpId, $weekOpens, $weekStart, $cfg, $weekRates, $pay, $ded['total'], 0.0
                );

                $net = round($pay - $ded['total'] - $advanceTaken, 2);

                $employeeSummaries[] = [
                    'employee_id'         => (int) $leaveEmpId,
                    'shift'               => $onLeave->shift?->name,
                    'name'                => $onLeave->name,
                    'position'            => $onLeave->position ?? '',
                    'dailyRate'           => round($dayRate, 2),
                    'leaveDays'           => round($days, 2),
                    'paidLeaveDays'       => round($paidDays, 2),
                    'leavePay'            => $pay,
                    'gross'               => $pay,
                    'sssDeduction'        => $ded['sss'],
                    'philhealthDeduction' => $ded['philhealth'],
                    'pagibigDeduction'    => $ded['pagibig'],
                    'withholdingTax'      => $ded['tax'],
                    'autoDeductions'      => $ded['total'],
                    'vale'                => round($advanceTaken, 2),
                    'vale_advance'        => round($advanceTaken, 2),
                    'vale_advance_due'    => round($advanceDue, 2),
                    'totalDeductions'     => round($ded['total'] + $advanceTaken, 2),
                    'net'                 => $net,
                ] + array_fill_keys([
                    'workdays', 'hours', 'minutes', 'overtime', 'holidayPay', 'restDayPay',
                    'nightDiffPay', 'bonus', 'late_minutes', 'manualDeductions',
                ], 0);

                $weeklyTotalSalary += $net;
            }

            $payrollWeeks[] = [
                'week_range'     => $weekRange,
                'total_payroll'  => round($weeklyTotalSalary, 2),
                'working_days'   => $empWeekRecords ? $empWeekRecords->count() : 0,
                'employee_count' => count($employeeSummaries),
                'details'        => $employeeSummaries,
            ];
        }

        return $payrollWeeks;
    }

    /**
     * Group records by day. Output shape matches the original $dailyPayroll exactly.
     *
     * A day of approved paid leave is a day on this list too. It is not an
     * attendance row and never will be — nothing writes one — so a worker
     * signed off for today simply had no row for today, which is the screen
     * the office opens when it asks why somebody is missing from the payroll.
     */
    private function groupByDay($records, array $cfg): array
    {
        $payrollByDay = $records->where('time_in', '!=', null)->groupBy('date');
        $dailyPayroll = [];

        // The rate each worker's days were priced at, so a leave day in the
        // same range is worth what a worked one was. Filled as the days are
        // walked and read afterwards, when the leave rows are added.
        $pricedDaily = [];

        foreach ($payrollByDay as $date => $dayRecords) {
            $dailyTotal = 0;
            $dayDetails = [];

            foreach ($dayRecords as $detail) {
                if (!$detail->employee) continue;
                $employee = $detail->employee;
                $r = $this->computeRecord($detail, $cfg);

                $dayDetails[] = [
                    'id'                  => $detail->id,
                    'employee_id'         => $detail->employee_id,
                    'name'                => $employee->name,
                    'shift'               => $r['shift']['name'] ?? null,
                    'hours'               => round($r['hours'], 2),
                    'minutes'             => (int) round($r['hours'] * 60),
                    'dailyRate'           => round((float) ($r['dailyRate'] ?? 0), 2),
                    'rate'                => round($r['rate'], 2),
                    'basicPay'            => round($r['basicPay'], 2),
                    'ot_hours'            => round($r['ot_hours'], 2),
                    // Exact, where ot_hours is rounded: a week added up from
                    // rounded days drifts by a minute (3 × 2.17 is not 6h 30m).
                    'ot_minutes'          => (int) round($r['ot_hours'] * 60),
                    'ot_rate'             => round($r['ot_rate'], 2),
                    'late_minutes'        => $r['lateMinutes'],
                    'otPay'               => round($r['otPay'], 2),
                    'holidayPay'          => round($r['holidayPay'], 2),
                    'restDayPay'          => round($r['restDayPay'], 2),
                    'nightDiffPay'        => round($r['nightDiffPay'], 2),
                    'bonus'               => round($this->ratesOn($date, $cfg)['bonus'] ?? 0, 2),
                    'is_holiday'          => $r['isHoliday'],
                    'holiday_type'        => $r['holidayType'],
                    'gross'               => round($r['gross'], 2),
                    'sssDeduction'        => round($r['sssDeduction'], 2),
                    'philhealthDeduction' => round($r['philhealthDeduction'], 2),
                    'pagibigDeduction'    => round($r['pagibigDeduction'], 2),
                    'withholdingTax'      => round($r['withholdingTax'], 2),
                    'autoDeductions'      => round($r['autoDeductions'], 2),
                    'vale'                => round($r['vale'], 2),
                    'manualDeductions'    => round($r['manualDeductions'], 2),
                    'totalDeductions'     => round($r['totalDeductions'], 2),
                    'net'                 => round($r['net'], 2),
                ];

                $pricedDaily[$detail->employee_id] ??= (float) ($r['dailyRate'] ?? 0);
                $dailyTotal += $r['net'];
            }

            $dailyPayroll[$date] = [
                'date'           => $date,
                'formatted_date' => Carbon::parse($date)->format('m/d/Y (l)'),
                'total'          => round($dailyTotal, 2),
                'details'        => $dayDetails,
            ];
        }

        foreach ($this->leaveByDay($cfg, $pricedDaily) as $date => $leaveRows) {
            $dailyPayroll[$date] ??= [
                'date'           => $date,
                'formatted_date' => Carbon::parse($date)->format('m/d/Y (l)'),
                'total'          => 0.0,
                'details'        => [],
            ];

            // After the days worked, not before: the weekly table reads a
            // worker's rate off the first row it finds for them, and a day
            // actually worked is the better answer where there is one.
            $dailyPayroll[$date]['details'] = array_merge($dailyPayroll[$date]['details'], $leaveRows);
            $dailyPayroll[$date]['total']   = round(
                $dailyPayroll[$date]['total'] + array_sum(array_column($leaveRows, 'net')), 2
            );
        }

        ksort($dailyPayroll);

        return array_values($dailyPayroll);
    }

    /**
     * Approved leave as day rows, keyed by date.
     *
     * One row per worker per day off, priced and deducted exactly as the week
     * prices it — the same day rate, the same contributions worked out on
     * that rate — so the day view and the week view cannot disagree. Days
     * still to come are left out: leave is paid as it comes round.
     *
     * @param  array<int, float>  $pricedDaily  what a worked day cost, per employee
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function leaveByDay(array $cfg, array $pricedDaily): array
    {
        $from = (string) ($cfg['range']['from'] ?? '');
        $to   = (string) ($cfg['range']['to'] ?? '');

        if ($from === '' || $to === '') {
            return [];
        }

        $through = min($to, Carbon::now('Asia/Manila')->toDateString());
        $byDate  = [];

        foreach (($cfg['leave'] ?? []) as $empId => $filed) {
            $employee = $filed->first()?->employee;

            if (! $employee) {
                continue;
            }

            foreach ($filed as $row) {
                $first  = max($row->starts_on->toDateString(), $from);
                $last   = min($row->ends_on->toDateString(), $through);
                $cursor = Carbon::parse($first);

                while ($cursor->toDateString() <= $last) {
                    $date = $cursor->toDateString();
                    $days = $row->daysWithin($date, $date);

                    if ($days > 0) {
                        $byDate[$date][] = $this->leaveDayRow(
                            $employee, $row, $date, $days, $cfg, $pricedDaily[$empId] ?? 0.0
                        );
                    }

                    $cursor->addDay();
                }
            }
        }

        return $byDate;
    }

    /**
     * One day of leave, in the shape every other day row has.
     *
     * Every figure a worked day carries is here and at zero, because the
     * screens reading this list index straight into them. What the day is
     * worth sits on its own keys, so nothing mistakes a day off for hours.
     *
     * @return array<string, mixed>
     */
    private function leaveDayRow($employee, LeaveRequest $filed, string $date, float $days, array $cfg, float $priced): array
    {
        $rates = $this->ratesOn($date, $cfg);
        $rate  = $this->dayRateOf($employee, $rates, $priced);
        $paid  = $filed->is_paid ? $days : 0.0;
        $pay   = round($paid * $rate, 2);

        $ded = $this->leaveDeductions($rate, $paid, $rates, (bool) $employee->isExcludedFromPayroll());

        // The hourly the day rate works out at over this shift. Zero would be
        // a lie the receipt repeats: it reads the hourly off whichever row it
        // finds for a worker, and that may well be this one.
        $schedule = $employee->shift?->schedule();
        $hours    = $schedule && WorkSchedule::has($schedule) ? max(1.0, WorkSchedule::paidHours($schedule)) : 8.0;

        return [
            // Not an attendance row and never keyed as one: the callers that
            // index these by id are matching them back to attendance.
            'id'                  => 'leave-' . $filed->id . '-' . $date,
            'employee_id'         => (int) $employee->id,
            'name'                => $employee->name,
            'shift'               => $employee->shift?->name,
            'leave'               => true,
            'leave_type'          => $filed->type_label,
            'leave_paid'          => (bool) $filed->is_paid,
            'leaveDays'           => round($days, 2),
            'leavePay'            => $pay,
            'dailyRate'           => round($rate, 2),
            'rate'                => round($rate / $hours, 2),
            'bonus'               => round($rates['bonus'] ?? 0, 2),
            'is_holiday'          => false,
            'holiday_type'        => null,
            'gross'               => $pay,
            'sssDeduction'        => $ded['sss'],
            'philhealthDeduction' => $ded['philhealth'],
            'pagibigDeduction'    => $ded['pagibig'],
            'withholdingTax'      => $ded['tax'],
            'autoDeductions'      => $ded['total'],
            'totalDeductions'     => $ded['total'],
            'net'                 => round($pay - $ded['total'], 2),
        ] + array_fill_keys([
            'hours', 'minutes', 'basicPay', 'ot_hours', 'ot_minutes', 'ot_rate',
            'late_minutes', 'otPay', 'holidayPay', 'restDayPay', 'nightDiffPay',
            'vale', 'manualDeductions',
        ], 0);
    }

    /**
     * Pivot the per-employee-per-week summaries into an employee-centric list.
     * No new math — purely re-aggregates the weekly $details by employee.
     */
    private function pivotByEmployee(array $weeks): array
    {
        $employees = [];

        foreach ($weeks as $week) {
            foreach ($week['details'] as $d) {
                $id = $d['employee_id'];

                if (!isset($employees[$id])) {
                    $employees[$id] = [
                        'employee_id' => $id,
                        'name'        => $d['name'],
                        'position'    => $d['position'] ?? '',
                        'periods'     => [],
                        'totals'      => [
                            'workdays'        => 0,
                            'hours'           => 0,
                            'minutes'         => 0,
                            'gross'           => 0,
                            'overtime'        => 0,
                            'holidayPay'      => 0,
                            'restDayPay'      => 0,
                            'nightDiffPay'    => 0,
                            'leaveDays'       => 0,
                            'paidLeaveDays'   => 0,
                            'leavePay'        => 0,
                            'bonus'           => 0,
                            'totalDeductions' => 0,
                            'net'             => 0,
                        ],
                    ];
                }

                $period = $d;
                $period['week_range'] = $week['week_range'];
                $employees[$id]['periods'][] = $period;

                $employees[$id]['totals']['workdays']        += $d['workdays'];
                $employees[$id]['totals']['hours']           += $d['hours'];
                $employees[$id]['totals']['minutes']         += $d['minutes'];
                $employees[$id]['totals']['gross']           += $d['gross'];
                $employees[$id]['totals']['overtime']        += $d['overtime'];
                $employees[$id]['totals']['holidayPay']      += $d['holidayPay'];
                $employees[$id]['totals']['restDayPay']      += $d['restDayPay'];
                $employees[$id]['totals']['nightDiffPay']    += $d['nightDiffPay'];
                $employees[$id]['totals']['leaveDays']       += $d['leaveDays'] ?? 0;
                $employees[$id]['totals']['paidLeaveDays']   += $d['paidLeaveDays'] ?? 0;
                $employees[$id]['totals']['leavePay']        += $d['leavePay'] ?? 0;
                $employees[$id]['totals']['bonus']           += $d['bonus'];
                $employees[$id]['totals']['totalDeductions'] += $d['totalDeductions'];
                $employees[$id]['totals']['net']             += $d['net'];
            }
        }

        foreach ($employees as &$emp) {
            foreach ($emp['totals'] as $k => $v) {
                $emp['totals'][$k] = round($v, 2);
            }
            $emp['totals']['workdays'] = (int) $emp['totals']['workdays'];
            $emp['totals']['minutes']  = (int) $emp['totals']['minutes'];
        }
        unset($emp);

        $employees = array_values($employees);
        usort($employees, fn ($a, $b) => strcasecmp($a['name'], $b['name']));

        return $employees;
    }
}
