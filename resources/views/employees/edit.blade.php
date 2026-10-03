@extends('layouts')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/employee-list.css') }}">
@endpush

@section('content')
<div class="employee-container">
    <x-page-header :title="__('Edit Employee Profile')">
        <x-slot:actions>
            <a href="{{ route('employees.register', $employee->isPending() ? ['tab' => 'pending'] : []) }}" class="btn btn-outline-secondary shadow-sm px-4">
                <i class="fas fa-arrow-left me-2"></i>{{ __('Back to Employees') }}
            </a>
        </x-slot:actions>
    </x-page-header>

    @if($errors->any())
        <div class="alert alert-danger border-0 shadow-sm mb-4">
            <i class="fas fa-exclamation-circle me-2"></i>
            <ul class="mb-0 mt-1">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <div class="row">
        <div class="col-12">
            <form action="{{ route('employees.update', $employee->id) }}" method="POST" class="rgx rgx-edit" id="rgxForm" data-mode="edit">
                @csrf
                @method('PUT')

                {{-- Who is being edited, at a glance. The Employee ID, the
                     fingerprint and the labor type's rates were read-only
                     boxes and a card inside Employment & Pay; they are facts
                     about the worker, not answers to type, so they sit here.
                     The name follows the name fields as they are typed. --}}
                @php $lt = $employee->laborType; @endphp
                <div class="rgx-who">
                    <span class="rgx-who-av">@include('partials.worker-icon', ['size' => '58%'])</span>
                    <div class="rgx-who-main">
                        <div class="rgx-who-name" id="rgxWhoName">{{ $employee->name }}</div>
                        <div class="rgx-who-meta">
                            <span class="rgx-tag rgx-tag-id" title="{{ __('Used in Payroll & Reports.') }}">#{{ $employee->id }}</span>
                            @if($employee->fingerprint_id)
                                <span class="rgx-tag rgx-tag-ok" title="{{ __('Cannot be changed here.') }}"><i class="fas fa-fingerprint"></i> {{ __('Fingerprint') }} {{ $employee->fingerprint_id }}</span>
                            @else
                                <span class="rgx-tag rgx-tag-warn" title="{{ __('Enrolled at the kiosk.') }}"><i class="fas fa-fingerprint"></i> {{ __('No fingerprint yet') }}</span>
                            @endif
                            @if($employee->site)
                                <span class="rgx-tag"><i class="fas fa-location-dot"></i> {{ $employee->site->name }}</span>
                            @endif
                            @if($employee->date_hired)
                                <span class="rgx-tag"><i class="fas fa-calendar"></i> {{ __('Hired') }} {{ $employee->date_hired->format('M j, Y') }}</span>
                            @endif
                        </div>
                    </div>
                    @if($lt)
                        <div class="rgx-rates" title="{{ __('Current Labor Type') }}">
                            <div class="rgx-rate rgx-rate-name"><small>{{ __('Labor type') }}</small><b>{{ $lt->name }}</b></div>
                            <div class="rgx-rate"><small>{{ __('Daily') }}</small><b>{{ $lt->getFormattedDailyRate() }}</b></div>
                            <div class="rgx-rate"><small>{{ __('Hourly') }}</small><b>{{ $lt->getFormattedHourlyRate() }}</b></div>
                            <div class="rgx-rate"><small>{{ __('OT') }}</small><b>{{ $lt->getFormattedOTRate() }}</b></div>
                        </div>
                    @endif
                </div>

                <div class="ep-section">
                    <div class="ep-section-head">
                        <span class="ep-section-icon"><i class="fas fa-helmet-safety"></i></span>
                        <div>
                            <h3 class="ep-section-title">{{ __('Employment & Pay') }}</h3>
                            <p class="ep-section-sub">{{ __('What the worker is paid and where they are assigned.') }}</p>
                        </div>
                    </div>

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

                        {{-- Employee ID, fingerprint and the labor type's rates are
                             shown in the strip at the top. The fingerprint still
                             goes back with the form, unchanged, as it always did. --}}
                        <input type="hidden" name="fingerprint_id" value="{{ $employee->fingerprint_id }}">
                    </div>
                </div>

                @include('employees._profile_fields', ['employee' => $employee])

                <div class="ep-actions">
                    {{-- What has been changed so far, counted in the page. --}}
                    <div class="rgx-prog" aria-live="polite">
                        <span class="rgx-dirty" id="rgxDirty">{{ __('No changes yet') }}</span>
                        <span class="rgx-count"><b id="rgxDone">0</b> {{ __('of') }} <b id="rgxTotal">0</b> {{ __('required filled') }}</span>
                    </div>
                    <span class="ep-actions-note"></span>
                    <a href="{{ route('employees.register', $employee->isPending() ? ['tab' => 'pending'] : []) }}" class="btn btn-outline-secondary px-4">{{ __('Cancel') }}</a>
                    <button type="submit" class="btn btn-primary fw-bold px-4">
                        <i class="fas fa-save me-2"></i>{{ __('Save Changes') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- In the head, not the body: a stylesheet the parser only reaches near
     the end of the page paints the sections unstyled first. --}}
@push('styles')
    @include('employees._profile_styles')
    @include('employees._register_layout')
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
@include('employees._register_progress')

@endsection
