@extends('layouts')
@section('page_title', 'Audit Logs')

@php
    use Illuminate\Support\Str;

    $roleCls = [
        'admin' => 'r-admin', 'staff' => 'r-staff', 'payroll_officer' => 'r-payroll',
        'hr' => 'r-hr', 'site_supervisor' => 'r-sup', 'employee' => 'r-emp',
    ];
    $initials = fn ($name) => collect(preg_split('/\s+/', trim((string) $name) ?: '?'))
                    ->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
    $nodeIcon = fn ($action) => match (strtolower((string) $action)) {
        'created'                   => 'plus',
        'approved', 'finalized'     => 'check',
        'updated'                   => 'pencil',
        'recalculated', 'reopened'  => 'refresh-cw',
        'deleted'                   => 'trash-2',
        'rejected', 'cancelled'     => 'x',
        'restored'                  => 'rotate-ccw',
        'signed in'                 => 'log-in',
        'signed out'                => 'log-out',
        'failed'                    => 'shield-alert',
        'locked out'                => 'lock',
        'blocked'                   => 'ban',
        'password reset'            => 'key-round',
        'time in', 'time out'       => 'clock',
        'exported'                  => 'download',
        'remittance', 'submitted'   => 'send',
        default                     => 'dot',
    };

    // A link to this page with some of the query changed; null removes a key.
    $here = function (array $change = [], bool $keepPage = false) {
        $params = request()->except($keepPage ? [] : ['page']);
        foreach ($change as $k => $v) {
            if ($v === null || $v === [] || $v === '') {
                unset($params[$k]);
            } else {
                $params[$k] = $v;
            }
        }
        return route('audit-logs.index', $params);
    };
    // Tick or untick one value of a multi-value filter.
    $toggle = function (string $key, string $value) use ($selected, $here) {
        $list = $selected[$key];
        $list = in_array($value, $list, true) ? array_values(array_diff($list, [$value])) : [...$list, $value];
        return $here([$key => $list]);
    };
    // A period of its own replaces any other.
    $periodLink = fn (array $p) => $here(['range' => null, 'from' => null, 'to' => null] + $p);

    $periodLabel = match ($range) {
        'today'  => 'today',
        '30'     => 'the last 30 days',
        'all'    => 'all time',
        'custom' => $from->format('M j') . ' – ' . $to->format('M j, Y'),
        default  => 'the last 7 days',
    };
    $dayLabel = function ($date) {
        $d = \Illuminate\Support\Carbon::parse($date);
        return ($d->isToday() ? 'Today · ' : ($d->isYesterday() ? 'Yesterday · ' : '')) . $d->format('D, M j');
    };

    $parts = collect([
        [$summary['deletions'], 'deletion'], [$summary['rejections'], 'rejection'],
        [$summary['roles'], 'role change'], [$summary['settings'], 'settings change'],
        [$summary['signins'], 'refused sign-in'],
    ])->filter(fn ($p) => $p[0] > 0)->map(fn ($p) => $p[0] . ' ' . Str::plural($p[1], $p[0]))->implode(', ');

    $max    = max(10, (int) ceil($chart->max('n') / 10) * 10);
    $inFrom = $from->toDateString();
    $inTo   = $to->toDateString();
    $view   = request('view') === 'flat' ? 'flat' : 'day';
    $open   = (int) request('entry');
    $items  = collect($logs->items());
    $shown  = $items->groupBy(fn ($l) => $l->created_at->toDateString())->map->count();

    $chips = [];
    $chips[] = [$range === 'custom' ? $periodLabel : Str::ucfirst($periodLabel), $range === '7' ? null : $periodLink([])];
    if (request('q')) $chips[] = ['“' . Str::limit(request('q'), 30) . '”', $here(['q' => null])];
    if (request('quick') === 'sensitive') $chips[] = ['Sensitive only', $here(['quick' => null])];
    if (request('quick') === 'mine') $chips[] = ['My actions', $here(['quick' => null])];
    foreach (['module', 'action', 'person'] as $k) foreach ($selected[$k] as $v) $chips[] = [Str::ucfirst($v), $toggle($k, $v)];
    if (request('subject_type')) $chips[] = [request('subject_type') . ' #' . request('subject_id'), $here(['subject_type' => null, 'subject_id' => null])];
    if (request('user_id')) $chips[] = ['Person #' . request('user_id'), $here(['user_id' => null])];
@endphp

