<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Employee;
use App\Models\Attendance;
use App\Models\LaborType;
use App\Models\Project;
use App\Models\Kiosk;
use Carbon\Carbon;

class KioskController extends Controller
{
    /**
     * Get all labor types for position dropdown
     */
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
        ]);

        $kiosk = Kiosk::resolve($request->kiosk_id, $request->kiosk_code);

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

        // Resolve project from id or name (the kiosk sends a name like "Site A").
        $projectId = $request->project_id;
        if (!$projectId && $request->filled('project')) {
            $projectId = Project::firstOrCreate(['name' => trim($request->project)])->id;
        }

        $employee = Employee::create([
            'name'          => $request->name,
            'labor_type_id' => $laborType?->id,
            'position'      => $position,
            'rate_per_hour' => $hourlyRate,
            'project_id'    => $projectId,
            'kiosk_id'      => $kiosk?->id,
            'site_id'       => $kiosk?->site_id,
            // Kiosk registrations wait for admin acceptance before joining the
            // active workforce — the admin Accepts or Rejects them on the
            // Register & Manage page.
            'status'        => Employee::STATUS_PENDING,
        ]);

        return response()->json([
            'success'  => true,
            'message'  => $employee->name . ' submitted — awaiting admin approval.',
            'employee' => [
                'id'           => $employee->id,
                'name'         => $employee->name,
                'position'     => $employee->position,
                'rate_per_hour'=> $employee->rate_per_hour,
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
        ]);

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
            'fingerprint_id'=> 'required|string|unique:employees,fingerprint_id',
        ]);

        $employee = Employee::findOrFail($request->employee_id);
        $employee->fingerprint_id = $request->fingerprint_id;
        $employee->save();

        return response()->json([
            'success'       => true,
            'message'       => $employee->name . ' fingerprint enrolled at ID #' . $request->fingerprint_id,
            'employee_id'   => $employee->id,
            'fingerprint_id'=> $employee->fingerprint_id,
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
        ]);

        $kiosk = Kiosk::resolve($request->kiosk_id, $request->kiosk_code);
        if ($kiosk) {
            $kiosk->forceFill(['last_seen_at' => now()])->save();
        }

        $fp = (string) $request->fingerprint_id;

        // Include soft-deleted so a previously removed worker who scans again is
        // restored rather than colliding on the unique fingerprint_id.
        $employee = Employee::withTrashed()->where('fingerprint_id', $fp)->first();
        if ($employee && $employee->trashed()) {
            $employee->restore();
        }

        $isNew = false;
        if (!$employee) {
            $employee = Employee::create([
                'name'           => 'Unregistered Worker',
                'position'       => null,
                'rate_per_hour'  => 0,
                'labor_type_id'  => null,
                'site_id'        => $kiosk?->site_id,
                'kiosk_id'       => $kiosk?->id,
                'status'         => Employee::STATUS_PENDING,
                'fingerprint_id' => $fp,
            ]);
            $isNew = true;
        } elseif (!$employee->kiosk_id && $kiosk) {
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
        ]);

        $kiosk = Kiosk::resolve($request->kiosk_id, $request->kiosk_code);
        if ($kiosk) {
            $kiosk->forceFill(['last_seen_at' => now()])->save();
        }

        $fp = (string) $request->fingerprint_id;

        $employee = Employee::withTrashed()->where('fingerprint_id', $fp)->first();
        if ($employee && $employee->trashed()) {
            $employee->restore();
        }

        $isNew = false;
        if (!$employee) {
            $employee = Employee::create([
                'name'           => 'Unregistered Worker',
                'position'       => null,
                'rate_per_hour'  => 0,
                'labor_type_id'  => null,
                'site_id'        => $kiosk?->site_id,
                'kiosk_id'       => $kiosk?->id,
                'status'         => Employee::STATUS_PENDING,
                'fingerprint_id' => $fp,
            ]);
            $isNew = true;
        } elseif (!$employee->kiosk_id && $kiosk) {
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
        $today = Carbon::now()->setTimezone('Asia/Manila')->format('Y-m-d');

        $rows = Attendance::with('employee')
            ->where('date', $today)
            ->whereNotNull('time_in')
            ->get()
            ->groupBy('employee_id');

        $records = [];
        foreach ($rows as $empId => $recs) {
            $emp = $recs->first()->employee;
            if (!$emp) continue;

            $am = $recs->firstWhere('session', 'AM');
            $pm = $recs->firstWhere('session', 'PM');

            $totalMin = 0;
            $working  = false;
            $lastIn   = null;
            foreach ($recs as $r) {
                if ($r->time_in && $r->time_out) {
                    $totalMin += abs(Carbon::parse($r->time_in)->diffInMinutes(Carbon::parse($r->time_out)));
                } elseif ($r->time_in && !$r->time_out) {
                    $working = true;
                    $lastIn  = $r->time_in;
                }
            }

            $totalHours = round($totalMin / 60, 2);
            $overtime   = max(0, round($totalHours - 8, 2));

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
            'records'  => $records,
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