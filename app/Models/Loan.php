<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A sum issued to a worker and collected back over several payrolls.
 *
 * Distinct from `vale`, which payroll already handles and which is settled
 * inside a single period.
 */
class Loan extends Model
{
    public const TYPES = [
        'loan'    => 'Loan',
        'advance' => 'Salary Advance',
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

    /** Loans a payroll run should look at: still owed, and not paused. */
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
     * Never more than the balance, so the last instalment settles the loan
     * exactly rather than overshooting it. A loan whose collection has not
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

