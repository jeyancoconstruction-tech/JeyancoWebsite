@extends('layouts')
@section('page_title', 'Register & Manage Employees')

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@2.47.0/tabler-icons.min.css">
{{-- The Register Employee page's own chrome (.ep-label, .ep-hint, .ep-req,
     .ep-optional, .ep-mono) and the dialog's, included rather than copied so
     the modal and the full form cannot drift apart. In the head, not the
     body: a stylesheet the parser only reaches late paints the page unstyled
     first. --}}
@include('employees._profile_styles')
@include('employees._modal_styles')
<style>
/* Register & Manage. The redesigned parts are named .rmx-*: the older .rm-*
   names are claimed by site-wide rules marked !important (a brand bar down
   the left of every .rm-tab, a grey chip on every .rm-pill) that the design
   cannot sit under. .rm-pane stays — it is the hook the tabs, and the tests
   pinning which tab opens, read. Light is the base palette; dark follows. */
.rmx {
    --rmx-card: #FFFFFF;   --rmx-track: #E9EDF2;  --rmx-tab-on: #FFFFFF;
    --rmx-table: #FFFFFF;  --rmx-thead: #F8F9FB;  --rmx-hover: #F5F7FA;
    --rmx-chip: #F2F4F7;
    --rmx-line: #E4E7EC;   --rmx-line-strong: #D0D5DD; --rmx-bw: 1px;
    --rmx-txt: #101828;    --rmx-txt-2: #475467;  --rmx-txt-3: #667085;
    --rmx-primary: #185FA5;
    --rmx-accent-bg: #EAF2FD; --rmx-accent-fg: #1668DC; --rmx-accent-line: #C5DAF7;
    --rmx-danger-bg: #FEF3F2; --rmx-danger-fg: #B42318; --rmx-danger-line: #FECDCA;
    --rmx-ok-bg: #ECFDF3;     --rmx-ok-fg: #027A48;     --rmx-ok-line: #ABEFC6;
    --rmx-warn-bg: #FFFAEB;   --rmx-warn-fg: #B54708;   --rmx-warn-line: #FEDF89;
    --rmx-green: #22C55E;     --rmx-amber: #F59E0B;     --rmx-slate: #64748B;
    --rmx-green-ico: #16A34A; --rmx-amber-ico: #D97706;
    --rmx-lift: 0 1px 2px rgba(16, 24, 40, .06);
    color: var(--rmx-txt);
}
html[data-bs-theme="dark"] .rmx {
    --rmx-card: #131E2D;   --rmx-track: #131E2D;  --rmx-tab-on: #1E2B3F;
    --rmx-table: #151F2F;  --rmx-thead: #131C2B;  --rmx-hover: #1A2638;
    --rmx-chip: #1B2638;
    --rmx-line: rgba(255, 255, 255, .08); --rmx-line-strong: rgba(255, 255, 255, .16); --rmx-bw: .5px;
    --rmx-txt: #E7ECF3;    --rmx-txt-2: #9CA9BD;  --rmx-txt-3: #78879E;
    --rmx-accent-bg: rgba(59, 130, 246, .16); --rmx-accent-fg: #6FAEFF; --rmx-accent-line: rgba(79, 151, 245, .45);
    --rmx-danger-bg: rgba(239, 68, 68, .14);  --rmx-danger-fg: #F87171; --rmx-danger-line: rgba(239, 68, 68, .45);
    --rmx-ok-bg: rgba(34, 197, 94, .14);      --rmx-ok-fg: #4ADE80;     --rmx-ok-line: rgba(34, 197, 94, .35);
    --rmx-warn-bg: rgba(245, 158, 11, .14);   --rmx-warn-fg: #FBBF24;   --rmx-warn-line: rgba(245, 158, 11, .4);
    --rmx-green-ico: #4ADE80; --rmx-amber-ico: #FBBF24;
    --rmx-lift: none;
}
.rmx *, .rmx *::before, .rmx *::after { box-sizing: border-box; }

/* Validation errors the modal could not show */
.rm-alert { display: flex; gap: 10px; align-items: flex-start; padding: 12px 16px; border-radius: 10px; font-size: 13.5px; margin-bottom: 18px; border-left: 4px solid transparent; }
.rm-alert-err { background: var(--rmx-danger-bg); color: var(--rmx-danger-fg); border-left-color: #DC2626; }

/* Header */
.rmx-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 1.5rem; flex-wrap: wrap; }
.rmx .rmx-title { font-size: 22px !important; font-weight: 600 !important; letter-spacing: -.01em !important; line-height: 1.3; color: var(--rmx-txt); margin: 0 0 5px; }
.rmx-sub { font-size: 13px; color: var(--rmx-txt-2); margin: 0; }
.rmx-primary { height: 38px; padding: 0 16px; display: inline-flex; align-items: center; gap: 6px; flex-shrink: 0;
    background: var(--rmx-primary); color: #fff; border: none; border-radius: 8px; font-size: 13.5px; font-weight: 500;
    text-decoration: none; transition: filter .15s; }
.rmx-primary:hover, .rmx-primary:focus { color: #fff; text-decoration: none; filter: brightness(1.1); }
.rmx-primary i { font-size: 16px; }

/* Stat cards — they also switch tabs */
.rmx-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 1.25rem; }
@media (max-width: 720px) { .rmx-stats { grid-template-columns: 1fr; } }
.rmx-stat { appearance: none; margin: 0; font: inherit; color: inherit; text-align: left; width: 100%; cursor: pointer;
    background: var(--rmx-card); border: 0; border-left: 3px solid var(--rmx-slate); border-radius: 0 12px 12px 0;
    padding: 1rem 1.25rem; box-shadow: var(--rmx-lift); transition: background .15s; }
.rmx-stat:hover { background: var(--rmx-hover); }
.rmx-stat-active  { border-left-color: var(--rmx-green); }
.rmx-stat-pending { border-left-color: var(--rmx-amber); }
.rmx-stat-num { display: block; font-size: 28px; font-weight: 600; color: var(--rmx-txt); line-height: 1; font-variant-numeric: tabular-nums; }
.rmx-stat-lbl { display: flex; align-items: center; gap: 6px; margin-top: 8px; font-size: 12px; color: var(--rmx-txt-2); text-transform: uppercase; letter-spacing: .5px; }
.rmx-stat-lbl i { font-size: 15px; color: var(--rmx-txt-3); }
.rmx-stat-active  .rmx-stat-lbl i { color: var(--rmx-green-ico); }
.rmx-stat-pending .rmx-stat-lbl i { color: var(--rmx-amber-ico); }

