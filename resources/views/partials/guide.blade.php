{{-- ── Section guide ───────────────────────────────────────────────────────
     A "?" in the top bar that opens three lines on the section you are in.
     The layout includes this once, and the text comes from config/guides.php
     keyed by the first URL segment — so a section is documented by being
     listed there, and no page template changes to get one.

     A section with no entry renders nothing at all, rather than an empty
     panel: better no button than a button that opens nothing. --}}

@php
    $guideKey = request()->segment(1) ?: 'dashboard';
    $guideAll = config('guides.' . $guideKey);
    $guide    = $guideAll[app()->getLocale()] ?? $guideAll['en'] ?? null;
@endphp

@if($guide)
<button type="button" class="guide-btn" id="guideBtn"
        aria-expanded="false" aria-controls="guidePanel"
        title="{{ __('How this section works') }}" aria-label="{{ __('How this section works') }}">
    <i data-lucide="help-circle"></i>
</button>

<div class="guide-panel" id="guidePanel" role="dialog" aria-labelledby="guideTitle" hidden>
    <div class="guide-head">
        <h6 id="guideTitle">{{ $guide['title'] }}</h6>
        <button type="button" class="guide-close" id="guideClose" aria-label="{{ __('Close') }}">&times;</button>
    </div>
    <ul class="guide-list">
        @foreach($guide['lines'] as $line)
            <li>{{ $line }}</li>
        @endforeach
    </ul>
</div>

<style>
    .guide-btn {
        width: 34px; height: 34px; flex: none;
        display: inline-flex; align-items: center; justify-content: center;
        border: 1px solid var(--border); border-radius: var(--radius-sm, 8px);
        background: var(--bg-subtle); color: var(--text-secondary);
        cursor: pointer; transition: var(--transition, all .16s ease);
    }
    .guide-btn:hover, .guide-btn[aria-expanded="true"] {
        color: var(--brand); border-color: var(--brand); background: var(--brand-subtle);
    }
    .guide-btn i, .guide-btn svg { width: 17px; height: 17px; }

    .guide-panel {
        position: fixed; z-index: 1080;
        top: calc(var(--topbar-height, 60px) + 10px); right: 18px;
        width: min(340px, calc(100vw - 36px));
        padding: 14px 16px 16px;
        background: var(--surface);
        border: 1px solid var(--border); border-radius: var(--radius-lg, 12px);
        box-shadow: var(--shadow-lg, 0 12px 32px rgba(16, 24, 40, .16));
    }
    .guide-head { display: flex; align-items: flex-start; gap: 10px; margin-bottom: 10px; }
    .guide-head h6 { flex: 1; margin: 0; font-size: .95rem; font-weight: 700; color: var(--text-primary); }
    .guide-close {
        border: none; background: none; padding: 0 2px;
        font-size: 20px; line-height: 1; color: var(--text-muted); cursor: pointer;
    }
    .guide-close:hover { color: var(--text-primary); }

    .guide-list { margin: 0; padding-left: 17px; display: flex; flex-direction: column; gap: 9px; }
    .guide-list li { font-size: .83rem; line-height: 1.55; color: var(--text-secondary); }
    .guide-list li::marker { color: var(--brand); }
</style>

<script>
(function () {
    const btn   = document.getElementById('guideBtn');
    const panel = document.getElementById('guidePanel');
    const close = document.getElementById('guideClose');

    function show(open) {
        panel.hidden = !open;
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    btn.addEventListener('click', function (e) {
        e.stopPropagation();
        show(panel.hidden);
    });
    close.addEventListener('click', function () { show(false); btn.focus(); });

    // Reading it is the whole point, so it closes on the next click anywhere
    // else rather than sitting over the page.
    document.addEventListener('click', function (e) {
        if (!panel.hidden && !panel.contains(e.target)) show(false);
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !panel.hidden) { show(false); btn.focus(); }
    });
})();
</script>
@endif
