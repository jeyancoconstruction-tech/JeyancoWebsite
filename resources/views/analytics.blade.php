@extends('layouts')
@section('page_title', 'Analytics')

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@2.47.0/tabler-icons.min.css">
<style>
/* Analytics & Insights. Every name is prefixed: the reference's own class
   names (.card, .grid, .tag, .legend…) are ones the app's global styles
   already claim. Light is the base palette; dark is the reference's. */
.ana {
    --ana-panel: #FFFFFF;  --ana-panel-2: #F8F9FB;
    --ana-line: #E4E7EC;   --ana-line-2: #D0D5DD;  --ana-bw: 1px;
    --ana-txt: #101828;    --ana-txt-2: #475467;   --ana-txt-3: #667085;
    --ana-grid: rgba(16, 24, 40, .06);
    --ana-accent: #3B82F6; --ana-accent-soft: rgba(59, 130, 246, .12);
    --ana-green: #22C55E;  --ana-green-soft: rgba(34, 197, 94, .12);
    --ana-amber: #F59E0B;  --ana-amber-soft: rgba(245, 158, 11, .14);
    --ana-red: #EF4444;    --ana-red-soft: rgba(239, 68, 68, .12);
    --ana-violet: #8B5CF6; --ana-violet-soft: rgba(139, 92, 246, .12);
    --ana-ico-blue: #2563EB; --ana-ico-green: #16A34A; --ana-ico-red: #DC2626;
    --ana-ico-amber: #D97706; --ana-ico-violet: #7C3AED;
    --ana-radius: 12px;

    max-width: 1400px; margin: 0 auto;
    color: var(--ana-txt);
    font-family: 'Inter', system-ui, -apple-system, sans-serif;
    font-size: 14px; line-height: 1.5; -webkit-font-smoothing: antialiased;
}
html[data-bs-theme="dark"] .ana {
    --ana-panel: #111827;  --ana-panel-2: #0E1524;
    --ana-line: rgba(255, 255, 255, .07); --ana-line-2: rgba(255, 255, 255, .12); --ana-bw: .5px;
    --ana-txt: #E7ECF3;    --ana-txt-2: #9CA9BD;   --ana-txt-3: #64748B;
    --ana-grid: rgba(255, 255, 255, .06);
    --ana-accent-soft: rgba(59, 130, 246, .14); --ana-green-soft: rgba(34, 197, 94, .14);
    --ana-amber-soft: rgba(245, 158, 11, .14);  --ana-red-soft: rgba(239, 68, 68, .14);
    --ana-violet-soft: rgba(139, 92, 246, .14);
    --ana-ico-blue: #60A5FA; --ana-ico-green: #4ADE80; --ana-ico-red: #F87171;
    --ana-ico-amber: #FBBF24; --ana-ico-violet: #A78BFA;
}
.ana *, .ana *::before, .ana *::after { box-sizing: border-box; }
.ana h1, .ana h3, .ana p { margin: 0; }

/* Header */
.ana-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 20px; flex-wrap: wrap; }
.ana .ana-head h1 { font-size: 22px !important; font-weight: 600 !important; letter-spacing: -.01em !important; line-height: 1.3; color: var(--ana-txt); }
.ana-sub { font-size: 13px; color: var(--ana-txt-2); margin-top: 3px; }
.ana-sub b { color: var(--ana-txt); font-weight: 500; }
.ana-period { display: inline-flex; align-items: center; gap: 7px; background: var(--ana-panel); border: var(--ana-bw) solid var(--ana-line-2); border-radius: 20px; padding: 7px 14px; font-size: 13px; color: var(--ana-txt-2); }

