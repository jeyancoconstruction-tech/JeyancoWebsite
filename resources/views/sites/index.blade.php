@extends('layouts')
@section('page_title', 'Sites')

@section('content')
<div class="site-page">

    {{-- ── Page header ─────────────────────────────────────────────────────── --}}
    <div class="site-header">
        <div class="site-header-left">
            <h1 class="site-title">Site Management</h1>
            <span class="site-count-chip" id="siteCountChip">—</span>
        </div>
        <p class="site-header-sub">Create, rename, or remove sites. Employees are reassigned automatically when a site is removed.</p>
    </div>

    <div class="site-layout">

        {{-- ─── LEFT: Add new site ──────────────────────────────────────────── --}}
        <div class="site-add-col">
            <div class="site-card">
                <div class="site-card-label">
                    <i class="fas fa-plus-circle"></i> Add New Site
                </div>

                <label class="site-field-label">Project Name</label>
                <input type="text" id="newSiteInput" class="site-input"
                       placeholder="e.g., Tower 2 — Riverside" maxlength="100">

                <label class="site-field-label mt-3">
                    <i class="fas fa-map-marker-alt me-1" style="color:#16a34a;"></i>Location
                </label>
                <input type="text" id="newSiteLocationSearch" class="site-input"
                       placeholder="Search location, or drop a pin on the map" autocomplete="off">
                <div id="newSiteMap" class="site-map"></div>
                <input type="hidden" id="newSiteLocation">
                <input type="hidden" id="newSiteLat">
                <input type="hidden" id="newSiteLng">

                <button type="button" id="addSiteBtn" class="site-add-btn">
                    <i class="fas fa-plus me-1"></i> Add Site
                </button>
                <div id="addSiteError" class="site-err" style="display:none;"></div>
            </div>
        </div>

        {{-- ─── RIGHT: Sites list ───────────────────────────────────────────── --}}
        <div class="site-list-col">
            <div class="site-card">
                <div class="site-card-label">
                    <i class="fas fa-layer-group"></i> All Sites
                </div>
                <div id="sitesList">
                    <div class="site-loading" id="sitesLoading">
                        <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                        Loading sites…
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ── Styles ──────────────────────────────────────────────────────────────── --}}
<style>
.site-page { max-width: none; width: 100%; margin: 0; }

