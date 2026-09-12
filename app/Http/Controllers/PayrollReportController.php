<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\LoanDeduction;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\Site;
use Illuminate\Http\Request;

/**
 * Management reports over finished payroll.
 *
 * Every figure comes from payroll_run_items — the frozen numbers a run
 * produced — rather than from a fresh computation. A report and the payslip it
 * summarises therefore always agree, which they cannot if each recomputes.
 *
 * The existing Payroll Records screen is untouched and still recomputes live;
 * the two answer different questions.
 */
class PayrollReportController extends Controller
{
    public const REPORTS = [
        'summary'    => 'Payroll Summary',
        'employee'   => 'Payroll by Employee',
        'site'       => 'Labor Cost by Site',
        'overtime'   => 'Overtime Report',
        'deductions' => 'Deductions Report',
        'advances'   => 'Cash Advances',
    ];

    public function index(Request $request)
    {
        $report = array_key_exists($request->get('report'), self::REPORTS)
            ? $request->get('report')
            : 'summary';

        $from = $request->get('from', now()->startOfMonth()->toDateString());
        $to   = $request->get('to', now()->toDateString());
        $siteId = $request->get('site_id');
        $runId  = $request->get('run');

        // Only signed-off runs are reportable: a draft is a working figure.
        $runs = PayrollRun::whereIn('status', ['approved', 'finalized'])
            ->when($runId, fn ($q) => $q->where('id', $runId))
            ->when(! $runId, fn ($q) => $q->whereBetween('period_start', [$from, $to]))
            ->when($siteId, fn ($q) => $q->where('site_id', $siteId))
            ->orderBy('period_start')
            ->get();

        $items = PayrollRunItem::with(['employee', 'site', 'run'])
            ->whereIn('payroll_run_id', $runs->pluck('id'))
            ->when($siteId, fn ($q) => $q->where('site_id', $siteId))
            ->when($request->filled('q'), fn ($q) => $q->where('employee_name', 'like', '%' . $request->q . '%'))
            ->get();

        return view('reports.index', [
            'report'  => $report,
            'reports' => self::REPORTS,
            'runs'    => $runs,
            'items'   => $items,
            'rows'    => $this->rowsFor($report, $items, $runs),
            'totals'  => [
                'gross'      => round($items->sum('gross_pay'), 2),
                'deductions' => round($items->sum('total_deductions'), 2),
                'net'        => round($items->sum('net_pay'), 2),
                'headcount'  => $items->pluck('employee_id')->unique()->count(),
            ],
            'sites'      => Site::orderBy('name')->get(['id', 'name']),
            'allRuns'    => PayrollRun::whereIn('status', ['approved', 'finalized'])
                                ->latest('period_start')->get(['id', 'code', 'period_start', 'period_end']),
            'from'       => $from,
            'to'         => $to,
            'siteId'     => $siteId,
            'runId'      => $runId,
        ]);
    }

    /** Each report is the same rows, grouped by a different question. */
    private function rowsFor(string $report, $items, $runs): array
    {
        return match ($report) {
            'employee' => $items->groupBy('employee_id')->map(fn ($g) => [
                'label'      => $g->first()->employee_name,
                'sub'        => $g->first()->position,
                'count'      => $g->count(),
                'days'       => round($g->sum('days_worked'), 2),
                'gross'      => round($g->sum('gross_pay'), 2),
                'deductions' => round($g->sum('total_deductions'), 2),
                'net'        => round($g->sum('net_pay'), 2),
            ])->sortBy('label')->values()->all(),

            'site' => $items->groupBy('site_id')->map(fn ($g) => [
                'label'      => $g->first()->site->name ?? 'Unassigned',
                'sub'        => $g->pluck('employee_id')->unique()->count() . ' worker(s)',
                'count'      => $g->count(),
                'days'       => round($g->sum('days_worked'), 2),
                'gross'      => round($g->sum('gross_pay'), 2),
                'deductions' => round($g->sum('total_deductions'), 2),
                'net'        => round($g->sum('net_pay'), 2),
            ])->sortBy('label')->values()->all(),

            // Overtime as the runs paid it, which is overtime counted from
            // attendance. It listed filed claims until those were retired; a
            // report of what was claimed would disagree with the payslips.
            'overtime' => $items->where('overtime_pay', '>', 0)->groupBy('employee_id')->map(fn ($g) => [
                'label'      => $g->first()->employee_name,
                'sub'        => $g->first()->site->name ?? 'No site',
                'count'      => $g->count(),
                'days'       => round($g->sum('ot_hours'), 2),
                'gross'      => round($g->sum('overtime_pay'), 2),
                'deductions' => 0,
                'net'        => round($g->sum('overtime_pay'), 2),
            ])->sortBy('label')->values()->all(),

            'deductions' => collect([
                ['label' => 'Vale',            'key' => 'vale'],
                ['label' => 'Loan Repayment',  'key' => 'loan_deduction'],
                ['label' => 'Cash Advance',    'key' => 'advance_deduction'],
                ['label' => 'Statutory & Other', 'key' => 'other_deductions'],
            ])->map(fn ($d) => [
                'label'      => $d['label'],
                'sub'        => $items->where($d['key'], '>', 0)->count() . ' payslip line(s)',
                'count'      => $items->where($d['key'], '>', 0)->count(),
                'days'       => 0,
                'gross'      => 0,
                'deductions' => round($items->sum($d['key']), 2),
                'net'        => round($items->sum($d['key']), 2),
            ])->filter(fn ($r) => $r['deductions'] > 0)->values()->all(),

            // Cash advances only. Loans are no longer issued, and the ones on
            // file are history rather than a balance anybody is collecting.
            'advances' => Loan::advances()->with(['employee', 'deductions'])->get()->map(fn ($l) => [
                'label'      => $l->employee->name ?? '—',
                'sub'        => $l->status_label,
                'count'      => $l->deductions->count(),
                'days'       => 0,
                'gross'      => round($l->principal, 2),
                'deductions' => round($l->paid_amount, 2),
                'net'        => round($l->balance, 2),
            ])->sortBy('label')->values()->all(),

            // summary — one row per run
            default => $runs->map(fn ($r) => [
                'label'      => $r->code,
                'sub'        => $r->period_label . ($r->site ? ' · ' . $r->site->name : ''),
                'count'      => $r->employee_count,
                'days'       => 0,
                'gross'      => round($r->total_gross, 2),
                'deductions' => round($r->total_deductions, 2),
                'net'        => round($r->total_net, 2),
            ])->values()->all(),
        };
    }
}
