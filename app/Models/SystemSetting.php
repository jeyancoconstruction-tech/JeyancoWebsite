<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The one row of settings that are not payroll.
 *
 * `Setting` and `PayrollRate` answer for pay. This answers for the system: who
 * the company says it is on a payslip, and how strict the login is.
 *
 * Nothing here is dated. A rate change must not rewrite a period already paid,
 * which is why those are insert-only — but a company that corrects its address
 * means the old one was wrong, not that it was right until today.
 */
class SystemSetting extends Model
{
    protected $fillable = [
        'company_name',
        'company_tagline',
        'company_address',
        'logo_path',
        'session_timeout_minutes',
        'password_min_length',
        'max_login_attempts',
        'lockout_seconds',
        'expected_time_in',
        'grace_period_minutes',
        'standard_hours_per_day',
        'auto_count_overtime',
        'week_starts_on',
        'payroll_cycle',
        'default_theme',
        'locale',
    ];

    protected $casts = [
        'session_timeout_minutes' => 'integer',
        'password_min_length'     => 'integer',
        'max_login_attempts'      => 'integer',
        'lockout_seconds'         => 'integer',
        'grace_period_minutes'    => 'integer',
        'standard_hours_per_day'  => 'float',
        'auto_count_overtime'     => 'boolean',
        'week_starts_on'          => 'integer',
    ];

    /**
     * The values that were hardcoded before this table existed. A fresh install
     * with no row behaves exactly as it did, rather than falling to zero and
     * locking everybody out of a login that allows no attempts.
     */
    public const DEFAULTS = [
        'company_name'            => 'JEYANCO CONSTRUCTION',
        'company_tagline'         => 'Payroll Dept. · Panganiban, PH',
        'company_address'         => null,
        'logo_path'               => null,
        'session_timeout_minutes' => 120,
        'password_min_length'     => 8,
        'max_login_attempts'      => 5,
        'lockout_seconds'         => 60,
        'expected_time_in'        => '08:00:00',
        'grace_period_minutes'    => 15,
        'standard_hours_per_day'  => 8,
        'auto_count_overtime'     => true,
        'week_starts_on'          => 1,
        'payroll_cycle'           => 'weekly',
        'default_theme'           => 'dark',
        'locale'                  => 'en',
    ];

    /** The container key the resolved row is memoised under. */
    private const MEMO = 'system.settings';

    /**
     * The row, or an unsaved one carrying the defaults. Never null, so callers
     * do not each have to decide what a missing row means.
     */
    public static function current(): self
    {
        if (app()->bound(self::MEMO)) {
            return app(self::MEMO);
        }

        $row = static::first() ?? new static(self::DEFAULTS);
        app()->instance(self::MEMO, $row);

        return $row;
    }

    /**
     * Forget the memo, so the next read sees what was just saved. It lives on
     * the container rather than in a static, so a test gets a fresh one with
     * its fresh application instead of inheriting the last test's row.
     */
    public static function forget(): void
    {
        app()->forgetInstance(self::MEMO);
    }

    /**
     * The logo to print. An office that has not uploaded one keeps the bundled
     * file rather than a broken image.
     */
    public function logoUrl(): string
    {
        return $this->logo_path
            ? asset('storage/' . $this->logo_path)
            : asset('images/JeyancoLogo.png');
    }
}
