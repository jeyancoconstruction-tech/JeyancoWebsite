<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\Employee;
use App\Models\LaborType;
use App\Models\Site;
use App\Notifications\EmployeeAlert;

class EmployeeController extends Controller
{
    public function index()
    {
        // Directory shows the live workforce (active). Pending kiosk detections,
        // archived leavers and removed records live on the Register & Manage hub.
        $employees = Employee::active()->with(['laborType', 'site'])->get();
        $sites     = Site::orderBy('name')->get();

        // ── Notifications ──────────────────────────────────────────────────
        $user              = auth()->user();
        $missingFp         = Employee::active()->whereNull('fingerprint_id')->count();
        $unassignedSite    = Employee::active()->whereNull('site_id')->count();

        if ($missingFp > 0) {
            EmployeeAlert::fireOnce($user, 'missing_fingerprint',
                'Missing Fingerprint Enrollment',
                "{$missingFp} employee" . ($missingFp > 1 ? 's have' : ' has') . " no fingerprint enrolled and cannot clock in."
            );
        }

        if ($unassignedSite > 0) {
            EmployeeAlert::fireOnce($user, 'unassigned_site',
                'Unassigned Employees',
                "{$unassignedSite} employee" . ($unassignedSite > 1 ? 's are' : ' is') . " not assigned to any site."
            );
        }

        return view('employees.index', compact('employees', 'sites'));
    }

    public function create()
    {
        $laborTypes        = LaborType::all();
        $sites             = Site::orderBy('name')->get();
        $nextFingerprintId = $this->nextFingerprintId();
        return view('employees.create', compact('laborTypes', 'sites', 'nextFingerprintId'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'           => 'required|string|max:255',
            'rate_per_hour'  => 'required|numeric|min:0.01',
            'labor_type_id'  => 'required|exists:labor_types,id',
            'site_id'        => 'nullable|exists:sites,id',
            'fingerprint_id' => 'nullable|string|unique:employees,fingerprint_id',
            'photo'          => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ], [
            'fingerprint_id.unique' => 'This Fingerprint ID is already registered.',
        ]);

        $laborType = LaborType::findOrFail($request->labor_type_id);

        // Auto-assign next sequential ID when the field is left blank.
        $fingerprintId = $request->filled('fingerprint_id')
            ? $request->fingerprint_id
            : (string) $this->nextFingerprintId();

        $photoPath = null;
        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('employees', 'public');
        }

        Employee::create([
            'name'           => $request->name,
            'position'       => $laborType->name,
            'rate_per_hour'  => $request->rate_per_hour,
            'labor_type_id'  => $request->labor_type_id,
            'site_id'        => $request->site_id ?: null,
            'fingerprint_id' => $fingerprintId,
            'photo'          => $photoPath,
            'status'         => Employee::STATUS_ACTIVE,
        ]);

        EmployeeAlert::fire(auth()->user(), 'new_employee',
            'New Employee Registered',
            $request->name . ' has been added to the system.'
        );

