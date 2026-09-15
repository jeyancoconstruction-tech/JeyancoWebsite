<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Kiosk;
use App\Models\Shift;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * The attendance kiosks, and whether they are talking to us.
 *
 * Nothing here changes how a kiosk works. It reads what the kiosk already
 * writes: the `kiosks` row, the same 'kiosk_location_*' cache entry
 * KioskLocationController fills from the Pi's GPS heartbeat, and the
 * attendance the device produced today.
 */
class DeviceMonitoringController extends Controller
{
    /** The prefix KioskLocationController writes. Read here, never written. */
    private const LOC_PREFIX = 'kiosk_location_';

    /** A device silent for longer than this is treated as offline. */
    private const OFFLINE_AFTER_SECONDS = 180;

    /**
     * Past this a kiosk is still online, but has missed a heartbeat or two.
     * A display tier only: nothing else reads it, and offline is unchanged.
     */
    private const LATE_AFTER_SECONDS = 90;

    /** The strip draws 6 AM to 8 PM, one column an hour. */
    private const FIRST_HOUR = 6;
    private const HOURS = 14;

    public function index()
    {
        $now = now();

        $devices = Kiosk::with('site')->orderBy('name')->get()
            ->map(fn (Kiosk $kiosk) => $this->device($kiosk, $now))
            ->sortBy(fn ($d) => [['off' => 0, 'late' => 1, 'ok' => 2][$d['state']], strtolower($d['kiosk']->name)])
            ->values();

        return view('devices.index', [
            'devices' => $devices,
            'summary' => [
                'total'   => $devices->count(),
                'online'  => $devices->whereIn('state', ['ok', 'late'])->count(),
                'late'    => $devices->where('state', 'late')->count(),
                'offline' => $devices->where('state', 'off')->count(),
                'scans'   => $devices->sum('scans'),
                'fix'     => $devices->where('gps', 'fix')->count(),
                'nosig'   => $devices->where('gps', 'none')->count(),
                'stale'   => $devices->where('gps', 'stale')->count(),
            ],
            'bands'        => $this->bands(),
            'nowAt'        => $this->position($now),
            'firstHour'    => self::FIRST_HOUR,
            'hours'        => self::HOURS,
            'offlineAfter' => self::OFFLINE_AFTER_SECONDS,
            'lateAfter'    => self::LATE_AFTER_SECONDS,
            'checkedAt'    => $now,
        ]);
    }

    private function device(Kiosk $kiosk, Carbon $now): array
    {
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

        $hasCoords = isset($fix['lat'], $fix['lng']) && $fix['lat'] !== null && $fix['lng'] !== null;
        $gps = match (true) {
            ! $hasCoords                                              => 'none',
            ($fix['status'] ?? null) === 'fix' && $state !== 'off'    => 'fix',
            default                                                   => 'stale',
        };

        // Today's time-ins and time-outs at this kiosk, by the hour they happened.
        $hours = array_fill(0, self::HOURS, 0);
        $scans = 0;
        foreach (Attendance::where('kiosk_id', $kiosk->id)->onWorkday()->get(['id', 'time_in', 'time_out']) as $row) {
            foreach (['time_in', 'time_out'] as $column) {
                if (! $row->{$column}) {
                    continue;
                }
                $scans++;
                $slot = Carbon::parse($row->{$column})->hour - self::FIRST_HOUR;
                $hours[max(0, min(self::HOURS - 1, $slot))]++;
            }
        }

        $last = Attendance::with('employee:id,name')->where('kiosk_id', $kiosk->id)->latest('updated_at')->first();

        return [
            'kiosk'       => $kiosk,
            'state'       => $state,
            'seconds'     => $seconds,
            'last_seen'   => $lastSeen,
            'pct'         => $seconds === null ? 100 : min(100, round($seconds / self::OFFLINE_AFTER_SECONDS * 100, 1)),
            'over'        => $seconds !== null && $seconds > self::OFFLINE_AFTER_SECONDS ? $seconds - self::OFFLINE_AFTER_SECONDS : null,
            'gps'         => $gps,
            'gps_status'  => $fix['status'] ?? null,
            'lat'         => $hasCoords ? (float) $fix['lat'] : null,
            'lng'         => $hasCoords ? (float) $fix['lng'] : null,
            'hours'       => $hours,
            'scans'       => $scans,
            // Where a silence began on today's strip, if it began today.
            'silent_from' => $state === 'off' && $lastSeen && $lastSeen->isSameDay($now) ? $this->position($lastSeen) : null,
            'last'        => $last,
            'last_out'    => $last && $last->time_out,
            'last_at'     => $last ? Carbon::parse($last->time_out ?: $last->time_in) : null,
        ];
    }

    /**
     * The day shift's two sessions, as positions on the strip. Read from the
     * shift itself so the bands move when the office moves the hours.
     */
    private function bands(): array
    {
        $shift = Shift::where('crosses_midnight', false)->orderBy('id')->first();

        $pairs = [
            [$shift?->am_starts_at ?? '08:00', $shift?->am_ends_at ?? '12:00'],
            [$shift?->pm_starts_at ?? '13:00', $shift?->pm_ends_at ?? '17:00'],
        ];

        return collect($pairs)->map(function ($pair) {
            [$from, $to] = array_map(fn ($t) => Carbon::parse(is_string($t) ? $t : (string) $t), $pair);

            return [
                'from'  => $this->position($from),
                'to'    => $this->position($to),
                'label' => $from->format('g') . '–' . $to->format('g'),
            ];
        })->all();
    }

    /** Hours past the strip's first hour, clamped to the strip. */
    private function position(Carbon $at): float
    {
        return max(0, min(self::HOURS, $at->hour + $at->minute / 60 - self::FIRST_HOUR));
    }
}
