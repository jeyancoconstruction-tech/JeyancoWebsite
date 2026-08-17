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

    /** Roles the Admin may assign, with their human labels. */
    public const ROLES = [
        self::ROLE_ADMIN => 'Administrator',
        self::ROLE_STAFF => 'Staff',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',       // Para sa Full Name o Nickname
        'username',   // Ito ang ginamit nating identity sa registration
        'password',   // Ang Access Key mo
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
