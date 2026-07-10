<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Live GPS position of a Raspberry Pi kiosk (NEO-M8L), posted every ~30s by
 * gps_tracker.py. Only the latest fix matters, so it lives in the cache for a
 * day rather than in a table — no migration, and stale fixes expire themselves.
 */
class KioskLocationController extends Controller
{
    private const CACHE_PREFIX = 'kiosk_location_';

    /** POST /api/location  {kiosk_id, lat, lng} */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'kiosk_id' => 'required|string|max:50',
            'lat'      => 'required|numeric|between:-90,90',
            'lng'      => 'required|numeric|between:-180,180',
        ]);

        Cache::put(
            self::CACHE_PREFIX . $data['kiosk_id'],
            [
                'kiosk_id'   => $data['kiosk_id'],
                'lat'        => (float) $data['lat'],
                'lng'        => (float) $data['lng'],
                'updated_at' => now()->toIso8601String(),
            ],
            now()->addDay()
        );

        return response()->json(['success' => true]);
    }

    /** GET /api/location/{kioskId} */
    public function latest(string $kioskId): JsonResponse
    {
        $fix = Cache::get(self::CACHE_PREFIX . $kioskId);

        if (! $fix) {
            return response()->json(['lat' => null, 'lng' => null]);
        }

        return response()->json($fix);
    }

    /**
     * GET /api/location/latest?kiosk_id=…
     * The dashboard map calls this query-string form; keep it working alongside
     * the path form above.
     */
    public function latestByQuery(Request $request): JsonResponse
    {
        $kioskId = trim((string) $request->query('kiosk_id', ''));

        if ($kioskId === '') {
            return response()->json(['lat' => null, 'lng' => null]);
        }

        return $this->latest($kioskId);
    }
}
