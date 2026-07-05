@extends('layouts')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/employee-list.css') }}">
@endpush

@section('content')
<div class="employee-container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="page-title">Add New Employee</h2>
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
                <form action="{{ route('employees.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf

                    <div class="row g-4">
                        {{-- Full Name --}}
                        <div class="col-12">
                            <label class="form-label fw-bold text-secondary">Full Name</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0">👤</span>
                                <input type="text" name="name" value="{{ old('name') }}"
                                       class="form-control border-start-0 @error('name') is-invalid @enderror"
                                       placeholder="Enter full name" required>
                            </div>
                            @error('name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>

                        {{-- Labor Type --}}
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-secondary">Labor Type</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0">🏗️</span>
                                <select name="labor_type_id" id="labor_type_selector"
                                        class="form-select border-start-0 @error('labor_type_id') is-invalid @enderror" required>
                                    <option value="">— Select Labor Type —</option>
                                    @foreach($laborTypes as $type)
                                        <option value="{{ $type->id }}"
                                                data-daily="{{ $type->daily_rate }}"
                                                data-ot="{{ $type->ot_rate }}"
                                                {{ old('labor_type_id') == $type->id ? 'selected' : '' }}>
                                            {{ $type->name }} — ₱{{ number_format($type->daily_rate, 2) }}/day
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            @error('labor_type_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>

                        {{-- Rate per Hour (auto-filled) --}}
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-secondary">Rate Per Hour</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0">₱</span>
                                <input type="number" step="0.01" id="rate_per_hour" name="rate_per_hour"
                                       value="{{ old('rate_per_hour') }}"
                                       class="form-control border-start-0 @error('rate_per_hour') is-invalid @enderror"
                                       placeholder="Auto-filled from labor type" required>
                            </div>
                            @error('rate_per_hour')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            <small class="text-muted">Auto-filled when a labor type is selected.</small>
                        </div>

                        {{-- Site Assignment --}}
                        <div class="col-12">
                            <label class="form-label fw-bold text-secondary">
                                <i class="fas fa-map-marker-alt me-1" style="color:#16a34a;"></i>Site Assignment
                            </label>
                            <div class="d-flex gap-2 align-items-start flex-wrap">
                                <select name="site_id" id="site_select"
                                        class="form-select @error('site_id') is-invalid @enderror"
                                        style="flex:1;min-width:180px;">
                                    <option value="">— Unassigned —</option>
                                    @foreach($sites as $site)
                                        <option value="{{ $site->id }}" {{ old('site_id') == $site->id ? 'selected' : '' }}>
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
                            @error('site_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror

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

                        {{-- Photo --}}
                        <div class="col-12">
                            <label class="form-label fw-bold text-secondary">Photo <span class="text-muted fw-normal">(optional)</span></label>

                            <div id="photoBox" style="display:flex;flex-direction:column;align-items:center;gap:14px;padding:20px 16px;border:2px dashed #cbd5e1;border-radius:12px;background:var(--bg-subtle,#f8fafc);">
                                <div id="photoPlaceholder" style="display:flex;flex-direction:column;align-items:center;gap:6px;color:#94a3b8;">
                                    <i class="fas fa-user-circle" style="font-size:3.5rem;"></i>
                                    <span style="font-size:12px;">No photo selected</span>
                                </div>
                                <img id="photoPreviewImg" src="" alt="Preview"
                                     style="display:none;width:110px;height:110px;object-fit:cover;border-radius:50%;border:3px solid #e2e8f0;box-shadow:0 2px 8px rgba(0,0,0,.12);">

                                <div style="display:flex;gap:8px;flex-wrap:wrap;justify-content:center;">
                                    <button type="button" id="openCameraBtn"
                                            style="background:#1e3a8a;color:#fff;border:none;border-radius:7px;padding:7px 18px;font-size:13px;font-weight:600;cursor:pointer;">
                                        <i class="fas fa-camera me-1"></i>Camera
                                    </button>
                                    <button type="button" id="openGalleryBtn"
                                            style="background:#0f766e;color:#fff;border:none;border-radius:7px;padding:7px 18px;font-size:13px;font-weight:600;cursor:pointer;">
                                        <i class="fas fa-images me-1"></i>Gallery
                                    </button>
                                    <button type="button" id="photoRemoveBtn"
                                            style="display:none;background:#dc2626;color:#fff;border:none;border-radius:7px;padding:7px 14px;font-size:13px;font-weight:600;cursor:pointer;">
                                        <i class="fas fa-times me-1"></i>Remove
                                    </button>
                                </div>
                            </div>

                            <input type="file" id="galleryInput" name="photo"
                                   accept="image/jpg,image/jpeg,image/png"
                                   class="@error('photo') is-invalid @enderror"
                                   style="display:none;">
                            @error('photo')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            <small class="text-muted">JPG or PNG, max 2 MB.</small>
                        </div>

                        {{-- Fingerprint ID --}}
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-secondary">
                                Fingerprint ID
                                <span class="badge ms-1" style="background:#eff6ff;color:#1e40af;font-size:10px;font-weight:600;border:1px solid #bfdbfe;border-radius:99px;padding:2px 8px;">Auto-assigned</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0">👆</span>
                                <input type="text" name="fingerprint_id"
                                       value="{{ old('fingerprint_id', $nextFingerprintId) }}"
                                       class="form-control border-start-0 @error('fingerprint_id') is-invalid @enderror"
                                       placeholder="{{ $nextFingerprintId }}">
                            </div>
                            @error('fingerprint_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            <small class="text-muted">Next available ID is pre-filled. Clear to auto-assign on save.</small>
                        </div>
                    </div>

                    <hr class="my-4">

                    <div class="d-flex justify-content-end gap-2">
                        <a href="{{ route('employees.index') }}" class="btn btn-outline-secondary px-4">Cancel</a>
                        <button type="submit" class="btn fw-bold px-5"
                                style="background:#1e3a8a;color:#fff;border:none;border-radius:8px;">
                            <i class="fas fa-plus me-2"></i>Add Employee
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

{{-- Camera modal --}}
<div class="modal fade" id="cameraModal" tabindex="-1" aria-labelledby="cameraModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:480px;">
        <div class="modal-content" style="border-radius:16px;overflow:hidden;border:none;">
            <div class="modal-header" style="background:linear-gradient(135deg,#1e3a8a,#1e40af);color:#fff;border:none;padding:14px 20px;">
                <h6 class="modal-title mb-0 fw-bold" id="cameraModalLabel">
                    <i class="fas fa-camera me-2"></i>Take Photo
                </h6>
                <button type="button" class="btn-close btn-close-white" id="cameraCloseBtn" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0" style="background:#000;position:relative;">
                <video id="cameraStream" autoplay playsinline muted
                       style="width:100%;display:block;max-height:380px;object-fit:cover;"></video>
                <canvas id="cameraCanvas" style="display:none;"></canvas>
                <div id="cameraError" style="display:none;padding:40px 24px;text-align:center;color:#f87171;">
                    <i class="fas fa-video-slash" style="font-size:2.5rem;margin-bottom:12px;display:block;"></i>
                    <span id="cameraErrorMsg">Camera not available.</span>
                    <div style="margin-top:12px;font-size:12px;color:#94a3b8;">Use the Gallery option instead.</div>
                </div>
            </div>
            <div class="modal-footer" style="border:none;background:#0f172a;justify-content:center;gap:10px;padding:14px 20px;">
                <button type="button" id="captureBtn"
                        style="background:#1e3a8a;color:#fff;border:none;border-radius:8px;padding:10px 28px;font-size:14px;font-weight:700;cursor:pointer;">
                    <i class="fas fa-circle me-2" style="color:#ef4444;font-size:10px;"></i>Capture
                </button>
                <button type="button" data-bs-dismiss="modal"
                        style="background:rgba(255,255,255,.1);color:#e2e8f0;border:1px solid rgba(255,255,255,.2);border-radius:8px;padding:10px 20px;font-size:14px;font-weight:600;cursor:pointer;">
                    Cancel
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const galleryInput   = document.getElementById('galleryInput');
    const openCameraBtn  = document.getElementById('openCameraBtn');
    const openGalleryBtn = document.getElementById('openGalleryBtn');
    const photoPreview   = document.getElementById('photoPreviewImg');
    const placeholder    = document.getElementById('photoPlaceholder');
    const removeBtn      = document.getElementById('photoRemoveBtn');
    const cameraStream   = document.getElementById('cameraStream');
    const canvas         = document.getElementById('cameraCanvas');
    const captureBtn     = document.getElementById('captureBtn');
    const cameraModal    = document.getElementById('cameraModal');
    const cameraError    = document.getElementById('cameraError');
    const cameraErrorMsg = document.getElementById('cameraErrorMsg');
    const bsModal        = new bootstrap.Modal(cameraModal);
    let stream           = null;

    function showPreview(url) {
        photoPreview.src            = url;
        photoPreview.style.display  = 'block';
        placeholder.style.display   = 'none';
        removeBtn.style.display     = '';
    }

    function clearPreview() {
        photoPreview.src            = '';
        photoPreview.style.display  = 'none';
        placeholder.style.display   = 'flex';
        removeBtn.style.display     = 'none';
        galleryInput.value          = '';
    }

    // Gallery — open file picker
    openGalleryBtn.addEventListener('click', () => galleryInput.click());
    galleryInput.addEventListener('change', () => {
        if (galleryInput.files[0]) {
            showPreview(URL.createObjectURL(galleryInput.files[0]));
        }
    });

    // Remove selected photo
    removeBtn.addEventListener('click', clearPreview);

    // Camera — open getUserMedia stream
    openCameraBtn.addEventListener('click', async () => {
        cameraError.style.display  = 'none';
        cameraStream.style.display = 'block';
        captureBtn.disabled        = true;
        bsModal.show();

        try {
            stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'user', width: { ideal: 1280 }, height: { ideal: 720 } },
                audio: false,
            });
            cameraStream.srcObject = stream;
            cameraStream.onloadedmetadata = () => { captureBtn.disabled = false; };
        } catch (e) {
            cameraStream.style.display = 'none';
            cameraError.style.display  = 'flex';
            cameraError.style.flexDirection = 'column';
            cameraError.style.alignItems = 'center';
            cameraErrorMsg.textContent =
                e.name === 'NotAllowedError'  ? 'Camera access was denied.' :
                e.name === 'NotFoundError'    ? 'No camera found on this device.' :
                'Could not open camera (' + e.name + ').';
            captureBtn.disabled = true;
        }
    });

    // Stop camera when modal closes
    cameraModal.addEventListener('hidden.bs.modal', () => {
        if (stream) { stream.getTracks().forEach(t => t.stop()); stream = null; }
        cameraStream.srcObject     = null;
        cameraStream.style.display = 'block';
        cameraError.style.display  = 'none';
        captureBtn.disabled        = false;
    });

    // Capture snapshot → convert to File → set on galleryInput
    captureBtn.addEventListener('click', () => {
        if (!stream) return;
        canvas.width  = cameraStream.videoWidth  || 640;
        canvas.height = cameraStream.videoHeight || 480;
        canvas.getContext('2d').drawImage(cameraStream, 0, 0);
        canvas.toBlob(blob => {
            const file = new File([blob], 'camera-photo.jpg', { type: 'image/jpeg' });
            const dt   = new DataTransfer();
            dt.items.add(file);
            galleryInput.files = dt.files;
            showPreview(URL.createObjectURL(blob));
            bsModal.hide();
        }, 'image/jpeg', 0.92);
    });
})();
</script>
@endsection
