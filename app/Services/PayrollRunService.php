<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Loan;
use App\Models\LoanDeduction;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use Illuminate\Support\Facades\DB;

/**
 * Turns a payroll period into frozen per-employee figures.
 *
 * This service does NOT compute payroll. PayrollService::computeForRange()
 * remains the single source of that arithmetic — the same code the existing
 * Payroll Records screen has always used — and is called here unchanged. What
 * this adds is everything around it:
 *
 *   1. asks PayrollService for the period,
 *   2. layers on the things it has no way to know about: approved paid
 *      leave and cash advance instalments due,
 *   3. writes the result down, so the numbers stop moving.
 *
 * Recalculating replaces a run's items wholesale. Collecting against advances
 * is deliberately NOT done here — that happens once, at finalisation, so a run
 * recalculated five times does not collect five instalments.
 */
class PayrollRunService
{
    public function __construct(private PayrollService $payroll)
    {
    }

    /**
     * Compute the run and replace its items. Safe to call repeatedly.
     */
    public function calculate(PayrollRun $run): PayrollRun
    {
        $rows = $this->rows($run);

        DB::transaction(function () use ($run, $rows) {
            $run->items()->delete();

            foreach ($rows as $row) {
                PayrollRunItem::create($row);
            }

            $run->update([
                'status'           => $run->status === 'draft' ? 'calculated' : $run->status,
                'total_gross'      => round(array_sum(array_column($rows, 'gross_pay')), 2),
                'total_deductions' => round(array_sum(array_column($rows, 'total_deductions')), 2),
                'total_net'        => round(array_sum(array_column($rows, 'net_pay')), 2),
                'employee_count'   => count($rows),
                'calculated_at'    => now(),
            ]);
        });

        return $run->fresh('items');
    }

    /**
     * What calculate() would write for a period, without writing it.
     *
     * The Payroll Processing page shows a period nobody has processed yet this
     * way, so the figures the office reviews are the figures that get frozen —
     * one computation, not a preview that could disagree with the run.
     *
     * @return list<array<string, mixed>>
     */
    public function preview(string $from, string $to): array
    {
        return $this->rows(new PayrollRun(['period_start' => $from, 'period_end' => $to]));
    }

    /**
     * One row per worker the period touches, in the shape of a run item.
     *
     * @return list<array<string, mixed>>
     */
    private function rows(PayrollRun $run): array
    {
        $from = $run->period_start->toDateString();
        $to   = $run->period_end->toDateString();

        // The existing engine, untouched, on the run's own range.
        $computed = $this->payroll->computeForRange($from, $to);
        $byEmployee = collect($computed['employees'] ?? [])->keyBy('employee_id');

        $otHours  = $this->overtimeHours($computed['days'] ?? []);
        $rates    = $this->ratesPriced($computed['days'] ?? []);
        $leave    = $this->approvedLeave($from, $to);
        $advances = $this->collectibleAdvances($to);

        // Everyone the period touches: worked, or has approved leave. A worker
        // with only leave still needs a payslip.
        $ids = $byEmployee->keys()
            ->merge($leave->keys())
            ->unique();

        $employees = Employee::with('site')
            ->registered()
            ->whereIn('id', $ids)
            ->when($run->site_id, fn ($q) => $q->where('site_id', $run->site_id))
            ->get()
            ->keyBy('id');

        $rows = [];

        foreach ($employees as $id => $employee) {
            $rows[] = $this->buildItem(
                $run,
                $employee,
                $byEmployee->get($id),
                $otHours[$id] ?? 0.0,
                $rates[$id] ?? null,
                $leave->get($id, collect()),
                $advances->get($id, collect())
            );
        }

        return $rows;
    }

