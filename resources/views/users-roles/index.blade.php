@extends('layouts')
@section('page_title', 'Users & Roles')

@php
    use Illuminate\Support\Str;

    $roleCls = [
        'admin' => 'r-admin', 'staff' => 'r-staff', 'payroll_officer' => 'r-payroll',
        'hr' => 'r-hr', 'site_supervisor' => 'r-sup', 'employee' => 'r-emp',
    ];
    $cls      = fn ($role) => $roleCls[$role] ?? 'r-emp';
    $initials = fn ($name) => collect(preg_split('/\s+/', trim((string) $name) ?: '?'))
                    ->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
    $fp       = fn (string $bits, string $class = '') => '<span class="fp ' . $class . '">'
                    . collect(str_split($bits))->map(fn ($c) => '<i class="' . ($c === '1' ? 'on' : '') . '"></i>')->implode('')
                    . '</span>';
    $n9       = fn (string $bits) => substr_count($bits, '1');
    $when     = fn ($at) => $at === null ? 'Never'
                    : ($at->isToday() ? 'Today, ' . $at->format('g:i A')
                    : ($at->isYesterday() ? 'Yesterday, ' . $at->format('g:i A') : $at->format('M j, Y')));
    $icons    = [
        'leave' => 'calendar-days', 'loans' => 'wallet', 'assignments' => 'clipboard-list',
        'payroll-processing' => 'calculator', 'payslips' => 'file-text', 'payroll-reports' => 'file-bar-chart',
        'users-roles' => 'shield-check', 'audit-logs' => 'scroll-text', 'devices' => 'monitor-smartphone',
    ];
    $moduleKeys = array_keys($modules);
    $me         = auth()->user();
    $onlyAdmin  = $stats['admins'] <= 1;
    $query      = fn (array $change) => route('users-roles.index', array_filter(array_merge(request()->except(['page', 'account']), $change), fn ($v) => $v !== null && $v !== ''));
    $selFp      = $selected ? ($fingerprints[$selected->role] ?? str_repeat('0', 9)) : null;
    $selFirst   = $selected ? mb_strtoupper(Str::before(trim($selected->name ?: $selected->username), ' ')) : '';
@endphp

@push('styles')
@include('system._kit')
<style>
.ur-tiles { display: grid; grid-template-columns: 1.25fr repeat({{ count($roles) }}, 1fr); gap: 10px; margin-bottom: 14px; }
@media (max-width: 1200px) { .ur-tiles { grid-template-columns: repeat(4, 1fr); } }
.ur-tile { background: var(--surface); border: 1px solid var(--border); border-radius: 10px; padding: 11px 12px; box-shadow: var(--shadow-xs); display: block; color: inherit; transition: border-color .15s; }
.ur-tile:hover { border-color: var(--border-md); color: inherit; }
.ur-tile.on { border-color: var(--brand); box-shadow: 0 0 0 3px var(--brand-subtle); }
.ur-tile-top { display: flex; align-items: center; gap: 7px; font-size: 12px; font-weight: 600; color: var(--text-secondary); white-space: nowrap; }
.ur-tile-top svg { width: 14px; height: 14px; color: var(--brand); }
.ur-tile-n { font-size: 24px; font-weight: 700; letter-spacing: -.02em; margin: 8px 0 9px; color: var(--text-primary); line-height: 1; }
.ur-tile-n small { font-size: 11.5px; font-weight: 500; color: var(--text-muted); margin-left: 5px; letter-spacing: 0; }
.ur-tile-foot { display: flex; align-items: center; justify-content: space-between; gap: 8px; font-size: 10.5px; color: var(--text-muted); height: 14px; }
.ur-dist { display: flex; height: 10px; border-radius: 3px; overflow: hidden; gap: 2px; flex: 1; }
.ur-dist i { background: var(--rc); min-width: 4px; }
.ur-grid { display: grid; grid-template-columns: minmax(0, 1fr) 336px; gap: 14px; align-items: start; margin-bottom: 14px; }
@media (max-width: 1100px) { .ur-grid { grid-template-columns: 1fr; } }
.uname { font-size: 11.5px; color: var(--text-secondary); background: var(--bg-subtle); border: 1px solid var(--border); border-radius: 5px; padding: 2px 6px; }
.ur-pick { display: inline-flex; align-items: center; gap: 7px; height: 28px; padding: 0 6px 0 9px; border: 1px solid var(--border); border-radius: 7px; background: var(--surface); font-size: 12.5px; font-weight: 600; color: var(--text-primary); white-space: nowrap; }
.ur-pick:hover { border-color: var(--border-md); }
.ur-pick svg { width: 14px; height: 14px; color: var(--text-muted); margin-left: 2px; }
.ur-pick.open { border-color: var(--brand); box-shadow: 0 0 0 3px color-mix(in srgb, var(--brand) 18%, transparent); }
.ur-st { display: inline-flex; align-items: center; gap: 6px; font-size: 12.5px; color: var(--text-secondary); }
.ur-st::before { content: ""; width: 7px; height: 7px; border-radius: 50%; background: var(--success); }
.ur-st.off::before { background: var(--text-muted); }
.stale { color: var(--warning) !important; }
.stale-chip { font-size: 10px; font-weight: 700; color: var(--warning); background: var(--warning-soft); border-radius: 4px; padding: 1px 5px; margin-left: 6px; }
.ur-search { width: 218px; }

