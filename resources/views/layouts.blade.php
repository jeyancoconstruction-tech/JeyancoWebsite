<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>@yield('page_title') | Jeyanco Payroll</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    {{-- The shipped favicon.ico is a zero-byte file, which is why the tab
         showed a blank globe. These are generated from the real mark. --}}
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32.png') }}">
    <link rel="icon" type="image/png" sizes="64x64" href="{{ asset('favicon-64.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('favicon-180.png') }}">

    {{-- Apply the theme BEFORE paint to prevent a flash of the wrong one. The
         fallback is the office's default from System Settings; a viewer who has
         used the toggle has their own choice in this browser, and it wins. --}}
    {{-- 'system' is a preference, not a theme: it means whatever the device
         is set to — Windows, macOS, a phone — and it follows the device when
         that setting changes while the page is open. --}}
    <script>
        (function () {
            var fallback = @json($company?->default_theme ?? 'light');
            var html = document.documentElement;
            var dark = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;

            window.jeyancoTheme = function (pref) {
                if (pref === 'system') return dark && dark.matches ? 'dark' : 'light';
                return pref === 'dark' ? 'dark' : 'light';
            };
            function preference() {
                try { return localStorage.getItem('jeyanco-theme') || fallback; } catch (e) { return fallback; }
            }

            html.setAttribute('data-bs-theme', window.jeyancoTheme(preference()));

            if (dark && dark.addEventListener) {
                dark.addEventListener('change', function () {
                    if (preference() === 'system') html.setAttribute('data-bs-theme', window.jeyancoTheme('system'));
                });
            }
        })();
    </script>

    @if(session('force_theme'))
        {{-- Just signed in. Set the remembered choice as well as the attribute,
             so the rest of the session stays light and the toggle still works
             from there. --}}
        <script>
            (function () {
                var t = @json(session('force_theme'));
                try { localStorage.setItem('jeyanco-theme', t); } catch (e) {}
                document.documentElement.setAttribute('data-bs-theme', window.jeyancoTheme(t));
            })();
        </script>
    @endif

    @if(session('theme_changed'))
        {{-- The default was just changed. The person who changed it has their
             own choice saved in this browser, which outranks it — so take the
             save as choosing it for themselves too, or the page they land on
             looks exactly as it did and the save reads as having failed. --}}
        <script>
            (function () {
                var picked = @json(session('theme_changed'));
                try { localStorage.setItem('jeyanco-theme', picked); } catch (e) {}
                document.documentElement.setAttribute('data-bs-theme', window.jeyancoTheme(picked));
            })();
        </script>
    @endif

    {{-- Open the connections to the CDNs while the page is still being
         read, rather than one after another as each file is reached. --}}
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    {{-- Cache-bust each stylesheet with its file mtime so a new deploy always
         serves fresh CSS instead of a stale browser-cached copy. --}}
    @php $cssv = fn ($f) => asset($f) . '?v=' . (@filemtime(public_path($f)) ?: '1'); @endphp
    <link rel="stylesheet" href="{{ $cssv('layouts.css') }}">
    <link rel="stylesheet" href="{{ $cssv('dashboard.css') }}">
    <link rel="stylesheet" href="{{ $cssv('payroll.css') }}">
    <link rel="stylesheet" href="{{ $cssv('attendance.css') }}">
    <link rel="stylesheet" href="{{ $cssv('ai.css') }}">
    <link rel="stylesheet" href="{{ $cssv('analytics.css') }}">
    @if(file_exists(public_path('login.css')))
        <link rel="stylesheet" href="{{ $cssv('login.css') }}">
    @endif
    <link rel="stylesheet" href="{{ $cssv('dark-mode.css') }}">

    {{-- Enterprise design system — loaded LAST so it owns the final visual language --}}
    <link rel="stylesheet" href="{{ $cssv('enterprise.css') }}">

    {{-- Jeyanco brand design tokens — loaded AFTER enterprise so it owns the final palette --}}
    <link rel="stylesheet" href="{{ $cssv('design-tokens.css') }}">

    {{-- Layout-stability and polish layer — loaded LAST so it settles the
         remaining shifts, z-index clashes and overflow bugs. --}}
    <link rel="stylesheet" href="{{ $cssv('ui-fixes.css') }}">

    {{-- Sidebar density. The rail carries twenty links now; this is the
         arithmetic that keeps them on one screen. --}}
    <link rel="stylesheet" href="{{ $cssv('nav-fit.css') }}">

    {{-- The rail follows the theme, and the containers come down to its
         density. Loaded last so both win their ties. --}}
    <link rel="stylesheet" href="{{ $cssv('density.css') }}">

    {{-- The page header every signed-in page opens with (components/page-header). --}}
    <link rel="stylesheet" href="{{ $cssv('page-header.css') }}">

    {{-- The floating chat's full-screen view (js/chatbot-full.js). --}}
    <link rel="stylesheet" href="{{ $cssv('chatbot-full.css') }}">

    {{-- The loading screen the site opens on. In the head because the check
         inside it has to stamp <html> before the styles below it are read;
         the overlay itself is the first thing in the body. --}}
    @include('_loading_head')

    @include('_notify_styles')

    {{-- The tint on a figure that changed by itself, and the note that says
         the live connection is down. --}}
    <link rel="stylesheet" href="{{ $cssv('live.css') }}">

    @stack('styles')

    {{-- Phones and tablets. Every rule in it is inside a max-width query of
         1024px or less, so a desktop or laptop never matches one; last, so it
         wins its ties with everything above. See mobile.css. --}}
    <link rel="stylesheet" href="{{ $cssv('mobile.css') }}">

    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- Live.on() before live.js has loaded. A page's own script runs while
         the page is still being read; the live connection is opened last, once
         everything it might update exists. This holds the subscriptions until
         then, so a page can say what it watches wherever it is natural to. --}}
    <script>
        window.Live = window.Live || {
            _queued: [],
            on: function (topics, fn) { this._queued.push([topics, fn]); return this; },
            refresh: function () {},
            revisions: function () { return {}; },
            streaming: function () { return false; }
        };
    </script>

    {{-- Pinned to the release @latest resolved to, byte for byte. @latest
         answered with a redirect cached for sixty seconds, so after a minute
         on any page the next one waited on a trip to unpkg before it could
         draw; a versioned file is cached for a year. --}}
    <script src="https://cdn.jsdelivr.net/npm/lucide@1.47.0/dist/umd/lucide.min.js"></script>
</head>

<body class="bg-light">

{{-- The overlay the site opens on, first in the body so it is painted while
     the rest of the page is still arriving behind it. Only on opening the
     site — a click inside it never shows it. See _loading.blade.php and
     _loading_head.blade.php. --}}
@include('_loading')

{{-- One notification system for every page: toasts for what a Create,
     Update or Delete did, and a styled dialog in place of the browser's
     confirm(). Included here, above the content, so window.Notify exists
     before any page script reaches for it. See _notify.blade.php. --}}
@include('_notify')

<!-- SIDEBAR OVERLAY (mobile) -->
<div class="sidebar-overlay" id="sidebar-overlay"></div>

