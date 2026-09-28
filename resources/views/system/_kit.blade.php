{{-- ── System pages · "Site logbook" design layer ─────────────────────────
     Shared by Users & Roles, Audit Logs, Device Monitoring and the System
     Settings tabs. Built on the app's own tokens; the only new ones are the
     role hues (small marks, never fills), the ruler's tick greys and the
     inverse surface the save bar uses. Page-scoped like modules/_kit — nothing
     global is touched, and every class is sx-* or specific to these pages. --}}
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
html[data-bs-theme="light"] {
    --r-admin: #1668DC; --r-staff: #0E7490; --r-payroll: #6D5BD0;
    --r-hr: #B93815; --r-sup: #A15C07; --r-emp: #667085;
    --tick: #D0D5DD; --tick-major: #98A2B3;
    --inverse: #101828; --inverse-text: #F2F4F7;
}
html[data-bs-theme="dark"] {
    --r-admin: #4F97F5; --r-staff: #2BB3C9; --r-payroll: #9280E8;
    --r-hr: #F07B55; --r-sup: #D9A441; --r-emp: #98A2B3;
    --tick: #2E3F5C; --tick-major: #5B6E8C;
    --inverse: #E8EDF5; --inverse-text: #0C1522;
}
.r-admin { --rc: var(--r-admin); } .r-staff { --rc: var(--r-staff); }
.r-payroll { --rc: var(--r-payroll); } .r-hr { --rc: var(--r-hr); }
.r-sup { --rc: var(--r-sup); } .r-emp { --rc: var(--r-emp); }

.sx-page { width: 100%; }
.sx-page a { text-decoration: none; }
.sx-page button { font-family: inherit; cursor: pointer; }
.mono { font-family: 'JetBrains Mono', ui-monospace, Consolas, monospace; font-feature-settings: normal; letter-spacing: -.01em; }


