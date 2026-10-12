@extends('layouts')
@section('page_title', 'Employees')

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@2.47.0/tabler-icons.min.css">
{{-- The Register Employee page's own chrome (.ep-label, .ep-hint, .ep-req,
     .ep-optional, .ep-mono) and the dialog's, included rather than copied so
     the modal and the full form cannot drift apart. In the head, not the
     body: a stylesheet the parser only reaches late paints the page unstyled
     first. --}}
@include('employees._profile_styles')
@include('employees._modal_styles')
<style>
/* Register & Manage. The redesigned parts are named .rmx-*: the older .rm-*
   names are claimed by site-wide rules marked !important (a brand bar down
   the left of every .rm-tab, a grey chip on every .rm-pill) that the design
   cannot sit under. .rm-pane stays — it is the hook the tabs, and the tests
   pinning which tab opens, read. Light is the base palette; dark follows. */
.rmx {
    --rmx-card: #FFFFFF;   --rmx-track: #E9EDF2;  --rmx-tab-on: #FFFFFF;
    --rmx-table: #FFFFFF;  --rmx-thead: #F8F9FB;  --rmx-hover: #F5F7FA;
    --rmx-chip: #F2F4F7;
    --rmx-line: #E4E7EC;   --rmx-line-strong: #D0D5DD; --rmx-bw: 1px;
    --rmx-txt: #101828;    --rmx-txt-2: #475467;  --rmx-txt-3: #667085;
    --rmx-primary: #185FA5;
    --rmx-accent-bg: #EAF2FD; --rmx-accent-fg: #1668DC; --rmx-accent-line: #C5DAF7;
    --rmx-danger-bg: #FEF3F2; --rmx-danger-fg: #B42318; --rmx-danger-line: #FECDCA;
    --rmx-ok-bg: #ECFDF3;     --rmx-ok-fg: #027A48;     --rmx-ok-line: #ABEFC6;
    --rmx-warn-bg: #FFFAEB;   --rmx-warn-fg: #B54708;   --rmx-warn-line: #FEDF89;
    --rmx-green: #22C55E;     --rmx-amber: #F59E0B;     --rmx-slate: #64748B;
    --rmx-green-ico: #16A34A; --rmx-amber-ico: #D97706;
    --rmx-lift: 0 1px 2px rgba(16, 24, 40, .06);
    color: var(--rmx-txt);
}
html[data-bs-theme="dark"] .rmx {
    --rmx-card: #131E2D;   --rmx-track: #131E2D;  --rmx-tab-on: #1E2B3F;
    --rmx-table: #151F2F;  --rmx-thead: #131C2B;  --rmx-hover: #1A2638;
    --rmx-chip: #1B2638;
    --rmx-line: rgba(255, 255, 255, .08); --rmx-line-strong: rgba(255, 255, 255, .16); --rmx-bw: .5px;
    --rmx-txt: #E7ECF3;    --rmx-txt-2: #9CA9BD;  --rmx-txt-3: #78879E;
    --rmx-accent-bg: rgba(59, 130, 246, .16); --rmx-accent-fg: #6FAEFF; --rmx-accent-line: rgba(79, 151, 245, .45);
    --rmx-danger-bg: rgba(239, 68, 68, .14);  --rmx-danger-fg: #F87171; --rmx-danger-line: rgba(239, 68, 68, .45);
    --rmx-ok-bg: rgba(34, 197, 94, .14);      --rmx-ok-fg: #4ADE80;     --rmx-ok-line: rgba(34, 197, 94, .35);
    --rmx-warn-bg: rgba(245, 158, 11, .14);   --rmx-warn-fg: #FBBF24;   --rmx-warn-line: rgba(245, 158, 11, .4);
    --rmx-green-ico: #4ADE80; --rmx-amber-ico: #FBBF24;
    --rmx-lift: none;
}
.rmx *, .rmx *::before, .rmx *::after { box-sizing: border-box; }

