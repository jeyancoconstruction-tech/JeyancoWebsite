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
    <div class="dir-header">
        <div class="dir-header-text">
            <h1 class="dir-title"><i class="fas fa-users dir-title-icon"></i> Employee Directory</h1>
            <p class="dir-sub">Manage and view all employees in the company.</p>
        </div>
        <div class="dir-header-actions">
            <a href="{{ route('employees.export') }}" class="dir-btn-ghost">
                <i class="fas fa-download"></i> Export
            </a>
            <a href="{{ route('employees.create') }}" class="dir-btn-primary">
                <i class="fas fa-plus"></i> Add Employee
            </a>
        </div>
    </div>

    {{-- ── Summary cards ───────────────────────────────────────────────────────
         Bawat bilang ay galing sa parehong koleksyon na ipinapakita ng talahanayan
         sa ibaba, kaya imposibleng magkasalungat ang card at ang mga row. --}}
    <div class="dir-stats">
        <div class="dir-stat">
            <div class="dir-stat-body">
                <span class="dir-stat-label">Total Employees</span>
                <span class="dir-stat-value">{{ $stats['total'] }}</span>
                <span class="dir-stat-foot">Active workforce</span>
            </div>
            <span class="dir-stat-icon blue"><i class="fas fa-users"></i></span>
        </div>

        <div class="dir-stat">
            <div class="dir-stat-body">
                <span class="dir-stat-label">Total Rate / HR</span>
                <span class="dir-stat-value">₱{{ number_format($stats['total_rate'], 2) }}</span>
                <span class="dir-stat-foot">Combined hourly rate</span>
            </div>
            <span class="dir-stat-icon green"><i class="fas fa-wallet"></i></span>
        </div>

        <div class="dir-stat">
            <div class="dir-stat-body">
                <span class="dir-stat-label">Total Vale Balance</span>
                <span class="dir-stat-value">₱{{ number_format($stats['total_vale'], 2) }}</span>
                <span class="dir-stat-foot">Outstanding balance</span>
            </div>
            <span class="dir-stat-icon purple"><i class="fas fa-id-card"></i></span>
        </div>

        <div class="dir-stat">
            <div class="dir-stat-body">
                <span class="dir-stat-label">Avg. Rate / HR</span>
                <span class="dir-stat-value">₱{{ number_format($stats['avg_rate'], 2) }}</span>
                <span class="dir-stat-foot">Average hourly rate</span>
            </div>
            <span class="dir-stat-icon amber"><i class="fas fa-chart-line"></i></span>
        </div>

        <div class="dir-stat">
            <div class="dir-stat-body">
                <span class="dir-stat-label">With Fingerprint</span>
                <span class="dir-stat-value">{{ $stats['with_fingerprint'] }}</span>
                <span class="dir-stat-foot">
                    @if($stats['no_fingerprint'] > 0)
                        {{ $stats['no_fingerprint'] }} still to enrol
                    @else
                        Enrolled employees
                    @endif
                </span>
            </div>
            <span class="dir-stat-icon teal"><i class="fas fa-fingerprint"></i></span>
        </div>
    </div>

    {{-- ── Table card ──────────────────────────────────────────────────────── --}}
    <div class="emp-card">

        {{-- Toolbar: tabs, search, filter --}}
        <div class="dir-toolbar">
            <div class="dir-tabs">
                <button type="button" class="dir-tab active" data-scope="all">
                    All Employees <span class="dir-tab-count" id="countAll">{{ $stats['total'] }}</span>
                </button>
                <button type="button" class="dir-tab" data-scope="fp">
                    With Fingerprint <span class="dir-tab-count">{{ $stats['with_fingerprint'] }}</span>
                </button>
                @if($pendingCount > 0)
                <a href="{{ route('employees.register') }}" class="dir-tab dir-tab-link" title="Naghihintay ng pag-apruba sa Register & Manage">
                    Pending <span class="dir-tab-count warn">{{ $pendingCount }}</span>
                </a>
                @endif
            </div>

            <div class="dir-toolbar-right">
                <div class="emp-search-wrap">
                    <i class="fas fa-search emp-search-icon"></i>
                    <input type="text" id="empSearch" class="emp-search" placeholder="Search by name, position…">
                </div>

                <div class="emp-select-wrap">
                    <select id="siteFilter" class="emp-select">
                        <option value="">All Sites</option>
                        @foreach($sites as $site)
                            <option value="{{ $site->id }}">{{ $site->name }}</option>
                        @endforeach
                    </select>
                    <i class="fas fa-filter emp-select-icon"></i>
                </div>

                <button type="button" id="selectionModeBtn" class="dir-icon-btn" title="Select rows">
                    <i class="fas fa-list-check"></i>
                </button>
            </div>
        </div>

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
                        data-name="{{ strtolower($emp->name) }}"
                        data-position="{{ strtolower($emp->position ?: ($emp->laborType->name ?? '')) }}"
                        data-fp="{{ $emp->fingerprint_id ? '1' : '0' }}">

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
                            <a href="{{ route('employees.show', $emp->id) }}"
                               class="dir-icon-btn dir-view-btn" title="View {{ $emp->name }}">
                                <i class="fas fa-eye"></i>
                            </a>
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
                                    <button type="button" class="emp-more-item emp-more-delete js-emp-delete"
                                            data-id="{{ $emp->id }}"
                                            data-name="{{ $emp->name }}">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
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

        {{-- Ilang ang ipinapakita ngayon. Nagbabago ito habang naghahanap o
             nagsasala, kaya hindi ito nagsisinungaling tungkol sa nakikita mo. --}}
        <div class="dir-foot">
            <span class="dir-foot-text">
                Showing <strong id="dirShown">{{ $stats['total'] }}</strong>
                of <strong>{{ $stats['total'] }}</strong> employees
            </span>
        </div>

        {{-- Filter / search empty state --}}
        <div id="noMatch" class="emp-empty" style="display:none;padding:48px 0;">
            <div class="emp-empty-icon"><i class="fas fa-filter"></i></div>
            <p class="emp-empty-title">No results</p>
            <p class="emp-empty-sub">Try a different name or site filter.</p>
        </div>
    </div>
