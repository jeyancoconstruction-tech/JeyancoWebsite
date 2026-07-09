@extends('layouts')
@section('page_title', 'Employees')

@section('content')
<div class="emp-page">

    {{-- ── Flash ──────────────────────────────────────────────────────────── --}}
    @if(session('success'))
    <div class="emp-flash">
        <i class="fas fa-check-circle"></i>
        {{ session('success') }}
    </div>
    @endif

    {{-- ── Page header ─────────────────────────────────────────────────────── --}}
    <div class="emp-header">
        <div class="emp-header-left">
            <h1 class="emp-title">Employee Directory</h1>
            <span class="emp-count-chip">{{ $employees->count() }} employees</span>
        </div>
        <div class="emp-header-right">
            {{-- Search --}}
            <div class="emp-search-wrap">
                <i class="fas fa-search emp-search-icon"></i>
                <input type="text" id="empSearch" class="emp-search" placeholder="Search by name…">
            </div>

            {{-- Site filter --}}
            <div class="emp-select-wrap">
                <select id="siteFilter" class="emp-select">
                    <option value="">All Sites</option>
                    @foreach($sites as $site)
                        <option value="{{ $site->id }}">{{ $site->name }}</option>
                    @endforeach
                </select>
                <i class="fas fa-chevron-down emp-select-icon"></i>
            </div>

            {{-- Selection mode toggle --}}
            <button type="button" id="selectionModeBtn" class="emp-btn-secondary">
                <i class="fas fa-check-square"></i>
                <span>Select</span>
            </button>

            {{-- Delete All --}}
            <button type="button" id="empDeleteAllBtn" class="emp-del-all-btn">
                <i class="fas fa-trash-alt"></i>
                <span>Delete All</span>
            </button>
        </div>
    </div>

    {{-- ── Table card ──────────────────────────────────────────────────────── --}}
    <div class="emp-card">

        {{-- Bulk action bar (visible only when rows are checked) --}}
        <div id="bulkActionBar" class="emp-bulk-bar" style="display:none;">
            <div class="emp-bulk-info">
                <i class="fas fa-check-square"></i>
                <span id="bulkCount">0</span> employee(s) selected
            </div>
            <div class="emp-bulk-actions">
                <button type="button" id="bulkDeselectAll" class="emp-bulk-cancel">
                    <i class="fas fa-times"></i> Deselect All
                </button>
                <button type="button" id="bulkDeleteBtn" class="emp-bulk-delete">
                    <i class="fas fa-trash"></i> Delete Selected
                </button>
            </div>
        </div>

        <div class="table-responsive">
            <table class="emp-table" id="empTable">
                <thead>
                    <tr>
                        <th class="emp-col-check">
                            <input type="checkbox" id="selectAll" class="emp-checkbox" title="Select all visible">
                        </th>
                        <th>Employee</th>
                        <th>Site</th>
                        <th>Labor Type</th>
                        <th class="text-center">Rate / hr</th>
                        <th class="text-center">Vale Balance</th>
                        <th>Fingerprint</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($employees as $emp)
                    <tr data-site="{{ $emp->site_id ?? '' }}"
                        data-name="{{ strtolower($emp->name) }}">

                        <td class="emp-col-check">
                            <input type="checkbox" class="emp-row-check" value="{{ $emp->id }}">
                        </td>

                        {{-- Employee (avatar + name + ID) --}}
                        <td>
                            <div class="emp-cell">
                                @if($emp->photo)
                                    <img src="{{ url('storage/' . $emp->photo) }}"
                                         alt="{{ $emp->name }}"
                                         class="emp-avatar-img">
                                @else
                                    <div class="emp-avatar-initials emp-av-{{ substr(strtolower($emp->name), 0, 1) }}">
                                        {{ strtoupper(substr($emp->name, 0, 1)) }}
                                    </div>
                                @endif
                                <div class="emp-info">
                                    <span class="emp-name">{{ $emp->name }}</span>
                                    <span class="emp-id-badge">#{{ str_pad($emp->id, 4, '0', STR_PAD_LEFT) }}</span>
                                </div>
                            </div>
                        </td>

                        {{-- Site --}}
                        <td>
                            @if($emp->site)
                                <span class="emp-badge-site">
                                    <i class="fas fa-map-marker-alt"></i>
                                    {{ $emp->site->name }}
                                </span>
                            @else
                                <span class="emp-dash">—</span>
                            @endif
                        </td>

                        {{-- Labor Type --}}
                        <td>
                            @if($emp->laborType)
                                <span class="emp-badge-labor">
                                    <i class="fas fa-briefcase"></i>
                                    {{ $emp->laborType->name }}
                                </span>
                            @else
                                <span class="emp-dash">—</span>
                            @endif
                        </td>

                        {{-- Rate --}}
                        <td class="emp-rate">
                            ₱{{ number_format($emp->rate_per_hour, 2) }}
                        </td>

                        {{-- Vale balance --}}
                        <td class="emp-vale {{ ($emp->vale ?? 0) > 0 ? 'has-vale' : '' }}" data-vale-cell="{{ $emp->id }}">
                            ₱{{ number_format($emp->vale ?? 0, 2) }}
                        </td>

                        {{-- Fingerprint --}}
                        <td>
                            @if($emp->fingerprint_id)
                                <span class="emp-badge-fp">
                                    <i class="fas fa-fingerprint"></i>
                                    {{ $emp->fingerprint_id }}
                                </span>
                            @else
                                <span class="emp-dash">Not set</span>
                            @endif
                        </td>

                        {{-- Actions --}}
                        <td class="emp-actions-cell">
                            <div class="emp-more-wrap">
                                <button type="button" class="emp-more-btn" aria-label="More options">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                                <div class="emp-more-menu">
                                    <a href="{{ route('employees.edit', $emp->id) }}" class="emp-more-item">
                                        <i class="fas fa-pen"></i> Edit
                                    </a>
                                    <button type="button" class="emp-more-item js-set-vale"
                                            data-id="{{ $emp->id }}"
                                            data-name="{{ $emp->name }}"
                                            data-vale="{{ $emp->vale ?? 0 }}">
                                        <i class="fas fa-coins"></i> Set Vale
                                    </button>
                                    <form action="{{ route('employees.destroy', $emp->id) }}" method="POST">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="emp-more-item emp-more-delete"
                                                onclick="return confirm('Delete {{ addslashes($emp->name) }}? This cannot be undone.')">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr class="emp-empty-row">
                        <td colspan="8">
                            <div class="emp-empty">
                                <div class="emp-empty-icon"><i class="fas fa-users"></i></div>
                                <p class="emp-empty-title">No employees yet</p>
                                <p class="emp-empty-sub">Register employees from the kiosk to get started.</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Filter / search empty state --}}
        <div id="noMatch" class="emp-empty" style="display:none;padding:48px 0;">
            <div class="emp-empty-icon"><i class="fas fa-filter"></i></div>
            <p class="emp-empty-title">No results</p>
            <p class="emp-empty-sub">Try a different name or site filter.</p>
        </div>
    </div>
