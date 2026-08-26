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

    /** Paid for each day actually worked — what every worker is today. */
    public const EMPLOYMENT_DAILY = 'daily';

    /** Engaged on a contract. Recorded so the office can tell them apart; the
     *  payroll computation does NOT yet treat them differently. */
    public const EMPLOYMENT_CONTRACTUAL = 'contractual';

    public const EMPLOYMENT_TYPES = [
        self::EMPLOYMENT_DAILY        => 'Arawan (daily)',
        self::EMPLOYMENT_CONTRACTUAL  => 'Contractual',
    ];

    protected $fillable = [
        'name', 'rate_per_hour', 'position', 'employment_type', 'project_id', 'labor_type_id',
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

    // ── Fingerprint slots ────────────────────────────────────────────────────

    /**
     * Free a sensor slot so it can be handed to another worker.
     *
     * fingerprint_id carries a DB-level unique index that spans soft-deleted
     * rows, and Laravel's `unique` validation rule does not filter them either.
     * So a worker who was removed or archived keeps owning their slot forever —
     * the kiosk enrolls a finger into that free sensor slot, the web refuses it
     * with "already been taken", and the slot is dead for good. Every removal
     * burns one more slot.
     *
     * A worker who is removed or archived cannot clock in, so their claim on the
     * slot is meaningless: release it. A pending stub the kiosk auto-created from
     * an unknown scan is the same physical finger, so release it too — unless it
     * already collected attendance, which the admin has to resolve deliberately.
     *
     * @return static|null  null when the slot is free to use, or the employee
     *                      still legitimately holding it.
     */
    public static function releaseFingerprint(string $fingerprintId, ?int $exceptEmployeeId = null): ?self
    {
        $holder = static::withTrashed()
            ->where('fingerprint_id', $fingerprintId)
            ->when($exceptEmployeeId, fn ($q) => $q->where('id', '!=', $exceptEmployeeId))
            ->first();

        if (! $holder) {
            return null;                                  // nobody holds it
        }

        if ($holder->trashed() || $holder->isArchived()) {
            $holder->forceFill(['fingerprint_id' => null])->save();
            return null;
        }

        if ($holder->isPending() && $holder->attendances()->count() === 0) {
            $holder->forceFill(['fingerprint_id' => null])->save();
            return null;
        }

        return $holder;                                   // real conflict
    }

    /** Why the slot could not be released — a message the kiosk can show as-is. */
    public static function fingerprintConflictMessage(self $holder, string $fingerprintId): string
    {
        if ($holder->isPending()) {
            return "Fingerprint #{$fingerprintId} is already logging attendance for a pending worker ("
                 . $holder->name . '). Complete or remove them on the web first.';
        }

        return "Fingerprint #{$fingerprintId} is already assigned to " . $holder->name . '.';
    }

    /** Human label for the employment type. */
    public function getEmploymentLabelAttribute(): string
    {
        return self::EMPLOYMENT_TYPES[$this->employment_type] ?? self::EMPLOYMENT_TYPES[self::EMPLOYMENT_DAILY];
    }

    /** True when the worker is engaged on a contract rather than paid per day. */
    public function isContractual(): bool
    {
        return $this->employment_type === self::EMPLOYMENT_CONTRACTUAL;
    }

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