.ins .sx-card-head { min-height: 44px; padding: 9px 14px 9px 16px; }
.ins .x { color: var(--text-muted); display: grid; place-items: center; }
.ins .x svg { width: 16px; height: 16px; }
.ins-top { padding: 16px; display: flex; gap: 12px; align-items: flex-start; border-bottom: 1px solid var(--border); }
.ins-name { font-size: 15.5px; font-weight: 700; line-height: 1.2; color: var(--text-primary); }
.ins-meta { font-size: 12px; color: var(--text-muted); margin-top: 3px; word-break: break-word; }
.ins-chips { display: flex; gap: 8px; margin-top: 9px; align-items: center; flex-wrap: wrap; }
.ins-dl { display: grid; grid-template-columns: auto 1fr; gap: 7px 14px; padding: 12px 16px; margin: 0; border-bottom: 1px solid var(--border); font-size: 12.5px; }
.ins-dl dt { color: var(--text-muted); font-weight: 500; }
.ins-dl dd { margin: 0; color: var(--text-primary); font-weight: 500; text-align: right; }
.ins-sec { padding: 12px 16px 10px; border-bottom: 1px solid var(--border); position: relative; }
.ins-sec-h { display: flex; align-items: center; justify-content: space-between; margin-bottom: 4px; }
.acc-group > .sx-label { display: block; margin: 8px 0 2px; font-size: 9.5px; }
.acc-item { display: flex; align-items: center; gap: 9px; padding: 3px 0; font-size: 12.5px; color: var(--text-primary); }
.acc-item .mk { width: 18px; height: 18px; border-radius: 5px; display: grid; place-items: center; flex: none; }
.acc-item .mk svg { width: 12px; height: 12px; stroke-width: 2.8; }
.acc-item.yes .mk { background: color-mix(in srgb, var(--rc) 16%, var(--surface)); color: var(--rc); }
.acc-item.no { color: var(--text-muted); }
.acc-item.no .mk { border: 1.5px dashed var(--border-md); }
.acc-item.lock { color: var(--text-muted); }
.acc-item.lock .mk { background: var(--bg-subtle); border: 1px solid var(--border); color: var(--text-muted); }
.acc-item.lock .mk svg { width: 10px; height: 10px; stroke-width: 2.4; }
.acc-item .why { margin-left: auto; font-size: 10.5px; color: var(--text-muted); font-family: 'JetBrains Mono', monospace; }
.hist { position: relative; padding-left: 18px; margin-top: 8px; }
.hist::before { content: ""; position: absolute; left: 4px; top: 7px; bottom: 18px; width: 1px; background: var(--border-md); }
.hist-i { position: relative; padding-bottom: 10px; font-size: 12.5px; color: var(--text-primary); line-height: 1.4; }
.hist-i::before { content: ""; position: absolute; left: -18px; top: 4px; width: 9px; height: 9px; border-radius: 50%; background: var(--surface); border: 2px solid var(--warning); }
.hist-i.c::before { border-color: var(--success); }
.hist-i.d::before { border-color: var(--danger); }
.hist-t { font-size: 11px; color: var(--text-muted); margin-top: 1px; }
.ins-foot { display: flex; gap: 8px; padding: 12px 16px; align-items: center; flex-wrap: wrap; }
.ins-foot form { margin: 0; }
.ins-foot .push { margin-left: auto; }

