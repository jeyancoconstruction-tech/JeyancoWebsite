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
    $peak  = max(1, $devices->flatMap(fn ($d) => $d['hours'])->max() ?? 1);
    $silent = $devices->where('state', 'off');
    $first  = $silent->first();
    $late   = $devices->where('state', 'late')->first();
@endphp

@push('styles')
@include('system._kit')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<style>

/* ── Kiosk console ─────────────────────────────────────────────────────────
   One kiosk at a time, as the site sees it: its own screen on the left, and
   on the right where it is (the map), whether it is talking to us, and what
   it recorded today. It stands outside the part of the page that refreshes,
   so the screen and the map are never reloaded under the reader. */
.dvc { display: flex; flex-direction: column; margin-bottom: 14px; }
.dvc-head { display: flex; align-items: center; gap: 10px; padding: 10px 14px; border-bottom: 1px solid var(--border); flex-wrap: wrap; }
.dvc-picks { display: flex; gap: 6px; flex-wrap: wrap; min-width: 0; }
.dvc-pick { display: inline-flex; align-items: center; gap: 8px; height: 30px; padding: 0 11px; border-radius: 8px; border: 1px solid var(--border-md); background: var(--surface); color: var(--text-primary); font-size: 12.5px; font-weight: 600; cursor: pointer; }
.dvc-pick i { width: 8px; height: 8px; border-radius: 50%; background: var(--danger); flex: none; }
.dvc-pick i.ok { background: var(--success); box-shadow: 0 0 0 3px color-mix(in srgb, var(--success) 20%, transparent); }
.dvc-pick i.late { background: var(--warning); }
.dvc-pick small { font-size: 11px; font-weight: 600; color: var(--text-muted); }
.dvc-pick.on { border-color: var(--brand); box-shadow: 0 0 0 1px var(--brand) inset; background: var(--brand-subtle); }
.dvc-head .sp { flex: 1; }
.dvc-live { font-family: 'JetBrains Mono', ui-monospace, monospace; font-size: 11.5px; color: var(--text-muted); white-space: nowrap; }
.dvc-live::before { content: ""; display: inline-block; width: 7px; height: 7px; border-radius: 50%; background: var(--success); margin-right: 6px; vertical-align: 1px; animation: dvc-blink 1.6s ease-in-out infinite; }
.dvc-live.bad::before { background: var(--danger); animation: none; }
@keyframes dvc-blink { 50% { opacity: .35; } }

