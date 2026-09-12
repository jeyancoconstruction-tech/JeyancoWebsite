<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Loan;
use App\Models\LoanDeduction;
use App\Support\Modules;
use Illuminate\Http\Request;

/**
 * Leave and loans, on one screen with two tabs — the same shape the
 * Attendance page already uses.
 *
 * Overtime used to be the second tab, filed and approved by hand as a claim.
 * It is counted from attendance now — the time past the shift's regular
 * hours — so a claim could only pay the same evening twice, and the tab went.
 * Loans & Advances, which had a sidebar entry of its own, took its place.
 *
 * The page opens under the leave module. The loans tab needs the loans module
 * as well, and so does every loan write (LoanController): a supervisor who
 * approves leave has no business seeing who owes the company what.
 *
 * Neither tab writes attendance. Approved leave and loan instalments are read
 * by Payroll Processing.
 */
class LeaveLoansController extends Controller
{
    public function index(Request $request)
    {
        $canLoans = (bool) $request->user()?->canAccessModule(Modules::LOANS);
        $tab      = $request->get('tab') === 'loans' ? 'loans' : 'leave';

        abort_if($tab === 'loans' && ! $canLoans, 403);

        $view = [
            'tab'       => $tab,
            'canLoans'  => $canLoans,
            'employees' => Employee::registered()->orderBy('name')->get(['id', 'name']),
            'counts'    => [
                'leave_pending' => LeaveRequest::where('status', 'pending')->count(),
            ],
        ];

        // Only the open tab is queried. The two share filter names — status,
        // type, q — that mean different things on each side.
        if ($tab === 'loans') {
            $view['loans'] = Loan::with(['employee', 'deductions'])
                ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
                ->when($request->filled('type'), fn ($q) => $q->where('type', $request->type))
                ->when($request->filled('q'), fn ($q) => $q->whereHas('employee',
                    fn ($e) => $e->where('name', 'like', '%' . $request->q . '%')))
                ->latest('issued_on')
                ->paginate(15, ['*'], 'loan_page')
                ->withQueryString();

            $view['summary'] = [
                'active'      => Loan::where('status', 'active')->count(),
                'outstanding' => (float) Loan::where('status', 'active')->sum('balance'),
                'issued'      => (float) Loan::sum('principal'),
                'collected'   => (float) LoanDeduction::sum('amount'),
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
