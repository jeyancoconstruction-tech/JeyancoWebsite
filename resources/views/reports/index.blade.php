@extends('layouts')

@section('page_title', 'Payroll Reports')

{{-- Payroll Reports, a sub-item of Payroll Records. Every figure is
     PayrollService's, as Payroll Records computes it (see
     PayrollReportController); colours are the theme's tokens.

     UI revision (2026-10-19): one summary strip instead of tiles, plain tabs,
     one filter line, no chart card — a share is a bar in its own row — and a
     row opens a side panel with the worker's pay, overtime, deductions and
     site. Print / PDF previews the very page that prints (reports/print). --}}

@push('styles')
<style>
.rp { --rp-mono: 'JetBrains Mono', ui-monospace, Consolas, monospace; display: flex; flex-direction: column; gap: 10px; }
.rp, #rpPanel, #dtrModal { --rp-late: #c2570c; --rp-ot: #1d5fd0; --rp-bar: #2a78d6; --rp-bar2: #94a3b8; }
html[data-bs-theme="dark"] .rp, html[data-bs-theme="dark"] #rpPanel, html[data-bs-theme="dark"] #dtrModal { --rp-late: #f59e42; --rp-ot: #6aa7f5; --rp-bar: #3987e5; --rp-bar2: #64748b; }
html[data-bs-theme] .main-content .rp > .page-head { margin-bottom: -2px !important; }