/* ── Buttons & links ─────────────────────────────────────────────────── */
.sx-btn {
    height: 34px; padding: 0 13px; border-radius: 8px; border: 1px solid var(--border);
    background: var(--surface); color: var(--text-primary); font: 600 13px/1 'Inter', system-ui, sans-serif;
    display: inline-flex; align-items: center; gap: 7px; white-space: nowrap; text-decoration: none;
    box-shadow: var(--shadow-xs); transition: background .15s, border-color .15s, color .15s;
}
.sx-btn:hover { border-color: var(--border-md); color: var(--text-primary); }
.sx-btn svg { width: 15px; height: 15px; }
.sx-btn.primary { background: var(--brand); border-color: var(--brand); color: #fff; box-shadow: 0 1px 2px rgba(22,104,220,.3); }
.sx-btn.primary:hover { background: var(--brand-strong); border-color: var(--brand-strong); color: #fff; }
.sx-btn.sm { height: 28px; padding: 0 10px; font-size: 12px; gap: 6px; }
.sx-btn.sm svg { width: 13px; height: 13px; }
.sx-btn.quiet { background: transparent; border-color: transparent; box-shadow: none; color: var(--text-secondary); }
.sx-btn.danger { color: var(--danger); }
.sx-btn.danger:hover { border-color: var(--danger); background: var(--danger-soft); color: var(--danger); }
.sx-btn:disabled, .sx-btn.is-disabled { opacity: .45; pointer-events: none; }
.sx-link { color: var(--brand); font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; white-space: nowrap; }
.sx-link:hover { color: var(--brand-strong); }
.sx-link svg { width: 13px; height: 13px; }

/* ── Status line · every page opens with its one-sentence verdict ──── */
.sx-status {
    --tone: var(--success); --tone-soft: var(--success-soft);
    display: flex; align-items: center; gap: 11px; min-height: 46px; padding: 8px 14px 8px 12px; margin-bottom: 14px;
    background: var(--surface); border: 1px solid var(--border); border-radius: 10px; box-shadow: var(--shadow-xs);
    font-size: 13px; color: var(--text-secondary); position: relative; flex-wrap: wrap;
}
.sx-status::before { content: ""; position: absolute; left: -1px; top: -1px; bottom: -1px; width: 4px; background: var(--tone); border-radius: 10px 0 0 10px; }
.sx-status.warn { --tone: var(--warning); --tone-soft: var(--warning-soft); }
.sx-status.danger { --tone: var(--danger); --tone-soft: var(--danger-soft); }
.sx-status.info { --tone: var(--brand); --tone-soft: var(--brand-subtle); }
.sx-status-ico { width: 26px; height: 26px; border-radius: 7px; background: var(--tone-soft); color: var(--tone); display: grid; place-items: center; flex: none; margin-left: 4px; }
.sx-status-ico svg { width: 15px; height: 15px; }
.sx-status b { color: var(--text-primary); font-weight: 600; }
.sx-status > span { white-space: nowrap; }
.sx-status a.plain { color: inherit; }
.sx-status a.plain:hover b { color: var(--brand); }
.sx-status .dotsep { width: 3px; height: 3px; border-radius: 50%; background: var(--text-muted); opacity: .6; flex: none; }
.sx-status-end { margin-left: auto; display: flex; align-items: center; gap: 10px; font-size: 12px; color: var(--text-muted); white-space: nowrap; }
.live { display: inline-flex; align-items: center; gap: 7px; }
.live::before { content: ""; width: 7px; height: 7px; border-radius: 50%; background: var(--success); box-shadow: 0 0 0 3px var(--success-soft); }

/* ── Cards ───────────────────────────────────────────────────────────── */
.sx-card { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; box-shadow: var(--shadow-xs); position: relative; }
.sx-card-head { display: flex; align-items: center; gap: 10px; padding: 11px 16px; border-bottom: 1px solid var(--border); min-height: 52px; flex-wrap: wrap; }
.sx-page .sx-card-title { font-size: 13.5px; font-weight: 700; color: var(--text-primary); margin: 0; white-space: nowrap; letter-spacing: -.01em; }
.sx-card-note { font-size: 12px; color: var(--text-muted); }
.sx-idx { font-family: 'JetBrains Mono', ui-monospace, monospace; font-size: 10px; font-weight: 600; color: var(--text-muted); border: 1px solid var(--border-md); border-radius: 4px; padding: 3px 5px; line-height: 1; }
.sx-card-tools { margin-left: auto; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.sx-card-foot { display: flex; align-items: center; gap: 10px; padding: 10px 16px; border-top: 1px solid var(--border); font-size: 12px; color: var(--text-muted); flex-wrap: wrap; }
.sx-card-foot b { color: var(--text-primary); font-weight: 600; }
.sx-empty { padding: 36px 16px; text-align: center; color: var(--text-muted); font-size: 13px; }
.sx-empty svg { width: 22px; height: 22px; display: block; margin: 0 auto 8px; opacity: .6; }

/* ── Inputs ──────────────────────────────────────────────────────────── */
.sx-input { height: 34px; border: 1px solid var(--border); background: var(--surface); border-radius: 8px; padding: 0 11px; font-size: 13px; color: var(--text-primary); display: flex; align-items: center; gap: 8px; margin: 0; }
.sx-input svg { width: 15px; height: 15px; color: var(--text-muted); flex: none; }
.sx-input input { flex: 1; min-width: 0; border: 0; outline: 0; background: transparent; font: inherit; color: inherit; padding: 0; height: 100%; }
.sx-input input::placeholder { color: var(--text-muted); }
.sx-input:focus-within { border-color: var(--brand); box-shadow: 0 0 0 3px var(--brand-subtle); }
input.sx-input { width: 100%; display: block; outline: 0; }
input.sx-input:focus { border-color: var(--brand); box-shadow: 0 0 0 3px var(--brand-subtle); }
.sx-seg { display: inline-flex; padding: 2px; background: var(--bg-subtle); border: 1px solid var(--border); border-radius: 8px; gap: 2px; }
.sx-seg > a, .sx-seg > button, .sx-seg > span { height: 28px; padding: 0 10px; border-radius: 6px; font-size: 12.5px; font-weight: 600; color: var(--text-secondary); display: inline-flex; align-items: center; gap: 6px; white-space: nowrap; border: 0; background: transparent; }
.sx-seg svg { width: 14px; height: 14px; }
.sx-seg .on { background: var(--surface); color: var(--text-primary); box-shadow: var(--shadow-sm); }
.sx-seg .n { font-family: 'JetBrains Mono', ui-monospace, monospace; font-size: 11px; color: var(--text-muted); font-weight: 500; }

/* ── Badges & tags ───────────────────────────────────────────────────── */
.sx-badge { display: inline-flex; align-items: center; gap: 6px; height: 22px; padding: 0 8px; border-radius: 6px; font-size: 11.5px; font-weight: 600; white-space: nowrap; }
.sx-badge::before { content: ""; width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
.sx-badge.nodot::before { display: none; }
.sx-badge svg { width: 12px; height: 12px; }
.sx-badge.ok { background: var(--success-soft); color: var(--success); }
.sx-badge.warn { background: var(--warning-soft); color: var(--warning); }
.sx-badge.danger { background: var(--danger-soft); color: var(--danger); }
.sx-badge.info { background: var(--brand-subtle); color: var(--brand); }
.sx-badge.muted { background: var(--bg-subtle); color: var(--text-secondary); border: 1px solid var(--border); }
.sx-tag { font-family: 'JetBrains Mono', ui-monospace, monospace; font-size: 10.5px; font-weight: 600; letter-spacing: .03em; text-transform: uppercase; color: var(--text-secondary); border: 1px solid var(--border); background: var(--bg-subtle); border-radius: 4px; padding: 2px 6px; line-height: 1.35; white-space: nowrap; }

/* ── Roles: pip, chip and the access fingerprint ─────────────────────── */
.pip { width: 8px; height: 8px; border-radius: 2px; background: var(--rc, var(--r-emp)); flex: none; display: inline-block; }
.sx-role { display: inline-flex; align-items: center; gap: 7px; font-size: 12.5px; font-weight: 600; color: var(--text-primary); white-space: nowrap; }
.fp { display: inline-flex; gap: 2px; align-items: center; flex: none; }
.fp i { width: 5px; height: 12px; border-radius: 1.5px; background: var(--border-md); opacity: .6; }
.fp i.on { background: var(--rc); opacity: 1; }
.fp i:nth-child(3), .fp i:nth-child(6) { margin-right: 3px; }

/* ── People ──────────────────────────────────────────────────────────── */
.av { width: 30px; height: 30px; border-radius: 50%; display: grid; place-items: center; font-size: 11.5px; font-weight: 700; flex: none;
      background: color-mix(in srgb, var(--rc, var(--brand)) 15%, var(--surface)); color: var(--rc, var(--brand)); }
.av.sm { width: 22px; height: 22px; font-size: 9px; }
.av.lg { width: 46px; height: 46px; font-size: 15px; }
.person { display: flex; align-items: center; gap: 10px; min-width: 0; }
.person .nm { font-weight: 600; color: var(--text-primary); line-height: 1.25; white-space: nowrap; }
.person .nm a { color: inherit; }
.person .sb { font-size: 11.5px; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.you { font-size: 9.5px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: var(--brand); background: var(--brand-subtle); border-radius: 4px; padding: 1px 5px; margin-left: 6px; vertical-align: 1px; }

/* ── Tables ──────────────────────────────────────────────────────────── */
.sx-table-wrap { overflow-x: auto; }
.sx-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 13px; margin: 0; }
.sx-table th { text-align: left; font-size: 10.5px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: var(--text-muted); padding: 9px 14px; background: var(--bg-subtle); border-bottom: 1px solid var(--border); white-space: nowrap; }
.sx-table td { padding: 10px 14px; border-bottom: 1px solid var(--border); vertical-align: middle; color: var(--text-primary); }
.sx-table tr:last-child td { border-bottom: none; }
.sx-table tr[data-href] { cursor: pointer; }
.sx-table tr[data-href]:hover td { background: var(--bg-subtle); }
.sx-table tr.sel td, .sx-table tr.sel:hover td { background: var(--brand-subtle); }
.sx-table tr.sel td:first-child { box-shadow: inset 3px 0 0 var(--brand); }
.sx-table tr.off td > * { opacity: .5; }
.sx-table .muted { color: var(--text-secondary); }
.sx-table .dim { color: var(--text-muted); font-size: 12px; }

/* ── Small labels, alerts ────────────────────────────────────────────── */
.sx-label { font-family: 'JetBrains Mono', ui-monospace, monospace; font-size: 10px; font-weight: 600; letter-spacing: .1em; text-transform: uppercase; color: var(--text-muted); }
.sx-kbd { font-family: 'JetBrains Mono', ui-monospace, monospace; font-size: 10.5px; border: 1px solid var(--border); border-bottom-width: 2px; border-radius: 4px; padding: 0 5px; color: var(--text-muted); background: var(--surface); }
.sx-alert { display: flex; gap: 10px; align-items: flex-start; padding: 11px 14px; border-radius: 10px; font-size: 13px; margin-bottom: 14px; background: var(--danger-soft); color: var(--danger); border: 1px solid color-mix(in srgb, var(--danger) 35%, transparent); }
.sx-alert svg { width: 16px; height: 16px; flex: none; margin-top: 1px; }
.sx-alert ul { margin: 4px 0 0; padding-left: 18px; }
.sx-pager { display: flex; gap: 4px; margin-left: auto; }
.sx-pager a, .sx-pager span { min-width: 28px; height: 28px; padding: 0 6px; border: 1px solid var(--border); border-radius: 6px; display: grid; place-items: center; font-family: 'JetBrains Mono', monospace; font-size: 11.5px; color: var(--text-secondary); background: var(--surface); }
.sx-pager a:hover { border-color: var(--brand); color: var(--brand); }
.sx-pager .on { background: var(--brand); border-color: var(--brand); color: #fff; }
.sx-pager .gap { border-color: transparent; background: transparent; }
.sx-pager .dis { opacity: .4; }
.sx-pager svg { width: 14px; height: 14px; }

/* ══ System Settings hub ═══════════════════════════════════════════════ */
.st-wrap { display: grid; grid-template-columns: 236px minmax(0, 1fr); gap: 16px; align-items: start; }
@media (max-width: 900px) { .st-wrap { grid-template-columns: 1fr; } }
.st-nav { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 4px 8px 8px; box-shadow: var(--shadow-xs); position: sticky; top: 16px; }
@media (max-width: 900px) { .st-nav { position: static; } }
.st-nav > .sx-label { display: block; padding: 12px 10px 6px; }
.st-item { display: flex; gap: 11px; align-items: center; padding: 8px 10px; border-radius: 9px; text-decoration: none; margin-bottom: 2px; position: relative; }
.st-item:hover { background: var(--bg-subtle); }
.st-item .ic { width: 32px; height: 32px; border-radius: 8px; background: var(--bg-subtle); border: 1px solid var(--border); display: grid; place-items: center; color: var(--text-secondary); flex: none; }
.st-item .ic svg { width: 16px; height: 16px; }
.st-item .t { display: block; font-size: 13px; font-weight: 600; color: var(--text-primary); line-height: 1.25; }
.st-item .d { display: block; font-size: 11.5px; color: var(--text-muted); margin-top: 1px; white-space: nowrap; }
.st-item.on, .st-item.on:hover { background: var(--brand-subtle); box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--brand) 22%, transparent); }
.st-item.on .ic { background: var(--brand); border-color: var(--brand); color: #fff; }
.st-item .dirty { position: absolute; right: 12px; top: 50%; margin-top: -4px; width: 8px; height: 8px; border-radius: 50%; background: var(--warning); box-shadow: 0 0 0 3px var(--warning-soft); }
.st-nav-foot { margin: 8px 2px 0; padding: 12px 8px 4px; border-top: 1px solid var(--border); font-size: 11.5px; color: var(--text-muted); line-height: 1.5; display: flex; gap: 8px; }
.st-nav-foot svg { width: 14px; height: 14px; flex: none; margin-top: 1px; }

/* The left column (settings/_side): the nav, then the current values filling
   what is left of it. On a wide screen it takes the height of the form beside
   it — never adding its own — and is held to the screen while that scrolls. */
.st-side { min-width: 0; }
.st-side-in { display: flex; flex-direction: column; gap: 14px; }
@media (min-width: 901px) {
    /* body's overflow-x: hidden makes it a scroll container that never
       scrolls, which pins every sticky element in place. clip hides the same
       overflow without that, so the column can follow the page. */
    body:has(.st-side) { overflow-x: clip; overflow-y: visible; }
    .st-wrap:has(> .st-side) { align-items: stretch; }
    .st-side { contain: size; }
    .st-side-in { position: sticky; top: calc(var(--topbar-height, 60px) + 16px); height: 100%; max-height: calc(100vh - var(--topbar-height, 60px) - 32px); }
    .st-side .st-nav { position: static; flex: none; }
}
.st-glance { flex: 1 1 auto; min-height: 0; display: flex; flex-direction: column; overflow: hidden; background: var(--surface); border: 1px solid var(--border); border-radius: 12px; box-shadow: var(--shadow-xs); }
.st-glance-head { display: flex; align-items: center; justify-content: space-between; padding: 12px 18px 6px; flex: none; }
.st-glance-head .live { font-size: 11px; color: var(--text-muted); }
.st-glance-list { flex: 1 1 auto; min-height: 0; display: flex; flex-direction: column; padding: 0 8px; overflow: hidden; }
.st-gl { flex: 1 1 auto; min-height: 30px; display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 0 10px; border-radius: 8px; font-size: 12px; text-decoration: none; }
.st-gl + .st-gl { box-shadow: inset 0 1px 0 var(--border); }
.st-gl:hover { background: var(--bg-subtle); }
.st-gl.on, .st-gl.on + .st-gl.on { background: var(--brand-subtle); box-shadow: none; }
.st-gl .k { color: var(--text-muted); white-space: nowrap; flex: none; }
.st-gl .v { color: var(--text-primary); font-weight: 600; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; text-align: right; }
.st-gl.on .v { color: var(--brand); }
.st-glance-foot { flex: none; margin: 6px 8px 8px; padding: 10px 10px 4px; border-top: 1px solid var(--border); font-size: 12px; }

/* Two cards side by side share one height; their rows share the difference,
   so the shorter card has no empty band at its foot. */
.st-two > .sx-card { display: flex; flex-direction: column; }
.st-two > .sx-card > :not(.sx-card-head) { flex: 1 1 auto; }
.st-two > .sx-card > .chg { align-content: center; }
.st-two > .sx-card > .sx-empty { display: flex; flex-direction: column; justify-content: center; }
.st-last { display: inline-flex; align-items: center; gap: 7px; font-size: 12px; color: var(--text-muted); }
.st-last svg { width: 14px; height: 14px; }
.st-last b { color: var(--text-secondary); font-weight: 600; }

.st-row { display: grid; grid-template-columns: 240px minmax(0, 1fr); gap: 28px; padding: 16px 18px; border-bottom: 1px solid var(--border); position: relative; }
@media (max-width: 1100px) { .st-row { grid-template-columns: 1fr; gap: 10px; } }
.st-row:last-child { border-bottom: none; }
.st-row-l .t { font-size: 13px; font-weight: 600; color: var(--text-primary); display: flex; align-items: center; gap: 8px; }
.st-row-l .d { font-size: 12px; color: var(--text-muted); margin-top: 4px; line-height: 1.5; }
.st-field { display: flex; flex-direction: column; gap: 7px; min-width: 0; }
.st-inline { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.st-help { font-size: 11.5px; color: var(--text-muted); display: flex; align-items: center; gap: 6px; }
.st-help svg { width: 12px; height: 12px; flex: none; }
.st-help .was { text-decoration: line-through; }
.st-count { margin-left: auto; font-family: 'JetBrains Mono', ui-monospace, monospace; font-size: 11px; color: var(--text-muted); }
.edited { font-size: 9.5px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; color: var(--warning); background: var(--warning-soft); border-radius: 4px; padding: 2px 6px; }
.sx-input.wide { height: 38px; font-size: 13.5px; }
.sx-input.dirty, input.sx-input.dirty { border-color: var(--warning); box-shadow: 0 0 0 3px var(--warning-soft); }

.stepper { display: inline-flex; align-items: stretch; border: 1px solid var(--border); border-radius: 8px; overflow: hidden; background: var(--surface); height: 36px; box-shadow: var(--shadow-xs); }
.stepper.dirty { border-color: var(--warning); box-shadow: 0 0 0 3px var(--warning-soft); }
.stepper button { width: 34px; display: grid; place-items: center; color: var(--text-secondary); background: var(--bg-subtle); border: 0; padding: 0; }
.stepper button:hover { color: var(--brand); }
.stepper button svg { width: 14px; height: 14px; }
.stepper .v { min-width: 96px; padding: 0 10px; display: flex; align-items: center; justify-content: center; gap: 6px; border-left: 1px solid var(--border); border-right: 1px solid var(--border); }
.stepper input { width: 52px; border: 0; outline: 0; background: transparent; text-align: right; font-family: 'JetBrains Mono', ui-monospace, monospace; font-size: 14px; font-weight: 600; color: var(--text-primary); padding: 0; -moz-appearance: textfield; }
.stepper input::-webkit-outer-spin-button, .stepper input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
.stepper .u { font-size: 12px; font-weight: 500; color: var(--text-muted); }
.presets { display: inline-flex; gap: 6px; flex-wrap: wrap; }
.presets button { height: 28px; padding: 0 11px; border-radius: 999px; border: 1px solid var(--border); font-size: 12px; font-weight: 600; color: var(--text-secondary); display: inline-flex; align-items: center; gap: 5px; background: var(--surface); }
.presets button:hover { border-color: var(--brand); color: var(--brand); }
.presets button.on { border-color: var(--brand); color: var(--brand); background: var(--brand-subtle); }
.presets button svg { width: 12px; height: 12px; stroke-width: 2.6; }
.range { font-family: 'JetBrains Mono', ui-monospace, monospace; font-size: 11px; color: var(--text-muted); }

.savebar { display: flex; align-items: center; gap: 12px; margin-top: 14px; padding: 10px 12px 10px 16px; border-radius: 12px; background: var(--inverse); color: var(--inverse-text); box-shadow: var(--shadow-xl); font-size: 13px; position: sticky; bottom: 14px; z-index: 5; flex-wrap: wrap; }
.savebar > span { white-space: nowrap; }
.savebar .dot { flex: none; width: 8px; height: 8px; border-radius: 50%; background: #FDB022; box-shadow: 0 0 0 3px rgba(253,176,34,.25); }
.savebar b { font-weight: 600; }
.savebar .muted { opacity: .7; }
.savebar .sx-btn.quiet { color: inherit; opacity: .85; }
.savebar .sp { margin-left: auto; }
.savebar .when-clean { display: none; }
.savebar.clean { background: var(--surface); color: var(--text-muted); border: 1px solid var(--border); box-shadow: var(--shadow-xs); position: relative; bottom: auto; }
.savebar.clean .when-clean { display: inline-flex; align-items: center; gap: 10px; }
.savebar.clean .when-dirty { display: none; }
.savebar.clean svg.ok { width: 16px; height: 16px; color: var(--success); }
.savebar.clean .sx-btn.quiet { color: var(--text-secondary); }
</style>