</div>

{{-- ── Set Vale modal ─────────────────────────────────────────────────────── --}}
<div class="modal fade" id="empValeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content emp-modal-content">
            <div class="emp-modal-header">
                <div>
                    <h3 class="emp-modal-title">Set Vale Balance</h3>
                    <p class="emp-modal-sub" id="valeModalName">—</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3">
                <label class="emp-site-add-label" for="valeInput"><i class="fas fa-coins"></i> Vale amount (₱)</label>
                <input type="number" step="0.01" min="0" id="valeInput" class="emp-modal-input" style="width:100%;" placeholder="0.00">
                <p class="emp-modal-sub mt-2" style="color:var(--text-muted);">Manual running balance per employee. Payroll deductions are still entered per period on the Payroll page.</p>
                <div class="d-flex justify-content-end gap-2 mt-3">
                    <button type="button" class="emp-btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="emp-site-add-btn" id="valeSaveBtn">Save</button>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ── Styles ──────────────────────────────────────────────────────────────── --}}
<style>
/* ── Page shell ──────────────────────────────────────────────────────────── */
.emp-page { max-width: none; width: 100%; margin: 0; }

/* ── Flash ───────────────────────────────────────────────────────────────── */
.emp-flash {
    display: flex; align-items: center; gap: 10px;
    background: var(--surface); border: 1px solid var(--border); border-left: 3px solid var(--success);
    color: var(--text-primary); padding: 11px 16px; border-radius: 6px;
    font-size: 13.5px; font-weight: 500; margin-bottom: 16px;
}
.emp-flash i { color: var(--success); }

