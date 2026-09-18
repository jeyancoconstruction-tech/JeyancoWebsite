@extends('layouts')
@section('page_title', 'Device Monitoring')

@php
    use Illuminate\Support\Str;

    // "42 s", "1 min 50 s", "2 h 14 min", "3 d 4 h".
    $dur = function (?int $s) {
        if ($s === null) return 'never';
        if ($s < 60) return $s . ' s';
        if ($s < 180) return intdiv($s, 60) . ' min' . ($s % 60 ? ' ' . ($s % 60) . ' s' : '');
        if ($s < 3600) return intdiv($s, 60) . ' min';
        if ($s < 86400) return intdiv($s, 3600) . ' h' . (intdiv($s % 3600, 60) ? ' ' . intdiv($s % 3600, 60) . ' min' : '');
        return intdiv($s, 86400) . ' d' . (intdiv($s % 86400, 3600) ? ' ' . intdiv($s % 86400, 3600) . ' h' : '');
    };
    $pct   = fn ($h) => round($h / $hours * 100, 3) . '%';
    $tone  = ['ok' => 'ok', 'late' => 'warn', 'off' => 'danger'];
    $badge = ['ok' => 'Online', 'late' => 'Online · late', 'off' => 'Offline'];
    $axis  = collect(range(0, $hours - 1))->map(fn ($i) => $i % 2 ? '' : \Illuminate\Support\Carbon::today()->setHour($firstHour + $i)->format('ga'))
                ->map(fn ($l) => rtrim($l, 'm'));
    $peak  = max(1, $devices->flatMap(fn ($d) => $d['hours'])->max() ?? 1);
    $silent = $devices->where('state', 'off');
    $first  = $silent->first();
    $late   = $devices->where('state', 'late')->first();
    // A steady mark on the locator, from the coordinates themselves.
    $spot = fn ($v) => $v === null ? 50 : 18 + (int) (fmod(abs($v) * 1000, 1) * 64);
@endphp

@push('styles')
@include('system._kit')
<style>
.dv-fleet { display: grid; grid-template-columns: 1.15fr 1fr 1fr 1.55fr; margin-bottom: 14px; }
@media (max-width: 1100px) { .dv-fleet { grid-template-columns: 1fr 1fr; } }
.dv-fleet > div { padding: 14px 18px 15px; border-right: 1px solid var(--border); }
.dv-fleet > div:last-child { border-right: none; }
.dv-k { font-size: 12px; color: var(--text-secondary); font-weight: 500; display: flex; align-items: center; gap: 6px; }
.dv-k svg { width: 14px; height: 14px; color: var(--text-muted); }
.dv-v { font-size: 26px; font-weight: 700; letter-spacing: -.02em; margin-top: 7px; line-height: 1; color: var(--text-primary); }
.dv-v small { font-size: 14px; color: var(--text-muted); font-weight: 600; margin-left: 2px; }
.dv-s { font-size: 11.5px; color: var(--text-muted); margin-top: 8px; }
.dv-seg { display: flex; gap: 3px; margin-top: 10px; }
.dv-seg i { flex: 1; height: 8px; border-radius: 2px; background: var(--success); }
.dv-seg i.late { background: var(--warning); } .dv-seg i.off { background: var(--danger); }
.dv-rule { display: grid; grid-template-columns: auto 1fr; gap: 6px 10px; margin-top: 8px; font-size: 12px; color: var(--text-secondary); align-items: center; }
.dv-rule .sx-badge { justify-self: start; }