.mx-table th.role, .mx-table td.c { text-align: center; }
.mx-rh { display: inline-flex; flex-direction: column; align-items: center; gap: 4px; text-transform: none; letter-spacing: 0; }
.mx-rh .sx-role { font-size: 12px; }
.mx-rh .mono { font-size: 10.5px; font-weight: 500; color: var(--text-muted); }
.mx { display: inline-grid; place-items: center; width: 24px; height: 24px; border-radius: 6px; vertical-align: middle; }
.mx.on { background: color-mix(in srgb, var(--rc) 15%, var(--surface)); color: var(--rc); }
.mx.on svg { width: 14px; height: 14px; stroke-width: 2.8; }
.mx.no::before { content: ""; width: 10px; height: 2px; border-radius: 1px; background: var(--border-md); }
.mx.lock { color: var(--text-muted); }
.mx.lock svg { width: 13px; height: 13px; }
.mx-table td { padding: 8px 14px; }
.mx-table tr.mx-group td { background: var(--bg-subtle); padding: 6px 14px; }
.mx-mod { display: flex; align-items: center; gap: 10px; font-weight: 600; }
.mx-mod svg { width: 15px; height: 15px; color: var(--text-muted); }
.mx-table td.hl { background: color-mix(in srgb, var(--hlc) 6%, var(--surface)); }
.mx-table th.hl { background: color-mix(in srgb, var(--hlc) 12%, var(--bg-subtle)); box-shadow: inset 0 3px 0 var(--hlc); }
.mx-sel { display: block; font-size: 9.5px; font-weight: 700; letter-spacing: .06em; color: var(--hlc); margin-bottom: 3px; }
.legend { display: flex; gap: 14px; font-size: 12px; color: var(--text-secondary); }
.legend span { display: inline-flex; align-items: center; gap: 6px; }
.legend .mx { width: 18px; height: 18px; border-radius: 5px; }
.legend .mx.on svg { width: 11px; height: 11px; }

