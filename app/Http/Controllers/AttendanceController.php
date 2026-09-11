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
        $now   = Carbon::now();

        // A day nobody closed is closed at the end of its session and flagged
        // for review. There is no scheduler on this deployment, so this runs
        // where attendance is read — as it already does for payroll and the
        // kiosk's board. Without it a forgotten time-out sat open for good,
        // reading "Invalid" on a row the office could not resolve from here.
        Attendance::closeStale(null, $now);

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

        // CURRENT DAY VIEW — the day each crew is working, which is not the
        // same date for both of them. A night shift that timed in at 8pm is
        // still on its own workday at 2am, on a row dated the evening before;
        // under a plain calendar filter this table went empty at midnight on
        // exactly the crew still standing on site, and their rows appeared in
        // the history as missed sign-outs while they were working.
        //
        // Eager-load the site each clock was taken at. One kiosk is carried
        // between sites, so "which site" is a property of the attendance, not
        // of the worker — reading it off the employee would show wherever they
        // were first registered. The shift is loaded too: each row's status is
        // read against the shift it was worked under.
        $todayAttendances = $filtered(
                Attendance::with(['employee', 'site', 'shift'])
                    ->fromWorkday($now)
                    ->whereNotNull('time_in')
                    ->orderByDesc('time_in')
            )->get();

        // HISTORY — workdays that have finished, which for the night crew is
        // the following morning rather than midnight.
        $historyAttendances = $filtered(
                Attendance::with(['employee', 'site', 'shift'])
                    ->beforeWorkday($now)
                    ->orderBy('date', 'desc')
                    ->orderBy('session', 'asc')
            )->paginate(15)
            // Without this, page 2 drops the filters and quietly shows
            // everything again.
            ->withQueryString();

        // Stats. Counted by worker, not by row: a day is several rows — a
        // morning, an afternoon after lunch, a stretch begun again after a
        // mistaken time-out — and counting those made one man on site read as
        // "3 present" beside a workforce of one. It also made the low-turnout
        // alert below compare a row count against a headcount.
        $presentToday = $todayAttendances->unique('employee_id')->count();
        $clockedIn    = $todayAttendances->whereNull('time_out')->unique('employee_id')->count();
        $weekStart    = Carbon::today()->startOfWeek(); // Monday — resets each week

        // Missed sign-outs within the current week. A day still being worked
        // is not one, so the running workday is excluded by the shift working
        // it rather than by yesterday's date — which would have counted the
        // whole night crew as invalid every night.
        $invalidCount = $filtered(
                Attendance::whereBetween('date', [$weekStart, $today])
                    ->beforeWorkday($now)
                    ->whereNotNull('time_in')
                    ->whereNull('time_out')
            )->count();

        // Global holiday dates (overlay) — shown as a secondary tag.
        $holidayDates = Holiday::dateList();

        // ── Notifications ──────────────────────────────────────────────────
        $user = auth()->user();

        // Deliberately unfiltered: an alert is about the whole workforce, and
        // firing it off a filtered count would mean "no invalid attendance"
        // simply because Site B is selected.
        $invalidAll = Attendance::whereBetween('date', [$weekStart, $today])
            ->beforeWorkday($now)
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
        // "Past days" as the shift that worked them reckons days. On the
        // calendar a night crew's running day is already yesterday, so
        // clearing history at two in the morning deleted the shift in
        // progress out from under the people working it.
        $deleted = Attendance::whereIn('id', $ids)
            ->beforeWorkday()
            ->delete();
        return response()->json(['success' => true, 'deleted' => $deleted]);
    }

    /** Delete every history record (finished workdays only). */
    public function deleteAllHistory()
    {
        $deleted = Attendance::beforeWorkday()->delete();
        return response()->json(['success' => true, 'deleted' => $deleted]);
    }
}
