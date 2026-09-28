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
.dvc-map { position: relative; flex: 1 1 200px; min-height: 130px; border-bottom: 1px solid var(--border); }
.dvc-map #dvMap { position: absolute; inset: 0; z-index: 0; }
.dvc-map .leaflet-control-attribution { font-size: 9px; }
.dvc-mapnote { padding: 8px 14px; border-bottom: 1px solid var(--border); font-size: 12.5px; color: var(--text-secondary); display: flex; flex-direction: column; gap: 3px; }
.dvc-mapnote b { color: var(--text-primary); }
.dvc-mapnote .ok { color: var(--success); } .dvc-mapnote .bad { color: var(--danger); } .dvc-mapnote .warn { color: var(--warning); }
.dvc-mapnote small { font-family: 'JetBrains Mono', ui-monospace, monospace; font-size: 11px; color: var(--text-muted); }
.dvc-mapnote a { font-size: 11.5px; font-weight: 600; }
.dvc-facts { display: grid; grid-template-columns: 1fr 1fr; border-bottom: 1px solid var(--border); flex: none; }
.dvc-facts > div { padding: 6px 14px; border-right: 1px solid var(--border); border-bottom: 1px solid var(--border); min-width: 0; }
.dvc-facts > div:nth-child(2n) { border-right: 0; }
.dvc-facts > div:nth-last-child(-n+2) { border-bottom: 0; }
.dvc-facts span { display: block; font-size: 10px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--text-muted); }
.dvc-facts b { display: block; font-size: 13.5px; font-weight: 700; color: var(--text-primary); margin-top: 1px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.dvc-facts b.ok { color: var(--success); } .dvc-facts b.off { color: var(--danger); } .dvc-facts b.late { color: var(--warning); }
.dvc-log { flex: 1 1 150px; display: flex; flex-direction: column; min-height: 100px; }
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

/* The pins on the map. */
.dvc-pin { width: 18px; height: 18px; border-radius: 50%; border: 3px solid #fff; box-shadow: 0 1px 4px rgba(0,0,0,.35); background: #16a34a; }
.dvc-pin.stale { background: #8a97ab; }
.dvc-pin.off { background: #dc2626; }
.dvc-pin.live::after { content: ""; position: absolute; inset: -9px; border-radius: 50%; border: 2px solid #16a34a; animation: dvc-ring 1.8s ease-out infinite; }
@keyframes dvc-ring { from { transform: scale(.4); opacity: .9; } to { transform: scale(1.4); opacity: 0; } }
.dvc-site { width: 26px; height: 26px; border-radius: 7px; background: var(--brand, #1668dc); color: #fff; display: grid; place-items: center; box-shadow: 0 1px 4px rgba(0,0,0,.35); border: 2px solid #fff; font: 800 11px Inter, sans-serif; }
@media (prefers-reduced-motion: reduce) { .dvc-live::before, .dvc-pin.live::after { animation: none; } }

@media (max-width: 1200px) {
    .dvc-body { grid-template-columns: minmax(0, 1fr); }
    .dvc-screen { border-right: 0; border-bottom: 1px solid var(--border); }
    .dvc-side { height: auto; min-height: 0; }
    .dvc-map { flex: none; height: 260px; }
    .dvc-log ol { max-height: 320px; }
}
.dvc-side .dvc-mapnote, .dvc-side .dvc-facts { flex: none; }
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
/* The kiosks share the width and reach the bottom of the screen: one kiosk
   takes the row, two split it. Each card's list of today's scans takes what
   height is left, so there is no empty space under or beside them. */
.dv-live { display: flex; flex-direction: column; min-height: calc(100dvh - var(--topbar-height, 60px) - 118px); }
.dv-grid { flex: 1 1 auto; display: grid; grid-template-columns: repeat(auto-fit, minmax(460px, 1fr)); gap: 14px; align-items: stretch; }
.dv-card { display: flex; flex-direction: column; }
.dv-today { flex: 1 1 auto; display: flex; flex-direction: column; min-height: 150px; border-bottom: 1px solid var(--border); }
.dv-today .dv-row { padding: 9px 14px 6px; }
.dv-today ol { list-style: none; margin: 0; padding: 0; flex: 1 1 0; min-height: 110px; overflow-y: auto; }
.dv-today li { display: grid; grid-template-columns: 62px minmax(0, 1fr) auto; gap: 10px; align-items: center; padding: 6px 14px; border-top: 1px solid var(--border); font-size: 12.5px; }
.dv-today li:nth-child(even) { background: color-mix(in srgb, var(--bg-subtle) 60%, transparent); }
.dv-today time { font-family: 'JetBrains Mono', monospace; font-size: 11.5px; color: var(--text-muted); }
.dv-today li b { font-weight: 600; color: var(--text-primary); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.dv-today li span { font-size: 10.5px; font-weight: 800; letter-spacing: .04em; padding: 2px 7px; border-radius: 5px; white-space: nowrap; }
.dv-today li span.in { color: var(--success); background: var(--success-soft); }
.dv-today li span.out { color: var(--danger); background: var(--danger-soft); }
.dv-today li span.rej { color: var(--warning); background: var(--warning-soft); }
.dv-today .none { display: grid; place-items: center; padding: 20px; color: var(--text-muted); font-size: 12.5px; border-top: 1px solid var(--border); flex: 1; }
@media (max-width: 1100px) { .dv-grid { grid-template-columns: 1fr; } .dv-live { min-height: 0; } }
.dv-card { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; box-shadow: var(--shadow-xs); position: relative; }
.dv-card.is-off { border-color: color-mix(in srgb, var(--danger) 40%, var(--border)); }
.dv-card.is-off::before, .dv-card.is-late::before { content: ""; position: absolute; left: -1px; right: -1px; top: -1px; height: 3px; border-radius: 12px 12px 0 0; background: var(--danger); }
.dv-card.is-late::before { background: var(--warning); }
.dv-head { display: flex; align-items: center; gap: 12px; padding: 10px 14px; border-bottom: 1px solid var(--border); }
.dv-ico { width: 32px; height: 32px; border-radius: 9px; display: grid; place-items: center; background: var(--success-soft); color: var(--success); position: relative; flex: none; }
.dv-ico svg { width: 16px; height: 16px; }
.dv-ico::after { content: ""; position: absolute; right: -3px; bottom: -3px; width: 12px; height: 12px; border-radius: 50%; background: var(--success); border: 2.5px solid var(--surface); }
.is-late .dv-ico { background: var(--warning-soft); color: var(--warning); } .is-late .dv-ico::after { background: var(--warning); }
.is-off .dv-ico { background: var(--danger-soft); color: var(--danger); } .is-off .dv-ico::after { background: var(--danger); }
.dv-name { font-size: 13.5px; font-weight: 700; color: var(--text-primary); }
.dv-meta { font-size: 12px; color: var(--text-muted); display: flex; align-items: center; gap: 6px; margin-top: 2px; flex-wrap: wrap; }
.dv-meta svg { width: 12px; height: 12px; }
.dv-head .sx-badge { margin-left: auto; }
.dv-sec { padding: 9px 14px 10px; border-bottom: 1px solid var(--border); }
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

.hr { position: relative; height: 48px; display: grid; grid-template-columns: repeat({{ $hours }}, 1fr); gap: 4px; align-items: end; }
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
.dv-cell { padding: 9px 14px; display: flex; gap: 12px; align-items: center; min-width: 0; }
.dv-cell + .dv-cell { border-left: 1px solid var(--border); }
.dv-cell .v { font-size: 12.5px; font-weight: 600; color: var(--text-primary); margin-top: 3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.dv-cell .s { font-family: 'JetBrains Mono', monospace; font-size: 11px; color: var(--text-muted); margin-top: 2px; white-space: nowrap; }
.dv-cell .sx-link { font-size: 11.5px; margin-top: 3px; }
.gps { width: 70px; height: 48px; border-radius: 8px; border: 1px solid var(--border); position: relative; overflow: hidden; flex: none; background-color: var(--bg-subtle);
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
            </div>
            <aside class="dvc-side">
                <div class="dvc-map"><div id="dvMap" aria-label="Where the kiosk is"></div></div>
                <div class="dvc-mapnote" data-mapnote>Reading the kiosk's position…</div>
                <div class="dvc-facts" data-facts></div>
                <div class="dvc-log"><header><span>Scans today at this kiosk</span><span data-count></span></header><ol data-log></ol></div>
            </aside>
        </div>
    </section>
    @endif

    <div id="dv-live" class="dv-live">
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
                        <a class="sx-btn sm dv-watch" href="#dvConsole" data-console-kiosk="{{ $k->id }}"><i data-lucide="monitor-play"></i> Watch</a>
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

                    <section class="dv-today">
                        <div class="dv-row"><span class="dv-lbl">Scans today at this kiosk</span><span class="dv-val">{{ count($d['today']) }}</span></div>
                        @if($d['today'])
                            <ol>
                                @foreach($d['today'] as $s)
                                    <li><time>{{ $s['at']->format('g:i A') }}</time><b>{{ $s['name'] }}</b>
                                        <span class="{{ $s['kind'] }}">{{ $s['kind'] === 'rej' ? mb_strtoupper($s['why']) : ($s['kind'] === 'in' ? 'TIME IN' : 'TIME OUT') . ($s['session'] ? ' · ' . $s['session'] : '') . ($s['auto'] ? ' · AUTO' : '') }}</span></li>
                                @endforeach
                            </ol>
                        @else
                            <div class="none">No scans at this kiosk yet today.</div>
                        @endif
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
                   no_gps: 'LOCATION NOT CONFIRMED', outside_location: 'LOCATION NOT CONFIRMED' };
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
        const b = s.board || {};
        const cell = (label, value, cls) => `<div><span>${label}</span><b class="${cls || ''}">${value}</b></div>`;
        facts.innerHTML =
            cell('Status', k.state === 'ok' ? 'Online' : k.state === 'late' ? 'Online · late' : 'Off', k.state) +
            cell('Last heartbeat', k.seen ? esc(k.seen) : 'Never') +
            cell('Settings reached it', k.read ? esc(k.read) : '—') +
            cell('Scanned today', s.on ? (b.total ?? 0) + ' ' + ((b.total ?? 0) === 1 ? 'worker' : 'workers') : '—');
    }

    const minutes = v => { const m = String(v || '').match(/^(\d{1,2}):(\d{2})(?::\d{2})?\s*([AP]M)$/i); if (!m) return -1; let h = +m[1] % 12; if (m[3].toUpperCase() === 'PM') h += 12; return h * 60 + +m[2]; };
    function renderLog() {
        const s = data && data.screen;
        if (!s || !s.on) { logBox.innerHTML = '<li class="none">Nothing to show while the kiosk is off.</li>'; logCount.textContent = ''; return; }
        const ev = [];
        ((s.board || {}).records || []).forEach(r => (r.entries || []).forEach(e => {
            if (e.in) ev.push({ t: e.in, who: r.name, type: 'in', ses: e.session });
            if (e.out) ev.push({ t: e.out, who: r.name, type: 'out', ses: e.session, auto: e.auto });
        }));
        recent.forEach(e => ev.push({ t: String(e.time || '').replace(/:\d{2}(\s*[AP]M)$/i, '$1'), who: e.name || 'Unknown finger', type: 'rej',
                                      label: e.kind === 'unknown' ? 'NOT RECOGNISED' : (WARN[e.code] || 'REJECTED') }));
        ev.sort((a, b) => minutes(b.t) - minutes(a.t));
        logCount.textContent = ev.length;
        logBox.innerHTML = ev.length ? ev.map(e => `<li><time>${esc(e.t)}</time><b>${esc(e.who)}</b><span class="${e.type}">${e.label ? esc(e.label) : (e.type === 'in' ? 'TIME IN' : 'TIME OUT') + ' · ' + esc(e.ses) + (e.auto ? ' · AUTO' : '')}</span></li>`).join('')
                                     : '<li class="none">No scans at this kiosk yet today.</li>';
    }

    function renderPicks() {
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
            // The summary above the console refreshes with it.
            const top = document.getElementById('dv-top'), freshTop = doc.getElementById('dv-top');
            if (top && freshTop) top.innerHTML = freshTop.innerHTML;
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