<!-- SIDEBAR -->
<div class="sidebar" id="sidebar">

    <div class="sidebar-top">
        <div class="brand-title mb-4">
            <div class="logo-wrapper">
                <img src="{{ $company?->logoUrl() ?? asset('images/logo-mark.png') }}" class="brand-icon" alt="Logo">
            </div>
            @php
                // The reference stacks the name: first word large, the remainder
                // spaced beneath it. Split the real company name rather than
                // hard-coding, so a renamed company still renders correctly.
                $brandName  = $company?->company_name ?? 'Jeyanco Construction';
                $brandParts = preg_split('/\s+/', trim($brandName), 2);
            @endphp
            <span class="brand-text typing">
                <span class="brand-line1">{{ $brandParts[0] ?? $brandName }}</span>
                @if(!empty($brandParts[1]))
                    <span class="brand-line2">{{ $brandParts[1] }}</span>
                @endif
            </span>
        </div>

        <nav class="nav-menu">
            <script>
                // A rail group's fold (Leave & Advances, Payroll Records, Payroll Settings). Runs
                // before the rail is painted. Open while you are in one of its
                // pages, folded everywhere else; a fold is kept while you move
                // round the section, and arriving from elsewhere slides it open.
                window.jeyancoNavGroup = function (btnId, subId, KEY, IN) {
                    var sub = document.getElementById(subId);
                    var btn = document.getElementById(btnId);
                    if (!sub || !btn) return;
                    function get() { try { return sessionStorage.getItem(KEY); } catch (e) { return null; } }
                    function put(v) { try { if (v === null) { sessionStorage.removeItem(KEY); } else { sessionStorage.setItem(KEY, v); } } catch (e) {} }
                    function set(open) {
                        sub.classList.toggle('folded', !open);
                        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
                    }
                    var was = get();
                    if (!IN) {
                        put(null);              // leaving the section folds them away
                    } else if (was === 'closed') {
                        set(false);             // folded stays folded while you are in it
                    } else if (was === null) {
                        set(false);             // arriving: they slide open
                        requestAnimationFrame(function () {
                            requestAnimationFrame(function () { set(true); });
                        });
                        put('open');
                    }
                    btn.addEventListener('click', function () {
                        var open = sub.classList.contains('folded');
                        set(open);
                        // Inside the section a fold is kept (a filter reloads the
                        // page). Opened elsewhere, the page picked opens with them
                        // already open instead of sliding again.
                        put(open ? 'open' : (IN ? 'closed' : null));
                        if (open && window.jeyancoRailReveal) {
                            setTimeout(function () { window.jeyancoRailReveal(sub, btn, true); }, 300);
                        }
                    });
                };
            </script>

            <div class="menu-section">{{ __('MAIN') }}</div>
            <a class="nav-link {{ request()->is('dashboard') ? 'active' : '' }}" href="{{ url('/dashboard') }}">
                <i data-lucide="layout-dashboard"></i> <span>{{ __('Dashboard') }}</span>
            </a>

            <div class="menu-section">{{ __('WORKFORCE') }}</div>
            <a class="nav-link {{ request()->is('attendance*') ? 'active' : '' }}" href="{{ url('/attendance') }}">
                <i data-lucide="calendar-check"></i> <span>{{ __('Attendance') }}</span>
            </a>
            @php
                // One entry for every worker page: the list, registering,
                // editing and a worker's profile all start and end there. It
                // was called Register & Manage beside an Employee Directory
                // that duplicated it; with the directory retired it carries
                // the plain name, and /employees leads here too.
                $onRegisterHub = request()->is('employees*');
                $pendingKiosk  = \App\Models\Employee::pending()->count();
            @endphp
            <a class="nav-link {{ $onRegisterHub ? 'active' : '' }}" href="{{ route('employees.register') }}">
                <i data-lucide="users"></i> <span>{{ __('Employees') }}</span>
                @if($pendingKiosk > 0)
                    <span class="nav-pending-badge" title="{{ $pendingKiosk }} worker(s) detected by the kiosk awaiting registration">{{ $pendingKiosk }}</span>
                @endif
            </a>

            {{-- Leave & Advances holds its two sections as sub-items, set up
                 like Payroll Records (Michael, 2026-09-26): Cash Advances first,
                 then Leave. They replaced the tab row on the page. Cash Advances
                 shows only to an account that can open it. --}}
            @if(auth()->user()?->canAccessModule('leave'))
                @php
                    $inLeave    = request()->is('leave-advances*');
                    $leaveTab   = request('tab') === 'advances' && auth()->user()->canAccessModule('loans') ? 'advances' : 'leave';
                    $leaveItems = [];
                    if (auth()->user()->canAccessModule('loans')) {
                        $leaveItems['advances'] = [__('Cash Advances'), route('leave.index', ['tab' => 'advances'])];
                    }
                    $leaveItems['leave'] = [__('Leave'), route('leave.index')];
                @endphp
                <button type="button" class="nav-link nav-parent {{ $inLeave ? 'has-on' : '' }}" id="navLeaveBtn" aria-controls="navSubLeave" aria-expanded="{{ $inLeave ? 'true' : 'false' }}">
                    <i data-lucide="calendar-days"></i> <span>{{ __('Leave & Advances') }}</span>
                    <span class="nav-end">
                        <svg class="nav-chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
                    </span>
                </button>
                <div class="nav-sub {{ $inLeave ? '' : 'folded' }}" id="navSubLeave">
                    <div class="nav-sub-in">
                        <div class="nav-sub-list" role="group" aria-label="{{ __('Leave & Advances') }}">
                            @foreach($leaveItems as $key => [$name, $href])
                                <a class="nav-sub-link {{ $inLeave && $leaveTab === $key ? 'on' : '' }}" href="{{ $href }}" @if($inLeave && $leaveTab === $key) aria-current="page" @endif>{{ $name }}</a>
                            @endforeach
                        </div>
                    </div>
                </div>
                <script>jeyancoNavGroup('navLeaveBtn', 'navSubLeave', 'jeyanco-nav-leave', {{ $inLeave ? 'true' : 'false' }});</script>
            @endif

            <div class="menu-section">{{ __('PROJECT') }}</div>
            <a class="nav-link {{ request()->is('sites*') ? 'active' : '' }}" href="{{ route('sites.index') }}">
                <i data-lucide="map-pin"></i> <span>{{ __('Sites') }}</span>
            </a>

            <div class="menu-section">{{ __('PAYROLL') }}</div>
            @php
                $onRecords = (request()->is('payroll*') || request()->is('reports*') || request()->is('payslip*'))
                           && ! request()->is('payroll-reports*') && ! request()->is('payslips*');
                $onRemit   = request()->is('remittances*');
                // What is due or overdue, from the months the tracker last
                // worked out; nothing is priced to draw the sidebar.
                $remitDue  = app(\App\Services\RemittanceTracker::class)->badge();
                // Payroll Reports is the third page of the group (2026-09-26).
                $canReports = (bool) auth()->user()?->canAccessModule('payroll-reports');
                $onReports  = $canReports && request()->is('payroll-reports*');
                $inRecords  = $onRecords || $onRemit || $onReports;
            @endphp
            {{-- Payroll Records holds its own pages, set up as Michael's
                 jeyanco-sidebar-submenu mockup: the parent is a toggle with a
                 chevron and never turns solid blue; only the open page is lit,
                 with a blue marker on the guide line. They are open while you
                 are in one of them and folded everywhere else, where a red dot
                 on the parent says something inside is due. --}}
            <button type="button" class="nav-link nav-parent {{ $inRecords ? 'has-on' : '' }}" id="navRecordsBtn" aria-controls="navSubRecords" aria-expanded="{{ $inRecords ? 'true' : 'false' }}">
                <i data-lucide="receipt"></i> <span>{{ __('Payroll Records') }}</span>
                <span class="nav-end">
                    @if($remitDue > 0)
                        <span class="nav-dot" title="{{ $remitDue }} {{ __('remittance(s) to remit') }}"></span>
                    @endif
                    <svg class="nav-chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
                </span>
            </button>
            <div class="nav-sub {{ $inRecords ? '' : 'folded' }}" id="navSubRecords">
                <div class="nav-sub-in">
                    <div class="nav-sub-list" role="group" aria-label="{{ __('Payroll Records') }}">
                        <a class="nav-sub-link {{ $onRecords ? 'on' : '' }}" href="{{ url('/payroll-records') }}" @if($onRecords) aria-current="page" @endif>{{ __('Records') }}</a>
                        <a class="nav-sub-link {{ $onRemit ? 'on' : '' }}" href="{{ route('remittances.index') }}" @if($onRemit) aria-current="page" @endif>
                            <span>{{ __('Remittance tracker') }}</span>
                            @if($remitDue > 0)
                                <span class="nav-sub-badge" title="{{ $remitDue }} {{ __('remittance(s) to remit') }}">{{ $remitDue }}</span>
                            @endif
                        </a>
                        @if($canReports)
                            <a class="nav-sub-link {{ $onReports ? 'on' : '' }}" href="{{ route('payroll-reports.index') }}" @if($onReports) aria-current="page" @endif>{{ __('Reports') }}</a>
                        @endif
                    </div>
                </div>
            </div>
            <script>jeyancoNavGroup('navRecordsBtn', 'navSubRecords', 'jeyanco-nav-records', {{ $inRecords ? 'true' : 'false' }});</script>
            {{-- Payslips are off the rail: they open from their payroll run.
                 Workers have no web account — they use the kiosk. --}}
            {{-- Admin only, like the rest of the settings page it opens. It sits
                 under Payroll Records rather than in SYSTEM because that is what
                 it configures. --}}
            @if(auth()->user()?->isAdmin())
                {{-- Its four sections as sub-items, set up like Payroll Records
                     (Michael, 2026-09-26): they replaced the tab row on the page.
                     On the settings page itself a pick switches the section in
                     place (settings/index), as the tabs did. --}}
                @php
                    $inSettings = request()->is('settings') || request()->is('settings/*');
                    $setTab     = in_array(request('tab'), ['attendance', 'labor', 'holiday'], true) ? request('tab') : 'payroll';
                    $setItems   = [
                        'payroll'    => __('Multipliers & Deductions'),
                        'attendance' => __('Work Schedule'),
                        'labor'      => __('Labor Types'),
                        'holiday'    => __('Holidays'),
                    ];
                @endphp
                <button type="button" class="nav-link nav-parent {{ $inSettings ? 'has-on' : '' }}" id="navSettingsBtn" aria-controls="navSubSettings" aria-expanded="{{ $inSettings ? 'true' : 'false' }}">
                    <i data-lucide="settings"></i> <span>{{ __('Payroll Settings') }}</span>
                    <span class="nav-end">
                        <svg class="nav-chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
                    </span>
                </button>
                <div class="nav-sub {{ $inSettings ? '' : 'folded' }}" id="navSubSettings">
                    <div class="nav-sub-in">
                        <div class="nav-sub-list" role="group" aria-label="{{ __('Payroll Settings') }}">
                            @foreach($setItems as $key => $name)
                                <a class="nav-sub-link {{ $inSettings && $setTab === $key ? 'on' : '' }}" href="{{ route('settings.index', ['tab' => $key]) }}" data-settings-pane="{{ $key }}" @if($inSettings && $setTab === $key) aria-current="page" @endif>{{ $name }}</a>
                            @endforeach
                        </div>
                    </div>
                </div>
                <script>jeyancoNavGroup('navSettingsBtn', 'navSubSettings', 'jeyanco-nav-settings', {{ $inSettings ? 'true' : 'false' }});</script>
            @endif

            <div class="menu-section">{{ __('INSIGHTS') }}</div>
            <a class="nav-link {{ request()->is('analytics*') ? 'active' : '' }}" href="{{ url('/analytics') }}">
                <i data-lucide="bar-chart-3"></i> <span>{{ __('Analytics') }}</span>
            </a>
            {{-- Payroll Reports moved under Payroll Records (2026-09-26). --}}

            @if(! auth()->user()?->isAdmin() && auth()->user()?->canAccessModule('devices'))
                {{-- A Site Supervisor never sees the admin SYSTEM block below,
                     but does watch the kiosk on their own site. --}}
                <div class="menu-section">{{ __('SYSTEM') }}</div>
                <a class="nav-link {{ request()->is('device-monitoring*') ? 'active' : '' }}" href="{{ route('devices.index') }}">
                    <i data-lucide="monitor-smartphone"></i> <span>{{ __('Device Monitoring') }}</span>
                </a>
            @endif

            @if(auth()->user()?->isAdmin())
                {{-- Creating and editing an account belong to Users & Roles —
                     its list, its Create account button, the form's own
                     breadcrumb — so that entry stays lit on either. --}}
                <div class="menu-section">{{ __('SYSTEM') }}</div>
                <a class="nav-link {{ request()->is('users-roles*') || request()->is('accounts*') ? 'active' : '' }}" href="{{ route('users-roles.index') }}">
                    <i data-lucide="shield-check"></i> <span>{{ __('Users & Roles') }}</span>
                </a>
                @if(auth()->user()?->canAccessModule('devices'))
                    <a class="nav-link {{ request()->is('device-monitoring*') ? 'active' : '' }}" href="{{ route('devices.index') }}">
                        <i data-lucide="monitor-smartphone"></i> <span>{{ __('Device Monitoring') }}</span>
                    </a>
                @endif
                <a class="nav-link {{ request()->is('system-settings*') ? 'active' : '' }}" href="{{ route('system-settings.about') }}">
                    <i data-lucide="sliders-horizontal"></i> <span>{{ __('System Settings') }}</span>
                </a>
            @endif

        </nav>
        <script>
            // The rows keep their size when a group opens (Michael, 2026-09-26):
            // the rows under it move down and the rail scrolls, instead of every
            // row shrinking to make room. So on a desktop the row height is
            // measured with every group folded, the way the rail fills its
            // height, and pinned; measured again only when the window resizes.
            (function () {
                var nav  = document.querySelector('.nav-menu');
                var rail = nav && nav.closest('.sidebar-top');
                if (!nav || !rail) return;
                var wide = window.matchMedia ? window.matchMedia('(min-width: 1025px)') : { matches: true };

                function measure() {
                    var link = nav.querySelector('.nav-link');
                    // Every group folded, and no transitions: a row animates
                    // all its properties, and read mid-way it is the wrong size.
                    nav.classList.add('rows-measure');
                    nav.classList.remove('rows-set');
                    nav.style.removeProperty('--nav-row');
                    if (wide.matches && link) {            // the phone drawer keeps its own rows
                        var h = link.getBoundingClientRect().height;
                        if (h > 0) {
                            nav.style.setProperty('--nav-row', h + 'px');
                            nav.classList.add('rows-set');
                        }
                    }
                    if (link) link.getBoundingClientRect();   // settle before transitions return
                    nav.classList.remove('rows-measure');
                }

                // Scroll the rail, and only the rail, so a group (or the page
                // lit in it) is in view, without losing its toggle off the top.
                window.jeyancoRailReveal = function (el, keep, smooth) {
                    var r = rail.getBoundingClientRect(), e = el.getBoundingClientRect();
                    var below = e.bottom - (r.bottom - 8);
                    if (below <= 0) return;
                    var room = keep ? keep.getBoundingClientRect().top - (r.top + 8) : below;
                    var by = Math.min(below, Math.max(0, room));
                    if (by > 0) rail.scrollBy({ top: by, behavior: smooth ? 'smooth' : 'auto' });
                };

                measure();
                // The page lit in an open group, once it is open (arriving,
                // the group slides open a moment after the rail is drawn).
                var lit = nav.querySelector('.nav-sub-link.on');
                if (lit) {
                    var group = lit.closest('.nav-sub');
                    var show = function () { if (!group.classList.contains('folded')) window.jeyancoRailReveal(lit, null, false); };
                    if (group.classList.contains('folded')) { setTimeout(show, 340); } else { show(); }
                }

                // Again once everything has loaded (the loading screen, fonts),
                // and whenever the window is resized.
                window.addEventListener('load', measure);
                var t;
                window.addEventListener('resize', function () {
                    clearTimeout(t);
                    t = setTimeout(measure, 120);
                });
            })();
        </script>
    </div>

