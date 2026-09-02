{{-- Profile photo: take one with the camera, or pick an existing file.

     Both routes end at the same <input type="file" name="photo">, so the
     server sees an ordinary upload either way — a camera snapshot is drawn to
     a canvas, turned into a File, and assigned to that input through a
     DataTransfer.

     Shared by Register Employee and Edit Employee. Whichever form includes it
     must be enctype="multipart/form-data", or the file is silently dropped.

     Optional $currentPhoto: an existing photo to show on first paint. --}}
@php $currentPhoto = $currentPhoto ?? null; @endphp

<div id="photoBox" class="ep-photo">
    <div id="photoPlaceholder" class="ep-photo-empty" style="{{ $currentPhoto ? 'display:none;' : '' }}">
        <i class="fas fa-user-circle"></i>
    </div>
    <img id="photoPreviewImg" src="{{ $currentPhoto ?? '' }}" alt="Preview"
         class="ep-photo-img" style="{{ $currentPhoto ? '' : 'display:none;' }}">

    <div class="ep-photo-actions">
        <button type="button" id="openCameraBtn" class="btn btn-sm btn-primary">
            <i class="fas fa-camera me-1"></i>{{ __('Camera') }}
        </button>
        <button type="button" id="openGalleryBtn" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-images me-1"></i>{{ __('Gallery') }}
        </button>
        <button type="button" id="photoRemoveBtn" class="btn btn-sm btn-outline-danger"
                style="{{ $currentPhoto ? '' : 'display:none;' }}">
            <i class="fas fa-times"></i>
        </button>
    </div>
</div>

<input type="file" id="galleryInput" name="photo"
       accept="image/jpeg,image/png"
       class="@error('photo') is-invalid @enderror"
       style="display:none;">
@error('photo')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
<span class="ep-hint">{{ __('JPG or PNG, max 2 MB.') }}</span>

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

    if (!galleryInput || !cameraModal) return;

    const bsModal = new bootstrap.Modal(cameraModal);
    let stream    = null;

    function showPreview(url) {
        photoPreview.src           = url;
        photoPreview.style.display = 'block';
        placeholder.style.display  = 'none';
        removeBtn.style.display    = '';
    }

    function clearPreview() {
        photoPreview.src           = '';
        photoPreview.style.display = 'none';
        placeholder.style.display  = 'flex';
        removeBtn.style.display    = 'none';
        galleryInput.value         = '';
    }

    // Gallery — pick an existing file from the device.
    openGalleryBtn.addEventListener('click', () => galleryInput.click());
    galleryInput.addEventListener('change', () => {
        if (galleryInput.files[0]) showPreview(URL.createObjectURL(galleryInput.files[0]));
    });

    removeBtn.addEventListener('click', clearPreview);

    // Camera — getUserMedia needs a secure context, so this works on the
    // deployed HTTPS site but not over plain http on a LAN address.
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
            cameraErrorMsg.textContent =
                e.name === 'NotAllowedError' ? 'Camera access was denied.' :
                e.name === 'NotFoundError'   ? 'No camera found on this device.' :
                !window.isSecureContext      ? 'The camera needs a secure (https) connection.' :
                'Could not open camera (' + e.name + ').';
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