/* The role picker and its confirmation, floated over the table. */
.ur-float { position: absolute; z-index: 1060; background: var(--surface); border: 1px solid var(--border-md); border-radius: 12px; box-shadow: var(--shadow-xl); }
.ur-float::before { content: ""; position: absolute; top: -6px; right: var(--arrow, 56px); width: 10px; height: 10px; background: var(--surface); border-left: 1px solid var(--border-md); border-top: 1px solid var(--border-md); transform: rotate(45deg); }
.ur-float.menu { width: 300px; padding: 6px; }
.ur-float.pop { width: 346px; padding: 14px; }
.menu-o { display: flex; align-items: center; gap: 9px; padding: 8px 9px; border-radius: 7px; font-size: 12.5px; font-weight: 600; color: var(--text-primary); width: 100%; border: 0; background: transparent; text-align: left; }
.menu-o:hover { background: var(--bg-subtle); box-shadow: inset 0 0 0 1px var(--border); }
.menu-o .fp { margin-left: auto; }
.menu-o .k { font-family: 'JetBrains Mono', monospace; font-size: 10.5px; color: var(--text-muted); width: 26px; text-align: right; }
.menu-o .c { width: 14px; height: 14px; color: var(--brand); flex: none; stroke-width: 2.8; }
.menu-o .c-sp { width: 14px; flex: none; }
.menu-o:disabled { opacity: .38; cursor: not-allowed; }
.menu-o:disabled:hover { background: transparent; box-shadow: none; }
.menu-foot { border-top: 1px solid var(--border); margin-top: 6px; padding: 9px 9px 5px; font-size: 11.5px; color: var(--text-muted); display: flex; gap: 7px; align-items: flex-start; line-height: 1.45; }
.menu-foot svg { width: 13px; height: 13px; flex: none; margin-top: 2px; }
.guard { display: flex; gap: 9px; padding: 10px; margin: 2px 2px 6px; border-radius: 8px; background: var(--danger-soft); color: var(--danger); font-size: 12px; line-height: 1.45; }
.guard svg { width: 15px; height: 15px; flex: none; margin-top: 1px; }
.pop h4 { font-size: 14px !important; font-weight: 700; margin: 0; color: var(--text-primary); line-height: 1.35 !important; }
.pop-fp { display: flex; align-items: center; gap: 10px; margin: 10px 0 12px; padding: 9px 10px; border-radius: 8px; background: var(--bg-subtle); border: 1px solid var(--border); font-size: 11.5px; color: var(--text-muted); }
.pop-fp > svg { width: 14px; height: 14px; }
.pop-fp .mono { margin-left: auto; color: var(--text-secondary); font-weight: 600; }
.diff { border: 1px solid var(--border); border-radius: 8px; margin-bottom: 12px; }
.diff-r { display: flex; gap: 10px; padding: 9px 10px; border-bottom: 1px solid var(--border); align-items: flex-start; }
.diff-r:last-child { border-bottom: none; }
.diff-r .k { width: 50px; flex: none; font-size: 11px; font-weight: 700; padding-top: 4px; }
.diff-r.g .k { color: var(--success); } .diff-r.l .k { color: var(--danger); } .diff-r.s .k { color: var(--text-muted); }
.mods { display: flex; flex-wrap: wrap; gap: 5px; }
.mods span { height: 22px; padding: 0 7px; border-radius: 5px; font-size: 11.5px; font-weight: 600; display: inline-flex; align-items: center; gap: 4px; }
.mods svg { width: 11px; height: 11px; stroke-width: 3; }
.g .mods span { background: var(--success-soft); color: var(--success); }
.l .mods span { background: var(--danger-soft); color: var(--danger); }
.s .mods span { background: var(--bg-subtle); color: var(--text-secondary); border: 1px solid var(--border); }
.mods .none { color: var(--text-muted); font-size: 11.5px; padding-top: 3px; }
.pop-foot { display: flex; align-items: center; gap: 8px; }
.pop-note { font-size: 11px; color: var(--text-muted); margin-right: auto; display: flex; align-items: center; gap: 5px; }
.pop-note svg { width: 12px; height: 12px; }
</style>
@endpush

