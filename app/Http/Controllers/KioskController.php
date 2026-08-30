<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Employee;
use App\Models\Attendance;
use App\Models\LaborType;
use App\Models\Project;
use App\Models\Kiosk;
use App\Models\Site;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class KioskController extends Controller
{
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
                'message' => 'Naka-off o walang GPS ang kiosk — hindi matatanggap ang attendance. I-on ang lokasyon at subukan ulit.',
            ];
        }

        $distance = $this->haversineMeters((float) $curLat, (float) $curLng, (float) $destLat, (float) $destLng);
        $radius   = (int) config('kiosk.geofence_radius');

        if ($distance > $radius) {
            return [
                'success'    => false,
                'code'       => 'outside_location',
                'message'    => 'Nasa labas ng authorized na lugar (' . number_format($distance)
                                . 'm, limit ' . $radius . 'm) — hindi matatanggap ang attendance.',
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

        $employees = Employee::with('laborType')
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
                'fingerprint_id'   => $e->fingerprint_id,
                'enrolled'         => $enrolled,
                // What the kiosk puts on the badge. "Pending" here means the
                // finger is missing — not the admin-approval status, which the
                // worker standing at the kiosk has no way to act on.
                'state'            => $enrolled ? 'enrolled' : 'pending',
            ];
        });

        // Needs-a-finger first; alphabetical within each group.
        $sorted = $rows->sortBy([
            fn ($a, $b) => ($a['enrolled'] ? 1 : 0) <=> ($b['enrolled'] ? 1 : 0),
            fn ($a, $b) => strcasecmp($a['name'], $b['name']),
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
        $employees = Employee::with('laborType')->get()->map(function ($emp) {
            return [
                'id'           => $emp->id,
                'name'         => $emp->name,
                'position'     => $emp->position,
                'rate_per_hour'=> $emp->rate_per_hour,
                'fingerprint_id'=> $emp->fingerprint_id,
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

        $employeeId = $request->employee_id;
        $type       = $request->type;

        $now     = Carbon::now()->setTimezone('Asia/Manila');
        $today   = $now->format('Y-m-d');
        $session = $now->hour < 12 ? 'AM' : 'PM';   // morning vs afternoon session

        // One row per employee PER SESSION per day, so AM and PM are independent
        // (morning in/out + afternoon in/out). No empty placeholder rows.
        $attendance = Attendance::where('employee_id', $employeeId)
            ->where('date', $today)
            ->where('session', $session)
            ->first();

        if ($type === 'time_in') {
            if ($attendance && $attendance->time_in) {
                return response()->json([
                    'success' => false,
                    'message' => "Already timed in for the {$session} session."
                ]);
            }
            if (!$attendance) {
                $attendance = new Attendance([
                    'employee_id' => $employeeId,
                    'site_id'     => $site?->id,
                    'kiosk_id'    => $kiosk?->id,
                    'date'        => $today,
                    'session'     => $session,
                ]);
            }
            $attendance->time_in = $now;
        } else { // time_out
            if (!$attendance || !$attendance->time_in) {
                return response()->json([
                    'success' => false,
                    'message' => "Cannot time out before timing in for the {$session} session."
                ]);
            }
            if ($attendance->time_out) {
                return response()->json([
                    'success' => false,
                    'message' => "Already timed out for the {$session} session."
                ]);
            }
            $attendance->time_out = $now;
        }

        $attendance->save();

        return response()->json([
            'success'    => true,
            'session'    => $session,
            'attendance' => [
                'id'          => $attendance->id,
                'employee_id' => $attendance->employee_id,
                'date'        => $attendance->date,
                'session'     => $session,
                'time_in'     => $attendance->time_in  ? Carbon::parse($attendance->time_in)->format('H:i:s')  : null,
                'time_out'    => $attendance->time_out ? Carbon::parse($attendance->time_out)->format('H:i:s') : null,
            ]
        ]);
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
                'message'   => 'Hindi pa nakarehistro ang daliring ito. '
                             . 'Idagdag muna ang manggagawa sa web, tapos kunin '
                             . 'ang daliri sa kiosk.',
            ]);
        }

        if (!$employee->kiosk_id && $kiosk) {
            // Trace an existing worker back to the kiosk that detected them.
            $employee->forceFill(['kiosk_id' => $kiosk->id])->save();
        }

        // ── Record attendance (mirrors AttendanceController AM/PM session logic) ──
        $now     = Carbon::now()->setTimezone('Asia/Manila');
        $today   = $now->format('Y-m-d');
        $session = $now->hour < 12 ? 'AM' : 'PM';

        $attendance = Attendance::where('employee_id', $employee->id)
            ->where('date', $today)
            ->where('session', $session)
            ->first();

        if ($request->type === 'time_in') {
            if ($attendance && $attendance->time_in) {
                return response()->json([
                    'success'  => false,
                    'message'  => "Already timed in for the {$session} session.",
                    'employee' => $this->kioskEmployeePayload($employee),
                ]);
            }
            if (!$attendance) {
                $attendance = new Attendance([
                    'employee_id' => $employee->id,
                    'site_id'     => $site?->id,
                    'kiosk_id'    => $kiosk?->id,
                    'date'        => $today,
                    'session'     => $session,
                ]);
            }
            $attendance->time_in = $now;
        } else { // time_out
            if (!$attendance || !$attendance->time_in) {
                return response()->json([
                    'success'  => false,
                    'message'  => "Cannot time out before timing in ({$session} session).",
                    'employee' => $this->kioskEmployeePayload($employee),
                ]);
            }
            if ($attendance->time_out) {
                return response()->json([
                    'success'  => false,
                    'message'  => "Already timed out for the {$session} session.",
                    'employee' => $this->kioskEmployeePayload($employee),
                ]);
            }
            $attendance->time_out = $now;
        }

        $attendance->save();

        $verb = $request->type === 'time_in' ? 'Time-in' : 'Time-out';

        return response()->json([
            'success'    => true,
            'message'    => ($isNew ? 'New fingerprint detected — pending registration. ' : '')
                            . "{$verb} recorded for the {$session} session.",
            'is_new'     => $isNew,
            'pending'    => $employee->isPending(),
            'employee'   => $this->kioskEmployeePayload($employee),
            'attendance' => [
                'id'       => $attendance->id,
                'date'     => $attendance->date,
                'session'  => $attendance->session,
                'time_in'  => $attendance->time_in  ? Carbon::parse($attendance->time_in)->format('H:i:s')  : null,
                'time_out' => $attendance->time_out ? Carbon::parse($attendance->time_out)->format('H:i:s') : null,
            ],
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
                'message'   => 'Hindi pa nakarehistro ang daliring ito. '
                             . 'Idagdag muna ang manggagawa sa web, tapos kunin '
                             . 'ang daliri sa kiosk.',
            ]);
        }

        if (!$employee->kiosk_id && $kiosk) {
            $employee->forceFill(['kiosk_id' => $kiosk->id])->save();
        }

        // Decide the next action for the CURRENT session (AM/PM) — WITHOUT writing
        // anything here; the kiosk's /attendance call performs the actual write.
        $now     = Carbon::now()->setTimezone('Asia/Manila');
        $today   = $now->format('Y-m-d');
        $session = $now->hour < 12 ? 'AM' : 'PM';

        $attendance = Attendance::where('employee_id', $employee->id)
            ->where('date', $today)
            ->where('session', $session)
            ->first();

        if (!$attendance || !$attendance->time_in) {
            $type = 'time_in';
        } elseif (!$attendance->time_out) {
            $type = 'time_out';
        } else {
            return response()->json([
                'success'  => false,
                'message'  => $employee->name . " already completed the {$session} session.",
                'employee' => $this->kioskEmployeePayload($employee),
            ]);
        }

        return response()->json([
            'success'  => true,
            'type'     => $type,
            'session'  => $session,
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
        $today = Carbon::now()->setTimezone('Asia/Manila')->format('Y-m-d');

        // Scope to where the device is standing. Without this the Site B board
        // listed everyone who clocked in anywhere today.
        $rows = Attendance::with('employee')
            ->where('date', $today)
            ->whereNotNull('time_in')
            ->when($site, fn ($q) => $q->where('site_id', $site->id))
            ->get()
            ->groupBy('employee_id');

        $records = [];
        foreach ($rows as $empId => $recs) {
            $emp = $recs->first()->employee;
            if (!$emp) continue;

            $am = $recs->firstWhere('session', 'AM');
            $pm = $recs->firstWhere('session', 'PM');

            // A session still running counts up to now. It used to count as
            // nothing, so a worker who clocked in at 7am and was still on site
            // at 8pm showed zero hours and no overtime — the board could only
            // report overtime after they had already gone home, which is too
            // late for the foreman standing in front of it.
            $now      = Carbon::now()->setTimezone('Asia/Manila');
            $totalMin = 0;
            $working  = false;
            $lastIn   = null;

            foreach ($recs as $r) {
                if (! $r->time_in) {
                    continue;
                }

                if ($r->time_out) {
                    $totalMin += abs(Carbon::parse($r->time_in)->diffInMinutes(Carbon::parse($r->time_out)));
                } else {
                    $working = true;
                    $lastIn  = $r->time_in;
                    $totalMin += max(0, Carbon::parse($r->time_in)->diffInMinutes($now, false));
                }
            }

            $totalHours = round($totalMin / 60, 2);
            $overtime   = max(0, round($totalHours - 8, 2));

            // Overtime being earned right now, as opposed to a finished day
            // that happened to run long. The board marks the two differently:
            // one is a number to record, the other is a decision to make.
            $otRunning = $working && $overtime > 0;

            $records[] = [
                'employee_id'    => $empId,
                'name'           => $emp->name,
                'position'       => $emp->position ?: 'Worker',
                'pending'        => $emp->isPending(),
                'am_in'          => $this->fmt12($am?->time_in),
                'am_out'         => $this->fmt12($am?->time_out),
                'pm_in'          => $this->fmt12($pm?->time_in),
                'pm_out'         => $this->fmt12($pm?->time_out),
                'total_hours'    => $totalHours,
                'overtime_hours' => $overtime,
                'ot_running'     => $otRunning,
                'working'        => $working,
                'since'          => $working ? $this->fmt12($lastIn) : null,
                'status'         => $working ? 'working' : 'done',
            ];
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

    /** Format a stored timestamp as a 12-hour clock string (or null). */
    private function fmt12($value): ?string
    {
        return $value ? Carbon::parse($value)->format('g:i A') : null;
    }

    /**
     * Compact employee shape returned to the kiosk display.
     */
    private function kioskEmployeePayload(Employee $employee): array
    {
        return [
            'id'             => $employee->id,
            'name'           => $employee->name,
            'position'       => $employee->position,
            'rate_per_hour'  => $employee->rate_per_hour,
            'fingerprint_id' => $employee->fingerprint_id,
            'status'         => $employee->status,
        ];
    }
}