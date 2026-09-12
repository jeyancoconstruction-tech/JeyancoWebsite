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

    /** Day-to-day operations only — no Settings, no Account Management. */
    public const ROLE_STAFF = 'staff';

    // Narrower roles for the modules added around payroll. They are additions,
    // not a replacement: every existing account keeps the role it has, and
    // 'staff' still means exactly what it meant before. What each one may open
    // is declared in App\Support\Modules, and applies only to those modules —
    // the older screens keep the guards they already had.

    /** Payroll, its reports and payslips. No employee administration. */
    public const ROLE_PAYROLL = 'payroll_officer';

    /** People: employees, leave, loans. No payroll figures. */
    public const ROLE_HR = 'hr';

    /** One site's crew: assignments, leave approval, devices. */
    public const ROLE_SUPERVISOR = 'site_supervisor';

    /** A worker's own records only. */
    public const ROLE_EMPLOYEE = 'employee';

    /** Roles the Admin may assign, with their human labels. */
    public const ROLES = [
        self::ROLE_ADMIN      => 'Administrator',
        self::ROLE_STAFF      => 'Staff',
        self::ROLE_PAYROLL    => 'Payroll Officer',
        self::ROLE_HR         => 'HR',
        self::ROLE_SUPERVISOR => 'Site Supervisor',
        self::ROLE_EMPLOYEE   => 'Employee',
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

    public function isStaff(): bool
    {
        return $this->role === self::ROLE_STAFF;
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
        return self::ROLES[$this->role] ?? 'Staff';
    }

    /** Accounts allowed to sign in. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
