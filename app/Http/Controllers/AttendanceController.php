<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Holiday;
use App\Notifications\AttendanceAlert;
use Carbon\Carbon;

class AttendanceController extends Controller
{
    /**
     * Web: Display attendance page (admin panel)
     */
    public function index()
    {
        $today = Carbon::today();

        // CURRENT DAY VIEW — resets daily: only today's present employees
        // (a record exists only once an employee actually times in).
        $todayAttendances = Attendance::with('employee')
            ->whereDate('date', $today)
            ->whereNotNull('time_in')
            ->orderByDesc('time_in')
            ->get();

        // HISTORY — all previous days (kept accessible, but out of the day view).
        $historyAttendances = Attendance::with('employee')
            ->whereDate('date', '<', $today)
            ->orderBy('date', 'desc')
            ->orderBy('session', 'asc')
            ->paginate(15);

        // Stats
        $presentToday = $todayAttendances->count();
        $clockedIn    = $todayAttendances->whereNull('time_out')->count(); // still on-site (no time-out yet)
        $weekStart    = Carbon::today()->startOfWeek(); // Monday — resets each week
        $invalidCount = Attendance::whereBetween('date', [$weekStart, $today->copy()->subDay()])
            ->whereNotNull('time_in')
            ->whereNull('time_out')
            ->count(); // missed sign-outs within the current week only

        // Global holiday dates (overlay) — shown as a secondary tag.
        $holidayDates = Holiday::dateList();

        // ── Notifications ──────────────────────────────────────────────────
        $user = auth()->user();

        if ($invalidCount > 0) {
            AttendanceAlert::fireOnce($user, 'invalid_clock_in',
                'Invalid Attendance Detected',
                "{$invalidCount} employee" . ($invalidCount > 1 ? 's' : '') . " clocked in but never clocked out."
            );
        }

        $totalEmployees = Employee::active()->count();
        if ($totalEmployees > 0 && $presentToday < ($totalEmployees / 2)) {
            AttendanceAlert::fireOnce($user, 'low_attendance',
                'Low Attendance Today',
                "Only {$presentToday} of {$totalEmployees} employees are present today."
            );
        }

        return view('attendance', compact(
            'todayAttendances', 'historyAttendances',
            'presentToday', 'clockedIn', 'invalidCount', 'holidayDates'
        ));
    }

    /** Delete selected history records (past days only). */
    public function bulkDeleteHistory(Request $request)
    {
        $ids     = array_filter((array) $request->input('ids', []), 'is_numeric');
        if (empty($ids)) {
            return response()->json(['success' => false, 'message' => 'No records selected.']);
        }
        $deleted = Attendance::whereIn('id', $ids)
            ->whereDate('date', '<', Carbon::today())
            ->delete();
        return response()->json(['success' => true, 'deleted' => $deleted]);
    }

    /** Delete every history record (past days only). */
    public function deleteAllHistory()
    {
        $deleted = Attendance::whereDate('date', '<', Carbon::today())->delete();
        return response()->json(['success' => true, 'deleted' => $deleted]);
    }

    /**
     * Kiosk: Handle attendance POST (time_in / time_out)
     */
    public function record(Request $request)
    {
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'type' => 'required|in:time_in,time_out',
        ]);

        $employeeId = $request->employee_id;
        $type = $request->type;
        $now = Carbon::now();
        $today = $now->format('Y-m-d');

        /** Determine Session based on time. Default: Before 12 PM = AM, After = PM */
        $currentSession = $now->hour < 12 ? 'AM' : 'PM';

        // Look up this session's record WITHOUT creating one. A row is only
        // saved once a real time-in happens, so no empty/absent placeholder
        // records remain for sessions with no activity.
        $attendance = Attendance::where('employee_id', $employeeId)
            ->where('date', $today)
            ->where('session', $currentSession)
            ->first();

        if ($type === 'time_in') {
            if ($attendance && $attendance->time_in) {
                return response()->json([
                    'success' => false,
                    'message' => "Already time-in for $currentSession session."
                ]);
            }
            if (!$attendance) {
                $attendance = new Attendance([
                    'employee_id' => $employeeId,
                    'date'        => $today,
                    'session'     => $currentSession,
                ]);
            }
            $attendance->time_in = $now; // full datetime — matches kiosk storage
        }
        elseif ($type === 'time_out') {
            if (!$attendance || !$attendance->time_in) {
                return response()->json([
                    'success' => false,
                    'message' => "Cannot time-out without a $currentSession time-in."
                ]);
            }
            if ($attendance->time_out) {
                return response()->json([
                    'success' => false,
                    'message' => "Already time-out for $currentSession session."
                ]);
            }
            $attendance->time_out = $now; // full datetime — matches kiosk storage
        }

        $attendance->save();

        // Fire overtime notification when time_out is saved and shift > 8 hours.
        if ($type === 'time_out' && $attendance->time_in && $attendance->time_out) {
            $hours = abs(Carbon::parse($attendance->time_in)->diffInMinutes(Carbon::parse($attendance->time_out))) / 60;
            if ($hours > 8) {
                $employee = $attendance->employee ?? Employee::find($employeeId);
                $name = $employee ? $employee->name : "Employee #{$employeeId}";
                AttendanceAlert::fireOnce(
                    \App\Models\User::where('is_admin', true)->first(),
                    'overtime',
                    'Overtime Recorded',
                    "{$name} worked " . round($hours, 1) . " hours ({$currentSession} session)."
                );
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Successfully recorded $type for $currentSession.",
            'attendance' => $attendance
        ]);
    }
}