</div>

{{-- ── Delete confirmation ─────────────────────────────────────────────────
     Ang pagbura ay tinatanong sa isang modal na nagsasabi ng PANGALAN, hindi
     sa isang browser prompt na madaling mapindot nang hindi nababasa. --}}
<div class="modal fade" id="empDeleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content emp-modal-content">
            <div class="emp-modal-header">
                <div>
                    <h3 class="emp-modal-title">Remove employee</h3>
                    <p class="emp-modal-sub" id="deleteModalName">—</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3">
                <p class="emp-modal-sub" style="color:var(--text-muted);">
                    Mapupunta siya sa <strong>Removed</strong> sa Register &amp; Manage at
                    maibabalik mula roon. Mananatili ang attendance at payroll niya.
                </p>
                <form id="empDeleteForm" method="POST" class="d-flex justify-content-end gap-2 mt-3">
                    @csrf
                    @method('DELETE')
                    <button type="button" class="emp-btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="emp-bulk-delete">
                        <i class="fas fa-trash"></i> Remove
                    </button>
                </form>
            </div>
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
/* ═══════════════════════════════════════════════════════════════════════
   EMPLOYEE DIRECTORY — header, summary cards, toolbar
   Ang mga kulay ay galing sa enterprise.css tokens, hindi hardcoded,
   kaya sumusunod ito sa tema ng buong sistema.
   ═══════════════════════════════════════════════════════════════════════ */