    /**
     * One worker's row. Everything PayrollService produced is carried across
     * verbatim; only the two things it cannot see — leave and cash advances —
     * are added on top.
     */
    private function buildItem(
        PayrollRun $run,
        Employee $employee,
        ?array $computed,
        float $otHours,
        ?array $rate,
        $leaveRows,
        $advanceRows
    ): array {
        $t     = $computed['totals'] ?? [];
        $weeks = collect($computed['periods'] ?? []);

        // The rate the engine priced the days at. rate_per_hour is a cached
        // figure that can drift from the labour type's daily rate the engine
        // actually uses, and a payslip that reads "hours × rate" has to name
        // the rate that was used. A worker with only leave has no priced day
        // and falls back to it.
        $dailyRate  = $rate['daily']  ?? (float) ($employee->rate_per_hour * 8);
        $hourlyRate = $rate['hourly'] ?? (float) $employee->rate_per_hour;

        // ── Earnings from attendance, as the engine computed them ─────────
        $basic      = (float) ($t['gross'] ?? 0);
        $holiday    = (float) ($t['holidayPay'] ?? 0);
        $restDay    = (float) ($t['restDayPay'] ?? 0);
        $nightDiff  = (float) ($t['nightDiffPay'] ?? 0);
        $bonus      = (float) ($t['bonus'] ?? 0);
        $engineOt   = (float) ($t['overtime'] ?? 0);

        // The engine's gross already contains its own overtime, holiday, rest
        // day and night differential. Basic is what is left once those are
        // taken back out, so the payslip can show them as separate lines
        // without counting any peso twice.
        //
        // The bonus is not in that gross — the engine adds it to net, a bonus
        // not being wages — so it is not taken out. It used to be, which cut
        // basic pay by the bonus and then added it back as a line of its own:
        // the payslip showed a bonus that the net never paid.
        $basicOnly = round($basic - $engineOt - $holiday - $restDay - $nightDiff, 2);
        $basicOnly = max($basicOnly, 0);

        // ── Approved paid leave, credited at the daily rate ───────────────
        $paidLeaveDays = 0.0;
        $leaveDays     = 0.0;
        foreach ($leaveRows as $row) {
            $d = $row->daysWithin($run->period_start->toDateString(), $run->period_end->toDateString());
            $leaveDays += $d;
            if ($row->is_paid) {
                $paidLeaveDays += $d;
            }
        }
        $leavePay = round($paidLeaveDays * $dailyRate, 2);

        // ── Deductions the engine already applied, line by line ───────────
        // Each week of its answer carries the statutory split, so the run adds
        // those up rather than carrying one lump: a contribution is remitted
        // to its own agency, and a lump cannot be. (The totals never carried a
        // vale at all, which is why it is read off the weeks too.)
        $sss   = round((float) $weeks->sum('sssDeduction'), 2);
        $phil  = round((float) $weeks->sum('philhealthDeduction'), 2);
        $pag   = round((float) $weeks->sum('pagibigDeduction'), 2);
        $tax   = round((float) $weeks->sum('withholdingTax'), 2);
        $vale  = round((float) $weeks->sum('vale'), 2);
        $other = round((float) $weeks->sum('manualDeductions'), 2);

        // ── Cash advance instalments due this period ──────────────────────
        $advanceDue = 0.0;
        foreach ($advanceRows as $advance) {
            $advanceDue += $advance->installmentFor($run->period_end->toDateString());
        }

        $grossPay = round(
            $basicOnly + $engineOt + $holiday + $restDay
            + $nightDiff + $leavePay + $bonus,
            2
        );

        $totalDeductions = round($sss + $phil + $pag + $tax + $vale + $other + $advanceDue, 2);

        return [
            'payroll_run_id' => $run->id,
            'employee_id'    => $employee->id,
            'employee_name'  => $employee->name,
            'position'       => $employee->position,
            'site_id'        => $employee->site_id,
            'daily_rate'     => round($dailyRate, 2),
            'hourly_rate'    => round($hourlyRate, 2),

            'days_worked'     => (float) ($t['workdays'] ?? 0),
            'regular_hours'   => (float) ($t['hours'] ?? 0),
            'ot_hours'        => round($otHours, 2),
            'late_minutes'    => (int) $weeks->sum('late_minutes'),
            'absent_days'     => 0,
            'leave_days'      => round($leaveDays, 2),
            'paid_leave_days' => round($paidLeaveDays, 2),

            'basic_pay'      => $basicOnly,
            'overtime_pay'   => round($engineOt, 2),
            'holiday_pay'    => $holiday,
            'rest_day_pay'   => $restDay,
            'night_diff_pay' => $nightDiff,
            'leave_pay'      => $leavePay,
            'bonus'          => $bonus,
            'other_earnings' => 0,

            'sss'               => $sss,
            'philhealth'        => $phil,
            'pagibig'           => $pag,
            'tax'               => $tax,
            'vale'              => round($vale, 2),
            // Loans are no longer issued. The column keeps what older runs
            // charged, and collectLoans() still settles those at finalisation.
            'loan_deduction'    => 0,
            'advance_deduction' => round($advanceDue, 2),
            'other_deductions'  => $other,

            'gross_pay'        => $grossPay,
            'total_deductions' => $totalDeductions,
            'net_pay'          => round($grossPay - $totalDeductions, 2),
        ];
    }

