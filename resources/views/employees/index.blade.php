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
                        <td colspan="7">
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

{{-- ── Styles ──────────────────────────────────────────────────────────────── --}}
<style>
/* ── Page shell ──────────────────────────────────────────────────────────── */
.emp-page { max-width: none; width: 100%; margin: 0; }

/* ── Flash ───────────────────────────────────────────────────────────────── */
.emp-flash {
    display: flex; align-items: center; gap: 10px;
    background: #f0fdf4; border: 1px solid #bbf7d0; border-left: 4px solid #16a34a;
    color: #166534; padding: 11px 16px; border-radius: 10px;
    font-size: 13.5px; font-weight: 500; margin-bottom: 20px;
}

/* ── Page header ─────────────────────────────────────────────────────────── */
.emp-header {
    display: flex; align-items: center; justify-content: space-between;
    flex-wrap: wrap; gap: 14px; margin-bottom: 22px;
}
.emp-header-left { display: flex; align-items: center; gap: 12px; }
.emp-title {
    font-size: 1.45rem; font-weight: 700; color: #0f172a; margin: 0;
}
.emp-count-chip {
    font-size: 12px; font-weight: 600; color: #2563eb;
    background: #eff6ff; border: 1px solid #bfdbfe;
    padding: 3px 10px; border-radius: 20px;
}
.emp-header-right {
    display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
}