.dvc-body { flex: 1; display: grid; grid-template-columns: minmax(0, 1fr) 380px; min-height: 0; }
.dvc-screen { padding: 14px; display: flex; flex-direction: column; justify-content: flex-start; background: var(--bg-subtle); border-right: 1px solid var(--border); min-width: 0; }
.km-frame { position: relative; width: 100%; aspect-ratio: 1024 / 600; border-radius: 14px; background: #05080d; padding: 10px; box-shadow: 0 0 0 1px #1b2433 inset, 0 10px 30px rgba(8, 15, 28, .18); }
.km-frame::after { content: ""; position: absolute; left: 50%; bottom: 3px; width: 44px; height: 3px; margin-left: -22px; border-radius: 2px; background: #1c2635; }
.km-glass { position: relative; width: 100%; height: 100%; overflow: hidden; border-radius: 4px; background: #0a0e14; }
.km-screen, .km-off { position: absolute; left: 0; top: 0; width: 1024px; height: 600px; transform-origin: 0 0; border: 0; background: #0a0e14; }
.km-frame:fullscreen { border-radius: 0; padding: 0; display: grid; place-items: center; background: #000; }
.km-frame:fullscreen .km-glass { width: min(100vw, 170.67vh); height: auto; aspect-ratio: 1024 / 600; border-radius: 0; }
.kx-off { width: 1024px; height: 600px; background: #030507; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 18px; color: #5d6a7d; font-family: Inter, system-ui, sans-serif; text-align: center; }
.kx-off svg { width: 96px; height: 96px; color: #3a4658; }
.kx-off b { font-size: 38px; letter-spacing: .2em; color: #c9d3e2; }
.kx-off p { margin: 0; font-size: 19px; max-width: 760px; line-height: 1.5; }
.kx-off p strong { color: #c9d3e2; }
.dvc-cap { display: flex; justify-content: space-between; gap: 10px; margin-top: 10px; font-size: 11.5px; color: var(--text-muted); }
.dvc-cap b { color: var(--text-secondary); font-weight: 600; }

.dvc-side { display: flex; flex-direction: column; min-width: 0; height: 0; min-height: 100%; overflow: hidden; }
.dvc-map { position: relative; flex: 0 1 190px; min-height: 140px; border-bottom: 1px solid var(--border); }
.dvc-map #dvMap { position: absolute; inset: 0; z-index: 0; }
.dvc-map .leaflet-control-attribution { font-size: 9px; }
.dvc-mapnote { padding: 8px 14px; border-bottom: 1px solid var(--border); font-size: 12.5px; color: var(--text-secondary); display: flex; flex-direction: column; gap: 3px; }
.dvc-mapnote b { color: var(--text-primary); }
.dvc-mapnote .ok { color: var(--success); } .dvc-mapnote .bad { color: var(--danger); } .dvc-mapnote .warn { color: var(--warning); }
.dvc-mapnote small { font-family: 'JetBrains Mono', ui-monospace, monospace; font-size: 11px; color: var(--text-muted); }
.dvc-mapnote a { font-size: 11.5px; font-weight: 600; }
/* The four facts sit under the kiosk screen, where there is width to spare,
   so the side column keeps its height for the scans. */
.dvc-facts { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); margin-top: 10px; background: var(--surface); border: 1px solid var(--border); border-radius: 10px; flex: none; }
.dvc-facts > div { padding: 8px 14px; border-right: 1px solid var(--border); min-width: 0; }
.dvc-facts > div:last-child { border-right: 0; }
.dvc-facts span { display: block; font-size: 10px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--text-muted); }
.dvc-facts b { display: block; font-size: 13.5px; font-weight: 700; color: var(--text-primary); margin-top: 1px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.dvc-facts b.ok { color: var(--success); } .dvc-facts b.off { color: var(--danger); } .dvc-facts b.late { color: var(--warning); }
.dvc-log { flex: 1 1 260px; display: flex; flex-direction: column; min-height: 200px; }
.dvc-log > header { display: flex; justify-content: space-between; padding: 8px 14px; font-size: 10.5px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--text-muted); border-bottom: 1px solid var(--border); }
.dvc-log ol { list-style: none; margin: 0; padding: 0; flex: 1 1 0; min-height: 0; overflow-y: auto; }
.dvc-log li { display: grid; grid-template-columns: 62px minmax(0, 1fr) auto; gap: 10px; align-items: center; padding: 6px 14px; border-bottom: 1px solid var(--border); font-size: 12.5px; }
.dvc-log li:nth-child(even) { background: color-mix(in srgb, var(--bg-subtle) 60%, transparent); }
.dvc-log time { font-family: 'JetBrains Mono', monospace; font-size: 11.5px; color: var(--text-muted); }
.dvc-log li b { font-weight: 600; color: var(--text-primary); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.dvc-log li span { font-size: 10.5px; font-weight: 800; letter-spacing: .04em; padding: 2px 7px; border-radius: 5px; white-space: nowrap; }
.dvc-log li span.in { color: var(--success); background: var(--success-soft); }
.dvc-log li span.out { color: var(--danger); background: var(--danger-soft); }
.dvc-log li span.rej { color: var(--warning); background: var(--warning-soft); }
.dvc-log li.none { display: block; padding: 22px 14px; text-align: center; color: var(--text-muted); }
.dvc-log > header span:last-child { text-transform: none; letter-spacing: 0; font-family: 'JetBrains Mono', monospace; font-weight: 600; }
.dvc-chips { display: flex; gap: 6px; padding: 8px 14px; border-bottom: 1px solid var(--border); flex: none; flex-wrap: wrap; }
.dvc-chips[hidden] { display: none; }
.dvc-chip { display: inline-flex; align-items: center; gap: 6px; height: 24px; padding: 0 9px; border-radius: 999px; border: 1px solid var(--border-md); background: var(--surface); color: var(--text-secondary); font-size: 11.5px; font-weight: 600; cursor: pointer; }
.dvc-chip b { font-family: 'JetBrains Mono', monospace; font-size: 11px; color: var(--text-primary); }
.dvc-chip i { width: 7px; height: 7px; border-radius: 50%; }
.dvc-chip i.in { background: var(--success); } .dvc-chip i.out { background: var(--danger); } .dvc-chip i.rej { background: var(--warning); }
.dvc-chip:hover { border-color: var(--text-muted); }
.dvc-chip.on { background: var(--text-primary); border-color: var(--text-primary); color: var(--surface); }
.dvc-chip.on b { color: inherit; }

/* The pins on the map. */
.dvc-pin { width: 18px; height: 18px; border-radius: 50%; border: 3px solid #fff; box-shadow: 0 1px 4px rgba(0,0,0,.35); background: #16a34a; }
.dvc-pin.stale { background: #8a97ab; }
.dvc-pin.off { background: #dc2626; }
.dvc-pin.live::after { content: ""; position: absolute; inset: -9px; border-radius: 50%; border: 2px solid #16a34a; animation: dvc-ring 1.8s ease-out infinite; }
/* No GPS signal: the ring is red, as on the dashboard's and the Sites map. */
.dvc-pin.stale::after, .dvc-pin.off::after { content: ""; position: absolute; inset: -9px; border-radius: 50%; border: 2px solid #dc2626; animation: dvc-ring 1.8s ease-out infinite; }
@keyframes dvc-ring { from { transform: scale(.4); opacity: .9; } to { transform: scale(1.4); opacity: 0; } }
.dvc-site { width: 26px; height: 26px; border-radius: 7px; background: var(--brand, #1668dc); color: #fff; display: grid; place-items: center; box-shadow: 0 1px 4px rgba(0,0,0,.35); border: 2px solid #fff; font: 800 11px Inter, sans-serif; }
@media (prefers-reduced-motion: reduce) { .dvc-live::before, .dvc-pin::after { animation: none; } }

@media (max-width: 1200px) {
    .dvc-body { grid-template-columns: minmax(0, 1fr); }
    .dvc-screen { border-right: 0; border-bottom: 1px solid var(--border); }
    .dvc-side { height: auto; min-height: 0; }
    .dvc-map { flex: none; height: 260px; }
    .dvc-log ol { max-height: 320px; }
    .dvc-log { flex: none; min-height: 0; }
}
.dvc-side .dvc-mapnote { flex: none; }
@media (max-width: 700px) { .dvc-facts { grid-template-columns: 1fr 1fr; } .dvc-facts > div:nth-child(2) { border-right: 0; } .dvc-facts > div:nth-child(-n+2) { border-bottom: 1px solid var(--border); } }
html[data-bs-theme="dark"] #dvMap .leaflet-tile-pane { filter: invert(1) hue-rotate(180deg) brightness(.9) contrast(.88) saturate(.55); }
html[data-bs-theme="dark"] #dvMap .leaflet-control-attribution { background: color-mix(in srgb, var(--surface) 85%, transparent); color: var(--text-muted); }
.dv-watch { margin-left: 6px; }
.dv-fleet { display: grid; grid-template-columns: 1.15fr 1fr 1fr 1.55fr; margin-bottom: 14px; }
@media (max-width: 1100px) { .dv-fleet { grid-template-columns: 1fr 1fr; } }
.dv-fleet > div { padding: 10px 16px 11px; border-right: 1px solid var(--border); }
.dv-fleet > div:last-child { border-right: none; }
.dv-k { font-size: 12px; color: var(--text-secondary); font-weight: 500; display: flex; align-items: center; gap: 6px; }
.dv-k svg { width: 14px; height: 14px; color: var(--text-muted); }
.dv-v { font-size: 21px; font-weight: 700; letter-spacing: -.02em; margin-top: 5px; line-height: 1; color: var(--text-primary); }
.dv-v small { font-size: 14px; color: var(--text-muted); font-weight: 600; margin-left: 2px; }
.dv-s { font-size: 11.5px; color: var(--text-muted); margin-top: 5px; }
.dv-seg { display: flex; gap: 3px; margin-top: 10px; }
.dv-seg i { flex: 1; height: 8px; border-radius: 2px; background: var(--success); }
.dv-seg i.late { background: var(--warning); } .dv-seg i.off { background: var(--danger); }
.dv-rule { display: grid; grid-template-columns: auto 1fr; gap: 4px 10px; margin-top: 5px; font-size: 12px; color: var(--text-secondary); align-items: center; }
.dv-rule .sx-badge { justify-self: start; }

.dv-listhead { display: flex; align-items: center; gap: 10px; margin: 0 0 10px; flex-wrap: wrap; }
.dv-listhead .sx-card-note { margin-right: auto; }
.dv-hint { font-size: 12px; color: var(--text-muted); display: inline-flex; align-items: center; gap: 6px; }
.dv-hint svg { width: 13px; height: 13px; }

/* ── B · All kiosks: one line each ───────────────────────────────────────
   The console above is where a kiosk's scans are read, one kiosk at a time.
   Down here every kiosk is one line — is it alive, how busy it has been,
   who scanned last, where it is — and Watch puts it in the console. Nothing
   here repeats the console's list. */
.dv-ledger { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; box-shadow: var(--shadow-xs); overflow: hidden; }
.dv-lhead, .dv-line { display: grid; grid-template-columns: minmax(220px, 1.3fr) minmax(170px, 1fr) minmax(230px, 1.35fr) minmax(170px, 1fr) minmax(150px, .9fr) 96px; align-items: center; }
.dv-lhead { background: var(--bg-subtle); border-bottom: 1px solid var(--border); }
.dv-lhead > span { padding: 7px 14px; font-size: 10.5px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--text-muted); white-space: nowrap; }
.dv-line { position: relative; border-bottom: 1px solid var(--border); transition: background .15s; }
.dv-line:last-child { border-bottom: 0; }
.dv-line > div { padding: 11px 14px; min-width: 0; }
.dv-line + .dv-line { }
.dv-line::before { content: ""; position: absolute; left: 0; top: 0; bottom: 0; width: 3px; background: transparent; }
.dv-line.is-off::before { background: var(--danger); }
.dv-line.is-late::before { background: var(--warning); }
.dv-line.is-watched { background: color-mix(in srgb, var(--brand) 5%, transparent); }
.dv-line.is-watched .dv-go .sx-btn { border-color: var(--brand); color: var(--brand); }

.dv-who { display: flex; align-items: center; gap: 11px; }
.dv-ico { width: 34px; height: 34px; border-radius: 9px; display: grid; place-items: center; background: var(--success-soft); color: var(--success); position: relative; flex: none; }
.dv-ico svg { width: 16px; height: 16px; }
.dv-ico::after { content: ""; position: absolute; right: -3px; bottom: -3px; width: 12px; height: 12px; border-radius: 50%; background: var(--success); border: 2.5px solid var(--surface); }
.is-late .dv-ico { background: var(--warning-soft); color: var(--warning); } .is-late .dv-ico::after { background: var(--warning); }
.is-off .dv-ico { background: var(--danger-soft); color: var(--danger); } .is-off .dv-ico::after { background: var(--danger); }
.dv-name { font-size: 13.5px; font-weight: 700; color: var(--text-primary); display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.dv-meta { font-size: 12px; color: var(--text-muted); display: flex; align-items: center; gap: 5px; margin-top: 4px; flex-wrap: wrap; }
.dv-meta { flex-wrap: nowrap; white-space: nowrap; overflow: hidden; }
.dv-meta span:last-child { overflow: hidden; text-overflow: ellipsis; }
.dv-state { margin-top: 6px; }
.dv-meta svg { width: 12px; height: 12px; }

.dv-kv { display: flex; align-items: baseline; justify-content: space-between; gap: 8px; margin-bottom: 6px; white-space: nowrap; }
.dv-kv b { font-family: 'JetBrains Mono', monospace; font-size: 13px; font-weight: 700; color: var(--text-primary); }
.dv-kv b.ok { color: var(--success); } .dv-kv b.warn { color: var(--warning); } .dv-kv b.danger { color: var(--danger); }
.dv-at { font-family: 'JetBrains Mono', monospace; font-size: 11px; color: var(--text-muted); }

.ruler-track { position: relative; height: 6px; border-radius: 3px; background: var(--bg-subtle); border: 1px solid var(--border); overflow: visible; }
.ruler-late { position: absolute; top: 0; bottom: 0; right: 0; background: repeating-linear-gradient(135deg, color-mix(in srgb, var(--warning) 22%, transparent) 0 3px, transparent 3px 6px); border-left: 1px dashed color-mix(in srgb, var(--warning) 60%, transparent); }
.ruler-fill { position: absolute; left: 0; top: 0; bottom: 0; border-radius: 2px 0 0 2px; }
.ruler-fill.ok { background: var(--success); } .ruler-fill.warn { background: var(--warning); } .ruler-fill.danger { background: var(--danger); border-radius: 2px; }
.ruler-mark { position: absolute; top: -4px; width: 2px; height: 12px; margin-left: -1px; background: var(--text-primary); border-radius: 1px; }
.ruler-over { display: flex; align-items: center; gap: 5px; margin-top: 6px; font-size: 11.5px; font-weight: 600; color: var(--danger); line-height: 1.3; }
.ruler-over svg { width: 13px; height: 13px; flex: none; }

/* The workday as fourteen hour-bars: how busy the kiosk has been, when it
   went quiet, and where "now" is. The count is the same figure as the
   fleet strip's, kiosk by kiosk. */
.dv-act { display: flex; align-items: flex-end; gap: 12px; }
.dv-act .spark { flex: 1; min-width: 0; }
.hr { position: relative; height: 30px; display: grid; grid-template-columns: repeat({{ $hours }}, 1fr); gap: 2px; align-items: end; }
.hr-band { position: absolute; top: 0; bottom: 0; background: color-mix(in srgb, var(--brand) 3.5%, transparent); border-radius: 2px; }
.hr i { display: block; background: var(--brand); border-radius: 2px 2px 0 0; position: relative; z-index: 1; }
.hr i.zero { background: var(--border-md); height: 2px; }
.hr i.fut { height: 4px; background: repeating-linear-gradient(90deg, var(--border-md) 0 2px, transparent 2px 4px); border-radius: 0; }
.hr-now { position: absolute; top: -3px; bottom: 0; border-left: 1.5px solid var(--text-primary); z-index: 2; }
.hr-silent { position: absolute; top: 0; bottom: 0; background: repeating-linear-gradient(135deg, color-mix(in srgb, var(--danger) 18%, transparent) 0 3px, transparent 3px 6px); border-left: 1.5px solid var(--danger); z-index: 1; }
.hr-x { display: flex; justify-content: space-between; font-family: 'JetBrains Mono', monospace; font-size: 9.5px; color: var(--text-muted); margin-top: 3px; }
.dv-count { flex: none; text-align: right; line-height: 1; }
.dv-count b { display: block; font-size: 20px; font-weight: 700; letter-spacing: -.02em; color: var(--text-primary); }
.dv-count small { font-size: 10.5px; color: var(--text-muted); font-weight: 600; }

.dv-last, .dv-gps { display: flex; gap: 10px; align-items: center; }
.dv-last .av { flex: none; }
.dv-last .v, .dv-gps .v { font-size: 12.5px; font-weight: 600; color: var(--text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.dv-last .s, .dv-gps .s { font-family: 'JetBrains Mono', monospace; font-size: 11px; color: var(--text-muted); margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.dv-last .s i { font-style: normal; font-weight: 700; }
.dv-last .s i.in { color: var(--success); } .dv-last .s i.out { color: var(--danger); }
.dv-gps .dot { width: 9px; height: 9px; border-radius: 50%; flex: none; background: var(--warning); }
.dv-gps .dot.fix { background: var(--success); box-shadow: 0 0 0 3px color-mix(in srgb, var(--success) 18%, transparent); }
.dv-gps .dot.stale { background: var(--text-muted); }
.dv-gps .sx-link { font-size: 11.5px; }
.dv-go { text-align: right; }
.dv-empty { padding: 26px; text-align: center; color: var(--text-muted); font-size: 13px; }

@media (max-width: 1180px) {
    .dv-lhead { display: none; }
    .dv-line { grid-template-columns: 1fr 1fr; }
    .dv-line > .dv-who { grid-column: 1 / -1; padding-bottom: 4px; }
    .dv-line > .dv-go { grid-column: 1 / -1; text-align: left; padding-top: 0; }
}
@media (max-width: 640px) { .dv-line { grid-template-columns: 1fr; } }
</style>
@endpush

@section('content')
<div class="sx-page">

    <x-page-header title="Device Monitoring" />

    {{-- ── Summary: the status line and the fleet strip, above the console.
         Refreshed with #dv-live. --}}
    <div id="dv-top">
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
    </div>

    {{-- ── A · Kiosk console ─────────────────────────────────────────────
         Outside #dv-live, so the refresh below never reloads the screen or
         the map. It keeps itself current from devices.live. --}}
    @if($devices->isNotEmpty())
    <section class="sx-card dvc" id="dvConsole" data-url="{{ route('devices.live') }}" data-screen="{{ url('device-monitoring') }}" aria-label="Kiosk console">
        <header class="dvc-head">
            <span class="sx-idx">A</span><h2 class="sx-card-title">Kiosk console</h2>
            <div class="dvc-picks">
                @foreach($devices as $d)
                    <button type="button" class="dvc-pick" data-kiosk="{{ $d['kiosk']->id }}"><i class="{{ $d['state'] }}"></i>{{ $d['kiosk']->name }} <small>{{ $d['kiosk']->site->name ?? 'Unassigned' }}</small></button>
                @endforeach
            </div>
            <span class="sp"></span>
            <span class="dvc-live" data-checked>Connecting…</span>
            <button type="button" class="sx-btn sm" data-full><i data-lucide="maximize"></i> Full screen</button>
            <a class="sx-btn sm" href="#" target="_blank" rel="noopener" data-open><i data-lucide="external-link"></i> Open screen</a>
        </header>
        <div class="dvc-body">
            <div class="dvc-screen">
                <div class="km-frame"><div class="km-glass">
                    <iframe class="km-screen" data-frame title="Kiosk screen" allow="fullscreen"></iframe>
                    <div class="km-off" data-off hidden></div>
                </div></div>
                <div class="dvc-cap" data-cap></div>
                <div class="dvc-facts" data-facts></div>
            </div>
            <aside class="dvc-side">
                <div class="dvc-map"><div id="dvMap" aria-label="Where the kiosk is"></div></div>
                <div class="dvc-mapnote" data-mapnote>Reading the kiosk's position…</div>
                <div class="dvc-log"><header><span>Today at this kiosk</span><span data-count></span></header>
                    <div class="dvc-chips" data-chips></div><ol data-log></ol></div>
            </aside>
        </div>
    </section>
    @endif

    <div id="dv-live" class="dv-live">
        {{-- ── B · All kiosks ─────────────────────────────────────────── --}}
        @php
            $axisAt = fn ($h) => rtrim(\Illuminate\Support\Carbon::today()->setHour($h)->format('ga'), 'm');
        @endphp
        <div class="dv-listhead">
            <span class="sx-idx">B</span><h2 class="sx-card-title">All kiosks</h2><span class="sx-card-note">{{ $summary['total'] }} registered · the ones that need attention come first</span>
            <span class="dv-hint"><i data-lucide="monitor-play"></i>Watch opens a kiosk in the console above</span>
        </div>

        <div class="dv-ledger">
            <div class="dv-lhead"><span>Kiosk</span><span>Heartbeat</span><span>Scans this workday</span><span>Last scan</span><span>GPS</span><span></span></div>
            @forelse($devices as $d)
                @php
                    $k = $d['kiosk']; $t = $tone[$d['state']];
                    $emp = $d['last']?->employee?->name;
                @endphp
                <article class="dv-line is-{{ $d['state'] }}" data-line="{{ $k->id }}">
                    <div class="dv-who">
                        <div class="dv-ico"><i data-lucide="monitor-smartphone"></i></div>
                        <div style="min-width:0">
                            <div class="dv-name">{{ $k->name }}</div>
                            <div class="dv-meta"><span class="mono">{{ $k->code }}</span>·<i data-lucide="map-pin"></i><span>{{ $k->site->name ?? 'Unassigned' }}</span></div>
                            <span class="sx-badge {{ $t }} dv-state">{{ $badge[$d['state']] }}</span>
                        </div>
                    </div>

                    <div class="dv-beat">
                        <div class="dv-kv"><b class="{{ $t }}" @if($d['last_seen']) title="Last heartbeat {{ $d['last_seen']->format('M j, g:i:s A') }}" @endif>{{ $d['seconds'] === null ? 'never' : $dur($d['seconds']) . ' ago' }}</b></div>
                        <div class="ruler-track" title="Online up to {{ $lateAfter / 60 }} min · late to {{ $offlineAfter / 60 }} min · offline after">
                            <div class="ruler-late" style="left: {{ $lateAfter / $offlineAfter * 100 }}%"></div>
                            <div class="ruler-fill {{ $t }}" style="width: {{ $d['pct'] }}%"></div>
                            @if($d['pct'] < 100)<div class="ruler-mark" style="left: {{ $d['pct'] }}%"></div>@endif
                        </div>
                        @if($d['over'])
                            <div class="ruler-over"><i data-lucide="triangle-alert"></i>{{ $dur($d['over']) }} past the {{ $offlineAfter / 60 }}-minute limit</div>
                        @elseif($d['seconds'] === null)
                            <div class="ruler-over"><i data-lucide="triangle-alert"></i>No heartbeat received from this kiosk yet</div>
                        @endif
                    </div>

                    <div class="dv-act">
                        <div class="spark">
                            <div class="hr">
                                @foreach($bands as $band)
                                    <div class="hr-band" style="left: {{ $pct($band['from']) }}; width: {{ $pct($band['to'] - $band['from']) }}" title="{{ $band['label'] }}"></div>
                                @endforeach
                                @foreach($d['hours'] as $i => $n)
                                    @if($i > $nowAt)
                                        <i class="fut"></i>
                                    @elseif($n)
                                        <i style="height: {{ max(3, $n / $peak * 30) }}px" title="{{ $n }} at {{ \Illuminate\Support\Carbon::today()->setHour($firstHour + $i)->format('g A') }}"></i>
                                    @else
                                        <i class="zero"></i>
                                    @endif
                                @endforeach
                                @if($d['silent_from'] !== null && $d['silent_from'] < $nowAt)
                                    <div class="hr-silent" style="left: {{ $pct($d['silent_from']) }}; width: {{ $pct($nowAt - $d['silent_from']) }}" title="Silent since then"></div>
                                @endif
                                @if($nowAt > 0 && $nowAt < $hours)<div class="hr-now" style="left: {{ $pct($nowAt) }}" title="Now"></div>@endif
                            </div>
                            <div class="hr-x"><span>{{ $axisAt($firstHour) }}</span><span>{{ $axisAt(12) }}</span><span>{{ $axisAt($firstHour + $hours) }}</span></div>
                        </div>
                        <div class="dv-count"><b>{{ $d['scans'] }}</b><small>{{ $d['scans'] === 1 ? 'scan' : 'scans' }}</small></div>
                    </div>

                    <div class="dv-last">
                        <span class="av">@if($emp)@include('partials.worker-icon')@else — @endif</span>
                        <div style="min-width:0">
                            @if($d['last'])
                                <div class="v">{{ $emp ?? 'Unknown worker' }}</div>
                                <div class="s"><i class="{{ $d['last_out'] ? 'out' : 'in' }}">{{ $d['last_out'] ? 'OUT' : 'IN' }}</i> · {{ $d['last_at']->isToday() ? $d['last_at']->format('g:i A') : $d['last_at']->format('M j, g:i A') }}</div>
                            @else
                                <div class="v">None recorded</div>
                                <div class="s">—</div>
                            @endif
                        </div>
                    </div>

                    <div class="dv-gps">
                        <span class="dot {{ $d['gps'] }}"></span>
                        <div style="min-width:0">
                            <div class="v">{{ ['fix' => 'Fix', 'stale' => $d['state'] === 'off' ? 'Last known' : 'No signal now', 'none' => 'No GPS signal'][$d['gps']] }}@if($d['gps'] !== 'none' && $d['last_seen']) · {{ $d['last_seen']->format('H:i') }}@endif</div>
                            @if($d['lat'] !== null)
                                <a class="sx-link" href="https://www.google.com/maps?q={{ $d['lat'] }},{{ $d['lng'] }}" target="_blank" rel="noopener">{{ number_format($d['lat'], 4) }}, {{ number_format($d['lng'], 4) }} ↗</a>
                            @else
                                <div class="s">no coordinates yet</div>
                            @endif
                        </div>
                    </div>

                    <div class="dv-go"><a class="sx-btn sm" href="#dvConsole" data-console-kiosk="{{ $k->id }}"><i data-lucide="monitor-play"></i> Watch</a></div>
                </article>
            @empty
                <div class="dv-empty">No kiosks registered.</div>
            @endforelse
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
// ── Kiosk console ────────────────────────────────────────────────────────────
// The chosen kiosk's own screen (its v8 files, at its 1024 × 600), where it is
// on the map against its site's pin and geofence, and what it recorded today.
// Asks every two seconds whether anybody scanned (each scan is handed to the
// screen as it happens), and every ten for the rest.
(function () {
    const box = document.getElementById('dvConsole');
    if (!box) return;

    const frame   = box.querySelector('[data-frame]');
    const glass   = frame.parentElement;
    const offBox  = box.querySelector('[data-off]');
    const facts   = box.querySelector('[data-facts]');
    const checked = box.querySelector('[data-checked]');
    const note    = box.querySelector('[data-mapnote]');
    const logBox  = box.querySelector('[data-log]'), logCount = box.querySelector('[data-count]');
    const opener  = box.querySelector('[data-open]');
    const cap     = box.querySelector('[data-cap]');
    const W = 1024, H = 600;
    const WARN = { already_in: 'ALREADY TIMED IN', no_open: 'NO OPEN TIME IN', just_timed_in: 'JUST TIMED IN',
                   just_timed_out: 'JUST TIMED OUT', session_done: 'SESSION DONE', wrong_shift: 'REJECTED',
                   not_registered: 'NOT REGISTERED YET', mode_buttons: 'PRESS A BUTTON FIRST',
                   no_gps: 'LOCATION NOT CONFIRMED', outside_location: 'LOCATION NOT CONFIRMED',
                   wrong_site: 'NOT ASSIGNED TO THIS SITE', no_site: 'NO SITE SET', no_site_location: 'LOCATION NOT CONFIRMED' };
    const esc = v => String(v ?? '').replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));

    let current = Number(box.dataset.first) || null, data = null, seq = null, recent = [], loaded = null, timer = null;
    const seen = new Set();

    function fit() {
        const s = glass.clientWidth / W;
        frame.style.transform = offBox.style.transform = 'scale(' + s + ')';
    }
    new ResizeObserver(fit).observe(glass);
    document.addEventListener('fullscreenchange', () => setTimeout(fit, 50));

    // ── The map ─────────────────────────────────────────────────────────────
    let map = null, kioskPin = null, sitePin = null, fence = null, line = null, framedFor = null;
    const NAGA = [13.6218, 123.1948];
    function ensureMap() {
        if (map || typeof L === 'undefined') return;
        map = L.map('dvMap', { zoomControl: true, attributionControl: true }).setView(NAGA, 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap contributors', maxZoom: 19 }).addTo(map);
    }
    function drawMap(k) {
        ensureMap();
        if (!map) return;
        const m = k.map || {};
        [kioskPin, sitePin, fence, line].forEach(l => l && map.removeLayer(l));
        kioskPin = sitePin = fence = line = null;
        const pts = [];

        if (m.site) {
            const at = [m.site.lat, m.site.lng];
            fence = L.circle(at, { radius: m.site.radius, color: '#1668dc', weight: 1.5, fillColor: '#1668dc', fillOpacity: .08, dashArray: '4 4' }).addTo(map);
            sitePin = L.marker(at, { icon: L.divIcon({ className: '', html: '<div class="dvc-site">S</div>', iconSize: [26, 26], iconAnchor: [13, 13] }),
                                     title: m.site.name }).addTo(map).bindTooltip(esc(m.site.name) + ' · pin', { direction: 'top', offset: [0, -12] });
            pts.push(at);
        }
        if (m.lat !== null && m.lng !== null) {
            const at = [m.lat, m.lng];
            const cls = k.state === 'off' ? 'off' : m.gps === 'fix' ? 'live' : 'stale';
            kioskPin = L.marker(at, { icon: L.divIcon({ className: '', html: '<div class="dvc-pin ' + cls + '"></div>', iconSize: [18, 18], iconAnchor: [9, 9] }),
                                      zIndexOffset: 500 }).addTo(map).bindTooltip(esc(k.name), { direction: 'top', offset: [0, -10] });
            pts.push(at);
            if (m.site) line = L.polyline([[m.site.lat, m.site.lng], at], { color: m.inside ? '#16a34a' : '#dc2626', weight: 2, dashArray: '5 6' }).addTo(map);
        }

        // Framed once per kiosk, so a poll never yanks the map from the reader.
        if (framedFor !== k.id) {
            framedFor = k.id;
            if (fence && kioskPin) map.fitBounds(L.latLngBounds(pts).extend(fence.getBounds()), { padding: [24, 24], maxZoom: 18 });
            else if (fence) map.fitBounds(fence.getBounds(), { padding: [24, 24] });
            else if (pts.length) map.setView(pts[0], 16);
            else map.setView(NAGA, 13);
        }
        setTimeout(() => map.invalidateSize(), 0);

        // What the map says, in words.
        const where = m.site
            ? (m.lat === null ? '<span class="warn">No GPS fix yet</span> — the kiosk has not sent its position.'
              : m.inside ? `<span class="ok">Inside the geofence</span> · <b>${m.distance} m</b> from ${esc(m.site.name)}'s pin (radius ${m.site.radius} m)`
                         : `<span class="bad">Outside the geofence</span> · <b>${m.distance} m</b> from ${esc(m.site.name)}'s pin — ${m.distance - m.site.radius} m past its ${m.site.radius} m radius`)
            : (k.site ? `<span class="warn">${esc(k.site)} has no pin</span> — set it on the Sites page to measure the distance.` : '<span class="warn">No site set for this kiosk.</span>');
        const gps = m.lat === null ? '' : `<small>${m.gps === 'fix' ? 'GPS fix' : 'Last known position'} · ${esc(m.fix_at || '')} · ${m.lat.toFixed(5)}, ${m.lng.toFixed(5)}</small>
            <a href="https://www.google.com/maps?q=${m.lat},${m.lng}" target="_blank" rel="noopener">Open in Google Maps ↗</a>`;
        note.innerHTML = `<div>${where}</div>${gps}`;
    }

    // ── The screen ──────────────────────────────────────────────────────────
    function showScreen() {
        const s = data && data.screen;
        if (!s) return;
        opener.href = box.dataset.screen + '/' + s.kiosk.id + '/screen';
        if (!s.on) {
            const k = s.kiosk;
            offBox.innerHTML = `<div class="kx-off">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"><path d="M12 3v8M6.3 6.3a8 8 0 1011.4 0"/></svg>
                <b>KIOSK IS OFF</b>
                <p><strong>${esc(k.name)}</strong> ${k.seen_at ? 'has sent no heartbeat since <strong>' + esc(k.seen_at) + '</strong> (' + esc(k.seen) + ').' : 'has never reported to the web.'}</p>
                <p>Its screen shows here again on its own once it is switched on and online.</p></div>`;
            offBox.hidden = false; frame.hidden = true;
            if (loaded !== null) { frame.src = 'about:blank'; loaded = null; }
            cap.innerHTML = '<span>' + esc(k.name) + ' · off</span><span>View only</span>';
            return;
        }
        offBox.hidden = true; frame.hidden = false;
        if (loaded !== s.kiosk.id) { loaded = s.kiosk.id; frame.src = box.dataset.screen + '/' + s.kiosk.id + '/screen'; }
        cap.innerHTML = '<span><b>' + esc(s.kiosk.name) + '</b> · ' + esc(s.site || '') + ' · the kiosk\'s own screen, 1024 × 600</span><span>View only — nothing is recorded from here</span>';
    }

    function renderFacts() {
        const s = data && data.screen, k = s && s.kiosk;
        if (!k) return;
        const m = k.map || {};
        const cell = (label, value, cls) => `<div><span>${label}</span><b class="${cls || ''}">${value}</b></div>`;
        facts.innerHTML =
            cell('Status', k.state === 'ok' ? 'Online' : k.state === 'late' ? 'Online · late' : 'Off', k.state) +
            cell('Last heartbeat', k.seen ? esc(k.seen) : 'Never') +
            cell('Settings reached it', k.read ? esc(k.read) : '—') +
            cell('GPS', m.gps === 'fix' ? 'Fix' : m.gps === 'stale' ? 'Last known' : 'No signal', m.gps === 'fix' ? 'ok' : m.gps === 'none' ? 'late' : '');
    }

    // The one list of scans on the page. The chips count and filter it.
    let only = 'all';
    const chipsBox = box.querySelector('[data-chips]');
    const minutes = v => { const m = String(v || '').match(/^(\d{1,2}):(\d{2})(?::\d{2})?\s*([AP]M)$/i); if (!m) return -1; let h = +m[1] % 12; if (m[3].toUpperCase() === 'PM') h += 12; return h * 60 + +m[2]; };
    function renderLog() {
        const s = data && data.screen;
        if (!s || !s.on) { logBox.innerHTML = '<li class="none">Nothing to show while the kiosk is off.</li>'; logCount.textContent = ''; chipsBox.hidden = true; return; }
        chipsBox.hidden = false;
        const ev = [];
        ((s.board || {}).records || []).forEach(r => (r.entries || []).forEach(e => {
            if (e.in) ev.push({ t: e.in, who: r.name, type: 'in', ses: e.session });
            if (e.out) ev.push({ t: e.out, who: r.name, type: 'out', ses: e.session, auto: e.auto });
        }));
        recent.forEach(e => ev.push({ t: String(e.time || '').replace(/:\d{2}(\s*[AP]M)$/i, '$1'), who: e.name || 'Unknown finger', type: 'rej',
                                      label: e.kind === 'unknown' ? 'NOT RECOGNISED' : (WARN[e.code] || 'REJECTED') }));
        ev.sort((a, b) => minutes(b.t) - minutes(a.t));
        const n = kind => ev.filter(e => e.type === kind).length;
        const chip = (key, label, count) => `<button type="button" class="dvc-chip${only === key ? ' on' : ''}" data-chip="${key}">${key === 'all' ? '' : `<i class="${key}"></i>`}${label}<b>${count}</b></button>`;
        chipsBox.innerHTML = chip('all', 'All', ev.length) + chip('in', 'In', n('in')) + chip('out', 'Out', n('out')) + chip('rej', 'Rejected', n('rej'));
        logCount.textContent = ev.length + (ev.length === 1 ? ' scan' : ' scans');
        const shown = only === 'all' ? ev : ev.filter(e => e.type === only);
        logBox.innerHTML = shown.length ? shown.map(e => `<li><time>${esc(e.t)}</time><b>${esc(e.who)}</b><span class="${e.type}">${e.label ? esc(e.label) : (e.type === 'in' ? 'TIME IN' : 'TIME OUT') + ' · ' + esc(e.ses) + (e.auto ? ' · AUTO' : '')}</span></li>`).join('')
                                     : `<li class="none">${ev.length ? 'None of these yet today.' : 'No scans at this kiosk yet today.'}</li>`;
    }

    // The kiosk in the console is marked in the list below.
    function markWatched() {
        const id = data && data.screen && data.screen.kiosk ? data.screen.kiosk.id : current;
        document.querySelectorAll('[data-line]').forEach(l => l.classList.toggle('is-watched', Number(l.dataset.line) === id));
    }
    document.addEventListener('dv:refreshed', markWatched);

    function renderPicks() {
        markWatched();
        (data.kiosks || []).forEach(k => {
            const b = box.querySelector('[data-kiosk="' + k.id + '"]');
            if (!b) return;
            b.classList.toggle('on', !!(data.screen && data.screen.kiosk && data.screen.kiosk.id === k.id));
            b.querySelector('i').className = k.state;
        });
    }

    async function ask(light) {
        const url = new URL(box.dataset.url, location.href);
        if (current) url.searchParams.set('kiosk', current);
        url.searchParams.set('since', seq === null ? -1 : seq);
        if (light) url.searchParams.set('light', 1);
        const res = await fetch(url, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
        if (!res.ok) throw new Error(res.status);
        return res.json();
    }

    function take(r) {
        const events = r.events || [];
        if (r.seq !== undefined) seq = r.seq;
        events.forEach(e => {
            if (seen.has(e.seq)) return;
            seen.add(e.seq);
            if (!['in', 'out', 'scan'].includes(e.kind)) recent.push(e);
            if (frame.contentWindow && !frame.hidden) frame.contentWindow.postMessage({ type: 'kiosk-event', event: e }, location.origin);
        });
        recent = recent.slice(-20);
        return events.some(e => e.kind === 'in' || e.kind === 'out');
    }

    function mark(text, bad) { checked.textContent = text; checked.classList.toggle('bad', !!bad); }

    async function load() {
        try {
            data = await ask(false);
            current = data.screen && data.screen.kiosk ? data.screen.kiosk.id : current;
            mark('Live · ' + data.checked);
            take(data);
            ((data.screen && data.screen.rejects) || []).forEach(e => { if (!seen.has(e.seq)) { seen.add(e.seq); recent.push(e); } });
            recent = recent.slice(-30);
            showScreen(); renderFacts(); renderLog(); renderPicks(); fit();
            if (data.screen) drawMap(data.screen.kiosk);
        } catch (e) { mark('Could not reach the server — retrying', true); }
    }

    async function tick() {
        if (data && document.visibilityState === 'visible') {
            try {
                const r = await ask(true);
                const wasOn = !!(data.screen && data.screen.on);
                data.kiosks = r.kiosks;
                mark('Live · ' + r.checked);
                if (take(r) || !!(r.screen && r.screen.on) !== wasOn) await load();
                else { renderLog(); renderPicks(); }
            } catch (e) { /* the slow poll says so */ }
        }
        setTimeout(tick, 2000);
    }
    function schedule() { clearTimeout(timer); timer = setTimeout(async () => { if (document.visibilityState === 'visible') await load(); schedule(); }, 10000); }

    box.addEventListener('click', e => {
        const c = e.target.closest('[data-chip]');
        if (c) { only = c.dataset.chip; renderLog(); return; }
        const k = e.target.closest('[data-kiosk]');
        if (k) { current = Number(k.dataset.kiosk); seq = null; recent = []; seen.clear(); load(); return; }
        if (e.target.closest('[data-full]')) {
            const f = box.querySelector('.km-frame');
            if (document.fullscreenElement) document.exitFullscreen();
            else if (f && f.requestFullscreen) f.requestFullscreen().then(() => setTimeout(fit, 50)).catch(() => {});
        }
    });
    // A kiosk in the list below opens here.
    document.addEventListener('click', e => {
        const row = e.target.closest('[data-console-kiosk]');
        if (!row) return;
        e.preventDefault();
        current = Number(row.dataset.consoleKiosk); seq = null; recent = []; seen.clear(); load();
        box.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });

    load();
    schedule();
    tick();
})();

(function () {
    const live = () => document.getElementById('dv-live');

    document.addEventListener('click', e => {
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
            // The summary above the console refreshes with it.
            const top = document.getElementById('dv-top'), freshTop = doc.getElementById('dv-top');
            if (top && freshTop) top.innerHTML = freshTop.innerHTML;
            if (window.lucide) lucide.createIcons();
            document.dispatchEvent(new Event('dv:refreshed'));
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
