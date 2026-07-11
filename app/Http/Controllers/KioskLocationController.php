<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\KioskAlert;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Live position + anti-theft heartbeat for a Raspberry Pi kiosk (NEO-M8L),
 * posted every ~30s by gps_tracker.py.
 *
 * Everything lives in the cache — the latest fix, the "home" geofence anchor,
 * and the last alert state — so there is no migration and stale data expires
 * itself. Offline detection is LAZY: there is no scheduler on this deployment,
 * so we recompute presence whenever the location is read (the dashboard polls
 * /api/location/latest every few seconds, which is enough to catch an
 * unplugged/offline Pi and raise the alert).
 */
class KioskLocationController extends Controller
{
    private const LOC_PREFIX   = 'kiosk_location_';   // latest heartbeat + fix
    private const HOME_PREFIX  = 'kiosk_home_';       // geofence anchor (first good fix)
    private const STATE_PREFIX = 'kiosk_alert_state_'; // last alerted presence/geofence

    /**
     * POST /api/location  {kiosk_id, lat?, lng?, status?, timestamp?}
     *
     * lat/lng are nullable so a "no_fix" heartbeat still proves the Pi is alive
     * — that is the whole point for anti-theft: silence must be distinguishable
     * from "GPS just has no fix yet".
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'kiosk_id'  => 'required|string|max:50',
            'lat'       => 'nullable|numeric|between:-90,90',
            'lng'       => 'nullable|numeric|between:-180,180',
            'status'    => 'nullable|string|in:fix,no_fix',
            'timestamp' => 'nullable|integer',
        ]);

        $kioskId = $data['kiosk_id'];

        // Backward compatible: an older Pi that only sends lat/lng (no status)
        // is treated as a good fix.
        $hasFix = ($data['status'] ?? null) !== 'no_fix'
            && isset($data['lat'], $data['lng'])
            && $data['lat'] !== null && $data['lng'] !== null;

        $prev = Cache::get(self::LOC_PREFIX . $kioskId, []);

        $record = [
            'kiosk_id'   => $kioskId,
            // Keep the last GOOD coordinates even across no_fix heartbeats.
            'lat'        => $hasFix ? (float) $data['lat'] : ($prev['lat'] ?? null),
            'lng'        => $hasFix ? (float) $data['lng'] : ($prev['lng'] ?? null),
            'status'     => $hasFix ? 'fix' : 'no_fix',
            'last_seen'  => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
        ];

        Cache::put(self::LOC_PREFIX . $kioskId, $record, now()->addDays(7));

        // The first good fix becomes the geofence anchor ("home").
        if ($hasFix && ! Cache::has(self::HOME_PREFIX . $kioskId)) {
            Cache::put(self::HOME_PREFIX . $kioskId, [
                'lat' => (float) $data['lat'],
                'lng' => (float) $data['lng'],
                'set_at' => now()->toIso8601String(),
            ], now()->addDays(30));
        }

        // Evaluate now so a geofence breach alerts on the very heartbeat that
        // carries the offending fix — while the Pi is still online.
        $this->evaluateAlerts($kioskId);

        return response()->json(['success' => true]);
    }

    /** GET /api/location/{kioskId} */
    public function latest(string $kioskId): JsonResponse
    {
        $snapshot = $this->snapshot($kioskId);

        if (! $snapshot) {
            return response()->json(['lat' => null, 'lng' => null]);
        }

        return response()->json($snapshot);
    }

    /** GET /api/location/latest?kiosk_id=…  (the dashboard map polls this form) */
    public function latestByQuery(Request $request): JsonResponse
    {
        $kioskId = trim((string) $request->query('kiosk_id', ''));

        if ($kioskId === '') {
            return response()->json(['lat' => null, 'lng' => null]);
        }

        return $this->latest($kioskId);
    }

    /** GET /api/location/{kioskId}/status  (explicit anti-theft status) */
    public function status(string $kioskId): JsonResponse
    {
        $snapshot = $this->snapshot($kioskId);

        if (! $snapshot) {
            return response()->json([
                'kiosk_id' => $kioskId,
                'alert'    => 'never_seen',
                'online'   => false,
                'lat'      => null,
                'lng'      => null,
            ]);
        }

        return response()->json($snapshot);
    }

