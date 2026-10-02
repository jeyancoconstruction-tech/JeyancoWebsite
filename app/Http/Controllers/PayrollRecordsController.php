<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\PayrollRate;
use App\Models\Site;
use App\Services\PayrollService;
use App\Notifications\PayrollNotification;
use Illuminate\Http\Request;
use Carbon\Carbon;

/**
 * Unified Payroll Records module — consolidates the former Pay Periods,
 * Payroll Records and Reports pages into one tabbed page (Reports / By Employee
 * / Pay Periods). One date-range context (weekly Mon–Sun, daily, or custom)
 * plus an optional employee filter drives all tabs. All figures come from
 * PayrollService.
 */
class PayrollRecordsController extends Controller
{
    public function index(Request $request, PayrollService $payroll)
    {
        $period = $this->resolvePeriod($request);

        $data      = $payroll->computeForRange($period['from'], $period['to']);
        $days      = $data['days'];
        $employees = $data['employees'];
        // Days waiting for review on Attendance, not paid until settled.
        $held      = $data['held'] ?? [];

        // Optional employee filter (name or DB id #), and the site: one site's
        // crew, or every site as a whole.
        $search = trim((string) $request->input('employee', ''));
        $siteId = $this->siteId($request);
        $sites  = Site::orderBy('name')->get(['id', 'name']);
        $selectedEmployee = null;

        if ($search !== '' || $siteId) {
            $employees = $this->filterEmployees($employees, $request);
            $ids = array_column($employees, 'employee_id');

            foreach ($days as &$day) {
                $day['details'] = array_values(array_filter($day['details'], fn ($d) => in_array($d['employee_id'], $ids)));
                $day['total']   = round(array_sum(array_column($day['details'], 'net')), 2);
            }
            unset($day);
            $days = array_values(array_filter($days, fn ($day) => count($day['details']) > 0));
            $selectedEmployee = $search !== '' ? ($employees[0] ?? null) : null;
        }

        // The numbers the period was computed at, for the receipt to show its
        // own workings. Resolved at the period's end, the same day the bonus
        // resolves on, so the two agree.
        $rateSet = PayrollRate::effectiveOn($period['to']);
        $rates   = $rateSet ? $rateSet->toRates() : PayrollRate::fallbackRates();
        $rates['uses_defaults'] = (bool) ($rateSet?->uses_defaults);

        $summary = $this->summarize($employees);

        // ── Notifications ──────────────────────────────────────────────────
        // Payroll ready: follows the saved notify_payroll value (its switch
        // left System Settings on 2026-10-02).
        if (! empty($employees) && $search === '' && ! $siteId && \App\Models\SystemSetting::current()->enabled('notify_payroll')) {
            $user      = auth()->user();
            $net       = number_format($summary['net'] ?? 0, 2);
            $empCount  = count($employees);

            PayrollNotification::fireOnce($user, 'period_computed',
                'Payroll Computed',
                "Period {$period['label']}: {$empCount} employee" . ($empCount > 1 ? 's' : '') . ", ₱{$net} total net pay."
            );

            // High vale — alert if any employee has a cumulative vale > 5000
            $highValeEmps = collect($employees)->filter(fn ($e) => ($e['totals']['deductions']['vale'] ?? 0) > 5000);
            if ($highValeEmps->isNotEmpty()) {
                $names = $highValeEmps->pluck('name')->join(', ');
                PayrollNotification::fireOnce($user, 'high_vale',
                    'High Vale Balance',
                    "High cash advance this period: {$names}."
                );
            }
        }

        // Waiting days of the workers on screen only, when a name or a site narrows them.
        if ($search !== '' || $siteId) {
            $held = array_intersect_key($held, array_flip(array_column($employees, 'employee_id')));
        }

        return view('payroll-records', compact('period', 'days', 'employees', 'summary', 'search', 'selectedEmployee', 'rates', 'held', 'sites', 'siteId'));
    }

