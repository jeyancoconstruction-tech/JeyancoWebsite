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
                Every field marked * is required. Only the photo and the Government ID numbers may be left blank.
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
                            <input type="date" id="date_hired" name="date_hired" required value="{{ old('date_hired') }}"
                                   class="form-control @error('date_hired') is-invalid @enderror">
                            @error('date_hired')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
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
                            <div class="d-flex gap-2 align-items-start flex-wrap">
                                <select name="site_id" id="site_select" required
                                        class="form-select @error('site_id') is-invalid @enderror"
                                        style="flex:1;min-width:160px;">
                                    <option value="">{{ __('— Select a site —') }}</option>
                                    @foreach($sites as $site)
                                        <option value="{{ $site->id }}" {{ old('site_id') == $site->id ? 'selected' : '' }}>
                                            {{ $site->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <button type="button" id="newSiteBtn" class="btn btn-primary">
                                    <i class="fas fa-plus me-1"></i>{{ __('New Site') }}
                                </button>
                            </div>
                            @error('site_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror

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

                        {{-- Photo — camera or gallery, see _photo_picker --}}
                        <div class="col-md-6 col-lg-3">
                            <label class="ep-label">{{ __('Profile Photo') }} <span class="ep-optional">{{ __('(optional)') }}</span></label>
                            @include('employees._photo_picker')
                        </div>

                        {{-- No fingerprint field: the kiosk reads the finger and
                             assigns the slot, and that enrolment is what makes
                             the worker active. --}}
                        <div class="col-12">
                            <p class="ep-note">
                                <i class="fas fa-fingerprint"></i>
                                This worker is saved as <strong>{{ __('Pending') }}</strong>. Enrol their fingerprint at the
                                kiosk to activate them — they appear on the kiosk's list of workers needing a
                                finger, and become active across Attendance, Payroll and the Dashboard the
                                moment it is scanned.
                            </p>
                        </div>
                    </div>
                </div>

                @include('employees._profile_fields')

                <div class="ep-actions">
                    <p class="ep-actions-note">{{ __('Every field marked') }} <span class="ep-req">*</span> {{ __('is required. Only the photo and the Government ID numbers may be left blank.') }}</p>
                    <a href="{{ route('employees.register') }}" class="btn btn-outline-secondary px-4">{{ __('Cancel') }}</a>
                    <button type="submit" class="btn btn-primary fw-bold px-4">
                        <i class="fas fa-user-plus me-2"></i>{{ __('Register Employee') }}
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
    const csrfToken   = '{{ csrf_token() }}';
    const siteUrl     = '{{ route("sites.store") }}';
    const siteSelect  = document.getElementById('site_select');
    const newSiteBtn  = document.getElementById('newSiteBtn');
    const panel       = document.getElementById('newSitePanel');
    const nameInput   = document.getElementById('newSiteName');
    const saveBtn     = document.getElementById('saveSiteBtn');
    const cancelBtn   = document.getElementById('cancelSiteBtn');
    const errEl       = document.getElementById('newSiteError');
    const rateInput   = document.getElementById('rate_per_hour');
    const ltSelector  = document.getElementById('labor_type_selector');

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
    ltSelector.addEventListener('change', function () {
        const opt = this.options[this.selectedIndex];
        if (opt.value) {
            const daily = parseFloat(opt.dataset.daily) || 0;
            rateInput.value = (daily / 8).toFixed(2);
        } else {
            rateInput.value = '';
        }
    });
    if (ltSelector.value) ltSelector.dispatchEvent(new Event('change'));

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
