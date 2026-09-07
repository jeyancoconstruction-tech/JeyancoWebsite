<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\OvertimeRequest;
use App\Models\Site;
use Illuminate\Http\Request;

/**
 * Leave and overtime, on one screen with two tabs — the same shape the
 * Attendance page already uses.
 *
 * Neither writes attendance. Approved rows are read by Payroll Processing.
 */
class LeaveOvertimeController extends Controller
{
    public function index(Request $request)
    {
        $tab = $request->get('tab') === 'overtime' ? 'overtime' : 'leave';

        $leave = LeaveRequest::with(['employee', 'approver'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('type'), fn ($q) => $q->where('leave_type', $request->type))
            ->when($request->filled('q'), fn ($q) => $q->whereHas('employee',
                fn ($e) => $e->where('name', 'like', '%' . $request->q . '%')))
            ->when($request->filled('from'), fn ($q) => $q->where('ends_on', '>=', $request->from))
            ->when($request->filled('to'), fn ($q) => $q->where('starts_on', '<=', $request->to))
            ->latest('starts_on')
            ->paginate(15, ['*'], 'leave_page')
            ->withQueryString();

        $overtime = OvertimeRequest::with(['employee', 'site', 'approver'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('site_id'), fn ($q) => $q->where('site_id', $request->site_id))
            ->when($request->filled('q'), fn ($q) => $q->whereHas('employee',
                fn ($e) => $e->where('name', 'like', '%' . $request->q . '%')))
            ->when($request->filled('from'), fn ($q) => $q->where('date', '>=', $request->from))
            ->when($request->filled('to'), fn ($q) => $q->where('date', '<=', $request->to))
            ->latest('date')
            ->paginate(15, ['*'], 'ot_page')
            ->withQueryString();

        return view('leave.index', [
            'tab'       => $tab,
            'leave'     => $leave,
            'overtime'  => $overtime,
            'employees' => Employee::orderBy('name')->get(['id', 'name', 'rate_per_hour']),
            'sites'     => Site::orderBy('name')->get(['id', 'name']),
            'counts'    => [
                'leave_pending' => LeaveRequest::where('status', 'pending')->count(),
                'ot_pending'    => OvertimeRequest::where('status', 'pending')->count(),
            ],
        ]);
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

    public function storeOvertime(Request $request)
    {
        $data = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'site_id'     => 'nullable|exists:sites,id',
            'date'        => 'required|date',
            'starts_at'   => 'nullable|date_format:H:i',
            'ends_at'     => 'nullable|date_format:H:i',
            'hours'       => 'nullable|numeric|min:0|max:24',
            'multiplier'  => 'nullable|numeric|min:1|max:5',
            'reason'      => 'nullable|string|max:1000',
        ]);

        $employee = Employee::findOrFail($data['employee_id']);

        $data['hours'] = $data['hours']
            ?: OvertimeRequest::hoursBetween($data['starts_at'] ?? null, $data['ends_at'] ?? null);

        // Frozen on the row: what the worker earns per overtime hour today
        // must not be restated by a rate change tomorrow.
        $data['hourly_rate'] = (float) $employee->rate_per_hour;
        $data['multiplier']  = $data['multiplier'] ?? 1.25;
        $data['amount']      = round($data['hours'] * $data['hourly_rate'] * $data['multiplier'], 2);
        $data['status']      = 'pending';
        $data['filed_by']    = auth()->id();

        $ot = OvertimeRequest::create($data);

        AuditLog::record('Overtime', 'created',
            'Filed ' . $ot->hours . 'h overtime for ' . $employee->name, $ot);

        return back()->with('success', 'Overtime filed and awaiting approval.');
    }

    /** Approve or reject either kind. One route, because the decision is one act. */
    public function decide(Request $request, string $kind, int $id)
    {
        $data = $request->validate([
            'decision' => 'required|in:approved,rejected,cancelled',
            'note'     => 'nullable|string|max:500',
        ]);

        $model = $kind === 'overtime'
            ? OvertimeRequest::findOrFail($id)
            : LeaveRequest::findOrFail($id);

        $model->update([
            'status'        => $data['decision'],
            'approved_by'   => auth()->id(),
            'approved_at'   => now(),
            'decision_note' => $data['note'] ?? null,
        ]);

        AuditLog::record(
            $kind === 'overtime' ? 'Overtime' : 'Leave',
            $data['decision'],
            ucfirst($data['decision']) . ' for ' . ($model->employee->name ?? 'employee'),
            $model
        );

        return back()->with('success', 'Request ' . $data['decision'] . '.');
    }
}

