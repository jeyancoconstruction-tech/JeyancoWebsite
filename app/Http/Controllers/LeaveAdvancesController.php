<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Loan;
use App\Support\Modules;
use Illuminate\Http\Request;

/**
 * Leave and cash advances, on one screen with two tabs — the same shape the
 * Attendance page already uses.
 *
 * What is not here matters as much. Overtime was a tab, filed by hand as a
 * claim; it is counted from attendance now, so a claim could only pay the
 * same evening twice. Loans sat beside the advances; they are no longer
 * issued. Old loan rows stay on file but are neither listed nor collected.
 *
 * The page opens under the leave module. The advances tab needs the advances
 * module as well, and so does every advance write (LoanController): a
 * supervisor who approves leave has no business seeing who owes the company
 * what.
 *
 * Neither tab writes attendance. Approved leave and advance instalments are
 * read by Payroll Processing.
 */
class LeaveAdvancesController extends Controller
{
    public function index(Request $request)
    {
        $canAdvances = (bool) $request->user()?->canAccessModule(Modules::ADVANCES);
        $tab         = $request->get('tab') === 'advances' ? 'advances' : 'leave';

        abort_if($tab === 'advances' && ! $canAdvances, 403);

        $view = [
            'tab'         => $tab,
            'canAdvances' => $canAdvances,
            'employees'   => Employee::registered()->orderBy('name')->get(['id', 'name']),
            'counts'      => [
                'leave_pending' => LeaveRequest::where('status', 'pending')->count(),
            ],
        ];

        // Only the open tab is queried. The two share filter names — status
        // and q — that mean different things on each side.
        if ($tab === 'advances') {
            // The ledger comes with each row: the balance shown, the progress
            // and the history are all worked out from it, and asking per row
            // would be a query apiece.
            $view['advances'] = Loan::advances()
                ->with(['employee', 'deductions' => fn ($q) => $q->orderBy('deducted_on')->orderBy('id')])
                ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
                ->when($request->filled('q'), fn ($q) => $q->whereHas('employee',
                    fn ($e) => $e->where('name', 'like', '%' . $request->q . '%')))
                ->latest('issued_on')
                ->paginate(15, ['*'], 'advance_page')
                ->withQueryString();

            // An advance the schedule has finished collecting is settled
            // whether or not anybody pressed anything, so the columns are
            // brought up to what the schedule says before they are read.
            $view['advances']->each(fn (Loan $l) => $l->syncSettlement());

            $totals = Loan::advances()
                ->with(['deductions' => fn ($q) => $q->orderBy('deducted_on')->orderBy('id')])
                ->get();

            $view['summary'] = [
                'active'      => $totals->reject->settled->count(),
                'outstanding' => round($totals->sum(fn (Loan $l) => $l->outstanding), 2),
                'issued'      => round($totals->sum('principal'), 2),
                'collected'   => round($totals->sum(fn (Loan $l) => $l->paid_amount), 2),
            ];
        } else {
            $view['leave'] = LeaveRequest::with(['employee', 'approver'])
                ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
                ->when($request->filled('type'), fn ($q) => $q->where('leave_type', $request->type))
                ->when($request->filled('q'), fn ($q) => $q->whereHas('employee',
                    fn ($e) => $e->where('name', 'like', '%' . $request->q . '%')))
                ->when($request->filled('from'), fn ($q) => $q->where('ends_on', '>=', $request->from))
                ->when($request->filled('to'), fn ($q) => $q->where('starts_on', '<=', $request->to))
                ->latest('starts_on')
                ->paginate(15, ['*'], 'leave_page')
                ->withQueryString();
        }

        return view('leave.index', $view);
    }

    public function storeLeave(Request $request)
    {
        $data = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'leave_type'  => 'required|string|max:40',
            'starts_on'   => 'required|date',
            'ends_on'     => 'required|date|after_or_equal:starts_on',
            'days'        => 'nullable|numeric|min:0|max:365',
            'is_paid'     => 'nullable|boolean',
            'reason'      => 'nullable|string|max:1000',
        ]);

        // Calendar days when the filer did not say otherwise. The office often
        // wants a different figure — a half day, or a range crossing a holiday
        // it chose not to charge — so the field stays editable.
        $data['days'] = $data['days']
            ?: \Carbon\Carbon::parse($data['starts_on'])->diffInDays($data['ends_on']) + 1;

        $data['is_paid']  = $request->boolean('is_paid');
        $data['status']   = 'pending';
        $data['filed_by'] = auth()->id();

        $leave = LeaveRequest::create($data);

        AuditLog::record('Leave', 'created',
            'Filed ' . $leave->type_label . ' for ' . $leave->employee->name, $leave);

        return back()->with('success', 'Leave filed and awaiting approval.');
    }

    /** Approve, reject or cancel a leave request. */
    public function decide(Request $request, int $id)
    {
        $data = $request->validate([
            'decision' => 'required|in:approved,rejected,cancelled',
            'note'     => 'nullable|string|max:500',
        ]);

        $leave = LeaveRequest::findOrFail($id);

        $leave->update([
            'status'        => $data['decision'],
            'approved_by'   => auth()->id(),
            'approved_at'   => now(),
            'decision_note' => $data['note'] ?? null,
        ]);

        AuditLog::record('Leave', $data['decision'],
            ucfirst($data['decision']) . ' for ' . ($leave->employee->name ?? 'employee'), $leave);

        return back()->with('success', 'Request ' . $data['decision'] . '.');
    }
}
