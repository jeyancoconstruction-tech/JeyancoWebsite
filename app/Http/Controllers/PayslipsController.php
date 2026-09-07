<?php

namespace App\Http\Controllers;

use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\SystemSetting;
use Illuminate\Http\Request;

/**
 * Payslips issued from a payroll run.
 *
 * Distinct from the existing PayslipController, which prints straight from
 * live attendance and is left exactly as it is. These come off frozen run
 * items, so a slip handed to a worker in August still says in December what it
 * said the day it was printed.
 */
class PayslipsController extends Controller
{
    public function index(Request $request)
    {
        // Only a run the office has signed produces a payslip.
        $runs = PayrollRun::whereIn('status', ['approved', 'finalized'])
            ->latest('period_start')
            ->get();

        $runId = $request->get('run') ?: $runs->first()?->id;
        $run   = $runId ? PayrollRun::with('site')->find($runId) : null;

        $items = $run
            ? PayrollRunItem::with(['employee', 'site'])
                ->where('payroll_run_id', $run->id)
                ->when($request->filled('q'), fn ($q) => $q->where('employee_name', 'like', '%' . $request->q . '%'))
                ->orderBy('employee_name')
                ->paginate(20)->withQueryString()
            : null;

        return view('payslips.index', compact('runs', 'run', 'items'));
    }

    public function show(PayrollRunItem $item)
    {
        $item->load(['run', 'employee', 'site']);

        return view('payslips.show', [
            'item'    => $item,
            'run'     => $item->run,
            'company' => SystemSetting::current(),
        ]);
    }

    /** Same slip, print stylesheet, no app chrome. */
    public function print(PayrollRunItem $item)
    {
        $item->load(['run', 'employee', 'site']);

        return view('payslips.print', [
            'item'    => $item,
            'run'     => $item->run,
            'company' => SystemSetting::current(),
        ]);
    }

    /** Every slip in a run, one per page, for a single print job. */
    public function printRun(PayrollRun $run)
    {
        $run->load(['items.employee', 'items.site', 'site']);

        return view('payslips.print-run', [
            'run'     => $run,
            'company' => SystemSetting::current(),
        ]);
    }
}

