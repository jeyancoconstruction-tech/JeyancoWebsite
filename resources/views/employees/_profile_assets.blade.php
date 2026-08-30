{{-- Styles and repeat-row behaviour for _profile_fields. Kept beside it so a
     page only has to include the two partials to get a working form. --}}
<style>
.ep-section { background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:22px 24px; margin-bottom:18px; }
.ep-section-head { display:flex; gap:14px; align-items:flex-start; margin-bottom:18px;
    padding-bottom:14px; border-bottom:1px solid #f1f5f9; }
.ep-section-icon { flex:none; width:38px; height:38px; border-radius:10px; background:#eff6ff; color:#2563eb;
    display:inline-flex; align-items:center; justify-content:center; font-size:15px; }
.ep-section-title { font-size:1.02rem; font-weight:700; color:#0f172a; margin:0 0 2px; }
.ep-section-sub { font-size:.8rem; color:#64748b; margin:0; }

.ep-label { display:block; font-size:.78rem; font-weight:700; color:#475569;
    text-transform:uppercase; letter-spacing:.02em; margin-bottom:5px; }
.ep-hint { display:block; font-size:.75rem; color:#94a3b8; margin-top:4px; }
.ep-divider { margin:6px 0 2px; border-color:#e2e8f0; }
.ep-subhead { font-size:.82rem; font-weight:700; color:#334155; margin:6px 0 0; }

/* Repeatable education / work-experience rows */
.ep-repeat-row { position:relative; background:#f8fafc; border:1px solid #e2e8f0; border-radius:11px;
    padding:16px 44px 16px 16px; margin-bottom:12px; }
.ep-row-x { position:absolute; top:10px; right:10px; width:28px; height:28px; border-radius:8px;
    border:1px solid #e2e8f0; background:#fff; color:#94a3b8; cursor:pointer; font-size:12px;
    display:inline-flex; align-items:center; justify-content:center; transition:color .15s, border-color .15s; }
.ep-row-x:hover { color:#dc2626; border-color:#fecaca; background:#fef2f2; }
/* A lone row has nothing to fall back to, so hide its remove button. */
.ep-repeat-row:only-child .ep-row-x { display:none; }

.ep-add-btn { border:1px dashed #cbd5e1; background:#fff; color:#2563eb; font-weight:600; font-size:.85rem;
    border-radius:10px; padding:9px 16px; cursor:pointer; transition:background .15s, border-color .15s; }
.ep-add-btn:hover { background:#eff6ff; border-color:#93c5fd; }

@media (max-width: 576px) {
    .ep-section { padding:18px 16px; }
    .ep-repeat-row { padding:14px 40px 14px 14px; }
}
</style>

<script>
(function () {
    // Each repeat group clones its first row, blanks the inputs and renumbers
    // the field names. Indices only ever go up, so removing a middle row can
    // leave gaps — harmless, since the controller re-indexes on save.
    document.querySelectorAll('.js-row-add').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const group = document.querySelector('[data-repeat="' + btn.dataset.target + '"]');
            if (!group) return;

            const rows  = group.querySelectorAll('.ep-repeat-row');
            const clone = rows[rows.length - 1].cloneNode(true);
            const next  = rows.length;

            clone.querySelectorAll('[name]').forEach(function (field) {
                field.name = field.name.replace(/\[\d+\]/, '[' + next + ']');
                if (field.tagName === 'SELECT') field.selectedIndex = 0;
                else field.value = '';
            });

            group.appendChild(clone);
            const first = clone.querySelector('input, select, textarea');
            if (first) first.focus();
        });
    });

    // Delegated so rows added after load are removable too.
    document.addEventListener('click', function (e) {
        const x = e.target.closest('.js-row-remove');
        if (!x) return;

        const row   = x.closest('.ep-repeat-row');
        const group = row.parentElement;

        // Never remove the last row — clear it instead, so the section keeps
        // an empty row to type into.
        if (group.querySelectorAll('.ep-repeat-row').length === 1) {
            row.querySelectorAll('[name]').forEach(function (field) {
                if (field.tagName === 'SELECT') field.selectedIndex = 0;
                else field.value = '';
            });
            return;
        }
        row.remove();
    });
})();
</script>
