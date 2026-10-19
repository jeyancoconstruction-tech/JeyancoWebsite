<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\Kiosk;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Proof that a finger was read at a kiosk, just now.
 *
 * When the Pi reports a scan (/scan-attendance), the answer carries a random
 * token tied to the worker it recognised. The kiosk hands it back with the
 * TIME IN / TIME OUT it records, and with the DTR or complaint it opens. A
 * token is good for config('kiosk.scan_token_ttl') seconds, and a time in or
 * out uses it up — one finger, one record.
 *
 * Enforced only when config('kiosk.device_key') is set: then nobody but a Pi
 * can report a scan (App\Http\Middleware\KioskDevice), so nobody but a Pi
 * can mint a token, and attendance can only be a real, current scan.
 */
final class KioskScan
{
    private const KEY = 'kiosk_scan_';

    public static function enforced(): bool
    {
        return (string) config('kiosk.device_key') !== '';
    }

    public static function issue(Employee $employee, ?Kiosk $kiosk = null): string
    {
        $token = Str::random(40);

        Cache::put(self::KEY . $token, [
            'employee_id' => $employee->id,
            'kiosk_id'    => $kiosk?->id,
            'at'          => now()->toIso8601String(),
        ], now()->addSeconds(max(30, (int) config('kiosk.scan_token_ttl', 180))));

        return $token;
    }

    /** The scan behind a token, without using it up; null when unknown or expired. */
    public static function peek(mixed $token): ?array
    {
        if (! is_string($token) || strlen($token) !== 40) {
            return null;
        }

        $scan = Cache::get(self::KEY . $token);

        return is_array($scan) && isset($scan['employee_id']) ? $scan : null;
    }

    /** The worker behind a token, or null. */
    public static function employee(mixed $token): ?Employee
    {
        $scan = self::peek($token);

        return $scan ? Employee::with(['shift', 'site', 'laborType'])->find($scan['employee_id']) : null;
    }

    /** Use the token up for this worker. False when it is missing, expired or somebody else's. */
    public static function consume(mixed $token, int $employeeId): bool
    {
        $scan = self::peek($token);

        if (! $scan || (int) $scan['employee_id'] !== $employeeId) {
            return false;
        }

        Cache::forget(self::KEY . $token);

        return true;
    }
}