/* ── Header ── */
.dir-header {
    display: flex; align-items: flex-start; justify-content: space-between;
    gap: 20px; flex-wrap: wrap; margin-bottom: 22px;
}
.dir-title {
    display: flex; align-items: center; gap: 12px;
    font-size: 1.85rem; font-weight: 800; letter-spacing: -0.02em;
    color: var(--text, #e8eef7); margin: 0;
}
.dir-title-icon {
    font-size: 1.4rem; color: var(--accent, #2f7fd1);
}
.dir-sub {
    margin: 6px 0 0 40px; font-size: 0.88rem;
    color: var(--text-muted, #8fa2bd);
}
.dir-header-actions { display: flex; align-items: center; gap: 10px; }

.dir-btn-ghost, .dir-btn-primary {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 11px 18px; border-radius: 10px;
    font-size: 0.86rem; font-weight: 600; text-decoration: none;
    cursor: pointer; transition: filter .15s, transform .1s, background .15s;
    white-space: nowrap;
}
.dir-btn-ghost {
    background: var(--surface-2, #1a2438);
    border: 1px solid var(--border, #2a3856);
    color: var(--text, #e8eef7);
}
.dir-btn-ghost:hover { background: var(--surface-3, #212d44); color: var(--text, #e8eef7); }
.dir-btn-primary {
    background: var(--accent, #2f7fd1); border: 1px solid var(--accent, #2f7fd1);
    color: #fff; box-shadow: 0 2px 10px rgba(47,127,209,0.28);
}
.dir-btn-primary:hover { filter: brightness(1.08); color: #fff; }
.dir-btn-ghost:active, .dir-btn-primary:active { transform: translateY(1px); }
.dir-btn-ghost:focus-visible, .dir-btn-primary:focus-visible {
    outline: 2px solid var(--accent, #2f7fd1); outline-offset: 2px;
}

/* ── Summary cards ── */
.dir-stats {
    display: grid; gap: 14px; margin-bottom: 20px;
    grid-template-columns: repeat(5, minmax(0, 1fr));
}
.dir-stat {
    display: flex; align-items: flex-start; justify-content: space-between; gap: 12px;
    background: var(--surface, #131c2e);
    border: 1px solid var(--border, #2a3856);
    border-radius: 14px; padding: 18px;
    transition: border-color .18s;
}
.dir-stat:hover { border-color: var(--accent, #2f7fd1); }
.dir-stat-body { display: flex; flex-direction: column; min-width: 0; }
.dir-stat-label {
    font-size: 0.74rem; font-weight: 600; color: var(--text-muted, #8fa2bd);
    letter-spacing: 0.2px;
}
.dir-stat-value {
    font-size: 1.55rem; font-weight: 800; line-height: 1.15; margin-top: 8px;
    color: var(--text, #e8eef7); font-variant-numeric: tabular-nums;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.dir-stat-foot {
    font-size: 0.7rem; color: var(--text-dim, #5a6b86); margin-top: 6px;
}
.dir-stat-icon {
    display: inline-flex; align-items: center; justify-content: center;
    width: 42px; height: 42px; border-radius: 11px; flex-shrink: 0;
    font-size: 1.05rem;
}
.dir-stat-icon.blue   { background: rgba(47,127,209,0.16);  color: #6fa8dc; }
.dir-stat-icon.green  { background: rgba(43,182,115,0.16);  color: #4ecb8d; }
.dir-stat-icon.purple { background: rgba(139,92,246,0.16);  color: #a78bfa; }
.dir-stat-icon.amber  { background: rgba(232,163,61,0.16);  color: #e8a33d; }
.dir-stat-icon.teal   { background: rgba(20,184,166,0.16);  color: #2dd4bf; }

/* ── Toolbar: tabs + search + filter ── */
.dir-toolbar {
    display: flex; align-items: center; justify-content: space-between;
    gap: 14px; flex-wrap: wrap;
    padding: 14px 18px; border-bottom: 1px solid var(--border, #2a3856);
}
.dir-tabs { display: flex; align-items: center; gap: 4px; flex-wrap: wrap; }
.dir-tab {
    display: inline-flex; align-items: center; gap: 8px;
    background: none; border: none; border-bottom: 2px solid transparent;
    padding: 9px 14px; border-radius: 8px 8px 0 0;
    font-size: 0.85rem; font-weight: 600; color: var(--text-muted, #8fa2bd);
    cursor: pointer; text-decoration: none; transition: color .15s, border-color .15s;
}
.dir-tab:hover { color: var(--text, #e8eef7); }
.dir-tab.active {
    color: var(--accent, #2f7fd1); border-bottom-color: var(--accent, #2f7fd1);
}
.dir-tab:focus-visible { outline: 2px solid var(--accent, #2f7fd1); outline-offset: 2px; }
.dir-tab-count {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 22px; height: 22px; padding: 0 7px; border-radius: 11px;
    background: var(--surface-3, #212d44); color: var(--text, #e8eef7);
    font-size: 0.72rem; font-weight: 700; font-variant-numeric: tabular-nums;
}
.dir-tab.active .dir-tab-count { background: rgba(47,127,209,0.2); color: #6fa8dc; }
.dir-tab-count.warn { background: rgba(232,163,61,0.18); color: #e8a33d; }

.dir-toolbar-right { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }

.dir-icon-btn {
    display: inline-flex; align-items: center; justify-content: center;
    width: 38px; height: 38px; border-radius: 9px;
    background: var(--surface-2, #1a2438);
    border: 1px solid var(--border, #2a3856);
    color: var(--text-muted, #8fa2bd);
    cursor: pointer; text-decoration: none; transition: all .15s;
}
.dir-icon-btn:hover { color: var(--accent, #2f7fd1); border-color: var(--accent, #2f7fd1); }
.dir-icon-btn:focus-visible { outline: 2px solid var(--accent, #2f7fd1); outline-offset: 2px; }
.dir-view-btn { margin-right: 6px; vertical-align: middle; }

/* ── Footer count ── */
.dir-foot {
    display: flex; align-items: center; justify-content: space-between;
    padding: 14px 18px; border-top: 1px solid var(--border, #2a3856);
}
.dir-foot-text { font-size: 0.8rem; color: var(--text-muted, #8fa2bd); }
.dir-foot-text strong { color: var(--text, #e8eef7); font-variant-numeric: tabular-nums; }

/* ── Responsive ── */
@media (max-width: 1400px) {
    .dir-stats { grid-template-columns: repeat(3, minmax(0, 1fr)); }
}
@media (max-width: 900px) {
    .dir-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .dir-title { font-size: 1.5rem; }
    .dir-sub { margin-left: 0; }
    .dir-header-actions { width: 100%; }
    .dir-btn-ghost, .dir-btn-primary { flex: 1; justify-content: center; }
}
@media (max-width: 560px) {
    .dir-stats { grid-template-columns: 1fr; }
    .dir-toolbar { flex-direction: column; align-items: stretch; }
    .dir-toolbar-right { flex-direction: column; align-items: stretch; }
    .dir-toolbar-right .emp-search-wrap,
    .dir-toolbar-right .emp-select-wrap { width: 100%; }
}

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
    // Aling tab ang bukas. Pinagsasama ito sa search at sa site filter, kaya
    // ang tatlo ay nagpapaliit nang sabay imbes na maglaban-laban.
    let dirScope = 'all';

    function applyFilter() {
        const siteVal  = document.getElementById('siteFilter').value;
        const query    = document.getElementById('empSearch').value.trim().toLowerCase();
        const rows     = document.querySelectorAll('#empTable tbody tr[data-site]');
        let visible    = 0;

        rows.forEach(r => {
            const siteOk  = !siteVal || r.dataset.site === siteVal;
            const nameOk  = !query   || (r.dataset.name || '').includes(query)
                                     || (r.dataset.position || '').includes(query);
            const scopeOk = dirScope !== 'fp' || r.dataset.fp === '1';
            const show    = siteOk && nameOk && scopeOk;
            r.style.display = show ? '' : 'none';
            if (show) visible++;
        });

        document.getElementById('noMatch').style.display = (rows.length > 0 && visible === 0) ? 'block' : 'none';

        // Ang bilang sa ibaba ay dapat sumasalamin sa NAKIKITA, hindi sa
        // kabuuan — kung hindi, nagsisinungaling ito habang naghahanap ka.
        const shown = document.getElementById('dirShown');
        if (shown) shown.textContent = visible;

        updateBulkBar();
    }

    // ── Tabs ─────────────────────────────────────────────────────────────────
    document.querySelectorAll('.dir-tab[data-scope]').forEach(tab => {
        tab.addEventListener('click', () => {
            document.querySelectorAll('.dir-tab[data-scope]')
                    .forEach(t => t.classList.toggle('active', t === tab));
            dirScope = tab.dataset.scope;
            applyFilter();
        });
    });

    // ── Delete: ask in a modal that names the person ─────────────────────────
    (function () {
        const modalEl = document.getElementById('empDeleteModal');
        const form    = document.getElementById('empDeleteForm');
        const nameEl  = document.getElementById('deleteModalName');
        if (!modalEl || !form) return;

        const base  = '{{ url('employees') }}';
        const modal = new bootstrap.Modal(modalEl);

        document.querySelectorAll('.js-emp-delete').forEach(btn => {
            btn.addEventListener('click', () => {
                form.action = base + '/' + btn.dataset.id;
                if (nameEl) nameEl.textContent = btn.dataset.name || '';
                document.querySelectorAll('.emp-more-menu.open')
                        .forEach(m => m.classList.remove('open'));
                modal.show();
            });
        });
    })();

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

    // "Delete All" was removed from this page: a single click that wiped the
    // entire workforce sat one slip away from the ordinary buttons, and bulk
    // select already covers deleting several people deliberately.

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
