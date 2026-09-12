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
.pp-head { margin-bottom: 18px; }
.pp-crumb { font-size: 12px; color: var(--pp-txt-3); margin-bottom: 5px; }
.pp-crumb b { color: var(--pp-txt-2); font-weight: 500; }
.pp .pp-head h1 { font-size: 21px !important; font-weight: 600 !important; letter-spacing: -.01em !important; line-height: 1.3; color: var(--pp-txt); }
.pp-head p { font-size: 13px; color: var(--pp-txt-2); margin-top: 4px; }

/* The period's run: where it stands, and the next step */
.pp-run { background: var(--pp-panel); border: var(--pp-bw) solid var(--pp-line); border-radius: var(--pp-radius); margin-bottom: 18px; overflow: hidden; }
.pp-run-top { display: flex; align-items: center; justify-content: space-between; gap: 14px; padding: 14px 18px; border-bottom: var(--pp-bw) solid var(--pp-line); flex-wrap: wrap; }
.pp-run-title { font-size: 15px; font-weight: 600; display: flex; align-items: center; gap: 9px; flex-wrap: wrap; }
.pp-run-sub { font-size: 12.5px; color: var(--pp-txt-2); margin-top: 3px; display: flex; gap: 6px 16px; flex-wrap: wrap; }
.pp-run-sub b { color: var(--pp-txt); font-weight: 600; font-variant-numeric: tabular-nums; }
.pp-run-actions { display: flex; gap: 8px; flex-wrap: wrap; }
.pp-run-actions form { margin: 0; }
.pp-steps { list-style: none; display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); padding: 16px 18px; }
.pp-step { position: relative; padding-right: 10px; min-width: 0; }
.pp-step:not(:last-child)::after { content: ''; position: absolute; top: 13px; left: 36px; right: 8px; height: 2px; border-radius: 2px; background: var(--pp-line-2); }
.pp-step.done:not(:last-child)::after { background: var(--pp-green-txt); opacity: .45; }
.pp-dot { width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 14px; background: var(--pp-panel-2); border: var(--pp-bw) solid var(--pp-line-2); color: var(--pp-txt-3); position: relative; z-index: 1; flex-shrink: 0; }
.pp-step.done .pp-dot { background: var(--pp-green-soft); border-color: transparent; color: var(--pp-green-txt); }
.pp-step.now .pp-dot { background: var(--pp-accent-soft); border-color: var(--pp-accent); color: var(--pp-accent-txt); }
.pp-step-k { font-size: 12.5px; font-weight: 600; margin-top: 8px; }
.pp-step.todo .pp-step-k { color: var(--pp-txt-2); }
.pp-step-n { font-size: 11.5px; color: var(--pp-txt-3); margin-top: 1px; line-height: 1.4; }

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
.pp-by { font-size: 11px; color: var(--pp-txt-3); margin-top: 4px; white-space: nowrap; }
.pp-acts { display: inline-flex; align-items: center; gap: 6px; justify-content: flex-end; }
.pp-acts form { margin: 0; }
.pp-done { color: var(--pp-green-txt); font-size: 12px; display: inline-flex; align-items: center; gap: 4px; white-space: nowrap; }

