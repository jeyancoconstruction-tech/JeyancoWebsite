@extends('layouts')
@section('page_title', 'Register & Manage Employees')

@section('content')
@php
    use App\Models\Employee;
    $renderName = fn ($e) => ($e->isPending() && $e->name === 'Unregistered Worker') ? 'Unregistered Worker' : $e->name;
@endphp
<div class="rm-page">

    {{-- ── Flash / errors ──────────────────────────────────────────────────── --}}
    @if(session('success'))
    <div class="rm-alert rm-alert-ok"><i class="fas fa-check-circle"></i><span>{{ session('success') }}</span></div>
    @endif
    @if($errors->any())
    <div class="rm-alert rm-alert-err">
        <i class="fas fa-exclamation-circle"></i>
        <div><strong>Please fix the following:</strong>
            <ul class="mb-0 mt-1">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    </div>
    @endif

    {{-- ── Header ──────────────────────────────────────────────────────────── --}}
    <div class="rm-header">
        <div>
            <h1 class="rm-title">Register &amp; Manage Employees</h1>
            <p class="rm-sub">
                Workers detected by the <strong>Site A</strong> fingerprint kiosk appear here automatically.
                Complete their details to activate them across Attendance, Payroll and the Dashboard.
            </p>
        </div>
        <button type="button" class="rm-btn-primary" id="rmAddBtn">
            <i class="fas fa-user-plus"></i> Add Manually
        </button>
    </div>

    {{-- ── Stat chips (also switch tabs) ───────────────────────────────────── --}}
    <div class="rm-stats">
        <button class="rm-stat rm-stat-pending active" data-tab="pending">
            <span class="rm-stat-num">{{ $pending->count() }}</span>
            <span class="rm-stat-lbl"><i class="fas fa-fingerprint"></i> Pending from kiosk</span>
        </button>
        <button class="rm-stat rm-stat-active" data-tab="active">
            <span class="rm-stat-num">{{ $active->count() }}</span>
            <span class="rm-stat-lbl"><i class="fas fa-user-check"></i> Active</span>
        </button>
        <button class="rm-stat rm-stat-archived" data-tab="archived">
            <span class="rm-stat-num">{{ $archived->count() }}</span>
            <span class="rm-stat-lbl"><i class="fas fa-box-archive"></i> Archived</span>
        </button>
        <button class="rm-stat rm-stat-removed" data-tab="removed">
            <span class="rm-stat-num">{{ $removed->count() }}</span>
            <span class="rm-stat-lbl"><i class="fas fa-trash-can-arrow-up"></i> Removed</span>
        </button>
    </div>

    {{-- ── Tabs ────────────────────────────────────────────────────────────── --}}
    <div class="rm-tabs">
        <button class="rm-tab active" data-tab="pending">Pending <span class="rm-tab-count">{{ $pending->count() }}</span></button>
        <button class="rm-tab" data-tab="active">Active <span class="rm-tab-count">{{ $active->count() }}</span></button>
        <button class="rm-tab" data-tab="archived">Archived <span class="rm-tab-count">{{ $archived->count() }}</span></button>
        <button class="rm-tab" data-tab="removed">Removed <span class="rm-tab-count">{{ $removed->count() }}</span></button>
    </div>

    {{-- ═══ PENDING ════════════════════════════════════════════════════════ --}}
    <div class="rm-pane active" data-pane="pending">
        <div class="rm-card">
            <div class="rm-card-note">
                <i class="fas fa-circle-info"></i>
                These workers scanned a new fingerprint on the kiosk. Click <strong>Complete</strong> to set their
                name, position and rate — they then become active everywhere in the system.
            </div>
            <div class="table-responsive">
                <table class="rm-table">
                    <thead>
                        <tr>
                            <th>Worker</th><th>Fingerprint</th><th>Detected at</th>
                            <th>First seen</th><th class="text-center">Logs</th><th></th>
                        </tr>
                    </thead>
                    <tbody id="rmPendingBody">
                        @include('employees._rows_pending', ['pending' => $pending])
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ═══ ACTIVE ═════════════════════════════════════════════════════════ --}}
    <div class="rm-pane" data-pane="active">
        <div class="rm-card">
            <div class="table-responsive">
                <table class="rm-table">
                    <thead>
                        <tr>
                            <th>Employee</th><th>Site</th><th>Labor Type</th>
                            <th class="text-center">Rate / hr</th><th>Fingerprint</th>
                            <th class="text-center">Logs</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($active as $e)
                        <tr>
                            <td>@include('employees._person', ['e' => $e, 'displayName' => $e->name])</td>
                            <td>@include('employees._site', ['e' => $e])</td>
                            <td>@include('employees._labor', ['e' => $e])</td>
                            <td class="rm-rate">₱{{ number_format($e->rate_per_hour, 2) }}</td>
                            <td>@include('employees._fp', ['e' => $e])</td>
                            <td class="text-center"><span class="rm-pill">{{ $e->attendances_count }}</span></td>
                            <td class="rm-actions">
                                <button class="rm-btn-ghost js-emp-edit"
                                        data-mode="edit"
                                        data-id="{{ $e->id }}"
                                        data-name="{{ $e->name }}"
                                        data-labor="{{ $e->labor_type_id }}"
                                        data-rate="{{ $e->rate_per_hour }}"
                                        data-site="{{ $e->site_id }}"
                                        data-fp="{{ $e->fingerprint_id }}">
                                    <i class="fas fa-pen"></i> Edit
                                </button>
                                @include('employees._menu', ['e' => $e, 'context' => 'active'])
                            </td>
                        </tr>
                    @empty
                        @include('employees._empty', ['icon' => 'users', 'title' => 'No active employees', 'sub' => 'Complete a pending detection or add one manually to get started.'])
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ═══ ARCHIVED ═══════════════════════════════════════════════════════ --}}
    <div class="rm-pane" data-pane="archived">
        <div class="rm-card">
            <div class="rm-card-note">
                <i class="fas fa-circle-info"></i>
                Archived workers have left the company. Their payroll history stays intact; reactivate them anytime.
            </div>
            <div class="table-responsive">
                <table class="rm-table">
                    <thead>
                        <tr><th>Employee</th><th>Site</th><th>Labor Type</th><th>Archived</th><th class="text-center">Logs</th><th></th></tr>
                    </thead>
                    <tbody>
                    @forelse($archived as $e)
                        <tr>
                            <td>@include('employees._person', ['e' => $e, 'displayName' => $e->name])</td>
                            <td>@include('employees._site', ['e' => $e])</td>
                            <td>@include('employees._labor', ['e' => $e])</td>
                            <td class="rm-muted">{{ $e->archived_at?->format('M d, Y') ?? '—' }}</td>
                            <td class="text-center"><span class="rm-pill">{{ $e->attendances_count }}</span></td>
                            <td class="rm-actions">@include('employees._menu', ['e' => $e, 'context' => 'archived'])</td>
                        </tr>
                    @empty
                        @include('employees._empty', ['icon' => 'box-archive', 'title' => 'Nothing archived', 'sub' => 'Workers you archive will appear here.'])
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ═══ REMOVED ════════════════════════════════════════════════════════ --}}
    <div class="rm-pane" data-pane="removed">
        <div class="rm-card">
            <div class="rm-card-note">
                <i class="fas fa-circle-info"></i>
                Removed records are hidden everywhere but never lost. Restore them, or permanently delete as a last resort.
            </div>
            <div class="table-responsive">
                <table class="rm-table">
                    <thead>
                        <tr><th>Employee</th><th>Site</th><th>Labor Type</th><th>Removed</th><th class="text-center">Logs</th><th></th></tr>
                    </thead>
                    <tbody>
                    @forelse($removed as $e)
                        <tr>
                            <td>@include('employees._person', ['e' => $e, 'displayName' => $e->name])</td>
                            <td>@include('employees._site', ['e' => $e])</td>
                            <td>@include('employees._labor', ['e' => $e])</td>
                            <td class="rm-muted">{{ $e->deleted_at?->format('M d, Y') ?? '—' }}</td>
                            <td class="text-center"><span class="rm-pill">{{ $e->attendances_count }}</span></td>
                            <td class="rm-actions">@include('employees._menu', ['e' => $e, 'context' => 'removed'])</td>
                        </tr>
                    @empty
                        @include('employees._empty', ['icon' => 'trash-can-arrow-up', 'title' => 'Nothing removed', 'sub' => 'Removed records can be restored from here.'])
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- ════════════════════════════ EMPLOYEE FORM MODAL ═══════════════════════ --}}
<div class="modal fade" id="empFormModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:540px;">
    <div class="modal-content rm-modal">
      <form id="empForm" method="POST" enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="_method" id="empFormMethod" value="POST">
        <input type="hidden" name="_form_mode" id="empFormModeField" value="">
        <input type="hidden" name="_form_id" id="empFormIdField" value="">

        <div class="rm-modal-head">
            <div>
                <h6 class="rm-modal-title" id="empFormTitle">Complete Registration</h6>
                <p class="rm-modal-sub" id="empFormSub">Set this worker's details to activate them.</p>
            </div>
            <button type="button" class="rm-modal-x" data-bs-dismiss="modal" aria-label="Close"><i class="fas fa-times"></i></button>
        </div>

        <div class="rm-modal-body">
            <div class="rm-field">
                <label class="rm-label">Full Name <span class="req">*</span></label>
                <input type="text" name="name" id="empName" class="rm-input" placeholder="Enter full name" autocomplete="off" required>
            </div>

            <div class="rm-grid2">
                <div class="rm-field">
                    <label class="rm-label">Labor Type <span class="req">*</span></label>
                    <div class="rm-select-wrap">
                        <select name="labor_type_id" id="empLabor" class="rm-input" required>
                            <option value="">— Select —</option>
                            @foreach($laborTypes as $lt)
                                <option value="{{ $lt->id }}" data-daily="{{ $lt->daily_rate }}">{{ $lt->name }}</option>
                            @endforeach
                        </select>
                        <i class="fas fa-chevron-down rm-select-icon"></i>
                    </div>
                </div>
                <div class="rm-field">
                    <label class="rm-label">Rate / hour</label>
                    <div class="rm-rate-box">
                        <span class="rm-rate-cur">₱</span>
                        <span id="empRateView" class="rm-rate-val">—</span>
                        <input type="hidden" name="rate_per_hour" id="empRate">
                    </div>
                    <p class="rm-hint" id="empRateHint">Auto from labor type</p>
                </div>
            </div>

            <div class="rm-grid2">
                <div class="rm-field">
                    <label class="rm-label">Site</label>
                    <div class="rm-select-wrap">
                        <select name="site_id" id="empSite" class="rm-input">
                            <option value="">— Unassigned —</option>
                            @foreach($sites as $s)
                                <option value="{{ $s->id }}">{{ $s->name }}</option>
                            @endforeach
                        </select>
                        <i class="fas fa-chevron-down rm-select-icon"></i>
                    </div>
                </div>
                <div class="rm-field">
                    <label class="rm-label">Fingerprint ID</label>
                    <input type="text" name="fingerprint_id" id="empFp" class="rm-input rm-mono" placeholder="—" autocomplete="off">
                    <p class="rm-hint">Captured from the kiosk scan.</p>
                </div>
            </div>

            <div class="rm-field">
                <label class="rm-label">Photo <span class="rm-optional">(optional)</span></label>
                <div class="rm-photo-row">
                    <div class="rm-photo-box" id="empPhotoBox">
                        <i class="fas fa-user" id="empPhotoIcon"></i>
                        <img id="empPhotoPreview" src="" alt="" style="display:none;">
                    </div>
                    <div>
                        <button type="button" class="rm-btn-ghost" id="empPhotoPick"><i class="fas fa-images"></i> Choose</button>
                        <button type="button" class="rm-btn-ghost rm-btn-danger-ghost" id="empPhotoClear" style="display:none;"><i class="fas fa-times"></i> Remove</button>
                        <input type="file" name="photo" id="empPhoto" accept="image/jpg,image/jpeg,image/png" hidden>
                    </div>
                </div>
            </div>
        </div>

        <div class="rm-modal-foot">
            <button type="button" class="rm-btn-cancel" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="rm-btn-primary" id="empFormSubmit"><i class="fas fa-check"></i> <span>Save &amp; Activate</span></button>
        </div>
      </form>
    </div>
  </div>
