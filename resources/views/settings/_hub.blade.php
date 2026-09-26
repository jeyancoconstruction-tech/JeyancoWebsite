{{-- ── Settings hub ────────────────────────────────────────────────────────
     One nav for every System Settings tab: categories down the left, each
     with its current value in a line. Each item is a link to its own URL
     rather than a JS panel — the forms post, and need a real address to come
     back to. "Accounts & roles" opens Users & Roles, the one people screen.

     Payroll and Attendance are not here: they configure pay, so they live on
     the Payroll Settings page with the multipliers they work with. --}}
@php
    $system ??= \App\Models\SystemSetting::current();
    $hub    ??= [
        'accounts' => \App\Models\User::count(),
        'admins'   => \App\Models\User::where('role', \App\Models\User::ROLE_ADMIN)->count(),
    ];
    $minutes = (int) $system->session_timeout_minutes;
    $session = $minutes % 60 === 0 ? ($minutes / 60) . '-h' : $minutes . '-min';

    $onCompany    = request()->routeIs('system-settings.about');
    $onSecurity   = request()->routeIs('system-settings.security');
    $onAppearance = request()->routeIs('system-settings.appearance');
    $onKiosk      = request()->routeIs('system-settings.kiosk');
@endphp

<nav class="st-nav" aria-label="System Settings">
    <span class="sx-label">Organization</span>
    <a class="st-item {{ $onCompany ? 'on' : '' }}" href="{{ route('system-settings.about') }}">
        <span class="ic"><i data-lucide="building-2"></i></span>
        <span><span class="t">Company</span><span class="d">Name, logo, address</span></span>
        @if($onCompany)<span class="dirty" data-hub-dirty hidden></span>@endif
    </a>
    <a class="st-item" href="{{ route('users-roles.index') }}">
        <span class="ic"><i data-lucide="users"></i></span>
        <span><span class="t">Accounts &amp; roles</span><span class="d">{{ $hub['accounts'] }} {{ \Illuminate\Support\Str::plural('account', $hub['accounts']) }} · {{ $hub['admins'] }} {{ \Illuminate\Support\Str::plural('admin', $hub['admins']) }}</span></span>
    </a>

    <span class="sx-label">System</span>
    <a class="st-item {{ $onAppearance ? 'on' : '' }}" href="{{ route('system-settings.appearance') }}">
        <span class="ic"><i data-lucide="palette"></i></span>
        <span><span class="t">Appearance</span><span class="d">{{ match ($system->default_theme) { 'system' => 'Follows the device setting', 'light' => 'Opens in the light theme', default => 'Opens in the dark theme' } }}</span></span>
        @if($onAppearance)<span class="dirty" data-hub-dirty hidden></span>@endif
    </a>
    <a class="st-item {{ $onSecurity ? 'on' : '' }}" href="{{ route('system-settings.security') }}">
        <span class="ic"><i data-lucide="lock"></i></span>
        <span><span class="t">Security</span><span class="d">{{ $session }} sessions · {{ (int) $system->max_login_attempts }} tries</span></span>
        @if($onSecurity)<span class="dirty" data-hub-dirty hidden></span>@endif
    </a>
    <a class="st-item {{ $onKiosk ? 'on' : '' }}" href="{{ route('system-settings.kiosk') }}">
        <span class="ic"><i data-lucide="fingerprint"></i></span>
        <span><span class="t">Kiosk</span><span class="d">{{ $system->kioskMode() === \App\Models\SystemSetting::KIOSK_AUTO ? 'Automatic · scan only' : 'Buttons · TIME IN / OUT' }}</span></span>
        @if($onKiosk)<span class="dirty" data-hub-dirty hidden></span>@endif
    </a>

    <div class="st-nav-foot"><i data-lucide="info"></i><span>Pay, work schedules and holidays live in <a class="sx-link" href="{{ route('settings.index') }}">Payroll Settings</a>.</span></div>
</nav>
