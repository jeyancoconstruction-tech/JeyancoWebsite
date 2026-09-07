<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\DeductionType;
use App\Models\Loan;
use App\Services\PayrollService;
use Illuminate\Http\Request;

/**
 * The catalogue of deductions the office applies.
 *
 * The statutory figures are NOT restated here. SSS, PhilHealth, Pag-IBIG and
 * tax live in Payroll Settings and are computed by PayrollService; this screen
 * shows what they are set to and links to where they are edited. Only a row
 * whose source is 'manual' states its own number.
 */
class DeductionController extends Controller
{
    public function index(PayrollService $payroll)
    {
        return view('deductions.index', [
            'types'  => DeductionType::ordered()->get(),
            // Read-only mirror of what Payroll Settings currently holds, so
            // this page can show the live figures without owning them.
            'rates'  => $payroll->config()['rates'] ?? [],
            'ledger' => [
                'loans'    => Loan::collectible()->where('type', 'loan')->sum('balance'),
                'advances' => Loan::collectible()->where('type', 'advance')->sum('balance'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code'        => 'required|string|max:40|unique:deduction_types,code',
            'name'        => 'required|string|max:120',
            'category'    => 'required|in:statutory,loan,company,other',
            'amount'      => 'nullable|numeric|min:0',
            'percentage'  => 'nullable|numeric|min:0|max:100',
            'description' => 'nullable|string|max:500',
        ]);

        // Anything added here is the office's own; the owned kinds are seeded.
        $data['source']     = 'manual';
        $data['is_active']  = true;
        $data['sort_order'] = (int) DeductionType::max('sort_order') + 10;

        $type = DeductionType::create($data);

        AuditLog::record('Deductions', 'created', 'Added deduction ' . $type->name, $type);

        return back()->with('success', 'Deduction added.');
    }

    public function update(Request $request, DeductionType $deduction)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:120',
            'amount'      => 'nullable|numeric|min:0',
            'percentage'  => 'nullable|numeric|min:0|max:100',
            'description' => 'nullable|string|max:500',
        ]);

        // A settings- or ledger-backed row may be renamed and described, but
        // its figure belongs to its owner and is not editable from here.
        if ($deduction->source !== 'manual') {
            unset($data['amount'], $data['percentage']);
        }

        $deduction->update($data);

        AuditLog::record('Deductions', 'updated', 'Updated deduction ' . $deduction->name, $deduction);

        return back()->with('success', 'Deduction updated.');
    }

    public function toggle(DeductionType $deduction)
    {
        $deduction->update(['is_active' => ! $deduction->is_active]);

        AuditLog::record('Deductions', 'updated',
            ($deduction->is_active ? 'Enabled ' : 'Disabled ') . $deduction->name, $deduction);

        return back()->with('success', 'Deduction ' . ($deduction->is_active ? 'enabled' : 'disabled') . '.');
    }
}