    /**
     * Collect the instalments this run charged. Called once, from
     * finalisation, so recalculating a run never collects twice.
     *
     * Each instalment is taken from its own kind. A run charges cash advances
     * only now, but a loan issued before that may still be open, and taking an
     * advance's instalment from whichever row is oldest would pay the loan
     * down with it. A run calculated while loans were still charged carries a
     * loan instalment too, and that one still settles its loan.
     */
    public function collectLoans(PayrollRun $run): int
    {
        $collected = 0;

        DB::transaction(function () use ($run, &$collected) {
            foreach ($run->items as $item) {
                $owed = ['loan' => $item->loan_deduction, Loan::ADVANCE => $item->advance_deduction];

                foreach ($owed as $type => $due) {
                    if ($due <= 0) {
                        continue;
                    }

                    $rows = Loan::collectible()
                        ->where('employee_id', $item->employee_id)
                        ->where('type', $type)
                        ->orderBy('issued_on')
                        ->get();

                    foreach ($rows as $loan) {
                        if ($due <= 0) {
                            break;
                        }

                        $take = min($loan->installmentFor($run->period_end->toDateString()), $due, $loan->balance);
                        if ($take <= 0) {
                            continue;
                        }

                        LoanDeduction::create([
                            'loan_id'        => $loan->id,
                            'payroll_run_id' => $run->id,
                            'amount'         => $take,
                            'deducted_on'    => $run->period_end->toDateString(),
                            'note'           => 'Collected by payroll run ' . $run->code,
                        ]);

                        $loan->balance = round($loan->balance - $take, 2);
                        if ($loan->balance <= 0) {
                            $loan->balance = 0;
                            $loan->status  = 'paid';
                        }
                        $loan->save();

                        $due -= $take;
                        $collected++;
                    }
                }
            }
        });

        return $collected;
    }

    /**
     * Overtime hours per employee, as attendance counted them — the time past
     * each shift's regular hours. The engine's totals carry the overtime pay
     * but not the hours, so they are summed from its days.
     *
     * Overtime used to be claimed by hand as well, and approved claims were
     * added here on top of this. Claims are retired: overtime is counted, not
     * filed, and a claim could only pay the same evening twice. Runs already
     * finalised keep the figures they were frozen with.
     *
     * @return array<int, float> employee_id => hours
     */
    private function overtimeHours(array $days): array
    {
        $hours = [];

        foreach ($days as $day) {
            foreach ($day['details'] ?? [] as $d) {
                $id         = (int) $d['employee_id'];
                $hours[$id] = ($hours[$id] ?? 0.0) + (isset($d['ot_minutes'])
                    ? $d['ot_minutes'] / 60
                    : (float) ($d['ot_hours'] ?? 0));
            }
        }

        return $hours;
    }

    /**
     * The rate each worker's days were priced at, off the last day of theirs
     * the engine priced in the period.
     *
     * @return array<int, array{daily: float, hourly: float}>
     */
    private function ratesPriced(array $days): array
    {
        $rates = [];

        foreach ($days as $day) {
            foreach ($day['details'] ?? [] as $d) {
                $rates[(int) $d['employee_id']] = [
                    'daily'  => (float) ($d['dailyRate'] ?? 0),
                    'hourly' => (float) ($d['rate'] ?? 0),
                ];
            }
        }

        return $rates;
    }

    /** Approved leave touching the range, grouped per employee. */
    private function approvedLeave(string $from, string $to)
    {
        return LeaveRequest::approved()
            ->overlapping($from, $to)
            ->get()
            ->groupBy('employee_id');
    }

    /** Cash advances still owed, grouped per employee. Old loans are not charged. */
    private function collectibleAdvances(string $periodEnd)
    {
        return Loan::advances()->collectible()
            ->where(function ($q) use ($periodEnd) {
                $q->whereNull('starts_on')->orWhere('starts_on', '<=', $periodEnd);
            })
            ->orderBy('issued_on')
            ->get()
            ->groupBy('employee_id');
    }
}
