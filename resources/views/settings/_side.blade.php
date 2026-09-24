{{-- The left column of every System Settings tab: the hub nav, and under it
     every value the four tabs hold, so the column is never left half empty
     beside a long form. Each value links to the tab that changes it; the
     current tab's values are marked. Held to the screen's height while the
     form scrolls past (see .st-side in system/_kit). --}}
@php
    $system ??= \App\Models\SystemSetting::current();
    $hub    ??= [
        'accounts' => \App\Models\User::count(),
        'admins'   => \App\Models\User::where('role', \App\Models\User::ROLE_ADMIN)->count(),
    ];
    $mins = fn (int $m) => $m % 60 === 0 ? ($m / 60) . ' h' : $m . ' min';
    $secs = fn (int $s) => $s % 60 === 0 ? ($s / 60) . ' min' : ($s < 60 ? $s . ' s' : intdiv($s, 60) . ' min ' . ($s % 60) . ' s');

    $glance = [
        ['system-settings.about', 'Company', [
            ['Name', $system->company_name ?: '—'],
            ['Address', $system->company_address ?: 'Not set'],
        ]],
        ['system-settings.appearance', 'Appearance', [
            ['Default theme', ucfirst($system->default_theme ?: 'dark')],
        ]],
        ['system-settings.security', 'Security', [
            ['Session', $mins((int) $system->session_timeout_minutes)],
            ['Password', (int) $system->password_min_length . '+ characters'],
            ['Lockout', (int) $system->max_login_attempts . ' tries · ' . $secs((int) $system->lockout_seconds)],
        ]],
        ['system-settings.kiosk', 'Kiosk', [
            ['Mode', $system->kioskMode() === \App\Models\SystemSetting::KIOSK_AUTO ? 'Automatic' : 'Buttons'],
            ['Repeat guard', $secs((int) ($system->kiosk_repeat_guard_seconds ?? 180))],
            ['Back to Attendance', $secs((int) ($system->kiosk_idle_return_seconds ?? 60))],
        ]],
    ];
@endphp

<div class="st-side">
    <div class="st-side-in">
        @include('settings._hub')

        <section class="st-glance" aria-label="Current values">
            <div class="st-glance-head"><span class="sx-label">Current values</span><span class="live">Saved</span></div>
            <div class="st-glance-list">
                @foreach($glance as [$route, $tab, $rows])
                    @foreach($rows as [$label, $value])
                        <a class="st-gl {{ request()->routeIs($route) ? 'on' : '' }}" href="{{ route($route) }}" title="{{ $tab }} · {{ $label }}: {{ $value }}">
                            <span class="k">{{ $label }}</span><span class="v">{{ $value }}</span>
                        </a>
                    @endforeach
                @endforeach
            </div>
            <a class="st-glance-foot sx-link" href="{{ route('audit-logs.index', ['module' => 'Settings', 'range' => 'all']) }}"><i data-lucide="history"></i> Every change in Audit Logs</a>
        </section>
    </div>
</div>
