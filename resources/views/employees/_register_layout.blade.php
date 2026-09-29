{{-- Register Employee on one screen.

     On a wide screen the form's sections sit in three columns instead of one
     long page, so HR sees every field without scrolling:

         Employment & Pay │ Personal Information │ Government IDs
                          │ Address              │ Contact Information
         ───────────── progress · Cancel · Register ─────────────

     Only the arrangement changes. The fields, their names, their order in
     the markup and everything the form posts are exactly what
     _profile_fields.blade.php and create.blade.php already render; Edit
     Employee shares those fields and keeps its long layout. Below 1200px
     the page falls back to the stacked sections. --}}
<style>
/* ── Section head: counter chip ──────────────────────────────────────── */
.rgx .ep-section-head { position: relative; }
.rgx-chip {
    margin-left: auto; flex: none;
    font: 700 .72rem/1 ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    font-variant-numeric: tabular-nums;
    padding: 5px 8px; border-radius: 999px;
    color: var(--warning, #b26a00);
    background: var(--warning-soft, #fff4e0);
}
.rgx-chip.is-done { color: var(--success, #1d7a46); background: var(--success-soft, #e6f4ec); }
.rgx-chip.is-opt  { color: var(--text-muted, #8a929b); background: transparent; font-weight: 600;
                    font-family: inherit; padding-right: 0; }

/* ── Progress in the action bar ──────────────────────────────────────── */
.rgx-prog { display: flex; align-items: center; gap: 10px; flex: none; }
.rgx-bar {
    width: 140px; height: 5px; border-radius: 999px; overflow: hidden;
    background: var(--bg-subtle, #eef0f3);
}
.rgx-bar i {
    display: block; height: 100%; width: 0;
    background: var(--brand, #1e5c9b); border-radius: inherit;
    transition: width .25s ease, background-color .25s ease;
}
.rgx-bar i.is-done { background: var(--success, #1d7a46); }
.rgx-count { font-size: .8rem; color: var(--text-secondary, #66707c); white-space: nowrap; }
.rgx-count b { color: var(--text-primary, #1b2430); font-variant-numeric: tabular-nums; }
.rgx .ep-actions-note { margin-left: 4px; }

/* ── Three columns ───────────────────────────────────────────────────── */
@media (min-width: 1200px) {
    .rgx {
        display: grid;
        grid-template-columns: 1.12fr 1fr 1fr;
        grid-template-areas:
            "emp per ids"
            "emp adr con"
            "act act act";
        gap: 12px;
        align-items: stretch;
    }
    .rgx > .ep-section { margin: 0; padding: 14px 16px; }
    .rgx > .ep-section:has(#first_name) { grid-area: emp; }
    .rgx > .ep-section:has(#birth_date) { grid-area: per; }
    .rgx > .ep-section:has(#phone) { grid-area: con; }
    .rgx > .ep-section:has(#address_province) { grid-area: adr; }
    .rgx > .ep-section:has(#sss_number) { grid-area: ids; }
    .rgx > .ep-actions { grid-area: act; margin: 0; }

    /* Head: title only. The one-line explanations under each title are
       what made the page long; the labels and the chip say the same. */
    .rgx .ep-section-head { margin-bottom: 10px; padding-bottom: 10px; }
    .rgx .ep-section-icon { width: 30px; height: 30px; font-size: 13px; }
    .rgx .ep-section-sub { display: none; }

    /* Every section's fields in a two-column grid, whatever Bootstrap
       column classes they carry for the stacked layout. */
    .rgx .ep-section .row.g-3 {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 8px 12px;
        margin: 0;
    }
    .rgx .ep-section .row.g-3 > * {
        width: auto; max-width: none; flex: none;
        padding: 0; margin: 0; min-width: 0;
    }
    .rgx .ep-section .row.g-3 > .col-12 { grid-column: 1 / -1; }

    .rgx .form-control, .rgx .form-select { min-height: 36px; padding-top: 6px; padding-bottom: 6px; font-size: .88rem; }
    .rgx .ep-label { margin-bottom: 4px; font-size: .68rem; }

    /* Standing help text goes; what the form says back (a locked Position,
       a validation message) stays. */
    .rgx .ep-hint { display: none; }
    .rgx .ep-hint.js-position-hint:not(:empty) { display: block; font-size: .7rem; margin-top: 3px; }

    /* Employment & Pay: Date Hired beside Site, the contract pair after. */
    .rgx .js-contract-only { order: 2; }

    /* Personal: Nationality beside the short Blood box. */
    .rgx .ep-section .row.g-3 > :has(#blood_type) { order: 2; }

    /* Address: the three look-ups, then the street across the whole row. */
    .rgx .ep-section .row.g-3 > :has(#address_street) { order: 2; grid-column: 1 / -1; }

    /* Contact: the emergency contact as its own inset block. */
    .rgx .ep-subhead { margin: 14px 0 8px; padding-top: 12px; }
    .rgx .ep-section .row.g-3 > :has(#emergency_contact_name) { grid-column: 1 / -1; }

    .rgx > .ep-actions { padding-top: 10px; padding-bottom: 10px; }
    .rgx .ep-actions .btn { white-space: nowrap; }
    .rgx .ep-actions-note { flex: 1; min-width: 0; font-size: .74rem; line-height: 1.35; }

    /* The IDs section's chip already says Optional; four "(optional)"s
       only cut the labels short. */
    .rgx .ep-section:has(#sss_number) .ep-optional { display: none; }
}
</style>
