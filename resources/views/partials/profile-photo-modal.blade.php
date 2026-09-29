{{-- Your profile photo — from the profile menu in the top bar.

     Signed in with Google: Google's picture shows until you choose your own;
     removing yours brings Google's back. An account the admin made: the
     letter shows until you choose a picture.

     The picture is cropped square and shrunk to 256px here, in the browser,
     so what is sent is small. --}}
@php
    $me        = auth()->user();
    $hasOwn    = (bool) $me?->photo;
    $hasGoogle = (bool) $me?->google_avatar;
@endphp
<div class="modal fade" id="profilePhotoModal" tabindex="-1" aria-labelledby="ppTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:440px">
        <div class="modal-content pp-modal">
            <div class="pp-head">
                <span class="pp-head-ic"><i data-lucide="image"></i></span>
                <div class="pp-head-t">
                    <h6 id="ppTitle">{{ __('Profile photo') }}</h6>
                    <p>{{ $me?->usesGoogle() && $hasGoogle ? __('Your Google photo shows unless you choose your own.') : __('Your letter shows until you choose a photo.') }}</p>
                </div>
                <button type="button" class="pp-x" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"><i data-lucide="x"></i></button>
            </div>
            <div class="pp-body">
                <div class="pp-face" id="ppFace">
                    @include('partials.user-avatar', ['user' => $me, 'text' => $me?->initial() ?? 'A'])
                </div>
                <div class="pp-who">
                    <b>{{ $me?->name }}</b>
                    <small id="ppSource">
                        @if($hasOwn) {{ __('Your own photo') }}
                        @elseif($hasGoogle) {{ __('From your Google account') }}
                        @else {{ __('No picture yet — showing your initial') }}
                        @endif
                    </small>
                </div>
                <input type="file" id="ppFile" accept="image/png,image/jpeg,image/webp" hidden>
                <p class="pp-err" id="ppErr" role="alert" hidden></p>
            </div>
            <div class="pp-foot">
                <button type="button" class="pp-btn pp-btn-ghost" id="ppRemove" @if(! $hasOwn) hidden @endif>
                    {{ $hasGoogle ? __('Use Google photo') : __('Remove photo') }}
                </button>
                <span class="pp-sp"></span>
                <button type="button" class="pp-btn" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                <button type="button" class="pp-btn pp-btn-pri" id="ppPick"><i data-lucide="upload"></i>{{ $hasOwn || $hasGoogle ? __('Change photo') : __('Upload photo') }}</button>
            </div>
        </div>
    </div>
</div>

<style>
/* An account's picture inside any avatar circle. */
.u-av-img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; display: block; }
.avatar-box:has(.u-av-img), .av:has(.u-av-img), .acct-avatar:has(.u-av-img) { padding: 0; overflow: hidden; background: none !important; }