.rp-card { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; }
.rp-btn {
    display: inline-flex; align-items: center; gap: 8px; height: 36px; padding: 0 14px; border-radius: 9px;
    border: 1px solid var(--border-md); background: var(--surface); color: var(--text-primary);
    font-size: 13px; font-weight: 600; white-space: nowrap; text-decoration: none; cursor: pointer;
}
.rp-btn svg { width: 16px; height: 16px; flex: none; }
.rp-btn:hover { border-color: var(--brand); color: var(--text-primary); }
.rp-btn.pri { background: var(--brand); border-color: var(--brand); color: #fff; }
.rp-btn.pri:hover { color: #fff; filter: brightness(1.06); }

/* Report tabs: words on a line, the one open underlined. */
.rp-tabs { display: flex; gap: 2px; overflow-x: auto; border-bottom: 1px solid var(--border); scrollbar-width: none; }
.rp-tabs::-webkit-scrollbar { display: none; }
.rp-tab { position: relative; display: flex; align-items: center; color: var(--text-secondary); font-size: 13px; font-weight: 600; padding: 0 12px; height: 38px; white-space: nowrap; text-decoration: none; }
.rp-tab:hover { color: var(--text-primary); }
.rp-tab.on { color: var(--brand); }
.rp-tab.on::after { content: ""; position: absolute; left: 10px; right: 10px; bottom: -1px; height: 2px; border-radius: 2px; background: var(--brand); }

/* Filters: one line. */
.rp-filters { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
.rp-chips { display: flex; gap: 2px; background: var(--bg-subtle); border: 1px solid var(--border); border-radius: 9px; padding: 2px; }
.rp-chips a { display: inline-flex; align-items: center; color: var(--text-secondary); font-size: 12.5px; font-weight: 600; height: 28px; padding: 0 12px; border-radius: 7px; white-space: nowrap; text-decoration: none; }
.rp-chips a:hover { color: var(--text-primary); }
.rp-chips a.on { background: var(--surface); color: var(--text-primary); box-shadow: 0 1px 2px rgba(0,0,0,.10); }
.rp-period { display: flex; align-items: center; gap: 4px; }
.rp-step { display: inline-grid; place-items: center; width: 32px; height: 32px; border: 1px solid var(--border-md); border-radius: 9px; background: var(--surface); color: var(--text-primary); text-decoration: none; }
.rp-step svg { width: 15px; height: 15px; }
.rp-step:hover { border-color: var(--brand); color: var(--text-primary); }
.rp-step.off { opacity: .4; pointer-events: none; }
.rp-step .lbl { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); }
.rp-when { min-width: 150px; padding: 0 6px; text-align: center; font-size: 13.5px; font-weight: 700; color: var(--text-primary); white-space: nowrap; font-variant-numeric: tabular-nums; }
.rp-sel { display: flex; align-items: center; gap: 7px; border: 1px solid var(--border-md); background: var(--surface); border-radius: 9px; padding: 0 10px; height: 32px; margin: 0; }
.rp-sel svg { width: 15px; height: 15px; color: var(--text-muted); flex: none; }
html[data-bs-theme] .rp .rp-sel input,
html[data-bs-theme] .rp .rp-sel select {
    border: 0 !important; background: transparent !important; box-shadow: none !important; outline: none;
    height: 30px; padding: 0; color: var(--text-primary); font-size: 13px; font-weight: 500; min-width: 0;
}
html[data-bs-theme] .rp .rp-sel input.q { width: 180px; }
html[data-bs-theme] .rp .rp-sel select { width: 130px; }
html[data-bs-theme] .rp .rp-sel select option { background: var(--surface); color: var(--text-primary); }
.rp-filters .sp { flex: 1; }
.rp-lnk { color: var(--text-secondary); font-size: 12.5px; font-weight: 600; padding: 0 4px; text-decoration: none; }
.rp-lnk:hover { color: var(--brand); }

/* The summary strip: the figures on one line, a hairline between them. */
.rp-kpis { display: flex; flex-wrap: wrap; background: var(--surface); border: 1px solid var(--border); border-radius: 12px; }
.rp-kpi { flex: 1 1 130px; padding: 9px 16px; display: flex; flex-direction: column; gap: 1px; min-width: 0; border-left: 1px solid var(--border); }
.rp-kpi:first-child { border-left: 0; }
.rp-kpi span { font-size: 11.5px; font-weight: 600; color: var(--text-secondary); }
.rp-kpi b { font-size: 17px; font-weight: 700; font-variant-numeric: tabular-nums; color: var(--text-primary); white-space: nowrap; }
.rp-kpi b.net { color: var(--brand); }
.rp-kpi b.late { color: var(--rp-late); }
.rp-kpi b.ot { color: var(--rp-ot); }
.rp-kpi b.bad { color: var(--danger); }
.rp-kpi small { font-size: 11px; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.rp-kpi small .up { color: var(--success); font-weight: 700; }
.rp-kpi small .down { color: var(--danger); font-weight: 700; }

/* Table */
.rp-tcard { overflow: hidden; }
.rp-t-h { display: flex; align-items: baseline; gap: 10px; padding: 10px 14px; border-bottom: 1px solid var(--border); }
.rp-t-h h3 { margin: 0; font-size: 14px; font-weight: 700; color: var(--text-primary); }
.rp-t-h small { color: var(--text-muted); font-size: 12px; }
.rp-t-h .rp-t-hint { margin-left: auto; }
.rp-wrap { overflow: auto; }
.rp-table { width: 100%; border-collapse: separate; border-spacing: 0; min-width: 640px; }
.rp-table thead th { position: sticky; top: 0; z-index: 1; font-size: 11.5px; color: var(--text-secondary); font-weight: 600; text-align: right; padding: 8px 12px; background: var(--surface); border-bottom: 1px solid var(--border-md); white-space: nowrap; }
.rp-table th.l, .rp-table td.l { text-align: left; }
.rp-table thead th button { all: unset; cursor: pointer; display: inline-flex; gap: 4px; align-items: center; }
.rp-table thead th button:focus-visible { outline: 2px solid var(--brand); outline-offset: 2px; }
.rp-table thead th svg { width: 10px; height: 10px; opacity: 0; transition: opacity .12s; }
.rp-table thead th:hover svg { opacity: .45; }
.rp-table thead th.sorted svg { opacity: 1; color: var(--brand); }
.rp-table tbody td { padding: 8px 12px; border-bottom: 1px solid var(--border); text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; font-size: 13px; color: var(--text-primary); }
.rp-table tbody tr:hover td { background: var(--bg-subtle); }
.rp-table td.z { color: var(--text-muted); }
.rp-table td.ded { color: var(--danger); }
.rp-table td.net { font-weight: 700; }
.rp-table td.code { font-family: var(--rp-mono); font-weight: 700; }
.rp-table td.strong { font-weight: 600; }
.rp-table td.tone-late { color: var(--rp-late); }
.rp-table td.tone-ot { color: var(--rp-ot); }
.rp-table td.tone-bad { color: var(--danger); }
.rp-table tbody td[class*="tone-"] { font-weight: 700; }
.rp-table tfoot td { position: sticky; bottom: 0; background: var(--bg-subtle); font-weight: 700; padding: 9px 12px; text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; border-top: 1px solid var(--border-md); color: var(--text-primary); }
.rp-table tfoot td.l { text-align: left; font-size: 12px; color: var(--text-secondary); }
.rp-table tfoot td.ded { color: var(--danger); }
.rp-table tfoot td.tone-ot { color: var(--rp-ot); }
.rp-table tfoot td.tone-late { color: var(--rp-late); }
.rp-table tfoot td.tone-bad { color: var(--danger); }
.rp-table tbody tr[data-dtr], .rp-table tbody tr[data-emp], .rp-table tbody tr[data-site] { cursor: pointer; }
.rp-table tbody tr[data-dtr]:focus-visible td, .rp-table tbody tr[data-emp]:focus-visible td, .rp-table tbody tr[data-site]:focus-visible td { background: var(--brand-subtle); outline: none; }
.rp-table tbody tr.is-open td { background: var(--brand-subtle); }
.rp-empty { padding: 36px 16px; text-align: center; color: var(--text-muted); font-size: 13px; }

/* Who: the name, then the code, trade and site — no coloured badges. */
.rp-emp b { display: block; font-size: 13px; font-weight: 600; }
.rp-emp small { display: block; color: var(--text-muted); font-size: 11.5px; }
.rp-pair b { display: block; font-size: 13px; font-weight: 500; }
.rp-pair small { display: block; color: var(--text-muted); font-size: 11.5px; }

/* A share as a bar in its row. */
.rp-share { display: inline-flex; align-items: center; gap: 8px; justify-content: flex-end; }
.rp-share .tr { width: 84px; height: 6px; border-radius: 3px; background: var(--bg-subtle); overflow: hidden; display: flex; }
.rp-share .tr i { display: block; height: 100%; background: var(--rp-bar); }
.rp-share .tr i + i { background: var(--rp-bar2); }
.rp-share span { min-width: 44px; text-align: right; }

/* Status of an advance. */
.rp-st { display: inline-flex; align-items: center; font-size: 12px; font-weight: 600; border-radius: 6px; padding: 2px 8px; }
.rp-st.paid { background: var(--success-soft); color: var(--success); }
.rp-st.open { background: var(--warning-soft); color: var(--warning); }

/* The DTR's week: seven squares. */
.rp-week { display: inline-flex; gap: 3px; vertical-align: middle; }
.rp-week i { width: 18px; height: 18px; border-radius: 4px; display: grid; place-items: center; font-style: normal; font-size: 9.5px; font-weight: 700; color: var(--text-muted); background: var(--bg-subtle); border: 1px solid var(--border); }
.rp-week i.ok { background: var(--success-soft); color: var(--success); border-color: transparent; }
.rp-week i.abs { background: var(--danger-soft); color: var(--danger); border-color: transparent; }
.rp-week i.miss { background: var(--warning-soft); color: var(--warning); border-color: transparent; }
.rp-week i.live { background: var(--brand-subtle); color: var(--brand); border-color: transparent; }
.rp-week i.off { background: transparent; border-style: dashed; }

/* ── The side panel ───────────────────────────────────────────────────── */
html[data-bs-theme] #rpPanel { width: min(420px, 100vw); background: var(--surface); color: var(--text-primary); border-left: 1px solid var(--border); }
.rpp-head { padding: 16px 18px 14px; border-bottom: 1px solid var(--border); display: flex; gap: 12px; align-items: flex-start; }
.rpp-head .who { flex: 1; min-width: 0; }
.rpp-head b { display: block; font-size: 16px; font-weight: 700; }
.rpp-head small { display: block; color: var(--text-muted); font-size: 12px; margin-top: 2px; }
.rpp-x { all: unset; box-sizing: border-box; width: 32px; height: 32px; display: grid; place-items: center; border-radius: 8px; border: 1px solid var(--border-md); cursor: pointer; color: var(--text-secondary); }
.rpp-x:hover { color: var(--text-primary); border-color: var(--brand); }
.rpp-x svg { width: 15px; height: 15px; }
.rpp-net { display: flex; justify-content: space-between; align-items: baseline; padding: 14px 18px; border-bottom: 1px solid var(--border); }
.rpp-net span { font-size: 12px; color: var(--text-secondary); font-weight: 600; }
.rpp-net b { font-size: 22px; font-weight: 800; color: var(--brand); font-variant-numeric: tabular-nums; }
.rpp-body { padding: 4px 18px 18px; overflow-y: auto; }
.rpp-sec { padding: 12px 0; border-bottom: 1px solid var(--border); }
.rpp-sec:last-child { border-bottom: 0; }
.rpp-sec h4 { margin: 0 0 8px; font-size: 12px; font-weight: 700; color: var(--text-secondary); display: flex; align-items: center; gap: 8px; }
.rpp-sec h4 em { font-style: normal; font-size: 10.5px; font-weight: 700; color: var(--brand); background: var(--brand-subtle); border-radius: 5px; padding: 1px 6px; }
.rpp-sec ul { list-style: none; margin: 0; padding: 0; }
.rpp-sec li { display: flex; justify-content: space-between; gap: 12px; padding: 4px 0; font-size: 13px; }
.rpp-sec li span { color: var(--text-secondary); }
.rpp-sec li b { font-weight: 600; font-variant-numeric: tabular-nums; text-align: right; }
.rpp-sec li.tot { border-top: 1px solid var(--border); margin-top: 4px; padding-top: 7px; }
.rpp-sec li.tot b, .rpp-sec li.tot span { font-weight: 700; color: var(--text-primary); }
.rpp-sec li .neg { color: var(--danger); }
.rpp-sec li .ot { color: var(--rp-ot); }
.rpp-sec .none { color: var(--text-muted); font-size: 12.5px; }
.rpp-acts { display: flex; gap: 6px; flex-wrap: wrap; padding: 12px 18px; border-top: 1px solid var(--border); }
.rpp-acts .rp-btn { height: 32px; font-size: 12.5px; padding: 0 11px; }

/* ── The paper preview ───────────────────────────────────────────────── */
#rpPaperModal .modal-dialog { max-width: min(1000px, calc(100% - 32px)); }
html[data-bs-theme] #rpPaperModal .modal-content { background: var(--surface); border: 1px solid var(--border); border-radius: 14px; overflow: hidden; }
.rpv-bar { display: flex; align-items: center; gap: 10px; padding: 10px 14px; border-bottom: 1px solid var(--border); }
.rpv-bar b { font-size: 14px; }
.rpv-bar small { color: var(--text-muted); font-size: 12px; }
.rpv-bar .sp { flex: 1; }
.rpv-frame { display: block; width: 100%; height: min(76vh, 900px); border: 0; background: #e5e7eb; }

/* ── A worker's DTR: a pop-up in the middle, the page behind it blurred ─ */
body.dtr-open .modal-backdrop { --bs-backdrop-bg: rgba(8, 14, 28, .42); --bs-backdrop-opacity: 1;
    -webkit-backdrop-filter: blur(5px); backdrop-filter: blur(5px); }
#dtrModal .dtr-dialog { max-width: min(940px, calc(100% - 32px)); margin-left: auto; margin-right: auto; }
html[data-bs-theme] #dtrModal .modal-content.dtr-pop { background: var(--surface); border: 1px solid var(--border); border-radius: 14px; overflow: hidden; box-shadow: 0 24px 60px rgba(0,0,0,.28); }
.dtr-bar { display: flex; align-items: center; gap: 10px; padding: 10px 14px; border-bottom: 1px solid var(--border); background: var(--bg-subtle); flex-wrap: wrap; }
.dtr-who { display: flex; align-items: center; gap: 10px; min-width: 0; flex: 1 1 180px; }
.dtr-who b { display: block; font-size: 14px; font-weight: 700; color: var(--text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.dtr-who small { display: block; font-size: 11.5px; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.dtr-av { width: 32px; height: 32px; border-radius: 8px; display: grid; place-items: center; flex: none; font-weight: 700; font-size: 12px; color: var(--text-secondary); background: var(--surface); border: 1px solid var(--border-md); }
.dtr-acts { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.dtr-acts .rp-btn { height: 32px; padding: 0 11px; font-size: 12.5px; }
.dtr-nav { display: inline-flex; align-items: center; gap: 2px; border: 1px solid var(--border-md); border-radius: 9px; background: var(--surface); height: 32px; padding: 0 2px; }
.dtr-nav span { min-width: 52px; text-align: center; font-size: 12px; font-weight: 600; color: var(--text-secondary); font-variant-numeric: tabular-nums; }
.dtr-ico { all: unset; box-sizing: border-box; width: 28px; height: 28px; display: grid; place-items: center; border-radius: 8px; color: var(--text-secondary); cursor: pointer; }
.dtr-ico svg { width: 16px; height: 16px; }
.dtr-ico:hover { background: var(--bg-subtle); color: var(--text-primary); }
.dtr-ico:focus-visible { outline: 2px solid var(--brand); outline-offset: 1px; }
.dtr-ico:disabled { opacity: .35; cursor: default; background: none; }
.dtr-x { width: 32px; height: 32px; border: 1px solid var(--border-md); background: var(--surface); }
html[data-bs-theme] #dtrModal .dtr-body { padding: 18px 20px 20px; background: var(--surface); transition: opacity .12s; }
#dtrModal .dtr-body.is-loading { opacity: .45; }
.dtr-msg { padding: 48px 16px; text-align: center; color: var(--text-muted); font-size: 13px; }

/* The page itself. */
.dtr-sheet { color: var(--text-primary); font-size: 12.5px; }
.dtr-sheet table { width: 100%; border-collapse: collapse; }
.dtr-top td { padding: 0 0 12px; vertical-align: bottom; }
.dtr-top td.r { text-align: right; }
.dtr-co { font-size: 17px; font-weight: 800; letter-spacing: .01em; color: var(--text-primary); }
.dtr-addr { font-size: 11px; color: var(--text-muted); }
.dtr-doc { margin-top: 2px; font-size: 10.5px; letter-spacing: .16em; text-transform: uppercase; font-weight: 700; color: var(--text-secondary); }
.dtr-k { font-size: 10px; letter-spacing: .1em; text-transform: uppercase; font-weight: 700; color: var(--text-secondary); }
.dtr-per { font-size: 14px; font-weight: 700; }
.dtr-info { border: 1px solid var(--border); margin-bottom: 12px; }
.dtr-info th, .dtr-info td { padding: 6px 10px; border: 1px solid var(--border); text-align: left; vertical-align: top; }
.dtr-info th { width: 15%; background: var(--bg-subtle); font-size: 10px; letter-spacing: .08em; text-transform: uppercase; font-weight: 700; color: var(--text-secondary); white-space: nowrap; }
.dtr-info td { width: 35%; }
.dtr-days th, .dtr-days td { border: 1px solid var(--border); padding: 4px 7px; text-align: center; white-space: nowrap; font-variant-numeric: tabular-nums; }
.dtr-days thead th { background: var(--bg-subtle); font-size: 10px; letter-spacing: .06em; text-transform: uppercase; font-weight: 700; color: var(--text-secondary); }
.dtr-days .l { text-align: left; }
.dtr-days .rem { white-space: normal; }
.dtr-days td.date span { color: var(--text-muted); font-size: 11px; margin-left: 4px; }
.dtr-days td.miss { color: var(--danger); font-weight: 700; font-size: 11.5px; }
.dtr-days td.late { color: var(--rp-late); font-weight: 700; }
.dtr-days td.ot { color: var(--rp-ot); font-weight: 700; }
.dtr-days td.z { color: var(--text-muted); }
.dtr-days tr.is-off td, .dtr-days tr.is-none td { background: color-mix(in srgb, var(--bg-subtle) 60%, transparent); }
.dtr-rem { font-weight: 700; }
.dtr-rem.good { color: var(--success); }
.dtr-rem.bad { color: var(--danger); }
.dtr-rem.mute { color: var(--text-secondary); font-weight: 600; }
.dtr-rem.live { color: var(--brand); }
.dtr-days td small { color: var(--text-muted); font-size: 11px; margin-left: 4px; }
.dtr-days tr.total td { background: var(--bg-subtle); font-weight: 800; border-top: 2px solid var(--border-md); }
.dtr-days tr.total td.l { font-size: 10.5px; letter-spacing: .06em; text-transform: uppercase; color: var(--text-secondary); }
.dtr-days tr.total td.rem { letter-spacing: 0; text-transform: none; font-size: 11.5px; }
.dtr-note { margin: 6px 0 0; font-size: 11px; color: var(--text-muted); }
.dtr-sign { margin-top: 38px; }
.dtr-sign td { width: 42%; padding: 0; text-align: center; vertical-align: top; }
.dtr-sign td.gap { width: 16%; }
.dtr-sign .line { border-top: 1px solid var(--text-primary); margin-bottom: 4px; }
.dtr-sign b { display: block; font-size: 12.5px; min-height: 18px; }
.dtr-sign span { display: block; font-size: 10.5px; letter-spacing: .08em; text-transform: uppercase; color: var(--text-secondary); font-weight: 700; }
.dtr-gen { margin-top: 14px; font-size: 10.5px; color: var(--text-muted); }

/* Ctrl+P on the page itself: with a worker's DTR open, that page prints;
   otherwise the report alone. Print / PDF uses reports/print instead. */
@media print {
    body.dtr-open .rp, body.dtr-open .modal-backdrop, body.dtr-open .dtr-bar { display: none !important; }
    body.dtr-open #dtrModal { position: static !important; display: block !important; overflow: visible !important; }
    body.dtr-open #dtrModal .modal-dialog { display: block !important; max-width: none !important; height: auto !important; min-height: 0 !important; margin: 0 !important; transform: none !important; }
    body.dtr-open #dtrModal .modal-content, body.dtr-open #dtrModal .dtr-body { border: 0 !important; box-shadow: none !important; overflow: visible !important; max-height: none !important; background: #fff !important; padding: 0 !important; }
    body.dtr-open .dtr-sheet { --text-primary: #111; --text-secondary: #444; --text-muted: #666; --border: #b8b8b8; --border-md: #8a8a8a; --bg-subtle: #f1f3f5; --surface: #fff; --rp-late: #b45309; --rp-ot: #1d4ed8; --danger: #b91c1c; --success: #15803d; --brand: #1d4ed8; }

    .sidebar, .topbar, .rp-tabs, .rp-filters, .page-head-actions, .chatbot-fab, .chatbot-window, #rpPanel, .offcanvas-backdrop { display: none !important; }
    .main-content { margin: 0 !important; padding: 0 !important; }
    .rp-card, .rp-kpis { box-shadow: none !important; break-inside: avoid; }
    .rp-wrap { overflow: visible !important; }
    .rp-table thead th, .rp-table tfoot td { position: static !important; }
    .rp-print-head { display: block !important; }
}
.rp-print-head { display: none; font-size: 12px; color: var(--text-secondary); margin-top: -8px; }
@media (max-width: 760px) {
    .rp-kpi { flex-basis: 45%; border-left: 0; border-top: 1px solid var(--border); }
    html[data-bs-theme] .rp .rp-sel input.q { width: 140px; }
}
</style>
@endpush

@section('content')
@php
    use App\Http\Controllers\PayrollReportController as R;

    $isDtr   = $report === 'dtr';
    $panel   = $panel ?? [];
    $filters = array_filter(['site' => $siteId, 'q' => $q !== '' ? $q : null], fn ($v) => $v !== null && $v !== '');
    // The week or the month on screen follows to every tab, with the site
    // and the search.
    $keepQuery = $filters + ['span' => $period['span'], 'at' => $period['at']];
    $step = fn (array $to) => route('payroll-reports.index', $filters + ['report' => $report] + $to);

    $svg = fn ($path, $w = '1.8') => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="' . $w . '" aria-hidden="true">' . $path . '</svg>';
    $arrow = $svg('<path d="M7 10l5-5 5 5M7 14l5 5 5-5"/>', '2.4');
    // By the pay week a month is the weeks that end in it, so the dates are
    // written out beside the report's name.
    $periodText = $isDtr ? $period['label'] : \App\Services\DtrService::label($fromC, $toC);

    // Zero reads as nothing: a blank cell, so the figures that are there
    // stand out. The Excel export still writes R::fmt's dash.
    $isZero = fn ($val) => ! is_array($val) && is_numeric($val) && (float) $val == 0.0;

    // A cell: the text is R::fmt's, the same the Excel export writes.
    $cell = function (array $col, $val, array $row) use ($isZero) {
        [$key, , $type] = $col;
        $txt = e(R::fmt($type, $val));
        if (in_array($type, ['money0', 'net0', 'dur0', 'int0'], true) && $isZero($val)) {
            return '';
        }
        $share = function (float $pct, ?float $rest = null) use ($txt) {
            $pct = max(0, min(100, $pct));
            return '<span class="rp-share"><span class="tr"><i style="width:' . round($pct, 2) . '%"></i></span><span>' . $txt . '</span></span>';
        };
        return match (true) {
            $type === 'emp'    => '<div class="rp-emp"><b>' . e($val['t']) . '</b><small>' . e($val['sub']) . '</small></div>',
            $type === 'pair'   => '<div class="rp-pair"><b>' . e($val['top']) . '</b><small>' . e($val['sub']) . '</small></div>',
            $type === 'status' => '<span class="rp-st ' . e($val['c']) . '">' . $txt . '</span>',
            $type === 'week'   => '<span class="rp-week" role="img" aria-label="' . e($val['t']) . '">'
                                . implode('', array_map(fn ($d) => '<i class="' . e($d['c']) . '" title="' . e($d['n']) . '">' . e($d['d']) . '</i>', $val['days'] ?? []))
                                . '</span>',
            $type === 'pct'    => $share((float) $val),
            // What of an advance has been collected, beside the amount.
            $key === 'paid' && isset($row['principal']) && (float) $row['principal'] > 0
                               => $share((float) $val / (float) $row['principal'] * 100),
            default            => $txt,
        };
    };
    $cls = function (array $col, $val) use ($isZero) {
        $type = $col[2];
        $zero = $isZero($val);
        return trim(implode(' ', array_filter([
            ! empty($col[3]) ? 'l' : '',
            in_array($type, ['net', 'net0'], true) && ! $zero ? 'net' : '',
            $type === 'minus' && ! $zero ? 'ded' : '',
            $type === 'code' ? 'code' : '',
            $type === 'strong' ? 'strong' : '',
            in_array($type, ['money0', 'net0', 'dur0', 'int0'], true) && $zero ? 'z' : '',
            // The DTR's colours: only a figure that is there is coloured.
            ! empty($col[5]) && is_numeric($val) && ! $zero ? 'tone-' . $col[5] : '',
        ])));
    };
    $sortVal = fn ($val) => is_array($val) ? ($val['v'] ?? '') : $val;
    $paperUrl = route('payroll-reports.print', array_merge($keepQuery, ['report' => $report]));
@endphp

<div class="rp" id="reportList" data-live="payroll attendance leave advances settings">

    <x-page-header :title="__('Payroll Reports')">
        <x-slot:actions>
            <a class="rp-btn" href="{{ route('payroll-reports.export', array_merge($keepQuery, ['report' => $report])) }}">
                {!! $svg('<path d="M14 3H7a2 2 0 00-2 2v14a2 2 0 002 2h10a2 2 0 002-2V8z"/><path d="M14 3v5h5M9 13l6 5M15 13l-6 5"/>') !!} {{ __('Export Excel') }}
            </a>
            <button type="button" class="rp-btn pri" id="rpPrint" data-paper="{{ $paperUrl }}">
                {!! $svg('<path d="M6 9V3h12v6M6 18H4v-7h16v7h-2M8 14h8v7H8z"/>') !!} {{ __('Print / PDF') }}
            </button>
        </x-slot:actions>
    </x-page-header>
    <div class="rp-print-head">{{ $reports[$report] }} · {{ $periodText }}</div>

    <nav class="rp-tabs" aria-label="{{ __('Report') }}">
        @foreach($reports as $k => $t)
            <a class="rp-tab {{ $k === $report ? 'on' : '' }}" href="{{ route('payroll-reports.index', array_merge($keepQuery, ['report' => $k])) }}" @if($k === $report) aria-current="page" @endif>{{ __($t) }}</a>
        @endforeach
    </nav>

    <form class="rp-filters" method="GET" action="{{ route('payroll-reports.index') }}" id="rpFilters" aria-label="{{ __('Filters') }}">
        <input type="hidden" name="report" value="{{ $report }}">
        {{-- The period, the same on every tab: Weekly or Monthly, then back,
             which one, and on. No date fields. It rides along in the form so
             a site or a name keeps the period. --}}
        <input type="hidden" name="span" value="{{ $period['span'] }}">
        <input type="hidden" name="at" value="{{ $period['at'] }}">
        <div class="rp-chips" role="group" aria-label="{{ __('Period') }}">
            <a href="{{ $step($period['week']) }}" class="{{ $period['span'] === 'week' ? 'on' : '' }}" @if($period['span'] === 'week') aria-current="true" @endif>{{ __('Weekly') }}</a>
            <a href="{{ $step($period['month']) }}" class="{{ $period['span'] === 'month' ? 'on' : '' }}" @if($period['span'] === 'month') aria-current="true" @endif>{{ __('Monthly') }}</a>
        </div>
        <div class="rp-period">
            <a class="rp-step" href="{{ $step($period['prev']) }}" id="rpPrev" title="{{ __('Previous') }}">{!! $svg('<path d="M15 6l-6 6 6 6"/>', '2.4') !!}<span class="lbl">{{ __('Previous') }}</span></a>
            <span class="rp-when" id="rpWhen" aria-live="polite" @if(! $isDtr && $period['span'] === 'month') title="{{ __('The pay weeks that end in this month') }}" @endif>{{ $period['label'] }}</span>
            @if($period['next'])
                <a class="rp-step" href="{{ $step($period['next']) }}" id="rpNext" title="{{ __('Next') }}">{!! $svg('<path d="M9 6l6 6-6 6"/>', '2.4') !!}<span class="lbl">{{ __('Next') }}</span></a>
            @else
                <span class="rp-step off" id="rpNext" aria-disabled="true" title="{{ __('Nothing after this yet') }}">{!! $svg('<path d="M9 6l6 6-6 6"/>', '2.4') !!}<span class="lbl">{{ __('Next') }}</span></span>
            @endif
        </div>
        <span class="sp"></span>
        <label class="rp-sel">
            {!! $svg('<path d="M12 22s7-6.5 7-12a7 7 0 10-14 0c0 5.5 7 12 7 12z"/>', '2') !!}
            <select name="site" aria-label="{{ __('Site') }}" id="rpSite">
                <option value="">{{ __('All sites') }}</option>
                @foreach($sites as $s)
                    <option value="{{ $s->id }}" @selected($siteId === $s->id)>{{ $s->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="rp-sel">
            {!! $svg('<circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/>', '2') !!}
            <input class="q" type="search" name="q" value="{{ $q }}" placeholder="{{ __('Employee name or ID') }}" aria-label="{{ __('Employee') }}" id="rpQ">
        </label>
        @if($filters)<a class="rp-lnk" href="{{ route('payroll-reports.index', ['report' => $report, 'span' => $period['span'], 'at' => $period['at']]) }}">{{ __('Clear') }}</a>@endif
    </form>

    <section class="rp-kpis {{ count($kpis) === 7 ? 'is-7' : '' }}" aria-label="{{ __('Totals') }}">
        @foreach($kpis as [$label, $value, $sub, $delta, $kcls])
            <div class="rp-kpi">
                <span>{{ __($label) }}</span>
                <b class="{{ $kcls }}">{{ $value }}</b>
                <small title="{{ $sub }}">
                    @if($sub !== null)
                        {{ $sub }}
                    @elseif($delta !== null)
                        <span class="{{ $delta >= 0 ? 'up' : 'down' }}">{{ $delta >= 0 ? '↑' : '↓' }} {{ number_format(abs($delta), 1) }}%</span> {{ __('vs previous') }}
                    @else
                        {{ __('No earlier period') }}
                    @endif
                </small>
            </div>
        @endforeach
    </section>

    {{-- Boxed to the screen like Payroll Records' list: the rows scroll under
         their column heads, and the totals stay on the card's bottom edge
         however few rows there are. --}}
    <section class="rp-card rp-tcard" aria-label="{{ __($table['title']) }}" data-fill-screen>
        <div class="rp-t-h">
            <h3>{{ __($reports[$report]) }}</h3><small>{{ $periodText }}</small>
            @if($table['rows'])
                <small class="rp-t-hint">{{ $isDtr ? __('Click an employee to open the DTR') : ($report === 'site' ? __('Click a site to see its crew') : __('Click an employee for pay, OT and deductions')) }}</small>
            @endif
        </div>
        <div class="rp-wrap" data-fill-scroll>
            <table class="rp-table" id="rpTable">
                <thead>
                    <tr>
                        @foreach($table['cols'] as $i => $col)
                            <th class="{{ ! empty($col[3]) ? 'l' : '' }}" aria-sort="none">
                                <button type="button" data-sort="{{ $i }}">{{ __($col[1]) }}{!! $arrow !!}</button>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse($table['rows'] as $row)
                        @php
                            $empId = $row['employee']['id'] ?? null;
                            $name  = $row['employee']['t'] ?? '';
                        @endphp
                        @if($isDtr)
                        <tr data-dtr="{{ $row['_id'] }}" tabindex="0" role="button" aria-label="{{ __('Open the DTR of :name', ['name' => $name]) }}">
                        @elseif($report === 'site' && ! empty($row['_site']))
                        <tr data-site="{{ route('payroll-reports.index', ['report' => 'employee', 'site' => $row['_site'], 'span' => $period['span'], 'at' => $period['at']]) }}" tabindex="0" role="link" aria-label="{{ __('See the crew of :site', ['site' => $row['site']['t']]) }}">
                        @elseif($empId && isset($panel[$empId]))
                        <tr data-emp="{{ $empId }}" tabindex="0" role="button" aria-label="{{ __('Show the pay of :name', ['name' => $name]) }}">
                        @else
                        <tr>
                        @endif
                            @foreach($table['cols'] as $col)
                                @php $val = $row[$col[0]] ?? null; @endphp
                                <td class="{{ $cls($col, $val) }}" data-v="{{ $sortVal($val) }}">{!! $cell($col, $val, $row) !!}</td>
                            @endforeach
                        </tr>
                    @empty
                        <tr><td class="rp-empty" colspan="{{ count($table['cols']) }}">{{ $isDtr ? __('No employees for these filters.') : __('No records for these filters.') }}</td></tr>
                    @endforelse
                </tbody>
                @if($table['rows'])
                    <tbody data-fill-gap aria-hidden="true"><tr><td colspan="{{ count($table['cols']) }}"></td></tr></tbody>
                    <tfoot>
                        <tr>
                            @foreach($table['foot'] as $i => $f)
                                @php
                                    $col   = $table['cols'][$i];
                                    $blank = $i > 0 && in_array($col[2], ['dur0', 'int0'], true) && $isZero($f);
                                @endphp
                                <td class="{{ $i === 0 ? 'l' : trim(($col[2] === 'minus' && (float) $f > 0 ? 'ded ' : '') . (! empty($col[5]) && (float) $f > 0 ? 'tone-' . $col[5] : '')) }}">{{ $i === 0 ? $f : ($blank ? '' : R::fmt($col[2] === 'net0' || $col[2] === 'money0' ? 'money' : $col[2], $f)) }}</td>
                            @endforeach
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </section>

    {{-- What the side panel reads. Inside the live region, so a refresh
         brings the figures with the rows. --}}
    @unless($isDtr)
        <script type="application/json" id="rpPanelData">@json($panel)</script>
    @endunless
</div>
@include('modules._fill_screen')

@unless($isDtr)
{{-- A worker's figures, from a row. --}}
<div class="offcanvas offcanvas-end" tabindex="-1" id="rpPanel" aria-labelledby="rppName">
    <div class="rpp-head">
        <div class="who"><b id="rppName"></b><small id="rppMeta"></small></div>
        <button type="button" class="rpp-x" data-bs-dismiss="offcanvas" aria-label="{{ __('Close') }}">{!! $svg('<path d="M6 6l12 12M18 6L6 18"/>', '2.4') !!}</button>
    </div>
    <div class="rpp-net"><span>{{ __('Net pay') }} · {{ $periodText }}</span><b id="rppNet"></b></div>
    <div class="rpp-body" id="rppBody"></div>
    <div class="rpp-acts">
        <a class="rp-btn" id="rppDtr" href="#">{{ __('Open DTR') }}</a>
        <a class="rp-btn" id="rppSlip" href="#" target="_blank" rel="noopener">{{ __('Payslip') }}</a>
        <a class="rp-btn" id="rppProfile" href="#">{{ __('Employee profile') }}</a>
    </div>
</div>
@endunless

{{-- Print / PDF: the paper itself, in a frame — what is seen is what prints. --}}
<div class="modal fade" id="rpPaperModal" tabindex="-1" aria-labelledby="rpvTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="rpv-bar">
                <div><b id="rpvTitle">{{ __($reports[$report]) }}</b> <small>{{ $periodText }}</small></div>
                <span class="sp"></span>
                <button type="button" class="rp-btn" data-bs-dismiss="modal">{{ __('Close') }}</button>
                <button type="button" class="rp-btn pri" id="rpvPrint">{!! $svg('<path d="M6 9V3h12v6M6 18H4v-7h16v7h-2M8 14h8v7H8z"/>') !!} {{ __('Print / Save as PDF') }}</button>
            </div>
            <iframe class="rpv-frame" id="rpvFrame" title="{{ __('Print preview') }}"></iframe>
        </div>
    </div>
</div>

@if($isDtr)
{{-- A worker's DTR. Outside the live region, so a refresh of the list never
     redraws it; the page inside is reports/_dtr_sheet, fetched per worker. --}}
<div class="modal fade" id="dtrModal" tabindex="-1" aria-labelledby="dtrTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable dtr-dialog">
        <div class="modal-content dtr-pop">
            <div class="dtr-bar">
                <div class="dtr-who">
                    <span class="dtr-av" id="dtrAv"></span>
                    <div style="min-width:0"><b id="dtrTitle"></b><small id="dtrSub"></small></div>
                </div>
                <div class="dtr-acts">
                    <a class="rp-btn" id="dtrPdf" href="#">{!! $svg('<path d="M12 4v11M7 11l5 5 5-5M5 20h14"/>', '2') !!}{{ __('Download PDF') }}</a>
                    <a class="rp-btn" id="dtrXls" href="#">{!! $svg('<path d="M14 3H7a2 2 0 00-2 2v14a2 2 0 002 2h10a2 2 0 002-2V8z"/><path d="M14 3v5h5M9 13l6 5M15 13l-6 5"/>') !!}{{ __('Download Excel') }}</a>
                    <span class="dtr-nav">
                        <button type="button" class="dtr-ico" id="dtrPrev" aria-label="{{ __('Previous employee') }}" title="{{ __('Previous employee') }}">{!! $svg('<path d="M15 6l-6 6 6 6"/>', '2.4') !!}</button>
                        <span id="dtrPos"></span>
                        <button type="button" class="dtr-ico" id="dtrNext" aria-label="{{ __('Next employee') }}" title="{{ __('Next employee') }}">{!! $svg('<path d="M9 6l6 6-6 6"/>', '2.4') !!}</button>
                    </span>
                    <button type="button" class="dtr-ico dtr-x" data-bs-dismiss="modal" aria-label="{{ __('Close') }}" title="{{ __('Close') }}">{!! $svg('<path d="M6 6l12 12M18 6L6 18"/>', '2.4') !!}</button>
                </div>
            </div>
            <div class="modal-body dtr-body" id="dtrBody" tabindex="0"></div>
        </div>
    </div>
</div>
@endif
@endsection

@push('scripts')
<script>
(function () {
    // The filters apply as they change: a site, a name.
    const form = document.getElementById('rpFilters');
    if (form) {
        const go = () => form.requestSubmit ? form.requestSubmit() : form.submit();
        const site = document.getElementById('rpSite');
        site && site.addEventListener('change', go);
        const q = document.getElementById('rpQ');
        let t;
        q && q.addEventListener('input', () => { clearTimeout(t); t = setTimeout(go, 600); });
    }

    // Sort by any column; again to reverse. The list is a live region: when
    // it is redrawn the rows come back in the server's order, so the order
    // asked for is put back — the DTR's arrows step through it.
    const table = () => document.getElementById('rpTable');
    let sorted = null, dir = -1;
    const order = () => {
        const t = table();
        if (sorted === null || !t) return;
        const i = sorted, body = t.tBodies[0];
        const rows = [...body.rows].filter(r => r.cells.length > 1);
        const num = s => /^-?\d+(\.\d+)?$/.test(s);
        rows.sort((a, c) => {
            const x = a.cells[i].dataset.v || '', y = c.cells[i].dataset.v || '';
            return (num(x) && num(y) ? parseFloat(x) - parseFloat(y) : x.localeCompare(y)) * dir;
        });
        rows.forEach(r => body.appendChild(r));
        t.querySelectorAll('thead th').forEach((th, j) => {
            th.classList.toggle('sorted', j === i);
            th.setAttribute('aria-sort', j === i ? (dir > 0 ? 'ascending' : 'descending') : 'none');
        });
    };
    document.addEventListener('click', e => {
        const b = e.target.closest && e.target.closest('#rpTable thead [data-sort]');
        if (!b) return;
        const i = +b.dataset.sort;
        dir = sorted === i ? -dir : -1;
        sorted = i;
        order();
    });
    document.addEventListener('live:updated', order);

    // A site's row opens its crew on Payroll by Employee.
    const goSite = row => { window.location.href = row.dataset.site; };
    document.addEventListener('click', e => {
        const row = e.target.closest && e.target.closest('#rpTable tbody tr[data-site]');
        if (row) goSite(row);
    });
    document.addEventListener('keydown', e => {
        if (e.key === 'Enter' && e.target.matches && e.target.matches('#rpTable tbody tr[data-site]')) goSite(e.target);
    });

    // Print / PDF: the paper in a frame; its own print prints it.
    const pm = document.getElementById('rpPaperModal'), frame = document.getElementById('rpvFrame');
    document.addEventListener('click', e => {
        const b = e.target.closest && e.target.closest('#rpPrint');
        if (!b) return;
        frame.src = b.dataset.paper;
        bootstrap.Modal.getOrCreateInstance(pm).show();
    });
    document.getElementById('rpvPrint').addEventListener('click', () => {
        try { frame.contentWindow.focus(); frame.contentWindow.print(); }
        catch (err) { window.open(frame.src, '_blank', 'noopener'); }
    });
    pm.addEventListener('hidden.bs.modal', () => { frame.removeAttribute('src'); });
})();
</script>
@unless($isDtr)
@php
    $jsLinks = [
        'report'  => $report,
        'dtr'     => route('payroll-reports.index', ['report' => 'dtr', 'span' => $period['span'], 'at' => $period['at']]),
        'slip'    => route('payslip.batch', ['from' => $from, 'to' => $to, 'employee' => 0]),
        'profile' => route('employees.edit', ['employee' => 0]),
    ];
@endphp
<script>
// A worker's pay, overtime and deductions, from a row — the section the open
// report is about first. The figures are the same sums the row shows.
(function () {
    const el = document.getElementById('rpPanel');
    if (!el) return;
    const L = @json($jsLinks);
    const $ = id => document.getElementById(id);
    const fmt = new Intl.NumberFormat('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const peso = n => '₱' + fmt.format(Number(n) || 0);
    const esc = t => String(t ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const dur = m => { m = Math.round(Number(m) || 0); return Math.floor(m / 60) + 'h ' + String(m % 60).padStart(2, '0') + 'm'; };
    const data = () => { try { return JSON.parse($('rpPanelData').textContent || '{}'); } catch (e) { return {}; } };
    let openRow = null;

    const li = (k, v, c) => '<li><span>' + esc(k) + '</span><b class="' + (c || '') + '">' + v + '</b></li>';
    const tot = (k, v, c) => '<li class="tot"><span>' + esc(k) + '</span><b class="' + (c || '') + '">' + v + '</b></li>';
    const sec = (title, inner, mine) => '<section class="rpp-sec"><h4>' + esc(title) + (mine ? ' <em>This report</em>' : '') + '</h4>' + inner + '</section>';

    function sections(w) {
        const p = w.pay;
        const none = '<div class="none">Nothing this period.</div>';
        const out = {};

        out.pay = p ? '<ul>'
            + li('Days worked', esc(p.workdays)) + li('Hours', dur(p.minutes))
            + li('Gross pay', peso(p.gross)) + li('Deductions', '−' + peso(p.ded), 'neg')
            + tot('Net pay', peso(p.net)) + '</ul>' : none;

        out.ot = p && p.otMin > 0 ? '<ul>'
            + li('Regular hours', dur(Math.max(0, p.minutes - p.otMin)))
            + li('Overtime hours', dur(p.otMin), 'ot')
            + tot('Overtime pay', peso(p.overtime)) + '</ul>' : '<div class="none">No overtime this period.</div>';

        if (p) {
            const kinds = [['SSS', p.sss], ['PhilHealth', p.ph], ['Pag-IBIG', p.pagibig], ['Withholding tax', p.tax],
                           ['Cash advance', p.advance], ['Vale', p.vale], ['Other', p.other]].filter(k => Number(k[1]) > 0);
            out.ded = kinds.length ? '<ul>' + kinds.map(k => li(k[0], '−' + peso(k[1]), 'neg')).join('')
                + tot('Total deductions', '−' + peso(p.ded), 'neg') + '</ul>' : '<div class="none">No deductions this period.</div>';

            const earn = [['Regular pay', p.regular, true], ['Paid leave', p.leave], ['Overtime', p.overtime],
                          ['Holiday, rest day & night', Math.max(0, p.premiums - p.overtime)], ['Bonus', p.bonus]]
                .filter(k => k[2] || Number(k[1]) > 0);
            out.earn = '<ul>' + earn.map(k => li(k[0], peso(k[1]))).join('') + tot('Gross pay', peso(p.gross)) + '</ul>';
        } else {
            out.ded = none;
            out.earn = none;
        }

        out.adv = w.owed > 0 ? '<ul>' + li('Advances still open', esc(w.loans)) + tot('Balance owed', peso(w.owed)) + '</ul>'
                             : '<div class="none">No cash advance owed.</div>';

        out.where = '<ul>' + li('Site', esc(w.site)) + (w.shift ? li('Shift', esc(w.shift)) : '')
            + (w.trade ? li('Trade', esc(w.trade)) : '') + '</ul>';
        return out;
    }

    const ORDER = {
        employee:   ['pay', 'earn', 'ded', 'ot', 'adv', 'where'],
        overtime:   ['ot', 'pay', 'earn', 'ded', 'adv', 'where'],
        deductions: ['ded', 'pay', 'earn', 'ot', 'adv', 'where'],
        advances:   ['adv', 'ded', 'pay', 'earn', 'ot', 'where'],
    };
    const TITLE = { pay: 'Pay this period', earn: 'Earnings', ded: 'Deductions', ot: 'Overtime', adv: 'Cash advance', where: 'Site & section' };

    function show(row) {
        const id = row.dataset.emp, w = data()[id];
        if (!w) return;
        const s = sections(w), order = ORDER[L.report] || ORDER.employee;

        $('rppName').textContent = w.name;
        $('rppMeta').textContent = [w.code, w.trade, w.site].filter(Boolean).join(' · ');
        $('rppNet').textContent = w.pay ? peso(w.pay.net) : '—';
        $('rppBody').innerHTML = order.map((k, i) => sec(TITLE[k], s[k], i === 0)).join('');
        $('rppDtr').href = L.dtr + '&q=' + encodeURIComponent(w.code.replace('#', ''));
        $('rppSlip').href = L.slip.replace(/employee=0\b/, 'employee=' + encodeURIComponent(id));
        $('rppSlip').hidden = !w.pay;
        $('rppProfile').href = L.profile.replace(/\/0\/edit/, '/' + encodeURIComponent(id) + '/edit');

        if (openRow) openRow.classList.remove('is-open');
        openRow = row;
        row.classList.add('is-open');
        bootstrap.Offcanvas.getOrCreateInstance(el).show();
    }

    document.addEventListener('click', e => {
        const row = e.target.closest && e.target.closest('#rpTable tbody tr[data-emp]');
        if (row) show(row);
    });
    document.addEventListener('keydown', e => {
        if ((e.key === 'Enter' || e.key === ' ') && e.target.matches && e.target.matches('#rpTable tbody tr[data-emp]')) {
            e.preventDefault();
            show(e.target);
        }
    });
    el.addEventListener('hidden.bs.offcanvas', () => { if (openRow) openRow.classList.remove('is-open'); openRow = null; });
})();
</script>
@endunless
@if($isDtr)
@php
    // One worker's page and its two downloads, for the period on screen. The
    // 0 stands where the script writes the worker's id.
    $dtrFor   = ['employee' => 0, 'span' => $period['span'], 'at' => $period['at']];
    $dtrLinks = [
        'sheet'  => route('payroll-reports.dtr', $dtrFor),
        'pdf'    => route('payroll-reports.dtr.pdf', $dtrFor),
        'xls'    => route('payroll-reports.dtr.excel', $dtrFor),
        'period' => $period['label'],
    ];
@endphp
<script>
// A worker's DTR: the pop-up, the page inside it, and stepping through the
// list in the order it is sorted. Rows are found when asked for, never kept:
// the list is a live region and is redrawn under this script.
(function () {
    const el = document.getElementById('dtrModal');
    if (!el) return;

    const links = @json($dtrLinks);
    const says  = { loading: @json(__('Loading the DTR…')), failed: @json(__('This DTR could not be loaded. Close it and try again.')) };
    const $     = id => document.getElementById(id);
    const body  = $('dtrBody'), prev = $('dtrPrev'), next = $('dtrNext');
    const of    = (link, id) => link.replace(/\/dtr\/0(?=[\/?]|$)/, '/dtr/' + encodeURIComponent(id));
    const rows  = () => [...document.querySelectorAll('#rpTable tbody tr[data-dtr]')];
    const kept  = new Map();   // pages already read, for as long as the pop-up is open
    let current = null, asked = 0;

    function say(text) {
        const note = document.createElement('div');
        note.className = 'dtr-msg';
        note.textContent = text;
        body.replaceChildren(note);
    }

    function open(id) {
        const list = rows(), i = list.findIndex(r => r.dataset.dtr === String(id));
        if (i < 0) return false;

        current = String(id);
        const mine = ++asked;
        const row = list[i], name = row.querySelector('.rp-emp b'), code = row.querySelector('.rp-emp small');
        const full = name ? name.textContent.trim() : '';
        const init = full.split(/\s+/).filter(Boolean).slice(0, 2).map(w => w[0]).join('').toUpperCase();

        $('dtrTitle').textContent = full;
        $('dtrSub').textContent = [code ? code.textContent : '', links.period].filter(Boolean).join(' · ');
        $('dtrAv').textContent = init;
        $('dtrPdf').href = of(links.pdf, current);
        $('dtrXls').href = of(links.xls, current);
        $('dtrPos').textContent = (i + 1) + ' / ' + list.length;
        prev.disabled = i === 0;
        next.disabled = i === list.length - 1;

        if (kept.has(current)) {
            body.innerHTML = kept.get(current);
            body.classList.remove('is-loading');
            body.scrollTop = 0;
            return true;
        }

        // The page before stays, dimmed, until the next one is read.
        body.classList.add('is-loading');
        if (!body.firstElementChild) say(says.loading);

        fetch(of(links.sheet, current), { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' } })
            .then(r => { if (!r.ok) throw new Error(r.status); return r.text(); })
            .then(html => {
                kept.set(String(id), html);
                if (mine !== asked) return;
                body.innerHTML = html;
                body.scrollTop = 0;
            })
            .catch(() => { if (mine === asked) say(says.failed); })
            .finally(() => { if (mine === asked) body.classList.remove('is-loading'); });

        return true;
    }

    function step(by) {
        const list = rows(), to = list[list.findIndex(r => r.dataset.dtr === current) + by];
        if (to) open(to.dataset.dtr);
    }

    const show = id => { if (open(id)) bootstrap.Modal.getOrCreateInstance(el).show(); };

    document.addEventListener('click', e => {
        const row = e.target.closest && e.target.closest('#rpTable tbody tr[data-dtr]');
        if (row) show(row.dataset.dtr);
    });
    document.addEventListener('keydown', e => {
        if ((e.key === 'Enter' || e.key === ' ') && e.target.matches && e.target.matches('#rpTable tbody tr[data-dtr]')) {
            e.preventDefault();
            show(e.target.dataset.dtr);
        }
    });

    prev.addEventListener('click', () => step(-1));
    next.addEventListener('click', () => step(1));
    el.addEventListener('keydown', e => {
        if (e.key === 'ArrowLeft') step(-1);
        if (e.key === 'ArrowRight') step(1);
    });

    el.addEventListener('show.bs.modal', () => document.body.classList.add('dtr-open'));
    el.addEventListener('hidden.bs.modal', () => {
        document.body.classList.remove('dtr-open');
        asked++;
        kept.clear();
        body.replaceChildren();
    });
})();
</script>
@endif
@endpush
