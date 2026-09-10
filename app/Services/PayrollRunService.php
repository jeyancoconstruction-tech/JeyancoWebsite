<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Loan;
use App\Models\LoanDeduction;
use App\Models\OvertimeRequest;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\SystemSetting;
use Carbon\Carbon;
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
 *   2. layers on the things it has no way to know about: approved overtime
 *      claims, approved paid leave, and loan instalments due,
 *   3. writes the result down, so the numbers stop moving.
 *
 * Recalculating replaces a run's items wholesale. Collecting against loans is
 * deliberately NOT done here — that happens once, at finalisation, so a run
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
        $from = $run->period_start->toDateString();
        $to   = $run->period_end->toDateString();

        // The existing engine, untouched, on the run's own range.
        $computed = $this->payroll->computeForRange($from, $to);
        $byEmployee = collect($computed['employees'] ?? [])->keyBy('employee_id');

        $overtime = $this->approvedOvertime($from, $to, $computed['days'] ?? []);
        $leave    = $this->approvedLeave($from, $to);
        $loans    = $this->collectibleLoans($to);

        // Everyone the period touches: worked, or has approved leave, or has
        // approved overtime. A worker with only leave still needs a payslip.
        $ids = $byEmployee->keys()
            ->merge($overtime->keys())
            ->merge($leave->keys())
            ->unique();

        $employees = Employee::with('site')
            ->whereIn('id', $ids)
            ->when($run->site_id, fn ($q) => $q->where('site_id', $run->site_id))
            ->get()
            ->keyBy('id');

        DB::transaction(function () use ($run, $employees, $byEmployee, $overtime, $leave, $loans) {
            $run->items()->delete();

            $gross = $deductions = $net = 0.0;
            $count = 0;

            foreach ($employees as $id => $employee) {
                $row = $this->buildItem(
                    $run,
                    $employee,
                    $byEmployee->get($id),
                    $overtime->get($id),
                    $leave->get($id, collect()),
                    $loans->get($id, collect())
                );

                PayrollRunItem::create($row);

                $gross      += $row['gross_pay'];
                $deductions += $row['total_deductions'];
                $net        += $row['net_pay'];
                $count++;
            }

            $run->update([
                'status'           => $run->status === 'draft' ? 'calculated' : $run->status,
                'total_gross'      => round($gross, 2),
                'total_deductions' => round($deductions, 2),
                'total_net'        => round($net, 2),
                'employee_count'   => $count,
                'calculated_at'    => now(),
            ]);
        });

        return $run->fresh('items');
    }

    /**
     * One worker's row. Everything PayrollService produced is carried across
     * verbatim; only the three things it cannot see are added on top.
     */
    private function buildItem(
        PayrollRun $run,
        Employee $employee,
        ?array $computed,
        ?object $ot,
        $leaveRows,
        $loanRows
    ): array {
        $t = $computed['totals'] ?? [];

        $dailyRate  = (float) ($employee->rate_per_hour * 8);
        $hourlyRate = (float) $employee->rate_per_hour;

        // ── Earnings from attendance, as the engine computed them ─────────
        $basic      = (float) ($t['gross'] ?? 0);
        $holiday    = (float) ($t['holidayPay'] ?? 0);
        $restDay    = (float) ($t['restDayPay'] ?? 0);
        $nightDiff  = (float) ($t['nightDiffPay'] ?? 0);
        $bonus      = (float) ($t['bonus'] ?? 0);
        $engineOt   = (float) ($t['overtime'] ?? 0);

        // The engine's gross already contains its own overtime, holiday, rest
        // day, night differential and bonus. Basic is what is left once those
        // are taken back out, so the payslip can show them as separate lines
        // without counting any peso twice.
        $basicOnly = round($basic - $engineOt - $holiday - $restDay - $nightDiff - $bonus, 2);
        $basicOnly = max($basicOnly, 0);

        // ── Approved overtime claims, which the engine cannot see ─────────
        $claimedOt      = $ot ? (float) $ot->amount : 0.0;
        $claimedOtHours = $ot ? (float) $ot->hours : 0.0;

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

        // ── Deductions the engine already applied ─────────────────────────
        $vale             = (float) ($t['vale'] ?? 0);
        $engineDeductions = (float) ($t['totalDeductions'] ?? 0);
        $statutoryAndOther = round($engineDeductions - $vale, 2);
        $statutoryAndOther = max($statutoryAndOther, 0);

        // ── Loan and advance instalments due this period ──────────────────
        $loanDue = $advanceDue = 0.0;
        foreach ($loanRows as $loan) {
            $due = $loan->installmentFor($run->period_end->toDateString());
            if ($loan->type === 'advance') {
                $advanceDue += $due;
            } else {
                $loanDue += $due;
            }
        }

        $grossPay = round(
            $basicOnly + $engineOt + $claimedOt + $holiday + $restDay
            + $nightDiff + $leavePay + $bonus,
            2
        );

        $totalDeductions = round($statutoryAndOther + $vale + $loanDue + $advanceDue, 2);

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
            'ot_hours'        => round($claimedOtHours, 2),
            'late_minutes'    => 0,
            'absent_days'     => 0,
            'leave_days'      => round($leaveDays, 2),
            'paid_leave_days' => round($paidLeaveDays, 2),

            'basic_pay'      => $basicOnly,
            'overtime_pay'   => round($engineOt + $claimedOt, 2),
            'holiday_pay'    => $holiday,
            'rest_day_pay'   => $restDay,
            'night_diff_pay' => $nightDiff,
            'leave_pay'      => $leavePay,
            'bonus'          => $bonus,
            'other_earnings' => 0,

            // The statutory split lives inside PayrollService's own totals; it
            // is carried as one figure rather than guessed at line by line.
            'sss'               => 0,
            'philhealth'        => 0,
            'pagibig'           => 0,
            'tax'               => 0,
            'vale'              => round($vale, 2),
            'loan_deduction'    => round($loanDue, 2),
            'advance_deduction' => round($advanceDue, 2),
            'other_deductions'  => $statutoryAndOther,

            'gross_pay'        => $grossPay,
            'total_deductions' => $totalDeductions,
            'net_pay'          => round($grossPay - $totalDeductions, 2),
        ];
    }

    /**
     * Collect the loan instalments this run charged. Called once, from
     * finalisation, so recalculating a run never collects twice.
     */
    public function collectLoans(PayrollRun $run): int
    {
        $collected = 0;

        DB::transaction(function () use ($run, &$collected) {
            foreach ($run->items as $item) {
                $due = $item->loan_deduction + $item->advance_deduction;
                if ($due <= 0) {
                    continue;
                }

                $loans = Loan::collectible()
                    ->where('employee_id', $item->employee_id)
                    ->orderBy('issued_on')
                    ->get();

                foreach ($loans as $loan) {
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
        });

        return $collected;
    }

    /**
     * Approved overtime in the range, summed per employee — less what the
     * attendance already paid as overtime on the same day.
     *
     * Once overtime is counted from the kiosk (the time after the shift ends),
     * a claim for that same evening would pay it twice: the engine's overtime
     * and the claim were simply added. On a day that has both, the larger of
     * the two is paid now, not the sum. Days before the new count are left as
     * they were computed, so no settled period moves.
     */
    private function approvedOvertime(string $from, string $to, array $days = [])
    {
        $rulesFrom = SystemSetting::current()->schedule_rules_from;
        $rulesFrom = $rulesFrom ? Carbon::parse($rulesFrom)->toDateString() : null;

        $kioskOt = [];
        foreach ($days as $day) {
            $date = Carbon::parse($day['date'])->toDateString();
            foreach ($day['details'] ?? [] as $d) {
                $key           = $d['employee_id'] . '|' . $date;
                $kioskOt[$key] = ($kioskOt[$key] ?? 0.0) + (float) ($d['ot_hours'] ?? 0);
            }
        }

        return OvertimeRequest::approved()
            ->inRange($from, $to)
            ->get()
            ->groupBy('employee_id')
            ->map(function ($claims, $empId) use ($kioskOt, $rulesFrom) {
                $hours = $amount = 0.0;

                foreach ($claims as $c) {
                    $date    = $c->date->toDateString();
                    $already = ($rulesFrom && $date >= $rulesFrom) ? ($kioskOt[$empId . '|' . $date] ?? 0.0) : 0.0;
                    $payable = max(0.0, (float) $c->hours - $already);
                    $share   = (float) $c->hours > 0 ? $payable / (float) $c->hours : 0.0;

                    $hours  += $payable;
                    $amount += (float) $c->amount * $share;
                }

                return (object) ['hours' => round($hours, 2), 'amount' => round($amount, 2)];
            });
    }

    /** Approved leave touching the range, grouped per employee. */
    private function approvedLeave(string $from, string $to)
    {
        return LeaveRequest::approved()
            ->overlapping($from, $to)
            ->get()
            ->groupBy('employee_id');
    }

    /** Loans still owed, grouped per employee. */
    private function collectibleLoans(string $periodEnd)
    {
        return Loan::collectible()
            ->where(function ($q) use ($periodEnd) {
                $q->whereNull('starts_on')->orWhere('starts_on', '<=', $periodEnd);
            })
            ->orderBy('issued_on')
            ->get()
            ->groupBy('employee_id');
    }
}
