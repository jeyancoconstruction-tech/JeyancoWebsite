@extends('layouts')
@section('page_title', 'Sites')

{{-- Site Management, after Michael's design (saved at docs/sites.html): the
     form and a large map side by side, every site as a card underneath.

     The map is Leaflet on OpenStreetMap, as on the dashboard. The page used to
     ask for Google Maps, and with no GOOGLE_MAPS_API_KEY set it quietly hid the
     map and left a bare text box — so a site's pin could only be set from the
     dashboard. Place search is Photon (photon.komoot.io), which is built for
     search-as-you-type; Nominatim's rules forbid that use. Neither needs a key. --}}

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<style>
.sm-page { --sm-r: 16px; --sm-r-sm: 12px; display: flex; flex-direction: column; gap: 18px; }

/* ── Header ─────────────────────────────────────────────────────────────── */
.sm-titlerow { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.sm-title { margin: 0; font-size: 1.75rem; font-weight: 800; letter-spacing: -.02em; color: var(--text-primary); }
.sm-count {
    font-size: 12.5px; font-weight: 700; font-variant-numeric: tabular-nums;
    color: var(--brand); background: var(--brand-subtle);
    border: 1px solid color-mix(in srgb, var(--brand) 30%, transparent);
    padding: 3px 10px; border-radius: 999px;
}
.sm-sub { margin: 6px 0 0; color: var(--text-secondary); max-width: 72ch; font-size: .92rem; }

/* ── Cards ──────────────────────────────────────────────────────────────── */
.sm-grid { display: grid; grid-template-columns: 400px minmax(0, 1fr); gap: 18px; align-items: stretch; }
.sm-card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--sm-r); min-width: 0; }
.sm-ch {
    display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap;
    padding: 16px 20px; border-bottom: 1px solid var(--border);
}
.sm-ch h2 { margin: 0; font-size: .95rem; font-weight: 700; display: flex; align-items: center; gap: 10px; color: var(--text-primary); min-width: 0; }
.sm-ch h2 i { color: var(--brand); }
.sm-ch h2 span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.sm-hint { font-size: 12px; color: var(--text-muted); }
.sm-body { padding: 20px; display: flex; flex-direction: column; gap: 18px; }

/* ── Fields ─────────────────────────────────────────────────────────────── */
.sm-field { display: flex; flex-direction: column; gap: 8px; }
.sm-label { font-size: .88rem; font-weight: 600; color: var(--text-primary); display: flex; align-items: center; gap: 5px; margin: 0; }
.sm-req { color: var(--danger); }
.sm-input {
    height: 46px; width: 100%; padding: 0 15px; border-radius: var(--sm-r-sm);
    border: 1px solid var(--border-md); background: var(--bg-subtle); color: var(--text-primary);
    font-size: .94rem; outline: none; transition: border-color .15s, box-shadow .15s;
}
.sm-input::placeholder { color: var(--text-muted); opacity: .8; }
.sm-input:focus { border-color: var(--brand); box-shadow: 0 0 0 3px color-mix(in srgb, var(--brand) 20%, transparent); }

.sm-loc { position: relative; }
.sm-loc .sm-lead { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: var(--text-muted); pointer-events: none; font-size: .9rem; }
.sm-loc .sm-input { padding-left: 42px; padding-right: 44px; }
.sm-clear {
    position: absolute; right: 7px; top: 7px; width: 32px; height: 32px; border-radius: 8px;
    border: 0; background: transparent; color: var(--text-muted); cursor: pointer; display: grid; place-items: center;
}
.sm-clear:hover { background: var(--brand-subtle); color: var(--text-primary); }

/* The suggestions. Drawn by the page rather than by a widget, so they read
   like the rest of it in both themes. */
.sm-sugg {
    position: absolute; left: 0; right: 0; top: 52px; z-index: 1100;
    background: var(--bg-elevated); border: 1px solid var(--border-md); border-radius: 14px;
    box-shadow: var(--shadow-xl); padding: 6px; max-height: 340px; overflow: auto;
}
.sm-opt {
    width: 100%; display: flex; gap: 12px; align-items: flex-start; text-align: left;
    padding: 9px 11px; border: 0; border-radius: 10px; background: transparent; color: var(--text-primary); cursor: pointer;
}
.sm-opt[aria-selected="true"], .sm-opt:hover { background: var(--brand-subtle); }
.sm-opt .sm-oi { width: 30px; height: 30px; border-radius: 8px; background: var(--brand-subtle); color: var(--brand); display: grid; place-items: center; flex-shrink: 0; font-size: .82rem; }
.sm-opt.is-typed .sm-oi { background: var(--warning-soft); color: var(--warning); }
.sm-opt .sm-on { display: block; font-weight: 600; font-size: .88rem; }
.sm-opt .sm-on mark { background: none; color: var(--brand); padding: 0; }
.sm-opt .sm-oa { display: block; font-size: 12px; color: var(--text-muted); }
.sm-sugg-foot { font-size: 11.5px; color: var(--text-muted); padding: 6px 11px 4px; }

/* What has been picked, and how wide the on-site circle is. */
.sm-picked { display: flex; flex-direction: column; gap: 14px; padding: 15px; border-radius: 14px; background: var(--bg-subtle); border: 1px solid var(--border); }
.sm-picked-top { display: flex; gap: 12px; align-items: flex-start; }
.sm-picked-ico { width: 36px; height: 36px; border-radius: 10px; background: var(--danger-soft); color: var(--danger); display: grid; place-items: center; flex-shrink: 0; }
.sm-picked-name { font-weight: 700; color: var(--text-primary); overflow-wrap: anywhere; }
.sm-picked-addr { font-size: .82rem; color: var(--text-secondary); overflow-wrap: anywhere; }
.sm-coord { font-family: 'JetBrains Mono', ui-monospace, SFMono-Regular, Consolas, monospace; font-size: 12px; color: var(--brand); font-variant-numeric: tabular-nums; }
.sm-coord > span { white-space: nowrap; }
.sm-warn { font-size: 12.5px; color: var(--warning); background: var(--warning-soft); padding: 8px 10px; border-radius: 10px; }
.sm-radius { display: flex; flex-direction: column; gap: 6px; }
.sm-radius-row { display: flex; justify-content: space-between; align-items: baseline; font-size: .84rem; color: var(--text-secondary); }
.sm-radius-row label { margin: 0; }
.sm-radius-row b { color: var(--text-primary); font-variant-numeric: tabular-nums; }
.sm-radius input[type=range] { width: 100%; accent-color: var(--brand); }
.sm-radius-ends { display: flex; justify-content: space-between; font-size: 11px; color: var(--text-muted); font-variant-numeric: tabular-nums; }

.sm-err { font-size: .82rem; color: var(--danger); }
.sm-actions { display: flex; gap: 10px; }
.sm-btn {
    display: inline-flex; align-items: center; justify-content: center; gap: 8px; height: 46px; padding: 0 18px;
    border-radius: var(--sm-r-sm); font-size: .9rem; font-weight: 600; cursor: pointer;
    border: 1px solid var(--border-md); background: var(--surface); color: var(--text-primary);
}
.sm-btn:hover { background: var(--bg-subtle); }
.sm-btn.is-primary { flex: 1; background: var(--brand); border-color: var(--brand); color: #fff; font-weight: 700; }
.sm-btn.is-primary:hover { background: var(--brand-strong); border-color: var(--brand-strong); }
.sm-btn:disabled { opacity: .6; cursor: not-allowed; }
.sm-btn.is-small { height: 34px; padding: 0 12px; font-size: .82rem; }
.sm-btn.is-danger { background: var(--danger); border-color: var(--danger); color: #fff; }
.sm-btn.is-danger:hover { filter: brightness(1.08); background: var(--danger); }

/* ── Map ────────────────────────────────────────────────────────────────── */
.sm-mapcard { display: flex; flex-direction: column; overflow: hidden; }
.sm-mapbar {
    display: flex; justify-content: space-between; align-items: center; gap: 10px; flex-wrap: wrap;
    padding: 10px 14px; border-bottom: 1px solid var(--border); font-size: 12.5px; color: var(--text-muted);
}
.sm-mapbar b { color: var(--text-primary); font-weight: 600; }
.sm-mapbox { position: relative; flex: 1; min-height: clamp(420px, calc(100vh - 250px), 820px); }
.sm-map { position: absolute; inset: 0; cursor: crosshair; }
/* The map fills its card edge to edge; the card already draws the frame.
   Stated at this weight because ui-fixes.css makes every .leaflet-container
   position: relative, and the theme files give it a border and a radius. */
.sm-page .sm-map.leaflet-container { position: absolute; inset: 0; border: 0; border-radius: 0; background: var(--bg-subtle); }
.sm-page .sm-map .leaflet-interactive { cursor: pointer; }
/* Dark tiles in the dark theme, so the map is not a white slab on a navy
   page. The light theme keeps OpenStreetMap's own colours untouched. */
html[data-bs-theme="dark"] .sm-map .leaflet-tile-pane { filter: invert(1) hue-rotate(180deg) brightness(.9) contrast(.88) saturate(.55); }
html[data-bs-theme="light"] .sm-map .leaflet-tile-pane { filter: none; }
.sm-pin { background: none; border: 0; }
.sm-pin svg { display: block; filter: drop-shadow(0 2px 3px rgba(0,0,0,.35)); }
.sm-maptip {
    position: absolute; left: 10px; bottom: 22px; z-index: 500; pointer-events: none;
    font-size: 12px; color: var(--text-secondary); background: color-mix(in srgb, var(--surface) 92%, transparent);
    border: 1px solid var(--border-md); padding: 6px 10px; border-radius: 8px;
}
.sm-veil {
    position: absolute; inset: 0; z-index: 600; display: grid; place-items: center; text-align: center; padding: 24px; cursor: pointer;
    background: color-mix(in srgb, var(--bg) 78%, transparent); border: 0; width: 100%; color: inherit;
}
.sm-veil-in { max-width: 34ch; color: var(--text-secondary); font-size: .9rem; display: flex; flex-direction: column; align-items: center; gap: 10px; }
.sm-veil-in i { font-size: 1.9rem; color: var(--brand); }
.sm-veil-in b { color: var(--text-primary); font-size: 1rem; }

/* ── Site cards ─────────────────────────────────────────────────────────── */
.sm-sites { display: grid; grid-template-columns: repeat(auto-fill, minmax(360px, 1fr)); gap: 10px; padding: 16px; }
.sm-site { padding: 14px 15px; border-radius: 14px; background: var(--bg-subtle); border: 1px solid var(--border); display: flex; flex-direction: column; gap: 10px; min-width: 0; }
.sm-site.is-new, .sm-site.is-editing { border-color: var(--brand); background: var(--brand-subtle); }
.sm-srow { display: flex; align-items: flex-start; gap: 12px; }
.sm-smark { width: 36px; height: 36px; border-radius: 10px; display: grid; place-items: center; flex-shrink: 0; border: 0; }
.sm-smark.has { background: var(--danger-soft); color: var(--danger); cursor: pointer; }
.sm-smark.has:hover { outline: 2px solid color-mix(in srgb, var(--danger) 40%, transparent); }
.sm-smark.none { background: var(--surface); color: var(--text-muted); border: 1px solid var(--border); }
.sm-sinfo { flex: 1; min-width: 0; }
/* The headcount rides on the name's line, so the address gets the card's
   width instead of wrapping into a column beside a chip. */
.sm-shead { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 2px; }
.sm-sname { font-weight: 700; font-size: .95rem; color: var(--text-primary); overflow-wrap: anywhere; min-width: 0; }
.sm-saddr { font-size: .82rem; color: var(--text-secondary); overflow-wrap: anywhere; }
.sm-smeta { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 4px; font-size: 12px; color: var(--text-muted); align-items: center; }
.sm-nogps { color: var(--warning); }
.sm-emps { font-size: 11.5px; font-weight: 700; padding: 2px 9px; border-radius: 999px; white-space: nowrap; font-variant-numeric: tabular-nums; }
.sm-emps.some { background: var(--success-soft); color: var(--success); }
.sm-emps.zero { background: var(--surface); color: var(--text-muted); border: 1px solid var(--border); }
.sm-acts { display: flex; align-items: center; gap: 2px; }
.sm-ic { width: 34px; height: 34px; border-radius: 9px; border: 1px solid transparent; background: transparent; display: grid; place-items: center; cursor: pointer; }
.sm-ic.edit { color: var(--warning); }
.sm-ic.del  { color: var(--danger); }
.sm-ic:hover { background: var(--surface); border-color: var(--border-md); }
.sm-confirm {
    font-size: .84rem; color: var(--text-primary); background: var(--danger-soft);
    border: 1px solid color-mix(in srgb, var(--danger) 35%, transparent); border-radius: 12px; padding: 12px;
    display: flex; flex-direction: column; gap: 10px;
}
.sm-confirm-btns { display: flex; gap: 8px; flex-wrap: wrap; }
.sm-empty, .sm-loading { grid-column: 1 / -1; text-align: center; padding: 28px 0; color: var(--text-muted); font-size: .88rem; }

button.sm-btn:focus-visible, button.sm-ic:focus-visible, button.sm-smark:focus-visible, button.sm-clear:focus-visible, .sm-opt:focus-visible {
    outline: 2px solid var(--brand); outline-offset: 2px;
}

@media (max-width: 1050px) {
    .sm-grid { grid-template-columns: 1fr; }
    .sm-mapbox { min-height: 420px; }
}
@media (max-width: 640px) {
    .sm-title { font-size: 1.5rem; }
    .sm-body { padding: 16px; }
    .sm-ch { padding: 14px 16px; }
    .sm-sites { grid-template-columns: 1fr; padding: 12px; }
    .sm-mapbox { min-height: 340px; }
}
</style>
@endpush

@section('content')
<div class="sm-page">

    <header>
        <div class="sm-titlerow">
            <h1 class="sm-title">{{ __('Site Management') }}</h1>
            <span class="sm-count" id="smCount">—</span>
        </div>
        <p class="sm-sub">{{ __('Add a project site and pin its exact spot. The pin and radius set where GPS attendance counts as on-site.') }}</p>
    </header>

    <div class="sm-grid">

        {{-- ── The form: add a site, or edit the one picked below ─────────── --}}
        <form class="sm-card" id="smForm" novalidate autocomplete="off">
            <div class="sm-ch">
                <h2><i class="fas fa-circle-plus" id="smFormIcon"></i><span id="smFormTitle">{{ __('Add New Site') }}</span></h2>
                <span class="sm-hint" id="smFormHint">{{ __('Step 1 name · Step 2 location') }}</span>
            </div>
            <div class="sm-body">
                <div class="sm-field">
                    <label class="sm-label" for="smName">{{ __('Project name') }} <span class="sm-req">*</span></label>
                    <input id="smName" class="sm-input" type="text" maxlength="100"
                           placeholder="{{ __('e.g., Tower 2 — Riverside') }}">
                </div>

                <div class="sm-field">
                    <label class="sm-label" for="smLoc">{{ __('Location') }} <span class="sm-req">*</span></label>
                    <div class="sm-loc">
                        <i class="fas fa-magnifying-glass sm-lead" aria-hidden="true"></i>
                        <input id="smLoc" class="sm-input" type="text" maxlength="255"
                               placeholder="{{ __('Type a place or address, or pin it on the map') }}"
                               role="combobox" aria-expanded="false" aria-controls="smSugg" aria-autocomplete="list">
                        <button type="button" class="sm-clear" id="smClear" aria-label="{{ __('Clear location') }}" hidden><i class="fas fa-xmark"></i></button>
                        <div class="sm-sugg" id="smSugg" role="listbox" hidden></div>
                    </div>
                </div>

                <div class="sm-picked" id="smPicked" hidden>
                    <div class="sm-picked-top">
                        <span class="sm-picked-ico"><i class="fas fa-location-dot"></i></span>
                        <div style="min-width:0">
                            <div class="sm-picked-name" id="smPkName"></div>
                            <div class="sm-picked-addr" id="smPkAddr"></div>
                            <div class="sm-coord" id="smPkCoord"></div>
                        </div>
                    </div>
                    <div class="sm-warn" id="smPkWarn" hidden>{{ __('No GPS point yet. Tap the map to pin the exact spot, or GPS attendance can’t check this site.') }}</div>
                    <div class="sm-radius" id="smRadiusBox" hidden>
                        <div class="sm-radius-row">
                            <label for="smRadius">{{ __('On-site radius (GPS geofence)') }}</label>
                            <b id="smRadiusVal"></b>
                        </div>
                        <input id="smRadius" type="range" min="{{ $radiusMin }}" max="{{ $radiusMax }}" step="25">
                        <div class="sm-radius-ends"><span>{{ $radiusMin }} m</span><span>{{ $radiusMax }} m</span></div>
                    </div>
                </div>

                <div class="sm-err" id="smErr" role="alert" hidden></div>

                <div class="sm-actions">
                    <button type="submit" class="sm-btn is-primary" id="smSubmit">
                        <i class="fas fa-plus"></i><span id="smSubmitLabel">{{ __('Add site') }}</span>
                    </button>
                    <button type="button" class="sm-btn" id="smCancel" hidden>{{ __('Cancel') }}</button>
                </div>
            </div>
        </form>

        {{-- ── The map ──────────────────────────────────────────────────────── --}}
        <section class="sm-card sm-mapcard" aria-label="{{ __('Map') }}">
            <div class="sm-mapbar">
                <span>{{ __('Map') }} · <b id="smArea">Naga City, Camarines Sur</b></span>
                <span>{{ __('Tap anywhere to move the pin') }}</span>
            </div>
            <div class="sm-mapbox">
                <div class="sm-map" id="smMap" aria-label="{{ __('Map. Click to place the site pin.') }}"></div>
                <div class="sm-maptip" id="smTip">{{ __('Tap to drop a pin') }}</div>
                <button type="button" class="sm-veil" id="smVeil">
                    <span class="sm-veil-in">
                        <i class="fas fa-map-location-dot"></i>
                        <b>{{ __('Add a project name to open the map') }}</b>
                        <span>{{ __('Then search for a place, or tap the map to drop a pin.') }}</span>
                    </span>
                </button>
            </div>
        </section>
    </div>

    {{-- ── Every site ───────────────────────────────────────────────────────── --}}
    <section class="sm-card">
        <div class="sm-ch">
            <h2><i class="fas fa-layer-group"></i><span>{{ __('All Sites') }}</span></h2>
            <span class="sm-hint">{{ __('Employees move to Unassigned when a site is removed') }}</span>
        </div>
        <div class="sm-sites" id="smSites">
            <div class="sm-loading"><span class="spinner-border spinner-border-sm text-primary me-2" role="status"></span>{{ __('Loading sites…') }}</div>
        </div>
    </section>
</div>
@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function () {
    'use strict';
    const $    = id => document.getElementById(id);
    const CSRF = document.querySelector('meta[name="csrf-token"]').content;
    const URLS = {
        list:  @json(route('sites.list')),
        store: @json(route('sites.store')),
        site:  id => @json(url('/sites')) + '/' + id,
    };
    const RADIUS = { def: @json($radiusDefault), min: @json($radiusMin), max: @json($radiusMax), step: 25 };
    const NAGA   = { lat: 13.6218, lng: 123.1948 };   // the dashboard's default centre
    const PHOTON = 'https://photon.komoot.io';
    const PH_BOX = '116.9,4.5,126.7,21.2';             // search the Philippines only

    const snap = r => {
        const v = Math.round((Number(r) || RADIUS.def) / RADIUS.step) * RADIUS.step;
        return Math.min(RADIUS.max, Math.max(RADIUS.min, v));
    };

    // Everything the page is showing. Rendering reads it; nothing else does.
    const S = {
        sites: [], loaded: false, editing: null, confirm: null, flash: null,
        pin: null,        // { lat, lng } — the GPS point that will be saved
        place: null,      // the suggestion picked, so a small nudge keeps its name
        name: '', addr: '', label: '',   // what the summary shows, and the text saved
        typed: false,     // the address is the words typed, not a place found
        radius: snap(RADIUS.def),
        items: [], active: -1, searching: false, failed: false,
        opened: false, saving: false, geo: 0,
    };

    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const fmtLat = lat => Math.abs(lat).toFixed(5) + '° ' + (lat >= 0 ? 'N' : 'S');
    const fmtLng = lng => Math.abs(lng).toFixed(5) + '° ' + (lng >= 0 ? 'E' : 'W');
    const fmt = (lat, lng) => fmtLat(lat) + ', ' + fmtLng(lng);
    // On a narrow card the pair breaks after the comma, never inside a number.
    const fmtHtml = (lat, lng) => `<span class="sm-coord"><span>${fmtLat(lat)},</span> <span>${fmtLng(lng)}</span></span>`;
    const dist = (a, b) => {
        const dy = (a.lat - b.lat) * 111320;
        const dx = (a.lng - b.lng) * 111320 * Math.cos(a.lat * Math.PI / 180);
        return Math.hypot(dx, dy);
    };
    const far = m => m < 1000 ? Math.round(m) + ' m' : (m / 1000).toFixed(m < 10000 ? 1 : 0) + ' km';
    const cssVar = name => getComputedStyle(document.documentElement).getPropertyValue(name).trim() || '#1668DC';
    const pinned = s => s && s.latitude != null && s.longitude != null;
    const byId = id => S.sites.find(s => s.id === id);

    // ── The map ──────────────────────────────────────────────────────────────
    const map = L.map('smMap', { zoomControl: false }).setView([NAGA.lat, NAGA.lng], 14);
    L.control.zoom({ position: 'topright' }).addTo(map);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors', maxZoom: 19,
    }).addTo(map);

    const pinIcon = L.divIcon({
        className: 'sm-pin', iconSize: [28, 38], iconAnchor: [14, 37],
        html: '<svg width="28" height="38" viewBox="0 0 28 38" aria-hidden="true"><path d="M14 37C14 37 1.5 22.6 1.5 13.8a12.5 12.5 0 0 1 25 0C26.5 22.6 14 37 14 37z" fill="#ef4444" stroke="#fff" stroke-width="1.6"/><circle cx="14" cy="13.5" r="4.6" fill="#fff"/></svg>',
    });
    let pinMarker = null, pinCircle = null;
    const others = L.layerGroup().addTo(map);

    function drawPin() {
        if (!S.pin) {
            if (pinMarker)  { map.removeLayer(pinMarker);  pinMarker = null; }
            if (pinCircle)  { map.removeLayer(pinCircle);  pinCircle = null; }
            return;
        }
        const at = [S.pin.lat, S.pin.lng];
        const brand = cssVar('--brand');
        if (!pinCircle) {
            pinCircle = L.circle(at, { radius: S.radius, color: brand, weight: 1.5, dashArray: '5 4', fillColor: brand, fillOpacity: .14, interactive: false }).addTo(map);
        } else {
            pinCircle.setLatLng(at).setRadius(S.radius).setStyle({ color: brand, fillColor: brand });
        }
        if (!pinMarker) {
            pinMarker = L.marker(at, { icon: pinIcon, draggable: true, keyboard: false, zIndexOffset: 1000 }).addTo(map);
            pinMarker.on('drag', e => pinCircle && pinCircle.setLatLng(e.target.getLatLng()));
            pinMarker.on('dragend', e => { const p = e.target.getLatLng(); dropPin(p.lat, p.lng); });
        } else {
            pinMarker.setLatLng(at);
        }
    }

    // The other sites, faint, so a new one is not dropped on top of an old one.
    function drawOthers() {
        others.clearLayers();
        const ink = cssVar('--text-muted'), paper = cssVar('--surface');
        S.sites.filter(s => pinned(s) && s.id !== S.editing).forEach(s => {
            L.circleMarker([s.latitude, s.longitude], {
                radius: 6, color: ink, weight: 2, fillColor: paper, fillOpacity: 1, bubblingMouseEvents: false,
            }).bindTooltip(esc(s.name), { direction: 'top', offset: [0, -6] }).addTo(others);
        });
    }

    function openMap() {
        if (S.opened) return;
        S.opened = true;
        $('smVeil').hidden = true;
        map.invalidateSize();
    }

    map.on('click', e => {
        if (!S.opened) return;
        closeSugg();
        dropPin(e.latlng.lat, e.latlng.lng);
    });
    if (window.ResizeObserver) new ResizeObserver(() => map.invalidateSize()).observe($('smMap'));
    // The ring and the other sites take their colours from the theme.
    new MutationObserver(() => { drawPin(); drawOthers(); })
        .observe(document.documentElement, { attributes: true, attributeFilter: ['data-bs-theme'] });

    // ── Putting the pin down ─────────────────────────────────────────────────
    function dropPin(lat, lng) {
        S.pin = { lat, lng };
        hideErr();

        // A nudge off a place that was picked keeps the place: moving the pin
        // to the site gate should not rename SM City Naga to "Pinned location".
        if (S.place && dist(S.pin, S.place) <= 250) { render(); return; }
        S.place = null;

        // Typed words stay the address; the pin only adds where it is.
        if (S.typed) { render(); return; }

        S.name  = @json(__('Pinned location'));
        S.addr  = @json(__('Finding the address…'));
        S.label = 'Pinned at ' + fmt(lat, lng);
        $('smLoc').value = S.label;
        render();

        const ticket = ++S.geo;
        fetch(`${PHOTON}/reverse?lat=${lat}&lon=${lng}&limit=5`)
            .then(r => r.ok ? r.json() : Promise.reject())
            .then(d => {
                if (ticket !== S.geo || S.typed) return;
                // The nearest thing with a name: the closest hit is often a
                // bare house number, and "At Door 3 Seton Road" says less
                // than "~20 m from Plaza Medica Annex, Seton Road".
                const feats = d.features || [];
                const p = describe(feats.find(f => f.properties && f.properties.name) || feats[0]);
                if (!p) throw new Error('none');
                const d2 = dist(S.pin, p);
                S.addr  = (d2 < 30 ? 'At ' : '~' + far(d2) + ' from ') + p.full;
                S.label = S.addr;
                if (p.area) $('smArea').textContent = p.area;
                if ($('smLoc').value.startsWith('Pinned at ')) $('smLoc').value = S.label;
                render();
            })
            .catch(() => {
                if (ticket !== S.geo) return;
                S.addr = @json(__('No address found here. The coordinates are saved.'));
                render();
            });
    }

    // ── Place search ─────────────────────────────────────────────────────────
    /** A Photon result as a name, an address line and the area it sits in. */
    function describe(f) {
        if (!f || !f.geometry) return null;
        const p = f.properties || {};
        const [lng, lat] = f.geometry.coordinates;
        const street = [p.housenumber, p.street].filter(Boolean).join(' ');
        const name = p.name || street || p.locality || p.city || p.county || p.state;
        if (!name) return null;
        const seen = new Set([name.toLowerCase()]);
        const parts = [street, p.locality, p.district, p.city, p.county, p.state].filter(x => {
            if (!x || seen.has(x.toLowerCase())) return false;
            seen.add(x.toLowerCase());
            return true;
        });
        const addr = parts.join(', ');
        return {
            lat, lng, name, addr,
            full: addr ? name + ', ' + addr : name,
            area: [p.city || p.county || p.district, p.state].filter(Boolean).join(', '),
        };
    }

    let searchTimer = null, searchCtl = null;
    function onType() {
        const q = $('smLoc').value.trim();
        S.typed = false;
        $('smClear').hidden = !q && !S.pin;
        clearTimeout(searchTimer);
        if (searchCtl) searchCtl.abort();
        if (!q) { closeSugg(); return; }

        S.items = [{ type: 'typed', q }];
        S.active = -1;
        S.failed = false;
        S.searching = q.length >= 3;
        showSugg();
        if (S.searching) searchTimer = setTimeout(() => search(q), 280);
    }

    async function search(q) {
        searchCtl = new AbortController();
        const c = map.getCenter();
        const url = `${PHOTON}/api/?q=${encodeURIComponent(q)}&lat=${c.lat.toFixed(4)}&lon=${c.lng.toFixed(4)}&limit=8&bbox=${PH_BOX}`;
        let places = [];
        try {
            const r = await fetch(url, { signal: searchCtl.signal });
            if (!r.ok) throw new Error('http');
            const d = await r.json();
            const seen = new Set();
            places = (d.features || []).map(describe).filter(p => {
                if (!p || seen.has(p.full)) return false;
                seen.add(p.full);
                return true;
            });
            // Closest to the map first, the way the office is looking at it.
            places.sort((a, b) => dist(a, c) - dist(b, c));
            S.failed = false;
        } catch (e) {
            if (e.name === 'AbortError') return;
            S.failed = true;
        }
        if (q !== $('smLoc').value.trim()) return;
        S.searching = false;
        S.items = places.slice(0, 5).map(p => ({ type: 'place', p })).concat([{ type: 'typed', q }]);
        S.active = places.length ? 0 : -1;
        showSugg();
    }

    function hl(s, q) {
        const i = s.toLowerCase().indexOf(q.toLowerCase());
        if (!q || i < 0) return esc(s);
        return esc(s.slice(0, i)) + '<mark>' + esc(s.slice(i, i + q.length)) + '</mark>' + esc(s.slice(i + q.length));
    }

    function showSugg() {
        const q = $('smLoc').value.trim();
        const c = map.getCenter();
        const html = S.items.map((it, i) => {
            const sel = i === S.active;
            if (it.type === 'place') {
                return `<button type="button" class="sm-opt" role="option" id="smOpt${i}" data-i="${i}" aria-selected="${sel}">
                    <span class="sm-oi"><i class="fas fa-location-dot"></i></span>
                    <span><span class="sm-on">${hl(it.p.name, q)}</span>
                    <span class="sm-oa">${esc(it.p.addr || it.p.area)}${it.p.addr ? ' · ' : ''}${far(dist(it.p, c))} away</span></span></button>`;
            }
            return `<button type="button" class="sm-opt is-typed" role="option" id="smOpt${i}" data-i="${i}" aria-selected="${sel}">
                <span class="sm-oi"><i class="fas fa-pen"></i></span>
                <span><span class="sm-on">Use “${esc(it.q)}” as typed</span>
                <span class="sm-oa">Then tap the map to pin the exact spot</span></span></button>`;
        }).join('');
        const foot = S.searching ? 'Searching for places…'
            : S.failed ? 'Place search is not answering. Use the address as typed, then tap the map.'
            : S.items.length > 1 ? 'Closest to the map area first'
            : q.length < 3 ? 'Keep typing to search for places'
            : 'No place found by that name. Use it as typed, then tap the map.';

        const box = $('smSugg');
        box.innerHTML = html + `<div class="sm-sugg-foot">${foot}</div>`;
        box.querySelectorAll('.sm-opt').forEach(b => b.addEventListener('mousedown', e => { e.preventDefault(); choose(+b.dataset.i); }));
        box.hidden = false;
        $('smLoc').setAttribute('aria-expanded', 'true');
        if (S.active >= 0) $('smLoc').setAttribute('aria-activedescendant', 'smOpt' + S.active);
        else $('smLoc').removeAttribute('aria-activedescendant');
    }

    function closeSugg() {
        $('smSugg').hidden = true;
        $('smLoc').setAttribute('aria-expanded', 'false');
        $('smLoc').removeAttribute('aria-activedescendant');
    }

    function choose(i) {
        const it = S.items[i];
        if (!it) return;
        openMap();
        S.geo++;              // a reverse lookup still on its way is now stale
        hideErr();
        if (it.type === 'place') {
            const p = it.p;
            S.typed = false;
            S.place = { lat: p.lat, lng: p.lng };
            S.pin   = { lat: p.lat, lng: p.lng };
            S.name  = p.name;
            S.addr  = p.addr || p.area;
            S.label = p.full;
            $('smLoc').value = p.full;
            if (p.area) $('smArea').textContent = p.area;
            map.setView([p.lat, p.lng], 17);
        } else {
            S.typed = true;
            S.place = null;
            S.name  = it.q;
            S.addr  = @json(__('Typed address'));
            S.label = it.q;
        }
        closeSugg();
        render();
    }

    $('smLoc').addEventListener('input', onType);
    $('smLoc').addEventListener('focus', () => { openMap(); if ($('smLoc').value.trim() && !S.pin && !S.typed) onType(); });
    $('smLoc').addEventListener('blur', () => setTimeout(closeSugg, 120));
    $('smLoc').addEventListener('keydown', e => {
        if ($('smSugg').hidden) return;
        const n = S.items.length;
        if (e.key === 'ArrowDown')      { e.preventDefault(); S.active = (S.active + 1) % n; showSugg(); }
        else if (e.key === 'ArrowUp')   { e.preventDefault(); S.active = (S.active - 1 + n) % n; showSugg(); }
        else if (e.key === 'Enter')     { e.preventDefault(); choose(Math.max(0, S.active)); }
        else if (e.key === 'Escape')    { closeSugg(); }
    });
    $('smClear').addEventListener('click', () => {
        clearPick();
        $('smLoc').focus();
    });

    function clearPick() {
        S.geo++;
        S.pin = null; S.place = null; S.typed = false;
        S.name = ''; S.addr = ''; S.label = '';
        $('smLoc').value = '';
        closeSugg();
        render();
    }

    // Step 1 before step 2: the map opens once the site has a name.
    $('smName').addEventListener('input', () => { if ($('smName').value.trim()) openMap(); hideErr(); });
    $('smVeil').addEventListener('click', () => {
        if ($('smName').value.trim()) { openMap(); $('smLoc').focus(); }
        else $('smName').focus();
    });

    $('smRadius').addEventListener('input', e => {
        S.radius = snap(e.target.value);
        $('smRadiusVal').textContent = S.radius + ' m';
        drawPin();
    });

    // ── The form's own state ─────────────────────────────────────────────────
    function render() {
        const box = $('smPicked');
        const shown = !!(S.pin || S.typed);
        box.hidden = !shown;
        if (shown) {
            $('smPkName').textContent  = S.name;
            $('smPkAddr').textContent  = S.addr;
            $('smPkCoord').textContent = S.pin ? fmt(S.pin.lat, S.pin.lng) : '';
        }
        $('smPkWarn').hidden    = !!S.pin;
        $('smRadiusBox').hidden = !S.pin;
        $('smRadius').value     = S.radius;
        $('smRadiusVal').textContent = S.radius + ' m';
        $('smClear').hidden = !$('smLoc').value.trim() && !S.pin;
        $('smTip').textContent = S.pin ? 'Tap or drag to move the pin' : 'Tap to drop a pin';

        const editing = S.editing != null ? byId(S.editing) : null;
        $('smFormTitle').textContent = editing ? 'Edit ' + editing.name : @json(__('Add New Site'));
        $('smFormHint').textContent  = editing ? 'Move the pin or change the radius, then save' : @json(__('Step 1 name · Step 2 location'));
        $('smFormIcon').className    = editing ? 'fas fa-pen' : 'fas fa-circle-plus';
        $('smSubmitLabel').textContent = editing ? 'Save changes' : @json(__('Add site'));
        $('smSubmit').querySelector('i').className = S.saving ? 'fas fa-spinner fa-spin' : (editing ? 'fas fa-check' : 'fas fa-plus');
        $('smSubmit').disabled = S.saving;
        $('smCancel').hidden = !editing;

        drawPin();
    }

    function showErr(m) { $('smErr').textContent = m; $('smErr').hidden = false; }
    function hideErr()  { $('smErr').hidden = true; }

    function resetForm() {
        S.editing = null;
        S.radius = snap(RADIUS.def);
        $('smName').value = '';
        clearPick();
        hideErr();
        drawOthers();
        renderSites();
    }

    $('smCancel').addEventListener('click', resetForm);

    // ── Talking to the server ────────────────────────────────────────────────
    async function req(url, method, body) {
        const r = await fetch(url, {
            method,
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
            body: body ? JSON.stringify(body) : undefined,
        });
        let data = {};
        try { data = await r.json(); } catch (e) {}
        if (!r.ok || data.success === false) {
            const first = data.errors ? Object.values(data.errors)[0] : null;
            throw new Error((first && first[0]) || data.message || 'Something went wrong. Try again.');
        }
        return data;
    }

    $('smForm').addEventListener('submit', async e => {
        e.preventDefault();
        if (S.saving) return;
        closeSugg();

        const name = $('smName').value.trim();
        const text = $('smLoc').value.trim();
        if (!name) { showErr('Add a project name first.'); $('smName').focus(); return; }
        if (!S.pin && !text) { showErr('Pick a place from the suggestions, type an address, or tap the map to pin it.'); $('smLoc').focus(); return; }

        // Words typed over a pin are the address that is saved; the pin stays.
        const location = (S.pin && (!text || text === S.label)) ? S.label : text;
        const body = {
            name, location,
            latitude:  S.pin ? +S.pin.lat.toFixed(7) : null,
            longitude: S.pin ? +S.pin.lng.toFixed(7) : null,
        };
        if (S.pin) body.geofence_radius = S.radius;

        const editing = S.editing;
        S.saving = true; hideErr(); render();
        try {
            const data = editing != null
                ? await req(URLS.site(editing), 'PUT', body)
                : await req(URLS.store, 'POST', body);
            Notify.success(editing != null
                ? `"${data.site.name}" saved.`
                : `"${data.site.name}" added.` + (S.pin ? '' : ' Pin it on the map so GPS attendance can check it.'));
            S.flash = data.site.id;
            S.saving = false;
            resetForm();
            await loadSites();
        } catch (err) {
            S.saving = false;
            showErr(err.message);
            render();
        }
    });

    // ── The list ─────────────────────────────────────────────────────────────
    async function loadSites() {
        try {
            const data = await req(URLS.list, 'GET');
            const first = !S.loaded;
            S.sites = data.sites || [];
            S.loaded = true;
            if (S.editing != null && !byId(S.editing)) resetForm();
            renderSites();
            drawOthers();
            if (first) frameSites();
        } catch (e) {
            if (!S.loaded) $('smSites').innerHTML = '<div class="sm-empty">Could not load the sites. Refresh the page to try again.</div>';
        }
    }

    // First look: every pinned site in view, or Naga when none is pinned yet.
    function frameSites() {
        if (S.pin) return;
        const pts = S.sites.filter(pinned).map(s => [s.latitude, s.longitude]);
        if (pts.length === 1) map.setView(pts[0], 16);
        else if (pts.length > 1) map.fitBounds(pts, { padding: [50, 50], maxZoom: 16 });
        if (pts.length) $('smArea').textContent = pts.length === 1 ? S.sites.find(pinned).name : 'All pinned sites';
    }

    function renderSites() {
        const n = S.sites.length;
        $('smCount').textContent = n + (n === 1 ? ' site' : ' sites');
        if (!n) {
            $('smSites').innerHTML = '<div class="sm-empty">No sites yet. Add the first one above.</div>';
            return;
        }
        $('smSites').innerHTML = S.sites.map(s => {
            const has = pinned(s);
            const e = s.employees_count || 0;
            const cls = s.id === S.editing ? ' is-editing' : (s.id === S.flash ? ' is-new' : '');
            const confirm = S.confirm === s.id
                ? `<div class="sm-confirm" role="alert">
                       <span>Remove <b>${esc(s.name)}</b>?${e ? ` ${e} ${e === 1 ? 'employee moves' : 'employees move'} to Unassigned.` : ''} Its past attendance will show no site.</span>
                       <div class="sm-confirm-btns">
                           <button type="button" class="sm-btn is-small is-danger" data-yes="${s.id}"><i class="fas fa-trash-can"></i>Remove site</button>
                           <button type="button" class="sm-btn is-small" data-no>Cancel</button>
                       </div>
                   </div>`
                : '';
            return `<div class="sm-site${cls}" data-site="${s.id}">
                <div class="sm-srow">
                    ${has
                        ? `<button type="button" class="sm-smark has" data-show="${s.id}" title="Show on the map" aria-label="Show ${esc(s.name)} on the map"><i class="fas fa-location-dot"></i></button>`
                        : `<span class="sm-smark none" title="No pin yet"><i class="fas fa-location-dot"></i></span>`}
                    <div class="sm-sinfo">
                        <div class="sm-shead">
                            <span class="sm-sname">${esc(s.name)}</span>
                            <span class="sm-emps ${e ? 'some' : 'zero'}">${e} ${e === 1 ? 'emp' : 'emps'}</span>
                        </div>
                        <div class="sm-saddr">${s.location ? esc(s.location) : '<span class="sm-nogps">No location set</span>'}</div>
                        <div class="sm-smeta">${has
                            ? `${fmtHtml(s.latitude, s.longitude)}<span>· ${s.geofence_radius} m radius</span>`
                            : '<span class="sm-nogps">GPS check off until a pin is added</span>'}</div>
                    </div>
                    <div class="sm-acts">
                        <button type="button" class="sm-ic edit" data-edit="${s.id}" title="Edit" aria-label="Edit ${esc(s.name)}"><i class="fas fa-pen"></i></button>
                        <button type="button" class="sm-ic del" data-del="${s.id}" title="Remove" aria-label="Remove ${esc(s.name)}"><i class="fas fa-trash-can"></i></button>
                    </div>
                </div>
                ${confirm}
            </div>`;
        }).join('');
    }

    $('smSites').addEventListener('click', async e => {
        const b = e.target.closest('button');
        if (!b) return;
        if (b.dataset.show)  return showOnMap(+b.dataset.show);
        if (b.dataset.edit)  return startEdit(+b.dataset.edit);
        if (b.dataset.del)   { S.confirm = +b.dataset.del; renderSites(); return; }
        if ('no' in b.dataset) { S.confirm = null; renderSites(); return; }
        if (b.dataset.yes)   return removeSite(+b.dataset.yes, b);
    });

    function showOnMap(id) {
        const s = byId(id);
        if (!pinned(s)) return;
        openMap();
        map.setView([s.latitude, s.longitude], 17);
        others.eachLayer(l => {
            const at = l.getLatLng();
            if (at.lat === s.latitude && at.lng === s.longitude) l.openTooltip();
        });
        $('smMap').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function startEdit(id) {
        const s = byId(id);
        if (!s) return;
        S.editing = id; S.confirm = null; S.flash = null;
        S.geo++; S.place = null;
        $('smName').value = s.name;
        openMap();
        if (pinned(s)) {
            S.typed = false;
            S.pin = { lat: s.latitude, lng: s.longitude };
            S.name = s.name;
            S.label = s.location || 'Pinned at ' + fmt(s.latitude, s.longitude);
            S.addr = S.label;
            S.radius = snap(s.geofence_radius);
            map.setView([s.latitude, s.longitude], 17);
        } else {
            S.pin = null;
            S.typed = !!s.location;
            S.name = s.location || '';
            S.addr = @json(__('Typed address'));
            S.label = s.location || '';
            S.radius = snap(RADIUS.def);
        }
        $('smLoc').value = s.location || '';
        hideErr(); closeSugg();
        render(); renderSites(); drawOthers();
        const top = $('smForm').getBoundingClientRect().top;
        if (top < 0 || top > window.innerHeight * .6) $('smForm').scrollIntoView({ behavior: 'smooth', block: 'start' });
        $('smLoc').focus({ preventScroll: true });
    }

    async function removeSite(id, btn) {
        const s = byId(id);
        if (!s) return;
        btn.disabled = true;
        try {
            const data = await req(URLS.site(id), 'DELETE');
            const freed = data.freed_employees || 0;
            Notify.success(`"${s.name}" removed.` + (freed ? ` ${freed} ${freed === 1 ? 'employee' : 'employees'} moved to Unassigned.` : ''));
            S.confirm = null;
            if (S.editing === id) resetForm();
            await loadSites();
        } catch (err) {
            btn.disabled = false;
            Notify.error(err.message);
        }
    }

    // ── Start ────────────────────────────────────────────────────────────────
    render();
    loadSites();

    // A site added or changed at another desk, or a kiosk switched to one: the
    // list is asked for again. Quietly — the form and the map are left alone.
    Live.on('sites devices kiosk', () => loadSites());
})();
</script>
@endpush
