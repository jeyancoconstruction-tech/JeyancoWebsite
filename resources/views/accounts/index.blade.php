@extends('layouts')
@section('page_title', 'Accounts')

@section('content')
<div class="acct-page">

    {{-- ── Page header ─────────────────────────────────────────────────────── --}}
    <div class="acct-header">
        <div class="acct-header-left">
            <h1 class="acct-title">Account Management</h1>
            <span class="acct-count-chip">{{ $stats['total'] }} account{{ $stats['total'] !== 1 ? 's' : '' }}</span>
        </div>
        <p class="acct-header-sub">Create logins for your staff, change their details, and control who can sign in.</p>
    </div>

    @include('settings._system-tabs')

    {{-- ── Flash messages ──────────────────────────────────────────────────── --}}
    @if(session('success'))
        <div class="acct-flash ok"><i class="fas fa-check-circle"></i> {{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="acct-flash bad"><i class="fas fa-exclamation-circle"></i> {{ session('error') }}</div>
    @endif

    {{-- ── Stat strip ──────────────────────────────────────────────────────── --}}
    <div class="acct-stats">
        <div class="acct-stat">
            <span class="acct-stat-label">Administrators</span>
            <span class="acct-stat-value">{{ $stats['admins'] }}</span>
        </div>
        <div class="acct-stat">
            <span class="acct-stat-label">Staff</span>
            <span class="acct-stat-value">{{ $stats['staff'] }}</span>
        </div>
        <div class="acct-stat">
            <span class="acct-stat-label">Deactivated</span>
            <span class="acct-stat-value {{ $stats['inactive'] > 0 ? 'muted' : '' }}">{{ $stats['inactive'] }}</span>
        </div>
    </div>

    {{-- ── Toolbar: search / filter / create ───────────────────────────────── --}}
    <div class="acct-toolbar">
        <form method="GET" action="{{ route('accounts.index') }}" class="acct-filters">
            <div class="acct-search">
                <i class="fas fa-search"></i>
                <input type="text" name="q" value="{{ $search }}" placeholder="Search name, username or email…">
            </div>
            <select name="role" class="acct-select" onchange="this.form.submit()">
                <option value="">All roles</option>
                @foreach(\App\Models\User::ROLES as $value => $label)
                    <option value="{{ $value }}" {{ $role === $value ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
            <select name="status" class="acct-select" onchange="this.form.submit()">
                <option value="">All statuses</option>
                <option value="active"   {{ $status === 'active'   ? 'selected' : '' }}>Active</option>
                <option value="inactive" {{ $status === 'inactive' ? 'selected' : '' }}>Deactivated</option>
            </select>
            <button type="submit" class="acct-btn ghost">Apply</button>
            @if($search !== '' || $role || $status)
                <a href="{{ route('accounts.index') }}" class="acct-btn ghost">Clear</a>
            @endif
        </form>

        <a href="{{ route('accounts.create') }}" class="acct-btn primary">
            <i class="fas fa-user-plus"></i> Create Account
        </a>
    </div>

    {{-- ── Accounts table ──────────────────────────────────────────────────── --}}
    <div class="acct-card">
        <div class="acct-table-wrap">
            <table class="acct-table">
                <thead>
                    <tr>
                        <th>Account</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Last sign-in</th>
                        <th>Added by</th>
                        <th class="ta-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($accounts as $account)
                    <tr class="{{ $account->is_active ? '' : 'is-off' }}">
                        <td>
                            <div class="acct-person">
                                <img class="acct-avatar"
                                     src="https://ui-avatars.com/api/?name={{ urlencode($account->name) }}&background={{ $account->isAdmin() ? '6366f1' : '0ea5e9' }}&color=fff&bold=true"
                                     alt="">
                                <div class="acct-person-text">
                                    <span class="acct-name">
                                        {{ $account->name }}
                                        @if($account->id === auth()->id())
                                            <span class="acct-you">You</span>
                                        @endif
                                    </span>
                                    <span class="acct-email">{{ $account->email ?: '—' }}</span>
                                </div>
                            </div>
                        </td>
                        <td><code class="acct-user">{{ $account->username ?: '—' }}</code></td>
                        <td>
                            <span class="acct-pill role-{{ $account->role }}">{{ $account->role_label }}</span>
                        </td>
                        <td>
                            <span class="acct-pill {{ $account->is_active ? 'ok' : 'off' }}">
                                {{ $account->is_active ? 'Active' : 'Deactivated' }}
                            </span>
                        </td>
                        <td class="acct-dim">
                            {{ $account->last_login_at ? $account->last_login_at->format('M d, Y g:i A') : 'Never' }}
                        </td>
                        <td class="acct-dim">{{ $account->creator?->name ?? '—' }}</td>
                        <td class="ta-right">
                            <div class="acct-actions">
                                <a href="{{ route('accounts.edit', $account) }}" class="acct-icon-btn edit" title="Edit account">
                                    <i class="fas fa-pen"></i>
                                </a>

                                @if($account->id !== auth()->id())
                                    <form method="POST" action="{{ route('accounts.toggle', $account) }}" class="d-inline">
                                        @csrf @method('PATCH')
                                        <button type="submit"
                                                class="acct-icon-btn {{ $account->is_active ? 'off' : 'on' }}"
                                                title="{{ $account->is_active ? 'Deactivate' : 'Activate' }}">
                                            <i class="fas fa-{{ $account->is_active ? 'user-slash' : 'user-check' }}"></i>
                                        </button>
                                    </form>

                                    <form method="POST" action="{{ route('accounts.destroy', $account) }}" class="d-inline"
                                          onsubmit="return confirm('Delete {{ addslashes($account->name) }}\'s account? This cannot be undone.');">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="acct-icon-btn del" title="Delete account">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="acct-empty">
                            @if($search !== '' || $role || $status)
                                No accounts match those filters.
                            @else
                                No accounts yet. Create the first one.
                            @endif
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if($accounts->hasPages())
            <div class="acct-pager">{{ $accounts->links() }}</div>
        @endif
    </div>
</div>

{{-- ── Styles ──────────────────────────────────────────────────────────────── --}}
<style>
.acct-page { max-width: none; width: 100%; margin: 0; }

.acct-header { margin-bottom: 20px; }
.acct-header-left { display: flex; align-items: center; gap: 12px; }
.acct-title { font-size: 1.45rem; font-weight: 700; color: #0f172a; margin: 0; }
.acct-count-chip {
    font-size: 12px; font-weight: 600; color: #1e40af;
    background: #eff6ff; border: 1px solid #bfdbfe;
    padding: 3px 10px; border-radius: 20px;
}
.acct-header-sub { font-size: 13.5px; color: #64748b; margin: 6px 0 0; }

/* Flash */
.acct-flash {
    display: flex; align-items: center; gap: 9px;
    font-size: 13.5px; font-weight: 500;
    padding: 11px 15px; border-radius: 10px; margin-bottom: 16px;
}
.acct-flash.ok  { background: #dcfce7; border: 1px solid #bbf7d0; color: #166534; }
.acct-flash.bad { background: #fee2e2; border: 1px solid #fecaca; color: #991b1b; }

/* Stats */
.acct-stats { display: flex; gap: 12px; margin-bottom: 18px; flex-wrap: wrap; }
.acct-stat {
    flex: 1; min-width: 140px;
    background: #fff; border: 1px solid #e2e8f0; border-radius: 12px;
    padding: 14px 18px; display: flex; flex-direction: column; gap: 4px;
}
.acct-stat-label { font-size: 11.5px; font-weight: 700; letter-spacing: .4px; color: #64748b; text-transform: uppercase; }
.acct-stat-value { font-size: 1.5rem; font-weight: 700; color: #0f172a; line-height: 1.1; }
.acct-stat-value.muted { color: #dc2626; }

/* Toolbar */
.acct-toolbar {
    display: flex; align-items: center; justify-content: space-between;
    gap: 12px; margin-bottom: 16px; flex-wrap: wrap;
}
.acct-filters { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.acct-search { position: relative; }
.acct-search i {
    position: absolute; left: 12px; top: 50%; transform: translateY(-50%);
    font-size: 12px; color: #94a3b8;
}
.acct-search input {
    height: 40px; width: 280px; padding: 0 13px 0 33px; font-size: 13.5px;
    border: 1.5px solid #e2e8f0; border-radius: 8px;
    background: #fff; color: #0f172a; outline: none;
    transition: border-color .15s, box-shadow .15s;
}
.acct-search input:focus { border-color: #1e3a8a; box-shadow: 0 0 0 3px rgba(30,58,138,.08); }
.acct-select {
    height: 40px; padding: 0 11px; font-size: 13.5px;
    border: 1.5px solid #e2e8f0; border-radius: 8px;
    background: #fff; color: #0f172a; outline: none; cursor: pointer;
}

.acct-btn {
    height: 40px; padding: 0 16px; font-size: 13.5px; font-weight: 600;
    border-radius: 8px; border: 1.5px solid transparent; cursor: pointer;
    display: inline-flex; align-items: center; gap: 7px;
    text-decoration: none; transition: background .15s, border-color .15s;
}
.acct-btn.primary { background: #1e3a8a; color: #fff; }
.acct-btn.primary:hover { background: #1e40af; color: #fff; }
.acct-btn.ghost { background: #fff; border-color: #e2e8f0; color: #475569; }
.acct-btn.ghost:hover { border-color: #cbd5e1; color: #1e293b; }

/* Card + table */
.acct-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; overflow: hidden; }
.acct-table-wrap { overflow-x: auto; }
.acct-table { width: 100%; border-collapse: collapse; font-size: 13.5px; }
.acct-table thead th {
    text-align: left; font-size: 11.5px; font-weight: 700; letter-spacing: .4px;
    text-transform: uppercase; color: #64748b;
    padding: 13px 18px; background: #f8fafc; border-bottom: 1px solid #e2e8f0;
    white-space: nowrap;
}
.acct-table tbody td { padding: 13px 18px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
.acct-table tbody tr:last-child td { border-bottom: none; }
.acct-table tbody tr:hover { background: #f8fafc; }
.acct-table tbody tr.is-off { opacity: .62; }
.ta-right { text-align: right; }

.acct-person { display: flex; align-items: center; gap: 11px; }
.acct-avatar { width: 34px; height: 34px; border-radius: 50%; flex-shrink: 0; }
.acct-person-text { display: flex; flex-direction: column; min-width: 0; }
.acct-name { font-weight: 600; color: #1e293b; display: flex; align-items: center; gap: 7px; }
.acct-email { font-size: 12px; color: #94a3b8; }
.acct-you {
    font-size: 10px; font-weight: 700; letter-spacing: .3px; text-transform: uppercase;
    background: #eef2ff; border: 1px solid #c7d2fe; color: #4338ca;
    padding: 1px 6px; border-radius: 20px;
}
.acct-user {
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 12.5px;
    background: #f1f5f9; color: #334155; padding: 3px 8px; border-radius: 6px;
}
.acct-dim { color: #64748b; font-size: 12.5px; white-space: nowrap; }

.acct-pill {
    display: inline-block; font-size: 11.5px; font-weight: 700;
    padding: 3px 10px; border-radius: 20px; white-space: nowrap;
}
.acct-pill.role-admin { background: #eef2ff; border: 1px solid #c7d2fe; color: #4338ca; }
.acct-pill.role-staff { background: #ecfeff; border: 1px solid #a5f3fc; color: #0e7490; }
.acct-pill.ok  { background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; }
.acct-pill.off { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }

.acct-actions { display: inline-flex; align-items: center; gap: 4px; }
.acct-icon-btn {
    border: none; background: transparent;
    padding: 7px 9px; border-radius: 6px;
    cursor: pointer; font-size: 13px; line-height: 1;
    text-decoration: none; display: inline-flex; transition: background .12s;
}
.acct-icon-btn:hover { background: #f1f5f9; }
.acct-icon-btn.edit { color: #d97706; }
.acct-icon-btn.off  { color: #b45309; }
.acct-icon-btn.on   { color: #16a34a; }
.acct-icon-btn.del  { color: #dc2626; }

.acct-empty { text-align: center; padding: 40px 0; color: #94a3b8; font-size: 13px; }
.acct-pager { padding: 12px 18px; border-top: 1px solid #f1f5f9; }
.acct-pager .pagination { margin: 0; justify-content: center; }

/* Dark mode */
[data-bs-theme="dark"] .acct-title       { color: #e8edf5; }
[data-bs-theme="dark"] .acct-header-sub  { color: #6b7d96; }
[data-bs-theme="dark"] .acct-count-chip  { background: #172554; border-color: #1e3a8a; color: #93c5fd; }
[data-bs-theme="dark"] .acct-stat        { background: #151d2e; border-color: #283449; }
[data-bs-theme="dark"] .acct-stat-label  { color: #6b7d96; }
[data-bs-theme="dark"] .acct-stat-value  { color: #e8edf5; }
[data-bs-theme="dark"] .acct-search input,
[data-bs-theme="dark"] .acct-select      { background: #0f1a2e; border-color: #283449; color: #e8edf5; }
[data-bs-theme="dark"] .acct-search input:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,.12); }
[data-bs-theme="dark"] .acct-btn.ghost   { background: #151d2e; border-color: #283449; color: #9fb0c7; }
[data-bs-theme="dark"] .acct-btn.ghost:hover { border-color: #3d4a63; color: #e8edf5; }
[data-bs-theme="dark"] .acct-card        { background: #151d2e; border-color: #283449; }
[data-bs-theme="dark"] .acct-table thead th { background: #101828; color: #6b7d96; border-bottom-color: #283449; }
[data-bs-theme="dark"] .acct-table tbody td { border-bottom-color: #1e2637; }
[data-bs-theme="dark"] .acct-table tbody tr:hover { background: #1c2740; }
[data-bs-theme="dark"] .acct-name        { color: #e2e8f0; }
[data-bs-theme="dark"] .acct-email       { color: #64748b; }
[data-bs-theme="dark"] .acct-user        { background: #0f1a2e; color: #cbd5e1; }
[data-bs-theme="dark"] .acct-dim         { color: #6b7d96; }
[data-bs-theme="dark"] .acct-icon-btn:hover { background: #283449; }
[data-bs-theme="dark"] .acct-empty       { color: #475569; }
[data-bs-theme="dark"] .acct-pager       { border-top-color: #1e2637; }
[data-bs-theme="dark"] .acct-flash.ok    { background: #052e16; border-color: #166534; color: #86efac; }
[data-bs-theme="dark"] .acct-flash.bad   { background: #450a0a; border-color: #991b1b; color: #fca5a5; }

@media (max-width: 720px) {
    .acct-search input { width: 100%; }
    .acct-filters { width: 100%; }
}
</style>
@endsection
