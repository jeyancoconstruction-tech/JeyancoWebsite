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
        $needFp  = (int) config('kiosk.fingers_required', 2);
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
        <header class="eea-hero">
            <span class="eea-ico">@include('partials.worker-icon', ['size' => '66%'])</span>
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
            <aside class="eea-rail">
                {{-- The sections: one per section below, drawn by the script at
                     the end of the page from the sections' own titles. --}}
                <nav class="eea-tabs" id="eeaTabs" role="tablist" aria-label="{{ __('Employee sections') }}"></nav>
                <div class="eea-railbox">
                    <div>
                        <div class="eea-row"><span class="eea-k">{{ __('Profile') }}</span><span>{{ $profileDone }} {{ __('of') }} {{ count($profileFields) }} {{ __('recorded') }}</span></div>
                        <div class="eea-meter"><i style="width: {{ round($profileDone / count($profileFields) * 100) }}%"></i></div>
                    </div>
                    <div>
                        <div class="eea-row"><span class="eea-k">{{ __('Fingerprints') }}</span><span>{{ $fingers }} {{ __('of') }} {{ $needFp }}</span></div>
                        <div class="eea-slots">
                            @for($i = 0; $i < max($needFp, $fingers); $i++)
                                @if(isset($slots[$i]))
                                    <span class="eea-slot on"><i class="fas fa-fingerprint"></i>{{ __('Slot') }} {{ $slots[$i] }}</span>
                                @else
                                    <span class="eea-slot">{{ __('Empty') }}</span>
                                @endif
                            @endfor
                        </div>
                        @if($fingers < $needFp)
                            <div class="eea-note"><i class="fas fa-circle-exclamation"></i>{{ $needFp - $fingers === 1 ? __('One more at the kiosk') : __(':n more at the kiosk', ['n' => $needFp - $fingers]) }}</div>
                        @endif
                    </div>
                    <p class="eea-help"><b>{{ __('Changes apply tomorrow') }}</b>{{ __('Pay, shift and site take effect from the next day worked. Days already worked keep their old values.') }}</p>
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
        </div>
    </form>
</div>

{{-- In the head, not the body: a stylesheet the parser only reaches near
     the end of the page paints the sections unstyled first. --}}
@push('styles')
    @include('employees._profile_styles')
    @include('employees._register_layout')
<style>
/* ── Edit employee (2026-10-20 revision): a flat worker header, then the
   sections on a rail at the left and the open one filling the rest of the
   screen, Save always at its foot. Theme tokens throughout. ─────────────── */
.eea-page { display: flex; flex-direction: column; gap: 12px; }
.eea-back { align-self: flex-start; font-size: 13px; font-weight: 600; color: var(--text-muted); text-decoration: none; display: inline-flex; gap: 7px; align-items: center; }
.eea-back:hover { color: var(--brand); }
.eea { display: flex; flex-direction: column; gap: 16px; }

.eea-hero { display: flex; align-items: center; gap: 16px; }
/* The worker icon: black on safety yellow, the same in light and dark. */
.eea-ico { flex: none; width: 56px; height: 56px; border-radius: 12px; display: grid; place-items: center;
           background: #f5b73b; color: #111; box-shadow: inset 0 0 0 1px rgba(0,0,0,.14); }
.eea-id { min-width: 0; flex: 1; }
html[data-bs-theme] .eea-hero .eea-id h1 { margin: 0 !important; font-size: 22px !important; font-weight: 600; letter-spacing: -.015em; color: var(--text-primary) !important; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.eea-sub { color: var(--text-secondary); font-size: 13px; margin-top: 3px; }
.eea-chips { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
.eea-chip { display: inline-flex; align-items: center; gap: 6px; font-size: 11.5px; font-weight: 600; padding: 3px 8px; border-radius: 5px;
            border: 1px solid var(--border-md); color: var(--text-secondary); }
.eea-chip i:not(.fas) { width: 6px; height: 6px; border-radius: 50%; background: var(--text-muted); display: inline-block; }
.eea-chip i.st-active { background: var(--success, #12b76a); } .eea-chip i.st-pending { background: var(--warning, #d97706); }
.eea-chip .fas { font-size: 10px; }
.eea-chip.warn { color: var(--warning, #d97706); }
.eea-stats { display: flex; margin: 0; flex: none; border: 1px solid var(--border); border-radius: 10px; background: var(--surface); }
.eea-stats div { padding: 9px 20px; }
.eea-stats div + div { border-left: 1px solid var(--border); }
.eea-stats dt { font-size: 11px; color: var(--text-muted); font-weight: 600; letter-spacing: .06em; text-transform: uppercase; }
.eea-stats dd { margin: 3px 0 0; font-size: 16px; font-weight: 600; font-variant-numeric: tabular-nums; white-space: nowrap; color: var(--text-primary); }

/* The rail and the open section side by side, down to the bottom of the screen. */
.eea-card { display: grid; grid-template-columns: 250px minmax(0, 1fr); gap: 16px; min-height: calc(100vh - 250px); }
.eea-rail { display: flex; flex-direction: column; gap: 12px; }
.eea-tabs { display: flex; flex-direction: column; gap: 2px; padding: 6px; background: var(--surface); border: 1px solid var(--border); border-radius: 10px; }
.eea-tabs button { background: none; border: 0; border-radius: 7px; padding: 13px 12px; font-weight: 500; font-size: 13.5px; color: var(--text-secondary);
                   display: flex; align-items: center; gap: 10px; cursor: pointer; text-align: left; }
.eea-tabs button > .fas, .eea-tabs button > .far { width: 16px; text-align: center; font-size: 12px; color: var(--text-muted); }
.eea-tabs button:hover { background: var(--bg-subtle); color: var(--text-primary); }
.eea-tabs button.on { background: color-mix(in srgb, var(--brand) 10%, transparent); color: var(--text-primary); font-weight: 600; box-shadow: inset 2px 0 0 var(--brand); }
.eea-tabs button.on > .fas, .eea-tabs button.on > .far { color: var(--brand); }
.eea-tabs .cnt { margin-left: auto; font-size: 11.5px; font-weight: 500; color: var(--text-muted); font-variant-numeric: tabular-nums; }
.eea-tabs .cnt.done { color: var(--success, #12b76a); }
.eea-tabs .dotm { width: 7px; height: 7px; border-radius: 50%; display: inline-block; flex: none; }
.eea-tabs .dotm.need { background: var(--danger); }
.eea-tabs .dotm.chg { background: var(--brand); }

.eea-railbox { flex: 1; display: flex; flex-direction: column; gap: 18px; padding: 16px; background: var(--surface); border: 1px solid var(--border); border-radius: 10px; }
.eea-row { display: flex; justify-content: space-between; align-items: baseline; gap: 8px; font-size: 12px; color: var(--text-secondary); font-variant-numeric: tabular-nums; }
.eea-k { font-size: 11px; font-weight: 600; letter-spacing: .06em; text-transform: uppercase; color: var(--text-muted); }
.eea-meter { height: 5px; margin-top: 10px; border-radius: 3px; overflow: hidden; background: var(--border); }
.eea-meter i { display: block; height: 100%; background: var(--brand); }
.eea-slots { display: flex; gap: 6px; margin-top: 10px; }
.eea-slot { flex: 1; min-width: 0; height: 34px; border-radius: 7px; display: flex; align-items: center; justify-content: center; gap: 6px;
            font-size: 12px; color: var(--text-muted); border: 1px dashed var(--border-md); }
.eea-slot.on { border: 1px solid rgba(18,183,106,.45); background: rgba(18,183,106,.10); color: var(--success, #12b76a); font-weight: 600; }
.eea-note { margin-top: 9px; font-size: 12px; color: var(--warning, #d97706); display: flex; gap: 6px; align-items: center; }
.eea-help { margin: auto 0 0; padding-top: 12px; border-top: 1px solid var(--border); font-size: 12px; line-height: 1.55; color: var(--text-muted); }
.eea-help b { display: block; margin-bottom: 2px; font-weight: 600; color: var(--text-secondary); }

.eea-panel { display: flex; flex-direction: column; min-width: 0; background: var(--surface); border: 1px solid var(--border); border-radius: 10px; }
.eea .eea-body { flex: 1; display: flex; flex-direction: column; }

/* The open section: its name and why across the top, its fields the full width under it. */
.eea .ep-section { flex: 1; display: flex; flex-direction: column; margin: 0; padding: 0 24px 20px;
                   background: none !important; border: 0 !important; box-shadow: none !important; border-radius: 0 !important; }
.eea .ep-section[hidden] { display: none !important; }
.eea .ep-section-head { display: block !important; margin: 0 -24px 6px !important; padding: 16px 24px 14px !important; border: 0 !important; border-bottom: 1px solid var(--border) !important; }
.eea .ep-section-icon, .eea .rgx-chip { display: none !important; }
html[data-bs-theme] .eea .ep-section-title { font-size: 15px; font-weight: 600; margin: 0; color: var(--text-primary); }
.eea .ep-section-sub { display: block !important; font-size: 12.5px; color: var(--text-muted); margin: 3px 0 0; line-height: 1.5; }
.eea .ep-section > .row { margin-top: 0; }
.eea .ep-section > .row + .row { margin-top: 14px; }
.eea .ep-label { font-size: 12.5px !important; font-weight: 500 !important; text-transform: none !important; letter-spacing: 0 !important; color: var(--text-secondary) !important; margin-bottom: 6px; }
.eea .form-control, .eea .form-select { min-height: 40px; border-radius: 8px; font-size: 13.5px; }
.eea .form-control[readonly] { background: transparent; border-style: dashed; }
.eea .ep-hint { font-size: 11.5px; color: var(--text-muted); }
.eea .ep-subhead, .eea .eea-grp { display: flex; align-items: center; gap: 12px; margin: 20px 0 12px !important; padding: 0; border: 0;
                                   font-size: 12px; font-weight: 600; color: var(--text-secondary); }
.eea .ep-subhead::after, .eea .eea-grp::after { content: ''; flex: 1; height: 1px; background: var(--border); }

/* The other sections' fields: each row shared out across the full width. */
.eea .ep-section:not(.eea-emp) > .row { display: grid; grid-auto-flow: column; grid-auto-columns: minmax(0, 1fr); gap: 14px 18px; margin: 0; }
.eea .ep-section:not(.eea-emp) > .row > * { width: auto; max-width: none; padding: 0; margin: 0; }
.eea .ep-section:not(.eea-emp) > .row:first-of-type { margin-top: 14px; }

.eea-foot { display: flex; align-items: center; gap: 10px; padding: 13px 24px; border-top: 1px solid var(--border); background: var(--bg-subtle);
            border-radius: 0 0 10px 10px; position: sticky; bottom: 0; z-index: 5; }
.eea-saved { font-size: 12px; color: var(--text-muted); }
.eea-sp { flex: 1; }
.eea-btn { height: 38px; padding: 0 16px; border-radius: 8px; border: 1px solid var(--border-md); background: var(--surface); color: var(--text-primary);
           font-weight: 600; font-size: 13px; display: inline-flex; align-items: center; text-decoration: none; cursor: pointer; }
.eea-btn:hover { background: var(--bg-subtle); color: var(--text-primary); }
.eea-btn.pri { background: var(--brand); border-color: var(--brand); color: #fff; }
.eea-btn.pri:hover { filter: brightness(1.08); color: #fff; }
.eea .rgx-count { font-size: 12px; }

@media (max-width: 1100px) {
    .eea-hero { flex-wrap: wrap; } .eea-stats { width: 100%; } .eea-stats div { flex: 1; }
    .eea .ep-section:not(.eea-emp) > .row { grid-auto-flow: row; grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 900px) {
    .eea-card { grid-template-columns: 1fr; min-height: 0; }
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
                .filter(f => f.type !== 'hidden' && !f.disabled && !f.readOnly && f.name);
            const filled = fields.filter(f => String(f.value || '').trim() !== '').length;
            const cnt = btns[i].querySelector('.cnt');
            if (cnt) {
                const done = fields.length && filled === fields.length;
                cnt.className = 'cnt' + (done ? ' done' : '');
                cnt.innerHTML = done ? '<i class="fas fa-check"></i>' : `${filled}/${fields.length}`;
            }

            let dot = btns[i].querySelector('.dotm');
            const cls = bad || empty ? 'need' : (changed ? 'chg' : '');
            if (!cls) { if (dot) dot.remove(); return; }
            if (!dot) { dot = document.createElement('i'); btns[i].appendChild(dot); }
            dot.className = 'dotm ' + cls;
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