/* Validation errors the modal could not show */
.rm-alert { display: flex; gap: 10px; align-items: flex-start; padding: 12px 16px; border-radius: 10px; font-size: 13.5px; margin-bottom: 18px; border-left: 4px solid transparent; }
.rm-alert-err { background: var(--rmx-danger-bg); color: var(--rmx-danger-fg); border-left-color: #DC2626; }

/* Header buttons */
.rmx-primary { height: 38px; padding: 0 16px; display: inline-flex; align-items: center; gap: 6px; flex-shrink: 0;
    background: var(--rmx-primary); color: #fff; border: none; border-radius: 8px; font-size: 13.5px; font-weight: 500;
    text-decoration: none; transition: filter .15s; }
.rmx-primary:hover, .rmx-primary:focus { color: #fff; text-decoration: none; filter: brightness(1.1); }
.rmx-primary i { font-size: 16px; }
.rmx-head-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
/* On the page header's right, both as tall as the header itself. */
.rmx .page-head-actions > :is(.rmx-outline, .rmx-primary) { height: 36px; text-decoration: none; }

/* Stat cards — they also switch the list. Drawn as the Dashboard's KPI tiles:
   a tinted icon square, a small capitalised label, the number, one line under. */
.rmx-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: .75rem; }
.rmx-stat {
    appearance: none; margin: 0; font: inherit; color: inherit; text-align: left; width: 100%; cursor: pointer;
    display: flex; align-items: center; gap: 10px; min-width: 0; padding: 10px 12px;
    background: var(--bg-surface, #fff); border: 1px solid var(--border, #E4E7EC); border-radius: var(--radius-md, 10px);
    transition: border-color .15s ease, background .15s ease;
}
.rmx-stat:hover { border-color: var(--brand, #1668DC); background: var(--brand-subtle, #EAF2FD); }
.rmx-stat-ic {
    width: 32px; height: 32px; border-radius: 8px; flex: none;
    display: flex; align-items: center; justify-content: center; font-size: 13px;
}
.rmx-stat-active  .rmx-stat-ic { background: var(--brand-subtle); color: var(--brand); }
.rmx-stat-pending .rmx-stat-ic { background: var(--warning-soft); color: var(--warning); }
.rmx-stat-removed .rmx-stat-ic { background: var(--danger-soft);  color: var(--danger); }
.rmx-stat-body { min-width: 0; display: flex; flex-direction: column; }
.rmx-stat-lbl {
    order: 1; font-size: .6rem; font-weight: 700; letter-spacing: .03em; text-transform: uppercase;
    color: var(--text-muted); margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.rmx-stat-num {
    order: 2; display: block; font-size: 1.02rem; font-weight: 700; color: var(--text-primary);
    font-variant-numeric: tabular-nums; line-height: 1.2; margin: 1px 0 0;
}
.rmx-stat-sub { order: 3; font-size: .64rem; font-weight: 600; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
@media (max-width: 720px) {
    .rmx-stats { gap: 8px; }
    .rmx-stat { padding: 8px 9px; gap: 8px; }
    .rmx-stat-ic { width: 26px; height: 26px; font-size: 11px; }
    .rmx-stat-sub { display: none; }
}

/* Tabs and the one Select toggle */
.rmx-bar { display: flex; align-items: center; justify-content: space-between; margin-bottom: .6rem; flex-wrap: wrap; gap: 10px; }
.rmx-tabs { display: inline-flex; background: var(--rmx-track); border-radius: 10px; padding: 3px; gap: 2px; }
.rmx-tab { appearance: none; border: none; background: none; color: var(--rmx-txt-2); font: inherit; font-size: 12.5px;
    padding: 5px 14px; border-radius: 7px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;
    transition: background .15s, color .15s; }
.rmx-tab:hover { color: var(--rmx-txt); }
.rmx-tab.is-open { background: var(--rmx-tab-on); color: var(--rmx-txt); font-weight: 500; box-shadow: var(--rmx-lift); }
.rmx-count { background: var(--rmx-chip); color: var(--rmx-txt-3); font-size: 11px; padding: 1px 7px; border-radius: 10px; font-variant-numeric: tabular-nums; }
.rmx-tab.is-open .rmx-count { background: var(--rmx-accent-bg); color: var(--rmx-accent-fg); }
.rmx-outline { height: 30px; padding: 0 13px; display: inline-flex; align-items: center; gap: 6px; background: transparent;
    color: var(--rmx-txt); border: var(--rmx-bw) solid var(--rmx-line-strong); border-radius: 8px; font: inherit;
    font-size: 12.5px; cursor: pointer; transition: background .15s, border-color .15s; }
.rmx-outline i { font-size: 15px; }
.rmx-outline:hover { background: var(--rmx-hover); }
.rmx-outline.is-on { background: var(--rmx-primary); border-color: var(--rmx-primary); color: #fff; }
.rmx-outline:disabled { opacity: .5; cursor: not-allowed; }
/* Search, before Select: as tall as the button beside it. */
.rmx-tools { display: flex; align-items: center; gap: 8px; }
.rmx-search { display: flex; align-items: center; gap: 7px; height: 30px; width: 230px; margin: 0; padding: 0 10px;
    background: var(--rmx-card); border: var(--rmx-bw) solid var(--rmx-line-strong); border-radius: 8px; cursor: text;
    transition: border-color .15s, box-shadow .15s; }
.rmx-search:focus-within { border-color: var(--brand, #1668DC); box-shadow: 0 0 0 3px var(--rmx-accent-bg); }
.rmx-search i { font-size: 15px; color: var(--rmx-txt-3); flex: none; }
html[data-bs-theme] .rmx .rmx-search input {
    flex: 1; min-width: 0; height: 28px; padding: 0; border: 0 !important; background: transparent !important;
    box-shadow: none !important; outline: none; font: inherit; font-size: 12.5px; color: var(--rmx-txt);
}
.rmx-search input::placeholder { color: var(--rmx-txt-3); }
.rmx-search input::-webkit-search-cancel-button { cursor: pointer; }

/* Panes */
.rm-pane { display: none; }
.rm-pane.active { display: block; }

/* Table card */
.rmx-card { background: var(--rmx-table); border: var(--rmx-bw) solid var(--rmx-line); border-radius: 12px; overflow: hidden; }
.rmx-note { display: flex; gap: 8px; align-items: flex-start; padding: 10px 14px; font-size: 12.5px; color: var(--rmx-txt-2);
    background: var(--rmx-thead); border-bottom: var(--rmx-bw) solid var(--rmx-line); }
.rmx-note i { color: var(--rmx-accent-fg); font-size: 15px; margin-top: 1px; flex: none; }
/* One flex item for the sentence, or each text node becomes its own column. */
.rmx-note > span { flex: 1; min-width: 0; line-height: 1.6; }
.rmx-table-wrap { overflow-x: auto; }
.rmx-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.rmx-table thead tr { background: var(--rmx-thead); }
.rmx-table th { text-align: left; padding: 8px 10px; font-size: 10.5px; font-weight: 500; color: var(--rmx-txt-3);
    text-transform: uppercase; letter-spacing: .5px; white-space: nowrap; }
.rmx-table td { padding: 6px 10px; border-top: var(--rmx-bw) solid var(--rmx-line); vertical-align: middle; color: var(--rmx-txt-2); font-size: 12.5px; }
.rmx-table th:first-child, .rmx-table td:first-child { padding-left: 14px; }
.rmx-table th:last-child,  .rmx-table td:last-child  { padding-right: 14px; }
.rmx-table tbody tr { transition: background .12s; }
.rmx-table tbody tr:hover { background: var(--rmx-hover); }
.rmx-table .rmx-num { text-align: right; }
.rmx-table .rmx-center { text-align: center; }

/* Selection: the checkbox column exists only while its pane is selecting. */
.rmx-check-col { display: none; width: 38px; text-align: center; }
.rm-pane.selecting .rmx-check-col { display: table-cell; }
.rmx-check, .rmx-check-all { width: 16px; height: 16px; cursor: pointer; accent-color: var(--rmx-primary); vertical-align: middle; }
.rmx-check-all:disabled { cursor: not-allowed; opacity: .4; }
.rmx-bulk { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; padding: 10px 14px;
    background: var(--rmx-accent-bg); border-bottom: var(--rmx-bw) solid var(--rmx-line); }
.rmx-bulk[hidden] { display: none; }
.rmx-bulk-count { font-size: 13px; font-weight: 500; color: var(--rmx-txt); }
.rmx-bulk-count strong { font-size: 14px; }
.rmx-bulk-spacer { flex: 1 1 auto; }
.rmx-bulk-btn { height: 30px; padding: 0 12px; border-radius: 7px; font: inherit; font-size: 12.5px; font-weight: 500; cursor: pointer;
    display: inline-flex; align-items: center; gap: 6px; background: transparent; color: var(--rmx-txt);
    border: var(--rmx-bw) solid var(--rmx-line-strong); transition: filter .15s; }
.rmx-bulk-btn:hover { filter: brightness(1.08); }
.rmx-bulk-btn.danger { background: #DC2626; border-color: #DC2626; color: #fff; }
.rmx-bulk-btn:disabled { opacity: .55; cursor: not-allowed; }

/* A worker */
.rmx-person { display: flex; align-items: center; gap: 10px; min-width: 0; }
.rmx-avatar { width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center;
    font-size: 12px; font-weight: 600; flex-shrink: 0; overflow: hidden; }
.rmx-avatar img { width: 100%; height: 100%; object-fit: cover; }
/* Every worker the same: the brand tint, the icon in the brand colour. */
.rmx-avatar { background: var(--brand-subtle, #EAF2FD); color: var(--brand, #1668DC); }
.rmx-avatar-ico { width: 62%; height: 62%; display: block; }
.rmx-avatar-ico[hidden] { display: none; }
.rmx-who { min-width: 0; }
.rmx-name { font-size: 13px; font-weight: 500; color: var(--rmx-txt); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 260px; }
.rmx-name.is-muted { color: var(--rmx-warn-fg); font-style: italic; }
.rmx-id { font-size: 11px; color: var(--rmx-txt-3); font-variant-numeric: tabular-nums; margin-top: 1px; }
.rmx-tags { display: flex; gap: 6px; flex-wrap: wrap; margin: 5px 0 0 44px; }

/* Cells */
.rmx-pill { display: inline-flex; align-items: center; gap: 4px; font-size: 11.5px; padding: 2px 8px; border-radius: 20px; white-space: nowrap;
    background: var(--rmx-chip); color: var(--rmx-txt-2); border: var(--rmx-bw) solid var(--rmx-line); }
.rmx-pill i { font-size: 12px; }
.rmx-pill-ok     { background: var(--rmx-ok-bg);     color: var(--rmx-ok-fg);     border-color: transparent; }
.rmx-pill-warn   { background: var(--rmx-warn-bg);   color: var(--rmx-warn-fg);   border-color: transparent; }
.rmx-pill-accent { background: var(--rmx-accent-bg); color: var(--rmx-accent-fg); border-color: transparent; }
.rmx-labor { display: inline-flex; align-items: center; gap: 5px; color: var(--rmx-txt-2); font-size: 13px; white-space: nowrap; }
.rmx-labor i { font-size: 14px; color: var(--rmx-txt-3); }
.rmx-labor + .rmx-pill { margin-left: 6px; }
.rmx-shift { display: inline-flex; align-items: center; gap: 5px; color: var(--rmx-txt-2); font-size: 13px; white-space: nowrap; }
.rmx-shift i { font-size: 14px; color: var(--rmx-txt-3); }
.rmx-shift small { font-size: 11.5px; color: var(--rmx-txt-3); margin-left: 2px; }
.rmx-rate { text-align: right; font-weight: 500; color: var(--rmx-txt); font-variant-numeric: tabular-nums; white-space: nowrap; }
.rmx-logs { display: inline-flex; align-items: center; justify-content: center; min-width: 22px; height: 22px; padding: 0 6px;
    border-radius: 11px; background: var(--rmx-chip); color: var(--rmx-txt-2); font-size: 12px; font-variant-numeric: tabular-nums; }
.rmx-dash { color: var(--rmx-txt-3); }
.rmx-muted { color: var(--rmx-txt-3); font-size: 12.5px; white-space: nowrap; }

/* Row actions */
.rmx-actions { text-align: right; white-space: nowrap; }
.rmx-actions-inner { display: inline-flex; align-items: center; gap: 6px; justify-content: flex-end; }
.rmx-actions form { display: inline; margin: 0; }
.rmx-icon-btn, .rmx-text-btn { border-radius: 7px; display: inline-flex; align-items: center; justify-content: center; cursor: pointer;
    text-decoration: none; font: inherit; transition: filter .15s; }
.rmx-icon-btn { width: 28px; height: 28px; padding: 0; font-size: 13px; }
.rmx-text-btn { height: 28px; padding: 0 12px; gap: 6px; font-size: 12.5px; font-weight: 500; }
.rmx-icon-btn i, .rmx-text-btn i { font-size: 15px; }
.rmx-icon-btn i { font-size: 13px; }
.rmx-icon-btn:hover, .rmx-text-btn:hover { filter: brightness(1.15); text-decoration: none; }
.rmx-edit, .rmx-edit:hover       { background: var(--rmx-accent-bg); color: var(--rmx-accent-fg); border: var(--rmx-bw) solid var(--rmx-accent-line); }
.rmx-del, .rmx-del:hover         { background: var(--rmx-danger-bg); color: var(--rmx-danger-fg); border: var(--rmx-bw) solid var(--rmx-danger-line); }
.rmx-gift, .rmx-gift:hover       { background: var(--rmx-warn-bg); color: var(--rmx-warn-fg); border: var(--rmx-bw) solid var(--rmx-warn-line); position: relative; }
.rmx-gift-n { position: absolute; top: -6px; right: -6px; min-width: 16px; height: 16px; padding: 0 4px; border-radius: 8px;
    background: var(--rmx-warn-fg); color: #fff; font-size: 10px; font-weight: 700; line-height: 16px; text-align: center; }
.rmx-restore, .rmx-restore:hover { background: var(--rmx-ok-bg);     color: var(--rmx-ok-fg);     border: var(--rmx-bw) solid var(--rmx-ok-line); }

/* Empty tab */
.rmx-empty td { padding: 0 !important; }
.rmx-empty-inner { display: flex; flex-direction: column; align-items: center; padding: 48px 16px; text-align: center; }
.rmx-empty-icon { width: 56px; height: 56px; border-radius: 50%; background: var(--rmx-chip); display: flex; align-items: center;
    justify-content: center; font-size: 24px; color: var(--rmx-txt-3); margin-bottom: 12px; }
.rmx-empty-title { font-size: 14.5px; font-weight: 500; color: var(--rmx-txt); margin: 0 0 4px; }
.rmx-empty-sub { font-size: 12.5px; color: var(--rmx-txt-3); margin: 0; max-width: 380px; }

/* ── A bonus already given this period, inside the Add bonus dialog ──── */
.emp-bonus-given {
    display: flex; align-items: center; justify-content: space-between; gap: 10px;
    padding: 7px 0; font-size: 12.5px; color: var(--text-primary);
    border-bottom: 1px solid var(--border);
}
.emp-bonus-given:last-child { border-bottom: none; }
.emp-bonus-remove {
    border: none; background: none; padding: 0;
    font-size: 12px; font-weight: 600; color: var(--danger); cursor: pointer;
}
.emp-bonus-remove:hover     { text-decoration: underline; }
.emp-bonus-remove:disabled  { opacity: .55; cursor: not-allowed; }

/* The three cards, behind the arrow at the side. They open by the height of
   a grid row (0fr → 1fr), so the list below is pushed down smoothly and
   nothing jumps; the cards slide in from the arrow's side as they appear. */
.rmx-top { position: relative; }
.rmx-sumwrap { display: grid; grid-template-rows: 0fr; transition: grid-template-rows .16s ease-in; }
.rmx-sumwrap.open { grid-template-rows: 1fr; transition: grid-template-rows .22s ease-out; }
.rmx-sum-in { min-height: 0; overflow: hidden; }
.rmx-sumwrap .rmx-stats { transform: translateX(-16px); opacity: 0; transition: transform .16s ease-in, opacity .16s ease-in; }
.rmx-sumwrap.open .rmx-stats { transform: none; opacity: 1; transition: transform .22s ease-out, opacity .22s ease-out; }
@media (prefers-reduced-motion: reduce) { .rmx-sumwrap, .rmx-sumwrap .rmx-stats { transition: none !important; } }
.rmx-stat.is-on { border-color: var(--brand, #1668DC); background: var(--brand-subtle, #EAF2FD); box-shadow: inset 0 0 0 1px var(--brand, #1668DC); }

/* The arrow: a tab on the edge by the navigation bar. › shows the cards, ‹ hides them. */
.rmx-handle {
    position: absolute; z-index: 5; top: 0; left: calc(-1 * var(--rmx-gutter, 20px));
    width: 17px; height: 38px; padding: 0; cursor: pointer;
    display: grid; place-items: center; color: var(--brand, #1668DC);
    background: var(--rmx-card, #fff); border: var(--rmx-bw, 1px) solid var(--rmx-line-strong, #D0D5DD); border-left: 0;
    border-radius: 0 10px 10px 0; box-shadow: 3px 0 10px rgba(16, 24, 40, .08);
    transition: background .15s, color .15s, height .22s ease-out;
}
.rmx-handle svg { width: 13px; height: 13px; transition: transform .22s ease-out; }
.rmx-handle:hover { background: var(--rmx-hover); }
.rmx-handle:focus-visible { outline: 2px solid var(--brand, #1668DC); outline-offset: 2px; }
.rmx-handle[aria-expanded="true"] { background: var(--brand, #1668DC); border-color: var(--brand, #1668DC); color: #fff; height: 62px; }
.rmx-handle[aria-expanded="true"] svg { transform: rotate(180deg); }
@media (prefers-reduced-motion: reduce) { .rmx-handle, .rmx-handle svg { transition: none; } }

/* Which list is showing, where the tabs were. */
.rmx-showing { display: inline-flex; align-items: center; gap: 8px; min-height: 34px; font-size: 14px; color: var(--rmx-txt); }
.rmx-showing b { font-weight: 600; }

/* The Site and Labor type headings are filters: a click lists what is in the
   column, with how many workers each holds. */
.rmx-fbtn { display: inline-flex; align-items: center; gap: 4px; margin: -3px -6px; padding: 3px 6px; border: 0; border-radius: 6px;
    background: none; font: inherit; color: inherit; text-transform: inherit; letter-spacing: inherit; cursor: pointer; }
.rmx-fbtn i { font-size: 12px; transition: transform .15s; }
.rmx-fbtn:hover { background: var(--rmx-hover); color: var(--rmx-txt); }
.rmx-fbtn:focus-visible { outline: 2px solid var(--brand, #1668DC); outline-offset: 1px; }
.rmx-fbtn.is-on { background: var(--rmx-accent-bg); color: var(--rmx-accent-fg); font-weight: 600; }
.rmx-fbtn[aria-expanded="true"] i { transform: rotate(180deg); }
/* A filtered-out row stays gone even where the phone layout draws rows as cards. */
.rmx-table tbody tr[hidden] { display: none !important; }

.rmx-fmenu { position: fixed; z-index: 1060; min-width: 210px; max-width: 290px; max-height: 340px; overflow-y: auto; overscroll-behavior: contain; padding: 5px;
    background: var(--rmx-card); border: var(--rmx-bw) solid var(--rmx-line-strong); border-radius: 10px;
    box-shadow: 0 12px 28px rgba(16, 24, 40, .16); }
.rmx-fmenu[hidden] { display: none; }
.rmx-fopt { display: flex; align-items: center; justify-content: space-between; gap: 14px; width: 100%; padding: 7px 10px;
    border: 0; border-radius: 7px; background: none; font-size: 13px; color: var(--rmx-txt); text-align: left; cursor: pointer; }
.rmx-fopt span { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.rmx-fopt b { flex: none; font-size: 11px; font-weight: 600; color: var(--rmx-txt-3); background: var(--rmx-chip);
    padding: 1px 7px; border-radius: 10px; font-variant-numeric: tabular-nums; }
.rmx-fopt:hover, .rmx-fopt:focus-visible { background: var(--rmx-hover); outline: none; }
.rmx-fopt[aria-checked="true"] { color: var(--rmx-accent-fg); font-weight: 600; }
.rmx-fopt[aria-checked="true"] b { background: var(--rmx-accent-bg); color: var(--rmx-accent-fg); }
.rmx-fopt.is-all { margin-bottom: 4px; }

/* Beside the list's count: what it is narrowed to, and how many that leaves. */
.rmx-fsum { display: inline-flex; align-items: center; gap: 6px; margin-left: 4px; padding: 3px 3px 3px 9px; border-radius: 999px;
    font-size: 12.5px; color: var(--rmx-accent-fg); background: var(--rmx-accent-bg); }
.rmx-fsum[hidden] { display: none; }
.rmx-fsum b { font-weight: 700; font-variant-numeric: tabular-nums; }
.rmx-fclear { display: grid; place-items: center; width: 20px; height: 20px; padding: 0; border: 0; border-radius: 50%;
    background: none; color: inherit; cursor: pointer; }
.rmx-fclear:hover { background: var(--rmx-accent-line); }
.rmx-fempty { padding: 34px 16px; text-align: center; font-size: 13px; color: var(--rmx-txt-2); border-top: var(--rmx-bw) solid var(--rmx-line); }
.rmx-fempty[hidden] { display: none; }
.rmx-fclear-link { border: 0; background: none; padding: 0; color: var(--rmx-accent-fg); font-weight: 600; cursor: pointer; }
.rmx-fclear-link:hover { text-decoration: underline; }

/* A worker's name opens their card. */
.rmx-open { all: unset; display: block; cursor: pointer; border-radius: 8px; margin: -4px -6px; padding: 4px 6px; max-width: 100%; }
tr.rmx-row-card:hover .rmx-name { color: var(--brand, #1668DC); }
.rmx-open:focus-visible { outline: 2px solid var(--brand, #1668DC); outline-offset: 1px; }

/* The whole row opens the worker; its checkbox and buttons keep their own jobs. */
tr.rmx-row-card { cursor: pointer; }

/* The worker dialog. Who they are is said once, in the navy head; the body
   is the pay as three figures and then the rest as a plain list. */
#workerModal .emp-head { align-items: center; padding: 18px 20px; }
.wm-av { width: 52px; height: 52px; border-radius: 14px; flex: none; display: grid; place-items: center;
    background: rgba(255,255,255,.10); color: #8fbef7; box-shadow: inset 0 0 0 1px rgba(255,255,255,.14); }
#workerModal .emp-head-title { font-size: 17px; margin-bottom: 3px; }
.wm-id { font: 600 11.5px/1 ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; color: #cfe0f5;
    background: rgba(255,255,255,.10); padding: 3px 6px; border-radius: 5px; margin-right: 6px; }
.wm-chips { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 9px; }
.wm-chip { display: inline-flex; align-items: center; gap: 5px; font-size: 11px; font-weight: 600; padding: 3px 8px; border-radius: 6px;
    background: rgba(255,255,255,.08); color: #cfe0f5; box-shadow: inset 0 0 0 1px rgba(255,255,255,.14); }
.wm-chip i { font-size: 10px; }
.wm-chip.ok { background: rgba(18,183,106,.18); color: #86efac; box-shadow: inset 0 0 0 1px rgba(18,183,106,.40); }
.wm-chip.warn { background: rgba(247,144,9,.16); color: #fcd34d; box-shadow: inset 0 0 0 1px rgba(247,144,9,.40); }

.wm-pay { display: grid; grid-template-columns: repeat(3, 1fr); border: 1px solid var(--border, #e4e9f0); border-radius: 10px; overflow: hidden; margin-bottom: 14px; }
.wm-pay > div { padding: 11px 14px; border-left: 1px solid var(--border, #e4e9f0); min-width: 0; }
.wm-pay > div:first-child { border-left: 0; }
.wm-pay span { display: block; font-size: 10.5px; font-weight: 700; letter-spacing: .07em; text-transform: uppercase; color: var(--text-muted, #667085); margin-bottom: 3px; }
.wm-pay b { font-size: 17px; font-weight: 700; color: var(--text-primary, #101828); font-variant-numeric: tabular-nums; letter-spacing: -.01em; white-space: nowrap; }
.wm-pay small { font-size: 11.5px; font-weight: 500; color: var(--text-muted, #667085); margin-left: 2px; }

.wm-group { padding-top: 12px; margin-top: 12px; border-top: 1px solid var(--border, #e4e9f0); }
.wm-group:first-child { border-top: 0; margin-top: 0; padding-top: 0; }
.wm-group h6 { font-size: 10.5px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--brand, #1668DC); margin: 0 0 8px; }
.wm-list { margin: 0; display: grid; grid-template-columns: 1fr 1fr; gap: 8px 18px; }
.wm-list > div { min-width: 0; }
.wm-list > .wide { grid-column: 1 / -1; }
.wm-list dt { font-size: 11.5px; font-weight: 600; color: var(--text-muted, #667085); margin-bottom: 1px; }
.wm-list dd { margin: 0; font-size: 13.5px; font-weight: 600; color: var(--text-primary, #101828); overflow-wrap: anywhere; }
.wm-list dd.none { color: var(--text-muted, #98A2B3); font-weight: 500; }
.emp-foot a.emp-btn-cancel, .emp-foot a.emp-btn-save { text-decoration: none; }
@media (max-width: 575px) { .wm-pay b { font-size: 15px; } }

/* ── Bulk actions ─────────────────────────────────────────────────────────
   Tick workers on the Active list and a bar comes up from the bottom:
   transfer site, change shift, change labor type, add bonus. Each opens the
   one dialog below, which says what will change before it is confirmed. */

/* The site, beside Search: the same filter the Site heading sets. */
.rmx-sitepick { display: flex; align-items: center; gap: 6px; height: 30px; margin: 0; padding: 0 6px 0 10px;
    background: var(--rmx-card); border: var(--rmx-bw) solid var(--rmx-line-strong); border-radius: 8px;
    transition: border-color .15s, box-shadow .15s; }
.rmx-sitepick:focus-within { border-color: var(--brand, #1668DC); box-shadow: 0 0 0 3px var(--rmx-accent-bg); }
.rmx-sitepick i { font-size: 15px; color: var(--rmx-txt-3); flex: none; }
html[data-bs-theme] .rmx .rmx-sitepick select {
    height: 28px; max-width: 170px; padding: 0; border: 0 !important; background: transparent !important;
    box-shadow: none !important; outline: none; font: inherit; font-size: 12.5px; color: var(--rmx-txt); cursor: pointer;
}
/* A see-through select opens a white list in dark mode unless told otherwise. */
html[data-bs-theme] .rmx .rmx-sitepick select option { background: var(--surface); color: var(--text-primary); }

/* While selecting: what to do next, a row that ticks where it is clicked. */
.rmx-note.rmx-bulk-hint { display: none; }
.rm-pane.selecting .rmx-note.rmx-bulk-hint { display: flex; }
.rm-pane.selecting .rmx-table tbody tr { cursor: pointer; user-select: none; }
.rm-pane.selecting .rmx-table tbody tr:has(.rmx-check:checked) { background: var(--rmx-accent-bg); }

/* The tags under a worker's name — overtime allowed, a contract's end, a
   change dated ahead — are a note beside the name, not a second line of it:
   a size down from the pills in the columns (Michael, 2026-10-12, of
   "Overtime allowed": "liitan mo lang ng kaunti"). */
.rmx-pill.rmx-ot, .rmx-pill.rmx-contract, .rmx-pill.rmx-sched { font-size: 10px; line-height: 1.5; padding: 0 7px; gap: 3px; }
.rmx-pill.rmx-ot i, .rmx-pill.rmx-contract i, .rmx-pill.rmx-sched i { font-size: 10.5px; }
.rmx-tags:has(.rmx-ot, .rmx-contract, .rmx-sched) { margin-top: 3px; }

/* A change dated ahead, under the worker's name until its day comes. */
.rmx-sched { gap: 5px; }
.rmx-sched form { display: inline-flex; margin: 0; }
.rmx-sched-x { all: unset; cursor: pointer; display: inline-grid; place-items: center; width: 15px; height: 15px; border-radius: 50%; }
.rmx-sched-x i { font-size: 11px; }
.rmx-sched-x:hover { background: var(--rmx-accent-line); }
.rmx-sched-x:focus-visible { outline: 2px solid var(--brand, #1668DC); outline-offset: 1px; }

/* The bar. The page's colours turned inside out, so it reads as floating
   over the list in light and in dark alike. It keeps clear of the chat
   button in the corner. */
.rmx-abar { position: fixed; z-index: 1040; left: calc(var(--sidebar-width, 0px) + 16px); right: 96px; bottom: 20px;
    width: fit-content; max-width: calc(100vw - var(--sidebar-width, 0px) - 112px); margin: 0 auto;
    display: flex; align-items: center; gap: 10px; flex-wrap: wrap; padding: 10px 10px 10px 16px; border-radius: 14px;
    background: var(--text-primary); color: var(--surface); box-shadow: 0 14px 40px rgba(0, 0, 0, .35);
    transform: translateY(160%); visibility: hidden;
    transition: transform .25s cubic-bezier(.2, .8, .2, 1), visibility 0s linear .25s; }
.rmx-abar.show { transform: none; visibility: visible; transition: transform .25s cubic-bezier(.2, .8, .2, 1); }
@media (prefers-reduced-motion: reduce) { .rmx-abar, .rmx-abar.show { transition: none; } }
.rmx-abar b { font-size: 14px; font-weight: 700; white-space: nowrap; }
.rmx-abar-clr { all: unset; cursor: pointer; font-size: 12.5px; font-weight: 600; opacity: .7; padding: 6px; border-radius: 6px; }
.rmx-abar-clr:hover { opacity: 1; }
.rmx-abar-sep { width: 1px; height: 24px; background: currentColor; opacity: .2; }
.rmx-abtn { all: unset; box-sizing: border-box; cursor: pointer; display: inline-flex; align-items: center; gap: 7px; height: 38px;
    padding: 0 12px; border-radius: 9px; font-size: 13px; font-weight: 700; white-space: nowrap;
    background: var(--surface); color: var(--text-primary); }
.rmx-abtn i { font-size: 16px; }
.rmx-abtn:hover { box-shadow: inset 0 0 0 2px var(--brand, #1668DC); }
.rmx-abtn:focus-visible, .rmx-abar-clr:focus-visible { outline: 2px solid var(--brand, #1668DC); outline-offset: 2px; }

/* The dialog: what to change to, who it changes and from what, from when. */
#rmxBulkModal .emp-body { display: grid; gap: 16px; }
#rmxBulkModal .emp-field + .emp-field { margin-top: 0; }
#rmxBulkModal [hidden] { display: none !important; }
#rmxBulkModal .emp-foot { justify-content: flex-end; }
#rmxBulkModal .emp-btn-save:disabled { opacity: .45; cursor: not-allowed; }
.bk-seg { display: flex; gap: 6px; flex-wrap: wrap; }
.bk-seg button { all: unset; box-sizing: border-box; cursor: pointer; display: inline-flex; align-items: center; gap: 8px;
    padding: 9px 14px; border: 1px solid var(--border); border-radius: 9px; font-size: 13px; font-weight: 600;
    color: var(--text-primary); background: var(--bg-surface); }
.bk-seg button i { font-size: 15px; color: var(--text-muted); }
.bk-seg button small { font-size: 11.5px; font-weight: 500; color: var(--text-muted); }
.bk-seg button:hover { border-color: var(--border-md); }
.bk-seg button.on { border-color: var(--brand); background: var(--brand-subtle); color: var(--brand); box-shadow: inset 0 0 0 1px var(--brand); }
.bk-seg button.on i, .bk-seg button.on small { color: inherit; }
.bk-seg button:focus-visible { outline: 2px solid var(--brand); outline-offset: 2px; }
.bk-ck { display: flex; align-items: center; gap: 8px; margin: 10px 0 0; font-size: 13px; font-weight: 500; color: var(--text-primary); cursor: pointer; }
.bk-ck input { width: 16px; height: 16px; margin: 0; accent-color: var(--brand); cursor: pointer; }
.bk-ck b { font-variant-numeric: tabular-nums; }
.bk-peso { position: relative; }
.bk-peso span { position: absolute; left: 13px; top: 50%; transform: translateY(-50%); font-weight: 700; color: var(--text-muted); pointer-events: none; }
#rmxBulkModal .bk-peso .form-control { padding-left: 30px !important; font-size: 16px; font-weight: 700; }
#rmxBulkModal #bkWhyOther { margin-top: 8px; }
.bk-flow { display: grid; grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr); gap: 10px; align-items: stretch; }
.bk-flow > i { align-self: center; }
.bk-to span:empty { display: none; }
.bk-flow > div { display: flex; flex-direction: column; gap: 6px; min-width: 0; padding: 10px 12px;
    border: 1px solid var(--border); border-radius: 10px; background: var(--bg-subtle); }
.bk-flow small { font-size: 11px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--text-muted); }
.bk-flow > i { font-size: 20px; color: var(--text-muted); }
.bk-line { display: flex; justify-content: space-between; gap: 8px; font-size: 13px; color: var(--text-primary); }
.bk-line span { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.bk-line b { font-variant-numeric: tabular-nums; }
.bk-to b { font-size: 16px; color: var(--text-primary); overflow-wrap: anywhere; }
.bk-to span { font-size: 12px; color: var(--text-muted); }
.bk-total { display: flex; justify-content: space-between; align-items: center; padding: 12px 14px; font-size: 13px;
    border: 1px solid var(--border); border-radius: 10px; background: var(--bg-subtle); color: var(--text-primary); }
.bk-total b { font-size: 18px; font-variant-numeric: tabular-nums; }
.bk-chips { display: flex; gap: 6px; flex-wrap: wrap; max-height: 96px; overflow: auto; }
.bk-chips span { font-size: 12px; padding: 3px 9px; border-radius: 999px; color: var(--text-primary);
    background: var(--bg-subtle); border: 1px solid var(--border); }
.bk-note { display: flex; gap: 8px; margin: 0; padding: 10px 12px; border-radius: 9px; font-size: 12.5px; line-height: 1.5;
    color: var(--text-secondary); background: var(--bg-subtle); }
.bk-note i { font-size: 16px; flex: none; margin-top: 1px; color: var(--brand); }
.bk-err { margin: 0; font-size: 12.5px; color: var(--danger); }
@media (max-width: 560px) {
    .bk-flow { grid-template-columns: 1fr; }
    .bk-flow > i { transform: rotate(90deg); justify-self: center; }
}
</style>
@endpush

@section('content')
@php
    // Which tab opens, decided here rather than after paint. ?tab=pending is
    // where saving a worker lands; with nothing asked for, Active leads —
    // unless there is nobody active and somebody pending, because a fresh
    // system should not open on an empty table.
    $openTab = in_array(request('tab'), ['active', 'pending', 'removed'], true)
        ? request('tab')
        : (($active->count() === 0 && $pending->count() > 0) ? 'pending' : 'active');

    $tabs = [
        'active'  => ['label' => __('Active'),  'count' => $active->count()],
        'pending' => ['label' => __('Pending'), 'count' => $pending->count()],
        'removed' => ['label' => __('Removed'), 'count' => $removed->count()],
    ];
@endphp
<div class="rm-page rmx">

    {{-- session('success') is a toast. Errors that belong to the modal are
         shown in it as well; this is for anything that arrives without it. --}}
    @if($errors->any())
    <div class="rm-alert rm-alert-err">
        <i class="fas fa-exclamation-circle"></i>
        <div><strong>{{ __('Please fix the following:') }}</strong>
            <ul class="mb-0 mt-1">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    </div>
    @endif

    {{-- ── Header ──────────────────────────────────────────────────────────── --}}
    <x-page-header :title="__('Employees')">
        <x-slot:actions>
            {{-- The active workforce as a spreadsheet Excel opens. It came
                 over from the Employee Directory when that page was retired. --}}
            <a href="{{ route('employees.export') }}" class="rmx-outline" id="rmExportBtn">
                <i class="ti ti-file-spreadsheet" aria-hidden="true"></i>{{ __('Export to Excel') }}
            </a>
            {{-- The full registration, as a step-by-step pop-up over this
                 page (employees/_register_wizard.blade.php). A link all the
                 same, so it opens in a new tab with the pop-up up. The compact
                 modal further down stays for confirming and completing kiosk
                 detections. --}}
            <a href="{{ route('employees.register', ['register' => 1]) }}" class="rmx-primary" id="rmAddBtn" data-register-open aria-haspopup="dialog">
                <i class="ti ti-user-plus" aria-hidden="true"></i>{{ __('Register employee') }}
            </a>
        </x-slot:actions>
    </x-page-header>

    {{-- ── The three cards, behind the arrow at the side ─────────────────────
         The list comes first. The cards — which are also how you switch
         between Active, Pending and Removed — slide out from the arrow by the
         navigation bar, pushing the list down. They are out whenever the page
         loads; the arrow puts them away until the next load. --}}
    @php
        $showing = [
            'active'  => [__('Active'), $active->count()],
            'pending' => [__('Pending from kiosk'), $pending->count()],
            'removed' => [__('Removed'), $removed->count()],
        ];
    @endphp
    <div class="rmx-top">
        <button type="button" class="rmx-handle" id="rmxHandle" aria-expanded="true" aria-controls="rmxSum"
                title="{{ __('Hide Active, Pending and Removed') }}" aria-label="{{ __('Hide Active, Pending and Removed') }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>
        </button>
        <div class="rmx-sumwrap open" id="rmxSum">
            <div class="rmx-sum-in">
                <div class="rmx-stats">
                    <button type="button" class="rmx-stat rmx-stat-active" data-tab="active">
                        <span class="rmx-stat-ic"><i class="fas fa-helmet-safety" aria-hidden="true"></i></span>
                        <span class="rmx-stat-body">
                            <span class="rmx-stat-num">{{ $active->count() }}</span>
                            <span class="rmx-stat-lbl">{{ __('Active') }}</span>
                            <span class="rmx-stat-sub">{{ __('On the payroll') }}</span>
                        </span>
                    </button>
                    <button type="button" class="rmx-stat rmx-stat-pending" data-tab="pending">
                        <span class="rmx-stat-ic"><i class="fas fa-fingerprint" aria-hidden="true"></i></span>
                        <span class="rmx-stat-body">
                            <span class="rmx-stat-num">{{ $pending->count() }}</span>
                            <span class="rmx-stat-lbl">{{ __('Pending from kiosk') }}</span>
                            <span class="rmx-stat-sub">{{ __('Waiting for their details') }}</span>
                        </span>
                    </button>
                    <button type="button" class="rmx-stat rmx-stat-removed" data-tab="removed">
                        <span class="rmx-stat-ic"><i class="fas fa-user-slash" aria-hidden="true"></i></span>
                        <span class="rmx-stat-body">
                            <span class="rmx-stat-num">{{ $removed->count() }}</span>
                            <span class="rmx-stat-lbl">{{ __('Removed') }}</span>
                            <span class="rmx-stat-sub">{{ __('Records kept, can be restored') }}</span>
                        </span>
                    </button>
                </div>
            </div>
        </div>

    {{-- ── Which list is showing, and the one Select toggle for it ─────────── --}}
    <div class="rmx-bar">
        <div class="rmx-showing" id="rmxShowing" aria-live="polite">
            <b>{{ $showing[$openTab][0] }}</b><span class="rmx-count">{{ $showing[$openTab][1] }}</span>
            <span class="rmx-fsum" id="rmxFilterSum" hidden><i class="ti ti-filter" aria-hidden="true"></i><span data-fsum-text></span><b data-fsum-n></b><button type="button" class="rmx-fclear" data-fclear title="{{ __('Show everybody') }}" aria-label="{{ __('Clear the filters') }}"><i class="ti ti-x" aria-hidden="true"></i></button></span>
        </div>
        <div class="rmx-tools">
            {{-- One site's workers. The same filter the Site heading sets, kept
                 in step with it, and here so a whole site can be picked and
                 then ticked in one go. --}}
            <label class="rmx-sitepick" title="{{ __('Show one site only') }}">
                <i class="ti ti-map-pin" aria-hidden="true"></i>
                <select id="rmxSite" autocomplete="off" aria-label="{{ __('Site') }}">
                    <option value="*">{{ __('All sites') }}</option>
                    @foreach($sites as $s)
                        <option value="{{ $s->name }}">{{ $s->name }}</option>
                    @endforeach
                    <option value="">{{ __('No site') }}</option>
                </select>
            </label>
            {{-- Finds a worker in the list on screen by name or number, as it
                 is typed. It narrows the list the same way the headings do. --}}
            <label class="rmx-search">
                <i class="ti ti-search" aria-hidden="true"></i>
                <input type="search" id="rmxSearch" placeholder="{{ __('Search employee') }}" autocomplete="off"
                       spellcheck="false" enterkeyhint="search" aria-label="{{ __('Search employee by name or ID') }}">
            </label>
            {{-- Bulk removal is destructive, so it is something you opt into: the
                 checkbox column stays hidden until Select is pressed, and pressing
                 it again (it reads Done) puts it away. --}}
            <button type="button" class="rmx-outline" id="rmxSelect">
                <i class="ti ti-list-check" aria-hidden="true"></i><span class="js-select-label">{{ __('Select') }}</span>
            </button>
        </div>
    </div>
    </div>

    {{-- ═══ ACTIVE ═════════════════════════════════════════════════════════ --}}
    <div class="rm-pane {{ $openTab === 'active' ? 'active' : '' }}" data-pane="active" role="tabpanel">
        <div class="rmx-card">
            {{-- Only while selecting: the bar it speaks of comes up once
                 somebody is ticked. --}}
            <div class="rmx-note rmx-bulk-hint">
                <i class="ti ti-info-circle" aria-hidden="true"></i>
                <span>{{ __('Tick employees or use the header box to select everyone shown, then pick an action in the bar below. Tip: filter by site first, then select all.') }}</span>
            </div>
            <div class="rmx-bulk" hidden>
                <span class="rmx-bulk-count"><strong>0</strong> {{ __('selected') }}</span>
                <button type="button" class="rmx-bulk-btn js-bulk-clear">{{ __('Clear selection') }}</button>
                <span class="rmx-bulk-spacer"></span>
                <button type="button" class="rmx-bulk-btn danger js-bulk-remove"><i class="ti ti-trash" aria-hidden="true"></i>{{ __('Remove selected') }}</button>
            </div>
            <div class="rmx-table-wrap">
                <table class="rmx-table">
                    <thead>
                        <tr>
                            <th class="rmx-check-col"><input type="checkbox" class="rmx-check-all" aria-label="{{ __('Select all') }}"></th>
                            <th>{{ __('Employee') }}</th>
                            <th><button type="button" class="rmx-fbtn" data-filter="site" aria-haspopup="menu" aria-expanded="false" title="{{ __('Show one site only') }}">{{ __('Site') }}<i class="ti ti-chevron-down" aria-hidden="true"></i></button></th>
                            <th><button type="button" class="rmx-fbtn" data-filter="labor" aria-haspopup="menu" aria-expanded="false" title="{{ __('Show one labor type only') }}">{{ __('Labor type') }}<i class="ti ti-chevron-down" aria-hidden="true"></i></button></th>
                            <th><button type="button" class="rmx-fbtn" data-filter="shift" aria-haspopup="menu" aria-expanded="false" title="{{ __('Show one shift only') }}">{{ __('Shift') }}<i class="ti ti-chevron-down" aria-hidden="true"></i></button></th>
                            <th class="rmx-num">{{ __('Rate / hr') }}</th>
                            <th class="rmx-center" title="{{ __('Fingerprint slot on the kiosk') }}">{{ __('FP') }}</th>
                            <th class="rmx-center">{{ __('Logs') }}</th>
                            <th class="rmx-num">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody id="rmActiveBody" data-live="employees attendance sites settings">
                    @forelse($active as $e)
                        @php
                            // What the card a click on the name opens shows: the
                            // record as it stands, read here, no extra request.
                            $card = [
                                'id'       => '#' . str_pad($e->id, 4, '0', STR_PAD_LEFT),
                                'name'     => $e->name,
                                'role'     => $e->laborType?->name ?: ($e->job_title ?: $e->position),
                                'site'     => $e->site?->name,
                                'rate'     => '₱' . number_format((float) $e->rate_per_hour, 2) . ' / hr',
                                'daily'    => $e->laborType ? '₱' . number_format((float) $e->laborType->daily_rate, 2) . ' / day' : null,
                                'fp'       => $e->fingerprint_id ? __('Slot') . ' ' . $e->fingerprint_id : null,
                                'logs'     => $e->attendances_count,
                                'phone'    => $e->phone,
                                'email'    => $e->email,
                                'hired'    => $e->date_hired?->format('M j, Y'),
                                'place'    => collect([$e->address_street, $e->address_barangay, $e->address_city, $e->address_province, $e->address_postal])->filter()->implode(', ') ?: null,
                                'born'     => $e->birth_date ? $e->birth_date->format('M j, Y') . ' · ' . $e->birth_date->age . ' ' . __('yrs') : null,
                                'birthplace' => $e->birth_place,
                                'gender'   => $e->gender,
                                'civil'    => $e->civil_status,
                                'nation'   => $e->nationality,
                                'blood'    => $e->blood_type,
                                'shift'    => $e->shift ? $e->shift->name . ' · ' . \Carbon\Carbon::parse($e->shift->starts_at)->format('g:i A') : null,
                                // Null until the office has begun choosing who may: before
                                // that everybody takes overtime and there is nothing to say.
                                'overtime' => $bulk['overtimeRule'] ? ($e->overtime_allowed ? __('Allowed') : __('Not allowed')) : null,
                                'hiredAs'  => $e->isContractual()
                                    ? __('Contractual') . ($e->end_of_contract ? ' · ' . __('until') . ' ' . $e->end_of_contract->format('M j, Y') : '')
                                    : __('Regular'),
                                'ice'      => collect([$e->emergency_contact_name, $e->emergency_contact_relation ? '(' . $e->emergency_contact_relation . ')' : null])->filter()->implode(' ') ?: null,
                                'icePhone' => $e->emergency_contact_phone,
                                'edit'     => route('employees.edit', $e->id),
                                'profile'  => route('employees.show', $e->id),
                            ];
                        @endphp
                        {{-- The ids are what the bulk dialogs compare against: who is
                             already at the site, on the shift or of the labor
                             type being changed to, and so is left as they are. --}}
                        <tr class="rmx-row-card" data-live-key="emp-{{ $e->id }}" data-emp="{{ json_encode($card) }}" data-site="{{ $e->site?->name }}" data-labor="{{ $e->laborType?->name }}" data-shift="{{ $e->shift?->name }}" data-site-id="{{ $e->site_id }}" data-shift-id="{{ $e->shift_id }}" data-labor-id="{{ $e->labor_type_id }}" data-ot="{{ $e->overtime_allowed ? 1 : 0 }}">
                            <td class="rmx-check-col"><input type="checkbox" class="rmx-check" value="{{ $e->id }}" aria-label="Select {{ $e->name }}"></td>
                            <td><button type="button" class="rmx-open" aria-haspopup="dialog" aria-label="{{ __('Details of :name', ['name' => $e->name]) }}">@include('employees._person', ['e' => $e, 'displayName' => $e->name])</button>
                                {{-- A change dated ahead: what it will be and from
                                     when, with a way to call it off before then. --}}
                                @php $onContract = $e->isContractual() && $e->end_of_contract; @endphp
                                @if($onContract || $e->overtime_allowed || ($scheduled[$e->id] ?? collect())->isNotEmpty())
                                    <div class="rmx-tags">
                                        {{-- May take overtime. Nobody else does, from the day
                                             the first of them was allowed. --}}
                                        @if($e->overtime_allowed)
                                            <span class="rmx-pill rmx-pill-ok rmx-ot" title="{{ __('Hours worked past their regular hours are recorded and paid as overtime.') }}">
                                                <i class="ti ti-clock-plus" aria-hidden="true"></i>{{ __('Overtime allowed') }}
                                            </span>
                                        @endif
                                        {{-- On contract: the last day of it, after which they
                                             leave this list by themselves. Amber in its last
                                             thirty days, so a renewal is not a surprise. --}}
                                        @if($onContract)
                                            @php $daysLeft = (int) now()->startOfDay()->diffInDays($e->end_of_contract, false); @endphp
                                            <span class="rmx-pill {{ $daysLeft <= 30 ? 'rmx-pill-warn' : '' }} rmx-contract"
                                                  title="{{ __('Contractual. Leaves the Active list after this date; give the contract more months from Edit to extend it.') }}">
                                                <i class="ti ti-calendar-event" aria-hidden="true"></i>{{ __('Contract until :date', ['date' => $e->end_of_contract->format('M j, Y')]) }}
                                            </span>
                                        @endif
                                        @foreach(($scheduled[$e->id] ?? collect()) as $change)
                                            <span class="rmx-pill rmx-pill-accent rmx-sched" title="{{ __('Takes effect on :date', ['date' => $change->effective_on->format('M d, Y')]) }}">
                                                <i class="ti ti-calendar-time" aria-hidden="true"></i>{{ __(':to from :date', ['to' => $change->targetName(), 'date' => $change->effective_on->format('M d')]) }}
                                                <form action="{{ route('employees.changes.cancel', $change) }}" method="POST"
                                                      data-confirm="{{ __(':name stays as they are, and nothing changes on :date.', ['name' => $e->name, 'date' => $change->effective_on->format('M d, Y')]) }}"
                                                      data-confirm-title="{{ __('Cancel this change?') }}"
                                                      data-confirm-label="{{ __('Cancel the change') }}"
                                                      data-confirm-tone="warning">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="rmx-sched-x" title="{{ __('Cancel this change') }}" aria-label="{{ __('Cancel this change') }} — {{ $e->name }}"><i class="ti ti-x" aria-hidden="true"></i></button>
                                                </form>
                                            </span>
                                        @endforeach
                                    </div>
                                @endif
                            </td>
                            <td>@include('employees._site', ['e' => $e])</td>
                            <td>@include('employees._labor', ['e' => $e])</td>
                            <td>@if($e->shift)<span class="rmx-shift" title="{{ $e->shift->name }} · {{ \Carbon\Carbon::parse($e->shift->starts_at)->format('g:i A') }}"><i class="ti ti-{{ $e->shift->crosses_midnight ? 'moon' : 'sun' }}" aria-hidden="true"></i>{{ $e->shift->name }}<small>{{ \Carbon\Carbon::parse($e->shift->starts_at)->format('g:i A') }}</small></span>@else<span class="rmx-dash">—</span>@endif</td>
                            <td class="rmx-rate">₱{{ number_format($e->rate_per_hour, 2) }}</td>
                            <td class="rmx-center">@include('employees._fp', ['e' => $e])</td>
                            <td class="rmx-center"><span class="rmx-logs">{{ $e->attendances_count }}</span></td>
                            <td class="rmx-actions">
                                <div class="rmx-actions-inner">
                                    {{-- A bonus for the pay period running now. Only for
                                         whoever may already give one in Payroll Settings:
                                         it is money on a payslip. The count is what this
                                         worker was already given this period. --}}
                                    @if(auth()->user()?->isAdmin())
                                        @php $given = $bonuses[$e->id] ?? collect(); @endphp
                                        <button type="button" class="rmx-icon-btn rmx-gift js-add-bonus"
                                                title="{{ __('Add bonus') }}" aria-label="{{ __('Add bonus') }} — {{ $e->name }}"
                                                data-id="{{ $e->id }}"
                                                data-name="{{ $e->name }}"
                                                data-given="{{ json_encode($given->map(fn ($b) => [
                                                    'id'     => $b->id,
                                                    'amount' => '₱' . number_format((float) $b->amount, 2),
                                                    'note'   => $b->note,
                                                    'on'     => $b->effective_on->format('M d'),
                                                ])->values()) }}">
                                            <i class="ti ti-gift" aria-hidden="true"></i>
                                            @if($given->count())
                                                <span class="rmx-gift-n">{{ $given->count() }}</span>
                                            @endif
                                        </button>
                                    @endif
                                    {{-- Edit opens the full Register Employee form, not
                                         the quick modal: a five-field dialog could
                                         correct a record without ever showing the
                                         twenty other fields on it. --}}
                                    <a href="{{ route('employees.edit', $e->id) }}" class="rmx-icon-btn rmx-edit"
                                       title="{{ __('Edit') }}" aria-label="{{ __('Edit') }} {{ $e->name }}">
                                        <i class="ti ti-pencil" aria-hidden="true"></i>
                                    </a>
                                    <form action="{{ route('employees.destroy', $e->id) }}" method="POST"
                                          data-confirm="{{ __('The records of :name are preserved and can be restored from the Removed tab.', ['name' => $e->name]) }}"
                                          data-confirm-title="{{ __('Remove this worker?') }}"
                                          data-confirm-label="{{ __('Remove') }}"
                                          data-confirm-tone="warning">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="rmx-icon-btn rmx-del"
                                                title="{{ __('Remove') }}" aria-label="{{ __('Remove') }} {{ $e->name }}">
                                            <i class="ti ti-trash" aria-hidden="true"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        @include('employees._empty', ['icon' => 'users', 'title' => 'No active employees', 'sub' => 'Complete a pending detection, or use Register employee to get started.'])
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ═══ PENDING ════════════════════════════════════════════════════════ --}}
    <div class="rm-pane {{ $openTab === 'pending' ? 'active' : '' }}" data-pane="pending" role="tabpanel">
        <div class="rmx-card">
            <div class="rmx-note">
                <i class="ti ti-info-circle" aria-hidden="true"></i>
                <span>{{ __('Please scan your fingerprint on the kiosk.') }}</span>
            </div>
            <div class="rmx-bulk" hidden>
                <span class="rmx-bulk-count"><strong>0</strong> {{ __('selected') }}</span>
                <button type="button" class="rmx-bulk-btn js-bulk-clear">{{ __('Clear selection') }}</button>
                <span class="rmx-bulk-spacer"></span>
                <button type="button" class="rmx-bulk-btn danger js-bulk-remove"><i class="ti ti-x" aria-hidden="true"></i>{{ __('Cancel selected') }}</button>
            </div>
            <div class="rmx-table-wrap">
                <table class="rmx-table">
                    <thead>
                        <tr>
                            <th class="rmx-check-col"><input type="checkbox" class="rmx-check-all" aria-label="{{ __('Select all') }}"></th>
                            <th>{{ __('Worker') }}</th>
                            <th class="rmx-center" title="{{ __('Fingerprint slot on the kiosk') }}">{{ __('FP') }}</th>
                            <th><button type="button" class="rmx-fbtn" data-filter="site" aria-haspopup="menu" aria-expanded="false" title="{{ __('Show one site only') }}">{{ __('Site') }}<i class="ti ti-chevron-down" aria-hidden="true"></i></button></th>
                            <th>{{ __('First seen') }}</th>
                            <th class="rmx-center">{{ __('Logs') }}</th>
                            <th class="rmx-num">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody id="rmPendingBody" data-live="employees attendance sites settings">
                        @include('employees._rows_pending', ['pending' => $pending])
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ═══ REMOVED ════════════════════════════════════════════════════════ --}}
    <div class="rm-pane {{ $openTab === 'removed' ? 'active' : '' }}" data-pane="removed" role="tabpanel">
        <div class="rmx-card">
            <div class="rmx-note">
                <i class="ti ti-info-circle" aria-hidden="true"></i>
                <span>{{ __('Removed records are hidden everywhere but never lost. Restore them, or permanently delete as a last resort.') }}</span>
            </div>
            <div class="rmx-bulk" hidden>
                <span class="rmx-bulk-count"><strong>0</strong> {{ __('selected') }}</span>
                <button type="button" class="rmx-bulk-btn js-bulk-clear">{{ __('Clear selection') }}</button>
                <span class="rmx-bulk-spacer"></span>
                <button type="button" class="rmx-bulk-btn js-bulk-restore"><i class="ti ti-restore" aria-hidden="true"></i>{{ __('Restore selected') }}</button>
                <button type="button" class="rmx-bulk-btn danger js-bulk-purge"><i class="ti ti-trash-x" aria-hidden="true"></i>{{ __('Delete permanently') }}</button>
            </div>
            <div class="rmx-table-wrap">
                <table class="rmx-table">
                    <thead>
                        <tr>
                            <th class="rmx-check-col"><input type="checkbox" class="rmx-check-all" aria-label="{{ __('Select all') }}"></th>
                            <th>{{ __('Employee') }}</th>
                            <th><button type="button" class="rmx-fbtn" data-filter="site" aria-haspopup="menu" aria-expanded="false" title="{{ __('Show one site only') }}">{{ __('Site') }}<i class="ti ti-chevron-down" aria-hidden="true"></i></button></th>
                            <th><button type="button" class="rmx-fbtn" data-filter="labor" aria-haspopup="menu" aria-expanded="false" title="{{ __('Show one labor type only') }}">{{ __('Labor type') }}<i class="ti ti-chevron-down" aria-hidden="true"></i></button></th>
                            <th>{{ __('Removed') }}</th>
                            <th class="rmx-center">{{ __('Logs') }}</th>
                            <th class="rmx-num">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody id="rmRemovedBody" data-live="employees attendance sites settings">
                    @forelse($removed as $e)
                        <tr data-live-key="emp-{{ $e->id }}" data-site="{{ $e->site?->name }}" data-labor="{{ $e->laborType?->name }}">
                            <td class="rmx-check-col"><input type="checkbox" class="rmx-check" value="{{ $e->id }}" aria-label="Select {{ $e->name }}"></td>
                            <td>@include('employees._person', ['e' => $e, 'displayName' => $e->name])</td>
                            <td>@include('employees._site', ['e' => $e])</td>
                            <td>@include('employees._labor', ['e' => $e])</td>
                            <td class="rmx-muted">{{ $e->deleted_at?->format('M d, Y') ?? '—' }}</td>
                            <td class="rmx-center"><span class="rmx-logs">{{ $e->attendances_count }}</span></td>
                            <td class="rmx-actions">
                                <div class="rmx-actions-inner">
                                    <form action="{{ route('employees.restore', $e->id) }}" method="POST">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="rmx-icon-btn rmx-restore"
                                                title="{{ __('Restore') }}" aria-label="{{ __('Restore') }} {{ $e->name }}">
                                            <i class="ti ti-restore" aria-hidden="true"></i>
                                        </button>
                                    </form>
                                    <form action="{{ route('employees.force-delete', $e->id) }}" method="POST"
                                          data-confirm="{{ __(':name leaves this list for good and cannot be restored. Their attendance and payroll records are kept.', ['name' => $e->name]) }}"
                                          data-confirm-title="{{ __('Delete permanently?') }}"
                                          data-confirm-label="{{ __('Delete permanently') }}"
                                          data-confirm-tone="danger">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="rmx-icon-btn rmx-del"
                                                title="{{ __('Delete permanently') }}" aria-label="{{ __('Delete permanently') }} {{ $e->name }}">
                                            <i class="ti ti-trash-x" aria-hidden="true"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        @include('employees._empty', ['icon' => 'user-off', 'title' => 'Nothing removed', 'sub' => 'Removed records can be restored from here.'])
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- ── Bulk actions ────────────────────────────────────────────────────────
     Up from the bottom once somebody on the Active list is ticked. Each
     button opens the dialog further down for everybody ticked. Adding a
     bonus is offered to whoever may already give one from a row. --}}
<div class="rmx rmx-abar" id="rmxActs" role="region" aria-label="{{ __('Bulk actions') }}">
    <b id="rmxActsN">{{ __('0 selected') }}</b>
    <button type="button" class="rmx-abar-clr" id="rmxActsClear">{{ __('Clear') }}</button>
    <span class="rmx-abar-sep" aria-hidden="true"></span>
    <button type="button" class="rmx-abtn" data-act="site"><i class="ti ti-map-pin" aria-hidden="true"></i>{{ __('Transfer site') }}</button>
    <button type="button" class="rmx-abtn" data-act="shift"><i class="ti ti-moon" aria-hidden="true"></i>{{ __('Change shift') }}</button>
    <button type="button" class="rmx-abtn" data-act="labor"><i class="ti ti-tool" aria-hidden="true"></i>{{ __('Change labor type') }}</button>
    {{-- Who may take overtime. From the day the first worker is allowed,
         only the workers allowed have the hours past their regular hours
         recorded (App\Support\OvertimePermission). --}}
    <button type="button" class="rmx-abtn" data-act="overtime"><i class="ti ti-clock-plus" aria-hidden="true"></i>{{ __('Allow overtime') }}</button>
    @if(auth()->user()?->isAdmin())
        <button type="button" class="rmx-abtn" data-act="bonus"><i class="ti ti-gift" aria-hidden="true"></i>{{ __('Add bonus') }}</button>
    @endif
</div>

{{-- ════════════════════════════ EMPLOYEE FORM MODAL ═══════════════════════
     One modal serves four jobs — Add, Confirm, Complete and Edit — with the
     title, the sub-line and the submit label swapped by openModal(). It
     speaks the same language as the Register Employee page: the .ep-* chrome
     from employees/_profile_styles.blade.php and Bootstrap form controls the
     design tokens already theme for both modes. --}}
<div class="modal fade" id="empFormModal" tabindex="-1" aria-hidden="true" aria-labelledby="empFormTitle">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable emp-dialog">
    <div class="modal-content emp-modal">
      <form id="empForm" method="POST" enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="_method" id="empFormMethod" value="POST">
        <input type="hidden" name="_form_mode" id="empFormModeField" value="">
        <input type="hidden" name="_form_id" id="empFormIdField" value="">

        <div class="emp-head">
            <span class="emp-head-icon" aria-hidden="true"><i class="fas fa-helmet-safety"></i></span>
            <div class="emp-head-text">
                <h6 class="emp-head-title" id="empFormTitle">{{ __('Complete Registration') }}</h6>
                <p class="emp-head-sub" id="empFormSub">{{ __('Set this worker\'s details to activate them.') }}</p>
            </div>
            <button type="button" class="emp-head-x" data-bs-dismiss="modal" aria-label="{{ __('Close') }}">
                <i class="fas fa-times" aria-hidden="true"></i>
            </button>
        </div>

        <div class="modal-body emp-body">

            {{-- The page-level alert sits behind the modal, so when validation
                 sent the admin back here the form reopened saying nothing at
                 all about what was wrong. Same errors, shown where they can be
                 read and beside the field that raised them. --}}
            @if($errors->any())
                <div class="emp-alert" role="alert">
                    <i class="fas fa-circle-exclamation" aria-hidden="true"></i>
                    <div>
                        <strong>{{ __('Please fix the following:') }}</strong>
                        <ul>@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                    </div>
                </div>
            @endif

            <section class="ep-section emp-section">
                <header class="ep-section-head">
                    <span class="ep-section-icon" aria-hidden="true"><i class="fas fa-user"></i></span>
                    <div>
                        <h3 class="ep-section-title">{{ __('Worker') }}</h3>
                        <p class="ep-section-sub">{{ __('Who this record is for.') }}</p>
                    </div>
                </header>
                {{-- Three fields, the same three Register Employee asks for. The
                     controller composes `name` from these, so a correction made
                     here and the same one made on the full form end at the same
                     value. Middle Name carries no asterisk: the full form
                     requires it because it posts profile_form, and this modal
                     does not. --}}
                <div class="emp-grid emp-grid-3">
                    <div class="emp-field">
                        <label class="ep-label" for="empFirst">{{ __('First Name') }} <span class="ep-req" aria-hidden="true">*</span></label>
                        <input type="text" name="first_name" id="empFirst"
                               class="form-control @error('first_name') is-invalid @enderror"
                               placeholder="{{ __('Juan') }}"
                               autocomplete="off" required aria-required="true">
                        @error('first_name')
                            <p class="emp-err" role="alert"><i class="fas fa-circle-exclamation" aria-hidden="true"></i>{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="emp-field">
                        <label class="ep-label" for="empMiddle">{{ __('Middle Name') }}</label>
                        <input type="text" name="middle_name" id="empMiddle"
                               class="form-control @error('middle_name') is-invalid @enderror"
                               placeholder="{{ __('Santos') }}" autocomplete="off">
                        @error('middle_name')
                            <p class="emp-err" role="alert"><i class="fas fa-circle-exclamation" aria-hidden="true"></i>{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="emp-field">
                        <label class="ep-label" for="empLast">{{ __('Last Name') }} <span class="ep-req" aria-hidden="true">*</span></label>
                        <input type="text" name="last_name" id="empLast"
                               class="form-control @error('last_name') is-invalid @enderror"
                               placeholder="{{ __('Dela Cruz') }}"
                               autocomplete="off" required aria-required="true">
                        @error('last_name')
                            <p class="emp-err" role="alert"><i class="fas fa-circle-exclamation" aria-hidden="true"></i>{{ $message }}</p>
                        @enderror
                    </div>
                </div>
                @error('name')
                    <p class="emp-err" role="alert"><i class="fas fa-circle-exclamation" aria-hidden="true"></i>{{ $message }}</p>
                @else
                    <span class="ep-hint" id="empNameHint">{{ __('As it should read on the payslip.') }}</span>
                @enderror
            </section>

            <section class="ep-section emp-section">
                <header class="ep-section-head">
                    <span class="ep-section-icon" aria-hidden="true"><i class="fas fa-helmet-safety"></i></span>
                    <div>
                        <h3 class="ep-section-title">{{ __('Employment & Pay') }}</h3>
                        <p class="ep-section-sub">{{ __('What the worker is paid and where they are assigned.') }}</p>
                    </div>
                </header>
                <div class="emp-grid">
                    <div class="emp-field">
                        <label class="ep-label" for="empLabor">{{ __('Labor Type') }} <span class="ep-req" aria-hidden="true">*</span></label>
                        <select name="labor_type_id" id="empLabor"
                                class="form-select @error('labor_type_id') is-invalid @enderror"
                                required aria-required="true">
                            <option value="">{{ __('— Select —') }}</option>
                            @foreach($laborTypes as $lt)
                                <option value="{{ $lt->id }}" data-daily="{{ $lt->daily_rate }}">{{ $lt->name }}</option>
                            @endforeach
                        </select>
                        @error('labor_type_id')
                            <p class="emp-err" role="alert"><i class="fas fa-circle-exclamation" aria-hidden="true"></i>{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="emp-field">
                        <label class="ep-label" for="empRateView">{{ __('Rate / hour') }}</label>
                        {{-- Filled from the labor type, never typed. --}}
                        <div class="emp-rate" id="empRateBox" aria-live="polite">
                            <span class="emp-rate-cur" aria-hidden="true">₱</span>
                            <span id="empRateView" class="emp-rate-val" tabindex="-1">—</span>
                            <i class="fas fa-lock emp-rate-lock" aria-hidden="true" title="{{ __('Set by the labor type') }}"></i>
                            <input type="hidden" name="rate_per_hour" id="empRate">
                        </div>
                        <span class="ep-hint" id="empRateHint">{{ __('Auto from labor type') }}</span>
                    </div>

                    <div class="emp-field">
                        <label class="ep-label" for="empSite">{{ __('Site') }}</label>
                        <select name="site_id" id="empSite" class="form-select @error('site_id') is-invalid @enderror">
                            <option value="">{{ __('— Unassigned —') }}</option>
                            @foreach($sites as $s)
                                <option value="{{ $s->id }}">{{ $s->name }}</option>
                            @endforeach
                        </select>
                        @error('site_id')
                            <p class="emp-err" role="alert"><i class="fas fa-circle-exclamation" aria-hidden="true"></i>{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="emp-field">
                        <label class="ep-label" for="empShift">{{ __('Shift') }}</label>
                        <select name="shift_id" id="empShift" class="form-select @error('shift_id') is-invalid @enderror"
                                aria-describedby="empShiftHint">
                            @foreach($shifts as $sh)
                                <option value="{{ $sh->id }}" @selected(! $sh->crosses_midnight)>
                                    {{ $sh->name }} — {{ \Carbon\Carbon::parse($sh->starts_at)->format('g:i A') }}
                                </option>
                            @endforeach
                        </select>
                        @error('shift_id')
                            <p class="emp-err" role="alert"><i class="fas fa-circle-exclamation" aria-hidden="true"></i>{{ $message }}</p>
                        @else
                            <span class="ep-hint" id="empShiftHint">{{ __('Movable later from the employee list.') }}</span>
                        @enderror
                    </div>
                </div>
            </section>

            <section class="ep-section emp-section">
                <header class="ep-section-head">
                    <span class="ep-section-icon" aria-hidden="true"><i class="fas fa-fingerprint"></i></span>
                    <div>
                        <h3 class="ep-section-title">{{ __('Kiosk') }}</h3>
                        <p class="ep-section-sub">{{ __('The slot their finger is stored in on the kiosk.') }}</p>
                    </div>
                </header>

                <div class="emp-field">
                    <label class="ep-label" for="empFp">
                        {{ __('Fingerprint ID') }}
                        <span class="ep-optional">{{ __('(from the kiosk)') }}</span>
                    </label>
                    <div class="emp-fp">
                        <span class="emp-fp-icon" aria-hidden="true"><i class="fas fa-fingerprint"></i></span>
                        <input type="text" name="fingerprint_id" id="empFp"
                               class="form-control emp-fp-input ep-mono @error('fingerprint_id') is-invalid @enderror"
                               placeholder="{{ __('Not enrolled yet') }}" autocomplete="off"
                               inputmode="numeric" aria-describedby="empFpHint">
                    </div>
                    @error('fingerprint_id')
                        <p class="emp-err" role="alert"><i class="fas fa-circle-exclamation" aria-hidden="true"></i>{{ $message }}</p>
                    @else
                        <span class="ep-hint" id="empFpHint">{{ __('The slot this worker\'s finger is stored in on the kiosk. Filled in by the scan — change it only if the kiosk was re-enrolled.') }}</span>
                    @enderror
                </div>

                {{-- No photo field — see the note in employees/create.blade.php. --}}
            </section>
        </div>

        <div class="emp-foot">
            <p class="emp-foot-note"><span class="ep-req" aria-hidden="true">*</span> {{ __('Required') }}</p>
            <button type="button" class="emp-btn-cancel" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
            <button type="submit" class="emp-btn-save" id="empFormSubmit">
                <i class="fas fa-check" aria-hidden="true"></i> <span>{{ __('Save & Activate') }}</span>
            </button>
        </div>
      </form>
    </div>
  </div>
</div>

{{-- ── Add bonus ───────────────────────────────────────────────────────────
     One worker, the pay period running now. The period is shown rather than
     asked for: this is the bonus for the week being worked, and a date box
     would only invite a bonus dated into a week already paid. --}}
@if(auth()->user()?->isAdmin())
<div class="modal fade" id="empBonusModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered emp-dialog" style="max-width:460px;">
        <div class="modal-content emp-modal">
            <form method="POST" id="empBonusForm" data-once>
                @csrf
                <div class="emp-head">
                    <span class="emp-head-icon"><i class="fas fa-gift"></i></span>
                    <div class="emp-head-text">
                        <h6 class="emp-head-title">{{ __('Add bonus') }}</h6>
                        <p class="emp-head-sub"><span id="bonusModalName">—</span> &middot; {{ $bonusPeriod }}</p>
                    </div>
                    <button type="button" class="emp-head-x" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"><i class="fas fa-times"></i></button>
                </div>
                <div class="modal-body emp-body">
                    <div class="emp-field">
                        <label class="ep-label" for="bonusAmount">{{ __('Amount') }} <span class="ep-req">*</span></label>
                        <input class="form-control" id="bonusAmount" type="number" step="0.01" min="0.01" name="amount" required>
                        <span class="ep-hint">{{ __('Paid with this period, on top of the net. A bonus is not wages, so nothing is withheld on it.') }}</span>
                    </div>
                    <div class="emp-field">
                        <label class="ep-label" for="bonusNote">{{ __('Note') }}</label>
                        <input class="form-control" id="bonusNote" type="text" name="note" maxlength="160" placeholder="{{ __('What it is for') }}">
                    </div>
                </div>
            </form>
            {{-- Given already this period, each with a way back while the
                 period is still running. --}}
            <div class="modal-body emp-body pt-0" id="bonusGivenWrap" hidden>
                <p class="ep-label">{{ __('Given this period') }}</p>
                <div id="bonusGiven"></div>
            </div>
            <div class="emp-foot">
                <p class="emp-foot-note"><span class="ep-req">*</span> {{ __('Required') }}</p>
                <button type="button" class="emp-btn-cancel" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                <button type="submit" form="empBonusForm" class="emp-btn-save"><i class="fas fa-check"></i> <span>{{ __('Add bonus') }}</span></button>
            </div>
        </div>
    </div>
</div>
@endif

{{-- ── Register employee, step by step ─────────────────────────────────── --}}
@include('employees._register_wizard')

{{-- ── The bulk action dialog ──────────────────────────────────────────────
     One dialog for the four actions on the bar; the script shows the fields
     the action needs. It always says who changes and from what (Now → After),
     names them, and takes the date the change starts — today at the earliest,
     because a day already worked is never changed. --}}
<div class="modal fade" id="rmxBulkModal" tabindex="-1" aria-hidden="true" aria-labelledby="bkTitle">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable emp-dialog" style="max-width:520px;">
        <div class="modal-content emp-modal">
            <div class="emp-head">
                <span class="emp-head-icon" aria-hidden="true"><i class="fas fa-users" id="bkIcon"></i></span>
                <div class="emp-head-text">
                    <h6 class="emp-head-title" id="bkTitle">{{ __('Transfer site') }}</h6>
                    <p class="emp-head-sub" id="bkSub"></p>
                </div>
                <button type="button" class="emp-head-x" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"><i class="fas fa-times" aria-hidden="true"></i></button>
            </div>
            <div class="modal-body emp-body">
                {{-- Transfer site / Change shift: one button per choice. --}}
                <div class="emp-field" id="bkTo">
                    <span class="ep-label" id="bkToLabel">{{ __('Move to') }}</span>
                    <div class="bk-seg" id="bkSeg" role="group" aria-labelledby="bkToLabel"></div>
                </div>

                {{-- Change labor type. --}}
                <div class="emp-field" id="bkLabor" hidden>
                    <label class="ep-label" for="bkLaborType">{{ __('Change to') }}</label>
                    <select class="form-select" id="bkLaborType"></select>
                    <label class="bk-ck" for="bkUseRate">
                        <input type="checkbox" id="bkUseRate" checked>
                        <span>{{ __('Use this labor type\'s rate') }} <b id="bkLaborRate"></b></span>
                    </label>
                </div>

                {{-- Add bonus. --}}
                <div class="emp-field" id="bkAmount" hidden>
                    <label class="ep-label" for="bkAmt">{{ __('Bonus per employee') }}</label>
                    <div class="bk-peso"><span aria-hidden="true">₱</span><input class="form-control" type="number" id="bkAmt" min="0.01" step="0.01" inputmode="decimal" placeholder="0.00"></div>
                </div>
                <div class="emp-field" id="bkReason" hidden>
                    <label class="ep-label" for="bkWhy">{{ __('Reason') }}</label>
                    <select class="form-select" id="bkWhy">
                        <option>{{ __('Performance') }}</option>
                        <option>{{ __('Perfect attendance') }}</option>
                        <option>{{ __('Holiday') }}</option>
                        <option>{{ __('Project completion') }}</option>
                        <option value="Other">{{ __('Other') }}</option>
                    </select>
                    <input class="form-control" type="text" id="bkWhyOther" maxlength="160" placeholder="{{ __('Describe the reason') }}" aria-label="{{ __('Describe the reason') }}" hidden>
                </div>
                <div class="emp-field" id="bkPeriod" hidden>
                    <label class="ep-label" for="bkPer">{{ __('Add to payroll period') }}</label>
                    <select class="form-select" id="bkPer">
                        @foreach($bulk['periods'] as $p)
                            <option value="{{ $p['key'] }}">{{ $p['label'] }}{{ $p['key'] === 'current' ? ' ' . __('(current)') : '' }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="bk-flow" id="bkFlow">
                    <div class="bk-from"><small>{{ __('Now') }}</small><div id="bkFrom"></div></div>
                    <i class="ti ti-arrow-right" aria-hidden="true"></i>
                    <div class="bk-to"><small>{{ __('After') }}</small><b id="bkToName">–</b><span id="bkToSub"></span></div>
                </div>
                <div class="bk-total" id="bkTotal" hidden></div>
                <div class="bk-chips" id="bkChips"></div>

                <div class="emp-field" id="bkEffective">
                    <label class="ep-label" for="bkEff">{{ __('Effective date') }}</label>
                    <input class="form-control" type="date" id="bkEff" min="{{ $bulk['today'] }}" value="{{ $bulk['today'] }}">
                    <span class="ep-hint" id="bkEffHelp"></span>
                </div>

                <p class="bk-note"><i class="ti ti-info-circle" aria-hidden="true"></i><span id="bkNote"></span></p>
                <p class="bk-err" id="bkErr" role="alert" hidden></p>
            </div>
            <div class="emp-foot">
                <button type="button" class="emp-btn-cancel" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                <button type="button" class="emp-btn-save" id="bkConfirm"><i class="fas fa-check" aria-hidden="true"></i> <span></span></button>
            </div>
        </div>
    </div>
</div>

{{-- ── Script ──────────────────────────────────────────────────────────────── --}}
<script>
// ── Add bonus (for the pay period running now) ───────────────────────────────
// The form posts and the page comes back with a toast, the way the rest of the
// money screens work; what it already gave this period is listed with a way to
// take it back while the period is still open.
(function () {
    const modalEl = document.getElementById('empBonusModal');
    if (!modalEl) return;
    let modal = null;

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.js-add-bonus');
        if (!btn) return;

        const id   = btn.dataset.id;
        const form = document.getElementById('empBonusForm');

        form.action = `{{ url('employees') }}/${id}/bonus`;
        form.reset();
        delete form.dataset.sent;
        document.getElementById('bonusModalName').textContent = btn.dataset.name;

        // What this worker was already given this period.
        let given = [];
        try { given = JSON.parse(btn.dataset.given || '[]'); } catch (err) {}

        const wrap = document.getElementById('bonusGivenWrap');
        const list = document.getElementById('bonusGiven');
        list.textContent = '';

        given.forEach(b => {
            const row = document.createElement('div');
            row.className = 'emp-bonus-given';

            const what = document.createElement('span');
            what.textContent = b.amount + ' · ' + b.on + (b.note ? ' · ' + b.note : '');
            row.appendChild(what);

            const take = document.createElement('form');
            take.method = 'POST';
            take.action = `{{ url('employees') }}/${id}/bonus/${b.id}`;
            take.dataset.once = '';
            take.innerHTML = '@csrf' + '<input type="hidden" name="_method" value="DELETE">';

            const remove = document.createElement('button');
            remove.type = 'submit';
            remove.className = 'emp-bonus-remove';
            remove.textContent = '{{ __('Remove') }}';
            take.appendChild(remove);
            row.appendChild(take);
            list.appendChild(row);
        });

        wrap.hidden = given.length === 0;

        if (!modal && window.bootstrap) modal = new bootstrap.Modal(modalEl);
        if (modal) modal.show();
        setTimeout(() => document.getElementById('bonusAmount').focus(), 250);
    });

    // Sent once: a second click on Add bonus would give a second bonus.
    document.addEventListener('submit', function (e) {
        const form = e.target.closest('form[data-once]');
        if (!form) return;
        if (form.dataset.sent) { e.preventDefault(); return; }
        form.dataset.sent = '1';
        form.querySelectorAll('button[type="submit"]').forEach(b => { b.disabled = true; });
        // The modal's own button sits in the footer, outside the form it sends.
        if (form.id) {
            document.querySelectorAll(`button[form="${form.id}"]`).forEach(b => { b.disabled = true; });
        }
    }, true);
})();

(function () {
    // ── Tabs + stat cards ────────────────────────────────────────────────────
    // Leaving a tab is announced, so the selection module can put away a
    // selection that would otherwise survive out of sight.
    function switchTab(name) {
        const leaving = document.querySelector('.rm-pane.active');
        document.querySelectorAll('.rmx-tab').forEach(t => {
            const on = t.dataset.tab === name;
            t.classList.toggle('is-open', on);
            t.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        // The card for the list on screen, and the label where the tabs were.
        document.querySelectorAll('.rmx-stat').forEach(c => c.classList.toggle('is-on', c.dataset.tab === name));
        const card = document.querySelector('.rmx-stat[data-tab="' + name + '"]');
        const showing = document.getElementById('rmxShowing');
        if (card && showing) {
            showing.querySelector('b').textContent = card.querySelector('.rmx-stat-lbl').textContent.trim();
            showing.querySelector('.rmx-count').textContent = card.querySelector('.rmx-stat-num').textContent.trim();
        }
        document.querySelectorAll('.rm-pane').forEach(p => p.classList.toggle('active', p.dataset.pane === name));
        document.dispatchEvent(new CustomEvent('rmx:tab', {
            detail: { from: leaving && leaving.dataset.pane !== name ? leaving : null },
        }));
        // In the address itself, not after a #: a save, a cancel or a restore
        // redirects back to this address, and the server opens the tab it
        // names. A # never reaches the server, so every action used to land
        // on Active.
        try {
            const url = new URL(location.href);
            url.searchParams.set('tab', name);
            url.hash = '';
            history.replaceState(null, '', url);
        } catch (e) {}
    }
    document.querySelectorAll('.rmx-tab, .rmx-stat').forEach(el => el.addEventListener('click', () => switchTab(el.dataset.tab)));
    // The open tab is decided in the markup — see $openTab above. The hash is
    // still honoured for links saved before ?tab= existed.
    const hash = (location.hash || '').replace('#', '');
    if (['pending', 'active', 'removed'].includes(hash)) switchTab(hash);
    // The card for the list the page opened on.
    const opened = document.querySelector('.rm-pane.active')?.dataset.pane;
    document.querySelectorAll('.rmx-stat').forEach(c => c.classList.toggle('is-on', c.dataset.tab === opened));

    // ── The arrow at the side ────────────────────────────────────────────────
    // The cards are out on every load (the markup opens them). Put away, they
    // stay away only until the page loads again: nothing is remembered.
    const sum = document.getElementById('rmxSum'), handle = document.getElementById('rmxHandle');
    if (sum && handle) {
        handle.addEventListener('click', () => {
            const on = !sum.classList.contains('open');
            sum.classList.toggle('open', on);
            handle.setAttribute('aria-expanded', on ? 'true' : 'false');
            const label = on ? @json(__('Hide Active, Pending and Removed')) : @json(__('Show Active, Pending and Removed'));
            handle.title = label; handle.setAttribute('aria-label', label);
        });
    }

    // ── Employee form modal (confirm / complete) ─────────────────────────────
    const storeUrl = "{{ route('employees.store') }}";
    const baseUrl  = "{{ url('employees') }}";
    const nextFp   = "{{ $nextFingerprintId }}";

    const modalEl   = document.getElementById('empFormModal');
    const form      = document.getElementById('empForm');
    const methodEl  = document.getElementById('empFormMethod');
    const titleEl   = document.getElementById('empFormTitle');
    const subEl     = document.getElementById('empFormSub');
    const submitLbl = document.querySelector('#empFormSubmit span');
    const firstEl   = document.getElementById('empFirst');
    const middleEl  = document.getElementById('empMiddle');
    const lastEl    = document.getElementById('empLast');
    const laborEl   = document.getElementById('empLabor');
    const rateEl    = document.getElementById('empRate');
    const rateView  = document.getElementById('empRateView');
    const siteEl    = document.getElementById('empSite');
    const fpEl      = document.getElementById('empFp');

    let bsModal = null;
    function getModal() {
        if (!bsModal && window.bootstrap) bsModal = new bootstrap.Modal(modalEl);
        return bsModal;
    }

    function updateRate() {
        const opt = laborEl.options[laborEl.selectedIndex];
        if (opt && opt.value) {
            const hourly = (parseFloat(opt.dataset.daily || 0) / 8);
            rateView.textContent = hourly.toFixed(2);
            rateEl.value = hourly.toFixed(2);
        } else {
            rateView.textContent = '—'; rateEl.value = '';
        }
    }
    laborEl.addEventListener('change', updateRate);

    const modeField = document.getElementById('empFormModeField');
    const idField   = document.getElementById('empFormIdField');

    function openModal(mode, d) {
        modeField.value = mode;
        idField.value   = d.id || '';
        // The row carries the parts already split by Employee::splitName(), so
        // a worker created by the kiosk with only a bare name still opens with
        // the three boxes filled in rather than one of them holding all of it.
        firstEl.value  = d.first  || '';
        middleEl.value = d.middle || '';
        lastEl.value   = d.last   || '';
        siteEl.value = d.site || '';
        fpEl.value   = d.fp || (mode === 'add' ? nextFp : '');
        laborEl.value = d.labor || '';
        updateRate();

        if (mode === 'add') {
            form.action = storeUrl; methodEl.value = 'POST';
            titleEl.textContent = 'Add Employee Manually';
            subEl.textContent = 'Register a worker without a kiosk scan.';
            submitLbl.textContent = 'Register';
        } else if (mode === 'confirm') {
            form.action = `${baseUrl}/${d.id}/complete`; methodEl.value = 'POST';
            titleEl.textContent = 'Confirm the information';
            subEl.textContent = 'Review the details submitted from the kiosk — fix any typo and add a photo if needed, then activate.';
            submitLbl.textContent = 'Confirm & Activate';
        } else if (mode === 'complete') {
            form.action = `${baseUrl}/${d.id}/complete`; methodEl.value = 'POST';
            titleEl.textContent = 'Complete Registration';
            subEl.textContent = 'Set this kiosk-detected worker’s details to activate them.';
            submitLbl.textContent = 'Save & Activate';
        } else { // edit
            form.action = `${baseUrl}/${d.id}`; methodEl.value = 'PUT';
            titleEl.textContent = 'Edit Employee';
            subEl.textContent = 'Update this employee’s details.';
            submitLbl.textContent = 'Save Changes';
        }
        const m = getModal(); if (m) m.show();
        setTimeout(() => firstEl.focus(), 250);
    }

    // Delegated so pending rows swapped in by live polling stay clickable.
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.js-emp-edit');
        if (!btn) return;
        openModal(btn.dataset.mode, {
            id: btn.dataset.id, labor: btn.dataset.labor,
            first: btn.dataset.first, middle: btn.dataset.middle, last: btn.dataset.last,
            rate: btn.dataset.rate, site: btn.dataset.site, fp: btn.dataset.fp,
        });
    });

    form.addEventListener('submit', function () {
        const b = document.getElementById('empFormSubmit');
        b.disabled = true; b.querySelector('i').className = 'fas fa-spinner fa-spin';
    });

    // If validation failed server-side, reopen the form so errors aren't lost.
    @if($errors->any() && (old('first_name') || old('name')))
        openModal('{{ old('_form_mode', 'complete') }}', {
            id: '{{ old('_form_id') }}',
            first: @json(old('first_name')), middle: @json(old('middle_name')), last: @json(old('last_name')),
            labor: '{{ old('labor_type_id') }}', site: '{{ old('site_id') }}', fp: '{{ old('fingerprint_id') }}'
        });
    @endif

    // ── Realtime: the three lists keep themselves current ────────────────────
    // Active, Pending and Removed are live regions (their tbody carries
    // data-live), so live.js re-reads this page when a worker, a scan or a
    // site changes and patches in the rows that differ: a finger enrolled on
    // the kiosk takes the worker out of Pending and puts them in Active here,
    // with nothing reloaded. Only the Pending rows used to be refreshed, so
    // the cards said one thing and the lists another until the next reload.
    //
    // What is left to this page is what the rows do not carry: the numbers on
    // the cards — counted from the rows themselves, so the two cannot differ —
    // and saying who has arrived.
    (function () {
        const rowsOf = k => Array.from(document.querySelectorAll('.rm-pane[data-pane="' + k + '"] tbody > tr[data-live-key]'));
        const keysOf = k => rowsOf(k).map(tr => tr.dataset.liveKey);
        const nameOf = tr => (tr.querySelector('.rmx-name')?.textContent || '').trim();
        const set    = (sel, val) => { const el = document.querySelector(sel); if (el) el.textContent = val; };

        function counts() {
            ['active', 'pending', 'removed'].forEach(k => {
                const n = rowsOf(k).length;
                set('.rmx-stat-' + k + ' .rmx-stat-num', n);
                if (document.querySelector('.rmx-stat.is-on')?.dataset.tab === k) set('#rmxShowing .rmx-count', n);
            });
        }

        let before = { active: keysOf('active'), pending: keysOf('pending') };

        function news() {
            const now = { active: keysOf('active'), pending: keysOf('pending') };
            if (!window.Notify) { before = now; return; }

            // Out of Pending and into Active: their finger was enrolled, or
            // their details were confirmed at another desk.
            const joined = rowsOf('active').filter(tr => before.pending.includes(tr.dataset.liveKey));
            if (joined.length === 1) Notify.success(@json(__(':name is now active')).replace(':name', nameOf(joined[0])));
            else if (joined.length > 1) Notify.success(@json(__(':n workers are now active')).replace(':n', joined.length));

            // Not counting anybody this very page has just registered: the
            // pop-up's last step said so already (_register_wizard.blade.php).
            const arrived = now.pending.filter(k => !before.pending.includes(k) && !before.active.includes(k)).length;
            const own     = Math.min(arrived, window.rwJustRegistered || 0);
            if (own) window.rwJustRegistered -= own;
            const fresh   = arrived - own;
            if (fresh === 1) Notify.info(@json(__('1 new worker is waiting in Pending')));
            else if (fresh > 1) Notify.info(@json(__(':n new workers are waiting in Pending')).replace(':n', fresh));

            before = now;
        }

        // Each list says when it has been patched. The counts follow at once;
        // the news waits until all three have landed, so a worker moving from
        // one list to another is told as one thing.
        let told = null;
        document.addEventListener('live:updated', function (e) {
            if (!e.target.closest || !e.target.closest('.rm-pane')) return;
            counts();
            clearTimeout(told);
            told = setTimeout(news, 0);
        });
    })();
})();


// ── Bulk selection ───────────────────────────────────────────────────────────
// One Select toggle, beside the tabs, acting on whichever tab is open. Each
// tab keeps its own checkboxes and its own actions, and leaving a tab puts
// its selection away so nothing stays ticked out of sight.
(function () {
    const csrf   = document.querySelector('meta[name="csrf-token"]').content;
    const toggle = document.getElementById('rmxSelect');

    const ENDPOINTS = {
        remove:  { url: '{{ route('employees.bulk-delete') }}',       method: 'DELETE' },
        restore: { url: '{{ route('employees.bulk-restore') }}',      method: 'PATCH'  },
        purge:   { url: '{{ route('employees.bulk-force-delete') }}', method: 'DELETE' },
    };

    const openPane = () => document.querySelector('.rm-pane.active');
    const paneOf   = el   => el.closest('.rm-pane');
    // Only the rows on screen: a filter hides the rest, and a Select all or a
    // bulk delete must never reach a worker nobody can see.
    const boxesIn  = pane => Array.from(pane.querySelectorAll('tbody tr:not([hidden]) .rmx-check'));
    const pickedIn = pane => boxesIn(pane).filter(b => b.checked);

    function syncToggle() {
        const pane = openPane();
        const on   = !!pane && pane.classList.contains('selecting');
        toggle.classList.toggle('is-on', on);
        toggle.querySelector('.js-select-label').textContent = on ? 'Done' : 'Select';
        // Nothing to select on an empty tab, so the toggle has no job there.
        toggle.disabled = !pane || (boxesIn(pane).length === 0 && !on);
    }

    function sync(pane) {
        if (!pane) return;
        const boxes     = boxesIn(pane);
        const picked    = pickedIn(pane);
        const all       = pane.querySelector('.rmx-check-all');
        const bar       = pane.querySelector('.rmx-bulk');
        const selecting = pane.classList.contains('selecting');

        if (all) {
            all.disabled      = boxes.length === 0;
            all.checked       = boxes.length > 0 && picked.length === boxes.length;
            all.indeterminate = picked.length > 0 && picked.length < boxes.length;
        }
        if (bar) {
            // The bar rides along with selection mode rather than appearing on
            // the first tick, so entering the mode shows what can be done.
            bar.hidden = !selecting;
            const n = bar.querySelector('.rmx-bulk-count strong');
            if (n) n.textContent = picked.length;
            bar.querySelectorAll('button').forEach(b => { b.disabled = picked.length === 0; });
        }
        if (pane === openPane()) syncToggle();
        // The bar under the Active list acts on whoever is ticked here.
        document.dispatchEvent(new CustomEvent('rmx:selection', {
            detail: { pane: pane, selecting: selecting, ids: picked.map(b => b.value) },
        }));
    }

    // Entering the mode reveals the checkbox column; leaving it drops whatever
    // was ticked, so a stale selection can never survive out of sight.
    function setSelecting(pane, on) {
        if (!pane) return;
        pane.classList.toggle('selecting', on);
        // The ticks are theirs: the lists are not patched under a selection
        // (live.js waits on .live-busy) and catch up when Done is pressed.
        pane.classList.toggle('live-busy', on);
        if (!on) {
            boxesIn(pane).forEach(b => { b.checked = false; });
            const all = pane.querySelector('.rmx-check-all');
            if (all) { all.checked = false; all.indeterminate = false; }
        }
        sync(pane);
        syncToggle();
    }

    toggle.addEventListener('click', function () {
        const pane = openPane();
        if (pane) setSelecting(pane, !pane.classList.contains('selecting'));
    });

    document.addEventListener('rmx:tab', function (e) {
        if (e.detail && e.detail.from) setSelecting(e.detail.from, false);
        syncToggle();
    });
    document.addEventListener('rmx:filtered', function (e) { sync(e.detail.pane); });
    // A bulk action has been saved: the selection has done its job.
    document.addEventListener('rmx:select-done', function () {
        const pane = openPane();
        if (pane && pane.classList.contains('selecting')) setSelecting(pane, false);
    });

    document.addEventListener('change', function (e) {
        const t = e.target;
        if (t.classList && t.classList.contains('rmx-check-all')) {
            const pane = paneOf(t);
            boxesIn(pane).forEach(b => { b.checked = t.checked; });
            sync(pane);
        } else if (t.classList && t.classList.contains('rmx-check')) {
            sync(paneOf(t));
        }
    });

    document.addEventListener('click', async function (e) {
        const btn = e.target.closest('.js-bulk-clear, .js-bulk-remove, .js-bulk-restore, .js-bulk-purge');
        if (!btn) return;

        const pane = paneOf(btn);

        if (btn.classList.contains('js-bulk-clear')) {
            boxesIn(pane).forEach(b => { b.checked = false; });
            sync(pane);
            return;
        }

        const ids = pickedIn(pane).map(b => b.value);
        if (!ids.length) return;

        const kind = btn.classList.contains('js-bulk-restore') ? 'restore'
                   : btn.classList.contains('js-bulk-purge')   ? 'purge'
                   : 'remove';
        const one  = ids.length === 1;
        const many = ids.length + (one ? ' record' : ' records');

        const ASK = {
            purge:   { title: 'Permanently delete ' + many + '?',
                       message: 'They cannot be restored. Their attendance and payroll records are kept.',
                       confirmLabel: 'Delete permanently', tone: 'danger' },
            restore: { title: 'Restore ' + many + '?',
                       message: 'They move back to the list they came from.',
                       confirmLabel: 'Restore', tone: 'brand' },
        };
        const ask = ASK[kind] || {
            title: 'Remove ' + many + '?',
            message: 'They move to the Removed tab and can be restored from there.',
            confirmLabel: 'Remove', tone: 'warning',
        };

        if (!await Notify.confirm(ask)) return;

        const { url, method } = ENDPOINTS[kind];
        const label = btn.innerHTML;
        pane.querySelectorAll('.rmx-bulk button').forEach(b => { b.disabled = true; });
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Working…';

        try {
            const res = await fetch(url, {
                method:  method,
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'Accept':       'application/json',
                },
                body: JSON.stringify({ ids: ids }),
            });
            const data = await res.json();

            if (res.ok && data.success) {
                const done = data.deleted ?? data.restored ?? ids.length;
                Notify.success(done + (done === 1 ? ' record' : ' records') + ' updated');
                setTimeout(() => location.reload(), 700);
                return;
            }
            Notify.error(data.message || 'Could not complete that action.');
        } catch (err) {
            Notify.error('Network error — please try again.');
        }

        // Hand the buttons back to sync() rather than blanket-enabling them —
        // it is the one place that knows whether anything is still selected.
        btn.innerHTML = label;
        sync(pane);
    });

    // A list patched live fires no change event; the filter module re-applies
    // itself then and announces rmx:filtered, which is listened for above.

    document.querySelectorAll('.rm-pane').forEach(sync);
    syncToggle();
})();

// ── Site and labor type filters ─────────────────────────────────────────────
// The Site and Labor type headings open a list of what is in that column, each
// with how many workers it holds; picking one shows only those workers, and
// the count beside the list's name says how many that leaves. Each tab keeps
// its own; a reload shows everybody again. The counts follow the other filter,
// so they always add up to what the list would show.
(function () {
    const KEYS = {
        site:  { all: @json(__('All sites')),       none: @json(__('No site')) },
        labor: { all: @json(__('All labor types')), none: @json(__('No labor type')) },
        shift: { all: @json(__('All shifts')),      none: @json(__('No shift')) },
    };
    const state   = new Map();
    const stateOf = pane => { if (!state.has(pane)) state.set(pane, { site: null, labor: null, shift: null }); return state.get(pane); };
    const rowsOf  = pane => Array.from(pane.querySelectorAll('tbody > tr[data-site]'));
    const valueOf = (tr, key) => tr.dataset[key] || '';
    // The search box: every word typed has to be in the worker's name or
    // number, whatever the capitals or accents ("renono" finds Reñono).
    const search  = document.getElementById('rmxSearch');
    const fold    = s => s.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
    let words     = [];
    const found   = tr => !words.length || (t => words.every(w => t.includes(w)))(fold(tr.querySelector('.rmx-who')?.textContent || ''));
    // null is "all"; '' is the rows with nothing in the column.
    const matches = (tr, f, skip) => found(tr) && Object.keys(KEYS).every(k => k === skip || f[k] === null || valueOf(tr, k) === f[k]);
    const nameOf  = (key, value) => value === '' ? KEYS[key].none : value;
    const sitePick = document.getElementById('rmxSite');

    function apply(pane) {
        const f    = stateOf(pane);
        const rows = rowsOf(pane);
        let shown  = 0;
        rows.forEach(tr => {
            const on = matches(tr, f);
            tr.hidden = !on;
            if (on) shown++;
            else { const box = tr.querySelector('.rmx-check'); if (box) box.checked = false; }
        });
        pane.querySelectorAll('.rmx-fbtn').forEach(b => b.classList.toggle('is-on', f[b.dataset.filter] !== null));

        const on = Object.keys(KEYS).filter(k => f[k] !== null);
        let empty = pane.querySelector('.rmx-fempty');
        if (!empty) {
            empty = document.createElement('div');
            empty.className = 'rmx-fempty';
            empty.innerHTML = @json(__('No workers match this filter.')) + ' <button type="button" class="rmx-fclear-link" data-fclear>' + @json(__('Show everybody')) + '</button>';
            pane.querySelector('.rmx-card')?.appendChild(empty);
        }
        const narrowed = on.length > 0 || words.length > 0;
        empty.hidden = !(narrowed && shown === 0);

        if (pane.classList.contains('active')) {
            const sum = document.getElementById('rmxFilterSum');
            if (sum) {
                sum.hidden = !narrowed;
                sum.querySelector('[data-fsum-text]').textContent = (words.length ? ['“' + search.value.trim() + '”'] : [])
                    .concat(on.map(k => nameOf(k, f[k]))).join(' · ');
                sum.querySelector('[data-fsum-n]').textContent = shown;
            }
            // The picker beside Search says what the Site heading says.
            if (sitePick) sitePick.value = f.site === null ? '*' : f.site;
        }
        document.dispatchEvent(new CustomEvent('rmx:filtered', { detail: { pane } }));
    }

    // One list for every heading, on the page itself, so the table's own
    // scrolling box cannot cut it off. .rmx gives it the page's colours.
    const menu = document.createElement('div');
    menu.className = 'rmx rmx-fmenu';
    menu.setAttribute('role', 'menu');
    menu.hidden = true;
    document.body.appendChild(menu);
    let openBtn = null;

    function closeMenu() {
        if (!openBtn) return;
        menu.hidden = true;
        openBtn.setAttribute('aria-expanded', 'false');
        openBtn = null;
    }

    function openMenu(btn) {
        const pane = btn.closest('.rm-pane');
        const key  = btn.dataset.filter;
        const f    = stateOf(pane);
        const pool = rowsOf(pane).filter(tr => matches(tr, f, key));
        const counts = new Map();
        pool.forEach(tr => counts.set(valueOf(tr, key), (counts.get(valueOf(tr, key)) || 0) + 1));

        const items = [[null, KEYS[key].all, pool.length]]
            .concat([...counts.keys()].filter(Boolean).sort((a, b) => a.localeCompare(b)).map(v => [v, v, counts.get(v)]));
        if (counts.has('')) items.push(['', KEYS[key].none, counts.get('')]);

        menu.innerHTML = '';
        items.forEach(([value, label, n]) => {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'rmx-fopt' + (value === null ? ' is-all' : '');
            b.setAttribute('role', 'menuitemradio');
            b.setAttribute('aria-checked', f[key] === value ? 'true' : 'false');
            const t = document.createElement('span'); t.textContent = label;
            const c = document.createElement('b');    c.textContent = n;
            b.append(t, c);
            b.addEventListener('click', () => { f[key] = value; closeMenu(); apply(pane); btn.focus(); });
            menu.appendChild(b);
        });

        menu.hidden = false;
        const r = btn.getBoundingClientRect();
        menu.style.top  = Math.round(r.bottom + 6) + 'px';
        menu.style.left = Math.round(Math.max(8, Math.min(r.left - 6, window.innerWidth - menu.offsetWidth - 8))) + 'px';
        btn.setAttribute('aria-expanded', 'true');
        openBtn = btn;
        (menu.querySelector('[aria-checked="true"]') || menu.firstElementChild).focus();
    }

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.rmx-fbtn');
        if (btn) {
            e.stopPropagation();
            const again = openBtn === btn;
            closeMenu();
            if (!again) openMenu(btn);
            return;
        }
        if (e.target.closest('[data-fclear]')) {
            const pane = document.querySelector('.rm-pane.active');
            if (search) search.value = '';
            words = [];
            if (pane) { state.set(pane, { site: null, labor: null, shift: null }); apply(pane); }
            return;
        }
        if (openBtn && !menu.contains(e.target)) closeMenu();
    });

    // Typing narrows the list on screen at once. The words stay in the box
    // from one tab to the next, so the same worker can be looked for in each.
    function typed() {
        words = fold(search.value).split(/\s+/).filter(Boolean);
        closeMenu();
        const pane = document.querySelector('.rm-pane.active');
        if (pane) apply(pane);
    }
    // The site picker sets the same filter the Site heading does, for the
    // list on screen; '*' is everybody, '' the workers with no site.
    if (sitePick) {
        sitePick.addEventListener('change', function () {
            closeMenu();
            const pane = document.querySelector('.rm-pane.active');
            if (!pane) return;
            stateOf(pane).site = sitePick.value === '*' ? null : sitePick.value;
            apply(pane);
        });
    }
    if (search) {
        search.addEventListener('input', typed);
        search.addEventListener('search', typed);
        // A box the browser refilled on Back narrows the list it shows.
        if (search.value) typed();
    }

    menu.addEventListener('keydown', function (e) {
        const opts = Array.from(menu.querySelectorAll('.rmx-fopt'));
        const at   = opts.indexOf(document.activeElement);
        if (e.key === 'ArrowDown') { e.preventDefault(); opts[Math.min(at + 1, opts.length - 1)]?.focus(); }
        if (e.key === 'ArrowUp')   { e.preventDefault(); opts[Math.max(at - 1, 0)]?.focus(); }
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && openBtn) { const b = openBtn; closeMenu(); b.focus(); }
    });
    window.addEventListener('resize', closeMenu);
    // The page moving under the list closes it; the list's own scrolling does not.
    window.addEventListener('scroll', function (e) { if (e.target !== menu) closeMenu(); }, true);

    // The list's count and the summary beside it belong to the tab on screen.
    document.addEventListener('rmx:tab', function () {
        closeMenu();
        const pane = document.querySelector('.rm-pane.active');
        if (pane) apply(pane);
    });

    // The lists are patched live, and a patch writes each row as the server
    // drew it — every one of them showing. The filter is put back in the same
    // breath, before the screen is drawn, so a hidden row never blinks back.
    document.addEventListener('live:updated', function (e) {
        const pane = e.target.closest && e.target.closest('.rm-pane');
        if (pane) apply(pane);
    });
})();

// ── Bulk actions ────────────────────────────────────────────────────────────
// The bar under a selection on the Active list, the dialog its four buttons
// open, and the Undo that follows a save. The dialog says what will change
// before anything is sent: who moves and from what (those already there are
// left out and counted), from which date, and what stays as it was. The
// server decides the same things again for itself.
(function () {
    const cfg     = @json($bulk);
    const bar     = document.getElementById('rmxActs');
    const modalEl = document.getElementById('rmxBulkModal');
    if (!bar || !modalEl) return;

    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const $    = id => document.getElementById(id);
    const peso = n => '₱' + Number(n).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const many = (n, word) => n + ' ' + word + (n === 1 ? '' : 's');
    const el   = (tag, text, cls) => { const x = document.createElement(tag); if (cls) x.className = cls; if (text != null) x.textContent = text; return x; };

    // ── The bar: up while somebody on the Active list is ticked ─────────────
    let pane = null, ids = [];

    document.addEventListener('rmx:selection', function (e) {
        const d = e.detail;
        if (!d.pane || d.pane.dataset.pane !== 'active') return;
        pane = d.pane;
        ids  = d.selecting && d.pane.classList.contains('active') ? d.ids : [];
        bar.classList.toggle('show', ids.length > 0);
        $('rmxActsN').textContent = ids.length + ' selected';
    });

    $('rmxActsClear').addEventListener('click', function () {
        const clear = pane && pane.querySelector('.js-bulk-clear');
        if (clear) clear.click();
    });

    // A row ticks where it is clicked while selecting, and Shift takes every
    // row from the last one ticked to this one. The row's own buttons keep
    // their jobs; the worker's card stays shut until Done.
    let lastBox = null;
    document.addEventListener('click', function (e) {
        const row = e.target.closest && e.target.closest('.rm-pane.selecting tbody tr');
        const box = row && row.querySelector('.rmx-check');
        if (!box) return;

        const range = function () {
            if (!e.shiftKey || !lastBox || lastBox === box) return;
            const all = Array.from(row.closest('.rm-pane').querySelectorAll('tbody tr:not([hidden]) .rmx-check'));
            const a = all.indexOf(lastBox), b = all.indexOf(box);
            if (a < 0 || b < 0) return;
            all.slice(Math.min(a, b), Math.max(a, b) + 1).forEach(x => { x.checked = true; });
        };

        if (e.target === box) { range(); lastBox = box; return; }

        const own = e.target.closest('a, form, label, input, select, .rmx-actions, button:not(.rmx-open)');
        if (own && row.contains(own)) return;

        e.preventDefault();
        e.stopPropagation();
        box.checked = !box.checked;
        range();
        lastBox = box;
        box.dispatchEvent(new Event('change', { bubbles: true }));
    }, true);

    // ── Who is ticked, read off their rows ──────────────────────────────────
    function chosen() {
        if (!pane) return [];
        return ids.map(function (id) {
            const box = pane.querySelector('.rmx-check[value="' + id + '"]');
            const tr  = box && box.closest('tr');
            if (!tr) return null;
            return {
                id: id,
                name: (tr.querySelector('.rmx-name')?.textContent || '').trim(),
                site: tr.dataset.siteId || '', shift: tr.dataset.shiftId || '', labor: tr.dataset.laborId || '',
                siteName: tr.dataset.site || '', shiftName: tr.dataset.shift || '', laborName: tr.dataset.labor || '',
                ot: tr.dataset.ot === '1', otName: tr.dataset.ot === '1' ? 'Overtime allowed' : 'No overtime',
            };
        }).filter(Boolean);
    }

    // ── The dialog ──────────────────────────────────────────────────────────
    const TITLE = { site: @json(__('Transfer site')), shift: @json(__('Change shift')), labor: @json(__('Change labor type')), overtime: @json(__('Allow overtime')), bonus: @json(__('Add bonus')) };
    const ICON  = { site: 'fa-location-dot', shift: 'fa-moon', labor: 'fa-helmet-safety', overtime: 'fa-clock', bonus: 'fa-gift' };
    const modal = () => bootstrap.Modal.getOrCreateInstance(modalEl);
    const show  = (id, on) => { $(id).hidden = !on; };

    let act = null, choice = null, people = [], sending = false;

    // One button per site or shift; the one pressed is the choice.
    function segments(options) {
        const seg = $('bkSeg');
        seg.textContent = '';
        options.forEach(function (o) {
            const b = el('button');
            b.type = 'button';
            b.dataset.v = o.v;
            b.setAttribute('aria-pressed', 'false');
            const i = el('i', null, 'ti ti-' + o.icon);
            i.setAttribute('aria-hidden', 'true');
            b.append(i, document.createTextNode(o.label));
            if (o.small) b.append(el('small', o.small));
            b.addEventListener('click', function () {
                choice = o.v;
                seg.querySelectorAll('button').forEach(x => {
                    x.classList.toggle('on', x === b);
                    x.setAttribute('aria-pressed', x === b ? 'true' : 'false');
                });
                update();
            });
            seg.appendChild(b);
        });
    }

    function open(kind) {
        people = chosen();
        if (!people.length) return;

        act = kind; choice = null; sending = false;
        $('bkErr').hidden = true;
        $('bkEff').value = cfg.today;
        $('bkTitle').textContent = TITLE[kind];
        $('bkIcon').className = 'fas ' + ICON[kind];
        $('bkSub').textContent = many(people.length, 'employee') + ' selected';

        show('bkTo', kind === 'site' || kind === 'shift' || kind === 'overtime');
        show('bkLabor', kind === 'labor');
        ['bkAmount', 'bkReason', 'bkPeriod', 'bkTotal'].forEach(id => show(id, kind === 'bonus'));
        show('bkFlow', kind !== 'bonus');
        show('bkEffective', kind !== 'bonus');

        if (kind === 'site') {
            $('bkToLabel').textContent = 'Move to';
            $('bkEffHelp').textContent = 'Scans from this date count at the new site.';
            segments(cfg.sites.map(s => ({ v: String(s.id), icon: 'map-pin', label: s.name }))
                .concat([{ v: '', icon: 'map-pin-off', label: 'Unassigned' }]));
        } else if (kind === 'shift') {
            $('bkToLabel').textContent = 'Change to';
            $('bkEffHelp').textContent = 'The new schedule applies to scans from this date.';
            segments(cfg.shifts.map(s => ({ v: String(s.id), icon: s.night ? 'moon' : 'sun', label: s.name, small: s.from })));
        } else if (kind === 'overtime') {
            $('bkToLabel').textContent = 'Overtime';
            $('bkEffHelp').textContent = 'Applies to work from this date.';
            segments([{ v: '1', icon: 'clock-plus', label: 'Allow overtime' }, { v: '0', icon: 'clock-off', label: 'Do not allow' }]);
        } else if (kind === 'labor') {
            $('bkEffHelp').textContent = 'The new labor type and rate apply to work from this date.';
            const pick = $('bkLaborType');
            pick.textContent = '';
            pick.appendChild(new Option('Choose a labor type…', ''));
            cfg.labor.forEach(t => pick.appendChild(new Option(t.name, String(t.id))));
            $('bkUseRate').checked = true;
        } else {
            $('bkAmt').value = '';
            $('bkWhy').selectedIndex = 0;
            $('bkWhyOther').value = '';
            $('bkWhyOther').hidden = true;
            $('bkPer').value = 'current';
        }

        update();
        modal().show();
    }

    // "Site A  5 / Site B  3": where the ones who will change stand now.
    function standing(list, key, none) {
        const counts = new Map();
        list.forEach(p => counts.set(p[key], (counts.get(p[key]) || 0) + 1));
        const box = $('bkFrom');
        box.textContent = '';
        [...counts.keys()].sort((a, b) => a.localeCompare(b)).forEach(function (name) {
            const line = el('span', null, 'bk-line');
            line.append(el('span', name || none), el('b', counts.get(name)));
            box.appendChild(line);
        });
    }

    function update() {
        let moving = people, label = '', note = '', ready = false;
        const skipped = words => {
            const n = people.length - moving.length;
            return n ? ' ' + n + ' ' + words + ' will be skipped.' : '';
        };
        const after = (name, sub) => { $('bkToName').textContent = name; $('bkToSub').textContent = sub || ''; };

        if (act === 'site') {
            const to = choice === null ? null
                     : choice === '' ? { name: 'Unassigned', where: 'Not linked to any site' }
                     : cfg.sites.find(s => String(s.id) === choice);
            moving = to ? people.filter(p => p.site !== choice) : people;
            standing(moving, 'siteName', 'No site');
            after(to ? to.name : 'Choose a site', to ? to.where : '');
            if (!to) {
                note = 'Pick the site to move them to.';
            } else {
                note = choice === '' ? 'They will not belong to any site until reassigned, and cannot scan at a kiosk until then.'
                     : to.kiosks     ? 'They will scan at ' + to.name + '’s kiosk from the effective date.'
                                     : to.name + ' has no kiosk yet, so they cannot scan there until a kiosk is set to it.';
                note += ' Past attendance stays at the site it was scanned at, and pay is not changed.';
                note += skipped(choice === '' ? 'already without a site' : 'already at ' + to.name);
            }
            label = 'Transfer ' + many(moving.length, 'employee');
            ready = !!to && moving.length > 0;
        } else if (act === 'shift') {
            const to = cfg.shifts.find(s => String(s.id) === choice);
            moving = to ? people.filter(p => p.shift !== choice) : people;
            standing(moving, 'shiftName', 'No shift');
            after(to ? to.name : 'Choose a shift', to ? [to.from, to.to].filter(Boolean).join(' – ') : '');
            note = !to ? 'Pick the shift to move them to.'
                 : 'Their scans follow ' + to.name + ' from the effective date.'
                   + (to.night ? ' Hours worked from ' + cfg.night + ' carry the night differential.' : '')
                   + ' Past attendance and payroll are not changed.' + skipped('already on ' + to.name);
            label = 'Change shift for ' + moving.length;
            ready = !!to && moving.length > 0;
        } else if (act === 'overtime') {
            // Allowed, the hours past their regular hours are overtime. Not
            // allowed, those hours are not recorded at all. And there is no
            // rule until somebody is allowed: the first allowance is what
            // begins the choosing, for everybody, so that is said before it
            // is confirmed.
            const allow = choice === '1';
            moving = choice === null ? people : people.filter(p => p.ot !== allow);
            standing(moving, 'otName', '');
            after(choice === null ? 'Choose' : allow ? 'Overtime allowed' : 'No overtime',
                  choice === null ? '' : allow ? 'Hours past their regular hours are overtime' : 'Hours past their regular hours are not recorded');

            if (choice === null) {
                note = 'Choose whether they may take overtime.';
            } else if (allow) {
                note = 'From the effective date, the hours they work past their regular hours are recorded and paid as overtime.';
                if (!cfg.overtimeRule) {
                    const others = Math.max(0, pane.querySelectorAll('tbody > tr[data-live-key]').length - moving.length);
                    note += ' From that date only the employees allowed here take overtime: the other ' + many(others, 'active employee')
                          + ' will not have it recorded until they are allowed too.';
                }
                note += ' Past attendance and payroll are not changed.' + skipped('already allowed');
            } else if (!cfg.overtimeRule) {
                note = 'Nobody is held back yet: overtime is recorded for everybody until the first employee is allowed.';
            } else {
                note = 'From the effective date, the hours they work past their regular hours are not recorded as overtime and are not paid.'
                     + ' Past attendance and payroll are not changed.' + skipped('not allowed already');
            }
            label = (choice === '0' ? 'Stop overtime for ' : 'Allow overtime for ') + moving.length;
            ready = choice !== null && moving.length > 0;
        } else if (act === 'labor') {
            const to      = cfg.labor.find(t => String(t.id) === choice);
            const useRate = $('bkUseRate').checked;
            moving = to ? people.filter(p => p.labor !== choice) : people;
            $('bkLaborRate').textContent = to ? '(' + peso(to.rate) + ' / hr)' : '';
            standing(moving, 'laborName', 'No labor type');
            after(to ? to.name : 'Choose a labor type',
                  to ? (useRate ? 'Rate becomes ' + peso(to.rate) + ' / hr' : 'Each keeps their current rate') : '');
            note = !to ? 'Pick the labor type to change them to.'
                 : (useRate ? 'Rates are updated to ' + peso(to.rate) + ' / hr from the effective date.' : 'Rates stay as they are.')
                   + ' Past attendance and payroll are not changed.' + skipped('already ' + to.name);
            label = 'Change labor type for ' + moving.length;
            ready = !!to && moving.length > 0;
        } else {
            const amount = parseFloat($('bkAmt').value) || 0;
            const period = cfg.periods.find(p => p.key === $('bkPer').value) || cfg.periods[0];
            const total  = $('bkTotal');
            total.textContent = '';
            total.append(el('span', people.length + ' × ' + peso(amount)), el('b', peso(amount * people.length)));
            note  = 'The bonus is added on top of net pay for ' + period.label + ' and shows on each payslip.';
            label = 'Add bonus to ' + people.length;
            ready = amount > 0;
        }

        const chips = $('bkChips');
        chips.textContent = '';
        moving.forEach(p => chips.appendChild(el('span', p.name)));

        $('bkNote').textContent = note;
        $('bkConfirm').querySelector('span').textContent = label;
        $('bkConfirm').disabled = !ready || sending;
    }

    $('bkLaborType').addEventListener('change', function () { choice = this.value || null; update(); });
    $('bkUseRate').addEventListener('change', update);
    ['bkAmt', 'bkPer'].forEach(id => $(id).addEventListener('input', update));
    $('bkWhy').addEventListener('change', function () {
        $('bkWhyOther').hidden = this.value !== 'Other';
        if (!$('bkWhyOther').hidden) $('bkWhyOther').focus();
    });
    bar.querySelectorAll('[data-act]').forEach(b => b.addEventListener('click', () => open(b.dataset.act)));
    modalEl.addEventListener('shown.bs.modal', function () {
        const first = act === 'bonus' ? $('bkAmt') : act === 'labor' ? $('bkLaborType') : $('bkSeg').querySelector('button');
        if (first) first.focus();
    });

    // ── Save, and the way back ──────────────────────────────────────────────
    const post = (url, body) => fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
        body: JSON.stringify(body),
    }).then(res => res.json().catch(() => ({})).then(data => ({ ok: res.ok, data: data })));
    // What the server said was wrong, in its own words.
    const said = data => (data.errors && Object.values(data.errors)[0] && Object.values(data.errors)[0][0]) || data.message;
    // The lists re-read themselves; nothing is reloaded, so the Undo stays up.
    const reread = () => { if (window.Live && Live.refresh) Live.refresh(); else location.reload(); };

    $('bkConfirm').addEventListener('click', async function () {
        if (sending) return;

        const fail = function (message) { $('bkErr').textContent = message; $('bkErr').hidden = false; };
        $('bkErr').hidden = true;

        const body = { ids: people.map(p => Number(p.id)) };

        if (act === 'bonus') {
            const why = $('bkWhy').value === 'Other' ? $('bkWhyOther').value.trim() : $('bkWhy').value;
            if (!why) { fail('Please describe the reason.'); $('bkWhyOther').focus(); return; }
            body.amount = $('bkAmt').value;
            body.reason = why;
            body.period = $('bkPer').value;
        } else {
            const on = $('bkEff').value;
            if (!on || on < cfg.today) { fail('Pick today or a later date. Days already worked are not changed.'); return; }
            body.effective_on = on;
            if (act === 'site')  body.site_id = choice === '' ? null : Number(choice);
            if (act === 'shift') body.shift_id = Number(choice);
            if (act === 'labor') { body.labor_type_id = Number(choice); body.use_rate = $('bkUseRate').checked; }
            if (act === 'overtime') body.allow = choice === '1';
        }

        sending = true;
        update();

        try {
            const { ok, data } = await post(cfg.urls[act], body);
            if (ok && data.success) {
                if ('overtime_rule' in data) cfg.overtimeRule = data.overtime_rule;
                modal().hide();
                document.dispatchEvent(new CustomEvent('rmx:select-done'));
                reread();
                Notify.success(data.message, {
                    timeout: 10000,
                    action: { label: @json(__('Undo')), run: () => undo(data.batch) },
                });
                return;
            }
            fail(said(data) || 'Could not complete that action.');
        } catch (err) {
            fail('Network error. Please try again.');
        } finally {
            sending = false;
            update();
        }
    });

    async function undo(batch) {
        try {
            const { ok, data } = await post(cfg.urls.undo, { batch: batch });
            if (ok && data.success) {
                if ('overtime_rule' in data) cfg.overtimeRule = data.overtime_rule;
                reread(); Notify.info(data.message); return;
            }
            Notify.error(said(data) || 'Could not undo that.');
        } catch (err) {
            Notify.error('Network error. Please try again.');
        }
    }
})();

// ── A worker, opened from their row ─────────────────────────────────────────
// A click anywhere on an Active row — but its checkbox and its own buttons —
// opens the worker in the centred dialog below: the record at a glance, and
// Full profile or Edit (the Register Employee form). Enter on the name does too.
(function () {
    // The dialog's markup comes after this script, and Bootstrap loads after
    // it too, so both are looked up when a worker is first opened.
    const modal = { show: () => bootstrap.Modal.getOrCreateInstance(document.getElementById('workerModal')).show() };
    const esc = t => String(t ?? '').replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
    const fact = (label, value, wide) => `<div class="${wide ? 'wide' : ''}"><dt>${label}</dt><dd class="${value ? '' : 'none'}">${esc(value || '—')}</dd></div>`;
    // "₱150.00 / hr" → the figure large, its unit small beside it.
    const money = (label, value) => {
        const [fig, unit] = String(value || '—').split(/\s*\/\s*/);
        return `<div><span>${label}</span><b>${esc(fig)}</b>${unit ? `<small>/ ${esc(unit)}</small>` : ''}</div>`;
    };
    const $ = id => document.getElementById(id);

    function open(row) {
        let d;
        try { d = JSON.parse(row.dataset.emp || '{}'); } catch (e) { return; }
        $('wmTitle').textContent = d.name;
        $('wmSub').innerHTML = (d.id ? `<span class="wm-id">${esc(d.id)}</span>` : '') + esc([d.role, d.site].filter(Boolean).join(' · '));
        $('wmChips').innerHTML = [
            d.fp ? `<span class="wm-chip ok"><i class="fas fa-fingerprint"></i>${esc(d.fp)}</span>`
                 : `<span class="wm-chip warn"><i class="fas fa-fingerprint"></i>No fingerprint yet</span>`,
        ].join('');
        $('wmPay').innerHTML =
            money('Rate', d.rate) + money('Daily', d.daily) +
            `<div><span>Logs</span><b>${esc(String(d.logs ?? 0))}</b><small>scans</small></div>`;
        const group = (title, rows) => `<section class="wm-group"><h6>${title}</h6><dl class="wm-list">${rows}</dl></section>`;
        $('wmFacts').innerHTML =
            group('Personal',
                fact('Born', d.born) + fact('Birthplace', d.birthplace) +
                fact('Gender', d.gender) + fact('Civil status', d.civil) +
                fact('Nationality', d.nation) + fact('Blood type', d.blood)) +
            group('Contact',
                fact('Phone', d.phone) + fact('Email', d.email) + fact('Address', d.place, true)) +
            group('In case of emergency',
                fact('Contact', d.ice) + fact('Phone', d.icePhone)) +
            group('Work',
                fact('Hired', d.hired) + fact('Shift', d.shift) + fact('Employment type', d.hiredAs, !d.overtime) + (d.overtime ? fact('Overtime', d.overtime) : ''));
        $('wmProfile').href = d.profile;
        $('wmEdit').href = d.edit;
        modal.show();
    }

    document.addEventListener('click', e => {
        const row = e.target.closest('tr.rmx-row-card[data-emp]');
        if (!row) return;
        // The checkbox, bonus, edit and remove keep their own jobs. Only what is
        // inside the row counts — the whole table sits inside the bulk form.
        const inRow = sel => { const hit = e.target.closest(sel); return hit && row.contains(hit); };
        if (inRow('input, label, a, form, .rmx-actions, .rmx-check-col')) return;
        if (inRow('button') && !inRow('.rmx-open')) return;
        e.preventDefault();
        open(row);
    });
})();
</script>

{{-- ── A worker, opened from their row ───────────────────────────────────────
     Centred, in the same dress as the Leave & Advances dialogs: the navy head,
     the worker's icon, their record at a glance, and the two ways on — the
     full profile, or Edit (the Register Employee form). Filled from the row. --}}
<div class="modal fade" id="workerModal" tabindex="-1" aria-labelledby="wmTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" style="max-width:560px">
        <div class="modal-content emp-modal">
            <div class="emp-head">
                <span class="wm-av">@include('partials.worker-icon', ['size' => '60%'])</span>
                <div class="emp-head-text">
                    <h6 class="emp-head-title" id="wmTitle">{{ __('Worker') }}</h6>
                    <p class="emp-head-sub" id="wmSub"></p>
                    <div class="wm-chips" id="wmChips"></div>
                </div>
                <button type="button" class="emp-head-x" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body emp-body">
                <div class="wm-pay" id="wmPay"></div>
                <div id="wmFacts"></div>
            </div>
            <div class="emp-foot">
                <p class="emp-foot-note">{{ __('Edit opens the full employee form.') }}</p>
                <a class="emp-btn-cancel" id="wmProfile" href="#"><i class="ti ti-id" aria-hidden="true"></i>{{ __('Full profile') }}</a>
                <a class="emp-btn-save" id="wmEdit" href="#"><i class="fas fa-pen" aria-hidden="true"></i>{{ __('Edit') }}</a>
            </div>
        </div>
    </div>
</div>
@endsection
