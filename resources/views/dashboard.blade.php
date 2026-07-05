@extends('layouts')

@section('page_title', 'Dashboard')

@section('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<style>
    .dashboard-wrapper { padding-top: 24px; padding-bottom: 48px; }

    /* Stat card layout */
    .stat-card-inner {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
    }
    .stat-label {
        font-size: 11px;
        font-weight: 700;
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 8px;
    }
    .stat-value {
        font-size: 1.875rem;
        font-weight: 800;
        color: #0f172a;
        line-height: 1;
        margin-bottom: 5px;
        letter-spacing: -0.5px;
    }
    .stat-sub {
        font-size: 12px;
        color: #94a3b8;
        margin: 0;
    }
    .icon-box {
        width: 48px;
        height: 48px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 12px;
        flex-shrink: 0;
        transition: transform 0.2s ease;
    }
    .analytics-card:hover .icon-box { transform: scale(1.08); }

    /* Clock */
    .clock-widget {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 12px 18px;
        text-align: right;
        box-shadow: 0 1px 2px rgba(0,0,0,0.04);
    }
    .clock-time {
        font-size: 1.1rem;
        font-weight: 700;
        color: #0f172a;
        font-variant-numeric: tabular-nums;
        letter-spacing: 0.5px;
        line-height: 1.2;
    }
    .clock-date {
        font-size: 10.5px;
        color: #94a3b8;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-top: 2px;
    }

    /* Leaflet map fixes inside card */
    #kioskMap { height: 340px; }
    #kioskMap .leaflet-control-attribution { font-size: 9px; }
</style>
@endsection

@section('content')
<div class="container-fluid dashboard-wrapper">

    {{-- HEADER --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <p class="mb-1" style="font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:0.5px;">
                WORKSPACE &rsaquo; REAL-TIME
            </p>
            <h4 class="fw-bold mb-0" style="color:#0f172a;letter-spacing:-0.3px;">Dashboard</h4>
        </div>
        <div class="clock-widget d-none d-sm-block">
            <div class="clock-time" id="current-time">--:-- --</div>
            <div class="clock-date"><i class="fas fa-calendar-day me-1"></i>{{ date('m/d/Y') }}</div>
        </div>
    </div>

    {{-- ROW 1: Stat Cards --}}
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

    {{-- ROW 3: Active Project Map (LIVE GPS) --}}
    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="table-card">
                <div class="table-card-header">
                    <h6>
                        <i class="fas fa-map-location-dot"></i>
                        Active Project Site
                    </h6>
                    <span id="kiosk-status" style="font-size:12px;color:#94a3b8;font-weight:500;">
                        <i class="fas fa-circle-notch fa-spin"></i> Locating kiosk...
                    </span>
                </div>
                <div class="p-3">
                    <div id="kioskMap" class="rounded-3 overflow-hidden"
                         style="height:340px;border:1px solid #e2e8f0;"></div>
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
    // Live clock
    (function tick() {
        const el = document.getElementById('current-time');
        if (el) el.innerText = new Date().toLocaleTimeString('en-US', { hour:'2-digit', minute:'2-digit', second:'2-digit', hour12:true });
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
                    borderColor: '#6366f1',
                    backgroundColor: 'rgba(99,102,241,0.08)',
                    fill: true,
                    tension: 0.4,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: '#6366f1',
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

    // ---- LIVE KIOSK GPS MAP ----
    (function () {
        const KIOSK_ID = 'jeyanco-01';
        const HOME = [13.0, 124.0];   // TODO: palitan ng totoong site coords mo

        const mapEl = document.getElementById('kioskMap');
        if (!mapEl) return;

        const map = L.map('kioskMap').setView(HOME, 16);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap',
            maxZoom: 19,
        }).addTo(map);

        const marker = L.marker(HOME).addTo(map);
        const statusEl = document.getElementById('kiosk-status');
        let hasFix = false;

        async function refresh() {
            try {
                const res = await fetch(`/api/location/latest?kiosk_id=${KIOSK_ID}`);
                const d = await res.json();
                if (d.lat && d.lng) {
                    const pos = [d.lat, d.lng];
                    marker.setLatLng(pos);
                    if (!hasFix) { map.setView(pos, 17); hasFix = true; }
                    else { map.panTo(pos); }
                    const t = d.recorded_at ? new Date(d.recorded_at).toLocaleTimeString() : '';
                    statusEl.innerHTML = `<i class="fas fa-circle text-success" style="font-size:8px;"></i> Live &middot; ${t}`;
                } else {
                    statusEl.innerHTML = `<i class="fas fa-circle text-warning" style="font-size:8px;"></i> Waiting for GPS fix`;
                }
            } catch (e) {
                statusEl.innerHTML = `<i class="fas fa-circle text-danger" style="font-size:8px;"></i> Offline`;
            }
        }

        refresh();
        setInterval(refresh, 10000);   // every 10s

        // Leaflet needs a size recalc if the card animates/loads in
        setTimeout(() => map.invalidateSize(), 300);
    })();
</script>
@endsection