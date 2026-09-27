@extends('layouts')
@section('page_title', 'Users & Roles')

{{-- Users & Roles, redesigned 2026-09-27 to sit with System Settings: one
     accounts card that carries its own filters (role tabs with their counts,
     status, search), the access matrix under it, and the selected account
     beside both, held in view while the list scrolls. The status strip, the
     role tiles and their access bars are gone — every figure they showed is
     on a tab or in a footer now. Changing a role, editing, deactivating and
     deleting work exactly as before. --}}

@php
    use Illuminate\Support\Str;

    $roleCls = [
        'admin' => 'r-admin', 'staff' => 'r-staff', 'payroll_officer' => 'r-payroll',
        'hr' => 'r-hr', 'site_supervisor' => 'r-sup', 'employee' => 'r-emp',
    ];
    $cls      = fn ($role) => $roleCls[$role] ?? 'r-emp';
    // A role from before there were two is shown as the one it reads as (HR),
    // the way User::role_label names it — colour, picker and access alike.
    $roleOf   = fn ($user) => array_key_exists($user->role, $roles) ? $user->role : \App\Models\User::ROLE_HR;
    $initials = fn ($name) => collect(preg_split('/\s+/', trim((string) $name) ?: '?'))
                    ->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
    // How many of the modules a role opens, out of however many there are.
    $nOpen    = fn (string $bits) => substr_count($bits, '1');
    $nAll     = count($modules);
    $when     = fn ($at) => $at === null ? 'Never'
                    : ($at->isToday() ? 'Today, ' . $at->format('g:i A')
                    : ($at->isYesterday() ? 'Yesterday, ' . $at->format('g:i A') : $at->format('M j, Y')));
    $icons    = [
        'leave' => 'calendar-days', 'loans' => 'wallet',
        'payslips' => 'file-text', 'payroll-reports' => 'file-bar-chart',
        'users-roles' => 'shield-check', 'audit-logs' => 'scroll-text', 'devices' => 'monitor-smartphone',
    ];
    $moduleKeys = array_keys($modules);
    $me         = auth()->user();
    $onlyAdmin  = $stats['admins'] <= 1;
    $query      = fn (array $change) => route('users-roles.index', array_filter(array_merge(request()->except(['page', 'account']), $change), fn ($v) => $v !== null && $v !== ''));
    $selRole    = $selected ? $roleOf($selected) : null;
    $selFp      = $selected ? ($fingerprints[$selRole] ?? str_repeat('0', $nAll)) : null;
    $selFirst   = $selected ? Str::before(trim($selected->name ?: $selected->username), ' ') : '';
    $status     = in_array($filters['status'], ['active', 'disabled', 'idle'], true) ? $filters['status'] : '';
@endphp

@push('styles')
@include('system._kit')
<style>
/* ── Page ─────────────────────────────────────────────────────────────── */
.ur { display: flex; flex-direction: column; gap: 16px; }
html[data-bs-theme] .main-content .ur > .page-head { margin-bottom: -4px !important; }
.ur-card { background: var(--surface); border: 1px solid var(--border); border-radius: 14px; box-shadow: var(--shadow-xs); }
.ur-grid { display: grid; grid-template-columns: minmax(0, 1fr) 360px; gap: 16px; align-items: start; }
@media (max-width: 1280px) { .ur-grid { grid-template-columns: minmax(0, 1fr) 320px; } }
@media (max-width: 1100px) { .ur-grid { grid-template-columns: minmax(0, 1fr); } }
.ur-main { display: flex; flex-direction: column; gap: 16px; min-width: 0; }

/* Buttons: one height and one radius across the page. */
.ur-btn { height: 38px; padding: 0 14px; border-radius: 10px; border: 1px solid var(--border-md); background: var(--surface); color: var(--text-primary);
          font-size: 13px; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; white-space: nowrap; text-decoration: none; cursor: pointer; }
