@extends('layouts')
@section('page_title', 'Payroll Processing')

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@2.47.0/tabler-icons.min.css">
<style>
/* Payroll Processing. Every name is prefixed: the reference's own (.card,
   .btn, .row, .badge, .field…) are ones Bootstrap and the app's global styles
   already claim. Light is the base palette; dark is the reference's. */
.pp {
    --pp-panel: #FFFFFF;  --pp-panel-2: #F8F9FB;
    --pp-line: #E4E7EC;   --pp-line-2: #D0D5DD;  --pp-bw: 1px;
    --pp-txt: #101828;    --pp-txt-2: #475467;   --pp-txt-3: #667085;
    --pp-accent: #3B82F6; --pp-accent-soft: rgba(59, 130, 246, .12); --pp-accent-bold: #185FA5; --pp-accent-txt: #2563EB;
    --pp-green-soft: rgba(34, 197, 94, .12);  --pp-green-txt: #16A34A;
    --pp-red-soft: rgba(239, 68, 68, .12);    --pp-red-txt: #DC2626;
    --pp-amber-soft: rgba(245, 158, 11, .14); --pp-amber-txt: #B45309;
    --pp-radius: 12px;

    max-width: 1400px; margin: 0 auto;
    color: var(--pp-txt);
    font-family: 'Inter', system-ui, -apple-system, sans-serif;
    font-size: 14px; line-height: 1.5; -webkit-font-smoothing: antialiased;
}
html[data-bs-theme="dark"] .pp {
    --pp-panel: #111827;  --pp-panel-2: #0E1524;
    --pp-line: rgba(255, 255, 255, .07); --pp-line-2: rgba(255, 255, 255, .12); --pp-bw: .5px;
    --pp-txt: #E7ECF3;    --pp-txt-2: #9CA9BD;   --pp-txt-3: #64748B;
    --pp-accent-soft: rgba(59, 130, 246, .14); --pp-accent-txt: #60A5FA;
    --pp-green-soft: rgba(34, 197, 94, .14);   --pp-green-txt: #4ADE80;
    --pp-red-soft: rgba(239, 68, 68, .14);     --pp-red-txt: #F87171;
    --pp-amber-soft: rgba(245, 158, 11, .14);  --pp-amber-txt: #FBBF24;
}
.pp *, .pp *::before, .pp *::after { box-sizing: border-box; }
.pp h1, .pp h3, .pp h4, .pp p, .pp ol { margin: 0; }
.pp button { font-family: inherit; }
.pp a { text-decoration: none; }

/* Header */
.pp-head { margin-bottom: 20px; }
.pp-crumb { font-size: 12px; color: var(--pp-txt-3); margin-bottom: 5px; }
.pp-crumb b { color: var(--pp-txt-2); font-weight: 500; }
.pp .pp-head h1 { font-size: 21px !important; font-weight: 600 !important; letter-spacing: -.01em !important; line-height: 1.3; color: var(--pp-txt); }
.pp-head p { font-size: 13px; color: var(--pp-txt-2); margin-top: 4px; }

/* Layout */
.pp-layout { display: grid; grid-template-columns: 310px minmax(0, 1fr); gap: 18px; align-items: start; }

