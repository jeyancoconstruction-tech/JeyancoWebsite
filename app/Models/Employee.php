<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class Employee extends Model
{
    use SoftDeletes;

    // Lifecycle states.
    public const STATUS_PENDING  = 'pending';   // detected by a kiosk, details incomplete
    public const STATUS_ACTIVE   = 'active';     // fully registered, part of the workforce
    public const STATUS_ARCHIVED = 'archived';   // left the company, records preserved

    protected $fillable = [
        'name', 'rate_per_hour', 'position', 'project_id', 'labor_type_id',
        'site_id', 'kiosk_id', 'status', 'vale', 'fingerprint_id', 'photo', 'archived_at',
    ];

    protected $casts = [
        'rate_per_hour' => 'float',
        'vale'          => 'float',
        'archived_at'   => 'datetime',
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',
    ];

    // ── Relationships ────────────────────────────────────────────────────────
    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function kiosk()
    {
        return $this->belongsTo(Kiosk::class);
    }

    public function projectSite()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function laborType()
    {
        return $this->belongsTo(LaborType::class, 'labor_type_id');
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    // ── Scopes ───────────────────────────────────────────────────────────────
    public function scopeActive(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_ACTIVE);
    }

    public function scopePending(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_PENDING);
    }

    public function scopeArchived(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_ARCHIVED);
    }

    // ── State helpers ────────────────────────────────────────────────────────
    public function isPending(): bool  { return $this->status === self::STATUS_PENDING; }
    public function isActive(): bool   { return $this->status === self::STATUS_ACTIVE; }
    public function isArchived(): bool { return $this->status === self::STATUS_ARCHIVED; }

    /**
     * Get the daily rate based on labor type or fallback to rate_per_hour * 8
     */
    public function getDailyRate()
    {
        if ($this->laborType) {
            return $this->laborType->daily_rate;
        }
        return $this->rate_per_hour * 8;
    }

    /**
     * Get the OT rate based on labor type or fallback to rate_per_hour * 1.25
     */
    public function getOTRate()
    {
        if ($this->laborType) {
            return $this->laborType->ot_rate;
        }
        return $this->rate_per_hour * 1.25 * 8;
    }
}