@push('styles')
@include('system._kit')
<style>
.au-top { display: grid; grid-template-columns: minmax(0, 1fr) 272px; }
@media (max-width: 1000px) { .au-top { grid-template-columns: 1fr; } }
.au-chart { padding: 26px 18px 12px 16px; position: relative; min-width: 0; }
.au-plot { position: relative; height: 112px; margin-left: 26px; border-bottom: 1px solid var(--border-md); }
.au-grid i { position: absolute; left: 0; right: 0; border-top: 1px dashed var(--border); }
.au-grid b { position: absolute; left: -26px; font: 500 10px 'JetBrains Mono', monospace; color: var(--text-muted); transform: translateY(50%); }
.au-bars { position: absolute; inset: 0; display: flex; align-items: flex-end; gap: 4px; }
.au-bars a { flex: 1; height: 100%; display: flex; align-items: flex-end; }
.au-bars a i { display: block; width: 100%; background: color-mix(in srgb, var(--brand) 22%, var(--surface)); border-radius: 3px 3px 0 0; min-height: 2px; transition: filter .12s; }
.au-bars a:hover i { filter: brightness(.9); }
.au-bars a.in i { background: var(--brand); }
.au-bars a.sun i { opacity: .6; }
.au-bars a.today i { background: repeating-linear-gradient(135deg, var(--brand) 0 3px, color-mix(in srgb, var(--brand) 55%, var(--surface)) 3px 6px); }
.au-bars a.today:not(.in) i { opacity: .6; }
.au-brush { position: absolute; top: -10px; bottom: -1px; border: 1.5px solid var(--brand); border-radius: 6px; background: color-mix(in srgb, var(--brand) 7%, transparent); pointer-events: none; }
.au-brush .h { position: absolute; top: 50%; width: 9px; height: 26px; margin-top: -13px; background: var(--surface); border: 1.5px solid var(--brand); border-radius: 3px; pointer-events: auto; cursor: ew-resize; }
.au-brush .h.l { left: -5.5px; } .au-brush .h.r { right: -5.5px; }
.au-brush-l { position: absolute; top: -22px; right: -2px; font: 600 10.5px 'Inter', sans-serif; color: #fff; background: var(--brand); padding: 2px 7px; border-radius: 4px; white-space: nowrap; }
.au-x { position: relative; height: 16px; margin: 6px 0 0 26px; }
.au-x span { position: absolute; font: 500 10px 'JetBrains Mono', monospace; color: var(--text-muted); transform: translateX(-50%); white-space: nowrap; }
.au-tip { position: absolute; transform: translateX(-50%); background: var(--inverse); color: var(--inverse-text); font-size: 11.5px; padding: 6px 9px; border-radius: 7px; white-space: nowrap; box-shadow: var(--shadow-lg); z-index: 3; line-height: 1.35; pointer-events: none; }
.au-tip b { display: block; font-weight: 700; }
.au-tip::after { content: ""; position: absolute; left: 50%; bottom: -4px; margin-left: -4px; width: 8px; height: 8px; background: var(--inverse); transform: rotate(45deg); }
.au-out { border-left: 1px solid var(--border); padding: 16px 18px; }
.au-stack { display: flex; height: 12px; border-radius: 4px; overflow: hidden; gap: 2px; margin: 10px 0 14px; background: var(--bg-subtle); }
.au-leg { display: flex; align-items: center; gap: 9px; font-size: 12.5px; color: var(--text-secondary); padding: 4px 0; }
.au-leg i { width: 9px; height: 9px; border-radius: 3px; flex: none; }
.au-leg b { margin-left: auto; font-family: 'JetBrains Mono', monospace; font-size: 12px; color: var(--text-primary); }
.t-ok { background: var(--success); } .t-warn { background: var(--warning); } .t-danger { background: var(--danger); } .t-muted { background: var(--border-md); }
.au-custom { display: none; align-items: center; gap: 8px; padding: 10px 16px; border-bottom: 1px solid var(--border); background: var(--bg-subtle); flex-wrap: wrap; font-size: 12px; color: var(--text-muted); }
.au-custom.show { display: flex; }
.au-custom input[type="date"] { height: 32px; border: 1px solid var(--border); border-radius: 7px; background: var(--surface); color: var(--text-primary); padding: 0 8px; font-size: 12.5px; min-width: 140px; }

.au-body { display: grid; grid-template-columns: 252px minmax(0, 1fr); gap: 14px; align-items: start; margin-top: 14px; }
@media (max-width: 1000px) { .au-body { grid-template-columns: 1fr; } }
.fc { padding: 12px 14px; border-bottom: 1px solid var(--border); }
.fc:last-child { border-bottom: none; }
.fc-h { display: flex; justify-content: space-between; align-items: center; margin-bottom: 7px; }
.fc-h a { font-size: 11.5px; font-weight: 500; color: var(--text-muted); }
.fc-opt { display: flex; align-items: center; gap: 9px; padding: 4px 0; font-size: 12.5px; color: var(--text-primary); }
.fc-opt:hover { color: var(--brand); }
.fc-opt .cb { width: 15px; height: 15px; border-radius: 4px; border: 1.5px solid var(--border-md); flex: none; display: grid; place-items: center; }
.fc-opt .cb svg { width: 11px; height: 11px; stroke-width: 3; }
.fc-opt.on .cb { background: var(--brand); border-color: var(--brand); color: #fff; }
.fc-opt .lb { flex: 1; min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.fc-bar { width: 44px; height: 4px; background: var(--bg-subtle); border-radius: 2px; overflow: hidden; box-shadow: inset 0 0 0 1px var(--border); flex: none; }
.fc-bar i { display: block; height: 100%; background: var(--text-muted); opacity: .55; }
.fc-opt .n { width: 26px; text-align: right; font-family: 'JetBrains Mono', monospace; font-size: 11px; color: var(--text-muted); flex: none; }
.fc-chips { display: flex; flex-wrap: wrap; gap: 6px; }
.fc-chips a { height: 27px; padding: 0 10px; border: 1px solid var(--border); border-radius: 999px; font-size: 12px; font-weight: 600; color: var(--text-secondary); display: inline-flex; align-items: center; gap: 6px; background: var(--surface); }
.fc-chips a.on { background: var(--brand); border-color: var(--brand); color: #fff; }
.fc-chips a .n { font-family: 'JetBrains Mono', monospace; font-size: 10.5px; font-weight: 500; opacity: .75; }
.fc-chips a.sens:not(.on) { border-color: color-mix(in srgb, var(--warning) 45%, var(--border)); color: var(--warning); }
.fc-empty { font-size: 12px; color: var(--text-muted); }
.dotc { width: 8px; height: 8px; border-radius: 50%; flex: none; }

.lg-filters { display: flex; align-items: center; gap: 8px; padding: 9px 16px; border-bottom: 1px solid var(--border); font-size: 12px; color: var(--text-muted); flex-wrap: wrap; }
.chip { display: inline-flex; align-items: center; gap: 6px; height: 26px; padding: 0 7px 0 10px; border-radius: 999px; background: var(--brand-subtle); color: var(--brand); font-size: 12px; font-weight: 600; }
.chip svg { width: 12px; height: 12px; }
.lg-day { display: flex; align-items: center; gap: 10px; padding: 8px 16px; background: var(--bg-subtle); border-bottom: 1px solid var(--border); }
.lg-day .sx-label { color: var(--text-secondary); }
.lg-day .n { margin-left: auto; font-family: 'JetBrains Mono', monospace; font-size: 11px; color: var(--text-muted); }
.lg-e { display: grid; grid-template-columns: 58px 28px minmax(0, 1fr) 18px; column-gap: 12px; padding: 12px 16px; border-bottom: 1px solid var(--border); position: relative; cursor: pointer; }
.lg-e:hover { background: color-mix(in srgb, var(--brand) 2%, var(--surface)); }
.lg-e::before { content: ""; position: absolute; left: calc(16px + 58px + 12px + 13.5px); top: 0; bottom: 0; width: 1px; background: var(--border); }
.lg-time { font-family: 'JetBrains Mono', monospace; font-size: 12.5px; font-weight: 600; color: var(--text-primary); text-align: right; padding-top: 4px; line-height: 1.2; }
.lg-time small { display: block; font-size: 10px; color: var(--text-muted); font-weight: 500; }
.lg-node { width: 28px; height: 28px; border-radius: 50%; display: grid; place-items: center; position: relative; z-index: 1; box-shadow: 0 0 0 3px var(--surface); background: var(--bg-subtle); color: var(--text-muted); }
.lg-node svg { width: 13px; height: 13px; stroke-width: 2.5; }
.lg-node.ok { background: var(--success-soft); color: var(--success); }
.lg-node.warn { background: var(--warning-soft); color: var(--warning); }
.lg-node.danger { background: var(--danger-soft); color: var(--danger); }
.lg-line { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; font-size: 13px; line-height: 1.3; min-height: 28px; }
.lg-line b { font-weight: 600; color: var(--text-primary); }
.lg-verb { font-size: 11.5px; font-weight: 700; padding: 2px 7px; border-radius: 5px; background: var(--bg-subtle); color: var(--text-secondary); }
.lg-verb.ok { color: var(--success); background: var(--success-soft); }
.lg-verb.warn { color: var(--warning); background: var(--warning-soft); }
.lg-verb.danger { color: var(--danger); background: var(--danger-soft); }
.lg-desc { font-size: 13px; color: var(--text-secondary); margin-top: 3px; line-height: 1.45; word-break: break-word; }
.lg-meta { display: flex; align-items: center; gap: 10px; margin-top: 6px; font-family: 'JetBrains Mono', monospace; font-size: 11px; color: var(--text-muted); flex-wrap: wrap; }
.lg-meta .subj { color: var(--brand); font-family: 'Inter', sans-serif; font-weight: 600; font-size: 11.5px; display: inline-flex; align-items: center; gap: 4px; }
.lg-meta .subj svg { width: 12px; height: 12px; }
.lg-chev { color: var(--text-muted); padding-top: 6px; transition: transform .15s; }
.lg-chev svg { width: 16px; height: 16px; }
.lg-e.open { background: color-mix(in srgb, var(--brand) 4%, var(--surface)); }
.lg-e.open::after { content: ""; position: absolute; left: 0; top: 0; bottom: 0; width: 3px; background: var(--brand); }
.lg-e.open .lg-chev { color: var(--brand); transform: rotate(90deg); }
.lg-detail { display: none; margin-top: 12px; border: 1px solid var(--border); border-radius: 10px; background: var(--surface); cursor: auto; }
.lg-e.open .lg-detail { display: block; }
.lg-e.open .lg-meta { display: none; }
.lg-dgrid { display: grid; grid-template-columns: 1.15fr .7fr .9fr 1.1fr; }
@media (max-width: 900px) { .lg-dgrid { grid-template-columns: 1fr 1fr; } }
.lg-dgrid > div { padding: 10px 12px; border-right: 1px solid var(--border); }
.lg-dgrid > div:last-child { border-right: none; }
.lg-dgrid .v { font-size: 12.5px; font-weight: 600; margin-top: 4px; color: var(--text-primary); }
.lg-dgrid .v.mono { font-size: 12px; }
.lg-subj { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-top: 1px solid var(--border); font-size: 12.5px; flex-wrap: wrap; }
.lg-subj b { font-weight: 600; color: var(--text-primary); }
.lg-subj .sp { margin-left: auto; display: flex; gap: 6px; flex-wrap: wrap; }
.lg-ua { padding: 8px 12px; border-top: 1px solid var(--border); font-family: 'JetBrains Mono', monospace; font-size: 10.5px; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.lg-seal { display: flex; align-items: center; gap: 8px; padding: 9px 12px; border-top: 1px solid var(--border); background: var(--bg-subtle); border-radius: 0 0 10px 10px; font-size: 12px; color: var(--text-secondary); }
.lg-seal svg { width: 14px; height: 14px; color: var(--text-muted); }
.lg-more { padding: 10px 16px 10px 114px; font-size: 12.5px; border-bottom: 1px solid var(--border); }
.lg-load { display: flex; justify-content: center; align-items: center; gap: 12px; padding: 14px 16px; font-size: 12px; color: var(--text-muted); flex-wrap: wrap; }
</style>
@endpush

@section('content')
<div class="sx-page">

    <div class="sx-head">
        <div>
            <div class="sx-eyebrow">System · 02 / 04</div>
            <h1 class="sx-title">Audit Logs</h1>
            <p class="sx-sub">Who did what, and when. A sealed record: nothing in the system edits or deletes an entry, and this screen only reads.</p>
        </div>
        <div class="sx-actions">
            <span class="sx-badge muted nodot" style="height:34px;padding:0 11px;border-radius:8px"><i data-lucide="lock"></i> Append-only</span>
            <a class="sx-btn" href="{{ route('audit-logs.export', request()->except('page', 'entry')) }}"><i data-lucide="download"></i> Export this view (CSV)</a>
        </div>
    </div>

    {{-- ── Status line ─────────────────────────────────────────────────── --}}
    <div class="sx-status {{ $summary['sensitive'] ? 'warn' : '' }}">
        <span class="sx-status-ico"><i data-lucide="{{ $summary['sensitive'] ? 'eye' : 'shield-check' }}"></i></span>
        <span><b>{{ number_format($summary['total']) }} {{ Str::plural('entry', $summary['total']) }}</b> in {{ $periodLabel }}</span><span class="dotsep"></span>
        @if($summary['sensitive'])
            <span><b>{{ $summary['sensitive'] }} sensitive</b>@if($parts) — {{ $parts }}@endif</span>
        @else
            <span>nothing sensitive</span>
        @endif
        <span class="sx-status-end">
            @if($lastEntry) Last entry {{ $lastEntry->created_at->diffForHumans() }} @endif
            @if($summary['sensitive'])
                <a class="sx-link" href="{{ $here(['quick' => 'sensitive']) }}">Review sensitive <i data-lucide="arrow-right"></i></a>
            @endif
        </span>
    </div>

    {{-- ── A · Activity ────────────────────────────────────────────────── --}}
    <div class="sx-card">
        <div class="sx-card-head">
            <span class="sx-idx">A</span><h2 class="sx-card-title">Activity</h2>
            <span class="sx-card-note">Entries per day, last 30 days — drag the handles to set the range</span>
            <div class="sx-card-tools">
                <div class="sx-seg">
                    <a class="{{ $range === 'today' ? 'on' : '' }}" href="{{ $periodLink(['range' => 'today']) }}">Today</a>
                    <a class="{{ $range === '7' ? 'on' : '' }}" href="{{ $periodLink([]) }}">7 days</a>
                    <a class="{{ $range === '30' ? 'on' : '' }}" href="{{ $periodLink(['range' => '30']) }}">30 days</a>
                    <button type="button" class="{{ in_array($range, ['custom', 'all'], true) ? 'on' : '' }}" data-custom-toggle><i data-lucide="calendar"></i> Custom</button>
                </div>
            </div>
        </div>
        <form method="GET" action="{{ route('audit-logs.index') }}" class="au-custom {{ in_array($range, ['custom', 'all'], true) ? 'show' : '' }}" id="au-custom">
            @foreach(request()->except(['from', 'to', 'range', 'page', 'entry']) as $k => $v)
                @foreach((array) $v as $item)<input type="hidden" name="{{ is_array($v) ? $k . '[]' : $k }}" value="{{ $item }}">@endforeach
            @endforeach
            From <input type="date" name="from" value="{{ $range === 'custom' ? $from->toDateString() : '' }}">
            to <input type="date" name="to" value="{{ $range === 'custom' ? $to->toDateString() : '' }}">
            <button type="submit" class="sx-btn sm primary">Apply</button>
            <a class="sx-btn sm {{ $range === 'all' ? 'primary' : '' }}" href="{{ $periodLink(['range' => 'all']) }}">All time</a>
        </form>
        <div class="au-top">
            <div class="au-chart">
                <div class="au-plot" id="au-plot">
                    <div class="au-grid">
                        @foreach([0, .25, .5, .75, 1] as $f)
                            <i style="bottom:{{ $f * 100 }}%"></i><b style="bottom:{{ $f * 100 }}%">{{ (int) round($max * $f) }}</b>
                        @endforeach
                    </div>
                    <div class="au-bars" id="au-bars">
                        @foreach($chart as $bar)
                            @php $in = $bar['date'] >= $inFrom && $bar['date'] <= $inTo; @endphp
                            <a href="{{ $periodLink(['from' => $bar['date'], 'to' => $bar['date']]) }}"
                               class="{{ $in ? 'in' : '' }} {{ $bar['day']->isSunday() ? 'sun' : '' }} {{ $bar['day']->isToday() ? 'today' : '' }}"
                               data-date="{{ $bar['date'] }}" data-label="{{ $bar['day']->format('D, M j') }}" data-n="{{ $bar['n'] }}"
                               aria-label="{{ $bar['day']->format('M j') }}: {{ $bar['n'] }} entries">
                                <i style="height:{{ $bar['n'] ? max(2, $bar['n'] / $max * 100) : 0 }}%"></i>
                            </a>
                        @endforeach
                    </div>
                    <div class="au-brush" id="au-brush" hidden>
                        <span class="h l" data-edge="l"></span><span class="h r" data-edge="r"></span>
                        <span class="au-brush-l">{{ $range === 'custom' ? $from->format('M j') . ' – ' . $to->format('M j') : Str::ucfirst($periodLabel) }} · {{ number_format($logs->total()) }}</span>
                    </div>
                    <div class="au-tip" id="au-tip" hidden></div>
                </div>
                <div class="au-x" id="au-x"></div>
            </div>
            <div class="au-out">
                @php $outTotal = max(1, array_sum($outcome)); @endphp
                <span class="sx-label">By outcome · in range</span>
                <div class="au-stack">
                    @foreach(['ok', 'warn', 'danger', 'muted'] as $t)
                        @if($outcome[$t])<i class="t-{{ $t }}" style="flex: {{ $outcome[$t] }}"></i>@endif
                    @endforeach
                </div>
                <div class="au-leg"><i class="t-ok"></i>Added or approved<b>{{ $outcome['ok'] }}</b></div>
                <div class="au-leg"><i class="t-warn"></i>Changed<b>{{ $outcome['warn'] }}</b></div>
                <div class="au-leg"><i class="t-danger"></i>Removed or refused<b>{{ $outcome['danger'] }}</b></div>
                <div class="au-leg"><i class="t-muted"></i>Other<b>{{ $outcome['muted'] }}</b></div>
            </div>
        </div>
    </div>

    <div class="au-body">
        {{-- ── Facet rail ───────────────────────────────────────────────── --}}
        <aside class="sx-card">
            <div class="fc">
                <form method="GET" action="{{ route('audit-logs.index') }}" class="sx-input">
                    <i data-lucide="search"></i>
                    @foreach(request()->except(['q', 'page', 'entry']) as $k => $v)
                        @foreach((array) $v as $item)<input type="hidden" name="{{ is_array($v) ? $k . '[]' : $k }}" value="{{ $item }}">@endforeach
                    @endforeach
                    <input type="search" name="q" value="{{ request('q') }}" placeholder="Search descriptions" aria-label="Search descriptions">
                </form>
            </div>
            <div class="fc">
                <div class="fc-h"><span class="sx-label">Quick view</span></div>
                <div class="fc-chips">
                    <a class="{{ ! request('quick') ? 'on' : '' }}" href="{{ $here(['quick' => null]) }}">Everything <span class="n">{{ number_format($summary['total']) }}</span></a>
                    <a class="sens {{ request('quick') === 'sensitive' ? 'on' : '' }}" href="{{ $here(['quick' => 'sensitive']) }}">Sensitive <span class="n">{{ $summary['sensitive'] }}</span></a>
                    <a class="{{ request('quick') === 'mine' ? 'on' : '' }}" href="{{ $here(['quick' => 'mine']) }}">Mine</a>
                </div>
            </div>
            @foreach([
                ['module', 'Module', $facets['modules']],
                ['action', 'Action', $facets['actions']],
                ['person', 'Person', $facets['people']],
            ] as [$key, $title, $options])
                @php $top = max(1, $options->max() ?? 1); @endphp
                <div class="fc">
                    <div class="fc-h"><span class="sx-label">{{ $title }}</span>
                        @if($selected[$key])<a href="{{ $here([$key => null]) }}">Clear</a>@else<span class="fc-empty">{{ $key === 'person' ? 'Anyone' : 'Any' }}</span>@endif
                    </div>
                    @forelse($options as $value => $n)
                        @php $value = (string) $value; $on = in_array($value, $selected[$key], true); @endphp
                        <a class="fc-opt {{ $on ? 'on' : '' }}" href="{{ $toggle($key, $value) }}">
                            <span class="cb">@if($on)<i data-lucide="check"></i>@endif</span>
                            @if($key === 'action')<span class="dotc t-{{ \App\Models\AuditLog::toneFor($value) }}"></span>@endif
                            @if($key === 'person')<span class="av sm {{ $roleCls[$nameRoles[$value] ?? ''] ?? 'r-emp' }}">{{ $initials($value) }}</span>@endif
                            <span class="lb">{{ Str::ucfirst($value) }}</span>
                            <span class="fc-bar"><i style="width:{{ $n / $top * 100 }}%"></i></span>
                            <span class="n">{{ $n }}</span>
                        </a>
                    @empty
                        <div class="fc-empty">Nothing in this period.</div>
                    @endforelse
                </div>
            @endforeach
        </aside>

        {{-- ── B · Entries ──────────────────────────────────────────────── --}}
        <section class="sx-card">
            <div class="sx-card-head">
                <span class="sx-idx">B</span><h2 class="sx-card-title">Entries</h2><span class="sx-card-note">Newest first · {{ $view === 'day' ? 'grouped by day · ' : '' }}times in PH time</span>
                <div class="sx-card-tools">
                    <div class="sx-seg">
                        <a class="{{ $view === 'day' ? 'on' : '' }}" href="{{ $here(['view' => null], true) }}"><i data-lucide="rows-3"></i> By day</a>
                        <a class="{{ $view === 'flat' ? 'on' : '' }}" href="{{ $here(['view' => 'flat'], true) }}"><i data-lucide="list"></i> Flat</a>
                    </div>
                </div>
            </div>
            <div class="lg-filters">
                Filters
                @foreach($chips as [$label, $remove])
                    @if($remove)
                        <a class="chip" href="{{ $remove }}">{{ $label }} <i data-lucide="x"></i></a>
                    @else
                        <span class="chip">{{ $label }}</span>
                    @endif
                @endforeach
                @if(count($chips) > 1 || $range !== '7')<a class="sx-link" style="font-weight:500;color:var(--text-muted)" href="{{ route('audit-logs.index') }}">Clear all</a>@endif
                <span style="margin-left:auto" class="mono">{{ number_format($logs->total()) }} {{ Str::plural('result', $logs->total()) }}</span>
            </div>

            @php $day = null; @endphp
            @forelse($logs as $log)
                @php
                    $d    = $log->created_at->toDateString();
                    $tone = $log->tone;
                    $who  = $log->user_name ?: 'System';
                    $cls  = $roleCls[$roles[$log->user_id] ?? ''] ?? 'r-emp';
                    $key  = $log->subject_type ? $log->subject_type . '#' . $log->subject_id : null;
                    $subj = $key ? ($subjects[$key] ?? null) : null;
                @endphp
                @if($view === 'day' && $d !== $day)
                    @if($day && ($shown[$day] ?? 0) < ($dayTotals[$day] ?? 0))
                        <div class="lg-more"><a class="sx-link" href="{{ $periodLink(['from' => $day, 'to' => $day]) }}">Show all {{ $dayTotals[$day] }} from {{ \Illuminate\Support\Carbon::parse($day)->format('M j') }} <i data-lucide="chevron-down"></i></a></div>
                    @endif
                    @php $day = $d; @endphp
                    <div class="lg-day"><span class="sx-label">{{ $dayLabel($d) }}</span><span class="n">{{ $dayTotals[$d] ?? $shown[$d] }} {{ Str::plural('entry', $dayTotals[$d] ?? $shown[$d]) }}</span></div>
                @endif
                <div class="lg-e {{ $open === $log->id ? 'open' : '' }}" data-entry>
                    <div class="lg-time">@if($view === 'flat')<small>{{ $log->created_at->format('M j') }}</small>@endif{{ $log->created_at->format('g:i') }}<small>{{ $log->created_at->format('A') }}</small></div>
                    <div class="lg-node {{ $tone }}"><i data-lucide="{{ $nodeIcon($log->action) }}"></i></div>
                    <div style="min-width:0">
                        <div class="lg-line">
                            <span class="av sm {{ $cls }}">{{ $initials($who) }}</span><b>{{ $who }}</b>
                            <span class="lg-verb {{ $tone }}">{{ Str::ucfirst($log->action) }}</span>
                            <span class="sx-tag">{{ $log->module }}</span>
                        </div>
                        <div class="lg-desc">{{ $log->description ?: '—' }}</div>
                        <div class="lg-meta">
                            <span>{{ $log->ip_address ?: 'no IP' }}</span>
                            @if($subj)
                                @if($subj['url'])
                                    <a class="subj" href="{{ $subj['url'] }}"><i data-lucide="link-2"></i>{{ $subj['label'] }}</a>
                                @else
                                    <span>{{ $subj['label'] }}{{ $subj['gone'] ? ' · no longer exists' : '' }}</span>
                                @endif
                            @endif
                        </div>
                        <div class="lg-detail">
                            <div class="lg-dgrid">
                                <div><span class="sx-label">Recorded</span><div class="v mono">{{ $log->created_at->format('M j, Y · g:i:s A') }}</div></div>
                                <div><span class="sx-label">Entry</span><div class="v mono">#{{ number_format($log->id) }}</div></div>
                                <div><span class="sx-label">IP address</span><div class="v mono">{{ $log->ip_address ?: '—' }}</div></div>
                                <div><span class="sx-label">Device</span><div class="v">{{ $log->device }}</div></div>
                            </div>
                            <div class="lg-subj">
                                <span class="sx-label">Subject</span>
                                @if($subj)
                                    <b>{{ $subj['label'] }}{{ $subj['gone'] ? ' · no longer exists' : '' }}</b>
                                    @if($subj['url'])<a class="sx-link" href="{{ $subj['url'] }}">Open <i data-lucide="arrow-up-right"></i></a>@endif
                                @else
                                    <span style="color:var(--text-muted)">None recorded</span>
                                @endif
                                <span class="sp">
                                    <a class="sx-btn sm" href="{{ $here(['person' => [$who]]) }}">All by {{ Str::limit($who, 24) }}</a>
                                    @if($log->subject_type)
                                        <a class="sx-btn sm" href="{{ $here(['subject_type' => $log->subject_type, 'subject_id' => $log->subject_id, 'range' => 'all']) }}">All about this record</a>
                                    @endif
                                </span>
                            </div>
                            @if($log->user_agent)<div class="lg-ua">{{ $log->user_agent }}</div>@endif
                            <div class="lg-seal"><i data-lucide="lock"></i>Sealed. No one can edit or delete an entry — administrators included.</div>
                        </div>
                    </div>
                    <div class="lg-chev"><i data-lucide="chevron-right"></i></div>
                </div>
            @empty
                <div class="sx-empty"><i data-lucide="scroll-text"></i>Nothing matches these filters in {{ $periodLabel }}.</div>
            @endforelse
            @if($view === 'day' && $day && ($shown[$day] ?? 0) < ($dayTotals[$day] ?? 0))
                <div class="lg-more"><a class="sx-link" href="{{ $periodLink(['from' => $day, 'to' => $day]) }}">Show all {{ $dayTotals[$day] }} from {{ \Illuminate\Support\Carbon::parse($day)->format('M j') }} <i data-lucide="chevron-down"></i></a></div>
            @endif

            <div class="lg-load">
                @if($logs->onFirstPage() === false)
                    <a class="sx-btn sm" href="{{ $logs->previousPageUrl() }}"><i data-lucide="arrow-up"></i> Newer entries</a>
                @endif
                @if($logs->hasMorePages())
                    <a class="sx-btn sm" href="{{ $logs->nextPageUrl() }}"><i data-lucide="history"></i> Load older entries</a>
                @elseif($range !== 'all')
                    <a class="sx-btn sm" href="{{ $periodLink(['range' => 'all']) }}"><i data-lucide="history"></i> Look further back</a>
                @endif
                <span class="mono">{{ number_format($grandTotal) }} {{ Str::plural('entry', $grandTotal) }} on record @if($firstDate) since {{ \Illuminate\Support\Carbon::parse($firstDate)->format('M j, Y') }}@endif</span>
            </div>
        </section>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    // Open or close an entry; links inside it keep working.
    document.addEventListener('click', e => {
        const entry = e.target.closest('[data-entry]');
        if (!entry || e.target.closest('a, button, .lg-detail')) return;
        entry.classList.toggle('open');
    });

    const toggle = document.querySelector('[data-custom-toggle]');
    if (toggle) toggle.addEventListener('click', () => document.getElementById('au-custom').classList.toggle('show'));

    const bars  = [...document.querySelectorAll('#au-bars a')];
    const plot  = document.getElementById('au-plot');
    const brush = document.getElementById('au-brush');
    const tip   = document.getElementById('au-tip');
    const xAxis = document.getElementById('au-x');
    if (!bars.length) return;

    const center = b => b.offsetLeft + b.offsetWidth / 2;

    const short = b => new Date(b.dataset.date + 'T00:00:00').toLocaleDateString('en-US', { month: 'short', day: 'numeric' });

    // Mondays, and today — a Monday right beside today would print over it.
    bars.forEach((b, i) => {
        const last = i === bars.length - 1;
        const monday = new Date(b.dataset.date + 'T00:00:00').getDay() === 1 && i < bars.length - 3;
        if (monday || last) {
            const s = document.createElement('span');
            s.textContent = last ? 'Today' : short(b);
            s.style.left = center(b) + 'px';
            xAxis.appendChild(s);
        }
    });

    // Tooltip.
    bars.forEach(b => {
        b.addEventListener('mouseenter', () => {
            tip.innerHTML = '<b>' + b.dataset.label + ' · ' + b.dataset.n + ' ' + (b.dataset.n === '1' ? 'entry' : 'entries') + '</b>Click to open that day';
            tip.hidden = false;
            tip.style.left = center(b) + 'px';
            tip.style.bottom = (b.querySelector('i').offsetHeight + 12) + 'px';
        });
        b.addEventListener('mouseleave', () => { tip.hidden = true; });
    });

    // The brush covers the period, where it falls inside these thirty days.
    const inRange = bars.map((b, i) => b.classList.contains('in') ? i : -1).filter(i => i >= 0);
    if (!inRange.length) return;
    let lo = inRange[0], hi = inRange[inRange.length - 1];

    // The label names the days and counts what they hold, live while dragging.
    const label = brush.querySelector('.au-brush-l');
    function draw() {
        const l = bars[lo].offsetLeft - 4, r = bars[hi].offsetLeft + bars[hi].offsetWidth + 4;
        brush.style.left = l + 'px';
        brush.style.width = (r - l) + 'px';
        brush.hidden = false;
        const total = bars.slice(lo, hi + 1).reduce((sum, b) => sum + (+b.dataset.n || 0), 0);
        label.textContent = (lo === hi ? short(bars[lo]) : short(bars[lo]) + ' – ' + short(bars[hi])) + ' · ' + total.toLocaleString('en-US');
    }
    draw();

    // Drag a handle to a new edge; letting go opens that range.
    let edge = null;
    brush.querySelectorAll('.h').forEach(h => h.addEventListener('pointerdown', e => {
        edge = h.dataset.edge;
        h.setPointerCapture(e.pointerId);
        e.preventDefault();
    }));
    document.addEventListener('pointermove', e => {
        if (!edge) return;
        const x = e.clientX - plot.getBoundingClientRect().left;
        let nearest = 0, best = Infinity;
        bars.forEach((b, i) => { const dist = Math.abs(center(b) - x); if (dist < best) { best = dist; nearest = i; } });
        if (edge === 'l') lo = Math.min(nearest, hi); else hi = Math.max(nearest, lo);
        draw();
    });
    document.addEventListener('pointerup', () => {
        if (!edge) return;
        edge = null;
        const url = new URL(window.location.href);
        ['range', 'page', 'entry'].forEach(k => url.searchParams.delete(k));
        url.searchParams.set('from', bars[lo].dataset.date);
        url.searchParams.set('to', bars[hi].dataset.date);
        window.location.href = url.toString();
    });
})();
</script>
@endpush
