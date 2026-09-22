<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /** Full access, including Settings and Account Management. */
    public const ROLE_ADMIN = 'admin';

    /**
     * Everyone else in the office. Opens the day-to-day screens and the modules
     * App\Support\Modules grants it — leave, cash advances, project assignment,
     * payroll processing, payslips, payroll reports and device monitoring — but
     * not Settings, Account Management, Users & Roles or the Audit Logs.
     */
    public const ROLE_HR = 'hr';

    // Two roles, Administrator and HR, as Chapter 4 (Table 4.5) describes the
    // system. The narrower roles tried before them — Staff, Payroll Officer and
    // Site Supervisor — were folded into HR by the 2026_09_22_090000 migration,
    // which kept every account and its history. Their keys stay here only so
    // old audit entries and that migration can still name them.
    public const ROLE_STAFF = 'staff';
    public const ROLE_PAYROLL = 'payroll_officer';
    public const ROLE_SUPERVISOR = 'site_supervisor';

    // There is no role for a worker. Workers clock in and out at the kiosk
    // and never sign in to the web; the Employee role that once gave them a
    // login was retired, and its accounts deactivated, by the
    // 2026_09_15_120000 migration.

    /** Roles the Admin may assign, with their human labels. */
    public const ROLES = [
        self::ROLE_ADMIN => 'Administrator',
        self::ROLE_HR    => 'HR',
    ];

    /** A new account is HR unless an Administrator picks otherwise. */
    protected $attributes = [
        'role' => self::ROLE_HR,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',       // Para sa Full Name o Nickname
        'username',   // The identity used at registration
        'password',   // The Access Key
        'email',
        'role',
        'is_active',
        'created_by',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     * * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Keep the legacy is_admin flag in step with `role`. Several modules still
     * read is_admin directly (admin notifications, the AI assistant), so the
     * two must never disagree.
     */
    protected static function booted(): void
    {
        static::saving(function (User $user) {
            $user->is_admin = $user->role === self::ROLE_ADMIN;
        });
    }

    /** The admin who created this account, when it was made in-system. */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isHr(): bool
    {
        return $this->role === self::ROLE_HR;
    }

    /**
     * May this account open one of the modules added around payroll?
     * Older screens are not governed by this — they keep their own guards.
     */
    public function canAccessModule(string $module): bool
    {
        return \App\Support\Modules::allows($this, $module);
    }

    /** Human label for the assigned role. */
    public function getRoleLabelAttribute(): string
    {
        return self::ROLES[$this->role] ?? 'HR';
    }

    /** Accounts allowed to sign in. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