/* Picker */
.pp-picker { background: var(--pp-panel); border: var(--pp-bw) solid var(--pp-line); border-radius: var(--pp-radius); overflow: hidden; position: sticky; top: 16px; }
.pp-top { padding: 12px; border-bottom: var(--pp-bw) solid var(--pp-line); display: flex; flex-direction: column; gap: 8px; margin: 0; }
.pp-field { display: flex; align-items: center; gap: 8px; background: var(--pp-panel-2); border: var(--pp-bw) solid var(--pp-line-2); border-radius: 9px; padding: 0 10px; height: 36px; margin: 0; transition: border-color .15s, box-shadow .15s; }
.pp-field:focus-within { border-color: var(--pp-accent); box-shadow: 0 0 0 3px var(--pp-accent-soft); }
.pp-field i { font-size: 15px; color: var(--pp-txt-3); }
.pp-field input, .pp-field select { border: none; background: none; outline: none; box-shadow: none; color: var(--pp-txt); font-size: 13px; height: 34px; flex: 1; min-width: 0; padding: 0; font-family: inherit; }
.pp-field select { cursor: pointer; }
.pp-field select option, .pp-field select optgroup { background: var(--pp-panel); color: var(--pp-txt); }
.pp-field input::placeholder { color: var(--pp-txt-3); }
.pp-plist { max-height: 540px; overflow-y: auto; position: relative; }
.pp-pitem { width: 100%; border-left: 3px solid transparent; padding: 11px 14px; display: flex; align-items: center; gap: 10px; color: inherit; transition: background .12s; }
.pp-pitem:hover { background: var(--pp-panel-2); color: inherit; }
.pp-pitem.sel { background: var(--pp-accent-soft); border-left-color: var(--pp-accent); }
.pp-av { width: 34px; height: 34px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 600; flex-shrink: 0; }
.pp-pmain { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.pp-nm { font-size: 13px; font-weight: 500; color: var(--pp-txt); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.pp-mt { font-size: 11px; color: var(--pp-txt-3); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.pp-chev { margin-left: auto; color: var(--pp-accent); font-size: 16px; opacity: 0; }
.pp-pitem.sel .pp-chev { opacity: 1; }
.pp-pitem.idle .pp-nm { color: var(--pp-txt-2); }
.pp-pitem.idle .pp-av { opacity: .55; }
.pp-pnone, .pp-pempty { padding: 24px; text-align: center; color: var(--pp-txt-3); font-size: 13px; }

/* Right panel */
.pp-rpanel { min-width: 0; }
.pp-emp { display: flex; align-items: center; gap: 14px; background: var(--pp-panel); border: var(--pp-bw) solid var(--pp-line); border-radius: var(--pp-radius); padding: 16px 18px; margin-bottom: 16px; flex-wrap: wrap; }
.pp-big-av { width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 18px; font-weight: 600; flex-shrink: 0; }
.pp-info { flex: 1; min-width: 0; }
.pp-n { font-size: 16px; font-weight: 600; }
.pp-s { font-size: 12.5px; color: var(--pp-txt-2); margin-top: 2px; display: flex; gap: 4px 10px; flex-wrap: wrap; }
.pp-s span { display: inline-flex; align-items: center; gap: 4px; }
.pp-s i { font-size: 13px; color: var(--pp-txt-3); }
.pp-per { display: inline-flex; align-items: center; gap: 6px; background: var(--pp-panel-2); border: var(--pp-bw) solid var(--pp-line-2); border-radius: 20px; padding: 6px 12px; font-size: 12px; color: var(--pp-txt-2); white-space: nowrap; }

/* The three choices */
.pp-menu { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; margin-bottom: 16px; }
.pp-mtab { position: relative; display: block; background: var(--pp-panel); border: var(--pp-bw) solid var(--pp-line); border-radius: var(--pp-radius); padding: 16px; color: var(--pp-txt); overflow: hidden; transition: border-color .18s, transform .18s, background .18s; }
.pp-mtab:hover { border-color: var(--pp-line-2); transform: translateY(-2px); color: var(--pp-txt); }
.pp-mtab.on { border-color: var(--pp-accent); background: linear-gradient(180deg, var(--pp-accent-soft), transparent) var(--pp-panel); }
.pp-mi { width: 38px; height: 38px; border-radius: 9px; display: flex; align-items: center; justify-content: center; font-size: 20px; margin-bottom: 12px; }
.pp-mtitle { display: block; font-size: 14px; font-weight: 600; }
.pp-md { display: block; font-size: 12px; color: var(--pp-txt-2); margin-top: 3px; line-height: 1.45; }
.pp-go { position: absolute; top: 16px; right: 16px; color: var(--pp-accent); font-size: 18px; opacity: 0; transition: opacity .18s; }
.pp-mtab.on .pp-go, .pp-mtab:hover .pp-go { opacity: 1; }
.pp-i-blue  { background: var(--pp-accent-soft); color: var(--pp-accent-txt); }
.pp-i-amber { background: var(--pp-amber-soft);  color: var(--pp-amber-txt); }
.pp-i-green { background: var(--pp-green-soft);  color: var(--pp-green-txt); }

/* A view */
.pp-view { background: var(--pp-panel); border: var(--pp-bw) solid var(--pp-line); border-radius: var(--pp-radius); overflow: hidden; }
.pp-view-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 14px 18px; border-bottom: var(--pp-bw) solid var(--pp-line); flex-wrap: wrap; }
.pp .pp-view-head h3 { font-size: 15px !important; font-weight: 600 !important; display: flex; align-items: center; gap: 8px; color: var(--pp-txt); }
.pp-view-body { padding: 18px; }

/* Buttons, badges, bits */
.pp-btn { height: 34px; display: inline-flex; align-items: center; gap: 6px; border-radius: 8px; font-size: 12.5px; font-weight: 500; padding: 0 13px; cursor: pointer; border: var(--pp-bw) solid var(--pp-line-2); background: var(--pp-panel-2); color: var(--pp-txt-2); white-space: nowrap; transition: background .15s, filter .15s, color .15s; }
.pp-btn:hover { background: var(--pp-panel); color: var(--pp-txt); }
.pp-btn.primary { background: var(--pp-accent-bold); border-color: transparent; color: #fff; }
.pp-btn.primary:hover { filter: brightness(1.1); color: #fff; }
.pp-btn.ghost { background: transparent; }
.pp-btn.sm { height: 30px; padding: 0 11px; }
.pp-btn.icon { width: 30px; padding: 0; justify-content: center; }
.pp-btn:disabled { opacity: .5; cursor: not-allowed; filter: none; }
.pp-btn i { font-size: 15px; }
.pp-badge { display: inline-flex; align-items: center; gap: 5px; font-size: 12px; font-weight: 500; padding: 4px 11px; border-radius: 20px; white-space: nowrap; }
.pp-badge i { font-size: 13px; }
.pp-b-green { background: var(--pp-green-soft);  color: var(--pp-green-txt); }
.pp-b-blue  { background: var(--pp-accent-soft); color: var(--pp-accent-txt); }
.pp-b-amber { background: var(--pp-amber-soft);  color: var(--pp-amber-txt); }
.pp-b-muted { background: var(--pp-panel-2); color: var(--pp-txt-3); border: var(--pp-bw) solid var(--pp-line); }
.pp-num { font-variant-numeric: tabular-nums; }
.pp-muted { color: var(--pp-txt-3); }
.pp-c-accent { color: var(--pp-accent-txt); }
.pp-c-amber  { color: var(--pp-amber-txt); }
.pp-c-green  { color: var(--pp-green-txt); }
.pp-c-red    { color: var(--pp-red-txt); }

/* Salary computation */
.pp-facts { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; margin-bottom: 14px; }
.pp-fact { background: var(--pp-panel-2); border: var(--pp-bw) solid var(--pp-line); border-radius: 10px; padding: 9px 12px; }
.pp-fact span { display: block; font-size: 10.5px; font-weight: 600; color: var(--pp-txt-3); text-transform: uppercase; letter-spacing: .5px; }
.pp-fact b { display: block; font-size: 15px; font-weight: 600; margin-top: 2px; font-variant-numeric: tabular-nums; }
.pp-flow { list-style: none; padding: 0; display: grid; grid-template-columns: repeat(4, minmax(0, 1fr) auto) minmax(0, 1fr); gap: 8px; margin-bottom: 16px; }
.pp-stage { background: var(--pp-panel-2); border: var(--pp-bw) solid var(--pp-line); border-radius: 10px; padding: 10px 12px; min-width: 0; }
.pp-stage .k { display: block; font-size: 10.5px; font-weight: 600; color: var(--pp-txt-3); text-transform: uppercase; letter-spacing: .5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.pp-stage b { display: block; font-size: 15px; font-weight: 600; margin-top: 3px; font-variant-numeric: tabular-nums; white-space: nowrap; }
.pp-stage small { display: block; font-size: 11px; color: var(--pp-txt-3); margin-top: 2px; line-height: 1.35; }
.pp-stage.is-gross b { color: var(--pp-green-txt); }
.pp-stage.is-ded b { color: var(--pp-red-txt); }
.pp-stage.is-net { border-color: var(--pp-accent); background: linear-gradient(180deg, var(--pp-accent-soft), transparent) var(--pp-panel-2); }
.pp-op { align-self: center; color: var(--pp-txt-3); font-size: 16px; font-weight: 600; text-align: center; }
.pp-wf { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.pp-box { background: var(--pp-panel-2); border: var(--pp-bw) solid var(--pp-line); border-radius: 10px; overflow: hidden; display: flex; flex-direction: column; }
.pp-bh { display: flex; align-items: center; gap: 8px; padding: 11px 14px; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: .5px; }
.pp-box.earn .pp-bh { background: var(--pp-green-soft); color: var(--pp-green-txt); }
.pp-box.ded .pp-bh  { background: var(--pp-red-soft);   color: var(--pp-red-txt); }
.pp-bb { padding: 4px 14px; flex: 1; }
.pp-foot { display: flex; justify-content: space-between; padding: 11px 14px; border-top: var(--pp-bw) solid var(--pp-line); background: var(--pp-panel); font-weight: 600; font-size: 13px; }
.pp-row { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 9px 0; font-size: 13px; border-bottom: 1px solid var(--pp-line); }
.pp-row:last-child { border-bottom: none; }
.pp-row .l { color: var(--pp-txt-2); display: flex; align-items: center; gap: 9px; min-width: 0; }
.pp-row .l i { font-size: 15px; color: var(--pp-txt-3); width: 18px; text-align: center; flex-shrink: 0; }
.pp-row .l small { color: var(--pp-txt-3); display: block; font-size: 11px; margin-top: 1px; }
.pp-row .a { font-variant-numeric: tabular-nums; white-space: nowrap; color: var(--pp-txt); }
.pp-row.zero .a { color: var(--pp-txt-3); }
.pp-net-strip { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; background: linear-gradient(90deg, var(--pp-accent-soft), transparent); border: var(--pp-bw) solid var(--pp-accent); border-radius: 10px; padding: 14px 18px; margin-top: 14px; }
.pp-net-strip .k { font-size: 12px; color: var(--pp-txt-2); text-transform: uppercase; letter-spacing: .5px; }
.pp-net-strip .k span { color: var(--pp-txt-3); text-transform: none; letter-spacing: 0; }
.pp-net-strip .v { font-size: 22px; font-weight: 600; font-variant-numeric: tabular-nums; }

/* Remittance tracker */
.pp-note { display: flex; align-items: flex-start; gap: 8px; margin: 14px 18px 0; padding: 10px 12px; border-radius: 9px; background: var(--pp-amber-soft); color: var(--pp-txt-2); font-size: 12.5px; }
.pp-note i { color: var(--pp-amber-txt); font-size: 16px; margin-top: 1px; }
.pp-note.inline { margin: 0 0 14px; }
.pp-tablewrap { overflow-x: auto; margin-top: 14px; }
.pp-table { width: 100%; border-collapse: collapse; font-size: 13px; margin: 0; }
.pp-table thead th { text-align: left; padding: 10px 14px; font-size: 10.5px; font-weight: 500; color: var(--pp-txt-3); text-transform: uppercase; letter-spacing: .5px; background: var(--pp-panel-2); white-space: nowrap; border: none; }
.pp-table tbody td { padding: 12px 14px; border-top: var(--pp-bw) solid var(--pp-line); vertical-align: middle; color: var(--pp-txt); background: transparent; }
.pp-table .r { text-align: right; }
.pp-ag { display: flex; align-items: center; gap: 10px; }
.pp-ag-ico { width: 32px; height: 32px; border-radius: 8px; background: var(--pp-panel-2); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.pp-ag-ico i { font-size: 16px; color: var(--pp-accent-txt); }
.pp-ag-n { font-weight: 500; }
.pp-ag-s { font-size: 11px; color: var(--pp-txt-3); margin-top: 1px; }
.pp-ag-id { font-size: 11.5px; color: var(--pp-txt-2); margin-top: 2px; font-variant-numeric: tabular-nums; }
.pp-ag-id b { color: var(--pp-txt); font-weight: 600; letter-spacing: .2px; }
.pp-ag-id.missing { color: var(--pp-amber-txt); display: inline-flex; align-items: center; gap: 4px; }
.pp-by { font-size: 11px; color: var(--pp-txt-3); margin-top: 4px; white-space: nowrap; }
.pp-acts { display: inline-flex; align-items: center; gap: 6px; justify-content: flex-end; }
.pp-acts form { margin: 0; }
.pp-done { color: var(--pp-green-txt); font-size: 12px; display: inline-flex; align-items: center; gap: 4px; white-space: nowrap; }

/* Payslip — the Payroll Records receipt, rule for rule, so the two pages hand
   a worker the same slip. Its colours are the app's own tokens, which follow
   the theme. */
.emp-slip { background: var(--surface); border: 1px solid var(--border); border-radius: 6px; padding: 14px 16px; height: 100%; }
.emp-slip-head { display: flex; align-items: center; gap: 10px; border-bottom: 1.5px solid var(--brand); padding-bottom: 8px; margin-bottom: 10px; }
.emp-slip-logo { width: 34px; height: 34px; border-radius: 50%; object-fit: cover; border: 1px solid var(--border); flex-shrink: 0; }
.emp-slip-co { flex: 1; min-width: 0; }
.emp-slip-co .co  { font-size: 12.5px; font-weight: 800; color: var(--brand); letter-spacing: .3px; line-height: 1.1; }
.emp-slip-co .sub { font-size: 9.5px; color: var(--text-secondary); }
.emp-slip-doc { text-align: right; }
.emp-slip-doc .lbl { font-size: 12px; font-weight: 800; letter-spacing: 2px; color: var(--text-secondary); }
.emp-slip-doc .per { font-size: 9px; color: var(--text-secondary); }
.emp-slip-emp { display: flex; justify-content: space-between; gap: 8px; margin-bottom: 10px; flex-wrap: wrap; }
.emp-slip-emp .who  { font-weight: 700; color: var(--text-primary); font-size: 13px; }
.emp-slip-emp .meta { color: var(--text-secondary); font-size: 11px; }
.emp-slip-cols { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.emp-slip-cols > div { display: flex; flex-direction: column; }
.emp-slip-cols .ln.sum { margin-top: auto; }
.emp-slip-cols h6 { margin: 0 0 5px; font-size: 9.5px; text-transform: uppercase; letter-spacing: .5px; color: var(--text-secondary); font-weight: 800; border-bottom: 1px solid var(--border); padding-bottom: 3px; }
.emp-slip .ln { display: flex; justify-content: space-between; font-size: 11.5px; padding: 2.5px 0; font-variant-numeric: tabular-nums; }
.emp-slip .ln .k { color: var(--text-secondary); }
.emp-slip .ln .v { color: var(--text-primary); font-weight: 600; }
.emp-slip .ln.sum { border-top: 1px solid var(--border-md); margin-top: 3px; padding-top: 5px; font-weight: 800; }
.emp-slip-net { display: flex; justify-content: space-between; align-items: center; margin-top: 10px; border: 1.5px solid var(--brand); border-radius: 6px; padding: 8px 12px; background: var(--brand-subtle); }
.emp-slip-net .k { font-size: 11px; font-weight: 800; letter-spacing: .5px; color: var(--brand); }
.emp-slip-net .v { font-size: 1.15rem; font-weight: 900; color: var(--brand); font-variant-numeric: tabular-nums; }
.rc-basis {
    margin: 0 0 12px; padding: 8px 10px; border-radius: 6px;
    background: var(--bg-subtle); border: 1px solid var(--border);
    font-size: 11.5px; color: var(--text-secondary); font-variant-numeric: tabular-nums;
}
.rc-math {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 6px 14px;
    margin-top: 12px; padding: 9px 12px; border-radius: 6px;
    background: var(--bg-subtle); border: 1px solid var(--border);
    font-size: 11.5px; font-variant-numeric: tabular-nums;
}
.rc-math-item { display: flex; justify-content: space-between; gap: 10px; min-width: 0; }
.rc-math span { color: var(--text-secondary); }
.rc-math b    { color: var(--text-primary); font-weight: 700; white-space: nowrap; }

.pp-placeholder { background: var(--pp-panel); border: var(--pp-bw) dashed var(--pp-line-2); border-radius: var(--pp-radius); padding: 64px 24px; text-align: center; color: var(--pp-txt-3); }
.pp-placeholder i { font-size: 36px; opacity: .5; display: block; margin-bottom: 12px; }

@media (max-width: 1100px) {
    .pp-flow { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .pp-op { display: none; }
    .pp-stage.is-net { grid-column: 1 / -1; }
}
@media (max-width: 860px) {
    .pp-layout { grid-template-columns: 1fr; }
    .pp-picker { position: static; }
    .pp-plist { max-height: 300px; }
    .pp-menu, .pp-wf { grid-template-columns: 1fr; }
    .pp-facts { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 560px) {
    .emp-slip-cols { grid-template-columns: 1fr; }
}

/* Entrance — the calm row-level rise the other pages use */
@keyframes pp-rise { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
.pp-anim { opacity: 0; animation: pp-rise .5s ease forwards; }
@media (prefers-reduced-motion: reduce) { .pp-anim { animation: none; opacity: 1; } .pp-mtab:hover { transform: none; } }
</style>
@endpush

@section('content')
@php
    $peso  = fn ($n) => '₱' . number_format((float) $n, 2);
    $dur   = fn ($m) => \App\Support\WorkSchedule::duration($m);
    $views = [
        'workflow' => ['Salary computation', 'Earnings, deductions, and net pay breakdown.', 'ti-calculator', 'pp-i-blue'],
        'tracker'  => ['Remittance tracker', 'Deduction status per agency — SSS, PhilHealth, Pag-IBIG, BIR.', 'ti-transfer-out', 'pp-i-amber'],
        'payslip'  => ['View payslip', 'Printable official payslip for this period.', 'ti-file-text', 'pp-i-green'],
    ];
    $here = fn (array $q = []) => route('payroll-processing.index', array_merge([
        'period' => $period['key'], 'employee' => $sel['employee_id'] ?? null, 'view' => $view,
    ], $q));
@endphp
<div class="pp" id="ppRoot">

    <div class="pp-head pp-anim" style="animation-delay:.02s">
        <div class="pp-crumb">Jeyanco / Payroll / <b>Payroll processing</b></div>
        <h1>Payroll processing</h1>
        <p>Select an employee, then choose what to view — salary computation, remittance tracker, or payslip.</p>
    </div>

    <div class="pp-layout pp-anim" style="animation-delay:.06s">
        <aside class="pp-picker">
            {{-- A real form, so a period can be picked with scripting off. --}}
            <form class="pp-top" method="GET" action="{{ route('payroll-processing.index') }}">
                <label class="pp-field">
                    <i class="ti ti-search"></i>
                    <input id="ppSearch" type="search" placeholder="Search employee or ID" autocomplete="off" aria-label="Search employee or ID">
                </label>
                <label class="pp-field">
                    <i class="ti ti-calendar"></i>
                    <select name="period" id="ppPeriod" aria-label="Pay period">
                        @foreach(collect($periods)->groupBy('group') as $group => $list)
                            <optgroup label="{{ $group }}">
                                @foreach($list as $p)
                                    <option value="{{ $p['key'] }}" @selected($p['key'] === $period['key'])>{{ $p['label'] }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                </label>
                <input type="hidden" name="view" value="{{ $view }}">
                <noscript><button class="pp-btn sm" type="submit">Show period</button></noscript>
            </form>

            <div class="pp-plist" id="ppList">
                @foreach($rows as $r)
                    <a class="pp-pitem {{ $sel && $sel['employee_id'] === $r['employee_id'] ? 'sel' : '' }} {{ $r['worked'] ? '' : 'idle' }}"
                       href="{{ $here(['employee' => $r['employee_id']]) }}"
                       data-name="{{ mb_strtolower($r['name'] . ' ' . $r['code']) }}"
                       @if($sel && $sel['employee_id'] === $r['employee_id']) aria-current="true" @endif>
                        <span class="pp-av" style="background:{{ $r['color'] }}22;color:{{ $r['color'] }}">{{ $r['initial'] }}</span>
                        <span class="pp-pmain">
                            <span class="pp-nm">{{ $r['name'] }}</span>
                            <span class="pp-mt">{{ $r['code'] }} · {{ $r['labor'] }}@unless($r['worked']) · no attendance @endunless</span>
                        </span>
                        <i class="ti ti-chevron-right pp-chev"></i>
                    </a>
                @endforeach
                @if($rows->isEmpty())
                    <div class="pp-pempty">No attendance in this period.</div>
                @endif
            </div>
            <div class="pp-pnone" id="ppNone" hidden>No employee found.</div>
        </aside>

        <section class="pp-rpanel">
            @if(! $sel)
                <div class="pp-placeholder">
                    <i class="ti ti-user-search"></i>
                    Nobody worked in {{ $period['span'] }}, so there is no payroll to process for it. Pick another period on the left.
                </div>
            @else
                <div class="pp-emp">
                    <div class="pp-big-av" style="background:{{ $sel['color'] }}22;color:{{ $sel['color'] }}">{{ $sel['initial'] }}</div>
                    <div class="pp-info">
                        <div class="pp-n">{{ $sel['name'] }}</div>
                        <div class="pp-s">
                            <span><i class="ti ti-id"></i>{{ $sel['code'] }}</span>
                            <span><i class="ti ti-briefcase"></i>{{ $sel['labor'] }}</span>
                            @if($sel['site'])<span><i class="ti ti-map-pin"></i>{{ $sel['site'] }}</span>@endif
                            <span><i class="ti ti-cash"></i>{{ $peso($sel['hourly_rate']) }}/hr</span>
                        </div>
                    </div>
                    <div class="pp-per"><i class="ti ti-calendar-event"></i>{{ $period['span'] }}</div>
                </div>

                <nav class="pp-menu" aria-label="What to view">
                    @foreach($views as $key => [$title, $desc, $icon, $tone])
                        <a class="pp-mtab {{ $view === $key ? 'on' : '' }}" data-v="{{ $key }}" href="{{ $here(['view' => $key]) }}"
                           @if($view === $key) aria-current="page" @endif>
                            <span class="pp-mi {{ $tone }}"><i class="ti {{ $icon }}"></i></span>
                            <span class="pp-mtitle">{{ $title }}</span>
                            <span class="pp-md">{{ $desc }}</span>
                            <i class="ti ti-arrow-right pp-go"></i>
                        </a>
                    @endforeach
                </nav>

                {{-- ── Salary computation ─────────────────────────────────── --}}
                @php
                    $premiums = $sel['overtime'] + $sel['night'] + $sel['holiday'] + $sel['rest']
                              + $sel['leave'] + $sel['bonus'] + $sel['other_earnings'];
                    $days = rtrim(rtrim(number_format($sel['days'], 2), '0'), '.');
                @endphp
                <div class="pp-view" data-pane="workflow" @if($view !== 'workflow') hidden @endif>
                    <div class="pp-view-head">
                        <h3><i class="ti ti-calculator pp-c-accent"></i>Salary computation workflow</h3>
                        @if($run)
                            <span class="pp-badge pp-b-green"><i class="ti ti-lock"></i>Final · {{ $run->code }}</span>
                        @else
                            <span class="pp-badge pp-b-muted"><i class="ti ti-eye"></i>Live from attendance</span>
                        @endif
                    </div>
                    <div class="pp-view-body">
                        @unless($sel['worked'])
                            <div class="pp-note inline"><i class="ti ti-info-circle"></i><span>No attendance for {{ $sel['name'] }} in {{ $period['span'] }} yet — every figure below stays at zero until they clock in.</span></div>
                        @endunless
                        <div class="pp-facts">
                            <div class="pp-fact"><span>Days worked</span><b>{{ $days }}</b></div>
                            <div class="pp-fact"><span>Time worked</span><b>{{ $dur($sel['minutes']) }}</b></div>
                            <div class="pp-fact"><span>Overtime</span><b>{{ $dur($sel['ot_minutes']) }}</b></div>
                            <div class="pp-fact"><span>Late</span><b>{{ $dur($sel['late_minutes']) }}</b></div>
                        </div>

                        <ol class="pp-flow" aria-label="How the net pay is reached">
                            <li class="pp-stage"><span class="k">1 · Basic pay</span><b>{{ $peso($sel['basic']) }}</b><small>Regular time × hourly rate</small></li>
                            <li class="pp-op" aria-hidden="true">+</li>
                            <li class="pp-stage"><span class="k">2 · Premiums</span><b>{{ $peso($premiums) }}</b><small>Overtime and other premiums</small></li>
                            <li class="pp-op" aria-hidden="true">=</li>
                            <li class="pp-stage is-gross"><span class="k">3 · Gross pay</span><b>{{ $peso($sel['gross']) }}</b><small>Before deductions</small></li>
                            <li class="pp-op" aria-hidden="true">−</li>
                            <li class="pp-stage is-ded"><span class="k">4 · Deductions</span><b>{{ $peso($sel['deductions']) }}</b><small>Contributions, tax, advances</small></li>
                            <li class="pp-op" aria-hidden="true">=</li>
                            <li class="pp-stage is-net"><span class="k">5 · Net pay</span><b>{{ $peso($sel['net']) }}</b><small>Take-home pay</small></li>
                        </ol>

                        <div class="pp-wf">
                            @foreach(['earn' => ['Earnings', 'ti-plus', 'Gross pay', $sel['gross'], 'pp-c-green'], 'ded' => ['Deductions', 'ti-minus', 'Total deductions', $sel['deductions'], 'pp-c-red']] as $side => [$title, $icon, $sumLabel, $sum, $sumTone])
                                <div class="pp-box {{ $side }}">
                                    <div class="pp-bh"><i class="ti {{ $icon }}"></i>{{ $title }}</div>
                                    <div class="pp-bb">
                                        @foreach($lines[$side] as $l)
                                            <div class="pp-row {{ $l['amount'] == 0 ? 'zero' : '' }}">
                                                <span class="l"><i class="ti {{ $l['icon'] }}"></i><span>{{ $l['label'] }}@if($l['note'])<small>{{ $l['note'] }}</small>@endif</span></span>
                                                <span class="a">{{ $peso($l['amount']) }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                    <div class="pp-foot"><span>{{ $sumLabel }}</span><span class="pp-num {{ $sumTone }}">{{ $peso($sum) }}</span></div>
                                </div>
                            @endforeach
                        </div>

                        <div class="pp-net-strip">
                            <span class="k">Net pay <span>· gross − deductions</span></span>
                            <span class="v">{{ $peso($sel['net']) }}</span>
                        </div>
                    </div>
                </div>

                {{-- ── Remittance tracker ─────────────────────────────────── --}}
                <div class="pp-view" data-pane="tracker" @if($view !== 'tracker') hidden @endif>
                    <div class="pp-view-head">
                        <h3><i class="ti ti-transfer-out pp-c-amber"></i>Deduction &amp; remittance tracker</h3>
                        @if($open > 0)
                            <span class="pp-badge pp-b-amber"><i class="ti ti-clock"></i>{{ $open }} pending</span>
                        @elseif(collect($track)->whereNotNull('done_text')->isNotEmpty())
                            <span class="pp-badge pp-b-green"><i class="ti ti-circle-check"></i>All settled</span>
                        @endif
                    </div>
                    @if($notice)
                        <div class="pp-note"><i class="ti ti-info-circle"></i><span>{{ $notice }}</span></div>
                    @endif
                    <div class="pp-tablewrap">
                        <table class="pp-table">
                            <thead>
                                <tr><th>Agency / deduction</th><th class="r">Amount</th><th>Period</th><th>Status</th><th class="r">Action</th></tr>
                            </thead>
                            <tbody>
                                @foreach($track as $t)
                                    <tr>
                                        <td>
                                            <div class="pp-ag">
                                                <span class="pp-ag-ico"><i class="ti {{ $t['icon'] }}"></i></span>
                                                <div>
                                                    <div class="pp-ag-n">{{ $t['label'] }}</div>
                                                    @if($t['id'])
                                                        <div class="pp-ag-id">{{ $t['id_label'] }} <b>{{ $t['id'] }}</b></div>
                                                    @elseif($t['id_missing'])
                                                        <div class="pp-ag-id missing"><i class="ti ti-alert-triangle"></i>{{ $t['id_missing'] }}</div>
                                                    @endif
                                                    <div class="pp-ag-s">{{ $t['sub'] }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="r pp-num" style="font-weight:500">
                                            {{ $peso($t['amount']) }}
                                            @if($t['was'] !== null)
                                                <div class="pp-by" title="The pay for this period has changed since this line was marked">{{ $peso($t['was']) }} when marked</div>
                                            @endif
                                        </td>
                                        <td class="pp-muted" style="white-space:nowrap">{{ $period['span'] }}</td>
                                        <td>
                                            <span class="pp-badge {{ $t['tone'] }}"><i class="ti {{ $t['badge'] }}"></i>{{ $t['state'] }}</span>
                                            @if($t['by'])<div class="pp-by">{{ $t['by'] }}</div>@endif
                                        </td>
                                        <td class="r">
                                            <div class="pp-acts">
                                                @if($t['done_text'])
                                                    <span class="pp-done"><i class="ti ti-checks"></i>{{ $t['done_text'] }}</span>
                                                @endif
                                                @foreach($t['actions'] as $a)
                                                    <form method="POST" action="{{ route('payroll-processing.track', [$sel['employee_id'], $t['kind']]) }}">
                                                        @csrf
                                                        <input type="hidden" name="period" value="{{ $period['key'] }}">
                                                        <input type="hidden" name="action" value="{{ $a['action'] }}">
                                                        @if($a['primary'])
                                                            <button class="pp-btn primary sm" type="submit">{{ $a['label'] }}</button>
                                                        @else
                                                            <button class="pp-btn ghost sm icon" type="submit" title="{{ $a['label'] }}" aria-label="{{ $a['label'] }} {{ $t['label'] }}"><i class="ti {{ $a['icon'] }}"></i></button>
                                                        @endif
                                                    </form>
                                                @endforeach
                                                @if(! $t['actions'] && ! $t['done_text'])
                                                    <span class="pp-muted">—</span>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- ── Payslip ──────────────────────────────────────────────
                     The Payroll Records receipt, and the same printable page
                     behind its Print / Save as PDF button. --}}
                <div class="pp-view" data-pane="payslip" @if($view !== 'payslip') hidden @endif>
                    <div class="pp-view-head">
                        <h3><i class="ti ti-file-text pp-c-green"></i>Payslip</h3>
                        <a class="pp-btn primary" target="_blank" rel="noopener"
                           href="{{ route('payslip.batch', ['from' => $period['from'], 'to' => $period['to'], 'employee' => $sel['employee_id']]) }}">
                            <i class="ti ti-printer"></i>Print / Save as PDF
                        </a>
                    </div>
                    <div class="pp-view-body">
                        <div class="emp-slip" style="border:none;padding:0;background:transparent;">
                            <div class="emp-slip-head">
                                <img class="emp-slip-logo" src="{{ $company?->logoUrl() ?? asset('images/JeyancoLogo.png') }}" alt=""
                                     onerror="this.onerror=null;this.src='{{ asset('images/JeyancoLogo.png') }}'">
                                <div class="emp-slip-co">
                                    <div class="co">{{ $company?->company_name ?? 'JEYANCO CONSTRUCTION' }}</div>
                                    <div class="sub">{{ $company?->company_tagline ?? 'Payroll Dept. · Panganiban, PH' }}</div>
                                </div>
                                <div class="emp-slip-doc">
                                    <div class="lbl">PAYSLIP</div>
                                    <div class="per">{{ $period['span'] }}</div>
                                </div>
                            </div>

                            <div class="emp-slip-emp">
                                <span class="who">{{ $sel['name'] }}</span>
                                <span class="meta">{{ $slip['meta'] }}</span>
                            </div>

                            <div class="rc-basis">{{ $slip['basis'] }}</div>

                            <div class="emp-slip-cols">
                                <div>
                                    <h6>Earnings</h6>
                                    @foreach($slip['earn'] as [$k, $v])
                                        <div class="ln"><span class="k">{{ $k }}</span><span class="v">{{ $peso($v) }}</span></div>
                                    @endforeach
                                    <div class="ln sum"><span class="k">Gross pay</span><span class="v">{{ $peso($slip['gross']) }}</span></div>
                                </div>
                                <div>
                                    <h6>Deductions</h6>
                                    @foreach($slip['ded'] as [$k, $v])
                                        <div class="ln"><span class="k">{{ $k }}</span><span class="v">{{ $peso($v) }}</span></div>
                                    @endforeach
                                    <div class="ln sum"><span class="k">Total deductions</span><span class="v" style="color:var(--danger);">{{ $peso($slip['deductions']) }}</span></div>
                                </div>
                            </div>

                            {{-- The bonus is added to net, not to gross, so it
                                 belongs in the arithmetic that reaches net. --}}
                            <div class="rc-math">
                                <div class="rc-math-item"><span>Gross</span><b>{{ $peso($slip['gross']) }}</b></div>
                                <div class="rc-math-item"><span>− Deductions</span><b>{{ $peso($slip['deductions']) }}</b></div>
                                <div class="rc-math-item"><span>+ Bonus</span><b>{{ $peso($slip['bonus']) }}</b></div>
                            </div>

                            <div class="emp-slip-net">
                                <span class="k">NET PAY &middot; {{ $period['span'] }}</span>
                                <span class="v">{{ $peso($slip['net']) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </section>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const root = document.getElementById('ppRoot');
    if (!root) return;

    // Search narrows the list in place.
    const search = document.getElementById('ppSearch');
    const none   = document.getElementById('ppNone');
    const items  = root.querySelectorAll('.pp-pitem');
    search?.addEventListener('input', () => {
        const q = search.value.trim().toLowerCase();
        let shown = 0;
        items.forEach(el => {
            const match = el.dataset.name.includes(q);
            el.hidden = !match;
            if (match) shown++;
        });
        if (none) none.hidden = shown > 0 || items.length === 0;
    });

    // A new period is a new page.
    document.getElementById('ppPeriod')?.addEventListener('change', e => e.target.form.submit());

    // In a long list, keep the chosen worker in sight without moving the page.
    const list = document.getElementById('ppList');
    const sel  = list?.querySelector('.pp-pitem.sel');
    if (list && sel && sel.offsetTop + sel.offsetHeight > list.clientHeight) {
        list.scrollTop = sel.offsetTop - list.clientHeight / 2;
    }

    // The three cards switch in place; the address and the links keep up, so a
    // reload or another worker lands on the same card.
    const tabs = root.querySelectorAll('.pp-mtab');
    tabs.forEach(tab => tab.addEventListener('click', e => {
        if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
        e.preventDefault();

        const v = tab.dataset.v;
        tabs.forEach(t => {
            const on = t === tab;
            t.classList.toggle('on', on);
            on ? t.setAttribute('aria-current', 'page') : t.removeAttribute('aria-current');
        });
        root.querySelectorAll('[data-pane]').forEach(p => { p.hidden = p.dataset.pane !== v; });

        items.forEach(a => { const u = new URL(a.href); u.searchParams.set('view', v); a.href = u.toString(); });
        const field = root.querySelector('input[name="view"]');
        if (field) field.value = v;
        try { const u = new URL(location.href); u.searchParams.set('view', v); history.replaceState(null, '', u); } catch (_) {}
    }));
})();
</script>
@endpush