/* The dialog, in the dress of the app's other dialogs: a navy head. */
.pp-modal { border: 1px solid var(--border, #e4e9f0) !important; border-radius: var(--radius-lg, 12px) !important; overflow: hidden; background: var(--bg-surface, #fff); }
.pp-head { display: flex; align-items: flex-start; gap: 13px; padding: 17px 20px; background: var(--sidebar-bg, #071a33); color: #fff; }
.pp-head-ic { flex: none; width: 34px; height: 34px; border-radius: 10px; display: grid; place-items: center; background: rgba(255,255,255,.10); color: #8fbef7; }
.pp-head-ic svg { width: 16px; height: 16px; }
.pp-head-t { flex: 1; min-width: 0; }
.pp-head-t h6 { font-size: 15.5px; font-weight: 700; margin: 0 0 2px; color: #fff; }
.pp-head-t p { font-size: 12px; line-height: 1.4; margin: 0; color: #a9c0da; }
.pp-x { flex: none; width: 30px; height: 30px; border: 0; border-radius: 8px; background: rgba(255,255,255,.10); color: #cfe0f5; display: grid; place-items: center; cursor: pointer; }
.pp-x:hover { background: rgba(255,255,255,.2); color: #fff; }
.pp-x svg { width: 15px; height: 15px; }
.pp-body { padding: 24px 20px 18px; display: flex; flex-direction: column; align-items: center; gap: 12px; text-align: center; }
.pp-face {
    width: 112px; height: 112px; border-radius: 50%; display: grid; place-items: center; overflow: hidden;
    background: var(--brand-subtle, #EAF2FD); color: var(--brand, #1668DC); font-size: 42px; font-weight: 800;
    box-shadow: 0 0 0 4px var(--bg-surface, #fff), 0 0 0 5px var(--border, #e4e9f0);
}
.pp-who b { display: block; font-size: 16px; color: var(--text-primary, #101828); }
.pp-who small { font-size: 12.5px; color: var(--text-muted, #667085); }
.pp-err { margin: 0; font-size: 12.5px; color: var(--danger, #B42318); }
.pp-foot { display: flex; align-items: center; gap: 8px; padding: 14px 20px; border-top: 1px solid var(--border, #e4e9f0); }
.pp-sp { flex: 1; }
.pp-btn { height: 38px; padding: 0 14px; border-radius: 8px; border: 1px solid var(--border, #e4e9f0); background: var(--bg-surface, #fff);
    color: var(--text-primary, #101828); font-size: 13px; font-weight: 600; display: inline-flex; align-items: center; gap: 7px; cursor: pointer; }
.pp-btn:hover { background: var(--bg-subtle, #f0f3f8); }
.pp-btn svg { width: 15px; height: 15px; }
.pp-btn-pri { background: var(--brand, #1668DC); border-color: var(--brand, #1668DC); color: #fff; }
.pp-btn-pri:hover { background: var(--brand-strong, #1257BC); }
.pp-btn-ghost { color: var(--text-secondary, #344054); }
.pp-btn:disabled { opacity: .6; cursor: progress; }
</style>

<script>
(function () {
    const pick = document.getElementById('ppPick'), file = document.getElementById('ppFile');
    const remove = document.getElementById('ppRemove'), err = document.getElementById('ppErr');
    if (!pick || !file) return;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const url  = @json(route('profile.photo.store'));
    const hasGoogle = @json($hasGoogle);
    const initial   = @json($me?->initial() ?? 'A');

    const say = m => { err.textContent = m; err.hidden = !m; };

    // Every avatar of mine on this page: the top bar and this dialog.
    function show(src) {
        document.querySelectorAll('[data-me-avatar], #ppFace').forEach(box => {
            box.innerHTML = src ? `<img class="u-av-img" src="${src}" alt="" referrerpolicy="no-referrer">` : initial;
        });
    }

    // Square, centred, 256px — small enough to keep in the account row.
    function shrink(f) {
        return new Promise((resolve, reject) => {
            const img = new Image();
            img.onload = () => {
                const side = Math.min(img.width, img.height), c = document.createElement('canvas');
                c.width = c.height = 256;
                c.getContext('2d').drawImage(img, (img.width - side) / 2, (img.height - side) / 2, side, side, 0, 0, 256, 256);
                URL.revokeObjectURL(img.src);
                resolve(c.toDataURL('image/jpeg', 0.86));
            };
            img.onerror = () => reject(new Error('read'));
            img.src = URL.createObjectURL(f);
        });
    }

    pick.addEventListener('click', () => file.click());

    file.addEventListener('change', async () => {
        const f = file.files[0];
        file.value = '';
        if (!f) return;
        if (!/^image\/(png|jpeg|webp)$/.test(f.type)) { say(@json(__('Choose a JPG, PNG or WebP picture.'))); return; }
        say('');
        pick.disabled = true;
        try {
            const photo = await shrink(f);
            const res = await fetch(url, {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify({ photo }),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || !data.success) throw new Error(data.message || @json(__('The photo could not be saved.')));
            show(data.avatar);
            document.getElementById('ppSource').textContent = @json(__('Your own photo'));
            remove.hidden = false;
            remove.textContent = hasGoogle ? @json(__('Use Google photo')) : @json(__('Remove photo'));
            pick.lastChild.textContent = @json(__('Change photo'));
            window.Notify?.success?.(data.message);
        } catch (e) {
            say(e.message === 'read' ? @json(__('That picture could not be read.')) : e.message);
        } finally {
            pick.disabled = false;
        }
    });

    remove?.addEventListener('click', async () => {
        remove.disabled = true;
        try {
            const res = await fetch(url, {
                method: 'DELETE', credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || !data.success) throw new Error(data.message || @json(__('That could not be done.')));
            show(data.avatar);
            document.getElementById('ppSource').textContent = data.avatar ? @json(__('From your Google account')) : @json(__('No picture yet — showing your initial'));
            remove.hidden = true;
            pick.lastChild.textContent = data.avatar ? @json(__('Change photo')) : @json(__('Upload photo'));
            window.Notify?.success?.(data.message);
        } catch (e) {
            say(e.message);
        } finally {
            remove.disabled = false;
        }
    });
})();
</script>
