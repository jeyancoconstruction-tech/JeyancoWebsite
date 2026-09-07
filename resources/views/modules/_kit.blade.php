{{-- Shared chrome for the modules added around payroll.

     Included rather than copied, exactly as employees/_profile_styles.blade.php
     is, so eleven pages cannot drift into eleven dialects. Every value is a
     design token, so light and dark follow one set of rules and there is no
     parallel [data-bs-theme="dark"] block to keep in step.

     Nothing global is touched: this is a page-scoped <style>, the same way the
     existing Register & Manage and Sites pages carry their own. --}}
<style>
/* ── Page ─────────────────────────────────────────────────────────────── */
.mod-page { max-width: none; width: 100%; }

.mod-head {
    display: flex; justify-content: space-between; align-items: flex-start;
    gap: 16px; flex-wrap: wrap; margin-bottom: 18px;
}
.mod-title {
    font-size: 1.45rem; font-weight: 700; letter-spacing: -.02em;
    color: var(--text-primary); margin: 0 0 4px;
}
.mod-sub {
    font-size: .85rem; color: var(--text-secondary); margin: 0; max-width: 70ch;
}
.mod-head-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }

/* ── Stat strip · a row of figures, not a wall of cards ───────────────── */
.mod-stats {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 10px; margin-bottom: 16px;
}
.mod-stat {
    background: var(--bg-surface); border: 1px solid var(--border);
    border-radius: var(--radius-md); padding: 11px 14px;
}
.mod-stat-label {
    font-size: .68rem; font-weight: 700; letter-spacing: .06em;
    text-transform: uppercase; color: var(--text-muted); margin: 0 0 4px;
}
.mod-stat-value {
    font-size: 1.15rem; font-weight: 700; color: var(--text-primary);
    font-variant-numeric: tabular-nums; line-height: 1.15; margin: 0;
}
.mod-stat-value.is-ok     { color: var(--success); }
.mod-stat-value.is-warn   { color: var(--warning); }
.mod-stat-value.is-danger { color: var(--danger); }
.mod-stat-sub { font-size: .72rem; color: var(--text-muted); margin: 2px 0 0; }

/* ── Panel ────────────────────────────────────────────────────────────── */
.mod-card {
    background: var(--bg-surface); border: 1px solid var(--border);
    border-radius: var(--radius-lg); overflow: hidden; margin-bottom: 16px;
}
.mod-card-head {
    display: flex; align-items: center; justify-content: space-between;
    gap: 12px; flex-wrap: wrap;
    padding: 12px 16px; border-bottom: 1px solid var(--border);
}
.mod-card-title {
    margin: 0; font-size: .9rem; font-weight: 600; color: var(--text-primary);
    display: flex; align-items: center; gap: 8px;
}
.mod-card-title i { color: var(--brand); font-size: .85rem; }
.mod-card-body { padding: 16px; }

/* ── Filters ──────────────────────────────────────────────────────────── */
.mod-filters {
    display: flex; flex-wrap: wrap; gap: 10px; align-items: flex-end;
    padding: 12px 16px; border-bottom: 1px solid var(--border);
    background: var(--bg-subtle);
}
.mod-filter { display: flex; flex-direction: column; gap: 4px; min-width: 0; }
.mod-filter > label {
    font-size: .68rem; font-weight: 700; letter-spacing: .05em;
    text-transform: uppercase; color: var(--text-muted);
}
.mod-filters .form-control,
.mod-filters .form-select { height: 36px; font-size: 13px; padding: 0 10px; min-width: 118px; }
.mod-filters .form-select { padding-right: 28px; }
.mod-filter-grow { flex: 1 1 180px; }

/* A native date box renders wider than its min-width because of the picker
   glyph. Left at the global 150px, five filters plus Apply and Reset spilled
   onto a second row on a 1366px laptop and left a band of dead space above
   the buttons. */
.mod-filters input[type="date"] { min-width: 128px !important; }
.mod-filters .mod-filter { flex: 0 1 auto; min-width: 0; }