</div>

<div class="main-content">

    <div class="topbar d-flex justify-content-between align-items-center px-4">

        <div class="d-flex align-items-center gap-3">
            <button class="sidebar-toggle" id="sidebar-toggle" aria-label="{{ __('Toggle sidebar') }}">
                <i data-lucide="menu"></i>
            </button>
            <div class="status-pill d-none d-lg-flex">
                <div class="dot pulse"></div>
                <span>{{ __('SYSTEM LIVE') }}</span>
            </div>
            <div class="v-divider"></div>
            <div>
                <h4 class="page-main-title">@yield('page_title', 'Dashboard')</h4>
                <div class="topbar-breadcrumb">
                    <span>{{ __('Jeyanco') }}</span> <i data-lucide="chevron-right"></i> <span class="active">{{ __('Control Panel') }}</span>
                </div>
            </div>
        </div>

        <div class="d-flex align-items-center gap-4">
            <div class="search-container d-none d-md-flex" style="position: relative;">
                <i data-lucide="search"></i>
                <input type="text" id="global-search-input" placeholder="{{ __('Search data...') }}" autocomplete="off">
                <div id="search-suggestions" class="search-suggestions-dropdown"></div>
                <kbd>⌘ K</kbd>
            </div>

            @include('partials.guide')

            <button class="theme-switch" id="themeToggle" type="button" role="switch" aria-label="{{ __('Toggle dark mode') }}" title="{{ __('Toggle dark / light mode') }}">
                <span class="ts-knob">
                    <i data-lucide="sun" class="ts-sun"></i>
                    <i data-lucide="moon" class="ts-moon"></i>
                </span>
            </button>

            {{-- ── Notification Bell ──────────────────────────────────────── --}}
            <div class="notif-wrapper" id="notifWrapper">
                <i data-lucide="bell"></i>
                <span class="notif-badge" id="notifBadge" style="display:none;"></span>

                {{-- Dropdown panel --}}
                <div class="notif-dropdown" id="notifDropdown">
                    <div class="notif-dd-header">
                        <span class="notif-dd-title">{{ __('Notifications') }}</span>
                        <div class="notif-dd-actions">
                            <button class="notif-dd-mark-all" id="notifMarkAll" type="button">{{ __('Mark all read') }}</button>
                            <button class="notif-dd-delete-all" id="notifDeleteAll" type="button">{{ __('Delete all') }}</button>
                        </div>
                    </div>
                    <div class="notif-dd-list" id="notifList">
                        <div class="notif-dd-empty">{{ __('Loading…') }}</div>
                    </div>
                </div>
            </div>

            <div class="dropdown">
                <div class="profile-capsule" data-bs-toggle="dropdown">
                    {{-- The initial is rendered here rather than fetched as a
                         picture of a letter from ui-avatars.com. That request
                         went out on every page load, and any refresh where it
                         was slow, blocked or offline left the profile blank. --}}
                    <div class="avatar-box avatar-letter" aria-hidden="true">
                        {{ mb_strtoupper(mb_substr(auth()->user()->name ?? 'A', 0, 1)) }}
                    </div>
                    <div class="profile-info d-none d-md-block">
                        <span class="u-name">{{ auth()->user()->name ?? 'ADMIN123' }}</span>
                        <span class="u-role">{{ auth()->user()?->role_label ?? 'Staff' }}</span>
                    </div>
                    <i data-lucide="chevron-down" class="chevron"></i>
                </div>
                <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                    @if(auth()->user()?->isAdmin())
                        <li>
                            <a class="dropdown-item" href="{{ route('accounts.index') }}">
                                <i data-lucide="user-cog"></i> {{ __('Manage Accounts') }}
                            </a>
                        </li>
                    @endif
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form action="{{ route('logout') }}" method="POST">
                            @csrf
                            <button class="dropdown-item text-danger" type="submit"><i data-lucide="power"></i> {{ __('Logout') }}</button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <!-- PAGE CONTENT -->
    <div class="container-fluid py-4">
        @yield('content')
    </div>

