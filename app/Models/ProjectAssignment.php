<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A posting of a worker to a site, with its own dates and rate.
 *
 * employees.site_id still says where someone belongs now, and the kiosk still
 * stamps each clock with the site it was taken at. This is the history around
 * those two, not a replacement for either.
 */
class ProjectAssignment extends Model
{
    public const STATUSES = [
        'active'    => 'Active',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ];

    protected $fillable = [
        'employee_id', 'site_id', 'position', 'rate', 'rate_type',
        'starts_on', 'ends_on', 'employment_type', 'status', 'notes', 'created_by',
    ];

    protected $casts = [
        'rate'      => 'float',
        'starts_on' => 'date',
        'ends_on'   => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('status', 'active');
    }

    /** Postings that were running at any point inside the range. */
    public function scopeCovering(Builder $q, string $from, string $to): Builder
    {
        return $q->where('starts_on', '<=', $to)
                 ->where(function ($w) use ($from) {
                     $w->whereNull('ends_on')->orWhere('ends_on', '>=', $from);
                 });
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }

    public function getRateLabelAttribute(): string
    {
        return '₱' . number_format($this->rate, 2) . ($this->rate_type === 'hourly' ? '/hr' : '/day');
    }
}