/* Filters */
.ana-filters { display: flex; gap: 10px; flex-wrap: wrap; align-items: flex-end; background: var(--ana-panel); border: var(--ana-bw) solid var(--ana-line); border-radius: var(--ana-radius); padding: 14px; margin-bottom: 18px; }
.ana-fgroup { display: flex; flex-direction: column; gap: 5px; }
.ana-fgroup label { font-size: 10.5px; font-weight: 600; color: var(--ana-txt-3); text-transform: uppercase; letter-spacing: .6px; margin: 0; }
.ana-fctrl { position: relative; }
.ana-fctrl select { appearance: none; -webkit-appearance: none; background: var(--ana-panel-2); border: var(--ana-bw) solid var(--ana-line-2); border-radius: 8px; color: var(--ana-txt); font-size: 13px; padding: 9px 32px 9px 12px; height: 38px; min-width: 150px; cursor: pointer; font-family: inherit; transition: border-color .15s; }
.ana-fctrl select:hover { border-color: var(--ana-accent); }
.ana-fctrl select:focus { outline: none; border-color: var(--ana-accent); box-shadow: 0 0 0 3px var(--ana-accent-soft); }
.ana-fctrl select option { background: var(--ana-panel); color: var(--ana-txt); }
.ana-chev { position: absolute; right: 11px; top: 50%; transform: translateY(-50%); color: var(--ana-txt-3); pointer-events: none; font-size: 16px; }
.ana-fspacer { flex: 1; min-width: 0; }
.ana-fbtn { height: 38px; display: inline-flex; align-items: center; gap: 6px; background: var(--ana-accent); border: none; border-radius: 8px; color: #fff; font-size: 13px; font-weight: 500; padding: 0 16px; cursor: pointer; transition: filter .15s; font-family: inherit; text-decoration: none; }
.ana-fbtn:hover { filter: brightness(1.08); color: #fff; }
.ana-fbtn.ghost { background: transparent; border: var(--ana-bw) solid var(--ana-line-2); color: var(--ana-txt-2); }
.ana-fbtn.ghost:hover { background: var(--ana-panel-2); color: var(--ana-txt); filter: none; }

/* Summary cards */
.ana-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(155px, 1fr)); gap: 12px; margin-bottom: 18px; }
.ana-card { background: var(--ana-panel); border: var(--ana-bw) solid var(--ana-line); border-radius: var(--ana-radius); padding: 15px; position: relative; overflow: hidden; transition: border-color .2s, transform .2s; }
.ana-card:hover { border-color: var(--ana-line-2); transform: translateY(-2px); }
.ana-ico { width: 36px; height: 36px; border-radius: 9px; display: flex; align-items: center; justify-content: center; font-size: 19px; margin-bottom: 11px; }
.ana-lbl { font-size: 11px; font-weight: 600; color: var(--ana-txt-3); text-transform: uppercase; letter-spacing: .5px; }
.ana-val { font-size: 26px; font-weight: 600; letter-spacing: -.02em; margin-top: 5px; line-height: 1; color: var(--ana-txt); }
.ana-unit { font-size: 14px; }
.ana-meta { font-size: 11.5px; color: var(--ana-txt-2); margin-top: 6px; display: flex; align-items: center; gap: 4px; }
.ana-i-blue   { background: var(--ana-accent-soft); color: var(--ana-ico-blue); }
.ana-i-green  { background: var(--ana-green-soft);  color: var(--ana-ico-green); }
.ana-i-red    { background: var(--ana-red-soft);    color: var(--ana-ico-red); }
.ana-i-amber  { background: var(--ana-amber-soft);  color: var(--ana-ico-amber); }
.ana-i-violet { background: var(--ana-violet-soft); color: var(--ana-ico-violet); }

/* Chart grid */
.ana-grid { display: grid; gap: 16px; margin-bottom: 16px; }
.ana-g2 { grid-template-columns: repeat(auto-fit, minmax(380px, 1fr)); }
.ana-chart-card { background: var(--ana-panel); border: var(--ana-bw) solid var(--ana-line); border-radius: var(--ana-radius); padding: 18px; min-width: 0; }
.ana-chart-card.wide { grid-column: 1 / -1; }
.ana-ch-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; margin-bottom: 16px; }
.ana-ch-head h3 { font-size: 15px; font-weight: 600; letter-spacing: -.01em; color: var(--ana-txt); }
.ana-desc { font-size: 12px; color: var(--ana-txt-2); margin-top: 2px; }
.ana-tag { display: inline-flex; align-items: center; gap: 5px; background: var(--ana-panel-2); border: var(--ana-bw) solid var(--ana-line-2); border-radius: 20px; padding: 4px 11px; font-size: 11.5px; color: var(--ana-txt-2); white-space: nowrap; }
.ana-canvas { position: relative; height: 260px; transition: opacity .2s; }
.ana-canvas.tall { height: 300px; }
.ana-legend { display: flex; gap: 16px; flex-wrap: wrap; margin-top: 14px; justify-content: center; }
.ana-legend span { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; color: var(--ana-txt-2); }
.ana-legend i { width: 9px; height: 9px; border-radius: 50%; display: inline-block; }
.ana-empty { position: absolute; inset: 0; display: none; align-items: center; justify-content: center; flex-direction: column; gap: 8px; color: var(--ana-txt-3); font-size: 13px; }
.ana-empty i { font-size: 28px; opacity: .5; }

