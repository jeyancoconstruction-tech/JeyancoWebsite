<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use App\Models\Attendance;
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

        // Figures for the summary cards. Counted from the same collection the
        // table renders, so a number on a card can never disagree with the
        // rows underneath it.
        $withFingerprint = $employees->filter(fn ($e) => ! empty($e->fingerprint_id))->count();
        $totalRate       = (float) $employees->sum('rate_per_hour');
        $totalVale       = (float) $employees->sum(fn ($e) => (float) ($e->vale ?? 0));

        $stats = [
            'total'            => $employees->count(),
            'total_rate'       => $totalRate,
            'total_vale'       => $totalVale,
            'avg_rate'         => $employees->count() ? $totalRate / $employees->count() : 0.0,
            'with_fingerprint' => $withFingerprint,
            'no_fingerprint'   => $employees->count() - $withFingerprint,
        ];

        // The directory lists the live workforce. Pending kiosk detections are
        // counted separately so the tab can say how many are waiting without
        // mixing them into the table.
        $pendingCount = Employee::pending()->count();

        return view('employees.index', compact('employees', 'sites', 'stats', 'pendingCount'));
    }

    /**
     * The directory as a CSV, matching the columns on screen.
     *
     * Streamed rather than built in memory, and plain CSV rather than a real
     * workbook: xlsx is a zip archive, and the deployment image has no zip
     * extension to build one with.
     */
    public function export()
    {
        $employees = Employee::active()->with(['laborType', 'site'])->orderBy('name')->get();

        $filename = 'employee-directory_' . now()->format('Y-m-d') . '.csv';
        $columns  = ['Employee ID', 'Name', 'Site', 'Position / Labor Type',
                     'Employment Type', 'Rate per Hour', 'Vale Balance', 'Fingerprint'];

        return response()->streamDownload(function () use ($employees, $columns) {
            $out = fopen('php://output', 'w');

            // Excel reads a bare UTF-8 CSV as Windows-1252 and turns the peso
            // sign into mojibake. The byte-order mark tells it otherwise.
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, ['Jeyanco Construction — Employee Directory']);
            fputcsv($out, ['Generated', now()->format('M d, Y g:i A')]);
            fputcsv($out, []);
            fputcsv($out, $columns);

            foreach ($employees as $e) {
                fputcsv($out, [
                    '#' . str_pad((string) $e->id, 4, '0', STR_PAD_LEFT),
                    $e->name,
                    $e->site->name ?? '',
                    $e->position ?: ($e->laborType->name ?? ''),
                    $e->employment_label,
                    number_format((float) $e->rate_per_hour, 2, '.', ''),
                    number_format((float) ($e->vale ?? 0), 2, '.', ''),
                    $e->fingerprint_id ? 'Enrolled (#' . $e->fingerprint_id . ')' : 'Not enrolled',
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * One worker's profile: who they are, and what this week has come to.
     *
     * The route was registered but the method never written, so the directory's
     * View button answered 500. The pay figures come from PayrollService — the
     * same call behind Payroll Records — so this page cannot quote a number the
     * payroll disagrees with.
     */
    public function show(Employee $employee, \App\Services\PayrollService $payroll)
    {
        $employee->load(['laborType', 'site', 'kiosk']);

        $start = Carbon::now()->startOfWeek(Carbon::MONDAY);
        $end   = Carbon::now()->endOfWeek(Carbon::SUNDAY);

        $totals = [];
        try {
            foreach ($payroll->computeForRange($start->toDateString(), $end->toDateString())['employees'] ?? [] as $row) {
                if ((int) ($row['employee_id'] ?? 0) === (int) $employee->id) {
                    $totals = $row['totals'] ?? [];
                    break;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Employee profile: payroll compute failed — ' . $e->getMessage());
        }

        $attendance = Attendance::where('employee_id', $employee->id)
            ->orderByDesc('date')->orderBy('session')
            ->limit(12)->get();

        return view('employees.show', [
            'employee'   => $employee,
            'totals'     => $totals,
            'period'     => $start->format('M d') . ' – ' . $end->format('M d, Y'),
            'attendance' => $attendance,
        ]);
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
            'employment_type' => ['nullable', \Illuminate\Validation\Rule::in(array_keys(Employee::EMPLOYMENT_TYPES))],
            'contract_rate'   => ['nullable', 'numeric', 'min:0'],
            'site_id'        => 'nullable|exists:sites,id',
            'fingerprint_id' => ['nullable', 'string', Rule::unique('employees', 'fingerprint_id')->whereNull('deleted_at')],
            'photo'          => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ], [
            'fingerprint_id.unique' => 'This Fingerprint ID is already registered.',
        ]);

        $laborType = LaborType::findOrFail($request->labor_type_id);

        // Auto-assign next sequential ID when the field is left blank.
        $fingerprintId = $request->filled('fingerprint_id')
            ? (string) $request->fingerprint_id
            : (string) $this->nextFingerprintId();

        // Reclaim the slot from a removed or archived worker who still owns it.
        if ($holder = Employee::releaseFingerprint($fingerprintId)) {
            return back()->withInput()->withErrors([
                'fingerprint_id' => Employee::fingerprintConflictMessage($holder, $fingerprintId),
            ]);
        }

        $photoPath = null;
        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('employees', 'public');
        }

        Employee::create(Employee::withoutMissingColumns([
            'name'           => $request->name,
            'position'       => $laborType->name,
            'employment_type' => $request->input('employment_type', Employee::EMPLOYMENT_DAILY),
            'contract_rate'  => $request->filled('contract_rate') ? (float) $request->contract_rate : null,
            'rate_per_hour'  => $request->rate_per_hour,
            'labor_type_id'  => $request->labor_type_id,
            'site_id'        => $request->site_id ?: null,
            'fingerprint_id' => $fingerprintId,
            'photo'          => $photoPath,
            'status'         => Employee::STATUS_ACTIVE,
        ]));

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
            'employment_type' => ['nullable', \Illuminate\Validation\Rule::in(array_keys(Employee::EMPLOYMENT_TYPES))],
            'contract_rate'   => ['nullable', 'numeric', 'min:0'],
            'site_id'        => 'nullable|exists:sites,id',
            'fingerprint_id' => ['nullable', 'string', Rule::unique('employees', 'fingerprint_id')->ignore($id)->whereNull('deleted_at')],
        ], [
            'fingerprint_id.unique' => 'This Fingerprint ID is already registered.',
        ]);

        $laborType = LaborType::findOrFail($request->labor_type_id);

        $fingerprintId = $request->filled('fingerprint_id')
            ? (string) $request->fingerprint_id
            : $employee->fingerprint_id;

        if ($fingerprintId && $holder = Employee::releaseFingerprint($fingerprintId, $employee->id)) {
            return back()->withInput()->withErrors([
                'fingerprint_id' => Employee::fingerprintConflictMessage($holder, $fingerprintId),
            ]);
        }

        $updateData = [
            'name'           => $request->name,
            'position'       => $laborType->name,
            'employment_type' => $request->input('employment_type', $employee->employment_type ?: Employee::EMPLOYMENT_DAILY),
            'contract_rate'  => $request->filled('contract_rate') ? (float) $request->contract_rate : $employee->contract_rate,
            'rate_per_hour'  => $request->rate_per_hour,
            'labor_type_id'  => $request->labor_type_id,
            'site_id'        => $request->site_id ?: null,
            'fingerprint_id' => $fingerprintId,
        ];
        $updateData = Employee::withoutMissingColumns($updateData);

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
            'employment_type' => ['nullable', \Illuminate\Validation\Rule::in(array_keys(Employee::EMPLOYMENT_TYPES))],
            'contract_rate'   => ['nullable', 'numeric', 'min:0'],
            'site_id'        => 'nullable|exists:sites,id',
            'fingerprint_id' => ['nullable', 'string', Rule::unique('employees', 'fingerprint_id')->ignore($id)->whereNull('deleted_at')],
            'photo'          => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ], [
            'fingerprint_id.unique' => 'This Fingerprint ID is already registered.',
        ]);

        $laborType = LaborType::findOrFail($request->labor_type_id);

        $fingerprintId = $request->filled('fingerprint_id')
            ? (string) $request->fingerprint_id
            : $employee->fingerprint_id;

        if ($fingerprintId && $holder = Employee::releaseFingerprint($fingerprintId, $employee->id)) {
            return back()->withInput()->withErrors([
                'fingerprint_id' => Employee::fingerprintConflictMessage($holder, $fingerprintId),
            ]);
        }

        $data = [
            'name'           => $request->name,
            'position'       => $laborType->name,
            'employment_type' => $request->input('employment_type', $employee->employment_type ?: Employee::EMPLOYMENT_DAILY),
            'contract_rate'  => $request->filled('contract_rate') ? (float) $request->contract_rate : $employee->contract_rate,
            'rate_per_hour'  => $request->rate_per_hour,
            'labor_type_id'  => $request->labor_type_id,
            'site_id'        => $request->site_id ?: null,
            'fingerprint_id' => $fingerprintId,
            'status'         => Employee::STATUS_ACTIVE,
        ];
        $data = Employee::withoutMissingColumns($data);

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

    /** Manually set an employee's running vale (cash-advance) balance. */
    public function updateVale(Request $request, $id)
    {
        $employee = Employee::findOrFail($id);

        $data = $request->validate([
            'vale' => 'required|numeric|min:0',
        ]);

        $employee->update(['vale' => $data['vale']]);

        return response()->json([
            'success'   => true,
            'vale'      => (float) $employee->vale,
            'formatted' => '₱' . number_format($employee->vale, 2),
        ]);
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

    /**
     * Restore several removed workers at once.
     *
     * Scoped to onlyTrashed() exactly like the single-row restore, so an id
     * that is not actually in the Removed tab is ignored rather than acted on.
     */
    public function bulkRestore(Request $request)
    {
        $request->validate([
            'ids'   => 'required|array|min:1',
            'ids.*' => 'required|integer',
        ]);

        $employees = Employee::onlyTrashed()->whereIn('id', $request->ids)->get();
        foreach ($employees as $employee) {
            $employee->restore();
        }

        return response()->json(['success' => true, 'restored' => $employees->count()]);
    }

    /**
     * Permanently delete several removed workers at once.
     *
     * Same steps as forceDelete() for each row — photo file, attendance rows,
     * then the record — and the same onlyTrashed() guard, so nothing that is
     * still live can be destroyed through this path.
     */
    public function bulkForceDelete(Request $request)
    {
        $request->validate([
            'ids'   => 'required|array|min:1',
            'ids.*' => 'required|integer',
        ]);

        $employees = Employee::onlyTrashed()->whereIn('id', $request->ids)->get();
        foreach ($employees as $employee) {
            if ($employee->photo) {
                Storage::disk('public')->delete($employee->photo);
            }
            $employee->attendances()->delete();
            $employee->forceDelete();
        }

        return response()->json(['success' => true, 'deleted' => $employees->count()]);
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
