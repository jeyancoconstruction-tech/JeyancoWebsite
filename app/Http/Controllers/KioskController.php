<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Employee;
use App\Models\Attendance;
use App\Models\LaborType;
use App\Models\Project;
use App\Models\Kiosk;
use App\Models\Site;
use App\Models\Shift;
use App\Models\SystemSetting;
use App\Models\AuditLog;
use App\Support\WorkSchedule;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class KioskController extends Controller
{
    /**
     * A TIME OUT this soon after the TIME IN is the sensor reading the
     * same finger twice, not a shift that lasted seconds.
     */
    private const DOUBLE_READ_SECONDS = 60;

    /**
     * GPS attendance validation (anti-fraud). Returns null when the clock action
     * is allowed, or a ready-to-return rejection payload when it must be blocked.
     *
     * Designated location = the kiosk's assigned site coordinates (set by the
     * admin on the dashboard map). The kiosk's current position is taken from
     * the clock request's own lat/lng when present, otherwise the latest cached
     * GPS heartbeat. GPS off / no recent fix is rejected; being farther than the
     * configured radius is rejected. A kiosk with no designated coordinates, an
     * unresolvable kiosk, or the master switch off is left ungated.
     */
    /**
     * Where the kiosk is standing right now.
     *
     * One device is carried between sites, so the switcher on the kiosk is the
     * authority — not the row in the database, which only remembers where the
     * device was last time. An explicit site_id therefore wins over the kiosk's
     * stored site, and is written back so the dashboard, the geofence and any
     * later request that omits site_id all agree with what the operator picked.
     */
    private function activeSite(Request $request, ?Kiosk $kiosk): ?Site
    {
        if ($request->filled('site_id')) {
            $site = Site::find($request->site_id);
            if ($site) {
                if ($kiosk && $kiosk->site_id !== $site->id) {
                    $kiosk->forceFill(['site_id' => $site->id])->save();
                }
                $kiosk?->setRelation('site', $site);   // keep the geofence in step
                return $site;
            }
        }

        return $kiosk?->site;
    }

    /**
     * Sites the kiosk can switch between.
     *
     * The kiosk used to carry a hard-coded Site A / Site B toggle, so a new site
     * added on the web was unreachable until someone edited the Pi. Driving the
     * switcher off this list means adding a site on the web is all it takes.
     */
    public function getSites()
    {
        $sites = Site::orderBy('name')->get(['id', 'name', 'location', 'latitude', 'longitude']);

        return response()->json([
            'success' => true,
            'sites'   => $sites->map(fn ($s) => [
                'id'        => $s->id,
                // Stable key for gps_tracker.py, which names sites by slug.
                'slug'      => \Illuminate\Support\Str::slug($s->name),
                'name'      => $s->name,
                'location'  => $s->location,
                'latitude'  => $s->latitude,
                'longitude' => $s->longitude,
                // Geofence radius in metres, so the tracker stops carrying its
                // own hard-coded copy of coordinates that drift out of step with
                // whatever the admin set on the dashboard map.
                'radius'    => (int) config('kiosk.geofence_radius'),
            ])->values(),
            'count'   => $sites->count(),
            'radius'  => (int) config('kiosk.geofence_radius'),
        ]);
    }

    /**
     * The site the operator just picked on the kiosk.
     *
     * The web used to learn about a move only on the next scan or GPS
     * heartbeat, so the map and Device Monitoring could still say Site A for a
     * kiosk already standing at Site B. The kiosk now reports the pick as it
     * is made, and shows whether the web took it.
     */
    public function setActiveSite(Request $request)
    {
        $request->validate([
            'kiosk_id'   => 'nullable',
            'kiosk_code' => 'nullable|string',
            'site_id'    => 'nullable',
            'site'       => 'nullable|string',
        ]);

        $kiosk = Kiosk::resolve($request->kiosk_id, $request->kiosk_code);
        $want  = trim((string) ($request->input('site_id') ?? $request->input('site') ?? ''));

        $site = null;
        if ($want !== '') {
            $site = ctype_digit($want) ? Site::find((int) $want) : null;
            $site ??= Site::all()->first(
                fn ($s) => \Illuminate\Support\Str::slug($s->name) === \Illuminate\Support\Str::slug($want)
            );
        }

        if (! $kiosk || ! $site) {
            return response()->json([
                'success' => false,
                'message' => $kiosk ? "Unknown site '{$want}'." : 'Unknown kiosk.',
            ], 404);
        }

        $previous = $kiosk->site;
        $kiosk->forceFill(['site_id' => $site->id])->save();

        if (! $previous || $previous->id !== $site->id) {
            AuditLog::record('kiosk', 'updated',
                "Kiosk {$kiosk->code} set to {$site->name}" . ($previous ? " (was {$previous->name})" : ''), $kiosk);
        }

        return response()->json([
            'success' => true,
            'kiosk'   => $kiosk->code,
            'site'    => [
                'id'   => $site->id,
                'name' => $site->name,
                'slug' => \Illuminate\Support\Str::slug($site->name),
            ],
        ]);
    }

    private function locationGate(?Kiosk $kiosk, Request $request): ?array
    {
        if (! config('kiosk.enforce_location')) {
            return null;
        }
        if (! $kiosk) {
            return null; // can't identify the device → don't block existing flows
        }

        $site = $kiosk->site;
        $destLat = $site?->latitude;
        $destLng = $site?->longitude;
        if ($destLat === null || $destLng === null) {
            return null; // no designated location assigned yet → ungated
        }

        // Current position: prefer the coordinates sent with the scan, else the
        // latest cached heartbeat (must be a real, recent fix).
        $curLat = $request->input('lat');
        $curLng = $request->input('lng');

        if ($curLat === null || $curLng === null) {
            $cacheKey = 'kiosk_location_' . ($request->kiosk_id ?: $kiosk->code);
            $fix = Cache::get($cacheKey);
            $maxAge = (int) (config('kiosk.location_max_age') ?: config('kiosk.offline_after'));

            $fresh = $fix
                && ($fix['status'] ?? null) === 'fix'
                && ($fix['lat'] ?? null) !== null
                && isset($fix['last_seen'])
                && Carbon::parse($fix['last_seen'])->diffInSeconds(now()) <= $maxAge;

            if ($fresh) {
                $curLat = $fix['lat'];
                $curLng = $fix['lng'];
            }
        }

        if ($curLat === null || $curLng === null) {
            return [
                'success' => false,
                'code'    => 'no_gps',
                'message' => 'The kiosk is off or has no GPS fix, so attendance cannot be accepted. Turn location on and try again.',
            ];
        }

        $distance = $this->haversineMeters((float) $curLat, (float) $curLng, (float) $destLat, (float) $destLng);
        $radius   = (int) config('kiosk.geofence_radius');

        if ($distance > $radius) {
            return [
                'success'    => false,
                'code'       => 'outside_location',
                'message'    => 'Outside the authorised location (' . number_format($distance)
                                . 'm, limit ' . $radius . 'm), so attendance cannot be accepted.',
                'distance_m' => round($distance, 1),
            ];
        }

        return null; // within the designated location → allowed
    }

    /** Great-circle distance in metres. */
    private function haversineMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earth = 6_371_000;
        $dLat  = deg2rad($lat2 - $lat1);
        $dLon  = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return $earth * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * Get all labor types for position dropdown
     */
    /**
     * The workforce this kiosk should show, and who still needs a finger.
     *
     * The kiosk used to be where a worker typed their own name, position and
     * site — on a touchscreen, standing on a site, with the office having no
     * say until an admin approved it afterwards. The details belong where the
     * office already keeps them, so this turns the kiosk around: the admin
     * enters the worker on the web, and the kiosk only collects the one thing
     * a browser cannot — the fingerprint.
     *
     * Scoped to where the device is standing, so a kiosk at Site C lists the
     * people working at Site C. Those still missing a finger come first,
     * because they are the only rows anyone needs to act on.
     */
    public function roster(Request $request)
    {
        $kiosk = Kiosk::resolve($request->kiosk_id, $request->kiosk_code);
        $site  = $this->activeSite($request, $kiosk);

        $employees = Employee::with(['laborType', 'shift'])
            ->whereIn('status', [Employee::STATUS_ACTIVE, Employee::STATUS_PENDING])
            ->when($site, fn ($q) => $q->where('site_id', $site->id))
            ->orderBy('name')
            ->get();

        $rows = $employees->map(function (Employee $e) {
            $enrolled = ! empty($e->fingerprint_id);

            return [
                'id'               => $e->id,
                'name'             => $e->name,
                'position'         => $e->position ?: ($e->laborType->name ?? 'Worker'),
                'employment_type'  => $e->employment_type,
                'employment_label' => $e->employment_label,
                'shift'            => $this->shiftPayload($e),
                'fingerprint_id'   => $e->fingerprint_id,
                'enrolled'         => $enrolled,
                // What the kiosk puts on the badge. "Pending" here means the
                // finger is missing — not the admin-approval status, which the
                // worker standing at the kiosk has no way to act on.
                'state'            => $enrolled ? 'enrolled' : 'pending',
                // Lets the kiosk mark a just-added name so the person holding
                // the tablet can see their new hire arrive without reloading.
                'added_at'         => optional($e->created_at)->toIso8601String(),
                'is_new'           => $e->created_at
                    && $e->created_at->greaterThan(now()->subHours(24)),
            ];
        });

        // Needs-a-finger first, newest of those at the very top; everyone
        // already enrolled follows alphabetically.
        //
        // The order follows the actual job. Someone is standing at the kiosk
        // because a name was just added in the office, and that name is the one
        // they are looking for — putting it under "A" in a fifty-row list makes
        // them hunt for the thing they came to do. The enrolled half is a
        // reference list instead, so alphabetical is right there.
        $sorted = $rows->sortBy([
            fn ($a, $b) => ($a['enrolled'] ? 1 : 0) <=> ($b['enrolled'] ? 1 : 0),
            fn ($a, $b) => $a['enrolled']
                ? strcasecmp($a['name'], $b['name'])
                : strcmp((string) $b['added_at'], (string) $a['added_at']),
        ])->values();

        return response()->json([
            'success'   => true,
            'site'      => $site ? ['id' => $site->id, 'name' => $site->name] : null,
            'employees' => $sorted,
            'counts'    => [
                'total'    => $sorted->count(),
                'pending'  => $sorted->where('enrolled', false)->count(),
                'enrolled' => $sorted->where('enrolled', true)->count(),
            ],
        ]);
    }

    public function getLaborTypes()
    {
        $laborTypes = LaborType::select('id', 'name', 'daily_rate')->get()
            ->map(function ($lt) {
                return [
                    'id'          => $lt->id,
                    'name'        => $lt->name,
                    'daily_rate'  => $lt->daily_rate,
                    'hourly_rate' => $lt->getHourlyRate(),
                ];
            });

        return response()->json($laborTypes);
    }

    /**
     * Get all projects for registration dropdown
     */
    public function getProjects()
    {
        $projects = Project::select('id', 'name')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success'  => true,
            'projects' => $projects
        ]);
    }

    /**
     * Get all employees for dropdown
     */
    public function getEmployees()
    {
        $employees = Employee::select('id', 'name')->get();

        return response()->json($employees);
    }

    /**
     * Register new employee from kiosk
     */
    public function registerEmployee(Request $request)
    {
        // Accept BOTH the structured payload (labor_type_id / project_id) and the
        // kiosk UI's simpler payload (position name + project name).
        $request->validate([
            'name'          => 'required|string|max:255',
            'labor_type_id' => 'nullable|exists:labor_types,id',
            'position'      => 'nullable|string|max:255',
            'project_id'    => 'nullable|exists:projects,id',
            'project'       => 'nullable|string|max:255',
            'kiosk_id'      => 'nullable',
            'kiosk_code'    => 'nullable|string',
            'site_id'       => 'nullable|exists:sites,id',
            'fingerprint_id'=> 'nullable|string',
        ]);

        $kiosk = Kiosk::resolve($request->kiosk_id, $request->kiosk_code);

        // Keep the slot the kiosk just enrolled. Dropping it used to leave the
        // worker unable to clock in, and now that the sensor syncs against
        // active-fingerprints it would also get wiped off the R307 within a
        // minute as an orphan.
        $fp = null;

        // A pending row already holding this finger is this same worker, met
        // earlier by the scan loop. Fill it in instead of creating a second.
        $adopt = null;

        if ($request->filled('fingerprint_id')) {
            $fp     = (string) $request->fingerprint_id;
            $adopt  = Employee::pendingHolderOf($fp);

            if (! $adopt && $holder = Employee::releaseFingerprint($fp)) {
                return response()->json([
                    'success' => false,
                    'message' => Employee::fingerprintConflictMessage($holder, $fp),
                ]);
            }
        }

        // Resolve labor type from an explicit id, otherwise by matching the
        // position name to an existing labor type (so its rate is applied).
        $laborType = null;
        if ($request->filled('labor_type_id')) {
            $laborType = LaborType::find($request->labor_type_id);
        } elseif ($request->filled('position')) {
            $laborType = LaborType::whereRaw('LOWER(name) = ?', [strtolower(trim($request->position))])->first();
        }

        $position   = $laborType?->name ?? ($request->position ?: 'Worker');
        $hourlyRate = $laborType ? $laborType->getHourlyRate() : 0;

        // The kiosk's REGISTER form sends the chosen site by name in `project`
        // (its "PROJECT / SITE" field). A name that matches a real site IS a
        // site: recording it as a project created a shadow "Site B" row in the
        // projects table while employees.site_id — the column the whole web
        // reads — stayed on whatever site the device happened to sit at. The
        // worker picked Site B and the system showed Site A.
        $namedSite = $request->filled('project')
            ? Site::whereRaw('LOWER(name) = ?', [strtolower(trim($request->project))])->first()
            : null;

        // Explicit site_id wins, then the site named in the form, then wherever
        // the device is standing.
        $employeeSite = $request->filled('site_id')
            ? Site::find($request->site_id)
            : ($namedSite ?: $kiosk?->site);

        // Only a name that is NOT one of our sites is a genuine project.
        $projectId = $request->project_id;
        if (!$projectId && $request->filled('project') && !$namedSite) {
            $projectId = Project::firstOrCreate(['name' => trim($request->project)])->id;
        }

        $attributes = [
            'name'          => $request->name,
            'labor_type_id' => $laborType?->id,
            'position'      => $position,
            'rate_per_hour' => $hourlyRate,
            'project_id'    => $projectId,
            'kiosk_id'      => $kiosk?->id,
            'site_id'       => $employeeSite?->id,
            'fingerprint_id'=> $fp,
            // A worker the kiosk enrols starts on the day crew rather than on no
            // crew at all; the office moves them across on the employee list.
            'shift_id'      => Shift::defaultForNewHire(),
            // Kiosk registrations wait for admin acceptance before joining the
            // active workforce — the admin Accepts or Rejects them on the
            // Register & Manage page.
            'status'        => Employee::STATUS_PENDING,
        ];

        if ($adopt) {
            $adopt->fill($attributes)->save();
            $employee = $adopt->refresh();
        } else {
            $employee = Employee::create($attributes);
        }

        return response()->json([
            'success'  => true,
            'message'  => $employee->name . ' submitted — awaiting admin approval.',
            'employee' => [
                'id'           => $employee->id,
                'name'         => $employee->name,
                'position'     => $employee->position,
                'rate_per_hour'=> $employee->rate_per_hour,
                'fingerprint_id'=> $employee->fingerprint_id,
            ]
        ]);
    }

    /**
     * Get system settings and labor types for biometric display
     */
    public function getSettings()
    {
        $laborTypes = LaborType::select('id', 'name', 'daily_rate')->get();

        return response()->json([
            'success'     => true,
            'labor_types' => $laborTypes
        ]);
    }

    /**
     * Get employee by biometric/fingerprint ID
     */
    public function getEmployeeByBiometric(Request $request)
    {
        $request->validate([
            'fingerprint_id' => 'required|string',
        ]);

        $employee = Employee::where('fingerprint_id', $request->fingerprint_id)->first();

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Employee not found with this fingerprint ID'
            ]);
        }

        $laborType = $employee->laborType;

        return response()->json([
            'success'  => true,
            'employee' => [
                'id'           => $employee->id,
                'name'         => $employee->name,
                'position'     => $employee->position,
                'rate_per_hour'=> $employee->rate_per_hour,
                'labor_type'   => $laborType ? [
                    'id'         => $laborType->id,
                    'name'       => $laborType->name,
                    'daily_rate' => $laborType->daily_rate,
                    'hourly_rate'=> $laborType->getHourlyRate(),
                ] : null,
            ]
        ]);
    }

    /**
     * Get all employees with their labor type and rate information
     */
    public function getEmployeesWithDetails()
    {
        $employees = Employee::with(['laborType', 'shift'])->get()->map(function ($emp) {
            return [
                'id'           => $emp->id,
                'name'         => $emp->name,
                'position'     => $emp->position,
                'rate_per_hour'=> $emp->rate_per_hour,
                'fingerprint_id'=> $emp->fingerprint_id,
                'shift'        => $this->shiftPayload($emp),
                'labor_type'   => $emp->laborType ? [
                    'id'         => $emp->laborType->id,
                    'name'       => $emp->laborType->name,
                    'daily_rate' => $emp->laborType->daily_rate,
                    'hourly_rate'=> $emp->laborType->getHourlyRate(),
                ] : null,
            ];
        });

        return response()->json($employees);
    }

    /**
     * Record attendance (time_in / time_out)
     */
    public function attendance(Request $request)
    {
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'type'        => 'required|in:time_in,time_out',
            'kiosk_id'    => 'nullable',
            'kiosk_code'  => 'nullable|string',
            'site_id'     => 'nullable|exists:sites,id',
            'lat'         => 'nullable|numeric|between:-90,90',
            'lng'         => 'nullable|numeric|between:-180,180',
        ]);

        $kiosk = Kiosk::resolve($request->kiosk_id, $request->kiosk_code);
        $site  = $this->activeSite($request, $kiosk);

        // Anti-fraud GPS gate (same rule as /clock) when the kiosk identifies itself.
        if ($gate = $this->locationGate($kiosk, $request)) {
            return response()->json($gate);
        }

        $employee = Employee::with('shift')->findOrFail($request->employee_id);
        $now      = Carbon::now()->setTimezone('Asia/Manila');

        return response()->json($this->recordClock($employee, $request->type, $site, $kiosk, $now));
    }

    /**
     * Record a TIME IN or TIME OUT for one worker, by the rules of their shift.
     *
     * The one place the kiosk writes attendance from — /attendance and /clock
     * both land here, so the two can never disagree:
     *
     *   • TIME IN is open only around the worker's own shift: a day-shift worker
     *     cannot clock in at night. TIME OUT is always accepted, so the rule can
     *     never keep somebody "on site".
     *   • TIME IN after a TIME OUT opens a new stretch. It used to reuse the
     *     session's row and overwrite the time-in already recorded, which is how
     *     a worker coming back from a mistaken time-out erased their own morning.
     *   • TIME IN while a stretch from an earlier session is still open closes
     *     that one at its session's end, marked AUTO for the office to review.
     *     A worker who forgot to clock out at lunch used to be refused all
     *     afternoon ("Already timed in for the PM session").
     */
    private function recordClock(Employee $employee, string $type, ?Site $site, ?Kiosk $kiosk, Carbon $now): array
    {
        $shift = Shift::forEmployee($employee);
        $sched = $shift && $shift->hasSchedule() ? $shift->schedule() : null;
        $who   = $this->kioskEmployeePayload($employee);

        // A pending registration is a name waiting for a finger, not a member
        // of the workforce — and the kiosk's own sign-up creates one holding a
        // fingerprint, so "we recognise this finger" was never the same
        // question as "this person is registered". Until registration is
        // finished they are outside attendance and payroll, which has to mean
        // no day can be opened for them here either.
        if ($employee->isPending()) {
            return [
                'success'  => false,
                'code'     => 'not_registered',
                'employee' => $who,
                'message'  => $employee->name . ' is not registered yet. Enrol their '
                            . 'fingerprint at the kiosk, then they can time in.',
            ];
        }

        Attendance::closeStale($employee->id, $now);
        $open = Attendance::openRow($employee->id, $now);

        // ── TIME OUT ─────────────────────────────────────────────────────────
        if ($type === 'time_out') {
            if (! $open) {
                return [
                    'success'  => false,
                    'code'     => 'no_open',
                    'employee' => $who,
                    'message'  => 'No open time in to close. If you forgot to time in, ask the office.',
                ];
            }

            // A finger held a moment too long reads twice, and the second read
            // closed the day the first one opened. That left a whole shift
            // recorded as a few seconds — worth no hours, but counted as a
            // clock-in of its own on every screen that lists them, which is
            // how one worker came to have three sign-ins for one night.
            $openedAt = WorkSchedule::moment($open->time_in, (string) $open->date);

            if ($openedAt->diffInSeconds($now, true) < self::DOUBLE_READ_SECONDS) {
                return [
                    'success'  => false,
                    'code'     => 'just_timed_in',
                    'employee' => $who,
                    'since'    => WorkSchedule::label($openedAt),
                    'message'  => 'Timed in a moment ago, at ' . WorkSchedule::label($openedAt)
                                . '. Press TIME OUT again when you are leaving.',
                ];
            }

            $open->time_out = $now;
            $open->save();

            $payload = [
                'success'       => true,
                'type'          => 'time_out',
                'session'       => $open->session,
                'session_label' => $shift?->sessionLabel($open->session) ?? "{$open->session} SESSION",
                'employee'      => $who,
                'message'       => 'Time-out recorded.',
                'attendance'    => $this->attendancePayload($open),
            ];

            if ($sched) {
                // Whole minutes, as payroll counts them.
                $in    = WorkSchedule::moment($open->time_in, (string) $open->date)->startOfMinute();
                $day   = WorkSchedule::shiftDayFor($sched, $in);

                // What the morning already took, so a worker back from lunch
                // is not offered a second day's worth of regular hours.
                $used  = Attendance::regularMinutesUsed($employee->id, $day, $sched, $open->id);
                $split = WorkSchedule::split($sched, $in, $now->copy()->startOfMinute(), $day, $used);

                if ($split['ot'] > 0) {
                    // Where the overtime began, which is no longer always the
                    // end of the shift: a day that buys eight of its eleven
                    // hours turns over in the middle of the afternoon.
                    $otFrom = WorkSchedule::overtimeStart($split)
                        ?? WorkSchedule::sessionEnd($sched, 'PM', $day);

                    $payload['ot_hours'] = round($split['ot'], 2);
                    $payload['ot_from']  = WorkSchedule::label($otFrom);
                    $payload['ot_to']    = WorkSchedule::label($now);
                }
            }

            return $payload;
        }

        // ── TIME IN ──────────────────────────────────────────────────────────
        if ($sched && ! WorkSchedule::acceptsTimeInAt($sched, $now)) {
            [$opens, $closes] = $shift->timeInWindowLabels($now);

            return [
                'success'  => false,
                'code'     => 'wrong_shift',
                'employee' => $who,
                'shift'    => $shift->name,
                'opens'    => $opens,
                'closes'   => $closes,
                'message'  => "{$employee->name} is on the {$shift->name} shift. TIME IN is open {$opens}–{$closes}.",
            ];
        }

        $session    = $sched ? WorkSchedule::sessionAt($sched, $now) : ($now->hour < 12 ? 'AM' : 'PM');
        $shiftDay   = $sched ? WorkSchedule::shiftDayFor($sched, $now) : $now->toDateString();
        $autoClosed = null;

        if ($open) {
            $openIn      = WorkSchedule::moment($open->time_in, (string) $open->date);
            $openSession = in_array($open->session, ['AM', 'PM'], true) ? $open->session : null;
            $sameStretch = ! $sched
                || (WorkSchedule::shiftDayFor($sched, $openIn) === $shiftDay && $openSession === $session);

            if ($sameStretch) {
                return [
                    'success'  => false,
                    'code'     => 'already_in',
                    'employee' => $who,
                    'since'    => WorkSchedule::label($openIn),
                    'message'  => 'Already timed in since ' . WorkSchedule::label($openIn) . '. To leave, press TIME OUT.',
                ];
            }

            $openDay = WorkSchedule::shiftDayFor($sched, $openIn);
            $end     = WorkSchedule::sessionEnd($sched, $openSession ?? WorkSchedule::sessionAt($sched, $openIn), $openDay);
            $closeAt = $end->lessThan($now) ? $end : $now->copy();

            $open->autoClose($closeAt, 'Timed in for the next session without timing out');
            $autoClosed = ['session' => $open->session, 'at' => WorkSchedule::label($closeAt)];
        }

        // Back after a time-out in this same session: a second stretch, and the
        // minutes between the two are not counted.
        $previous = Attendance::where('employee_id', $employee->id)
            ->where('date', $shiftDay)
            ->where('session', $session)
            ->whereNotNull('time_out')
            ->orderByDesc('time_out')
            ->first();

        $row = Attendance::create([
            'employee_id' => $employee->id,
            'shift_id'    => $shift?->id,
            'site_id'     => $site?->id,
            'kiosk_id'    => $kiosk?->id,
            'date'        => $shiftDay,
            'session'     => $session,
            'time_in'     => $now,
        ]);

        $payload = [
            'success'       => true,
            'type'          => 'time_in',
            'session'       => $session,
            'session_label' => $shift?->sessionLabel($session) ?? "{$session} SESSION",
            'employee'      => $who,
            'message'       => 'Time-in recorded.',
            'attendance'    => $this->attendancePayload($row),
        ];

        if ($previous) {
            $payload['again']    = true;
            $payload['gap_from'] = WorkSchedule::label(WorkSchedule::moment($previous->time_out, (string) $previous->date));
            $payload['gap_to']   = WorkSchedule::label($now);
        }

        if ($autoClosed) {
            $payload['auto_closed'] = $autoClosed;
        }

        if ($sched) {
            $starts = WorkSchedule::sessionStart($sched, $session, $shiftDay);
            if ($now->lessThan($starts)) {
                $payload['paid_from'] = WorkSchedule::label($starts);
            }
        }

        return $payload;
    }

    /** One attendance row as the kiosk shows it. */
    private function attendancePayload(Attendance $row): array
    {
        return [
            'id'          => $row->id,
            'employee_id' => $row->employee_id,
            'date'        => $row->date,
            'session'     => $row->session,
            'time_in'     => $row->time_in  ? Carbon::parse($row->time_in)->format('H:i:s')  : null,
            'time_out'    => $row->time_out ? Carbon::parse($row->time_out)->format('H:i:s') : null,
            'close_type'  => $row->close_type,
        ];
    }

    /**
     * Get attendance records with employee details
     * Used by OT / Night Diff tab
     */
    public function getAttendanceRecords(Request $request)
    {
        $request->validate([
            'from' => 'required|date',
            'to'   => 'required|date|after_or_equal:from',
        ]);

        $query = Attendance::with('employee')
            ->whereBetween('date', [$request->from, $request->to])
            ->whereNotNull('time_in')
            ->whereNotNull('time_out')
            ->orderBy('date', 'asc');

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        $records = $query->get()->map(function ($att) {
            return [
                'id'            => $att->id,
                'employee_id'   => $att->employee_id,
                'employee_name' => optional($att->employee)->name ?? '---',
                'position'      => optional($att->employee)->position ?? 'Worker',
                'rate_per_hour' => optional($att->employee)->rate_per_hour ?? 0,
                'date'          => $att->date,
                // Combine date + time for accurate OT/ND calculation
                'time_in'       => $att->date . ' ' . Carbon::parse($att->time_in)->format('H:i:s'),
                'time_out'      => $att->date . ' ' . Carbon::parse($att->time_out)->format('H:i:s'),
            ];
        });

        return response()->json([
            'success' => true,
            'records' => $records
        ]);
    }

    /**
     * Save fingerprint ID to employee (called by Python scanner after enrollment)
     */
    public function saveFingerprint(Request $request)
    {
        $request->validate([
            'employee_id'   => 'required|exists:employees,id',
            'fingerprint_id'=> 'required|string',
        ]);

        $employee = Employee::findOrFail($request->employee_id);
        $fp       = (string) $request->fingerprint_id;

        // A `unique` rule here would reject the slot whenever a removed or
        // archived worker still owned it — which is most of them, since the
        // unique index spans soft-deleted rows. Reclaim the slot instead, and
        // only refuse when someone who can actually clock in still holds it.
        if ($holder = Employee::releaseFingerprint($fp, $employee->id)) {
            return response()->json([
                'success' => false,
                'message' => Employee::fingerprintConflictMessage($holder, $fp),
            ]);
        }

        $employee->fingerprint_id = $fp;

        // Enrolment is what activates a worker registered on the web. They are
        // created pending precisely because nobody has read their finger yet;
        // this call is that moment, so they join the workforce here.
        //
        // Only a pending worker is promoted. An archived leaver re-enrolling a
        // finger must not quietly return to the active roster — bringing
        // someone back is an admin decision, made on Register & Manage.
        $activated = $employee->isPending();
        if ($activated) {
            $employee->status = Employee::STATUS_ACTIVE;
        }

        $employee->save();

        return response()->json([
            'success'       => true,
            'message'       => $activated
                ? $employee->name . ' is now active — fingerprint enrolled at ID #' . $fp
                : $employee->name . ' fingerprint enrolled at ID #' . $fp,
            'employee_id'   => $employee->id,
            'fingerprint_id'=> $employee->fingerprint_id,
            'status'        => $employee->status,
            'activated'     => $activated,
        ]);
    }

    /**
     * Primary kiosk endpoint — clock in/out by fingerprint.
     *
     * The Site A kiosk only knows the fingerprint ID it scanned. This single
     * call:
     *   1. Resolves the kiosk (defaults to Site A) and stamps last_seen_at.
     *   2. Finds the employee by fingerprint — auto-creating a PENDING stub
     *      for an unknown fingerprint so it surfaces on the Register Employee
     *      page for the admin to complete.
     *   3. Records the time_in / time_out for the current AM/PM session.
     *
     * The attendance written here flows straight into Dashboard, Attendance and
     * Payroll because those modules read from the shared `attendances` table.
     */
    public function clock(Request $request)
    {
        $request->validate([
            'fingerprint_id' => 'required|string',
            'type'           => 'required|in:time_in,time_out',
            'kiosk_id'       => 'nullable',
            'kiosk_code'     => 'nullable|string',
            'site_id'        => 'nullable|exists:sites,id',
            'lat'            => 'nullable|numeric|between:-90,90',
            'lng'            => 'nullable|numeric|between:-180,180',
        ]);

        $kiosk = Kiosk::resolve($request->kiosk_id, $request->kiosk_code);
        if ($kiosk) {
            $kiosk->forceFill(['last_seen_at' => now()])->save();
        }

        // Resolve the site BEFORE the gate so the geofence measures against the
        // site the operator switched to, not the one the device sat at last.
        $site = $this->activeSite($request, $kiosk);

        // Anti-fraud: reject before creating any employee/attendance if the kiosk
        // is outside its designated location or has no GPS fix.
        if ($gate = $this->locationGate($kiosk, $request)) {
            return response()->json($gate);
        }

        $fp = (string) $request->fingerprint_id;

        // Include soft-deleted so a previously removed worker who scans again is
        // restored rather than colliding on the unique fingerprint_id.
        $employee = Employee::withTrashed()->where('fingerprint_id', $fp)->first();
        if ($employee && $employee->trashed()) {
            $employee->restore();
        }

        // An unknown finger used to open a pending "Unregistered Worker" here,
        // back when the kiosk was where a worker registered themselves and an
        // admin completed the row afterwards.
        //
        // Workers now start on the web and the kiosk only attaches a finger to
        // one, so inventing a person no longer leads anywhere — and it caused
        // real damage. Enrolling stores the template on the sensor a moment
        // before the browser reports it, and any read in that gap opened a
        // placeholder holding the slot. Attaching then quietly stripped that
        // placeholder and left it behind, nameless, beside the real worker.
        //
        // An unknown finger is now simply unknown.
        $isNew = false;
        if (!$employee) {
            return response()->json([
                'success'   => false,
                'not_found' => true,
                'message'   => 'This fingerprint is not registered yet. '
                             . 'Add the worker on the web first, then enrol '
                             . 'their finger at the kiosk.',
            ]);
        }

        if (!$employee->kiosk_id && $kiosk) {
            // Trace an existing worker back to the kiosk that detected them.
            $employee->forceFill(['kiosk_id' => $kiosk->id])->save();
        }

        // Same rules as /attendance — the shift window, a fresh stretch after a
        // time-out, AUTO-closing a forgotten session — from the one method.
        $now    = Carbon::now()->setTimezone('Asia/Manila');
        $result = $this->recordClock($employee->loadMissing('shift'), $request->type, $site, $kiosk, $now);

        return response()->json($result + [
            'is_new'  => $isNew,
            'pending' => $employee->isPending(),
        ]);
    }

    /**
     * Pi kiosk endpoint — resolve a scanned fingerprint into an employee and
     * the NEXT attendance action (time_in / time_out).
     *
     * This endpoint does NOT write the attendance row: the kiosk UI records it
     * by calling POST /api/kiosk/attendance with the returned employee + type.
     * Keeping the write in one place avoids double-logging.
     *
     * Unknown fingerprints auto-create a PENDING worker (surfaces on the
     * Register & Manage page); soft-deleted workers are restored on re-scan.
     */
    public function scanAttendance(Request $request)
    {
        $request->validate([
            'fingerprint_id' => 'required|string',
            'kiosk_id'       => 'nullable',
            'kiosk_code'     => 'nullable|string',
            'site_id'        => 'nullable|exists:sites,id',
        ]);

        $kiosk = Kiosk::resolve($request->kiosk_id, $request->kiosk_code);
        if ($kiosk) {
            $kiosk->forceFill(['last_seen_at' => now()])->save();
        }

        $site = $this->activeSite($request, $kiosk);

        $fp = (string) $request->fingerprint_id;

        $employee = Employee::withTrashed()->where('fingerprint_id', $fp)->first();
        if ($employee && $employee->trashed()) {
            $employee->restore();
        }

        // An unknown finger used to open a pending "Unregistered Worker" here,
        // back when the kiosk was where a worker registered themselves and an
        // admin completed the row afterwards.
        //
        // Workers now start on the web and the kiosk only attaches a finger to
        // one, so inventing a person no longer leads anywhere — and it caused
        // real damage. Enrolling stores the template on the sensor a moment
        // before the browser reports it, and any read in that gap opened a
        // placeholder holding the slot. Attaching then quietly stripped that
        // placeholder and left it behind, nameless, beside the real worker.
        //
        // An unknown finger is now simply unknown.
        $isNew = false;
        if (!$employee) {
            return response()->json([
                'success'   => false,
                'not_found' => true,
                'message'   => 'This fingerprint is not registered yet. '
                             . 'Add the worker on the web first, then enrol '
                             . 'their finger at the kiosk.',
            ]);
        }

        if (!$employee->kiosk_id && $kiosk) {
            $employee->forceFill(['kiosk_id' => $kiosk->id])->save();
        }

        // Say so here rather than suggesting TIME IN and having the write
        // refuse it a moment later — the worker would see the kiosk offer
        // them a button and then turn them away for no stated reason.
        if ($employee->isPending()) {
            return response()->json([
                'success'  => false,
                'code'     => 'not_registered',
                'employee' => $this->kioskEmployeePayload($employee),
                'pending'  => true,
                'message'  => $employee->name . ' is not registered yet. Enrol their '
                            . 'fingerprint at the kiosk, then they can time in.',
            ]);
        }

        // Suggest the next action — WITHOUT writing anything; /attendance does
        // the write. An open stretch suggests TIME OUT, none suggests TIME IN.
        //
        // This used to answer "already completed the AM session" once a session
        // had an in and an out, and a worker back from a mistaken time-out was
        // turned away. The kiosk's buttons now say what the worker means; the
        // suggestion is only what an older kiosk without buttons acts on.
        $now = Carbon::now()->setTimezone('Asia/Manila');
        Attendance::closeStale($employee->id, $now);

        $open    = Attendance::openRow($employee->id, $now);
        $shift   = Shift::forEmployee($employee);
        $sched   = $shift && $shift->hasSchedule() ? $shift->schedule() : null;
        $session = $open?->session
            ?? ($sched ? WorkSchedule::sessionAt($sched, $now) : ($now->hour < 12 ? 'AM' : 'PM'));

        return response()->json([
            'success'  => true,
            'type'     => $open ? 'time_out' : 'time_in',
            'session'  => $session,
            'session_label' => $shift?->sessionLabel($session) ?? "{$session} SESSION",
            'open'     => $open ? [
                'session' => $open->session,
                'since'   => WorkSchedule::label(WorkSchedule::moment($open->time_in, (string) $open->date)),
            ] : null,
            'message'  => $isNew ? 'New fingerprint detected — pending registration.' : 'Fingerprint recognized.',
            'is_new'   => $isNew,
            'pending'  => $employee->isPending(),
            'employee' => $this->kioskEmployeePayload($employee),
        ]);
    }

    /**
     * Realtime "who is on site" board for the kiosk.
     *
     * Returns today's attendance grouped per employee with separate AM/PM
     * in/out, total hours, computed overtime (> 8h/day) and a live working flag.
     */
    public function todayAttendance(Request $request)
    {
        $kiosk = Kiosk::resolve($request->kiosk_id, $request->kiosk_code);
        $site  = $this->activeSite($request, $kiosk);
        $now   = Carbon::now()->setTimezone('Asia/Manila');
        $today = $now->format('Y-m-d');

        // What a day buys before overtime starts: the standard day less its
        // unpaid meal period, the same figure payroll divides by. This board
        // read a bare 8, so an office running nine hours with an hour of lunch
        // was shown overtime an hour before anybody had earned any.
        $sysDay = SystemSetting::current();
        $paidStandard = max(1.0, (float) $sysDay->standard_hours_per_day
                                 - (int) $sysDay->unpaid_break_minutes / 60);

        // Scope to where the device is standing. Without this the Site B board
        // listed everyone who clocked in anywhere today.
        //
        // Today's date alone is not the day the board has to show. A night
        // shift clocks in the evening before and is still on site at one in the
        // morning, on a row dated yesterday — under a plain date filter the
        // foreman's board went blank on exactly the crew still working. Rows
        // left open from yesterday are pulled in as well, within the same
        // window a shift can still be running.
        // A day nobody closed, closed now at the end of its session — so the
        // board stops showing somebody "working" hours after they went home.
        Attendance::closeStale(null, $now);

        // From this date the board counts hours the way payroll does: inside
        // the shift's sessions, with overtime after it ends.
        $rulesFrom = $sysDay->schedule_rules_from
            ? Carbon::parse($sysDay->schedule_rules_from)->toDateString()
            : null;

        $yesterday = $now->copy()->subDay()->toDateString();

        $rows = Attendance::with(['employee.shift', 'shift'])
            ->ofRegistered()
            ->whereNotNull('time_in')
            ->where(function ($q) use ($today, $yesterday, $now) {
                $q->whereIn('date', [$today, $yesterday])
                  ->orWhere(fn ($o) => $o->whereNull('time_out')
                                         ->where('time_in', '>=', $now->copy()->subHours(18)));
            })
            ->when($site, fn ($q) => $q->where('site_id', $site->id))
            ->orderBy('time_in')
            ->get()
            ->groupBy('employee_id');

        $records = [];
        foreach ($rows as $empId => $recs) {
            $emp = $recs->first()->employee;
            if (!$emp) continue;

            $shift = Shift::forEmployee($emp);
            $sched = $shift && $shift->hasSchedule() ? $shift->schedule() : null;

            // Which rows are "today" for this worker: their current shift day —
            // a night crew's 3 AM still belongs to last evening's shift — plus
            // anything still open. Without a schedule, today's date as before.
            $shiftDay = $sched ? WorkSchedule::shiftDayFor($sched, $now) : $today;
            $recs = $recs->filter(function ($r) use ($sched, $shiftDay, $today, $now) {
                if (! $r->time_out) {
                    return WorkSchedule::moment($r->time_in, (string) $r->date)
                        ->greaterThanOrEqualTo($now->copy()->subHours(18));
                }
                return (string) $r->date === ($sched ? $shiftDay : $today);
            })->values();

            if ($recs->isEmpty()) continue;

            $scheduled = $sched && $rulesFrom && $shiftDay >= $rulesFrom;

            $amRows = $recs->where('session', 'AM')->values();
            $pmRows = $recs->where('session', 'PM')->values();
            $am     = $amRows->first();
            $pm     = $pmRows->first();
            $amLast = $amRows->last();
            $pmLast = $pmRows->last();

            if ($scheduled) {
                $regular = $ot = 0.0;
                $working = false;
                $lastIn  = null;

                // The day's regular hours, spent once across its records
                // rather than offered afresh to each. Keyed by workday: the
                // rows here can span two of them.
                $usedByDay = [];

                foreach ($recs as $r) {
                    // Whole minutes, as payroll counts them.
                    $in = WorkSchedule::moment($r->time_in, (string) $r->date)->startOfMinute();
                    if ($r->time_out) {
                        [, $out] = WorkSchedule::stretch($r->time_in, $r->time_out, (string) $r->date);
                    } else {
                        $working = true;
                        $lastIn  = $r->time_in;
                        $out     = $now->copy()->startOfMinute();
                    }
                    if ($out->lessThan($in)) {
                        $out = $in->copy();
                    }

                    $onDay    = (string) $r->date;
                    $split    = WorkSchedule::split($sched, $in, $out, $onDay, $usedByDay[$onDay] ?? 0);

                    $usedByDay[$onDay] = ($usedByDay[$onDay] ?? 0)
                                       + (int) round($split['regular'] * 60);

                    $regular += $split['regular'];
                    $ot      += $split['ot'];
                }

                $records[] = $this->boardRow($emp, $empId, $shift, $am, $pm, $amLast, $pmLast, $amRows, $pmRows, $recs, [
                    'total_hours'    => round($regular, 2),
                    'overtime_hours' => round($ot, 2),
                    'ot_running'     => $working && $ot > 0,
                    'working'        => $working,
                    'since'          => $working ? $this->fmt12($lastIn) : null,
                    'scheduled'      => true,
                ]);
                continue;
            }

            // A session still running counts up to now. It used to count as
            // nothing, so a worker who clocked in at 7am and was still on site
            // at 8pm showed zero hours and no overtime — the board could only
            // report overtime after they had already gone home, which is too
            // late for the foreman standing in front of it.
            $totalMin = 0;
            $working  = false;
            $lastIn   = null;

            foreach ($recs as $r) {
                if (! $r->time_in) {
                    continue;
                }

                // Whole minutes, as payroll counts them.
                $from = Carbon::parse($r->time_in)->startOfMinute();

                if ($r->time_out) {
                    $totalMin += abs($from->diffInMinutes(Carbon::parse($r->time_out)->startOfMinute()));
                } else {
                    $working = true;
                    $lastIn  = $r->time_in;
                    $totalMin += max(0, $from->diffInMinutes($now->copy()->startOfMinute(), false));
                }
            }

            $totalHours = round($totalMin / 60, 2);
            $overtime   = max(0, round($totalHours - $paidStandard, 2));

            // Overtime being earned right now, as opposed to a finished day
            // that happened to run long. The board marks the two differently:
            // one is a number to record, the other is a decision to make.
            $otRunning = $working && $overtime > 0;

            $records[] = $this->boardRow($emp, $empId, $shift, $am, $pm, $amLast, $pmLast, $amRows, $pmRows, $recs, [
                'total_hours'    => $totalHours,
                'overtime_hours' => $overtime,
                'ot_running'     => $otRunning,
                'working'        => $working,
                'since'          => $working ? $this->fmt12($lastIn) : null,
            ]);
        }

        // Currently-working first, then by name.
        usort($records, function ($a, $b) {
            if ($a['working'] !== $b['working']) return $b['working'] <=> $a['working'];
            return strcasecmp($a['name'], $b['name']);
        });

        return response()->json([
            'success'  => true,
            'date'     => $today,
            'kiosk'    => $kiosk?->name ?? 'Site A Kiosk',
            'working'  => collect($records)->where('working', true)->count(),
            'total'    => count($records),
            'overtime' => collect($records)->where('overtime_hours', '>', 0)->count(),
            // How many are ON overtime this minute — the number the foreman
            // can still act on.
            'ot_now'   => collect($records)->where('ot_running', true)->count(),
            'records'  => $records,
        ]);
    }

    /**
     * Fingerprint slots the sensor should be holding (kiosk ↔ web two-way sync).
     *
     * The Pi polls this every ~60s and deletes any slot on the R307 that is not
     * in `fingerprint_ids` — so archiving or removing a worker on the web wipes
     * their finger from the sensor without anyone touching the kiosk.
     *
     * Who stays enrolled: every non-archived, non-deleted employee that has a
     * fingerprint_id. Pending workers count — the kiosk enrolled them, their
     * details just are not filled in yet, and dropping them would erase a finger
     * the admin has not reviewed. Archived and soft-deleted workers fall out.
     *
     * The list is deliberately NOT scoped to the calling kiosk: a superset is
     * harmless (a slot the sensor does not have is a no-op), while a too-narrow
     * list would make a kiosk delete fingers that are still valid.
     */
    public function activeFingerprints()
    {
        $employees = Employee::whereNotNull('fingerprint_id')
            ->where('status', '!=', Employee::STATUS_ARCHIVED)
            ->orderBy('name')
            ->get();

        // Sensor slot numbers. fingerprint_id is a string column, so drop any
        // non-numeric value rather than letting (int) cast it to slot 0.
        $fingerprintIds = $employees
            ->pluck('fingerprint_id')
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        return response()->json([
            'success'         => true,
            'fingerprint_ids' => $fingerprintIds,                              // e.g. [1, 2, 5, 8]
            'employees'       => $employees->map(fn ($e) => $this->kioskEmployeePayload($e))->values(),
            'count'           => $employees->count(),
        ]);
    }

    /**
     * One worker's line on the board.
     *
     * AM/PM in is the first stretch's time in and AM/PM out the last stretch's
     * time out. A second stretch in the same session shows as a count, and an
     * AUTO close is flagged, so the foreman can tell a time nobody clocked from
     * one somebody did. Every stretch is listed for the detail view.
     */
    private function boardRow(Employee $emp, $empId, ?Shift $shift, $am, $pm, $amLast, $pmLast, $amRows, $pmRows, $recs, array $totals): array
    {
        return [
            'employee_id'  => $empId,
            'name'         => $emp->name,
            'position'     => $emp->position ?: 'Worker',
            'pending'      => $emp->isPending(),
            'shift'        => $this->shiftPayload($emp),
            'night'        => (bool) $shift?->crosses_midnight,
            'am_in'        => $this->fmt12($am?->time_in),
            'am_out'       => $this->fmt12($amLast?->time_out),
            'pm_in'        => $this->fmt12($pm?->time_in),
            'pm_out'       => $this->fmt12($pmLast?->time_out),
            'am_count'     => $amRows->count(),
            'pm_count'     => $pmRows->count(),
            'am_out_auto'  => $amLast?->close_type === 'auto',
            'pm_out_auto'  => $pmLast?->close_type === 'auto',
            'needs_review' => $recs->contains(fn ($r) => (bool) $r->needs_review),
            'entries'      => $recs->map(fn ($r) => [
                'session' => $r->session,
                'in'      => $this->fmt12($r->time_in),
                'out'     => $this->fmt12($r->time_out),
                'auto'    => $r->close_type === 'auto',
            ])->values()->all(),
            'status'       => $totals['working'] ? 'working' : 'done',
        ] + $totals + ['scheduled' => false];
    }

    /** Format a stored timestamp as a 12-hour clock string (or null). */
    private function fmt12($value): ?string
    {
        return $value ? Carbon::parse($value)->format('g:i A') : null;
    }

    /**
     * Compact employee shape returned to the kiosk display.
     */
    /**
     * A worker's shift, as the kiosk sees it.
     *
     * The kiosk is a separate application: it can only know about the crews if
     * the API tells it, so every payload that names a worker names their shift
     * too. Null where nobody has assigned one — that is a real state the board
     * should be able to show, not something to paper over with a default.
     */
    private function shiftPayload(?Employee $employee): ?array
    {
        $shift = $employee?->shift;

        return $shift ? [
            'id'               => $shift->id,
            'name'             => $shift->name,
            'starts_at'        => (string) $shift->starts_at,
            'crosses_midnight' => (bool) $shift->crosses_midnight,
        ] : null;
    }

    private function kioskEmployeePayload(Employee $employee): array
    {
        return [
            'id'             => $employee->id,
            'name'           => $employee->name,
            'position'       => $employee->position,
            'rate_per_hour'  => $employee->rate_per_hour,
            'fingerprint_id' => $employee->fingerprint_id,
            'status'         => $employee->status,
            'shift'          => $this->shiftPayload($employee),
        ];
    }
}