/* A filter change in flight */
.ana.is-loading .ana-canvas, .ana.is-loading .ana-val { opacity: .55; }

@media (max-width: 640px) {
    .ana-fctrl select { min-width: 130px; }
    .ana-cards { grid-template-columns: repeat(2, 1fr); }
    .ana-g2 { grid-template-columns: 1fr; }
}

/* ── Entrance ────────────────────────────────────────────────────────────
   The page rises into place once, on arrival: the header, then the filters,
   then each card and each chart a beat after the one before. Only opacity
   and transform move, so the browser composites it without laying anything
   out again, and the last item has settled in under a second.

   `backwards` rather than `forwards`: the start frame holds through each
   delay, but once an item has arrived its own rules style it again. A
   `forwards` fill would pin the final transform and kill the cards' hover
   lift for good. Each item carries its place in line as --ana-i; the group
   around it sets where that line starts and how far apart its items are. */
.ana       { --ana-dur: 420ms; --ana-ease: cubic-bezier(.22, 1, .36, 1); --ana-base: 0ms; --ana-step: 60ms; }
.ana-cards { --ana-base: 90ms;  --ana-step: 30ms; }
.ana-grid  { --ana-base: 220ms; --ana-step: 50ms; }
@keyframes ana-enter { from { opacity: 0; transform: translate3d(0, 12px, 0); } }
.ana-enter {
    animation: ana-enter var(--ana-dur) var(--ana-ease) backwards;
    animation-delay: calc(var(--ana-base) + var(--ana-i, 0) * var(--ana-step));
}
@media (prefers-reduced-motion: reduce) { .ana-enter { animation: none; } .ana-card:hover { transform: none; } }
</style>
@endpush

@section('content')
@php
    $c = $analytics['cards'];
    $m = $analytics['meta'];
    $p = $analytics['period'];

    // The formats the page's script counts up to, so the first paint and the
    // animated one read the same.
    $avg = fn ($v) => rtrim(rtrim(number_format((float) $v, 1, '.', ''), '0'), '.');

    // Shift colours in the order the chart assigns them.
    $shiftColours = ['--ana-accent', '--ana-violet', '--ana-amber', '--ana-green', '--ana-red'];
    $shiftList    = $shifts->pluck('name');