/* Payslip — paper, in either theme */
.pp-doc { position: relative; background: #fff; color: #1a1a1a; border-radius: 10px; overflow: hidden; }
.pp-draft { background: #FEF3C7; color: #92400E; font-size: 11.5px; font-weight: 600; text-align: center; padding: 6px 12px; letter-spacing: .3px; }
.pp-doc-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; padding: 20px 24px; border-bottom: 2px solid #185FA5; }
.pp-brand { display: flex; align-items: center; gap: 11px; }
.pp-logo { width: 42px; height: 42px; border-radius: 9px; object-fit: contain; background: #fff; }
.pp-t1 { font-size: 15px; font-weight: 700; color: #185FA5; }
.pp-t2 { font-size: 11px; color: #777; }
.pp-doc-per { text-align: right; }
.pp-doc-per .k, .pp-pmeta .k { font-size: 10px; color: #999; text-transform: uppercase; letter-spacing: .5px; }
.pp-doc-per .v { font-size: 13px; font-weight: 600; margin-top: 2px; }
.pp-pmeta { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; padding: 14px 24px; background: #f6f8fb; }
.pp-pmeta .v { font-size: 13px; font-weight: 600; margin-top: 2px; overflow-wrap: anywhere; }
.pp-pcols { display: grid; grid-template-columns: 1fr 1fr; }
.pp-pcol { padding: 16px 24px; }
.pp-pcol.l { border-right: 1px solid #eee; }
.pp .pp-pcol h4 { font-size: 11px !important; font-weight: 700 !important; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 10px; }
.pp .pp-pcol.l h4 { color: #16a34a; }
.pp .pp-pcol.r h4 { color: #dc2626; }
.pp-pline { display: flex; justify-content: space-between; gap: 10px; padding: 5px 0; font-size: 13px; border-bottom: 1px solid #f2f2f2; }
.pp-pline .pl { color: #444; }
.pp-pline .pl small { color: #aaa; font-size: 11px; margin-left: 3px; }
.pp-pline .pa { font-variant-numeric: tabular-nums; white-space: nowrap; }
.pp-pline.m .pl, .pp-pline.m .pa { color: #b8b8b8; }
.pp-psub { display: flex; justify-content: space-between; padding-top: 9px; font-weight: 700; font-size: 13px; }
.pp-psub.g span:last-child { color: #16a34a; }
.pp-psub.r span:last-child { color: #dc2626; }
.pp-pnet { display: flex; align-items: center; justify-content: space-between; padding: 16px 24px; background: #185FA5; color: #fff; }
.pp-pnet .k { font-size: 12px; text-transform: uppercase; letter-spacing: .6px; }
.pp-pnet .v { font-size: 22px; font-weight: 700; font-variant-numeric: tabular-nums; }
.pp-pfoot { display: flex; justify-content: space-between; padding: 12px 24px; font-size: 11px; color: #888; border-top: 1px solid #eee; flex-wrap: wrap; gap: 8px; }
.pp-sig { display: inline-flex; align-items: center; gap: 5px; }
.pp-sig i { color: #16a34a; }
.pp-sig.wait i { color: #F59E0B; }

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
    .pp-steps { grid-template-columns: 1fr; row-gap: 12px; }
    .pp-step { display: flex; align-items: flex-start; gap: 10px; }
    .pp-step:not(:last-child)::after { display: none; }
    .pp-step-k { margin-top: 3px; }
    .pp-facts, .pp-pmeta { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 560px) {
    .pp-pcols { grid-template-columns: 1fr; }
    .pp-pcol.l { border-right: none; border-bottom: 1px solid #eee; }
}

/* Entrance — the calm row-level rise the other pages use */
@keyframes pp-rise { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
.pp-anim { opacity: 0; animation: pp-rise .5s ease forwards; }
@media (prefers-reduced-motion: reduce) { .pp-anim { animation: none; opacity: 1; } .pp-mtab:hover { transform: none; } }

/* Printing prints the payslip and nothing else */
@media print {
    body * { visibility: hidden !important; }
    #ppDoc, #ppDoc * { visibility: visible !important; }
    #ppDoc { position: absolute; left: 0; top: 0; width: 100%; border-radius: 0; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    @page { margin: 14mm; }
}
</style>
@endpush

@section('content')
@php
    $peso   = fn ($n) => '₱' . number_format((float) $n, 2);
    $dur    = fn ($m) => \App\Support\WorkSchedule::duration($m);
    $status = $run?->status;
    $views  = [
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

    {{-- The period's run, as its own workflow: where it stands and the next
         step. Every button is one of the run's existing actions. --}}
    <section class="pp-run pp-anim" style="animation-delay:.06s" aria-label="Payroll run for this period">
        <div class="pp-run-top">
            <div>
                <div class="pp-run-title">
                    @if($processed)
                        {{ $run->code }}
                        <span class="pp-badge {{ ['calculated' => 'pp-b-amber', 'approved' => 'pp-b-blue', 'finalized' => 'pp-b-green'][$status] ?? 'pp-b-muted' }}">{{ $run->status_label }}</span>
                    @else
                        Not processed yet
                        <span class="pp-badge pp-b-muted"><i class="ti ti-eye"></i>Live from attendance</span>
                    @endif
                </div>
                <div class="pp-run-sub">
                    <span>{{ $period['label'] }}</span>
                    <span>Employees <b>{{ $totals['employees'] }}</b></span>
                    <span>Gross <b>{{ $peso($totals['gross']) }}</b></span>
                    <span>Deductions <b>{{ $peso($totals['deductions']) }}</b></span>
                    <span>Net <b>{{ $peso($totals['net']) }}</b></span>
                </div>
            </div>

            <div class="pp-run-actions">
                @if(! $run)
                    <form method="POST" action="{{ route('payroll-processing.store') }}"
                          data-confirm="The figures for {{ $period['span'] }} are written into a payroll run. They can be recalculated until the run is approved."
                          data-confirm-title="Process this period?"
                          data-confirm-label="Process payroll"
                          data-confirm-tone="brand">
                        @csrf
                        <input type="hidden" name="period_start" value="{{ $period['from'] }}">
                        <input type="hidden" name="period_end" value="{{ $period['to'] }}">
                        <button class="pp-btn primary" type="submit" @disabled($rows->isEmpty())><i class="ti ti-player-play"></i>Process payroll</button>
                    </form>
                @elseif($run->isEditable())
                    <form method="POST" action="{{ route('payroll-processing.calculate', $run) }}">
                        @csrf
                        <button class="pp-btn" type="submit"><i class="ti ti-refresh"></i>{{ $processed ? 'Recalculate' : 'Calculate' }}</button>
                    </form>
                    @if($status === 'calculated')
                        <form method="POST" action="{{ route('payroll-processing.approve', $run) }}"
                              data-confirm="The figures can still be reopened later, until the run is finalized."
                              data-confirm-title="Approve {{ $run->code }}?"
                              data-confirm-label="Approve"
                              data-confirm-tone="brand">
                            @csrf
                            <button class="pp-btn primary" type="submit"><i class="ti ti-circle-check"></i>Approve</button>
                        </form>
                    @endif
                @elseif($status === 'approved')
                    <form method="POST" action="{{ route('payroll-processing.reopen', $run) }}">
                        @csrf
                        <button class="pp-btn" type="submit"><i class="ti ti-lock-open"></i>Reopen</button>
                    </form>
                    <form method="POST" action="{{ route('payroll-processing.finalize', $run) }}"
                          data-confirm="This cannot be undone. Payslips are issued, and cash advance instalments are taken off their balances now."
                          data-confirm-title="Finalize {{ $run->code }}?"
                          data-confirm-label="Finalize"
                          data-confirm-tone="brand">
                        @csrf
                        <input type="hidden" name="confirm" value="1">
                        <button class="pp-btn primary" type="submit"><i class="ti ti-lock"></i>Finalize</button>
                    </form>
                @elseif($final && auth()->user()?->canAccessModule('payslips'))
                    <a class="pp-btn" href="{{ route('payslips.print-run', $run) }}" target="_blank" rel="noopener"><i class="ti ti-printer"></i>Print all payslips</a>
                @endif

                @if($run)
                    <a class="pp-btn ghost" href="{{ route('payroll-processing.show', $run) }}"><i class="ti ti-list-details"></i>Run details</a>
                @endif
            </div>
        </div>

        <ol class="pp-steps">
            @foreach($stages as $s)
                <li class="pp-step {{ $s['state'] }}" @if($s['state'] === 'now') aria-current="step" @endif>
                    <span class="pp-dot"><i class="ti {{ $s['state'] === 'done' ? 'ti-check' : $s['icon'] }}"></i></span>
                    <div>
                        <div class="pp-step-k">{{ $s['label'] }}</div>
                        <div class="pp-step-n">{{ $s['note'] }}</div>
                    </div>
                </li>
            @endforeach
        </ol>
    </section>

    <div class="pp-layout pp-anim" style="animation-delay:.1s">
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
                    <a class="pp-pitem {{ $sel && $sel['employee_id'] === $r['employee_id'] ? 'sel' : '' }}"
                       href="{{ $here(['employee' => $r['employee_id']]) }}"
                       data-name="{{ mb_strtolower($r['name'] . ' ' . $r['code']) }}"
                       @if($sel && $sel['employee_id'] === $r['employee_id']) aria-current="true" @endif>
                        <span class="pp-av" style="background:{{ $r['color'] }}22;color:{{ $r['color'] }}">{{ $r['initial'] }}</span>
                        <span class="pp-pmain">
                            <span class="pp-nm">{{ $r['name'] }}</span>
                            <span class="pp-mt">{{ $r['code'] }} · {{ $r['labor'] }}</span>
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
                        @if($final)
                            <span class="pp-badge pp-b-green"><i class="ti ti-lock"></i>Final · {{ $run->code }}</span>
                        @elseif($processed)
                            <span class="pp-badge pp-b-blue"><i class="ti ti-snowflake"></i>Frozen in {{ $run->code }}</span>
                        @else
                            <span class="pp-badge pp-b-muted"><i class="ti ti-eye"></i>Live from attendance</span>
                        @endif
                    </div>
                    <div class="pp-view-body">
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
                        @elseif($final && ! $notice)
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
                                                <div><div class="pp-ag-n">{{ $t['label'] }}</div><div class="pp-ag-s">{{ $t['sub'] }}</div></div>
                                            </div>
                                        </td>
                                        <td class="r pp-num" style="font-weight:500">{{ $peso($t['amount']) }}</td>
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
                                                    <form method="POST" action="{{ route('payroll-processing.track', [$sel['item'], $t['kind']]) }}">
                                                        @csrf
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

                {{-- ── Payslip ────────────────────────────────────────────── --}}
                <div class="pp-view" data-pane="payslip" @if($view !== 'payslip') hidden @endif>
                    <div class="pp-view-head">
                        <h3><i class="ti ti-file-text pp-c-green"></i>Payslip</h3>
                        <div class="pp-acts">
                            <button class="pp-btn" type="button" data-print><i class="ti ti-download"></i>Download PDF</button>
                            <button class="pp-btn primary" type="button" data-print><i class="ti ti-printer"></i>Print payslip</button>
                        </div>
                    </div>
                    <div class="pp-view-body">
                        <div class="pp-doc" id="ppDoc">
                            @unless($final)
                                <div class="pp-draft">DRAFT — this payslip is final once {{ $processed ? $run->code : 'the period' }} is finalized</div>
                            @endunless
                            <div class="pp-doc-head">
                                <div class="pp-brand">
                                    <img class="pp-logo" src="{{ $company?->logoUrl() ?? asset('images/JeyancoLogo.png') }}" alt=""
                                         onerror="this.onerror=null;this.src='{{ asset('images/JeyancoLogo.png') }}'">
                                    <div>
                                        <div class="pp-t1">{{ $company?->company_name ?? 'JEYANCO CONSTRUCTION' }}</div>
                                        <div class="pp-t2">Official Payslip{{ $processed ? ' · ' . $run->code : '' }}</div>
                                    </div>
                                </div>
                                <div class="pp-doc-per"><div class="k">Pay period</div><div class="v">{{ $period['span'] }}</div></div>
                            </div>
                            <div class="pp-pmeta">
                                <div><div class="k">Employee</div><div class="v">{{ $sel['name'] }}</div></div>
                                <div><div class="k">Employee ID</div><div class="v">{{ $sel['code'] }}</div></div>
                                <div><div class="k">Labor type</div><div class="v">{{ $sel['labor'] }}</div></div>
                                <div><div class="k">Rate / hr</div><div class="v">{{ $peso($sel['hourly_rate']) }}</div></div>
                            </div>
                            <div class="pp-pcols">
                                @foreach(['earn' => ['l', 'Earnings', 'g', 'Gross pay', $sel['gross']], 'ded' => ['r', 'Deductions', 'r', 'Total deductions', $sel['deductions']]] as $side => [$col, $title, $subTone, $subLabel, $sub])
                                    <div class="pp-pcol {{ $col }}">
                                        <h4>{{ $title }}</h4>
                                        @foreach($lines[$side] as $l)
                                            <div class="pp-pline {{ $l['amount'] == 0 ? 'm' : '' }}">
                                                <span class="pl">{{ $l['label'] }}@if($l['note'])<small>{{ $l['note'] }}</small>@endif</span>
                                                <span class="pa">{{ $peso($l['amount']) }}</span>
                                            </div>
                                        @endforeach
                                        <div class="pp-psub {{ $subTone }}"><span>{{ $subLabel }}</span><span>{{ $peso($sub) }}</span></div>
                                    </div>
                                @endforeach
                            </div>
                            <div class="pp-pnet"><span class="k">Net pay</span><span class="v">{{ $peso($sel['net']) }}</span></div>
                            <div class="pp-pfoot">
                                @if($processed)
                                    <span class="pp-sig"><i class="ti ti-circle-check"></i>Processed {{ $stages[1]['note'] }}</span>
                                @else
                                    <span class="pp-sig wait"><i class="ti ti-clock"></i>Not processed yet</span>
                                @endif
                                @if(in_array($status, ['approved', 'finalized'], true))
                                    <span class="pp-sig"><i class="ti ti-circle-check"></i>Approved {{ $stages[2]['note'] }}</span>
                                @else
                                    <span class="pp-sig wait"><i class="ti ti-clock"></i>Approval pending</span>
                                @endif
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

    root.querySelectorAll('[data-print]').forEach(b => b.addEventListener('click', () => window.print()));
})();
</script>
@endpush
