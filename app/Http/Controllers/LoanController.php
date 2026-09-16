<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Loan;
use App\Models\LoanDeduction;
use Illuminate\Http\Request;

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
        $data = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'principal'   => 'required|numeric|min:1',
            'installment' => 'required|numeric|min:1',
            'schedule'    => 'required|in:per_payroll,monthly',
            'issued_on'   => 'required|date',
            'starts_on'   => 'nullable|date|after_or_equal:issued_on',
            'reference'   => 'nullable|string|max:40',
            'notes'       => 'nullable|string|max:1000',
        ]);

        // An instalment larger than the advance would collect more than was
        // ever issued. Cap it so the first collection simply settles the sum.
        $data['installment'] = min($data['installment'], $data['principal']);

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
     * Payroll's own collections are written by PayrollRunService at
     * finalisation, so the two never double up.
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
            'deducted_on' => 'required|date',
            'note'        => 'nullable|string|max:255',
        ], [], ['amount' => 'payment']);

        LoanDeduction::create([
            'loan_id'     => $loan->id,
            'amount'      => $data['amount'],
            'deducted_on' => $data['deducted_on'],
            'note'        => $data['note'] ?? 'Recorded manually',
        ]);

        $loan->load(['deductions' => fn ($q) => $q->orderBy('deducted_on')->orderBy('id')]);
        $loan->syncSettlement();

        AuditLog::record('Loans', 'updated', 'Recorded ₱' . number_format($data['amount'], 2)
            . ' against ' . $loan->employee->name . "'s " . $loan->type_label
            . ' — ₱' . number_format($loan->outstanding, 2) . ' left', $loan);

        return back()->with('success', $loan->settled
            ? 'Payment recorded — ' . $loan->employee->name . "'s cash advance is now fully paid."
            : 'Payment recorded. ₱' . number_format($loan->outstanding, 2) . ' left to collect.');
    }

    /** A loan on file is history now; nothing here changes it. */
    private function onlyAdvances(Loan $loan): void
    {
        abort_unless($loan->type === Loan::ADVANCE, 404);
    }
}