@endphp
<div class="ana" id="anaRoot" data-endpoint="{{ route('analytics.data') }}">

    <div class="ana-head ana-enter" style="--ana-i:0">
        <div>
            <h1>Analytics &amp; insights</h1>
            <div class="ana-sub">Performance overview for <b id="anaPeriodLabel">{{ $p['label'] }}{{ $p['scope'] !== '' ? ' — ' . $p['scope'] : '' }}</b></div>
        </div>
        <div class="ana-period" id="anaLive" title="Updated {{ $analytics['updated_at'] }}"><i class="ti ti-calendar"></i><span>Live data</span></div>
    </div>

    {{-- A real form, so the filters still work with scripting off; with it on,
         every change redraws in place. --}}
    <form class="ana-filters ana-enter" id="anaFilters" method="GET" action="{{ route('analytics') }}" style="--ana-i:1">
        <div class="ana-fgroup">
            <label for="anaRange">Date range</label>
            <div class="ana-fctrl">
                <select id="anaRange" name="range">
                    @foreach($ranges as $value => $label)
                        <option value="{{ $value }}" @selected($filters['range'] === (string) $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <i class="ti ti-chevron-down ana-chev"></i>
            </div>
        </div>
        <div class="ana-fgroup">
            <label for="anaSite">Site</label>
            <div class="ana-fctrl">
                <select id="anaSite" name="site">
                    <option value="all">All sites</option>
                    @foreach($sites as $s)
                        <option value="{{ $s->id }}" @selected($filters['site'] === $s->id)>{{ $s->name }}</option>
                    @endforeach
                </select>
                <i class="ti ti-chevron-down ana-chev"></i>
            </div>
        </div>
        <div class="ana-fgroup">
            <label for="anaShift">Shift</label>
            <div class="ana-fctrl">
                <select id="anaShift" name="shift">
                    <option value="all">All shifts</option>
                    @foreach($shifts as $s)
                        <option value="{{ $s->id }}" @selected($filters['shift'] === $s->id)>{{ $s->name }}</option>
                    @endforeach
                </select>
                <i class="ti ti-chevron-down ana-chev"></i>
            </div>
        </div>
        <div class="ana-fgroup">
            <label for="anaStatus">Employee status</label>
            <div class="ana-fctrl">
                <select id="anaStatus" name="status">
                    @foreach($statuses as $value => $label)
                        <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <i class="ti ti-chevron-down ana-chev"></i>
            </div>
        </div>
        <div class="ana-fspacer"></div>
        <a class="ana-fbtn ghost" id="anaReset" href="{{ route('analytics') }}"><i class="ti ti-refresh"></i>Reset</a>
        <button class="ana-fbtn" type="submit"><i class="ti ti-adjustments"></i>Apply filters</button>
    </form>

    <div class="ana-cards">
        <div class="ana-card ana-enter" style="--ana-i:0"><div class="ana-ico ana-i-blue"><i class="ti ti-users"></i></div><div class="ana-lbl">Total employees</div><div class="ana-val" data-v="totalEmp">{{ $c['totalEmp'] }}</div><div class="ana-meta" data-m="totalEmp">{{ $m['totalEmp'] }}</div></div>
        <div class="ana-card ana-enter" style="--ana-i:1"><div class="ana-ico ana-i-green"><i class="ti ti-user-check"></i></div><div class="ana-lbl">Present</div><div class="ana-val" data-v="present">{{ $avg($c['present']) }}</div><div class="ana-meta" data-m="present">{{ $m['present'] }}</div></div>
        <div class="ana-card ana-enter" style="--ana-i:2"><div class="ana-ico ana-i-red"><i class="ti ti-user-x"></i></div><div class="ana-lbl">Absent</div><div class="ana-val" data-v="absent">{{ $avg($c['absent']) }}</div><div class="ana-meta" data-m="absent">{{ $m['absent'] }}</div></div>
        <div class="ana-card ana-enter" style="--ana-i:3"><div class="ana-ico ana-i-amber"><i class="ti ti-clock-exclamation"></i></div><div class="ana-lbl">Late</div><div class="ana-val" data-v="late">{{ $c['late'] }}</div><div class="ana-meta" data-m="late">{{ $m['late'] }}</div></div>
        <div class="ana-card ana-enter" style="--ana-i:4"><div class="ana-ico ana-i-violet"><i class="ti ti-clock-plus"></i></div><div class="ana-lbl">Overtime</div><div class="ana-val" data-v="ot">{{ number_format($c['ot'], 1) }}<span class="ana-unit">h</span></div><div class="ana-meta" data-m="ot">{{ $m['ot'] }}</div></div>
        <div class="ana-card ana-enter" style="--ana-i:5"><div class="ana-ico ana-i-blue"><i class="ti ti-briefcase"></i></div><div class="ana-lbl">Hours worked</div><div class="ana-val" data-v="hours">{{ number_format($c['hours']) }}<span class="ana-unit">h</span></div><div class="ana-meta" data-m="hours">{{ $m['hours'] }}</div></div>
        <div class="ana-card ana-enter" style="--ana-i:6"><div class="ana-ico ana-i-green"><i class="ti ti-cash"></i></div><div class="ana-lbl">Payroll cost</div><div class="ana-val" data-v="payroll">₱{{ number_format($c['payroll']) }}</div><div class="ana-meta" data-m="payroll">{{ $m['payroll'] }}</div></div>
    </div>

    <div class="ana-grid">
        <div class="ana-chart-card wide ana-enter" style="--ana-i:0">
            <div class="ana-ch-head">
                <div><h3>Attendance trends</h3><div class="ana-desc">Daily employee presence over the selected period</div></div>
                <span class="ana-tag"><i class="ti ti-chart-line"></i><span id="anaTrendTag">{{ $p['tag'] }}</span></span>
            </div>
            <div class="ana-canvas tall"><canvas id="anaTrend" role="img" aria-label="Employees present per day"></canvas><div class="ana-empty" data-empty><i class="ti ti-mood-empty"></i>No data for these filters</div></div>
        </div>
    </div>

    <div class="ana-grid ana-g2">
        <div class="ana-chart-card ana-enter" style="--ana-i:1">
            <div class="ana-ch-head"><div><h3>Late / absent trends</h3><div class="ana-desc">Punctuality issues over time</div></div></div>
            <div class="ana-canvas"><canvas id="anaLateAbsent" role="img" aria-label="Late starts and absences per day"></canvas><div class="ana-empty" data-empty><i class="ti ti-mood-empty"></i>No data</div></div>
            <div class="ana-legend"><span><i style="background:var(--ana-amber)"></i>Late</span><span><i style="background:var(--ana-red)"></i>Absent</span></div>
        </div>
        <div class="ana-chart-card ana-enter" style="--ana-i:2">
            <div class="ana-ch-head"><div><h3>Hours worked by shift</h3><div class="ana-desc">{{ $shiftList->count() > 1 ? $shiftList->implode(' vs ') . ' distribution' : 'Distribution by shift' }}</div></div></div>
            <div class="ana-canvas"><canvas id="anaShiftHours" role="img" aria-label="Hours worked per day, by shift"></canvas><div class="ana-empty" data-empty><i class="ti ti-mood-empty"></i>No data</div></div>
            <div class="ana-legend" id="anaShiftLegend">
                @foreach($analytics['shiftHours']['datasets'] as $i => $set)
                    <span><i style="background:var({{ $shiftColours[$i % count($shiftColours)] }})"></i>{{ $set['label'] }}</span>
                @endforeach
            </div>
        </div>
    </div>

    <div class="ana-grid ana-g2">
        <div class="ana-chart-card ana-enter" style="--ana-i:3">
            <div class="ana-ch-head"><div><h3>Site performance</h3><div class="ana-desc">Attendance rate by site</div></div></div>
            <div class="ana-canvas"><canvas id="anaSites" role="img" aria-label="Attendance rate by site"></canvas><div class="ana-empty" data-empty><i class="ti ti-mood-empty"></i>No data</div></div>
        </div>
        <div class="ana-chart-card ana-enter" style="--ana-i:4">
            <div class="ana-ch-head"><div><h3>Payroll summary</h3><div class="ana-desc">Gross vs net — weekly</div></div><span class="ana-tag"><i class="ti ti-currency-peso"></i>Pesos</span></div>
            <div class="ana-canvas"><canvas id="anaPayroll" role="img" aria-label="Gross and net pay by pay week"></canvas><div class="ana-empty" data-empty><i class="ti ti-mood-empty"></i>No data</div></div>
            <div class="ana-legend"><span><i style="background:var(--ana-accent)"></i>Gross pay</span><span><i style="background:var(--ana-green)"></i>Net pay</span></div>
        </div>
    </div>

</div>

<script type="application/json" id="anaData">@json($analytics)</script>
@endsection

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
(function () {
    const root = document.getElementById('anaRoot');
    if (!root || typeof Chart === 'undefined') return;

    const form     = document.getElementById('anaFilters');
    const fields   = ['range', 'site', 'shift', 'status'].map(name => form.elements[name]);
    const defaults = { range: '30', site: 'all', shift: 'all', status: 'all' };
    const charts   = {};
    let data       = JSON.parse(document.getElementById('anaData').textContent);
    let colors     = palette();
    let paintedAt  = 0;

    // Motion is for arrival only: the first paint times its count-ups and
    // chart draws to each item's entrance, and every later paint starts at
    // once. Somebody who has asked for less motion gets none of it.
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    let entering       = true;

    // ── Theme ────────────────────────────────────────────────────────────
    // A canvas inherits nothing, so every colour is read off the page's own
    // custom properties — and read again when the theme is switched.
    function css(name) { return getComputedStyle(root).getPropertyValue(name).trim(); }
    function palette() {
        return {
            accent: css('--ana-accent'), green: css('--ana-green'), amber: css('--ana-amber'),
            red: css('--ana-red'), violet: css('--ana-violet'),
            txt: css('--ana-txt'), txt2: css('--ana-txt-2'), grid: css('--ana-grid'),
            tipBg: css('--ana-panel-2'), tipLine: css('--ana-line-2'),
        };
    }
    function applyDefaults() {
        Chart.defaults.color = colors.txt2;
        Chart.defaults.font.family = "'Inter', system-ui, -apple-system, sans-serif";
        Chart.defaults.font.size = 11;
    }
    applyDefaults();

    const shiftColor = i => [colors.accent, colors.violet, colors.amber, colors.green, colors.red][i % 5];

    // ── Cards ────────────────────────────────────────────────────────────
    const trim1 = v => { const s = (Math.round(v * 10) / 10).toFixed(1); return s.endsWith('.0') ? s.slice(0, -2) : s; };
    const FORMAT = {
        totalEmp: v => Math.round(v).toString(),
        present:  trim1,
        absent:   trim1,
        late:     v => Math.round(v).toString(),
        ot:       v => v.toLocaleString('en-US', { minimumFractionDigits: 1, maximumFractionDigits: 1 }),
        hours:    v => Math.round(v).toLocaleString('en-US'),
        payroll:  v => '₱' + Math.round(v).toLocaleString('en-US'),
    };

    // How long an item waits before its entrance, as the stylesheet set it.
    function entryDelay(el) {
        const item = el && el.closest('.ana-enter');
        if (!entering || reduceMotion || !item) return 0;
        return (parseFloat(getComputedStyle(item).animationDelay) || 0) * 1000;
    }

    // Count a card from what it shows now to its new value. A newer count
    // takes over from one still running rather than fighting it for the text.
    function animateVal(el, to, fmt, wait) {
        const run  = (el._anaRun = (el._anaRun || 0) + 1);
        const from = el._anaNow ?? parseFloat(el.getAttribute('data-cur') || '0');
        const dur  = 700;
        let start  = null;

        const show = v => { el._anaNow = v; el.childNodes[0].nodeValue = fmt(v); };

        if (reduceMotion) { el.setAttribute('data-cur', to); show(to); return; }

        function tick(now) {
            if (el._anaRun !== run) return;
            if (start === null) start = now;
            const t = Math.min(1, (now - start) / dur);
            show(from + (to - from) * (1 - Math.pow(1 - t, 3)));
            if (t < 1) requestAnimationFrame(tick);
            else { el.setAttribute('data-cur', to); show(to); }
        }

        if (wait > 0) setTimeout(() => requestAnimationFrame(tick), wait);
        else requestAnimationFrame(tick);
    }

    function setCards(a) {
        root.querySelectorAll('[data-v]').forEach(el => {
            const k = el.getAttribute('data-v');
            if (k in a.cards) animateVal(el, Number(a.cards[k]) || 0, FORMAT[k], entryDelay(el));
        });
        root.querySelectorAll('[data-m]').forEach(el => {
            const k = el.getAttribute('data-m');
            if (k in a.meta) el.textContent = a.meta[k];
        });
    }

    // ── Charts ───────────────────────────────────────────────────────────
    // A chart's first draw waits for its card to arrive, so a line is not
    // half drawn by the time anybody can see it. Every later draw starts at
    // once — the delay is asked afresh each time, and arrival is over by then.
    function motion(id) {
        if (reduceMotion) return false;
        const wait = entryDelay(document.getElementById(id));
        return { duration: 800, easing: 'easeOutQuart', delay: () => (entering ? wait : 0) };
    }
    const gridCfg = () => ({ color: colors.grid, drawBorder: false });
    const baseScales = x => ({
        x: { grid: { display: false }, ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: x || 10 } },
        y: { grid: gridCfg(), beginAtZero: true, ticks: { precision: 0 } },
    });
    const peso = v => Math.abs(v) >= 1000
        ? '₱' + (v / 1000).toFixed(v % 1000 === 0 ? 0 : 1) + 'k'
        : '₱' + v;

    function mkGradient(ctx, color) {
        const g = ctx.createLinearGradient(0, 0, 0, 260);
        g.addColorStop(0, color + '55');
        g.addColorStop(1, color + '02');
        return g;
    }
    function toggleEmpty(id, isEmpty) {
        const canvas = document.getElementById(id);
        canvas.parentElement.querySelector('[data-empty]').style.display = isEmpty ? 'flex' : 'none';
        canvas.style.opacity = isEmpty ? '.15' : '1';
    }
    function tt(unit) {
        return {
            backgroundColor: colors.tipBg, borderColor: colors.tipLine, borderWidth: 1,
            titleColor: colors.txt, bodyColor: colors.txt2, padding: 10, cornerRadius: 8,
            displayColors: true, boxPadding: 4,
            callbacks: {
                label: c => {
                    let v = c.chart.options.indexAxis === 'y' ? c.parsed.x : c.parsed.y;
                    if (unit === '₱') v = '₱' + Math.round(v).toLocaleString('en-US');
                    else if (unit === '%') v = Math.round(v) + '%';
                    else if (unit === 'h') v = (+v).toFixed(1) + 'h';
                    else v = Math.round(v);
                    return ' ' + c.dataset.label + ': ' + v;
                },
            },
        };
    }
    function upsert(key, id, config) {
        if (charts[key]) { charts[key].data = config.data; charts[key].update(); return; }
        config.options.animation = motion(id);
        charts[key] = new Chart(document.getElementById(id), config);
    }

    function renderAll(a) {
        const t = a.trend;

        // Attendance trend (area line)
        toggleEmpty('anaTrend', t.present.every(v => !v) && t.absent.every(v => !v));
        const ctxT = document.getElementById('anaTrend').getContext('2d');
        upsert('t', 'anaTrend', {
            type: 'line',
            data: { labels: t.labels, datasets: [{
                label: 'Present', data: t.present, borderColor: colors.accent,
                backgroundColor: mkGradient(ctxT, colors.accent), borderWidth: 2, fill: true, tension: .4,
                pointRadius: 0, pointHoverRadius: 5, pointHoverBackgroundColor: colors.accent,
                pointHoverBorderColor: '#fff', pointHoverBorderWidth: 2,
            }] },
            options: { responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false }, tooltip: tt() }, scales: baseScales(12),
                interaction: { mode: 'index', intersect: false } },
        });

        // Late / absent (grouped bars)
        toggleEmpty('anaLateAbsent', t.late.every(v => !v) && t.absent.every(v => !v));
        upsert('la', 'anaLateAbsent', {
            type: 'bar',
            data: { labels: t.labels, datasets: [
                { label: 'Late', data: t.late, backgroundColor: colors.amber, borderRadius: 3, barPercentage: .7, categoryPercentage: .6 },
                { label: 'Absent', data: t.absent, backgroundColor: colors.red, borderRadius: 3, barPercentage: .7, categoryPercentage: .6 },
            ] },
            options: { responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false }, tooltip: tt() }, scales: baseScales(8) },
        });

        // Hours by shift (stacked bars), one series per shift on file
        const sh = a.shiftHours;
        toggleEmpty('anaShiftHours', sh.datasets.every(d => d.data.every(v => !v)));
        upsert('hs', 'anaShiftHours', {
            type: 'bar',
            data: { labels: sh.labels, datasets: sh.datasets.map((d, i) => ({
                label: d.label, data: d.data, backgroundColor: shiftColor(i),
                borderRadius: { topLeft: 3, topRight: 3 }, stack: 'h',
            })) },
            options: { responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false }, tooltip: tt('h') },
                scales: {
                    x: { grid: { display: false }, stacked: true, ticks: { maxTicksLimit: 8, maxRotation: 0 } },
                    y: { grid: gridCfg(), stacked: true, beginAtZero: true },
                } },
        });
        document.getElementById('anaShiftLegend').replaceChildren(...sh.datasets.map((d, i) => {
            const item = document.createElement('span');
            const dot  = document.createElement('i');
            dot.style.background = shiftColor(i);
            item.append(dot, d.label);
            return item;
        }));

        // Site performance (horizontal bars, attendance rate)
        const rates = a.sites.map(s => s.rate);
        toggleEmpty('anaSites', !rates.length);
        upsert('sp', 'anaSites', {
            type: 'bar',
            data: { labels: a.sites.map(s => s.name), datasets: [{
                label: 'Attendance rate', data: rates,
                backgroundColor: rates.map(v => v >= 70 ? colors.green : v >= 40 ? colors.amber : colors.red),
                borderRadius: 5, maxBarThickness: 26, minBarLength: 3,
            }] },
            options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false }, tooltip: tt('%') },
                scales: {
                    x: { grid: gridCfg(), beginAtZero: true, max: 100, ticks: { callback: v => v + '%' } },
                    y: { grid: { display: false } },
                } },
        });

        // Payroll (gross vs net, one pair per pay week)
        const p = a.payroll;
        toggleEmpty('anaPayroll', p.gross.every(v => !v) && p.net.every(v => !v));
        upsert('pr', 'anaPayroll', {
            type: 'bar',
            data: { labels: p.labels, datasets: [
                { label: 'Gross', data: p.gross, backgroundColor: colors.accent, borderRadius: 4, barPercentage: .7, categoryPercentage: .6 },
                { label: 'Net', data: p.net, backgroundColor: colors.green, borderRadius: 4, barPercentage: .7, categoryPercentage: .6 },
            ] },
            options: { responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false }, tooltip: tt('₱') },
                scales: {
                    x: { grid: { display: false }, ticks: { maxRotation: 0 } },
                    y: { grid: gridCfg(), beginAtZero: true, ticks: { callback: peso } },
                } },
        });
    }

    function paint(a) {
        data = a;
        paintedAt = Date.now();
        setCards(a);
        renderAll(a);
        document.getElementById('anaTrendTag').textContent = a.period.tag;
        document.getElementById('anaPeriodLabel').textContent = a.period.label + (a.period.scope ? ' — ' + a.period.scope : '');
        document.getElementById('anaLive').title = 'Updated ' + a.updated_at;
    }

    // ── Filters ──────────────────────────────────────────────────────────
    // Each change asks the server for the same figures under the new filters
    // and redraws in place. The address follows, so a reload or a shared link
    // opens on the same view. A newer request cancels one still in flight.
    let inflight = null;
    function refresh() {
        const q = new URLSearchParams();
        fields.forEach(el => q.set(el.name, el.value));
        history.replaceState(null, '', location.pathname + '?' + q.toString());

        if (inflight) inflight.abort();
        const ctrl = inflight = new AbortController();
        root.classList.add('is-loading');

        fetch(root.dataset.endpoint + '?' + q.toString(), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            signal: ctrl.signal,
        })
            .then(res => { if (!res.ok) throw new Error('HTTP ' + res.status); return res.json(); })
            .then(paint)
            .catch(err => { if (err.name !== 'AbortError') console.warn('Analytics refresh failed:', err); })
            .finally(() => {
                if (inflight === ctrl) { inflight = null; root.classList.remove('is-loading'); }
            });
    }

    form.addEventListener('submit', e => { e.preventDefault(); refresh(); });
    fields.forEach(el => el.addEventListener('change', refresh));
    document.getElementById('anaReset').addEventListener('click', e => {
        e.preventDefault();
        fields.forEach(el => { el.value = defaults[el.name]; });
        refresh();
    });

    // Live: the same filters asked again every minute while the tab is in
    // view, and at once on coming back to a tab left open.
    setInterval(() => { if (!document.hidden && !inflight) refresh(); }, 60000);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden && Date.now() - paintedAt > 60000) refresh();
    });

    // Rebuild every chart in the new palette when the theme is switched.
    new MutationObserver(() => {
        colors = palette();
        applyDefaults();
        Object.keys(charts).forEach(k => { charts[k].destroy(); delete charts[k]; });
        renderAll(data);
    }).observe(document.documentElement, { attributes: true, attributeFilter: ['data-bs-theme'] });

    paint(data);
    entering = false;
})();
</script>
@endpush
