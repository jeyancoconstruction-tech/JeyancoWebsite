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
                    <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:10px;">
                        <input id="siteSearch" type="text" placeholder="Search a place… (hal. Naga City)"
                               style="flex:1;min-width:200px;padding:8px 12px;border-radius:8px;border:1px solid #334155;background:#0f172a;color:#f8fafc;font-size:13px;">
                        <button id="siteSearchBtn" type="button"
                                style="padding:8px 14px;border:none;border-radius:8px;background:#334155;color:#fff;font-size:13px;font-weight:600;cursor:pointer;">
                            <i class="fas fa-search"></i> Search
                        </button>
                        <select id="siteSelect" title="Piliin ang site na itatakda"
                                style="padding:8px 12px;border-radius:8px;border:1px solid #334155;background:#0f172a;color:#f8fafc;font-size:13px;min-width:150px;"></select>
                        <button id="siteSaveBtn" type="button"
                                style="padding:8px 14px;border:none;border-radius:8px;background:#2563eb;color:#fff;font-size:13px;font-weight:600;cursor:pointer;">
                            <i class="fas fa-map-pin"></i> Save location
                        </button>
                    </div>
                    <div id="siteMapHint" style="font-size:12px;color:#94a3b8;margin-bottom:8px;">
                        Pumili ng site, mag-search o mag-click sa map para itakda ang lokasyon, tapos i-Save.
                    </div>
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