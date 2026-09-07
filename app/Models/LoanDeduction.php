<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One collection against a loan, and the payroll run that made it. */
class LoanDeduction extends Model
{
    protected $fillable = ['loan_id', 'payroll_run_id', 'amount', 'deducted_on', 'note'];

    protected $casts = [
        'amount'      => 'float',
        'deducted_on' => 'date',
    ];

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class);
    }
}

