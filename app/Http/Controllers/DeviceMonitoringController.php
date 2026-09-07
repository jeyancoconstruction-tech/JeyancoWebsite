<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Kiosk;
use Illuminate\Support\Facades\Cache;

/**
 * The attendance kiosks, and whether they are talking to us.
 *
 * Nothing here changes how a kiosk works. It reads what the kiosk already
 * writes: the `kiosks` row, the same 'kiosk_location_*' cache entry
 * KioskLocationController fills from the Pi's GPS heartbeat, and the most
 * recent attendance the device produced.
 */
class DeviceMonitoringController extends Controller
{
    /** The prefix KioskLocationController writes. Read here, never written. */
    private const LOC_PREFIX = 'kiosk_location_';

    /** A device silent for longer than this is treated as offline. */
    private const OFFLINE_AFTER_SECONDS = 180;

    public function index()
    {
        $kiosks = Kiosk::with('site')->orderBy('name')->get();

        $devices = $kiosks->map(function (Kiosk $kiosk) {
            // The Pi may be keyed by numeric id or by code, depending on how
            // the device was configured. Try both rather than showing a
            // working kiosk as silent.
            $fix = Cache::get(self::LOC_PREFIX . $kiosk->id)
                ?? Cache::get(self::LOC_PREFIX . $kiosk->code);

            $lastSeen = $fix['last_seen'] ?? null;
            $seconds  = $lastSeen ? now()->diffInSeconds(\Carbon\Carbon::parse($lastSeen)) : null;

            $lastAttendance = Attendance::where('kiosk_id', $kiosk->id)
                ->latest('updated_at')
                ->first();

            return [
                'kiosk'           => $kiosk,
                'online'          => $seconds !== null && $seconds <= self::OFFLINE_AFTER_SECONDS,
                'has_fix'         => ($fix['status'] ?? null) === 'fix',
                'last_seen'       => $lastSeen,
                'seconds_ago'     => $seconds,
                'lat'             => $fix['lat'] ?? null,
                'lng'             => $fix['lng'] ?? null,
                'last_attendance' => $lastAttendance,
                'today_count'     => Attendance::where('kiosk_id', $kiosk->id)
                                        ->whereDate('date', now()->toDateString())->count(),
            ];
        });

        return view('devices.index', [
            'devices' => $devices,
            'summary' => [
                'total'   => $devices->count(),
                'online'  => $devices->where('online', true)->count(),
                'offline' => $devices->where('online', false)->count(),
            ],
            'offlineAfter' => self::OFFLINE_AFTER_SECONDS,
        ]);
    }
}

