{{-- Dashboard chrome.

     The whole point of this file is the height arithmetic. The page fills the
     viewport exactly once — header, figures, then a grid that takes whatever
     is left — and every panel inside that grid scrolls its own body instead of
     pushing the page taller. Nothing is hidden; the page simply stops growing.

     Every colour is a design token, so light and dark follow one set of rules. --}}
<style>
/* ── The page is exactly one screen ───────────────────────────────────────
   100vh, less the sticky top bar and the container's own padding. Panels get
   min-height:0 so a grid child is allowed to be shorter than its content —
   without it a scrollable panel silently grows the page instead. */
.dash {
    --dash-gap: 12px;
    display: flex;
    flex-direction: column;
    gap: var(--dash-gap);
    height: calc(100vh - var(--topbar-height, 60px) - 46px);
    min-height: 0;
}

/* ── Strip: greeting, clock, and the actions worth one click ───────────── */
.dash-bar {
    display: flex; align-items: center; justify-content: space-between;
    gap: 14px; flex-wrap: wrap; flex: none;
    padding: 10px 14px;
    background: var(--bg-surface); border: 1px solid var(--border);
    border-radius: var(--radius-md);
}
.dash-greet { min-width: 0; }
.dash-greet h1 {
    margin: 0; font-size: 1rem; font-weight: 700; letter-spacing: -.01em;
    color: var(--text-primary);
}
.dash-greet p { margin: 1px 0 0; font-size: .76rem; color: var(--text-secondary); }
.dash-bar-right { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }

/* The clock keeps its own id and ticker; only its size changes here. */
.dash-clock {
    display: flex; align-items: center; gap: 9px;
    padding: 5px 11px 5px 8px;
    background: var(--bg-subtle); border: 1px solid var(--border);
    border-radius: var(--radius-sm); cursor: pointer; user-select: none;
}
.dash-clock:hover { border-color: var(--border-md); }
.dash-clock-ic {
    width: 26px; height: 26px; border-radius: 6px; flex: none;
    display: flex; align-items: center; justify-content: center;
    background: var(--brand-subtle); color: var(--brand); font-size: 12px;
}
.dash-clock-time {
    font-size: 13px; font-weight: 700; color: var(--text-primary);
    font-variant-numeric: tabular-nums; line-height: 1.15;
}
.dash-clock-date { font-size: 10.5px; color: var(--text-muted); line-height: 1.2; }

/* ── Figures: one row, six tiles, no card bigger than it needs to be ───── */
.dash-kpis {
    display: grid; grid-template-columns: repeat(6, 1fr); gap: var(--dash-gap);
    flex: none;
}
.kpi {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 12px; min-width: 0;
    background: var(--bg-surface); border: 1px solid var(--border);
    border-radius: var(--radius-md); text-decoration: none; color: inherit;
    transition: border-color .15s ease, background .15s ease;
}
a.kpi:hover { border-color: var(--brand); background: var(--brand-subtle); }
.kpi-ic {
    width: 32px; height: 32px; border-radius: 8px; flex: none;
    display: flex; align-items: center; justify-content: center; font-size: 13px;
}
.kpi-ic.blue   { background: var(--brand-subtle);   color: var(--brand); }
.kpi-ic.green  { background: var(--success-soft);   color: var(--success); }
.kpi-ic.amber  { background: var(--warning-soft);   color: var(--warning); }
.kpi-ic.red    { background: var(--danger-soft);    color: var(--danger); }
.kpi-body { min-width: 0; }
.kpi-label {
    font-size: .6rem; font-weight: 700; letter-spacing: .03em; text-transform: uppercase;
    color: var(--text-muted); margin: 0; white-space: nowrap;
    overflow: hidden; text-overflow: ellipsis;
}
/* Six tiles across 1440px leaves ~120px of text per tile. At the old tracking
   "Outstanding Vale" clipped to "OUTSTANDING VA…"; the icon gives up a few
   pixels so the label can say what it is. */
