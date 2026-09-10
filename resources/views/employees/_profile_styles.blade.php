{{-- Section chrome for the employee form.

     Every colour comes from the app's design tokens, which are redefined under
     html[data-bs-theme="dark"] — so light and dark both follow the same rules
     and there is nothing theme-specific to keep in sync here. The fallbacks
     after each token are the light values, in case this renders before
     design-tokens.css lands.

     Inputs themselves are left to Bootstrap's .form-control / .form-select,
     which enterprise.css already themes for both modes. --}}
<style>
/* ── Section card ─────────────────────────────────────────────────────── */
.ep-section {
    background: var(--bg-surface, #fff);
    border: 1px solid var(--border, #e3e6e9);
    border-radius: var(--radius-lg, 6px);
    padding: 20px 22px;
    margin-bottom: 14px;
}

.ep-section-head {
    display: flex;
    gap: 12px;
    align-items: center;
    margin-bottom: 16px;
    padding-bottom: 12px;
    border-bottom: 1px solid var(--border, #e3e6e9);
}
.ep-section-icon {
    flex: none;
    width: 34px; height: 34px;
    border-radius: var(--radius-md, 6px);
    background: var(--brand-subtle, #edf3f9);
    color: var(--brand, #1e5c9b);
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 14px;
}
.ep-section-title {
    font-size: .98rem; font-weight: 700; line-height: 1.25;
    color: var(--text-primary, #1b2430);
    margin: 0 0 1px;
}
.ep-section-sub {
    font-size: .78rem; line-height: 1.3;
    color: var(--text-secondary, #66707c);
    margin: 0;
}

/* ── Field chrome ─────────────────────────────────────────────────────── */
.ep-label {
    display: block;
    font-size: .72rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: .03em;
    color: var(--text-secondary, #66707c);
    margin-bottom: 5px;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.ep-hint {
    display: block;
    font-size: .74rem;
    color: var(--text-muted, #8a929b);
    margin-top: 4px;
}
/* Sub-heading inside a section, e.g. the emergency-contact group. */
.ep-subhead {
    font-size: .74rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: .04em;
    color: var(--text-muted, #8a929b);
    margin: 18px 0 10px;
    padding-top: 14px;
    border-top: 1px solid var(--border, #e3e6e9);
}

/* ID numbers read as digits, not prose. */
.ep-mono { font-variant-numeric: tabular-nums; letter-spacing: .01em; }

.ep-req      { color: var(--danger, #b3403a); font-weight: 700; }
.ep-optional { font-weight: 400; text-transform: none; letter-spacing: 0;
               color: var(--text-muted, #8a929b); }

/* ── Read-only field ──────────────────────────────────────────────────────
   View Details renders the same sections, the same labels and the same grid
   as Register Employee, with the stored answer where the input would be. It
   is deliberately shaped like a .form-control so the two pages read as one
   layout, and deliberately not one: no focus ring, no caret, nothing that
   suggests it can be typed into. Editing has its own page. */
.ep-value {
    display: block;
    min-height: 38px;
    padding: 8px 12px;
    border: 1px solid var(--border, #e3e6e9);
    border-radius: var(--radius-md, 6px);
    background: var(--bg-subtle, #f7f8fa);
    color: var(--text-primary, #1b2430);
    font-size: .9rem; line-height: 1.4;
    overflow-wrap: anywhere;
}
/* A blank answer says so, rather than leaving a box the eye has to interpret. */
.ep-value.is-empty { color: var(--text-muted, #8a929b); }
/* The photo picker CSS was here — .ep-photo*, .ep-cam*. Both the picker
   and its camera modal are gone with the field itself. */

/* ── Inline note ──────────────────────────────────────────────────────── */
.ep-note {
    display: flex; align-items: flex-start; gap: 8px;
    margin: 2px 0 0;
    padding: 10px 12px;
    font-size: .8rem; line-height: 1.45;
    color: var(--text-secondary, #66707c);
    background: var(--brand-subtle, #edf3f9);
    border: 1px solid var(--border, #e3e6e9);
    border-radius: var(--radius-md, 6px);
}
.ep-note i { color: var(--brand, #1e5c9b); margin-top: 2px; flex: none; }

/* ── Sticky action bar ────────────────────────────────────────────────── */
/* The form is long; keep Save reachable without scrolling back down. */
.ep-actions {
    position: sticky; bottom: 0; z-index: 5;
    display: flex; justify-content: flex-end; align-items: center; gap: 10px;
    background: var(--bg-surface, #fff);
    border: 1px solid var(--border, #e3e6e9);
    border-radius: var(--radius-lg, 6px);
    padding: 12px 16px;
    margin-bottom: 14px;
    box-shadow: var(--shadow-md, 0 1px 3px rgba(0,0,0,.07));
}
.ep-actions-note {
    margin: 0 auto 0 0;
    font-size: .78rem;
    color: var(--text-muted, #8a929b);
}

/* A field the form fills for you. design-tokens.css paints every .form-control
   with the plain surface and !important, so this needs the extra class to win
   — without it a locked field looks exactly like one you can type in. */
.form-control.ep-locked {
    background: var(--bg-subtle, #f1f3f5) !important;
    color: var(--text-secondary, #66707c) !important;
    cursor: not-allowed;
}

/* ── Address suggestions ──────────────────────────────────────────────── */
/* The list is positioned against .ap-field, not the grid column: a column is
   as tall as the tallest field in its row, so anchoring there would drop the
   suggestions below a validation message on a neighbouring input. */
.ap-field { position: relative; }
.ap-list {
    position: absolute;
    z-index: 30;
    left: 0; right: 0; top: calc(100% + 4px);
    max-height: 264px;
    overflow-y: auto;
    padding: 4px;
    background: var(--bg-elevated, #fff);
    border: 1px solid var(--border, #e3e6e9);
    border-radius: var(--radius-sm, 6px);
    box-shadow: var(--shadow-lg, 0 2px 8px rgba(0,0,0,.10));
}
.ap-list[hidden] { display: none; }
.ap-opt {
    padding: 7px 10px;
    border-radius: var(--radius-sm, 6px);
    font-size: .86rem;
    color: var(--text-primary, #1b2430);
    cursor: pointer;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.ap-opt:hover,
.ap-opt.is-active {
    background: var(--brand-subtle, #edf3f9);
    color: var(--brand, #1e5c9b);
}

@media (max-width: 576px) {
    .ep-section { padding: 16px 14px; }
    .ep-actions { flex-wrap: wrap; }
    .ep-actions-note { width: 100%; margin-bottom: 4px; }
}
</style>