/* Tabs and the one Select toggle */
.rmx-bar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem; flex-wrap: wrap; gap: 10px; }
.rmx-tabs { display: inline-flex; background: var(--rmx-track); border-radius: 10px; padding: 3px; gap: 2px; }
.rmx-tab { appearance: none; border: none; background: none; color: var(--rmx-txt-2); font: inherit; font-size: 13px;
    padding: 7px 14px; border-radius: 7px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;
    transition: background .15s, color .15s; }
.rmx-tab:hover { color: var(--rmx-txt); }
.rmx-tab.is-open { background: var(--rmx-tab-on); color: var(--rmx-txt); font-weight: 500; box-shadow: var(--rmx-lift); }
.rmx-count { background: var(--rmx-chip); color: var(--rmx-txt-3); font-size: 11px; padding: 1px 7px; border-radius: 10px; font-variant-numeric: tabular-nums; }
.rmx-tab.is-open .rmx-count { background: var(--rmx-accent-bg); color: var(--rmx-accent-fg); }
.rmx-outline { height: 34px; padding: 0 13px; display: inline-flex; align-items: center; gap: 6px; background: transparent;
    color: var(--rmx-txt); border: var(--rmx-bw) solid var(--rmx-line-strong); border-radius: 8px; font: inherit;
    font-size: 13px; cursor: pointer; transition: background .15s, border-color .15s; }
