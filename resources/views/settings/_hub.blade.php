{{-- ── Settings hub ────────────────────────────────────────────────────────
     One shell for every settings page: categories down the left, the chosen
     one on the right. Each item is a link to its own URL rather than a JS
     panel — the forms post, the accounts list paginates, and both need a real
     address to come back to.

     Only the categories that are backed by something are here. Notifications,
     Appearance and Data & backup have nothing behind them yet, and a control
     that does not reach anything is worse than a missing one: it says a
     decision was made that was never applied. --}}
<style>
    .hub { display: grid; grid-template-columns: 210px minmax(0, 1fr); gap: 16px; align-items: start; }
    @media (max-width: 860px) { .hub { grid-template-columns: 1fr; } }

    .hub-nav {
        background: var(--surface); border: 1px solid var(--border);
        border-radius: 6px; padding: 8px; position: sticky; top: 16px;
    }
    @media (max-width: 860px) { .hub-nav { position: static; } }

    .hub-sec {
        font-size: 10px; text-transform: uppercase; letter-spacing: .06em;
        color: var(--text-muted); padding: 10px 10px 5px;
    }
    .hub-item {
        display: flex; align-items: center; gap: 10px;
        padding: 9px 10px; margin-bottom: 1px; border-radius: 6px;
        font-size: 13px; color: var(--text-secondary); text-decoration: none;
        border-left: 3px solid transparent;
    }
    .hub-item:hover { background: var(--bg-subtle); color: var(--text-primary); }
    .hub-item.on {
        background: var(--brand-subtle); color: var(--text-primary);
        border-left-color: var(--brand); font-weight: 600;
    }
    .hub-item i { width: 16px; text-align: center; font-size: 14px; }
</style>

@php
    // routeIs() rather than a path match: Accounts lives on its own route, and
    // Company and Security share a path prefix.
    $onPayroll  = request()->routeIs('settings.index');
    $onCompany  = request()->routeIs('system-settings.about');
    $onAccounts = request()->is('accounts*');
    $onSecurity = request()->routeIs('system-settings.security');
    $onAttendance = request()->routeIs('system-settings.attendance');
@endphp

<nav class="hub-nav">
    <div class="hub-sec">Payroll</div>
    <a class="hub-item {{ $onPayroll ? 'on' : '' }}" href="{{ route('settings.index') }}">
        <i class="fas fa-wallet"></i> Payroll
    </a>
    <a class="hub-item {{ $onAttendance ? 'on' : '' }}" href="{{ route('system-settings.attendance') }}">
        <i class="fas fa-clock"></i> Attendance
    </a>

    <div class="hub-sec">Organization</div>
    <a class="hub-item {{ $onCompany ? 'on' : '' }}" href="{{ route('system-settings.about') }}">
        <i class="fas fa-building"></i> Company
    </a>
    <a class="hub-item {{ $onAccounts ? 'on' : '' }}" href="{{ route('accounts.index') }}">
        <i class="fas fa-users"></i> Accounts &amp; roles
    </a>

    <div class="hub-sec">System</div>
    <a class="hub-item {{ $onSecurity ? 'on' : '' }}" href="{{ route('system-settings.security') }}">
        <i class="fas fa-lock"></i> Security
    </a>
</nav>
