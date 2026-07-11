<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Services\PayrollService;
use Illuminate\Http\Request;
use Carbon\Carbon;

/**
 * Auto-generates a per-employee payslip for a pay period from attendance +
 * payroll data (via PayrollService). Supports view, print/Save-as-PDF (browser)
 * and CSV export — no external dependency.
 */
class PayslipController extends Controller
{
    public function export(Request $request, PayrollService $payroll, $employee)
    {
        $emp = Employee::findOrFail($employee);
        [$from, $to, $label] = $this->resolveRange($request);
        $p = $this->buildPayslip($payroll, $emp, $from, $to);

        $filename = 'payslip_' . str_replace(' ', '-', strtolower($emp->name)) . '_' . $from . '_to_' . $to . '.csv';

        $callback = function () use ($emp, $p, $label) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Jeyanco Construction — Payslip']);
            fputcsv($out, ['Employee', $emp->name]);
            fputcsv($out, ['Employee ID', '#' . $emp->id]);
            fputcsv($out, ['Position', $emp->position]);
            fputcsv($out, ['Pay Period', $label]);
            fputcsv($out, ['Workdays', $p['workdays']]);
            fputcsv($out, ['Total Hours', $p['hours']]);
            fputcsv($out, []);
            fputcsv($out, ['EARNINGS', 'Amount (PHP)']);
            fputcsv($out, ['Daily Rate', $p['dailyRate']]);
            fputcsv($out, ['Regular Pay', $p['regular']]);
            fputcsv($out, ['Overtime Pay', $p['overtime']]);
            fputcsv($out, ['Holiday Pay', $p['holidayPay']]);
            fputcsv($out, ['Rest Day Pay (Sun)', $p['restDayPay'] ?? 0]);
            fputcsv($out, ['Bonus', $p['bonus']]);
            fputcsv($out, ['Gross Pay', $p['gross']]);
            fputcsv($out, []);
            fputcsv($out, ['DEDUCTIONS', 'Amount (PHP)']);
            fputcsv($out, ['SSS', $p['ded']['sss']]);
            fputcsv($out, ['PhilHealth', $p['ded']['philhealth']]);
            fputcsv($out, ['PAG-IBIG', $p['ded']['pagibig']]);
            fputcsv($out, ['Vale / Utang', $p['ded']['vale']]);
            fputcsv($out, ['Other', $p['ded']['other']]);
            fputcsv($out, ['Total Deductions', $p['totalDeductions']]);
            fputcsv($out, []);
            fputcsv($out, ['NET PAY', $p['net']]);
            fclose($out);
        };

        return response()->streamDownload($callback, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * A4 batch print: every employee's payslip for one pay period, laid out as
     * compact cut-out slips (each with the company logo) so admin prints one
     * sheet and cuts it — saves paper. Opened in a new tab; auto-triggers print.
     */
    public function printBatch(Request $request, PayrollService $payroll)
    {
        [$from, $to, $label] = $this->resolveRange($request);

        $employees = $payroll->computeForRange($from, $to)['employees'];

        // Optional single-employee slip (the per-row "Print Slip" button).
        if ($request->filled('employee')) {
            $employees = collect($employees)
                ->where('employee_id', (int) $request->input('employee'))
                ->values()
                ->all();
        }

        $slips = collect($employees)->map(function (array $e): array {
            $t = $e['totals'];

            $ded = ['sss' => 0, 'philhealth' => 0, 'pagibig' => 0, 'vale' => 0, 'other' => 0];
            foreach ($e['periods'] as $p) {
                $ded['sss']        += $p['sssDeduction'];
                $ded['philhealth'] += $p['philhealthDeduction'];
                $ded['pagibig']    += $p['pagibigDeduction'];
                $ded['vale']       += $p['vale'];
                $ded['other']      += $p['manualDeductions'];
            }
            $ded = array_map(fn ($v) => round($v, 2), $ded);

            $regular = round($t['gross'] - $t['overtime'] - $t['holidayPay'] - ($t['restDayPay'] ?? 0), 2);

            return [
                'employee_id'     => $e['employee_id'],
                'name'            => $e['name'],
                'position'        => $e['position'] ?? '',
                'workdays'        => $t['workdays'],
                'hours'           => $t['hours'],
                'regular'         => $regular,
                'overtime'        => $t['overtime'],
                'holidayPay'      => $t['holidayPay'],
                'restDayPay'      => $t['restDayPay'] ?? 0,
                'bonus'           => $t['bonus'],
                'gross'           => $t['gross'],
                'ded'             => $ded,
                'totalDeductions' => $t['totalDeductions'],
                'net'             => $t['net'],
            ];
        })->values();

        return view('payslips-batch', [
            'slips'       => $slips,
            'periodLabel' => $label,
            'from'        => $from,
            'to'          => $to,
        ]);
    }

    /**
     * Resolve from/to (defaults to the current Monday–Sunday week).
     */
    private function resolveRange(Request $request): array
    {
        $today = Carbon::now();

        if ($request->filled('from') && $request->filled('to') && strtotime($request->from) && strtotime($request->to)) {
            $from = Carbon::parse($request->from)->toDateString();
            $to   = Carbon::parse($request->to)->toDateString();
            if ($from > $to) {
                [$from, $to] = [$to, $from];
            }
        } else {
            $monday = $today->copy()->startOfWeek(Carbon::MONDAY);
            $from   = $monday->toDateString();
            $to     = $monday->copy()->addDays(6)->toDateString();
        }

        $label = Carbon::parse($from)->format('m/d/Y') . ' – ' . Carbon::parse($to)->format('m/d/Y');

        return [$from, $to, $label];
    }

    /**
     * Build the payslip figures for an employee over a date range.
     */
    private function buildPayslip(PayrollService $payroll, Employee $emp, string $from, string $to): array
    {
        $employees = $payroll->computeForRange($from, $to)['employees'];
        $data = collect($employees)->firstWhere('employee_id', $emp->id);

        $ded = ['sss' => 0, 'philhealth' => 0, 'pagibig' => 0, 'vale' => 0, 'other' => 0];
        $totals = [
            'workdays' => 0, 'hours' => 0, 'gross' => 0, 'overtime' => 0,
            'holidayPay' => 0, 'restDayPay' => 0, 'bonus' => 0, 'totalDeductions' => 0, 'net' => 0,
        ];
        $periods = [];

        if ($data) {
            $totals  = $data['totals'];
            $periods = $data['periods'];

            foreach ($periods as $p) {
                $ded['sss']        += $p['sssDeduction'];
                $ded['philhealth'] += $p['philhealthDeduction'];
                $ded['pagibig']    += $p['pagibigDeduction'];
                $ded['vale']       += $p['vale'];
                $ded['other']      += $p['manualDeductions'];
            }
            foreach ($ded as $k => $v) {
                $ded[$k] = round($v, 2);
            }
        }

        // Regular pay = gross minus the OT, holiday, and rest day premium portions
        $regular = round($totals['gross'] - $totals['overtime'] - $totals['holidayPay'] - ($totals['restDayPay'] ?? 0), 2);

        // Daily rate from labor type (source of truth) or fall back to stored hourly × 8
        $dailyRate = $emp->laborType?->daily_rate ?? round(($emp->rate_per_hour ?? 0) * 8, 2);

        return array_merge($totals, [
            'regular'   => $regular,
            'dailyRate' => round($dailyRate, 2),
            'ded'       => $ded,
            'periods'   => $periods,
        ]);
    }
}