.rmx-outline i { font-size: 15px; }
.rmx-outline:hover { background: var(--rmx-hover); }
.rmx-outline.is-on { background: var(--rmx-primary); border-color: var(--rmx-primary); color: #fff; }
.rmx-outline:disabled { opacity: .5; cursor: not-allowed; }

/* Panes */
.rm-pane { display: none; }
.rm-pane.active { display: block; }

/* Table card */
.rmx-card { background: var(--rmx-table); border: var(--rmx-bw) solid var(--rmx-line); border-radius: 12px; overflow: hidden; }
.rmx-note { display: flex; gap: 8px; align-items: flex-start; padding: 10px 14px; font-size: 12.5px; color: var(--rmx-txt-2);
    background: var(--rmx-thead); border-bottom: var(--rmx-bw) solid var(--rmx-line); }
.rmx-note i { color: var(--rmx-accent-fg); font-size: 15px; margin-top: 1px; flex: none; }
/* One flex item for the sentence, or each text node becomes its own column. */
.rmx-note > span { flex: 1; min-width: 0; line-height: 1.6; }
.rmx-table-wrap { overflow-x: auto; }
.rmx-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.rmx-table thead tr { background: var(--rmx-thead); }
.rmx-table th { text-align: left; padding: 11px 10px; font-size: 10.5px; font-weight: 500; color: var(--rmx-txt-3);
    text-transform: uppercase; letter-spacing: .5px; white-space: nowrap; }
.rmx-table td { padding: 12px 10px; border-top: var(--rmx-bw) solid var(--rmx-line); vertical-align: middle; color: var(--rmx-txt-2); }
.rmx-table th:first-child, .rmx-table td:first-child { padding-left: 14px; }
.rmx-table th:last-child,  .rmx-table td:last-child  { padding-right: 14px; }
.rmx-table tbody tr { transition: background .12s; }
.rmx-table tbody tr:hover { background: var(--rmx-hover); }
.rmx-table .rmx-num { text-align: right; }
.rmx-table .rmx-center { text-align: center; }

/* Selection: the checkbox column exists only while its pane is selecting. */
.rmx-check-col { display: none; width: 38px; text-align: center; }
.rm-pane.selecting .rmx-check-col { display: table-cell; }
.rmx-check, .rmx-check-all { width: 16px; height: 16px; cursor: pointer; accent-color: var(--rmx-primary); vertical-align: middle; }
.rmx-check-all:disabled { cursor: not-allowed; opacity: .4; }
.rmx-bulk { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; padding: 10px 14px;
    background: var(--rmx-accent-bg); border-bottom: var(--rmx-bw) solid var(--rmx-line); }
.rmx-bulk[hidden] { display: none; }
.rmx-bulk-count { font-size: 13px; font-weight: 500; color: var(--rmx-txt); }
.rmx-bulk-count strong { font-size: 14px; }
.rmx-bulk-spacer { flex: 1 1 auto; }
.rmx-bulk-btn { height: 30px; padding: 0 12px; border-radius: 7px; font: inherit; font-size: 12.5px; font-weight: 500; cursor: pointer;
    display: inline-flex; align-items: center; gap: 6px; background: transparent; color: var(--rmx-txt);
    border: var(--rmx-bw) solid var(--rmx-line-strong); transition: filter .15s; }
.rmx-bulk-btn:hover { filter: brightness(1.08); }
.rmx-bulk-btn.danger { background: #DC2626; border-color: #DC2626; color: #fff; }
.rmx-bulk-btn:disabled { opacity: .55; cursor: not-allowed; }

/* A worker */
.rmx-person { display: flex; align-items: center; gap: 10px; min-width: 0; }
.rmx-avatar { width: 34px; height: 34px; border-radius: 50%; display: flex; align-items: center; justify-content: center;
    font-size: 13px; font-weight: 600; flex-shrink: 0; overflow: hidden; }
.rmx-avatar img { width: 100%; height: 100%; object-fit: cover; }
.rmx-who { min-width: 0; }
.rmx-name { font-size: 13.5px; font-weight: 500; color: var(--rmx-txt); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 260px; }
.rmx-name.is-muted { color: var(--rmx-warn-fg); font-style: italic; }
.rmx-id { font-size: 11px; color: var(--rmx-txt-3); font-variant-numeric: tabular-nums; margin-top: 1px; }
.rmx-tags { display: flex; gap: 6px; flex-wrap: wrap; margin: 5px 0 0 44px; }

/* Cells */
.rmx-pill { display: inline-flex; align-items: center; gap: 4px; font-size: 12px; padding: 3px 9px; border-radius: 20px; white-space: nowrap;
    background: var(--rmx-chip); color: var(--rmx-txt-2); border: var(--rmx-bw) solid var(--rmx-line); }
.rmx-pill i { font-size: 12px; }
.rmx-pill-ok     { background: var(--rmx-ok-bg);     color: var(--rmx-ok-fg);     border-color: transparent; }
.rmx-pill-warn   { background: var(--rmx-warn-bg);   color: var(--rmx-warn-fg);   border-color: transparent; }
.rmx-pill-accent { background: var(--rmx-accent-bg); color: var(--rmx-accent-fg); border-color: transparent; }
.rmx-labor { display: inline-flex; align-items: center; gap: 5px; color: var(--rmx-txt-2); font-size: 13px; white-space: nowrap; }
.rmx-labor i { font-size: 14px; color: var(--rmx-txt-3); }
.rmx-labor + .rmx-pill { margin-left: 6px; }
.rmx-rate { text-align: right; font-weight: 500; color: var(--rmx-txt); font-variant-numeric: tabular-nums; white-space: nowrap; }
.rmx-logs { display: inline-flex; align-items: center; justify-content: center; min-width: 22px; height: 22px; padding: 0 6px;
    border-radius: 11px; background: var(--rmx-chip); color: var(--rmx-txt-2); font-size: 12px; font-variant-numeric: tabular-nums; }
.rmx-dash { color: var(--rmx-txt-3); }
.rmx-muted { color: var(--rmx-txt-3); font-size: 12.5px; white-space: nowrap; }

/* Row actions */
.rmx-actions { text-align: right; white-space: nowrap; }
.rmx-actions-inner { display: inline-flex; align-items: center; gap: 6px; justify-content: flex-end; }
.rmx-actions form { display: inline; margin: 0; }
.rmx-icon-btn, .rmx-text-btn { border-radius: 7px; display: inline-flex; align-items: center; justify-content: center; cursor: pointer;
    text-decoration: none; font: inherit; transition: filter .15s; }
.rmx-icon-btn { width: 32px; height: 32px; padding: 0; font-size: 15px; }
.rmx-text-btn { height: 32px; padding: 0 12px; gap: 6px; font-size: 12.5px; font-weight: 500; }
.rmx-icon-btn i, .rmx-text-btn i { font-size: 15px; }
.rmx-icon-btn:hover, .rmx-text-btn:hover { filter: brightness(1.15); text-decoration: none; }
.rmx-edit, .rmx-edit:hover       { background: var(--rmx-accent-bg); color: var(--rmx-accent-fg); border: var(--rmx-bw) solid var(--rmx-accent-line); }
.rmx-del, .rmx-del:hover         { background: var(--rmx-danger-bg); color: var(--rmx-danger-fg); border: var(--rmx-bw) solid var(--rmx-danger-line); }
.rmx-restore, .rmx-restore:hover { background: var(--rmx-ok-bg);     color: var(--rmx-ok-fg);     border: var(--rmx-bw) solid var(--rmx-ok-line); }

/* Empty tab */
.rmx-empty td { padding: 0 !important; }
.rmx-empty-inner { display: flex; flex-direction: column; align-items: center; padding: 48px 16px; text-align: center; }
.rmx-empty-icon { width: 56px; height: 56px; border-radius: 50%; background: var(--rmx-chip); display: flex; align-items: center;
    justify-content: center; font-size: 24px; color: var(--rmx-txt-3); margin-bottom: 12px; }
.rmx-empty-title { font-size: 14.5px; font-weight: 500; color: var(--rmx-txt); margin: 0 0 4px; }
.rmx-empty-sub { font-size: 12.5px; color: var(--rmx-txt-3); margin: 0; max-width: 380px; }
</style>
@endpush

@section('content')
@php
    // Which tab opens, decided here rather than after paint. ?tab=pending is
    // where saving a worker lands; with nothing asked for, Active leads —
    // unless there is nobody active and somebody pending, because a fresh
    // system should not open on an empty table.
    $openTab = in_array(request('tab'), ['active', 'pending', 'removed'], true)
        ? request('tab')
        : (($active->count() === 0 && $pending->count() > 0) ? 'pending' : 'active');

    $tabs = [
        'active'  => ['label' => __('Active'),  'count' => $active->count()],
        'pending' => ['label' => __('Pending'), 'count' => $pending->count()],
        'removed' => ['label' => __('Removed'), 'count' => $removed->count()],
    ];
@endphp
<div class="rm-page rmx">

    {{-- session('success') is a toast. Errors that belong to the modal are
         shown in it as well; this is for anything that arrives without it. --}}
    @if($errors->any())
    <div class="rm-alert rm-alert-err">
        <i class="fas fa-exclamation-circle"></i>
        <div><strong>{{ __('Please fix the following:') }}</strong>
            <ul class="mb-0 mt-1">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    </div>
    @endif

    {{-- ── Header ──────────────────────────────────────────────────────────── --}}
    <div class="rmx-head">
        <div>
            <h1 class="rmx-title">{{ __('Register & manage employees') }}</h1>
            <p class="rmx-sub">{{ __('New workers and kiosk detections stay in Pending until a fingerprint is enrolled.') }}</p>
        </div>
        {{-- The full registration form, not the compact modal: a complete
             worker profile does not fit in a dialog. The modal stays for
             confirming and completing kiosk detections. --}}
        <a href="{{ route('employees.create') }}" class="rmx-primary" id="rmAddBtn">
            <i class="ti ti-user-plus" aria-hidden="true"></i>{{ __('Register employee') }}
        </a>
    </div>

    {{-- ── Stat cards (also switch tabs) ───────────────────────────────────── --}}
    <div class="rmx-stats">
        <button type="button" class="rmx-stat rmx-stat-active" data-tab="active">
            <span class="rmx-stat-num">{{ $active->count() }}</span>
            <span class="rmx-stat-lbl"><i class="ti ti-user-check" aria-hidden="true"></i>{{ __('Active') }}</span>
        </button>
        <button type="button" class="rmx-stat rmx-stat-pending" data-tab="pending">
            <span class="rmx-stat-num">{{ $pending->count() }}</span>
            <span class="rmx-stat-lbl"><i class="ti ti-fingerprint" aria-hidden="true"></i>{{ __('Pending from kiosk') }}</span>
        </button>
        <button type="button" class="rmx-stat rmx-stat-removed" data-tab="removed">
            <span class="rmx-stat-num">{{ $removed->count() }}</span>
            <span class="rmx-stat-lbl"><i class="ti ti-user-off" aria-hidden="true"></i>{{ __('Removed') }}</span>
        </button>
    </div>

    {{-- ── Tabs, and the one Select toggle for whichever tab is open ────────── --}}
    <div class="rmx-bar">
        <div class="rmx-tabs" role="tablist">
            @foreach($tabs as $key => $t)
                <button type="button" class="rmx-tab {{ $openTab === $key ? 'is-open' : '' }}" data-tab="{{ $key }}"
                        role="tab" aria-selected="{{ $openTab === $key ? 'true' : 'false' }}">
                    {{ $t['label'] }} <span class="rmx-count">{{ $t['count'] }}</span>
                </button>
            @endforeach
        </div>
        {{-- Bulk removal is destructive, so it is something you opt into: the
             checkbox column stays hidden until Select is pressed, and pressing
             it again (it reads Done) puts it away. --}}
        <button type="button" class="rmx-outline" id="rmxSelect">
            <i class="ti ti-list-check" aria-hidden="true"></i><span class="js-select-label">{{ __('Select') }}</span>
        </button>
    </div>

    {{-- ═══ ACTIVE ═════════════════════════════════════════════════════════ --}}
    <div class="rm-pane {{ $openTab === 'active' ? 'active' : '' }}" data-pane="active" role="tabpanel">
        <div class="rmx-card">
            <div class="rmx-bulk" hidden>
                <span class="rmx-bulk-count"><strong>0</strong> {{ __('selected') }}</span>
                <button type="button" class="rmx-bulk-btn js-bulk-clear">{{ __('Clear selection') }}</button>
                <span class="rmx-bulk-spacer"></span>
                <button type="button" class="rmx-bulk-btn danger js-bulk-remove"><i class="ti ti-trash" aria-hidden="true"></i>{{ __('Remove selected') }}</button>
            </div>
            <div class="rmx-table-wrap">
                <table class="rmx-table">
                    <thead>
                        <tr>
                            <th class="rmx-check-col"><input type="checkbox" class="rmx-check-all" aria-label="{{ __('Select all') }}"></th>
                            <th>{{ __('Employee') }}</th>
                            <th>{{ __('Site') }}</th>
                            <th>{{ __('Labor type') }}</th>
                            <th class="rmx-num">{{ __('Rate / hr') }}</th>
                            <th class="rmx-center" title="{{ __('Fingerprint slot on the kiosk') }}">{{ __('FP') }}</th>
                            <th class="rmx-center">{{ __('Logs') }}</th>
                            <th class="rmx-num">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($active as $e)
                        <tr>
                            <td class="rmx-check-col"><input type="checkbox" class="rmx-check" value="{{ $e->id }}" aria-label="Select {{ $e->name }}"></td>
                            <td>@include('employees._person', ['e' => $e, 'displayName' => $e->name])</td>
                            <td>@include('employees._site', ['e' => $e])</td>
                            <td>@include('employees._labor', ['e' => $e])</td>
                            <td class="rmx-rate">₱{{ number_format($e->rate_per_hour, 2) }}</td>
                            <td class="rmx-center">@include('employees._fp', ['e' => $e])</td>
                            <td class="rmx-center"><span class="rmx-logs">{{ $e->attendances_count }}</span></td>
                            <td class="rmx-actions">
                                <div class="rmx-actions-inner">
                                    {{-- Edit opens the full Register Employee form, not
                                         the quick modal: a five-field dialog could
                                         correct a record without ever showing the
                                         twenty other fields on it. --}}
                                    <a href="{{ route('employees.edit', $e->id) }}" class="rmx-icon-btn rmx-edit"
                                       title="{{ __('Edit') }}" aria-label="{{ __('Edit') }} {{ $e->name }}">
                                        <i class="ti ti-pencil" aria-hidden="true"></i>
                                    </a>
                                    <form action="{{ route('employees.destroy', $e->id) }}" method="POST"
                                          data-confirm="{{ __('The records of :name are preserved and can be restored from the Removed tab.', ['name' => $e->name]) }}"
                                          data-confirm-title="{{ __('Remove this worker?') }}"
                                          data-confirm-label="{{ __('Remove') }}"
                                          data-confirm-tone="warning">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="rmx-icon-btn rmx-del"
                                                title="{{ __('Remove') }}" aria-label="{{ __('Remove') }} {{ $e->name }}">
                                            <i class="ti ti-trash" aria-hidden="true"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        @include('employees._empty', ['icon' => 'users', 'title' => 'No active employees', 'sub' => 'Complete a pending detection, or use Register employee to get started.'])
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ═══ PENDING ════════════════════════════════════════════════════════ --}}
    <div class="rm-pane {{ $openTab === 'pending' ? 'active' : '' }}" data-pane="pending" role="tabpanel">
        <div class="rmx-card">
            <div class="rmx-note">
                <i class="ti ti-info-circle" aria-hidden="true"></i>
                <span>{{ __('Please scan your fingerprint on the kiosk.') }}</span>
            </div>
            <div class="rmx-bulk" hidden>
                <span class="rmx-bulk-count"><strong>0</strong> {{ __('selected') }}</span>
                <button type="button" class="rmx-bulk-btn js-bulk-clear">{{ __('Clear selection') }}</button>
                <span class="rmx-bulk-spacer"></span>
                <button type="button" class="rmx-bulk-btn danger js-bulk-remove"><i class="ti ti-x" aria-hidden="true"></i>{{ __('Cancel selected') }}</button>
            </div>
            <div class="rmx-table-wrap">
                <table class="rmx-table">
                    <thead>
                        <tr>
                            <th class="rmx-check-col"><input type="checkbox" class="rmx-check-all" aria-label="{{ __('Select all') }}"></th>
                            <th>{{ __('Worker') }}</th>
                            <th class="rmx-center" title="{{ __('Fingerprint slot on the kiosk') }}">{{ __('FP') }}</th>
                            <th>{{ __('Site') }}</th>
                            <th>{{ __('First seen') }}</th>
                            <th class="rmx-center">{{ __('Logs') }}</th>
                            <th class="rmx-num">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody id="rmPendingBody">
                        @include('employees._rows_pending', ['pending' => $pending])
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ═══ REMOVED ════════════════════════════════════════════════════════ --}}
    <div class="rm-pane {{ $openTab === 'removed' ? 'active' : '' }}" data-pane="removed" role="tabpanel">
        <div class="rmx-card">
            <div class="rmx-note">
                <i class="ti ti-info-circle" aria-hidden="true"></i>
                <span>{{ __('Removed records are hidden everywhere but never lost. Restore them, or permanently delete as a last resort.') }}</span>
            </div>
            <div class="rmx-bulk" hidden>
                <span class="rmx-bulk-count"><strong>0</strong> {{ __('selected') }}</span>
                <button type="button" class="rmx-bulk-btn js-bulk-clear">{{ __('Clear selection') }}</button>
                <span class="rmx-bulk-spacer"></span>
                <button type="button" class="rmx-bulk-btn js-bulk-restore"><i class="ti ti-restore" aria-hidden="true"></i>{{ __('Restore selected') }}</button>
                <button type="button" class="rmx-bulk-btn danger js-bulk-purge"><i class="ti ti-trash-x" aria-hidden="true"></i>{{ __('Delete permanently') }}</button>
            </div>
            <div class="rmx-table-wrap">
                <table class="rmx-table">
                    <thead>
                        <tr>
                            <th class="rmx-check-col"><input type="checkbox" class="rmx-check-all" aria-label="{{ __('Select all') }}"></th>
                            <th>{{ __('Employee') }}</th>
                            <th>{{ __('Site') }}</th>
                            <th>{{ __('Labor type') }}</th>
                            <th>{{ __('Removed') }}</th>
                            <th class="rmx-center">{{ __('Logs') }}</th>
                            <th class="rmx-num">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($removed as $e)
                        <tr>
                            <td class="rmx-check-col"><input type="checkbox" class="rmx-check" value="{{ $e->id }}" aria-label="Select {{ $e->name }}"></td>
                            <td>@include('employees._person', ['e' => $e, 'displayName' => $e->name])</td>
                            <td>@include('employees._site', ['e' => $e])</td>
                            <td>@include('employees._labor', ['e' => $e])</td>
                            <td class="rmx-muted">{{ $e->deleted_at?->format('M d, Y') ?? '—' }}</td>
                            <td class="rmx-center"><span class="rmx-logs">{{ $e->attendances_count }}</span></td>
                            <td class="rmx-actions">
                                <div class="rmx-actions-inner">
                                    <form action="{{ route('employees.restore', $e->id) }}" method="POST">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="rmx-icon-btn rmx-restore"
                                                title="{{ __('Restore') }}" aria-label="{{ __('Restore') }} {{ $e->name }}">
                                            <i class="ti ti-restore" aria-hidden="true"></i>
                                        </button>
                                    </form>
                                    <form action="{{ route('employees.force-delete', $e->id) }}" method="POST"
                                          data-confirm="{{ __('This deletes :name and every attendance record they have. It cannot be undone.', ['name' => $e->name]) }}"
                                          data-confirm-title="{{ __('Delete permanently?') }}"
                                          data-confirm-label="{{ __('Delete permanently') }}"
                                          data-confirm-tone="danger">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="rmx-icon-btn rmx-del"
                                                title="{{ __('Delete permanently') }}" aria-label="{{ __('Delete permanently') }} {{ $e->name }}">
                                            <i class="ti ti-trash-x" aria-hidden="true"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        @include('employees._empty', ['icon' => 'user-off', 'title' => 'Nothing removed', 'sub' => 'Removed records can be restored from here.'])
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- ════════════════════════════ EMPLOYEE FORM MODAL ═══════════════════════
     One modal serves four jobs — Add, Confirm, Complete and Edit — with the
     title, the sub-line and the submit label swapped by openModal(). It
     speaks the same language as the Register Employee page: the .ep-* chrome
     from employees/_profile_styles.blade.php and Bootstrap form controls the
     design tokens already theme for both modes. --}}
