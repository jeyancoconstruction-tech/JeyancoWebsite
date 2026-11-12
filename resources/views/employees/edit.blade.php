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
        $slots   = method_exists($employee, 'fingerprintSlots') ? $employee->fingerprintSlots() : array_filter([(string) $employee->fingerprint_id]);
        $fingers = count($slots);
        $needFp  = \App\Models\Employee::fingersTarget();
        $fpAsked = $employee->extraFingerRequested();
        // How complete the record is: the profile fields with an answer.
        $profileFields = ['birth_date', 'birth_place', 'gender', 'civil_status', 'nationality', 'blood_type',
                          'phone', 'email', 'emergency_contact_name', 'emergency_contact_relation', 'emergency_contact_phone',
                          'address_street', 'address_barangay', 'address_city', 'address_province', 'address_postal',
                          'sss_number', 'philhealth_number', 'pagibig_number', 'tin_number'];
        $profileDone = collect($profileFields)->filter(fn ($f) => filled($employee->{$f}))->count();
        $statusLabel = ['active' => __('Active'), 'pending' => __('Pending'), 'archived' => __('Archived'), 'deleted' => __('Deleted')][$employee->status] ?? ucfirst((string) $employee->status);
    @endphp

    <a class="eea-back" href="{{ route('employees.register', $employee->isPending() ? ['tab' => 'pending'] : []) }}"><i class="fas fa-arrow-left"></i> {{ __('Back to Employees') }}</a>

    <form action="{{ route('employees.update', $employee->id) }}" method="POST" class="eea" id="rgxForm" data-mode="edit" novalidate>
        @csrf
        @method('PUT')

        {{-- Who is being edited, and the figures payroll uses. The name
             follows the name fields as they are typed (_register_progress). --}}
        {{-- One line of who, then the pay figures. What the form below
             already shows (shift, employment, date hired) is not repeated. --}}
        <header class="eea-hero">
            <span class="eea-ico">@include('partials.worker-icon', ['size' => '64%'])</span>
            <div class="eea-id">
                <h1 id="rgxWhoName">{{ $employee->name }}</h1>
                <div class="eea-sub">
                    <span>#{{ str_pad($employee->id, 4, '0', STR_PAD_LEFT) }}</span>
                    <span>{{ $employee->position ?: ($lt->name ?? __('Worker')) }}</span>
                    @if($employee->site)<span>{{ $employee->site->name }}</span>@endif
                    <span class="eea-st st-{{ $employee->status }}"><i></i>{{ $statusLabel }}</span>
                </div>
            </div>
            <dl class="eea-stats">
                @if($lt)
                    <div><dt>{{ __('Daily rate') }}</dt><dd>{{ $lt->getFormattedDailyRate() }}</dd></div>
                    <div><dt>{{ __('Hourly') }}</dt><dd>{{ $lt->getFormattedHourlyRate() }}</dd></div>
                @endif
                <div title="{{ __('Enrolled at the kiosk.') }}"><dt>{{ __('Fingerprints') }}</dt><dd class="{{ $fingers === 0 || $fpAsked ? 'warn' : '' }}">{{ $fingers }} <small>{{ __('of') }} {{ $needFp }}</small></dd></div>
            </dl>
        </header>

        <div class="eea-card">
            <aside class="eea-rail">
                {{-- The sections: one per section below, drawn by the script at
                     the end of the page from the sections' own titles. --}}
                <nav class="eea-tabs" id="eeaTabs" role="tablist" aria-label="{{ __('Employee sections') }}"></nav>
                <div class="eea-railbox">
                    <div class="eea-row"><span>{{ __('Profile') }}</span><b>{{ $profileDone }} {{ __('of') }} {{ count($profileFields) }}</b></div>
                    <div class="eea-meter"><i style="width: {{ round($profileDone / count($profileFields) * 100) }}%"></i></div>
                    <div class="eea-row"><span>{{ __('Fingerprints') }}</span>
                        <b>{{ $fingers }} {{ __('of') }} {{ $needFp }}</b></div>
                    @if($fingers)<div class="eea-row eea-slots"><span>{{ $fingers === 1 ? __('Slot') : __('Slots') }} {{ implode(', ', $slots) }}</span></div>@endif
                    {{-- One finger is enough to work. Another is asked of the
                         kiosk from here; the buttons post the small forms after
                         the main one (a form cannot sit inside a form). --}}
                    @if($fingers === 0)
                        <div class="eea-note">{{ __('First finger: at the kiosk, under Enroll.') }}</div>
                    @elseif($fingers < $needFp && $fpAsked)
                        <div class="eea-fp">
                            <span class="eea-note">{{ __('Waiting at the kiosk, under Enroll.') }}</span>
                            <button type="submit" form="eeaFpCancel" class="eea-link">{{ __('Cancel') }}</button>
                        </div>
                    @elseif($fingers < $needFp)
                        <button type="submit" form="eeaFpAsk" class="eea-btn eea-fp-add"><i class="fas fa-fingerprint"></i>{{ $fingers === 1 ? __('Add second fingerprint') : __('Add another fingerprint') }}</button>
                    @endif
                </div>
            </aside>

            <div class="eea-panel">
            <div class="eea-body" id="eeaBody">
                <section class="ep-section eea-emp">
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

                    {{-- Three groups, each a full row: the name, the role and its
                         rate, and where / when they work. --}}
                    <p class="eea-grp">{{ __('Name') }}</p>
                    <div class="row g-3">
                        {{-- Name, in parts. `name` is composed from these on save. --}}
                        <div class="col-md-4">
                            <label class="ep-label" for="first_name">{{ __('First Name') }} <span class="ep-req">*</span></label>
                            <input type="text" id="first_name" name="first_name" value="{{ $first }}"
                                   class="form-control @error('first_name') is-invalid @enderror" required>
                            @error('first_name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="ep-label" for="middle_name">{{ __('Middle Name') }}</label>
                            <input type="text" id="middle_name" name="middle_name" value="{{ $middle }}"
                                   class="form-control @error('middle_name') is-invalid @enderror">
                            @error('middle_name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
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
                    </div>

                    <p class="eea-grp">{{ __('Role & rate') }}</p>
                    <div class="row g-3">
                        {{-- Labor Type comes before Position because it answers it:
                             the position IS the labor type, and `position` — the
                             column payroll reads — is derived from it on save
                             either way. --}}
                        <div class="col-md-4">
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

                        <div class="col-md-4">
                            <label class="ep-label" for="rate_per_hour">{{ __('Rate Per Hour') }} <span class="ep-req">*</span></label>
                            <input type="number" step="0.01" id="rate_per_hour" name="rate_per_hour"
                                   value="{{ $employee->rate_per_hour }}"
                                   class="form-control" style="cursor:not-allowed;" readonly required>
                            <span class="ep-hint">{{ __('Calculated from Labor Type (Daily ÷ 8).') }}</span>
                        </div>

                        {{-- Filled from the labor type and locked
                             (_position_from_labor.blade.php). --}}
                        <div class="col-md-4">
                            <label class="ep-label" for="job_title">{{ __('Position / Job Title') }} <span class="ep-req">*</span></label>
                            <input type="text" id="job_title" name="job_title" required
                                   value="{{ old('job_title', $employee->job_title) }}"
                                   class="form-control @error('job_title') is-invalid @enderror"
                                   placeholder="{{ __('e.g. Mason') }}">
                            @error('job_title')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            <span class="ep-hint js-position-hint"></span>
                        </div>
                    </div>

                    <p class="eea-grp">{{ __('Assignment') }}</p>
                    <div class="row g-3">
                        {{-- Site Assignment --}}
                        <div class="col-md-6 col-lg-3">
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

                        <div class="col-md-6 col-lg-3">
                            <label class="ep-label" for="date_hired">{{ __('Date Hired') }}</label>
                            <input type="date" id="date_hired" name="date_hired"
                                   value="{{ old('date_hired', $employee->date_hired?->format('Y-m-d')) }}"
                                   class="form-control @error('date_hired') is-invalid @enderror">
                            @error('date_hired')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
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
                        <div class="col-md-6 col-lg-3" id="contract_months_field" @if($empType !== $contractual) hidden @endif>
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

                        {{-- Other sites they also work at, besides the main one. --}}
                        <div class="col-12">
                            <span class="ep-label" id="eeaOtherSitesLabel">{{ __('Other Sites') }}</span>
                            @include('employees._other_sites', [
                                'sites'   => $sites,
                                'chosen'  => old('other_site_ids', \App\Models\Employee::hasOtherSites() ? $employee->otherSiteList()->pluck('id')->all() : []),
                                'main'    => 'site_select',
                                'labelId' => 'eeaOtherSitesLabel',
                            ])
                            <span class="ep-hint">{{ __('Optional. They can scan at these sites too. The site above stays their main site for payroll and reports.') }}</span>
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
                {{-- One status: when it was last saved, or — once something
                     is changed — how many changes and which fields. The
                     required count is still kept (the progress script writes
                     it) but not shown: the tabs flag a required field. --}}
                <div class="eea-status" aria-live="polite">
                    <span class="eea-saved">{{ __('Saved') }} {{ $employee->updated_at?->format('M j, Y · g:i A') }}</span>
                    <span class="rgx-dirty" id="rgxDirty">{{ __('No changes yet') }}</span><span class="eea-what" id="eeaWhat"></span>
                    <span class="rgx-count" hidden><b id="rgxDone">0</b><b id="rgxTotal">0</b></span>
                </div>
                <a href="{{ route('employees.register', $employee->isPending() ? ['tab' => 'pending'] : []) }}" class="eea-btn">{{ __('Cancel') }}</a>
                <button type="submit" class="eea-btn pri" id="eeaSave">{{ __('Save changes') }}</button>
            </footer>
            </div>
        </div>
    </form>

    {{-- Another finger, asked of the kiosk (the buttons in the rail). --}}
    <form id="eeaFpAsk" method="POST" action="{{ route('employees.finger-request', $employee->id) }}" hidden>@csrf</form>
    <form id="eeaFpCancel" method="POST" action="{{ route('employees.finger-request.cancel', $employee->id) }}" hidden>@csrf @method('DELETE')</form>
</div>

{{-- In the head, not the body: a stylesheet the parser only reaches near
     the end of the page paints the sections unstyled first. --}}
@push('styles')
    @include('employees._profile_styles')
    @include('employees._register_layout')
<style>
/* ── Edit employee (2026-11-12 revision, "calm"): one line of who and the
   pay figures, the sections as a quiet list with one mark each, the open
   section with its groups named at the left, and one status in the footer.
   Navy is the one accent (the worker tile, the open tab, Save), matching the
   worker card on Employees. Theme tokens for surfaces and text. ─────────── */
.eea-page { --ea-navy: #1f3a5f; --ea-navy-ink: #1f3a5f; --ea-ico: #f2ede4; --ea-green: #2f7a4f; --ea-amber: #b7791f; --ea-red: var(--danger, #d1483b);
            --ea-line: var(--border, #e9e3da); --ea-field: var(--border-md, #ded7cc);
            display: flex; flex-direction: column; gap: 12px; }
[data-bs-theme="dark"] .eea-page { --ea-navy: #2c5282; --ea-navy-ink: #8fb3df; --ea-green: #6cc08e; --ea-amber: #e3b467; }
.eea-back { align-self: flex-start; font-size: 13px; font-weight: 500; color: var(--text-muted); text-decoration: none; display: inline-flex; gap: 7px; align-items: center; }
.eea-back:hover { color: var(--text-primary); }
.eea { display: flex; flex-direction: column; gap: 20px; }

/* Who, and the pay. */
.eea-hero { display: flex; align-items: center; gap: 16px; }
.eea-ico { flex: none; width: 54px; height: 54px; border-radius: 12px; display: grid; place-items: center; background: var(--ea-navy); color: var(--ea-ico); }
.eea-id { min-width: 0; flex: 1; }
html[data-bs-theme] .eea-hero .eea-id h1 { margin: 0 !important; font-size: 23px !important; font-weight: 600; letter-spacing: -.015em; color: var(--text-primary) !important; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.eea-sub { display: flex; flex-wrap: wrap; align-items: center; color: var(--text-muted); font-size: 13px; margin-top: 4px; }
.eea-sub > span + span::before { content: '/'; margin: 0 9px; opacity: .45; color: var(--text-muted); font-weight: 400; }
.eea-st { display: inline-flex; align-items: center; gap: 6px; font-weight: 600; color: var(--text-secondary); }
.eea-st i { width: 7px; height: 7px; border-radius: 50%; background: currentColor; }
.eea-st.st-active { color: var(--ea-green); }
.eea-st.st-pending { color: var(--ea-amber); }
.eea-stats { display: flex; margin: 0; flex: none; }
.eea-stats > div { padding: 0 0 0 24px; margin-left: 24px; border-left: 1px solid var(--ea-line); }
.eea-stats > div:first-child { padding: 0; margin: 0; border: 0; }
.eea-stats dt { font-size: 11.5px; font-weight: 400; color: var(--text-muted); }
.eea-stats dd { margin: 2px 0 0; font-size: 20px; font-weight: 600; letter-spacing: -.01em; font-variant-numeric: tabular-nums; white-space: nowrap; color: var(--text-primary); }
.eea-stats dd small { font-size: 12px; font-weight: 500; color: var(--text-muted); }
.eea-stats dd.warn { color: var(--ea-amber); }

/* The sections at the left, the open one filling the rest. */
.eea-card { display: grid; grid-template-columns: 220px minmax(0, 1fr); gap: 28px; min-height: calc(100vh - 250px); }
.eea-rail { display: flex; flex-direction: column; }
.eea-tabs { display: flex; flex-direction: column; gap: 2px; }
.eea-tabs button { background: none; border: 0; border-radius: 8px; padding: 10px 12px; font-weight: 500; font-size: 13.5px; color: var(--text-muted);
                   display: flex; align-items: center; gap: 10px; cursor: pointer; text-align: left; }
.eea-tabs button > .fas, .eea-tabs button > .far { width: 16px; text-align: center; font-size: 13px; opacity: .8; }
.eea-tabs button:hover { color: var(--text-primary); }
.eea-tabs button.on { background: var(--surface); color: var(--text-primary); font-weight: 600; box-shadow: 0 0 0 1px var(--ea-line); }
.eea-tabs button.on > .fas, .eea-tabs button.on > .far { color: var(--ea-navy-ink); opacity: 1; }
.eea-tabs .cnt { margin-left: auto; display: inline-flex; align-items: center; font-size: 11px; }
.eea-tabs .dotm { width: 7px; height: 7px; border-radius: 50%; display: inline-block; }
.eea-tabs .cnt.gap  .dotm { background: var(--ea-amber); }
.eea-tabs .cnt.chg  .dotm { background: var(--ea-navy-ink); }
.eea-tabs .cnt.need .dotm { background: var(--ea-red); }
.eea-tabs .cnt.ok { color: var(--ea-green); }

.eea-railbox { margin-top: 18px; padding: 16px 12px 0; border-top: 1px solid var(--ea-line); font-size: 12.5px; }
.eea-row { display: flex; justify-content: space-between; align-items: baseline; gap: 8px; color: var(--text-muted); font-variant-numeric: tabular-nums; }
.eea-row b { color: var(--text-primary); font-weight: 600; }
.eea-meter { height: 4px; margin: 9px 0 18px; border-radius: 2px; overflow: hidden; background: var(--ea-line); }
.eea-meter i { display: block; height: 100%; background: var(--ea-navy-ink); }
.eea-note { margin-top: 6px; font-size: 12px; color: var(--ea-amber); }
.eea-slots { margin-top: 4px; font-size: 12px; }
.eea-fp { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-top: 10px; }
.eea-fp .eea-note { margin: 0; }
.eea-btn.eea-fp-add { width: 100%; justify-content: center; gap: 8px; height: 34px; margin-top: 12px; font-size: 12.5px; }
.eea-link { padding: 0; border: 0; background: none; font-size: 12.5px; font-weight: 600; color: var(--ea-navy-ink); cursor: pointer; }
.eea-link:hover { text-decoration: underline; }

.eea-panel { display: flex; flex-direction: column; min-width: 0; background: var(--surface); border: 1px solid var(--ea-line); border-radius: 12px; }
.eea .eea-body { flex: 1; display: flex; flex-direction: column; }

/* The open section: its name and why across the top. */
.eea .ep-section { flex: 1; display: flex; flex-direction: column; margin: 0; padding: 0 28px 22px;
                   background: none !important; border: 0 !important; box-shadow: none !important; border-radius: 0 !important; }
.eea .ep-section[hidden] { display: none !important; }
.eea .ep-section-head { display: block !important; margin: 0 0 4px !important; padding: 22px 0 8px !important; border: 0 !important; }
.eea .ep-section-icon, .eea .rgx-chip { display: none !important; }
html[data-bs-theme] .eea .ep-section-title { font-size: 17px; font-weight: 600; letter-spacing: -.01em; margin: 0; color: var(--text-primary); }
.eea .ep-section-sub { display: block !important; font-size: 12.5px; color: var(--text-muted); margin: 4px 0 0; line-height: 1.5; }
.eea .ep-section > .row { margin-top: 0; }
.eea .ep-section > .row + .row { margin-top: 14px; }
.eea .ep-label { font-size: 12px !important; font-weight: 400 !important; text-transform: none !important; letter-spacing: 0 !important; color: var(--text-muted) !important; margin-bottom: 6px; }
.eea .ep-req { color: var(--ea-amber) !important; }
.eea .form-control, .eea .form-select { min-height: 40px; border-radius: 8px; font-size: 13.5px; border-color: var(--ea-field); }
.eea .form-control:focus, .eea .form-select:focus { border-color: var(--ea-navy-ink); box-shadow: 0 0 0 3px color-mix(in srgb, var(--ea-navy-ink) 16%, transparent); }
.eea .form-control[readonly] { background: transparent; }
.eea .ep-hint { font-size: 11.5px; color: var(--text-muted); opacity: .85; }
.eea .ep-subhead { margin: 22px 0 12px !important; padding: 16px 0 0; border: 0; border-top: 1px solid var(--ea-line);
                   font-size: 12.5px; font-weight: 600; color: var(--text-primary); }

/* Employment & Pay: each group named at the left, its fields at the right. */
.eea .ep-section.eea-emp { display: grid; grid-template-columns: 174px minmax(0, 1fr); column-gap: 0; align-content: start; }
.eea .eea-emp > .ep-section-head { grid-column: 1 / -1; }
.eea .eea-emp > .eea-grp { margin: 0 !important; padding: 20px 0; border-top: 1px solid var(--ea-line); }
.eea .eea-emp > .row { margin-top: 0 !important; margin-bottom: 0 !important; padding: 4px 0 20px; border-top: 1px solid var(--ea-line); }
.eea .eea-emp > .eea-grp { padding-top: 22px; padding-right: 24px; font-size: 12.5px; font-weight: 600; color: var(--text-primary); }
.eea .eea-emp > .ep-section-head + .eea-grp, .eea .eea-emp > .ep-section-head + .eea-grp + .row { border-top: 0; }
.eea .eea-emp > .row + .row { margin-top: 0 !important; }
.eea .eea-emp .ep-suffix-group .ep-suffix { flex: 0 0 96px; }

/* The other sections' fields: each row shared out across the full width. */
.eea .ep-section:not(.eea-emp) > .row { display: grid; grid-auto-flow: column; grid-auto-columns: minmax(0, 1fr); gap: 14px 18px; margin: 0; }
.eea .ep-section:not(.eea-emp) > .row > * { width: auto; max-width: none; padding: 0; margin: 0; }
.eea .ep-section:not(.eea-emp) > .row:first-of-type { margin-top: 14px; }

/* One status, then Cancel and Save. */
.eea-foot { display: flex; align-items: center; gap: 10px; padding: 14px 28px; border-top: 1px solid var(--ea-line); background: var(--surface);
            border-radius: 0 0 12px 12px; position: sticky; bottom: 0; z-index: 5; }
.eea-status { flex: 1; min-width: 0; font-size: 12.5px; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.eea-status .rgx-dirty { display: none; font-weight: 600; color: var(--text-primary); }
.eea-status .rgx-dirty.is-on { display: inline; }
.eea-status:has(.rgx-dirty.is-on) .eea-saved { display: none; }
.eea-status .eea-what:empty { display: none; }
.eea-btn { height: 38px; padding: 0 18px; border-radius: 8px; border: 1px solid var(--ea-field); background: transparent; color: var(--text-primary);
           font-weight: 600; font-size: 13px; display: inline-flex; align-items: center; text-decoration: none; cursor: pointer; }
.eea-btn:hover { background: var(--bg-subtle); color: var(--text-primary); }
.eea-btn.pri { background: var(--ea-navy); border-color: var(--ea-navy); color: #fff; }
.eea-btn.pri:hover { filter: brightness(1.12); color: #fff; }
.eea-foot:not(:has(.rgx-dirty.is-on)) .eea-btn.pri { opacity: .5; }

/* Room at the right of the footer for the chat button, so it never sits on Save. */
.eea-foot { padding-right: 92px; }
.eea-status .rgx-dirty.is-on { background: none; padding: 0; border: 0; border-radius: 0; color: var(--text-primary); font-size: inherit; }

@media (max-width: 1100px) {
    .eea-hero { flex-wrap: wrap; } .eea-stats { width: 100%; }
    .eea .ep-section:not(.eea-emp) > .row { grid-auto-flow: row; grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .eea .ep-section.eea-emp { grid-template-columns: 1fr; }
    .eea .eea-emp > .eea-grp { padding: 18px 0 0; }
    .eea .eea-emp > .eea-grp + .row { border-top: 0; padding-top: 12px; }
}
@media (max-width: 900px) {
    .eea-card { grid-template-columns: 1fr; min-height: 0; gap: 16px; }
    .eea-tabs { flex-direction: row; overflow-x: auto; }
    .eea-tabs button { flex: none; }
    .eea-railbox { display: none; }
}
@media (max-width: 640px) { .eea-saved { display: none; } .eea-foot { flex-wrap: wrap; } .eea .ep-section:not(.eea-emp) > .row { grid-template-columns: 1fr; } }
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
        // The section's own icon, its name, and how many of its fields are filled.
        const icon = sec.querySelector('.ep-section-icon i');
        b.innerHTML = (icon ? `<i class="${icon.className}"></i>` : '') + '<span></span><span class="cnt"></span>';
        b.querySelector('span').textContent = title.trim().replace(/ Information$/, '');
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

            const fields = Array.from(sec.querySelectorAll('input, select, textarea'))
                .filter(f => f.type !== 'hidden' && !f.disabled && !f.readOnly && f.name)
                // Optional extras that are usually blank do not make a section incomplete.
                .filter(f => f.id !== 'name_suffix' && !f.name.startsWith('other_site'));
            const filled = fields.filter(f => String(f.value || '').trim() !== '').length;
            // One mark per tab, the most urgent: red, a required field is
            // empty or wrong; navy, changed and not saved; amber, something
            // optional still blank; a check, all of it recorded.
            const done = fields.length > 0 && filled === fields.length;
            const cls = bad || empty ? 'need' : changed ? 'chg' : done ? 'ok' : 'gap';
            const cnt = btns[i].querySelector('.cnt');
            if (cnt) {
                cnt.className = 'cnt ' + cls;
                cnt.innerHTML = cls === 'ok' ? '<i class="fas fa-check"></i>' : '<i class="dotm"></i>';
                cnt.title = cls === 'need' ? 'A required field is empty' : cls === 'chg' ? 'Changed, not saved yet'
                          : cls === 'gap' ? `${filled} of ${fields.length} recorded` : 'All recorded';
            }
        });
        // Which fields have changed, beside the count in the footer.
        const what = document.getElementById('eeaWhat');
        if (what) {
            const names = Array.from(document.querySelectorAll('#eeaBody .rgx-changed .ep-label'))
                .map(l => l.textContent.replace('*', '').trim().toLowerCase()).filter(Boolean);
            what.textContent = names.length ? ' · ' + names.slice(0, 3).join(', ') + (names.length > 3 ? ` +${names.length - 3}` : '') : '';
        }
    }

    const firstBad = secs.findIndex(s => s.querySelector('.is-invalid, .invalid-feedback.d-block'));
    let start = 0;
    try { start = Number(sessionStorage.getItem(KEY)) || 0; } catch (e) {}
    show(firstBad >= 0 ? firstBad : Math.min(start, secs.length - 1));

    const form = document.getElementById('rgxForm');
    // After the progress script has marked what changed.
    form.addEventListener('input', () => setTimeout(marks, 20));
    form.addEventListener('change', () => setTimeout(marks, 20));
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
