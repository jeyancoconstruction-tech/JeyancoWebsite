<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Kiosk;
use App\Models\Shift;
use App\Support\KioskFeed;
use App\Support\KioskStatus;
use Illuminate\Support\Carbon;

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
    /** How long silence makes a kiosk late, then offline: see KioskStatus. */
    private const OFFLINE_AFTER_SECONDS = KioskStatus::OFFLINE_AFTER_SECONDS;
    private const LATE_AFTER_SECONDS = KioskStatus::LATE_AFTER_SECONDS;

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
        ['state' => $state, 'seconds' => $seconds, 'last_seen' => $lastSeen, 'fix' => $fix] = KioskStatus::of($kiosk, $now);

        $hasCoords = isset($fix['lat'], $fix['lng']) && $fix['lat'] !== null && $fix['lng'] !== null;
        $gps = match (true) {
            ! $hasCoords                                              => 'none',
            ($fix['status'] ?? null) === 'fix' && $state !== 'off'    => 'fix',
            default                                                   => 'stale',
        };

        // Today's time-ins and time-outs at this kiosk, by the hour they happened.
        // And each of them as a line, newest first, for the card's list.
        $hours = array_fill(0, self::HOURS, 0);
        $scans = 0;
        $today = [];
        foreach (Attendance::with('employee:id,name')->where('kiosk_id', $kiosk->id)->onWorkday()->get(['id', 'employee_id', 'session', 'time_in', 'time_out', 'close_type']) as $row) {
            foreach (['time_in', 'time_out'] as $column) {
                if (! $row->{$column}) {
                    continue;
                }
                $today[] = [
                    'name'    => $row->employee?->name ?? 'Unknown worker',
                    'kind'    => $column === 'time_in' ? 'in' : 'out',
                    'session' => $row->session,
                    'auto'    => $column === 'time_out' && $row->close_type === 'auto',
                    'at'      => Carbon::parse($row->{$column}),
                ];
                $scans++;
                $slot = Carbon::parse($row->{$column})->hour - self::FIRST_HOUR;
                $hours[max(0, min(self::HOURS - 1, $slot))]++;
            }
        }

        // Scans the web turned away today — a finger nobody knows, a time in
        // twice — are not attendance, but they happened at the kiosk.
        foreach (KioskFeed::since($kiosk) as $e) {
            if (in_array($e['kind'], ['rej', 'warn', 'unknown'], true) && KioskFeed::when($e)->isSameDay($now)) {
                $today[] = ['name' => $e['name'] ?? 'Unknown finger', 'kind' => 'rej', 'session' => null, 'auto' => false,
                            'at' => KioskFeed::when($e), 'why' => $e['kind'] === 'unknown' ? 'Not recognised' : ($e['message'] ?? 'Turned away')];
            }
        }
        usort($today, fn ($a, $b) => $b['at'] <=> $a['at']);

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
            'today'       => array_slice($today, 0, 40),
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