.ur-btn svg { width: 16px; height: 16px; flex: none; }
.ur-btn:hover { border-color: var(--brand); color: var(--text-primary); }
.ur-btn.pri { background: var(--brand); border-color: var(--brand); color: #fff; }
.ur-btn.pri:hover { background: var(--brand-strong); border-color: var(--brand-strong); color: #fff; }
.ur-btn.ghost { border-color: transparent; background: transparent; color: var(--text-secondary); }
.ur-btn.ghost:hover { color: var(--text-primary); border-color: var(--border); }
.ur-btn.danger { color: var(--danger); }
.ur-btn.danger:hover { border-color: var(--danger); background: var(--danger-soft); color: var(--danger); }
.ur-btn.icon { width: 38px; padding: 0; justify-content: center; }
.ur-btn.sm { height: 32px; padding: 0 11px; font-size: 12.5px; border-radius: 9px; }
.ur-btn.sm.icon { width: 32px; }
.ur-btn.sm svg { width: 14px; height: 14px; }

/* ── Accounts card ────────────────────────────────────────────────────── */
.ur-head { display: flex; align-items: center; gap: 12px; padding: 14px 18px 0; flex-wrap: wrap; }
.ur-head h2 { margin: 0; font-size: 15px; font-weight: 700; color: var(--text-primary); }
.ur-head .sub { font-size: 12.5px; color: var(--text-muted); }
.ur-search { margin-left: auto; width: 280px; height: 38px; border: 1px solid var(--border-md); border-radius: 10px; background: var(--bg-subtle);
             display: flex; align-items: center; gap: 8px; padding: 0 12px; }
.ur-search svg { width: 16px; height: 16px; color: var(--text-muted); flex: none; }
.ur-search input { flex: 1; min-width: 0; border: 0; outline: 0; background: transparent; font-size: 13.5px; color: var(--text-primary); height: 100%; padding: 0; }
.ur-search input::placeholder { color: var(--text-muted); }
.ur-search:focus-within { border-color: var(--brand); box-shadow: 0 0 0 3px var(--brand-subtle); background: var(--surface); }

.ur-bar { display: flex; align-items: flex-end; gap: 12px; padding: 0 18px; border-bottom: 1px solid var(--border); margin-top: 10px; flex-wrap: wrap; }
.ur-tabs { display: flex; gap: 22px; }
.ur-tabs a { display: inline-flex; align-items: center; gap: 7px; padding: 10px 0 11px; font-size: 13px; font-weight: 600; color: var(--text-secondary);
             border-bottom: 2px solid transparent; margin-bottom: -1px; white-space: nowrap; }
.ur-tabs a:hover { color: var(--text-primary); }
.ur-tabs a.on { color: var(--brand); border-bottom-color: var(--brand); }
.ur-tabs .n { font-size: 11px; font-weight: 700; min-width: 20px; height: 18px; padding: 0 6px; border-radius: 999px; display: inline-grid; place-items: center;
              background: var(--bg-subtle); color: var(--text-muted); }
.ur-tabs a.on .n { background: var(--brand-subtle); color: var(--brand); }
.ur-seg { margin: 0 0 8px auto; display: inline-flex; padding: 3px; gap: 2px; border-radius: 10px; background: var(--bg-subtle); border: 1px solid var(--border); }
.ur-seg a { height: 28px; padding: 0 11px; border-radius: 7px; font-size: 12.5px; font-weight: 600; color: var(--text-secondary); display: inline-flex; align-items: center; gap: 6px; white-space: nowrap; }
.ur-seg a:hover { color: var(--text-primary); }
.ur-seg a.on { background: var(--surface); color: var(--text-primary); box-shadow: 0 1px 2px rgba(16, 24, 40, .12); }
.ur-seg .n { font-size: 11px; color: var(--text-muted); font-weight: 600; }

/* The list. The table keeps .sx-table: the phone layout turns its rows into cards. */
#roleList .sx-table th { background: transparent; font-size: 11px; letter-spacing: .06em; padding: 10px 18px; }
#roleList .sx-table td { padding: 12px 18px; font-size: 13.5px; }
#roleList .sx-table tr[data-href]:hover td { background: var(--bg-subtle); }
#roleList .sx-table tr.sel td, #roleList .sx-table tr.sel:hover td { background: var(--brand-subtle); }
#roleList .sx-table tr.sel td:first-child { box-shadow: inset 3px 0 0 var(--brand); }
#roleList .person { gap: 12px; }
#roleList .av { width: 36px; height: 36px; font-size: 12.5px; }
#roleList .person .nm { font-size: 13.5px; }
#roleList .person .sb { font-size: 12px; max-width: 320px; margin-top: 2px; }
.ur-pick { display: inline-flex; align-items: center; gap: 8px; height: 30px; padding: 0 8px 0 10px; border: 1px solid var(--border); border-radius: 8px;
           background: var(--surface); font-size: 12.5px; font-weight: 600; color: var(--text-primary); white-space: nowrap; cursor: pointer; }
.ur-pick:hover { border-color: var(--border-md); }
.ur-pick svg { width: 14px; height: 14px; color: var(--text-muted); }
.ur-pick.open { border-color: var(--brand); box-shadow: 0 0 0 3px var(--brand-subtle); }
.ur-when { color: var(--text-secondary); white-space: nowrap; }
.ur-when.stale { color: var(--warning); }
.ur-when small { display: block; font-size: 11.5px; color: var(--warning); font-weight: 600; margin-top: 1px; }
.ur-st { display: inline-flex; align-items: center; gap: 7px; font-size: 13px; color: var(--text-primary); white-space: nowrap; }
.ur-st::before { content: ""; width: 8px; height: 8px; border-radius: 50%; background: var(--success); }
.ur-st.off { color: var(--text-muted); }
.ur-st.off::before { background: var(--text-muted); }
.ur-g { display: block; margin-top: 3px; font-size: 11.5px; font-weight: 600; }
.ur-g.linked { color: var(--success); } .ur-g.pending { color: var(--warning); }
.ur-foot { display: flex; align-items: center; gap: 12px; padding: 12px 18px; border-top: 1px solid var(--border); font-size: 12.5px; color: var(--text-muted); flex-wrap: wrap; }
.ur-foot b { color: var(--text-primary); font-weight: 600; }
.ur-foot svg { width: 14px; height: 14px; vertical-align: -2px; margin-right: 4px; }

/* ── Access matrix ────────────────────────────────────────────────────── */
.mx-head { display: flex; align-items: center; gap: 12px; padding: 14px 18px; border-bottom: 1px solid var(--border); flex-wrap: wrap; }
.mx-head h2 { margin: 0; font-size: 15px; font-weight: 700; color: var(--text-primary); }
.mx-head .sub { font-size: 12.5px; color: var(--text-muted); }
.mx-legend { margin-left: auto; display: flex; gap: 16px; font-size: 12px; color: var(--text-secondary); }
.mx-legend span { display: inline-flex; align-items: center; gap: 6px; }
.mx-table { width: 100%; border-collapse: collapse; font-size: 13.5px; }
.mx-table th { padding: 12px 18px; text-align: left; font-size: 11px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: var(--text-muted); border-bottom: 1px solid var(--border); }
.mx-table th.role, .mx-table td.c { text-align: center; width: 190px; }
.mx-table th.role .sx-role { font-size: 12.5px; letter-spacing: 0; text-transform: none; }
.mx-table th.role small { display: block; font-size: 11.5px; font-weight: 500; letter-spacing: 0; text-transform: none; color: var(--text-muted); margin-top: 3px; }
.mx-table td { padding: 10px 18px; border-bottom: 1px solid var(--border); color: var(--text-primary); }
.mx-table tr:last-child td { border-bottom: 0; }
.mx-table tr.grp td { padding: 8px 18px 6px; font-size: 11px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: var(--text-muted); background: var(--bg-subtle); }
.mx-mod { display: flex; align-items: center; gap: 10px; font-weight: 600; }
.mx-mod svg { width: 16px; height: 16px; color: var(--text-muted); }
.mx-table .hl { background: color-mix(in srgb, var(--rc) 7%, var(--surface)); }
.mx-table th.hl { box-shadow: inset 0 2px 0 var(--rc); }
.mx-table th .mine { display: block; font-size: 10.5px; font-weight: 700; color: var(--rc); margin-bottom: 4px; letter-spacing: .04em; }
.mx { display: inline-grid; place-items: center; width: 24px; height: 24px; border-radius: 7px; vertical-align: middle; }
.mx.on { background: color-mix(in srgb, var(--rc) 15%, var(--surface)); color: var(--rc); }
.mx.on svg { width: 14px; height: 14px; stroke-width: 2.8; }
.mx.no::before { content: ""; width: 10px; height: 2px; border-radius: 1px; background: var(--border-md); }
.mx.lock { color: var(--text-muted); }
.mx.lock svg { width: 14px; height: 14px; }
.mx-legend .mx { width: 20px; height: 20px; border-radius: 6px; }
.mx-legend .mx.on svg, .mx-legend .mx.lock svg { width: 12px; height: 12px; }

/* ── Selected account ─────────────────────────────────────────────────── */
.ins { position: sticky; top: calc(var(--topbar-height, 60px) + 16px); overflow: hidden; }
@media (max-width: 1100px) { .ins { position: static; } }
.ins-bar { display: flex; align-items: center; justify-content: space-between; padding: 12px 12px 0 18px; }
.ins-bar span { font-size: 11px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: var(--text-muted); }
.ins-x { width: 30px; height: 30px; border-radius: 8px; display: grid; place-items: center; color: var(--text-muted); }
.ins-x:hover { background: var(--bg-subtle); color: var(--text-primary); }
.ins-x svg { width: 16px; height: 16px; }
.ins-top { display: flex; gap: 14px; align-items: center; padding: 10px 18px 16px; }
.ins-top .av { width: 52px; height: 52px; font-size: 17px; }
.ins-name { font-size: 16px; font-weight: 700; line-height: 1.25; color: var(--text-primary); }
.ins-meta { font-size: 12.5px; color: var(--text-muted); margin-top: 3px; word-break: break-word; }
.ins-chips { display: flex; gap: 6px; flex-wrap: wrap; padding: 0 18px 14px; }
.ins-chip { display: inline-flex; align-items: center; gap: 7px; height: 26px; padding: 0 10px; border-radius: 999px; font-size: 12px; font-weight: 600;
            background: var(--bg-subtle); border: 1px solid var(--border); color: var(--text-primary); }
.ins-chip.ok { background: var(--success-soft); border-color: transparent; color: var(--success); }
.ins-chip.off { color: var(--text-muted); }
.ins-chip.warn { background: var(--warning-soft); border-color: transparent; color: var(--warning); }
.ins-acts { display: flex; gap: 8px; padding: 0 18px 16px; border-bottom: 1px solid var(--border); }
.ins-acts form { margin: 0; }
.ins-acts .push { margin-left: auto; }
.ins-dl { display: grid; grid-template-columns: auto 1fr; gap: 10px 16px; padding: 14px 18px; margin: 0; border-bottom: 1px solid var(--border); font-size: 13px; }
.ins-dl dt { color: var(--text-muted); font-weight: 500; }
.ins-dl dd { margin: 0; color: var(--text-primary); font-weight: 600; text-align: right; }
.ins-fold { border-bottom: 1px solid var(--border); }
.ins-fold:last-child { border-bottom: 0; }
.ins-fold > summary { list-style: none; cursor: pointer; display: flex; align-items: center; gap: 10px; padding: 13px 18px; font-size: 13px; font-weight: 600; color: var(--text-primary); user-select: none; }
.ins-fold > summary::-webkit-details-marker { display: none; }
.ins-fold > summary:hover { background: var(--bg-subtle); }
.ins-fold > summary .aside { margin-left: auto; font-size: 12px; font-weight: 500; color: var(--text-muted); }
.ins-fold > summary .sx-link { margin-left: auto; font-size: 12px; }
.fold-arrow { width: 20px; height: 20px; display: grid; place-items: center; color: var(--text-muted); flex: none; transition: transform .15s; }
.fold-arrow svg { width: 16px; height: 16px; }
.ins-fold[open] > summary .fold-arrow { transform: rotate(90deg); color: var(--brand); }
.fold-body { padding: 0 18px 14px 48px; }
.acc-group > span { display: block; margin: 8px 0 4px; font-size: 10.5px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: var(--text-muted); }
.acc-item { display: flex; align-items: center; gap: 9px; padding: 3px 0; font-size: 13px; color: var(--text-primary); }
.acc-item .mk { width: 18px; height: 18px; border-radius: 5px; display: grid; place-items: center; flex: none; }
.acc-item .mk svg { width: 12px; height: 12px; stroke-width: 2.8; }
.acc-item.yes .mk { background: color-mix(in srgb, var(--rc) 16%, var(--surface)); color: var(--rc); }
.acc-item.no, .acc-item.lock { color: var(--text-muted); }
.acc-item.no .mk { border: 1.5px dashed var(--border-md); }
.acc-item.lock .mk { background: var(--bg-subtle); border: 1px solid var(--border); }
.acc-item.lock .mk svg { width: 10px; height: 10px; stroke-width: 2.4; }
.acc-item .why { margin-left: auto; font-size: 11px; color: var(--text-muted); }
.hist { position: relative; padding-left: 16px; }
.hist::before { content: ""; position: absolute; left: 4px; top: 6px; bottom: 16px; width: 1px; background: var(--border-md); }
.hist-i { position: relative; padding-bottom: 10px; font-size: 12.5px; color: var(--text-primary); line-height: 1.45; }
.hist-i::before { content: ""; position: absolute; left: -16px; top: 4px; width: 9px; height: 9px; border-radius: 50%; background: var(--surface); border: 2px solid var(--warning); }
.hist-i.c::before { border-color: var(--success); }
.hist-i.d::before { border-color: var(--danger); }
.hist-t { font-size: 11.5px; color: var(--text-muted); margin-top: 1px; }
.ins-empty { padding: 48px 20px; text-align: center; color: var(--text-muted); font-size: 13px; }
.ins-empty svg { width: 24px; height: 24px; display: block; margin: 0 auto 10px; opacity: .6; }

/* ── The role picker and its confirmation, floated over the table ────── */
.ur-float { position: absolute; z-index: 1060; background: var(--surface); border: 1px solid var(--border-md); border-radius: 12px; box-shadow: var(--shadow-xl); }
.ur-float::before { content: ""; position: absolute; top: -6px; right: var(--arrow, 56px); width: 10px; height: 10px; background: var(--surface); border-left: 1px solid var(--border-md); border-top: 1px solid var(--border-md); transform: rotate(45deg); }
.ur-float.menu { width: 280px; padding: 6px; }
.ur-float.pop { width: 372px; padding: 16px; }
.menu-o { display: flex; align-items: center; gap: 10px; padding: 9px 10px; border-radius: 8px; font-size: 13px; font-weight: 600; color: var(--text-primary); width: 100%; border: 0; background: transparent; text-align: left; cursor: pointer; }
.menu-o:hover { background: var(--bg-subtle); }
.menu-o .k { margin-left: auto; font-size: 11.5px; font-weight: 500; color: var(--text-muted); }
.menu-o .c { width: 14px; height: 14px; color: var(--brand); flex: none; stroke-width: 2.8; }
.menu-o .c-sp { width: 14px; flex: none; }
.menu-o:disabled { opacity: .4; cursor: not-allowed; }
.menu-o:disabled:hover { background: transparent; }
.menu-foot { border-top: 1px solid var(--border); margin-top: 6px; padding: 9px 10px 5px; font-size: 12px; color: var(--text-muted); line-height: 1.45; }
.guard { display: flex; gap: 9px; padding: 10px; margin: 2px 2px 6px; border-radius: 8px; background: var(--danger-soft); color: var(--danger); font-size: 12px; line-height: 1.45; }
.guard svg { width: 15px; height: 15px; flex: none; margin-top: 1px; }
.pop h4 { font-size: 14.5px !important; font-weight: 700; margin: 0 0 4px; color: var(--text-primary); line-height: 1.35 !important; }
.pop .pop-sub { font-size: 12.5px; color: var(--text-muted); margin-bottom: 12px; }
.diff { border: 1px solid var(--border); border-radius: 10px; margin-bottom: 14px; }
.diff-r { display: flex; gap: 10px; padding: 9px 12px; border-bottom: 1px solid var(--border); align-items: flex-start; }
.diff-r:last-child { border-bottom: none; }
.diff-r .k { width: 48px; flex: none; font-size: 11.5px; font-weight: 700; padding-top: 3px; }
.diff-r.g .k { color: var(--success); } .diff-r.l .k { color: var(--danger); } .diff-r.s .k { color: var(--text-muted); }
.mods { display: flex; flex-wrap: wrap; gap: 5px; }
.mods span { height: 22px; padding: 0 8px; border-radius: 6px; font-size: 11.5px; font-weight: 600; display: inline-flex; align-items: center; gap: 4px; }
.mods svg { width: 11px; height: 11px; stroke-width: 3; }
.g .mods span { background: var(--success-soft); color: var(--success); }
.l .mods span { background: var(--danger-soft); color: var(--danger); }
.s .mods span { background: var(--bg-subtle); color: var(--text-secondary); border: 1px solid var(--border); }
.mods .none { color: var(--text-muted); font-size: 12px; padding-top: 2px; }
.pop-foot { display: flex; align-items: center; gap: 8px; justify-content: flex-end; }
.pop-note { font-size: 11.5px; color: var(--text-muted); margin-right: auto; }
</style>
@endpush

@section('content')
<div class="sx-page ur">

    <x-page-header title="Users & Roles">
        <x-slot:actions>
            <a class="ur-btn ghost" href="{{ route('system-settings.about', ['section' => 'audit', 'module' => 'Users']) }}"><i data-lucide="scroll-text"></i> Audit log</a>
            <a class="ur-btn pri" href="{{ route('accounts.create') }}"><i data-lucide="user-plus"></i> Create account</a>
        </x-slot:actions>
    </x-page-header>

    @if($errors->any())
        <div class="sx-alert" role="alert"><i data-lucide="circle-alert"></i>
            <div><strong>Please fix the following:</strong><ul>@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        </div>
    @endif

    {{-- "picked": an account was chosen from the list; on a phone it then comes first. --}}
    <div class="ur-grid {{ request()->filled('account') ? 'picked' : '' }}">
        <div class="ur-main">
            {{-- ── Accounts ───────────────────────────────────────────────── --}}
            <section class="ur-card" aria-label="Accounts">
                <div class="ur-head">
                    <h2>Accounts</h2>
                    <span class="sub">{{ $stats['total'] }} {{ Str::plural('account', $stats['total']) }} in {{ count($roles) }} roles</span>
                    <form method="GET" action="{{ route('users-roles.index') }}" class="ur-search" role="search">
                        <i data-lucide="search"></i>
                        @foreach(['role' => $filters['role'], 'status' => $filters['status']] as $k => $v)
                            @if($v !== '')<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endif
                        @endforeach
                        <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="Search name, username or email" aria-label="Search accounts">
                    </form>
                </div>

                <div class="ur-bar">
                    <nav class="ur-tabs" aria-label="Role">
                        <a class="{{ $filters['role'] === '' ? 'on' : '' }}" href="{{ $query(['role' => null]) }}">All <span class="n">{{ $stats['total'] }}</span></a>
                        @foreach($roles as $key => $label)
                            <a class="{{ $filters['role'] === $key ? 'on' : '' }}" href="{{ $query(['role' => $key]) }}">{{ $label }} <span class="n">{{ $counts[$key] ?? 0 }}</span></a>
                        @endforeach
                    </nav>
                    <nav class="ur-seg" aria-label="Status">
                        <a class="{{ $status === '' ? 'on' : '' }}" href="{{ $query(['status' => null]) }}">All</a>
                        <a class="{{ $status === 'active' ? 'on' : '' }}" href="{{ $query(['status' => 'active']) }}">Active <span class="n">{{ $stats['active'] }}</span></a>
                        <a class="{{ $status === 'disabled' ? 'on' : '' }}" href="{{ $query(['status' => 'disabled']) }}">Disabled <span class="n">{{ $stats['disabled'] }}</span></a>
                        @if($stats['idle'] > 0 || $status === 'idle')
                            <a class="{{ $status === 'idle' ? 'on' : '' }}" href="{{ $query(['status' => 'idle']) }}" title="No sign-in for {{ \App\Http\Controllers\UserRoleController::IDLE_DAYS }}+ days">Idle <span class="n">{{ $stats['idle'] }}</span></a>
                        @endif
                    </nav>
                </div>

                <div class="sx-table-wrap" id="roleList" data-live="accounts audit">
                    <table class="sx-table">
                        <thead><tr><th>Account</th><th>Role</th><th>Last sign-in</th><th>Status</th></tr></thead>
                        <tbody>
                        @forelse($users as $u)
                            @php
                                $isSel = $selected && $selected->id === $u->id;
                                $rk    = $roleOf($u);
                                $seen  = $u->last_login_at;
                                $stale = $seen ? $seen->lt($idleCut) : ($u->created_at && $u->created_at->lt($idleCut));
                                $days  = $seen ? (int) floor(abs($seen->diffInDays(now()))) : null;
                                $href  = $query(['account' => $u->id, 'page' => $users->currentPage() > 1 ? $users->currentPage() : null]);
                                $sub   = $u->email && $u->email !== $u->username ? $u->email : null;
                            @endphp
                            <tr class="{{ $isSel ? 'sel' : '' }} {{ $u->is_active ? '' : 'off' }}" data-href="{{ $href }}">
                                <td>
                                    <div class="person">
                                        <span class="av {{ $cls($rk) }}">{{ $initials($u->name ?: $u->username) }}</span>
                                        <div style="min-width:0">
                                            <div class="nm"><a href="{{ $href }}">{{ $u->name ?: $u->username }}</a>@if($me && $me->id === $u->id)<span class="you">You</span>@endif</div>
                                            <div class="sb" title="{{ $u->username }}{{ $sub ? ' · ' . $sub : '' }}">{{ $u->username ?: '—' }}{{ $sub ? ' · ' . $sub : ($u->email ? '' : ' · no email on file') }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <button type="button" class="ur-pick {{ $cls($rk) }}" data-role-pick
                                            data-name="{{ $u->name ?: $u->username }}" data-role="{{ $rk }}"
                                            data-action="{{ route('users-roles.update', $u) }}"
                                            data-last-admin="{{ $u->isAdmin() && $onlyAdmin ? 1 : 0 }}">
                                        <span class="pip"></span>{{ $u->role_label }}<i data-lucide="chevron-down"></i>
                                    </button>
                                </td>
                                <td>
                                    <span class="ur-when {{ $stale ? 'stale' : '' }}">{{ $when($seen) }}@if($stale && $days)<small>{{ $days }} days ago</small>@endif</span>
                                </td>
                                <td>
                                    <span class="ur-st {{ $u->is_active ? '' : 'off' }}">{{ $u->is_active ? 'Active' : 'Disabled' }}</span>
                                    @if($g = $u->googleStatus())
                                        <span class="ur-g {{ $g }}" title="{{ $g === 'linked' ? 'Has signed in with Google' : 'Has not signed in with Google yet' }}">Google {{ $g }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4"><div class="sx-empty"><i data-lucide="users"></i>No accounts match those filters.</div></td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="ur-foot">
                    <span><i data-lucide="shield-check"></i><b>{{ $stats['admins'] }} {{ Str::plural('administrator', $stats['admins']) }}</b> — at least one is always kept</span>
                    @if($users->total() > $users->count())
                        <span>· {{ $users->firstItem() }}–{{ $users->lastItem() }} of {{ $users->total() }}</span>
                    @endif
                    @if($users->hasPages())
                        @php
                            $cur = $users->currentPage(); $last = $users->lastPage();
                            $pages = collect([1, 2, $cur - 1, $cur, $cur + 1, $last - 1, $last])->filter(fn ($p) => $p >= 1 && $p <= $last)->unique()->sort()->values();
                        @endphp
                        <nav class="sx-pager" aria-label="Pages">
                            @if($cur > 1)<a href="{{ $users->url($cur - 1) }}" aria-label="Previous"><i data-lucide="chevron-left"></i></a>@else<span class="dis"><i data-lucide="chevron-left"></i></span>@endif
                            @foreach($pages as $i => $p)
                                @if($i > 0 && $p - $pages[$i - 1] > 1)<span class="gap">…</span>@endif
                                @if($p === $cur)<span class="on">{{ $p }}</span>@else<a href="{{ $users->url($p) }}">{{ $p }}</a>@endif
                            @endforeach
                            @if($cur < $last)<a href="{{ $users->url($cur + 1) }}" aria-label="Next"><i data-lucide="chevron-right"></i></a>@else<span class="dis"><i data-lucide="chevron-right"></i></span>@endif
                        </nav>
                    @endif
                </div>
            </section>

            {{-- ── Access matrix ──────────────────────────────────────────── --}}
            <section class="ur-card" aria-label="Access matrix">
                <div class="mx-head">
                    <h2>Access matrix</h2>
                    <span class="sub">What each role can open. Set in code, not here.</span>
                    <div class="mx-legend">
                        <span><span class="mx on r-admin"><i data-lucide="check"></i></span>Can open</span>
                        <span><span class="mx no"></span>No access</span>
                        <span><span class="mx lock"><i data-lucide="lock"></i></span>Administrator only</span>
                    </div>
                </div>
                <div class="sx-table-wrap">
                    <table class="mx-table">
                        <thead>
                            <tr>
                                <th>Module</th>
                                @foreach($roles as $key => $label)
                                    @php $hl = $selRole === $key; @endphp
                                    <th class="role {{ $cls($key) }} {{ $hl ? 'hl' : '' }}">
                                        @if($hl)<span class="mine">{{ $selFirst }}’s role</span>@endif
                                        <span class="sx-role"><span class="pip"></span>{{ $label }}</span>
                                        <small>{{ $nOpen($fingerprints[$key]) }} of {{ $nAll }}</small>
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($groups as $group => $keys)
                                <tr class="grp"><td colspan="{{ count($roles) + 1 }}">{{ $group }}</td></tr>
                                @foreach($keys as $key)
                                    <tr>
                                        <td><span class="mx-mod"><i data-lucide="{{ $icons[$key] ?? 'square' }}"></i>{{ $modules[$key] }}</span></td>
                                        @foreach($roles as $roleKey => $label)
                                            @php
                                                $hl  = $selRole === $roleKey;
                                                $bit = $fingerprints[$roleKey][array_search($key, $moduleKeys, true)] ?? '0';
                                            @endphp
                                            <td class="c {{ $cls($roleKey) }} {{ $hl ? 'hl' : '' }}">
                                                @if($bit === '1')
                                                    <span class="mx on" title="Can open"><i data-lucide="check"></i></span>
                                                @elseif(in_array($key, $adminOnly, true))
                                                    <span class="mx lock" title="Administrator only"><i data-lucide="lock"></i></span>
                                                @else
                                                    <span class="mx no" title="No access"></span>
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        {{-- ── Selected account ───────────────────────────────────────────── --}}
        <aside class="ur-card ins" aria-label="Selected account">
            @if($selected)
                @php
                    $created = $history->firstWhere('action', 'created');
                    $isSelf  = $me && $me->id === $selected->id;
                    $histN   = $history->count() + ($created ? 0 : 1);
                @endphp
                <div class="ins-bar">
                    <span>Selected account</span>
                    <a class="ins-x" href="{{ request()->fullUrlWithoutQuery(['account']) }}" aria-label="Close" data-close-inspector><i data-lucide="x"></i></a>
                </div>
                <div class="ins-top {{ $cls($selRole) }}">
                    <span class="av">{{ $initials($selected->name ?: $selected->username) }}</span>
                    <div style="min-width:0">
                        <div class="ins-name">{{ $selected->name ?: $selected->username }}</div>
                        <div class="ins-meta">{{ $selected->username }}@if($selected->email && $selected->email !== $selected->username) · {{ $selected->email }}@endif</div>
                    </div>
                </div>
                <div class="ins-chips">
                    <span class="ins-chip {{ $cls($selRole) }}"><span class="pip"></span>{{ $selected->role_label }}</span>
                    <span class="ins-chip {{ $selected->is_active ? 'ok' : 'off' }}">{{ $selected->is_active ? 'Active' : 'Disabled' }}</span>
                    @if($g = $selected->googleStatus())
                        <span class="ins-chip {{ $g === 'linked' ? 'ok' : 'warn' }}" title="Sign in with Google">{{ $g === 'linked' ? 'Linked' : 'Pending' }}</span>
                    @endif
                </div>
                <div class="ins-acts">
                    <a class="ur-btn sm" href="{{ route('accounts.edit', $selected) }}"><i data-lucide="pencil"></i> Edit</a>
                    @unless($isSelf)
                        <form method="POST" action="{{ route('accounts.toggle', $selected) }}">
                            @csrf @method('PATCH')
                            @if($selected->is_active)
                                <button type="submit" class="ur-btn sm danger"><i data-lucide="user-x"></i> Deactivate</button>
                            @else
                                <button type="submit" class="ur-btn sm"><i data-lucide="user-check"></i> Activate</button>
                            @endif
                        </form>
                        <form method="POST" action="{{ route('accounts.destroy', $selected) }}" class="push"
                              data-confirm="This removes the account of {{ $selected->name }}. It cannot be undone."
                              data-confirm-title="Delete account?" data-confirm-label="Delete" data-confirm-tone="danger">
                            @csrf @method('DELETE')
                            <button type="submit" class="ur-btn sm icon ghost" title="Delete account" aria-label="Delete account"><i data-lucide="trash-2"></i></button>
                        </form>
                    @endunless
                </div>
                <dl class="ins-dl">
                    <dt>Last sign-in</dt><dd>{{ $when($selected->last_login_at) }}</dd>
                    <dt>Signs in with</dt><dd>{{ \App\Models\User::LOGIN_METHODS[$selected->login_method] ?? 'Password only' }}</dd>
                    @if($selected->usesGoogle())
                        <dt>Google</dt><dd>{{ $selected->google_linked_at ? 'Linked ' . $selected->google_linked_at->format('M j, Y') : 'Not signed in with Google yet' }}</dd>
                    @endif
                    <dt>Added</dt><dd>{{ $selected->created_at?->format('M j, Y') ?? '—' }}@if($selected->creator) · {{ $selected->creator->name }}@endif</dd>
                </dl>
                <details class="ins-fold" data-fold="access">
                    <summary>
                        <span class="fold-arrow"><i data-lucide="chevron-right"></i></span>
                        Can open · {{ $nOpen($selFp) }} of {{ $nAll }}
                        <span class="aside">{{ $selected->role_label }}</span>
                    </summary>
                    <div class="fold-body">
                        @foreach($groups as $group => $keys)
                            <div class="acc-group {{ $cls($selRole) }}"><span>{{ $group }}</span>
                                @foreach($keys as $key)
                                    @php $on = $selFp[array_search($key, $moduleKeys, true)] === '1'; @endphp
                                    @if($on)
                                        <div class="acc-item yes"><span class="mk"><i data-lucide="check"></i></span>{{ $modules[$key] }}</div>
                                    @elseif(in_array($key, $adminOnly, true))
                                        <div class="acc-item lock"><span class="mk"><i data-lucide="lock"></i></span>{{ $modules[$key] }}<span class="why">Admin only</span></div>
                                    @else
                                        <div class="acc-item no"><span class="mk"></span>{{ $modules[$key] }}</div>
                                    @endif
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                </details>
                <details class="ins-fold" data-fold="history">
                    <summary>
                        <span class="fold-arrow"><i data-lucide="chevron-right"></i></span>
                        History · {{ $histN }} {{ Str::plural('entry', $histN) }}
                        <a class="sx-link" href="{{ route('system-settings.about', ['section' => 'audit', 'subject_type' => 'User', 'subject_id' => $selected->id, 'range' => 'all']) }}">All activity <i data-lucide="arrow-up-right"></i></a>
                    </summary>
                    <div class="fold-body">
                        <div class="hist">
                            @foreach($history as $h)
                                @php $tone = \App\Models\AuditLog::toneFor($h->action); @endphp
                                <div class="hist-i {{ $tone === 'ok' ? 'c' : ($tone === 'danger' ? 'd' : '') }}">
                                    <div>{{ Str::limit($h->description, 90) }}</div>
                                    <div class="hist-t">{{ $when($h->created_at) }} · {{ $h->user_name ?: 'System' }}</div>
                                </div>
                            @endforeach
                            @unless($created)
                                <div class="hist-i c">
                                    <div>Account created</div>
                                    <div class="hist-t">{{ $selected->created_at?->format('M j, Y') ?? 'Date not recorded' }}@if($selected->creator) · {{ $selected->creator->name }}@endif</div>
                                </div>
                            @endunless
                        </div>
                    </div>
                </details>
            @else
                <div class="ins-empty"><i data-lucide="mouse-pointer-click"></i>Select an account to see its details.</div>
            @endif
        </aside>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    {{-- json_encode rather than @json: @json splits its argument on commas. --}}
    const ROLES = {!! json_encode(collect($roles)->map(fn ($label, $key) => ['label' => $label, 'cls' => $cls($key), 'fp' => $fingerprints[$key]]), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!};
    const MODULES = @json(array_values($modules));
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    let open = null;

    const esc = s => String(s).replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m]));
    const count = bits => bits.split('1').length - 1;

    function close() {
        if (!open) return;
        open.el.remove();
        open.btn.classList.remove('open');
        open = null;
    }

    function float(kind, html, btn) {
        close();
        const el = document.createElement('div');
        el.className = 'ur-float ' + kind;
        el.innerHTML = html;
        document.body.appendChild(el);
        btn.classList.add('open');
        open = { el, btn };
        if (window.lucide) lucide.createIcons();

        const r = btn.getBoundingClientRect();
        const left = Math.max(12, Math.min(window.innerWidth - el.offsetWidth - 12, r.left));
        el.style.top = (r.bottom + window.scrollY + 9) + 'px';
        el.style.left = (left + window.scrollX) + 'px';
        el.style.setProperty('--arrow', Math.max(14, left + el.offsetWidth - r.left - r.width / 2 - 5) + 'px');
        el.addEventListener('click', e => e.stopPropagation());
        return el;
    }

    function menu(btn) {
        const current = btn.dataset.role;
        const guard = btn.dataset.lastAdmin === '1';
        let html = '';
        if (guard) {
            html += '<div class="guard"><i data-lucide="lock"></i><span><b>' + esc(btn.dataset.name) + ' is the only Administrator.</b> '
                 + 'Make someone else an Administrator first.</span></div>';
        }
        Object.entries(ROLES).forEach(([key, r]) => {
            const disabled = guard && key !== current;
            html += '<button type="button" class="menu-o ' + r.cls + '" data-pick="' + key + '"' + (disabled ? ' disabled' : '') + '>'
                 + (key === current ? '<i data-lucide="check" class="c"></i>' : '<span class="c-sp"></span>')
                 + '<span class="pip"></span>' + esc(r.label) + '<span class="k">' + count(r.fp) + ' of ' + MODULES.length + ' modules</span></button>';
        });
        html += '<div class="menu-foot">Nothing changes until you confirm.</div>';

        const el = float('menu', html, btn);
        el.querySelectorAll('[data-pick]').forEach(o => o.addEventListener('click', () => {
            if (o.dataset.pick === current) { close(); return; }
            confirmStep(btn, o.dataset.pick);
        }));
    }

    function confirmStep(btn, to) {
        const from = btn.dataset.role;
        const a = ROLES[from].fp, b = ROLES[to].fp;
        const gains = [], loses = [], keeps = [];
        MODULES.forEach((m, i) => {
            if (a[i] === '0' && b[i] === '1') gains.push(m);
            else if (a[i] === '1' && b[i] === '0') loses.push(m);
            else if (a[i] === '1' && b[i] === '1') keeps.push(m);
        });
        const chips = (list, icon) => list.length
            ? list.map(m => '<span>' + (icon ? '<i data-lucide="' + icon + '"></i>' : '') + esc(m) + '</span>').join('')
            : '<span class="none">Nothing</span>';

        const html = '<h4>Make ' + esc(btn.dataset.name) + ' ' + esc(ROLES[to].label) + '?</h4>'
            + '<div class="pop-sub">From ' + esc(ROLES[from].label) + ' · ' + count(a) + ' → ' + count(b) + ' of ' + MODULES.length + ' modules</div>'
            + '<div class="diff">'
            + '<div class="diff-r g"><span class="k">Gains</span><span class="mods">' + chips(gains, 'plus') + '</span></div>'
            + '<div class="diff-r l"><span class="k">Loses</span><span class="mods">' + chips(loses, 'minus') + '</span></div>'
            + '<div class="diff-r s"><span class="k">Keeps</span><span class="mods">' + chips(keeps) + '</span></div>'
            + '</div>'
            + '<div class="pop-foot"><span class="pop-note">Written to the audit log</span>'
            + '<button type="button" class="ur-btn sm" data-cancel>Cancel</button>'
            + '<button type="button" class="ur-btn sm pri" data-go>Change role</button></div>';

        const el = float('pop', html, btn);
        el.querySelector('[data-cancel]').addEventListener('click', close);
        el.querySelector('[data-go]').addEventListener('click', () => {
            const f = document.createElement('form');
            f.method = 'POST';
            f.action = btn.dataset.action;
            f.innerHTML = '<input type="hidden" name="_token" value="' + esc(csrf) + '">'
                        + '<input type="hidden" name="_method" value="PATCH">'
                        + '<input type="hidden" name="role" value="' + esc(to) + '">';
            document.body.appendChild(f);
            f.submit();
        });
    }

    document.addEventListener('click', e => {
        const btn = e.target.closest('[data-role-pick]');
        if (btn) {
            e.stopPropagation();
            if (open && open.btn === btn) { close(); return; }
            menu(btn);
            return;
        }
        close();

        // A row opens the account beside it, unless the click was on something of its own.
        const row = e.target.closest('tr[data-href]');
        if (row && !e.target.closest('a, button, form, input')) window.location.href = row.dataset.href;
    });

    // Remember which sections of the selected account are open. Closed by default.
    document.querySelectorAll('.ins-fold[data-fold]').forEach(d => {
        const key = 'ur-fold-' + d.dataset.fold;
        try { if (localStorage.getItem(key) === '1') d.open = true; } catch (e) {}
        d.addEventListener('toggle', () => {
            try { localStorage.setItem(key, d.open ? '1' : '0'); } catch (e) {}
        });
        // The "All activity" link inside a summary opens the page, not the fold.
        d.querySelectorAll('summary a').forEach(a => a.addEventListener('click', e => e.stopPropagation()));
    });

    document.addEventListener('keydown', e => {
        if (e.key !== 'Escape') return;
        if (open) { close(); return; }
        const x = document.querySelector('[data-close-inspector]');
        if (x && !document.querySelector('#jy-confirm:not([hidden])') && !e.target.closest('input, textarea, select')) window.location.href = x.href;
    });
    window.addEventListener('resize', close);
})();
</script>
@endpush