    /**
     * Build the read model AND run alert evaluation (lazy offline detection).
     * Returns null if the kiosk has never checked in.
     */
    private function snapshot(string $kioskId): ?array
    {
        $rec = Cache::get(self::LOC_PREFIX . $kioskId);
        if (! $rec) {
            return null;
        }

        $this->evaluateAlerts($kioskId);

        $state = $this->computeState($kioskId, $rec);

        return [
            'kiosk_id'          => $kioskId,
            'lat'               => $rec['lat'],
            'lng'               => $rec['lng'],
            'status'            => $rec['status'],       // fix | no_fix
            'updated_at'        => $rec['updated_at'],
            'last_seen'         => $rec['last_seen'],
            'seconds_since_seen'=> $state['seconds_since'],
            'online'            => $state['presence'] === 'online',
            'in_geofence'       => $state['geofence'] === 'unknown' ? null : ($state['geofence'] === 'inside'),
            'distance_m'        => $state['distance'],
            'geofence_radius_m' => (int) config('kiosk.geofence_radius'),
            'alert'             => $state['alert'],
        ];
    }

    /**
     * Presence (online/offline) + geofence (inside/outside/unknown) from the
     * cached record. Pure — no side effects.
     */
    private function computeState(string $kioskId, array $rec): array
    {
        $offlineAfter = (int) config('kiosk.offline_after');
        $radius       = (int) config('kiosk.geofence_radius');

        $seconds  = $rec['last_seen'] ? Carbon::parse($rec['last_seen'])->diffInSeconds(now()) : PHP_INT_MAX;
        $presence = $seconds <= $offlineAfter ? 'online' : 'offline';

        $geofence = 'unknown';
        $distance = null;
        $home     = Cache::get(self::HOME_PREFIX . $kioskId);

        if ($home && $rec['status'] === 'fix' && $rec['lat'] !== null && $rec['lng'] !== null) {
            $distance = round($this->haversine($home['lat'], $home['lng'], $rec['lat'], $rec['lng']), 1);
            $geofence = $distance <= $radius ? 'inside' : 'outside';
        }

        $alert = match (true) {
            $presence === 'offline' => 'offline',
            $geofence === 'outside' => 'outside_geofence',
            $rec['status'] === 'no_fix' => 'no_fix',
            default => 'online',
        };

        return compact('seconds', 'presence', 'geofence', 'distance', 'alert') + ['seconds_since' => $seconds];
    }

    /**
     * Compare current state to the last alerted state; notify admins on any
     * transition into (or out of) a bad state. De-duplicated via the cached
     * state so each transition fires exactly once.
     */
    private function evaluateAlerts(string $kioskId): void
    {
        $rec = Cache::get(self::LOC_PREFIX . $kioskId);
        if (! $rec) {
            return;
        }

        $now  = $this->computeState($kioskId, $rec);
        // Assume "healthy" before the first observation so the first bad
        // transition still fires.
        $prev = Cache::get(self::STATE_PREFIX . $kioskId, ['presence' => 'online', 'geofence' => 'inside']);

        $label = strtoupper($kioskId);

        // Presence transitions.
        if ($prev['presence'] === 'online' && $now['presence'] === 'offline') {
            $this->alertAdmins('kiosk_offline', "Kiosk offline: {$label}",
                "Walang heartbeat mula sa {$label} nang lampas " . config('kiosk.offline_after') . "s. Baka na-unplug o nawalan ng power/internet.");
        } elseif ($prev['presence'] === 'offline' && $now['presence'] === 'online') {
            $this->alertAdmins('kiosk_online', "Kiosk online ulit: {$label}",
                "Bumalik na online ang {$label}.");
        }

        // Geofence transitions (only meaningful once we have a home + a fix).
        if ($now['geofence'] !== 'unknown') {
            if (in_array($prev['geofence'], ['inside', 'unknown'], true) && $now['geofence'] === 'outside') {
                $d = $now['distance'] !== null ? number_format($now['distance']) . 'm' : '?';
                $this->alertAdmins('kiosk_geofence', "Kiosk lumayo sa lugar: {$label}",
                    "Ang {$label} ay {$d} mula sa naitakdang lokasyon (limit " . config('kiosk.geofence_radius') . "m). Posibleng ninakaw/inilipat.");
            } elseif ($prev['geofence'] === 'outside' && $now['geofence'] === 'inside') {
                $this->alertAdmins('kiosk_geofence_ok', "Kiosk bumalik sa lugar: {$label}",
                    "Nasa loob na ulit ng geofence ang {$label}.");
            }
        }

        Cache::put(self::STATE_PREFIX . $kioskId, [
            'presence' => $now['presence'],
            'geofence' => $now['geofence'],
        ], now()->addDays(30));
    }

    private function alertAdmins(string $subtype, string $title, string $message): void
    {
        foreach (User::where('is_admin', true)->get() as $admin) {
            KioskAlert::fire($admin, $subtype, $title, $message);
        }
    }

    /** Great-circle distance in metres. */
    private function haversine(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earth = 6_371_000; // metres
        $dLat  = deg2rad($lat2 - $lat1);
        $dLon  = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return $earth * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
