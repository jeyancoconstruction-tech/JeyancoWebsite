{{-- Register Employee on one screen.

     On a wide screen the form's sections sit in three columns instead of one
     long page, so HR sees every field without scrolling:

         Employment & Pay │ Personal Information │ Government IDs
                          │ Address              │ Contact Information

     Employment & Pay, the one required section, uses its whole column:
     First │ Middle, Last + Suffix, Labor Type, Shift │ Rate,
     Position │ Date Hired, Site.
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

    /* Employment & Pay is the tallest column's neighbour and was left with
       empty space under Site while Last Name shared half a row with the
       Suffix. The surname and the labor type (its options carry the day
       rate) get whole rows. Tab order stays the markup's. */
    .rgx .ep-section:has(#first_name) .row.g-3 { grid-auto-flow: row dense; row-gap: 14px; }
    .rgx .ep-section .row.g-3 > :has(#last_name),
    .rgx .ep-section .row.g-3 > :has(select[name="labor_type_id"]),
    .rgx .ep-section .row.g-3 > :has(#site_select) { grid-column: 1 / -1; }

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

{{-- Edit Employee: the strip at the top and the change marks. --}}
<style>
.rgx-who {
    display: flex; align-items: center; gap: 14px;
    padding: 12px 16px; margin-bottom: 14px;
    background: var(--bg-surface, #fff);
    border: 1px solid var(--border, #e3e6e9);
    border-radius: var(--radius-lg, 6px);
}
.rgx-who-av {
    flex: none; width: 46px; height: 46px; border-radius: 12px;
    display: inline-flex; align-items: center; justify-content: center;
    background: var(--brand-subtle, #edf3f9); color: var(--brand, #1e5c9b);
}
.rgx-who-main { min-width: 0; flex: 1; }
.rgx-who-name {
    font-size: 1.05rem; font-weight: 700; line-height: 1.2;
    color: var(--text-primary, #1b2430);
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.rgx-who-meta { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 6px; }
.rgx-tag {
    display: inline-flex; align-items: center; gap: 5px;
    font-size: .72rem; font-weight: 600; line-height: 1;
    padding: 5px 8px; border-radius: 6px;
    color: var(--text-secondary, #66707c);
    background: var(--bg-subtle, #f1f3f5);
}
.rgx-tag i { font-size: .7rem; opacity: .8; }
.rgx-tag-id   { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; color: var(--text-primary, #1b2430); }
.rgx-tag-ok   { color: var(--success, #1d7a46); background: var(--success-soft, #e6f4ec); }
.rgx-tag-warn { color: var(--warning, #b26a00); background: var(--warning-soft, #fff4e0); }

.rgx-rates {
    flex: none; display: flex;
    border: 1px solid var(--border, #e3e6e9); border-radius: 8px; overflow: hidden;
}
.rgx-rate { padding: 7px 14px; border-left: 1px solid var(--border, #e3e6e9); }
.rgx-rate:first-child { border-left: 0; }
.rgx-rate small {
    display: block; font-size: .64rem; font-weight: 700; letter-spacing: .04em;
    text-transform: uppercase; color: var(--text-muted, #8a929b);
}
.rgx-rate b { font-size: .86rem; color: var(--text-primary, #1b2430); font-variant-numeric: tabular-nums; white-space: nowrap; }
.rgx-rate-name { background: var(--brand-subtle, #edf3f9); }
.rgx-rate-name b { color: var(--brand, #1e5c9b); }

/* A field that differs from what was loaded: a dot on its label and a
   brand edge on the box. */
.rgx-changed .ep-label::after {
    content: ""; display: inline-block; width: 6px; height: 6px; margin-left: 6px;
    border-radius: 50%; background: var(--brand, #1e5c9b); vertical-align: middle;
}
.rgx-changed .form-control, .rgx-changed .form-select { border-color: var(--brand, #1e5c9b) !important; }

.rgx-dirty {
    font-size: .76rem; font-weight: 700; padding: 5px 10px; border-radius: 999px;
    color: var(--text-muted, #8a929b); background: var(--bg-subtle, #f1f3f5);
}
.rgx-dirty.is-on { color: #fff; background: var(--brand, #1e5c9b); }

@media (min-width: 1200px) {
    .rgx-edit {
        grid-template-areas:
            "who who who"
            "emp per ids"
            "emp adr con"
            "act act act";
    }
    .rgx-edit > .rgx-who { grid-area: who; margin: 0; }
}
@media (max-width: 991px) {
    .rgx-who { flex-wrap: wrap; }
    .rgx-rates { width: 100%; }
    .rgx-rate { flex: 1; }
}
</style>
