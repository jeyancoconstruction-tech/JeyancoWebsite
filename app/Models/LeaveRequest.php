<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Leave filed for a worker. Approved paid leave is credited by Payroll
 * Processing; nothing here writes an attendance row.
 */
class LeaveRequest extends Model
{
    public const TYPES = [
        'vacation'    => 'Vacation Leave',
        'sick'        => 'Sick Leave',
        'emergency'   => 'Emergency Leave',
        'maternity'   => 'Maternity Leave',
        'paternity'   => 'Paternity Leave',
        'bereavement' => 'Bereavement Leave',
        'unpaid'      => 'Leave Without Pay',
    ];

    public const STATUSES = [
        'pending'   => 'Pending',
        'approved'  => 'Approved',
        'rejected'  => 'Rejected',
        'cancelled' => 'Cancelled',
    ];

    protected $fillable = [
        'employee_id', 'leave_type', 'starts_on', 'ends_on', 'days', 'is_paid',
        'reason', 'status', 'approved_by', 'approved_at', 'decision_note', 'filed_by',
    ];

    protected $casts = [
        'starts_on'   => 'date',
        'ends_on'     => 'date',
        'days'        => 'float',
        'is_paid'     => 'boolean',
        'approved_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function filer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'filed_by');
    }

    public function scopeApproved(Builder $q): Builder
    {
        return $q->where('status', 'approved');
    }

    /** Leave that touches the range at all, not only leave contained by it. */
    public function scopeOverlapping(Builder $q, string $from, string $to): Builder
    {
        return $q->where('starts_on', '<=', $to)->where('ends_on', '>=', $from);
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->leave_type] ?? ucfirst((string) $this->leave_type);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }

    /**
     * Working days inside the given range that this leave covers.
     *
     * A request may start before the payroll period and end after it, so
     * payroll must not credit the whole request — only the part that falls
     * inside the period it is paying.
     */
    public function daysWithin(string $from, string $to): float
    {
        $start = max($this->starts_on->toDateString(), $from);
        $end   = min($this->ends_on->toDateString(), $to);

        if ($start > $end) {
            return 0.0;
        }

        $span    = \Carbon\Carbon::parse($start)->diffInDays(\Carbon\Carbon::parse($end)) + 1;
        $total   = \Carbon\Carbon::parse($this->starts_on)->diffInDays($this->ends_on) + 1;
        $perDay  = $total > 0 ? $this->days / $total : 0;

        return round($span * $perDay, 2);
    }
}

