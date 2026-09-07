@extends('layouts')

@section('page_title', 'Dashboard')

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
@include('dashboard._styles')
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

    {{-- ── Strip: who, when, and the four things worth one click ────────── --}}
    <div class="dash-bar">
        <div class="dash-greet">
            <h1>{{ $greet }}, {{ auth()->user()->name ?? 'Admin' }}</h1>
            <p>{{ __('Here is what is happening at Jeyanco Construction today.') }}</p>
        </div>

        <div class="dash-bar-right">
            {{-- Every one of these goes somewhere real. --}}
            <a class="dash-act primary" href="{{ route('employees.create') }}">
                <i class="fas fa-user-plus"></i> {{ __('Register Worker') }}
            </a>
            @if(auth()->user()?->canAccessModule('payroll-processing'))
                <a class="dash-act" href="{{ route('payroll-processing.index') }}">
                    <i class="fas fa-calculator"></i> {{ __('Payroll Run') }}
                </a>
            @endif
            <a class="dash-act" href="{{ url('/attendance') }}">
                <i class="fas fa-calendar-check"></i> {{ __('Attendance') }}
            </a>
            <a class="dash-act" href="{{ url('/payroll-records') }}">
                <i class="fas fa-receipt"></i> {{ __('Payroll Records') }}
            </a>

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
        </div>
    </div>

    {{-- ── Figures. Each tile links to the screen it summarises. ─────────── --}}
    <div class="dash-kpis">
        <a class="kpi" href="{{ url('/employees') }}">
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
        <section class="panel area-chart">
            <div class="panel-head">
                <h2><i class="fas fa-chart-line"></i> {{ __('Attendance · Last 7 Days') }}</h2>
                <a class="panel-link" href="{{ url('/analytics') }}">{{ __('Analytics') }} <i class="fas fa-arrow-right"></i></a>
            </div>
            <div class="panel-body flexcol">
                <canvas id="attendanceChart"></canvas>
            </div>
        </section>

        {{-- Project Sites — the existing tracker, in a tighter frame --}}
        <section class="panel area-map site-tracker" id="siteTrackerCard">
            <div class="panel-head table-card-header" style="padding:8px 12px;">
                <h2><i class="fas fa-map-location-dot"></i> {{ __('Project Sites') }}</h2>
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

                    {{-- One row. Two stacked rows plus a wrapping hint left the
                         map about 100px tall, which is not a map. Every id is
                         exactly as it was — the Leaflet code is untouched. --}}
                    <div class="map-ctl">
                        <input id="siteSearch" class="map-input" type="text" placeholder="{{ __('Search a place…') }}">
                        <button id="siteSearchBtn" class="map-btn secondary" type="button" title="{{ __('Search') }}"><i class="fas fa-search"></i></button>
                        <select id="siteSelect" class="map-select" title="{{ __('Piliin ang site na itatakda') }}"></select>
                        <button id="siteSaveBtn" class="map-btn primary" type="button" title="{{ __('Save location') }}"><i class="fas fa-map-pin"></i> {{ __('Save') }}</button>
                    </div>
                    <div id="siteMapHint" class="map-hint">{{ __('Search or click the map, then Save.') }}</div>
                    <div id="kioskMap" class="rounded-3 overflow-hidden flex-grow-1"></div>
            </div>
        </section>

        {{-- Live attendance, full height --}}
        <section class="panel area-live">
            <div class="panel-head">
                <h2><i class="fas fa-user-clock"></i> {{ __('Live Attendance') }}</h2>
                <span class="panel-tag live">{{ __('TODAY') }}</span>
            </div>
            <div class="panel-body">
                @forelse($todayAttendance as $att)
                    <div class="row-item hoverable">
                        <div class="row-av">{{ strtoupper(substr(optional($att->employee)->name ?? 'W', 0, 1)) }}</div>
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

        {{-- What is waiting for someone. Every row is a real count and a real link. --}}
        <section class="panel area-todo">
            <div class="panel-head">
                <h2><i class="fas fa-clipboard-check"></i> {{ __('Needs Attention') }}</h2>
                @if(count($attention ?? []))
                    <span class="panel-tag muted">{{ count($attention) }}</span>
                @endif
            </div>
            <div class="panel-body">
                @forelse($attention ?? [] as $a)
                    <a class="row-item" href="{{ $a['url'] }}">
                        <div class="row-av {{ $a['tone'] }}"><i class="fas {{ $a['icon'] }}"></i></div>
                        <div class="row-main">
                            <p class="row-title">{{ __($a['label']) }}</p>
                        </div>
                        <span class="row-count {{ $a['tone'] }}">{{ $a['count'] }}</span>
                    </a>
                @empty
                    <div class="panel-empty">
                        <i class="fas fa-circle-check ok"></i>
                        <p>{{ __('Nothing waiting.') }}</p>
                        <p style="font-size:10.5px;">{{ __('Approvals and open payroll runs show up here.') }}</p>
                    </div>
                @endforelse
            </div>
        </section>

        {{-- Recent activity --}}
        <section class="panel area-feed">
            <div class="panel-head">
                <h2><i class="fas fa-wave-square"></i> {{ __('Recent Activity') }}</h2>
                <a class="panel-link" href="{{ auth()->user()?->isAdmin() ? route('audit-logs.index') : url('/employees') }}">
                    {{ auth()->user()?->isAdmin() ? __('Audit log') : __('Employees') }} <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            <div class="panel-body">
                @forelse($recentActivities as $act)
                    <div class="row-item hoverable">
                        <div class="row-av" style="background:{{ $act['color'] }}1f;color:{{ $act['color'] }};">
                            <i class="fas {{ $act['icon'] }}"></i>
                        </div>
                        <div class="row-main">
                            <p class="row-title">{{ $act['title'] }}</p>
                            <p class="row-sub">{{ $act['subtitle'] }}</p>
                        </div>
                        <span class="row-sub" style="flex:none;">{{ $act['time']->diffForHumans(null, true) }}</span>
                    </div>
                @empty
                    <div class="panel-empty">
                        <i class="fas fa-clock-rotate-left"></i>
                        <p>{{ __('No activity yet.') }}</p>
                    </div>
                @endforelse
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
        new Chart(ctx.getContext('2d'), {
            type: 'line',
            data: {
                labels: {!! json_encode($attendanceLabels ?? []) !!},
                datasets: [{
                    label: 'Hours Worked',
                    data: {!! json_encode($attendanceData ?? []) !!},
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
                        callbacks: { label: ctx => ' ' + ctx.parsed.y + ' hrs' }
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
                setHint('Pumili ng site, i-click ang mapa, tapos Save.');
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
