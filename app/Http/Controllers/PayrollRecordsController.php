<?php

namespace App\Http\Controllers;

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
        $weeks     = $data['weeks'];
        $days      = $data['days'];
        $employees = $data['employees'];

        // Optional employee filter (name or DB id #) — narrows every tab.
        $search = trim((string) $request->input('employee', ''));
        $selectedEmployee = null;

        if ($search !== '') {
            $needle = strtolower(ltrim($search, '#'));
            $match  = fn ($e) => str_contains(strtolower($e['name']), $needle) || (string) $e['employee_id'] === $needle;

            $employees = array_values(array_filter($employees, $match));
            $ids = array_column($employees, 'employee_id');

            foreach ($days as &$day) {
                $day['details'] = array_values(array_filter($day['details'], fn ($d) => in_array($d['employee_id'], $ids)));
                $day['total']   = round(array_sum(array_column($day['details'], 'net')), 2);
            }
            unset($day);
            $days = array_values(array_filter($days, fn ($day) => count($day['details']) > 0));

            foreach ($weeks as &$w) {
                $w['details']        = array_values(array_filter($w['details'], fn ($d) => in_array($d['employee_id'], $ids)));
                $w['employee_count'] = count($w['details']);
                $w['total_payroll']  = round(array_sum(array_column($w['details'], 'net')), 2);
            }
            unset($w);
            $weeks = array_values(array_filter($weeks, fn ($w) => count($w['details']) > 0));

            $selectedEmployee = $employees[0] ?? null;
        }

        $summary = $this->summarize($employees);

        // ── Notifications ──────────────────────────────────────────────────
        if (! empty($employees) && $search === '') {
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

        return view('payroll-records', compact('period', 'weeks', 'days', 'employees', 'summary', 'search', 'selectedEmployee'));
    }

    /**
     * Export the current period's per-employee totals as CSV (no dependency).
     */
    public function export(Request $request, PayrollService $payroll)
    {
        $period    = $this->resolvePeriod($request);
        $employees = $payroll->computeForRange($period['from'], $period['to'])['employees'];

        $search = trim((string) $request->input('employee', ''));
        if ($search !== '') {
            $needle = strtolower(ltrim($search, '#'));
            $employees = array_values(array_filter($employees, function ($e) use ($needle) {
                return str_contains(strtolower($e['name']), $needle) || (string) $e['employee_id'] === $needle;
            }));
        }

        $filename = 'payroll-records_' . $period['from'] . '_to_' . $period['to'] . '.csv';
        $columns  = ['Employee ID', 'Name', 'Position', 'Workdays', 'Hours', 'Gross Pay', 'Overtime', 'Holiday Pay', 'Rest Day Pay', 'Bonus', 'Deductions', 'Net Pay'];

        $callback = function () use ($employees, $columns, $period) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Jeyanco Payroll Records']);
            fputcsv($out, ['Period (' . ucfirst($period['mode']) . ')', $period['label']]);
            fputcsv($out, []);
            fputcsv($out, $columns);
            foreach ($employees as $e) {
                $t = $e['totals'];
                fputcsv($out, [
                    $e['employee_id'], $e['name'], $e['position'],
                    $t['workdays'], $t['hours'], $t['gross'], $t['overtime'],
                    $t['holidayPay'], $t['restDayPay'] ?? 0, $t['bonus'], $t['totalDeductions'], $t['net'],
                ]);
            }
            fclose($out);
        };

        return response()->streamDownload($callback, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * Resolve the reporting period into a from/to range + display metadata.
     * Weekly mode always yields a Monday–Sunday range.
     */
    private function resolvePeriod(Request $request): array
    {
        $mode  = $request->input('mode', 'weekly');
        $today = Carbon::now();

        if ($mode === 'daily') {
            $date  = $request->filled('date') && strtotime($request->date) ? Carbon::parse($request->date) : $today->copy();
            $from  = $to = $date->toDateString();
            $label = $date->format('l, m/d/Y');
        } elseif ($mode === 'custom') {
            $from = $request->filled('from') && strtotime($request->from)
                ? Carbon::parse($request->from)->toDateString()
                : $today->copy()->startOfMonth()->toDateString();
            $to = $request->filled('to') && strtotime($request->to)
                ? Carbon::parse($request->to)->toDateString()
                : $today->toDateString();
            if ($from > $to) {
                [$from, $to] = [$to, $from];
            }
            $label = Carbon::parse($from)->format('m/d/Y') . ' – ' . Carbon::parse($to)->format('m/d/Y');
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
            'custom_from' => $request->input('from', $today->copy()->startOfMonth()->toDateString()),
            'custom_to'   => $request->input('to', $today->toDateString()),
        ];
    }

    private function summarize(array $employees): array
    {
        $sum = fn ($k) => round(array_sum(array_map(fn ($e) => $e['totals'][$k], $employees)), 2);

        return [
            'employee_count'  => count($employees),
            'workdays'        => (int) array_sum(array_map(fn ($e) => $e['totals']['workdays'], $employees)),
            'hours'           => $sum('hours'),
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