.kpi { gap: 8px; padding: 10px 11px; }
.kpi-ic { width: 30px; height: 30px; flex: 0 0 30px; font-size: 12px; }
.kpi-value {
    font-size: 1.02rem; font-weight: 700; color: var(--text-primary);
    font-variant-numeric: tabular-nums; line-height: 1.2; margin: 1px 0 0;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.kpi-delta { font-size: .64rem; font-weight: 600; margin: 0; white-space: nowrap; }
.kpi-delta.up   { color: var(--success); }
.kpi-delta.down { color: var(--danger); }
.kpi-delta.flat { color: var(--text-muted); }

/* ── The grid that takes the rest of the screen ────────────────────────── */
.dash-grid {
    display: grid;
    grid-template-columns: 1.25fr 1fr 1fr;
    /* On the left, the chart over live attendance: the list earns the taller
       half, a seven-point line reads fine short. The map takes the two
       columns on the right at full height. */
    grid-template-rows: 0.88fr 1.12fr;
    gap: var(--dash-gap);
    flex: 1 1 auto;
    min-height: 0;
}
.dash-grid > * { min-height: 0; min-width: 0; }
.area-chart { grid-column: 1; grid-row: 1; }
.area-live  { grid-column: 1; grid-row: 2; }
.area-map   { grid-column: 2 / span 2; grid-row: 1 / span 2; }

/* ── Panel: fixed head, scrolling body ────────────────────────────────── */
.panel {
    display: flex; flex-direction: column; min-height: 0;
    background: var(--bg-surface); border: 1px solid var(--border);
    border-radius: var(--radius-md); overflow: hidden;
}
.panel-head {
    display: flex; align-items: center; justify-content: space-between;
    gap: 10px; flex: none;
    padding: 8px 12px; border-bottom: 1px solid var(--border);
}
.panel-head h2 {
    margin: 0; font-size: .78rem; font-weight: 700; color: var(--text-primary);
    display: flex; align-items: center; gap: 7px; letter-spacing: -.005em;
}
.panel-head h2 i { color: var(--brand); font-size: .72rem; }
.panel-link {
    font-size: .68rem; font-weight: 600; color: var(--text-secondary);
    text-decoration: none; display: inline-flex; align-items: center; gap: 4px;
    white-space: nowrap;
}
.panel-link:hover { color: var(--brand); }
.panel-body { flex: 1 1 auto; min-height: 0; overflow-y: auto; overscroll-behavior: contain; }
.panel-body.pad { padding: 10px 12px; }
.panel-body::-webkit-scrollbar { width: 6px; }
.panel-body::-webkit-scrollbar-thumb { background: var(--border-md); border-radius: 3px; }

.panel-tag {
    font-size: .62rem; font-weight: 700; letter-spacing: .05em; text-transform: uppercase;
    padding: 2px 7px; border-radius: 999px;
}
.panel-tag.live { background: var(--success-soft); color: var(--success); }
.panel-tag.muted { background: var(--bg-subtle); color: var(--text-secondary); }

/* ── Rows shared by the three list panels ─────────────────────────────── */
.row-item {
    display: flex; align-items: center; gap: 9px;
    padding: 8px 12px; text-decoration: none; color: inherit;
}
.row-item + .row-item { border-top: 1px solid var(--border); }
a.row-item:hover, .row-item.hoverable:hover { background: var(--bg-subtle); }
.row-av {
    width: 27px; height: 27px; border-radius: 50%; flex: none;
    display: flex; align-items: center; justify-content: center;
    font-size: 10.5px; font-weight: 700;
    background: var(--brand-subtle); color: var(--brand);
}
.row-av.ok    { background: var(--success-soft); color: var(--success); }
.row-av.warn  { background: var(--warning-soft); color: var(--warning); }
.row-av.info  { background: var(--brand-subtle); color: var(--brand); }
.row-av.danger{ background: var(--danger-soft);  color: var(--danger); }
.row-main { flex: 1 1 auto; min-width: 0; }
.row-title {
    font-size: 12px; font-weight: 600; color: var(--text-primary); margin: 0;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.row-sub {
    font-size: 10.5px; color: var(--text-muted); margin: 1px 0 0;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.row-right { flex: none; font-size: 11.5px; font-weight: 700; font-variant-numeric: tabular-nums; }
.row-right.ok { color: var(--success); }
.row-count {
    min-width: 22px; height: 20px; padding: 0 6px; border-radius: 10px;
    font-size: 11px; font-weight: 700; line-height: 20px; text-align: center;
    background: var(--bg-subtle); color: var(--text-secondary);
}
.row-count.warn   { background: var(--warning-soft); color: var(--warning); }
.row-count.info   { background: var(--brand-subtle); color: var(--brand); }
.row-count.ok     { background: var(--success-soft); color: var(--success); }
.row-count.danger { background: var(--danger-soft);  color: var(--danger); }

/* ── Empty states, one shape ──────────────────────────────────────────── */
.panel-empty {
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    height: 100%; min-height: 90px; padding: 16px; text-align: center; gap: 6px;
}
.panel-empty i { font-size: 17px; color: var(--text-muted); opacity: .5; }
.panel-empty p { margin: 0; font-size: 11.5px; color: var(--text-muted); }
.panel-empty .ok { color: var(--success); opacity: 1; }

/* ── The map and chart panels need their body to be a flex column ─────── */
.panel-body.flexcol { display: flex; flex-direction: column; overflow: hidden; padding: 10px 12px; gap: 7px; }
#attendanceChart { flex: 1 1 auto; min-height: 0; width: 100% !important; }

/* The Site Tracker keeps its minimise and maximise; the map is read-only and
   takes the whole body. Sites are pinned on the Sites page. */
.area-map .st-body { padding: 8px 10px; }
.area-map #kioskMap {
    flex: 1 1 auto; min-height: 118px !important; height: auto !important;
    border-radius: var(--radius-sm);
}

/* Dark tiles in the dark theme, as on the Sites page, so the map is not a
   white slab on a navy page. The light theme keeps OpenStreetMap's colours. */
html[data-bs-theme="dark"] #kioskMap .leaflet-tile-pane { filter: invert(1) hue-rotate(180deg) brightness(.9) contrast(.88) saturate(.55); }
html[data-bs-theme="dark"] #kioskMap { background: var(--bg-subtle); }
html[data-bs-theme="dark"] #kioskMap .leaflet-control-attribution { background: color-mix(in srgb, var(--surface) 85%, transparent); color: var(--text-muted); }
html[data-bs-theme="dark"] #kioskMap .leaflet-control-attribution a { color: var(--brand); }

/* A site: the red pin, with its name and range beside it. A kiosk: a round
   badge in the colour of where it stands, with its name and that state. */
.dm-mark { background: none; border: 0; }
.dm-kiosk { position: relative; width: 32px; height: 32px; white-space: nowrap; }
.dm-site { position: relative; width: 28px; height: 38px; white-space: nowrap; }
.dm-site svg { display: block; filter: drop-shadow(0 2px 3px rgba(0,0,0,.35)); }
/* A site's name sits over its pin, a kiosk's to the right of its badge, so a
   kiosk standing at its own site does not bury the site's name. Where one
   would land on another, it tries the other sides first (declutter() in
   dashboard.blade.php). */
.dm-chip { position: absolute; }
.dm-site .dm-chip { bottom: 40px; left: 50%; transform: translateX(-50%); align-items: center; text-align: center; }
.dm-site .dm-chip.at-below { bottom: auto; top: 40px; }
.dm-site .dm-chip.at-right { bottom: auto; top: 2px; left: 32px; transform: none; align-items: flex-start; text-align: left; }
.dm-site .dm-chip.at-left  { bottom: auto; top: 2px; left: auto; right: 32px; transform: none; align-items: flex-end; text-align: right; }
.dm-kiosk .dm-chip { left: 40px; top: 50%; transform: translateY(-50%); }
.dm-kiosk .dm-chip.at-left { left: auto; right: 40px; align-items: flex-end; text-align: right; }
/* Where no side is free, the less urgent name steps back and comes out on
   hover. The pins and badges themselves always show. */
.dm-chip.is-hidden { display: none; }
.leaflet-marker-icon.dm-mark:hover { z-index: 10000 !important; }
.dm-mark:hover .dm-chip.is-hidden { display: flex; }
.dm-chip {
    display: flex; flex-direction: column; max-width: 200px; overflow: hidden;
    padding: 3px 8px 4px; border-radius: 8px; line-height: 1.25;
    background: color-mix(in srgb, var(--surface) 94%, transparent);
    border: 1px solid var(--border-md); box-shadow: 0 2px 6px rgba(0,0,0,.18);
}
.dm-chip b { font-size: 11.5px; font-weight: 700; color: var(--text-primary); max-width: 100%; overflow: hidden; text-overflow: ellipsis; }
.dm-chip small { font-size: 10.5px; font-weight: 600; color: var(--text-secondary); max-width: 100%; overflow: hidden; text-overflow: ellipsis; }
.dm-av {
    flex: none; display: grid; place-items: center; width: 32px; height: 32px; border-radius: 50%;
    font-size: 14px; color: var(--dm-c); background: var(--surface); border: 2.5px solid var(--dm-c);
    box-shadow: 0 0 0 4px color-mix(in srgb, var(--dm-c) 22%, transparent), 0 2px 6px rgba(0,0,0,.3);
}
.dm-kiosk .dm-chip small { color: var(--dm-c); }
:is(.dm-kiosk, .dm-legend-keys span, .dm-pop-k, .dm-dot).is-in        { --dm-c: var(--success); }
:is(.dm-kiosk, .dm-legend-keys span, .dm-pop-k, .dm-dot):is(.is-out, .is-elsewhere) { --dm-c: var(--danger); }
:is(.dm-kiosk, .dm-legend-keys span, .dm-pop-k, .dm-dot).is-nogps     { --dm-c: var(--warning); }
:is(.dm-kiosk, .dm-legend-keys span, .dm-pop-k, .dm-dot).is-offline   { --dm-c: var(--text-muted); }
/* The header's dot. Not Bootstrap's text-* colours, which the dark theme
   turns all to one colour. */
.dm-dot { display: inline-block; width: 8px; height: 8px; margin-right: 3px; border-radius: 50%; background: var(--dm-c); vertical-align: 1px; }
.dm-kiosk.is-offline .dm-av { opacity: .75; }

/* The key, bottom left, and the way to the Sites page. */
.dm-legend {
    max-width: 280px; padding: 7px 10px; border-radius: 8px;
    font-size: 10.5px; line-height: 1.5; color: var(--text-secondary);
    background: color-mix(in srgb, var(--surface) 92%, transparent);
    border: 1px solid var(--border-md); box-shadow: var(--shadow-sm);
}
.dm-legend-keys { display: flex; flex-wrap: wrap; gap: 2px 10px; }
.dm-legend-keys span { display: inline-flex; align-items: center; gap: 5px; }
.dm-legend-keys i { width: 8px; height: 8px; border-radius: 50%; background: var(--dm-c); }
.dm-legend-note { margin-top: 3px; color: var(--text-muted); }
.dm-legend a { display: inline-block; margin-top: 3px; font-weight: 600; color: var(--brand); text-decoration: none; }
.dm-legend a i { font-size: 9px; }

/* What a click on a pin or a kiosk opens. */
.dm-pop { display: flex; flex-direction: column; gap: 3px; min-width: 190px; font-size: 12px; line-height: 1.4; }
.dm-pop b { font-size: 12.5px; }
.dm-pop-sub { font-size: 11px; color: var(--text-muted); }
.dm-pop-k { font-weight: 600; color: var(--dm-c); }
.dm-pop-k i { width: 14px; }

/* ── Below a laptop, one screen stops being the right answer ───────────
   A phone cannot hold six panels legibly, so the page is allowed to scroll
   again rather than crushing everything into unreadable slivers. */
@media (max-width: 1399px) {
    .dash-kpis { grid-template-columns: repeat(3, 1fr); }
}
@media (max-width: 1199px) {
    .dash { height: auto; }
    .dash-grid {
        grid-template-columns: 1fr 1fr;
        grid-template-rows: auto;
        min-height: 0;
    }
    .dash-grid > * { min-height: 260px; }
    .area-chart, .area-live {
        grid-column: auto; grid-row: auto;
    }
    .area-live { min-height: 320px; }
    /* The chart and live attendance side by side, the map across both. */
    .area-map { grid-column: 1 / -1; grid-row: auto; min-height: 420px; }
}
@media (max-width: 767px) {
    .dash-kpis { grid-template-columns: repeat(2, 1fr); }
    .dash-grid { grid-template-columns: 1fr; }
    .dash-bar { flex-direction: column; align-items: stretch; }
    .dash-bar-right { justify-content: space-between; }
    /* The map gets the height a phone can give it, and the kiosk count
       its own line under the title rather than an ellipsis beside it. */
    .area-map { min-height: 520px; }
    .area-map.site-tracker .panel-head.table-card-header { flex-wrap: wrap; row-gap: 4px; }
    .area-map #kiosk-status { order: 3; flex: 1 1 100%; }
    .dm-legend { max-width: 230px; font-size: 10px; }
}
</style>