/* A hidden input is still a flex child: it contributes nothing visible and a
   full 10px gap, which was the eight pixels that pushed Apply onto its own
   row. Take it out of the flow entirely. */
.mod-filters input[type="hidden"] { display: none; }

/* A select is as wide as its longest option — "Leave Without Pay" was making
   the type filter 177px. It can truncate; the open list still shows it whole. */
.mod-filters .form-select { max-width: 150px; text-overflow: ellipsis; }
.mod-filter-grow .form-control { width: 100%; }
.mod-filter-actions { display: flex; gap: 8px; align-items: center; margin-left: auto; }

/* ── Table ────────────────────────────────────────────────────────────── */
.mod-table-wrap { overflow-x: auto; }
.mod-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.mod-table thead th {
    position: sticky; top: 0; z-index: 2;
    background: var(--bg-subtle); color: var(--text-muted);
    font-size: .68rem; font-weight: 700; letter-spacing: .05em; text-transform: uppercase;
    padding: 9px 14px; text-align: left; white-space: nowrap;
    border-bottom: 1px solid var(--border);
}
.mod-table tbody td {
    padding: 10px 14px; border-bottom: 1px solid var(--border);
    color: var(--text-primary); vertical-align: middle;
}
.mod-table tbody tr:last-child td { border-bottom: none; }
.mod-table tbody tr:hover td { background: var(--bg-subtle); }
.mod-table .num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
.mod-table .muted { color: var(--text-secondary); }
.mod-table .strong { font-weight: 600; }

.mod-person { display: flex; align-items: center; gap: 9px; min-width: 0; }
.mod-avatar {
    width: 28px; height: 28px; border-radius: 50%; flex: none;
    display: flex; align-items: center; justify-content: center;
    background: var(--brand-subtle); color: var(--brand);
    font-size: 11px; font-weight: 700;
}
.mod-person-name { font-weight: 600; color: var(--text-primary); }
.mod-person-sub { font-size: .72rem; color: var(--text-muted); }

/* ── Badges · the app's semantic colours, never a new palette ─────────── */
.mod-badge {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 3px 9px; border-radius: 999px;
    font-size: .7rem; font-weight: 700; letter-spacing: .02em; white-space: nowrap;
}
.mod-badge.ok     { background: var(--success-soft); color: var(--success); }
.mod-badge.warn   { background: var(--warning-soft); color: var(--warning); }
.mod-badge.danger { background: var(--danger-soft);  color: var(--danger); }
.mod-badge.info   { background: var(--brand-subtle); color: var(--brand); }
.mod-badge.muted  { background: var(--bg-subtle);    color: var(--text-secondary); }
.mod-badge .dot { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }

