<?php

namespace App\Models;

use App\Support\ActivityRecorder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Append-only record of who did what. Nothing in the application updates or
 * deletes a row, and the screen over it is read-only for everyone.
 *
 * Most entries are written by App\Support\ActivityRecorder, which watches
 * every model change, sign-in and write request. AuditLog::record() is for
 * the places that can say more than a field-by-field diff — "Approved payroll
 * run PR-2026-37" rather than "status calculated → approved".
 */
class AuditLog extends Model
{
    protected $fillable = [
        'user_id', 'user_name', 'module', 'action', 'description',
        'subject_type', 'subject_id', 'ip_address', 'user_agent',
    ];

    /** The colour an action carries on screen, from the app's own palette. */
    public const TONES = [
        'ok'     => ['created', 'approved', 'finalized', 'restored', 'signed in', 'password reset', 'time in', 'time out'],
        'danger' => ['deleted', 'rejected', 'cancelled', 'failed', 'locked out', 'blocked'],
        'warn'   => ['updated', 'recalculated', 'reopened', 'remittance'],
    ];

    /** Worth a second look: removals, refusals, and changes to access or settings. */
    public const SENSITIVE_ACTIONS = ['deleted', 'rejected', 'cancelled', 'failed', 'locked out', 'blocked'];
    public const SENSITIVE_MODULES = ['Users', 'Settings'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeModule(Builder $q, ?string $module): Builder
    {
        return $module ? $q->where('module', $module) : $q;
    }

    public function scopeSensitive(Builder $q): Builder
    {
        return $q->where(fn (Builder $w) => $w
            ->whereIn('action', self::SENSITIVE_ACTIONS)
            ->orWhereIn('module', self::SENSITIVE_MODULES));
    }

    /**
     * Write an entry in the acting user's name. Deliberately swallows its own
     * failures: a log that cannot be written must never be the reason a
     * payroll approval fails.
     */
    public static function record(
        string $module,
        string $action,
        string $description = '',
        ?Model $subject = null
    ): void {
        // So the recorder does not log the same change a second time, less
        // well, from the model event underneath it.
        try {
            app(ActivityRecorder::class)->noteExplicit($module, $subject);
        } catch (\Throwable $e) {
            report($e);
        }

        $user = auth()->user();

        static::entry([
            'user_id'      => $user?->id,
            'user_name'    => $user?->name ?? $user?->username,
            'module'       => $module,
            'action'       => $action,
            'description'  => $description,
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id'   => $subject?->getKey(),
        ]);
    }

    /** Write one row as given, stamped with the request's IP and browser. */
    public static function entry(array $attributes): ?self
    {
        try {
            $request = request();

            return static::create($attributes + [
                'ip_address' => $request?->ip(),
                'user_agent' => substr((string) $request?->userAgent(), 0, 255),
            ]);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    public static function toneFor(?string $action): string
    {
        $action = strtolower((string) $action);

        foreach (self::TONES as $tone => $actions) {
            if (in_array($action, $actions, true)) {
                return $tone;
            }
        }

        return 'muted';
    }

    /** The colour a row carries in the log, reusing the app's palette. */
    public function getToneAttribute(): string
    {
        return self::toneFor($this->action);
    }

    /** "Chrome 128 on Windows", read from the stored user agent. */
    public function getDeviceAttribute(): string
    {
        $ua = (string) $this->user_agent;

        if ($ua === '') {
            return '—';
        }
        if (preg_match('#python-requests/([\d.]+)#i', $ua, $m)) {
            return 'Kiosk script (python-requests ' . $m[1] . ')';
        }

        $browser = null;
        if (preg_match('#Edg/(\d+)#', $ua, $m)) {
            $browser = 'Edge ' . $m[1];
        } elseif (preg_match('#OPR/(\d+)#', $ua, $m)) {
            $browser = 'Opera ' . $m[1];
        } elseif (preg_match('#Chrome/(\d+)#', $ua, $m)) {
            $browser = 'Chrome ' . $m[1];
        } elseif (preg_match('#Firefox/(\d+)#', $ua, $m)) {
            $browser = 'Firefox ' . $m[1];
        } elseif (preg_match('#Version/(\d+).*Safari#', $ua, $m)) {
            $browser = 'Safari ' . $m[1];
        }

        $os = match (true) {
            str_contains($ua, 'Windows')                              => 'Windows',
            str_contains($ua, 'Android')                              => 'Android',
            str_contains($ua, 'iPhone') || str_contains($ua, 'iPad')  => 'iOS',
            str_contains($ua, 'Mac OS')                               => 'macOS',
            str_contains($ua, 'Linux')                                => 'Linux',
            default                                                   => null,
        };

        if (! $browser) {
            return Str::limit($ua, 40);
        }

        return $browser . ($os ? ' on ' . $os : '');
    }
}