        return redirect()->route('employees.index')
            ->with('success', 'Employee registered successfully!');
    }

    public function edit($id)
    {
        $employee   = Employee::findOrFail($id);
        $laborTypes = LaborType::all();
        $sites      = Site::orderBy('name')->get();
        return view('employees.edit', compact('employee', 'laborTypes', 'sites'));
    }

    public function update(Request $request, $id)
    {
        $employee = Employee::findOrFail($id);

        $request->validate([
            'name'           => 'required|string|max:255',
            'rate_per_hour'  => 'required|numeric|min:0.01',
            'labor_type_id'  => 'required|exists:labor_types,id',
            'site_id'        => 'nullable|exists:sites,id',
            'fingerprint_id' => 'nullable|string|unique:employees,fingerprint_id,' . $id,
        ], [
            'fingerprint_id.unique' => 'This Fingerprint ID is already registered.',
        ]);

        $laborType = LaborType::findOrFail($request->labor_type_id);

        $updateData = [
            'name'           => $request->name,
            'position'       => $laborType->name,
            'rate_per_hour'  => $request->rate_per_hour,
            'labor_type_id'  => $request->labor_type_id,
            'site_id'        => $request->site_id ?: null,
            'fingerprint_id' => $request->fingerprint_id ?? $employee->fingerprint_id,
        ];

        if ($request->hasFile('photo')) {
            if ($employee->photo) {
                Storage::disk('public')->delete($employee->photo);
            }
            $updateData['photo'] = $request->file('photo')->store('employees', 'public');
        }

        $employee->update($updateData);

        return redirect()->route('employees.index')->with('success', 'Employee updated successfully!');
    }

    /**
     * "Remove" — soft delete. Payroll/attendance history is preserved; the
     * worker simply disappears from active views and lands in the Removed tab,
     * where they can be restored.
     */
    public function destroy($id)
    {
        $employee = Employee::findOrFail($id);
        $employee->delete(); // soft delete

        if (request()->expectsJson()) {
            return response()->json(['success' => true]);
        }
        return back()->with('success', $employee->name . ' was removed. Their records are preserved and can be restored.');
    }

    // ── Register & Manage hub ─────────────────────────────────────────────────

    /**
     * The dedicated Employee Registration & Management page.
     *
     *  - Pending  : workers auto-detected by the Site A kiosk (fingerprint only).
     *  - Active   : fully registered workforce.
     *  - Archived : workers who left the company (deactivated, reversible).
     *  - Removed  : soft-deleted records (restorable).
     */
    public function register()
    {
        $with = ['laborType', 'site', 'kiosk'];

        $pending  = Employee::pending()->with($with)->withCount('attendances')
                        ->orderByDesc('created_at')->get();
        $active   = Employee::active()->with($with)->withCount('attendances')
                        ->orderBy('name')->get();
        $archived = Employee::archived()->with($with)->withCount('attendances')
                        ->orderByDesc('archived_at')->get();
        $removed  = Employee::onlyTrashed()->with($with)->withCount('attendances')
                        ->orderByDesc('deleted_at')->get();

        $laborTypes        = LaborType::orderBy('name')->get();
        $sites             = Site::orderBy('name')->get();
        $nextFingerprintId = $this->nextFingerprintId();

        $liveSignature = $this->registerSignature($pending, [
            'pending'  => $pending->count(),
            'active'   => $active->count(),
            'archived' => $archived->count(),
            'removed'  => $removed->count(),
        ]);

        return view('register', compact(
            'pending', 'active', 'archived', 'removed',
            'laborTypes', 'sites', 'nextFingerprintId', 'liveSignature'
        ));
    }

    /**
     * Lightweight JSON feed the Register & Manage page polls so kiosk-detected
     * workers appear in realtime without a manual refresh. Returns the current
     * counts, a change signature, and the freshly-rendered pending rows.
     */
    public function registerLive()
    {
        $pending = Employee::pending()->with(['laborType', 'site', 'kiosk'])
                        ->withCount('attendances')
                        ->orderByDesc('created_at')->get();

        $counts = [
            'pending'  => $pending->count(),
            'active'   => Employee::active()->count(),
            'archived' => Employee::archived()->count(),
            'removed'  => Employee::onlyTrashed()->count(),
        ];

        return response()->json([
            'signature'    => $this->registerSignature($pending, $counts),
            'counts'       => $counts,
            'pending_html' => view('employees._rows_pending', ['pending' => $pending])->render(),
        ]);
    }

    /** Stable hash of the pending set + all tab counts — changes whenever anything does. */
    private function registerSignature($pending, array $counts): string
    {
        return md5(
            $pending->map(fn ($e) => $e->id . ':' . $e->updated_at?->timestamp)->implode(',')
            . '|' . implode(',', $counts)
        );
    }

    /**
     * Complete a kiosk-detected (pending) worker's profile and activate them.
     * The fingerprint ID and kiosk trace captured at scan time are kept.
     */
    public function complete(Request $request, $id)
    {
        $employee = Employee::findOrFail($id);

        $request->validate([
            'name'           => 'required|string|max:255',
            'rate_per_hour'  => 'required|numeric|min:0.01',
            'labor_type_id'  => 'required|exists:labor_types,id',
            'site_id'        => 'nullable|exists:sites,id',
            'fingerprint_id' => 'nullable|string|unique:employees,fingerprint_id,' . $id,
            'photo'          => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ], [
            'fingerprint_id.unique' => 'This Fingerprint ID is already registered.',
        ]);

        $laborType = LaborType::findOrFail($request->labor_type_id);

        $data = [
            'name'           => $request->name,
            'position'       => $laborType->name,
            'rate_per_hour'  => $request->rate_per_hour,
            'labor_type_id'  => $request->labor_type_id,
            'site_id'        => $request->site_id ?: null,
            'fingerprint_id' => $request->filled('fingerprint_id') ? $request->fingerprint_id : $employee->fingerprint_id,
            'status'         => Employee::STATUS_ACTIVE,
        ];

        if ($request->hasFile('photo')) {
            if ($employee->photo) {
                Storage::disk('public')->delete($employee->photo);
            }
            $data['photo'] = $request->file('photo')->store('employees', 'public');
        }

        $employee->update($data);

        EmployeeAlert::fire(auth()->user(), 'new_employee',
            'Employee Registration Completed',
            $employee->name . ' is now an active employee.'
        );

        return redirect()->route('employees.register')
            ->with('success', $employee->name . ' has been registered and activated.');
    }

    /**
     * Accept a kiosk-submitted worker into the active workforce. Only pending
     * workers that already have complete details can be accepted directly;
     * bare kiosk detections must be Completed (details filled) first.
     */
    public function accept($id)
    {
        $employee = Employee::findOrFail($id);

        if (! $employee->isPending()) {
            return redirect()->route('employees.register')
                ->with('error', 'Only workers awaiting approval can be accepted.');
        }

        $incomplete = $employee->name === 'Unregistered Worker'
            || empty($employee->labor_type_id)
            || (float) $employee->rate_per_hour <= 0;

        if ($incomplete) {
            return redirect()->route('employees.register')
                ->with('error', 'Complete this worker’s details before accepting them.');
        }

        $employee->update(['status' => Employee::STATUS_ACTIVE]);

        EmployeeAlert::fire(auth()->user(), 'new_employee',
            'Employee Accepted',
            $employee->name . ' was accepted and added to the workforce.'
        );

        return redirect()->route('employees.register')
            ->with('success', $employee->name . ' has been accepted and is now active.');
    }

    /** Deactivate / archive a worker who left the company (reversible). */
    public function archive($id)
    {
        $employee = Employee::findOrFail($id);
        $employee->update([
            'status'      => Employee::STATUS_ARCHIVED,
            'archived_at' => now(),
        ]);

        return back()->with('success', $employee->name . ' was archived.');
    }

    /** Bring an archived worker back into the active workforce. */
    public function activate($id)
    {
        $employee = Employee::findOrFail($id);
        $employee->update([
            'status'      => Employee::STATUS_ACTIVE,
            'archived_at' => null,
        ]);

        return back()->with('success', $employee->name . ' was reactivated.');
    }

    /** Restore a soft-deleted (removed) worker. */
    public function restore($id)
    {
        $employee = Employee::onlyTrashed()->findOrFail($id);
        $employee->restore();

        return back()->with('success', $employee->name . ' was restored.');
    }

    /**
     * Permanently delete a removed record. Last resort — only reachable from the
     * Removed tab. Detaches attendance first so payroll math never hits a
     * dangling employee reference.
     */
    public function forceDelete($id)
    {
        $employee = Employee::onlyTrashed()->findOrFail($id);

        if ($employee->photo) {
            Storage::disk('public')->delete($employee->photo);
        }
        $employee->attendances()->delete();
        $employee->forceDelete();

        return back()->with('success', 'Record permanently deleted.');
    }

    public function deleteAll()
    {
        $employees = Employee::all();
        foreach ($employees as $employee) {
            if ($employee->photo) {
                Storage::disk('public')->delete($employee->photo);
            }
        }
        $deleted = Employee::query()->delete(); // soft delete
        return response()->json(['success' => true, 'deleted' => $deleted]);
    }

    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids'   => 'required|array|min:1',
            'ids.*' => 'required|integer|exists:employees,id',
        ]);

        $deleted = Employee::whereIn('id', $request->ids)->delete(); // soft delete

        return response()->json(['success' => true, 'deleted' => $deleted]);
    }

    /**
     * Return the next sequential fingerprint ID by finding the numeric
     * maximum of all existing IDs. Uses CAST to avoid string-ordering
     * bugs where '9' > '10' lexicographically.
     */
    private function nextFingerprintId(): int
    {
        $max = Employee::withTrashed()->whereNotNull('fingerprint_id')
            ->selectRaw('MAX(CAST(fingerprint_id AS UNSIGNED)) as max_id')
            ->value('max_id');

        return ($max === null) ? 1 : (int) $max + 1;
    }
}
