@extends('layouts')

@section('page_title', 'Dashboard')

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<style>
    .dashboard-wrapper { padding-top: 0; padding-bottom: 40px; }

    /* ── Welcome banner ─────────────────────── */
    .welcome-banner {
        position: relative; overflow: hidden;
        border-radius: var(--radius-lg);
        padding: 22px 26px;
        display: flex; align-items: center; justify-content: space-between;
        gap: 20px; flex-wrap: wrap;
        background: #071A33 url("{{ asset('images/login-background.png') }}") center 42% / cover no-repeat;
        box-shadow: var(--shadow-md);
    }
    .welcome-banner::before {
        content: ''; position: absolute; inset: 0;
        /* Opaque navy only under the greeting; by 45% the site is fully visible,
           which is what carries the banner. */
        background: linear-gradient(92deg,
            rgba(7, 26, 51, 0.97) 0%,
            rgba(7, 26, 51, 0.93) 20%,
            rgba(7, 26, 51, 0.55) 36%,
            rgba(7, 26, 51, 0.14) 52%,
            rgba(7, 26, 51, 0.06) 100%);
    }
    .welcome-banner > * { position: relative; z-index: 1; }
    .greeting-title {
        font-size: 21px !important; font-weight: 700 !important; color: #fff !important;
        letter-spacing: -0.02em; margin: 0; line-height: 1.15;
    }
    .greeting-sub { font-size: 12.5px; color: #B9CCE4; margin: 6px 0 0; }
    .wb-rule {
        display: block; width: 46px; height: 3px; margin-top: 13px;
        border-radius: 3px; background: #3B8BF0;
    }

    /* The clock sits on the navy, so it carries its own light-on-dark skin. */
    html[data-bs-theme] .welcome-banner .clock-widget {
        background: rgba(8, 26, 50, 0.92) !important;
        border: 1px solid rgba(255, 255, 255, 0.10) !important;
        border-radius: var(--radius-sm); padding: 10px 18px 10px 13px;
        display: flex; align-items: center; gap: 12px; box-shadow: none !important;
        text-align: left !important;
    }
    html[data-bs-theme] .welcome-banner .clock-ic {
        width: 32px; height: 32px; border-radius: 7px; flex-shrink: 0;
        display: flex; align-items: center; justify-content: center;
        background: rgba(59, 139, 240, 0.20); color: #8FBEF7; font-size: 14px;
    }
    /* The reference reads date first, then the clock. The DOM order stays as it
       is so the two JS tickers keep writing to their own ids. */
    html[data-bs-theme] .welcome-banner .clock-widget > div:last-child {
        display: flex; flex-direction: column-reverse;
    }
    html[data-bs-theme] .welcome-banner .clock-time {
        font-size: 17px; font-weight: 700; color: #fff;
        font-variant-numeric: tabular-nums; letter-spacing: 0.4px; line-height: 1.2;
        text-transform: none; color: #fff !important;
    }
    html[data-bs-theme] .welcome-banner .clock-date {
        font-size: 11.5px; color: #A9C0DA !important; font-weight: 500;
        margin: 0 0 2px; text-transform: none !important; letter-spacing: 0;
    }

    /* ── Stat cards ─────────────────────────── */
    .stat-card-inner { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; }
    .stat-label { margin-bottom: 7px; }
    .stat-value { margin-bottom: 4px; line-height: 1; }
    .stat-sub { margin: 0; }
    .icon-box {
        display: flex; align-items: center; justify-content: center;
        flex-shrink: 0; transition: transform 0.2s ease;
    }
    .stat-delta {
        display: inline-flex; align-items: center; gap: 5px; width: fit-content;
        margin-top: 13px;
    }

    /* KPI card: icon left, jump chevron top-right, trend and spark along the foot. */
    .kpi-card { position: relative; padding: 15px 17px !important; overflow: hidden; }
    .kpi-head { display: flex; align-items: flex-start; gap: 13px; }
    .kpi-text { min-width: 0; }
    .kpi-jump {
        position: absolute; top: 13px; right: 14px; z-index: 2;
        width: 22px; height: 22px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        color: var(--text-muted); font-size: 11px; text-decoration: none;
        transition: background 0.15s ease, color 0.15s ease;
    }
    .kpi-jump:hover { background: var(--brand-subtle); color: var(--brand); }
    .kpi-jump:focus-visible { color: var(--brand); }
    .kpi-foot {
        display: flex; align-items: flex-end; justify-content: space-between;
        gap: 10px; margin-top: 12px;
    }
    .kpi-foot .stat-delta { margin-top: 0; }

    /* Real 7-day attendance line (Present Today only). */
    .kpi-spark { width: 104px; height: 30px; flex-shrink: 0; overflow: visible; }
    .kpi-spark .ks-line { fill: none; stroke: var(--success); stroke-width: 1.8; vector-effect: non-scaling-stroke;
                          stroke-linejoin: round; stroke-linecap: round; }
    .kpi-spark .ks-area { fill: rgba(18, 135, 74, 0.12); stroke: none; }

    /* The other three have no 7-day series behind them, so they hold the space
       and stay empty rather than drawing a curve that would imply a trend. */
    .kpi-wash { width: 104px; height: 30px; flex-shrink: 0; }

    /* Six columns in a half-width card: tighten the gutters and let a long
       name truncate rather than pushing the row menu out of the card. */
    .dashboard-wrapper .table > :not(caption) > * > * {
        padding-left: 6px !important; padding-right: 6px !important;
    }
    .dashboard-wrapper .table > :not(caption) > * > *.ps-4 { padding-left: 14px !important; }
    .dashboard-wrapper .table > :not(caption) > * > *.pe-4 { padding-right: 8px !important; }
    .dashboard-wrapper .table .badge { padding: 3px 8px !important; font-size: 10px !important; padding: 3px 7px !important; }
    .dashboard-wrapper .table thead th { letter-spacing: 0.03em !important; font-size: 9.5px !important; }
    .dashboard-wrapper .table tbody td { font-size: 11.5px !important; }
    /* Names run in full at this size, so nothing is clipped; the ellipsis is
       only a backstop for an unusually long one. */
    .pers-name { font-size: 11.5px; max-width: 100%; }
    .pers-cell { gap: 8px; }
    .pers-av { width: 26px; height: 26px; font-size: 9.5px; }

    /* Range label on the chart header. */
    .range-chip {
        font-size: 11.5px; font-weight: 600; color: var(--text-secondary);
        background: var(--bg-subtle); border: 1px solid var(--border);
        border-radius: 999px; padding: 4px 11px; white-space: nowrap;
    }

    /* Row menu -> the worker's own page. */
    .row-menu {
        display: inline-flex; align-items: center; justify-content: center;
        width: 22px; height: 22px; border-radius: 6px;
        color: var(--text-muted); text-decoration: none; font-size: 13px;
        transition: background 0.15s ease, color 0.15s ease;
    }
    .row-menu:hover { background: var(--brand-subtle); color: var(--brand); }

    /* Amount and row menu share the final column, menu on the row's edge. */
    .vale-cell { display: flex; align-items: center; justify-content: flex-end; gap: 6px; }

    .btn-view-all i { font-size: 10px; margin-left: 4px; }

    /* ── Recent Personnel rows ────────────────── */
    .pers-cell { display: flex; align-items: center; gap: 10px; min-width: 0; }
    .pers-av {
        width: 30px; height: 30px; border-radius: 50%; flex-shrink: 0;
        background: var(--brand-subtle); color: var(--brand);
        display: flex; align-items: center; justify-content: center;
        font-size: 11px; font-weight: 700; letter-spacing: 0.02em;
    }
    .pers-name {
        font-weight: 600; color: var(--text-primary); font-size: 13px;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .pers-idx { color: var(--text-muted); font-size: 12px; font-variant-numeric: tabular-nums; }

    /* ── Recent Activities feed ───────────────── */
    .act-item { display: flex; gap: 12px; padding: 11px 18px; align-items: flex-start; }
    .act-item + .act-item { border-top: 1px solid var(--border); }
    .act-item:hover { background: var(--bg-subtle); }
    .act-ic {
        width: 32px; height: 32px; border-radius: 50%; flex-shrink: 0; color: #fff;
        display: flex; align-items: center; justify-content: center; font-size: 12px;
    }
    .act-body { flex: 1; min-width: 0; }
    .act-title { font-size: 12.5px; font-weight: 600; color: var(--text-primary); margin: 0; line-height: 1.35; }
    .act-sub { font-size: 11.5px; color: var(--text-secondary); margin: 1px 0 0;
               white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .act-time { font-size: 11px; color: var(--text-muted); white-space: nowrap; flex-shrink: 0; }

    /* ── Live Attendance list ────────────────── */
    .la-item { display: flex; align-items: center; gap: 11px; padding: 10px 18px; }
    .la-item + .la-item { border-top: 1px solid var(--border); }
    .la-item:hover { background: var(--bg-subtle); }
    .la-avatar {
        width: 32px; height: 32px; border-radius: 50%; flex-shrink: 0;
        display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 12px;
    }
    .la-name { font-size: 12.5px; font-weight: 600; color: var(--text-primary); margin: 0; }
    .la-pos { font-size: 11px; color: var(--text-secondary); margin: 0; }
    .la-time { margin-left: auto; font-size: 12px; font-weight: 700; color: var(--success); font-variant-numeric: tabular-nums; }

    /* ── Empty states ──────────────────────── */
    .dash-empty { padding: 30px 16px; text-align: center; color: var(--text-muted); font-size: 12.5px; }
    .dash-empty > i {
        font-size: 20px; opacity: 0.5; display: flex; margin: 0 auto 12px;
        width: 46px; height: 46px; border-radius: 50%;
        background: var(--bg-subtle); color: var(--text-muted);
        align-items: center; justify-content: center;
    }

    /* ── Map controls ──────────────────────── */
    .map-ctl { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-bottom: 8px; }
    .map-input, .map-select {
        padding: 8px 11px; font-size: 12.5px;
        border: 1px solid var(--border); background: var(--surface); color: var(--text-primary);
    }
    .map-input { flex: 1; min-width: 120px; }
    .map-select { flex: 1; min-width: 110px; }
    .map-btn {
        padding: 8px 12px; border: none; font-size: 12.5px;
        font-weight: 600; cursor: pointer; color: #fff; white-space: nowrap;
        transition: background 0.15s ease;
    }
    .map-btn.secondary { background: var(--bg-subtle); color: var(--text-primary); border: 1px solid var(--border); }
    .map-btn.secondary:hover { background: var(--brand-subtle); color: var(--brand); }
    .map-btn.primary { background: var(--brand); }
    .map-btn.primary:hover { background: var(--brand-strong); }
    .map-hint { font-size: 11px; color: var(--text-muted); margin-bottom: 8px; }

    #kioskMap { height: 205px; min-height: 185px; border-radius: var(--radius-sm); overflow: hidden; }
    #kioskMap .leaflet-control-attribution { font-size: 9px; }

    /* ── Density ──────────────────────────── */
    .dashboard-wrapper .mb-4 { margin-bottom: 16px !important; }
    .dashboard-wrapper .g-3 { --bs-gutter-y: 16px; --bs-gutter-x: 16px; }
    .dashboard-wrapper .table-card-header { padding: 13px 18px !important; }
    .dashboard-wrapper .table > :not(caption) > * > * { padding-top: 9px !important; padding-bottom: 9px !important; }
    .dashboard-wrapper .table-card > .p-4 { padding: 14px 16px !important; min-height: 0 !important; }

    @media (max-width: 575px) {
        .welcome-banner { padding: 18px; }
        .greeting-title { font-size: 18px !important; }
    }
</style>
@endpush

@section('content')
<div class="container-fluid dashboard-wrapper">

    {{-- HEADER --}}
    @php
        $h = (int) now()->format('G');
        $greet = $h < 12 ? 'Good morning' : ($h < 18 ? 'Good afternoon' : 'Good evening');
    @endphp
    <div class="welcome-banner mb-4">
        <div>
            <h1 class="greeting-title">{{ $greet }}, {{ auth()->user()->name ?? 'Admin' }}!</h1>
            <p class="greeting-sub">{{ __('Here\'s what\'s happening at Jeyanco Construction today.') }}</p>
            <span class="wb-rule" aria-hidden="true"></span>
        </div>
        {{-- The tile is drawn as a calendar and now behaves like one: clicking
             it opens a read-only month view. The shell is the positioning
             context, so the popover overlays the banner instead of pushing it
             open. The two clock ids are unchanged — the live ticker still
             writes to them. --}}
        <div class="clock-shell d-none d-sm-block">
            <div class="clock-widget" id="clockWidget" role="button" tabindex="0"
                 aria-haspopup="dialog" aria-expanded="false"
                 title="{{ __('Open calendar') }}">
                <div class="clock-ic"><i class="fas fa-calendar-days"></i></div>
                <div>
                    <div class="clock-time" id="current-time">--:-- --</div>
                    <div class="clock-date" id="current-date">{{ now()->format('l, F d, Y') }}</div>
                </div>
            </div>
            <div class="mini-cal" id="miniCal" role="dialog" aria-label="{{ __('Calendar') }}" hidden></div>
        </div>
    </div>

    {{-- ROW 1: Stat Cards --}}
    @php
        $presentDelta = ($presentToday ?? 0) - ($presentYesterday ?? 0);
        $lwp = $lastWeekPayroll ?? 0;
        $payoutPct = $lwp > 0
            ? round((($weeklyPayroll - $lwp) / $lwp) * 100, 1)
            : (($weeklyPayroll ?? 0) > 0 ? 100 : 0);
        $presentClass = $presentDelta > 0 ? 'up' : ($presentDelta < 0 ? 'down' : 'flat');
        $presentIcon  = $presentDelta > 0 ? 'fa-arrow-up' : ($presentDelta < 0 ? 'fa-arrow-down' : 'fa-minus');
        $payoutClass  = $payoutPct > 0 ? 'up' : ($payoutPct < 0 ? 'down' : 'flat');
        $payoutIcon   = $payoutPct > 0 ? 'fa-arrow-up' : ($payoutPct < 0 ? 'fa-arrow-down' : 'fa-minus');
    @endphp
    @php
        // The only genuine 7-day series the controller hands us is attendance,
        // so that is the only card that gets a real trend line. The rest carry
        // a plain wash in the same spot rather than a made-up curve.
        $sparkPoints = '';
        $sparkArea = '';
        $series = array_values($attendanceData ?? []);
        if (count($series) > 1) {
            $lo = min($series); $hi = max($series);
            $span = max($hi - $lo, 1);
            $w = 104; $h = 30; $step = $w / (count($series) - 1);
            $pts = [];
            foreach ($series as $i => $v) {
                $x = round($i * $step, 1);
                $y = round($h - 3 - (($v - $lo) / $span) * ($h - 6), 1);
                $pts[] = $x . ',' . $y;
            }
            $sparkPoints = implode(' ', $pts);
            $sparkArea = '0,' . $h . ' ' . $sparkPoints . ' ' . $w . ',' . $h;
        }
    @endphp
    <div class="row g-3 mb-4">

        <div class="col-xl-3 col-md-6">
            <div class="card analytics-card variant-blue kpi-card h-100">
                <a class="kpi-jump" href="{{ url('/employees') }}" aria-label="View employees"><i class="fas fa-chevron-right"></i></a>
                <div class="kpi-head">
                    <div class="icon-box bg-primary bg-opacity-10">
                        <i class="fas fa-helmet-safety text-primary fa-lg"></i>
                    </div>
                    <div class="kpi-text">
                        <div class="stat-label">{{ __('Total Workforce') }}</div>
                        <div class="stat-value">{{ $totalEmployees ?? 0 }}</div>
                        <p class="stat-sub">{{ __('Active employees') }}</p>
                    </div>
                </div>
                <div class="kpi-foot">
                    @if(($newThisWeek ?? 0) > 0)
                        <span class="stat-delta up"><i class="fas fa-arrow-up"></i> +{{ $newThisWeek }} <span class="sd-note">{{ __('this week') }}</span></span>
                    @else
                        <span class="stat-delta flat"><i class="fas fa-minus"></i> 0 <span class="sd-note">{{ __('new this week') }}</span></span>
                    @endif
                    <span class="kpi-wash" aria-hidden="true"></span>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card analytics-card variant-green kpi-card h-100">
                <a class="kpi-jump" href="{{ url('/attendance') }}" aria-label="View attendance"><i class="fas fa-chevron-right"></i></a>
                <div class="kpi-head">
                    <div class="icon-box bg-success bg-opacity-10">
                        <i class="fas fa-user-check text-success fa-lg"></i>
                    </div>
                    <div class="kpi-text">
                        <div class="stat-label">{{ __('Present Today') }}</div>
                        <div class="stat-value text-success">{{ $presentToday ?? 0 }}</div>
                        <p class="stat-sub">{{ __('On-site attendance') }}</p>
                    </div>
                </div>
                <div class="kpi-foot">
                    <span class="stat-delta {{ $presentClass }}"><i class="fas {{ $presentIcon }}"></i> {{ $presentDelta > 0 ? '+' : '' }}{{ $presentDelta }} <span class="sd-note">{{ __('vs yesterday') }}</span></span>
                    <svg class="kpi-spark" viewBox="0 0 104 30" preserveAspectRatio="none" aria-hidden="true">
                        <polyline class="ks-area" points="{{ $sparkArea }}"></polyline>
                        <polyline class="ks-line" points="{{ $sparkPoints }}"></polyline>
                    </svg>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card analytics-card variant-amber kpi-card h-100">
                <a class="kpi-jump" href="{{ url('/payroll-records') }}" aria-label="View payroll records"><i class="fas fa-chevron-right"></i></a>
                <div class="kpi-head">
                    <div class="icon-box bg-warning bg-opacity-10">
                        <i class="fas fa-coins text-warning fa-lg"></i>
                    </div>
                    <div class="kpi-text">
                        <div class="stat-label">{{ __('Weekly Payout') }}</div>
                        <div class="stat-value">₱{{ number_format($weeklyPayroll ?? 0, 2) }}</div>
                        <p class="stat-sub">{{ __('Scheduled payment') }}</p>
                    </div>
                </div>
                <div class="kpi-foot">
                    <span class="stat-delta {{ $payoutClass }}"><i class="fas {{ $payoutIcon }}"></i> {{ $payoutPct > 0 ? '+' : '' }}{{ $payoutPct }}% <span class="sd-note">{{ __('vs last week') }}</span></span>
                    <span class="kpi-wash" aria-hidden="true"></span>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card analytics-card variant-red kpi-card h-100">
                <a class="kpi-jump" href="{{ url('/payroll-records') }}" aria-label="View payroll records"><i class="fas fa-chevron-right"></i></a>
                <div class="kpi-head">
                    <div class="icon-box bg-danger bg-opacity-10">
                        <i class="fas fa-file-invoice-dollar text-danger fa-lg"></i>
                    </div>
                    <div class="kpi-text">
                        <div class="stat-label">{{ __('Pending Vale') }}</div>
                        <div class="stat-value text-danger">₱{{ number_format($pendingVale ?? 0, 2) }}</div>
                        <p class="stat-sub">{{ __('Outstanding balance') }}</p>
                    </div>
                </div>
                <div class="kpi-foot">
                    @if(($pendingVale ?? 0) > 0)
                        <span class="stat-delta down"><i class="fas fa-circle-exclamation"></i> {{ __('Outstanding') }} <span class="sd-note">{{ __('balance') }}</span></span>
                    @else
                        <span class="stat-delta flat"><i class="fas fa-check"></i> {{ __('All settled') }}</span>
                    @endif
                    <span class="kpi-wash" aria-hidden="true"></span>
                </div>
            </div>
        </div>

    </div>

    {{-- ROW 2: Personnel Table + Chart --}}
    <div class="row g-3 mb-4">

        <div class="col-lg-7">
            <div class="table-card h-100 d-flex flex-column">
                <div class="table-card-header">
                    <h6><i class="fas fa-user-tie"></i> {{ __('Recent Personnel') }}</h6>
                    <button class="btn-view-all" data-bs-toggle="modal" data-bs-target="#employeeModal">
                        {{ __('View All') }} <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
                <div class="table-responsive flex-grow-1">
                    <table class="table align-middle table-hover mb-0">
                        <thead>
                            <tr>
                                <th class="ps-4" style="width:34px;">#</th>
                                <th>{{ __('Worker Name') }}</th>
                                <th class="text-center">{{ __('Position') }}</th>
                                <th class="text-center">{{ __('Job Type') }}</th>
                                <th class="text-end pe-4">{{ __('Vale Balance') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($employees->take(6) as $emp)
                            @php
                                // Two initials for the row avatar, from the name already on screen.
                                $nameParts = preg_split('/\s+/', trim($emp->name ?? ''), -1, PREG_SPLIT_NO_EMPTY);
                                $initials = strtoupper(mb_substr($nameParts[0] ?? 'W', 0, 1)
                                    . (count($nameParts) > 1 ? mb_substr(end($nameParts), 0, 1) : ''));
                            @endphp
                            <tr>
                                <td class="ps-4 pers-idx">{{ $loop->iteration }}</td>
                                <td>
                                    <div class="pers-cell">
                                        <span class="pers-av">{{ $initials }}</span>
                                        <span class="pers-name" title="{{ $emp->name }}">{{ $emp->name }}</span>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <span class="badge">{{ $emp->position }}</span>
                                </td>
                                <td class="text-center">
                                    <span class="badge">{{ $emp->employment_label }}</span>
                                </td>
                                <td class="pe-4">
                                    <div class="vale-cell">
                                        <span class="fw-semibold {{ $emp->vale > 0 ? 'text-danger' : 'text-muted' }}">₱{{ number_format($emp->vale ?? 0, 2) }}</span>
                                        <a class="row-menu" href="{{ route('employees.show', $emp) }}"
                                           aria-label="{{ __('Open :name', ['name' => $emp->name]) }}"
                                           title="{{ __('Open worker') }}"><i class="fas fa-ellipsis"></i></a>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="text-center py-5 text-muted">
                                    <i class="fas fa-users mb-2 d-block" style="font-size:1.5rem;opacity:0.25;"></i>
                                    No employee records found.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="table-card h-100 d-flex flex-column">
                <div class="table-card-header">
                    <h6><i class="fas fa-chart-line"></i> {{ __('Labor Hours Trend') }}</h6>
                    {{-- A label for the range the chart already draws (the controller
                         builds exactly seven days), not a filter control. --}}
                    <span class="range-chip">{{ __('This Week') }}</span>
                </div>
                <div class="p-4 flex-grow-1 d-flex align-items-center" style="min-height:260px;">
                    <canvas id="attendanceChart" style="width:100%;"></canvas>
                </div>
            </div>
        </div>

    </div>

    {{-- ROW 3: Live Attendance | Project Sites (map) | Recent Activities --}}
    <div class="row g-3 mb-4">

        {{-- Live Attendance (Today) --}}
        <div class="col-lg-4">
            <div class="table-card h-100 d-flex flex-column">
                <div class="table-card-header">
                    <h6><i class="fas fa-user-clock"></i> {{ __('Live Attendance') }}
                        <span style="font-weight:500;color:var(--text-secondary);font-size:12px;">{{ __('(Today)') }}</span>
                    </h6>
                    <span class="badge" style="background:rgba(34,197,94,0.15);color:var(--success);font-weight:700;font-size:10.5px;letter-spacing:0.5px;">
                        <i class="fas fa-circle" style="font-size:6px;vertical-align:middle;"></i> {{ __('LIVE') }}
                    </span>
                </div>
                <div class="flex-grow-1">
                    @forelse($todayAttendance as $att)
                        <div class="la-item">
                            <div class="la-avatar">{{ strtoupper(substr(optional($att->employee)->name ?? 'W', 0, 1)) }}</div>
                            <div>
                                <p class="la-name">{{ optional($att->employee)->name ?? 'Worker' }}</p>
                                <p class="la-pos">{{ optional($att->employee)->position ?? 'On site' }}</p>
                            </div>
                            <span class="la-time">{{ \Carbon\Carbon::parse($att->time_in)->format('g:i A') }}</span>
                        </div>
                    @empty
                        <div class="dash-empty">
                            <i class="fas fa-user-clock"></i>
                            No attendance records for today.<br>
                            <span style="font-size:12px;">{{ __('Attendance will appear here in real-time.') }}</span>
                            <div class="mt-3"><a href="{{ url('/attendance') }}" class="btn btn-sm btn-primary">{{ __('View Attendance') }}</a></div>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Project Sites (interactive map — search / set location / live kiosk GPS) --}}
        <div class="col-lg-4">
            <div class="table-card site-tracker h-100 d-flex flex-column" id="siteTrackerCard">
                <div class="table-card-header">
                    <h6><i class="fas fa-map-location-dot"></i> {{ __('Project Sites') }}</h6>
                    <span id="kiosk-status" style="font-size:11.5px;color:var(--text-secondary);font-weight:500;">
                        <i class="fas fa-circle-notch fa-spin"></i> {{ __('Locating…') }}
                    </span>
                    {{-- Window chrome only. The map, its markers, the place
                         search, the site picker, the save call and the live GPS
                         poll are untouched — the card is resized around them and
                         Leaflet is asked to re-measure afterwards. --}}
                    <div class="st-actions">
                        <button type="button" class="st-btn" id="siteTrackerMin"
                                aria-expanded="true"
                                title="{{ __('Minimize Site Tracker') }}"
                                aria-label="{{ __('Minimize Site Tracker') }}"
                                data-label-on="{{ __('Minimize Site Tracker') }}"
                                data-label-off="{{ __('Restore Site Tracker') }}">
                            <i class="fas fa-window-minimize"></i>
                        </button>
                        <button type="button" class="st-btn" id="siteTrackerMax"
                                aria-pressed="false"
                                title="{{ __('Maximize Site Tracker') }}"
                                aria-label="{{ __('Maximize Site Tracker') }}"
                                data-label-on="{{ __('Maximize Site Tracker') }}"
                                data-label-off="{{ __('Restore Site Tracker') }}">
                            <i class="fas fa-expand"></i>
                        </button>
                    </div>
                </div>
                <div class="st-body p-3 flex-grow-1 d-flex flex-column">
                    <div class="map-ctl">
                        <input id="siteSearch" class="map-input" type="text" placeholder="{{ __('Search a place…') }}">
                        <button id="siteSearchBtn" class="map-btn secondary" type="button" title="{{ __('Search') }}"><i class="fas fa-search"></i></button>
                    </div>
                    <div class="map-ctl">
                        <select id="siteSelect" class="map-select" title="{{ __('Piliin ang site na itatakda') }}"></select>
                        <button id="siteSaveBtn" class="map-btn primary" type="button" title="{{ __('Save location') }}"><i class="fas fa-map-pin"></i> {{ __('Save') }}</button>
                    </div>
                    <div id="siteMapHint" class="map-hint">{{ __('Pumili ng site, mag-search o mag-click sa map, tapos Save.') }}</div>
                    <div id="kioskMap" class="rounded-3 overflow-hidden flex-grow-1"></div>
                </div>
            </div>
        </div>

        {{-- Recent Activities --}}
        <div class="col-lg-4">
            <div class="table-card h-100 d-flex flex-column">
                <div class="table-card-header">
                    <h6><i class="fas fa-wave-square"></i> {{ __('Recent Activities') }}</h6>
                    <a class="btn-view-all" href="{{ url('/employees') }}">{{ __('View All') }} <i class="fas fa-arrow-right"></i></a>
                </div>
                <div class="flex-grow-1">
                    @forelse($recentActivities as $act)
                        <div class="act-item">
                            <div class="act-ic" style="background: {{ $act['color'] }};"><i class="fas {{ $act['icon'] }}"></i></div>
                            <div class="act-body">
                                <p class="act-title">{{ $act['title'] }}</p>
                                <p class="act-sub">{{ $act['subtitle'] }}</p>
                            </div>
                            <span class="act-time">{{ $act['time']->diffForHumans() }}</span>
                        </div>
                    @empty
                        <div class="dash-empty">
                            <i class="fas fa-clock-rotate-left"></i>
                            No recent activity yet.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

    </div>

    {{-- MODAL: Full Employee List --}}
    <div class="modal fade" id="employeeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title text-white fw-bold">
                        <i class="fas fa-users me-2"></i>{{ __('Workforce Registry') }}
                    </h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                </div>
                <div class="modal-body p-0">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="ps-4">{{ __('Name') }}</th>
                                <th>{{ __('Position') }}</th>
                                <th>{{ __('Rate / Hr') }}</th>
                                <th class="pe-4 text-end">{{ __('Vale') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($employees as $emp)
                            <tr>
                                <td class="ps-4 fw-semibold">{{ $emp->name }}</td>
                                <td>
                                    <span class="badge bg-light text-dark border">{{ $emp->position }}</span>
                                </td>
                                <td>₱{{ number_format($emp->rate_per_hour, 2) }}</td>
                                <td class="pe-4 text-end fw-semibold {{ $emp->vale > 0 ? 'text-danger' : 'text-muted' }}">
                                    ₱{{ number_format($emp->vale ?? 0, 2) }}
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light border fw-semibold" data-bs-dismiss="modal">{{ __('Close') }}</button>
                </div>
            </div>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    // Live clock (time + date)
    (function tick() {
        const now = new Date();
        const t = document.getElementById('current-time');
        const d = document.getElementById('current-date');
        if (t) t.innerText = now.toLocaleTimeString('en-US', { hour:'2-digit', minute:'2-digit', second:'2-digit', hour12:true });
        if (d) d.innerText = now.toLocaleDateString('en-US', { weekday:'long', year:'numeric', month:'long', day:'numeric' });
        setTimeout(tick, 1000);
    })();

    // Labor Hours Chart
    const ctx = document.getElementById('attendanceChart');
    if (ctx) {
        new Chart(ctx.getContext('2d'), {
            type: 'line',
            data: {
                labels: {!! json_encode($attendanceLabels ?? []) !!},
                datasets: [{
                    label: 'Hours Worked',
                    data: {!! json_encode($attendanceData ?? []) !!},
                    borderColor: '#1769E0',
                    backgroundColor: 'rgba(23,105,224,0.10)',
                    fill: true,
                    tension: 0.4,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: '#1769E0',
                    pointBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#0f172a',
                        titleColor: '#94a3b8',
                        bodyColor: '#f8fafc',
                        padding: 10,
                        borderRadius: 8,
                        callbacks: { label: ctx => ' ' + ctx.parsed.y + ' hrs' }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: '#f1f5f9', drawBorder: false },
                        ticks: { font: { size: 11 }, color: '#94a3b8' }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { font: { size: 11 }, color: '#94a3b8' }
                    }
                }
            }
        });
    }

    // ---- PROJECT SITE MAP (Leaflet / OpenStreetMap — libre, walang API key) ----
    (function () {
        const mapEl = document.getElementById('kioskMap');
        if (!mapEl) return;

        const NAGA = [13.6218, 123.1948];   // Naga City, Camarines Sur — default center
        const csrf = document.querySelector('meta[name="csrf-token"]').content;

        const map = L.map('kioskMap').setView(NAGA, 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors',
            maxZoom: 19,
        }).addTo(map);

        // FIX: the map used to render half-blank because Leaflet measured the
        // container before it was fully laid out. Recalc size a few times.
        const fixSize = () => map.invalidateSize();
        window.addEventListener('load', fixSize);
        setTimeout(fixSize, 300);
        setTimeout(fixSize, 900);

        // Minimising or maximising the card changes the map's box. Leaflet's
        // invalidateSize holds the centre and the zoom, so a box that grows
        // three times over simply shows three times the ground — maximising
        // pulled the view back to the whole region and lost the site you were
        // looking at. Read the bounds while Leaflet still has the old size
        // cached, then put the very same ground back at the new size: bigger
        // box, same place, more detail. Nothing else about the map changes.
        document.addEventListener('jeyanco:card-resize', () => {
            const showing = map.getBounds();
            requestAnimationFrame(() => {
                map.invalidateSize({ animate: false });
                map.fitBounds(showing, { animate: false });
            });
        });

        const siteSelect  = document.getElementById('siteSelect');
        const saveBtn     = document.getElementById('siteSaveBtn');
        const searchInput = document.getElementById('siteSearch');
        const searchBtn   = document.getElementById('siteSearchBtn');
        const hintEl      = document.getElementById('siteMapHint');
        const setHint = (msg, color) => { hintEl.textContent = msg; hintEl.style.color = color || '#94a3b8'; };
        const selectedName = () => (siteSelect.options[siteSelect.selectedIndex]?.text || 'site').replace(' 📍','');

        let siteMarkers = {};     // id -> saved-location marker
        let sitesById   = {};     // id -> site record
        let placing     = null;   // { lat, lng } pending pin
        let placingMarker = null;

        // ---- load all sites, drop markers, populate the picker ----
        async function loadSites(fit = true) {
            try {
                const res = await fetch('/sites/list', { headers: { 'Accept': 'application/json' } });
                const d = await res.json();
                const sites = d.sites || [];
                Object.values(siteMarkers).forEach(m => map.removeLayer(m));
                siteMarkers = {}; sitesById = {};
                const prev = siteSelect.value;
                siteSelect.innerHTML = '';
                const bounds = [];
                sites.forEach(s => {
                    sitesById[s.id] = s;
                    const hasLoc = s.latitude != null && s.longitude != null;
                    const opt = document.createElement('option');
                    opt.value = s.id;
                    opt.textContent = s.name + (hasLoc ? ' 📍' : '');
                    siteSelect.appendChild(opt);
                    if (hasLoc) {
                        const lat = parseFloat(s.latitude), lng = parseFloat(s.longitude);
                        siteMarkers[s.id] = L.marker([lat, lng]).addTo(map)
                            .bindPopup(`<b>${s.name}</b>${s.location ? '<br>' + s.location : ''}`);
                        bounds.push([lat, lng]);
                    }
                });
                // keep previous selection, else default to "Site A"
                if (prev && sitesById[prev]) siteSelect.value = prev;
                else {
                    const a = sites.find(s => s.name.trim().toLowerCase() === 'site a');
                    if (a) siteSelect.value = a.id;
                }
                if (fit) {
                    if (bounds.length === 1) map.setView(bounds[0], 16);
                    else if (bounds.length > 1) map.fitBounds(bounds, { padding: [40, 40] });
                }
                setHint('Pumili ng site, mag-search o mag-click sa map para itakda ang lokasyon, tapos i-Save.');
            } catch (e) {
                setHint('Hindi ma-load ang listahan ng sites.', '#ef4444');
            }
        }

        // ---- click / drag to place the pin ----
        function placePin(latlng) {
            placing = { lat: latlng.lat, lng: latlng.lng };
            if (placingMarker) placingMarker.setLatLng(latlng);
            else {
                placingMarker = L.marker(latlng, { draggable: true, zIndexOffset: 1000, opacity: 0.85 }).addTo(map);
                placingMarker.on('dragend', ev => {
                    const p = ev.target.getLatLng();
                    placing = { lat: p.lat, lng: p.lng };
                    setHint(`Pin para sa "${selectedName()}": ${placing.lat.toFixed(5)}, ${placing.lng.toFixed(5)} — pindutin ang Save.`, '#22c55e');
                });
            }
            setHint(`Pin para sa "${selectedName()}": ${placing.lat.toFixed(5)}, ${placing.lng.toFixed(5)} — pindutin ang Save.`, '#22c55e');
        }
        map.on('click', e => placePin(e.latlng));

        // ---- free place search via Nominatim (OpenStreetMap) ----
        async function doSearch() {
            const q = searchInput.value.trim();
            if (!q) return;
            setHint('Naghahanap ng lugar…');
            try {
                const url = `https://nominatim.openstreetmap.org/search?format=json&limit=1&countrycodes=ph&q=${encodeURIComponent(q)}`;
                const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                const arr = await res.json();
                if (!arr.length) { setHint('Walang nahanap na lugar. Subukan ang ibang pangalan.', '#ef4444'); return; }
                const lat = parseFloat(arr[0].lat), lng = parseFloat(arr[0].lon);
                map.setView([lat, lng], 16);
                placePin(L.latLng(lat, lng));   // auto-drop pin at the result
            } catch (e) {
                setHint('Hindi gumana ang search. Subukan ulit.', '#ef4444');
            }
        }
        searchBtn.addEventListener('click', doSearch);
        searchInput.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); doSearch(); } });

        // ---- save the pin as the selected site's location ----
        saveBtn.addEventListener('click', async () => {
            const id = siteSelect.value;
            if (!id) { setHint('Walang piniling site.', '#ef4444'); return; }
            if (!placing) { setHint('Mag-click muna sa map o mag-search para maglagay ng pin.', '#ef4444'); return; }
            const site = sitesById[id];
            saveBtn.disabled = true; setHint('Sine-save ang lokasyon…');
            try {
                const res = await fetch(`/sites/${id}`, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify({
                        name: site.name,
                        location: (searchInput.value.trim() || `${placing.lat.toFixed(5)}, ${placing.lng.toFixed(5)}`),
                        latitude: placing.lat,
                        longitude: placing.lng,
                    }),
                });
                const d = await res.json();
                if (!res.ok || !d.success) throw new Error((d.message) || 'Save failed');
                if (placingMarker) { map.removeLayer(placingMarker); placingMarker = null; }
                placing = null;
                await loadSites(false);
                setHint(`✅ Na-save ang lokasyon ng "${site.name}".`, '#22c55e');
            } catch (e) {
                setHint('Hindi na-save: ' + e.message, '#ef4444');
            } finally {
                saveBtn.disabled = false;
            }
        });

        loadSites();

        // ---- live kiosk GPS overlay (Raspberry Pi) — distinct red dot ----
        const KIOSK_ID = 'jeyanco-01';
        const statusEl  = document.getElementById('kiosk-status');
        const liveIcon  = L.divIcon({
            className: '',
            html: '<div style="width:14px;height:14px;background:#ef4444;border:2px solid #fff;border-radius:50%;box-shadow:0 0 0 4px rgba(239,68,68,.25)"></div>',
            iconSize: [14, 14], iconAnchor: [7, 7],
        });
        let liveMarker = null;
        let liveCentred = false;     // isang beses lang tayo mang-aagaw ng view

        // Ang mapa ay naka-zoom sa mga site pin (zoom 16 = ilang daang metro).
        // Ang kiosk ay pwedeng kilometro ang layo — naidadagdag ang marker pero
        // wala sa screen, kaya mukhang "walang lumalabas sa mapa". Isama ito sa
        // tanaw sa unang fix, at gawing clickable ang status para makabalik.
        function revealKiosk(pos, zoomIn) {
            const siteLatLngs = Object.values(siteMarkers).map(m => m.getLatLng());
            if (zoomIn || siteLatLngs.length === 0) {
                map.setView(pos, 16);
                return;
            }
            map.fitBounds([pos].concat(siteLatLngs.map(p => [p.lat, p.lng])),
                          { padding: [50, 50], maxZoom: 16 });
        }

        statusEl.style.cursor = 'pointer';
        statusEl.title = 'I-click para hanapin ang kiosk sa mapa';
        statusEl.addEventListener('click', () => {
            if (liveMarker) {
                revealKiosk(liveMarker.getLatLng(), true);
                liveMarker.openPopup();
            }
        });

        async function refreshLive() {
            try {
                const res = await fetch(`/api/location/latest?kiosk_id=${KIOSK_ID}`);
                const d = await res.json();
                if (d.lat && d.lng) {
                    const pos = [d.lat, d.lng];
                    const where = d.detected_site || 'Out of range';
                    if (liveMarker) liveMarker.setLatLng(pos).setPopupContent(`Live kiosk position &middot; ${where}`);
                    else liveMarker = L.marker(pos, { icon: liveIcon }).addTo(map)
                                       .bindPopup(`Live kiosk position &middot; ${where}`);

                    // Unang fix: iangat ang tanaw para makita mo talaga.
                    if (!liveCentred) {
                        liveCentred = true;
                        revealKiosk(pos, false);
                    }

                    const t = d.recorded_at ? new Date(d.recorded_at).toLocaleTimeString() : '';

                    // One kiosk is carried between sites, so "where does the GPS
                    // say it is" and "which site did the operator select" can
                    // disagree — and when they do, attendance is being filed
                    // against the wrong site. Say so instead of just "Live".
                    if (d.site_match === false) {
                        statusEl.innerHTML =
                            `<i class="fas fa-triangle-exclamation text-danger" style="font-size:9px;"></i> ` +
                            `GPS: ${where} &middot; naka-set sa ${d.active_site || '—'}`;
                    } else if (d.alert === 'outside_geofence') {
                        statusEl.innerHTML =
                            `<i class="fas fa-circle text-danger" style="font-size:8px;"></i> ` +
                            `Wala sa site &middot; ${Math.round(d.distance_m || 0)}m &middot; ${t}`;
                    } else {
                        statusEl.innerHTML =
                            `<i class="fas fa-circle text-success" style="font-size:8px;"></i> ` +
                            `Live &middot; ${where} &middot; ${t}`;
                    }
                } else if (d.status === 'no_fix') {
                    // Buhay ang kiosk, walang satellite lock. Ibang-iba ito sa
                    // katahimikan, na ibig sabihin nawawala ang kiosk.
                    statusEl.innerHTML =
                        `<i class="fas fa-circle text-warning" style="font-size:8px;"></i> Naka-on, walang GPS signal`;
                } else {
                    statusEl.innerHTML = `<i class="fas fa-circle text-warning" style="font-size:8px;"></i> Waiting for GPS`;
                }
            } catch (e) {
                statusEl.innerHTML = `<i class="fas fa-circle text-secondary" style="font-size:8px;"></i> No live GPS`;
            }
        }
        refreshLive();
        setInterval(refreshLive, 10000);
    })();
</script>
@endsection