</div>

<!-- FLOATING CHATBOT -->
<button id="chatbot-fab" class="chatbot-fab" title="{{ __('Chat with Jeyanco AI · drag to move') }}">
    <i class="fas fa-robot"></i>
    <span class="fab-pulse-ring"></span>
</button>

{{-- Behind the chat in full screen: the page it was opened on, blurred. --}}
<div id="chatbot-backdrop" class="chatbot-backdrop" hidden></div>

<div id="chatbot-window" class="chatbot-window" role="dialog" aria-label="{{ __('Jeyanco AI') }}">
    <div class="chatbot-header">
        <div class="chatbot-header-left">
            <div class="chatbot-avatar-wrap">
                <i class="fas fa-robot"></i>
                <span class="cb-status-dot"></span>
            </div>
            <div>
                <span class="chatbot-name">{{ __('Jeyanco AI') }}</span>
                <span class="chatbot-status-text">{{ __('Online • Ready to help') }}</span>
            </div>
        </div>
        <div class="chatbot-header-btns">
            <button id="chatbot-prompts-btn" class="cb-icon-btn cb-full-only" type="button" title="{{ __('Quick prompts') }}" aria-controls="cb-prompts" aria-pressed="true"><i class="fas fa-list-ul"></i></button>
            <button id="chatbot-full-btn" class="cb-icon-btn" type="button" title="{{ __('Full screen') }}" data-exit="{{ __('Exit full screen') }}" aria-pressed="false"><i class="fas fa-expand"></i></button>
            <button id="chatbot-minimize-btn" class="cb-icon-btn" title="{{ __('Close') }}"><i class="fas fa-times"></i></button>
        </div>
    </div>

    <div class="chatbot-body">
    {{-- Full screen only: the Jeyanco AI page's quick prompts. --}}
    <aside class="prompts-panel cb-prompts" id="cb-prompts" aria-label="{{ __('Quick prompts') }}">
        @include('partials.ai-prompts')
    </aside>

    <div class="chatbot-main">
    <div id="chatbot-messages" class="chatbot-messages">
        <div class="cb-welcome">
            <div class="cb-welcome-icon"><i class="fas fa-robot"></i></div>
            <div class="cb-welcome-text">
                <p><strong>{{ __('Mabuhay!') }}</strong> {{ __('I\'m Jeyanco AI') }}</p>
                <p>{{ __('Ask me about payroll, attendance, employees, and workforce data.') }}</p>
            </div>
        </div>
        <div class="cb-quick-chips" id="cb-quick-chips">
            <button class="cb-chip" data-msg="Total employees">{{ __('👥 Employees') }}</button>
            <button class="cb-chip" data-msg="Dashboard overview">{{ __('📊 Overview') }}</button>
            <button class="cb-chip" data-msg="Attendance today">{{ __('✅ Attendance') }}</button>
            <button class="cb-chip" data-msg="help">{{ __('❓ Help') }}</button>
        </div>
    </div>

    <div class="chatbot-footer">
        {{-- New chat sits right before the question, not up in the header. --}}
        <div class="cb-compose">
            <button id="chatbot-new-btn" class="cb-new-btn" type="button" title="{{ __('Start a new chat') }}"><i class="fas fa-pen-to-square"></i><span>{{ __('New chat') }}</span></button>
            <div class="cb-input-row">
                <input type="text" id="chatbot-input" class="cb-input" placeholder="{{ __('Ask something...') }}" autocomplete="off">
                <button id="chatbot-send" class="cb-send-btn"><i class="fas fa-paper-plane"></i></button>
            </div>
        </div>
        <p class="cb-hint">{{ __('Press Enter to send  ·  Powered by Jeyanco Intelligence') }}</p>
    </div>
    </div>{{-- .chatbot-main --}}
    </div>{{-- .chatbot-body --}}
</div>

{{-- The button can be dragged out of the way while the page is open; every
     page load puts it back in its corner. --}}
<script src="{{ asset('js/chatbot-move.js') }}?v={{ @filemtime(public_path('js/chatbot-move.js')) ?: '1' }}"></script>
{{-- The chat's full-screen view: the window grows into the Jeyanco AI page
     over the page it was opened on, blurred behind it. --}}
<script src="{{ asset('js/chatbot-full.js') }}?v={{ @filemtime(public_path('js/chatbot-full.js')) ?: '1' }}"></script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // Initialize lucide icons
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }

    document.addEventListener('shown.bs.modal', function () {
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    });

    // Floating Chatbot
    try {
        const fab = document.getElementById('chatbot-fab');
        const chatWindow = document.getElementById('chatbot-window');
        const minimizeBtn = document.getElementById('chatbot-minimize-btn');
        const newChatBtn = document.getElementById('chatbot-new-btn');
        const chatbotInput = document.getElementById('chatbot-input');
        const chatbotSend = document.getElementById('chatbot-send');
        const messagesContainer = document.getElementById('chatbot-messages');

        function escapeHtml(text) {
            const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
            return String(text).replace(/[&<>"']/g, m => map[m]);
        }

        function getTime() {
            return new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
        }

        function appendUserMsg(text) {
            const d = document.createElement('div');
            d.className = 'chatbot-msg user';
            d.innerHTML = `<div class="msg-content"><div class="msg-bubble">${escapeHtml(text)}</div><div class="msg-time">${getTime()}</div></div><div class="msg-avatar"><i class="fas fa-user"></i></div>`;
            messagesContainer.appendChild(d);
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }

        function showTyping() {
            const d = document.createElement('div');
            d.className = 'chatbot-msg ai';
            d.id = 'cb-typing';
            d.innerHTML = `<div class="msg-avatar"><i class="fas fa-robot"></i></div><div class="typing-dots"><span></span><span></span><span></span></div>`;
            messagesContainer.appendChild(d);
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }

        function removeTyping() {
            const el = document.getElementById('cb-typing');
            if (el) el.remove();
        }

        function appendAiMsg(text) {
            const d = document.createElement('div');
            d.className = 'chatbot-msg ai';
            d.innerHTML = `<div class="msg-avatar"><i class="fas fa-robot"></i></div><div class="msg-content"><div class="msg-bubble">${escapeHtml(text)}</div><div class="msg-time">${getTime()}</div></div>`;
            messagesContainer.appendChild(d);
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }

        function sendMessage(message) {
            appendUserMsg(message);
            showTyping();
            {{-- Absolute, hindi relative. Ang 'ai/chat' ay sinusukat mula sa
                 kasalukuyang path: sa /dashboard ito ay /ai/chat at gumagana,
                 pero sa /employees/register ito ay /employees/ai/chat — 404,
                 kaya tahimik na hindi gumagana ang chatbot sa mga page na may
                 malalim na URL. --}}
            fetch('{{ route('ai.chat') }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') },
                body: JSON.stringify({ message: message })
            })
            .then(r => r.json())
            .then(data => { removeTyping(); appendAiMsg(data.reply ?? 'Sorry, I could not process that.'); })
            .catch(err => { console.error('Chatbot error:', err); removeTyping(); appendAiMsg('Sorry, I encountered an error. Please try again.'); });
        }

        function attachChipListeners() {
            document.querySelectorAll('.cb-chip').forEach(chip => {
                chip.addEventListener('click', function() {
                    const msg = this.getAttribute('data-msg');
                    const chips = document.getElementById('cb-quick-chips');
                    if (chips) chips.remove();
                    sendMessage(msg);
                });
            });
        }

        function resetChat() {
            messagesContainer.innerHTML = `
                <div class="cb-welcome">
                    <div class="cb-welcome-icon"><i class="fas fa-robot"></i></div>
                    <div class="cb-welcome-text">
                        <p><strong>Mabuhay!</strong> I'm Jeyanco AI</p>
                        <p>Ask me about payroll, attendance, employees, and workforce data.</p>
                    </div>
                </div>
                <div class="cb-quick-chips" id="cb-quick-chips">
                    <button class="cb-chip" data-msg="Total employees">&#128101; Employees</button>
                    <button class="cb-chip" data-msg="Dashboard overview">&#128202; Overview</button>
                    <button class="cb-chip" data-msg="Attendance today">&#9989; Attendance</button>
                    <button class="cb-chip" data-msg="help">&#10067; Help</button>
                </div>`;
            attachChipListeners();
        }

        // One conversation, whatever size the window is: the full-screen
        // prompts (js/chatbot-full.js) send through the same function.
        window.jeyancoChat = window.jeyancoChat || {};
        window.jeyancoChat.send = function (msg) {
            const chips = document.getElementById('cb-quick-chips');
            if (chips) chips.remove();
            sendMessage(msg);
        };

        if (fab && chatWindow) {
            attachChipListeners();

            fab.addEventListener('click', function() {
                chatWindow.classList.toggle('open');
                if (chatWindow.classList.contains('open')) chatbotInput.focus();
            });

            minimizeBtn.addEventListener('click', function() {
                chatWindow.classList.remove('open');
            });

            // A fresh chat, ready for the next question (not on a phone,
            // where focusing would throw the keyboard up).
            newChatBtn.addEventListener('click', function () {
                resetChat();
                if (window.innerWidth > 768) chatbotInput.focus();
            });

            chatbotSend.addEventListener('click', function() {
                const msg = chatbotInput.value.trim();
                if (msg) { chatbotInput.value = ''; sendMessage(msg); }
            });

            chatbotInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    const msg = chatbotInput.value.trim();
                    if (msg) { chatbotInput.value = ''; sendMessage(msg); }
                }
            });
        }
    } catch (error) {
        console.error('Chatbot initialization error:', error);
    }

    // ===== GLOBAL SEARCH FUNCTIONALITY =====
    const globalSearchInput = document.getElementById('global-search-input');
    const suggestionsDropdown = document.getElementById('search-suggestions');
    let searchTimeout;

    if (globalSearchInput) {
        // Show suggestions on input
        globalSearchInput.addEventListener('input', function() {
            const query = this.value.trim();
            
            clearTimeout(searchTimeout);
            
            if (query.length < 2) {
                suggestionsDropdown.innerHTML = '';
                suggestionsDropdown.style.display = 'none';
                return;
            }

            searchTimeout = setTimeout(() => {
                fetch(`/search/suggestions?q=${encodeURIComponent(query)}`)
                   .then(async response => {
                        const data = await response.json();

                        if (!response.ok) {
                            throw new Error(data.reply || 'Server error');
                        }

                        return data;
                    })
                    .then(data => {
                        if (data.length > 0) {
                            let html = '<div style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); max-height: 400px; overflow-y: auto; z-index: 1000;">';
                            
                            let currentCategory = '';
                            data.forEach(item => {
                                if (item.category !== currentCategory) {
                                    if (currentCategory !== '') {
                                        html += '<div style="border-top: 1px solid #f0f0f0;"></div>';
                                    }
                                    html += `<div style="padding: 8px 12px; font-size: 12px; font-weight: 600; color: #666; text-transform: uppercase; background: #f8fafc;">${item.category}</div>`;
                                    currentCategory = item.category;
                                }
                                
                                html += `<a href="${item.url}" style="display: block; padding: 10px 12px; color: inherit; text-decoration: none; transition: background 0.2s;" onmouseover="this.style.background='#f0f0f0'" onmouseout="this.style.background='transparent'">
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <i data-lucide="${item.icon}" style="width: 16px; height: 16px; color: #1e3a8a;"></i>
                                        <span>${item.text}</span>
                                    </div>
                                </a>`;
                            });
                            
                            html += `<div style="border-top: 1px solid #f0f0f0; padding: 8px 12px;">
                                <a href="/search?q=${encodeURIComponent(query)}" style="display: block; color: #1e3a8a; text-decoration: none; font-weight: 600; font-size: 12px; transition: all 0.2s;" onmouseover="this.style.opacity='0.8'" onmouseout="this.style.opacity='1'">
                                    <i data-lucide="arrow-right" style="width: 14px; height: 14px; display: inline; margin-right: 6px;"></i>View All Results
                                </a>
                            </div>`;
                            
                            html += '</div>';
                            
                            suggestionsDropdown.innerHTML = html;
                            suggestionsDropdown.style.display = 'block';
                            if (typeof lucide !== 'undefined') {
                                lucide.createIcons();
                            }
                        } else {
                            suggestionsDropdown.innerHTML = '<div style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; text-align: center; color: #999;">No results found</div>';
                            suggestionsDropdown.style.display = 'block';
                        }
                    })
                    .catch(error => console.error('Search error:', error));
            }, 300);
        });

        // Submit search on Enter
        globalSearchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                const query = this.value.trim();
                if (query.length >= 2) {
                    window.location.href = `/search?q=${encodeURIComponent(query)}`;
                }
            }
        });

        // Close suggestions when clicking outside
        document.addEventListener('click', function(e) {
            if (e.target !== globalSearchInput && !globalSearchInput.contains(e.target)) {
                suggestionsDropdown.style.display = 'none';
            }
        });

        // Focus search with "/" key
        document.addEventListener('keydown', function(e) {
            if ((e.key === '/' || e.key === 'k') && (e.ctrlKey || e.metaKey) && !globalSearchInput.matches(':focus')) {
                e.preventDefault();
                globalSearchInput.focus();
            }
        });
    }

    // Add CSS for search suggestions
    const searchStyle = document.createElement('style');
    searchStyle.textContent = `
        .search-suggestions-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            margin-top: 4px;
            display: none;
            z-index: 1000;
        }

        .search-suggestions-dropdown a {
            cursor: pointer;
        }
    `;
    document.head.appendChild(searchStyle);

    // Ensure lucide icons are always rendered at the end
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    });

    // Mobile sidebar toggle
    (function() {
        const toggle  = document.getElementById('sidebar-toggle');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebar-overlay');
        if (!toggle || !sidebar || !overlay) return;

        function openSidebar() {
            if (sidebar.classList.contains('active')) return;
            sidebar.classList.add('active');
            overlay.classList.add('active');
            // <html> is the scrolling element (body's overflow did nothing but
            // clip). ui-fixes.js reserves the scrollbar gutter, so locking it
            // no longer moves the page sideways.
            if (window.jeyancoUI) window.jeyancoUI.lockScroll();
        }
        function closeSidebar() {
            if (!sidebar.classList.contains('active')) return;
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
            if (window.jeyancoUI) window.jeyancoUI.unlockScroll();
        }

        toggle.addEventListener('click', function() {
            sidebar.classList.contains('active') ? closeSidebar() : openSidebar();
        });
        overlay.addEventListener('click', closeSidebar);

        // Close on nav-link click (mobile UX)
        sidebar.querySelectorAll('.nav-link, .nav-sub-link').forEach(function(link) {
            link.addEventListener('click', function(e) {
                // Payroll Records folds and unfolds its pages; it goes nowhere.
                if (e.defaultPrevented || link.hasAttribute('aria-expanded')) return;
                if (window.innerWidth <= 1024) closeSidebar();
            });
        });
    })();

    // Theme toggle (dark / light) — init already ran in <head>
    //
    // The new theme grows out of the button as a circle until it covers the
    // whole page — sidebar, header, content, any open dialog — using the View
    // Transitions API: the browser snapshots the page, the theme is applied,
    // and the new snapshot is revealed through a growing clip-path. A browser
    // without the API fades the colours instead; somebody who has asked for
    // less motion gets the switch at once. What is switched, and how it is
    // remembered, is exactly as before.
    (function() {
        const html   = document.documentElement;
        const toggle = document.getElementById('themeToggle');
        if (!toggle) return;

        function syncAria() {
            toggle.setAttribute('aria-checked', html.getAttribute('data-bs-theme') === 'dark' ? 'true' : 'false');
        }
        syncAria();

        function applyTheme(next) {
            html.setAttribute('data-bs-theme', next);
            try { localStorage.setItem('jeyanco-theme', next); } catch (e) {}
            syncAria();
        }

        // One switch at a time: a second click mid-reveal would start a
        // transition on top of one still running.
        let switching = false;

        toggle.addEventListener('click', function() {
            if (switching) return;

            const next   = html.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
            const reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

            if (reduce) { applyTheme(next); return; }

            if (!document.startViewTransition) {
                // Colours ease across instead, only for the length of the switch
                // so it never slows a hover anywhere else.
                switching = true;
                html.classList.add('theme-transition');
                applyTheme(next);
                window.setTimeout(function() {
                    html.classList.remove('theme-transition');
                    switching = false;
                }, 500);
                return;
            }

            // Centred on the button, reaching the farthest corner of the screen.
            const box    = toggle.getBoundingClientRect();
            const x      = box.left + box.width / 2;
            const y      = box.top + box.height / 2;
            const radius = Math.hypot(Math.max(x, window.innerWidth - x), Math.max(y, window.innerHeight - y));

            switching = true;
            const transition = document.startViewTransition(function() { applyTheme(next); });

            transition.ready.then(function() {
                html.animate(
                    { clipPath: ['circle(0px at ' + x + 'px ' + y + 'px)', 'circle(' + radius + 'px at ' + x + 'px ' + y + 'px)'] },
                    { duration: 650, easing: 'cubic-bezier(.65,0,.35,1)', pseudoElement: '::view-transition-new(root)' }
                );
            }).catch(function() {});

            transition.finished.finally(function() { switching = false; });
        });
    })();
