@extends('layouts')

@section('page_title', 'Dashboard')

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<link rel="stylesheet" href="{{ asset('site-map.css') }}?v={{ @filemtime(public_path('site-map.css')) ?: '1' }}">
@include('dashboard._styles')
{{-- Laptop sizing, after the base so it wins the ties. --}}
@include('dashboard._laptop')
@endpush

@section('content')
@php
    $h     = (int) now()->format('G');
    $greet = $h < 12 ? 'Good morning' : ($h < 18 ? 'Good afternoon' : 'Good evening');

    $presentDelta = ($presentToday ?? 0) - ($presentYesterday ?? 0);
    $lwp          = $lastWeekPayroll ?? 0;
    $payoutPct    = $lwp > 0
        ? round(((($weeklyPayroll ?? 0) - $lwp) / $lwp) * 100, 1)
        : (($weeklyPayroll ?? 0) > 0 ? 100 : 0);

    $arrow = fn ($n) => $n > 0 ? 'up' : ($n < 0 ? 'down' : 'flat');
    $sign  = fn ($n) => ($n > 0 ? '+' : '') . $n;
@endphp

<div class="dash">

    {{-- ── Header: who, and when ─────────────────────────────────────────── --}}
    <x-page-header :title="$greet . ', ' . (auth()->user()->name ?? 'Admin')">
        <x-slot:actions>
            {{-- Same ids the live ticker and the month popover already use. --}}
            <div class="clock-shell" style="position:relative;">
                <div class="dash-clock" id="clockWidget" role="button" tabindex="0"
                     aria-haspopup="dialog" aria-expanded="false" title="{{ __('Open calendar') }}">
                    <div class="dash-clock-ic"><i class="fas fa-calendar-days"></i></div>
                    <div>
                        <div class="dash-clock-time" id="current-time">--:-- --</div>
                        <div class="dash-clock-date" id="current-date">{{ now()->format('l, F d, Y') }}</div>
                    </div>
                </div>
                <div class="mini-cal" id="miniCal" role="dialog" aria-label="{{ __('Calendar') }}" hidden></div>
            </div>
        </x-slot:actions>
    </x-page-header>

    {{-- ── Figures. Each tile links to the screen it summarises. ─────────── --}}
    {{-- Every one of these is a count of something that changes while the page
         is open, so they are re-read the moment any of it does. --}}
    <div class="dash-kpis" id="dash-kpis"
         data-live="attendance employees payroll advances devices leave">
        <a class="kpi" href="{{ route('employees.register') }}">
            <span class="kpi-ic blue"><i class="fas fa-helmet-safety"></i></span>
            <span class="kpi-body">
                <p class="kpi-label">{{ __('Active Workers') }}</p>
                <p class="kpi-value">{{ number_format($totalEmployees) }}</p>
                <p class="kpi-delta {{ ($newThisWeek ?? 0) > 0 ? 'up' : 'flat' }}">
                    {{ ($newThisWeek ?? 0) > 0 ? '+' . $newThisWeek . ' this week' : 'No new hires' }}
                </p>
            </span>
        </a>

        <a class="kpi" href="{{ url('/attendance') }}">
            <span class="kpi-ic green"><i class="fas fa-user-check"></i></span>
            <span class="kpi-body">
                <p class="kpi-label">{{ __('Present Today') }}</p>
                <p class="kpi-value">{{ number_format($presentToday) }}</p>
                <p class="kpi-delta {{ $arrow($presentDelta) }}">{{ $sign($presentDelta) }} vs yesterday</p>
            </span>
        </a>

        <a class="kpi" href="{{ url('/attendance') }}">
            <span class="kpi-ic {{ ($stillIn ?? 0) > 0 ? 'amber' : 'green' }}"><i class="fas fa-user-clock"></i></span>
            <span class="kpi-body">
                <p class="kpi-label">{{ __('Still Timed In') }}</p>
                <p class="kpi-value">{{ number_format($stillIn ?? 0) }}</p>
                <p class="kpi-delta flat">{{ ($stillIn ?? 0) > 0 ? 'Not yet timed out' : 'Everyone clocked out' }}</p>
            </span>
        </a>

        <a class="kpi" href="{{ url('/payroll-records') }}">
            <span class="kpi-ic blue"><i class="fas fa-peso-sign"></i></span>
            <span class="kpi-body">
                <p class="kpi-label">{{ __('This Week Payout') }}</p>
                <p class="kpi-value">₱{{ number_format($weeklyPayroll ?? 0, 2) }}</p>
                <p class="kpi-delta {{ $arrow($payoutPct) }}">{{ $sign($payoutPct) }}% vs last week</p>
            </span>
        </a>

        <a class="kpi" href="{{ route('settings.index') }}">
            <span class="kpi-ic {{ ($pendingVale ?? 0) > 0 ? 'amber' : 'green' }}"><i class="fas fa-hand-holding-dollar"></i></span>
            <span class="kpi-body">
                <p class="kpi-label">{{ __('Outstanding Vale') }}</p>
                <p class="kpi-value">₱{{ number_format($pendingVale ?? 0, 2) }}</p>
                <p class="kpi-delta flat">{{ ($pendingVale ?? 0) > 0 ? 'To be deducted' : 'All settled' }}</p>
            </span>
        </a>

        @php $devOffline = ($devices['offline'] ?? 0); @endphp
        <a class="kpi" href="{{ auth()->user()?->canAccessModule('devices') ? route('devices.index') : url('/sites') }}">
            <span class="kpi-ic {{ $devOffline > 0 ? 'red' : 'green' }}"><i class="fas fa-desktop"></i></span>
            <span class="kpi-body">
                <p class="kpi-label">{{ __('Kiosks Online') }}</p>
                <p class="kpi-value">{{ $devices['online'] ?? 0 }} / {{ $devices['total'] ?? 0 }}</p>
                <p class="kpi-delta {{ $devOffline > 0 ? 'down' : 'up' }}">
                    {{ $devOffline > 0 ? $devOffline . ' offline' : 'All reporting' }}
                </p>
            </span>
        </a>
    </div>

    {{-- ── The rest of the screen ────────────────────────────────────────── --}}
    <div class="dash-grid">

        {{-- Labor hours, last 7 days --}}
        <section class="panel area-chart" id="dash-chart" data-live="attendance">
            <div class="panel-head">
                <h2><i class="fas fa-chart-line"></i> {{ __('Attendance · Last 7 Days') }}</h2>
                <a class="panel-link" href="{{ url('/analytics') }}">{{ __('Analytics') }} <i class="fas fa-arrow-right"></i></a>
            </div>
            <div class="panel-body flexcol" id="dash-chart-body"
                 data-labels="{{ json_encode($attendanceLabels ?? []) }}"
                 data-values="{{ json_encode($attendanceData ?? []) }}">
                {{-- Chart.js writes its own width and height onto the canvas,
                     so the patch leaves it alone and redraws it instead. --}}
                <canvas id="attendanceChart" data-live-freeze></canvas>
            </div>
        </section>

        {{-- Live attendance, under the chart. A clock-in at the site
             appears here as it happens. --}}
        <section class="panel area-live" id="dash-live-attendance"
                 data-live="attendance employees">
            <div class="panel-head">
                <h2><i class="fas fa-user-clock"></i> {{ __('Live Attendance') }}</h2>
                <span class="panel-tag live">{{ __('TODAY') }}</span>
            </div>
            <div class="panel-body">
                @forelse($todayAttendance as $att)
                    <div class="row-item hoverable">
                        <div class="row-av">@include('partials.worker-icon')</div>
                        <div class="row-main">
                            <p class="row-title">{{ optional($att->employee)->name ?? __('Worker') }}</p>
                            <p class="row-sub">{{ optional($att->employee)->position ?? __('On site') }}</p>
                        </div>
                        <div class="row-right {{ $att->time_out ? '' : 'ok' }}">
                            {{ \Carbon\Carbon::parse($att->time_in)->format('g:i A') }}
                            @if(! $att->time_out)
                                <div class="row-sub" style="text-align:right;color:var(--success);">{{ __('in') }}</div>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="panel-empty">
                        <i class="fas fa-user-clock"></i>
                        <p>{{ __('Nobody has timed in yet today.') }}</p>
                        <p style="font-size:10.5px;">{{ __('Records appear here as the kiosk sends them.') }}</p>
                    </div>
                @endforelse
            </div>
            @if($todayAttendance->count())
                <div class="panel-head" style="border-top:1px solid var(--border);border-bottom:none;">
                    <span class="row-sub">{{ $presentToday }} {{ __('timed in today') }}</span>
                    <a class="panel-link" href="{{ url('/attendance') }}">{{ __('View all') }} <i class="fas fa-arrow-right"></i></a>
                </div>
            @endif
        </section>

        {{-- Project Sites — the existing tracker, across the right two
             thirds of the screen and its full height. --}}
        <section class="panel area-map site-tracker" id="siteTrackerCard">
            <div class="panel-head table-card-header" style="padding:8px 12px;">
                <h2><i class="fas fa-map-location-dot"></i> {{ __('Project Sites') }}</h2>
                {{-- Which site the map looks at: one of them, or all (js/site-map.js fills it). --}}
                <span id="kioskPick"></span>
                <span id="kiosk-status" style="font-size:10.5px;color:var(--text-secondary);font-weight:500;">
                    <i class="fas fa-circle-notch fa-spin"></i> {{ __('Locating…') }}
                </span>
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
            <div class="panel-body flexcol st-body">
                {{-- Read-only: every site with its range, and every kiosk where
                     its GPS last put it. Sites are pinned on the Sites page. --}}
                <div id="kioskMap" class="rounded-3 overflow-hidden flex-grow-1"
                     data-map-url="{{ route('dashboard.map') }}" data-sites-url="{{ route('sites.index') }}"></div>
            </div>
        </section>
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

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.5.1/dist/chart.umd.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="{{ asset('js/site-map.js') }}?v={{ @filemtime(public_path('js/site-map.js')) ?: '1' }}"></script>
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
        // The chart is canvas, so it inherits nothing. Take the colours from
        // the same custom properties the rest of the page is painted with, or
        // the grid stays light-mode white on a dark panel.
        const css       = getComputedStyle(document.documentElement);
        const tok       = (n, fb) => (css.getPropertyValue(n) || '').trim() || fb;
        const cBrand    = tok('--brand', '#1769E0');
        const cBorder   = tok('--border', '#e4e9f0');
        const cMuted    = tok('--text-muted', '#8a96a8');
        const cSurface  = tok('--bg-surface', '#ffffff');
        const cInk      = tok('--text-primary', '#0f1e33');
        const body   = document.getElementById('dash-chart-body');
        const series = () => {
            try {
                return {
                    labels: JSON.parse(body.dataset.labels || '[]'),
                    values: JSON.parse(body.dataset.values || '[]')
                };
            } catch (e) {
                return { labels: [], values: [] };
            }
        };
        const shown = series();

        const chart = new Chart(ctx.getContext('2d'), {
            type: 'line',
            data: {
                labels: shown.labels,
                datasets: [{
                    label: 'Workers Present',
                    data: shown.values,
                    borderColor: cBrand,
                    backgroundColor: 'transparent',
                    fill: true,
                    tension: 0.4,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    pointBackgroundColor: cSurface,
                    pointBorderColor: cBrand,
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
                        backgroundColor: cInk,
                        titleColor: cMuted,
                        bodyColor: cSurface,
                        padding: 10,
                        borderRadius: 8,
                        callbacks: { label: ctx => ' ' + ctx.parsed.y + (ctx.parsed.y === 1 ? ' worker' : ' workers') }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        border: { display: false },
                        grid: { color: cBorder, drawBorder: false, lineWidth: 1 },
                        // Whole workers, never 7.5 of one.
                        ticks: { font: { size: 10 }, color: cMuted, precision: 0, maxTicksLimit: 5 }
                    },
                    x: {
                        border: { display: false },
                        grid: { display: false },
                        ticks: { font: { size: 10 }, color: cMuted, maxRotation: 0, autoSkipPadding: 12 }
                    }
                }
            }
        });

        // Somebody timed in while this was open: the same seven days, redrawn
        // from the figures the patch brought in. No page reload, no re-fetch
        // of its own — the panel it sits in has already been re-read.
        document.addEventListener('live:updated', function (e) {
            if (!body || !e.target || !e.target.contains(body)) { return; }

            const now = series();
            chart.data.labels = now.labels;
            chart.data.datasets[0].data = now.values;
            chart.update('none');
        });
    }

    // ---- PROJECT SITES MAP (Leaflet / OpenStreetMap — libre, walang API key) ----
    // Read-only. Every pinned site with its range, and every kiosk where its
    // GPS last put it, marked by whether that is inside its site's range.
    // Sites are pinned and moved on the Sites page, not here. The pins, the
    // kiosk badges, the key and the popups are js/site-map.js, which the
    // Sites page map draws as well.
    (function () {
        const mapEl = document.getElementById('kioskMap');
        if (!mapEl || !window.JeyancoSiteMap) return;

        const NAGA     = [13.6218, 123.1948];   // Naga City, Camarines Sur — default center
        const statusEl = document.getElementById('kiosk-status');

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

        const layer = JeyancoSiteMap(map, {
            mapUrl:   mapEl.dataset.mapUrl,
            sitesUrl: mapEl.dataset.sitesUrl,
            statusEl,
            pickEl:   document.getElementById('kioskPick'),
        });

        statusEl.style.cursor = 'pointer';
        statusEl.title = 'Show every site and kiosk on the map';
        statusEl.addEventListener('click', layer.fitAll);

        // The tracker's own heartbeat, read as the dashboard always has.
        // Reading it is what notices a kiosk that has gone quiet: the offline
        // alert is raised lazily, on read, since nothing here runs on a timer
        // (KioskLocationController).
        const TRACKER_ID = 'jeyanco-01';

        function refresh() {
            fetch(`/api/location/latest?kiosk_id=${TRACKER_ID}`).catch(() => {});
            layer.refresh();
        }
        refresh();

        // A fix is cached rather than saved, and the API says so the moment
        // one lands, so a kiosk's marker moves when the kiosk does. Silence
        // announces nothing, so the map also looks again every minute: that is
        // how the pulse round a kiosk that stopped talking turns orange.
        Live.on('kiosk devices sites', refresh);
        setInterval(() => { if (!document.hidden) refresh(); }, 60000);
    })();
</script>
@endsection
