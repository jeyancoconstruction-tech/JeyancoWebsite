<?php

namespace App\Support;

use App\Models\Kiosk;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Whether a kiosk is talking to us, read one way for every page that asks.
 *
 * Device Monitoring and System Settings both answer "is it on?", and two
 * answers would sooner or later disagree. Two signals count: the Pi's GPS
 * heartbeat (KioskLocationController writes it every ~30 s) and the kiosk
 * screen asking for its settings (every ~5 s). A kiosk that has sent neither
 * for three minutes is off.
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

        // The kiosk screen also asks for its settings every few seconds. That
        // is a sign of life too: a kiosk whose GPS tracker has stopped (or
        // whose GPS module is unplugged) is still on and still scanning, and
        // must not show as off. The newer of the two counts.
        $read = $kiosk->settingsReadAt();
        if ($read && (! $lastSeen || $read->greaterThan($lastSeen))) {
            $lastSeen = Carbon::parse($read);
        }
        $seconds  = $lastSeen ? (int) abs($lastSeen->diffInSeconds($now, true)) : null;

        $state = match (true) {
            $seconds === null                         => 'off',
            $seconds <= self::LATE_AFTER_SECONDS      => 'ok',
            $seconds <= self::OFFLINE_AFTER_SECONDS   => 'late',
            default                                   => 'off',
        };

        return ['state' => $state, 'seconds' => $seconds, 'last_seen' => $lastSeen, 'fix' => $fix];
    }

    /**
     * One kiosk as the monitors list it: whether it is on, when it was last
     * heard, when its settings last reached it, and where it is against the
     * pin of the site it is set to — for the map, measured here so the page
     * and the numbers under it cannot disagree.
     */
    public static function line(Kiosk $kiosk, ?Carbon $now = null): array
    {
        $now    ??= now();
        $status = self::of($kiosk, $now);
        $fix    = $status['fix'] ?? [];
        $read   = $kiosk->settingsReadAt();
        $ago    = fn (?\Carbon\Carbon $at) => $at?->diffForHumans($now, ['short' => true, 'syntax' => Carbon::DIFF_RELATIVE_TO_NOW]);

        $lat = isset($fix['lat']) ? (float) $fix['lat'] : null;
        $lng = isset($fix['lng']) ? (float) $fix['lng'] : null;
        $gps = match (true) {
            $lat === null || $lng === null                               => 'none',
            ($fix['status'] ?? null) === 'fix' && $status['state'] !== 'off' => 'fix',
            default                                                      => 'stale',
        };

        $site     = $kiosk->site;
        $pinned   = $site && $site->isPinned();
        $distance = $pinned && $lat !== null ? round($site->metresFrom($lat, $lng)) : null;
        $radius   = $site ? $site->geofenceRadius() : null;

        return [
            'id'      => $kiosk->id,
            'name'    => $kiosk->name,
            'code'    => $kiosk->code,
            'site'    => $site?->name,
            'state'   => $status['state'],
            'seen'    => $ago($status['last_seen']),
            'seen_at' => $status['last_seen']?->format('M j · g:i A'),
            'read'    => $ago($read),
            'map'     => [
                'gps'      => $gps,
                'lat'      => $lat,
                'lng'      => $lng,
                'fix_at'   => $status['last_seen']?->format('g:i:s A'),
                'site'     => $pinned ? ['name' => $site->name, 'lat' => (float) $site->latitude, 'lng' => (float) $site->longitude, 'radius' => $radius] : null,
                'distance' => $distance,
                'inside'   => $distance === null ? null : $distance <= $radius,
            ],
        ];
    }
}