/* ── Page header ─────────────────────────────────────────────────────────── */
.emp-header {
    display: flex; align-items: center; justify-content: space-between;
    flex-wrap: wrap; gap: 14px; margin-bottom: 22px;
}
.emp-header-left { display: flex; align-items: baseline; gap: 10px; }
.emp-title {
    font-size: 20px; font-weight: 600; color: var(--text-primary); margin: 0; letter-spacing: -0.01em;
}
.emp-count-chip {
    font-size: 12px; font-weight: 500; color: var(--text-secondary);
    background: transparent; border: none; padding: 0;
}
.emp-header-right {
    display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
}

/* ── Search ──────────────────────────────────────────────────────────────── */
.emp-search-wrap {
    position: relative; display: flex; align-items: center;
}
.emp-search-icon {
    position: absolute; left: 11px; color: var(--text-muted);
    font-size: 12px; pointer-events: none;
}
.emp-search {
    height: 38px; padding: 0 12px 0 32px; font-size: 13px;
    border: 1px solid var(--border); border-radius: 6px;
    background: var(--surface); color: var(--text-primary); width: 210px;
    outline: none; transition: border-color .12s;
}
.emp-search:focus { border-color: var(--brand); }
.emp-search::placeholder { color: var(--text-muted); }

/* ── Select ──────────────────────────────────────────────────────────────── */
.emp-select-wrap { position: relative; }
.emp-select {
    height: 38px; padding: 0 30px 0 11px; font-size: 13px;
    border: 1px solid var(--border); border-radius: 6px;
    background: var(--surface); color: var(--text-primary);
    appearance: none; -webkit-appearance: none;
    cursor: pointer; outline: none;
    transition: border-color .12s;
    min-width: 140px;
}
.emp-select:focus { border-color: var(--brand); }
.emp-select-icon {
    position: absolute; right: 11px; top: 50%;
    transform: translateY(-50%); color: var(--text-muted);
    font-size: 10px; pointer-events: none;
}

/* ── Delete All button — muted danger, secondary weight (not a big red block) ── */
.emp-del-all-btn {
    height: 38px; padding: 0 14px; font-size: 13px; font-weight: 500;
    background: var(--surface); color: var(--danger);
    border: 1px solid var(--border); border-radius: 6px; cursor: pointer;
    display: inline-flex; align-items: center; gap: 7px;
    transition: all .12s; white-space: nowrap;
}
.emp-del-all-btn:hover { background: rgba(179,64,58,0.08); border-color: var(--danger); }

/* ── Secondary button ────────────────────────────────────────────────────── */
.emp-btn-secondary {
    height: 38px; padding: 0 14px; font-size: 13px; font-weight: 500;
    background: var(--surface); color: var(--text-primary);
    border: 1px solid var(--border); border-radius: 6px;
    cursor: pointer; display: inline-flex; align-items: center; gap: 7px;
    transition: all .12s;
    white-space: nowrap;
}
.emp-btn-secondary:hover { background: var(--brand-subtle); border-color: var(--border-md); }

/* ── Table card ──────────────────────────────────────────────────────────── */
.emp-card {
    background: var(--surface); border: 1px solid var(--border);
    border-radius: 6px; overflow: hidden;
}

/* ── Table ───────────────────────────────────────────────────────────────── */
.emp-table { width: 100%; border-collapse: collapse; }
.emp-table thead th {
    background: var(--surface); padding: 10px 16px;
    font-size: 11px; font-weight: 600; letter-spacing: .04em;
    text-transform: uppercase; color: var(--text-secondary);
    border-bottom: 1px solid var(--border);
    white-space: nowrap; position: sticky; top: 0; z-index: 1;
}
.emp-table thead th:last-child { width: 48px; }
.emp-table tbody td {
    padding: 10px 16px; border-bottom: 1px solid var(--border);
    vertical-align: middle; font-size: 13.5px; color: var(--text-primary);
}
.emp-table tbody tr:last-child td { border-bottom: none; }
.emp-table tbody tr { transition: background .1s; }
.emp-table tbody tr:hover td { background: var(--brand-subtle); }

