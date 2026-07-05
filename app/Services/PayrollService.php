<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Setting;
use App\Models\Holiday;
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
            'sss'            => $settings->sss ?? 0,
            'philhealth'     => $settings->philhealth ?? 0,
            'pagibig'        => $settings->pagibig ?? 0,
            'otMultiplier'   => $settings?->ot_multiplier ?? 1.25,
            'bonus'          => $settings?->bonus ?? 0,
            'sundayRestDay'  => $settings?->sunday_rest_day_enabled ?? true,
            // Per-type multipliers (fixed by PH labor law; not admin-configurable):
            //   Regular holidays → 200% (full premium = day earnings × 1.0 extra)
            //   Special (non-working) holidays → 130% (premium = day earnings × 0.3 extra)
            //   Custom holidays default to Regular (200%)
            'holidayTypeMap' => Holiday::typeMap(),   // 'Y-m-d' => 'regular'|'special'|'custom'
        ];
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
                // abs() — Carbon 3's diffInMinutes is signed; without it the value
                // is negative (time-in is earlier) and collapses to 0 hours.
                $hours   = abs($timeIn->diffInMinutes($timeOut)) / 60;
            } catch (\Exception $e) {
                Log::warning("Payroll: Failed to parse time for attendance {$rec->id}", [
                    'time_in'  => $rec->time_in,
                    'time_out' => $rec->time_out,
                    'error'    => $e->getMessage(),
                ]);
                $hours = 0;
            }
        }

        // Round worked hours to 2 decimals BEFORE computing pay so the
        // displayed hours reconcile exactly with gross (hours × rate = gross).
        $hours         = round(max(0, $hours), 2);
        $regular_hours = min(8, $hours);
        $ot_hours      = max(0, $hours - 8);

        // Hourly rate comes from the CONFIGURED labor-type daily rate (÷ 8),
        // which is the source of truth. We fall back to the stored
        // rate_per_hour only for employees without a labor type. This keeps
        // gross aligned with the rate configured in Settings even if an
        // employee's cached rate_per_hour has drifted.
        $dailyRate = $employee->laborType?->daily_rate;
        $rate      = $dailyRate !== null ? ($dailyRate / 8) : (float) ($employee->rate_per_hour ?? 0);
        $ot_rate   = $rate * $cfg['otMultiplier'];

        $basicPay    = $regular_hours * $rate;
        $otPay       = $ot_hours * $ot_rate;
        $dayEarnings = $basicPay + $otPay;

        // Holiday premium — rate depends on holiday type (PH labor law):
        //   Regular → 200% total pay (premium = earnings × 1.0)
        //   Special (non-working) → 130% total pay (premium = earnings × 0.3)
        //   Custom → treated as Regular (200%)
        $dateStr     = Carbon::parse($rec->date)->toDateString();
        $holidayType = $cfg['holidayTypeMap'][$dateStr] ?? null;
        $isHoliday   = $holidayType !== null;
        $hMultiplier = match ($holidayType) {
            'special' => 1.3,
            default   => 2.0,  // regular + custom
        };
        $holidayPay  = $isHoliday ? $dayEarnings * ($hMultiplier - 1) : 0;

        // Sunday rest day premium (130% of day earnings = +30% on top).
        // Applies even when the day is also a holiday (stacks per PH labor law).
        // A frozen per-record decision (rest_day_applied) wins so past Sundays
        // never recalculate when the global setting is toggled; null means
        // "follow the current global setting" (current week + future).
        $isSunday    = Carbon::parse($rec->date)->dayOfWeek === Carbon::SUNDAY;
        $applyRest   = $rec->rest_day_applied !== null ? (bool) $rec->rest_day_applied : $cfg['sundayRestDay'];
        $restDayPay  = ($isSunday && $applyRest) ? $dayEarnings * 0.30 : 0;

        $gross       = $dayEarnings + $holidayPay + $restDayPay;

        // Statutory deductions are computed on GROSS pay (not the daily rate).
        $sssDeduction        = ($gross * $cfg['sss']) / 100;
        $philhealthDeduction = ($gross * $cfg['philhealth']) / 100;
        $pagibigDeduction    = ($gross * $cfg['pagibig']) / 100;
        $autoDeductions      = $sssDeduction + $philhealthDeduction + $pagibigDeduction;

        $vale             = is_numeric($rec->vale) ? $rec->vale : 0;
        $manualDeductions = is_numeric($rec->deductions) ? $rec->deductions : 0;

        $totalDeductions = $autoDeductions + $vale + $manualDeductions;
        $net             = $gross - $totalDeductions;

        return compact(
            'hours', 'regular_hours', 'ot_hours', 'rate', 'ot_rate', 'basicPay', 'otPay',
            'dayEarnings', 'isHoliday', 'holidayType', 'hMultiplier', 'holidayPay', 'isSunday', 'restDayPay',
            'gross', 'dailyRate',
            'sssDeduction', 'philhealthDeduction', 'pagibigDeduction', 'autoDeductions',
            'vale', 'manualDeductions', 'totalDeductions', 'net'
        );
    }

    /**
     * Group records by week (Sunday start), then by employee within the week.
     * Output shape matches the original $payrollWeeks exactly.
     */
    private function groupByWeek($records, array $cfg): array
    {
        // Weeks run Monday–Sunday across the whole system.
        $recordsByWeek = $records->groupBy(function ($item) {
            $start = Carbon::parse($item->date)->startOfWeek(Carbon::MONDAY)->format('m/d/Y');
            $end   = Carbon::parse($item->date)->endOfWeek(Carbon::SUNDAY)->format('m/d/Y');
            return "$start - $end";
        });

        $payrollWeeks = [];

        foreach ($recordsByWeek as $weekRange => $weekGroup) {
            $weeklyTotalSalary = 0;
            $employeeSummaries = [];

            $employeeGroups = $weekGroup->groupBy(fn ($item) => $item->employee_id);

            $empWeekRecords = null;
            foreach ($employeeGroups as $empId => $empWeekRecords) {
                $employee = null;
                $sumHours = $sumGross = $sumOvertime = $sumHoliday = $sumRestDay = 0;
                $sumSss = $sumPhil = $sumPagibig = $sumAuto = 0;
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
                    $sumSss      += $r['sssDeduction'];
                    $sumPhil     += $r['philhealthDeduction'];
                    $sumPagibig  += $r['pagibigDeduction'];
                    $sumAuto     += $r['autoDeductions'];
                    $sumVale     += $r['vale'];
                    $sumManual   += $r['manualDeductions'];
                    $sumNet      += $r['net'];
                }

                if ($employee) {
                    $totalDeductions = $sumAuto + $sumVale + $sumManual;

                    // Flat bonus applied once per employee per pay period (week)
                    $empBonus = $cfg['bonus'];
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
                        'bonus'               => round($empBonus, 2),
                        'sssDeduction'        => round($sumSss, 2),
                        'philhealthDeduction' => round($sumPhil, 2),
                        'pagibigDeduction'    => round($sumPagibig, 2),
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
                    'bonus'               => round($cfg['bonus'], 2),
                    'is_holiday'          => $r['isHoliday'],
                    'holiday_type'        => $r['holidayType'],
                    'gross'               => round($r['gross'], 2),
                    'sssDeduction'        => round($r['sssDeduction'], 2),
                    'philhealthDeduction' => round($r['philhealthDeduction'], 2),
                    'pagibigDeduction'    => round($r['pagibigDeduction'], 2),
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