/* ── Search ──────────────────────────────────────────────────────────────── */
.emp-search-wrap {
    position: relative; display: flex; align-items: center;
}
.emp-search-icon {
    position: absolute; left: 11px; color: #94a3b8;
    font-size: 12px; pointer-events: none;
}
.emp-search {
    height: 36px; padding: 0 12px 0 32px; font-size: 13px;
    border: 1.5px solid #e2e8f0; border-radius: 8px;
    background: #fff; color: #0f172a; width: 200px;
    outline: none; transition: border-color .15s, box-shadow .15s;
}
.emp-search:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,.08); }
.emp-search::placeholder { color: #94a3b8; }

/* ── Select ──────────────────────────────────────────────────────────────── */
.emp-select-wrap { position: relative; }
.emp-select {
    height: 36px; padding: 0 30px 0 11px; font-size: 13px;
    border: 1.5px solid #e2e8f0; border-radius: 8px;
    background: #fff; color: #374151;
    appearance: none; -webkit-appearance: none;
    cursor: pointer; outline: none;
    transition: border-color .15s;
    min-width: 130px;
}
.emp-select:focus { border-color: #3b82f6; }
.emp-select-icon {
    position: absolute; right: 10px; top: 50%;
    transform: translateY(-50%); color: #94a3b8;
    font-size: 10px; pointer-events: none;
}

/* ── Delete All button ───────────────────────────────────────────────────── */
.emp-del-all-btn {
    height: 36px; padding: 0 14px; font-size: 13px; font-weight: 700;
    background: #dc2626; color: #fff;
    border: none; border-radius: 8px; cursor: pointer;
    display: inline-flex; align-items: center; gap: 6px;
    transition: background .15s; white-space: nowrap;
}
.emp-del-all-btn:hover { background: #b91c1c; }
[data-bs-theme="dark"] .emp-del-all-btn { background: #991b1b; }
[data-bs-theme="dark"] .emp-del-all-btn:hover { background: #7f1d1d; }

/* ── Secondary button ────────────────────────────────────────────────────── */
.emp-btn-secondary {
    height: 36px; padding: 0 14px; font-size: 13px; font-weight: 600;
    background: #f1f5f9; color: #475569;
    border: 1.5px solid #e2e8f0; border-radius: 8px;
    cursor: pointer; display: inline-flex; align-items: center; gap: 6px;
    transition: background .15s, color .15s, border-color .15s;
    white-space: nowrap;
}
.emp-btn-secondary:hover { background: #e2e8f0; color: #1e293b; border-color: #cbd5e1; }

/* ── Table card ──────────────────────────────────────────────────────────── */
.emp-card {
    background: #fff; border: 1px solid #e2e8f0;
    border-radius: 14px; overflow: hidden;
}

/* ── Table ───────────────────────────────────────────────────────────────── */
.emp-table { width: 100%; border-collapse: collapse; }
.emp-table thead th {
    background: #f8fafc; padding: 11px 16px;
    font-size: 11px; font-weight: 700; letter-spacing: .6px;
    text-transform: uppercase; color: #64748b;
    border-bottom: 1px solid #e2e8f0;
    white-space: nowrap;
}
.emp-table thead th:last-child { width: 48px; }
.emp-table tbody td {
    padding: 13px 16px; border-bottom: 1px solid #f1f5f9;
    vertical-align: middle; font-size: 14px;
}
.emp-table tbody tr:last-child td { border-bottom: none; }
.emp-table tbody tr { transition: background .1s; }
.emp-table tbody tr:hover td { background: #f8fafc; }

/* ── Employee cell (avatar + info) ───────────────────────────────────────── */
.emp-cell { display: flex; align-items: center; gap: 12px; }
.emp-avatar-img {
    width: 42px; height: 42px; border-radius: 50%;
    object-fit: cover; border: 2px solid #e0e7ef;
    flex-shrink: 0;
}
.emp-avatar-initials {
    width: 42px; height: 42px; border-radius: 50%;
    font-size: 15px; font-weight: 700; color: #fff;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0; border: 2px solid transparent;
}
/* Avatar color palette keyed by first letter */
.emp-av-a,.emp-av-b { background: linear-gradient(135deg,#3b82f6,#3b82f6); }
.emp-av-c,.emp-av-d { background: linear-gradient(135deg,#2563eb,#60a5fa); }
.emp-av-e,.emp-av-f { background: linear-gradient(135deg,#065f46,#10b981); }
.emp-av-g,.emp-av-h { background: linear-gradient(135deg,#92400e,#f59e0b); }
.emp-av-i,.emp-av-j { background: linear-gradient(135deg,#be123c,#f43f5e); }
.emp-av-k,.emp-av-l { background: linear-gradient(135deg,#0e7490,#38bdf8); }
.emp-av-m,.emp-av-n { background: linear-gradient(135deg,#3b82f6,#3b82f6); }
.emp-av-o,.emp-av-p { background: linear-gradient(135deg,#701a75,#e879f9); }
.emp-av-q,.emp-av-r { background: linear-gradient(135deg,#7c2d12,#f97316); }
.emp-av-s,.emp-av-t { background: linear-gradient(135deg,#134e4a,#2dd4bf); }
.emp-av-u,.emp-av-v { background: linear-gradient(135deg,#3b82f6,#2563eb); }
.emp-av-w,.emp-av-x { background: linear-gradient(135deg,#166534,#4ade80); }
.emp-av-y,.emp-av-z { background: linear-gradient(135deg,#9f1239,#fb7185); }

.emp-info  { display: flex; flex-direction: column; gap: 3px; min-width: 0; }
.emp-name  { font-size: 14px; font-weight: 600; color: #0f172a; white-space: nowrap; }
.emp-id-badge {
    font-size: 11px; font-weight: 600; font-family: 'Courier New', monospace;
    color: #64748b; background: #f1f5f9; border: 1px solid #e2e8f0;
    padding: 1px 6px; border-radius: 4px; width: fit-content;
}

/* ── Badges ──────────────────────────────────────────────────────────────── */
.emp-badge-site {
    display: inline-flex; align-items: center; gap: 5px;
    font-size: 12px; font-weight: 600; white-space: nowrap;
    color: #166534; background: #f0fdf4; border: 1px solid #bbf7d0;
    padding: 4px 9px; border-radius: 20px;
}
.emp-badge-site i { font-size: 9px; }

.emp-badge-labor {
    display: inline-flex; align-items: center; gap: 5px;
    font-size: 12px; font-weight: 600; white-space: nowrap;
    color: #fff; background: linear-gradient(135deg,#3b82f6,#3b82f6);
    padding: 4px 10px; border-radius: 20px;
}
.emp-badge-labor i { font-size: 10px; }

.emp-badge-fp {
    display: inline-flex; align-items: center; gap: 5px;
    font-size: 12px; font-weight: 600; font-family: 'Courier New', monospace;
    color: #fff; background: #059669;
    padding: 4px 9px; border-radius: 20px;
}
.emp-badge-fp i { font-size: 11px; }

.emp-dash { color: #94a3b8; font-size: 13px; }
.emp-rate { text-align: center; font-size: 14px; font-weight: 700; color: #374151; }
.emp-actions-cell { text-align: right; white-space: nowrap; }

/* ── Empty state ─────────────────────────────────────────────────────────── */
.emp-empty {
    display: flex; flex-direction: column; align-items: center;
    justify-content: center; padding: 56px 0; text-align: center;
}
.emp-empty-icon {
    width: 64px; height: 64px; border-radius: 50%;
    background: #f1f5f9; display: flex; align-items: center; justify-content: center;
    font-size: 24px; color: #94a3b8; margin-bottom: 16px;
}
.emp-empty-title { font-size: 15px; font-weight: 600; color: #374151; margin: 0 0 6px; }
.emp-empty-sub   { font-size: 13px; color: #94a3b8; margin: 0; }
.emp-empty-row td { padding: 0; border-bottom: none; }

/* ── Three-dot menu ──────────────────────────────────────────────────────── */
.emp-more-wrap { position: relative; display: inline-block; }
.emp-more-btn {
    width: 32px; height: 32px; border-radius: 7px;
    border: 1.5px solid #e2e8f0; background: #f8fafc;
    color: #64748b; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    transition: background .12s, border-color .12s, color .12s;
    font-size: 13px;
}
.emp-more-btn:hover        { background: #f1f5f9; border-color: #cbd5e1; color: #1e293b; }
.emp-more-btn.active       { background: #eff6ff; border-color: #bfdbfe; color: #2563eb; }
.emp-more-menu {
    display: none; position: absolute; right: 0; top: calc(100% + 6px);
    background: #fff; border: 1px solid #e2e8f0;
    border-radius: 10px; box-shadow: 0 8px 28px rgba(0,0,0,.10);
    z-index: 100; min-width: 148px; overflow: hidden;
}
.emp-more-menu.open { display: block; }
.emp-more-item {
    display: flex; align-items: center; gap: 9px;
    padding: 10px 14px; font-size: 13px; font-weight: 500;
    color: #374151; text-decoration: none;
    width: 100%; background: none; border: none; cursor: pointer;
    transition: background .1s;
}
.emp-more-item:hover        { background: #f8fafc; color: #1e293b; }
.emp-more-item i            { width: 14px; text-align: center; font-size: 12px; }
.emp-more-delete            { color: #dc2626; }
.emp-more-delete:hover      { background: #fef2f2; color: #991b1b; }

/* ── Modal ───────────────────────────────────────────────────────────────── */
.emp-modal-content {
    border: none; border-radius: 14px;
    box-shadow: 0 24px 64px rgba(0,0,0,0.14); overflow: hidden;
}
.emp-modal-header {
    display: flex; align-items: flex-start; justify-content: space-between;
    padding: 18px 24px;
    background: linear-gradient(135deg, #3b82f6, #2563eb); color: #fff;
}
.emp-modal-title { font-size: 16px; font-weight: 700; margin: 0 0 2px; }
.emp-modal-sub   { font-size: 12px; opacity: .8; margin: 0; }
.emp-site-add-panel {
    background: #f8fafc; border: 1px solid #e2e8f0;
    border-radius: 10px; padding: 14px 16px; margin-bottom: 16px;
}
.emp-site-add-label {
    font-size: 12px; font-weight: 700; color: #374151; margin-bottom: 10px;
    display: flex; align-items: center; gap: 6px;
}
.emp-site-add-label i { color: #3b82f6; }
.emp-modal-input {
    flex: 1; height: 36px; padding: 0 11px; font-size: 13px;
    border: 1.5px solid #e2e8f0; border-radius: 7px;
    background: #fff; color: #0f172a; outline: none;
    transition: border-color .15s;
}
.emp-modal-input:focus { border-color: #3b82f6; }
.emp-site-add-btn {
    height: 36px; padding: 0 16px; font-size: 13px; font-weight: 700;
    background: #3b82f6; color: #fff; border: none;
    border-radius: 7px; cursor: pointer; white-space: nowrap;
    transition: background .15s;
}
.emp-site-add-btn:hover { background: #2563eb; }
.emp-modal-err { font-size: 12px; color: #dc2626; margin-top: 6px; }
.emp-sites-loading { text-align: center; padding: 20px 0; color: #64748b; font-size: 13px; }

/* ── Site list rows ──────────────────────────────────────────────────────── */
.site-row {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 14px; border-radius: 9px;
    border: 1px solid #e2e8f0; margin-bottom: 8px;
    background: #fff; transition: border-color .15s;
}
.site-row:hover { border-color: #c7d2fe; }
.site-row .site-name  { flex: 1; font-weight: 600; color: #1e293b; font-size: 14px; }
.site-row .site-count { font-size: 12px; color: #64748b; white-space: nowrap; }
.site-row input.site-edit-input {
    flex: 1; font-size: 14px; font-weight: 600;
    border: 1.5px solid #3b82f6; border-radius: 6px; padding: 4px 8px;
    outline: none;
}
.site-action-btn {
    border: none; background: transparent;
    padding: 5px 7px; border-radius: 6px;
    cursor: pointer; font-size: 12px; line-height: 1;
    transition: background .12s;
}
.site-action-btn:hover  { background: #f1f5f9; }
.site-action-btn.edit   { color: #d97706; }
.site-action-btn.save   { color: #16a34a; }
.site-action-btn.cancel { color: #64748b; }
.site-action-btn.del    { color: #dc2626; }

/* ── Checkbox column (hidden until selection mode is active) ─────────────── */
.emp-col-check { display: none; width: 44px; text-align: center; padding-left: 8px !important; padding-right: 4px !important; }
.emp-selecting .emp-col-check { display: table-cell; }
.emp-checkbox, .emp-row-check {
    width: 16px; height: 16px; cursor: pointer;
    accent-color: #3b82f6; flex-shrink: 0;
}

/* ── Selection mode active button ────────────────────────────────────────── */
.emp-btn-selecting {
    background: #eff6ff !important; color: #2563eb !important;
    border-color: #bfdbfe !important;
}
.emp-btn-selecting:hover { background: #dbeafe !important; border-color: #93c5fd !important; }

/* ── Bulk action bar ─────────────────────────────────────────────────────── */
.emp-bulk-bar {
    display: flex; align-items: center; justify-content: space-between;
    padding: 10px 16px; gap: 12px; flex-wrap: wrap;
    background: #eff6ff; border-bottom: 1px solid #bfdbfe;
}
.emp-bulk-info {
    display: flex; align-items: center; gap: 8px;
    font-size: 13px; font-weight: 600; color: #2563eb;
}
.emp-bulk-info i { font-size: 14px; }
.emp-bulk-actions { display: flex; align-items: center; gap: 8px; }
.emp-bulk-cancel {
    height: 32px; padding: 0 12px; font-size: 12px; font-weight: 600;
    background: transparent; color: #64748b;
    border: 1.5px solid #cbd5e1; border-radius: 7px;
    cursor: pointer; transition: background .15s;
    display: inline-flex; align-items: center; gap: 6px;
}
.emp-bulk-cancel:hover { background: #f1f5f9; }
.emp-bulk-delete {
    height: 32px; padding: 0 14px; font-size: 12px; font-weight: 700;
    background: #dc2626; color: #fff;
    border: none; border-radius: 7px; cursor: pointer;
    transition: background .15s;
    display: inline-flex; align-items: center; gap: 6px;
}
.emp-bulk-delete:hover    { background: #b91c1c; }
.emp-bulk-delete:disabled { background: #fca5a5; cursor: not-allowed; }

/* ── Dark mode ───────────────────────────────────────────────────────────── */
[data-bs-theme="dark"] .emp-flash       { background:#052e16; border-color:#166534; border-left-color:#22c55e; color:#86efac; }
[data-bs-theme="dark"] .emp-title       { color: #e8edf5; }
[data-bs-theme="dark"] .emp-count-chip  { background: #172554; border-color: #3b82f6; color: #93c5fd; }
[data-bs-theme="dark"] .emp-search      { background: #151d2e; border-color: #283449; color: #e8edf5; }
[data-bs-theme="dark"] .emp-search:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,.12); }
[data-bs-theme="dark"] .emp-search::placeholder { color: #6b7d96; }
[data-bs-theme="dark"] .emp-select      { background: #151d2e; border-color: #283449; color: #cdd7e5; }
[data-bs-theme="dark"] .emp-select:focus { border-color: #3b82f6; }
[data-bs-theme="dark"] .emp-btn-secondary { background: #1c2740; border-color: #283449; color: #94a3b8; }
[data-bs-theme="dark"] .emp-btn-secondary:hover { background: #283449; color: #e2e8f0; border-color: #38465e; }
[data-bs-theme="dark"] .emp-card        { background: #151d2e; border-color: #283449; }
[data-bs-theme="dark"] .emp-table thead th { background: #1c2740; color: #6b7d96; border-bottom-color: #283449; }
[data-bs-theme="dark"] .emp-table tbody td { border-bottom-color: #1a2336; }
[data-bs-theme="dark"] .emp-table tbody tr:hover td { background: #1a2336; }
[data-bs-theme="dark"] .emp-name        { color: #e8edf5; }
[data-bs-theme="dark"] .emp-id-badge    { background: #1c2740; border-color: #283449; color: #9fb0c7; }
[data-bs-theme="dark"] .emp-avatar-img  { border-color: #283449; }
[data-bs-theme="dark"] .emp-badge-site  { background: #052e16; border-color: #166534; color: #86efac; }
[data-bs-theme="dark"] .emp-badge-fp    { background: #065f46; }
[data-bs-theme="dark"] .emp-rate        { color: #cdd7e5; }
[data-bs-theme="dark"] .emp-dash        { color: #475569; }
[data-bs-theme="dark"] .emp-empty-icon  { background: #1c2740; color: #475569; }
[data-bs-theme="dark"] .emp-empty-title { color: #9fb0c7; }
[data-bs-theme="dark"] .emp-empty-sub   { color: #475569; }
[data-bs-theme="dark"] .emp-more-btn    { background: #1c2740; border-color: #283449; color: #94a3b8; }
[data-bs-theme="dark"] .emp-more-btn:hover   { background: #283449; color: #e2e8f0; }
[data-bs-theme="dark"] .emp-more-btn.active  { background: #172554; border-color: #1d4ed8; color: #93c5fd; }
[data-bs-theme="dark"] .emp-more-menu   { background: #1c2740; border-color: #283449; box-shadow: 0 8px 24px rgba(0,0,0,.35); }
[data-bs-theme="dark"] .emp-more-item   { color: #cdd7e5; }
[data-bs-theme="dark"] .emp-more-item:hover  { background: #283449; color: #e8edf5; }
[data-bs-theme="dark"] .emp-more-delete { color: #f87171; }
[data-bs-theme="dark"] .emp-more-delete:hover { background: #2d1b1b; }
[data-bs-theme="dark"] .emp-modal-content { background: #151d2e; }
[data-bs-theme="dark"] .modal-body       { color: #cdd7e5; }
[data-bs-theme="dark"] .emp-site-add-panel { background: #0f1a2e; border-color: #283449; }
[data-bs-theme="dark"] .emp-site-add-label { color: #9fb0c7; }
[data-bs-theme="dark"] .emp-modal-input  { background: #151d2e; border-color: #283449; color: #e8edf5; }
[data-bs-theme="dark"] .emp-modal-input:focus { border-color: #3b82f6; }
[data-bs-theme="dark"] .site-row         { background: #1c2740; border-color: #283449; }
[data-bs-theme="dark"] .site-row:hover   { border-color: #4f46e5; }
[data-bs-theme="dark"] .site-row .site-name  { color: #e2e8f0; }
[data-bs-theme="dark"] .site-row .site-count { color: #6b7d96; }
[data-bs-theme="dark"] .site-action-btn:hover { background: #283449; }
[data-bs-theme="dark"] .emp-sites-loading { color: #6b7d96; }
[data-bs-theme="dark"] .emp-bulk-bar     { background: #172554; border-bottom-color: #3b82f6; }
[data-bs-theme="dark"] .emp-bulk-info    { color: #93c5fd; }
[data-bs-theme="dark"] .emp-bulk-cancel  { border-color: #283449; color: #94a3b8; }
[data-bs-theme="dark"] .emp-bulk-cancel:hover { background: #283449; }
[data-bs-theme="dark"] .emp-checkbox,
[data-bs-theme="dark"] .emp-row-check    { accent-color: #3b82f6; }
[data-bs-theme="dark"] .emp-btn-selecting { background: #172554 !important; color: #93c5fd !important; border-color: #3b82f6 !important; }
[data-bs-theme="dark"] .emp-btn-selecting:hover { background: #3b82f6 !important; }

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

    function escHtml(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
    }
})();
</script>
@endsection
