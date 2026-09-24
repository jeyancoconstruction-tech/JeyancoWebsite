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

    /**
     * How an account signs in. Google means the verified Google address must
     * be the account's email and there is no usable password; password means
     * Sign in with Google is refused for it.
     */
    public const LOGIN_BOTH     = 'both';
    public const LOGIN_GOOGLE   = 'google';
    public const LOGIN_PASSWORD = 'password';

    public const LOGIN_METHODS = [
        self::LOGIN_BOTH     => 'Google and password',
        self::LOGIN_GOOGLE   => 'Google only',
        self::LOGIN_PASSWORD => 'Password only',
    ];

    /** Surname particles that belong to the last name: "dela Cruz", "de los Santos". */
    private const PARTICLES = ['de', 'del', 'dela', 'delos', 'della', 'di', 'da', 'dos', 'das', 'la', 'las', 'los', 'san', 'santa', 'sta.', 'sto.', 'van', 'von', 'mc', 'y'];

    /** A new account is HR unless an Administrator picks otherwise. */
    protected $attributes = [
        'role'         => self::ROLE_HR,
        'login_method' => self::LOGIN_PASSWORD,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',       // Para sa Full Name o Nickname — always "first last"
        'first_name',
        'last_name',
        'username',   // The identity used at registration
        'password',   // The Access Key
        'email',
        'role',
        'is_active',
        'created_by',
        'login_method',
        'must_change_password',
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
            'google_linked_at' => 'datetime',
            'must_change_password' => 'boolean',
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

            // The name is kept in two parts and read everywhere as one. Given
            // the parts, the whole follows; given only the whole — a seeder, a
            // test, an older path — the parts are worked out from it.
            if ($user->isDirty(['first_name', 'last_name']) && filled($user->first_name)) {
                $user->name = trim($user->first_name . ' ' . $user->last_name);
            } elseif ($user->isDirty('name') && blank($user->first_name) && blank($user->last_name)) {
                [$user->first_name, $user->last_name] = self::splitName((string) $user->name);
            }
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

    /**
     * "Maria Clara Santos" → [Maria Clara, Santos]; "Juan dela Cruz" → [Juan,
     * dela Cruz]. A one-word name has no last name.
     *
     * @return array{0: string, 1: ?string}
     */
    public static function splitName(string $name): array
    {
        $words = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY);

        if (count($words) < 2) {
            return [$words[0] ?? '', null];
        }

        $at = count($words) - 1;
        while ($at > 1 && in_array(strtolower($words[$at - 1]), self::PARTICLES, true)) {
            $at--;
        }

        return [implode(' ', array_slice($words, 0, $at)), implode(' ', array_slice($words, $at))];
    }

    public function usesGoogle(): bool
    {
        return $this->login_method !== self::LOGIN_PASSWORD;
    }

    public function usesPassword(): bool
    {
        return $this->login_method !== self::LOGIN_GOOGLE;
    }

    /** 'linked' once they have signed in with Google, 'pending' until then, null if they cannot. */
    public function googleStatus(): ?string
    {
        if (! $this->usesGoogle()) {
            return null;
        }

        return $this->google_linked_at ? 'linked' : 'pending';
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
