<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\Loan;
use App\Models\LoanDeduction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Cash advances collected over several payrolls.
 *
 * The `vale` on employees is a different instrument, settled inside a single
 * period, and payroll already handles it. Nothing here touches it.
 *
 * The list is the Cash Advances tab of Leave & Advances
 * (LeaveAdvancesController); the writes stay here, behind the advances
 * module. Loans used to be issued here too. They are not any more: whatever
 * the form sends, a new row is an advance, and the loans already on file
 * cannot be edited or paid against from here.
 */
class LoanController extends Controller
{
    /** The old address. Cash Advances is a tab of Leave & Advances now. */
    public function index()
    {
        return redirect()->route('leave.index', ['tab' => 'advances']);
    }

    public function store(Request $request)
    {
        // The company advances one worker ₱30,000 at most — on what they owe,
        // so the room is the limit less every advance of theirs still being
        // paid back. Worked out before validating so the message can say why.
        $worker = Employee::find((int) $request->input('employee_id'));
        $owed   = $worker ? Loan::owedBy($worker->id) : 0.0;
        $room   = max(0.0, round(Loan::LIMIT_PER_EMPLOYEE - $owed, 2));
        $limit  = '₱' . number_format(Loan::LIMIT_PER_EMPLOYEE, 2);

        $overLimit = match (true) {
            $owed <= 0   => "A cash advance cannot be more than {$limit}.",
            $room <= 0   => "Cash advances are limited to {$limit} per employee. {$worker->name} still owes ₱"
                          . number_format($owed, 2) . ', so nothing more can be advanced until it is paid down.',
            default      => "Cash advances are limited to {$limit} per employee. {$worker->name} still owes ₱"
                          . number_format($owed, 2) . ', so at most ₱' . number_format($room, 2) . ' more can be advanced.',
        };

        // The amount as typed, for the instalment's message to name.
        $borrowed = is_numeric($request->input('principal'))
            ? '₱' . number_format((float) $request->input('principal'), 2)
            : 'the amount borrowed';

        $data = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'principal'   => 'required|numeric|min:1|max:' . $room,
            // Never more than was borrowed. It used to be lowered to the amount
            // without a word and saved, so the office entered one figure and
            // the advance collected another; it is refused now, and says why.
            'installment' => 'required|numeric|min:1|lte:principal',
            'schedule'    => 'required|in:per_payroll,monthly',
            'issued_on'   => 'required|date',
            'starts_on'   => 'nullable|date|after_or_equal:issued_on',
            'reference'   => 'nullable|string|max:40',
            'notes'       => 'nullable|string|max:1000',
        ], [
            'principal.max'   => $overLimit,
            'installment.lte' => "The instalment cannot be more than the amount borrowed ({$borrowed}). "
                               . 'Enter an instalment equal to or lower than it.',
        ], ['principal' => 'amount', 'installment' => 'instalment']);

        $data['type']       = Loan::ADVANCE;
        $data['balance']    = $data['principal'];
        $data['status']     = 'active';
        $data['created_by'] = auth()->id();

        $loan = Loan::create($data);

        AuditLog::record('Loans', 'created',
            $loan->type_label . ' of ₱' . number_format($loan->principal, 2)
            . ' for ' . $loan->employee->name, $loan);

        return back()->with('success', $loan->type_label . ' recorded.');
    }

    /**
     * Correct an advance that was entered wrongly.
     *
     * The instalment is the whole schedule, so changing it works the
     * collection out again from the first payroll rather than from today —
     * which is why it is for fixing a typo, not for re-negotiating. The
     * before and after both go to the Audit Log.
     */
    public function update(Request $request, Loan $loan)
    {
        $this->onlyAdvances($loan);

        $data = $request->validate([
            'installment' => 'nullable|numeric|min:1|max:' . $loan->principal,
            'status'      => 'nullable|in:active,paid,on_hold,cancelled',
            'notes'       => 'nullable|string|max:1000',
        ], [
            'installment.max' => 'The instalment cannot be more than the ₱'
                . number_format($loan->principal, 2) . ' advanced.',
        ]);

        $was = (float) $loan->installment;

        $loan->update(array_filter($data, fn ($v) => $v !== null));
        $loan->load(['deductions' => fn ($q) => $q->orderBy('deducted_on')->orderBy('id')]);
        $loan->syncSettlement();

        $now = (float) $loan->installment;

        AuditLog::record('Loans', 'updated',
            'Updated ' . $loan->type_label . ' for ' . $loan->employee->name
            . ($was === $now ? '' : ' — instalment ₱' . number_format($was, 2)
                . ' → ₱' . number_format($now, 2)
                . ', ₱' . number_format($loan->outstanding, 2) . ' left'),
            $loan);

        if ($was === $now) {
            return back()->with('success', 'Cash advance updated.');
        }

        $payrolls = max(1, (int) ceil($loan->principal / max(0.01, $now)));

        return back()->with('success',
            'Instalment set to ₱' . number_format($now, 2) . ' — '
            . $loan->employee->name . "'s advance now collects over {$payrolls} "
            . ($payrolls === 1 ? 'payroll' : 'payrolls') . ', ₱'
            . number_format($loan->outstanding, 2) . ' left.');
    }

    /**
     * A collection made outside payroll — cash handed back at the office.
     *
     * It comes off the balance in the week it was handed in, so payroll takes
     * less from then on, or nothing once the advance is settled. Payroll's
     * own instalments are not rows here: the schedule on Loan::walk() works
     * them out, which is what keeps the two from doubling up.
     */
    public function recordPayment(Request $request, Loan $loan)
    {
        $this->onlyAdvances($loan);

        // Never more than is actually left. What is left counts the payroll
        // instalments the schedule has already taken, not just the payments
        // made at the office, so an advance payroll has nearly finished
        // cannot be paid twice over.
        $left = $loan->outstanding;

        if ($left <= 0) {
            return back()->with('error', $loan->employee->name . "'s cash advance is already fully paid.");
        }

        $data = $request->validate([
            'amount'      => 'required|numeric|min:0.01|max:' . $left,
            // Between the day the cash went out and today. Before it was
            // issued there was nothing to pay back; after today it has not
            // been handed in yet, and a row dated ahead sat on file without
            // moving the balance until its week came round.
            'deducted_on' => 'required|date|after_or_equal:' . $loan->issued_on->toDateString()
                           . '|before_or_equal:' . now()->toDateString(),
            'note'        => 'nullable|string|max:255',
        ], [
            'deducted_on.after_or_equal'  => 'The payment date cannot be before the advance was issued ('
                                           . $loan->issued_on->format('M d, Y') . ').',
            'deducted_on.before_or_equal' => 'The payment date cannot be in the future.',
        ], ['amount' => 'payment']);

        // The same payment again within moments is the form sent twice — a
        // double click, or a refresh that resubmits — not a second payment.
        // Two genuine payments of the same amount on the same day are still
        // fine; they are simply not seconds apart.
        $again = LoanDeduction::where('loan_id', $loan->id)
            ->whereNull('payroll_run_id')
            ->where('amount', round((float) $data['amount'], 2))
            ->whereDate('deducted_on', $data['deducted_on'])
            ->where('created_at', '>=', now()->subSeconds(30))
            ->exists();

        if ($again) {
            return back()->with('error', 'That payment was already recorded a moment ago — it was not added twice.');
        }

        LoanDeduction::create([
            'loan_id'     => $loan->id,
            'amount'      => round((float) $data['amount'], 2),
            'deducted_on' => $data['deducted_on'],
            'note'        => filled($data['note'] ?? null) ? $data['note'] : null,
        ]);

        // A payment is a change to the advance as the office sees it, so it
        // moves to the top of the list like any other edit.
        $loan->touch();

        $loan->load(['deductions' => fn ($q) => $q->orderBy('deducted_on')->orderBy('id')]);
        $loan->syncSettlement();

        AuditLog::record('Loans', 'updated', 'Recorded ₱' . number_format($data['amount'], 2)
            . ' against ' . $loan->employee->name . "'s " . $loan->type_label
            . ' — ₱' . number_format($loan->outstanding, 2) . ' left', $loan);

        return back()->with('success', $loan->settled
            ? 'Payment recorded — ' . $loan->employee->name . "'s cash advance is now fully paid."
            : 'Payment recorded. ₱' . number_format($loan->outstanding, 2) . ' left to collect.');
    }

    /**
     * Delete a cash advance.
     *
     * Only the pay week it is deleted in gets its deduction back. That week's
     * payroll is still being worked out, so its instalment comes off and the
     * pay is whole again. A week that has already closed was paid with the
     * deduction in it, and keeps it: Payroll Records, Payroll Processing and
     * the payslip show every closed week exactly as it was.
     *
     * So the advance is kept and marked deleted rather than removed — payroll
     * works every instalment out from the advance itself, and a closed week
     * could not show a deduction worked out from a row that no longer exists.
     * It is gone from Leave & Advances, owes nothing, and counts against no
     * limit (see Loan::DELETED).
     */
    public function destroy(Loan $loan)
    {
        $this->onlyAdvances($loan);

        $loan->load(['employee', 'deductions' => fn ($q) => $q->orderBy('deducted_on')->orderBy('id')]);

        $name   = $loan->employee->name ?? 'the worker';
        $amount = '₱' . number_format($loan->principal, 2);
        $taken  = $loan->takenAround(now()->toDateString());

        DB::transaction(function () use ($loan, $name, $amount, $taken) {
            $loan->forceFill(['status' => Loan::DELETED])->save();

            AuditLog::record('Loans', 'deleted',
                "Deleted {$name}'s cash advance of {$amount} — issued " . $loan->issued_on->format('M d, Y')
                . ', ₱' . number_format($loan->installment, 2) . ' per payroll'
                . ($loan->reference ? ", ref {$loan->reference}" : '')
                . '; ₱' . number_format($taken['this_week'], 2) . " restored to this week's payroll"
                . '; closed payroll weeks keep the ₱' . number_format($taken['closed_weeks'], 2) . ' already deducted.', $loan);
        });

        return back()->with('success', $taken['this_week'] > 0
            ? "{$name}'s cash advance of {$amount} was deleted — ₱" . number_format($taken['this_week'], 2) . " back in this week's pay."
            : "{$name}'s cash advance of {$amount} was deleted. Nothing was deducted from this week's pay.");
    }

    /**
     * A loan on file is history now; nothing here changes it. Nor does
     * anything change a deleted advance: it is fixed as it was deleted.
     */
    private function onlyAdvances(Loan $loan): void
    {
        abort_unless($loan->type === Loan::ADVANCE && ! $loan->isDeleted(), 404);
    }
}
