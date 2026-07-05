@extends('layouts')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/employee-list.css') }}">
@endpush

@section('content')
<div class="employee-container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="page-title">Edit Employee Profile</h2>
        <a href="{{ route('employees.index') }}" class="btn btn-outline-secondary shadow-sm px-4">
            <i class="fas fa-arrow-left me-2"></i>Back to List
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

    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="custom-card p-4 p-md-5">
                <form action="{{ route('employees.update', $employee->id) }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="row g-4">
                        {{-- Full Name --}}
                        <div class="col-12">
                            <label class="form-label fw-bold text-secondary">Full Name</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0">👤</span>
                                <input type="text" name="name" value="{{ $employee->name }}"
                                       class="form-control border-start-0" placeholder="Enter full name" required>
                            </div>
                        </div>

                        {{-- Labor Type --}}
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-secondary">Labor Type</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0">💼</span>
                                <select id="labor_type_select" name="labor_type_id"
                                        class="form-select border-start-0" required>
                                    <option value="">— Select Labor Type —</option>
                                    @foreach($laborTypes as $labor)
                                        <option value="{{ $labor->id }}"
                                                data-daily="{{ $labor->daily_rate }}"
                                                {{ $employee->labor_type_id == $labor->id ? 'selected' : '' }}>
                                            {{ $labor->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        {{-- Rate per Hour (auto-calculated) --}}
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-secondary">Rate Per Hour</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0">₱</span>
                                <input type="number" step="0.01" id="rate_per_hour" name="rate_per_hour"
                                       value="{{ $employee->rate_per_hour }}"
                                       class="form-control border-start-0"
                                       style="cursor:not-allowed;" readonly>
                            </div>
                            <small class="text-muted">Calculated from Labor Type (Daily ÷ 8).</small>
                        </div>

                        {{-- Site Assignment --}}
                        <div class="col-12">
                            <label class="form-label fw-bold text-secondary">
                                <i class="fas fa-map-marker-alt me-1" style="color:#16a34a;"></i>Site Assignment
                            </label>
                            <div class="d-flex gap-2 align-items-start flex-wrap">
                                <select name="site_id" id="site_select"
                                        class="form-select"
                                        style="flex:1;min-width:180px;">
                                    <option value="">— Unassigned —</option>
                                    @foreach($sites as $site)
                                        <option value="{{ $site->id }}"
                                                {{ $employee->site_id == $site->id ? 'selected' : '' }}>
                                            {{ $site->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <button type="button" id="newSiteBtn"
                                        class="btn fw-600"
                                        style="background:#6366f1;color:#fff;border:none;padding:8px 14px;border-radius:7px;white-space:nowrap;">
                                    <i class="fas fa-plus me-1"></i>New Site
                                </button>
                            </div>

                            {{-- Inline new-site panel (Project Name + Google Maps location) --}}
                            <div id="newSitePanel" style="display:none;background:var(--bg-subtle,#f8fafc);border:1px solid var(--border,#e2e8f0);" class="mt-2 p-3 rounded-2">
                                <label class="form-label fw-semibold mb-1" style="font-size:13px;">Project Name</label>
                                <input type="text" id="newSiteName" class="form-control form-control-sm mb-3"
                                       placeholder="e.g., Tower 2 — Riverside" maxlength="100">

                                <label class="form-label fw-semibold mb-1" style="font-size:13px;">
                                    <i class="fas fa-map-marker-alt me-1" style="color:#16a34a;"></i>Location
                                </label>
                                <input type="text" id="newSiteLocationSearch" class="form-control form-control-sm mb-2"
                                       placeholder="Search an address, or drop a pin on the map" autocomplete="off">
                                <div id="newSiteMap" class="rounded-2 mb-2" style="height:220px;width:100%;background:var(--bg-body,#e5e7eb);"></div>
                                <input type="hidden" id="newSiteLocation">
                                <input type="hidden" id="newSiteLat">
                                <input type="hidden" id="newSiteLng">

                                <div class="d-flex gap-2">
                                    <button type="button" id="saveSiteBtn"
                                            class="btn btn-sm fw-semibold"
                                            style="background:#16a34a;color:#fff;border:none;padding:6px 14px;border-radius:6px;white-space:nowrap;">
                                        <i class="fas fa-save me-1"></i>Save Site
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
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-secondary">Employee ID</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0">🆔</span>
                                <input type="text" value="#{{ $employee->id }}" class="form-control border-start-0" readonly>
                            </div>
                            <small class="text-muted">System identifier — used in Payroll &amp; Reports.</small>
                        </div>

                        {{-- Fingerprint ID (read-only) --}}
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-secondary">Fingerprint ID</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0">👆</span>
                                <input type="text" value="{{ $employee->fingerprint_id ?? 'Not set' }}"
                                       class="form-control border-start-0" readonly>
                                <input type="hidden" name="fingerprint_id" value="{{ $employee->fingerprint_id }}">
                            </div>
                            <small class="text-muted">Biometric identifier — cannot be changed here.</small>
                        </div>

                        {{-- Current labor type info card --}}
                        @if($employee->laborType)
                        <div class="col-12 p-3 rounded-3" style="background:var(--bg-subtle,#f0f4ff);border-left:4px solid #3b82f6;">
                            <small class="text-muted d-block mb-2">Current Labor Type</small>
                            <div class="row g-2">
                                <div class="col-6">
                                    <strong style="color:var(--primary,#1e3a8a);">{{ $employee->laborType->name }}</strong>
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

                    <hr class="my-4">

                    <div class="d-flex justify-content-end gap-2">
                        <a href="{{ route('employees.index') }}" class="btn btn-outline-secondary px-4">Cancel</a>
                        <button type="submit" class="btn fw-bold px-5"
                                style="background:#1e3a8a;color:#fff;border:none;border-radius:8px;">
                            <i class="fas fa-save me-2"></i>Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="{{ asset('js/site-location-picker.js') }}"></script>
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
@endsection
