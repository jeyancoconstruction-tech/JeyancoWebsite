{{-- ── System Settings tabs ────────────────────────────────────────────────
     Links rather than client-side panes: the accounts list searches, filters
     and paginates through the query string, which a hidden tab would fight.
     Both pages carry this strip, so Accounts reads as part of System Settings
     without its 300 lines being duplicated into the other controller.

     Self-contained styles, like the page it heads — the settings page's tab
     chrome is defined inside that template and would not reach here. --}}
<style>
    .sys-tabs {
        display: flex; gap: 4px; flex-wrap: wrap;
        border-bottom: 2px solid var(--border); margin: 4px 0 20px;
    }
    .sys-tab {
        display: inline-flex; align-items: center; gap: 8px;
        padding: 12px 20px; border-bottom: 3px solid transparent;
        font-size: 14px; font-weight: 700; text-decoration: none;
        color: var(--text-secondary);
    }
    .sys-tab:hover { color: var(--brand); }
    .sys-tab.active { color: var(--brand); border-bottom-color: var(--brand); }
</style>

<nav class="sys-tabs">
    <a class="sys-tab {{ request()->is('accounts*') ? 'active' : '' }}"
       href="{{ route('accounts.index') }}">
        <i class="fas fa-user-gear"></i> Accounts
    </a>
    <a class="sys-tab {{ request()->is('system-settings*') ? 'active' : '' }}"
       href="{{ route('system-settings.index') }}">
        <i class="fas fa-shield-halved"></i> Security
    </a>
</nav>