<div class="modal fade" id="empFormModal" tabindex="-1" aria-hidden="true" aria-labelledby="empFormTitle">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable emp-dialog">
    <div class="modal-content emp-modal">
      <form id="empForm" method="POST" enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="_method" id="empFormMethod" value="POST">
        <input type="hidden" name="_form_mode" id="empFormModeField" value="">
        <input type="hidden" name="_form_id" id="empFormIdField" value="">

        <div class="emp-head">
            <span class="emp-head-icon" aria-hidden="true"><i class="fas fa-helmet-safety"></i></span>
            <div class="emp-head-text">
                <h6 class="emp-head-title" id="empFormTitle">{{ __('Complete Registration') }}</h6>
                <p class="emp-head-sub" id="empFormSub">{{ __('Set this worker\'s details to activate them.') }}</p>
            </div>
            <button type="button" class="emp-head-x" data-bs-dismiss="modal" aria-label="{{ __('Close') }}">
                <i class="fas fa-times" aria-hidden="true"></i>
            </button>
        </div>

        <div class="modal-body emp-body">

            {{-- The page-level alert sits behind the modal, so when validation
                 sent the admin back here the form reopened saying nothing at
                 all about what was wrong. Same errors, shown where they can be
                 read and beside the field that raised them. --}}
            @if($errors->any())
                <div class="emp-alert" role="alert">
                    <i class="fas fa-circle-exclamation" aria-hidden="true"></i>
                    <div>
                        <strong>{{ __('Please fix the following:') }}</strong>
                        <ul>@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                    </div>
                </div>
            @endif

            <section class="ep-section emp-section">
                <header class="ep-section-head">
                    <span class="ep-section-icon" aria-hidden="true"><i class="fas fa-user"></i></span>
                    <div>
                        <h3 class="ep-section-title">{{ __('Worker') }}</h3>
                        <p class="ep-section-sub">{{ __('Who this record is for.') }}</p>
                    </div>
                </header>
                {{-- Three fields, the same three Register Employee asks for. The
                     controller composes `name` from these, so a correction made
                     here and the same one made on the full form end at the same
                     value. Middle Name carries no asterisk: the full form
                     requires it because it posts profile_form, and this modal
                     does not. --}}
                <div class="emp-grid emp-grid-3">
                    <div class="emp-field">
                        <label class="ep-label" for="empFirst">{{ __('First Name') }} <span class="ep-req" aria-hidden="true">*</span></label>
                        <input type="text" name="first_name" id="empFirst"
                               class="form-control @error('first_name') is-invalid @enderror"
                               placeholder="{{ __('Juan') }}"
                               autocomplete="off" required aria-required="true">
                        @error('first_name')
                            <p class="emp-err" role="alert"><i class="fas fa-circle-exclamation" aria-hidden="true"></i>{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="emp-field">
                        <label class="ep-label" for="empMiddle">{{ __('Middle Name') }}</label>
                        <input type="text" name="middle_name" id="empMiddle"
                               class="form-control @error('middle_name') is-invalid @enderror"
                               placeholder="{{ __('Santos') }}" autocomplete="off">
                        @error('middle_name')
                            <p class="emp-err" role="alert"><i class="fas fa-circle-exclamation" aria-hidden="true"></i>{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="emp-field">
                        <label class="ep-label" for="empLast">{{ __('Last Name') }} <span class="ep-req" aria-hidden="true">*</span></label>
                        <input type="text" name="last_name" id="empLast"
                               class="form-control @error('last_name') is-invalid @enderror"
                               placeholder="{{ __('Dela Cruz') }}"
                               autocomplete="off" required aria-required="true">
                        @error('last_name')
                            <p class="emp-err" role="alert"><i class="fas fa-circle-exclamation" aria-hidden="true"></i>{{ $message }}</p>
                        @enderror
                    </div>
                </div>
                @error('name')
                    <p class="emp-err" role="alert"><i class="fas fa-circle-exclamation" aria-hidden="true"></i>{{ $message }}</p>
                @else
                    <span class="ep-hint" id="empNameHint">{{ __('As it should read on the payslip.') }}</span>
                @enderror
            </section>

            <section class="ep-section emp-section">
                <header class="ep-section-head">
                    <span class="ep-section-icon" aria-hidden="true"><i class="fas fa-helmet-safety"></i></span>
                    <div>
                        <h3 class="ep-section-title">{{ __('Employment & Pay') }}</h3>
                        <p class="ep-section-sub">{{ __('What the worker is paid and where they are assigned.') }}</p>
                    </div>
                </header>
                <div class="emp-grid">
                    <div class="emp-field">
                        <label class="ep-label" for="empLabor">{{ __('Labor Type') }} <span class="ep-req" aria-hidden="true">*</span></label>
                        <select name="labor_type_id" id="empLabor"
                                class="form-select @error('labor_type_id') is-invalid @enderror"
                                required aria-required="true">
                            <option value="">{{ __('— Select —') }}</option>
                            @foreach($laborTypes as $lt)
                                <option value="{{ $lt->id }}" data-daily="{{ $lt->daily_rate }}">{{ $lt->name }}</option>
                            @endforeach
                        </select>
                        @error('labor_type_id')
                            <p class="emp-err" role="alert"><i class="fas fa-circle-exclamation" aria-hidden="true"></i>{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="emp-field">
                        <label class="ep-label" for="empRateView">{{ __('Rate / hour') }}</label>
                        {{-- Filled from the labor type, never typed. --}}
                        <div class="emp-rate" id="empRateBox" aria-live="polite">
                            <span class="emp-rate-cur" aria-hidden="true">₱</span>
                            <span id="empRateView" class="emp-rate-val" tabindex="-1">—</span>
                            <i class="fas fa-lock emp-rate-lock" aria-hidden="true" title="{{ __('Set by the labor type') }}"></i>
                            <input type="hidden" name="rate_per_hour" id="empRate">
                        </div>
                        <span class="ep-hint" id="empRateHint">{{ __('Auto from labor type') }}</span>
                    </div>

                    <div class="emp-field">
                        <label class="ep-label" for="empSite">{{ __('Site') }}</label>
                        <select name="site_id" id="empSite" class="form-select @error('site_id') is-invalid @enderror">
                            <option value="">{{ __('— Unassigned —') }}</option>
                            @foreach($sites as $s)
                                <option value="{{ $s->id }}">{{ $s->name }}</option>
                            @endforeach
                        </select>
                        @error('site_id')
                            <p class="emp-err" role="alert"><i class="fas fa-circle-exclamation" aria-hidden="true"></i>{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="emp-field">
                        <label class="ep-label" for="empShift">{{ __('Shift') }}</label>
                        <select name="shift_id" id="empShift" class="form-select @error('shift_id') is-invalid @enderror"
                                aria-describedby="empShiftHint">
                            @foreach($shifts as $sh)
                                <option value="{{ $sh->id }}" @selected(! $sh->crosses_midnight)>
                                    {{ $sh->name }} — {{ \Carbon\Carbon::parse($sh->starts_at)->format('g:i A') }}
                                </option>
                            @endforeach
                        </select>
                        @error('shift_id')
                            <p class="emp-err" role="alert"><i class="fas fa-circle-exclamation" aria-hidden="true"></i>{{ $message }}</p>
                        @else
                            <span class="ep-hint" id="empShiftHint">{{ __('Movable later from the employee list.') }}</span>
                        @enderror
                    </div>
                </div>
            </section>

            <section class="ep-section emp-section">
                <header class="ep-section-head">
                    <span class="ep-section-icon" aria-hidden="true"><i class="fas fa-fingerprint"></i></span>
                    <div>
                        <h3 class="ep-section-title">{{ __('Kiosk') }}</h3>
                        <p class="ep-section-sub">{{ __('The slot their finger is stored in on the kiosk.') }}</p>
                    </div>
                </header>

                <div class="emp-field">
                    <label class="ep-label" for="empFp">
                        {{ __('Fingerprint ID') }}
                        <span class="ep-optional">{{ __('(from the kiosk)') }}</span>
                    </label>
                    <div class="emp-fp">
                        <span class="emp-fp-icon" aria-hidden="true"><i class="fas fa-fingerprint"></i></span>
                        <input type="text" name="fingerprint_id" id="empFp"
                               class="form-control emp-fp-input ep-mono @error('fingerprint_id') is-invalid @enderror"
                               placeholder="{{ __('Not enrolled yet') }}" autocomplete="off"
                               inputmode="numeric" aria-describedby="empFpHint">
                    </div>
                    @error('fingerprint_id')
                        <p class="emp-err" role="alert"><i class="fas fa-circle-exclamation" aria-hidden="true"></i>{{ $message }}</p>
                    @else
                        <span class="ep-hint" id="empFpHint">{{ __('The slot this worker\'s finger is stored in on the kiosk. Filled in by the scan — change it only if the kiosk was re-enrolled.') }}</span>
                    @enderror
                </div>

                {{-- No photo field — see the note in employees/create.blade.php. --}}
            </section>
        </div>

        <div class="emp-foot">
            <p class="emp-foot-note"><span class="ep-req" aria-hidden="true">*</span> {{ __('Required') }}</p>
            <button type="button" class="emp-btn-cancel" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
            <button type="submit" class="emp-btn-save" id="empFormSubmit">
                <i class="fas fa-check" aria-hidden="true"></i> <span>{{ __('Save & Activate') }}</span>
            </button>
        </div>
      </form>
    </div>
  </div>
</div>

{{-- ── Script ──────────────────────────────────────────────────────────────── --}}
<script>
(function () {
    // ── Tabs + stat cards ────────────────────────────────────────────────────
    // Leaving a tab is announced, so the selection module can put away a
    // selection that would otherwise survive out of sight.
    function switchTab(name) {
        const leaving = document.querySelector('.rm-pane.active');
        document.querySelectorAll('.rmx-tab').forEach(t => {
            const on = t.dataset.tab === name;
            t.classList.toggle('is-open', on);
            t.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        document.querySelectorAll('.rm-pane').forEach(p => p.classList.toggle('active', p.dataset.pane === name));
        document.dispatchEvent(new CustomEvent('rmx:tab', {
            detail: { from: leaving && leaving.dataset.pane !== name ? leaving : null },
        }));
        try { history.replaceState(null, '', '#' + name); } catch (e) {}
    }
    document.querySelectorAll('.rmx-tab, .rmx-stat').forEach(el => el.addEventListener('click', () => switchTab(el.dataset.tab)));
    // The open tab is decided in the markup — see $openTab above. The hash is
    // still honoured for links saved before ?tab= existed.
    const hash = (location.hash || '').replace('#', '');
    if (['pending', 'active', 'removed'].includes(hash)) switchTab(hash);

    // ── Employee form modal (confirm / complete) ─────────────────────────────
    const storeUrl = "{{ route('employees.store') }}";
    const baseUrl  = "{{ url('employees') }}";
    const nextFp   = "{{ $nextFingerprintId }}";

    const modalEl   = document.getElementById('empFormModal');
    const form      = document.getElementById('empForm');
    const methodEl  = document.getElementById('empFormMethod');
    const titleEl   = document.getElementById('empFormTitle');
    const subEl     = document.getElementById('empFormSub');
    const submitLbl = document.querySelector('#empFormSubmit span');
    const firstEl   = document.getElementById('empFirst');
    const middleEl  = document.getElementById('empMiddle');
    const lastEl    = document.getElementById('empLast');
    const laborEl   = document.getElementById('empLabor');
    const rateEl    = document.getElementById('empRate');
    const rateView  = document.getElementById('empRateView');
    const siteEl    = document.getElementById('empSite');
    const fpEl      = document.getElementById('empFp');

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

    const modeField = document.getElementById('empFormModeField');
    const idField   = document.getElementById('empFormIdField');

    function openModal(mode, d) {
        modeField.value = mode;
        idField.value   = d.id || '';
        // The row carries the parts already split by Employee::splitName(), so
        // a worker created by the kiosk with only a bare name still opens with
        // the three boxes filled in rather than one of them holding all of it.
        firstEl.value  = d.first  || '';
        middleEl.value = d.middle || '';
        lastEl.value   = d.last   || '';
        siteEl.value = d.site || '';
        fpEl.value   = d.fp || (mode === 'add' ? nextFp : '');
        laborEl.value = d.labor || '';
        updateRate();

        if (mode === 'add') {
            form.action = storeUrl; methodEl.value = 'POST';
            titleEl.textContent = 'Add Employee Manually';
            subEl.textContent = 'Register a worker without a kiosk scan.';
            submitLbl.textContent = 'Register';
        } else if (mode === 'confirm') {
            form.action = `${baseUrl}/${d.id}/complete`; methodEl.value = 'POST';
            titleEl.textContent = 'Confirm the information';
            subEl.textContent = 'Review the details submitted from the kiosk — fix any typo and add a photo if needed, then activate.';
            submitLbl.textContent = 'Confirm & Activate';
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
        setTimeout(() => firstEl.focus(), 250);
    }

    // Delegated so pending rows swapped in by live polling stay clickable.
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.js-emp-edit');
        if (!btn) return;
        openModal(btn.dataset.mode, {
            id: btn.dataset.id, labor: btn.dataset.labor,
            first: btn.dataset.first, middle: btn.dataset.middle, last: btn.dataset.last,
            rate: btn.dataset.rate, site: btn.dataset.site, fp: btn.dataset.fp,
        });
    });

    form.addEventListener('submit', function () {
        const b = document.getElementById('empFormSubmit');
        b.disabled = true; b.querySelector('i').className = 'fas fa-spinner fa-spin';
    });

    // If validation failed server-side, reopen the form so errors aren't lost.
    @if($errors->any() && (old('first_name') || old('name')))
        openModal('{{ old('_form_mode', 'complete') }}', {
            id: '{{ old('_form_id') }}',
            first: @json(old('first_name')), middle: @json(old('middle_name')), last: @json(old('last_name')),
            labor: '{{ old('labor_type_id') }}', site: '{{ old('site_id') }}', fp: '{{ old('fingerprint_id') }}'
        });
    @endif

    // ── Realtime: auto-refresh kiosk-detected (pending) workers ──────────────
    // Polls a lightweight feed every few seconds so new fingerprint scans on
    // the kiosk appear here without the admin having to refresh the page.
    (function () {
        const liveUrl = "{{ route('employees.register.live') }}";
        const body    = document.getElementById('rmPendingBody');
        let lastSig     = @json($liveSignature ?? null);
        let prevPending = {{ $pending->count() }};

        function setCount(sel, val) { const el = document.querySelector(sel); if (el) el.textContent = val; }
        function updateCounts(c) {
            ['pending', 'active', 'removed'].forEach(k => {
                setCount('.rmx-stat-' + k + ' .rmx-stat-num', c[k]);
                setCount('.rmx-tab[data-tab="' + k + '"] .rmx-count', c[k]);
            });
            const badge = document.querySelector('.nav-pending-badge');
            if (badge) {
                badge.textContent = c.pending;
                badge.style.display = c.pending > 0 ? '' : 'none';
            }
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
                    Notify.info(n + ' new worker' + (n > 1 ? 's' : '') + ' detected from the kiosk');
                }
                prevPending = d.counts.pending;
            } catch (e) { /* offline / transient — try again next tick */ }
        }
        setInterval(poll, 5000);
    })();
})();


// ── Bulk selection ───────────────────────────────────────────────────────────
// One Select toggle, beside the tabs, acting on whichever tab is open. Each
// tab keeps its own checkboxes and its own actions, and leaving a tab puts
// its selection away so nothing stays ticked out of sight.
(function () {
    const csrf   = document.querySelector('meta[name="csrf-token"]').content;
    const toggle = document.getElementById('rmxSelect');

    const ENDPOINTS = {
        remove:  { url: '{{ route('employees.bulk-delete') }}',       method: 'DELETE' },
        restore: { url: '{{ route('employees.bulk-restore') }}',      method: 'PATCH'  },
        purge:   { url: '{{ route('employees.bulk-force-delete') }}', method: 'DELETE' },
    };

    const openPane = () => document.querySelector('.rm-pane.active');
    const paneOf   = el   => el.closest('.rm-pane');
    const boxesIn  = pane => Array.from(pane.querySelectorAll('tbody .rmx-check'));
    const pickedIn = pane => boxesIn(pane).filter(b => b.checked);

    function syncToggle() {
        const pane = openPane();
        const on   = !!pane && pane.classList.contains('selecting');
        toggle.classList.toggle('is-on', on);
        toggle.querySelector('.js-select-label').textContent = on ? 'Done' : 'Select';
        // Nothing to select on an empty tab, so the toggle has no job there.
        toggle.disabled = !pane || (boxesIn(pane).length === 0 && !on);
    }

    function sync(pane) {
        if (!pane) return;
        const boxes     = boxesIn(pane);
        const picked    = pickedIn(pane);
        const all       = pane.querySelector('.rmx-check-all');
        const bar       = pane.querySelector('.rmx-bulk');
        const selecting = pane.classList.contains('selecting');

        if (all) {
            all.disabled      = boxes.length === 0;
            all.checked       = boxes.length > 0 && picked.length === boxes.length;
            all.indeterminate = picked.length > 0 && picked.length < boxes.length;
        }
        if (bar) {
            // The bar rides along with selection mode rather than appearing on
            // the first tick, so entering the mode shows what can be done.
            bar.hidden = !selecting;
            const n = bar.querySelector('.rmx-bulk-count strong');
            if (n) n.textContent = picked.length;
            bar.querySelectorAll('button').forEach(b => { b.disabled = picked.length === 0; });
        }
        if (pane === openPane()) syncToggle();
    }

    // Entering the mode reveals the checkbox column; leaving it drops whatever
    // was ticked, so a stale selection can never survive out of sight.
    function setSelecting(pane, on) {
        if (!pane) return;
        pane.classList.toggle('selecting', on);
        if (!on) {
            boxesIn(pane).forEach(b => { b.checked = false; });
            const all = pane.querySelector('.rmx-check-all');
            if (all) { all.checked = false; all.indeterminate = false; }
        }
        sync(pane);
        syncToggle();
    }

    toggle.addEventListener('click', function () {
        const pane = openPane();
        if (pane) setSelecting(pane, !pane.classList.contains('selecting'));
    });

    document.addEventListener('rmx:tab', function (e) {
        if (e.detail && e.detail.from) setSelecting(e.detail.from, false);
        syncToggle();
    });

    document.addEventListener('change', function (e) {
        const t = e.target;
        if (t.classList && t.classList.contains('rmx-check-all')) {
            const pane = paneOf(t);
            boxesIn(pane).forEach(b => { b.checked = t.checked; });
            sync(pane);
        } else if (t.classList && t.classList.contains('rmx-check')) {
            sync(paneOf(t));
        }
    });

    document.addEventListener('click', async function (e) {
        const btn = e.target.closest('.js-bulk-clear, .js-bulk-remove, .js-bulk-restore, .js-bulk-purge');
        if (!btn) return;

        const pane = paneOf(btn);

        if (btn.classList.contains('js-bulk-clear')) {
            boxesIn(pane).forEach(b => { b.checked = false; });
            sync(pane);
            return;
        }

        const ids = pickedIn(pane).map(b => b.value);
        if (!ids.length) return;

        const kind = btn.classList.contains('js-bulk-restore') ? 'restore'
                   : btn.classList.contains('js-bulk-purge')   ? 'purge'
                   : 'remove';
        const one  = ids.length === 1;
        const many = ids.length + (one ? ' record' : ' records');

        const ASK = {
            purge:   { title: 'Permanently delete ' + many + '?',
                       message: 'This cannot be undone. Their attendance history and photos go too.',
                       confirmLabel: 'Delete permanently', tone: 'danger' },
            restore: { title: 'Restore ' + many + '?',
                       message: 'They move back to the list they came from.',
                       confirmLabel: 'Restore', tone: 'brand' },
        };
        const ask = ASK[kind] || {
            title: 'Remove ' + many + '?',
            message: 'They move to the Removed tab and can be restored from there.',
            confirmLabel: 'Remove', tone: 'warning',
        };

        if (!await Notify.confirm(ask)) return;

        const { url, method } = ENDPOINTS[kind];
        const label = btn.innerHTML;
        pane.querySelectorAll('.rmx-bulk button').forEach(b => { b.disabled = true; });
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Working…';

        try {
            const res = await fetch(url, {
                method:  method,
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'Accept':       'application/json',
                },
                body: JSON.stringify({ ids: ids }),
            });
            const data = await res.json();

            if (res.ok && data.success) {
                const done = data.deleted ?? data.restored ?? ids.length;
                Notify.success(done + (done === 1 ? ' record' : ' records') + ' updated');
                setTimeout(() => location.reload(), 700);
                return;
            }
            Notify.error(data.message || 'Could not complete that action.');
        } catch (err) {
            Notify.error('Network error — please try again.');
        }

        // Hand the buttons back to sync() rather than blanket-enabling them —
        // it is the one place that knows whether anything is still selected.
        btn.innerHTML = label;
        sync(pane);
    });

    // The pending rows are swapped out wholesale by the 5-second live refresh,
    // which fires no change event — without this the count would keep showing a
    // selection whose rows are already gone.
    const pendingBody = document.getElementById('rmPendingBody');
    if (pendingBody) {
        new MutationObserver(() => sync(paneOf(pendingBody))).observe(pendingBody, { childList: true });
    }

    document.querySelectorAll('.rm-pane').forEach(sync);
    syncToggle();
})();
</script>
@endsection
