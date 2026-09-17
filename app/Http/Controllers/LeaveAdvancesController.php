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
 * module as well, and so does every advance write (LoanController): a role
 * that files leave has no business seeing who owes the company what.
 *
 * Leave has no approval step. The people filing it are the owner, HR and
 * staff — the same people who would approve it — so it counts as filed, and
 * the one decision left on a row is to cancel it.
 *
 * Neither tab writes attendance. Filed leave and advance instalments are read
 * by Payroll Processing.
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
        ];

        // Only the open tab is queried. The two share filter names — status
        // and q — that mean different things on each side.
        if ($tab === 'advances') {
            // The ledger comes with each row: the balance shown, the progress
            // and the history are all worked out from it, and asking per row
            // would be a query apiece.
            //
            // Listed, not every advance: a deleted one is kept only so that
            // the payroll weeks it was deducted in stay as they were paid.
            $view['advances'] = Loan::listed()
                ->with(['employee', 'deductions' => fn ($q) => $q->orderBy('deducted_on')->orderBy('id')])
                ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
                ->when($request->filled('q'), fn ($q) => $q->whereHas('employee',
                    fn ($e) => $e->where('name', 'like', '%' . $request->q . '%')))
                // The most recently added or changed first — a new advance, an
                // edited instalment, a payment recorded. The row the office
                // just worked on is the one at the top, not wherever its issue
                // date happens to sort it.
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->paginate(15, ['*'], 'advance_page')
                ->withQueryString();

            // An advance the schedule has finished collecting is settled
            // whether or not anybody pressed anything, so the columns are
            // brought up to what the schedule says before they are read.
            //
            // A loop, not each() with an arrow function: each() stops at the
            // first callback that returns false, and syncSettlement() returns
            // false for a row with nothing to write. So every row after the
            // first one already up to date was never brought up to date —
            // which is how an advance read "Fully Paid" in the blue of an
            // active one: the label is worked out live, the colour reads the
            // status column nobody had written.
            //
            // Every advance is loaded for the totals anyway, and whether an
            // instalment was taken depends on each week's pay — a payroll
            // computation. It is done once, for all of them, and the rows on
            // this page borrow it.
            $totals = Loan::listed()
                ->with(['deductions' => fn ($q) => $q->orderBy('deducted_on')->orderBy('id')])
                ->get();

            Loan::loadPayRoom($totals);
            Loan::sharePayRoom($totals, $view['advances']->getCollection());

            foreach ($view['advances'] as $advance) {
                $advance->syncSettlement();
            }

            // What each worker still owes, so the New Cash Advance form can say
            // how much more they may be advanced the moment they are picked —
            // rather than only after Record is pressed. Read off the rows
            // already loaded for the summary below; no query per worker.
            $view['owed'] = $totals->groupBy('employee_id')
                ->map(fn ($rows) => round($rows->sum(fn (Loan $l) => $l->outstanding), 2))
                ->filter(fn (float $v) => $v > 0)
                ->all();

            $view['summary'] = [
                'active'      => $totals->reject->settled->count(),
                'outstanding' => round($totals->sum(fn (Loan $l) => $l->outstanding), 2),
                'issued'      => round($totals->sum('principal'), 2),
                'collected'   => round($totals->sum(fn (Loan $l) => $l->paid_amount), 2),
            ];
        } else {
            $view['leave'] = LeaveRequest::with(['employee', 'filer', 'approver'])
                // Completed is read off the calendar rather than stored, so the
                // filter asks the dates, not the column.
                ->when($request->filled('status'), fn ($q) => $q->showing((string) $request->status))
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
        $data['days'] = ($data['days'] ?? null)
            ?: \Carbon\Carbon::parse($data['starts_on'])->diffInDays($data['ends_on']) + 1;

        // Filed is decided. Only the owner, HR and staff file leave, and they
        // are the ones who would have approved it — so it counts from the
        // moment it is entered, and paid leave reaches payroll as its days
        // come round without anybody pressing a second button.
        $data['is_paid']     = $request->boolean('is_paid');
        $data['status']      = 'approved';
        $data['filed_by']    = auth()->id();
        $data['approved_by'] = auth()->id();
        $data['approved_at'] = now();

        $leave = LeaveRequest::create($data);

        AuditLog::record('Leave', 'created',
            'Filed ' . $leave->type_label . ' for ' . $leave->employee->name, $leave);

        return back()->with('success', 'Leave filed.');
    }

    /**
     * Cancel a filed leave, or restore one cancelled by mistake.
     *
     * With no approval step, rejecting a request is gone with it — and that
     * was the only way to take back a leave filed in error. Cancelling is
     * that way now. It leaves who filed it untouched; the audit log records
     * who called it off.
     */
    public function decide(Request $request, int $id)
    {
        $data = $request->validate([
            'decision' => 'required|in:approved,cancelled',
            'note'     => 'nullable|string|max:500',
        ]);

        $leave = LeaveRequest::findOrFail($id);

        $leave->update([
            'status'        => $data['decision'],
            'decision_note' => $data['note'] ?? $leave->decision_note,
        ]);

        $action = $data['decision'] === 'cancelled' ? 'cancelled' : 'restored';

        AuditLog::record('Leave', $action,
            ucfirst($action) . ' ' . $leave->type_label . ' for ' . ($leave->employee->name ?? 'employee'), $leave);

        return back()->with('success', 'Leave ' . $action . '.');
    }
}
