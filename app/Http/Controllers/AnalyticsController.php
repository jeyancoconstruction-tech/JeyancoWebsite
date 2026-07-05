<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Site;
use App\Services\PayrollService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    public function index(PayrollService $payroll)
    {
        $today      = Carbon::today();
        $monthStart = $today->copy()->startOfMonth()->toDateString();
        $monthEnd   = $today->toDateString();
        $monthLabel = $today->format('F Y');

        // ── KPIs ─────────────────────────────────────────────────────────────
        $totalEmployees = Employee::active()->count();
        $presentToday   = Attendance::whereDate('date', $today)->whereNotNull('time_in')->count();
        $activeSites    = Site::withCount('employees')->get()->where('employees_count', '>', 0)->count();

        // Current month payroll
        $monthly   = $payroll->computeForRange($monthStart, $monthEnd);
        $empColl   = collect($monthly['employees']);

        $monthlyNet     = round($empColl->sum(fn ($e) => $e['totals']['net']), 2);
        $monthlyGross   = round($empColl->sum(fn ($e) => $e['totals']['gross']), 2);
        $monthlyOTPay   = round($empColl->sum(fn ($e) => $e['totals']['overtime']), 2);
        $monthlyHoliday = round($empColl->sum(fn ($e) => $e['totals']['holidayPay']), 2);

        // Attendance rate this month
        $totalPresent    = Attendance::whereBetween('date', [$monthStart, $monthEnd])
            ->whereNotNull('time_in')->count();
        $daysSoFar       = max(1, $today->diffInDays($today->copy()->startOfMonth()) + 1);
        $possiblePresent = max(1, $totalEmployees * $daysSoFar);
        $attendanceRate  = min(100, round(($totalPresent / $possiblePresent) * 100, 1));

        // Overtime hours this month
        $overtimeHours = round(
            Attendance::whereBetween('date', [$monthStart, $monthEnd])
                ->whereNotNull('time_in')->whereNotNull('time_out')
                ->get()
                ->sum(function ($rec) {
                    $h = abs(Carbon::parse($rec->time_in)->diffInMinutes(Carbon::parse($rec->time_out))) / 60;
                    return max(0, $h - 8);
                }),
            1
        );

        // Last month comparison for net payroll
        $lmStart    = $today->copy()->subMonthNoOverflow()->startOfMonth()->toDateString();
        $lmEnd      = $today->copy()->subMonthNoOverflow()->endOfMonth()->toDateString();
        $lmData     = $payroll->computeForRange($lmStart, $lmEnd);
        $lastMonNet = round(collect($lmData['employees'])->sum(fn ($e) => $e['totals']['net']), 2);
        $netChange  = $lastMonNet > 0 ? round((($monthlyNet - $lastMonNet) / $lastMonNet) * 100, 1) : null;

        // ── Deduction breakdown (SSS, PhilHealth, Pag-IBIG, Vale, Other) ────
        $sssTot    = round($empColl->sum(fn ($e) => collect($e['periods'])->sum('sssDeduction')), 2);
        $philTot   = round($empColl->sum(fn ($e) => collect($e['periods'])->sum('philhealthDeduction')), 2);
        $pagibigTot = round($empColl->sum(fn ($e) => collect($e['periods'])->sum('pagibigDeduction')), 2);
        $valeTot   = round($empColl->sum(fn ($e) => collect($e['periods'])->sum('vale')), 2);
        $otherTot  = round($empColl->sum(fn ($e) => collect($e['periods'])->sum('manualDeductions')), 2);

        // ── Attendance trend — last 30 days ───────────────────────────────────
        $rawTrend = Attendance::selectRaw('date, COUNT(DISTINCT employee_id) as count')
            ->where('date', '>=', $today->copy()->subDays(29)->toDateString())
            ->whereNotNull('time_in')
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('count', 'date');

        $trendLabels = [];
        $trendData   = [];
        for ($i = 29; $i >= 0; $i--) {
            $d             = $today->copy()->subDays($i)->toDateString();
            $trendLabels[] = Carbon::parse($d)->format('M d');
            $trendData[]   = (int) ($rawTrend[$d] ?? 0);
        }

        // ── Weekly payroll — last 4 complete weeks ────────────────────────────
        $weekStart  = $today->copy()->subWeeks(3)->startOfWeek(Carbon::MONDAY)->toDateString();
        $weeklyData = $payroll->computeForRange($weekStart, $monthEnd);

        $weekLabels = [];
        $weekGross  = [];
        $weekNet    = [];
        foreach ($weeklyData['weeks'] as $w) {
            $weekLabels[] = $w['week_range'];
            $weekGross[]  = round(collect($w['details'])->sum('gross'), 2);
            $weekNet[]    = round(collect($w['details'])->sum('net'), 2);
        }

        // ── Labor type distribution ────────────────────────────────────────────
        $laborDist = Employee::active()->with('laborType')
            ->get()
            ->groupBy(fn ($e) => $e->laborType?->name ?? 'Unassigned')
            ->map(fn ($g) => $g->count())
            ->sortByDesc(fn ($c) => $c);

        // ── Site distribution ──────────────────────────────────────────────────
        $siteDist = Site::withCount('employees')
            ->orderByDesc('employees_count')
            ->get()
            ->mapWithKeys(fn ($s) => [$s->name => $s->employees_count]);

        // ── Top 5 OT employees ─────────────────────────────────────────────────
        $topOT = $empColl
            ->sortByDesc(fn ($e) => $e['totals']['overtime'])
            ->take(5)
            ->map(fn ($e) => [
                'name' => $e['name'],
                'ot'   => $e['totals']['overtime'],
            ])
            ->values();

        // ── Employee performance table (this month) ────────────────────────────
        $empTable = $empColl->sortByDesc(fn ($e) => $e['totals']['net'])->values();

        return view('analytics', compact(
            'monthLabel',
            'totalEmployees', 'presentToday', 'activeSites',
            'monthlyNet', 'monthlyGross', 'monthlyOTPay', 'monthlyHoliday',
            'overtimeHours', 'attendanceRate',
            'netChange', 'lastMonNet',
            'sssTot', 'philTot', 'pagibigTot', 'valeTot', 'otherTot',
            'trendLabels', 'trendData',
            'weekLabels', 'weekGross', 'weekNet',
            'laborDist', 'siteDist',
            'topOT', 'empTable'
        ));
    }
}
