@extends('layouts')

@section('page_title', 'Payroll Reports')

{{-- Payroll Reports, a sub-item of Payroll Records. Laid out after Michael's
     jeyanco-payroll-reports.html (2026-09-26): report tabs, the period, the
     totals, a chart and a sortable table. Every figure is PayrollService's,
     as Payroll Records computes it (see PayrollReportController); colours are
     the theme's tokens. --}}

@push('styles')
<style>
.rp { --rp-mono: 'JetBrains Mono', ui-monospace, Consolas, monospace; display: flex; flex-direction: column; gap: 10px; }
.rp { --rp-s1: #2a78d6; --rp-s2: #eb6834; --rp-s3: #1baf7a; --rp-s4: #eda100; --rp-s5: #7c3aed; --rp-s6: #db2777; --rp-s7: #64748b; }
html[data-bs-theme="dark"] .rp { --rp-s1: #3987e5; --rp-s2: #d95926; --rp-s3: #199e70; --rp-s4: #c98500; --rp-s5: #8b5cf6; --rp-s6: #ec4899; --rp-s7: #94a3b8; }
html[data-bs-theme] .main-content .rp > .page-head { margin-bottom: -2px !important; }

.rp-card { background: var(--surface); border: 1px solid var(--border); border-radius: 14px; box-shadow: var(--shadow-xs); }
.rp-btn {
    display: inline-flex; align-items: center; gap: 8px; height: 38px; padding: 0 14px; border-radius: 10px;
    border: 1px solid var(--border-md); background: var(--surface); color: var(--text-primary);
    font-size: 13px; font-weight: 600; white-space: nowrap; text-decoration: none; cursor: pointer;
}
.rp-btn svg { width: 16px; height: 16px; flex: none; }
.rp-btn:hover { border-color: var(--brand); color: var(--text-primary); }
.rp-btn.pri { background: var(--brand); border-color: var(--brand); color: #fff; }
.rp-btn.pri:hover { color: #fff; filter: brightness(1.06); }

/* Report tabs */
.rp-tabs { display: flex; gap: 6px; overflow-x: auto; padding: 4px; background: var(--surface); border: 1px solid var(--border); border-radius: 12px; scrollbar-width: none; }
.rp-tabs::-webkit-scrollbar { display: none; }
.rp-tab { display: flex; gap: 8px; align-items: center; color: var(--text-secondary); font-size: 12.5px; font-weight: 600; padding: 0 12px; height: 32px; border-radius: 9px; white-space: nowrap; text-decoration: none; }
.rp-tab svg { width: 16px; height: 16px; }
.rp-tab:hover { color: var(--text-primary); background: var(--bg-subtle); }
.rp-tab.on { background: var(--brand-subtle); color: var(--brand); }

/* Filters */
.rp-filters { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; padding: 8px 12px; }
.rp-chips { display: flex; gap: 4px; background: var(--bg-subtle); border: 1px solid var(--border); border-radius: 10px; padding: 3px; }
.rp-chips button { border: 0; background: none; color: var(--text-secondary); font-size: 12px; font-weight: 600; height: 26px; padding: 0 10px; border-radius: 7px; cursor: pointer; white-space: nowrap; }
.rp-chips button:hover { color: var(--text-primary); }
.rp-chips button.on { background: var(--surface); color: var(--text-primary); box-shadow: 0 1px 2px rgba(0,0,0,.12); }
.rp-sel { display: flex; align-items: center; gap: 8px; border: 1px solid var(--border-md); background: var(--surface); border-radius: 10px; padding: 0 10px; height: 32px; margin: 0; }
.rp-sel svg { width: 15px; height: 15px; color: var(--text-muted); flex: none; }
html[data-bs-theme] .rp .rp-sel input,
html[data-bs-theme] .rp .rp-sel select {
    border: 0 !important; background: transparent !important; box-shadow: none !important; outline: none;
    height: 34px; padding: 0; color: var(--text-primary); font-size: 13px; font-weight: 500; min-width: 0;
}
html[data-bs-theme] .rp .rp-sel input[type=date] { width: 114px; font-family: var(--rp-mono); font-size: 12.5px; }
html[data-bs-theme] .rp .rp-sel input.q { width: 140px; }
html[data-bs-theme] .rp .rp-sel select { width: 120px; }
/* The open list, which the browser draws white behind a see-through field. */
html[data-bs-theme] .rp .rp-sel select option { background: var(--surface); color: var(--text-primary); }
.rp-sel .dash { color: var(--text-muted); }
.rp-filters .sp { flex: 1; }
.rp-lnk { color: var(--brand); font-size: 13px; font-weight: 600; padding: 0 6px; text-decoration: none; }
.rp-lnk:hover { text-decoration: underline; }

/* Totals */
.rp-kpis { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: 1px; background: var(--border); border: 1px solid var(--border); border-radius: 14px; overflow: hidden; box-shadow: var(--shadow-xs); }
.rp-kpi { background: var(--surface); padding: 7px 12px; display: flex; flex-direction: column; gap: 1px; min-width: 0; }
.rp-kpi span { font-size: 10px; letter-spacing: .1em; text-transform: uppercase; font-weight: 700; color: var(--text-secondary); }
.rp-kpi b { font-size: 16px; font-weight: 700; font-variant-numeric: tabular-nums; color: var(--text-primary); white-space: nowrap; }
.rp-kpi b.net { color: var(--brand); }
.rp-kpi small { font-size: 10.5px; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.rp-kpi small .up { color: var(--success); font-weight: 700; }
.rp-kpi small .down { color: var(--danger); font-weight: 700; }

/* Chart */
.rp-chart { padding: 10px 14px; display: flex; flex-direction: column; gap: 8px; }
.rp-ch-h { display: flex; justify-content: space-between; align-items: baseline; gap: 10px; flex-wrap: wrap; }
.rp-ch-h h3 { margin: 0; font-size: 14px; font-weight: 700; color: var(--text-primary); }
.rp-ch-h small { color: var(--text-muted); font-size: 12px; }
.rp-legend { display: flex; gap: 12px; flex-wrap: wrap; font-size: 12px; color: var(--text-secondary); }
.rp-legend span { display: flex; gap: 6px; align-items: center; }
.rp-legend i { width: 10px; height: 10px; border-radius: 3px; }
.rp-hbars { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px 36px; }
@media (max-width: 1100px) { .rp-hbars { grid-template-columns: minmax(0, 1fr); } }
.rp-hb { display: grid; grid-template-columns: minmax(0, 130px) minmax(0, 1fr) auto; gap: 10px; align-items: center; font-size: 12.5px; }
.rp-hb .nm { color: var(--text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.rp-hb .tr { height: 14px; display: flex; gap: 2px; }
.rp-hb .tr i { display: block; height: 100%; min-width: 2px; }
.rp-hb .tr i:last-child { border-radius: 0 4px 4px 0; }
.rp-hb .v { font-variant-numeric: tabular-nums; font-weight: 600; color: var(--text-primary); min-width: 84px; text-align: right; white-space: nowrap; }
.rp-empty { padding: 36px 16px; text-align: center; color: var(--text-muted); font-size: 13px; }
.rp-tip { position: fixed; z-index: 1200; pointer-events: none; background: var(--text-primary); color: var(--surface); font-size: 12px; font-weight: 600; padding: 6px 9px; border-radius: 7px; white-space: nowrap; transform: translate(-50%, calc(-100% - 10px)); box-shadow: 0 6px 18px rgba(0,0,0,.25); }

/* Table */
.rp-tcard { overflow: hidden; }
.rp-t-h { display: flex; align-items: center; gap: 10px; padding: 9px 14px; border-bottom: 1px solid var(--border); }
.rp-t-h h3 { margin: 0; font-size: 14px; font-weight: 700; color: var(--text-primary); }
.rp-t-h small { color: var(--text-muted); font-size: 12px; }
.rp-wrap { overflow: auto; }
.rp-table { width: 100%; border-collapse: separate; border-spacing: 0; min-width: 640px; }
.rp-table thead th { position: sticky; top: 0; z-index: 1; font-size: 10.5px; letter-spacing: .06em; text-transform: uppercase; color: var(--text-secondary); font-weight: 700; text-align: right; padding: 7px 10px; background: var(--bg-subtle); border-bottom: 1px solid var(--border); white-space: nowrap; }
.rp-table th.l, .rp-table td.l { text-align: left; }
.rp-table thead th button { all: unset; cursor: pointer; display: inline-flex; gap: 4px; align-items: center; }
.rp-table thead th button:focus-visible { outline: 2px solid var(--brand); outline-offset: 2px; }
.rp-table thead th svg { width: 10px; height: 10px; opacity: .45; }
.rp-table thead th.sorted svg { opacity: 1; color: var(--brand); }
.rp-table tbody td { padding: 7px 10px; border-bottom: 1px solid var(--border); text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; font-size: 12.5px; color: var(--text-primary); }
.rp-table tbody tr:hover td { background: var(--bg-subtle); }
.rp-table td.z { color: var(--text-muted); }
.rp-table td.ded { color: var(--danger); }
.rp-table td.net { font-weight: 800; }
.rp-table td.code { font-family: var(--rp-mono); font-weight: 700; }
.rp-table td.strong { font-weight: 700; }
.rp-table tfoot td { position: sticky; bottom: 0; background: var(--bg-subtle); font-weight: 800; padding: 8px 10px; text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; border-top: 1px solid var(--border-md); color: var(--text-primary); }
.rp-table tfoot td.l { text-align: left; font-size: 11px; letter-spacing: .08em; text-transform: uppercase; color: var(--text-secondary); }
.rp-table tfoot td.ded { color: var(--danger); }
.rp-emp { display: flex; gap: 9px; align-items: center; }
.rp-av { width: 28px; height: 28px; border-radius: 50%; display: grid; place-items: center; font-weight: 700; font-size: 11px; color: #fff; flex: none; }
.rp-emp b { font-size: 13px; font-weight: 600; }
.rp-emp small { display: block; color: var(--text-muted); font-size: 11px; }
.rp-st { display: inline-flex; gap: 6px; align-items: center; font-size: 11.5px; font-weight: 700; border-radius: 999px; padding: 3px 9px; }
.rp-st i { width: 6px; height: 6px; border-radius: 50%; }
.rp-st.paid { background: var(--success-soft); color: var(--success); } .rp-st.paid i { background: var(--success); }
.rp-st.open { background: var(--warning-soft); color: var(--warning); } .rp-st.open i { background: var(--warning); }
.rp-sw { display: inline-block; width: 8px; height: 8px; border-radius: 2px; margin-right: 6px; vertical-align: middle; }

/* ── Employees DTR ────────────────────────────────────────────────────────
   Late is orange, overtime blue, an absence or a missing scan red — on the
   list, in the totals above it and on a worker's page. */
.rp, #dtrModal { --rp-late: #c2570c; --rp-ot: #1d5fd0; }
html[data-bs-theme="dark"] .rp, html[data-bs-theme="dark"] #dtrModal { --rp-late: #f59e42; --rp-ot: #6aa7f5; }
.rp-kpis.is-7 { grid-template-columns: repeat(7, minmax(0, 1fr)); }
.rp-kpi b.late, .rp-table td.tone-late { color: var(--rp-late); }
.rp-kpi b.ot, .rp-table td.tone-ot { color: var(--rp-ot); }
.rp-kpi b.bad, .rp-table td.tone-bad { color: var(--danger); }
.rp-table tbody td[class*="tone-"] { font-weight: 700; }
.rp-pair b { display: block; font-size: 12.5px; font-weight: 600; }
.rp-pair small { display: block; color: var(--text-muted); font-size: 11px; }
.rp-table tbody tr[data-dtr] { cursor: pointer; }
.rp-t-h .rp-t-hint { margin-left: auto; }
.rp-table tbody tr[data-dtr]:focus-visible td { background: var(--brand-subtle); outline: none; }

/* The period: Weekly or Monthly, then back, which one, and on. */
.rp-chips a { display: inline-flex; align-items: center; color: var(--text-secondary); font-size: 12px; font-weight: 600; height: 26px; padding: 0 12px; border-radius: 7px; white-space: nowrap; text-decoration: none; }
.rp-chips a:hover { color: var(--text-primary); }
.rp-chips a.on { background: var(--surface); color: var(--text-primary); box-shadow: 0 1px 2px rgba(0,0,0,.12); }
.rp-period { display: flex; align-items: center; gap: 6px; }
.rp-step { display: inline-flex; align-items: center; gap: 4px; height: 32px; padding: 0 10px; border: 1px solid var(--border-md); border-radius: 10px; background: var(--surface); color: var(--text-primary); font-size: 12.5px; font-weight: 600; white-space: nowrap; text-decoration: none; }
.rp-step svg { width: 14px; height: 14px; flex: none; }
.rp-step:hover { border-color: var(--brand); color: var(--text-primary); }
.rp-step.off { opacity: .45; pointer-events: none; }
.rp-when { min-width: 132px; padding: 0 4px; text-align: center; font-size: 13.5px; font-weight: 700; color: var(--text-primary); white-space: nowrap; font-variant-numeric: tabular-nums; }

/* A worker's DTR: a pop-up in the middle, the page behind it blurred. Scoped
   to the body class the script sets, so no other dialog's backdrop changes. */
body.dtr-open .modal-backdrop { --bs-backdrop-bg: rgba(8, 14, 28, .42); --bs-backdrop-opacity: 1;
    -webkit-backdrop-filter: blur(5px); backdrop-filter: blur(5px); }
#dtrModal .dtr-dialog { max-width: min(940px, calc(100% - 32px)); margin-left: auto; margin-right: auto; }
html[data-bs-theme] #dtrModal .modal-content.dtr-pop { background: var(--surface); border: 1px solid var(--border); border-radius: 16px; overflow: hidden; box-shadow: 0 24px 60px rgba(0,0,0,.28); }
.dtr-bar { display: flex; align-items: center; gap: 10px; padding: 10px 14px; border-bottom: 1px solid var(--border); background: var(--bg-subtle); flex-wrap: wrap; }
.dtr-who { display: flex; align-items: center; gap: 10px; min-width: 0; flex: 1 1 180px; }
.dtr-who b { display: block; font-size: 14px; font-weight: 700; color: var(--text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.dtr-who small { display: block; font-size: 11.5px; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.dtr-acts { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.dtr-acts .rp-btn { height: 32px; padding: 0 11px; font-size: 12.5px; }
.dtr-nav { display: inline-flex; align-items: center; gap: 2px; border: 1px solid var(--border-md); border-radius: 10px; background: var(--surface); height: 32px; padding: 0 2px; }
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

/* Print / PDF: the report alone, on paper. */
@media print {
    /* With a worker's DTR open, that page is what prints. */
    body.dtr-open .rp, body.dtr-open .modal-backdrop, body.dtr-open .dtr-bar { display: none !important; }
    body.dtr-open #dtrModal { position: static !important; display: block !important; overflow: visible !important; }
    body.dtr-open #dtrModal .modal-dialog { display: block !important; max-width: none !important; height: auto !important; min-height: 0 !important; margin: 0 !important; transform: none !important; }
    body.dtr-open #dtrModal .modal-content, body.dtr-open #dtrModal .dtr-body { border: 0 !important; box-shadow: none !important; overflow: visible !important; max-height: none !important; background: #fff !important; padding: 0 !important; }
    body.dtr-open .dtr-sheet { --text-primary: #111; --text-secondary: #444; --text-muted: #666; --border: #b8b8b8; --border-md: #8a8a8a; --bg-subtle: #f1f3f5; --surface: #fff; --rp-late: #b45309; --rp-ot: #1d4ed8; --danger: #b91c1c; --success: #15803d; --brand: #1d4ed8; }

    .sidebar, .topbar, .rp-tabs, .rp-filters, .page-head-actions, .chatbot-fab, .chatbot-window, .rp-tip { display: none !important; }
    .main-content { margin: 0 !important; padding: 0 !important; }
    .rp-card, .rp-kpis { box-shadow: none !important; break-inside: avoid; }
    .rp-wrap { overflow: visible !important; }
    .rp-table thead th, .rp-table tfoot td { position: static !important; }
    .rp-print-head { display: block !important; }
}
.rp-print-head { display: none; font-size: 12px; color: var(--text-secondary); margin-top: -8px; }
</style>
@endpush

@section('content')
@php
    use App\Http\Controllers\PayrollReportController as R;

    $isDtr   = $report === 'dtr';
    $filters = array_filter(['site' => $siteId, 'q' => $q !== '' ? $q : null], fn ($v) => $v !== null && $v !== '');
    $custom  = ($isDtr ? $dtr['span'] === 'custom' : $preset === 'custom') ? ['from' => $from, 'to' => $to] : [];
    $keepQuery = $filters + ($custom ?: ($isDtr ? ['span' => $dtr['span'], 'at' => $from] : ['preset' => $preset]));
    // Another tab keeps the site, the search and a range picked by hand. A
    // preset is the payroll reports' and a week or a month the DTR's, so
    // neither follows across.
    $tabQuery = fn (string $k) => ($k === 'dtr') === $isDtr ? $keepQuery : $filters + $custom;
    $url = fn (array $with) => route('payroll-reports.index', array_merge($keepQuery, ['report' => $report], $with));
    $dtrUrl = fn (array $with) => route('payroll-reports.index', $filters + ['report' => 'dtr'] + $with);

    $icons = [
        'dtr'        => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4M8.5 15.5l2.2 2.2 4.8-4.8"/>',
        'employee'   => '<circle cx="9" cy="8" r="4"/><path d="M2 21c0-4 3-6 7-6s7 2 7 6M16 4a4 4 0 010 8M22 21c0-3-2-5-4-5.5"/>',
        'site'       => '<path d="M12 22s7-6.5 7-12a7 7 0 10-14 0c0 5.5 7 12 7 12z"/><circle cx="12" cy="10" r="2.5"/>',
        'overtime'   => '<circle cx="12" cy="13" r="8"/><path d="M12 9v4l2.5 2M9 2h6"/>',
        'deductions' => '<path d="M5 12h14"/><circle cx="12" cy="12" r="9"/>',
        'advances'   => '<rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><path d="M6 12h.01M18 12h.01"/>',
    ];
    $svg = fn ($path, $w = '1.8') => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="' . $w . '" aria-hidden="true">' . $path . '</svg>';
    $arrow = $svg('<path d="M7 10l5-5 5 5M7 14l5 5 5-5"/>', '2.4');
    $periodText = $isDtr ? $dtr['label'] : $fromC->format('M j') . ' – ' . $toC->format('M j, Y');

    // A cell: the text is R::fmt's, the same the Excel export writes.
    $cell = function (array $col, $val) {
        [$key, , $type] = $col;
        $txt = e(R::fmt($type, $val));
        return match ($type) {
            'emp'    => '<div class="rp-emp"><span class="rp-av" style="background:' . e($val['c']) . '">' . e($val['init']) . '</span><div><b>' . e($val['t']) . '</b><small>' . e($val['sub']) . '</small></div></div>',
            'pair'   => '<div class="rp-pair"><b>' . e($val['top']) . '</b><small>' . e($val['sub']) . '</small></div>',
            'status' => '<span class="rp-st ' . e($val['c']) . '"><i></i>' . $txt . '</span>',
            default  => $txt,
        };
    };
    $cls = function (array $col, $val) {
        $type = $col[2];
        $zero = ! is_array($val) && is_numeric($val) && (float) $val == 0.0;
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
@endphp

<div class="rp" id="reportList" data-live="payroll attendance leave advances settings">

    <x-page-header :title="__('Payroll Reports')">
        <x-slot:actions>
            <a class="rp-btn" href="{{ route('payroll-reports.export', array_merge($keepQuery, ['report' => $report])) }}">
                {!! $svg('<path d="M14 3H7a2 2 0 00-2 2v14a2 2 0 002 2h10a2 2 0 002-2V8z"/><path d="M14 3v5h5M9 13l6 5M15 13l-6 5"/>') !!} {{ __('Export Excel') }}
            </a>
            <button type="button" class="rp-btn pri" onclick="window.print()">
                {!! $svg('<path d="M6 9V3h12v6M6 18H4v-7h16v7h-2M8 14h8v7H8z"/>') !!} {{ __('Print / PDF') }}
            </button>
        </x-slot:actions>
    </x-page-header>
    <div class="rp-print-head">{{ $reports[$report] }} · {{ $periodText }}</div>

    <nav class="rp-tabs" aria-label="{{ __('Report') }}">
        @foreach($reports as $k => $t)
            <a class="rp-tab {{ $k === $report ? 'on' : '' }}" href="{{ route('payroll-reports.index', array_merge($tabQuery($k), ['report' => $k])) }}" @if($k === $report) aria-current="page" @endif>
                {!! $svg($icons[$k]) !!}{{ __($t) }}
            </a>
        @endforeach
    </nav>

    <form class="rp-card rp-filters" method="GET" action="{{ route('payroll-reports.index') }}" id="rpFilters" aria-label="{{ __('Filters') }}">
        <input type="hidden" name="report" value="{{ $report }}">
        @if($isDtr)
            {{-- The DTR is read by the week or the month, one step at a time.
                 The span rides along so a site or a name keeps the period;
                 a date picked by hand clears it, and those dates are used. --}}
            <input type="hidden" name="span" value="{{ $dtr['span'] !== 'custom' ? $dtr['span'] : '' }}" id="rpPreset">
            <input type="hidden" name="at" value="{{ $from }}">
            <div class="rp-chips" role="group" aria-label="{{ __('Period') }}">
                <a href="{{ $dtrUrl($dtr['week']) }}" class="{{ $dtr['span'] === 'week' ? 'on' : '' }}" @if($dtr['span'] === 'week') aria-current="true" @endif>{{ __('Weekly') }}</a>
                <a href="{{ $dtrUrl($dtr['month']) }}" class="{{ $dtr['span'] === 'month' ? 'on' : '' }}" @if($dtr['span'] === 'month') aria-current="true" @endif>{{ __('Monthly') }}</a>
            </div>
            <div class="rp-period">
                <a class="rp-step" href="{{ $dtrUrl($dtr['prev']) }}" id="rpPrev">{!! $svg('<path d="M15 6l-6 6 6 6"/>', '2.4') !!}{{ __('Previous') }}</a>
                <span class="rp-when" id="rpWhen" aria-live="polite">{{ $dtr['label'] }}</span>
                @if($dtr['next'])
                    <a class="rp-step" href="{{ $dtrUrl($dtr['next']) }}" id="rpNext">{{ __('Next') }}{!! $svg('<path d="M9 6l6 6-6 6"/>', '2.4') !!}</a>
                @else
                    <span class="rp-step off" id="rpNext" aria-disabled="true" title="{{ __('Nothing after this yet') }}">{{ __('Next') }}{!! $svg('<path d="M9 6l6 6-6 6"/>', '2.4') !!}</span>
                @endif
            </div>
        @else
            <input type="hidden" name="preset" value="{{ $preset !== 'custom' ? $preset : '' }}" id="rpPreset">
            <div class="rp-chips" role="group" aria-label="{{ __('Period') }}">
                @foreach($presets as $k => $t)
                    <button type="submit" name="preset" value="{{ $k }}" class="{{ $preset === $k ? 'on' : '' }}" @if($preset === $k) aria-pressed="true" @endif>{{ __($t) }}</button>
                @endforeach
            </div>
        @endif
        <label class="rp-sel" title="{{ $isDtr ? __('Follows the week or month above. Set both dates yourself to read any range.') : __('Whole pay weeks, from the week holding the first date to the week holding the last') }}">
            {!! $svg('<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>', '2') !!}
            <input type="date" name="from" value="{{ $from }}" aria-label="{{ __('From') }}" id="rpFrom">
            <span class="dash">–</span>
            <input type="date" name="to" value="{{ $to }}" aria-label="{{ __('To') }}" id="rpTo">
        </label>
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
        <span class="sp"></span>
        <a class="rp-lnk" href="{{ route('payroll-reports.index', ['report' => $report]) }}">{{ __('Reset') }}</a>
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
                        {{ __('vs previous') }} <span class="{{ $delta >= 0 ? 'up' : 'down' }}">{{ $delta >= 0 ? '↑' : '↓' }} {{ number_format(abs($delta), 1) }}%</span>
                    @else
                        {{ __('No earlier period to compare') }}
                    @endif
                </small>
            </div>
        @endforeach
    </section>

    {{-- The DTR has no chart: its totals, then everybody. --}}
    @if($chart)
    <section class="rp-card rp-chart" aria-label="{{ __('Chart') }}">
        <div class="rp-ch-h"><h3>{{ __($chart['title']) }}</h3><small>{{ $chart['sub'] }}</small></div>
        @if(! empty($chart['legend']))
            <div class="rp-legend">
                @foreach($chart['legend'] as [$n, $c])<span><i style="background:{{ $c }}"></i>{{ __($n) }}</span>@endforeach
            </div>
        @endif
        @if($chart['items'])
            <div class="rp-hbars">
                @foreach($chart['items'] as $x)
                    <div class="rp-hb">
                        <span class="nm" title="{{ $x['label'] }}">{{ $x['label'] }}</span>
                        <span class="tr">
                            @foreach($x['parts'] as $pt)
                                @if($pt['v'] > 0)<i style="width:{{ round($pt['v'] / $chart['max'] * 100, 3) }}%;background:{{ $pt['c'] }}" data-tip="{{ $x['label'] }} · {{ $pt['n'] }}: {{ $pt['fmt'] }}"></i>@endif
                            @endforeach
                        </span>
                        <span class="v">{{ $x['fmt'] }}</span>
                    </div>
                @endforeach
            </div>
        @else
            <div class="rp-empty">{{ __('Nothing to show for this period.') }}</div>
        @endif
    </section>
    @endif

    <section class="rp-card rp-tcard" aria-label="{{ __($table['title']) }}">
        <div class="rp-t-h"><h3>{{ __($reports[$report]) }}</h3><small>{{ $periodText }}</small>@if($isDtr && $table['rows'])<small class="rp-t-hint">{{ __('Click an employee to open the DTR') }}</small>@endif</div>
        <div class="rp-wrap">
            <table class="rp-table" id="rpTable">
                <thead>
                    <tr>
                        @foreach($table['cols'] as $i => $col)
                            <th class="{{ ! empty($col[3]) ? 'l' : '' }}" aria-sort="none">
                                <button type="button" data-sort="{{ $i }}">@if(! empty($col[4]))<span class="rp-sw" style="background:{{ $col[4] }}"></span>@endif{{ __($col[1]) }}{!! $arrow !!}</button>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse($table['rows'] as $row)
                        <tr @if($isDtr) data-dtr="{{ $row['_id'] }}" tabindex="0" role="button" aria-label="{{ __('Open the DTR of :name', ['name' => $row['employee']['t']]) }}" @endif>
                            @foreach($table['cols'] as $col)
                                @php $val = $row[$col[0]] ?? null; @endphp
                                <td class="{{ $cls($col, $val) }}" data-v="{{ $sortVal($val) }}">{!! $cell($col, $val) !!}</td>
                            @endforeach
                        </tr>
                    @empty
                        <tr><td class="rp-empty" colspan="{{ count($table['cols']) }}">{{ $isDtr ? __('No employees for these filters.') : __('No records for these filters.') }}</td></tr>
                    @endforelse
                </tbody>
                @if($table['rows'])
                    <tfoot>
                        <tr>
                            @foreach($table['foot'] as $i => $f)
                                @php $col = $table['cols'][$i]; @endphp
                                <td class="{{ $i === 0 ? 'l' : trim(($col[2] === 'minus' && (float) $f > 0 ? 'ded ' : '') . (! empty($col[5]) && (float) $f > 0 ? 'tone-' . $col[5] : '')) }}">{{ $i === 0 ? $f : R::fmt($col[2] === 'net0' || $col[2] === 'money0' ? 'money' : $col[2], $f) }}</td>
                            @endforeach
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </section>
</div>
<div class="rp-tip" id="rpTip" hidden></div>

@if($isDtr)
{{-- A worker's DTR. Outside the live region, so a refresh of the list never
     redraws it; the page inside is reports/_dtr_sheet, fetched per worker. --}}
<div class="modal fade" id="dtrModal" tabindex="-1" aria-labelledby="dtrTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable dtr-dialog">
        <div class="modal-content dtr-pop">
            <div class="dtr-bar">
                <div class="dtr-who">
                    <span class="rp-av" id="dtrAv"></span>
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
    // The filters apply as they change: a date pair, a site, a name.
    const form = document.getElementById('rpFilters');
    if (form) {
        const preset = document.getElementById('rpPreset');
        const from = document.getElementById('rpFrom'), to = document.getElementById('rpTo');
        const go = () => form.requestSubmit ? form.requestSubmit() : form.submit();
        [from, to].forEach(el => el && el.addEventListener('change', () => {
            if (from.value && to.value) { preset.value = ''; go(); }
        }));
        const site = document.getElementById('rpSite');
        site && site.addEventListener('change', go);
        const q = document.getElementById('rpQ');
        let t;
        q && q.addEventListener('input', () => { clearTimeout(t); t = setTimeout(go, 600); });
    }

    // Sort by any column; again to reverse. The list is a live region: when
    // it is redrawn the rows come back in the server's order, so the order
    // asked for is put back — the DTR's arrows step through it.
    const table = document.getElementById('rpTable');
    if (table) {
        let sorted = null, dir = -1;
        const order = () => {
            if (sorted === null) return;
            const i = sorted, body = table.tBodies[0];
            const rows = [...body.rows].filter(r => r.cells.length > 1);
            const num = s => /^-?\d+(\.\d+)?$/.test(s);
            rows.sort((a, c) => {
                const x = a.cells[i].dataset.v || '', y = c.cells[i].dataset.v || '';
                return (num(x) && num(y) ? parseFloat(x) - parseFloat(y) : x.localeCompare(y)) * dir;
            });
            rows.forEach(r => body.appendChild(r));
            table.querySelectorAll('thead th').forEach((th, j) => {
                th.classList.toggle('sorted', j === i);
                th.setAttribute('aria-sort', j === i ? (dir > 0 ? 'ascending' : 'descending') : 'none');
            });
        };
        table.querySelector('thead').addEventListener('click', e => {
            const b = e.target.closest('[data-sort]');
            if (!b) return;
            const i = +b.dataset.sort;
            dir = sorted === i ? -dir : -1;
            sorted = i;
            order();
        });
        document.addEventListener('live:updated', order);
    }

    // The chart's figures on hover.
    const tip = document.getElementById('rpTip');
    document.addEventListener('pointermove', e => {
        const t = e.target.closest && e.target.closest('.rp [data-tip]');
        if (!t) { tip.hidden = true; return; }
        tip.textContent = t.dataset.tip;
        tip.hidden = false;
        tip.style.left = e.clientX + 'px';
        tip.style.top = e.clientY + 'px';
    });
})();
</script>
@if($isDtr)
@php
    // One worker's page and its two downloads, for the dates on screen. The
    // 0 stands where the script writes the worker's id.
    $dtrFor   = ['employee' => 0, 'from' => $from, 'to' => $to];
    $dtrLinks = [
        'sheet'  => route('payroll-reports.dtr', $dtrFor),
        'pdf'    => route('payroll-reports.dtr.pdf', $dtrFor),
        'xls'    => route('payroll-reports.dtr.excel', $dtrFor),
        'period' => $dtr['label'],
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
        const row = list[i], badge = row.querySelector('.rp-av'), name = row.querySelector('.rp-emp b'), code = row.querySelector('.rp-emp small');

        $('dtrTitle').textContent = name ? name.textContent : '';
        $('dtrSub').textContent = [code ? code.textContent : '', links.period].filter(Boolean).join(' · ');
        $('dtrAv').textContent = badge ? badge.textContent : '';
        $('dtrAv').style.background = badge ? badge.style.background : '';
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