/* ── Buttons · same sizes as the rest of the app ──────────────────────── */
.mod-btn {
    height: 36px; padding: 0 14px; font-size: 13px; font-weight: 600;
    border-radius: var(--radius-sm); cursor: pointer; white-space: nowrap;
    display: inline-flex; align-items: center; justify-content: center; gap: 7px;
    border: 1px solid var(--border); background: var(--bg-surface);
    color: var(--text-primary); text-decoration: none;
    transition: background .15s ease, border-color .15s ease, color .15s ease;
}
.mod-btn:hover { background: var(--brand-subtle); border-color: var(--brand); color: var(--brand); }
.mod-btn:focus-visible { outline: 2px solid var(--brand); outline-offset: 2px; }
.mod-btn.primary {
    background: var(--brand); border-color: var(--brand); color: #fff; font-weight: 700;
}
.mod-btn.primary:hover { background: var(--brand-strong); border-color: var(--brand-strong); color: #fff; }
.mod-btn.danger { color: var(--danger); }
.mod-btn.danger:hover { background: var(--danger-soft); border-color: var(--danger); color: var(--danger); }
.mod-btn.ok { color: var(--success); }
.mod-btn.ok:hover { background: var(--success-soft); border-color: var(--success); color: var(--success); }
.mod-btn:disabled, .mod-btn.is-disabled { opacity: .55; cursor: not-allowed; }
.mod-btn.sm { height: 30px; padding: 0 10px; font-size: 12px; }

.mod-row-actions { display: flex; gap: 6px; justify-content: flex-end; }

/* ── Tabs · matching the Attendance page's underline ──────────────────── */
.mod-tabs { display: flex; gap: 4px; border-bottom: 1px solid var(--border); margin-bottom: 16px; }
.mod-tab {
    padding: 9px 16px; font-size: 13px; font-weight: 700;
    color: var(--text-secondary); text-decoration: none;
    border-bottom: 2px solid transparent; background: none;
    display: inline-flex; align-items: center; gap: 7px;
}
.mod-tab:hover { color: var(--brand); }
.mod-tab.active { color: var(--brand); border-bottom-color: var(--brand); }
.mod-tab-count {
    min-width: 18px; height: 18px; padding: 0 5px; border-radius: 9px;
    background: var(--bg-subtle); color: var(--text-secondary);
    font-size: 10.5px; font-weight: 700; line-height: 18px; text-align: center;
}
.mod-tab.active .mod-tab-count { background: var(--brand-subtle); color: var(--brand); }

/* ── States ───────────────────────────────────────────────────────────── */
.mod-empty { padding: 40px 20px; text-align: center; color: var(--text-muted); }
.mod-empty i { font-size: 24px; opacity: .45; display: block; margin-bottom: 10px; }
.mod-empty-title { font-size: 14px; font-weight: 600; color: var(--text-secondary); margin: 0 0 4px; }
.mod-empty-sub { font-size: 12.5px; margin: 0; }

.mod-alert {
    display: flex; gap: 10px; align-items: flex-start;
    padding: 11px 14px; border-radius: var(--radius-md);
    font-size: 13px; margin-bottom: 16px; border: 1px solid transparent;
}
.mod-alert i { margin-top: 2px; flex: none; }
.mod-alert.ok     { background: var(--success-soft); color: var(--success); border-color: var(--success); }
.mod-alert.err    { background: var(--danger-soft);  color: var(--danger);  border-color: var(--danger); }
.mod-alert.info   { background: var(--brand-subtle); color: var(--brand);   border-color: var(--brand); }
.mod-alert ul { margin: 4px 0 0; padding-left: 18px; }

.mod-note {
    display: flex; gap: 8px; align-items: flex-start;
    padding: 10px 12px; margin: 0 0 14px;
    font-size: .8rem; line-height: 1.45;
    color: var(--text-secondary); background: var(--bg-subtle);
    border: 1px solid var(--border); border-radius: var(--radius-md);
}
.mod-note i { color: var(--brand); margin-top: 2px; flex: none; }

/* ── Pagination · Laravel's own markup, toned to the app ──────────────── */
.mod-pager { padding: 10px 16px; border-top: 1px solid var(--border); }
.mod-pager .pagination { margin: 0; font-size: 13px; }

/* ── Definition grid, for a payslip or a run summary ──────────────────── */
.mod-dl { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px 20px; }
.mod-dl dt {
    font-size: .68rem; font-weight: 700; letter-spacing: .05em;
    text-transform: uppercase; color: var(--text-muted); margin: 0 0 2px;
}
.mod-dl dd {
    margin: 0; font-size: 13.5px; font-weight: 600; color: var(--text-primary);
    font-variant-numeric: tabular-nums;
}

/* ── Forms inside module modals reuse the employee form's chrome ──────── */
.mod-form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
@media (max-width: 575px) { .mod-form-grid { grid-template-columns: 1fr; } }
.mod-form-grid .full { grid-column: 1 / -1; }

@media (max-width: 767px) {
    .mod-filters { padding: 12px; }
    .mod-filter-actions { margin-left: 0; width: 100%; }
    .mod-table { min-width: 720px; }
}
</style>

