{{-- Chrome for the employee form modal.

     It lived inside register.blade.php's page-scoped <style>, which meant the
     six other screens using these class names — Leave & Overtime, Loans,
     Project Assignment, both Payroll Processing pages and the Employee
     directory — rendered their dialogs with no styling at all: a browser
     default close button, unstyled Cancel and Save, no navy header.

     Included rather than copied, the same way employees/_profile_styles is,
     so one dialog cannot drift from another. Every colour is a design token,
     so light and dark follow one set of rules. --}}
<style>
/* ── Employee form modal ──────────────────────────────────────────────────
   Every colour is a design token, so light and dark follow the same rules and
   there is no parallel [data-bs-theme="dark"] block to keep in sync — the same
   discipline employees/_profile_styles.blade.php already follows. The .ep-*
   labels, hints and required marks come from that partial, included below, so
   this modal and the Register Employee page cannot drift apart. */

.emp-dialog { max-width: 620px; }

.emp-modal {
    border: 1px solid var(--border, #e4e9f0) !important;
    border-radius: var(--radius-lg, 12px) !important;
    overflow: hidden;
    box-shadow: var(--shadow-xl, 0 24px 48px rgba(15,30,51,.14));
}

/* ── Head ─────────────────────────────────────────────────────────────── */
/* Navy, like every other modal header in the app — this one was the last
   place still wearing the old electric-blue gradient. */
.emp-head {
    display: flex; align-items: flex-start; gap: 13px;
    padding: 17px 20px;
    background: var(--sidebar-bg, #071a33);
    color: #fff;
}
.emp-head-icon {
    flex: none; width: 34px; height: 34px; border-radius: var(--radius-md, 10px);
    display: inline-flex; align-items: center; justify-content: center;
    background: rgba(255,255,255,.10);
    color: #8fbef7; font-size: 14px;
}
.emp-head-text { flex: 1 1 auto; min-width: 0; }
.emp-head-title { font-size: 15.5px; font-weight: 700; margin: 0 0 2px; color: #fff; letter-spacing: -.01em; }
.emp-head-sub { font-size: 12px; line-height: 1.4; margin: 0; color: #a9c0da; }
.emp-head-x {
    flex: none; width: 30px; height: 30px; padding: 0;
    background: rgba(255,255,255,.10); border: none; border-radius: var(--radius-sm, 8px);
    color: #cfe0f5; cursor: pointer;
    display: inline-flex; align-items: center; justify-content: center;
    transition: background .15s ease, color .15s ease;
}
.emp-head-x:hover { background: rgba(255,255,255,.20); color: #fff; }
.emp-head-x:focus-visible { outline: 2px solid #8fbef7; outline-offset: 2px; }

/* ── Body ─────────────────────────────────────────────────────────────── */
.emp-body {
    padding: 18px 20px 20px !important;
    background: var(--bg-surface, #fff);
}

/* The three groups are the Register Employee page's own section cards — same
   icon tile, same title, same sub-line — so a modal and a page read as one
   document. Two things change for the modal: the card sits on the modal's own
   surface, so it takes the subtle fill rather than surface on surface, and the
   padding is tighter than a full-width page can afford. Nothing about the
   fields inside is asserted here; .form-control keeps what design-tokens.css
   gives it. */
.emp-section {
    background: var(--bg-subtle, #f7f8fa);
    padding: 15px 16px;
    margin-bottom: 0;
}
.emp-section + .emp-section { margin-top: 14px; }
.emp-section .ep-section-head  { margin-bottom: 14px; padding-bottom: 10px; }
.emp-section .ep-section-icon  { width: 30px; height: 30px; font-size: 13px; }
.emp-section .ep-section-title { font-size: .92rem; }

.emp-field { display: flex; flex-direction: column; min-width: 0; }
.emp-field + .emp-field { margin-top: 14px; }

.emp-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px 14px; }
.emp-grid .emp-field + .emp-field { margin-top: 0; }
/* The name is three boxes, like Register Employee. At 620px the dialog has
   room for them side by side; below that they stack rather than shrink to
   something nobody can read a surname in. */
.emp-grid-3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
@media (max-width: 560px) { .emp-grid, .emp-grid-3 { grid-template-columns: 1fr; }
                            .emp-grid .emp-field + .emp-field { margin-top: 14px; } }

/* ── Controls ─────────────────────────────────────────────────────────── */
/* One height, one radius, one focus ring, matching the Register Employee page.
   design-tokens.css paints .form-control / .form-select with !important, so the
   sizing here is deliberately the only thing this file asserts about them. */
.emp-modal .form-control,
.emp-modal .form-select {
    height: 42px;
    padding: 0 13px;
    font-size: 14px;
}
.emp-modal .form-select { padding-right: 34px; cursor: pointer; }
.emp-modal .form-control:hover:not(:focus):not(:disabled),
.emp-modal .form-select:hover:not(:focus) { border-color: var(--border-md, #d2dae6) !important; }
.emp-modal .form-control:disabled,
.emp-modal .form-control[readonly] {
    background: var(--bg-subtle, #f0f3f8) !important;
    color: var(--text-secondary, #5b6a80) !important;
    cursor: not-allowed;
}

/* Validation. :user-invalid fires only after the field has been interacted
   with, so a form that has not been touched is never painted red — and the
   server's own validation output still lands on .is-invalid exactly as before.
   (Do not write the Blade directive's name here: this is a CSS comment to a
   human, but Blade parses the whole file and would compile it.) */
.emp-modal .form-control.is-invalid,
.emp-modal .form-select.is-invalid,
.emp-modal .form-control:user-invalid,
.emp-modal .form-select:user-invalid {
    border-color: var(--danger, #d0342c) !important;
}
.emp-modal .form-control.is-invalid:focus,
.emp-modal .form-select.is-invalid:focus,
.emp-modal .form-control:user-invalid:focus,
.emp-modal .form-select:user-invalid:focus {
    box-shadow: 0 0 0 3px var(--danger-soft, #fcecec) !important;
}

.emp-err {
    display: flex; align-items: flex-start; gap: 6px;
    font-size: .74rem; line-height: 1.4;
    color: var(--danger, #d0342c);
    margin: 5px 0 0;
}
.emp-err i { margin-top: 2px; flex: none; font-size: .7rem; }

.emp-alert {
    display: flex; gap: 10px; align-items: flex-start;
    padding: 11px 13px; margin-bottom: 16px;
    font-size: 13px; line-height: 1.45;
    color: var(--danger, #d0342c);
    background: var(--danger-soft, #fcecec);
    border: 1px solid var(--danger, #d0342c);
    border-radius: var(--radius-md, 10px);
}
.emp-alert i { margin-top: 3px; flex: none; }
.emp-alert strong { font-weight: 700; }
.emp-alert ul { margin: 4px 0 0; padding-left: 17px; }

/* ── Rate · a value the form works out for you ────────────────────────── */
.emp-rate {
    height: 42px; display: flex; align-items: center; gap: 5px;
    padding: 0 13px;
    background: var(--bg-subtle, #f0f3f8);
    border: 1px solid var(--border, #e4e9f0);
    border-radius: var(--radius-sm, 8px);
}
.emp-rate-cur { font-size: 13px; font-weight: 600; color: var(--text-muted, #8a96a8); }
.emp-rate-val {
    font-size: 15px; font-weight: 700; color: var(--brand, #1769e0);
    font-variant-numeric: tabular-nums; outline: none;
}
.emp-rate-lock { margin-left: auto; font-size: 10px; color: var(--text-muted, #8a96a8); }

/* ── Fingerprint · say what the number is ─────────────────────────────── */
.emp-fp { position: relative; }
.emp-fp-icon {
    position: absolute; left: 12px; top: 50%; transform: translateY(-50%);
    color: var(--text-muted, #8a96a8); font-size: 14px; pointer-events: none;
    transition: color .15s ease;
}
.emp-fp-input { padding-left: 36px !important; }
.emp-fp:focus-within .emp-fp-icon { color: var(--brand, #1769e0); }

/* ── Photo ────────────────────────────────────────────────────────────── */
.emp-photo {
    display: flex; align-items: center; gap: 14px;
    padding: 12px;
    border: 1px dashed var(--border-md, #d2dae6);
    border-radius: var(--radius-md, 10px);
    background: var(--bg-subtle, #f0f3f8);
}
.emp-photo-box {
    flex: none; width: 68px; height: 68px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    overflow: hidden;
    background: var(--bg-surface, #fff);
    border: 2px solid var(--border, #e4e9f0);
    color: var(--text-muted, #8a96a8); font-size: 24px;
}
.emp-photo-box img { width: 100%; height: 100%; object-fit: cover; display: block; }
.emp-photo-side { min-width: 0; display: flex; flex-direction: column; gap: 6px; }
.emp-photo-actions { display: flex; gap: 7px; flex-wrap: wrap; }

/* ── Buttons ──────────────────────────────────────────────────────────── */
.emp-btn-ghost {
    height: 34px; padding: 0 12px;
    font-size: 12.5px; font-weight: 600;
    color: var(--text-secondary, #5b6a80);
    background: var(--bg-surface, #fff);
    border: 1px solid var(--border, #e4e9f0);
    border-radius: var(--radius-sm, 8px);
    cursor: pointer;
    display: inline-flex; align-items: center; gap: 6px;
    transition: background .15s ease, color .15s ease, border-color .15s ease;
}
.emp-btn-ghost:hover {
    background: var(--brand-subtle, #eaf1fd);
    color: var(--brand, #1769e0);
    border-color: var(--brand, #1769e0);
}
.emp-btn-ghost-danger:hover {
    background: var(--danger-soft, #fcecec);
    color: var(--danger, #d0342c);
    border-color: var(--danger, #d0342c);
}
.emp-btn-ghost:focus-visible { outline: 2px solid var(--brand, #1769e0); outline-offset: 2px; }

.emp-foot {
    display: flex; align-items: center; gap: 10px;
    padding: 14px 20px;
    background: var(--bg-surface, #fff);
    border-top: 1px solid var(--border, #e4e9f0);
}
.emp-foot-note {
    margin: 0 auto 0 0;
    font-size: .74rem;
    color: var(--text-muted, #8a96a8);
}

.emp-btn-cancel, .emp-btn-save {
    height: 40px; padding: 0 18px;
    font-size: 13.5px; font-weight: 600;
    border-radius: var(--radius-sm, 8px);
    cursor: pointer;
    display: inline-flex; align-items: center; justify-content: center; gap: 7px;
    white-space: nowrap;
    transition: background .15s ease, border-color .15s ease, color .15s ease;
}
.emp-btn-cancel {
    color: var(--text-primary, #0f1e33);
    background: var(--bg-surface, #fff);
    border: 1px solid var(--border, #e4e9f0);
}
.emp-btn-cancel:hover { background: var(--bg-subtle, #f0f3f8); border-color: var(--border-md, #d2dae6); }

.emp-btn-save {
    color: #fff; font-weight: 700;
    background: var(--brand, #1769e0);
    border: 1px solid var(--brand, #1769e0);
    box-shadow: 0 1px 2px rgba(23,105,224,.24);
}
.emp-btn-save:hover {
    background: var(--brand-strong, #1257bc);
    border-color: var(--brand-strong, #1257bc);
    color: #fff;
}
.emp-btn-save:disabled {
    opacity: .65; cursor: progress;
    background: var(--brand, #1769e0); border-color: var(--brand, #1769e0);
}
.emp-btn-cancel:focus-visible, .emp-btn-save:focus-visible {
    outline: 2px solid var(--brand, #1769e0); outline-offset: 2px;
}

/* ── Responsive ───────────────────────────────────────────────────────── */
@media (max-width: 575px) {
    .emp-grid { grid-template-columns: 1fr; }
    .emp-head { padding: 14px 15px; }
    .emp-body { padding: 15px !important; }
    .emp-foot { padding: 12px 15px; flex-wrap: wrap; }
    .emp-foot-note { width: 100%; margin: 0 0 4px; }
    .emp-btn-cancel, .emp-btn-save { flex: 1 1 0; }
    .emp-photo { flex-direction: column; align-items: flex-start; }
}
</style>
