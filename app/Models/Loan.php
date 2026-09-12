<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A cash advance issued to a worker and collected back over several payrolls.
 *
 * Distinct from `vale`, which payroll already handles and which is settled
 * inside a single period.
 *
 * The table is still `loans`, and its type column still tells an advance from
 * a loan. Loans are no longer issued, but the ones that were keep their
 * history, and a run calculated while they were still charged settles them at
 * finalisation (PayrollRunService::collectLoans).
 */
class Loan extends Model
{
    /** The only kind issued now. */
    public const ADVANCE = 'advance';

    /** Labels for every kind on file, the retired one included. */
    public const TYPES = [
        'loan'    => 'Loan',
        'advance' => 'Cash Advance',
    ];

    public const STATUSES = [
        'active'    => 'Active',
        'paid'      => 'Fully Paid',
        'on_hold'   => 'On Hold',
        'cancelled' => 'Cancelled',
    ];

    protected $fillable = [
        'employee_id', 'type', 'reference', 'principal', 'balance', 'installment',
        'schedule', 'issued_on', 'starts_on', 'status', 'notes', 'created_by',
    ];

    protected $casts = [
        'principal'   => 'float',
        'balance'     => 'float',
        'installment' => 'float',
        'issued_on'   => 'date',
        'starts_on'   => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function deductions(): HasMany
    {
        return $this->hasMany(LoanDeduction::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Cash advances — everything this table still issues. */
    public function scopeAdvances(Builder $q): Builder
    {
        return $q->where('type', self::ADVANCE);
    }

    /** Rows a payroll run should look at: still owed, and not paused. */
    public function scopeCollectible(Builder $q): Builder
    {
        return $q->where('status', 'active')->where('balance', '>', 0);
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? ucfirst((string) $this->type);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }

    public function getPaidAmountAttribute(): float
    {
        return round($this->principal - $this->balance, 2);
    }

    public function getProgressAttribute(): int
    {
        if ($this->principal <= 0) {
            return 0;
        }

        return (int) min(100, round(($this->paid_amount / $this->principal) * 100));
    }

    /**
     * What this payroll should take, given the period it covers.
     *
     * Never more than the balance, so the last instalment settles the advance
     * exactly rather than overshooting it. One whose collection has not
     * started by the end of the period is left alone.
     */
    public function installmentFor(string $periodEnd): float
    {
        if ($this->status !== 'active' || $this->balance <= 0) {
            return 0.0;
        }

        if ($this->starts_on && $this->starts_on->toDateString() > $periodEnd) {
            return 0.0;
        }

        return round(min($this->installment, $this->balance), 2);
    }
}
