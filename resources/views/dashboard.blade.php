@extends('layouts')

@section('page_title', 'Dashboard')

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<style>
    .dashboard-wrapper { padding-top: 0; padding-bottom: 48px; }

    /* ── Greeting header ─────────────────────────────────────────── */
    .greeting-title {
        font-size: 1.6rem; font-weight: 800; color: var(--text-primary);
        letter-spacing: -0.4px; margin: 0; line-height: 1.10;
    }
    .greeting-sub { font-size: 13px; color: var(--text-secondary); margin: 5px 0 0; }

    .clock-widget {
        background: var(--bg-surface); border: 1px solid var(--border);
        border-radius: var(--radius-md); padding: 9px 16px 9px 12px;
        display: flex; align-items: center; gap: 12px; box-shadow: var(--shadow-sm);
    }
    .clock-ic {
        width: 40px; height: 40px; border-radius: 12px; flex-shrink: 0;
        display: flex; align-items: center; justify-content: center;
        background: var(--primary-soft); color: var(--primary-light);
    }
    .clock-time {
        font-size: 1.1rem; font-weight: 700; color: var(--text-primary);
        font-variant-numeric: tabular-nums; letter-spacing: 0.5px; line-height: 1.15;
    }
    .clock-date {
        font-size: 11px; color: var(--text-secondary); font-weight: 600;
        letter-spacing: 0.3px; margin-top: 1px;
    }

    /* ── Stat cards ──────────────────────────────────────────────── */
    .stat-card-inner { display: flex; justify-content: space-between; align-items: flex-start; }
    .stat-label {
        font-size: 11px; font-weight: 700; color: var(--text-secondary);
        text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px;
    }
    .stat-value {
        font-size: 1.875rem; font-weight: 800; color: var(--text-primary);
        line-height: 1; margin-bottom: 5px; letter-spacing: -0.5px;
    }
    .stat-sub { font-size: 12px; color: var(--text-secondary); margin: 0; }
    .icon-box {
        width: 48px; height: 48px; display: flex; align-items: center; justify-content: center;
        border-radius: 12px; flex-shrink: 0; transition: transform 0.2s ease;
    }
    .analytics-card:hover .icon-box { transform: scale(1.08); }

    .stat-delta {
        display: inline-flex; align-items: center; gap: 5px; width: fit-content;
        font-size: 11.5px; font-weight: 700; margin-top: 12px;
        padding: 3px 9px; border-radius: 20px;
    }
    .stat-delta.up   { color: var(--success); background: rgba(34,197,94,0.13); }
    .stat-delta.down { color: var(--danger);  background: rgba(239,68,68,0.13); }
    .stat-delta.flat { color: var(--text-muted); background: rgba(148,163,184,0.15); }
    .stat-delta .sd-note { font-weight: 500; opacity: 0.85; }

    /* ── Recent Activities feed ──────────────────────────────────── */
    .act-item { display: flex; gap: 12px; padding: 12px 20px; align-items: flex-start; }
    .act-item + .act-item { border-top: 1px solid var(--border); }
    .act-ic {
        width: 34px; height: 34px; border-radius: 10px; flex-shrink: 0; color: #fff;
        display: flex; align-items: center; justify-content: center; font-size: 13px;
    }
    .act-body { flex: 1; min-width: 0; }
    .act-title { font-size: 13px; font-weight: 600; color: var(--text-primary); margin: 0; line-height: 1.3; }
    .act-sub { font-size: 12px; color: var(--text-secondary); margin: 1px 0 0;
               white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .act-time { font-size: 11px; color: var(--text-muted); white-space: nowrap; flex-shrink: 0; }

    /* ── Live Attendance list ────────────────────────────────────── */
    .la-item { display: flex; align-items: center; gap: 12px; padding: 11px 20px; }
    .la-item + .la-item { border-top: 1px solid var(--border); }
    .la-avatar {
        width: 34px; height: 34px; border-radius: 50%; flex-shrink: 0;
        background: var(--primary-soft); color: var(--primary-light);
        display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 13px;
    }
    .la-name { font-size: 13px; font-weight: 600; color: var(--text-primary); margin: 0; }
    .la-pos { font-size: 11.5px; color: var(--text-secondary); margin: 0; }
    .la-time { margin-left: auto; font-size: 12.5px; font-weight: 700; color: var(--success); font-variant-numeric: tabular-nums; }

    .dash-empty { padding: 34px 16px; text-align: center; color: var(--text-muted); font-size: 13px; }
    .dash-empty > i { font-size: 26px; opacity: 0.35; display: block; margin-bottom: 12px; }

    /* ── Compact map controls (token-based, theme aware) ─────────── */
    .map-ctl { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-bottom: 8px; }
    .map-input, .map-select {
        padding: 8px 11px; border-radius: 10px; font-size: 13px;
        border: 1px solid var(--border-md); background: var(--bg-subtle); color: var(--text-primary);
    }
    .map-input { flex: 1; min-width: 120px; }
    .map-select { flex: 1; min-width: 110px; }
    .map-btn {
        padding: 8px 12px; border: none; border-radius: 10px; font-size: 13px;
        font-weight: 600; cursor: pointer; color: #fff; white-space: nowrap;
    }
    .map-btn.secondary { background: var(--bg-elevated); color: var(--text-primary); border: 1px solid var(--border-md); }
    .map-btn.primary { background: var(--primary); }
    .map-hint { font-size: 11.5px; color: var(--text-secondary); margin-bottom: 8px; }

    /* Leaflet map inside card */
    #kioskMap { height: 260px; min-height: 220px; border-radius: 12px; overflow: hidden; }
    #kioskMap .leaflet-control-attribution { font-size: 9px; }

    /* ── COMPACT + CLEAN: fit the dashboard on one screen, tidily ────── */
    .dashboard-wrapper .mb-4 { margin-bottom: 12px !important; }
    .dashboard-wrapper .g-3 { --bs-gutter-y: 12px; --bs-gutter-x: 12px; }

    /* Header */
    .dashboard-wrapper .greeting-title { font-size: 19px !important; }
    .dashboard-wrapper .greeting-sub { font-size: 12px; margin-top: 2px; }
    .dashboard-wrapper .clock-widget { padding: 7px 14px !important; gap: 10px; }
    .dashboard-wrapper .clock-ic { width: 34px; height: 34px; }
    .dashboard-wrapper .clock-time { font-size: 1.05rem; }

    /* Stat cards — balanced padding, icon sized to its box */
    .dashboard-wrapper .analytics-card { padding: 13px 15px !important; }
    .dashboard-wrapper .stat-label { margin-bottom: 5px; }
    .dashboard-wrapper .stat-value { font-size: 21px !important; margin-bottom: 3px; }
    .dashboard-wrapper .stat-sub { font-size: 11.5px; }
    .dashboard-wrapper .stat-delta { margin-top: 8px; }
    .dashboard-wrapper .icon-box { width: 36px !important; height: 36px !important; }
    .dashboard-wrapper .icon-box i { font-size: 14px !important; }

    /* Cards / tables — tidy compact rows, consistent header height */
    .dashboard-wrapper .table-card-header { padding: 11px 18px !important; }
    .dashboard-wrapper .table > :not(caption) > * > * { padding-top: 7px !important; padding-bottom: 7px !important; }

    /* Chart fills its card so it lines up with the personnel table height */
    .dashboard-wrapper .table-card > .p-4 { padding: 12px 16px !important; min-height: 0 !important; }

    /* Map + bottom lists */
    #kioskMap { height: 195px !important; min-height: 175px !important; }
    .dashboard-wrapper .la-item { padding: 8px 20px !important; }
    .dashboard-wrapper .act-item { padding: 9px 20px !important; }
</style>
@endpush

@section('content')
<div class="container-fluid dashboard-wrapper">

    {{-- HEADER --}}
    @php
        $h = (int) now()->format('G');
        $greet = $h < 12 ? 'Good morning' : ($h < 18 ? 'Good afternoon' : 'Good evening');
    @endphp
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h1 class="greeting-title">{{ $greet }}, {{ auth()->user()->name ?? 'Admin' }} !</h1>
            <p class="greeting-sub">Here's what's happening at Jeyanco Construction today.</p>
        </div>
        <div class="clock-widget d-none d-sm-flex">
            <div class="clock-ic"><i class="fas fa-clock"></i></div>
            <div>
                <div class="clock-time" id="current-time">--:-- --</div>
                <div class="clock-date" id="current-date">{{ now()->format('l, F d, Y') }}</div>
            </div>
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
    <div class="row g-3 mb-4">

        <div class="col-xl-3 col-md-6">
            <div class="card analytics-card variant-blue p-4 h-100">
                <div class="stat-card-inner">
                    <div>
                        <div class="stat-label">Total Workforce</div>
                        <div class="stat-value">{{ $totalEmployees ?? 0 }}</div>
                        <p class="stat-sub">Active employees</p>
                    </div>
                    <div class="icon-box bg-primary bg-opacity-10">
                        <i class="fas fa-helmet-safety text-primary fa-lg"></i>
                    </div>
                </div>
                @if(($newThisWeek ?? 0) > 0)
                    <span class="stat-delta up"><i class="fas fa-arrow-up"></i> +{{ $newThisWeek }} <span class="sd-note">this week</span></span>
                @else
                    <span class="stat-delta flat"><i class="fas fa-minus"></i> 0 <span class="sd-note">new this week</span></span>
                @endif
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card analytics-card variant-green p-4 h-100">
                <div class="stat-card-inner">
                    <div>
                        <div class="stat-label">Present Today</div>
                        <div class="stat-value text-success">{{ $presentToday ?? 0 }}</div>
                        <p class="stat-sub">On-site attendance</p>
                    </div>
                    <div class="icon-box bg-success bg-opacity-10">
                        <i class="fas fa-user-check text-success fa-lg"></i>
                    </div>
                </div>
                <span class="stat-delta {{ $presentClass }}"><i class="fas {{ $presentIcon }}"></i> {{ $presentDelta > 0 ? '+' : '' }}{{ $presentDelta }} <span class="sd-note">vs yesterday</span></span>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card analytics-card variant-amber p-4 h-100">
                <div class="stat-card-inner">
                    <div>
                        <div class="stat-label">Weekly Payout</div>
                        <div class="stat-value">₱{{ number_format($weeklyPayroll ?? 0, 2) }}</div>
                        <p class="stat-sub">Scheduled payment</p>
                    </div>
                    <div class="icon-box bg-warning bg-opacity-10">
                        <i class="fas fa-coins text-warning fa-lg"></i>
                    </div>
                </div>
                <span class="stat-delta {{ $payoutClass }}"><i class="fas {{ $payoutIcon }}"></i> {{ $payoutPct > 0 ? '+' : '' }}{{ $payoutPct }}% <span class="sd-note">vs last week</span></span>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card analytics-card variant-red p-4 h-100">
                <div class="stat-card-inner">
                    <div>
                        <div class="stat-label">Pending Vale</div>
                        <div class="stat-value text-danger">₱{{ number_format($pendingVale ?? 0, 2) }}</div>
                        <p class="stat-sub">Outstanding balance</p>
                    </div>
                    <div class="icon-box bg-danger bg-opacity-10">
                        <i class="fas fa-file-invoice-dollar text-danger fa-lg"></i>
                    </div>
                </div>
                @if(($pendingVale ?? 0) > 0)
                    <span class="stat-delta down"><i class="fas fa-circle-exclamation"></i> Outstanding <span class="sd-note">balance</span></span>
                @else
                    <span class="stat-delta flat"><i class="fas fa-check"></i> All settled</span>
                @endif
            </div>
        </div>

    </div>

    {{-- ROW 2: Personnel Table + Chart --}}
    <div class="row g-3 mb-4">

        <div class="col-lg-7">
            <div class="table-card h-100 d-flex flex-column">
                <div class="table-card-header">
                    <h6><i class="fas fa-user-tie"></i> Recent Personnel</h6>
                    <button class="btn-view-all" data-bs-toggle="modal" data-bs-target="#employeeModal">
                        View All
                    </button>
                </div>
                <div class="table-responsive flex-grow-1">
                    <table class="table align-middle table-hover mb-0">
                        <thead>
                            <tr>
                                <th class="ps-4">Worker Name</th>
                                <th class="text-center">Position</th>
                                <th class="text-end pe-4">Vale Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($employees->take(6) as $emp)
                            <tr>
                                <td class="ps-4 fw-semibold">{{ $emp->name }}</td>
                                <td class="text-center">
                                    <span class="badge bg-light text-dark border">{{ $emp->position }}</span>
                                </td>
                                <td class="text-end pe-4 fw-semibold {{ $emp->vale > 0 ? 'text-danger' : 'text-muted' }}">
                                    ₱{{ number_format($emp->vale ?? 0, 2) }}
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="3" class="text-center py-5 text-muted">
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
                    <h6><i class="fas fa-chart-line"></i> Labor Hours Trend</h6>
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
                    <h6><i class="fas fa-user-clock"></i> Live Attendance
                        <span style="font-weight:500;color:var(--text-secondary);font-size:12px;">(Today)</span>
                    </h6>
                    <span class="badge" style="background:rgba(34,197,94,0.15);color:var(--success);font-weight:700;font-size:10.5px;letter-spacing:0.5px;">
                        <i class="fas fa-circle" style="font-size:6px;vertical-align:middle;"></i> LIVE
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
                            <span style="font-size:12px;">Attendance will appear here in real-time.</span>
                            <div class="mt-3"><a href="{{ url('/attendance') }}" class="btn btn-sm btn-primary">View Attendance</a></div>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Project Sites (interactive map — search / set location / live kiosk GPS) --}}
        <div class="col-lg-4">
            <div class="table-card h-100 d-flex flex-column">
                <div class="table-card-header">
                    <h6><i class="fas fa-map-location-dot"></i> Project Sites</h6>
                    <span id="kiosk-status" style="font-size:11.5px;color:var(--text-secondary);font-weight:500;">
                        <i class="fas fa-circle-notch fa-spin"></i> Locating…
                    </span>
                </div>
                <div class="p-3 flex-grow-1 d-flex flex-column">
                    <div class="map-ctl">
                        <input id="siteSearch" class="map-input" type="text" placeholder="Search a place…">
                        <button id="siteSearchBtn" class="map-btn secondary" type="button" title="Search"><i class="fas fa-search"></i></button>
                    </div>
                    <div class="map-ctl">
                        <select id="siteSelect" class="map-select" title="Piliin ang site na itatakda"></select>
                        <button id="siteSaveBtn" class="map-btn primary" type="button" title="Save location"><i class="fas fa-map-pin"></i> Save</button>
                    </div>
                    <div id="siteMapHint" class="map-hint">Pumili ng site, mag-search o mag-click sa map, tapos Save.</div>
                    <div id="kioskMap" class="rounded-3 overflow-hidden flex-grow-1"></div>
                </div>
            </div>
        </div>

        {{-- Recent Activities --}}
        <div class="col-lg-4">
            <div class="table-card h-100 d-flex flex-column">
                <div class="table-card-header">
                    <h6><i class="fas fa-wave-square"></i> Recent Activities</h6>
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
                        <i class="fas fa-users me-2"></i>Workforce Registry
                    </h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="ps-4">Name</th>
                                <th>Position</th>
                                <th>Rate / Hr</th>
                                <th class="pe-4 text-end">Vale</th>
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
                    <button type="button" class="btn btn-light border fw-semibold" data-bs-dismiss="modal">Close</button>
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
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(99,102,241,0.08)',
                    fill: true,
                    tension: 0.4,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: '#3b82f6',
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
        async function refreshLive() {
            try {
                const res = await fetch(`/api/location/latest?kiosk_id=${KIOSK_ID}`);
                const d = await res.json();
                if (d.lat && d.lng) {
                    const pos = [d.lat, d.lng];
                    if (liveMarker) liveMarker.setLatLng(pos);
                    else liveMarker = L.marker(pos, { icon: liveIcon }).addTo(map).bindPopup('Live kiosk position');
                    const t = d.recorded_at ? new Date(d.recorded_at).toLocaleTimeString() : '';
                    statusEl.innerHTML = `<i class="fas fa-circle text-success" style="font-size:8px;"></i> Live &middot; ${t}`;
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