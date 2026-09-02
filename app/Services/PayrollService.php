<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Setting;
use App\Models\Holiday;
use App\Models\PayrollRate;
use App\Models\SystemSetting;
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
     * Resolve the configurable payroll settings + holiday overlay once.
     */
    public function config(): array
    {
        $settings = Setting::first();

        return [
            // Multipliers, the wage floor, the period bonus and the
            // contribution rates are all dated. A change adds a row rather than
            // editing one, so a period reopened next year still computes at the
            // numbers that applied then. Loaded once as a timeline and resolved
            // per attendance date — a query per record would be thousands.
            'rateTimeline'   => PayrollRate::timeline(),
            'sundayRestDay'  => $settings?->sunday_rest_day_enabled ?? true,
            // Day-type multipliers are fixed by PH labor law, not configurable.
            // See doleFactors() for the whole table.
            'holidayTypeMap' => Holiday::typeMap(),   // 'Y-m-d' => 'regular'|'special'|'custom'

            // The shape of a working day. Not dated: these describe how the
            // office runs rather than what a circular says it must pay, and a
            // shift that starts at 8 has always started at 8 as far as payroll
            // is concerned.
            'day' => SystemSetting::current(),
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
     * Hours worked between 10 PM and 6 AM, which carry the night differential.
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
                if ($h >= 22 || $h < 6) $minutes++;
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

        $query = Attendance::with('employee');
        if ($from && $to) {
            $query->whereBetween('date', [$from, $to]);
        } elseif ($from) {
            $query->where('date', '>=', $from);
        } elseif ($to) {
            $query->where('date', '<=', $to);
        }
        $records = $query->get();

        $weeks = $this->groupByWeek($records, $cfg);

        return [
            'weeks'     => $weeks,
            'days'      => $this->groupByDay($records, $cfg),
            'employees' => $this->pivotByEmployee($weeks),
        ];
    }

    /**
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
                $timeIn  = is_string($rec->time_in) ? Carbon::parse($rec->time_in) : $rec->time_in;
                $timeOut = is_string($rec->time_out) ? Carbon::parse($rec->time_out) : $rec->time_out;

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

        // Round worked hours to 2 decimals BEFORE computing pay so the
        // displayed hours reconcile exactly with gross (hours × rate = gross).
        $hours         = round(max(0, $hours), 2);
        $regular_hours = $autoOt ? min($standard, $hours) : $hours;
        $ot_hours      = $autoOt ? max(0, $hours - $standard) : 0.0;

        // The multipliers in force on the day being computed, not today's.
        $dateStr = Carbon::parse($rec->date)->toDateString();
        $rates   = $this->ratesOn($dateStr, $cfg);

        [$nightRegularHours, $nightOtHours] = ($hours > 0 && $rec->time_in)
            ? $this->nightHours(Carbon::parse($rec->time_in), $hours)
            : [0.0, 0.0];
        $night_hours = round($nightRegularHours + $nightOtHours, 2);

        // Hourly rate comes from the CONFIGURED labor-type daily rate (÷ 8),
        // which is the source of truth. We fall back to the stored
        // rate_per_hour only for employees without a labor type. This keeps
        // gross aligned with the rate configured in Settings even if an
        // employee's cached rate_per_hour has drifted.
        $configured = $employee->laborType?->daily_rate;
        $dailyRate  = $configured !== null
            ? (float) $configured
            : ((float) ($employee->rate_per_hour ?? 0)) * $standard;

        // The wage order's daily floor for that day, if one is on file. A
        // labour type still carrying last year's rate is paid at the floor
        // rather than below it. The floor is dated like everything else, so
        // raising it never reaches back into a period already paid.
        $wageFloor = $rates['daily_rate'] ?? null;
        if ($wageFloor !== null && $wageFloor > $dailyRate) {
            $dailyRate = (float) $wageFloor;
        }

        $rate    = $dailyRate / $standard;
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

        // Sunday rest day. A frozen per-record decision (rest_day_applied) wins
        // so past Sundays never recalculate when the global setting is toggled;
        // null means "follow the current global setting" (current week + future).
        $isSunday    = Carbon::parse($rec->date)->dayOfWeek === Carbon::SUNDAY;
        $applyRest   = $rec->rest_day_applied !== null ? (bool) $rec->rest_day_applied : $cfg['sundayRestDay'];
        $isRestDay   = $isSunday && $applyRest;

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

        if ($rec->time_in && $day) {
            $in       = Carbon::parse($rec->time_in);
            $expected = Carbon::parse($rec->date)->setTimeFromTimeString((string) $day->expected_time_in);
            $allowed  = $expected->copy()->addMinutes((int) $day->grace_period_minutes);

            $actual = $expected->copy()->setTime((int) $in->format('G'), (int) $in->format('i'), 0);

            if ($actual->greaterThan($allowed)) {
                $lateMinutes = (int) round($expected->diffInMinutes($actual));
            }
        }

        return compact(
            'hours', 'regular_hours', 'ot_hours', 'night_hours', 'lateMinutes', 'rate', 'ot_rate', 'basicPay', 'otPay',
            'dayEarnings', 'isHoliday', 'holidayType', 'hMultiplier', 'holidayPay', 'isSunday', 'restDayPay',
            'nightDiffPay', 'rates',
            'gross', 'dailyRate',
            'sssDeduction', 'philhealthDeduction', 'pagibigDeduction', 'withholdingTax', 'autoDeductions',
            'vale', 'manualDeductions', 'totalDeductions', 'net'
        );
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

        $payrollWeeks = [];

        foreach ($recordsByWeek as $weekRange => $weekGroup) {
            $weeklyTotalSalary = 0;
            $employeeSummaries = [];

            // The bonus is a figure for the whole period, not for a day, so it
            // resolves once — on the last day of the week, the day the period
            // is paid. A bonus raised mid-week takes effect on the period that
            // ends after it, and never on one already paid.
            $weekBonus = $this->ratesOn(
                Carbon::parse($weekGroup->first()->date)->endOfWeek($weekEnd)->toDateString(),
                $cfg
            )['bonus'] ?? 0;

            $employeeGroups = $weekGroup->groupBy(fn ($item) => $item->employee_id);

            $empWeekRecords = null;
            foreach ($employeeGroups as $empId => $empWeekRecords) {
                $employee = null;
                $sumHours = $sumGross = $sumOvertime = $sumHoliday = $sumRestDay = $sumNightDiff = 0;
                $sumSss = $sumPhil = $sumPagibig = $sumTax = $sumAuto = 0;
                $sumVale = $sumManual = $sumNet = 0;
                $empDates = [];

                foreach ($empWeekRecords as $rec) {
                    if (!$rec->employee) continue;
                    $employee = $rec->employee;
                    if ($rec->time_in) {
                        $empDates[] = Carbon::parse($rec->date)->toDateString();
                    }
                    $r = $this->computeRecord($rec, $cfg);

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
                    $sumManual   += $r['manualDeductions'];
                    $sumNet      += $r['net'];
                }

                if ($employee) {
                    $totalDeductions = $sumAuto + $sumVale + $sumManual;

                    // Flat bonus applied once per employee per pay period (week)
                    $empBonus = $weekBonus;
                    $sumNet  += $empBonus;

                    $employeeSummaries[] = [
                        'employee_id'         => $empId,
                        'name'                => $employee->name,
                        'position'            => $employee->position ?? '',
                        'workdays'            => count(array_unique($empDates)),
                        'hours'               => round($sumHours, 2),
                        'gross'               => round($sumGross, 2),
                        'overtime'            => round($sumOvertime, 2),
                        'holidayPay'          => round($sumHoliday, 2),
                        'restDayPay'          => round($sumRestDay, 2),
                        'nightDiffPay'        => round($sumNightDiff, 2),
                        'bonus'               => round($empBonus, 2),
                        'sssDeduction'        => round($sumSss, 2),
                        'philhealthDeduction' => round($sumPhil, 2),
                        'pagibigDeduction'    => round($sumPagibig, 2),
                        'withholdingTax'      => round($sumTax, 2),
                        'autoDeductions'      => round($sumAuto, 2),
                        'vale'                => round($sumVale, 2),
                        'manualDeductions'    => round($sumManual, 2),
                        'totalDeductions'     => round($totalDeductions, 2),
                        'net'                 => round($sumNet, 2),
                    ];

                    $weeklyTotalSalary += $sumNet;
                }
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
     */
    private function groupByDay($records, array $cfg): array
    {
        $payrollByDay = $records->where('time_in', '!=', null)->groupBy('date');
        $dailyPayroll = [];

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
                    'hours'               => round($r['hours'], 2),
                    'dailyRate'           => $r['dailyRate'] !== null ? round($r['dailyRate'], 2) : round($r['rate'] * 8, 2),
                    'rate'                => round($r['rate'], 2),
                    'basicPay'            => round($r['basicPay'], 2),
                    'ot_hours'            => round($r['ot_hours'], 2),
                    'ot_rate'             => round($r['ot_rate'], 2),
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

                $dailyTotal += $r['net'];
            }

            $dailyPayroll[] = [
                'date'           => $date,
                'formatted_date' => Carbon::parse($date)->format('m/d/Y (l)'),
                'total'          => round($dailyTotal, 2),
                'details'        => $dayDetails,
            ];
        }

        return $dailyPayroll;
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
                            'gross'           => 0,
                            'overtime'        => 0,
                            'holidayPay'      => 0,
                            'restDayPay'      => 0,
                            'nightDiffPay'    => 0,
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
                $employees[$id]['totals']['gross']           += $d['gross'];
                $employees[$id]['totals']['overtime']        += $d['overtime'];
                $employees[$id]['totals']['holidayPay']      += $d['holidayPay'];
                $employees[$id]['totals']['restDayPay']      += $d['restDayPay'];
                $employees[$id]['totals']['nightDiffPay']    += $d['nightDiffPay'];
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
        }
        unset($emp);

        $employees = array_values($employees);
        usort($employees, fn ($a, $b) => strcasecmp($a['name'], $b['name']));

        return $employees;
    }
}
