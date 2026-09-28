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
        'unpaid_break_minutes',
        'auto_count_overtime',
        'week_starts_on',
        'payroll_cycle',
        'default_theme',
        'locale',
        'shift',
        // Payroll counts hours by the shift's sessions from this date on.
        'schedule_rules_from',
        // How the attendance kiosk records a scan (System Settings → Kiosk).
        'kiosk_attendance_mode',
        'kiosk_idle_return_seconds',
        // The rest of jeyanco-settings.html (2026-09-27).
        'company_tin',
        'accent_color',
        'table_density',
        'signin_intro',
        'google_sign_in',
        'sessions_revoked_at',
        'kiosk_unknown_alert',
        'kiosk_offline_alert_minutes',
        'notify_missing_scans',
        'notify_remittances',
        'notify_payroll',
        'notify_email',
        // kiosk_repeat_guard_seconds and kiosk_repeat_guard_on are still
        // columns, but nothing reads them since 2026-09-27: the kiosk takes one
        // time in and one time out per session instead.
        //
        // sss_due_day … bir_due_day are still columns (with their defaults)
        // but nothing reads them since 2026-09-26: the Remittance Tracker
        // reminds in the last week of the month after instead.
    ];

    /** The worker presses TIME IN or TIME OUT, then scans. */
    public const KIOSK_BUTTONS = 'buttons';

    /** The worker only scans; the web decides whether it is IN or OUT. */
    public const KIOSK_AUTO = 'auto';

    /** How each mode is named on screen and in the Audit Log. */
    public const KIOSK_MODES = [
        self::KIOSK_BUTTONS => 'Buttons',
        self::KIOSK_AUTO    => 'Automatic',
    ];

    /** The kiosk's mode, falling back to Buttons for anything unexpected. */
    public function kioskMode(): string
    {
        return $this->kiosk_attendance_mode === self::KIOSK_AUTO ? self::KIOSK_AUTO : self::KIOSK_BUTTONS;
    }

    /**
     * Which day of the week is the rest day.
     *
     * Not a setting of its own: it is the seventh day of the week the office
     * actually runs, so moving where the week starts moves the rest day with
     * it. A week beginning Monday rests on Sunday; one beginning Sunday rests
     * on Saturday. Returned in Carbon's numbering, 0 = Sunday.
     */
    public function restDayOn(): int
    {
        return (((int) $this->week_starts_on) + 6) % 7;
    }

    /** That day's name, for a screen that has to say which one it means. */
    public function restDayName(): string
    {
        return ['Sunday', 'Monday', 'Tuesday', 'Wednesday',
                'Thursday', 'Friday', 'Saturday'][$this->restDayOn()];
    }

    protected $casts = [
        'session_timeout_minutes' => 'integer',
        'password_min_length'     => 'integer',
        'max_login_attempts'      => 'integer',
        'lockout_seconds'         => 'integer',
        'grace_period_minutes'    => 'integer',
        'standard_hours_per_day'  => 'float',
        'unpaid_break_minutes'    => 'integer',
        'auto_count_overtime'     => 'boolean',
        'week_starts_on'          => 'integer',
        'kiosk_idle_return_seconds'  => 'integer',
        'signin_intro'               => 'boolean',
        'google_sign_in'             => 'boolean',
        'sessions_revoked_at'        => 'datetime',
        'kiosk_unknown_alert'        => 'boolean',
        'kiosk_offline_alert_minutes' => 'integer',
        'notify_missing_scans'       => 'boolean',
        'notify_remittances'         => 'boolean',
        'notify_payroll'             => 'boolean',
        'notify_email'               => 'boolean',
    ];

    /**
     * The accent colours Appearance offers: name, swatch, then the brand
     * colour, its pressed shade and its tint for the light theme and for the
     * dark one. Blue is the design's own and needs no override.
     */
    public const ACCENTS = [
        'blue'   => ['Blue',   '#3B82F6', ['#1668DC', '#1257BC', '#EAF2FD'], ['#4F97F5', '#6FAEFF', '#152742']],
        'teal'   => ['Teal',   '#0D9488', ['#0D9488', '#0F766E', '#E6F6F4'], ['#2FBFAF', '#5EDBCB', '#10292A']],
        'violet' => ['Violet', '#7C3AED', ['#7C3AED', '#6D28D9', '#F1EBFE'], ['#A48BFA', '#C4B2FF', '#221A3A']],
        'orange' => ['Orange', '#EA580C', ['#EA580C', '#C2410C', '#FFF1E8'], ['#F7924A', '#FDBA74', '#2E1D12']],
    ];

    /** Display size: how large every page is drawn (calm.css). */
    public const DENSITIES = ['large' => 'Large', 'comfortable' => 'Comfortable', 'compact' => 'Compact'];

    /** The saved accent's token rules, or nothing for the design's own blue. */
    public function accentCss(): string
    {
        $key = (string) $this->accent_color;

        return $key !== 'blue' && isset(self::ACCENTS[$key]) ? self::accentRules($key) : '';
    }

    /** One accent's tokens for both themes; the settings page previews with it too. */
    public static function accentRules(string $key): string
    {
        [, , $light, $dark] = self::ACCENTS[$key] ?? self::ACCENTS['blue'];
        $tokens = fn (array $c, int $alpha) => "--brand:{$c[0]};--brand-strong:{$c[1]};--brand-subtle:{$c[2]};"
            . "--primary:{$c[0]};--primary-mid:{$c[1]};--primary-light:{$c[0]};--primary-soft:{$c[2]};--accent:{$c[0]};"
            . "--sidebar-accent:{$c[0]};--sidebar-active:color-mix(in srgb, {$c[0]} {$alpha}%, transparent);";

        return 'html[data-bs-theme="light"]{' . $tokens($light, 26) . '}'
             . 'html[data-bs-theme="dark"]{' . $tokens($dark, 30) . '}';
    }

    /**
     * A switch, falling back to its default while the column is still
     * missing: the code deploys a moment before its migration is run.
     */
    public function enabled(string $key): bool
    {
        return (bool) ($this->getAttribute($key) ?? self::DEFAULTS[$key] ?? false);
    }

    /** How long a kiosk may be quiet before admins are told. */
    public function kioskOfflineAlertSeconds(): int
    {
        return max(60, (int) ($this->kiosk_offline_alert_minutes ?: 10) * 60);
    }


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
        'unpaid_break_minutes'    => 0,
        'auto_count_overtime'     => true,
        'week_starts_on'          => 1,
        'payroll_cycle'           => 'weekly',
        'default_theme'           => 'dark',
        'locale'                  => 'en',
        'shift'                   => 'day',
        'kiosk_attendance_mode'      => self::KIOSK_BUTTONS,
        'kiosk_idle_return_seconds'  => 60,
        'company_tin'                => null,
        'accent_color'               => 'blue',
        'table_density'              => 'comfortable',
        'signin_intro'               => true,
        'google_sign_in'             => true,
        'sessions_revoked_at'        => null,
        'kiosk_unknown_alert'        => true,
        'kiosk_offline_alert_minutes' => 10,
        'notify_missing_scans'       => true,
        'notify_remittances'         => true,
        'notify_payroll'             => true,
        'notify_email'               => false,
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