.site-header { margin-bottom: 22px; }
.site-header-left { display: flex; align-items: center; gap: 12px; }
.site-title { font-size: 1.45rem; font-weight: 700; color: #0f172a; margin: 0; }
.site-count-chip {
    font-size: 12px; font-weight: 600; color: #1e40af;
    background: #eff6ff; border: 1px solid #bfdbfe;
    padding: 3px 10px; border-radius: 20px;
}
.site-header-sub { font-size: 13.5px; color: #64748b; margin: 6px 0 0; }

/* Two-column layout */
.site-layout { display: grid; grid-template-columns: 380px 1fr; gap: 20px; align-items: start; }
@media (max-width: 820px) { .site-layout { grid-template-columns: 1fr; } }

/* Cards */
.site-card {
    background: #fff; border: 1px solid #e2e8f0;
    border-radius: 14px; padding: 20px 22px;
}
.site-card-label {
    font-size: 12px; font-weight: 700; letter-spacing: .4px;
    color: #374151; margin-bottom: 16px;
    display: flex; align-items: center; gap: 7px;
}
.site-card-label i { color: #1e3a8a; }

/* Fields */
.site-field-label { display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 6px; }
.site-field-label.mt-3 { margin-top: 16px; }
.site-input {
    width: 100%; height: 42px; padding: 0 13px; font-size: 14px;
    border: 1.5px solid #e2e8f0; border-radius: 8px;
    background: #fff; color: #0f172a; outline: none;
    transition: border-color .15s, box-shadow .15s;
}
.site-input:focus { border-color: #1e3a8a; box-shadow: 0 0 0 3px rgba(30,58,138,.08); }
.site-map { height: 220px; width: 100%; border-radius: 10px; background: #e5e7eb; margin: 8px 0 14px; }

.site-add-btn {
    width: 100%; height: 42px; font-size: 14px; font-weight: 700;
    background: #1e3a8a; color: #fff; border: none; border-radius: 8px;
    cursor: pointer; transition: background .15s;
}
.site-add-btn:hover { background: #1e40af; }
.site-add-btn:disabled { opacity: .6; cursor: not-allowed; }
.site-err { font-size: 12px; color: #dc2626; margin-top: 8px; }
.site-loading { text-align: center; padding: 24px 0; color: #64748b; font-size: 13px; }

/* Site list rows */
.site-row {
    display: flex; align-items: center; gap: 10px;
    padding: 12px 14px; border-radius: 10px;
    border: 1px solid #e2e8f0; margin-bottom: 8px;
    background: #fff; transition: border-color .15s;
}
.site-row:last-child { margin-bottom: 0; }
.site-row:hover { border-color: #c7d2fe; }
.site-row .site-name  { flex: 1; font-weight: 600; color: #1e293b; font-size: 14px; min-width: 0; }
.site-row .site-count {
    font-size: 12px; font-weight: 600; color: #166534;
    background: #f0fdf4; border: 1px solid #bbf7d0;
    padding: 3px 9px; border-radius: 20px; white-space: nowrap;
}
.site-row input.site-edit-input {
    flex: 1; font-size: 14px; font-weight: 600;
    border: 1.5px solid #6366f1; border-radius: 6px; padding: 5px 9px;
    outline: none;
}
.site-action-btn {
    border: none; background: transparent;
    padding: 6px 8px; border-radius: 6px;
    cursor: pointer; font-size: 13px; line-height: 1;
    transition: background .12s;
}
.site-action-btn:hover  { background: #f1f5f9; }
.site-action-btn.edit   { color: #d97706; }
.site-action-btn.save   { color: #16a34a; }
.site-action-btn.cancel { color: #64748b; }
.site-action-btn.del    { color: #dc2626; }

.site-empty { text-align: center; padding: 32px 0; color: #94a3b8; font-size: 13px; }

/* Dark mode */
[data-bs-theme="dark"] .site-title       { color: #e8edf5; }
[data-bs-theme="dark"] .site-header-sub  { color: #6b7d96; }
[data-bs-theme="dark"] .site-count-chip  { background: #172554; border-color: #1e3a8a; color: #93c5fd; }
[data-bs-theme="dark"] .site-card        { background: #151d2e; border-color: #283449; }
[data-bs-theme="dark"] .site-card-label  { color: #9fb0c7; }
[data-bs-theme="dark"] .site-field-label { color: #cbd5e1; }
[data-bs-theme="dark"] .site-input       { background: #0f1a2e; border-color: #283449; color: #e8edf5; }
[data-bs-theme="dark"] .site-input:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,.12); }
[data-bs-theme="dark"] .site-row         { background: #1c2740; border-color: #283449; }
[data-bs-theme="dark"] .site-row:hover   { border-color: #4f46e5; }
[data-bs-theme="dark"] .site-row .site-name  { color: #e2e8f0; }
[data-bs-theme="dark"] .site-row .site-count { background: #052e16; border-color: #166534; color: #86efac; }
[data-bs-theme="dark"] .site-action-btn:hover { background: #283449; }
[data-bs-theme="dark"] .site-loading     { color: #6b7d96; }
[data-bs-theme="dark"] .site-empty       { color: #475569; }

@keyframes siteToastIn {
    from { opacity: 0; transform: translateX(14px); }
    to   { opacity: 1; transform: none; }
}
</style>

{{-- ── Script ───────────────────────────────────────────────────────────────── --}}
<script src="{{ asset('js/site-location-picker.js') }}"></script>
<script>
(function () {
    const csrf       = '{{ csrf_token() }}';
    const listUrl    = '{{ route("sites.list") }}';
    const storeUrl   = '{{ route("sites.store") }}';
    const updateBase = '/sites/';
    const deleteBase = '/sites/';

    // ── Google Maps location picker ──────────────────────────────────────────
    const newSiteLoc = document.getElementById('newSiteLocation');
    const newSiteLat = document.getElementById('newSiteLat');
    const newSiteLng = document.getElementById('newSiteLng');
    const sitePicker = JeyancoSiteMap.init({
        apiKey:       '{{ config('services.google_maps.key') }}',
        searchInput:  document.getElementById('newSiteLocationSearch'),
        mapEl:        document.getElementById('newSiteMap'),
        addressField: newSiteLoc,
        latField:     newSiteLat,
        lngField:     newSiteLng,
    });

    // ── HTTP helper ──────────────────────────────────────────────────────────
    async function req(url, method, body) {
        const r = await fetch(url, {
            method,
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            body: body ? JSON.stringify(body) : undefined,
        });
        return r.json();
    }

    // ── Load & render site list ──────────────────────────────────────────────
    async function loadSites() {
        const list = document.getElementById('sitesList');
        list.innerHTML = '<div class="site-loading"><div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>Loading…</div>';
        try {
            const data = await req(listUrl, 'GET');
            renderSites(data.sites || []);
        } catch {
            list.innerHTML = '<p class="text-danger text-center py-3">Failed to load sites.</p>';
        }
    }

    function renderSites(sites) {
        const list = document.getElementById('sitesList');
        document.getElementById('siteCountChip').textContent =
            `${sites.length} site${sites.length !== 1 ? 's' : ''}`;

        if (sites.length === 0) {
            list.innerHTML = '<div class="site-empty">No sites yet. Add one on the left.</div>';
            return;
        }
        list.innerHTML = sites.map(s => `
            <div class="site-row" id="site-row-${s.id}" data-id="${s.id}" data-name="${escHtml(s.name)}">
                <span class="site-name" id="site-name-${s.id}" title="${escHtml(s.location || '')}">${escHtml(s.name)}${s.location ? ` <i class="fas fa-map-marker-alt" style="color:#16a34a;font-size:10px;"></i>` : ''}</span>
                <span class="site-count">${s.employees_count} emp${s.employees_count !== 1 ? 's' : ''}</span>
                <button class="site-action-btn edit" title="Rename" onclick="startEdit(${s.id})">
                    <i class="fas fa-pen"></i>
                </button>
                <button class="site-action-btn del" title="Delete" onclick="deleteSite(${s.id}, '${escHtml(s.name)}', ${s.employees_count})">
                    <i class="fas fa-trash"></i>
                </button>
            </div>`).join('');
    }

    // ── Inline edit ──────────────────────────────────────────────────────────
    window.startEdit = function (id) {
        const row      = document.getElementById(`site-row-${id}`);
        const nameSpan = document.getElementById(`site-name-${id}`);
        const current  = row.dataset.name;
        nameSpan.outerHTML = `<input class="site-edit-input" id="site-input-${id}" value="${escHtml(current)}" maxlength="100">`;
        row.querySelector('.site-action-btn.edit').outerHTML =
            `<button class="site-action-btn save" title="Save" onclick="saveEdit(${id})"><i class="fas fa-check"></i></button>
             <button class="site-action-btn cancel" title="Cancel" onclick="cancelEdit(${id})"><i class="fas fa-times"></i></button>`;
        const inp = document.getElementById(`site-input-${id}`);
        inp.focus();
        inp.addEventListener('keydown', e => {
            if (e.key === 'Enter')  { e.preventDefault(); saveEdit(id); }
            if (e.key === 'Escape') { e.preventDefault(); cancelEdit(id); }
        });
    };

    window.cancelEdit = function () { loadSites(); };

    window.saveEdit = async function (id) {
        const input = document.getElementById(`site-input-${id}`);
        const name  = input ? input.value.trim() : '';
        if (!name) { if (input) input.style.borderColor = '#dc2626'; return; }
        const saveBtn = document.querySelector(`#site-row-${id} .save`);
        if (saveBtn) { saveBtn.disabled = true; saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>'; }
        try {
            const data = await req(updateBase + id, 'PUT', { name });
            if (data.success) {
                flashToast(`Renamed to "${data.site.name}".`, 'success');
            } else {
                flashToast(data.errors?.name?.[0] || data.message || 'Rename failed.', 'error');
            }
        } catch { flashToast('Network error.', 'error'); }
        finally { loadSites(); }
    };

    // ── Delete ───────────────────────────────────────────────────────────────
    window.deleteSite = async function (id, name, count) {
        let msg = `Delete "${name}"?`;
        if (count > 0) msg += `\n\n${count} employee${count !== 1 ? 's' : ''} will become unassigned.`;
        if (!confirm(msg)) return;
        const row = document.getElementById(`site-row-${id}`);
        if (row) row.style.opacity = '0.4';
        try {
            const data = await req(deleteBase + id, 'DELETE');
            if (data.success) {
                flashToast(`"${name}" deleted.${data.freed_employees > 0 ? ` ${data.freed_employees} employee(s) unassigned.` : ''}`, 'success');
            } else {
                flashToast(data.message || 'Delete failed.', 'error');
            }
        } catch { flashToast('Network error.', 'error'); }
        finally { loadSites(); }
    };

    // ── Add new site ─────────────────────────────────────────────────────────
    document.getElementById('addSiteBtn').addEventListener('click', addSite);
    document.getElementById('newSiteInput').addEventListener('keydown', e => {
        if (e.key === 'Enter') { e.preventDefault(); addSite(); }
    });

    async function addSite() {
        const input = document.getElementById('newSiteInput');
        const errEl = document.getElementById('addSiteError');
        const name  = input.value.trim();
        errEl.style.display = 'none';
        if (!name) { showAddErr('Please enter a site name.'); return; }
        const btn = document.getElementById('addSiteBtn');
        btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        try {
            const data = await req(storeUrl, 'POST', {
                name,
                location:  newSiteLoc.value || null,
                latitude:  newSiteLat.value || null,
                longitude: newSiteLng.value || null,
            });
            if (data.success) {
                input.value = '';
                JeyancoSiteMap.reset(sitePicker);
                flashToast(`"${data.site.name}" added.`, 'success');
                loadSites();
            } else {
                showAddErr(data.errors?.name?.[0] || data.message || 'Could not add site.');
            }
        } catch { showAddErr('Network error — please try again.'); }
        finally  { btn.disabled = false; btn.innerHTML = '<i class="fas fa-plus me-1"></i> Add Site'; }
    }

    function showAddErr(msg) {
        const el = document.getElementById('addSiteError');
        el.textContent = msg; el.style.display = 'block';
    }

    // ── Toast ────────────────────────────────────────────────────────────────
    function flashToast(msg, type) {
        let wrap = document.getElementById('site-toast-wrap');
        if (!wrap) {
            wrap = document.createElement('div');
            wrap.id = 'site-toast-wrap';
            wrap.style.cssText = 'position:fixed;top:76px;right:20px;z-index:9999;display:flex;flex-direction:column;gap:6px;min-width:240px;max-width:340px;';
            document.body.appendChild(wrap);
        }
        const pal = type === 'error'
            ? { bg:'#fee2e2', bd:'#fecaca', tx:'#991b1b', ic:'times-circle' }
            : { bg:'#dcfce7', bd:'#bbf7d0', tx:'#166534', ic:'check-circle' };
        const el = document.createElement('div');
        el.style.cssText = `background:${pal.bg};border:1px solid ${pal.bd};color:${pal.tx};padding:10px 14px;border-radius:9px;font-size:13px;font-weight:500;display:flex;align-items:center;gap:8px;box-shadow:0 4px 16px rgba(0,0,0,.1);animation:siteToastIn .2s ease;`;
        el.innerHTML = `<i class="fas fa-${pal.ic}"></i> ${msg}`;
        wrap.appendChild(el);
        setTimeout(() => { el.style.transition = 'opacity .3s'; el.style.opacity = '0'; setTimeout(() => el.remove(), 320); }, 3000);
    }

    function escHtml(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
    }

    // ── Init ─────────────────────────────────────────────────────────────────
    loadSites();
    JeyancoSiteMap.refresh(sitePicker);
})();
</script>
@endsection
