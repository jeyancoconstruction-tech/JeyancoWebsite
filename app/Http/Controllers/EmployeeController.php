<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use App\Models\AuditLog;
use App\Models\Bonus;
use App\Models\Employee;
use App\Models\EmployeeChange;
use App\Models\Shift;
use App\Models\SystemSetting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use App\Models\Attendance;
use App\Models\LaborType;
use App\Models\Site;
use App\Notifications\EmployeeAlert;

class EmployeeController extends Controller
{
    /**
     * The Employee Directory was retired: Register & Manage lists the same
     * workers and now carries the directory's Export and Add bonus. The
     * address stays and leads there, so an old link, a bookmark or a
     * notification still lands somewhere useful.
     */
    public function index()
    {
        return redirect()->route('employees.register');
    }

    /**
     * Tell whoever is looking at the workforce what is missing from it: a
     * worker with no fingerprint cannot clock in, and one with no site is not
     * on any site's roster. Each alert is sent once, not on every visit.
     */
    private function alertMissingSetup(): void
    {
        $user           = auth()->user();
        $missingFp      = Employee::active()->whereNull('fingerprint_id')->count();
        $unassignedSite = Employee::active()->whereNull('site_id')->count();

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
    }

    /**
     * The pay period running now, and the bonuses already given in it one
     * worker at a time, so a row can offer to take one back. Loaded once for
     * the whole list rather than per row, and only for whoever may give one.
     *
     * @return array{0: \Illuminate\Support\Collection, 1: string}  [bonuses by employee id, the period as words]
     */
    private function bonusesThisPeriod(): array
    {
        [$periodOpens, $periodCloses] = self::payPeriod();

        $period  = Carbon::parse($periodOpens)->format('M d') . ' – ' . Carbon::parse($periodCloses)->format('M d, Y');
        $bonuses = auth()->user()?->isAdmin()
            ? Bonus::with('employees:id')
                ->where('all_employees', false)
                ->whereBetween('effective_on', [$periodOpens, $periodCloses])
                ->orderBy('id')
                ->get()
                ->filter(fn (Bonus $b) => $b->employees->count() === 1)
                ->groupBy(fn (Bonus $b) => $b->employees->first()->id)
            : collect();

        return [$bonuses, $period];
    }

    /**
     * The pay period running on a day — the pay week payroll groups by, which
     * opens on the day System Settings names.
     *
     * @return array{0: string, 1: string}  [opens, closes] as Y-m-d
     */
    public static function payPeriod(?string $on = null): array
    {
        $opens = Carbon::parse($on ?? now())
            ->startOfWeek((int) (SystemSetting::current()->week_starts_on ?? Carbon::MONDAY));

        return [$opens->toDateString(), $opens->copy()->addDays(6)->toDateString()];
    }

