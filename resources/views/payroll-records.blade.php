@extends('layouts')

@section('page_title', 'Payroll Records')

{{-- Payroll Records.

     Laid out after the jeyanco-payroll-records.html mockup (2026-09-26): a period bar, a summary
     (net payroll with what it is made of, and eight figures beside it), then
     the breakdown, whose rows open the worker's payslip in a side panel.

     Every figure is the one PayrollService::computeForRange() returned for
     the period — this file only lays them out. The mock's site and shift
     filters, paid/draft statuses and week-on-week change are not here: the
     records carry none of them. Colours are the theme's tokens, so light and
     dark follow the rest of the app. --}}

@push('styles')
<style>
.prx { --prx-mono: 'JetBrains Mono', ui-monospace, Consolas, monospace; display: flex; flex-direction: column; gap: 14px; }
/* The column's gap is under the shared header already; its own margin comes
   in by the difference, so the period bar sits 12px under it as the content
   does on every page. */
html[data-bs-theme] .main-content .prx > .page-head { margin-bottom: -2px !important; }

.prx-card { background: var(--surface); border: 1px solid var(--border); border-radius: 14px; box-shadow: var(--shadow-xs); }

.prx-btn {
    display: inline-flex; align-items: center; gap: 8px; height: 36px; padding: 0 14px;
    border-radius: 10px; border: 1px solid var(--border-md); background: var(--surface);
    color: var(--text-primary); font-size: 13px; font-weight: 600; white-space: nowrap;
    text-decoration: none; cursor: pointer; transition: border-color .15s, filter .15s;
}
.prx-btn:hover { border-color: var(--brand); color: var(--text-primary); }
.prx-btn.pri { background: var(--brand); border-color: var(--brand); color: #fff; }
.prx-btn.pri:hover { filter: brightness(1.08); color: #fff; }
.prx-btn[aria-disabled="true"] { opacity: .5; pointer-events: none; }
.prx-btn svg { width: 16px; height: 16px; flex: none; }
.prx-ib {
    width: 36px; height: 36px; border-radius: 9px; border: 1px solid var(--border-md);
    background: var(--surface); color: var(--text-secondary); display: grid; place-items: center;
    text-decoration: none; cursor: pointer; flex: none;
}
.prx-ib:hover { color: var(--text-primary); border-color: var(--brand); }
.prx-ib svg { width: 16px; height: 16px; }

/* ── Period bar ───────────────────────────────────────────────────────── */
.prx-period { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; padding: 12px 14px; }
.prx-seg { display: flex; border: 1px solid var(--border-md); border-radius: 10px; overflow: hidden; background: var(--bg-subtle); }
.prx-seg button { border: 0; background: none; height: 34px; padding: 0 14px; color: var(--text-secondary); font-size: 13px; font-weight: 600; cursor: pointer; }
.prx-seg button:hover { color: var(--text-primary); }
.prx-seg button.on { background: var(--brand); color: #fff; }
.prx-wk { display: flex; align-items: center; gap: 6px; position: relative; }
.prx-when {
    display: flex; flex-direction: column; align-items: flex-start; min-width: 150px;
    padding: 2px 6px; border: 0; border-radius: 8px; background: none; text-align: left; cursor: pointer;
    color: var(--text-primary);
}
.prx-when:hover { background: var(--bg-subtle); }
.prx-when b { font-size: 15px; font-weight: 700; line-height: 1.25; }
.prx-when small { font-size: 12px; color: var(--text-secondary); font-family: var(--prx-mono); font-variant-numeric: tabular-nums; }
/* The native week or date picker the label opens. It stays in the layout
   (a picker cannot open from nothing) but takes no room and no clicks; a
   browser without pickers gets it shown as a plain field instead. */
.prx-pick { position: absolute; left: 44px; top: 100%; width: 1px; height: 1px; opacity: 0; pointer-events: none; border: 0; padding: 0; }
.prx-pick.is-shown {
    position: static; width: 150px; height: 34px; opacity: 1; pointer-events: auto; padding: 0 8px;
    border: 1px solid var(--border-md); border-radius: 8px; background: var(--surface); color: var(--text-primary);
}
.prx-chip {
    display: inline-flex; align-items: center; gap: 7px; height: 28px; padding: 0 6px 0 11px; border-radius: 999px;
    background: var(--brand-subtle); color: var(--brand); font-size: 12px; font-weight: 700; white-space: nowrap;
}
.prx-chip a { display: grid; place-items: center; width: 18px; height: 18px; border-radius: 50%; color: inherit; text-decoration: none; }
.prx-chip a:hover { background: color-mix(in srgb, var(--brand) 18%, transparent); }
.prx-period .sp { flex: 1; }
.prx-sel {
    display: flex; align-items: center; gap: 8px; height: 36px; padding: 0 10px; margin: 0;
    border: 1px solid var(--border-md); border-radius: 10px; background: var(--surface);
}
.prx-sel:focus-within { border-color: var(--brand); box-shadow: 0 0 0 3px var(--brand-subtle); }
.prx-sel svg { width: 15px; height: 15px; color: var(--text-muted); flex: none; }
html[data-bs-theme] .prx .prx-sel input,
html[data-bs-theme] .prx .prx-sel select {
    border: 0 !important; background: transparent !important; box-shadow: none !important; outline: none;
    height: 34px; width: 200px; padding: 0; color: var(--text-primary); font-size: 13px; font-weight: 500;
}

/* ── Summary ──────────────────────────────────────────────────────────── */
.prx-sum { display: grid; grid-template-columns: minmax(0, 1.25fr) minmax(0, 2fr); gap: 14px; }
.prx-hero { padding: 18px 20px; display: flex; flex-direction: column; justify-content: center; gap: 12px; }
.prx-hero .k {
    display: flex; justify-content: space-between; align-items: center; gap: 8px;
    font-size: 11px; letter-spacing: .12em; text-transform: uppercase; font-weight: 700; color: var(--text-secondary);
}
.prx-tag {
    font-size: 12px; font-weight: 600; letter-spacing: 0; text-transform: none; color: var(--text-secondary);
    background: var(--bg-subtle); border: 1px solid var(--border); border-radius: 6px; padding: 2px 8px; white-space: nowrap;
}
.prx-hero .v { font-size: 34px; font-weight: 800; letter-spacing: -.01em; line-height: 1; font-variant-numeric: tabular-nums; color: var(--text-primary); }
.prx-flow { display: flex; height: 10px; border-radius: 6px; overflow: hidden; background: var(--bg-subtle); }
.prx-flow i { display: block; height: 100%; }
.prx-legend { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 8px; font-size: 12px; color: var(--text-secondary); }
.prx-legend span { min-width: 0; }
.prx-legend span::before { content: ""; display: inline-block; width: 8px; height: 8px; border-radius: 2px; background: var(--c); margin-right: 6px; }
.prx-legend b { display: block; margin-top: 2px; color: var(--text-primary); font-family: var(--prx-mono); font-size: 13.5px; font-weight: 600; font-variant-numeric: tabular-nums; white-space: nowrap; }
.prx-tiles {
    display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 1px;
    background: var(--border); border: 1px solid var(--border); border-radius: 14px; overflow: hidden; box-shadow: var(--shadow-xs);
}
.prx-tile { background: var(--surface); padding: 14px 16px; display: flex; flex-direction: column; gap: 4px; min-width: 0; }
.prx-tile span { font-size: 10.5px; letter-spacing: .1em; text-transform: uppercase; font-weight: 700; color: var(--text-secondary); }
.prx-tile b { font-size: 19px; font-weight: 700; font-variant-numeric: tabular-nums; color: var(--text-primary); white-space: nowrap; }
.prx-tile small { font-size: 11.5px; color: var(--text-muted); }
.prx-tile.zero b { color: var(--text-muted); }
@media (max-width: 1200px) { .prx-sum { grid-template-columns: minmax(0, 1fr); } }

/* ── Breakdown ────────────────────────────────────────────────────────── */
.prx-list { overflow: hidden; }
.prx-thead { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; padding: 14px 16px; border-bottom: 1px solid var(--border); }
html[data-bs-theme] .prx .prx-h {
    margin: 0 !important; font-size: 15px !important; font-weight: 700 !important; line-height: 1.3 !important;
    letter-spacing: 0 !important; color: var(--text-primary); display: flex; gap: 8px; align-items: center;
}
.prx-h svg { width: 17px; height: 17px; color: var(--brand); }
.prx-thead .sp { flex: 1; }
.prx-note { font-size: 12px; color: var(--text-muted); }
.prx-wrap { overflow: auto; }
.prx-table { width: 100%; min-width: 1080px; border-collapse: separate; border-spacing: 0; }
.prx-table th, .prx-table td { text-align: right; white-space: nowrap; }
.prx-table thead th {
    position: sticky; top: 0; z-index: 2; padding: 10px 8px;
    background: var(--bg-subtle); border-bottom: 1px solid var(--border);
    font-size: 10px; letter-spacing: .06em; text-transform: uppercase; font-weight: 700; color: var(--text-secondary);
}
.prx-table tbody td {
    padding: 10px 8px; border-bottom: 1px solid var(--border);
    font-size: 12.5px; font-variant-numeric: tabular-nums; color: var(--text-primary);
}
/* The table's outer edges keep the card's inset. */
.prx-table tr > :first-child { padding-left: 16px; }
.prx-table tr > :last-child { padding-right: 16px; }
/* A long name takes a second line rather than the width the figures need,
   and the employee stays in view while the figures scroll sideways. */
.prx-table td.emp { white-space: normal; min-width: 200px; }
.prx-table th:first-child, .prx-table td:first-child { position: sticky; left: 0; z-index: 1; background: var(--surface); }
.prx-table thead th:first-child, .prx-table tfoot td:first-child { z-index: 3; background: var(--bg-subtle); }
.prx-table tbody tr.prx-dategrp td:first-child { background: var(--bg); }
.prx-table .l { text-align: left; }
.prx-table .sepl { border-left: 1px dashed var(--border-md); }
.prx-table td.z { color: var(--text-muted); }
.prx-table td.ded { color: var(--danger); }
.prx-table td.net { font-weight: 800; font-size: 13.5px; }
.prx-table tbody tr.pr-row { cursor: pointer; }
.prx-table tbody tr.pr-row:hover td { background: var(--bg-subtle); }
.prx-table tbody tr.pr-row:focus-visible { outline: 2px solid var(--brand); outline-offset: -2px; }
.prx-table td small { display: block; font-size: 11px; color: var(--text-muted); font-weight: 500; }
.prx-table tfoot td {
    position: sticky; bottom: 0; z-index: 2; padding: 12px 8px;
    background: var(--bg-subtle); border-top: 1px solid var(--border-md);
    font-size: 12.5px; font-weight: 800; font-variant-numeric: tabular-nums; color: var(--text-primary);
}
.prx-table tfoot td.l { font-size: 11px; letter-spacing: .1em; text-transform: uppercase; color: var(--text-secondary); }
.prx-dategrp td { text-align: left !important; background: var(--bg) !important; font-size: 12px !important; font-weight: 700; letter-spacing: .04em; color: var(--text-secondary) !important; cursor: default; }
.prx-empty td { text-align: center !important; padding: 48px 16px !important; color: var(--text-muted) !important; }

.prx-emp { display: flex; gap: 10px; align-items: center; }
.prx-av {
    width: 34px; height: 34px; border-radius: 50%; display: grid; place-items: center; flex: none;
    font-weight: 700; font-size: 12.5px; color: #fff; background: var(--c, var(--brand));
}
.prx-emp b { display: block; font-size: 13px; font-weight: 700; line-height: 1.3; }
.prx-emp small { display: block; color: var(--text-muted); font-size: 11.5px; }
.prx-shift { display: inline-flex; align-items: center; font-size: 12px; font-weight: 700; border-radius: 7px; padding: 3px 8px; background: var(--bg-subtle); color: var(--text-secondary); }
.prx-shift.day { background: var(--warning-soft); color: var(--warning); }
.prx-shift.night { background: var(--brand-subtle); color: var(--brand); }

/* Lateness is reported, never deducted — so it is a note on the row, not a
   figure in the money columns. */
.pr-late, .pr-leave {
    display: inline-block; margin-left: 6px; padding: 0 7px; border-radius: 999px;
    font-size: 10.5px; font-weight: 700; vertical-align: 1px;
}
.pr-late { color: var(--warning); background: var(--warning-soft); }
/* A day of approved leave is a paid day with no hours on it; the row says
   what it is, in the money colour — a signed-off day off is not a problem. */
.pr-leave { color: var(--brand); background: var(--brand-subtle); }
.pr-leave.unpaid { color: var(--text-muted); background: var(--bg-subtle); }

/* ── The payslip panel ────────────────────────────────────────────────── */
.prx-drawer.offcanvas { --bs-offcanvas-width: min(540px, 100%); background: var(--surface); color: var(--text-primary); border-left: 1px solid var(--border); }
.prx-dh { padding: 18px 22px; border-bottom: 1px solid var(--border); display: flex; gap: 12px; align-items: center; }
.prx-dh .prx-av { width: 46px; height: 46px; font-size: 16px; }
.prx-dh b { display: block; font-size: 17px; }
.prx-dh small { color: var(--text-muted); font-size: 12.5px; }
.prx-dh .prx-ib { margin-left: auto; }
.prx-db { padding: 18px 22px; overflow: auto; display: flex; flex-direction: column; gap: 18px; flex: 1; }
.prx-netbox {
    border-radius: 14px; padding: 16px 18px; border: 1px solid var(--border);
    background: linear-gradient(135deg, var(--brand-subtle), transparent);
    display: flex; justify-content: space-between; align-items: flex-end; gap: 10px;
}
.prx-netbox span { font-size: 11px; letter-spacing: .12em; text-transform: uppercase; font-weight: 700; color: var(--text-secondary); }
.prx-netbox b { display: block; font-size: 30px; font-weight: 800; font-variant-numeric: tabular-nums; }
.prx-netbox small { color: var(--text-secondary); font-size: 12.5px; text-align: right; }
.prx-dsec h4 { margin: 0 0 8px !important; font-size: 11px !important; letter-spacing: .12em; text-transform: uppercase; color: var(--text-secondary); font-weight: 700; }
.prx-lines { list-style: none; margin: 0; padding: 0; }
.prx-lines li { display: flex; justify-content: space-between; gap: 10px; padding: 8px 0; border-bottom: 1px dashed var(--border); font-size: 13.5px; }
.prx-lines li span { color: var(--text-secondary); }
.prx-lines li b { font-family: var(--prx-mono); font-weight: 600; font-variant-numeric: tabular-nums; white-space: nowrap; }
.prx-lines li.tot { border-bottom: 0; border-top: 1px solid var(--border-md); font-weight: 800; }
.prx-lines li.tot span { color: var(--text-primary); }
.prx-days { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 6px; }
.prx-days div { border-radius: 9px; padding: 8px 4px; text-align: center; background: var(--bg-subtle); display: flex; flex-direction: column; gap: 3px; font-size: 11px; color: var(--text-muted); }
.prx-days div b { font-size: 13px; color: var(--text-primary); font-family: var(--prx-mono); }
.prx-days div.w { background: var(--brand-subtle); }
.prx-days div.lv { background: var(--violet-soft); }
.prx-days div em { font-style: normal; font-size: 10px; color: var(--success); }
.prx-days div.lv em { color: var(--violet); }
.rc-basis {
    margin: 0; padding: 8px 10px; border-radius: 8px; background: var(--bg-subtle); border: 1px solid var(--border);
    font-size: 12px; color: var(--text-secondary); font-variant-numeric: tabular-nums;
}
/* Three label-and-figure pairs, each one cell, so a pair cannot split. The
   bonus is added to net, not to gross, so it sits in the arithmetic that
   reaches net rather than in the earnings. */
.rc-math {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 6px 14px;
    padding: 9px 12px; border-radius: 8px; background: var(--bg-subtle); border: 1px solid var(--border);
    font-size: 12px; font-variant-numeric: tabular-nums;
}
.rc-math-item { display: flex; justify-content: space-between; gap: 10px; min-width: 0; }
.rc-math span { color: var(--text-secondary); }
.rc-math b { color: var(--text-primary); font-weight: 700; white-space: nowrap; }
.prx-df { padding: 14px 22px; border-top: 1px solid var(--border); display: flex; gap: 8px; justify-content: flex-end; flex-wrap: wrap; }
/* The chat button floats bottom-right, exactly where the panel's buttons are. */
body:has(.prx-drawer.show) .chatbot-fab,
body:has(.prx-drawer.showing) .chatbot-fab { visibility: hidden; }

/* ── Preview & download ───────────────────────────────────────────────── */
/* A payslip is a sheet of paper wide; the register is twelve columns, and
   gets the room for them. */
.prx-modal .modal-dialog { max-width: min(860px, calc(100% - 32px)); transition: max-width .2s ease; }
.prx-modal.is-wide .modal-dialog { max-width: min(1200px, calc(100% - 32px)); }
.prx-modal .modal-content { background: var(--surface); color: var(--text-primary); border: 1px solid var(--border); border-radius: 18px; overflow: hidden; }
.prx-mh { padding: 16px 20px; display: flex; gap: 12px; align-items: center; border-bottom: 1px solid var(--border); flex-wrap: wrap; }
html[data-bs-theme] .prx-modal .prx-mh h3 { margin: 0 !important; font-size: 17px !important; font-weight: 700 !important; line-height: 1.3 !important; color: var(--text-primary); }
.prx-mh small { color: var(--text-muted); font-size: 12.5px; }
.prx-mh .sp { flex: 1; }
.prx-mb { padding: 18px 20px; background: var(--bg-subtle); }
.prx-opts { display: flex; gap: 10px; flex-wrap: wrap; }
.prx-opt {
    flex: 1; min-width: 180px; border: 1px solid var(--border-md); border-radius: 12px; padding: 12px 14px;
    background: var(--surface); color: var(--text-primary); text-align: left; cursor: pointer;
    display: flex; flex-direction: column; gap: 3px; font: inherit;
}
.prx-opt b { font-size: 13.5px; }
.prx-opt small { color: var(--text-muted); font-size: 12px; }
.prx-opt:hover { border-color: var(--brand); }
.prx-opt.on { border-color: var(--brand); box-shadow: 0 0 0 1px var(--brand) inset; }
.prx-pv-pick { margin-top: 14px; max-width: 360px; }
.prx-pv-empty { margin-top: 16px; padding: 32px 16px; text-align: center; color: var(--text-muted); }
.prx-mf { padding: 14px 20px; display: flex; gap: 8px; align-items: center; justify-content: flex-end; border-top: 1px solid var(--border); flex-wrap: wrap; }
.prx-mf .note { margin-right: auto; font-size: 12px; color: var(--text-muted); }

/* The paper: what gets printed looks like paper in either theme. */
.prx-slip { margin-top: 16px; background: #fff; color: #0f1b2d; border-radius: 10px; padding: 24px 26px; font-size: 12.5px; box-shadow: 0 8px 24px rgba(15, 27, 45, .14); }
.prx-slip header { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; border-bottom: 2px solid #0f1b2d; padding-bottom: 12px; margin-bottom: 14px; }
.prx-slip header .co { display: flex; gap: 10px; align-items: center; }
.prx-slip header img { width: 36px; height: 36px; border-radius: 50%; object-fit: cover; }
.prx-slip header b { display: block; font-size: 16px; letter-spacing: .06em; }
.prx-slip header small { display: block; color: #56657d; }
.prx-slip .who { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 4px 20px; margin-bottom: 14px; }
.prx-slip .who span { color: #56657d; }
.prx-slip .two { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 20px; }
.prx-slip .two > div { display: flex; flex-direction: column; }
.prx-slip h5 { margin: 0 0 6px !important; font-size: 10.5px !important; letter-spacing: .12em; text-transform: uppercase; color: #56657d; font-weight: 700; }
.prx-slip .r { display: flex; justify-content: space-between; gap: 10px; padding: 4px 0; border-bottom: 1px dotted #d6dde8; font-variant-numeric: tabular-nums; }
.prx-slip .r.t { font-weight: 800; border-bottom: 0; border-top: 1px solid #0f1b2d; margin-top: auto; }
.prx-slip .bonus { margin-top: 12px; display: flex; justify-content: space-between; color: #12793a; font-weight: 700; font-variant-numeric: tabular-nums; }
.prx-slip .netline { margin-top: 12px; background: #eef3fb; border-radius: 8px; padding: 12px 14px; display: flex; justify-content: space-between; font-size: 15px; font-weight: 800; font-variant-numeric: tabular-nums; }
.prx-slip .sign { display: flex; justify-content: space-between; gap: 20px; margin-top: 26px; color: #56657d; font-size: 11px; }
.prx-slip .sign div { flex: 1; border-top: 1px solid #8a97ab; padding-top: 4px; text-align: center; }
.prx-slip.reg { overflow: auto; padding: 22px 20px; }
.prx-slip table { width: 100%; border-collapse: collapse; font-size: 11.5px; white-space: nowrap; }
.prx-slip th { padding: 6px 5px; border-bottom: 1px solid #0f1b2d; text-align: right; font-size: 10px; letter-spacing: .04em; text-transform: uppercase; color: #56657d; }
.prx-slip td { padding: 6px 5px; border-bottom: 1px dotted #d6dde8; text-align: right; font-variant-numeric: tabular-nums; }
.prx-slip .r.none { color: #8a97ab; }
.prx-slip th.l, .prx-slip td.l { text-align: left; }
.prx-slip tfoot td { font-weight: 800; border-bottom: 0; border-top: 1px solid #0f1b2d; }
</style>
@endpush

@section('content')
@php
    $isDaily = $period['mode'] === 'daily';
    $start   = \Carbon\Carbon::parse($period['from']);
    $end     = \Carbon\Carbon::parse($period['to']);
    $dur     = fn ($m) => \App\Support\WorkSchedule::duration((int) round((float) $m));
    $peso    = fn ($n) => '₱' . number_format((float) $n, 2);

    // ── Read off the day rows ────────────────────────────────────────────
    // The daily rate and shift belong to the worker rather than to a day, so
    // they are read off any day they worked; so is the hourly the days were
    // actually priced at, so nothing has to divide and guess. The hours per
    // day and the overtime minutes are the same rows, added up.
    $rateOf = $shiftOf = $hourlyOf = $otOf = $grid = [];
    $holidayShifts = 0;
    foreach ($days as $day) {
        foreach ($day['details'] as $d) {
            $id = $d['employee_id'];
            $rateOf[$id]   ??= $d['dailyRate'];
            $shiftOf[$id]  ??= $d['shift'];
            $hourlyOf[$id] ??= $d['rate'] ?? null;
            $otOf[$id]      = ($otOf[$id] ?? 0) + (int) ($d['ot_minutes'] ?? 0);
            if ($d['is_holiday'] ?? false) { $holidayShifts++; }

            $cell = $grid[$id][$day['date']] ?? ['min' => 0, 'ot' => 0, 'leave' => null];
            $cell['min'] += (int) ($d['minutes'] ?? 0);
            $cell['ot']  += (int) ($d['ot_minutes'] ?? 0);
            if ($d['leave'] ?? false) { $cell['leave'] = $d['leave_type'] ?? __('Leave'); }
            $grid[$id][$day['date']] = $cell;
        }
    }
    // A worker on leave all week worked no day to read a rate or a shift off,
    // so the week's own figures fill the gap — otherwise they show at ₱0.00 a
    // day while being paid for the week.
    foreach ($employees as $empRow) {
        foreach ($empRow['periods'] as $p) {
            $rateOf[$empRow['employee_id']]  ??= $p['dailyRate'] ?? null;
            $shiftOf[$empRow['employee_id']] ??= $p['shift'] ?? null;
        }
    }

    // ── One slip per worker ──────────────────────────────────────────────
    // Built from the same totals as before. The deductions are summed from
    // the weekly rows because only those carry them itemised. Regular pay is
    // the gross less every premium in it and less the paid leave: a day off
    // is not regular pay, and it gets a line of its own. The bonus is added
    // to net, not to gross.
    $palette = ['#2563eb', '#7c3aed', '#0d9488', '#be123c', '#4f46e5', '#0369a1', '#b45309', '#15803d'];
    $weekDates = [];
    for ($d = $start->copy(); $d->lte($end); $d->addDay()) { $weekDates[] = $d->copy(); }

    $slips = [];
    foreach ($employees as $emp) {
        $t  = $emp['totals'];
        $id = $emp['employee_id'];
        $sss = $phil = $pag = $tax = $vale = $other = $adv = $defer = $late = 0;
        foreach ($emp['periods'] as $pp) {
            $sss   += $pp['sssDeduction'];
            $phil  += $pp['philhealthDeduction'];
            $pag   += $pp['pagibigDeduction'];
            $tax   += $pp['withholdingTax'];
            $vale  += $pp['vale'];
            $adv   += $pp['vale_advance'] ?? 0;
            $defer += $pp['cash_advance_deferred'] ?? 0;
            $other += $pp['manualDeductions'];
            $late  += (int) ($pp['late_minutes'] ?? 0);
        }

        $regular = round($t['gross'] - $t['overtime'] - $t['holidayPay']
                       - ($t['restDayPay'] ?? 0) - ($t['nightDiffPay'] ?? 0)
                       - ($t['leavePay'] ?? 0), 2);

        $slips[$id] = [
            'id'        => $id,
            'code'      => '#' . str_pad($id, 4, '0', STR_PAD_LEFT),
            'name'      => $emp['name'],
            'initial'   => mb_strtoupper(mb_substr($emp['name'], 0, 1)),
            'color'     => $palette[$id % count($palette)],
            'position'  => $emp['position'] ?? '',
            'shift'     => $shiftOf[$id] ?? null,
            'dailyRate' => (float) ($rateOf[$id] ?? 0),
            'rate'      => $hourlyOf[$id] ?? null,
            'workdays'  => $t['workdays'],
            'minutes'   => $t['minutes'],
            'duration'  => $dur($t['minutes']),
            'otDur'     => ($otOf[$id] ?? 0) > 0 ? $dur($otOf[$id]) : '',
            'regular'   => $regular,
            'overtime'  => $t['overtime'],
            'night'     => $t['nightDiffPay'] ?? 0,
            'leave'     => $t['leavePay'] ?? 0,
            'leaveDays' => $t['leaveDays'] ?? 0,
            'holiday'   => $t['holidayPay'],
            'rest'      => $t['restDayPay'] ?? 0,
            'bonus'     => $t['bonus'],
            'gross'     => $t['gross'],
            'sss'       => round($sss, 2),
            'phil'      => round($phil, 2),
            'pag'       => round($pag, 2),
            'tax'       => round($tax, 2),
            'vale'      => round($vale, 2),
            'advance'   => round($adv, 2),
            'deferred'  => round($defer, 2),
            'other'     => round($other, 2),
            'late'      => $late,
            'ded'       => $t['totalDeductions'],
            'net'       => $t['net'],
            // The week at a glance, for the panel. A day is its hours, its
            // overtime, or the leave it was.
            'days'      => array_map(function (\Carbon\Carbon $date) use ($grid, $id, $dur) {
                $c = $grid[$id][$date->toDateString()] ?? null;
                return [
                    'label' => $date->format('D j'),
                    'dur'   => $c && $c['min'] > 0 ? $dur($c['min']) : '',
                    'ot'    => $c && $c['ot'] > 0 ? $dur($c['ot']) : '',
                    'leave' => $c['leave'] ?? null,
                ];
            }, $weekDates),
        ];
    }

    $sumOf = fn ($k) => round(array_sum(array_column($slips, $k)), 2);
    $showNight = $sumOf('night') > 0;
    $showLeave = $sumOf('leave') > 0;
    $cols = 12 + ($isDaily ? 0 : 1) + ($showNight ? 1 : 0) + ($showLeave ? 1 : 0);

    // ── Where the period bar goes ────────────────────────────────────────
    $keep = $search !== '' ? ['employee' => $search] : [];
    if ($isDaily) {
        $prevUrl  = route('payroll-records', ['mode' => 'daily', 'date' => $start->copy()->subDay()->toDateString()] + $keep);
        $nextUrl  = route('payroll-records', ['mode' => 'daily', 'date' => $start->copy()->addDay()->toDateString()] + $keep);
        $whenBig  = $start->format('l, M j');
        $whenSub  = $start->format('m/d/Y');
        $short    = $start->format('M j');
        $unit     = __('day');
    } else {
        $prevUrl  = route('payroll-records', ['mode' => 'weekly', 'week' => $start->copy()->subWeek()->format('o-\WW')] + $keep);
        $nextUrl  = route('payroll-records', ['mode' => 'weekly', 'week' => $start->copy()->addWeek()->format('o-\WW')] + $keep);
        $whenBig  = __('Week') . ' ' . $start->isoWeek . ', ' . $start->isoWeekYear;
        $whenSub  = $start->format('m/d') . ' – ' . $end->format('m/d/Y');
        $short    = __('Week') . ' ' . $start->isoWeek;
        $unit     = __('week');
    }
    $clearUrl = route('payroll-records', array_filter([
        'mode' => $period['mode'],
        'week' => $isDaily ? null : $start->format('o-\WW'),
        'date' => $isDaily ? $start->toDateString() : null,
    ]));

    // ── The summary's bar: what the pay is made of ──────────────────────
    // Regular pay (and paid leave), the premiums on top, the bonus, and the
    // part of it that went to deductions — carved from the regular pay, as
    // the deductions are taken from the pay rather than added to it.
    $premiums = round($summary['overtime'] + $summary['holidayPay'] + ($summary['restDayPay'] ?? 0) + $sumOf('night'), 2);
    $base     = round($sumOf('regular') + $sumOf('leave'), 2);
    $ded      = $summary['totalDeductions'];
    $segs     = [
        ['var(--brand)',   max(0, $base - $ded)],
        ['var(--success)', max(0, $premiums)],
        ['var(--warning)', max(0, $summary['bonus'])],
        ['var(--danger)',  max(0, $ded)],
    ];
    $segTotal = array_sum(array_column($segs, 1)) ?: 1;

    $otPct   = (int) round(((float) ($rates['ot_multiplier'] ?? 1.25)) * 100);
    $restPct = (int) round(((float) ($rates['rest_day_multiplier'] ?? 1.3)) * 100);
    $otMins  = array_sum($otOf);

    $icon = [
        'xls'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M14 3H7a2 2 0 00-2 2v14a2 2 0 002 2h10a2 2 0 002-2V8z"/><path d="M14 3v5h5M9 13l6 5M15 13l-6 5"/></svg>',
        'dl'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M14 3H7a2 2 0 00-2 2v14a2 2 0 002 2h10a2 2 0 002-2V8z"/><path d="M14 3v5h5M12 11v6M9 14l3 3 3-3"/></svg>',
        'prev'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M15 6l-6 6 6 6"/></svg>',
        'next'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>',
        'find'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>',
        'cal'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>',
        'x'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" width="12" height="12"><path d="M6 6l12 12M18 6L6 18"/></svg>',
        'close' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>',
        'print' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M6 9V3h12v6M6 18H4a1 1 0 01-1-1v-6a2 2 0 012-2h14a2 2 0 012 2v6a1 1 0 01-1 1h-2"/><rect x="6" y="14" width="12" height="7" rx="1"/></svg>',
    ];

    $shiftClass = fn (?string $s) => $s === null ? '' : (str_contains(strtolower($s), 'night') ? 'night' : (str_contains(strtolower($s), 'day') ? 'day' : ''));
    $money = fn ($v, string $cls = '') => '<td class="' . ((float) $v ? $cls : 'z') . '">' . ((float) $v ? e($peso($v)) : '—') . '</td>';
@endphp

<div class="prx">

    <x-page-header :title="__('Payroll Records')">
        <x-slot:actions>
            <button type="button" class="prx-btn" data-prx-preview="register">{!! $icon['xls'] !!} {{ __('Export register') }}</button>
            <button type="button" class="prx-btn pri" data-prx-preview="single">{!! $icon['dl'] !!} {{ __('Preview & Download') }}</button>
        </x-slot:actions>
    </x-page-header>

    {{-- ── Period ──────────────────────────────────────────────────────────
         A week (Monday to Sunday) or a single day, not a loose range: the
         daily breakdown is the days inside a pay period. The arrows step one
         week or one day; the label opens the calendar to jump further. --}}
    <form class="prx-card prx-period" method="GET" action="{{ route('payroll-records') }}" id="prFilter" aria-label="{{ __('Period') }}">
        <input type="hidden" name="mode" id="mode" value="{{ $period['mode'] }}">

        <div class="prx-seg" role="group" aria-label="{{ __('View') }}">
            <button type="button" class="{{ $isDaily ? '' : 'on' }}" data-mode="weekly" aria-pressed="{{ $isDaily ? 'false' : 'true' }}">{{ __('Weekly') }}</button>
            <button type="button" class="{{ $isDaily ? 'on' : '' }}" data-mode="daily" aria-pressed="{{ $isDaily ? 'true' : 'false' }}">{{ __('Daily') }}</button>
        </div>

        <div class="prx-wk">
            <a class="prx-ib" href="{{ $prevUrl }}" aria-label="{{ $isDaily ? __('Previous day') : __('Previous week') }}">{!! $icon['prev'] !!}</a>
            <button type="button" class="prx-when" id="prWhen" title="{{ __('Pick a') }} {{ $unit }}">
                <b>{{ $whenBig }}</b><small>{{ $whenSub }}</small>
            </button>
            {{-- Switching between Weekly and Daily stays where you are: a day
                 opens the week it is in, and a week opens on today when today
                 is in it, otherwise on its Monday. --}}
            @if($isDaily)
                <input type="date" name="date" value="{{ $start->toDateString() }}" class="prx-pick" id="prPick" aria-label="{{ __('Date') }}">
                <input type="hidden" name="week" value="{{ $start->format('o-\WW') }}">
            @else
                <input type="week" name="week" value="{{ $start->format('o-\WW') }}" class="prx-pick" id="prPick" aria-label="{{ __('Week') }}">
                <input type="hidden" name="date" value="{{ now()->between($start->copy()->startOfDay(), $end->copy()->endOfDay()) ? now()->toDateString() : $start->toDateString() }}">
            @endif
            <a class="prx-ib" href="{{ $nextUrl }}" aria-label="{{ $isDaily ? __('Next day') : __('Next week') }}">{!! $icon['next'] !!}</a>
        </div>

        @if($search !== '')
            <span class="prx-chip">
                “{{ $search }}” · {{ count($employees) }} {{ count($employees) === 1 ? __('employee') : __('employees') }}
                <a href="{{ $clearUrl }}" aria-label="{{ __('Show all employees') }}">{!! $icon['x'] !!}</a>
            </span>
        @endif

        <span class="sp"></span>

        <label class="prx-sel">
            {!! $icon['find'] !!}
            <input type="text" name="employee" value="{{ $search }}" placeholder="{{ __('Employee name or ID') }}"
                   autocomplete="off" aria-label="{{ __('Employee name or ID') }}" enterkeyhint="search">
        </label>
    </form>

    {{-- ── Summary ─────────────────────────────────────────────────────────
         Every figure here is computed from attendance, leave, advances and
         the payroll settings — so it follows all four. --}}
    <section class="prx-sum" id="prSummary" data-live="payroll attendance leave advances settings" aria-label="{{ __('Summary') }}">
        <div class="prx-card prx-hero">
            <div class="k"><span>{{ __('Net payroll') }}</span><span class="prx-tag">{{ $short }}</span></div>
            <div class="v">{{ $peso($summary['net']) }}</div>
            <div class="prx-flow" aria-hidden="true">
                @foreach($segs as [$c, $v])
                    @if($v > 0)<i style="width:{{ round($v / $segTotal * 100, 3) }}%;background:{{ $c }}"></i>@endif
                @endforeach
            </div>
            <div class="prx-legend">
                <span style="--c:var(--brand)">{{ __('Regular pay') }}<b>{{ $peso($base) }}</b></span>
                <span style="--c:var(--success)">{{ __('OT & premiums') }}<b>{{ $peso($premiums) }}</b></span>
                <span style="--c:var(--warning)">{{ __('Bonus') }}<b>{{ $peso($summary['bonus']) }}</b></span>
                <span style="--c:var(--danger)">{{ __('Deductions') }}<b>−{{ $peso($ded) }}</b></span>
            </div>
        </div>

        <div class="prx-tiles">
            @php
                $tiles = [
                    [__('Gross pay'),    $peso($summary['gross']),           __('Before deductions'),                         (float) $summary['gross']],
                    [__('Deductions'),   $peso($summary['totalDeductions']), ($rates['withholding_tax'] ?? false)
                                                                                ? __('SSS · PhilHealth · Pag-IBIG · tax · vale')
                                                                                : __('SSS · PhilHealth · Pag-IBIG · vale'),    (float) $summary['totalDeductions']],
                    [__('Overtime'),     $peso($summary['overtime']),        $otMins > 0 ? $dur($otMins) . ' ' . __('at') . ' ' . $otPct . '%' : __('No overtime'), (float) $summary['overtime']],
                    [__('Holiday pay'),  $peso($summary['holidayPay']),      $holidayShifts > 0
                                                                                ? $holidayShifts . ' ' . ($holidayShifts === 1 ? __('holiday shift') : __('holiday shifts'))
                                                                                : ($isDaily ? __('Not a holiday') : __('No holidays this week')), (float) $summary['holidayPay']],
                    [__('Rest day pay'), $peso($summary['restDayPay'] ?? 0), __('Rest days at') . ' ' . $restPct . '%',        (float) ($summary['restDayPay'] ?? 0)],
                    [__('Bonus'),        $peso($summary['bonus']),           __('Added after deductions'),                    (float) $summary['bonus']],
                    [__('Employees'),    (string) $summary['employee_count'], $isDaily ? __('Paid this day') : __('Paid this week'), (float) $summary['employee_count']],
                    [__('Hours / days'), $dur($summary['minutes']),          $summary['workdays'] . ' ' . ($summary['workdays'] === 1 ? __('man-day') : __('man-days')), (float) $summary['minutes']],
                ];
            @endphp
            @foreach($tiles as [$k, $v, $s, $n])
                <div class="prx-tile {{ $n ? '' : 'zero' }}"><span>{{ $k }}</span><b>{{ $v }}</b><small>{{ $s }}</small></div>
            @endforeach
        </div>
    </section>

    {{-- ── The breakdown ───────────────────────────────────────────────────
         A week is one row per worker; a day is one row per shift. The money
         columns add up to Gross, then Deductions come off and the Bonus goes
         on to make Net. Night differential and paid leave get a column only
         when the period has any. A row opens that worker's payslip. --}}
    <section class="prx-card prx-list" id="prBreakdown" data-live="payroll attendance leave advances settings employees"
             data-fill-screen aria-label="{{ __('Breakdown') }}">
        <div class="prx-thead">
            <h2 class="prx-h">{!! $icon['cal'] !!} {{ $isDaily ? __('Daily breakdown') : __('Weekly breakdown') }}</h2>
            <span class="prx-note">{{ count($employees) }} {{ count($employees) === 1 ? __('employee') : __('employees') }}</span>
            <span class="sp"></span>
            <span class="prx-note">{{ __('Click a row for the full payslip') }}</span>
        </div>
        <div class="prx-wrap" data-fill-scroll>
            <table class="prx-table">
                <thead>
                    <tr>
                        <th class="l">{{ __('Employee') }}</th>
                        <th class="l">{{ __('Shift') }}</th>
                        @unless($isDaily)<th>{{ __('Days') }}</th>@endunless
                        <th>{{ __('Hours') }}</th>
                        <th>{{ __('Rate') }}</th>
                        <th class="sepl">{{ __('Basic') }}</th>
                        <th>{{ __('Overtime') }}</th>
                        @if($showNight)<th>{{ __('Night diff') }}</th>@endif
                        <th>{{ __('Rest day') }}</th>
                        <th>{{ __('Holiday') }}</th>
                        @if($showLeave)<th>{{ __('Paid leave') }}</th>@endif
                        <th class="sepl">{{ __('Gross') }}</th>
                        <th>{{ __('Deductions') }}</th>
                        <th title="{{ __('Added after deductions') }}">{{ __('Bonus') }}</th>
                        <th>{{ __('Net pay') }}</th>
                    </tr>
                </thead>

                @if($isDaily)
                    @php $foot = ['basic' => 0, 'ot' => 0, 'night' => 0, 'rest' => 0, 'hol' => 0, 'leave' => 0, 'gross' => 0, 'min' => 0]; @endphp
                    <tbody>
                    @forelse($days as $day)
                        <tr class="prx-dategrp"><td colspan="{{ $cols }}">{{ \Carbon\Carbon::parse($day['date'])->format('l, M j') }} · {{ count($day['details']) }} {{ count($day['details']) === 1 ? __('shift') : __('shifts') }}</td></tr>
                        @foreach($day['details'] as $d)
                            @php
                                $s      = $slips[$d['employee_id']] ?? null;
                                $isLeave = (bool) ($d['leave'] ?? false);
                                $lv     = (float) ($d['leavePay'] ?? 0);
                                $basic  = round($d['gross'] - ($d['otPay'] ?? 0) - ($d['holidayPay'] ?? 0) - ($d['restDayPay'] ?? 0) - ($d['nightDiffPay'] ?? 0) - $lv, 2);
                                foreach (['basic' => $basic, 'ot' => $d['otPay'] ?? 0, 'night' => $d['nightDiffPay'] ?? 0, 'rest' => $d['restDayPay'] ?? 0,
                                          'hol' => $d['holidayPay'] ?? 0, 'leave' => $lv, 'gross' => $d['gross'], 'min' => $d['minutes'] ?? 0] as $k => $v) { $foot[$k] += $v; }
                                // The whole computation for the row is already
                                // here, so the payslip is built from it rather
                                // than fetched again.
                                $rowSlip = $s ? array_merge($s, ['dailyRate' => (float) $d['dailyRate'], 'rate' => $d['rate'] ?? null]) : null;
                            @endphp
                            <tr class="pr-row" tabindex="0" @if($rowSlip) data-slip="{{ json_encode($rowSlip) }}" @endif>
                                <td class="l emp">
                                    <div class="prx-emp">
                                        <span class="prx-av" style="--c:{{ $s['color'] ?? $palette[0] }}">{{ $s['initial'] ?? mb_substr($d['name'], 0, 1) }}</span>
                                        <div>
                                            <b>{{ $d['name'] }}</b>
                                            <small>{{ $s['code'] ?? '' }}@if(($s['position'] ?? '') !== '') · {{ $s['position'] }}@endif
                                                @if(($d['late_minutes'] ?? 0) > 0)
                                                    <span class="pr-late" title="{{ __('Past the grace period for this shift') }}">{{ $d['late_minutes'] }}m {{ __('late') }}</span>
                                                @endif
                                                @if($isLeave)
                                                    <span class="pr-leave {{ ($d['leave_paid'] ?? false) ? '' : 'unpaid' }}"
                                                          title="{{ ($d['leave_paid'] ?? false) ? __('Approved leave, filed as paid — credited at the daily rate') : __('Approved leave, filed unpaid') }}">{{ $d['leave_type'] }}</span>
                                                @endif
                                            </small>
                                        </div>
                                    </div>
                                </td>
                                <td class="l">@if($d['shift'])<span class="prx-shift {{ $shiftClass($d['shift']) }}">{{ $d['shift'] }}</span>@else — @endif</td>
                                <td>{{ $isLeave ? '—' : $dur($d['minutes']) }}@if(($d['ot_minutes'] ?? 0) > 0)<small>{{ $dur($d['ot_minutes']) }} OT</small>@endif</td>
                                <td>{{ ($d['rate'] ?? null) !== null ? $peso($d['rate']) : '—' }}<small>{{ $peso($d['dailyRate']) }}/{{ __('day') }}</small></td>
                                <td class="sepl {{ $basic ? '' : 'z' }}">{{ $basic ? $peso($basic) : '—' }}</td>
                                {!! $money($d['otPay'] ?? 0) !!}
                                @if($showNight){!! $money($d['nightDiffPay'] ?? 0) !!}@endif
                                {!! $money($d['restDayPay'] ?? 0) !!}
                                {!! $money($d['holidayPay'] ?? 0) !!}
                                @if($showLeave){!! $money($lv) !!}@endif
                                <td class="sepl">{{ $peso($d['gross']) }}</td>
                                <td class="{{ $d['totalDeductions'] ? 'ded' : 'z' }}">{{ $d['totalDeductions'] ? '−' . $peso($d['totalDeductions']) : '—' }}</td>
                                {!! $money($d['bonus'] ?? 0) !!}
                                <td class="net">{{ $peso($d['net']) }}</td>
                            </tr>
                        @endforeach
                    @empty
                        <tr class="prx-empty"><td colspan="{{ $cols }}">{{ __('No payroll records for this day.') }}</td></tr>
                    @endforelse
                    </tbody>
                    {{-- The day's earnings add up across its shifts; its
                         deductions do not. A cash advance instalment is taken
                         from the worker's pay for the period, not from a
                         shift, so the rows under-count it — the summary above
                         has the day's deductions and net. --}}
                    @if(count($days))
                        <tfoot>
                            <tr>
                                <td class="l" colspan="2">{{ __('Day total before deductions & bonus') }}</td>
                                <td>{{ $dur($foot['min']) }}</td>
                                <td></td>
                                <td class="sepl">{{ $peso($foot['basic']) }}</td>
                                <td>{{ $peso($foot['ot']) }}</td>
                                @if($showNight)<td>{{ $peso($foot['night']) }}</td>@endif
                                <td>{{ $peso($foot['rest']) }}</td>
                                <td>{{ $peso($foot['hol']) }}</td>
                                @if($showLeave)<td>{{ $peso($foot['leave']) }}</td>@endif
                                <td class="sepl">{{ $peso($foot['gross']) }}</td>
                                <td></td>
                                <td></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    @endif
                @else
                    <tbody>
                    @forelse($employees as $emp)
                        @php $s = $slips[$emp['employee_id']]; @endphp
                        <tr class="pr-row" tabindex="0" data-slip="{{ json_encode($s) }}">
                            <td class="l emp">
                                <div class="prx-emp">
                                    <span class="prx-av" style="--c:{{ $s['color'] }}">{{ $s['initial'] }}</span>
                                    <div>
                                        <b>{{ $s['name'] }}</b>
                                        <small>{{ $s['code'] }}@if($s['position'] !== '') · {{ $s['position'] }}@endif
                                            @if($s['late'] > 0)
                                                <span class="pr-late" title="{{ __('Total past the grace period this period') }}">{{ $s['late'] }}m {{ __('late') }}</span>
                                            @endif
                                            @if($s['leaveDays'] > 0)
                                                <span class="pr-leave" title="{{ __('Approved leave in this period') }}">{{ __('Leave') }} · {{ rtrim(rtrim(number_format($s['leaveDays'], 2), '0'), '.') }}d</span>
                                            @endif
                                        </small>
                                    </div>
                                </div>
                            </td>
                            <td class="l">@if($s['shift'])<span class="prx-shift {{ $shiftClass($s['shift']) }}">{{ $s['shift'] }}</span>@else — @endif</td>
                            <td>{{ $s['workdays'] }}</td>
                            <td>{{ $s['duration'] }}@if($s['otDur'] !== '')<small>{{ $s['otDur'] }} OT</small>@endif</td>
                            <td>{{ $s['rate'] !== null ? $peso($s['rate']) : '—' }}<small>{{ $peso($s['dailyRate']) }}/{{ __('day') }}</small></td>
                            <td class="sepl {{ $s['regular'] ? '' : 'z' }}">{{ $s['regular'] ? $peso($s['regular']) : '—' }}</td>
                            {!! $money($s['overtime']) !!}
                            @if($showNight){!! $money($s['night']) !!}@endif
                            {!! $money($s['rest']) !!}
                            {!! $money($s['holiday']) !!}
                            @if($showLeave){!! $money($s['leave']) !!}@endif
                            <td class="sepl">{{ $peso($s['gross']) }}</td>
                            <td class="{{ $s['ded'] ? 'ded' : 'z' }}">{{ $s['ded'] ? '−' . $peso($s['ded']) : '—' }}</td>
                            {!! $money($s['bonus']) !!}
                            <td class="net">{{ $peso($s['net']) }}</td>
                        </tr>
                    @empty
                        <tr class="prx-empty"><td colspan="{{ $cols }}">{{ __('No payroll records for this week.') }}</td></tr>
                    @endforelse
                    </tbody>
                    @if(count($employees))
                        <tfoot>
                            <tr>
                                <td class="l" colspan="2">{{ __('Total') }} · {{ count($employees) }}</td>
                                <td>{{ $summary['workdays'] }}</td>
                                <td>{{ $dur($summary['minutes']) }}</td>
                                <td></td>
                                <td class="sepl">{{ $peso($sumOf('regular')) }}</td>
                                <td>{{ $peso($summary['overtime']) }}</td>
                                @if($showNight)<td>{{ $peso($sumOf('night')) }}</td>@endif
                                <td>{{ $peso($summary['restDayPay'] ?? 0) }}</td>
                                <td>{{ $peso($summary['holidayPay']) }}</td>
                                @if($showLeave)<td>{{ $peso($sumOf('leave')) }}</td>@endif
                                <td class="sepl">{{ $peso($summary['gross']) }}</td>
                                <td class="ded">−{{ $peso($summary['totalDeductions']) }}</td>
                                <td>{{ $peso($summary['bonus']) }}</td>
                                <td>{{ $peso($summary['net']) }}</td>
                            </tr>
                        </tfoot>
                    @endif
                @endif
            </table>
        </div>
    </section>

    {{-- ── The payslip panel ───────────────────────────────────────────────
         One shell, filled from whichever row was clicked; the row carries its
         worker's whole slip, so nothing is fetched. --}}
    <aside class="offcanvas offcanvas-end prx-drawer" tabindex="-1" id="prSlip" aria-labelledby="rcName">
        <div class="prx-dh">
            <span class="prx-av" id="rcAv"></span>
            <div><b id="rcName">&mdash;</b><small id="rcMeta">&mdash;</small></div>
            <button type="button" class="prx-ib" data-bs-dismiss="offcanvas" aria-label="{{ __('Close') }}">{!! $icon['close'] !!}</button>
        </div>
        <div class="prx-db">
            <div class="prx-netbox">
                <div><span>{{ __('Net pay') }} · {{ $short }}</span><b id="rcNet">&mdash;</b></div>
                <small id="rcWorked">&mdash;</small>
            </div>
            @unless($isDaily)
                <div class="prx-dsec"><h4>{{ __('Hours per day') }}</h4><div class="prx-days" id="rcDays"></div></div>
            @endunless
            {{-- What the hours were paid at: without it the earnings are a
                 list of figures with nothing to check them against. --}}
            <div class="rc-basis" id="rcBasis">&mdash;</div>
            <div class="prx-dsec"><h4>{{ __('Earnings') }}</h4><ul class="prx-lines" id="rcEarn"></ul></div>
            <div class="prx-dsec"><h4>{{ __('Deductions') }}</h4><ul class="prx-lines" id="rcDedList"></ul></div>
            <div class="rc-math">
                <div class="rc-math-item"><span>{{ __('Gross') }}</span><b id="rcMGross">&mdash;</b></div>
                <div class="rc-math-item"><span>{{ __('− Deductions') }}</span><b id="rcMDed">&mdash;</b></div>
                <div class="rc-math-item"><span>{{ __('+ Bonus') }}</span><b id="rcMBonus">&mdash;</b></div>
            </div>
        </div>
        <div class="prx-df">
            <button type="button" class="prx-btn" id="rcPreview">{{ __('Preview payslip') }}</button>
            <a href="#" target="_blank" rel="noopener" id="rcPrint" class="prx-btn pri">{!! $icon['print'] !!} {{ __('Print / Save as PDF') }}</a>
        </div>
    </aside>

    {{-- ── Preview & download ──────────────────────────────────────────────
         A payslip (one worker, or every worker in the period) prints from the
         payslip page and saves as PDF from there; the register downloads as
         the Excel file, and what is previewed is exactly what it contains.
         Every payslip at once prints the whole period, so it is offered only
         while the list is not narrowed by a search. --}}
    <div class="modal fade prx-modal" id="exportPreviewModal" tabindex="-1" aria-labelledby="pvTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="prx-mh">
                    <h3 id="pvTitle">{{ __('Preview & download') }}</h3>
                    <small>{{ ucfirst($period['mode']) }} · {{ $period['label'] }} · {{ count($employees) }} {{ count($employees) === 1 ? __('employee') : __('employees') }}</small>
                    <span class="sp"></span>
                    <button type="button" class="prx-ib" data-bs-dismiss="modal" aria-label="{{ __('Close') }}">{!! $icon['close'] !!}</button>
                </div>
                <div class="modal-body prx-mb">
                    <div class="prx-opts" role="group" aria-label="{{ __('What to download') }}">
                        <button type="button" class="prx-opt" data-pv="single"><b>{{ __('Single payslip') }}</b><small>{{ __('One employee · print or save as PDF') }}</small></button>
                        @if($search === '')
                            <button type="button" class="prx-opt" data-pv="all"><b>{{ __('All payslips') }}</b><small>{{ __('Every employee this') }} {{ $unit }} · {{ count($employees) }} {{ count($employees) === 1 ? __('page') : __('pages') }}</small></button>
                        @endif
                        <button type="button" class="prx-opt" data-pv="register"><b>{{ __('Payroll register') }}</b><small>{{ __('Summary table · Excel') }}</small></button>
                    </div>

                    <div data-pane="single" hidden>
                        <label class="prx-sel prx-pv-pick">
                            <select id="pvEmp" aria-label="{{ __('Employee') }}"></select>
                        </label>
                        <div id="pvSingle"></div>
                    </div>
                    <div data-pane="all" id="pvAll" hidden></div>
                    <div data-pane="register" hidden>
                        <div class="prx-slip reg">
                            <header>
                                <div><b>{{ __('PAYROLL REGISTER') }}</b><small>{{ ucfirst($period['mode']) }} · {{ $period['label'] }}</small></div>
                                <div style="text-align:right"><small>{{ $company?->company_name ?? 'JEYANCO CONSTRUCTION' }}</small></div>
                            </header>
                            <table>
                                <thead>
                                    <tr>
                                        <th class="l">{{ __('ID') }}</th><th class="l">{{ __('Name') }}</th><th class="l">{{ __('Position') }}</th>
                                        <th>{{ __('Workdays') }}</th><th>{{ __('Hours') }}</th>
                                        <th>{{ __('Gross') }}</th><th>{{ __('Overtime') }}</th>
                                        <th>{{ __('Holiday') }}</th><th>{{ __('Rest Day') }}</th>
                                        <th>{{ __('Bonus') }}</th><th>{{ __('Deductions') }}</th>
                                        <th>{{ __('Net Pay') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($employees as $e)
                                        @php $t = $e['totals']; @endphp
                                        <tr>
                                            <td class="l">#{{ str_pad($e['employee_id'], 4, '0', STR_PAD_LEFT) }}</td>
                                            <td class="l" style="font-weight:600">{{ $e['name'] }}</td>
                                            <td class="l">{{ $e['position'] }}</td>
                                            <td>{{ $t['workdays'] }}</td>
                                            <td>{{ number_format($t['hours'], 2) }}</td>
                                            <td>₱{{ number_format($t['gross'], 2) }}</td>
                                            <td>₱{{ number_format($t['overtime'], 2) }}</td>
                                            <td>₱{{ number_format($t['holidayPay'], 2) }}</td>
                                            <td>₱{{ number_format($t['restDayPay'] ?? 0, 2) }}</td>
                                            <td>₱{{ number_format($t['bonus'], 2) }}</td>
                                            <td style="color:#b91c1c">₱{{ number_format($t['totalDeductions'], 2) }}</td>
                                            <td style="font-weight:700">₱{{ number_format($t['net'], 2) }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="12" class="l" style="text-align:center;padding:24px;color:#56657d">{{ __('No payroll data for this period.') }}</td></tr>
                                    @endforelse
                                </tbody>
                                @if(count($employees))
                                    <tfoot>
                                        <tr>
                                            <td class="l" colspan="3">{{ __('TOTAL') }}</td>
                                            <td>{{ $summary['workdays'] }}</td>
                                            <td>{{ number_format($summary['hours'], 2) }}</td>
                                            <td>₱{{ number_format($summary['gross'], 2) }}</td>
                                            <td>₱{{ number_format($summary['overtime'], 2) }}</td>
                                            <td>₱{{ number_format($summary['holidayPay'], 2) }}</td>
                                            <td>₱{{ number_format($summary['restDayPay'], 2) }}</td>
                                            <td>₱{{ number_format($summary['bonus'], 2) }}</td>
                                            <td>₱{{ number_format($summary['totalDeductions'], 2) }}</td>
                                            <td>₱{{ number_format($summary['net'], 2) }}</td>
                                        </tr>
                                    </tfoot>
                                @endif
                            </table>
                        </div>
                    </div>
                </div>
                <div class="prx-mf">
                    <span class="note" id="pvNote"></span>
                    <button type="button" class="prx-btn" data-bs-dismiss="modal">{{ __('Close') }}</button>
                    <a href="#" class="prx-btn pri" id="pvGo" target="_blank" rel="noopener">{{ __('Download') }}</a>
                </div>
            </div>
        </div>
    </div>

</div>{{-- /prx --}}

@include('modules._fill_screen')
@endsection

@push('scripts')
@php
    // Last resort only. Each row carries the hourly rate payroll priced it
    // at, which is the honest figure: how many hours a day's rate buys is a
    // property of the shift, and two crews need not agree on it. This
    // office-wide sum is what remains for a row from before that was true.
    $sysDay    = \App\Models\SystemSetting::current();
    $paidHours = max(1, (float) $sysDay->standard_hours_per_day - (int) $sysDay->unpaid_break_minutes / 60);

    // Built here: Blade's @json splits its argument on commas, so an array
    // written inside it does not survive.
    $jsPeriod = ['label' => $period['label'], 'short' => $short, 'daily' => $isDaily];
    $jsCompany = [
        'name'    => $company?->company_name ?? 'JEYANCO CONSTRUCTION',
        'tagline' => $company?->company_tagline ?? 'Payroll Dept. · Panganiban, PH',
        'logo'    => $company?->logoUrl() ?? asset('images/JeyancoLogo.png'),
    ];
    $jsPrint = route('payslip.batch', ['from' => $period['from'], 'to' => $period['to']]);
    $jsExcel = route('payroll-records.export.excel', request()->query());
@endphp
<script>
(function () {
    const root = document.querySelector('.prx');
    if (!root) return;

    const RATES      = @json($rates);
    const PAID_HOURS = {{ $paidHours }};
    const PERIOD     = @json($jsPeriod);
    const CO         = @json($jsCompany);
    const PRINT_URL  = @json($jsPrint);
    const EXCEL_URL  = @json($jsExcel);

    const fmt   = new Intl.NumberFormat('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const money = n => '₱' + fmt.format(Number(n) || 0);
    const num   = n => Number(n) || 0;
    const esc   = t => String(t ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const $     = id => document.getElementById(id);
    const set   = (id, text) => { const el = $(id); if (el) el.textContent = text; };

    // ── Period bar ──────────────────────────────────────────────────────
    const form = $('prFilter');
    form.querySelectorAll('[data-mode]').forEach(b => b.addEventListener('click', () => {
        $('mode').value = b.dataset.mode;
        form.submit();
    }));
    // The label opens the browser's own week or date picker; one without a
    // picker shows the field instead, to type into.
    const pick = $('prPick');
    $('prWhen').addEventListener('click', () => {
        try { pick.showPicker(); }
        catch (e) { pick.classList.add('is-shown'); pick.focus(); }
    });
    pick.addEventListener('change', () => { if (pick.value) form.submit(); });

    // ── What each line of a slip is, and what produced it ───────────────
    // Each line names its own multiplier or rate, because "why is this 250"
    // is the question a column of totals cannot answer.
    function earnings(s) {
        return [
            ['Regular pay (' + s.workdays + 'd)', s.regular, true],
            ['Overtime (×' + num(RATES.ot_multiplier).toFixed(2) + ')' + (s.otDur ? ' · ' + s.otDur : ''), s.overtime],
            ['Night differential (×' + num(RATES.night_diff_multiplier).toFixed(2) + ')', s.night],
            ['Holiday pay (×' + num(RATES.regular_holiday_multiplier).toFixed(2) + ')', s.holiday],
            ['Rest day pay (×' + num(RATES.rest_day_multiplier).toFixed(2) + ')', s.rest],
            // Approved leave that was filed as paid, credited at the day rate.
            [num(s.leaveDays) > 0 ? 'Paid leave (' + (Math.round(num(s.leaveDays) * 100) / 100) + 'd)' : 'Paid leave', s.leave],
        ].filter(l => l[2] || num(l[1]) !== 0);
    }
    function deductions(s) {
        return [
            ['SSS (' + num(RATES.sss_rate).toFixed(2) + '%)', s.sss],
            ['PhilHealth (' + num(RATES.philhealth_rate).toFixed(2) + '%)', s.phil],
            ['Pag-IBIG (' + num(RATES.pagibig_rate).toFixed(2) + '%)', s.pag],
            [RATES.withholding_tax ? 'Withholding tax (BIR)' : 'Withholding tax (off)', s.tax],
            // A cash advance instalment this period's pay could not cover is
            // not taken; the line says so, so a smaller vale does not look like
            // a forgotten deduction.
            ['Vale / cash advance'
                + (num(s.advance) > 0 ? ' (' + money(s.advance) + ' instalment)' : '')
                + (num(s.deferred) > 0 ? ' — ' + money(s.deferred) + ' deferred, pay too low' : ''), s.vale, num(s.deferred) > 0],
            ['Other adjustments', s.other],
        ].filter(l => l[2] || num(l[1]) !== 0);
    }
    function hourly(s) {
        // Zero is a real answer — a contractual worker is settled against
        // their contract — so this tests for a missing figure, not a falsy one.
        return (s.rate === undefined || s.rate === null) ? num(s.dailyRate) / PAID_HOURS : num(s.rate);
    }
    function meta(s) {
        return s.code + (s.position ? ' · ' + s.position : '') + (s.shift ? ' · ' + s.shift + ' shift' : '');
    }

    // ── The payslip panel ───────────────────────────────────────────────
    const drawerEl = $('prSlip');
    let current = null;

    function openSlip(row) {
        let s;
        try { s = JSON.parse(row.dataset.slip); } catch (e) { return; }
        current = s;

        const av = $('rcAv');
        av.textContent = s.initial;
        av.style.setProperty('--c', s.color);
        set('rcName', s.name);
        set('rcMeta', meta(s));
        set('rcNet', money(s.net));
        set('rcWorked', s.duration + ' over ' + s.workdays + ' day' + (s.workdays === 1 ? '' : 's')
                      + (s.late > 0 ? ' · ' + s.late + 'm late' : ''));

        const days = $('rcDays');
        if (days) {
            days.innerHTML = (s.days || []).map(d => {
                const cls  = d.leave ? 'lv' : (d.dur ? 'w' : '');
                const main = d.leave ? 'Leave' : (d.dur || '–');
                const note = d.leave ? '<em>' + esc(d.leave) + '</em>' : (d.ot ? '<em>+' + esc(d.ot) + ' OT</em>' : '');
                return '<div class="' + cls + '">' + esc(d.label) + '<b>' + esc(main) + '</b>' + note + '</div>';
            }).join('');
        }

        set('rcBasis', money(s.dailyRate) + '/day · ' + money(hourly(s)) + '/hr · '
                     + s.workdays + ' day' + (s.workdays === 1 ? '' : 's') + ' worked'
                     + (s.late > 0 ? ' · ' + s.late + 'm late' : ''));

        const li = (k, v, neg) => '<li><span>' + esc(k) + '</span><b>' + (neg ? '−' : '') + money(v) + '</b></li>';
        $('rcEarn').innerHTML = earnings(s).map(l => li(l[0], l[1])).join('')
            + '<li class="tot"><span>Gross pay</span><b>' + money(s.gross) + '</b></li>';
        $('rcDedList').innerHTML = (deductions(s).map(l => li(l[0], l[1], true)).join('') || '<li><span>None this period</span><b>' + money(0) + '</b></li>')
            + '<li class="tot"><span>Total deductions</span><b>−' + money(s.ded) + '</b></li>';

        set('rcMGross', money(s.gross));
        set('rcMDed',   money(s.ded));
        set('rcMBonus', money(s.bonus));

        $('rcPrint').href = PRINT_URL + '&employee=' + encodeURIComponent(s.id);
        bootstrap.Offcanvas.getOrCreateInstance(drawerEl).show();
    }

    // Delegated, so rows the live feed has replaced still open.
    const list = $('prBreakdown');
    list.addEventListener('click', e => {
        const row = e.target.closest('tr.pr-row[data-slip]');
        if (row) openSlip(row);
    });
    list.addEventListener('keydown', e => {
        if ((e.key === 'Enter' || e.key === ' ') && e.target.matches('tr.pr-row[data-slip]')) {
            e.preventDefault();
            openSlip(e.target);
        }
    });

    // ── Preview & download ──────────────────────────────────────────────
    const modalEl = $('exportPreviewModal');
    let pane = 'single', chosen = null;

    /** Every worker's slip on the page, once each, in the order listed. */
    function slips() {
        const seen = new Map();
        list.querySelectorAll('tr.pr-row[data-slip]').forEach(r => {
            try {
                const s = JSON.parse(r.dataset.slip);
                if (!seen.has(String(s.id))) seen.set(String(s.id), s);
            } catch (e) {}
        });
        return [...seen.values()];
    }

    function paper(s) {
        const row = (k, v, neg) => '<div class="r"><span>' + esc(k) + '</span><span>' + (neg ? '−' : '') + money(v) + '</span></div>';
        return '<article class="prx-slip">'
            + '<header><div class="co"><img src="' + esc(CO.logo) + '" alt=""><div><b>' + esc(CO.name) + '</b><small>' + esc(CO.tagline) + '</small></div></div>'
            + '<div style="text-align:right"><b style="font-size:13px;letter-spacing:.2em">PAYSLIP</b><small>' + esc(PERIOD.label) + '</small></div></header>'
            + '<div class="who">'
            + '<div><span>Employee:</span> <b>' + esc(s.name) + '</b></div><div><span>ID:</span> ' + esc(s.code) + '</div>'
            + '<div><span>Position:</span> ' + esc(s.position || '—') + '</div><div><span>Shift:</span> ' + esc(s.shift || '—') + '</div>'
            + '<div><span>Rate:</span> ' + money(hourly(s)) + '/hr · ' + money(s.dailyRate) + '/day</div>'
            + '<div><span>Days / hours:</span> ' + s.workdays + ' · ' + esc(s.duration) + '</div></div>'
            + '<div class="two"><div><h5>Earnings</h5>' + earnings(s).map(l => row(l[0], l[1])).join('')
            + '<div class="r t"><span>Gross</span><span>' + money(s.gross) + '</span></div></div>'
            + '<div><h5>Deductions</h5>' + (deductions(s).map(l => row(l[0], l[1], true)).join('') || '<div class="r none"><span>None this period</span><span>—</span></div>')
            + '<div class="r t"><span>Total</span><span>−' + money(s.ded) + '</span></div></div></div>'
            + (num(s.bonus) > 0 ? '<div class="bonus"><span>+ Bonus (added after deductions)</span><span>' + money(s.bonus) + '</span></div>' : '')
            + '<div class="netline"><span>NET PAY</span><span>' + money(s.net) + '</span></div>'
            + '<div class="sign"><div>Prepared by</div><div>Received by</div></div>'
            + '</article>';
    }

    function renderPv() {
        const all = slips();
        if (!all.some(s => String(s.id) === chosen)) chosen = all[0] ? String(all[0].id) : null;
        if (pane === 'all' && !modalEl.querySelector('[data-pv="all"]')) pane = 'single';

        modalEl.querySelectorAll('[data-pv]').forEach(b => {
            b.classList.toggle('on', b.dataset.pv === pane);
            b.setAttribute('aria-pressed', b.dataset.pv === pane ? 'true' : 'false');
        });
        modalEl.querySelectorAll('[data-pane]').forEach(p => { p.hidden = p.dataset.pane !== pane; });
        modalEl.classList.toggle('is-wide', pane === 'register');

        const empty = '<div class="prx-pv-empty">No payroll records for this period.</div>';
        const go = $('pvGo');
        let off = false;

        if (pane === 'single') {
            const sel = $('pvEmp');
            sel.innerHTML = all.map(s => '<option value="' + esc(s.id) + '"' + (String(s.id) === chosen ? ' selected' : '') + '>' + esc(s.name) + '</option>').join('');
            sel.closest('.prx-pv-pick').hidden = !all.length;
            const s = all.find(x => String(x.id) === chosen);
            $('pvSingle').innerHTML = s ? paper(s) : empty;
            go.href = s ? PRINT_URL + '&employee=' + encodeURIComponent(s.id) : '#';
            go.textContent = 'Print / Save as PDF';
            set('pvNote', 'Opens the payslip to print, or to save as PDF.');
            off = !s;
        } else if (pane === 'all') {
            $('pvAll').innerHTML = all.length ? all.map(paper).join('') : empty;
            go.href = PRINT_URL;
            go.textContent = 'Print / Save as PDF';
            set('pvNote', 'Every payslip in the period, one page each.');
            off = !all.length;
        } else {
            go.href = EXCEL_URL;
            go.textContent = 'Download Excel';
            set('pvNote', 'This is exactly what the Excel file will contain.');
        }
        go.setAttribute('aria-disabled', off ? 'true' : 'false');
        // The Excel file downloads in place; a payslip opens its own page.
        if (pane === 'register') { go.removeAttribute('target'); } else { go.target = '_blank'; }
    }

    function openPv(kind, id) {
        pane = kind;
        if (id !== undefined && id !== null) chosen = String(id);
        renderPv();
        const show = () => bootstrap.Modal.getOrCreateInstance(modalEl).show();
        // One layer at a time: the panel closes before the preview opens.
        if (drawerEl.classList.contains('show')) {
            drawerEl.addEventListener('hidden.bs.offcanvas', show, { once: true });
            bootstrap.Offcanvas.getOrCreateInstance(drawerEl).hide();
        } else {
            show();
        }
    }

    root.addEventListener('click', e => {
        const b = e.target.closest('[data-prx-preview]');
        if (b) openPv(b.dataset.prxPreview);
    });
    $('rcPreview').addEventListener('click', () => { if (current) openPv('single', current.id); });
    modalEl.addEventListener('click', e => {
        const b = e.target.closest('[data-pv]');
        if (b) { pane = b.dataset.pv; renderPv(); }
    });
    $('pvEmp').addEventListener('change', e => { chosen = e.target.value; renderPv(); });

    // The live feed rewrites the list in place, and with it the height the
    // list was fitted to; ask for the fit again.
    document.addEventListener('live:updated', () => {
        document.dispatchEvent(new Event('fill-screen:refit'));
    });
})();
</script>
@endpush