</div>

{{-- ── Styles ──────────────────────────────────────────────────────────────── --}}
<style>
.rm-page { max-width: none; width: 100%; margin: 0; }

.rm-alert { display:flex; gap:10px; align-items:flex-start; padding:12px 16px; border-radius:10px; font-size:13.5px; margin-bottom:18px; border-left:4px solid transparent; }
.rm-alert-ok  { background:#f0fdf4; color:#166534; border-left-color:#16a34a; }
.rm-alert-err { background:#fef2f2; color:#991b1b; border-left-color:#dc2626; }

.rm-header { display:flex; justify-content:space-between; align-items:flex-start; gap:16px; flex-wrap:wrap; margin-bottom:20px; }
.rm-title { font-size:1.5rem; font-weight:700; color:#0f172a; margin:0 0 5px; }
.rm-sub { font-size:.875rem; color:#64748b; margin:0; max-width:640px; }

.rm-btn-primary { height:42px; padding:0 20px; font-size:14px; font-weight:700; color:#fff; border:none; border-radius:9px; cursor:pointer;
    background:linear-gradient(135deg,#1e3a8a,#1e40af); display:inline-flex; align-items:center; gap:8px; box-shadow:0 4px 14px rgba(30,58,138,.22); transition:transform .1s, opacity .15s; white-space:nowrap; }
.rm-btn-primary:hover { opacity:.93; transform:translateY(-1px); }

/* Stat chips */
.rm-stats { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:22px; }
@media(max-width:720px){ .rm-stats{ grid-template-columns:repeat(2,1fr); } }
.rm-stat { text-align:left; background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:16px 18px; cursor:pointer;
    display:flex; flex-direction:column; gap:6px; transition:border-color .15s, box-shadow .15s, transform .1s; }
.rm-stat:hover { transform:translateY(-2px); box-shadow:0 8px 24px rgba(15,23,42,.07); }
.rm-stat.active { border-color:#1e3a8a; box-shadow:0 0 0 3px rgba(30,58,138,.08); }
.rm-stat-num { font-size:1.7rem; font-weight:800; color:#0f172a; line-height:1; }
.rm-stat-lbl { font-size:12.5px; font-weight:600; color:#64748b; display:flex; align-items:center; gap:6px; }
.rm-stat-pending  .rm-stat-num { color:#b45309; }
.rm-stat-active   .rm-stat-num { color:#15803d; }
.rm-stat-archived .rm-stat-num { color:#7c3aed; }
.rm-stat-removed  .rm-stat-num { color:#dc2626; }

/* Tabs */
.rm-tabs { display:flex; gap:6px; border-bottom:1.5px solid #e2e8f0; margin-bottom:18px; overflow-x:auto; }
.rm-tab { background:none; border:none; padding:11px 16px; font-size:14px; font-weight:600; color:#64748b; cursor:pointer;
    border-bottom:2.5px solid transparent; margin-bottom:-1.5px; white-space:nowrap; transition:color .15s; }
.rm-tab:hover { color:#1e293b; }
.rm-tab.active { color:#1e3a8a; border-bottom-color:#1e3a8a; }
.rm-tab-count { font-size:11px; font-weight:700; background:#f1f5f9; color:#475569; border-radius:10px; padding:1px 7px; margin-left:3px; }
.rm-tab.active .rm-tab-count { background:#eff6ff; color:#1e40af; }

.rm-pane { display:none; }
.rm-pane.active { display:block; }

.rm-card { background:#fff; border:1px solid #e2e8f0; border-radius:14px; overflow:hidden; }
.rm-card-note { display:flex; gap:9px; align-items:flex-start; padding:13px 18px; background:#f8fafc; border-bottom:1px solid #eef2f7; font-size:12.5px; color:#475569; }
.rm-card-note i { color:#1e3a8a; margin-top:2px; }

.rm-table { width:100%; border-collapse:collapse; }
.rm-table thead th { background:#f8fafc; padding:11px 16px; font-size:11px; font-weight:700; letter-spacing:.5px; text-transform:uppercase; color:#64748b; border-bottom:1px solid #e2e8f0; white-space:nowrap; }
.rm-table tbody td { padding:13px 16px; border-bottom:1px solid #f1f5f9; vertical-align:middle; font-size:14px; }
.rm-table tbody tr:last-child td { border-bottom:none; }
.rm-table tbody tr:hover td { background:#f8fafc; }

.rm-person { display:flex; align-items:center; gap:12px; }
.rm-avatar { width:42px; height:42px; border-radius:50%; flex-shrink:0; display:flex; align-items:center; justify-content:center;
    font-size:15px; font-weight:700; color:#fff; background:linear-gradient(135deg,#1e3a8a,#3b82f6); overflow:hidden; border:2px solid #e0e7ef; }
.rm-avatar img { width:100%; height:100%; object-fit:cover; }
.rm-person-info { display:flex; flex-direction:column; gap:3px; min-width:0; }
.rm-person-name { font-size:14px; font-weight:600; color:#0f172a; }
.rm-person-name.muted { color:#b45309; font-style:italic; }
.rm-id { font-size:11px; font-weight:600; font-family:monospace; color:#64748b; background:#f1f5f9; border:1px solid #e2e8f0; padding:1px 6px; border-radius:4px; width:fit-content; }

.rm-badge { display:inline-flex; align-items:center; gap:5px; font-size:12px; font-weight:600; padding:4px 9px; border-radius:20px; white-space:nowrap; }
.rm-badge-site  { color:#166534; background:#f0fdf4; border:1px solid #bbf7d0; }
.rm-badge-labor { color:#fff; background:linear-gradient(135deg,#1e3a8a,#3b82f6); }
.rm-badge-fp    { color:#fff; background:#059669; font-family:monospace; }
.rm-badge i { font-size:10px; }
.rm-dash { color:#94a3b8; font-size:13px; }
.rm-muted { color:#64748b; font-size:13px; white-space:nowrap; }
.rm-rate { text-align:center; font-weight:700; color:#374151; }
.rm-pill { display:inline-block; min-width:26px; font-size:12px; font-weight:700; color:#475569; background:#f1f5f9; border:1px solid #e2e8f0; border-radius:20px; padding:2px 8px; }

.rm-actions { text-align:right; white-space:nowrap; }
.rm-actions > * { vertical-align:middle; }
.rm-actions > * + * { margin-left:6px; }
.rm-btn-complete { height:34px; padding:0 14px; font-size:13px; font-weight:700; color:#fff; border:none; border-radius:8px; cursor:pointer;
    background:linear-gradient(135deg,#b45309,#d97706); display:inline-flex; align-items:center; gap:6px; transition:opacity .15s; }
.rm-btn-complete:hover { opacity:.9; }
.rm-btn-ghost { height:34px; padding:0 13px; font-size:13px; font-weight:600; color:#475569; background:#f1f5f9; border:1.5px solid #e2e8f0; border-radius:8px; cursor:pointer; display:inline-flex; align-items:center; gap:6px; transition:background .15s; }
.rm-btn-ghost:hover { background:#e2e8f0; color:#1e293b; }
.rm-btn-danger-ghost { color:#dc2626; }
.rm-btn-accept { height:34px; padding:0 14px; font-size:13px; font-weight:700; color:#fff; border:none; border-radius:8px; cursor:pointer;
    background:linear-gradient(135deg,#15803d,#22c55e); display:inline-flex; align-items:center; gap:6px; transition:opacity .15s; }
.rm-btn-accept:hover { opacity:.9; }
.rm-btn-reject { height:34px; padding:0 13px; font-size:13px; font-weight:700; color:#dc2626; background:#fef2f2; border:1.5px solid #fecaca; border-radius:8px; cursor:pointer; display:inline-flex; align-items:center; gap:6px; transition:background .15s; }
.rm-btn-reject:hover { background:#fee2e2; }

/* kebab menu */
.rm-menu-wrap { position:relative; display:inline-block; }
.rm-menu-btn { width:34px; height:34px; border-radius:8px; border:1.5px solid #e2e8f0; background:#f8fafc; color:#64748b; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:background .12s; }
.rm-menu-btn:hover, .rm-menu-btn.active { background:#eff6ff; border-color:#bfdbfe; color:#1e40af; }
.rm-menu { display:none; position:absolute; right:0; top:calc(100% + 6px); background:#fff; border:1px solid #e2e8f0; border-radius:10px; box-shadow:0 8px 28px rgba(0,0,0,.1); z-index:120; min-width:172px; overflow:hidden; }
.rm-menu.open { display:block; }
.rm-menu-item { display:flex; align-items:center; gap:9px; padding:10px 14px; font-size:13px; font-weight:500; color:#374151; background:none; border:none; width:100%; text-align:left; cursor:pointer; text-decoration:none; transition:background .1s; }
.rm-menu-item:hover { background:#f8fafc; }
.rm-menu-item i { width:14px; text-align:center; font-size:12px; }
.rm-menu-item.danger { color:#dc2626; }
.rm-menu-item.danger:hover { background:#fef2f2; }
.rm-menu-item.ok { color:#15803d; }
.rm-menu-item.ok:hover { background:#f0fdf4; }

/* empty */
.rm-empty td { padding:0 !important; }
.rm-empty-inner { display:flex; flex-direction:column; align-items:center; padding:52px 0; text-align:center; }
.rm-empty-icon { width:60px; height:60px; border-radius:50%; background:#f1f5f9; display:flex; align-items:center; justify-content:center; font-size:22px; color:#94a3b8; margin-bottom:14px; }
.rm-empty-title { font-size:15px; font-weight:600; color:#374151; margin:0 0 5px; }
.rm-empty-sub { font-size:13px; color:#94a3b8; margin:0; max-width:380px; }

/* modal */
.rm-modal { border:none; border-radius:16px; overflow:hidden; }
.rm-modal-head { display:flex; justify-content:space-between; align-items:flex-start; padding:18px 22px; background:linear-gradient(135deg,#1e3a8a,#1e40af); color:#fff; }
.rm-modal-title { font-size:16px; font-weight:700; margin:0 0 2px; }
.rm-modal-sub { font-size:12px; opacity:.82; margin:0; }
.rm-modal-x { background:rgba(255,255,255,.12); border:none; color:#fff; width:30px; height:30px; border-radius:8px; cursor:pointer; }
.rm-modal-body { padding:20px 22px; display:flex; flex-direction:column; gap:14px; }
.rm-grid2 { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
@media(max-width:480px){ .rm-grid2{ grid-template-columns:1fr; } }
.rm-field { display:flex; flex-direction:column; }
.rm-label { font-size:13px; font-weight:600; color:#374151; margin-bottom:6px; }
.req { color:#dc2626; }
.rm-optional { font-weight:400; color:#94a3b8; font-size:12px; }
.rm-input { width:100%; height:42px; border:1.5px solid #e2e8f0; border-radius:8px; padding:0 13px; font-size:14px; color:#0f172a; background:#fff; outline:none; appearance:none; -webkit-appearance:none; transition:border-color .15s, box-shadow .15s; }
.rm-input:focus { border-color:#1e3a8a; box-shadow:0 0 0 3px rgba(30,58,138,.08); }
.rm-mono { font-family:monospace; }
.rm-hint { font-size:12px; color:#94a3b8; margin:5px 0 0; }
.rm-select-wrap { position:relative; }
.rm-select-wrap .rm-input { padding-right:32px; cursor:pointer; }
.rm-select-icon { position:absolute; right:11px; top:50%; transform:translateY(-50%); color:#94a3b8; font-size:11px; pointer-events:none; }
.rm-rate-box { height:42px; border:1.5px solid #e2e8f0; border-radius:8px; display:flex; align-items:center; gap:4px; padding:0 13px; background:#f8fafc; }
.rm-rate-cur { font-size:13px; color:#94a3b8; font-weight:600; }
.rm-rate-val { font-size:15px; font-weight:700; color:#1e3a8a; }
.rm-photo-row { display:flex; align-items:center; gap:14px; }
.rm-photo-box { width:64px; height:64px; border-radius:12px; border:2px dashed #e2e8f0; background:#f8fafc; display:flex; align-items:center; justify-content:center; overflow:hidden; color:#94a3b8; font-size:22px; flex-shrink:0; }
.rm-photo-box img { width:100%; height:100%; object-fit:cover; }
.rm-modal-foot { display:flex; justify-content:flex-end; gap:10px; padding:16px 22px; border-top:1px solid #f1f5f9; }
.rm-btn-cancel { height:42px; padding:0 18px; font-size:14px; font-weight:600; background:#f1f5f9; color:#475569; border:1px solid #e2e8f0; border-radius:9px; cursor:pointer; }
.rm-btn-cancel:hover { background:#e2e8f0; }

/* ── Dark mode ───────────────────────────────────────────────────────────── */
[data-bs-theme="dark"] .rm-title { color:#e8edf5; }
[data-bs-theme="dark"] .rm-sub { color:#94a3b8; }
[data-bs-theme="dark"] .rm-stat { background:#151d2e; border-color:#283449; }
[data-bs-theme="dark"] .rm-stat-num { color:#e8edf5; }
[data-bs-theme="dark"] .rm-stat.active { border-color:#3b82f6; box-shadow:0 0 0 3px rgba(59,130,246,.12); }
[data-bs-theme="dark"] .rm-tabs { border-bottom-color:#283449; }
[data-bs-theme="dark"] .rm-tab { color:#94a3b8; }
[data-bs-theme="dark"] .rm-tab.active { color:#93c5fd; border-bottom-color:#3b82f6; }
[data-bs-theme="dark"] .rm-tab-count { background:#1c2740; color:#9fb0c7; }
[data-bs-theme="dark"] .rm-btn-reject { background:#2a1416; border-color:#5b2426; color:#f87171; }
[data-bs-theme="dark"] .rm-btn-reject:hover { background:#3a1a1d; }
[data-bs-theme="dark"] .rm-card { background:#151d2e; border-color:#283449; }
[data-bs-theme="dark"] .rm-card-note { background:#0f1a2e; border-bottom-color:#1c2740; color:#9fb0c7; }
[data-bs-theme="dark"] .rm-table thead th { background:#1c2740; color:#6b7d96; border-bottom-color:#283449; }
[data-bs-theme="dark"] .rm-table tbody td { border-bottom-color:#1a2336; }
[data-bs-theme="dark"] .rm-table tbody tr:hover td { background:#1a2336; }
[data-bs-theme="dark"] .rm-person-name { color:#e8edf5; }
[data-bs-theme="dark"] .rm-id { background:#1c2740; border-color:#283449; color:#9fb0c7; }
[data-bs-theme="dark"] .rm-muted { color:#9fb0c7; }
[data-bs-theme="dark"] .rm-rate { color:#cdd7e5; }
[data-bs-theme="dark"] .rm-pill { background:#1c2740; border-color:#283449; color:#9fb0c7; }
[data-bs-theme="dark"] .rm-badge-site { color:#86efac; background:#052e16; border-color:#166534; }
[data-bs-theme="dark"] .rm-btn-ghost { background:#1c2740; border-color:#283449; color:#94a3b8; }
[data-bs-theme="dark"] .rm-btn-ghost:hover { background:#283449; color:#e2e8f0; }
[data-bs-theme="dark"] .rm-menu-btn { background:#1c2740; border-color:#283449; color:#94a3b8; }
[data-bs-theme="dark"] .rm-menu-btn:hover, [data-bs-theme="dark"] .rm-menu-btn.active { background:#172554; border-color:#1d4ed8; color:#93c5fd; }
[data-bs-theme="dark"] .rm-menu { background:#1c2740; border-color:#283449; box-shadow:0 8px 24px rgba(0,0,0,.4); }
[data-bs-theme="dark"] .rm-menu-item { color:#cdd7e5; }
[data-bs-theme="dark"] .rm-menu-item:hover { background:#283449; }
[data-bs-theme="dark"] .rm-empty-icon { background:#1c2740; color:#475569; }
[data-bs-theme="dark"] .rm-empty-title { color:#9fb0c7; }
[data-bs-theme="dark"] .rm-modal { background:#151d2e; }
[data-bs-theme="dark"] .rm-label { color:#cbd5e1; }
[data-bs-theme="dark"] .rm-input { background:#0f1a2e; border-color:#283449; color:#e8edf5; }
[data-bs-theme="dark"] .rm-input:focus { border-color:#3b82f6; box-shadow:0 0 0 3px rgba(59,130,246,.12); }
[data-bs-theme="dark"] .rm-rate-box { background:#0f1a2e; border-color:#283449; }
[data-bs-theme="dark"] .rm-rate-val { color:#93c5fd; }
[data-bs-theme="dark"] .rm-photo-box { background:#0f1a2e; border-color:#283449; }
[data-bs-theme="dark"] .rm-modal-foot { border-top-color:#1c2740; }
[data-bs-theme="dark"] .rm-btn-cancel { background:#1c2740; border-color:#283449; color:#94a3b8; }
</style>

{{-- ── Script ──────────────────────────────────────────────────────────────── --}}
<script>
(function () {
    // ── Tabs + stat chips ────────────────────────────────────────────────────
    function switchTab(name) {
        document.querySelectorAll('.rm-tab').forEach(t => t.classList.toggle('active', t.dataset.tab === name));
        document.querySelectorAll('.rm-stat').forEach(s => s.classList.toggle('active', s.dataset.tab === name));
        document.querySelectorAll('.rm-pane').forEach(p => p.classList.toggle('active', p.dataset.pane === name));
        try { history.replaceState(null, '', '#' + name); } catch (e) {}
    }
    document.querySelectorAll('.rm-tab, .rm-stat').forEach(el => el.addEventListener('click', () => switchTab(el.dataset.tab)));
    // Open the tab from the URL hash, defaulting to whichever has items.
    const hash = (location.hash || '').replace('#', '');
    if (['pending','active','archived','removed'].includes(hash)) switchTab(hash);
    else if ({{ $pending->count() }} === 0 && {{ $active->count() }} > 0) switchTab('active');

    // ── Kebab menus ──────────────────────────────────────────────────────────
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.rm-menu-btn');
        document.querySelectorAll('.rm-menu.open').forEach(m => {
            if (!btn || m !== btn.nextElementSibling) { m.classList.remove('open'); m.previousElementSibling.classList.remove('active'); }
        });
        if (btn) {
            e.stopPropagation();
            const menu = btn.nextElementSibling, opening = !menu.classList.contains('open');
            menu.classList.toggle('open', opening); btn.classList.toggle('active', opening);
        }
    });

    // ── Employee form modal (add / complete / edit) ──────────────────────────
    const storeUrl = "{{ route('employees.store') }}";
    const baseUrl  = "{{ url('employees') }}";
    const nextFp   = "{{ $nextFingerprintId }}";

    const modalEl   = document.getElementById('empFormModal');
    const form      = document.getElementById('empForm');
    const methodEl  = document.getElementById('empFormMethod');
    const titleEl   = document.getElementById('empFormTitle');
    const subEl     = document.getElementById('empFormSub');
    const submitLbl = document.querySelector('#empFormSubmit span');
    const nameEl    = document.getElementById('empName');
    const laborEl   = document.getElementById('empLabor');
    const rateEl    = document.getElementById('empRate');
    const rateView  = document.getElementById('empRateView');
    const siteEl    = document.getElementById('empSite');
    const fpEl      = document.getElementById('empFp');
    const photoEl   = document.getElementById('empPhoto');
    const photoIcon = document.getElementById('empPhotoIcon');
    const photoPrev = document.getElementById('empPhotoPreview');
    const photoClr  = document.getElementById('empPhotoClear');

    let bsModal = null;
    function getModal() {
        if (!bsModal && window.bootstrap) bsModal = new bootstrap.Modal(modalEl);
        return bsModal;
    }

    function updateRate() {
        const opt = laborEl.options[laborEl.selectedIndex];
        if (opt && opt.value) {
            const hourly = (parseFloat(opt.dataset.daily || 0) / 8);
            rateView.textContent = hourly.toFixed(2);
            rateEl.value = hourly.toFixed(2);
        } else {
            rateView.textContent = '—'; rateEl.value = '';
        }
    }
    laborEl.addEventListener('change', updateRate);

    function clearPhoto() {
        photoEl.value = ''; photoPrev.src = ''; photoPrev.style.display = 'none';
        photoIcon.style.display = ''; photoClr.style.display = 'none';
    }
    document.getElementById('empPhotoPick').addEventListener('click', () => photoEl.click());
    photoClr.addEventListener('click', clearPhoto);
    photoEl.addEventListener('change', function (e) {
        const f = e.target.files[0]; if (!f) return;
        const r = new FileReader();
        r.onload = ev => { photoPrev.src = ev.target.result; photoPrev.style.display = 'block'; photoIcon.style.display = 'none'; photoClr.style.display = ''; };
        r.readAsDataURL(f);
    });

    const modeField = document.getElementById('empFormModeField');
    const idField   = document.getElementById('empFormIdField');

    function openModal(mode, d) {
        clearPhoto();
        modeField.value = mode;
        idField.value   = d.id || '';
        nameEl.value = d.name || '';
        siteEl.value = d.site || '';
        fpEl.value   = d.fp || (mode === 'add' ? nextFp : '');
        laborEl.value = d.labor || '';
        updateRate();

        if (mode === 'add') {
            form.action = storeUrl; methodEl.value = 'POST';
            titleEl.textContent = 'Add Employee Manually';
            subEl.textContent = 'Register a worker without a kiosk scan.';
            submitLbl.textContent = 'Register';
        } else if (mode === 'complete') {
            form.action = `${baseUrl}/${d.id}/complete`; methodEl.value = 'POST';
            titleEl.textContent = 'Complete Registration';
            subEl.textContent = 'Set this kiosk-detected worker’s details to activate them.';
            submitLbl.textContent = 'Save & Activate';
        } else { // edit
            form.action = `${baseUrl}/${d.id}`; methodEl.value = 'PUT';
            titleEl.textContent = 'Edit Employee';
            subEl.textContent = 'Update this employee’s details.';
            submitLbl.textContent = 'Save Changes';
        }
        const m = getModal(); if (m) m.show();
        setTimeout(() => nameEl.focus(), 250);
    }

    // Delegated so pending rows swapped in by live polling stay clickable.
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.js-emp-edit');
        if (!btn) return;
        openModal(btn.dataset.mode, {
            id: btn.dataset.id, name: btn.dataset.name, labor: btn.dataset.labor,
            rate: btn.dataset.rate, site: btn.dataset.site, fp: btn.dataset.fp,
        });
    });
    document.getElementById('rmAddBtn').addEventListener('click', () => openModal('add', {}));

    form.addEventListener('submit', function () {
        const b = document.getElementById('empFormSubmit');
        b.disabled = true; b.querySelector('i').className = 'fas fa-spinner fa-spin';
    });

    // If validation failed server-side, reopen the form so errors aren't lost.
    @if($errors->any() && old('name'))
        openModal('{{ old('_form_mode', 'complete') }}', {
            id: '{{ old('_form_id') }}', name: @json(old('name')),
            labor: '{{ old('labor_type_id') }}', site: '{{ old('site_id') }}', fp: '{{ old('fingerprint_id') }}'
        });
    @endif

    // ── Realtime: auto-refresh kiosk-detected (pending) workers ──────────────
    // Polls a lightweight feed every few seconds so new fingerprint scans on
    // the Pi kiosk appear here without the admin having to refresh the page.
    (function () {
        const liveUrl = "{{ route('employees.register.live') }}";
        const body    = document.getElementById('rmPendingBody');
        let lastSig     = @json($liveSignature ?? null);
        let prevPending = {{ $pending->count() }};

        function setCount(sel, val) { const el = document.querySelector(sel); if (el) el.textContent = val; }
        function updateCounts(c) {
            ['pending', 'active', 'archived', 'removed'].forEach(k => {
                setCount('.rm-stat-' + k + ' .rm-stat-num', c[k]);
                setCount('.rm-tab[data-tab="' + k + '"] .rm-tab-count', c[k]);
            });
            const badge = document.querySelector('.nav-pending-badge');
            if (badge) {
                badge.textContent = c.pending;
                badge.style.display = c.pending > 0 ? '' : 'none';
            }
        }
        function toast(msg) {
            let t = document.getElementById('rmToast');
            if (!t) {
                t = document.createElement('div');
                t.id = 'rmToast';
                t.style.cssText = 'position:fixed;bottom:26px;left:50%;transform:translateX(-50%) translateY(10px);' +
                    'z-index:9999;background:#2563eb;color:#fff;padding:12px 20px;border-radius:12px;font-weight:600;' +
                    'font-size:13.5px;box-shadow:0 12px 34px rgba(0,0,0,.4);display:flex;align-items:center;gap:10px;' +
                    'opacity:0;transition:opacity .25s ease,transform .25s ease;';
                document.body.appendChild(t);
            }
            t.innerHTML = '<i class="fas fa-fingerprint"></i> ' + msg;
            requestAnimationFrame(() => { t.style.opacity = '1'; t.style.transform = 'translateX(-50%) translateY(0)'; });
            clearTimeout(t._hide);
            t._hide = setTimeout(() => { t.style.opacity = '0'; t.style.transform = 'translateX(-50%) translateY(10px)'; }, 4500);
        }

        async function poll() {
            try {
                const res = await fetch(liveUrl, { headers: { 'Accept': 'application/json' } });
                if (!res.ok) return;
                const d = await res.json();
                if (!d || d.signature === lastSig) return;   // nothing changed
                lastSig = d.signature;
                if (body) body.innerHTML = d.pending_html;
                updateCounts(d.counts);
                if (d.counts.pending > prevPending) {
                    const n = d.counts.pending - prevPending;
                    toast(n + ' new worker' + (n > 1 ? 's' : '') + ' detected from the kiosk');
                }
                prevPending = d.counts.pending;
            } catch (e) { /* offline / transient — try again next tick */ }
        }
        setInterval(poll, 5000);
    })();
})();
</script>
@endsection
