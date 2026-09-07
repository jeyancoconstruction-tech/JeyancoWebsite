<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A payroll run: a period, the people in it, and the figures frozen for them.
 *
 * PayrollService does the arithmetic. This records the answer, which is what
 * lets a payslip issued last month stay the same after a rate is edited today.
 */
class PayrollRun extends Model
{
    public const STATUSES = [
        'draft'      => 'Draft',
        'calculated' => 'Calculated',
        'approved'   => 'Approved',
        'finalized'  => 'Finalized',
    ];

    protected $fillable = [
        'code', 'title', 'period_start', 'period_end', 'site_id', 'status',
        'total_gross', 'total_deductions', 'total_net', 'employee_count', 'notes',
        'created_by', 'calculated_at', 'approved_by', 'approved_at',
        'finalized_by', 'finalized_at',
    ];

    protected $casts = [
        'period_start'     => 'date',
        'period_end'       => 'date',
        'total_gross'      => 'float',
        'total_deductions' => 'float',
        'total_net'        => 'float',
        'calculated_at'    => 'datetime',
        'approved_at'      => 'datetime',
        'finalized_at'     => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(PayrollRunItem::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function finalizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }

    public function getPeriodLabelAttribute(): string
    {
        return $this->period_start->format('M d') . ' – ' . $this->period_end->format('M d, Y');
    }

    /** Figures may still be recomputed. A finalised run never may. */
    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'calculated'], true);
    }

    public function isFinal(): bool
    {
        return $this->status === 'finalized';
    }

    /** Payslips are only issued off a run the office has signed. */
    public function isPayable(): bool
    {
        return in_array($this->status, ['approved', 'finalized'], true);
    }

    /** PR-2026-0007 — sequential within the year, readable on a payslip. */
    public static function nextCode(): string
    {
        $year   = now()->format('Y');
        $prefix = 'PR-' . $year . '-';

        $last = static::where('code', 'like', $prefix . '%')
            ->orderByDesc('code')
            ->value('code');

        $n = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }
}