    /**
     * Export the current period's per-employee totals as CSV (no dependency).
     */
    /**
     * Export the current period's per-employee totals for Excel.
     *
     * Written as an HTML table served under an .xls name, which Excel opens
     * natively. It does warn that the extension does not match the contents —
     * the price of needing no PHP extensions beyond the standard ones. A real
     * .xlsx is a ZIP archive and cannot be produced without ext-zip, which the
     * deployment image does not carry.
     */
    public function exportExcel(Request $request, PayrollService $payroll)
    {
        $period    = $this->resolvePeriod($request);
        $employees = $this->filterEmployees(
            $payroll->computeForRange($period['from'], $period['to'])['employees'],
            $request
        );

        $totals   = $this->summarize($employees);
        $site     = ($id = $this->siteId($request)) ? Site::find($id) : null;
        $filename = 'payroll-records_' . ($site ? \Illuminate\Support\Str::slug($site->name) . '_' : '') . $period['from'] . '_to_' . $period['to'] . '.xls';
        $html     = view('exports.payroll-excel', compact('employees', 'period', 'totals'))->render();

        return response($html, 200, [
            'Content-Type'        => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Narrow the computed rows by the page's site (the worker's assigned site)
     * and its "employee (name or ID)" box.
     */
    private function filterEmployees(array $employees, Request $request): array
    {
        if ($siteId = $this->siteId($request)) {
            $onSite    = Employee::withTrashed()->where('site_id', $siteId)->pluck('id')->all();
            $employees = array_values(array_filter($employees, fn ($e) => in_array((int) $e['employee_id'], $onSite, true)));
        }

        $search = trim((string) $request->input('employee', ''));
        if ($search === '') {
            return $employees;
        }

        $needle = strtolower(ltrim($search, '#'));

        return array_values(array_filter($employees, function ($e) use ($needle) {
            return str_contains(strtolower($e['name']), $needle)
                || (string) $e['employee_id'] === $needle;
        }));
    }

    /** The site asked for, or null for every site as a whole. */
    private function siteId(Request $request): ?int
    {
        $id = (int) $request->input('site', 0);

        return $id > 0 && Site::whereKey($id)->exists() ? $id : null;
    }

    /**
     * Resolve the reporting period into a from/to range + display metadata.
     * Weekly mode always yields a Monday–Sunday range.
     */
    private function resolvePeriod(Request $request): array
    {
        $mode  = $request->input('mode', 'weekly');
        $today = Carbon::now();

        // A week or a single day. A loose range is not a pay period, so a daily
        // breakdown of one breaks down into days nobody is paid on. The form
        // does not offer it; a URL that asks for it anyway falls back.
        if ($mode === 'custom') {
            $mode = 'weekly';
        }

        if ($mode === 'daily') {
            $date  = $request->filled('date') && strtotime($request->date) ? Carbon::parse($request->date) : $today->copy();
            $from  = $to = $date->toDateString();
            $label = $date->format('l, m/d/Y');
        } else {
            $mode = 'weekly';
            $week = (string) $request->input('week', '');
            if (preg_match('/^(\d{4})-W(\d{1,2})$/', $week, $m)) {
                $monday = Carbon::now()->setISODate((int) $m[1], (int) $m[2], 1)->startOfDay();
            } else {
                $monday = $today->copy()->startOfWeek(Carbon::MONDAY);
            }
            $sunday = $monday->copy()->addDays(6);
            $from   = $monday->toDateString();
            $to     = $sunday->toDateString();
            $label  = $monday->format('m/d/Y') . ' – ' . $sunday->format('m/d/Y');
        }

        return [
            'mode'        => $mode,
            'from'        => $from,
            'to'          => $to,
            'label'       => $label,
            'week'        => $request->input('week', $today->format('o-\WW')),
            'date'        => $request->input('date', $today->toDateString()),
        ];
    }

    private function summarize(array $employees): array
    {
        $sum = fn ($k) => round(array_sum(array_map(fn ($e) => $e['totals'][$k], $employees)), 2);

        return [
            'employee_count'  => count($employees),
            'workdays'        => (int) array_sum(array_map(fn ($e) => $e['totals']['workdays'], $employees)),
            'hours'           => $sum('hours'),
            'minutes'         => (int) array_sum(array_map(fn ($e) => $e['totals']['minutes'], $employees)),
            'gross'           => $sum('gross'),
            'overtime'        => $sum('overtime'),
            'holidayPay'      => $sum('holidayPay'),
            'restDayPay'      => $sum('restDayPay'),
            'bonus'           => $sum('bonus'),
            'totalDeductions' => $sum('totalDeductions'),
            'net'             => $sum('net'),
        ];
    }
}
