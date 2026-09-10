@extends('layouts')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/employee-list.css') }}">
@endpush

@section('content')
<div class="employee-container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="page-title mb-1">{{ __('Register Employee') }}</h2>
            <p class="text-muted mb-0" style="font-size:.875rem;">
                Every field marked * is required. The Government ID numbers, the blood type and the email may be left blank.
            </p>
        </div>
        <a href="{{ route('employees.register') }}" class="btn btn-outline-secondary shadow-sm px-4">
            <i class="fas fa-arrow-left me-2"></i>{{ __('Back to Register & Manage') }}
        </a>
    </div>

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
            <form action="{{ route('employees.store') }}" method="POST" enctype="multipart/form-data">
                @csrf

                <div class="ep-section">
                    <div class="ep-section-head">
                        <span class="ep-section-icon"><i class="fas fa-helmet-safety"></i></span>
                        <div>
                            <h3 class="ep-section-title">{{ __('Employment & Pay') }}</h3>
                            <p class="ep-section-sub">{{ __('What the worker is paid and where they are assigned. Required to activate them.') }}</p>
                        </div>
                    </div>

                    <div class="row g-3">
                        {{-- Name, in parts. `name` itself is composed from these
                             on save — it stays what the rest of the app reads. --}}
                        <div class="col-md-4 col-lg-3">
                            <label class="ep-label" for="first_name">{{ __('First Name') }} <span class="ep-req">*</span></label>
                            <input type="text" id="first_name" name="first_name" value="{{ old('first_name') }}"
                                   class="form-control @error('first_name') is-invalid @enderror"
                                   placeholder="{{ __('Juan') }}" required>
                            @error('first_name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4 col-lg-2">
                            <label class="ep-label" for="middle_name">{{ __('Middle Name') }} <span class="ep-req">*</span></label>
                            <input type="text" id="middle_name" name="middle_name" required value="{{ old('middle_name') }}"
                                   class="form-control @error('middle_name') is-invalid @enderror"
                                   placeholder="{{ __('Santos') }}">
                            @error('middle_name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4 col-lg-3">
                            <label class="ep-label" for="last_name">{{ __('Last Name') }} <span class="ep-req">*</span></label>
                            <input type="text" id="last_name" name="last_name" value="{{ old('last_name') }}"
                                   class="form-control @error('last_name') is-invalid @enderror"
                                   placeholder="{{ __('Dela Cruz') }}" required>
                            @error('last_name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>

                        {{-- Employee type drives the three fields below it. --}}
                        <div class="col-md-6 col-lg-3">
                            <label class="ep-label" for="employment_type">{{ __('Employee Type') }} <span class="ep-req">*</span></label>
                            <select name="employment_type" id="employment_type" required
                                    class="form-select @error('employment_type') is-invalid @enderror">
                                @foreach(\App\Models\Employee::EMPLOYMENT_TYPES as $val => $label)
                                    <option value="{{ $val }}"
                                        {{ old('employment_type', \App\Models\Employee::EMPLOYMENT_DAILY) === $val ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                            @error('employment_type')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>

                        {{-- ── Regular only: paid by the hour off a labor type ──
                             Labor Type comes before Position because it answers it:
                             the position IS the labor type for a regular worker, and
                             `position` — the column payroll reads — is derived from
                             it on save either way. Asking for a job title first
                             invited a second, different answer to the same question. --}}
                        <div class="col-md-6 col-lg-4 js-regular-only">
                            <label class="ep-label" for="labor_type_selector">{{ __('Labor Type') }} <span class="ep-req">*</span></label>
                            <select name="labor_type_id" id="labor_type_selector"
                                    class="form-select @error('labor_type_id') is-invalid @enderror">
                                <option value="">{{ __('— Select Labor Type —') }}</option>
                                @foreach($laborTypes as $type)
                                    <option value="{{ $type->id }}"
                                            data-name="{{ $type->name }}"
                                            data-daily="{{ $type->daily_rate }}"
                                            data-ot="{{ $type->ot_rate }}"
                                            {{ old('labor_type_id') == $type->id ? 'selected' : '' }}>
                                        {{ $type->name }} — ₱{{ number_format($type->daily_rate, 2) }}/day
                                    </option>
                                @endforeach
                            </select>
                            @error('labor_type_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            <span class="ep-hint">{{ __('Choose a Labor Type to automatically fill the Position.') }}</span>
                        </div>

                        {{-- Which crew they will be on. Every day they work is
                             stamped with the shift they were on that day. --}}
                        <div class="col-md-6 col-lg-3 js-regular-only">
                            <label class="ep-label" for="shift_select">{{ __('Shift') }}</label>
                            <select id="shift_select" name="shift_id" class="form-select">
                                <option value="">{{ __('— Select —') }}</option>
                                @foreach($shifts as $sh)
                                    <option value="{{ $sh->id }}" {{ old('shift_id') == $sh->id ? 'selected' : '' }}>
                                        {{ $sh->name }} — {{ \Carbon\Carbon::parse($sh->starts_at)->format('g:i A') }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6 col-lg-2 js-regular-only">
                            <label class="ep-label" for="rate_per_hour">{{ __('Rate Per Hour') }} <span class="ep-req">*</span></label>
                            <input type="number" step="0.01" id="rate_per_hour" name="rate_per_hour"
                                   value="{{ old('rate_per_hour') }}"
                                   class="form-control @error('rate_per_hour') is-invalid @enderror"
                                   placeholder="0.00">
                            @error('rate_per_hour')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            <span class="ep-hint">{{ __('Auto-filled from the labor type.') }}</span>
                        </div>

                        {{-- Filled from the labor type and locked for a regular
                             worker, typed by hand for a contractual one, who has no
                             labor type to take it from. The toggle owns that switch. --}}
                        <div class="col-md-6 col-lg-3">
                            <label class="ep-label" for="job_title">{{ __('Position / Job Title') }} <span class="ep-req">*</span></label>
                            <input type="text" id="job_title" name="job_title" required value="{{ old('job_title') }}"
                                   class="form-control @error('job_title') is-invalid @enderror"
                                   placeholder="{{ __('e.g. Mason') }}">
                            @error('job_title')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            <span class="ep-hint js-position-hint"></span>
                        </div>

                        <div class="col-md-6 col-lg-3">
                            <label class="ep-label" for="date_hired">{{ __('Date Hired') }} <span class="ep-req">*</span></label>
                            {{-- A worker is nearly always registered on the day they
                                 start, so today is the right answer often enough to be
                                 the default. It stays a plain date input — changing it
                                 costs the same as filling it in did. now() is Manila
                                 time (config/app.php), so this is the office's today,
                                 not the server's UTC one. --}}
                            <input type="date" id="date_hired" name="date_hired" required
                                   value="{{ old('date_hired', now()->toDateString()) }}"
                                   class="form-control @error('date_hired') is-invalid @enderror">
                            @error('date_hired')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            <span class="ep-hint">{{ __('Today by default — change it if they started earlier.') }}</span>
                        </div>

                        {{-- ── Contractual only ── --}}
                        <div class="col-md-6 col-lg-3 js-contract-only" hidden>
                            <label class="ep-label" for="contract_rate">{{ __('Contract Amount') }} <span class="ep-req">*</span></label>
                            <input type="number" step="0.01" min="0" name="contract_rate" id="contract_rate"
                                   class="form-control @error('contract_rate') is-invalid @enderror"
                                   value="{{ old('contract_rate') }}"
                                   placeholder="300000.00">
                            @error('contract_rate')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            <span class="ep-hint">{{ __('Total for the whole project.') }}</span>
                        </div>

                        <div class="col-md-6 col-lg-3 js-contract-only" hidden>
                            <label class="ep-label" for="end_of_contract">{{ __('End of Contract') }} <span class="ep-req">*</span></label>
                            <input type="date" id="end_of_contract" name="end_of_contract"
                                   value="{{ old('end_of_contract') }}"
                                   class="form-control @error('end_of_contract') is-invalid @enderror">
                            @error('end_of_contract')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12 js-contract-only" hidden>
                            <p class="ep-note">
                                <i class="fas fa-circle-info"></i>
                                Contractual workers are settled against their contract, so this payroll
                                computes no wages for them. Their attendance and hours are still recorded.
                            </p>
                        </div>

                        {{-- Site Assignment --}}
                        <div class="col-md-6 col-lg-6">
                            <label class="ep-label" for="site_select">{{ __('Site Assignment') }} <span class="ep-req">*</span></label>
                            <select name="site_id" id="site_select" required
                                    class="form-select @error('site_id') is-invalid @enderror">
                                <option value="">{{ __('— Select a site —') }}</option>
                                @foreach($sites as $site)
                                    <option value="{{ $site->id }}" {{ old('site_id') == $site->id ? 'selected' : '' }}>
                                        {{ $site->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('site_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            {{-- No New Site button. A site is made on the Sites page,
                                 which is the screen that owns them. The panel that used
                                 to open here carried its own name field, its own map and
                                 its own save, inside a form already asking for twenty-five
                                 other things. --}}
                        </div>

                        {{-- No photo field. Railway wipes the container's
                             filesystem on every deploy and there is no volume
                             behind storage/app/public, so an uploaded photo
                             was certain to vanish — it only needed a deploy to
                             do it. A field that silently loses what you put in
                             it is worse than not having the field. Workers
                             show their initial instead. --}}

                        {{-- No fingerprint field: the kiosk reads the finger and
                             assigns the slot, and that enrolment is what makes
                             the worker active.

                             The paragraph that used to say so is gone. Saving
                             now lands on the Pending tab with the new worker in
                             it, which shows the same thing instead of
                             explaining it in advance. --}}
                    </div>
                </div>

                @include('employees._profile_fields')

                <div class="ep-actions">
                    <p class="ep-actions-note">{{ __('Every field marked') }} <span class="ep-req">*</span> {{ __('is required. The Government ID numbers, the blood type and the email may be left blank.') }}</p>
                    <a href="{{ route('employees.register') }}" class="btn btn-outline-secondary px-4">{{ __('Cancel') }}</a>
                    <button type="submit" class="btn btn-primary fw-bold px-4">
                        <i class="fas fa-user-plus me-2"></i>{{ __('Register Employee') }}
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
@endpush

{{-- site-location-picker.js is not loaded here any more: it existed for the
     inline New Site map, which is gone with the button. address-picker.js
     below is a different thing — the province / city / barangay cascade. --}}
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
// ── Labor type → Rate Per Hour ──────────────────────────────────────────────
// This lived inside the New Site script, which is why removing that button
// took the rate auto-fill with it: one <script> was doing two unrelated jobs.
// It has its own now, so the next thing removed from this page cannot take it
// along as well.
//
// Position is filled separately, in _employment_type_toggle.blade.php, off the
// same change event. Two listeners on one select is fine; one listener doing
// two jobs was not.
(function () {
    const ltSelector = document.getElementById('labor_type_selector');
    const rateInput  = document.getElementById('rate_per_hour');
    if (!ltSelector || !rateInput) return;

    ltSelector.addEventListener('change', function () {
        const opt = this.options[this.selectedIndex];
        // data-daily is the labor type's daily rate; the hour is the standard
        // eight, not the span of the shift — see the note on paid hours.
        rateInput.value = opt && opt.value
            ? ((parseFloat(opt.dataset.daily) || 0) / 8).toFixed(2)
            : '';
    });

    // A failed validation brings the chosen labor type back in the select;
    // without this the rate beside it would come back empty.
    if (ltSelector.value) ltSelector.dispatchEvent(new Event('change'));
})();
</script>

@include('employees._employment_type_toggle')

@endsection
