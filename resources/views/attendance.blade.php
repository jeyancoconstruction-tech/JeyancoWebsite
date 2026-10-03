@extends('layouts')

@section('page_title', 'Attendance Monitoring')

@push('styles')
<style>
/* ── Attendance Monitoring ────────────────────────────────────────────────
   Built on the design tokens alone, so one block serves both themes. */
/* The full width the layout gives, as every other page takes it — no padding
   of its own on top of the layout's. */
.attendance-container.atm { max-width:none; width:100%; margin:0; padding:0; }


/* ── Heading ─────────────────────────────────────────────────────────────── */
/* The shared page header, with the date and the shift schedule on its right.
   The schedule keeps to one line, as tall as a button, so this header is the
   same height as every other page's; on a narrower window its hours are cut
   short (the whole line is in its tooltip) rather than wrapping under the
   title. */
.atm .page-head { flex-wrap:nowrap; }
.atm .page-head-main { flex:none; }
.atm .page-head-actions { flex:0 1 auto; min-width:0; flex-wrap:nowrap; gap:14px; }
.atm-date { display:flex; align-items:center; gap:7px; flex:none; white-space:nowrap; font-size:13px; color:var(--text-muted); }

/* The shifts in one line, and the way to change them. The page reads every
   day against these hours, so they are stated where the reading happens. */
.atm-sched {
    display:flex; align-items:center; gap:10px; min-width:0; max-width:100%; height:32px;
    padding:0 4px 0 12px; border:1px solid var(--border); border-radius:var(--radius-lg);
    background:var(--surface); box-shadow:var(--shadow-xs);
    color:inherit; text-decoration:none; text-align:left;
}
a.atm-sched:hover { border-color:var(--brand); color:inherit; text-decoration:none; }
.atm-sched > i { font-size:15px; color:var(--brand); flex:none; }
.atm-sched-txt { display:flex; align-items:baseline; gap:8px; min-width:0; white-space:nowrap; }
.atm-sched-txt b { flex:none; font-size:12.5px; color:var(--text-primary); }
.atm-sched-txt small { min-width:0; overflow:hidden; text-overflow:ellipsis; font-size:11.5px; color:var(--text-muted); }
.atm-sched-go {
    display:flex; align-items:center; gap:6px; flex:none; white-space:nowrap;
    font-size:12px; font-weight:700; color:var(--brand);
    background:var(--brand-subtle); border-radius:var(--radius-sm); padding:4px 9px 4px 10px;
}
.atm-sched-go i { font-size:10px; }

/* ── Colour ───────────────────────────────────────────────────────────────
   Ink, grey, one blue, and red for what is wrong — nothing else. A page where
   everything has a colour has nothing that stands out; here a problem does. */
/* The panel sits on <body>, outside .atm, so it is given the same colours. */
.atm, .atm-panel {
    --atm-ink:  var(--text-primary);
    --atm-mute: var(--text-muted);
    --atm-bad:  var(--danger);
}

/* ── Cards ───────────────────────────────────────────────────────────────
   One strip of four numbers. Each is a question, so each is a link — to the
   same status the control under the tabs sets. Anchors, not buttons: the
   answer is a URL the office can bookmark or send to somebody. */
