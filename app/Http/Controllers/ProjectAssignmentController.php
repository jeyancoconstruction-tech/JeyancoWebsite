<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\ProjectAssignment;
use App\Models\Site;
use Illuminate\Http\Request;

/**
 * Postings of workers to sites.
 *
 * employees.site_id still says where someone belongs now, and the kiosk still
 * stamps each clock with the site it was taken at. Creating an assignment
 * optionally moves the worker's current site to match, because that is what
 * the office means by assigning someone — but it is a checkbox, not a rule.
 */
class ProjectAssignmentController extends Controller
{
    public function index(Request $request)
    {
        $assignments = ProjectAssignment::with(['employee', 'site'])
            ->when($request->filled('site_id'), fn ($q) => $q->where('site_id', $request->site_id))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('q'), fn ($q) => $q->whereHas('employee',
                fn ($e) => $e->where('name', 'like', '%' . $request->q . '%')))
            ->orderByDesc('starts_on')
            ->paginate(15)
            ->withQueryString();

        // The headline the module exists for: who is on each site right now.
        $bySite = Site::withCount(['employees'])
            ->orderBy('name')
            ->get()
            ->map(function ($site) {
                $site->active_assignments = ProjectAssignment::active()
                    ->where('site_id', $site->id)->count();
                return $site;
            });

        return view('assignments.index', [
            'assignments' => $assignments,
            'bySite'      => $bySite,
            'sites'       => Site::orderBy('name')->get(['id', 'name']),
            'employees'   => Employee::registered()->with('laborType')->orderBy('name')
                                ->get(['id', 'name', 'position', 'rate_per_hour', 'employment_type']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_id'  => 'required|exists:employees,id',
            'site_id'      => 'required|exists:sites,id',
            'position'     => 'nullable|string|max:100',
            'rate'         => 'required|numeric|min:0',
            'rate_type'    => 'required|in:daily,hourly',
            'starts_on'    => 'required|date',
            'ends_on'      => 'nullable|date|after_or_equal:starts_on',
            'notes'        => 'nullable|string|max:1000',
            'move_employee' => 'nullable|boolean',
        ]);

        $employee = Employee::findOrFail($data['employee_id']);

        $assignment = ProjectAssignment::create([
            'employee_id'     => $employee->id,
            'site_id'         => $data['site_id'],
            'position'        => $data['position'] ?: $employee->position,
            'rate'            => $data['rate'],
            'rate_type'       => $data['rate_type'],
            'starts_on'       => $data['starts_on'],
            'ends_on'         => $data['ends_on'] ?? null,
            'employment_type' => $employee->employment_type,
            'status'          => 'active',
            'notes'           => $data['notes'] ?? null,
            'created_by'      => auth()->id(),
        ]);

        // Only the site moves, and only when asked. Rate, labor type, shift and
        // everything else payroll reads are left alone.
        if ($request->boolean('move_employee')) {
            $employee->update(['site_id' => $data['site_id']]);
        }

        AuditLog::record('Assignments', 'created',
            'Assigned ' . $employee->name . ' to ' . $assignment->site->name, $assignment);

        return back()->with('success', 'Assignment created.');
    }

    public function end(Request $request, ProjectAssignment $assignment)
    {
        $data = $request->validate([
            'ends_on' => 'nullable|date',
            'status'  => 'required|in:completed,cancelled',
        ]);

        $assignment->update([
            'ends_on' => $data['ends_on'] ?? now()->toDateString(),
            'status'  => $data['status'],
        ]);

        AuditLog::record('Assignments', 'updated',
            'Ended assignment of ' . ($assignment->employee->name ?? 'employee')
            . ' at ' . ($assignment->site->name ?? 'site'), $assignment);

        return back()->with('success', 'Assignment closed.');
    }
}

