<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Loan;
use App\Support\Modules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
 * by payroll (Payroll Records and the payslip).
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
        // The form sends the days picked on its calendar, one by one, so HR
        // can file exactly the days the worker is off — Monday and Thursday,
        // say — rather than everything between two dates. A start and an end
        // are still taken, for anything that posts a range.
        $picked = $request->filled('dates');

        $data = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'leave_type'  => 'required|string|max:40',
            'dates'       => 'nullable|array|max:366',
            'dates.*'     => 'date_format:Y-m-d',
            'starts_on'   => $picked ? 'nullable' : 'required|date',
            'ends_on'     => $picked ? 'nullable' : 'required|date|after_or_equal:starts_on',
            'days'        => 'nullable|numeric|min:0|max:365',
            'is_paid'     => 'nullable|boolean',
            'reason'      => 'nullable|string|max:1000',
        ], [
            'starts_on.required' => 'Pick at least one day on the calendar.',
        ]);

        // A leave is a run of days, starts_on to ends_on, and payroll credits
        // every day inside it. Days picked apart from each other are filed as
        // one leave per unbroken run, so the days between them are not paid
        // as leave.
        $runs = $picked
            ? $this->runsOf($data['dates'])
            : [[$data['starts_on'], $data['ends_on']]];

        // Calendar days when the filer did not say otherwise. The office often
        // wants a different figure — a half day, or a range crossing a holiday
        // it chose not to charge — so the field stays editable. It is one
        // figure, so it only applies when the days picked are one run.
        $typedDays = count($runs) === 1 ? ($data['days'] ?? null) : null;

        $leaves = DB::transaction(function () use ($runs, $data, $typedDays, $request) {
            return array_map(fn (array $run) => LeaveRequest::create([
                'employee_id' => $data['employee_id'],
                'leave_type'  => $data['leave_type'],
                'reason'      => $data['reason'] ?? null,
                'starts_on'   => $run[0],
                'ends_on'     => $run[1],
                'days'        => $typedDays ?: \Carbon\Carbon::parse($run[0])->diffInDays($run[1]) + 1,
                // Filed is decided. Only the owner, HR and staff file leave, and
                // they are the ones who would have approved it — so it counts
                // from the moment it is entered, and paid leave reaches payroll
                // as its days come round without anybody pressing a second button.
                'is_paid'     => $request->boolean('is_paid'),
                'status'      => 'approved',
                'filed_by'    => auth()->id(),
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]), $runs);
        });

        foreach ($leaves as $leave) {
            AuditLog::record('Leave', 'created',
                'Filed ' . $leave->type_label . ' for ' . $leave->employee->name
                . ' (' . $leave->starts_on->format('M j') . ($leave->ends_on->ne($leave->starts_on) ? '–' . $leave->ends_on->format('M j') : '') . ')',
                $leave);
        }

        return back()->with('success', 'Leave filed.');
    }

    /**
     * Days picked on the calendar, as unbroken runs: [[first, last], …].
     *
     * @param  list<string>  $dates  Y-m-d, in any order, repeats allowed
     * @return list<array{0: string, 1: string}>
     */
    private function runsOf(array $dates): array
    {
        $dates = array_values(array_unique($dates));
        sort($dates);

        $runs = [];
        foreach ($dates as $d) {
            $last = count($runs) - 1;
            if ($last >= 0 && \Carbon\Carbon::parse($runs[$last][1])->addDay()->toDateString() === $d) {
                $runs[$last][1] = $d;
            } else {
                $runs[] = [$d, $d];
            }
        }

        return $runs;
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
