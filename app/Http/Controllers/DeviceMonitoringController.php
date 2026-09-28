<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Kiosk;
use App\Models\Shift;
use App\Support\KioskFeed;
use App\Support\KioskStatus;
use Illuminate\Http\Request;
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

    /**
     * What each kiosk's screen is showing, for the kiosk console on this page.
     *
     * The kiosk itself is a page on the Pi; nothing streams its screen. What
     * it shows is drawn from what it reads from us — the "who is on site"
     * board, the roster it enrols from, the last scan — so reading the same
     * answers shows the office the same thing the site sees. A kiosk that has
     * stopped sending its heartbeat is off, and all the monitor shows then is
     * that it is off and since when: its last board would be a guess.
     *
     * Read only. Nothing here records a scan, and the kiosk's own settings
     * poll is not called, so the monitor never counts as the kiosk checking in.
     */
    public function live(Request $request)
    {
        $now    = now();
        $kiosks = Kiosk::with('site')->orderBy('name')->get();

        $list = $kiosks->map(fn (Kiosk $k) => KioskStatus::line($k, $now))->values();

        $asked = $kiosks->firstWhere('id', (int) $request->query('kiosk'));
        $kiosk = $asked
            ?? $kiosks->first(fn (Kiosk $k) => KioskStatus::of($k, $now)['state'] !== 'off')
            ?? $kiosks->first();

        if (! $kiosk) {
            return response()->json(['kiosks' => [], 'screen' => null]);
        }

        $line   = $list->firstWhere('id', $kiosk->id);
        $screen = ['kiosk' => $line, 'on' => $line['state'] !== 'off'];

        // Each scan as the web answered it (RecordKioskEvent), newest after
        // `since`. A monitor opening asks with since=-1 and gets only the
        // number to start from, not the scans from before it was opened.
        $since  = (int) $request->query('since', -1);
        $events = $since < 0 ? [] : KioskFeed::since($kiosk, $since);
        $feed   = ['seq' => KioskFeed::last($kiosk), 'events' => $events];

        // The quick poll: is it on, and did anybody scan. The board is read
        // on the slower one, or straight after a scan.
        if ($request->boolean('light')) {
            return response()->json(['checked' => $now->format('g:i:s A'), 'kiosks' => $list, 'screen' => $screen] + $feed);
        }

        if ($screen['on']) {
            $ask = fn (string $method) => app(KioskController::class)
                ->{$method}(Request::create('/', 'GET', ['kiosk_id' => $kiosk->id]))
                ->getData(true);

            $last = \App\Models\Attendance::with('employee:id,name')
                ->where('kiosk_id', $kiosk->id)
                ->where('updated_at', '>=', $now->copy()->subHours(18))
                // The scan that happened last, not the row touched last.
                ->orderByRaw('COALESCE(time_out, time_in) DESC')->first();

            $screen += [
                'site'    => $kiosk->site?->name,
                'session' => $this->session($now),
                'board'   => $ask('todayAttendance'),
                'roster'  => $ask('roster'),
                'mode'    => \App\Models\SystemSetting::current()->kioskMode(),
                'last'    => $last ? [
                    'name' => $last->employee?->name ?? 'Unknown',
                    'type' => $last->time_out ? 'out' : 'in',
                    'at'   => \Carbon\Carbon::parse($last->time_out ?: $last->time_in)->format('g:i A'),
                    'ago'  => \Carbon\Carbon::parse($last->time_out ?: $last->time_in)->diffForHumans($now, ['short' => true, 'syntax' => \Carbon\Carbon::DIFF_RELATIVE_TO_NOW]),
                ] : null,
            ];
        }

        return response()->json([
            'checked' => $now->format('g:i:s A'),
            'kiosks'  => $list,
            'screen'  => $screen,
        ] + $feed);
    }

    /**
     * The kiosk's own screen — its v8 files, unchanged, in public/kiosk-screen
     * — drawn from this system's data, for the monitor to hold in a frame.
     */
    public function screen(Kiosk $kiosk)
    {
        $files = glob(public_path('kiosk-screen/*')) ?: [];

        return view('kiosk-screen', [
            'kiosk' => $kiosk,
            // The kiosk's reads go to screen-api/{sites|settings|…}.
            'api'   => url('device-monitoring/' . $kiosk->id . '/screen-api'),
            'v'     => $files ? max(array_map('filemtime', $files)) : 1,
        ]);
    }

    /**
     * What the kiosk screen reads, for one kiosk: its sites, its settings,
     * today's board and its roster — the same answers the device gets. Read
     * only; asking for the settings here is not the kiosk checking in.
     */
    public function screenApi(Request $request, Kiosk $kiosk, string $what)
    {
        $as = Request::create('/', 'GET', ['kiosk_id' => $kiosk->id]);
        $kiosks = app(KioskController::class);

        return match ($what) {
            'sites' => response()->json($kiosks->getSites()->getData(true) + [
                'active'     => $kiosk->site ? ['slug' => \Illuminate\Support\Str::slug($kiosk->site->name), 'id' => $kiosk->site->id, 'name' => $kiosk->site->name] : null,
                'kiosk_code' => $kiosk->code,
            ]),
            'settings'         => response()->json($kiosks->settingsAnswer((string) $request->query('v'))),
            'today-attendance' => $kiosks->todayAttendance($as),
            'roster'           => $kiosks->roster($as),
            default            => abort(404),
        };
    }

    /** The session pill in the kiosk's header, read off the day shift's hours. */
    private function session(Carbon $now): string
    {
        $day   = Shift::where('crosses_midnight', false)->orderBy('id')->first();
        $night = Shift::where('crosses_midnight', true)->orderBy('id')->first();
        $at    = fn ($t, $fallback) => $now->copy()->setTimeFromTimeString((string) ($t ?: $fallback));

        if ($now->lt($at($day?->am_ends_at, '12:00'))) {
            return 'AM SESSION';
        }
        if ($now->lt($at($day?->pm_ends_at, '17:00'))) {
            return 'PM SESSION';
        }

        return $night ? 'NIGHT SHIFT' : 'OFF-SHIFT';
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
