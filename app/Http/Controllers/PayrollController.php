<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use Illuminate\Http\Request;

class PayrollController extends Controller
{
    /**
     * The Pay Periods page was consolidated into the unified Payroll Records
     * module. Redirect any old links there.
     */
    public function index()
    {
        return redirect()->route('payroll-records');
    }

    // ✅ Save Vale
    public function updateVale(Request $request, $id)
    {
        $attendance = Attendance::findOrFail($id);
        $attendance->vale = is_numeric($request->vale) ? $request->vale : 0;
        $attendance->save();

        return response()->json(['success' => true]);
    }

    // ✅ Save Deductions
    public function updateDeductions(Request $request, $id)
    {
        $attendance = Attendance::findOrFail($id);
        $attendance->deductions = is_numeric($request->deductions) ? $request->deductions : 0;
        $attendance->save();

        return response()->json(['success' => true]);
    }
}