@section('content')
<div class="sx-page">

    <div class="sx-head">
        <div>
            <div class="sx-eyebrow">System · 01 / 04</div>
            <h1 class="sx-title">Users &amp; Roles</h1>
            <p class="sx-sub">Who has an account, which role each one carries, and exactly what that role can open.</p>
        </div>
        <div class="sx-actions">
            <a class="sx-btn primary" href="{{ route('accounts.create') }}"><i data-lucide="user-plus"></i> Create account</a>
        </div>
    </div>

    @if($errors->any())
        <div class="sx-alert" role="alert"><i data-lucide="circle-alert"></i>
            <div><strong>Please fix the following:</strong><ul>@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        </div>
    @endif

    {{-- ── Status line ─────────────────────────────────────────────────── --}}
    <div class="sx-status {{ $stats['admins'] === 0 ? 'danger' : '' }}">
        <span class="sx-status-ico"><i data-lucide="shield-check"></i></span>
        <span><b>{{ $stats['total'] }} {{ Str::plural('account', $stats['total']) }}</b> in {{ count($roles) }} roles</span><span class="dotsep"></span>
        <span><b>{{ $stats['admins'] }} {{ Str::plural('admin', $stats['admins']) }}</b> (1 is the minimum)</span><span class="dotsep"></span>
        <a class="plain" href="{{ $query(['status' => 'disabled']) }}"><b>{{ $stats['disabled'] }}</b> disabled</a><span class="dotsep"></span>
        <a class="plain" href="{{ $query(['status' => 'idle']) }}"><b>{{ $stats['idle'] }}</b> idle for 90+ days</a>
        <span class="sx-status-end">
            @if($lastRoleChange)
                Last role change {{ Str::lcfirst($when($lastRoleChange->created_at)) }}
            @else
                No role changes recorded yet
            @endif
            <a class="sx-link" href="{{ route('audit-logs.index', ['module' => 'Users', 'range' => 'all']) }}">Audit Logs <i data-lucide="arrow-up-right"></i></a>
        </span>
    </div>

    {{-- ── Role tiles ──────────────────────────────────────────────────── --}}
    <div class="ur-tiles">
        <a class="ur-tile {{ $filters['role'] === '' ? 'on' : '' }}" href="{{ $query(['role' => null]) }}">
            <div class="ur-tile-top"><i data-lucide="users"></i> All accounts</div>
            <div class="ur-tile-n">{{ $stats['total'] }}<small>{{ count($roles) }} roles</small></div>
            <div class="ur-tile-foot"><span class="ur-dist">
                @foreach($roles as $key => $label)
                    @if(($counts[$key] ?? 0) > 0)<i class="{{ $cls($key) }}" style="flex: {{ $counts[$key] }}" title="{{ $label }}: {{ $counts[$key] }}"></i>@endif
                @endforeach
            </span></div>
        </a>
        @foreach($roles as $key => $label)
            <a class="ur-tile {{ $cls($key) }} {{ $filters['role'] === $key ? 'on' : '' }}" href="{{ $query(['role' => $key]) }}">
                <div class="ur-tile-top"><span class="pip"></span>{{ $label }}</div>
                <div class="ur-tile-n">{{ $counts[$key] ?? 0 }}</div>
                <div class="ur-tile-foot">{!! $fp($fingerprints[$key]) !!}<span class="mono">{{ $n9($fingerprints[$key]) }} / 9</span></div>
            </a>
        @endforeach
    </div>

    <div class="ur-grid">
        {{-- ── A · Accounts ─────────────────────────────────────────────── --}}
        <div class="sx-card">
            <div class="sx-card-head">
                <span class="sx-idx">A</span><h2 class="sx-card-title">Accounts</h2><span class="sx-card-note">sorted by name</span>
                <div class="sx-card-tools">
                    <form method="GET" action="{{ route('users-roles.index') }}" class="sx-input ur-search">
                        <i data-lucide="search"></i>
                        @foreach(['role' => $filters['role'], 'status' => $filters['status']] as $k => $v)
                            @if($v !== '')<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endif
                        @endforeach
                        <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="Name, username or email" aria-label="Search accounts">
                    </form>
                    <div class="sx-seg">
                        <a class="{{ ! in_array($filters['status'], ['active', 'disabled', 'idle'], true) ? 'on' : '' }}" href="{{ $query(['status' => null]) }}">All <span class="n">{{ $stats['total'] }}</span></a>
                        <a class="{{ $filters['status'] === 'active' ? 'on' : '' }}" href="{{ $query(['status' => 'active']) }}">Active <span class="n">{{ $stats['active'] }}</span></a>
                        <a class="{{ $filters['status'] === 'disabled' ? 'on' : '' }}" href="{{ $query(['status' => 'disabled']) }}">Disabled <span class="n">{{ $stats['disabled'] }}</span></a>
                        @if($filters['status'] === 'idle')<a class="on" href="{{ $query(['status' => 'idle']) }}">Idle <span class="n">{{ $stats['idle'] }}</span></a>@endif
                    </div>
                </div>
            </div>

            <div class="sx-table-wrap" id="roleList" data-live="accounts audit">
                <table class="sx-table">
                    <thead><tr><th>Account</th><th>Username</th><th>Role</th><th>Last sign-in</th><th>Status</th></tr></thead>
                    <tbody>
                    @forelse($users as $u)
                        @php
                            $isSel  = $selected && $selected->id === $u->id;
                            $seen   = $u->last_login_at;
                            $stale  = $seen ? $seen->lt($idleCut) : ($u->created_at && $u->created_at->lt($idleCut));
                            $days   = $seen ? (int) floor(abs($seen->diffInDays(now()))) : null;
                        @endphp
                        <tr class="{{ $isSel ? 'sel' : '' }} {{ $u->is_active ? '' : 'off' }}" data-href="{{ $query(['account' => $u->id, 'page' => $users->currentPage() > 1 ? $users->currentPage() : null]) }}">
                            <td>
                                <div class="person">
                                    <span class="av {{ $cls($u->role) }}">{{ $initials($u->name ?: $u->username) }}</span>
                                    <div style="min-width:0">
                                        <div class="nm"><a href="{{ $query(['account' => $u->id, 'page' => $users->currentPage() > 1 ? $users->currentPage() : null]) }}">{{ $u->name ?: $u->username }}</a>@if($me && $me->id === $u->id)<span class="you">You</span>@endif</div>
                                        <div class="sb">{{ $u->email ?: 'No email on file' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td><span class="mono uname">{{ $u->username ?: '—' }}</span></td>
                            <td>
                                <button type="button" class="ur-pick {{ $cls($u->role) }}" data-role-pick
                                        data-name="{{ $u->name ?: $u->username }}" data-role="{{ $u->role }}"
                                        data-action="{{ route('users-roles.update', $u) }}"
                                        data-last-admin="{{ $u->isAdmin() && $onlyAdmin ? 1 : 0 }}">
                                    <span class="pip"></span>{{ $u->role_label }}<i data-lucide="chevron-down"></i>
                                </button>
                            </td>
                            <td class="{{ $stale ? 'stale' : 'muted' }}" style="white-space:nowrap">
                                {{ $when($seen) }}@if($stale && $days)<span class="stale-chip">{{ $days }} days</span>@endif
                            </td>
                            <td><span class="ur-st {{ $u->is_active ? '' : 'off' }}">{{ $u->is_active ? 'Active' : 'Disabled' }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><div class="sx-empty"><i data-lucide="users"></i>No accounts match those filters.</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            <div class="sx-card-foot">
                <span>
                    @if($users->total())
                        Showing <b>{{ $users->firstItem() }}–{{ $users->lastItem() }}</b> of {{ $users->total() }} · click a row to inspect, change a role from its chip
                    @else
                        Nothing to show
                    @endif
                </span>
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
        </div>

        {{-- ── Inspector ────────────────────────────────────────────────── --}}
        <aside class="sx-card ins">
            @if($selected)
                @php
                    $created = $history->firstWhere('action', 'created');
                    $isSelf  = $me && $me->id === $selected->id;
                @endphp
                <div class="sx-card-head">
                    <span class="sx-label">Selected account</span>
                    <div class="sx-card-tools"><span class="sx-kbd">Esc</span><a class="x" href="{{ request()->fullUrlWithoutQuery(['account']) }}" aria-label="Close" data-close-inspector><i data-lucide="x"></i></a></div>
                </div>
                <div class="ins-top {{ $cls($selected->role) }}">
                    <span class="av lg">{{ $initials($selected->name ?: $selected->username) }}</span>
                    <div style="min-width:0">
                        <div class="ins-name">{{ $selected->name ?: $selected->username }}</div>
                        <div class="ins-meta"><span class="mono">{{ $selected->username }}</span>@if($selected->email) · {{ $selected->email }}@endif</div>
                        <div class="ins-chips">
                            <span class="sx-role"><span class="pip"></span>{{ $selected->role_label }}</span>
                            <span class="sx-badge {{ $selected->is_active ? 'ok' : 'muted' }}">{{ $selected->is_active ? 'Active' : 'Disabled' }}</span>
                        </div>
                    </div>
                </div>
                <dl class="ins-dl">
                    <dt>Last sign-in</dt><dd>{{ $when($selected->last_login_at) }}</dd>
                    <dt>Added</dt><dd>{{ $selected->created_at?->format('M j, Y') ?? '—' }}@if($selected->creator) · by {{ $selected->creator->name }}@endif</dd>
                </dl>
                <div class="ins-sec">
                    <div class="ins-sec-h"><span class="sx-label">Can open · {{ $n9($selFp) }} of 9</span>{!! $fp($selFp, $cls($selected->role)) !!}</div>
                    @foreach($groups as $group => $keys)
                        <div class="acc-group {{ $cls($selected->role) }}"><span class="sx-label">{{ $group }}</span>
                            @foreach($keys as $key)
                                @php $on = $selFp[array_search($key, $moduleKeys, true)] === '1'; @endphp
                                @if($on)
                                    <div class="acc-item yes"><span class="mk"><i data-lucide="check"></i></span>{{ $modules[$key] }}</div>
                                @elseif(in_array($key, $adminOnly, true))
                                    <div class="acc-item lock"><span class="mk"><i data-lucide="lock"></i></span>{{ $modules[$key] }}<span class="why">admin only</span></div>
                                @else
                                    <div class="acc-item no"><span class="mk"></span>{{ $modules[$key] }}</div>
                                @endif
                            @endforeach
                        </div>
                    @endforeach
                </div>
                <div class="ins-sec">
                    <div class="ins-sec-h"><span class="sx-label">History</span><a class="sx-link" style="font-size:12px" href="{{ route('audit-logs.index', ['subject_type' => 'User', 'subject_id' => $selected->id, 'range' => 'all']) }}">All activity <i data-lucide="arrow-up-right"></i></a></div>
                    <div class="hist">
                        @foreach($history as $h)
                            @php $tone = \App\Models\AuditLog::toneFor($h->action); @endphp
                            <div class="hist-i {{ $tone === 'ok' ? 'c' : ($tone === 'danger' ? 'd' : '') }}">
                                <div>{{ \Illuminate\Support\Str::limit($h->description, 90) }}</div>
                                <div class="hist-t">{{ $when($h->created_at) }} · by {{ $h->user_name ?: 'System' }}</div>
                            </div>
                        @endforeach
                        @unless($created)
                            <div class="hist-i c">
                                <div>Account created</div>
                                <div class="hist-t">{{ $selected->created_at?->format('M j, Y') ?? 'Date not recorded' }}@if($selected->creator) · by {{ $selected->creator->name }}@endif</div>
                            </div>
                        @endunless
                    </div>
                </div>
                <div class="ins-foot">
                    <a class="sx-btn sm" href="{{ route('accounts.edit', $selected) }}"><i data-lucide="pencil"></i> Edit account</a>
                    @unless($isSelf)
                        <form method="POST" action="{{ route('accounts.toggle', $selected) }}">
                            @csrf @method('PATCH')
                            @if($selected->is_active)
                                <button type="submit" class="sx-btn sm danger"><i data-lucide="user-x"></i> Deactivate</button>
                            @else
                                <button type="submit" class="sx-btn sm"><i data-lucide="user-check"></i> Activate</button>
                            @endif
                        </form>
                        <form method="POST" action="{{ route('accounts.destroy', $selected) }}" class="push"
                              data-confirm="This removes the account of {{ $selected->name }}. It cannot be undone."
                              data-confirm-title="Delete account?" data-confirm-label="Delete" data-confirm-tone="danger">
                            @csrf @method('DELETE')
                            <button type="submit" class="sx-btn sm quiet" title="Delete account" aria-label="Delete account"><i data-lucide="trash-2"></i></button>
                        </form>
                    @endunless
                </div>
            @else
                <div class="sx-empty"><i data-lucide="mouse-pointer-click"></i>Select an account to see what it can open.</div>
            @endif
        </aside>
    </div>

    {{-- ── B · Access matrix ────────────────────────────────────────────── --}}
    <div class="sx-card">
        <div class="sx-card-head">
            <span class="sx-idx">B</span><h2 class="sx-card-title">Access matrix</h2>
            <span class="sx-card-note">What each role can open. Set in code and shown here — changing it is a code change, not a click.</span>
            <div class="sx-card-tools legend">
                <span><span class="mx on r-admin"><i data-lucide="check"></i></span>Can open</span>
                <span><span class="mx no"></span>No access</span>
                <span><span class="mx lock"><i data-lucide="lock"></i></span>Administrator only</span>
            </div>
        </div>
        <div class="sx-table-wrap">
            <table class="sx-table mx-table">
                <thead>
                    <tr>
                        <th style="width:250px">Module</th>
                        @foreach($roles as $key => $label)
                            @php $hl = $selected && $selected->role === $key; @endphp
                            <th class="role {{ $cls($key) }} {{ $hl ? 'hl' : '' }}" @if($hl) style="--hlc: var(--rc)" @endif>
                                @if($hl)<span class="mx-sel">{{ $selFirst }}’S ROLE</span>@endif
                                <span class="mx-rh"><span class="sx-role"><span class="pip"></span>{{ $label }}</span><span class="mono">{{ $n9($fingerprints[$key]) }} of 9</span></span>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($groups as $group => $keys)
                        <tr class="mx-group"><td colspan="{{ count($roles) + 1 }}"><span class="sx-label">{{ $group }}</span></td></tr>
                        @foreach($keys as $key)
                            <tr>
                                <td><span class="mx-mod"><i data-lucide="{{ $icons[$key] ?? 'square' }}"></i>{{ $modules[$key] }}</span></td>
                                @foreach($roles as $roleKey => $label)
                                    @php
                                        $hl  = $selected && $selected->role === $roleKey;
                                        $bit = $fingerprints[$roleKey][array_search($key, $moduleKeys, true)] ?? '0';
                                    @endphp
                                    <td class="c {{ $cls($roleKey) }} {{ $hl ? 'hl' : '' }}" @if($hl) style="--hlc: var(--rc)" @endif>
                                        @if($bit === '1')
                                            <span class="mx on"><i data-lucide="check"></i></span>
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
    const fpHtml = (bits, cls) => '<span class="fp ' + cls + '">' + bits.split('').map(c => '<i class="' + (c === '1' ? 'on' : '') + '"></i>').join('') + '</span>';
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
        const left = Math.max(12, Math.min(window.innerWidth - el.offsetWidth - 12, r.right - el.offsetWidth));
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
                 + 'Promote someone else first — without one, nobody can open System Settings, Account Management or this page.</span></div>';
        }
        Object.entries(ROLES).forEach(([key, r]) => {
            const disabled = guard && key !== current;
            html += '<button type="button" class="menu-o ' + r.cls + '" data-pick="' + key + '"' + (disabled ? ' disabled' : '') + '>'
                 + (key === current ? '<i data-lucide="check" class="c"></i>' : '<span class="c-sp"></span>')
                 + '<span class="pip"></span>' + esc(r.label) + fpHtml(r.fp, r.cls) + '<span class="k">' + count(r.fp) + '/9</span></button>';
        });
        html += '<div class="menu-foot"><i data-lucide="' + (guard ? 'server' : 'info') + '"></i><span>'
             + (guard ? 'The server refuses this too — the screen just says so before you try.'
                      : 'Bars show Workforce · Payroll · System access. Nothing changes until you confirm.')
             + '</span></div>';

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

        const html = '<h4>Change ' + esc(btn.dataset.name) + ' from ' + esc(ROLES[from].label) + ' to ' + esc(ROLES[to].label) + '?</h4>'
            + '<div class="pop-fp">' + fpHtml(a, ROLES[from].cls) + '<i data-lucide="arrow-right"></i>' + fpHtml(b, ROLES[to].cls)
            + '<span class="mono">' + count(a) + ' → ' + count(b) + ' modules</span></div>'
            + '<div class="diff">'
            + '<div class="diff-r g"><span class="k">Gains</span><span class="mods">' + chips(gains, 'plus') + '</span></div>'
            + '<div class="diff-r l"><span class="k">Loses</span><span class="mods">' + chips(loses, 'minus') + '</span></div>'
            + '<div class="diff-r s"><span class="k">Keeps</span><span class="mods">' + chips(keeps) + '</span></div>'
            + '</div>'
            + '<div class="pop-foot"><span class="pop-note"><i data-lucide="scroll-text"></i>Written to Audit Logs</span>'
            + '<button type="button" class="sx-btn sm" data-cancel>Cancel</button>'
            + '<button type="button" class="sx-btn sm primary" data-go>Change role</button></div>';

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

        // A row opens the inspector, unless the click was on something of its own.
        const row = e.target.closest('tr[data-href]');
        if (row && !e.target.closest('a, button, form, input')) window.location.href = row.dataset.href;
    });

    document.addEventListener('keydown', e => {
        if (e.key !== 'Escape') return;
        if (open) { close(); return; }
        const x = document.querySelector('[data-close-inspector]');
        if (x && !document.querySelector('#jy-confirm:not([hidden])')) window.location.href = x.href;
    });
    window.addEventListener('resize', close);
})();
</script>
@endpush
