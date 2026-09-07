<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Attendance;
use App\Models\Kiosk;
use App\Services\PayrollService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

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
            // The panel is full height on the one-screen layout and scrolls its
            // own body, so six rows left a third of it empty. Twelve fills it
            // and the rest is a scroll away rather than a page away.
            ->take(12)
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
            ->take(8)
            ->values();

        // Attendance chart
        $attendanceLabels = [];
        $attendanceData = [];
        for ($i=6; $i>=0; $i--) {
            $date = Carbon::today()->subDays($i);
            $attendanceLabels[] = $date->format('M d');
            $attendanceData[] = Attendance::where('date', $date->format('Y-m-d'))->count();
        }

        // ── Still on site: timed in today and not yet out ──────────────────
        $stillIn = Attendance::where('date', Carbon::today()->format('Y-m-d'))
            ->whereNotNull('time_in')
            ->whereNull('time_out')
            ->count();

        // ── The action queue, and the counters beside it ───────────────────
        $attention = $this->buildAttention($stillIn);
        $devices   = $this->deviceSummary();

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
            'attendanceData',
            'stillIn',
            'attention',
            'devices'
        ));
    }

    /**
     * What is waiting for someone to act on it.
     *
     * Every row is a real count over real records and links to the screen that
     * clears it; a row with nothing outstanding is left out entirely rather
     * than shown as a zero. The extension modules are consulted only once
     * their tables exist, so a server that has not run the new migrations yet
     * still renders this page — it simply has fewer rows.
     */
    private function buildAttention(int $stillIn): array
    {
        $rows = [];

        $pendingKiosk = Employee::pending()->count();
        if ($pendingKiosk > 0) {
            $rows[] = [
                'icon'  => 'fa-user-plus',
                'tone'  => 'warn',
                'label' => 'Kiosk registrations to complete',
                'count' => $pendingKiosk,
                'url'   => route('employees.register'),
            ];
        }

        if ($stillIn > 0) {
            $rows[] = [
                'icon'  => 'fa-user-clock',
                'tone'  => 'info',
                'label' => 'Workers still timed in',
                'count' => $stillIn,
                'url'   => url('/attendance'),
            ];
        }

        if (Schema::hasTable('leave_requests')) {
            $n = \App\Models\LeaveRequest::where('status', 'pending')->count();
            if ($n > 0) {
                $rows[] = [
                    'icon'  => 'fa-calendar-day',
                    'tone'  => 'warn',
                    'label' => 'Leave requests awaiting a decision',
                    'count' => $n,
                    'url'   => route('leave.index', ['tab' => 'leave', 'status' => 'pending']),
                ];
            }
        }

        if (Schema::hasTable('overtime_requests')) {
            $n = \App\Models\OvertimeRequest::where('status', 'pending')->count();
            if ($n > 0) {
                $rows[] = [
                    'icon'  => 'fa-clock',
                    'tone'  => 'warn',
                    'label' => 'Overtime claims awaiting approval',
                    'count' => $n,
                    'url'   => route('leave.index', ['tab' => 'overtime', 'status' => 'pending']),
                ];
            }
        }

        if (Schema::hasTable('payroll_runs')) {
            $open = \App\Models\PayrollRun::whereIn('status', ['draft', 'calculated'])->count();
            if ($open > 0) {
                $rows[] = [
                    'icon'  => 'fa-calculator',
                    'tone'  => 'info',
                    'label' => 'Payroll runs open for review',
                    'count' => $open,
                    'url'   => route('payroll-processing.index', ['status' => 'calculated']),
                ];
            }

            $toFinalize = \App\Models\PayrollRun::where('status', 'approved')->count();
            if ($toFinalize > 0) {
                $rows[] = [
                    'icon'  => 'fa-lock',
                    'tone'  => 'ok',
                    'label' => 'Approved runs ready to finalise',
                    'count' => $toFinalize,
                    'url'   => route('payroll-processing.index', ['status' => 'approved']),
                ];
            }
        }

        return $rows;
    }

    /**
     * Kiosk presence, from the same cache entry KioskLocationController fills
     * from the Pi's heartbeat. Read-only, and it invents nothing: a kiosk that
     * has never reported is counted as offline because that is what it is.
     */
    private function deviceSummary(): array
    {
        $kiosks  = Kiosk::all();
        $online  = 0;

        foreach ($kiosks as $k) {
            $fix = Cache::get('kiosk_location_' . $k->id) ?? Cache::get('kiosk_location_' . $k->code);
            $seen = $fix['last_seen'] ?? null;
            if ($seen && Carbon::parse($seen)->diffInSeconds(now()) <= 180) {
                $online++;
            }
        }

        return [
            'total'   => $kiosks->count(),
            'online'  => $online,
            'offline' => $kiosks->count() - $online,
        ];
    }
}