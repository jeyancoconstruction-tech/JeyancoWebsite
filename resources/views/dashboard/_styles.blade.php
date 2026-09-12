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
    /* The map earns the taller half: a seven-point line reads fine short, a
       map at 100px does not. */
    grid-template-rows: 0.88fr 1.12fr;
    gap: var(--dash-gap);
    flex: 1 1 auto;
    min-height: 0;
}
.dash-grid > * { min-height: 0; min-width: 0; }
.area-chart { grid-column: 1; grid-row: 1; }
.area-map   { grid-column: 1; grid-row: 2; }
.area-live  { grid-column: 2; grid-row: 1 / span 2; }
.area-todo  { grid-column: 3; grid-row: 1; }
.area-feed  { grid-column: 3; grid-row: 2; }

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

/* The Site Tracker keeps every id and control it already had; only the box
   around it is tighter. Its maximise still lifts it out of this grid. */
/* One control row, not two: search, site and Save side by side, so the map
   keeps the height instead of the chrome. */
.area-map .st-body { gap: 6px; padding: 8px 10px; }
.area-map .map-ctl { flex-wrap: nowrap; gap: 6px; }
.area-map .map-input { flex: 1 1 auto; min-width: 70px; }
.area-map .map-select { flex: 1 1 90px; min-width: 0; }
.area-map .map-input, .area-map .map-select { height: 29px; font-size: 12px; padding: 0 8px; }
.area-map .map-select { padding-right: 22px !important; background-position: right 6px center !important; }
.area-map .map-btn { height: 29px; padding: 0 9px; font-size: 11.5px; flex: 0 0 auto; }

/* The hint changes length as you search and save; one reserved line keeps the
   map from resizing under it. */
.area-map .map-hint {
    min-height: 15px; max-height: 15px; font-size: 10px; line-height: 15px;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap; display: block;
}
.area-map #kioskMap {
    flex: 1 1 auto; min-height: 118px !important; height: auto !important;
    border-radius: var(--radius-sm);
}

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
    .area-chart, .area-map, .area-live, .area-todo, .area-feed {
        grid-column: auto; grid-row: auto;
    }
    .area-live { min-height: 320px; }
}
@media (max-width: 767px) {
    .dash-kpis { grid-template-columns: repeat(2, 1fr); }
    .dash-grid { grid-template-columns: 1fr; }
    .dash-bar { flex-direction: column; align-items: stretch; }
    .dash-bar-right { justify-content: space-between; }
}
</style>
