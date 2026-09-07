<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\OvertimeRequest;
use App\Models\Site;
use Illuminate\Http\Request;

/**
 * Attendance seen per construction site.
 *
 * READ ONLY, and deliberately so. Every row shown here was written by the
 * kiosk; this module has no create, update or delete, and adds no second way
 * to record a clock. It is the existing attendances table asked a different
 * question: not "what did this worker do" but "what happened on this site".
 */
class SiteAttendanceController extends Controller
{
    public function index(Request $request)
    {
        $date = $request->get('date', now()->toDateString());
        $to   = $request->get('to', $date);

        $records = Attendance::with(['employee', 'site', 'shift'])
            ->whereBetween('date', [$date, $to])
            ->when($request->filled('site_id'), fn ($q) => $q->where('site_id', $request->site_id))
            ->when($request->filled('q'), fn ($q) => $q->whereHas('employee',
                fn ($e) => $e->where('name', 'like', '%' . $request->q . '%')))
            ->orderByDesc('date')
            ->orderBy('time_in')
            ->paginate(25)
            ->withQueryString();

        // Per-site tally for the range. Assigned counts the crew the site
        // holds; present counts distinct people who actually clocked there,
        // which is not the same number the moment someone is moved.
        $sites = Site::orderBy('name')->get();
        $tally = [];

        foreach ($sites as $site) {
            $rows = Attendance::whereBetween('date', [$date, $to])
                ->where('site_id', $site->id)
                ->get();

            $present = $rows->pluck('employee_id')->unique()->count();

            $tally[] = [
                'site'     => $site,
                'assigned' => Employee::where('site_id', $site->id)->count(),
                'present'  => $present,
                'records'  => $rows->count(),
                'open'     => $rows->whereNull('time_out')->count(),
                'overtime' => (float) OvertimeRequest::approved()
                                ->inRange($date, $to)
                                ->where('site_id', $site->id)
                                ->sum('hours'),
            ];
        }

        return view('site-attendance.index', [
            'records' => $records,
            'tally'   => $tally,
            'sites'   => $sites,
            'date'    => $date,
            'to'      => $to,
        ]);
    }
}

