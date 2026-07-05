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
            'attendanceLabels',
            'attendanceData'
        ));
    }
}