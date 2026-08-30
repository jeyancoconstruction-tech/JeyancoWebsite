<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

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
        self::EMPLOYMENT_DAILY        => 'Regular',
        self::EMPLOYMENT_CONTRACTUAL  => 'Contractual',
    ];

    /** Every profile column the Register Employee form writes. */
    public const PROFILE_FIELDS = [
        'birth_date', 'birth_place', 'gender', 'civil_status', 'nationality', 'religion', 'blood_type',
        'phone', 'email', 'emergency_contact_name', 'emergency_contact_relation', 'emergency_contact_phone',
        'address_street', 'address_barangay', 'address_city', 'address_province', 'address_postal',
        'sss_number', 'philhealth_number', 'pagibig_number', 'tin_number',
        'job_title', 'date_hired',
        'education', 'work_experience', 'skills', 'notes',
    ];

    public const GENDERS        = ['Male', 'Female'];
    public const CIVIL_STATUSES = ['Single', 'Married', 'Widowed', 'Separated'];

    protected $fillable = [
        'name', 'rate_per_hour', 'position', 'employment_type', 'contract_rate', 'project_id', 'labor_type_id',
        'site_id', 'kiosk_id', 'status', 'vale', 'fingerprint_id', 'photo', 'archived_at',
        ...self::PROFILE_FIELDS,
    ];

    protected $casts = [
        'rate_per_hour' => 'float',
        'contract_rate' => 'float',
        'vale'          => 'float',
        'archived_at'   => 'datetime',
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',
        'birth_date'    => 'date',
        'date_hired'    => 'date',
        // Stored as JSON so a worker can carry several of each without
        // needing a child table per list.
        'education'       => 'array',
        'work_experience' => 'array',
        'skills'          => 'array',
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
    /**
     * The pending row already standing in for this finger, if there is one.
     *
     * A fingerprint IS the identity here. When the kiosk's scan loop meets a
     * finger it does not recognise it opens a pending "Unregistered Worker"
     * placeholder — including, moments later, the very finger someone is in
     * the middle of enrolling. Registering that finger afterwards is the same
     * person arriving with their details, so the placeholder is filled in
     * rather than left behind beside a second row for the same worker.
     *
     * Adopting also keeps whatever the placeholder already logged: someone who
     * clocked in for days before an admin registered them does not lose that
     * attendance.
     */
    public static function pendingHolderOf(string $fingerprintId): ?self
    {
        return static::where('fingerprint_id', $fingerprintId)
            ->where('status', self::STATUS_PENDING)
            ->first();
    }

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

        // A leftover placeholder from the old self-registration flow can be
        // cleared out of the way. A real worker cannot: silently blanking a
        // named person's fingerprint is how one worker's finger ended up
        // clocking in as somebody else — the first person simply stopped
        // being recognised, with nothing anywhere to say why.
        $isPlaceholder = $holder->isPending()
            && (trim((string) $holder->name) === '' || $holder->name === 'Unregistered Worker');

        if ($isPlaceholder && $holder->attendances()->count() === 0) {
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

    /**
     * Strip attributes whose column the database does not have yet.
     *
     * A deploy can land new code before its migration has run — that is
     * exactly what happened here: the Confirm button on Register & Manage
     * started answering 500 with "Unknown column 'employment_type'", because
     * the code was writing a column the live database had not been given.
     *
     * Losing the employment type for one save is a far smaller failure than
     * refusing to save the worker at all. The worker is stored, the type
     * keeps its default, and it starts sticking the moment the column exists.
     */
    public static function withoutMissingColumns(array $attributes): array
    {
        $guarded = array_merge(['employment_type', 'contract_rate'], self::PROFILE_FIELDS);

        foreach ($guarded as $column) {
            if (array_key_exists($column, $attributes) && ! self::tableHas($column)) {
                unset($attributes[$column]);
            }
        }

        return $attributes;
    }

    /** Cached for the request — one schema query at most, not one per save. */
    private static function tableHas(string $column): bool
    {
        static $seen = [];

        if (! array_key_exists($column, $seen)) {
            try {
                $seen[$column] = Schema::hasColumn('employees', $column);
            } catch (\Throwable $e) {
                // Treat as absent so a save still goes through — but say so.
                // Swallowing this silently once turned a missing import into
                // "the column is not there", and the type stopped saving with
                // nothing anywhere to explain why.
                Log::warning("Employee: cannot check column '{$column}' — " . $e->getMessage());
                $seen[$column] = false;
            }
        }

        return $seen[$column];
    }

    /** The address as one line, skipping the parts that were left blank. */
    public function getFullAddressAttribute(): string
    {
        return collect([
            $this->address_street,
            $this->address_barangay,
            $this->address_city,
            $this->address_province,
            $this->address_postal,
        ])->filter()->implode(', ');
    }

    /** Age in whole years, or null when no birth date is on file. */
    public function getAgeAttribute(): ?int
    {
        return $this->birth_date?->age;
    }

    /** Human label for the employment type. */
    public function getEmploymentLabelAttribute(): string
    {
        return self::EMPLOYMENT_TYPES[$this->employment_type] ?? self::EMPLOYMENT_TYPES[self::EMPLOYMENT_DAILY];
    }

    /**
     * The flat amount this worker earns for each day present, or null when the
     * usual hours x rate computation applies.
     *
     * Both conditions matter: tagging someone contractual without agreeing an
     * amount must not silently drop their pay to zero.
     */
    public function contractDayPay(): ?float
    {
        if (! $this->isContractual()) {
            return null;
        }
        $rate = (float) ($this->contract_rate ?? 0);

        return $rate > 0 ? $rate : null;
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
