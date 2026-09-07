<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Overtime claimed for a day. A claim, never a clock: the kiosk remains the
 * only thing that records attendance.
 */
class OvertimeRequest extends Model
{
    public const STATUSES = [
        'pending'  => 'Pending',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
    ];

    protected $fillable = [
        'employee_id', 'site_id', 'date', 'starts_at', 'ends_at', 'hours',
        'hourly_rate', 'multiplier', 'amount', 'reason', 'status',
        'approved_by', 'approved_at', 'decision_note', 'filed_by',
    ];

    protected $casts = [
        'date'        => 'date',
        'hours'       => 'float',
        'hourly_rate' => 'float',
        'multiplier'  => 'float',
        'amount'      => 'float',
        'approved_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopeApproved(Builder $q): Builder
    {
        return $q->where('status', 'approved');
    }

    public function scopeInRange(Builder $q, string $from, string $to): Builder
    {
        return $q->whereBetween('date', [$from, $to]);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }

    /**
     * Hours between the two times, tolerating a shift that crosses midnight.
     * Returned rather than stored, so the form can show it before saving.
     */
    public static function hoursBetween(?string $start, ?string $end): float
    {
        if (! $start || ! $end) {
            return 0.0;
        }

        $s = \Carbon\Carbon::parse($start);
        $e = \Carbon\Carbon::parse($end);

        if ($e->lessThanOrEqualTo($s)) {
            $e->addDay();
        }

        return round($s->diffInMinutes($e) / 60, 2);
    }
}

