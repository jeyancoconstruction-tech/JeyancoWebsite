{{-- Profile photo: take one with the camera, or pick an existing file.

     Both routes end at the same <input type="file" name="photo">, so the
     server sees an ordinary upload either way — a camera snapshot is drawn to
     a canvas, turned into a File, and assigned to that input through a
     DataTransfer.

     Shared by Register Employee and Edit Employee. Whichever form includes it
     must be enctype="multipart/form-data", or the file is silently dropped.

     Optional $currentPhoto: an existing photo to show on first paint. --}}
@php
    $currentPhoto = $currentPhoto ?? null;

    // The server's cap (photo => 'nullable|image|mimes:jpg,jpeg,png|max:2048')
    // written once, so the message under the box, the client-side check and
    // the rule behind them cannot say three different numbers.
    $photoMaxMb = 2;
@endphp

<div id="photoBox" class="ep-photo">
    <div id="photoPlaceholder" class="ep-photo-empty" style="{{ $currentPhoto ? 'display:none;' : '' }}">
        <i class="fas fa-user-circle"></i>
    </div>
    <img id="photoPreviewImg" src="{{ $currentPhoto ?? '' }}" alt="{{ __('Profile photo preview') }}"
         class="ep-photo-img" style="{{ $currentPhoto ? '' : 'display:none;' }}">

    <div class="ep-photo-actions">
        <button type="button" id="openCameraBtn" class="btn btn-sm btn-primary">
            <i class="fas fa-camera me-1"></i><span id="cameraBtnLabel">{{ __('Camera') }}</span>
        </button>
        <button type="button" id="openGalleryBtn" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-images me-1"></i><span id="galleryBtnLabel">{{ __('Gallery') }}</span>
        </button>
        <button type="button" id="photoRemoveBtn" class="btn btn-sm btn-outline-danger"
                title="{{ __('Remove photo') }}"
                style="{{ $currentPhoto ? '' : 'display:none;' }}">
            <i class="fas fa-times"></i>
        </button>
    </div>
</div>

<input type="file" id="galleryInput" name="photo"
       accept="image/jpeg,image/png"
       class="@error('photo') is-invalid @enderror"
       style="display:none;">

{{-- Rejected before the form is ever submitted. Filling in twenty fields and
     then being sent back because the picture was 4 MB is the whole reason
     this check is here as well as on the server. --}}
<p id="photoError" class="ep-photo-err" role="alert" hidden></p>

@error('photo')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
<span class="ep-hint">{{ __('JPG or PNG, max :mb MB.', ['mb' => $photoMaxMb]) }}</span>

{{-- Camera modal --}}
<div class="modal fade" id="cameraModal" tabindex="-1" aria-labelledby="cameraModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:480px;">
        <div class="modal-content ep-cam">
            <div class="modal-header ep-cam-head">
                <h6 class="modal-title mb-0 fw-bold" id="cameraModalLabel">
                    <i class="fas fa-camera me-2"></i>{{ __('Take Photo') }}
                </h6>
                <button type="button" class="btn-close btn-close-white" id="cameraCloseBtn" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0 ep-cam-body">
                <video id="cameraStream" autoplay playsinline muted class="ep-cam-video"></video>
                <canvas id="cameraCanvas" style="display:none;"></canvas>
                <div id="cameraError" class="ep-cam-error" style="display:none;">
                    <i class="fas fa-video-slash"></i>
                    <span id="cameraErrorMsg">{{ __('Camera not available.') }}</span>
                    <small>{{ __('Use the Gallery option instead.') }}</small>
                </div>
            </div>
            <div class="modal-footer ep-cam-foot">
                <button type="button" id="captureBtn" class="btn btn-primary fw-bold px-4">
                    <i class="fas fa-circle me-2" style="font-size:9px;"></i>{{ __('Capture') }}
                </button>
                <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
            </div>
        </div>
    </div>
</div>

{{-- After Bootstrap, not before it.

     This script used to run inline here, in the middle of the body — where
     `bootstrap` does not exist yet, because the bundle is loaded near the end
     of layouts.blade.php. `new bootstrap.Modal(...)` threw on the first line
     of the IIFE, so no listener was ever attached and BOTH buttons were dead.
     @stack('scripts') renders after the bundle, which is where this belongs. --}}
