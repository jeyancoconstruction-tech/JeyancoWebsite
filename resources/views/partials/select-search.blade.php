{{-- A <select data-searchable> gets a box to type a name or ID into
     (public/js/select-search.js). Included once by a page that has one;
     File Leave and New Cash Advance were the first (Michael, 2026-10-15).

     The list's look is the address suggestions' (.ap-list): the same
     surface, border, shadow and brand tint on the row in hand. --}}
<style>
.ss { position: relative; }
.ss .ss-native { position: absolute; inset: 0; width: 100%; height: 100%; opacity: 0; pointer-events: none; }
.ss .ss-input { padding-left: 36px !important; cursor: text !important; text-overflow: ellipsis; }
.ss-glass {
    position: absolute; left: 13px; top: 50%; transform: translateY(-50%); z-index: 1;
    width: 15px; height: 15px; color: var(--text-muted); pointer-events: none; display: flex;
}
.ss-glass svg { width: 100%; height: 100%; }
.ss:focus-within .ss-glass { color: var(--brand); }
.ss-list {
    position: absolute; z-index: 30; left: 0; right: 0; top: calc(100% + 4px);
    max-height: 264px; overflow-y: auto; overscroll-behavior: contain; padding: 4px;
    background: var(--bg-elevated); border: 1px solid var(--border);
    border-radius: var(--radius-sm); box-shadow: var(--shadow-lg);
}
.ss-list[hidden] { display: none; }
.ss-opt {
    display: flex; align-items: center; gap: 10px; padding: 7px 10px;
    border-radius: var(--radius-sm); font-size: 13.5px; color: var(--text-primary); cursor: pointer;
}
.ss-name { flex: 1; min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ss-code { flex: none; font-size: 11.5px; font-weight: 600; color: var(--text-muted); font-variant-numeric: tabular-nums; }
.ss-opt:hover, .ss-opt.is-active { background: var(--brand-subtle); color: var(--brand); }
.ss-opt:hover .ss-code, .ss-opt.is-active .ss-code { color: var(--brand); }
.ss-opt.is-picked .ss-name { font-weight: 700; }
.ss-none { padding: 10px; font-size: 13px; color: var(--text-muted); }
</style>

@push('scripts')
<script src="{{ asset('js/select-search.js') }}?v={{ @filemtime(public_path('js/select-search.js')) ?: '1' }}"></script>
@endpush