.dv-listhead { display: flex; align-items: center; gap: 10px; margin: 0 0 10px; flex-wrap: wrap; }
.dv-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
@media (max-width: 1100px) { .dv-grid { grid-template-columns: 1fr; } }
.dv-card { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; box-shadow: var(--shadow-xs); position: relative; }
.dv-card.is-off { border-color: color-mix(in srgb, var(--danger) 40%, var(--border)); }
.dv-card.is-off::before, .dv-card.is-late::before { content: ""; position: absolute; left: -1px; right: -1px; top: -1px; height: 3px; border-radius: 12px 12px 0 0; background: var(--danger); }
.dv-card.is-late::before { background: var(--warning); }
.dv-head { display: flex; align-items: center; gap: 12px; padding: 14px 16px; border-bottom: 1px solid var(--border); }
.dv-ico { width: 38px; height: 38px; border-radius: 10px; display: grid; place-items: center; background: var(--success-soft); color: var(--success); position: relative; flex: none; }
.dv-ico svg { width: 19px; height: 19px; }
.dv-ico::after { content: ""; position: absolute; right: -3px; bottom: -3px; width: 12px; height: 12px; border-radius: 50%; background: var(--success); border: 2.5px solid var(--surface); }
.is-late .dv-ico { background: var(--warning-soft); color: var(--warning); } .is-late .dv-ico::after { background: var(--warning); }
.is-off .dv-ico { background: var(--danger-soft); color: var(--danger); } .is-off .dv-ico::after { background: var(--danger); }
.dv-name { font-size: 14.5px; font-weight: 700; color: var(--text-primary); }
.dv-meta { font-size: 12px; color: var(--text-muted); display: flex; align-items: center; gap: 6px; margin-top: 2px; flex-wrap: wrap; }
.dv-meta svg { width: 12px; height: 12px; }
.dv-head .sx-badge { margin-left: auto; }
.dv-sec { padding: 12px 16px 14px; border-bottom: 1px solid var(--border); }
.dv-row { display: flex; align-items: baseline; gap: 8px; margin-bottom: 9px; }
.dv-lbl { font-size: 12px; font-weight: 600; color: var(--text-secondary); }
.dv-val { margin-left: auto; font-family: 'JetBrains Mono', monospace; font-size: 13px; font-weight: 600; color: var(--text-primary); }
.dv-val.ok { color: var(--success); } .dv-val.warn { color: var(--warning); } .dv-val.danger { color: var(--danger); }
.dv-at { font-family: 'JetBrains Mono', monospace; font-size: 11px; color: var(--text-muted); }

.ruler-track { position: relative; height: 12px; border-radius: 3px; background: var(--bg-subtle); border: 1px solid var(--border); }
.ruler-late { position: absolute; top: 0; bottom: 0; right: 0; background: repeating-linear-gradient(135deg, color-mix(in srgb, var(--warning) 14%, transparent) 0 3px, transparent 3px 6px); border-left: 1px dashed color-mix(in srgb, var(--warning) 60%, transparent); }
.ruler-fill { position: absolute; left: 0; top: 0; bottom: 0; border-radius: 2px 0 0 2px; }
.ruler-fill.ok { background: var(--success); } .ruler-fill.warn { background: var(--warning); } .ruler-fill.danger { background: var(--danger); border-radius: 2px; }
.ruler-mark { position: absolute; top: -5px; width: 2px; height: 20px; margin-left: -1px; background: var(--text-primary); border-radius: 1px; }
.ruler-ticks { height: 7px; margin-top: 2px; border-right: 1px solid var(--tick-major);
    background-image: linear-gradient(90deg, var(--tick-major) 1px, transparent 1px), linear-gradient(90deg, var(--tick) 1px, transparent 1px);
    background-size: calc(100% / 6) 7px, calc(100% / 18) 4px; background-repeat: repeat-x; }
.ruler-lbl { display: flex; justify-content: space-between; font-family: 'JetBrains Mono', monospace; font-size: 9.5px; color: var(--text-muted); margin-top: 3px; }
.ruler-lbl span:last-child { color: var(--danger); font-weight: 600; }
.ruler-over { display: flex; align-items: center; gap: 6px; margin-top: 8px; font-size: 12px; font-weight: 600; color: var(--danger); }
.ruler-over svg { width: 14px; height: 14px; }

.hr { position: relative; height: 62px; display: grid; grid-template-columns: repeat({{ $hours }}, 1fr); gap: 4px; align-items: end; }
.hr-band { position: absolute; top: 0; bottom: 0; background: color-mix(in srgb, var(--brand) 6%, transparent); border-left: 1px dashed color-mix(in srgb, var(--brand) 35%, transparent); border-right: 1px dashed color-mix(in srgb, var(--brand) 35%, transparent); }
.hr-band span { position: absolute; top: 2px; left: 5px; font: 600 9px 'JetBrains Mono', monospace; color: color-mix(in srgb, var(--brand) 75%, var(--text-muted)); }
.hr i { display: block; background: var(--brand); border-radius: 2px 2px 0 0; position: relative; z-index: 1; }
.hr i.zero { background: var(--border-md); height: 2px; }
.hr i.fut { height: 8px; border: 1px dashed var(--border-md); border-bottom: none; background: transparent; }
.hr-now { position: absolute; top: -4px; bottom: 0; border-left: 1.5px solid var(--text-primary); z-index: 2; }
.hr-now::before { content: "now"; position: absolute; top: -3px; left: 4px; font: 600 9.5px 'JetBrains Mono', monospace; color: var(--text-primary); }
.hr-silent { position: absolute; top: 0; bottom: 0; background: repeating-linear-gradient(135deg, color-mix(in srgb, var(--danger) 18%, transparent) 0 4px, transparent 4px 8px); border-left: 1.5px solid var(--danger); z-index: 1; }
.hr-silent span { position: absolute; bottom: 4px; left: 5px; font: 700 9px 'JetBrains Mono', monospace; color: var(--danger); background: var(--surface); padding: 0 3px; border-radius: 3px; }
.hr-x { display: grid; grid-template-columns: repeat({{ $hours }}, 1fr); gap: 4px; font-family: 'JetBrains Mono', monospace; font-size: 9.5px; color: var(--text-muted); margin-top: 5px; border-top: 1px solid var(--border-md); padding-top: 3px; }

