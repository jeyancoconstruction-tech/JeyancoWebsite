{{-- The cards and fields the Company, Security and Appearance tabs share. The
     page shell around them lives in _hub, which every tab includes, so all four
     line up. Self-contained: the payroll page's .pr-* chrome is inside its own
     template and would render unstyled here. --}}
<style>
    .sy-card {
        background: var(--surface); border: 1px solid var(--border);
        border-radius: 6px; margin-bottom: 18px; overflow: hidden;
    }
    .sy-card-head {
        display: flex; align-items: flex-start; gap: 12px;
        padding: 16px 20px; border-bottom: 1px solid var(--border);
    }
    .sy-card-head > i { font-size: 1.05rem; color: var(--brand); margin-top: 2px; }
    .sy-card-head h6 { margin: 0; font-size: 1rem; font-weight: 700; color: var(--text-primary); }
    .sy-card-head p  { margin: 2px 0 0; font-size: .8rem; color: var(--text-secondary); }
    .sy-card-body { padding: 18px 20px; }

    .sy-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
    @media (max-width: 700px) { .sy-grid { grid-template-columns: 1fr; } }

    .sy-field label {
        display: block; margin-bottom: 4px;
        font-size: .78rem; font-weight: 600; color: var(--text-secondary);
    }
    .sy-input {
        width: 100%; padding: 9px 12px; border-radius: 6px;
        border: 1px solid var(--border); background: var(--bg-subtle);
        color: var(--text-primary); font-size: .9rem;
    }
    .sy-input:focus { outline: none; border-color: var(--brand); }
    .sy-hint { margin: 10px 0 0; font-size: .75rem; line-height: 1.6; color: var(--text-muted); }

    /* The logo is the one setting you can only check by looking at it. */
    .sy-logo-row { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }
    .sy-logo-preview {
        width: 56px; height: 56px; border-radius: 50%; object-fit: cover;
        border: 1px solid var(--border); background: var(--bg-subtle); flex: none;
    }

    .sy-foot {
        display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
        padding-top: 16px; border-top: 1px solid var(--border);
    }
    .sy-updated {
        margin-right: auto; display: inline-flex; align-items: center; gap: 7px;
        font-size: .78rem; color: var(--text-muted);
    }
    .sy-save {
        padding: 9px 22px; border: none; border-radius: 6px;
        background: var(--brand); color: #fff; font-weight: 600; font-size: .85rem; cursor: pointer;
    }
    .sy-save:hover { background: var(--brand-strong); }

    /* The toggle. The payroll settings page has one styled inside its own
       template, which does not reach here — so it is written again, in tokens
       rather than the hardcoded greys that one uses. */
    .ps-toggle-row {
        display: flex; align-items: center; gap: 12px; padding: 10px 14px;
        background: var(--bg-subtle); border: 1px solid var(--border); border-radius: 6px;
    }
    .ps-toggle-switch { position: relative; display: inline-block; width: 44px; height: 24px; flex-shrink: 0; margin: 0; }
    .ps-toggle-switch input { opacity: 0; width: 0; height: 0; }
    .ps-toggle-slider {
        position: absolute; inset: 0; border-radius: 99px; cursor: pointer;
        background: var(--border-md); transition: background .2s;
    }
    .ps-toggle-slider::before {
        content: ''; position: absolute; width: 18px; height: 18px; left: 3px; top: 3px;
        background: #fff; border-radius: 50%; transition: transform .2s; box-shadow: 0 1px 4px rgba(0,0,0,.2);
    }
    .ps-toggle-switch input:checked + .ps-toggle-slider { background: var(--success); }
    .ps-toggle-switch input:checked + .ps-toggle-slider::before { transform: translateX(20px); }
    .ps-toggle-label { font-size: .85rem; color: var(--text-secondary); line-height: 1.4; }
</style>
