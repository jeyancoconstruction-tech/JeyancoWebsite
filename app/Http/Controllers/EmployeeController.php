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

        // Figures for the summary card and the tabs. Counted from the same
        // collection the table renders, so a number on screen can never
        // disagree with the rows underneath it.
        //
        // Regular and contractual are counted apart because they are PAID
        // apart: a contractual worker is settled against their contract total
        // and earns nothing through this payroll, so only their attendance is
        // tracked here. Splitting them at the directory lets the office see
        // who actually lands on a payslip without opening each record.
        $contractual = $employees->filter(fn ($e) => $e->isContractual())->count();

        $stats = [
            'total'       => $employees->count(),
            'regular'     => $employees->count() - $contractual,
            'contractual' => $contractual,
        ];

        return view('employees.index', compact('employees', 'sites', 'stats'));
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
        $request->validate(array_merge($this->identityRules($request), [
            'site_id'        => 'nullable|exists:sites,id',
            'fingerprint_id' => ['nullable', 'string', Rule::unique('employees', 'fingerprint_id')->whereNull('deleted_at')],
            'photo'          => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ], $this->profileRules()), [
            'fingerprint_id.unique' => 'This Fingerprint ID is already registered.',
            'first_name.required_without' => 'The first name is required.',
            'last_name.required_with'     => 'The last name is required.',
        ]);

        $identity  = $this->identityData($request);
        $laborType = $request->filled('labor_type_id') ? LaborType::find($request->labor_type_id) : null;

        // No fingerprint is assigned here. A worker registered on the web is
        // not enrolled yet: the kiosk is what reads their finger and calls
        // saveFingerprint, and that is what activates them. Reserving a slot
        // now would make them look enrolled before anyone had scanned anything.
        $fingerprintId = $request->filled('fingerprint_id')
            ? (string) $request->fingerprint_id
            : null;

        // Reclaim the slot from a removed or archived worker who still owns it.
        if ($fingerprintId && $holder = Employee::releaseFingerprint($fingerprintId)) {
            return back()->withInput()->withErrors([
                'fingerprint_id' => Employee::fingerprintConflictMessage($holder, $fingerprintId),
            ]);
        }

        $photoPath = null;
        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('employees', 'public');
        }

        $employee = Employee::create(Employee::withoutMissingColumns(array_merge([
            // A contractual worker has no labor type to take a position from,
            // so their job title stands in.
            'position'       => $laborType?->name ?: ($request->input('job_title') ?: 'Contractual'),
            'employment_type' => $request->input('employment_type', Employee::EMPLOYMENT_DAILY),
            'contract_rate'  => $request->filled('contract_rate') ? (float) $request->contract_rate : null,
            'rate_per_hour'  => $request->rate_per_hour ?: 0,
            'labor_type_id'  => $request->labor_type_id ?: null,
            'site_id'        => $request->site_id ?: null,
            'fingerprint_id' => $fingerprintId,
            'photo'          => $photoPath,
            // Pending until the kiosk enrols a finger. An admin filling in a
            // fingerprint by hand has done that enrolment themselves, so that
            // case activates immediately.
            'status'         => $fingerprintId ? Employee::STATUS_ACTIVE : Employee::STATUS_PENDING,
        ], $this->profileData($request), $identity)));

        EmployeeAlert::fire(auth()->user(), 'new_employee',
            'New Employee Registered',
            $employee->isPending()
                ? $employee->name . ' is registered and waiting for fingerprint enrolment at the kiosk.'
                : $employee->name . ' has been added to the system.'
        );

        // Back to where the Register Employee button was pressed, on the tab
        // the new worker just landed in.
        return $employee->isPending()
            ? redirect()->to(route('employees.register') . '#pending')
                ->with('success', $employee->name . ' is registered. They will become active once their fingerprint is enrolled at the kiosk.')
            : redirect()->to(route('employees.register') . '#active')
                ->with('success', $employee->name . ' has been registered and activated.');
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

        $request->validate(array_merge($this->identityRules($request), [
            'site_id'        => 'nullable|exists:sites,id',
            'fingerprint_id' => ['nullable', 'string', Rule::unique('employees', 'fingerprint_id')->ignore($id)->whereNull('deleted_at')],
        ], $this->profileRules()), [
            'fingerprint_id.unique' => 'This Fingerprint ID is already registered.',
            'first_name.required_without' => 'The first name is required.',
            'last_name.required_with'     => 'The last name is required.',
        ]);

        $identity  = $this->identityData($request, $employee);
        $laborType = $request->filled('labor_type_id') ? LaborType::find($request->labor_type_id) : null;

        $fingerprintId = $request->filled('fingerprint_id')
            ? (string) $request->fingerprint_id
            : $employee->fingerprint_id;

        if ($fingerprintId && $holder = Employee::releaseFingerprint($fingerprintId, $employee->id)) {
            return back()->withInput()->withErrors([
                'fingerprint_id' => Employee::fingerprintConflictMessage($holder, $fingerprintId),
            ]);
        }

        $updateData = array_merge([
            'position'       => $laborType?->name ?: ($request->input('job_title') ?: $employee->position),
            'employment_type' => $request->input('employment_type', $employee->employment_type ?: Employee::EMPLOYMENT_DAILY),
            'contract_rate'  => $request->filled('contract_rate') ? (float) $request->contract_rate : $employee->contract_rate,
            'rate_per_hour'  => $request->rate_per_hour ?: $employee->rate_per_hour,
            'labor_type_id'  => $request->labor_type_id ?: $employee->labor_type_id,
            'site_id'        => $request->site_id ?: null,
            'fingerprint_id' => $fingerprintId,
        ], $this->profileData($request), $identity);
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

    // ── Worker profile (Register Employee form) ───────────────────────────────

    /**
     * Rules shared by the forms that can post name parts and a contract.
     *
     * Two forms reach store()/update(): the full page, which posts
     * first/middle/last, and the quick-edit modal on Register & Manage, which
     * posts a single `name`. Either is acceptable, neither is required on its
     * own — hence required_without on both sides.
     *
     * Labor type and hourly rate are required only for a worker this payroll
     * actually pays. A contractual worker has neither: they are settled
     * against a contract total, so the form hides both fields and there is
     * nothing to validate.
     */
    private function identityRules(Request $request): array
    {
        $contractual = $request->input('employment_type') === Employee::EMPLOYMENT_CONTRACTUAL;

        return [
            'name'        => 'required_without:first_name|nullable|string|max:255',
            'first_name'  => 'required_without:name|nullable|string|max:100',
            'middle_name' => 'nullable|string|max:100',
            'last_name'   => 'required_with:first_name|nullable|string|max:100',

            'labor_type_id' => $contractual
                ? 'nullable|exists:labor_types,id'
                : 'required|exists:labor_types,id',
            'rate_per_hour' => $contractual
                ? 'nullable|numeric|min:0'
                : 'required|numeric|min:0.01',

            'employment_type' => ['nullable', Rule::in(array_keys(Employee::EMPLOYMENT_TYPES))],
            'contract_rate'   => ['nullable', 'numeric', 'min:0'],
            'end_of_contract' => ['nullable', 'date'],
        ];
    }

    /**
     * Name, name parts, and the pay fields that depend on employment type.
     *
     * `name` stays the single value the rest of the app reads, so it is
     * composed from the parts whenever they are posted and left untouched
     * when they are not.
     */
    private function identityData(Request $request, ?Employee $employee = null): array
    {
        $contractual = $request->input('employment_type', $employee?->employment_type)
            === Employee::EMPLOYMENT_CONTRACTUAL;

        $data = [];

        if ($request->filled('first_name')) {
            $data['first_name']  = trim($request->input('first_name'));
            $data['middle_name'] = trim((string) $request->input('middle_name')) ?: null;
            $data['last_name']   = trim((string) $request->input('last_name')) ?: null;
            $data['name']        = Employee::composeName(
                $data['first_name'], $data['middle_name'], $data['last_name']
            );
        } elseif ($request->filled('name')) {
            $data['name'] = trim($request->input('name'));
        }

        if ($contractual) {
            // No labor type and no hourly rate: the form does not offer them,
            // and payroll does not pay this worker by the hour.
            $data['labor_type_id'] = null;
            $data['rate_per_hour'] = 0;

            // Only what this form actually submitted. The quick-edit modal on
            // Register & Manage posts neither, and must not clear a worker's
            // agreed contract as a side effect of correcting something else.
            if ($request->has('contract_rate')) {
                $data['contract_rate'] = $request->filled('contract_rate') ? (float) $request->contract_rate : null;
            }
            if ($request->has('end_of_contract')) {
                $data['end_of_contract'] = $request->input('end_of_contract') ?: null;
            }
        } elseif ($request->has('employment_type')) {
            // Switched to Regular on a form that offers the choice — the
            // contract no longer applies.
            $data['end_of_contract'] = null;
        }

        return $data;
    }

    /**
     * Validation for the resume-style half of the registration form.
     *
     * Everything here is optional on purpose. A worker the kiosk picked up from
     * a fingerprint scan has none of it, and payroll must never stall waiting
     * for a birthday or a barangay — the office completes the profile later.
     */
    private function profileRules(): array
    {
        return [
            // Personal
            'birth_date'   => 'nullable|date|before:today',
            'birth_place'  => 'nullable|string|max:180',
            'gender'       => ['nullable', Rule::in(Employee::GENDERS)],
            'civil_status' => ['nullable', Rule::in(Employee::CIVIL_STATUSES)],
            'nationality'  => 'nullable|string|max:60',
            'religion'     => 'nullable|string|max:60',
            'blood_type'   => 'nullable|string|max:5',

            // Contact
            'phone'                      => 'nullable|string|max:30',
            'email'                      => 'nullable|email|max:150',
            'emergency_contact_name'     => 'nullable|string|max:150',
            'emergency_contact_relation' => 'nullable|string|max:60',
            'emergency_contact_phone'    => 'nullable|string|max:30',

            // Address
            'address_street'   => 'nullable|string|max:200',
            'address_barangay' => 'nullable|string|max:120',
            'address_city'     => 'nullable|string|max:120',
            'address_province' => 'nullable|string|max:120',
            'address_postal'   => 'nullable|string|max:20',

            // Government IDs
            'sss_number'        => 'nullable|string|max:40',
            'philhealth_number' => 'nullable|string|max:40',
            'pagibig_number'    => 'nullable|string|max:40',
            'tin_number'        => 'nullable|string|max:40',

            // Job
            'job_title'  => 'nullable|string|max:150',
            'date_hired' => 'nullable|date',

            // Education (repeatable)
            'education'                  => 'nullable|array|max:10',
            'education.*.level'          => 'nullable|string|max:60',
            'education.*.school'         => 'nullable|string|max:180',
            'education.*.course'         => 'nullable|string|max:180',
            'education.*.year_graduated' => 'nullable|string|max:20',

            // Work experience (repeatable)
            'work_experience'            => 'nullable|array|max:10',
            'work_experience.*.company'  => 'nullable|string|max:180',
            'work_experience.*.position' => 'nullable|string|max:150',
            'work_experience.*.from'     => 'nullable|string|max:20',
            'work_experience.*.to'       => 'nullable|string|max:20',
            'work_experience.*.duties'   => 'nullable|string|max:500',

            'skills' => 'nullable|string|max:1000',
            'notes'  => 'nullable|string|max:2000',
        ];
    }

    /**
     * The profile columns present in this request, ready to save.
     *
     * Only fields the form actually submitted are returned. This matters:
     * `update()` is reached from two places — the full Edit Employee page,
     * which posts the whole profile, and the compact quick-edit modal on
     * Register & Manage, which posts only name, labor type, rate, site and
     * fingerprint. Mapping every column unconditionally would let that modal
     * blank a worker's entire profile as a side effect of correcting a rate.
     *
     * Within a form that does submit a section, an empty value is a real
     * clearing and is saved as null. The repeatable sections always post their
     * blank starter row, so rows with nothing in them are dropped — otherwise
     * an untouched form would store a worker with three empty schools. An
     * emptied list becomes null rather than [], so "no education on file"
     * reads the same whether it was never entered or later cleared.
     */
    private function profileData(Request $request): array
    {
        $data = [];

        foreach (Employee::PROFILE_FIELDS as $field) {
            if (in_array($field, ['education', 'work_experience', 'skills'], true)) {
                continue;                       // handled below
            }
            if (! $request->has($field)) {
                continue;                       // not part of this form
            }
            $value = $request->input($field);
            $data[$field] = ($value === '' || $value === null) ? null : $value;
        }

        foreach (['education', 'work_experience'] as $list) {
            if (! $request->has($list)) {
                continue;
            }

            $rows = collect($request->input($list, []))
                ->map(fn ($row) => array_map(
                    fn ($v) => is_string($v) ? trim($v) : $v,
                    is_array($row) ? $row : []
                ))
                ->reject(fn ($row) => collect($row)->filter(fn ($v) => $v !== '' && $v !== null)->isEmpty())
                ->values()
                ->all();

            $data[$list] = $rows ?: null;
        }

        // Typed as one comma-separated line, kept as a list.
        if ($request->has('skills')) {
            $skills = collect(explode(',', (string) $request->input('skills', '')))
                ->map(fn ($s) => trim($s))
                ->filter()
                ->unique()
                ->values()
                ->all();

            $data['skills'] = $skills ?: null;
        }

        return $data;
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
            // Details alone do not activate anyone. A worker the kiosk detected
            // arrives here with a finger already enrolled and so goes active;
            // one registered on the web has no finger yet and stays pending
            // until the kiosk enrols them.
            'status'         => $fingerprintId ? Employee::STATUS_ACTIVE : Employee::STATUS_PENDING,
        ];
        $data = Employee::withoutMissingColumns($data);

        if ($request->hasFile('photo')) {
            if ($employee->photo) {
                Storage::disk('public')->delete($employee->photo);
            }
            $data['photo'] = $request->file('photo')->store('employees', 'public');
        }

        $employee->update($data);

        $activated = $employee->isActive();

        EmployeeAlert::fire(auth()->user(), 'new_employee',
            'Employee Registration Completed',
            $activated
                ? $employee->name . ' is now an active employee.'
                : $employee->name . ' still needs fingerprint enrolment at the kiosk.'
        );

        return redirect()->to(route('employees.register') . ($activated ? '#active' : '#pending'))
            ->with('success', $activated
                ? $employee->name . ' has been registered and activated.'
                : $employee->name . ' has been saved. They become active once their fingerprint is enrolled at the kiosk.');
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
