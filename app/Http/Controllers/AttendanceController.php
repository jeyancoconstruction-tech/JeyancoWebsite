<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\Shift;
use App\Models\Site;
use App\Notifications\AttendanceAlert;
use Carbon\Carbon;

class AttendanceController extends Controller
{
    /**
     * Web: Display attendance page (admin panel)
     */
    public function index(Request $request)
    {
        $today = Carbon::today();

        // ── Global filters ──────────────────────────────────────────────────
        // Site and Shift are read once and applied to everything the page
        // shows: both tables and all three cards. Anything less and the cards
        // would be counting a different set of records from the one under
        // them, which is worse than having no filter at all.
        $sites  = Site::orderBy('name')->get();
        $shifts = Shift::orderBy('id')->get();

        // Checked against the lists rather than trusted. A stale bookmark
        // pointing at a deleted site should show the unfiltered page, not an
        // empty one with no way to tell why.
        $siteId  = $request->query('site');
        $shiftId = $request->query('shift');
        $siteId  = $sites->contains('id', (int) $siteId)   ? (int) $siteId  : null;
        $shiftId = $shifts->contains('id', (int) $shiftId) ? (int) $shiftId : null;

        $filtered = function ($query) use ($siteId, $shiftId) {
            // The attendance carries its own site and shift: one kiosk is
            // moved between sites, and a worker's shift can change, so
            // reading either off the employee would answer for today rather
            // than for the day the record is about.
            if ($siteId)  { $query->where('site_id', $siteId); }
            if ($shiftId) { $query->where('shift_id', $shiftId); }
            return $query;
        };

        // CURRENT DAY VIEW — resets daily: only today's present employees
        // (a record exists only once an employee actually times in).
        // Eager-load the site each clock was taken at. One kiosk is carried
        // between sites, so "which site" is a property of the attendance, not
        // of the worker — reading it off the employee would show wherever they
        // were first registered.
        $todayAttendances = $filtered(
                Attendance::with(['employee', 'site'])
                    ->whereDate('date', $today)
                    ->whereNotNull('time_in')
                    ->orderByDesc('time_in')
            )->get();

        // HISTORY — all previous days (kept accessible, but out of the day view).
        $historyAttendances = $filtered(
                Attendance::with(['employee', 'site'])
                    ->whereDate('date', '<', $today)
                    ->orderBy('date', 'desc')
                    ->orderBy('session', 'asc')
            )->paginate(15)
            // Without this, page 2 drops the filters and quietly shows
            // everything again.
            ->withQueryString();

        // Stats
        $presentToday = $todayAttendances->count();
        $clockedIn    = $todayAttendances->whereNull('time_out')->count(); // still on-site (no time-out yet)
        $weekStart    = Carbon::today()->startOfWeek(); // Monday — resets each week
        $invalidCount = $filtered(
                Attendance::whereBetween('date', [$weekStart, $today->copy()->subDay()])
                    ->whereNotNull('time_in')
                    ->whereNull('time_out')
            )->count(); // missed sign-outs within the current week only

        // Global holiday dates (overlay) — shown as a secondary tag.
        $holidayDates = Holiday::dateList();

        // ── Notifications ──────────────────────────────────────────────────
        $user = auth()->user();

        // Deliberately unfiltered: an alert is about the whole workforce, and
        // firing it off a filtered count would mean "no invalid attendance"
        // simply because Site B is selected.
        $invalidAll = Attendance::whereBetween('date', [$weekStart, $today->copy()->subDay()])
            ->whereNotNull('time_in')
            ->whereNull('time_out')
            ->count();

        if ($invalidAll > 0) {
            AttendanceAlert::fireOnce($user, 'invalid_clock_in',
                'Invalid Attendance Detected',
                "{$invalidAll} employee" . ($invalidAll > 1 ? 's' : '') . " clocked in but never clocked out."
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
            'presentToday', 'clockedIn', 'invalidCount', 'holidayDates',
            'sites', 'shifts', 'siteId', 'shiftId'
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
            // An open day, not a filled slot on today's clock: a worker who has
            // not clocked out of the morning cannot clock into the afternoon.
            if ($open = Attendance::openRow($employeeId, $now)) {
                $attendance = $open;
            }

            if ($attendance && $attendance->time_in && !$attendance->time_out) {
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
            // The day this worker has open — which for any shift longer than
            // the half of the day it began in is not the row the clock points at.
            $attendance = Attendance::openRow($employeeId, $now);

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

        // Fire overtime notification when a day runs past the standard hours
        // the office actually set, not a hardcoded eight.
        if ($type === 'time_out' && $attendance->time_in && $attendance->time_out) {
            $hours = abs(Carbon::parse($attendance->time_in)->diffInMinutes(Carbon::parse($attendance->time_out))) / 60;
            if ($hours > (float) \App\Models\SystemSetting::current()->standard_hours_per_day) {
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