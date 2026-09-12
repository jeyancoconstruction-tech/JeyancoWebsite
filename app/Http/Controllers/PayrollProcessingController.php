<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\PayrollRun;
use App\Models\Site;
use App\Services\PayrollRunService;
use Illuminate\Http\Request;

/**
 * Payroll runs: create, calculate, review, recalculate, approve, finalise.
 *
 * The arithmetic belongs to PayrollService, which is called through
 * PayrollRunService and is not modified. Nothing here finalises on its own —
 * every state change is a deliberate POST from a button the user pressed.
 */
class PayrollProcessingController extends Controller
{
    public function __construct(private PayrollRunService $runs)
    {
    }

    public function index(Request $request)
    {
        $runs = PayrollRun::with(['site', 'creator', 'approver'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->latest('period_start')
            ->paginate(15)
            ->withQueryString();

        return view('payroll-processing.index', [
            'runs'  => $runs,
            'sites' => Site::orderBy('name')->get(['id', 'name']),
            'counts' => [
                'draft'     => PayrollRun::whereIn('status', ['draft', 'calculated'])->count(),
                'approved'  => PayrollRun::where('status', 'approved')->count(),
                'finalized' => PayrollRun::where('status', 'finalized')->count(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'period_start' => 'required|date',
            'period_end'   => 'required|date|after_or_equal:period_start',
            'site_id'      => 'nullable|exists:sites,id',
            'title'        => 'nullable|string|max:120',
            'notes'        => 'nullable|string|max:1000',
        ]);

        $run = PayrollRun::create($data + [
            'code'       => PayrollRun::nextCode(),
            'status'     => 'draft',
            'created_by' => auth()->id(),
        ]);

        AuditLog::record('Payroll', 'created',
            'Created payroll run ' . $run->code . ' for ' . $run->period_label, $run);

        // Straight into the figures: a draft with nothing in it has nothing
        // to review, and the user's next click would be Calculate anyway.
        $this->runs->calculate($run);

        return redirect()->route('payroll-processing.show', $run)
            ->with('success', 'Payroll run ' . $run->code . ' created and calculated.');
    }

    public function show(PayrollRun $run)
    {
        $run->load(['items.employee', 'items.site', 'site', 'creator', 'approver', 'finalizer']);

        return view('payroll-processing.show', ['run' => $run]);
    }

    public function calculate(PayrollRun $run)
    {
        if (! $run->isEditable()) {
            return back()->with('error',
                'Run ' . $run->code . ' is ' . strtolower($run->status_label)
                . '. Reopen it before recalculating.');
        }

        $this->runs->calculate($run);

        AuditLog::record('Payroll', 'recalculated', 'Recalculated ' . $run->code, $run);

        return back()->with('success', 'Figures recalculated from attendance.');
    }

    public function approve(PayrollRun $run)
    {
        if ($run->status !== 'calculated') {
            return back()->with('error', 'Only a calculated run can be approved.');
        }

        $run->update([
            'status'      => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        AuditLog::record('Payroll', 'approved', 'Approved payroll run ' . $run->code, $run);

        return back()->with('success', 'Run ' . $run->code . ' approved. Finalise it to issue payslips.');
    }

    /**
     * The irreversible step. Cash advance instalments are collected HERE and
     * only here, so a run recalculated five times never collects five times.
     */
    public function finalize(Request $request, PayrollRun $run)
    {
        $request->validate(['confirm' => 'required|accepted']);

        if ($run->status !== 'approved') {
            return back()->with('error', 'Only an approved run can be finalised.');
        }

        $collected = $this->runs->collectLoans($run);

        $run->update([
            'status'       => 'finalized',
            'finalized_by' => auth()->id(),
            'finalized_at' => now(),
        ]);

        AuditLog::record('Payroll', 'finalized',
            'Finalised ' . $run->code . ' — ₱' . number_format($run->total_net, 2)
            . ' net across ' . $run->employee_count . ' worker(s), '
            . $collected . ' instalment collection(s) posted', $run);

        return back()->with('success', 'Run finalised. Payslips are now available.');
    }

    /** Approved but not yet finalised, and something was wrong. */
    public function reopen(PayrollRun $run)
    {
        if ($run->isFinal()) {
            return back()->with('error', 'A finalised run cannot be reopened.');
        }

        $run->update(['status' => 'calculated', 'approved_by' => null, 'approved_at' => null]);

        AuditLog::record('Payroll', 'reopened', 'Reopened ' . $run->code, $run);

        return back()->with('success', 'Run reopened for editing.');
    }

    public function destroy(PayrollRun $run)
    {
        if ($run->isFinal()) {
            return back()->with('error', 'A finalised run cannot be deleted.');
        }

        $code = $run->code;
        $run->delete();

        AuditLog::record('Payroll', 'deleted', 'Deleted payroll run ' . $code);

        return redirect()->route('payroll-processing.index')->with('success', 'Run ' . $code . ' deleted.');
    }
}

