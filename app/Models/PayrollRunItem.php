<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One worker's frozen figures inside a payroll run. */
class PayrollRunItem extends Model
{
    protected $fillable = [
        'payroll_run_id', 'employee_id', 'employee_name', 'position', 'site_id',
        'daily_rate', 'hourly_rate',
        'days_worked', 'regular_hours', 'ot_hours', 'late_minutes',
        'absent_days', 'leave_days', 'paid_leave_days',
        'basic_pay', 'overtime_pay', 'holiday_pay', 'rest_day_pay',
        'night_diff_pay', 'leave_pay', 'bonus', 'other_earnings',
        'sss', 'philhealth', 'pagibig', 'tax', 'vale',
        'loan_deduction', 'advance_deduction', 'other_deductions',
        'gross_pay', 'total_deductions', 'net_pay', 'remarks',
    ];

    protected $casts = [
        'daily_rate'        => 'float',
        'hourly_rate'       => 'float',
        'days_worked'       => 'float',
        'regular_hours'     => 'float',
        'ot_hours'          => 'float',
        'late_minutes'      => 'integer',
        'absent_days'       => 'float',
        'leave_days'        => 'float',
        'paid_leave_days'   => 'float',
        'basic_pay'         => 'float',
        'overtime_pay'      => 'float',
        'holiday_pay'       => 'float',
        'rest_day_pay'      => 'float',
        'night_diff_pay'    => 'float',
        'leave_pay'         => 'float',
        'bonus'             => 'float',
        'other_earnings'    => 'float',
        'sss'               => 'float',
        'philhealth'        => 'float',
        'pagibig'           => 'float',
        'tax'               => 'float',
        'vale'              => 'float',
        'loan_deduction'    => 'float',
        'advance_deduction' => 'float',
        'other_deductions'  => 'float',
        'gross_pay'         => 'float',
        'total_deductions'  => 'float',
        'net_pay'           => 'float',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /** Payslip earnings, in the order they are printed. */
    public function earningLines(): array
    {
        return array_filter([
            'Basic Pay'        => $this->basic_pay,
            'Overtime'         => $this->overtime_pay,
            'Holiday Pay'      => $this->holiday_pay,
            'Rest Day Pay'     => $this->rest_day_pay,
            'Night Differential' => $this->night_diff_pay,
            'Paid Leave'       => $this->leave_pay,
            'Bonus'            => $this->bonus,
            'Other Earnings'   => $this->other_earnings,
        ], fn ($v) => $v > 0);
    }

    /** Payslip deductions, in the order they are printed. */
    public function deductionLines(): array
    {
        return array_filter([
            'SSS'              => $this->sss,
            'PhilHealth'       => $this->philhealth,
            'Pag-IBIG'         => $this->pagibig,
            'Withholding Tax'  => $this->tax,
            'Vale'             => $this->vale,
            'Loan'             => $this->loan_deduction,
            'Cash Advance'     => $this->advance_deduction,
            'Other Deductions' => $this->other_deductions,
        ], fn ($v) => $v > 0);
    }
}

