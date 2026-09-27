<?php

namespace App\Support;

use App\Models\Kiosk;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Whether a kiosk is talking to us, read one way for every page that asks.
 *
 * Device Monitoring and the kiosk monitor in System Settings both answer
 * "is it on?", and two answers would sooner or later disagree. The Pi's GPS
 * heartbeat (KioskLocationController writes it every ~30 s) is the signal: a
 * kiosk that has not sent one for three minutes is off.
 */
class KioskStatus
{
    /** The prefix KioskLocationController writes. Read here, never written. */
    public const LOC_PREFIX = 'kiosk_location_';

    /** A device silent for longer than this is treated as offline. */
    public const OFFLINE_AFTER_SECONDS = 180;

    /**
     * Past this a kiosk is still online, but has missed a heartbeat or two.
     * A display tier only: offline is unchanged by it.
     */
    public const LATE_AFTER_SECONDS = 90;

    /**
     * @return array{state: 'ok'|'late'|'off', seconds: ?int, last_seen: ?Carbon, fix: ?array}
     */
    public static function of(Kiosk $kiosk, ?Carbon $now = null): array
    {
        $now ??= now();

        // The Pi may be keyed by numeric id or by code, depending on how the
        // device was configured. Try both rather than showing a working kiosk
        // as silent.
        $fix = Cache::get(self::LOC_PREFIX . $kiosk->id) ?? Cache::get(self::LOC_PREFIX . $kiosk->code);

        $lastSeen = ! empty($fix['last_seen']) ? Carbon::parse($fix['last_seen']) : null;
        $seconds  = $lastSeen ? (int) abs($lastSeen->diffInSeconds($now, true)) : null;

        $state = match (true) {
            $seconds === null                         => 'off',
            $seconds <= self::LATE_AFTER_SECONDS      => 'ok',
            $seconds <= self::OFFLINE_AFTER_SECONDS   => 'late',
            default                                   => 'off',
        };

        return ['state' => $state, 'seconds' => $seconds, 'last_seen' => $lastSeen, 'fix' => $fix];
    }
}