.dv-foot { display: grid; grid-template-columns: 1.15fr 1fr; }
.dv-cell { padding: 12px 16px; display: flex; gap: 12px; align-items: center; min-width: 0; }
.dv-cell + .dv-cell { border-left: 1px solid var(--border); }
.dv-cell .v { font-size: 12.5px; font-weight: 600; color: var(--text-primary); margin-top: 3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.dv-cell .s { font-family: 'JetBrains Mono', monospace; font-size: 11px; color: var(--text-muted); margin-top: 2px; white-space: nowrap; }
.dv-cell .sx-link { font-size: 11.5px; margin-top: 3px; }
.gps { width: 84px; height: 60px; border-radius: 8px; border: 1px solid var(--border); position: relative; overflow: hidden; flex: none; background-color: var(--bg-subtle);
    background-image: linear-gradient(var(--border) 1px, transparent 1px), linear-gradient(90deg, var(--border) 1px, transparent 1px); background-size: 12px 12px; }
.gps .h { position: absolute; left: 0; right: 0; height: 1px; background: color-mix(in srgb, var(--brand) 45%, transparent); }
.gps .vv { position: absolute; top: 0; bottom: 0; width: 1px; background: color-mix(in srgb, var(--brand) 45%, transparent); }
.gps .x { position: absolute; width: 18px; height: 18px; margin: -9px 0 0 -9px; border-radius: 50%; background: color-mix(in srgb, var(--brand) 22%, transparent); display: grid; place-items: center; }
.gps .x::after { content: ""; width: 7px; height: 7px; border-radius: 50%; background: var(--brand); box-shadow: 0 0 0 2px var(--surface); }
.gps.stale .h, .gps.stale .vv { background: color-mix(in srgb, var(--text-muted) 40%, transparent); }
.gps.stale .x { background: color-mix(in srgb, var(--text-muted) 18%, transparent); }
.gps.stale .x::after { background: var(--text-muted); }
.gps.none { display: grid; place-items: center; color: var(--warning); background-image: none; }
.gps.none svg { width: 20px; height: 20px; }
.dv-table-view { display: none; }
.dv-live.as-table .dv-grid { display: none; }
.dv-live.as-table .dv-table-view { display: block; }
</style>
@endpush

@section('content')
<div class="sx-page">

    <div class="sx-head">
        <div>
            <div class="sx-eyebrow">System · 03 / 04</div>
            <h1 class="sx-title">Device Monitoring</h1>
            <p class="sx-sub">The attendance kiosks and whether they’re still talking to us. Read-only — nothing here changes how a kiosk works or what it records.</p>
        </div>
    </div>

    <div id="dv-live" class="dv-live">
        {{-- ── Status line ─────────────────────────────────────────────── --}}
        @if($summary['total'] === 0)
            <div class="sx-status info">
                <span class="sx-status-ico"><i data-lucide="monitor-smartphone"></i></span>
                <span><b>No kiosks registered</b> — a kiosk appears here once it has been added to the system</span>
        @elseif($first)
            <div class="sx-status danger">
                <span class="sx-status-ico"><i data-lucide="wifi-off"></i></span>
                @if($silent->count() === 1)
                    <span><b>{{ $first['kiosk']->name }}</b> <span class="mono" style="font-size:12px">{{ $first['kiosk']->code }}</span>
                        {{ $first['seconds'] === null ? 'has never reported' : 'has been silent for' }} @if($first['seconds'] !== null)<b>{{ $dur($first['seconds']) }}</b>@endif</span>
                @else
                    <span><b>{{ $silent->count() }} kiosks are silent</b> — {{ $silent->map(fn ($d) => $d['kiosk']->name)->implode(', ') }}</span>
                @endif
                @if($summary['online'])<span class="dotsep"></span><span>the other {{ $summary['online'] }} {{ $summary['online'] === 1 ? 'is' : 'are' }} reporting</span>@endif
        @elseif($late)
            <div class="sx-status warn">
                <span class="sx-status-ico"><i data-lucide="wifi"></i></span>
                <span><b>{{ $late['kiosk']->name }}</b> missed its last heartbeat — heard from <b>{{ $dur($late['seconds']) }}</b> ago</span>
        @else
            <div class="sx-status">
                <span class="sx-status-ico"><i data-lucide="wifi"></i></span>
                <span><b>All {{ $summary['total'] }} {{ Str::plural('kiosk', $summary['total']) }}</b> {{ $summary['total'] === 1 ? 'is' : 'are' }} reporting</span>
        @endif
                <span class="sx-status-end"><span class="live" data-checked>Live · checked {{ $checkedAt->format('g:i:s A') }}</span><a class="sx-btn sm" href="{{ route('devices.index') }}" data-refresh><i data-lucide="refresh-cw"></i> Refresh</a></span>
            </div>

        {{-- ── Fleet strip ─────────────────────────────────────────────── --}}
        <div class="sx-card dv-fleet">
            <div>
                <div class="dv-k"><i data-lucide="radio"></i> Online now</div>
                <div class="dv-v">{{ $summary['online'] }}<small>/ {{ $summary['total'] }}</small></div>
                <div class="dv-seg">@foreach($devices as $d)<i class="{{ $d['state'] === 'ok' ? '' : $d['state'] }}" title="{{ $d['kiosk']->name }}"></i>@endforeach</div>
                <div class="dv-s">{{ $summary['late'] }} late · {{ $summary['offline'] }} offline</div>
            </div>
            <div>
                <div class="dv-k"><i data-lucide="scan-face"></i> Scans this workday</div>
                <div class="dv-v">{{ number_format($summary['scans']) }}</div>
                <div class="dv-s">time-ins and time-outs · {{ $summary['total'] }} {{ Str::plural('kiosk', $summary['total']) }}</div>
            </div>
            <div>
                <div class="dv-k"><i data-lucide="locate-fixed"></i> GPS fix</div>
                <div class="dv-v">{{ $summary['fix'] }}<small>/ {{ $summary['total'] }}</small></div>
                <div class="dv-s">{{ $summary['nosig'] }} no signal · {{ $summary['stale'] }} last known</div>
            </div>
            <div>
                <div class="dv-k"><i data-lucide="info"></i> How status is decided</div>
                <div class="dv-rule">
                    <span class="sx-badge ok">Online</span><span>heartbeat within the last {{ $lateAfter % 60 ? rtrim(rtrim(number_format($lateAfter / 60, 1), '0'), '.') : $lateAfter / 60 }} min</span>
                    <span class="sx-badge warn">Late</span><span>{{ rtrim(rtrim(number_format($lateAfter / 60, 1), '0'), '.') }} – {{ $offlineAfter / 60 }} min since the last heartbeat</span>
                    <span class="sx-badge danger">Offline</span><span>nothing for {{ $offlineAfter / 60 }} min or more</span>
                </div>
            </div>
        </div>

        {{-- ── B · Kiosks ──────────────────────────────────────────────── --}}
        <div class="dv-listhead">
            <span class="sx-idx">B</span><h2 class="sx-card-title">Kiosks</h2><span class="sx-card-note">{{ $summary['total'] }} registered · the ones that need attention come first</span>
            <div class="sx-card-tools"><div class="sx-seg" data-view-toggle>
                <button type="button" class="on" data-view="cards"><i data-lucide="layout-grid"></i> Cards</button>
                <button type="button" data-view="table"><i data-lucide="list"></i> Table</button>
            </div></div>
        </div>

        <div class="dv-grid">
            @foreach($devices as $d)
                @php
                    $k = $d['kiosk']; $t = $tone[$d['state']];
                    $nowSlot = $nowAt;
                @endphp
                <article class="dv-card is-{{ $d['state'] }}">
                    <header class="dv-head">
                        <div class="dv-ico"><i data-lucide="monitor-smartphone"></i></div>
                        <div style="min-width:0">
                            <div class="dv-name">{{ $k->name }}</div>
                            <div class="dv-meta"><span class="mono">{{ $k->code }}</span>·<i data-lucide="map-pin"></i>{{ $k->site->name ?? 'Unassigned' }}</div>
                        </div>
                        <span class="sx-badge {{ $t }}">{{ $badge[$d['state']] }}</span>
                    </header>

                    <section class="dv-sec">
                        <div class="dv-row">
                            <span class="dv-lbl">Last heartbeat</span>
                            <span class="dv-val {{ $t }}">{{ $d['seconds'] === null ? 'never' : $dur($d['seconds']) . ' ago' }}</span>
                            @if($d['last_seen'])<span class="dv-at">{{ $d['last_seen']->format('H:i:s') }}</span>@endif
                        </div>
                        <div class="ruler-track">
                            <div class="ruler-late" style="left: {{ $lateAfter / $offlineAfter * 100 }}%"></div>
                            <div class="ruler-fill {{ $t }}" style="width: {{ $d['pct'] }}%"></div>
                            @if($d['pct'] < 100)<div class="ruler-mark" style="left: {{ $d['pct'] }}%"></div>@endif
                        </div>
                        <div class="ruler-ticks"></div>
                        <div class="ruler-lbl"><span>0</span><span>30s</span><span>1m</span><span>1m30</span><span>2m</span><span>2m30</span><span>3m · offline</span></div>
                        @if($d['over'])
                            <div class="ruler-over"><i data-lucide="triangle-alert"></i>{{ $dur($d['over']) }} past the {{ $offlineAfter / 60 }}-minute limit</div>
                        @elseif($d['seconds'] === null)
                            <div class="ruler-over"><i data-lucide="triangle-alert"></i>No heartbeat received from this kiosk yet</div>
                        @endif
                    </section>

                    <section class="dv-sec">
                        <div class="dv-row"><span class="dv-lbl">Scans this workday, by hour</span><span class="dv-val">{{ $d['scans'] }}</span></div>
                        <div class="hr">
                            @foreach($bands as $band)
                                <div class="hr-band" style="left: {{ $pct($band['from']) }}; width: {{ $pct($band['to'] - $band['from']) }}"><span>{{ $band['label'] }}</span></div>
                            @endforeach
                            @foreach($d['hours'] as $i => $n)
                                @if($i > $nowSlot)
                                    <i class="fut"></i>
                                @elseif($n)
                                    <i style="height: {{ max(3, $n / $peak * 50) }}px" title="{{ $n }} at {{ \Illuminate\Support\Carbon::today()->setHour($firstHour + $i)->format('g A') }}"></i>
                                @else
                                    <i class="zero"></i>
                                @endif
                            @endforeach
                            @if($d['silent_from'] !== null && $d['silent_from'] < $nowAt)
                                <div class="hr-silent" style="left: {{ $pct($d['silent_from']) }}; width: {{ $pct($nowAt - $d['silent_from']) }}"><span>silent</span></div>
                            @endif
                            @if($nowAt > 0 && $nowAt < $hours)<div class="hr-now" style="left: {{ $pct($nowAt) }}"></div>@endif
                        </div>
                        <div class="hr-x">@foreach($axis as $label)<span>{{ $label }}</span>@endforeach</div>
                    </section>

                    <footer class="dv-foot">
                        <div class="dv-cell">
                            @if($d['gps'] === 'none')
                                <div class="gps none"><i data-lucide="satellite-dish"></i></div>
                            @else
                                @php $gx = $spot($d['lng']); $gy = $spot($d['lat']); @endphp
                                <div class="gps {{ $d['gps'] }}"><span class="h" style="top: {{ $gy }}%"></span><span class="vv" style="left: {{ $gx }}%"></span><span class="x" style="left: {{ $gx }}%; top: {{ $gy }}%"></span></div>
                            @endif
                            <div style="min-width:0">
                                <span class="sx-label">GPS</span>
                                @if($d['gps'] === 'fix')
                                    <div class="v">Fix · {{ $d['last_seen']?->format('H:i') }}</div>
                                @elseif($d['gps'] === 'stale')
                                    <div class="v">{{ $d['state'] === 'off' ? 'Last known' : 'No signal now' }}@if($d['last_seen']) · {{ $d['last_seen']->format('H:i') }}@endif</div>
                                @else
                                    <div class="v">No GPS signal</div>
                                @endif
                                <div class="s">{{ $d['lat'] !== null ? number_format($d['lat'], 5) . ', ' . number_format($d['lng'], 5) : 'no coordinates yet' }}</div>
                                @if($d['lat'] !== null)
                                    <a class="sx-link" href="https://www.google.com/maps?q={{ $d['lat'] }},{{ $d['lng'] }}" target="_blank" rel="noopener">Open in Maps <i data-lucide="arrow-up-right"></i></a>
                                @endif
                            </div>
                        </div>
                        <div class="dv-cell">
                            @php $emp = $d['last']?->employee?->name; @endphp
                            <span class="av">{{ $emp ? collect(preg_split('/\s+/', $emp))->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('') : '—' }}</span>
                            <div style="min-width:0">
                                <span class="sx-label">Last attendance</span>
                                @if($d['last'])
                                    <div class="v">{{ $emp ?? 'Unknown worker' }}</div>
                                    <div class="s">{{ $d['last_out'] ? 'Time out' : 'Time in' }} · {{ $d['last_at']->isToday() ? $d['last_at']->format('g:i A') : $d['last_at']->format('M j, g:i A') }}</div>
                                @else
                                    <div class="v">None recorded</div>
                                @endif
                            </div>
                        </div>
                    </footer>
                </article>
            @endforeach
        </div>

        <div class="dv-table-view sx-card">
            <div class="sx-table-wrap">
                <table class="sx-table">
                    <thead><tr><th>Kiosk</th><th>Site</th><th>Status</th><th>Last heartbeat</th><th>GPS</th><th>Last attendance</th><th style="text-align:right">Scans today</th></tr></thead>
                    <tbody>
                    @forelse($devices as $d)
                        <tr>
                            <td><b>{{ $d['kiosk']->name }}</b> <span class="mono dim">{{ $d['kiosk']->code }}</span></td>
                            <td class="muted">{{ $d['kiosk']->site->name ?? 'Unassigned' }}</td>
                            <td><span class="sx-badge {{ $tone[$d['state']] }}">{{ $badge[$d['state']] }}</span></td>
                            <td class="mono">{{ $d['seconds'] === null ? 'never' : $dur($d['seconds']) . ' ago' }}</td>
                            <td class="muted">{{ ['fix' => 'Fix', 'stale' => 'Last known', 'none' => 'No signal'][$d['gps']] }}</td>
                            <td class="muted">{{ $d['last']?->employee?->name ?? '—' }}</td>
                            <td class="mono" style="text-align:right">{{ $d['scans'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><div class="sx-empty">No kiosks registered.</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const KEY = 'jeyanco-devices-view';
    const live = () => document.getElementById('dv-live');

    function applyView(view) {
        const el = live();
        if (!el) return;
        el.classList.toggle('as-table', view === 'table');
        el.querySelectorAll('[data-view]').forEach(b => b.classList.toggle('on', b.dataset.view === view));
    }
    let view = 'cards';
    try { view = localStorage.getItem(KEY) || 'cards'; } catch (e) {}
    applyView(view);

    document.addEventListener('click', e => {
        const b = e.target.closest('[data-view]');
        if (b) {
            view = b.dataset.view;
            try { localStorage.setItem(KEY, view); } catch (err) {}
            applyView(view);
            return;
        }
        const r = e.target.closest('[data-refresh]');
        if (r) { e.preventDefault(); refresh(); }
    });

    // Every thirty seconds while the page is in view, swap in a fresh copy
    // of the live part — the scroll position and the chosen view stay put.
    async function refresh() {
        try {
            const res = await fetch(window.location.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
            if (!res.ok || res.redirected) return;
            const doc = new DOMParser().parseFromString(await res.text(), 'text/html');
            const fresh = doc.getElementById('dv-live');
            if (!fresh) return;
            live().innerHTML = fresh.innerHTML;
            applyView(view);
            if (window.lucide) lucide.createIcons();
        } catch (e) { /* offline for a moment: the next tick tries again */ }
    }
    // A kiosk's heartbeat, its site switch and its GPS fix all say so as they
    // land, so this asks when there is something to ask about.
    Live.on('devices kiosk sites employees attendance', () => {
        if (document.visibilityState === 'visible') refresh();
    });

    // Except for going quiet, which nothing announces: a kiosk that stops
    // reporting only becomes offline with the passing of time.
    setInterval(() => { if (document.visibilityState === 'visible') refresh(); }, 60000);
})();
</script>
@endpush
