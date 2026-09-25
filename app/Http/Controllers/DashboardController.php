<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Attendance;
use App\Models\Kiosk;
use App\Models\Site;
use App\Services\PayrollService;
use App\Support\GoogleHolidays;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    public function index(PayrollService $payroll)
    {
        // Active workforce only — pending kiosk detections and archived/removed
        // workers are excluded so the dashboard reflects current employees.
        // The holiday calendar's daily refresh from Google Calendar rides on
        // the page opened most, and runs after it has been sent.
        GoogleHolidays::syncIfStale();

        $employees = Employee::active()->get();

        $totalEmployees = $employees->count();

        // Today is the rows Today's Attendance lists, by the same scopes, so
        // these tiles, the Live Attendance panel and the page its "View all"
        // opens cannot disagree about who is here. That is the day each crew
        // is working, not the calendar's — at 1am the night shift's rows are
        // dated yesterday — but a night that is over is history even before
        // the crew's next day opens. Matched on where a clock would land,
        // last night's finished shift sat under TODAY here all the next day
        // while the Attendance page showed nobody. Pending workers' old rows
        // are left out, as they are there.
        $now       = Carbon::now();
        $todayRows = fn () => Attendance::ofRegistered()
                                        ->fromWorkday($now)
                                        ->whereNotNull('time_in');

        $presentToday = $todayRows()->distinct('employee_id')->count('employee_id');

        // Weekly payout for the current Mon–Sun week — computed by PayrollService
        // so it matches Payroll Records exactly (single source of truth).
        $from = Carbon::now()->startOfWeek(Carbon::MONDAY)->toDateString();
        $to   = Carbon::now()->endOfWeek(Carbon::SUNDAY)->toDateString();
        //
        // Kept between opens until something payroll reads changes: every
        // tab with the dashboard open re-reads it on each clock-in, and the
        // week is the same answer for all of them.
        $netOf = fn (array $computed) => collect($computed['employees'])->sum(fn ($e) => $e['totals']['net']);

        $weeklyPayroll = $payroll->remembered('dashboard.this-week', $from, $to, $netOf);

        $pendingVale = $employees->sum('vale');

        // ── Deltas (read-only, for the stat-card trend chips) ──────────────
        // The workday each crew last finished: the same line, a day earlier.
        $presentYesterday = Attendance::ofRegistered()
                                       ->onWorkday($now->copy()->subDay())
                                       ->whereNotNull('time_in')
                                       ->distinct('employee_id')
                                       ->count('employee_id');

        $lwFrom = Carbon::now()->subWeek()->startOfWeek(Carbon::MONDAY)->toDateString();
        $lwTo   = Carbon::now()->subWeek()->endOfWeek(Carbon::SUNDAY)->toDateString();
        $lastWeekPayroll = $payroll->remembered('dashboard.last-week', $lwFrom, $lwTo, $netOf);

        $newThisWeek = Employee::active()
            ->where('created_at', '>=', Carbon::now()->subDays(7))
            ->count();

        // ── Live Attendance (today) ────────────────────────────────────────
        $todayAttendance = $todayRows()->with(['employee', 'shift'])
            ->orderByDesc('time_in')
            // The panel scrolls its own body. Twelve rows fill it, and the
            // rest are a scroll away rather than a page away.
            ->take(12)
            ->get();

        // Attendance chart — workers present on each workday, counted by
        // person like the tiles. It counted rows and its tooltip called them
        // hours, so one worker's morning and afternoon read as "2 hrs".
        $attendanceLabels = [];
        $attendanceData = [];
        for ($i=6; $i>=0; $i--) {
            $date = Carbon::today()->subDays($i);
            $attendanceLabels[] = $date->format('M d');
            $attendanceData[] = Attendance::ofRegistered()
                ->where('date', $date->format('Y-m-d'))
                ->whereNotNull('time_in')
                ->distinct('employee_id')
                ->count('employee_id');
        }

        // ── Still on site: timed in today and not yet out ──────────────────
        $stillIn = $todayRows()
            ->whereNull('time_out')
            ->distinct('employee_id')
            ->count('employee_id');

        // ── Kiosks reporting ───────────────────────────────────────────────
        $devices = $this->deviceSummary();

        return view('dashboard', compact(
            'employees',
            'totalEmployees',
            'presentToday',
            'weeklyPayroll',
            'pendingVale',
            'presentYesterday',
            'lastWeekPayroll',
            'newThisWeek',
            'todayAttendance',
            'attendanceLabels',
            'attendanceData',
            'stillIn',
            'devices'
        ));
    }

    /**
     * GET /dashboard/map: what the Project Sites map draws. It shows every
     * pinned site with its range, and every kiosk where its GPS last put it,
     * inside its site's range or not.
     *
     * Read-only. Sites are pinned on the Sites page and nowhere else. The fix
     * is the one KioskLocationController caches from the Pi's heartbeat.
     *
     * A kiosk is measured against the site it is set to. Failing that, it
     * counts as at whichever site's range it is standing in.
     */
    public function map(): JsonResponse
    {
        $sites  = Site::withCount('kiosks')->orderBy('name')->get();
        $pinned = $sites->filter->isPinned();
        $quiet  = (int) config('kiosk.offline_after');
        $now    = now();

        $kiosks = Kiosk::with('site')->orderBy('name')->get()->map(function (Kiosk $kiosk) use ($pinned, $quiet, $now) {
            // The Pi may be keyed by id or by code (DeviceMonitoringController).
            $fix  = Cache::get('kiosk_location_' . $kiosk->id) ?? Cache::get('kiosk_location_' . $kiosk->code) ?? [];
            $seen = ! empty($fix['last_seen']) ? Carbon::parse($fix['last_seen']) : null;
            $ago  = $seen ? (int) $seen->diffInSeconds($now, true) : null;
            $online = $ago !== null && $ago <= $quiet;

            $lat = isset($fix['lat']) ? (float) $fix['lat'] : null;
            $lng = isset($fix['lng']) ? (float) $fix['lng'] : null;
            $located = $lat !== null && $lng !== null;
            $gps = match (true) {
                ! $located                                        => 'none',
                ($fix['status'] ?? null) === 'fix' && $online     => 'fix',
                default                                           => 'stale',
            };

            $set      = $kiosk->site?->isPinned() ? $kiosk->site : null;
            $distance = $located && $set ? $set->metresFrom($lat, $lng) : null;
            $at       = $located
                ? $pinned->filter(fn (Site $s) => $s->holds($lat, $lng))->sortBy(fn (Site $s) => $s->metresFrom($lat, $lng))->first()
                : null;

            $state = match (true) {
                ! $online                                  => 'offline',
                $gps !== 'fix'                             => 'nogps',
                $set && $set->holds($lat, $lng)            => 'in',
                $at && $set                                => 'elsewhere',
                (bool) $at                                 => 'in',
                default                                    => 'out',
            };

            return [
                'id'         => $kiosk->id,
                'name'       => $kiosk->name,
                'code'       => $kiosk->code,
                'site'       => $kiosk->site?->name,
                'site_id'    => $kiosk->site_id,
                'lat'        => $lat,
                'lng'        => $lng,
                'state'      => $state,
                'gps'        => $gps,
                'seen_ago'   => $ago,
                'distance_m' => $distance !== null ? (int) round($distance) : null,
                'radius_m'   => $set?->geofenceRadius(),
                'at_site'    => $at?->name,
            ];
        })->values();

        return response()->json([
            'sites' => $sites->map(fn (Site $s) => [
                'id'       => $s->id,
                'name'     => $s->name,
                'location' => $s->location,
                'lat'      => $s->latitude,
                'lng'      => $s->longitude,
                'radius_m' => $s->geofenceRadius(),
                'kiosks'   => $s->kiosks_count,
            ])->values(),
            'kiosks' => $kiosks,
        ]);
    }

    /**
     * Kiosk presence, from the same cache entry KioskLocationController fills
     * from the Pi's heartbeat. Read-only, and it invents nothing: a kiosk that
     * has never reported is counted as offline because that is what it is.
     */
    private function deviceSummary(): array
    {
        $kiosks  = Kiosk::all();
        $online  = 0;

        foreach ($kiosks as $k) {
            $fix = Cache::get('kiosk_location_' . $k->id) ?? Cache::get('kiosk_location_' . $k->code);
            $seen = $fix['last_seen'] ?? null;
            if ($seen && Carbon::parse($seen)->diffInSeconds(now()) <= 180) {
                $online++;
            }
        }

        return [
            'total'   => $kiosks->count(),
            'online'  => $online,
            'offline' => $kiosks->count() - $online,
        ];
    }
}