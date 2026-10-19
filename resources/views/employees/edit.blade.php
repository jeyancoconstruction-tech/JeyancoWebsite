@extends('layouts')

@section('page_title', 'Edit Employee')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/employee-list.css') }}">
@endpush

@section('content')
<div class="employee-container eea-page">
    @if($errors->any())
        <div class="alert alert-danger border-0 shadow-sm mb-3">
            <i class="fas fa-exclamation-circle me-2"></i>
            <ul class="mb-0 mt-1">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    @php
        $lt      = $employee->laborType;
        $fingers = method_exists($employee, 'fingerprintSlots') ? count($employee->fingerprintSlots()) : ($employee->fingerprint_id ? 1 : 0);
        $needFp  = (int) config('kiosk.fingers_required', 2);
        $initials = collect(preg_split('/\s+/', trim((string) $employee->name)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
        $statusLabel = ['active' => __('Active'), 'pending' => __('Pending'), 'archived' => __('Archived'), 'deleted' => __('Deleted')][$employee->status] ?? ucfirst((string) $employee->status);
    @endphp

    <a class="eea-back" href="{{ route('employees.register', $employee->isPending() ? ['tab' => 'pending'] : []) }}"><i class="fas fa-arrow-left"></i> {{ __('Back to Employees') }}</a>

    <form action="{{ route('employees.update', $employee->id) }}" method="POST" class="eea" id="rgxForm" data-mode="edit" novalidate>
        @csrf
        @method('PUT')

        {{-- Who is being edited, and the figures payroll uses. The name
             follows the name fields as they are typed (_register_progress). --}}
        <header class="eea-hero">
            <span class="eea-ini">{{ $initials ?: '?' }}</span>
            <div class="eea-id">
                <h1 id="rgxWhoName">{{ $employee->name }}</h1>
                <div class="eea-sub">#{{ str_pad($employee->id, 4, '0', STR_PAD_LEFT) }} · {{ $employee->position ?: ($lt->name ?? __('Worker')) }}@if($employee->site) · {{ $employee->site->name }}@endif</div>
                <div class="eea-chips">
                    <span class="eea-chip"><i class="st-{{ $employee->status }}"></i>{{ $statusLabel }}</span>
                    @if($employee->shift)<span class="eea-chip">{{ $employee->shift->name }} · {{ \Carbon\Carbon::parse($employee->shift->starts_at)->format('g:i A') }}</span>@endif
                    <span class="eea-chip {{ $fingers < $needFp ? 'warn' : '' }}" title="{{ __('Enrolled at the kiosk.') }}"><i class="fas fa-fingerprint"></i>{{ __('Fingerprints') }} {{ $fingers }} {{ __('of') }} {{ $needFp }}</span>
                    <span class="eea-chip">{{ $employee->isContractual() ? __('Contractual') : __('Regular') }}</span>
                </div>
            </div>
            <dl class="eea-stats">
                @if($lt)
                    <div><dt>{{ __('Daily rate') }}</dt><dd>{{ $lt->getFormattedDailyRate() }}</dd></div>
                    <div><dt>{{ __('Hourly') }}</dt><dd>{{ $lt->getFormattedHourlyRate() }}</dd></div>
                @endif
                <div><dt>{{ __('Hired') }}</dt><dd>{{ $employee->date_hired ? $employee->date_hired->format('M j, Y') : '—' }}</dd></div>
            </dl>
        </header>

        <div class="eea-card">
            {{-- The tabs: one per section below, drawn by the script at the
                 end of the page from the sections' own titles. --}}
            <nav class="eea-tabs" id="eeaTabs" role="tablist" aria-label="{{ __('Employee sections') }}"></nav>
            <div class="eea-body" id="eeaBody">
                <section class="ep-section">
                    <header class="ep-section-head">
                        <span class="ep-section-icon"><i class="fas fa-helmet-safety"></i></span>
                        <div>
                            <h3 class="ep-section-title">{{ __('Employment & Pay') }}</h3>
                            <p class="ep-section-sub">{{ __('What the worker does, where, and the rate payroll uses. Pay, shift and site apply from the next day worked.') }}</p>
                        </div>
                    </header>

                    @php
                        // Workers registered before the name was captured in
                        // pieces — and everyone the kiosk creates — have only
                        // `name`. Split it so the fields are not blank, and let
                        // the admin correct it.
                        $parts = \App\Models\Employee::splitName($employee->name);
                        $first  = old('first_name',  $employee->first_name  ?: $parts['first_name']);
                        $middle = old('middle_name', $employee->middle_name ?: $parts['middle_name']);
                        $last   = old('last_name',   $employee->last_name   ?: $parts['last_name']);
                        $suffix = old('name_suffix', $employee->name_suffix ?: $parts['name_suffix']);
                    @endphp

                    <div class="row g-3">
                        {{-- Name, in parts. `name` is composed from these on save. --}}
                        <div class="col-md-4 col-lg-3">
                            <label class="ep-label" for="first_name">{{ __('First Name') }} <span class="ep-req">*</span></label>
                            <input type="text" id="first_name" name="first_name" value="{{ $first }}"
                                   class="form-control @error('first_name') is-invalid @enderror" required>
                            @error('first_name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4 col-lg-2">
                            <label class="ep-label" for="middle_name">{{ __('Middle Name') }}</label>
                            <input type="text" id="middle_name" name="middle_name" value="{{ $middle }}"
                                   class="form-control @error('middle_name') is-invalid @enderror">
                            @error('middle_name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4 col-lg-3">
                            <label class="ep-label" for="last_name">{{ __('Last Name') }} <span class="ep-req">*</span></label>
                            <div class="input-group ep-suffix-group">
                                <input type="text" id="last_name" name="last_name" value="{{ $last }}"
                                       class="form-control @error('last_name') is-invalid @enderror" required>
                                {{-- Jr., Sr., II… kept apart from the surname and added to the end of the name. --}}
                                <select name="name_suffix" id="name_suffix" class="form-select ep-suffix @error('name_suffix') is-invalid @enderror"
                                        aria-label="{{ __('Suffix') }}" title="{{ __('Suffix: Jr., Sr., II…') }}">
                                    <option value="">{{ __('Suffix') }}</option>
                                    @foreach(\App\Models\Employee::SUFFIXES as $sx)<option value="{{ $sx }}" @selected($suffix === $sx)>{{ $sx }}</option>@endforeach
                                </select>
                            </div>
                            @error('last_name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            @error('name_suffix')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>

                        {{-- Labor Type comes before Position because it answers it:
                             the position IS the labor type, and `position` — the
                             column payroll reads — is derived from it on save
                             either way. --}}
                        <div class="col-md-6 col-lg-4">
                            <label class="ep-label" for="labor_type_select">{{ __('Labor Type') }} <span class="ep-req">*</span></label>
                            <select id="labor_type_select" name="labor_type_id" class="form-select" required>
                                <option value="">{{ __('— Select Labor Type —') }}</option>
                                @foreach($laborTypes as $labor)
                                    <option value="{{ $labor->id }}"
                                            data-name="{{ $labor->name }}"
                                            data-daily="{{ $labor->daily_rate }}"
                                            {{ $employee->labor_type_id == $labor->id ? 'selected' : '' }}>
                                        {{ $labor->name }}
                                    </option>
                                @endforeach
                            </select>
                            <span class="ep-hint">{{ __('Choose a Labor Type to automatically fill the Position.') }}</span>
                        </div>

                        {{-- Which crew they are on. Changing it moves them from
                             tomorrow; every day already worked keeps the shift
                             it was worked under, so last month's lateness does
                             not move with them. --}}
                        <div class="col-md-6 col-lg-3">
                            <label class="ep-label" for="shift_select">{{ __('Shift') }}</label>
                            <select id="shift_select" name="shift_id" class="form-select">
                                <option value="">{{ __('— Select —') }}</option>
                                @foreach($shifts as $sh)
                                    <option value="{{ $sh->id }}" {{ old('shift_id', $employee->shift_id) == $sh->id ? 'selected' : '' }}>
                                        {{ $sh->name }} — {{ \Carbon\Carbon::parse($sh->starts_at)->format('g:i A') }}
                                    </option>
                                @endforeach
                            </select>
                            <span class="ep-hint">{{ __('Applies from the next day worked.') }}</span>
                        </div>

                        <div class="col-md-6 col-lg-2">
                            <label class="ep-label" for="rate_per_hour">{{ __('Rate Per Hour') }} <span class="ep-req">*</span></label>
                            <input type="number" step="0.01" id="rate_per_hour" name="rate_per_hour"
                                   value="{{ $employee->rate_per_hour }}"
                                   class="form-control" style="cursor:not-allowed;" readonly required>
                            <span class="ep-hint">{{ __('Calculated from Labor Type (Daily ÷ 8).') }}</span>
                        </div>

                        {{-- Filled from the labor type and locked
                             (_position_from_labor.blade.php). --}}
                        <div class="col-md-6 col-lg-3">
                            <label class="ep-label" for="job_title">{{ __('Position / Job Title') }} <span class="ep-req">*</span></label>
                            <input type="text" id="job_title" name="job_title" required
                                   value="{{ old('job_title', $employee->job_title) }}"
                                   class="form-control @error('job_title') is-invalid @enderror"
                                   placeholder="{{ __('e.g. Mason') }}">
                            @error('job_title')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            <span class="ep-hint js-position-hint"></span>
                        </div>

                        <div class="col-md-6 col-lg-3">
                            <label class="ep-label" for="date_hired">{{ __('Date Hired') }}</label>
                            <input type="date" id="date_hired" name="date_hired"
                                   value="{{ old('date_hired', $employee->date_hired?->format('Y-m-d')) }}"
                                   class="form-control @error('date_hired') is-invalid @enderror">
                            @error('date_hired')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>

                        {{-- Site Assignment --}}
                        <div class="col-md-6 col-lg-6">
                            <label class="ep-label" for="site_select">{{ __('Site Assignment') }} <span class="ep-req">*</span></label>
                            <select name="site_id" id="site_select" required class="form-select">
                                <option value="">{{ __('— Select a site —') }}</option>
                                @foreach($sites as $site)
                                    <option value="{{ $site->id }}"
                                            {{ $employee->site_id == $site->id ? 'selected' : '' }}>
                                        {{ $site->name }}
                                    </option>
                                @endforeach
                            </select>
                            {{-- No New Site button, as on Register Employee. A site is made
                                 on the Sites page, which is the screen that owns them. The
                                 panel that used to open here carried its own name field, its
                                 own map and its own save, inside a form already asking for
                                 twenty-five other things. --}}
                        </div>

                        {{-- How they were hired, as Register employee asks on its
                             first step. Regular stays on the Active list until
                             removed; a contract runs so many months from the
                             date hired, and the worker leaves the list the day
                             after it ends. More months here is how a contract
                             is extended. The end date is worked out, not typed. --}}
                        @php
                            $contractual = \App\Models\Employee::EMPLOYMENT_CONTRACTUAL;
                            $empType     = old('employment_type', $employee->isContractual() ? $contractual : \App\Models\Employee::EMPLOYMENT_REGULAR);
                            $empMonths   = old('contract_months', $employee->contractMonths());
                        @endphp
                        <div class="col-md-6 col-lg-3">
                            <label class="ep-label" for="employment_type">{{ __('Employment Type') }}</label>
                            <select id="employment_type" name="employment_type" class="form-select @error('employment_type') is-invalid @enderror">
                                <option value="{{ \App\Models\Employee::EMPLOYMENT_REGULAR }}" @selected($empType !== $contractual)>{{ __('Regular') }}</option>
                                <option value="{{ $contractual }}" @selected($empType === $contractual)>{{ __('Contractual') }}</option>
                            </select>
                            @error('employment_type')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            <span class="ep-hint" id="employment_hint"></span>
                        </div>
                        <div class="col-md-6 col-lg-2" id="contract_months_field" @if($empType !== $contractual) hidden @endif>
                            <label class="ep-label" for="contract_months">{{ __('Contract Length') }} <span class="ep-req">*</span></label>
                            <input type="number" id="contract_months" name="contract_months" min="1" max="{{ \App\Models\Employee::CONTRACT_MONTHS_MAX }}" step="1"
                                   value="{{ $empMonths }}" placeholder="{{ __('e.g. 6') }}"
                                   class="form-control @error('contract_months') is-invalid @enderror" @if($empType !== $contractual) disabled @endif>
                            @error('contract_months')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            <span class="ep-hint">{{ __('Months, 1 to :max.', ['max' => \App\Models\Employee::CONTRACT_MONTHS_MAX]) }}</span>
                        </div>
                        <div class="col-md-6 col-lg-3" id="contract_ends_field" @if($empType !== $contractual) hidden @endif>
                            <label class="ep-label" for="contract_ends">{{ __('Contract Ends') }}</label>
                            <input type="text" id="contract_ends" class="form-control ep-locked" readonly tabindex="-1"
                                   value="{{ $employee->isContractual() ? $employee->end_of_contract?->format('M j, Y') : '' }}"
                                   placeholder="{{ __('Set the contract length') }}">
                            <span class="ep-hint">{{ __('Leaves the Active list after this date.') }}</span>
                        </div>

                        {{-- Employee ID, fingerprint and the labor type's rates are
                             shown in the strip at the top. The fingerprint still
                             goes back with the form, unchanged, as it always did. --}}
                        <input type="hidden" name="fingerprint_id" value="{{ $employee->fingerprint_id }}">
                    </div>
                </section>

                @include('employees._profile_fields', ['employee' => $employee])
            </div>

            <footer class="eea-foot">
                <span class="eea-saved">{{ __('Last saved') }} {{ $employee->updated_at?->format('M j, Y g:i A') }}</span>
                <span class="eea-sp"></span>
                {{-- What has been changed so far, counted in the page. --}}
                <div class="rgx-prog" aria-live="polite">
                    <span class="rgx-dirty" id="rgxDirty">{{ __('No changes yet') }}</span>
                    <span class="rgx-count"><b id="rgxDone">0</b> {{ __('of') }} <b id="rgxTotal">0</b> {{ __('required filled') }}</span>
                </div>
                <a href="{{ route('employees.register', $employee->isPending() ? ['tab' => 'pending'] : []) }}" class="eea-btn">{{ __('Cancel') }}</a>
                <button type="submit" class="eea-btn pri" id="eeaSave">{{ __('Save changes') }}</button>
            </footer>
        </div>
    </form>
</div>

{{-- In the head, not the body: a stylesheet the parser only reaches near
     the end of the page paints the sections unstyled first. --}}
@push('styles')
    @include('employees._profile_styles')
    @include('employees._register_layout')
<style>
/* ── Edit employee (2026-10-19): a worker header, then one section at a
   time. Theme tokens throughout; the header keeps the rail's navy in both
   themes. ─────────────────────────────────────────────────────────────── */
.eea-page { display: flex; flex-direction: column; gap: 12px; }
.eea-back { align-self: flex-start; font-size: 13px; font-weight: 600; color: var(--text-muted); text-decoration: none; display: inline-flex; gap: 7px; align-items: center; }
.eea-back:hover { color: var(--brand); }
.eea { display: flex; flex-direction: column; gap: 14px; }

.eea-hero { position: relative; overflow: hidden; display: flex; align-items: center; gap: 20px; padding: 22px 24px; border-radius: 14px; color: #fff;
            background: linear-gradient(120deg, #0f2c57 0%, #143a70 60%, #1a4a8a 100%); }
.eea-hero::after { content: ''; position: absolute; right: -70px; top: -70px; width: 260px; height: 260px; border-radius: 50%;
                   border: 1px solid rgba(255,255,255,.07); box-shadow: 0 0 0 40px rgba(255,255,255,.025), 0 0 0 80px rgba(255,255,255,.02); pointer-events: none; }
.eea-ini { flex: none; width: 64px; height: 64px; border-radius: 16px; display: grid; place-items: center; font-weight: 800; font-size: 21px;
           background: rgba(255,255,255,.12); border: 1px solid rgba(255,255,255,.22); letter-spacing: .02em; }
.eea-id { min-width: 0; flex: 1; }
html[data-bs-theme] .eea-hero .eea-id h1 { margin: 0 !important; font-size: 22px !important; font-weight: 700; letter-spacing: -.01em; color: #fff !important; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.eea-sub { color: #b9cbe6; font-size: 13px; margin-top: 2px; }
.eea-chips { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 10px; }
.eea-chip { display: inline-flex; align-items: center; gap: 6px; font-size: 11.5px; font-weight: 600; padding: 3px 10px; border-radius: 999px;
            background: rgba(255,255,255,.1); border: 1px solid rgba(255,255,255,.18); color: #fff; }
.eea-chip i:not(.fas) { width: 7px; height: 7px; border-radius: 50%; background: #9aa9bf; display: inline-block; }
.eea-chip i.st-active { background: #4ade80; } .eea-chip i.st-pending { background: #fbbf24; }
.eea-chip .fas { font-size: 10px; opacity: .85; }
.eea-chip.warn { background: rgba(251,191,36,.18); border-color: rgba(251,191,36,.45); }
.eea-stats { position: relative; z-index: 1; display: flex; margin: 0; flex: none; }
.eea-stats div { padding: 0 20px; border-left: 1px solid rgba(255,255,255,.15); }
.eea-stats div:first-child { border-left: 0; }
.eea-stats dt { font-size: 11px; color: #9fb6d8; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; }
.eea-stats dd { margin: 3px 0 0; font-size: 18px; font-weight: 700; font-variant-numeric: tabular-nums; white-space: nowrap; }

.eea-card { background: var(--surface); border: 1px solid var(--border); border-radius: 14px; }
.eea-tabs { display: flex; gap: 2px; padding: 0 14px; border-bottom: 1px solid var(--border); overflow-x: auto; }
.eea-tabs button { flex: none; background: none; border: 0; padding: 14px 12px 12px; font-weight: 600; font-size: 13.5px; color: var(--text-muted);
                   display: inline-flex; align-items: center; gap: 7px; cursor: pointer; }
.eea-tabs button:hover { color: var(--text-primary); }
.eea-tabs button.on { color: var(--brand); box-shadow: inset 0 -2px 0 var(--brand); }
.eea-tabs .need { width: 7px; height: 7px; border-radius: 50%; background: var(--danger); display: inline-block; }
.eea-tabs .chg { width: 7px; height: 7px; border-radius: 50%; background: var(--brand); display: inline-block; }

/* Each section: its name and why on the left, its fields on the right. */
.eea .eea-body { padding: 4px 24px; }
.eea .ep-section { display: grid; grid-template-columns: 230px minmax(0, 1fr); column-gap: 28px; row-gap: 0; align-content: start; margin: 0; padding: 22px 0;
                   background: none !important; border: 0 !important; box-shadow: none !important; border-radius: 0 !important; }
.eea .ep-section[hidden] { display: none !important; }
.eea .ep-section-head { display: block !important; margin: 0 !important; padding: 0 !important; border: 0 !important; grid-column: 1; grid-row: 1 / span 12; }
.eea .ep-section > :not(.ep-section-head) { grid-column: 2; }
.eea .ep-section > .row + .row, .eea .ep-section > .row + * { margin-top: 14px; }
.eea .ep-section-icon, .eea .rgx-chip { display: none !important; }
.eea .ep-section-title { font-size: 14.5px; font-weight: 700; margin: 0; color: var(--text-primary); }
.eea .ep-section-sub { display: block !important; font-size: 12.5px; color: var(--text-muted); margin: 4px 0 0; line-height: 1.5; }
.eea .ep-label { font-size: 12.5px !important; font-weight: 600 !important; text-transform: none !important; letter-spacing: 0 !important; color: var(--text-secondary) !important; margin-bottom: 5px; }
.eea .form-control, .eea .form-select { min-height: 38px; border-radius: 9px; font-size: 13.5px; }
.eea .form-control[readonly] { background: var(--bg-subtle); }
.eea .ep-hint { font-size: 12px; color: var(--text-muted); }
.eea .ep-subhead { margin: 18px 0 6px !important; font-size: 12.5px; font-weight: 700; color: var(--text-secondary); padding-top: 14px; border-top: 1px dashed var(--border); }

.eea-foot { display: flex; align-items: center; gap: 10px; padding: 14px 24px; border-top: 1px solid var(--border); background: var(--bg-subtle);
            border-radius: 0 0 14px 14px; position: sticky; bottom: 0; z-index: 5; }
.eea-saved { font-size: 12.5px; color: var(--text-muted); }
.eea-sp { flex: 1; }
.eea-btn { height: 38px; padding: 0 16px; border-radius: 9px; border: 1px solid var(--border-md); background: var(--surface); color: var(--text-secondary);
           font-weight: 600; font-size: 13.5px; display: inline-flex; align-items: center; text-decoration: none; cursor: pointer; }
.eea-btn.pri { background: var(--brand); border-color: var(--brand); color: #fff; }
.eea .rgx-count { font-size: 12.5px; }

@media (max-width: 1100px) { .eea .ep-section { grid-template-columns: 1fr; } .eea .ep-section-head { grid-row: auto; margin-bottom: 12px !important; } .eea .ep-section > :not(.ep-section-head) { grid-column: 1; } .eea-hero { flex-wrap: wrap; } .eea-stats { width: 100%; } }
@media (max-width: 640px) { .eea-saved { display: none; } .eea-foot { flex-wrap: wrap; } }
</style>
@endpush

{{-- site-location-picker.js is not loaded here any more: it existed for the
     map inside the New Site panel, which is gone. The Sites page still
     loads it, which is where a site is made. --}}
<script src="{{ asset('js/address-picker.js') }}"></script>
<script>
    // Province -> City / Municipality -> Barangay, from the PSGC tables in
    // public/psgc. If those files cannot be reached the three inputs simply
    // stay the plain text fields they were before.
    JeyancoAddress.init({
        base:     '{{ asset('psgc') }}',
        province: document.getElementById('address_province'),
        city:     document.getElementById('address_city'),
        barangay: document.getElementById('address_barangay'),
    });
</script>
<script>
(function () {
    const rateInput = document.getElementById('rate_per_hour');
    const ltSelect  = document.getElementById('labor_type_select');

    // Labor type → auto-fill rate
    ltSelect.addEventListener('change', function () {
        const opt = this.options[this.selectedIndex];
        if (opt.value) {
            rateInput.value = (parseFloat(opt.dataset.daily) / 8).toFixed(2);
        } else {
            rateInput.value = '';
        }
    });
})();
</script>

@include('employees._position_from_labor')

<script>
// ── Employment type → the contract fields ───────────────────────────────────
// A contract's length and end show only for a contractual worker. The end is
// the date hired plus the months — the same day of the month, or the last day
// of a month too short for it — worked out here so it can be seen before
// saving, and again by the server, which is what is kept.
(function () {
    const type   = document.getElementById('employment_type');
    const months = document.getElementById('contract_months');
    const hired  = document.getElementById('date_hired');
    const ends   = document.getElementById('contract_ends');
    const hint   = document.getElementById('employment_hint');
    if (!type || !months || !hired || !ends) return;

    const CONTRACTUAL = @json(\App\Models\Employee::EMPLOYMENT_CONTRACTUAL);

    function contractEnd() {
        const n = parseInt(months.value, 10);
        if (!hired.value || !(n >= 1)) return '';
        const [y, m, d] = hired.value.split('-').map(Number);
        const end  = new Date(y, m - 1 + n, 1);
        const last = new Date(end.getFullYear(), end.getMonth() + 1, 0).getDate();
        end.setDate(Math.min(d, last));
        return end.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    }

    function sync() {
        const on = type.value === CONTRACTUAL;
        document.getElementById('contract_months_field').hidden = !on;
        document.getElementById('contract_ends_field').hidden   = !on;
        // Not sent unless it applies.
        months.disabled = !on;
        months.required = on;
        ends.value = on ? contractEnd() : '';
        if (hint) hint.textContent = on ? @json(__('Hired for a set number of months.')) : @json(__('Stays on the Active list until removed.'));
    }

    type.addEventListener('change', sync);
    months.addEventListener('input', sync);
    hired.addEventListener('input', sync);
    sync();
})();
</script>

<script>
// ── One section at a time ───────────────────────────────────────────────────
// The tabs are the sections' own titles. The one with a validation error opens
// first; a red dot marks a tab with a required field left empty, a blue one a
// tab with changes. The form posts every section, whichever is showing.
(function () {
    const body = document.getElementById('eeaBody');
    const nav  = document.getElementById('eeaTabs');
    if (!body || !nav) return;
    const secs = Array.from(body.querySelectorAll('.ep-section'));
    const KEY  = 'jeyanco-eea-tab-{{ $employee->id }}';

    const btns = secs.map((sec, i) => {
        const b = document.createElement('button');
        b.type = 'button';
        b.setAttribute('role', 'tab');
        const title = (sec.querySelector('.ep-section-title') || {}).textContent || ('Section ' + (i + 1));
        b.innerHTML = '<span></span>';
        b.firstChild.textContent = title.trim().replace(/ Information$/, '');
        b.addEventListener('click', () => show(i));
        nav.appendChild(b);
        return b;
    });

    function show(i) {
        secs.forEach((s, j) => { s.hidden = j !== i; btns[j].classList.toggle('on', j === i); btns[j].setAttribute('aria-selected', j === i ? 'true' : 'false'); });
        try { sessionStorage.setItem(KEY, String(i)); } catch (e) {}
    }

    function marks() {
        secs.forEach((sec, i) => {
            const empty = Array.from(sec.querySelectorAll('input[required], select[required], textarea[required]'))
                .some(f => !f.disabled && f.type !== 'hidden' && String(f.value || '').trim() === '');
            const changed = !!sec.querySelector('.rgx-changed');
            const bad = !!sec.querySelector('.is-invalid, .invalid-feedback.d-block');
            let dot = btns[i].querySelector('i');
            const cls = bad || empty ? 'need' : (changed ? 'chg' : '');
            if (!cls) { if (dot) dot.remove(); return; }
            if (!dot) { dot = document.createElement('i'); btns[i].appendChild(dot); }
            dot.className = cls;
        });
    }

    const firstBad = secs.findIndex(s => s.querySelector('.is-invalid, .invalid-feedback.d-block'));
    let start = 0;
    try { start = Number(sessionStorage.getItem(KEY)) || 0; } catch (e) {}
    show(firstBad >= 0 ? firstBad : Math.min(start, secs.length - 1));

    const form = document.getElementById('rgxForm');
    form.addEventListener('input', () => setTimeout(marks, 0));
    form.addEventListener('change', () => setTimeout(marks, 0));
    window.addEventListener('load', () => setTimeout(marks, 50));

    // A required field left empty in another tab: open it rather than send.
    form.addEventListener('submit', e => {
        const miss = Array.from(form.querySelectorAll('input[required], select[required], textarea[required]'))
            .find(f => !f.disabled && f.type !== 'hidden' && String(f.value || '').trim() === '');
        if (!miss) return;
        e.preventDefault();
        const i = secs.findIndex(s => s.contains(miss));
        if (i >= 0) show(i);
        miss.classList.add('is-invalid');
        miss.focus();
        marks();
    });
})();
</script>

@include('employees._register_progress')

@endsection
