{{-- The other sites a worker also works at, besides the main one (Michael,
     2026-10-21: "allowing employee have a multiple sites"). One chip per site,
     ticked to add it; the kiosk at any of them takes the worker's scans. The
     main site's chip is marked and cannot be ticked: it is theirs already.

     Shared by Register employee and Edit employee, so the two post the same
     field. The blank first entry makes "none ticked" arrive as none, rather
     than looking like a form that has no picker (which leaves them alone).

     $sites   every site
     $chosen  the ids ticked now
     $main    the id of the main-site select it follows --}}
@php $chosen = array_map('intval', $chosen ?? []); @endphp
<div class="osx" data-main="{{ $main ?? 'site_select' }}" role="group" aria-labelledby="{{ $labelId ?? 'osxLabel' }}">
    <input type="hidden" name="other_site_ids[]" value="">
    @forelse($sites as $s)
        <label class="osx-chip"><input type="checkbox" name="other_site_ids[]" value="{{ $s->id }}"{{ in_array((int) $s->id, $chosen, true) ? ' checked' : '' }}><span>{{ $s->name }}</span></label>
    @empty
        <span class="osx-none">{{ __('No sites yet. Add them on the Sites page.') }}</span>
    @endforelse
</div>

@once
@push('styles')
<style>
.osx { display: flex; flex-wrap: wrap; gap: 6px; }
.osx-chip { position: relative; display: inline-flex; margin: 0; cursor: pointer; }
.osx-chip input { position: absolute; opacity: 0; width: 1px; height: 1px; pointer-events: none; }
.osx-chip span { display: inline-flex; align-items: center; gap: 6px; height: 32px; padding: 0 12px;
    border: 1px solid var(--border); border-radius: 999px; background: var(--bg-subtle); color: var(--text-primary);
    font-size: 12.5px; font-weight: 600; white-space: nowrap; transition: background .15s, border-color .15s, color .15s; }
.osx-chip span::before { content: '+'; font-weight: 700; color: var(--text-muted); }
.osx-chip:hover span { border-color: var(--border-md, var(--border)); }
.osx-chip input:checked + span { border-color: var(--brand); background: var(--brand-subtle); color: var(--brand); box-shadow: inset 0 0 0 1px var(--brand); }
.osx-chip input:checked + span::before { content: '\2713'; color: inherit; }
.osx-chip input:focus-visible + span { outline: 2px solid var(--brand); outline-offset: 2px; }
.osx-chip.is-main { cursor: default; }
.osx-chip.is-main span { opacity: .6; }
.osx-chip.is-main span::before { content: none; }
.osx-chip.is-main span::after { content: 'Main'; font-size: 10px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: var(--text-muted); }
.osx-none { font-size: 12px; color: var(--text-muted); }
</style>
@endpush
<script>
// The main site's chip follows the main-site select: marked, and not ticked.
(function () {
    document.querySelectorAll('.osx').forEach(function (box) {
        const main = document.getElementById(box.dataset.main);
        const sync = function () {
            box.querySelectorAll('.osx-chip').forEach(function (chip) {
                const input  = chip.querySelector('input');
                const isMain = !!main && main.value !== '' && main.value === input.value;
                chip.classList.toggle('is-main', isMain);
                chip.title = isMain ? 'Their main site' : '';
                input.disabled = isMain;
                if (isMain) input.checked = false;
            });
        };
        if (main) main.addEventListener('change', sync);
        const form = box.closest('form');
        if (form) form.addEventListener('reset', function () { setTimeout(sync, 0); });
        sync();
    });
})();
</script>
@endonce
