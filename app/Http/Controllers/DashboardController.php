<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Attendance;
use App\Services\PayrollService;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(PayrollService $payroll)
    {
        // Active workforce only — pending kiosk detections and archived/removed
        // workers are excluded so the dashboard reflects current employees.
        $employees = Employee::active()->get();

        $totalEmployees = $employees->count();

        $presentToday = Attendance::where('date', Carbon::today()->format('Y-m-d'))
                                   ->whereNotNull('time_in')
                                   ->count();

        // Weekly payout for the current Mon–Sun week — computed by PayrollService
        // so it matches Payroll Records exactly (single source of truth).
        $from = Carbon::now()->startOfWeek(Carbon::MONDAY)->toDateString();
        $to   = Carbon::now()->endOfWeek(Carbon::SUNDAY)->toDateString();
        $weeklyPayroll = collect($payroll->computeForRange($from, $to)['employees'])
            ->sum(fn ($e) => $e['totals']['net']);

        $pendingVale = $employees->sum('vale');

        // ── Deltas (read-only, for the stat-card trend chips) ──────────────
        $presentYesterday = Attendance::where('date', Carbon::yesterday()->format('Y-m-d'))
                                       ->whereNotNull('time_in')
                                       ->count();

        $lwFrom = Carbon::now()->subWeek()->startOfWeek(Carbon::MONDAY)->toDateString();
        $lwTo   = Carbon::now()->subWeek()->endOfWeek(Carbon::SUNDAY)->toDateString();
        $lastWeekPayroll = collect($payroll->computeForRange($lwFrom, $lwTo)['employees'])
            ->sum(fn ($e) => $e['totals']['net']);

        $newThisWeek = Employee::active()
            ->where('created_at', '>=', Carbon::now()->subDays(7))
            ->count();

        // ── Live Attendance (today) ────────────────────────────────────────
        $todayAttendance = Attendance::with('employee')
            ->where('date', Carbon::today()->format('Y-m-d'))
            ->whereNotNull('time_in')
            ->orderByDesc('time_in')
            ->take(6)
            ->get();

        // ── Recent Activities feed (derived from real records) ─────────────
        $recentActivities = collect();
        Employee::active()->latest()->take(4)->get()->each(function ($e) use ($recentActivities) {
            $recentActivities->push([
                'icon'     => 'fa-user-plus',
                'color'    => '#6366f1',
                'title'    => 'Employee registered',
                'subtitle' => $e->name . ' added',
                'time'     => $e->created_at,
            ]);
        });
        Attendance::with('employee')->whereNotNull('time_in')->latest('created_at')->take(4)->get()
            ->each(function ($a) use ($recentActivities) {
                $recentActivities->push([
                    'icon'     => 'fa-clock',
                    'color'    => '#22c55e',
                    'title'    => 'Attendance recorded',
                    'subtitle' => (optional($a->employee)->name ?: 'A worker') . ' timed in',
                    'time'     => $a->created_at,
                ]);
            });
        $recentActivities = $recentActivities
            ->filter(fn ($a) => $a['time'] !== null)
            ->sortByDesc('time')
            ->take(6)
            ->values();

        // Attendance chart
        $attendanceLabels = [];
        $attendanceData = [];
        for ($i=6; $i>=0; $i--) {
            $date = Carbon::today()->subDays($i);
            $attendanceLabels[] = $date->format('M d');
            $attendanceData[] = Attendance::where('date', $date->format('Y-m-d'))->count();
        }

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
            'recentActivities',
            'attendanceLabels',
            'attendanceData'
        ));
    }
}