/* ── Employee cell (avatar + info) ───────────────────────────────────────── */
.emp-cell { display: flex; align-items: center; gap: 11px; }
.emp-avatar-img {
    width: 36px; height: 36px; border-radius: 50%;
    object-fit: cover; border: 1px solid var(--border);
    flex-shrink: 0;
}
.emp-avatar-initials {
    width: 36px; height: 36px; border-radius: 50%;
    font-size: 13px; font-weight: 600;
    background: var(--brand-subtle) !important; color: var(--brand);
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0; border: none;
}
/* Uniform avatar — one calm brand tint for everyone (no rainbow initials) */
[class*="emp-av-"] { background: var(--brand-subtle) !important; color: var(--brand) !important; }

.emp-info  { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.emp-name  { font-size: 13.5px; font-weight: 600; color: var(--text-primary); white-space: nowrap; }
.emp-id-badge {
    font-size: 11px; font-weight: 500; font-family: ui-monospace,'SF Mono','Courier New',monospace;
    color: var(--text-secondary); background: transparent; border: none;
    padding: 0; width: fit-content;
}

/* ── Badges — one calm, neutral outline style (no colour fills) ──────────── */
.emp-badge-site, .emp-badge-labor, .emp-badge-fp {
    display: inline-flex; align-items: center; gap: 6px;
    font-size: 12px; font-weight: 500; white-space: nowrap;
    color: var(--text-secondary) !important; background: transparent !important;
    border: 1px solid var(--border) !important;
    padding: 3px 9px; border-radius: 6px;
}
.emp-badge-site i, .emp-badge-labor i, .emp-badge-fp i { font-size: 9px; color: var(--text-muted); }
.emp-badge-fp { font-family: ui-monospace,'SF Mono','Courier New',monospace; }

.emp-dash { color: var(--text-muted); font-size: 13px; }
.emp-rate { text-align: center; font-size: 13.5px; font-weight: 600; color: var(--text-primary); font-variant-numeric: tabular-nums; }
.emp-vale { text-align: center; font-size: 13.5px; font-weight: 600; color: var(--text-muted); font-variant-numeric: tabular-nums; }
.emp-vale.has-vale { color: var(--danger); }
.emp-actions-cell { text-align: right; white-space: nowrap; }

/* ── Empty state ─────────────────────────────────────────────────────────── */
.emp-empty {
    display: flex; flex-direction: column; align-items: center;
    justify-content: center; padding: 48px 0; text-align: center;
}
.emp-empty-icon {
    width: 52px; height: 52px; border-radius: 50%;
    background: var(--bg-subtle); display: flex; align-items: center; justify-content: center;
    font-size: 20px; color: var(--text-muted); margin-bottom: 14px;
}
.emp-empty-title { font-size: 14px; font-weight: 600; color: var(--text-primary); margin: 0 0 5px; }
.emp-empty-sub   { font-size: 13px; color: var(--text-secondary); margin: 0; }
.emp-empty-row td { padding: 0; border-bottom: none; }

/* ── Three-dot menu ──────────────────────────────────────────────────────── */
.emp-more-wrap { position: relative; display: inline-block; }
.emp-more-btn {
    width: 32px; height: 32px; border-radius: 6px;
    border: 1px solid var(--border); background: var(--surface);
    color: var(--text-secondary); cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    transition: all .12s;
    font-size: 13px;
}
.emp-more-btn:hover        { background: var(--brand-subtle); border-color: var(--border-md); color: var(--text-primary); }
.emp-more-btn.active       { background: var(--brand-subtle); border-color: var(--brand); color: var(--brand); }
.emp-more-menu {
    display: none; position: absolute; right: 0; top: calc(100% + 6px);
    background: var(--surface); border: 1px solid var(--border);
    border-radius: 6px; box-shadow: var(--shadow-lg);
    z-index: 100; min-width: 150px; overflow: hidden;
}
.emp-more-menu.open { display: block; }
.emp-more-item {
    display: flex; align-items: center; gap: 9px;
    padding: 9px 14px; font-size: 13px; font-weight: 500;
    color: var(--text-primary); text-decoration: none;
    width: 100%; background: none; border: none; cursor: pointer;
    transition: background .1s;
}
.emp-more-item:hover        { background: var(--brand-subtle); }
.emp-more-item i            { width: 14px; text-align: center; font-size: 12px; color: var(--text-secondary); }
.emp-more-delete            { color: var(--danger); }
.emp-more-delete i          { color: var(--danger); }
.emp-more-delete:hover      { background: rgba(179,64,58,0.08); }

/* ── Modal ───────────────────────────────────────────────────────────────── */
.emp-modal-content {
    border: 1px solid var(--border); border-radius: 6px;
    box-shadow: var(--shadow-xl); overflow: hidden; background: var(--surface);
}
.emp-modal-header {
    display: flex; align-items: flex-start; justify-content: space-between;
    padding: 16px 20px;
    background: var(--surface); color: var(--text-primary); border-bottom: 1px solid var(--border);
}
.emp-modal-title { font-size: 15px; font-weight: 600; margin: 0 0 2px; }
.emp-modal-sub   { font-size: 12px; color: var(--text-secondary); margin: 0; }
.emp-site-add-panel {
    background: var(--bg-subtle); border: 1px solid var(--border);
    border-radius: 6px; padding: 14px 16px; margin-bottom: 16px;
}
.emp-site-add-label {
    font-size: 12px; font-weight: 600; color: var(--text-primary); margin-bottom: 10px;
    display: flex; align-items: center; gap: 6px;
}
.emp-site-add-label i { color: var(--brand); }
.emp-modal-input {
    flex: 1; height: 38px; padding: 0 11px; font-size: 13px;
    border: 1px solid var(--border); border-radius: 6px;
    background: var(--surface); color: var(--text-primary); outline: none;
    transition: border-color .12s;
}
.emp-modal-input:focus { border-color: var(--brand); }
.emp-site-add-btn {
    height: 38px; padding: 0 16px; font-size: 13px; font-weight: 600;
    background: var(--brand); color: #fff; border: none;
    border-radius: 6px; cursor: pointer; white-space: nowrap;
    transition: background .12s;
}
.emp-site-add-btn:hover { background: var(--brand-strong); }
.emp-modal-err { font-size: 12px; color: var(--danger); margin-top: 6px; }
.emp-sites-loading { text-align: center; padding: 20px 0; color: var(--text-secondary); font-size: 13px; }

/* ── Site list rows ──────────────────────────────────────────────────────── */
.site-row {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 14px; border-radius: 6px;
    border: 1px solid var(--border); margin-bottom: 8px;
    background: var(--surface); transition: border-color .12s;
}
.site-row:hover { border-color: var(--border-md); }
.site-row .site-name  { flex: 1; font-weight: 600; color: var(--text-primary); font-size: 13.5px; }
.site-row .site-count { font-size: 12px; color: var(--text-secondary); white-space: nowrap; }
.site-row input.site-edit-input {
    flex: 1; font-size: 13.5px; font-weight: 600;
    border: 1px solid var(--brand); border-radius: 6px; padding: 4px 8px;
    outline: none; background: var(--surface); color: var(--text-primary);
}
.site-action-btn {
    border: none; background: transparent;
    padding: 5px 7px; border-radius: 6px;
    cursor: pointer; font-size: 12px; line-height: 1;
    transition: background .12s; color: var(--text-secondary);
}
.site-action-btn:hover  { background: var(--brand-subtle); }
.site-action-btn.edit   { color: var(--text-secondary); }
.site-action-btn.save   { color: var(--success); }
.site-action-btn.cancel { color: var(--text-secondary); }
.site-action-btn.del    { color: var(--danger); }

/* ── Checkbox column (hidden until selection mode is active) ─────────────── */
.emp-col-check { display: none; width: 44px; text-align: center; padding-left: 8px !important; padding-right: 4px !important; }
.emp-selecting .emp-col-check { display: table-cell; }
.emp-checkbox, .emp-row-check {
    width: 16px; height: 16px; cursor: pointer;
    accent-color: var(--brand); flex-shrink: 0;
}

/* ── Selection mode active button ────────────────────────────────────────── */
.emp-btn-selecting {
    background: var(--brand-subtle) !important; color: var(--brand) !important;
    border-color: var(--brand) !important;
}
.emp-btn-selecting:hover { background: var(--brand-subtle) !important; border-color: var(--brand) !important; }

/* ── Bulk action bar ─────────────────────────────────────────────────────── */
.emp-bulk-bar {
    display: flex; align-items: center; justify-content: space-between;
    padding: 10px 16px; gap: 12px; flex-wrap: wrap;
    background: var(--brand-subtle); border-bottom: 1px solid var(--border);
}
.emp-bulk-info {
    display: flex; align-items: center; gap: 8px;
    font-size: 13px; font-weight: 600; color: var(--brand);
}
.emp-bulk-info i { font-size: 14px; }
.emp-bulk-actions { display: flex; align-items: center; gap: 8px; }
.emp-bulk-cancel {
    height: 34px; padding: 0 12px; font-size: 12px; font-weight: 500;
    background: var(--surface); color: var(--text-secondary);
    border: 1px solid var(--border); border-radius: 6px;
    cursor: pointer; transition: background .12s;
    display: inline-flex; align-items: center; gap: 6px;
}
.emp-bulk-cancel:hover { background: var(--bg-subtle); }
.emp-bulk-delete {
    height: 34px; padding: 0 14px; font-size: 12px; font-weight: 600;
    background: var(--danger); color: #fff;
    border: none; border-radius: 6px; cursor: pointer;
    transition: opacity .12s;
    display: inline-flex; align-items: center; gap: 6px;
}
.emp-bulk-delete:hover    { opacity: .9; }
.emp-bulk-delete:disabled { opacity: .5; cursor: not-allowed; }

/* Dark mode is handled by the theme-aware design tokens used above. */

@keyframes empToastIn {
    from { opacity: 0; transform: translateX(14px); }
    to   { opacity: 1; transform: none; }
}
</style>

{{-- ── Script ───────────────────────────────────────────────────────────────── --}}
<script>
(function () {
    const csrf = '{{ csrf_token() }}';

    // ── Three-dot menus ──────────────────────────────────────────────────────
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.emp-more-btn');
        document.querySelectorAll('.emp-more-menu.open').forEach(m => {
            if (!btn || m !== btn.nextElementSibling) {
                m.classList.remove('open');
                m.previousElementSibling.classList.remove('active');
            }
        });
        if (btn) {
            e.stopPropagation();
            const menu    = btn.nextElementSibling;
            const opening = !menu.classList.contains('open');
            menu.classList.toggle('open', opening);
            btn.classList.toggle('active', opening);
        }
    });

    // ── Unified filter (site + search name) ──────────────────────────────────
    function applyFilter() {
        const siteVal  = document.getElementById('siteFilter').value;
        const query    = document.getElementById('empSearch').value.trim().toLowerCase();
        const rows     = document.querySelectorAll('#empTable tbody tr[data-site]');
        let visible    = 0;

        rows.forEach(r => {
            const siteOk  = !siteVal || r.dataset.site === siteVal;
            const nameOk  = !query   || r.dataset.name.includes(query);
            const show    = siteOk && nameOk;
            r.style.display = show ? '' : 'none';
            if (show) visible++;
        });

        document.getElementById('noMatch').style.display = (rows.length > 0 && visible === 0) ? 'block' : 'none';
        updateBulkBar();
    }

    document.getElementById('siteFilter').addEventListener('change', applyFilter);
    document.getElementById('empSearch').addEventListener('input', applyFilter);

    // ── Selection mode ───────────────────────────────────────────────────────
    const selectionModeBtn = document.getElementById('selectionModeBtn');
    const empPage          = document.querySelector('.emp-page');
    let   selectionMode    = false;

    function enterSelectionMode() {
        selectionMode = true;
        empPage.classList.add('emp-selecting');
        selectionModeBtn.classList.add('emp-btn-selecting');
        selectionModeBtn.querySelector('i').className = 'fas fa-times';
        selectionModeBtn.querySelector('span').textContent = 'Cancel';
    }

    function exitSelectionMode() {
        selectionMode = false;
        empPage.classList.remove('emp-selecting');
        selectionModeBtn.classList.remove('emp-btn-selecting');
        selectionModeBtn.querySelector('i').className = 'fas fa-check-square';
        selectionModeBtn.querySelector('span').textContent = 'Select';
        allRowChecks().forEach(c => { c.checked = false; });
        updateBulkBar();
    }

    selectionModeBtn.addEventListener('click', function () {
        if (selectionMode) exitSelectionMode();
        else enterSelectionMode();
    });

    // ── Bulk delete ──────────────────────────────────────────────────────────
    const bulkUrl         = '{{ route("employees.bulk-delete") }}';
    const selectAllChk    = document.getElementById('selectAll');
    const bulkBar         = document.getElementById('bulkActionBar');
    const bulkCountEl     = document.getElementById('bulkCount');
    const bulkDeselectBtn = document.getElementById('bulkDeselectAll');
    const bulkDeleteBtn   = document.getElementById('bulkDeleteBtn');

    function visibleRowChecks() {
        return Array.from(document.querySelectorAll(
            '#empTable tbody tr[data-site]:not([style*="display: none"]) .emp-row-check'
        ));
    }
    function allRowChecks() {
        return Array.from(document.querySelectorAll('#empTable tbody .emp-row-check'));
    }
    function checkedIds() {
        return allRowChecks().filter(c => c.checked).map(c => c.value);
    }
    function updateBulkBar() {
        const ids     = checkedIds();
        const count   = ids.length;
        bulkCountEl.textContent  = count;
        bulkBar.style.display    = count > 0 ? 'flex' : 'none';

        const visible        = visibleRowChecks();
        const checkedVisible = visible.filter(c => c.checked).length;
        selectAllChk.checked       = visible.length > 0 && checkedVisible === visible.length;
        selectAllChk.indeterminate = checkedVisible > 0 && checkedVisible < visible.length;
    }

    selectAllChk.addEventListener('change', function () {
        visibleRowChecks().forEach(c => { c.checked = this.checked; });
        updateBulkBar();
    });

    document.getElementById('empTable').addEventListener('change', function (e) {
        if (e.target.classList.contains('emp-row-check')) updateBulkBar();
    });

    bulkDeselectBtn.addEventListener('click', function () {
        allRowChecks().forEach(c => { c.checked = false; });
        updateBulkBar();
    });

    bulkDeleteBtn.addEventListener('click', async function () {
        const ids = checkedIds();
        if (!ids.length) return;
        const label = ids.length === 1 ? '1 employee' : `${ids.length} employees`;
        if (!confirm(`Delete ${label}? This cannot be undone.`)) return;

        bulkDeleteBtn.disabled     = true;
        bulkDeleteBtn.innerHTML    = '<i class="fas fa-spinner fa-spin"></i> Deleting…';

        try {
            const r = await fetch(bulkUrl, {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: JSON.stringify({ ids }),
            });
            const data = await r.json();
            if (data.success) {
                ids.forEach(id => {
                    const chk = document.querySelector(`.emp-row-check[value="${id}"]`);
                    if (chk) chk.closest('tr').remove();
                });
                const remaining = document.querySelectorAll('#empTable tbody tr[data-site]').length;
                const chip = document.querySelector('.emp-count-chip');
                if (chip) chip.textContent = `${remaining} employee${remaining !== 1 ? 's' : ''}`;
                applyFilter();
                exitSelectionMode();
                flashToast(`${data.deleted} employee${data.deleted !== 1 ? 's' : ''} deleted.`, 'success');
            } else {
                flashToast(data.message || 'Bulk delete failed.', 'error');
            }
        } catch { flashToast('Network error — please try again.', 'error'); }
        finally {
            bulkDeleteBtn.disabled  = false;
            bulkDeleteBtn.innerHTML = '<i class="fas fa-trash"></i> Delete Selected';
        }
    });

    // ── Delete All employees ─────────────────────────────────────────────────
    const deleteAllUrl = '{{ route("employees.delete-all") }}';
    document.getElementById('empDeleteAllBtn').addEventListener('click', async function () {
        const total = document.querySelectorAll('#empTable tbody tr[data-site]').length;
        if (total === 0) { flashToast('No employees to delete.', 'error'); return; }
        if (!confirm(`Delete ALL ${total} employee${total !== 1 ? 's' : ''}? This cannot be undone.`)) return;

        this.disabled = true;
        this.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        try {
            const r    = await fetch(deleteAllUrl, {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            });
            const data = await r.json();
            if (data.success) {
                flashToast(`${data.deleted} employee${data.deleted !== 1 ? 's' : ''} deleted.`, 'success');
                setTimeout(() => location.reload(), 900);
            } else {
                flashToast(data.message || 'Delete failed.', 'error');
            }
        } catch { flashToast('Network error — please try again.', 'error'); }
        finally {
            this.disabled = false;
            this.innerHTML = '<i class="fas fa-trash-alt"></i><span>Delete All</span>';
        }
    });

    // ── Toast ────────────────────────────────────────────────────────────────
    function flashToast(msg, type) {
        let wrap = document.getElementById('emp-toast-wrap');
        if (!wrap) {
            wrap = document.createElement('div');
            wrap.id = 'emp-toast-wrap';
            wrap.style.cssText = 'position:fixed;top:76px;right:20px;z-index:9999;display:flex;flex-direction:column;gap:6px;min-width:240px;max-width:340px;';
            document.body.appendChild(wrap);
        }
        const pal = type === 'error'
            ? { bg:'#fee2e2', bd:'#fecaca', tx:'#991b1b', ic:'times-circle' }
            : { bg:'#dcfce7', bd:'#bbf7d0', tx:'#166534', ic:'check-circle' };
        const el = document.createElement('div');
        el.style.cssText = `background:${pal.bg};border:1px solid ${pal.bd};color:${pal.tx};padding:10px 14px;border-radius:9px;font-size:13px;font-weight:500;display:flex;align-items:center;gap:8px;box-shadow:0 4px 16px rgba(0,0,0,.1);animation:empToastIn .2s ease;`;
        el.innerHTML = `<i class="fas fa-${pal.ic}"></i> ${msg}`;
        wrap.appendChild(el);
        setTimeout(() => { el.style.transition = 'opacity .3s'; el.style.opacity = '0'; setTimeout(() => el.remove(), 320); }, 3000);
    }

    // ── Set Vale (manual per-employee balance) ───────────────────────────────
    const valeModalEl = document.getElementById('empValeModal');
    let   valeModal   = null;
    let   valeEmpId   = null;
    function getValeModal() {
        if (!valeModal && window.bootstrap) valeModal = new bootstrap.Modal(valeModalEl);
        return valeModal;
    }
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.js-set-vale');
        if (!btn) return;
        valeEmpId = btn.dataset.id;
        document.getElementById('valeModalName').textContent = btn.dataset.name;
        document.getElementById('valeInput').value = parseFloat(btn.dataset.vale || 0).toFixed(2);
        document.querySelectorAll('.emp-more-menu.open').forEach(m => {
            m.classList.remove('open');
            if (m.previousElementSibling) m.previousElementSibling.classList.remove('active');
        });
        const m = getValeModal(); if (m) m.show();
        setTimeout(() => document.getElementById('valeInput').focus(), 250);
    });
    document.getElementById('valeSaveBtn').addEventListener('click', async function () {
        const amount = parseFloat(document.getElementById('valeInput').value);
        if (isNaN(amount) || amount < 0) { flashToast('Enter a valid amount.', 'error'); return; }
        this.disabled = true;
        try {
            const r = await fetch(`{{ url('employees') }}/${valeEmpId}/vale`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: JSON.stringify({ vale: amount }),
            });
            const data = await r.json();
            if (data.success) {
                const cell = document.querySelector(`[data-vale-cell="${valeEmpId}"]`);
                if (cell) { cell.textContent = data.formatted; cell.classList.toggle('has-vale', data.vale > 0); }
                const m = getValeModal(); if (m) m.hide();
                flashToast('Vale balance updated.', 'success');
            } else { flashToast(data.message || 'Update failed.', 'error'); }
        } catch { flashToast('Network error — please try again.', 'error'); }
        finally { this.disabled = false; }
    });

    function escHtml(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
    }
})();
</script>
@endsection