    /**
     * Give this worker a bonus for the pay period running now.
     *
     * The same record Payroll Settings has always made — a grant naming one
     * worker on one date — so payroll pays it the way it pays any other:
     * added to the net of the week the date falls in, and not taxed, a bonus
     * not being wages.
     */
    public function storeBonus(Request $request, Employee $employee)
    {
        // A pending registration is not on the payroll at all, so nothing
        // would ever pay this out.
        abort_if($employee->isPending(), 404);

        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01|max:1000000',
            'note'   => 'nullable|string|max:160',
        ], [
            'amount.min' => 'A bonus of nothing is not a bonus.',
        ]);

        [$opens, $closes] = self::payPeriod();

        $bonus = Bonus::create([
            'amount'        => $data['amount'],
            'effective_on'  => now()->toDateString(),
            'all_employees' => false,
            'note'          => $data['note'] ?? null,
            'created_by'    => auth()->user()->name ?? auth()->user()->username ?? 'admin',
        ]);

        $bonus->employees()->sync([$employee->id]);

        $period = Carbon::parse($opens)->format('M d') . ' – ' . Carbon::parse($closes)->format('M d, Y');
        $amount = '₱' . number_format((float) $data['amount'], 2);

        AuditLog::record('Payroll', 'created',
            "Bonus of {$amount} for {$employee->name}, pay period {$period}"
            . (filled($data['note'] ?? null) ? ' — ' . $data['note'] : '') . '.', $bonus);

        return back()->with('success', "{$amount} bonus added to {$employee->name}'s pay for {$period}.");
    }

    /**
     * Take back a bonus given from this page, while the period it was given in
     * is still running.
     *
     * Only a grant that names this worker and nobody else, and only inside the
     * pay period running now: a week that has closed was paid with it, and is
     * left as it was — the same rule deleting a cash advance follows.
     */
    public function destroyBonus(Employee $employee, Bonus $bonus)
    {
        [$opens, $closes] = self::payPeriod();
        $named            = $bonus->employees()->pluck('employees.id')->all();

        abort_if(
            $bonus->all_employees
            || $named !== [$employee->id]
            || $bonus->effective_on->toDateString() < $opens
            || $bonus->effective_on->toDateString() > $closes,
            404
        );

        $amount = '₱' . number_format((float) $bonus->amount, 2);
        $bonus->employees()->detach();
        $bonus->delete();

        AuditLog::record('Payroll', 'deleted',
            "Removed the {$amount} bonus for {$employee->name}, given " . $bonus->effective_on->format('M d, Y') . '.', $bonus);

        return back()->with('success', "{$amount} bonus for {$employee->name} was removed.");
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
                     'Rate per Hour', 'Vale Balance', 'Fingerprint'];

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

    /**
     * Register Employee was a page of its own. It is a step-by-step pop-up
     * on the Employees page now (employees/_register_wizard.blade.php), so
     * this address leads there with the pop-up open — a bookmark, the search
     * and an old link all still land on the form they were after.
     */
    public function create()
    {
        return redirect()->route('employees.register', ['register' => 1]);
    }

    public function store(Request $request)
    {
        $request->validate(array_merge($this->identityRules($request), [
            'site_id'        => $request->boolean('profile_form') ? 'required|exists:sites,id' : 'nullable|exists:sites,id',
            'shift_id'       => 'nullable|exists:shifts,id',
            'fingerprint_id' => ['nullable', 'string', Rule::unique('employees', 'fingerprint_id')->whereNull('deleted_at')],
            'photo'          => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ], self::OTHER_SITE_RULES, $this->profileRules($request), $this->employmentRules()), [
            'fingerprint_id.unique' => 'This Fingerprint ID is already registered.',
            'first_name.required_without' => 'The first name is required.',
            'last_name.required_with'     => 'The last name is required.',
        ] + self::EMPLOYMENT_MESSAGES);

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
            throw \Illuminate\Validation\ValidationException::withMessages([
                'fingerprint_id' => Employee::fingerprintConflictMessage($holder, $fingerprintId),
            ]);
        }

        $photoPath = null;
        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('employees', 'public');
        }

        $employee = Employee::create(Employee::withoutMissingColumns(array_merge([
            'position'       => $laborType?->name ?: $request->input('job_title'),
            'employment_type' => Employee::EMPLOYMENT_DAILY,
            'rate_per_hour'  => $request->rate_per_hour ?: 0,
            'labor_type_id'  => $request->labor_type_id ?: null,
            'shift_id'       => $request->shift_id ?: Shift::defaultForNewHire(),
            'site_id'        => $request->site_id ?: null,
            'fingerprint_id' => $fingerprintId,
            'photo'          => $photoPath,
            // Pending until the kiosk enrols a finger. An admin filling in a
            // fingerprint by hand has done that enrolment themselves, so that
            // case activates immediately.
            'status'         => $fingerprintId ? Employee::STATUS_ACTIVE : Employee::STATUS_PENDING,
        ], $this->profileData($request, $laborType), $identity, $this->employmentData($request))));

        $employee->syncOtherSites($this->otherSiteIds($request));

        EmployeeAlert::fire(auth()->user(), 'new_employee',
            'New Employee Registered',
            $employee->isPending()
                ? $employee->name . ' is registered and waiting for fingerprint enrolment at the kiosk.'
                : $employee->name . ' has been added to the system.'
        );

        // The step-by-step pop-up on Employees posts this in the background
        // and stays where it is: it is told who was registered, and says so
        // on its last step. The same save, answered in the way it was asked.
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'id'      => $employee->id,
                'number'  => '#' . str_pad((string) $employee->id, 4, '0', STR_PAD_LEFT),
                'name'    => $employee->name,
                'labor'   => $laborType?->name,
                'site'    => $employee->site?->name,
                // Whether a kiosk stands at their site to enrol the finger on.
                'kiosk'   => $employee->site_id !== null && \App\Models\Kiosk::where('site_id', $employee->site_id)->exists(),
                'pending' => $employee->isPending(),
                // For a contractual worker, the last day of the contract.
                'contract_ends' => $employee->isContractual() ? $employee->end_of_contract?->format('M d, Y') : null,
            ]);
        }

        // Back to where the Register Employee button was pressed, on the tab
        // the new worker just landed in. A query parameter rather than a
        // fragment: a fragment never reaches the server, so the page had to
        // render on Active and let JavaScript move it afterwards.
        return $employee->isPending()
            ? redirect()->route('employees.register', ['tab' => 'pending'])
                ->with('success', $employee->name . ' is registered. They will become active once their fingerprint is enrolled at the kiosk.')
            : redirect()->route('employees.register', ['tab' => 'active'])
                ->with('success', $employee->name . ' has been registered and activated.');
    }

    public function edit($id)
    {
        $employee   = Employee::findOrFail($id);
        $laborTypes = LaborType::all();
        $sites      = Site::orderBy('name')->get();
        $shifts     = Shift::orderBy('id')->get();

        return view('employees.edit', compact('employee', 'laborTypes', 'sites', 'shifts'));
    }

    public function update(Request $request, $id)
    {
        $employee = Employee::findOrFail($id);

        $request->validate(array_merge($this->identityRules($request), [
            'site_id'        => $request->boolean('profile_form') ? 'required|exists:sites,id' : 'nullable|exists:sites,id',
            'shift_id'       => 'nullable|exists:shifts,id',
            'fingerprint_id' => ['nullable', 'string', Rule::unique('employees', 'fingerprint_id')->ignore($id)->whereNull('deleted_at')],
        ], self::OTHER_SITE_RULES, $this->profileRules($request), $this->employmentRules()), [
            'fingerprint_id.unique' => 'This Fingerprint ID is already registered.',
            'first_name.required_without' => 'The first name is required.',
            'last_name.required_with'     => 'The last name is required.',
        ] + self::EMPLOYMENT_MESSAGES);

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
            'rate_per_hour'  => $request->rate_per_hour ?: $employee->rate_per_hour,
            'labor_type_id'  => $request->labor_type_id ?: $employee->labor_type_id,
            'shift_id'       => $request->has('shift_id') ? ($request->shift_id ?: null) : $employee->shift_id,
            'site_id'        => $request->site_id ?: null,
            'fingerprint_id' => $fingerprintId,
        ], $this->profileData($request, $laborType), $identity, $this->employmentData($request));
        $updateData = Employee::withoutMissingColumns($updateData);

        if ($request->hasFile('photo')) {
            if ($employee->photo) {
                Storage::disk('public')->delete($employee->photo);
            }
            $updateData['photo'] = $request->file('photo')->store('employees', 'public');
        }

        $employee->update($updateData);
        $employee->syncOtherSites($this->otherSiteIds($request));

        // A worker still pending is edited from the Pending tab; land back on it.
        return redirect()->route('employees.register', $employee->isPending() ? ['tab' => 'pending'] : [])
            ->with('success', 'Employee updated successfully!');
    }

    // ── Other sites ───────────────────────────────────────────────────────────

    /** The sites a worker also works at, besides the main one (employees/_other_sites). */
    private const OTHER_SITE_RULES = [
        'other_site_ids'   => 'nullable|array',
        'other_site_ids.*' => 'nullable|integer|exists:sites,id',
    ];

    /**
     * The other sites a form sent, or null when it sent none at all. The
     * picker always posts a blank first entry, so ticking nothing still
     * arrives (as "none") and is told apart from a form without the picker.
     */
    private function otherSiteIds(Request $request): ?array
    {
        return $request->has('other_site_ids') ? (array) $request->input('other_site_ids', []) : null;
    }

    // ── Worker profile (Register Employee form) ───────────────────────────────

    /**
     * Rules shared by the forms that can post name parts.
     *
     * Two forms reach store()/update(): the full page, which posts
     * first/middle/last, and the quick-edit modal on Register & Manage, which
     * posts a single `name`. Either is acceptable, neither is required on its
     * own — hence required_without on both sides.
     *
     * Every worker is paid by the day off a labor type, so both forms must
     * name one and the rate that goes with it.
     */
    private function identityRules(Request $request): array
    {
        return [
            'name'        => 'required_without:first_name|nullable|string|max:255',
            'first_name'  => 'required_without:name|nullable|string|max:100',
            // Optional since 2026-09-30 (Michael): not everybody has one.
            'middle_name' => 'nullable|string|max:100',
            'last_name'   => 'required_with:first_name|nullable|string|max:100',
            'name_suffix' => ['nullable', Rule::in(Employee::SUFFIXES)],

            'labor_type_id' => 'required|exists:labor_types,id',
            'rate_per_hour' => 'required|numeric|min:0.01',
        ];
    }

    // ── How they were hired ───────────────────────────────────────────────────

    /** What the office is told when a contract is not filled in properly. */
    private const EMPLOYMENT_MESSAGES = [
        'date_hired.required_if'      => 'A contract runs from the date hired, so it is needed.',
        'contract_months.required_if' => 'Say how many months the contract runs.',
        'contract_months.integer'     => 'The contract length is a whole number of months.',
        'contract_months.min'         => 'A contract runs 1 to 36 months.',
        'contract_months.max'         => 'A contract runs 1 to 36 months.',
    ];

    /**
     * Regular or contractual, and for a contract, how many months.
     *
     * Nullable, because only Register employee and Edit Employee ask: the
     * quick-edit modal and the kiosk say nothing about it, and a save from
     * either leaves it as it stands.
     */
    private function employmentRules(): array
    {
        return [
            'employment_type' => ['nullable', Rule::in([Employee::EMPLOYMENT_REGULAR, Employee::EMPLOYMENT_CONTRACTUAL])],
            'contract_months' => ['nullable', 'required_if:employment_type,' . Employee::EMPLOYMENT_CONTRACTUAL,
                                  'integer', 'min:1', 'max:' . Employee::CONTRACT_MONTHS_MAX],
        ];
    }

    /**
     * The employment type and the day the contract ends, ready to save — or
     * nothing, when the request did not say.
     *
     * The end is worked out here from the date hired and the months, never
     * typed: it is the day after which the worker leaves the Active list
     * (Employee::endFinishedContracts()), so it has to be the same sum every
     * time. A contract that would already be over is refused, because saving
     * it would take the worker off the list on the next request.
     */
    private function employmentData(Request $request): array
    {
        if (! $request->filled('employment_type')) {
            return [];
        }

        if ($request->input('employment_type') !== Employee::EMPLOYMENT_CONTRACTUAL) {
            return ['employment_type' => Employee::EMPLOYMENT_REGULAR, 'end_of_contract' => null];
        }

        $ends = Employee::contractEnd((string) $request->input('date_hired'), (int) $request->input('contract_months'));

        if ($ends->toDateString() < now()->toDateString()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'contract_months' => 'That contract would already be over: it would have ended on ' . $ends->format('M d, Y')
                    . '. Give it more months, or check the date hired.',
            ]);
        }

        return ['employment_type' => Employee::EMPLOYMENT_CONTRACTUAL, 'end_of_contract' => $ends->toDateString()];
    }

    /**
     * Name and name parts.
     *
     * `name` stays the single value the rest of the app reads, so it is
     * composed from the parts whenever they are posted and left untouched
     * when they are not.
     */
    private function identityData(Request $request, ?Employee $employee = null): array
    {
        $data = [];

        if ($request->filled('first_name')) {
            $data['first_name']  = trim($request->input('first_name'));
            $data['middle_name'] = trim((string) $request->input('middle_name')) ?: null;
            $data['last_name']   = trim((string) $request->input('last_name')) ?: null;
            // The quick-edit modal posts no suffix, and must not drop one.
            $data['name_suffix'] = $request->has('name_suffix')
                ? ($request->input('name_suffix') ?: null)
                : $employee?->name_suffix;
            $data['name']        = Employee::composeName(
                $data['first_name'], $data['middle_name'], $data['last_name'], $data['name_suffix']
            );
        } elseif ($request->filled('name')) {
            $data['name'] = trim($request->input('name'));
        }

        return $data;
    }

    /**
     * Validation for the resume-style half of the registration form.
     *
     * Only Employment & Pay is required (Michael, 2026-09-30). A post carrying
     * `profile_form` came from Register Employee or Edit Employee, the two full
     * forms, and on those the position must be filled in with the rest of that
     * section (not the middle name or the date hired, since 2026-09-30). Nothing else may be held to that: the
     * quick-edit modal on Register & Manage posts five pay fields, and the
     * kiosk's complete endpoint posts what it read off a finger.
     *
     * Personal Information, Address, Contact Information and Government IDs
     * may all be left blank and filled in later. Until 2026-09-30 the full
     * forms required the first three, which held up a registration over a
     * birthday or a barangay nobody had to hand on the day.
     *
     * Religion and the repeatable education / work history / skills / notes
     * lists have no inputs on the form and are optional too.
     */
    private function profileRules(Request $request): array
    {
        // The position: required on the two full forms only.
        $need = $request->boolean('profile_form') ? 'required' : 'nullable';

        return [
            // Personal — optional, see above.
            'birth_date'   => 'nullable|date|before:today',
            'birth_place'  => 'nullable|string|max:180',
            'gender'       => ['nullable', Rule::in(Employee::GENDERS)],
            'civil_status' => ['nullable', Rule::in(Employee::CIVIL_STATUSES)],
            'nationality'  => 'nullable|string|max:60',
            'religion'     => 'nullable|string|max:60',
            'blood_type'   => 'nullable|string|max:5',

            // Contact — optional.
            'phone'                      => 'nullable|string|max:30',
            'email'                      => 'nullable|email|max:150',
            'emergency_contact_name'     => 'nullable|string|max:150',
            'emergency_contact_relation' => 'nullable|string|max:60',
            'emergency_contact_phone'    => 'nullable|string|max:30',

            // Address — optional.
            'address_street'   => 'nullable|string|max:200',
            'address_barangay' => 'nullable|string|max:120',
            'address_city'     => 'nullable|string|max:120',
            'address_province' => 'nullable|string|max:120',
            'address_postal'   => 'nullable|string|max:20',

            // Government IDs — optional.
            'sss_number'        => 'nullable|string|max:40',
            'philhealth_number' => 'nullable|string|max:40',
            'pagibig_number'    => 'nullable|string|max:40',
            'tin_number'        => 'nullable|string|max:40',

            // Job — the position is part of Employment & Pay, so required on
            // the full forms. The date hired is optional (2026-09-30).
            'job_title'  => "$need|string|max:150",
            // …unless they are on contract: it is what the contract runs from.
            'date_hired' => ['nullable', 'required_if:employment_type,' . Employee::EMPLOYMENT_CONTRACTUAL, 'date'],

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
    private function profileData(Request $request, ?LaborType $laborType = null): array
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

        // Last word on Position: it is the worker's labor type.
        // `position`, the column payroll actually reads, is derived from the
        // labor type on save whatever the Position box said, so leaving the two
        // free to disagree only ever produced a record that contradicted
        // itself. The form fills that box from the labor type and locks it;
        // this is the same rule applied again on arrival, so a post that went
        // around the browser cannot store a job title the labor type denies.
        // Held only against the full forms, and only when a labor type came
        // with them — the quick-edit modal has no Position field to keep in
        // step, and must not have one invented for it.
        if ($laborType && $request->boolean('profile_form')) {
            $data['job_title'] = $laborType->name;
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
        // shift: the worker card on an Active row names it. otherSites: the
        // Site column, its filter and the bulk dialog read every site.
        $with = Employee::hasOtherSites() ? ['laborType', 'site', 'kiosk', 'shift', 'otherSites'] : ['laborType', 'site', 'kiosk', 'shift'];

        $pending  = Employee::pending()->with($with)->withCount('attendances')
                        ->orderByDesc('created_at')->get();
        // The worker card counts each Active worker's fingers: load them here,
        // not one query per row.
        $active   = Employee::active()
                        ->with(Employee::hasExtraFingers() ? array_merge($with, ['extraFingerprints']) : $with)
                        ->withCount('attendances')
                        ->orderBy('name')->get();
        $archived = Employee::archived()->with($with)->withCount('attendances')
                        ->orderByDesc('archived_at')->get();
        $removed  = Employee::removed()->with($with)->withCount('attendances')
                        ->orderByDesc('deleted_at')->get();

        $laborTypes        = LaborType::orderBy('name')->get();
        $sites             = Site::orderBy('name')->get();
        $shifts            = Shift::orderBy('id')->get();
        $nextFingerprintId = $this->nextFingerprintId();

        $this->alertMissingSetup();
        [$bonuses, $bonusPeriod] = $this->bonusesThisPeriod();

        // Changes dated ahead from the bar under a selection, for the tag on
        // each worker's row; and what that bar's dialogs offer.
        $scheduled = EmployeeChange::waitingByEmployee();
        $bulk      = $this->bulkOptions($sites, $shifts, $laborTypes);

        return view('register', compact(
            'pending', 'active', 'archived', 'removed',
            'laborTypes', 'sites', 'shifts', 'nextFingerprintId',
            'bonuses', 'bonusPeriod', 'scheduled', 'bulk'
        ));
    }

    /**
     * What the bulk dialogs on Employees choose from, as the page's script
     * reads it: the real sites, shifts and labor types with their rates, the
     * pay period running now and the one after, and where each action posts.
     */
    private function bulkOptions($sites, $shifts, $laborTypes): array
    {
        $kiosks = \App\Models\Kiosk::query()->whereNotNull('site_id')
            ->selectRaw('site_id, COUNT(*) as n')->groupBy('site_id')->pluck('n', 'site_id');

        $time = fn (?string $t) => $t ? Carbon::parse($t)->format('g:i A') : null;

        [$opens, $closes]         = self::payPeriod();
        [$nextOpens, $nextCloses] = self::payPeriod(Carbon::parse($opens)->addDays(7)->toDateString());
        $words = fn (string $a, string $b) => Carbon::parse($a)->format('M d') . ' – ' . Carbon::parse($b)->format('M d, Y');

        return [
            'sites' => $sites->map(fn (Site $s) => [
                'id'     => $s->id,
                'name'   => $s->name,
                'where'  => $s->location,
                'kiosks' => (int) ($kiosks[$s->id] ?? 0),
            ])->values()->all(),
            'shifts' => $shifts->map(fn (Shift $s) => [
                'id'    => $s->id,
                'name'  => $s->name,
                'night' => (bool) $s->crosses_midnight,
                'from'  => $time($s->starts_at),
                'to'    => $time($s->pm_ends_at),
            ])->values()->all(),
            'labor' => $laborTypes->map(fn (LaborType $t) => [
                'id'   => $t->id,
                'name' => $t->name,
                'rate' => round((float) $t->daily_rate / 8, 2),
            ])->values()->all(),
            'periods' => [
                ['key' => 'current', 'label' => $words($opens, $closes)],
                ['key' => 'next',    'label' => $words($nextOpens, $nextCloses)],
            ],
            // The hours that carry the night differential, as payroll counts them.
            'night'    => Carbon::createFromTime(\App\Support\WorkSchedule::NIGHT_FROM_HOUR)->format('g:i A')
                        . ' to ' . Carbon::createFromTime(\App\Support\WorkSchedule::NIGHT_TO_HOUR)->format('g:i A'),
            'today'    => EmployeeChange::earliestDate(),
            'mayBonus' => (bool) auth()->user()?->isAdmin(),
            // The day from which only the workers allowed take overtime; null
            // while nobody has been allowed yet and everybody still does.
            'overtimeRule' => \App\Support\OvertimePermission::load()->startsOn(),
            'urls'     => [
                'site'  => route('employees.bulk-site'),
                'sites' => route('employees.bulk-other-site'),
                'shift' => route('employees.bulk-shift'),
                'labor' => route('employees.bulk-labor'),
                'overtime' => route('employees.bulk-overtime'),
                'bonus' => route('employees.bulk-bonus'),
                'undo'  => route('employees.bulk-undo'),
            ],
        ];
    }

    /**
     * How many workers are in each list, as JSON. The sidebar's Pending badge
     * asks for it on every page when the workforce changes (layouts.blade.php).
     *
     * It used to carry the Pending rows as well, for the Employees page to
     * swap in. That page's three lists are live regions now and re-read
     * themselves (register.blade.php), so the counts are all that is left.
     */
    public function registerLive()
    {
        return response()->json([
            'counts' => [
                'pending'  => Employee::pending()->count(),
                'active'   => Employee::active()->count(),
                'archived' => Employee::archived()->count(),
                'removed'  => Employee::removed()->count(),
            ],
        ])->header('Cache-Control', 'no-store');
    }

    /**
     * Complete a kiosk-detected (pending) worker's profile and activate them.
     * The fingerprint ID and kiosk trace captured at scan time are kept.
     */
    public function complete(Request $request, $id)
    {
        $employee = Employee::findOrFail($id);

        $request->validate([
            // The quick-edit modal posts the name in three parts, the same way
            // Register Employee does. `name` stays accepted on its own so an
            // older client — or anything else posting here — keeps working.
            'name'           => 'required_without:first_name|nullable|string|max:255',
            'first_name'     => 'required_without:name|nullable|string|max:100',
            'middle_name'    => 'nullable|string|max:100',
            'last_name'      => 'required_with:first_name|nullable|string|max:100',
            'rate_per_hour'  => 'required|numeric|min:0.01',
            'labor_type_id'  => 'required|exists:labor_types,id',
            'site_id'        => 'nullable|exists:sites,id',
            'shift_id'       => 'nullable|exists:shifts,id',
            'fingerprint_id' => ['nullable', 'string', Rule::unique('employees', 'fingerprint_id')->ignore($id)->whereNull('deleted_at')],
            'photo'          => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ], [
            'fingerprint_id.unique'       => 'This Fingerprint ID is already registered.',
            'first_name.required_without' => 'The first name is required.',
            'last_name.required_with'     => 'The last name is required.',
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

        // Parts win when they are posted, so the name column and the three
        // columns cannot disagree; a bare `name` is still taken as-is.
        $named = $request->filled('first_name')
            ? [
                'first_name'  => trim($request->input('first_name')),
                'middle_name' => trim((string) $request->input('middle_name')) ?: null,
                'last_name'   => trim((string) $request->input('last_name')) ?: null,
            ]
            : [];
        $named['name'] = $named
            ? Employee::composeName($named['first_name'], $named['middle_name'], $named['last_name'], $employee->name_suffix)
            : trim((string) $request->input('name'));

        $data = $named + [
            'position'       => $laborType->name,
            'rate_per_hour'  => $request->rate_per_hour,
            'labor_type_id'  => $request->labor_type_id,
            'site_id'        => $request->site_id ?: null,
            // Nobody joins the workforce untagged: an unassigned worker shows
            // as nothing on the settings card and falls back to the office
            // default at payroll time, where no one can see them.
            'shift_id'       => $request->shift_id ?: ($employee->shift_id ?: Shift::defaultForNewHire()),
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

        // Back to Pending, where the office was working through the list, even
        // when this worker has just gone active (Michael, 2026-09-30): the
        // toast says where they went.
        return redirect()->route('employees.register', ['tab' => 'pending'])
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
        $employee = Employee::removed()->findOrFail($id);
        $ended    = $this->bringBack($employee);

        return back()->with('success', $ended
            ? "{$employee->name} was restored as a regular employee. Their contract ended on {$ended}; set a new one from Edit if they are back on contract."
            : $employee->name . ' was restored.');
    }

    /**
     * Restore a removed worker, and keep them restored.
     *
     * A contract that has run out is what took a contractual worker off the
     * Active list, and it would take them off again on the very next look.
     * Whoever restores them has decided they are staying, so they come back
     * as a regular worker — on the list until somebody removes them — and a
     * new contract, if there is one, is set from Edit.
     *
     * @return string|null  the day the dropped contract ended, when one was dropped
     */
    private function bringBack(Employee $employee): ?string
    {
        $ended = $employee->contractIsOver() ? $employee->end_of_contract->format('M d, Y') : null;

        $employee->restore();

        if ($ended) {
            $employee->update(Employee::withoutMissingColumns([
                'employment_type' => Employee::EMPLOYMENT_REGULAR,
                'end_of_contract' => null,
            ]));
        }

        return $ended;
    }

    /**
     * Delete a removed worker for good. Only reachable from the Removed tab.
     * The worker goes; the attendance and payroll they earned stay — see
     * Employee::deleteForGood().
     */
    public function forceDelete($id)
    {
        $employee = Employee::removed()->findOrFail($id);
        $employee->deleteForGood();

        return back()->with('success', $employee->name . ' was deleted for good. Their attendance and payroll records are kept.');
    }

    /**
     * Restore several removed workers at once.
     *
     * Scoped to removed() exactly like the single-row restore, so an id that
     * is not actually in the Removed tab is ignored rather than acted on.
     */
    public function bulkRestore(Request $request)
    {
        $request->validate([
            'ids'   => 'required|array|min:1',
            'ids.*' => 'required|integer',
        ]);

        $employees = Employee::removed()->whereIn('id', $request->ids)->get();
        foreach ($employees as $employee) {
            $this->bringBack($employee);
        }

        return response()->json(['success' => true, 'restored' => $employees->count()]);
    }

    /**
     * Delete several removed workers for good at once.
     *
     * Same step as forceDelete() for each row, and the same removed() guard,
     * so nothing that is still live can be deleted through this path. Their
     * attendance and payroll stay.
     */
    public function bulkForceDelete(Request $request)
    {
        $request->validate([
            'ids'   => 'required|array|min:1',
            'ids.*' => 'required|integer',
        ]);

        $employees = Employee::removed()->whereIn('id', $request->ids)->get();
        foreach ($employees as $employee) {
            $employee->deleteForGood();
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
     * bugs where '9' > '10' lexicographically. Stand-in fingerprints (9001
     * and up) are not real sensor slots, so they do not count.
     */
    private function nextFingerprintId(): int
    {
        $max = Employee::withTrashed()->whereNotNull('fingerprint_id')
            ->whereRaw('CAST(fingerprint_id AS UNSIGNED) < ?', [Employee::FAKE_FINGERPRINT_FROM])
            ->selectRaw('MAX(CAST(fingerprint_id AS UNSIGNED)) as max_id')
            ->value('max_id');

        return ($max === null) ? 1 : (int) $max + 1;
    }
}