@push('scripts')
<script>
(function () {
    const galleryInput   = document.getElementById('galleryInput');
    const openCameraBtn  = document.getElementById('openCameraBtn');
    const openGalleryBtn = document.getElementById('openGalleryBtn');
    const cameraLabel    = document.getElementById('cameraBtnLabel');
    const galleryLabel   = document.getElementById('galleryBtnLabel');
    const photoPreview   = document.getElementById('photoPreviewImg');
    const placeholder    = document.getElementById('photoPlaceholder');
    const removeBtn      = document.getElementById('photoRemoveBtn');
    const errorEl        = document.getElementById('photoError');
    const cameraStream   = document.getElementById('cameraStream');
    const canvas         = document.getElementById('cameraCanvas');
    const captureBtn     = document.getElementById('captureBtn');
    const cameraModal    = document.getElementById('cameraModal');
    const cameraError    = document.getElementById('cameraError');
    const cameraErrorMsg = document.getElementById('cameraErrorMsg');

    if (!galleryInput || !openGalleryBtn) return;

    const MAX_BYTES = {{ $photoMaxMb }} * 1024 * 1024;
    const TYPES     = ['image/jpeg', 'image/png'];

    // Built on first use rather than at load. Gallery then works even if the
    // Bootstrap bundle is missing or slow — only the camera needs the modal.
    let bsModal = null;
    function getModal() {
        if (!bsModal && window.bootstrap && cameraModal) {
            bsModal = new bootstrap.Modal(cameraModal);
        }
        return bsModal;
    }

    let stream    = null;
    let objectUrl = null;   // revoked before the next one, so previews do not pile up

    function setError(msg) {
        if (!errorEl) return;
        errorEl.textContent = msg || '';
        errorEl.hidden      = ! msg;
    }

    function setPreview(url) {
        if (objectUrl) { URL.revokeObjectURL(objectUrl); objectUrl = null; }
        if (url && url.startsWith('blob:')) objectUrl = url;

        photoPreview.src           = url;
        photoPreview.style.display = 'block';
        placeholder.style.display  = 'none';
        removeBtn.style.display    = '';
        // The buttons say what they do next once there is a photo to replace.
        if (cameraLabel)  cameraLabel.textContent  = @json(__('Retake'));
        if (galleryLabel) galleryLabel.textContent = @json(__('Change'));
        setError('');
    }

    function clearPreview() {
        if (objectUrl) { URL.revokeObjectURL(objectUrl); objectUrl = null; }
        photoPreview.removeAttribute('src');
        photoPreview.style.display = 'none';
        placeholder.style.display  = 'flex';
        removeBtn.style.display    = 'none';
        galleryInput.value         = '';
        if (cameraLabel)  cameraLabel.textContent  = @json(__('Camera'));
        if (galleryLabel) galleryLabel.textContent = @json(__('Gallery'));
        setError('');
    }

    /** The same two rules the server applies, so a bad file is caught here. */
    function reject(file) {
        if (! TYPES.includes(file.type)) return @json(__('That file is not a JPG or PNG.'));
        if (file.size > MAX_BYTES) {
            // {size} is a JS placeholder, not a Laravel one — __() fills :mb
            // and leaves the braces for the line below.
            const mb = (file.size / 1024 / 1024).toFixed(1);
            return @json(__('That photo is {size} MB. The limit is :mb MB.', ['mb' => $photoMaxMb])).replace('{size}', mb);
        }
        return null;
    }

    // ── Gallery ─────────────────────────────────────────────────────────────
    openGalleryBtn.addEventListener('click', () => galleryInput.click());

    galleryInput.addEventListener('change', () => {
        const file = galleryInput.files && galleryInput.files[0];
        if (!file) return;

        const problem = reject(file);
        if (problem) {
            // Clear it, or the form would still post the file the message
            // just said was refused.
            galleryInput.value = '';
            setError(problem);
            return;
        }
        setPreview(URL.createObjectURL(file));
    });

    removeBtn.addEventListener('click', clearPreview);

    // ── Camera ──────────────────────────────────────────────────────────────
    // getUserMedia needs a secure context, so this works on the deployed HTTPS
    // site but not over plain http on a LAN address.
    if (openCameraBtn && cameraModal) {
        openCameraBtn.addEventListener('click', async () => {
            const modal = getModal();
            if (!modal) { setError(@json(__('The camera dialog could not be opened. Use Gallery instead.'))); return; }

            setError('');
            cameraError.style.display  = 'none';
            cameraStream.style.display = 'block';
            captureBtn.disabled        = true;
            modal.show();

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
                cameraErrorMsg.textContent =
                    e.name === 'NotAllowedError' ? @json(__('Camera access was denied.')) :
                    e.name === 'NotFoundError'   ? @json(__('No camera found on this device.')) :
                    !window.isSecureContext      ? @json(__('The camera needs a secure (https) connection.')) :
                    @json(__('Could not open camera')) + ' (' + e.name + ').';
                captureBtn.disabled = true;
            }
        });

        cameraModal.addEventListener('hidden.bs.modal', () => {
            if (stream) { stream.getTracks().forEach(t => t.stop()); stream = null; }
            cameraStream.srcObject     = null;
            cameraStream.style.display = 'block';
            cameraError.style.display  = 'none';
            captureBtn.disabled        = false;
        });

        // Snapshot → File on the same input the gallery would have filled.
        captureBtn.addEventListener('click', () => {
            if (!stream) return;
            captureBtn.disabled = true;

            const w = cameraStream.videoWidth  || 640;
            const h = cameraStream.videoHeight || 480;
            canvas.width  = w;
            canvas.height = h;
            canvas.getContext('2d').drawImage(cameraStream, 0, 0, w, h);

            // A 1280x720 JPEG lands well under the cap, but a high-resolution
            // webcam can overshoot it — so step the quality down rather than
            // handing the server a file it will refuse.
            const encode = (quality) => canvas.toBlob(blob => {
                if (!blob) { setError(@json(__('The photo could not be saved. Try again.'))); captureBtn.disabled = false; return; }

                if (blob.size > MAX_BYTES && quality > 0.4) {
                    encode(quality - 0.15);
                    return;
                }

                const file = new File([blob], 'camera-photo.jpg', { type: 'image/jpeg' });
                const dt   = new DataTransfer();
                dt.items.add(file);
                galleryInput.files = dt.files;

                setPreview(URL.createObjectURL(blob));
                getModal()?.hide();
                captureBtn.disabled = false;
            }, 'image/jpeg', quality);

            encode(0.92);
        });
    }
})();
</script>
@endpush