.atm-stats {
    display:grid; grid-template-columns:repeat(4, minmax(0, 1fr)); margin-bottom:12px;
    background:var(--surface); border:1px solid var(--border); border-radius:var(--radius-lg);
    box-shadow:var(--shadow-xs); overflow:hidden;
}
.atm-stat {
    display:flex; flex-direction:column; gap:2px; padding:12px 18px; position:relative;
    border-left:1px solid var(--border); color:inherit; text-decoration:none;
    transition:background .15s;
}
.atm-stat:first-child { border-left:0; }
.atm-stat:hover { background:var(--bg-subtle); color:inherit; text-decoration:none; }
.atm-stat.is-active { background:var(--bg-subtle); }
.atm-stat.is-active::after { content:""; position:absolute; left:0; right:0; bottom:0; height:2px; background:var(--atm-ink); }
.atm-stat-lbl { font-size:10.5px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:var(--text-secondary); }
.atm-stat-num { font-size:28px; font-weight:800; letter-spacing:-.02em; line-height:1.15; color:var(--atm-ink); font-variant-numeric:tabular-nums; }
.atm-stat-sub { font-size:11.5px; color:var(--atm-mute); min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
/* Red only when there is something to review. */
.atm-stat.is-bad.has-some .atm-stat-lbl,
.atm-stat.is-bad.has-some .atm-stat-num { color:var(--atm-bad); }
.atm-stat.is-bad.is-active::after { background:var(--atm-bad); }

/* The strip goes behind an arrow at the side, as the cards do on Employees.
   It folds by the height of a grid row (1fr → 0fr), so the list below rises
   smoothly and nothing jumps. Out on every page load. */
.atm-top { position:relative; }
.atm-fold { display:grid; grid-template-rows:0fr; transition:grid-template-rows .16s ease-in; }
.atm-fold.open { grid-template-rows:1fr; transition:grid-template-rows .22s ease-out; }
.atm-fold-in { min-height:0; overflow:hidden; }
.atm-fold .atm-stats { transform:translateX(-16px); opacity:0; transition:transform .16s ease-in, opacity .16s ease-in; }
.atm-fold.open .atm-stats { transform:none; opacity:1; transition:transform .22s ease-out, opacity .22s ease-out; }
@media (prefers-reduced-motion:reduce) { .atm-fold, .atm-fold .atm-stats { transition:none !important; } }

/* The arrow: a tab on the edge by the navigation bar. ‹ hides the strip, › brings it back. */
.atm-handle {
    position:absolute; z-index:5; top:0; left:calc(-1 * var(--atm-gutter, 20px));
    width:17px; height:38px; padding:0; cursor:pointer;
    display:grid; place-items:center; color:var(--brand);
    background:var(--surface); border:1px solid var(--border-md); border-left:0;
    border-radius:0 10px 10px 0; box-shadow:3px 0 10px rgba(16, 24, 40, .08);
    transition:background .15s, color .15s, height .22s ease-out;
}
.atm-handle svg { width:13px; height:13px; transition:transform .22s ease-out; }
.atm-handle:hover { background:var(--bg-subtle); }
.atm-handle:focus-visible { outline:2px solid var(--brand); outline-offset:2px; }
.atm-handle[aria-expanded="true"] { background:var(--brand); border-color:var(--brand); color:#fff; height:62px; }
.atm-handle[aria-expanded="true"] svg { transform:rotate(180deg); }
@media (prefers-reduced-motion:reduce) { .atm-handle, .atm-handle svg { transition:none; } }

/* ── The records card: tabs, filters, the list ─────────────────────────── */
.atm-card {
    display:flex; flex-direction:column; min-width:0;
    background:var(--surface); border:1px solid var(--border); border-radius:var(--radius-lg);
    box-shadow:var(--shadow-xs);
}
.atm-tabs { display:flex; gap:18px; margin:0; padding:0 18px; border-bottom:1px solid var(--border); }
.atm-tab {
    display:flex; align-items:center; gap:8px; margin-bottom:-1px; padding:11px 0 10px;
    border:0; border-bottom:2px solid transparent; border-radius:0; background:none;
    font-size:13px; font-weight:600; color:var(--atm-mute);
}
.atm-tab:hover { color:var(--atm-ink); }
.atm-tab.active { color:var(--atm-ink); border-bottom-color:var(--atm-ink); }
.atm-count { font-size:11px; font-weight:700; color:var(--atm-bad); }
.atm-count[hidden] { display:none; }

.atm-toolbar { display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin:0; padding:10px 18px; border-bottom:1px solid var(--border); }
.atm-field {
    display:flex; align-items:center; gap:8px; height:32px; padding:0 10px;
    border:1px solid var(--border); border-radius:var(--radius-md); background:var(--surface);
}
.atm-field[hidden] { display:none; }
.atm-field > i { font-size:12px; color:var(--atm-mute); }
.atm-field input, .atm-field select {
    height:30px; min-width:0; border:0; outline:none; box-shadow:none; background:transparent;
    font-size:12.5px; font-weight:500; color:var(--atm-ink);
}
.atm-field input { width:170px; }
.atm-field input::placeholder { color:var(--atm-mute); }
/* The open list is drawn by the browser, and a background on the select is
   enough for Chrome to stop theming it — the popup came back white under
   near-white text in dark mode. Both colours are stated so it follows. */
.atm-field select option { background-color:var(--bg-elevated); color:var(--text-primary); }
.atm-field:focus-within { border-color:var(--atm-ink); }
html[data-bs-theme] .atm-field input:focus-visible,
html[data-bs-theme] .atm-field select:focus-visible { box-shadow:none !important; outline:none !important; border-radius:0; }

/* A row of choices where one is always on. Radios underneath, so the form
   submits them like any other field and the keyboard moves between them. */
.atm-seg { display:flex; overflow:hidden; border:1px solid var(--border); border-radius:var(--radius-md); background:var(--surface); }
.atm-seg label { margin:0; }
.atm-seg label + label span { border-left:1px solid var(--border); }
.atm-seg label[hidden] { display:none; }
.atm-seg input { position:absolute; opacity:0; pointer-events:none; }
.atm-seg span {
    display:flex; align-items:center; gap:6px; height:30px; padding:0 11px; cursor:pointer;
    font-size:12px; font-weight:600; color:var(--atm-mute); white-space:nowrap;
}
.atm-seg span i { font-size:10.5px; }
.atm-seg span:hover { color:var(--atm-ink); }
.atm-seg input:checked + span { background:var(--atm-ink); color:var(--surface); }
.atm-seg input:focus-visible + span { outline:2px solid var(--brand); outline-offset:-2px; }
.atm-seg .atm-count { margin-left:2px; }
.atm-seg input:checked + span .atm-count { color:inherit; }
.atm-toolbar .atm-push { margin-left:auto; }

.atm-btn {
    display:inline-flex; align-items:center; gap:6px; padding:6px 12px; cursor:pointer;
    border:1px solid var(--border-md); border-radius:var(--radius-sm); background:var(--surface);
    font-size:12.5px; font-weight:600; color:var(--atm-ink);
}
.atm-btn:hover { background:var(--bg-subtle); }
.atm-btn.pri { background:var(--atm-ink); border-color:var(--atm-ink); color:var(--surface); }
.atm-btn.pri:hover { opacity:.9; background:var(--atm-ink); }
.atm-btn.danger { background:var(--surface); border-color:color-mix(in srgb, var(--danger) 45%, transparent); color:var(--danger); }
.atm-btn:disabled { opacity:.5; cursor:not-allowed; }
.atm-toolbar .atm-btn { height:32px; }

/* While a filter is being fetched the lists dim, so the reader does not take
   the old rows for the answer. */
.atm-card.is-loading .tab-content { opacity:.55; transition:opacity .15s; }

/* ── The list ────────────────────────────────────────────────────────────
   Five columns: who, in, out, hours, status. Wide enough for them; narrower
   than that, it scrolls sideways inside the card. */
.atm-scroll { overflow-x:auto; }
.atm-table { width:100%; min-width:860px; border-collapse:separate; border-spacing:0; }
.atm-table thead th {
    padding:9px 18px; text-align:left; white-space:nowrap;
    font-size:10.5px; font-weight:700; letter-spacing:.08em; text-transform:uppercase;
    color:var(--atm-mute); border-bottom:1px solid var(--border);
}
.atm-table tbody td {
    padding:11px 18px; vertical-align:middle; font-size:13px; color:var(--atm-ink);
    background:var(--surface); border-bottom:1px solid var(--border);
}
.atm-table th.atm-col-chev, .atm-table td.atm-col-chev { width:36px; padding-left:0; text-align:right; }
tr.atm-row { cursor:pointer; }
tr.atm-row:hover td, tr.atm-row.is-open td { background:var(--bg-subtle); }
tr.atm-row:focus-visible { outline:2px solid var(--brand); outline-offset:-2px; }
/* A day that is wrong is marked down its edge, so it is found in a long list. */
tr.atm-row.is-flag td:first-child { box-shadow:inset 3px 0 0 var(--atm-bad); }
tr.atm-dayhead td {
    padding:8px 18px; font-size:12px; font-weight:700; letter-spacing:.03em;
    color:var(--text-secondary); background:var(--bg);
}
/* The card reaches the bottom of the screen however little is in it, and
   grows past it with the page when the list is long — a minimum height set
   by the script below, never a box the rows scroll inside. */
.atm-card > .tab-content { flex:1; display:flex; flex-direction:column; }
.atm-card > .tab-content > .tab-pane.active { flex:1; display:flex; flex-direction:column; }
.atm-card .atm-scroll { flex:1; display:flex; flex-direction:column; }
.atm-empty-state {
    flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:8px;
    padding:40px 16px; text-align:center; font-size:13px; color:var(--atm-mute);
}
.atm-empty-state i { font-size:1.6rem; opacity:.35; }

.atm-emp { min-width:200px; }
.atm-emp b { display:block; font-size:13.5px; font-weight:700; color:var(--atm-ink); white-space:nowrap; }
.atm-emp small { display:block; font-size:11.5px; color:var(--atm-mute); white-space:nowrap; margin-top:1px; }

.atm-punch { display:flex; flex-direction:column; gap:2px; min-width:82px; }
/* AM and PM are told apart by a rule between them. */
.atm-table td.atm-sep, .atm-table th.atm-sep { border-left:1px solid var(--border); }
.atm-t { display:flex; align-items:center; gap:5px; font-size:13.5px; font-weight:600; font-variant-numeric:tabular-nums; white-space:nowrap; color:var(--atm-ink); }
.atm-t.is-mute { color:var(--atm-mute); font-weight:500; }
.atm-edited { display:inline-block; width:6px; height:6px; border-radius:50%; background:var(--brand); }
/* What stands beside a time: late, expected, due — grey. Red only when a
   scan is missing. */
.atm-note-t { display:inline-flex; align-items:center; gap:5px; font-size:11.5px; font-weight:500; color:var(--atm-mute); white-space:nowrap; }
.atm-note-t.bad { color:var(--atm-bad); font-weight:600; }
.atm-note-t.bad::before { content:""; width:5px; height:5px; border-radius:50%; background:var(--atm-bad); flex:none; }

.atm-hrs { font-size:15px; font-weight:800; font-variant-numeric:tabular-nums; white-space:nowrap; color:var(--atm-ink); }
.atm-hrs small { display:block; font-size:11px; font-weight:500; color:var(--atm-mute); }

.atm-status { display:flex; flex-direction:column; align-items:flex-start; gap:3px; }
.atm-st { display:inline-flex; align-items:center; gap:7px; font-size:12.5px; font-weight:600; color:var(--atm-ink); white-space:nowrap; }
.atm-st .atm-dot { width:7px; height:7px; border-radius:50%; background:var(--atm-mute); flex:none; }
.atm-st.good .atm-dot { background:var(--atm-ink); }
.atm-st.bad { color:var(--atm-bad); }
.atm-st.bad .atm-dot { background:var(--atm-bad); }
.atm-st.live .atm-dot { animation:atm-pulse 1.6s ease-in-out infinite; }
@keyframes atm-pulse { 50% { opacity:.3; } }
@media (prefers-reduced-motion: reduce) { .atm-st.live .atm-dot { animation:none; } }
.atm-chev { font-size:11px; color:var(--atm-mute); }

/* ── The detail: a side panel ─────────────────────────────────────────────
   The row under each day holds everything the list leaves out. It stays
   hidden in the table; the script shows a copy of it in this panel. */
.atm-table tr.atm-detail { display:none !important; }
.atm-shade {
    position:fixed; inset:0; z-index:1190; background:rgba(15, 23, 42, .32);
    opacity:0; pointer-events:none; transition:opacity .18s;
}
.atm-shade.on { opacity:1; pointer-events:auto; }
.atm-panel {
    position:fixed; top:0; right:0; bottom:0; z-index:1200; width:min(560px, 100vw);
    display:flex; flex-direction:column; gap:16px; padding:22px 24px 26px; overflow-y:auto;
    background:var(--surface); border-left:1px solid var(--border);
    box-shadow:-18px 0 40px rgba(15, 23, 42, .16);
    transform:translateX(100%); transition:transform .22s ease; color:var(--atm-ink);
}
.atm-panel.on { transform:none; }
@media (prefers-reduced-motion: reduce) { .atm-panel, .atm-shade { transition:none; } }
.atm-panel > td, .atm-panel .atm-pbody { display:flex; flex-direction:column; gap:16px; }

.atm-dhead { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; }
.atm-dhead h3 { margin:0; font-size:19px; font-weight:800; letter-spacing:-.02em; color:var(--atm-ink); }
.atm-dhead p { margin:3px 0 0; font-size:12px; color:var(--atm-mute); }
.atm-close {
    flex:none; width:32px; height:32px; display:grid; place-items:center; cursor:pointer;
    border:1px solid var(--border); border-radius:var(--radius-sm); background:var(--surface); color:var(--atm-mute);
}
.atm-close:hover { color:var(--atm-ink); background:var(--bg-subtle); }

.atm-dstate { display:flex; align-items:center; gap:12px; flex-wrap:wrap; padding:10px 12px; border:1px solid var(--border); border-radius:var(--radius-md); }
.atm-dstate.bad { border-color:color-mix(in srgb, var(--danger) 40%, transparent); background:var(--danger-soft); }

.atm-ses { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
.atm-ses > div { border:1px solid var(--border); border-radius:var(--radius-md); padding:10px 12px; }
.atm-ses h5 { margin:0 0 6px; font-size:11px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:var(--text-secondary); }
.atm-ses h5 span { font-weight:600; color:var(--atm-mute); letter-spacing:0; text-transform:none; }
.atm-ses-r { display:flex; justify-content:space-between; align-items:flex-start; gap:8px; padding:3px 0; font-size:12.5px; color:var(--atm-mute); }
.atm-ses-v { display:flex; flex-direction:column; align-items:flex-end; }
.atm-ses-v b { display:flex; align-items:center; gap:5px; font-weight:700; color:var(--atm-ink); font-variant-numeric:tabular-nums; }
.atm-ses-v b.is-mute { color:var(--atm-mute); font-weight:500; }
.atm-ses-v small { font-size:11px; color:var(--atm-mute); }
.atm-ses-v small.bad { color:var(--atm-bad); font-weight:600; }

.atm-dsec h4, .atm-dbox h4 { margin:0 0 8px; font-size:11px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:var(--atm-mute); }

/* The day against its shift, an hour either side. Positions come from the
   server as percentages, so the bar is drawn by CSS alone. One ink for the
   time worked, grey for the break, red for what is missing or late. */
.atm-tl { position:relative; width:100%; height:22px; }
.atm-tl > span { position:absolute; display:block; }
.atm-tl .base { top:9px; height:4px; border-radius:2px; background:var(--border); }
.atm-tl .bw   { top:7px; height:8px; border-radius:2px; background:repeating-linear-gradient(135deg, color-mix(in srgb, var(--atm-mute) 30%, transparent) 0 3px, transparent 3px 6px); }
.atm-tl .w    { top:6px; height:10px; border-radius:5px; background:var(--atm-ink); }
.atm-tl .w.live { background:linear-gradient(90deg, var(--atm-ink), var(--atm-ink) 70%, color-mix(in srgb, var(--atm-ink) 30%, transparent)); }
.atm-tl .w.miss { top:5px; height:12px; background:transparent; border:1.5px dashed var(--atm-bad); }
.atm-tl .w.br { background:color-mix(in srgb, var(--atm-mute) 55%, transparent); }
.atm-tl .w.ob { background:var(--atm-bad); }
.atm-tl .late { top:10px; height:2px; background:var(--atm-bad); }
.atm-tl .now  { top:0; bottom:0; width:2px; border-radius:1px; background:var(--brand); }
.atm-tlax { position:relative; width:100%; height:14px; margin-top:3px; font-size:10.5px; color:var(--atm-mute); font-variant-numeric:tabular-nums; }
.atm-tlax span { position:absolute; top:0; white-space:nowrap; }
.atm-tlax .is-mid { transform:translateX(-50%); }
.atm-tlax .is-end { transform:translateX(-100%); }
.atm-key { display:flex; flex-wrap:wrap; gap:6px 14px; margin-top:8px; font-size:11px; color:var(--atm-mute); }
.atm-key span { display:flex; align-items:center; gap:6px; }
.atm-key i { display:inline-block; width:14px; height:6px; border-radius:3px; }
.atm-key .k-w { background:var(--atm-ink); }
.atm-key .k-b { background:color-mix(in srgb, var(--atm-mute) 55%, transparent); }
.atm-key .k-m { border:1.5px dashed var(--atm-bad); height:8px; }
.atm-key .k-n { width:2px; height:12px; background:var(--brand); }

.atm-dgrid { display:flex; flex-direction:column; gap:12px; }
.atm-dbox { display:flex; flex-direction:column; gap:10px; padding:14px 16px; border:1px solid var(--border); border-radius:var(--radius-lg); }
.atm-dbox h4 { margin:0; }
.atm-scans { display:flex; flex-direction:column; margin:0; padding:0; list-style:none; }
.atm-scans li {
    display:grid; grid-template-columns:78px minmax(0, 1fr) auto; align-items:center; gap:10px;
    padding:7px 0; font-size:12.5px; border-bottom:1px solid var(--border);
}
.atm-scans li:last-child { border-bottom:0; }
.atm-scans .k { display:block; font-size:11.5px; color:var(--atm-mute); }
.atm-fix {
    display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin:0; padding:12px;
    background:var(--danger-soft); border:1px solid color-mix(in srgb, var(--danger) 30%, transparent);
    border-radius:var(--radius-md);
}
.atm-fix b { flex:1 1 100%; font-size:13px; color:var(--atm-ink); }
.atm-fix small { flex:1 1 100%; font-size:12px; color:var(--text-secondary); }
.atm-fix input[type=time] {
    padding:5px 8px; border:1px solid var(--border-md); border-radius:var(--radius-sm);
    background:var(--surface); color:var(--atm-ink); color-scheme:inherit; font-variant-numeric:tabular-nums;
}
.atm-decided {
    display:flex; align-items:center; gap:10px; margin:0 0 4px; padding:10px 12px;
    background:var(--bg-subtle); border:1px solid var(--border); border-radius:var(--radius-md); font-size:13px; color:var(--atm-ink);
}
.atm-decided > span { flex:1; min-width:0; }
.atm-decided small { display:block; font-size:12px; color:var(--atm-mute); margin-top:2px; }
.atm-del {
    display:flex; align-items:center; gap:10px; margin:0; padding-top:10px;
    border-top:1px solid var(--border); font-size:12px; color:var(--atm-mute);
}
.atm-del > span { flex:1; min-width:0; }
.atm-sum { display:grid; grid-template-columns:repeat(3, minmax(0, 1fr)); gap:12px; }
.atm-sum > div { display:flex; flex-direction:column; gap:2px; }
.atm-sum span { font-size:10.5px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:var(--atm-mute); }
.atm-sum b { font-size:16px; font-weight:800; font-variant-numeric:tabular-nums; color:var(--atm-ink); }
.atm-note { margin:0; font-size:12px; color:var(--atm-mute); }

.atm-foot {
    display:flex; flex-wrap:wrap; gap:8px 16px; padding:11px 18px;
    border-top:1px solid var(--border); font-size:12px; color:var(--atm-mute);
}

/* The card ends at the bottom of the screen, so its pager lands under the
   floating chat button (50px across, 28px in from the right). Kept clear of
   it, or the next-page arrow cannot be clicked. */
.att-pager { padding:0 76px 0 16px; }
.att-pager nav { padding-top:10px; }
.att-pager .pagination { margin-bottom:0; }

@media (max-width:1100px) {
    .atm-stats { grid-template-columns:repeat(2, minmax(0, 1fr)); }
    .atm-stat:nth-child(3) { border-left:0; }
    .atm-stat:nth-child(n+3) { border-top:1px solid var(--border); }
    .atm-toolbar .atm-push { margin-left:0; }
}
@media (max-width:700px) {
    .atm .page-head { flex-wrap:wrap; }
    .atm .page-head-actions { flex:1 1 100%; flex-wrap:wrap; justify-content:flex-start; }
    .atm-sched { height:auto; min-height:36px; padding:6px 10px; width:100%; }
    .atm-sched-txt { flex-direction:column; align-items:flex-start; gap:1px; white-space:normal; }
    .atm-sched-go { display:none; }
    .atm-toolbar { padding:10px 12px; }
    .atm-field, .atm-field input { width:100%; }
    .atm-field input { flex:1; }
    .atm-seg { max-width:100%; overflow-x:auto; }
    .atm-panel { padding:18px 16px 22px; }
    .atm-ses { grid-template-columns:minmax(0, 1fr); }
    .atm-sum { grid-template-columns:repeat(2, minmax(0, 1fr)); }
}
@media (max-width:420px) {
    .atm-stats { grid-template-columns:minmax(0, 1fr); }
    .atm-stat { border-left:0; border-top:1px solid var(--border); }
    .atm-stat:first-child { border-top:0; }
}
</style>
@endpush

@section('content')
@use('App\Support\WorkSchedule')
@php
    // A card is a question, and its answer is a list of people. Clicking one
    // narrows both tables to the rows behind its number; clicking it again
    // shows everybody. The site, shift and search already chosen are carried
    // along; the page number is not.
    $cardBase = request()->except(['view', 'tab', 'page']);
    $cardUrl  = fn (string $v) => route('attendance', $cardBase + ['view' => $todayView === $v ? 'all' : $v]);
    $cardOn   = fn (string $v) => $todayView === $v;

    // The status the control shows is the open tab's: each has its own
    // default until somebody picks one.
    $shownView = $openTab === 'history' ? $historyView : $todayView;

    // The shifts in a line: each one's hours, then the break and the regular
    // hours when every shift agrees on them.
    $scheduled = $shifts->filter->hasSchedule()->values();
    $hoursOf   = fn ($s) => $s->name . ' ' . WorkSchedule::label(\Carbon\Carbon::parse($s->starts_at))
                          . '–' . WorkSchedule::label(\Carbon\Carbon::parse($s->endsAt()));
    $sameBreak = $scheduled->pluck('break_minutes')->unique()->count() === 1;
    $sameReg   = $scheduled->pluck('regular_minutes')->unique()->count() === 1 && $scheduled->first()?->regular_minutes !== null;
    $schedLine = $scheduled->map($hoursOf)
        ->when($scheduled->isNotEmpty() && $sameBreak, fn ($l) => $l->push(__('Break') . ' ' . WorkSchedule::duration($scheduled->first()->break_minutes)))
        ->when($scheduled->isNotEmpty() && $sameReg, fn ($l) => $l->push(__('OT after') . ' ' . WorkSchedule::duration($scheduled->first()->regular_minutes)))
        ->implode(' · ');
    $canEdit = auth()->user()?->isAdmin();

    $statuses = [
        'all'        => __('All'),
        'clocked-in' => __('Working'),
        'break'      => __('On break'),
        'missed'     => __('Needs review'),
        'done'       => __('Done'),
    ];
@endphp

<div class="attendance-container atm">

    <x-page-header :title="__('Attendance Monitoring')">
        <x-slot:actions>
            <span class="atm-date"><i class="fas fa-calendar-day"></i>{{ now()->format('l, m/d/Y') }}</span>

            @if($scheduled->isNotEmpty())
                @if($canEdit)
                    <a class="atm-sched" href="{{ route('settings.index', ['tab' => 'attendance']) }}"
                       title="{{ $schedLine }}"
                       aria-label="{{ __('Edit the shift schedule in Payroll Settings') }}">
                @else
                    <div class="atm-sched" title="{{ $schedLine }}">
                @endif
                        <i class="far fa-clock"></i>
                        <span class="atm-sched-txt"><b>{{ __('Shift schedule') }}</b><small>{{ $schedLine }}</small></span>
                        @if($canEdit)
                            <span class="atm-sched-go">{{ __('Edit in Payroll Settings') }}<i class="fas fa-chevron-right"></i></span>
                        @endif
                @if($canEdit) </a> @else </div> @endif
            @endif
        </x-slot:actions>
    </x-page-header>

    {{-- ── Cards ────────────────────────────────────────────────────────────
         They follow the site and shift chosen below, so the numbers and the
         rows always describe the same crew. They do not follow the status or
         the search, which narrow the lists to a question about that crew.
         The arrow at the side puts them away until the page loads again.
         The fold sits outside #attStats, whose inside the filters and the
         live feed replace, so a fetch never reopens it. --}}
    <div class="atm-top">
    <button type="button" class="atm-handle" id="attStatsHandle" aria-expanded="true" aria-controls="attStatsFold"
            title="{{ __('Hide today\'s counts') }}" aria-label="{{ __('Hide today\'s counts') }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>
    </button>
    <div class="atm-fold open" id="attStatsFold">
    <div class="atm-fold-in">
    <div class="atm-stats" id="attStats" data-live="attendance employees">
        <a class="atm-stat is-brand {{ $cardOn('present') ? 'is-active' : '' }}" href="{{ $cardUrl('present') }}" data-view="present"
           @if($cardOn('present')) aria-current="true" @endif>
            <span class="atm-stat-lbl">{{ __('Present today') }}</span>
            <span class="atm-stat-num">{{ $presentToday }}</span>
            <span class="atm-stat-sub">
                {{ __('Scanned in') }}@if($presentToday) · {{ max(0, $presentToday - $nightCrew) }} {{ __('day') }}, {{ $nightCrew }} {{ __('night') }}@endif
            </span>
        </a>
        <a class="atm-stat is-good {{ $cardOn('clocked-in') ? 'is-active' : '' }}"
           href="{{ $cardUrl('clocked-in') }}" data-view="clocked-in"
           @if($cardOn('clocked-in')) aria-current="true" @endif>
            <span class="atm-stat-lbl">{{ __('Working now') }}</span>
            <span class="atm-stat-num">{{ $clockedIn }}</span>
            <span class="atm-stat-sub">
                @if($clockedIn)
                    {{ $clockedIn - $inSecond }} {{ __('in 1st session') }} · {{ $inSecond }} {{ __('in 2nd') }}
                @else
                    {{ __('On site, in a session') }}
                @endif
            </span>
        </a>
        <a class="atm-stat is-brk {{ $cardOn('break') ? 'is-active' : '' }}"
           href="{{ $cardUrl('break') }}" data-view="break"
           @if($cardOn('break')) aria-current="true" @endif>
            <span class="atm-stat-lbl">{{ __('On break') }}</span>
            <span class="atm-stat-num">{{ $onBreak }}</span>
            <span class="atm-stat-sub">
                {{ $overBreak ? $overBreak . ' ' . __('past the break') : __('Between sessions') }}
            </span>
        </a>
        <a @class(['atm-stat', 'is-bad', 'has-some' => $invalidCount > 0, 'is-active' => $cardOn('missed')])
           href="{{ $cardUrl('missed') }}" data-view="missed"
           @if($cardOn('missed')) aria-current="true" @endif>
            <span class="atm-stat-lbl">{{ __('Needs review') }}</span>
            <span class="atm-stat-num">{{ $invalidCount }}</span>
            <span class="atm-stat-sub">{{ $reviewToday }} {{ __('today') }} · {{ $reviewEarlier }} {{ __('earlier this week') }}</span>
        </a>
    </div>
    </div>
    </div>
    </div>

    <section class="atm-card" aria-label="{{ __('Attendance records') }}">

        <div class="atm-tabs" role="tablist">
            <button class="atm-tab {{ $openTab === 'today' ? 'active' : '' }}" type="button" role="tab"
                    data-bs-toggle="tab" data-bs-target="#att-today" data-tab="today"
                    aria-selected="{{ $openTab === 'today' ? 'true' : 'false' }}">
                {{ __('Today') }}
            </button>
            <button class="atm-tab {{ $openTab === 'history' ? 'active' : '' }}" type="button" role="tab"
                    data-bs-toggle="tab" data-bs-target="#att-history" data-tab="history"
                    aria-selected="{{ $openTab === 'history' ? 'true' : 'false' }}">
                {{ __('History') }}
                <span class="atm-count" id="attHistCount" data-live="attendance employees"
                      title="{{ __('Earlier days this week still waiting on a review') }}"
                      @if(! $reviewEarlier) hidden @endif>{{ $reviewEarlier }}</span>
            </button>
        </div>

        {{-- ── Filters ─────────────────────────────────────────────────────
             One GET form, so every choice combines with the others and the
             result is a URL. Script fetches it in place; without script the
             Apply button submits it. History is paginated fifteen days at a
             time, so the narrowing is the server's — hiding rows in the
             browser would filter the page you can see and ignore the rest. --}}
        <form method="GET" action="{{ route('attendance') }}" id="attFilters" class="atm-toolbar" role="search">

            <label class="atm-field">
                <i class="fas fa-magnifying-glass"></i>
                <input type="search" name="q" id="attSearch" value="{{ $search }}"
                       placeholder="{{ __('Search employee') }}" aria-label="{{ __('Search employee') }}" autocomplete="off">
            </label>

            <label class="atm-field">
                <i class="fas fa-location-dot"></i>
                <select name="site" id="attSite" aria-label="{{ __('Filter by site') }}">
                    <option value="">{{ __('All sites') }}</option>
                    @foreach($sites as $s)
                        <option value="{{ $s->id }}" @selected($siteId === $s->id)>{{ $s->name }}</option>
                    @endforeach
                </select>
            </label>

            <div class="atm-seg atm-push" style="order:10" role="radiogroup" aria-label="{{ __('Filter by shift') }}">
                <label><input type="radio" name="shift" value="" @checked($shiftId === null)><span>{{ __('All shifts') }}</span></label>
                @foreach($shifts as $sh)
                    <label>
                        <input type="radio" name="shift" value="{{ $sh->id }}" @checked($shiftId === $sh->id)>
                        <span><i class="fas {{ $sh->crosses_midnight ? 'fa-moon' : 'fa-sun' }}"></i>{{ $sh->name }}</span>
                    </label>
                @endforeach
            </div>

            <div class="atm-seg" role="radiogroup" aria-label="{{ __('Filter by status') }}">
                {{-- Working and On break are questions about now, so History,
                     where every day is over, has no buttons for them. --}}
                @foreach($statuses as $value => $label)
                    @php
                        $todayOnly = in_array($value, ['clocked-in', 'break'], true);
                        $labelAttr = $todayOnly ? ' data-today-only' . ($openTab === 'history' ? ' hidden' : '') : '';
                    @endphp
                    <label{!! $labelAttr !!}>
                        <input type="radio" name="view" value="{{ $value }}" @checked($shownView === $value)>
                        <span>{{ $label }}@if($value === 'missed' && $invalidCount)<em class="atm-count">{{ $invalidCount }}</em>@endif</span>
                    </label>
                @endforeach
            </div>

            {{-- How far back History reaches. It rides in the same form, so it
                 combines with the rest instead of clearing them. Shown only
                 while History is open — Today's Attendance is a single
                 workday. Hidden rather than removed, so the range survives a
                 trip to the other tab. --}}
            <label class="atm-field" id="attRangePick" @if($openTab !== 'history') hidden @endif>
                <i class="fas fa-calendar-days"></i>
                <select name="range" id="attRange" aria-label="{{ __('Filter history by date range') }}">
                    @foreach([
                        '7'    => __('Last 7 Days'),
                        '30'   => __('Last 30 Days'),
                        '3m'   => __('Last 3 Months'),
                        '6m'   => __('Last 6 Months'),
                        'year' => __('This Year'),
                        'all'  => __('All Time'),
                    ] as $key => $label)
                        {{-- (string) $key: PHP turns the two numeric keys into
                             ints, and a strict === against the string from the
                             query never matched them. --}}
                        <option value="{{ $key }}" @selected((string) $key === $range)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            {{-- The tab rides along, so a filter changed while reading History
                 comes back to History. --}}
            <input type="hidden" name="tab" id="attTabField" value="{{ $openTab }}">
            <noscript><button type="submit" class="atm-btn">{{ __('Apply') }}</button></noscript>

            {{-- No deleting from here. Every past day is what payroll is
                 computed from; a missing time out is fixed under its row. --}}
        </form>

        @php
            $heads = '<th>' . e(__('Employee')) . '</th>'
                   . '<th>' . e(__('AM in')) . '</th><th>' . e(__('AM out')) . '</th>'
                   . '<th class="atm-sep">' . e(__('PM in')) . '</th><th>' . e(__('PM out')) . '</th>'
                   . '<th>' . e(__('Hours')) . '</th><th>' . e(__('Status')) . '</th>'
                   . '<th class="atm-col-chev"><span class="visually-hidden">' . e(__('Open')) . '</span></th>';
        @endphp

        <div class="tab-content">

            <!-- ===== TODAY ===== -->
            <div class="tab-pane {{ $openTab === 'today' ? 'active' : '' }}" id="att-today" role="tabpanel">
                <div class="atm-scroll" id="attTodayList" data-live="attendance employees sites">
                    <table class="atm-table" id="todayTable">
                        <thead>
                            <tr>{!! $heads !!}</tr>
                        </thead>
                        <tbody>
                            @foreach($todayBoard as $d)
                                @include('attendance._day', ['d' => $d, 'tab' => 'today'])
                            @endforeach
                        </tbody>
                    </table>
                    @if($todayBoard->isEmpty())
                        <div class="atm-empty-state">
                            <i class="fas fa-fingerprint"></i>
                            <span>{{ $search !== '' ? __('Nobody on today\'s list matches these filters.') : match ($todayView) {
                                'clocked-in' => __('Nobody is clocked in right now.'),
                                'break'      => __('Nobody is on break right now.'),
                                'missed'     => __('Nothing on today\'s list is waiting on a review.'),
                                'done'       => __('No finished days yet today.'),
                                'all'        => __('Nobody on the roster matches these filters.'),
                                default      => __('No fingerprint scans yet today.'),
                            } }}</span>
                        </div>
                    @endif
                </div>
            </div>

            <!-- ===== HISTORY ===== -->
            <div class="tab-pane {{ $openTab === 'history' ? 'active' : '' }}" id="att-history" role="tabpanel">
                <div class="atm-scroll" id="attHistoryList" data-live="attendance employees sites">
                        <table class="atm-table" id="historyTable">
                            <thead>
                                <tr>{!! $heads !!}</tr>
                            </thead>
                            <tbody>
                                @foreach($historyBoard->groupBy(fn ($d) => $d->day->date()->toDateString()) as $date => $group)
                                    <tr class="atm-dayhead" data-live-key="date-{{ $date }}">
                                        <td colspan="8">{{ \Carbon\Carbon::parse($date)->format('l, m/d/Y') }}</td>
                                    </tr>
                                    @foreach($group as $d)
                                        @include('attendance._day', ['d' => $d, 'tab' => 'history'])
                                    @endforeach
                                @endforeach
                            </tbody>
                        </table>
                    @if($historyBoard->isEmpty())
                        <div class="atm-empty-state">
                            <i class="fas fa-clock-rotate-left"></i>
                            {{-- "Nothing here" and "nothing here lately" are different
                                 answers, and a reader who forgot the range is on would
                                 read the first as the second. --}}
                            <span>{{ $range === 'all'
                                ? __('No previous attendance records.')
                                : __('No attendance records in this date range.') }}</span>
                        </div>
                    @endif
                </div>

                {{-- appends(): these links live in the History pane, so page 2
                     has to carry the tab as well as the filters, or it lands on
                     Today's Attendance. --}}
                <div class="att-pager" id="attHistoryPager" data-live="attendance employees sites">{{ $historyAttendances->appends(array_filter(['tab' => 'history', 'view' => $view]))->links() }}</div>
            </div>
        </div>

        <div class="atm-foot">
            <span>{{ __('Click a row to see both sessions, the timeline, every scan, and to fix missing ones.') }}</span>
        </div>
    </section>
</div>

@push('scripts')
<script>
(function () {
    const card     = document.querySelector('.atm-card');
    const form     = document.getElementById('attFilters');
    const search   = document.getElementById('attSearch');
    const tabField = document.getElementById('attTabField');
    const regions  = ['attStats', 'attTodayList', 'attHistoryList', 'attHistoryPager', 'attHistCount'];
    const csrf     = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    if (!form || !card) return;

    // ── The open day, in a side panel ───────────────────────────────────────
    // Each row carries its detail in the (hidden) row under it. A click shows
    // a copy of that detail in a panel at the side. The table's markup is
    // replaced — by a filter, a fix, or the live feed — so which day is open
    // is kept here, and the panel is refreshed from the new markup.
    const shade = document.createElement('div');
    shade.className = 'atm-shade';
    const panel = document.createElement('aside');
    panel.className = 'atm-panel';
    panel.setAttribute('role', 'dialog');
    panel.setAttribute('aria-modal', 'true');
    panel.setAttribute('aria-label', @json(__('Attendance detail')));
    panel.hidden = true;
    document.body.append(shade, panel);

    let openKey = null;
    let shownHtml = null;

    function rowOf(key) {
        return document.querySelector('.tab-pane.active tr.atm-row[data-day="' + CSS.escape(key) + '"]')
            || document.querySelector('tr.atm-row[data-day="' + CSS.escape(key) + '"]');
    }

    function applyOpen() {
        const row = openKey ? rowOf(openKey) : null;
        const det = row && row.nextElementSibling && row.nextElementSibling.matches('tr.atm-detail') ? row.nextElementSibling : null;
        if (!det) openKey = null;

        document.querySelectorAll('tr.atm-row').forEach(tr => {
            const on = tr === row && !!det;
            tr.classList.toggle('is-open', on);
            tr.setAttribute('aria-expanded', on ? 'true' : 'false');
        });

        if (!det) {
            panel.classList.remove('on');
            shade.classList.remove('on');
            setTimeout(() => { if (!openKey) { panel.hidden = true; panel.innerHTML = ''; shownHtml = null; } }, 230);
            return;
        }

        // Copied again only when it changed, so a time being typed into a fix
        // is not wiped by a refresh that brought nothing new.
        const html = det.firstElementChild.innerHTML;
        if (html !== shownHtml) {
            const top = panel.scrollTop;
            panel.innerHTML = '<div class="atm-pbody">' + html + '</div>';
            panel.scrollTop = top;
            shownHtml = html;
        }
        panel.hidden = false;
        requestAnimationFrame(() => { panel.classList.add('on'); shade.classList.add('on'); respace(); });
    }

    function openDay(tr) {
        const was = openKey;
        openKey = was === tr.dataset.day ? null : tr.dataset.day;
        shownHtml = null;
        applyOpen();
        if (openKey) panel.querySelector('[data-atm-close]')?.focus({ preventScroll: true });
    }

    function closeDay() {
        if (!openKey) return;
        const row = rowOf(openKey);
        openKey = null;
        applyOpen();
        row?.focus({ preventScroll: true });
    }

    document.addEventListener('click', e => {
        if (e.target.closest('[data-atm-close]') || e.target === shade) { closeDay(); return; }
        const tr = e.target.closest('tr.atm-row');
        if (!tr) return;
        if (e.target.closest('input, button, a, label, select')) return;
        openDay(tr);
    });
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && openKey) { closeDay(); return; }
        if ((e.key === 'Enter' || e.key === ' ') && e.target.matches && e.target.matches('tr.atm-row')) {
            e.preventDefault();
            openDay(e.target);
        }
    });
    document.addEventListener('live:updated', applyOpen);

    // ── Timeline labels ─────────────────────────────────────────────────────
    // The break's label sits under the break, and on a night shift the break
    // can fall close enough to the start that the two labels ran into each
    // other. Where they would touch, the break's label moves along; where
    // there is no room at all it gives way — the hatched break and the
    // tooltip still say when it is. Measured, because only the browser knows
    // how wide the words came out.
    function spaceLabels() {
        panel.querySelectorAll('.atm-tlax').forEach(ax => {
            if (!ax.offsetParent) return;
            const [start, mid, end] = ax.querySelectorAll('span');
            if (!start || !mid || !end) return;

            mid.style.marginLeft = '';
            mid.style.visibility = '';

            const gap = 6;
            const from = start.getBoundingClientRect().right + gap;
            const to   = end.getBoundingClientRect().left - gap;
            const m    = mid.getBoundingClientRect();

            if (to - from < m.width) { mid.style.visibility = 'hidden'; return; }

            const left = Math.min(Math.max(m.left, from), to - m.width);
            if (left !== m.left) mid.style.marginLeft = (left - m.left) + 'px';
        });
    }

    // ── Down to the bottom of the screen ────────────────────────────────────
    // However little is in the list, the card reaches the foot of the
    // screen; a long list carries it on down with the page. A minimum
    // height, measured, because what sits above the card is not one height.
    // On a phone the page simply flows.
    const main = card.closest('.container-fluid') || card.parentElement;

    function fillDown() {
        card.style.minHeight = '';
        if (window.innerWidth < 768) return;

        const top  = card.getBoundingClientRect().top + window.scrollY;
        const foot = parseFloat(getComputedStyle(main).paddingBottom) || 0;
        const want = Math.floor(window.innerHeight - top - foot);
        card.style.minHeight = Math.max(0, want) + 'px';

        // Whatever still pushes the page a few pixels past the screen — a
        // margin the sum above did not see — comes off the minimum, but only
        // while the minimum is what sets the card's height.
        if (card.offsetHeight <= want + 1) {
            const over = document.documentElement.scrollHeight - window.innerHeight;
            if (over > 0) card.style.minHeight = Math.max(0, want - over) + 'px';
        }
    }

    let spacing = null;
    const respace = () => { cancelAnimationFrame(spacing); spacing = requestAnimationFrame(() => { fillDown(); spaceLabels(); }); };
    respace();
    document.fonts?.ready.then(respace);
    window.addEventListener('resize', respace);
    document.addEventListener('live:updated', respace);

    // ── The arrow at the side ───────────────────────────────────────────────
    // Out on every load (the markup opens the strip); put away, it stays away
    // only until the page loads again. The card below keeps its foot at the
    // bottom of the screen: as the strip folds, the card grows by the strip's
    // height at once, so it rises without its foot lifting, and it is
    // measured properly once the fold has finished.
    const fold   = document.getElementById('attStatsFold');
    const handle = document.getElementById('attStatsHandle');
    if (fold && handle) {
        const still = window.matchMedia('(prefers-reduced-motion: reduce)');
        handle.addEventListener('click', () => {
            const on = !fold.classList.contains('open');
            if (!on && card.style.minHeight) card.style.minHeight = (parseFloat(card.style.minHeight) + fold.offsetHeight) + 'px';
            fold.classList.toggle('open', on);
            handle.setAttribute('aria-expanded', on ? 'true' : 'false');
            const label = on ? @json(__('Hide today\'s counts')) : @json(__('Show today\'s counts'));
            handle.title = label; handle.setAttribute('aria-label', label);
            if (still.matches) respace();
        });
        fold.addEventListener('transitionend', e => {
            if (e.target === fold && e.propertyName === 'grid-template-rows') respace();
        });
    }

    // ── Filters, fetched in place ───────────────────────────────────────────
    // Any choice asks the server for the page as it would be, and swaps in
    // the parts that changed: the cards and both lists. The address bar
    // follows, so a refresh or a copied link lands on the same view.
    // Whether somebody has picked a status. Until they do, each tab shows its
    // own default — everybody present today, and every past day — and the
    // address bar carries no status at all.
    const DEFAULTS = { today: 'present', history: 'all' };
    let chosen = new URL(location).searchParams.get('view');

    function showStatus(v) {
        document.querySelectorAll('input[name="view"]').forEach(r => { r.checked = r.value === v; });
    }

    function urlOf() {
        const params = new URLSearchParams(new FormData(form));
        for (const [k, v] of [...params]) {
            if (v === '' || (k === 'range' && v === 'all') || (k === 'tab' && v === 'today')) params.delete(k);
        }
        params.delete('view');
        if (chosen) params.set('view', chosen);
        const q = params.toString();
        return form.action + (q ? '?' + q : '');
    }

    let pending = null;

    async function reload(url) {
        pending?.abort();
        const ctrl = new AbortController();
        pending = ctrl;
        card.classList.add('is-loading');

        try {
            const res = await fetch(url, {
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                signal: ctrl.signal,
            });
            if (!res.ok) { location.href = url; return; }

            const fresh = new DOMParser().parseFromString(await res.text(), 'text/html');
            regions.forEach(id => {
                const here = document.getElementById(id);
                const next = fresh.getElementById(id);
                if (!here || !next) return;
                here.innerHTML = next.innerHTML;
                here.hidden = next.hidden;
            });

            history.replaceState(null, '', url);
            applyOpen();
            respace();
        } catch (err) {
            if (err.name !== 'AbortError') location.href = url;
        } finally {
            if (pending === ctrl) {
                pending = null;
                card.classList.remove('is-loading');
            }
        }
    }

    form.addEventListener('change', e => {
        if (e.target === search) return;
        if (e.target.name === 'view') chosen = e.target.value;
        reload(urlOf());
    });

    let typing = null;
    search?.addEventListener('input', () => {
        clearTimeout(typing);
        typing = setTimeout(() => reload(urlOf()), 300);
    });
    form.addEventListener('submit', e => { e.preventDefault(); clearTimeout(typing); reload(urlOf()); });
    search?.addEventListener('keydown', e => {
        if (e.key === 'Enter') { e.preventDefault(); clearTimeout(typing); reload(urlOf()); }
    });

    // A card sets the status control, and the control fetches — so both
    // always show the same thing. Clicking the card that is on shows everybody.
    // Present has no button of its own, so while it is on none is lit.
    document.getElementById('attStats')?.addEventListener('click', e => {
        const a = e.target.closest('a[data-view]');
        if (!a || e.ctrlKey || e.metaKey || e.shiftKey) return;
        e.preventDefault();
        chosen = a.classList.contains('is-active') ? 'all' : a.dataset.view;
        showStatus(chosen);
        reload(urlOf());
    });

    // ── Tabs ────────────────────────────────────────────────────────────────
    // The range only means anything on History, and Working and On break
    // only on today, so they come and go with the tab; the tab rides in the
    // address bar too.
    const rangePick = document.getElementById('attRangePick');
    const todayOnly = ['clocked-in', 'break'];

    document.querySelectorAll('.atm-tab[data-tab]').forEach(btn => {
        btn.addEventListener('shown.bs.tab', () => {
            const tab = btn.dataset.tab;
            tabField.value   = tab;
            rangePick.hidden = tab !== 'history';
            document.querySelectorAll('[data-today-only]').forEach(el => { el.hidden = tab === 'history'; });

            const url = new URL(window.location);
            if (tab === 'history') url.searchParams.set('tab', 'history');
            else                   url.searchParams.delete('tab');
            history.replaceState(null, '', url);

            // Each tab shows the status it is filtered by: its own default
            // until somebody picks one, and on History never a button it
            // does not have — History reads those as everybody.
            const want = chosen || DEFAULTS[tab];
            showStatus(tab === 'history' && todayOnly.includes(want) ? 'all' : want);

            // The pane that was hidden had nothing to measure.
            respace();
        });
    });

    // ── Fixing a time out nobody scanned ────────────────────────────────────
    document.addEventListener('submit', async e => {
        const f = e.target.closest('form[data-fix]');
        if (!f) return;
        e.preventDefault();

        const btn  = e.submitter;
        const time = btn && btn.name === 'time' ? btn.value : f.querySelector('input[type="time"]').value;
        if (!time) { Notify.warning(@json(__('Enter a time first.'))); return; }

        f.querySelectorAll('button').forEach(b => b.disabled = true);
        try {
            const res  = await fetch(f.dataset.fix, {
                method: 'PATCH',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify({ time }),
            });
            const data = await res.json().catch(() => ({}));
            if (res.ok && data.success) {
                Notify.success(data.message);
                await reload(location.href);
            } else {
                Notify.error(data.message || @json(__('The time could not be saved.')));
                f.querySelectorAll('button').forEach(b => b.disabled = false);
            }
        } catch (err) {
            Notify.error(@json(__('Request failed:')) + ' ' + err.message);
            f.querySelectorAll('button').forEach(b => b.disabled = false);
        }
    });

    // ── Settling a day with no break scans ──────────────────────────────────
    document.addEventListener('submit', async e => {
        const f = e.target.closest('form[data-fix-break]');
        if (!f) return;
        e.preventDefault();

        const decision = e.submitter && e.submitter.value;
        if (!['accept', 'decline', 'undo'].includes(decision)) return;

        // Declining takes a day out of the pay, so it is asked about first, by name.
        if (decision === 'decline') {
            const ok = await Notify.confirm({
                title:        @json(__('Mark this day Not recorded?')),
                message:      @json(__(':name — :day, :times. It stays on the page as Not recorded and is not paid. You can undo it.'))
                                  .replace(':name', f.dataset.who).replace(':day', f.dataset.day).replace(':times', f.dataset.times),
                confirmLabel: @json(__('Decline · not recorded')),
                cancelLabel:  @json(__('Cancel')),
                tone:         'danger',
            });
            if (!ok) return;
        }

        f.querySelectorAll('button').forEach(b => b.disabled = true);
        try {
            const res  = await fetch(f.dataset.fixBreak, {
                method: 'PATCH',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify({ decision }),
            });
            const data = await res.json().catch(() => ({}));
            if (res.ok && data.success) {
                Notify.success(data.message);
                await reload(location.href);
            } else {
                Notify.error(data.message || @json(__('That could not be saved.')));
                f.querySelectorAll('button').forEach(b => b.disabled = false);
            }
        } catch (err) {
            Notify.error(@json(__('Request failed:')) + ' ' + err.message);
            f.querySelectorAll('button').forEach(b => b.disabled = false);
        }
    });

    // ── Undoing a time out the office set, deleting a day in review ────────
    // Undo goes straight through: it only puts the day back in Needs review.
    // Deleting cannot be taken back, so it is asked about first, by name.
    async function send(f, url, method, fallback) {
        f.querySelectorAll('button').forEach(b => b.disabled = true);
        try {
            const res  = await fetch(url, {
                method,
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
            });
            const data = await res.json().catch(() => ({}));
            if (res.ok && data.success) {
                Notify.success(data.message);
                await reload(location.href);
            } else {
                Notify.error(data.message || fallback);
                f.querySelectorAll('button').forEach(b => b.disabled = false);
            }
        } catch (err) {
            Notify.error(@json(__('Request failed:')) + ' ' + err.message);
            f.querySelectorAll('button').forEach(b => b.disabled = false);
        }
    }

    document.addEventListener('submit', async e => {
        const undo = e.target.closest('form[data-undo-out]');
        const del  = e.target.closest('form[data-delete-day]');
        if (!undo && !del) return;
        e.preventDefault();

        if (undo) {
            await send(undo, undo.dataset.undoOut, 'PATCH', @json(__('That could not be undone.')));
            return;
        }

        const ok = await Notify.confirm({
            title:        @json(__('Delete this day?')),
            message:      @json(__(':name — :day. Every scan of this day is deleted, and it cannot be undone. The audit log keeps the times.'))
                              .replace(':name', del.dataset.who).replace(':day', del.dataset.day),
            confirmLabel: @json(__('Delete this day')),
            cancelLabel:  @json(__('Cancel')),
            tone:         'danger',
        });
        if (!ok) return;

        await send(del, del.dataset.deleteDay, 'DELETE', @json(__('The day could not be deleted.')));
    });

    // ── Keeping "now" current ───────────────────────────────────────────────
    // The live feed re-reads the lists when somebody scans, but a break turns
    // into an overbreak with nobody scanning at all. Once a minute, while the
    // page is looked at, it is re-read for that alone.
    setInterval(() => {
        if (document.hidden || !window.Live || typeof window.Live.refresh !== 'function') return;
        window.Live.refresh();
    }, 60000);
})();
</script>
@endpush

@endsection