</script>  

@stack('scripts')

{{-- Shared UI behaviour fixes: date fields open on click, scroll locks that
     cannot shift the layout, maps that re-measure, the Site Tracker's
     minimise / maximise and the clock's month popover. --}}
<script src="{{ asset('js/ui-fixes.js') }}?v={{ @filemtime(public_path('js/ui-fixes.js')) ?: '1' }}"></script>

{{-- Lists drawn as cards on a phone name each line after its column; this
     copies the column headings onto the cells. Does nothing on a screen
     wider than a phone. --}}
<script src="{{ asset('js/mobile.js') }}?v={{ @filemtime(public_path('js/mobile.js')) ?: '1' }}"></script>

{{-- ── Live updates ────────────────────────────────────────────────────────
     Every page keeps one connection open and patches in what changes, so
     attendance filed at the site, an employee registered on the kiosk or a
     payment recorded at the next desk appears here without a refresh.

     The revisions are the page's own timestamp: the contents below were true
     as of these numbers, so the first thing the connection says is only what
     has happened since. Loaded last, after every page's own script, so a page
     that subscribes has had its chance to define what it does. --}}
<script>
    window.LiveConfig = {
        streamUrl:    @json(route('live.stream')),
        revisionsUrl: @json(route('live.revisions')),
        revisions:    @json(\App\Support\Live::revisions()),
        stream:       @json((bool) config('live.stream', true) && \App\Support\Live::available()),
        pollMs:       @json((int) config('live.poll_ms', 8000)),
    };
</script>
<script src="{{ asset('js/live.js') }}?v={{ @filemtime(public_path('js/live.js')) ?: '1' }}"></script>

{{-- ── Notification Bell — CSS ─────────────────────────────────────────────── --}}
<style>
        /* Initials avatar, drawn instead of downloaded. */
        .avatar-letter {
            display: flex; align-items: center; justify-content: center;
            width: 36px; height: 36px; flex: none;
            background: var(--brand); color: #fff;
            font-size: 14px; font-weight: 700; line-height: 1;
        }

/* Wrapper — position context for dropdown */
.notif-wrapper {
    position: relative;
    width: 38px; height: 38px;
    display: flex; align-items: center; justify-content: center;
    border-radius: 10px; cursor: pointer;
    border: 1px solid #e2e8f0; background: #f8fafc;
    color: #475569; transition: background .15s, border-color .15s;
    flex-shrink: 0;
}
.notif-wrapper:hover,
.notif-wrapper.open { background: #f1f5f9; border-color: #cbd5e1; }
.notif-wrapper.open { color: #1e3a8a; border-color: #bfdbfe; background: #eff6ff; }

.notif-badge {
    position: absolute; top: 5px; right: 5px;
    min-width: 16px; height: 16px; border-radius: 8px;
    background: #dc2626; color: #fff;
    font-size: 10px; font-weight: 700; line-height: 16px;
    text-align: center; padding: 0 4px;
    border: 2px solid #fff;
    animation: notifPop .25s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}

/* Dropdown panel */
.notif-dropdown {
    display: none; position: absolute;
    top: calc(100% + 10px); right: 0;
    width: 340px; max-height: 480px;
    background: #fff; border: 1px solid #e2e8f0;
    border-radius: 14px; box-shadow: 0 16px 48px rgba(0,0,0,.13);
    z-index: 9000; overflow: hidden;
    flex-direction: column;
}
.notif-wrapper.open .notif-dropdown { display: flex; }

.notif-dd-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 14px 16px 12px; border-bottom: 1px solid #f1f5f9;
    flex-shrink: 0;
}
.notif-dd-title {
    font-size: 13px; font-weight: 700; color: #0f172a;
}
.notif-dd-actions { display: flex; align-items: center; gap: 10px; }
.notif-dd-mark-all {
    font-size: 11px; font-weight: 600; color: #1e40af;
    background: none; border: none; cursor: pointer; padding: 0;
    transition: color .15s;
}
.notif-dd-mark-all:hover { color: #1e3a8a; }
.notif-dd-delete-all {
    font-size: 11px; font-weight: 600; color: #dc2626;
    background: none; border: none; cursor: pointer; padding: 0;
    transition: color .15s;
}
.notif-dd-delete-all:hover { color: #b91c1c; }

.notif-dd-list {
    overflow-y: auto; flex: 1;
}
.notif-dd-list::-webkit-scrollbar { width: 4px; }
.notif-dd-list::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 4px; }

/* Notification item */
.notif-item {
    display: flex; align-items: flex-start; gap: 11px;
    padding: 12px 16px; cursor: pointer; border-bottom: 1px solid #f8fafc;
    transition: background .1s; text-decoration: none;
}
.notif-item:last-child { border-bottom: none; }
.notif-item:hover { background: #f8fafc; }
.notif-item.unread { background: #fafbff; }
.notif-item.unread:hover { background: #f0f4ff; }

.notif-icon-wrap {
    width: 36px; height: 36px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0; font-size: 14px; color: #fff;
}
.notif-item-body { flex: 1; min-width: 0; }
.notif-item-title {
    font-size: 13px; font-weight: 600; color: #0f172a;
    margin: 0 0 2px; line-height: 1.3;
}
.notif-item-msg {
    font-size: 12px; color: #64748b; margin: 0 0 4px;
    white-space: normal; line-height: 1.4;
}
.notif-item-time { font-size: 11px; color: #94a3b8; }
.notif-unread-dot {
    width: 7px; height: 7px; background: #3b82f6;
    border-radius: 50%; flex-shrink: 0; margin-top: 5px;
}

/* Empty / loading states */
.notif-dd-empty {
    padding: 32px 16px; text-align: center;
    font-size: 13px; color: #94a3b8;
}

/* Dark mode */
[data-bs-theme="dark"] .notif-wrapper {
    background: #151d2e; border-color: #283449; color: #9fb0c7;
}
[data-bs-theme="dark"] .notif-wrapper:hover,
[data-bs-theme="dark"] .notif-wrapper.open {
    background: #1c2740; border-color: #38465e; color: #e8edf5;
}
[data-bs-theme="dark"] .notif-badge { border-color: #151d2e; }
[data-bs-theme="dark"] .notif-dropdown {
    background: #151d2e; border-color: #283449;
    box-shadow: 0 16px 48px rgba(0,0,0,.45);
}
[data-bs-theme="dark"] .notif-dd-header { border-bottom-color: #1c2740; }
[data-bs-theme="dark"] .notif-dd-title  { color: #e8edf5; }
[data-bs-theme="dark"] .notif-item      { border-bottom-color: #1a2336; }
[data-bs-theme="dark"] .notif-item:hover { background: #1c2740; }
[data-bs-theme="dark"] .notif-item.unread { background: #172554; }
[data-bs-theme="dark"] .notif-item.unread:hover { background: #1e3a8a22; }
[data-bs-theme="dark"] .notif-item-title { color: #e8edf5; }
[data-bs-theme="dark"] .notif-item-msg  { color: #9fb0c7; }
[data-bs-theme="dark"] .notif-item-time { color: #6b7d96; }
[data-bs-theme="dark"] .notif-dd-empty  { color: #475569; }
[data-bs-theme="dark"] .notif-dd-list::-webkit-scrollbar-thumb { background: #283449; }

@keyframes notifPop {
    from { transform: scale(0); opacity: 0; }
    to   { transform: scale(1); opacity: 1; }
}

/* Sidebar pending-kiosk badge */
.nav-pending-badge {
    margin-left: auto;
    min-width: 20px; height: 20px; padding: 0 6px;
    border-radius: 10px; background: #f59e0b; color: #fff;
    font-size: 11px; font-weight: 700; line-height: 20px; text-align: center;
}
</style>

{{-- ── Notification Bell — JS ───────────────────────────────────────────────── --}}
<script>
(function () {
    const csrf      = document.querySelector('meta[name="csrf-token"]').content;
    const wrapper   = document.getElementById('notifWrapper');
    const badge     = document.getElementById('notifBadge');
    const list      = document.getElementById('notifList');
    const markAllBtn    = document.getElementById('notifMarkAll');
    const deleteAllBtn  = document.getElementById('notifDeleteAll');

    // ── Toggle open/close ──────────────────────────────────────────────────
    wrapper.addEventListener('click', function (e) {
        // Don't toggle if clicking a notification item or the mark-all button
        if (e.target.closest('.notif-item') || e.target.closest('.notif-dd-mark-all') || e.target.closest('.notif-dd-delete-all')) return;
        const isOpen = wrapper.classList.toggle('open');
        if (isOpen) render();
    });

    document.addEventListener('click', function (e) {
        if (!wrapper.contains(e.target)) wrapper.classList.remove('open');
    });

    // ── Fetch notifications ────────────────────────────────────────────────
    async function fetchNotifications() {
        try {
            const r    = await fetch('/notifications', {
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf }
            });
            return await r.json();
        } catch { return null; }
    }

    // ── Render into dropdown ───────────────────────────────────────────────
    async function render() {
        list.innerHTML = '<div class="notif-dd-empty">Loading…</div>';
        const data = await fetchNotifications();
        if (!data) {
            list.innerHTML = '<div class="notif-dd-empty">Could not load notifications.</div>';
            return;
        }
        updateBadge(data.unread_count);
        if (data.notifications.length === 0) {
            list.innerHTML = '<div class="notif-dd-empty"><i class="fas fa-bell-slash" style="font-size:20px;margin-bottom:8px;display:block;"></i>No notifications yet</div>';
            return;
        }
        list.innerHTML = data.notifications.map(n => `
            <div class="notif-item ${n.read ? '' : 'unread'}" data-id="${n.id}" data-link="${escH(n.link)}">
                <div class="notif-icon-wrap" style="background:${escH(n.color)};">
                    <i class="fas ${escH(n.icon)}"></i>
                </div>
                <div class="notif-item-body">
                    <p class="notif-item-title">${escH(n.title)}</p>
                    <p class="notif-item-msg">${escH(n.message)}</p>
                    <span class="notif-item-time">${escH(n.created_at)}</span>
                </div>
                ${n.read ? '' : '<div class="notif-unread-dot"></div>'}
            </div>`).join('');

        // Click → mark read + navigate
        list.querySelectorAll('.notif-item').forEach(el => {
            el.addEventListener('click', async function () {
                const id   = this.dataset.id;
                const link = this.dataset.link;
                if (!this.classList.contains('read-pending')) {
                    this.classList.add('read-pending');
                    await fetch(`/notifications/${id}/read`, {
                        method: 'PATCH',
                        headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }
                    }).catch(() => {});
                }
                wrapper.classList.remove('open');
                if (link && link !== '#') window.location.href = link;
            });
        });
    }

    // ── Badge update ───────────────────────────────────────────────────────
    function updateBadge(count) {
        if (count > 0) {
            badge.textContent    = count > 99 ? '99+' : count;
            badge.style.display  = 'flex';
            badge.style.alignItems = 'center';
            badge.style.justifyContent = 'center';
        } else {
            badge.style.display = 'none';
        }
    }

    // ── Mark all read ──────────────────────────────────────────────────────
    markAllBtn.addEventListener('click', async function () {
        await fetch('/notifications/read-all', {
            method: 'PATCH',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }
        }).catch(() => {});
        render();
    });

    // ── Delete all ─────────────────────────────────────────────────────────
    deleteAllBtn.addEventListener('click', async function () {
        const ok = await Notify.confirm({
            title:        'Delete all notifications?',
            message:      'The list is cleared for good. This cannot be undone.',
            confirmLabel: 'Delete all',
            tone:         'danger',
        });
        if (!ok) return;
        await fetch('/notifications/delete-all', {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }
        }).catch(() => {});
        render();
    });

    // ── The count on the bell ──────────────────────────────────────────────
    // Asked for when there is a new notification rather than every minute:
    // the feed says when one is written, whoever wrote it.
    async function pollBadge() {
        const data = await fetchNotifications();
        if (data) updateBadge(data.unread_count);
    }

    pollBadge();   // initial load
    Live.on('notifications', pollBadge);

    function escH(s) {
        return String(s ?? '')
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
})();
</script>

</body>
</html>
