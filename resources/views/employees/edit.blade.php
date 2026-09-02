@extends('layouts')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/employee-list.css') }}">
@endpush

@section('content')
<div class="employee-container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="page-title">{{ __('Edit Employee Profile') }}</h2>
        <a href="{{ route('employees.index') }}" class="btn btn-outline-secondary shadow-sm px-4">
            <i class="fas fa-arrow-left me-2"></i>{{ __('Back to List') }}
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
            {{-- enctype matters: without it the browser posts no file at all and
                 a chosen photo is silently dropped. --}}
            <form action="{{ route('employees.update', $employee->id) }}" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')

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
                            <label class="ep-label" for="middle_name">{{ __('Middle Name') }} <span class="ep-req">*</span></label>
                            <input type="text" id="middle_name" name="middle_name" required value="{{ $middle }}"
                                   class="form-control @error('middle_name') is-invalid @enderror">
                            @error('middle_name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4 col-lg-3">
                            <label class="ep-label" for="last_name">{{ __('Last Name') }} <span class="ep-req">*</span></label>
                            <input type="text" id="last_name" name="last_name" value="{{ $last }}"
                                   class="form-control @error('last_name') is-invalid @enderror" required>
                            @error('last_name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>

                        {{-- Employee type drives the fields below it. --}}
                        <div class="col-md-6 col-lg-3">
                            <label class="ep-label" for="employment_type">{{ __('Employee Type') }} <span class="ep-req">*</span></label>
                            <select name="employment_type" id="employment_type" required
                                    class="form-select @error('employment_type') is-invalid @enderror">
                                @foreach(\App\Models\Employee::EMPLOYMENT_TYPES as $val => $label)
                                    <option value="{{ $val }}"
                                        {{ old('employment_type', $employee->employment_type) === $val ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                            @error('employment_type')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>

                        {{-- ── Regular only ──
                             Labor Type comes before Position because it answers it:
                             the position IS the labor type for a regular worker, and
                             `position` — the column payroll reads — is derived from
                             it on save either way. --}}
                        <div class="col-md-6 col-lg-4 js-regular-only">
                            <label class="ep-label" for="labor_type_select">{{ __('Labor Type') }} <span class="ep-req">*</span></label>
                            <select id="labor_type_select" name="labor_type_id" class="form-select">
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

                        <div class="col-md-6 col-lg-2 js-regular-only">
                            <label class="ep-label" for="rate_per_hour">{{ __('Rate Per Hour') }} <span class="ep-req">*</span></label>
                            <input type="number" step="0.01" id="rate_per_hour" name="rate_per_hour"
                                   value="{{ $employee->rate_per_hour }}"
                                   class="form-control" style="cursor:not-allowed;" readonly>
                            <span class="ep-hint">{{ __('Calculated from Labor Type (Daily ÷ 8).') }}</span>
                        </div>

                        {{-- Filled from the labor type and locked for a regular
                             worker, typed by hand for a contractual one, who has no
                             labor type to take it from. The toggle owns that switch. --}}
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
                            <label class="ep-label" for="date_hired">{{ __('Date Hired') }} <span class="ep-req">*</span></label>
                            <input type="date" id="date_hired" name="date_hired" required
                                   value="{{ old('date_hired', $employee->date_hired?->format('Y-m-d')) }}"
                                   class="form-control @error('date_hired') is-invalid @enderror">
                            @error('date_hired')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>

                        {{-- ── Contractual only ── --}}
                        <div class="col-md-6 col-lg-3 js-contract-only" hidden>
                            <label class="ep-label" for="contract_rate">{{ __('Contract Amount') }} <span class="ep-req">*</span></label>
                            <input type="number" step="0.01" min="0" name="contract_rate" id="contract_rate"
                                   class="form-control @error('contract_rate') is-invalid @enderror"
                                   value="{{ old('contract_rate', $employee->contract_rate) }}"
                                   placeholder="300000.00">
                            @error('contract_rate')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            <span class="ep-hint">{{ __('Total for the whole project.') }}</span>
                        </div>

                        <div class="col-md-6 col-lg-3 js-contract-only" hidden>
                            <label class="ep-label" for="end_of_contract">{{ __('End of Contract') }} <span class="ep-req">*</span></label>
                            <input type="date" id="end_of_contract" name="end_of_contract"
                                   value="{{ old('end_of_contract', $employee->end_of_contract?->format('Y-m-d')) }}"
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
                            <div class="d-flex gap-2 align-items-start flex-wrap">
                                <select name="site_id" id="site_select" required
                                        class="form-select"
                                        style="flex:1;min-width:160px;">
                                    <option value="">{{ __('— Select a site —') }}</option>
                                    @foreach($sites as $site)
                                        <option value="{{ $site->id }}"
                                                {{ $employee->site_id == $site->id ? 'selected' : '' }}>
                                            {{ $site->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <button type="button" id="newSiteBtn" class="btn btn-primary">
                                    <i class="fas fa-plus me-1"></i>{{ __('New Site') }}
                                </button>
                            </div>

                            {{-- Inline new-site panel (Project Name + Google Maps location) --}}
                            <div id="newSitePanel" style="display:none;background:var(--bg-subtle,#f8fafc);border:1px solid var(--border,#e2e8f0);" class="mt-2 p-3 rounded-2">
                                <label class="form-label fw-semibold mb-1" style="font-size:13px;">{{ __('Project Name') }}</label>
                                <input type="text" id="newSiteName" class="form-control form-control-sm mb-3"
                                       placeholder="{{ __('e.g., Tower 2 — Riverside') }}" maxlength="100">

                                <label class="form-label fw-semibold mb-1" style="font-size:13px;">
                                    <i class="fas fa-map-marker-alt me-1" style="color:#16a34a;"></i>{{ __('Location') }}
                                </label>
                                <input type="text" id="newSiteLocationSearch" class="form-control form-control-sm mb-2"
                                       placeholder="{{ __('Search an address, or drop a pin on the map') }}" autocomplete="off">
                                <div id="newSiteMap" class="rounded-2 mb-2" style="height:220px;width:100%;background:var(--bg-body,#e5e7eb);"></div>
                                <input type="hidden" id="newSiteLocation">
                                <input type="hidden" id="newSiteLat">
                                <input type="hidden" id="newSiteLng">

                                <div class="d-flex gap-2">
                                    <button type="button" id="saveSiteBtn"
                                            class="btn btn-sm fw-semibold"
                                            style="background:#16a34a;color:#fff;border:none;padding:6px 14px;border-radius:6px;white-space:nowrap;">
                                        <i class="fas fa-save me-1"></i>{{ __('Save Site') }}
                                    </button>
                                    <button type="button" id="cancelSiteBtn"
                                            class="btn btn-sm"
                                            style="background:var(--bg-surface,#f1f5f9);color:var(--text-secondary,#475569);border:1px solid var(--border,#e2e8f0);padding:6px 12px;border-radius:6px;">
                                        Cancel
                                    </button>
                                </div>
                                <div id="newSiteError" class="text-danger mt-1" style="font-size:12px;display:none;"></div>
                            </div>
                        </div>

                        {{-- Employee ID (read-only) --}}
                        <div class="col-md-6 col-lg-3">
                            <label class="ep-label">{{ __('Employee ID') }}</label>
                            <input type="text" value="#{{ $employee->id }}" class="form-control ep-mono" readonly>
                            <span class="ep-hint">{{ __('Used in Payroll & Reports.') }}</span>
                        </div>

                        {{-- Fingerprint ID (read-only) --}}
                        <div class="col-md-6 col-lg-3">
                            <label class="ep-label">{{ __('Fingerprint ID') }}</label>
                            <input type="text" value="{{ $employee->fingerprint_id ?? 'Not set' }}"
                                   class="form-control ep-mono" readonly>
                            <input type="hidden" name="fingerprint_id" value="{{ $employee->fingerprint_id }}">
                            <span class="ep-hint">{{ __('Cannot be changed here.') }}</span>
                        </div>

                        {{-- Photo — camera or gallery, see _photo_picker --}}
                        <div class="col-md-6 col-lg-3">
                            <label class="ep-label">{{ __('Profile Photo') }}</label>
                            @include('employees._photo_picker', [
                                'currentPhoto' => $employee->photo ? asset('storage/' . $employee->photo) : null,
                            ])
                        </div>

                        {{-- Current labor type info card --}}
                        @if($employee->laborType)
                        <div class="col-12 p-3 rounded-3" style="background:var(--bg-subtle,#f0f4ff);border-left:4px solid #3b82f6;">
                            <small class="text-muted d-block mb-2">{{ __('Current Labor Type') }}</small>
                            <div class="row g-2">
                                <div class="col-6">
                                    <strong style="color:var(--primary,#3b82f6);">{{ $employee->laborType->name }}</strong>
                                    <small class="text-muted d-block">Daily: {{ $employee->laborType->getFormattedDailyRate() }}</small>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted d-block">Hourly: {{ $employee->laborType->getFormattedHourlyRate() }}</small>
                                    <small class="text-muted">OT: {{ $employee->laborType->getFormattedOTRate() }}</small>
                                </div>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>

                @include('employees._profile_fields', ['employee' => $employee])

                <div class="ep-actions">
                    <a href="{{ route('employees.index') }}" class="btn btn-outline-secondary px-4">{{ __('Cancel') }}</a>
                    <button type="submit" class="btn btn-primary fw-bold px-4">
                        <i class="fas fa-save me-2"></i>{{ __('Save Changes') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@include('employees._profile_styles')

<script src="{{ asset('js/site-location-picker.js') }}"></script>
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
    const csrfToken  = '{{ csrf_token() }}';
    const siteUrl    = '{{ route("sites.store") }}';
    const siteSelect = document.getElementById('site_select');
    const newSiteBtn = document.getElementById('newSiteBtn');
    const panel      = document.getElementById('newSitePanel');
    const nameInput  = document.getElementById('newSiteName');
    const saveBtn    = document.getElementById('saveSiteBtn');
    const cancelBtn  = document.getElementById('cancelSiteBtn');
    const errEl      = document.getElementById('newSiteError');
    const rateInput  = document.getElementById('rate_per_hour');
    const ltSelect   = document.getElementById('labor_type_select');

    // Google Maps location picker for the new-site panel.
    const locField   = document.getElementById('newSiteLocation');
    const latField   = document.getElementById('newSiteLat');
    const lngField   = document.getElementById('newSiteLng');
    const sitePicker = JeyancoSiteMap.init({
        apiKey:       '{{ config('services.google_maps.key') }}',
        searchInput:  document.getElementById('newSiteLocationSearch'),
        mapEl:        document.getElementById('newSiteMap'),
        addressField: locField,
        latField:     latField,
        lngField:     lngField,
    });

    // Labor type → auto-fill rate
    ltSelect.addEventListener('change', function () {
        const opt = this.options[this.selectedIndex];
        if (opt.value) {
            rateInput.value = (parseFloat(opt.dataset.daily) / 8).toFixed(2);
        } else {
            rateInput.value = '';
        }
    });

    // Toggle new-site panel
    newSiteBtn.addEventListener('click', () => {
        panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
        if (panel.style.display === 'block') {
            nameInput.focus();
            JeyancoSiteMap.refresh(sitePicker);
        }
    });
    cancelBtn.addEventListener('click', () => {
        panel.style.display = 'none';
        nameInput.value = '';
        errEl.style.display = 'none';
    });

    // Save new site via AJAX
    saveBtn.addEventListener('click', async () => {
        const name = nameInput.value.trim();
        if (!name) { showErr('Please enter a site name.'); return; }
        errEl.style.display = 'none';
        saveBtn.disabled = true;
        saveBtn.textContent = 'Saving…';

        try {
            const r = await fetch(siteUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body: JSON.stringify({
                    name,
                    location:  locField.value || null,
                    latitude:  latField.value || null,
                    longitude: lngField.value || null,
                }),
            });
            const data = await r.json();
            if (data.success) {
                const opt = new Option(data.site.name, data.site.id, true, true);
                siteSelect.appendChild(opt);
                siteSelect.value = data.site.id;
                panel.style.display = 'none';
                nameInput.value = '';
                JeyancoSiteMap.reset(sitePicker);
            } else {
                showErr(data.errors?.name?.[0] || data.message || 'Could not create site.');
            }
        } catch { showErr('Network error — please try again.'); }
        finally { saveBtn.disabled = false; saveBtn.textContent = 'Save'; }
    });

    nameInput.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); saveBtn.click(); } });

    function showErr(msg) { errEl.textContent = msg; errEl.style.display = 'block'; }
})();
</script>

@include('employees._employment_type_toggle')

@endsection
