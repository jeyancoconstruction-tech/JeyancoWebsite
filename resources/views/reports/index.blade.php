@extends('layouts')

@section('page_title', 'Payroll Reports')

{{-- Payroll Reports, a sub-item of Payroll Records. Laid out after Michael's
     jeyanco-payroll-reports.html (2026-09-26): report tabs, the period, the
     totals, a chart and a sortable table. Every figure is PayrollService's,
     as Payroll Records computes it (see PayrollReportController); colours are
     the theme's tokens. --}}

@push('styles')
<style>
.rp { --rp-mono: 'JetBrains Mono', ui-monospace, Consolas, monospace; display: flex; flex-direction: column; gap: 14px; }
.rp { --rp-s1: #2a78d6; --rp-s2: #eb6834; --rp-s3: #1baf7a; --rp-s4: #eda100; --rp-s5: #7c3aed; --rp-s6: #db2777; --rp-s7: #64748b; }
html[data-bs-theme="dark"] .rp { --rp-s1: #3987e5; --rp-s2: #d95926; --rp-s3: #199e70; --rp-s4: #c98500; --rp-s5: #8b5cf6; --rp-s6: #ec4899; --rp-s7: #94a3b8; }
html[data-bs-theme] .main-content .rp > .page-head { margin-bottom: -2px !important; }

.rp-card { background: var(--surface); border: 1px solid var(--border); border-radius: 14px; box-shadow: var(--shadow-xs); }
.rp-btn {
    display: inline-flex; align-items: center; gap: 8px; height: 38px; padding: 0 14px; border-radius: 10px;
    border: 1px solid var(--border-md); background: var(--surface); color: var(--text-primary);
    font-size: 13px; font-weight: 600; white-space: nowrap; text-decoration: none; cursor: pointer;
}
.rp-btn svg { width: 16px; height: 16px; flex: none; }
.rp-btn:hover { border-color: var(--brand); color: var(--text-primary); }
.rp-btn.pri { background: var(--brand); border-color: var(--brand); color: #fff; }
.rp-btn.pri:hover { color: #fff; filter: brightness(1.06); }

/* Report tabs */
.rp-tabs { display: flex; gap: 6px; overflow-x: auto; padding: 4px; background: var(--surface); border: 1px solid var(--border); border-radius: 12px; scrollbar-width: none; }
.rp-tabs::-webkit-scrollbar { display: none; }
.rp-tab { display: flex; gap: 8px; align-items: center; color: var(--text-secondary); font-size: 13px; font-weight: 600; padding: 0 14px; height: 36px; border-radius: 9px; white-space: nowrap; text-decoration: none; }
.rp-tab svg { width: 16px; height: 16px; }
.rp-tab:hover { color: var(--text-primary); background: var(--bg-subtle); }
.rp-tab.on { background: var(--brand-subtle); color: var(--brand); }

/* Filters */
.rp-filters { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; padding: 10px 12px; }
.rp-chips { display: flex; gap: 4px; background: var(--bg-subtle); border: 1px solid var(--border); border-radius: 10px; padding: 3px; }
.rp-chips button { border: 0; background: none; color: var(--text-secondary); font-size: 12.5px; font-weight: 600; height: 30px; padding: 0 10px; border-radius: 7px; cursor: pointer; white-space: nowrap; }
.rp-chips button:hover { color: var(--text-primary); }
.rp-chips button.on { background: var(--surface); color: var(--text-primary); box-shadow: 0 1px 2px rgba(0,0,0,.12); }
.rp-sel { display: flex; align-items: center; gap: 8px; border: 1px solid var(--border-md); background: var(--surface); border-radius: 10px; padding: 0 10px; height: 38px; margin: 0; }
.rp-sel svg { width: 15px; height: 15px; color: var(--text-muted); flex: none; }
html[data-bs-theme] .rp .rp-sel input,
html[data-bs-theme] .rp .rp-sel select {
    border: 0 !important; background: transparent !important; box-shadow: none !important; outline: none;
    height: 34px; padding: 0; color: var(--text-primary); font-size: 13px; font-weight: 500; min-width: 0;
}
html[data-bs-theme] .rp .rp-sel input[type=date] { width: 114px; font-family: var(--rp-mono); font-size: 12.5px; }
html[data-bs-theme] .rp .rp-sel input.q { width: 140px; }
html[data-bs-theme] .rp .rp-sel select { width: 120px; }
.rp-sel .dash { color: var(--text-muted); }
.rp-filters .sp { flex: 1; }
.rp-lnk { color: var(--brand); font-size: 13px; font-weight: 600; padding: 0 6px; text-decoration: none; }
.rp-lnk:hover { text-decoration: underline; }

/* Totals */
.rp-kpis { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: 1px; background: var(--border); border: 1px solid var(--border); border-radius: 14px; overflow: hidden; box-shadow: var(--shadow-xs); }
.rp-kpi { background: var(--surface); padding: 11px 14px; display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.rp-kpi span { font-size: 10px; letter-spacing: .1em; text-transform: uppercase; font-weight: 700; color: var(--text-secondary); }
.rp-kpi b { font-size: 18px; font-weight: 700; font-variant-numeric: tabular-nums; color: var(--text-primary); white-space: nowrap; }
.rp-kpi b.net { color: var(--brand); }
.rp-kpi small { font-size: 11px; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.rp-kpi small .up { color: var(--success); font-weight: 700; }
.rp-kpi small .down { color: var(--danger); font-weight: 700; }

/* Chart */
.rp-chart { padding: 14px 16px; display: flex; flex-direction: column; gap: 12px; }
.rp-ch-h { display: flex; justify-content: space-between; align-items: baseline; gap: 10px; flex-wrap: wrap; }
.rp-ch-h h3 { margin: 0; font-size: 14px; font-weight: 700; color: var(--text-primary); }
.rp-ch-h small { color: var(--text-muted); font-size: 12px; }
.rp-legend { display: flex; gap: 12px; flex-wrap: wrap; font-size: 12px; color: var(--text-secondary); }
.rp-legend span { display: flex; gap: 6px; align-items: center; }
.rp-legend i { width: 10px; height: 10px; border-radius: 3px; }
.rp-hbars { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px 36px; }
@media (max-width: 1100px) { .rp-hbars { grid-template-columns: minmax(0, 1fr); } }
.rp-hb { display: grid; grid-template-columns: minmax(0, 130px) minmax(0, 1fr) auto; gap: 10px; align-items: center; font-size: 12.5px; }
.rp-hb .nm { color: var(--text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.rp-hb .tr { height: 14px; display: flex; gap: 2px; }
.rp-hb .tr i { display: block; height: 100%; min-width: 2px; }
.rp-hb .tr i:last-child { border-radius: 0 4px 4px 0; }
.rp-hb .v { font-variant-numeric: tabular-nums; font-weight: 600; color: var(--text-primary); min-width: 84px; text-align: right; white-space: nowrap; }
.rp-cols { display: flex; align-items: flex-end; gap: 10px; height: 150px; padding: 0 4px; border-bottom: 1px solid var(--border-md); }
.rp-col { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: flex-end; height: 100%; gap: 6px; min-width: 0; }
.rp-col i { display: block; width: min(42px, 70%); background: var(--rp-s1); border-radius: 4px 4px 0 0; }
.rp-col i.cur { box-shadow: inset 0 0 0 2px color-mix(in srgb, var(--rp-s1) 55%, #fff); }
.rp-col .cv { font-size: 11px; font-weight: 600; color: var(--text-primary); font-variant-numeric: tabular-nums; white-space: nowrap; }
.rp-col-x { display: flex; gap: 10px; padding: 0 4px; }
.rp-col-x span { flex: 1; text-align: center; font-size: 10.5px; color: var(--text-muted); font-family: var(--rp-mono); white-space: nowrap; overflow: hidden; }
.rp-empty { padding: 36px 16px; text-align: center; color: var(--text-muted); font-size: 13px; }
.rp-tip { position: fixed; z-index: 1200; pointer-events: none; background: var(--text-primary); color: var(--surface); font-size: 12px; font-weight: 600; padding: 6px 9px; border-radius: 7px; white-space: nowrap; transform: translate(-50%, calc(-100% - 10px)); box-shadow: 0 6px 18px rgba(0,0,0,.25); }

/* Table */
.rp-tcard { overflow: hidden; }
.rp-t-h { display: flex; align-items: center; gap: 10px; padding: 12px 16px; border-bottom: 1px solid var(--border); }
.rp-t-h h3 { margin: 0; font-size: 14px; font-weight: 700; color: var(--text-primary); }
.rp-t-h small { color: var(--text-muted); font-size: 12px; }
.rp-wrap { overflow: auto; }
.rp-table { width: 100%; border-collapse: separate; border-spacing: 0; min-width: 640px; }
.rp-table thead th { position: sticky; top: 0; z-index: 1; font-size: 10.5px; letter-spacing: .06em; text-transform: uppercase; color: var(--text-secondary); font-weight: 700; text-align: right; padding: 9px 10px; background: var(--bg-subtle); border-bottom: 1px solid var(--border); white-space: nowrap; }
.rp-table th.l, .rp-table td.l { text-align: left; }
.rp-table thead th button { all: unset; cursor: pointer; display: inline-flex; gap: 4px; align-items: center; }
.rp-table thead th button:focus-visible { outline: 2px solid var(--brand); outline-offset: 2px; }
.rp-table thead th svg { width: 10px; height: 10px; opacity: .45; }
.rp-table thead th.sorted svg { opacity: 1; color: var(--brand); }
.rp-table tbody td { padding: 10px 10px; border-bottom: 1px solid var(--border); text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; font-size: 13px; color: var(--text-primary); }
.rp-table tbody tr:hover td { background: var(--bg-subtle); }
.rp-table td.z { color: var(--text-muted); }
.rp-table td.ded { color: var(--danger); }
.rp-table td.net { font-weight: 800; }
.rp-table td.code { font-family: var(--rp-mono); font-weight: 700; }
.rp-table td.strong { font-weight: 700; }
.rp-table tfoot td { position: sticky; bottom: 0; background: var(--bg-subtle); font-weight: 800; padding: 11px 10px; text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; border-top: 1px solid var(--border-md); color: var(--text-primary); }
.rp-table tfoot td.l { text-align: left; font-size: 11px; letter-spacing: .08em; text-transform: uppercase; color: var(--text-secondary); }
.rp-table tfoot td.ded { color: var(--danger); }
.rp-emp { display: flex; gap: 9px; align-items: center; }
.rp-av { width: 28px; height: 28px; border-radius: 50%; display: grid; place-items: center; font-weight: 700; font-size: 11px; color: #fff; flex: none; }
.rp-emp b { font-size: 13px; font-weight: 600; }
.rp-emp small { display: block; color: var(--text-muted); font-size: 11px; }
.rp-st { display: inline-flex; gap: 6px; align-items: center; font-size: 11.5px; font-weight: 700; border-radius: 999px; padding: 3px 9px; }
.rp-st i { width: 6px; height: 6px; border-radius: 50%; }
.rp-st.paid { background: var(--success-soft); color: var(--success); } .rp-st.paid i { background: var(--success); }
.rp-st.open { background: var(--warning-soft); color: var(--warning); } .rp-st.open i { background: var(--warning); }
.rp-sw { display: inline-block; width: 8px; height: 8px; border-radius: 2px; margin-right: 6px; vertical-align: middle; }

/* Print / PDF: the report alone, on paper. */
@media print {
    .sidebar, .topbar, .rp-tabs, .rp-filters, .page-head-actions, .chatbot-fab, .chatbot-window, .rp-tip { display: none !important; }
    .main-content { margin: 0 !important; padding: 0 !important; }
    .rp-card, .rp-kpis { box-shadow: none !important; break-inside: avoid; }
    .rp-wrap { overflow: visible !important; }
    .rp-table thead th, .rp-table tfoot td { position: static !important; }
    .rp-print-head { display: block !important; }
}
.rp-print-head { display: none; font-size: 12px; color: var(--text-secondary); margin-top: -8px; }
</style>
@endpush

@section('content')
@php
    use App\Http\Controllers\PayrollReportController as R;

    $keepQuery = array_filter([
        'preset' => $preset !== 'custom' ? $preset : null,
        'from'   => $preset === 'custom' ? $from : null,
        'to'     => $preset === 'custom' ? $to : null,
        'site'   => $siteId, 'q' => $q !== '' ? $q : null,
    ], fn ($v) => $v !== null && $v !== '');
    $url = fn (array $with) => route('payroll-reports.index', array_merge($keepQuery, ['report' => $report], $with));

    $icons = [
        'summary'    => '<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 7h8M8 11h8M8 15h5"/>',
        'employee'   => '<circle cx="9" cy="8" r="4"/><path d="M2 21c0-4 3-6 7-6s7 2 7 6M16 4a4 4 0 010 8M22 21c0-3-2-5-4-5.5"/>',
        'site'       => '<path d="M12 22s7-6.5 7-12a7 7 0 10-14 0c0 5.5 7 12 7 12z"/><circle cx="12" cy="10" r="2.5"/>',
        'overtime'   => '<circle cx="12" cy="13" r="8"/><path d="M12 9v4l2.5 2M9 2h6"/>',
        'deductions' => '<path d="M5 12h14"/><circle cx="12" cy="12" r="9"/>',
        'advances'   => '<rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><path d="M6 12h.01M18 12h.01"/>',
    ];
    $svg = fn ($path, $w = '1.8') => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="' . $w . '" aria-hidden="true">' . $path . '</svg>';
    $arrow = $svg('<path d="M7 10l5-5 5 5M7 14l5 5 5-5"/>', '2.4');
    $periodText = $fromC->format('M j') . ' – ' . $toC->format('M j, Y');

    // A cell: the text is R::fmt's, the same the Excel export writes.
    $cell = function (array $col, $val) {
        [$key, , $type] = $col;
        $txt = e(R::fmt($type, $val));
        return match ($type) {
            'emp'    => '<div class="rp-emp"><span class="rp-av" style="background:' . e($val['c']) . '">' . e($val['init']) . '</span><div><b>' . e($val['t']) . '</b><small>' . e($val['sub']) . '</small></div></div>',
            'status' => '<span class="rp-st ' . e($val['c']) . '"><i></i>' . $txt . '</span>',
            default  => $txt,
        };
    };
    $cls = function (array $col, $val) {
        $type = $col[2];
        $zero = ! is_array($val) && is_numeric($val) && (float) $val == 0.0;
        return trim(implode(' ', array_filter([
            ! empty($col[3]) ? 'l' : '',
            in_array($type, ['net', 'net0'], true) && ! $zero ? 'net' : '',
            $type === 'minus' && ! $zero ? 'ded' : '',
            $type === 'code' ? 'code' : '',
            $type === 'strong' ? 'strong' : '',
            in_array($type, ['money0', 'net0'], true) && $zero ? 'z' : '',
        ])));
    };
    $sortVal = fn ($val) => is_array($val) ? ($val['v'] ?? '') : $val;
@endphp

<div class="rp" id="reportList" data-live="payroll attendance leave advances settings">

    <x-page-header :title="__('Payroll Reports')">
        <x-slot:actions>
            <a class="rp-btn" href="{{ route('payroll-reports.export', array_merge($keepQuery, ['report' => $report])) }}">
                {!! $svg('<path d="M14 3H7a2 2 0 00-2 2v14a2 2 0 002 2h10a2 2 0 002-2V8z"/><path d="M14 3v5h5M9 13l6 5M15 13l-6 5"/>') !!} {{ __('Export Excel') }}
            </a>
            <button type="button" class="rp-btn pri" onclick="window.print()">
                {!! $svg('<path d="M6 9V3h12v6M6 18H4v-7h16v7h-2M8 14h8v7H8z"/>') !!} {{ __('Print / PDF') }}
            </button>
        </x-slot:actions>
    </x-page-header>
    <div class="rp-print-head">{{ $reports[$report] }} · {{ $periodText }}</div>

    <nav class="rp-tabs" aria-label="{{ __('Report') }}">
        @foreach($reports as $k => $t)
            <a class="rp-tab {{ $k === $report ? 'on' : '' }}" href="{{ route('payroll-reports.index', array_merge($keepQuery, ['report' => $k])) }}" @if($k === $report) aria-current="page" @endif>
                {!! $svg($icons[$k]) !!}{{ __($t) }}
            </a>
        @endforeach
    </nav>

    <form class="rp-card rp-filters" method="GET" action="{{ route('payroll-reports.index') }}" id="rpFilters" aria-label="{{ __('Filters') }}">
        <input type="hidden" name="report" value="{{ $report }}">
        <input type="hidden" name="preset" value="{{ $preset !== 'custom' ? $preset : '' }}" id="rpPreset">
        <div class="rp-chips" role="group" aria-label="{{ __('Period') }}">
            @foreach($presets as $k => $t)
                <button type="submit" name="preset" value="{{ $k }}" class="{{ $preset === $k ? 'on' : '' }}" @if($preset === $k) aria-pressed="true" @endif>{{ __($t) }}</button>
            @endforeach
        </div>
        <label class="rp-sel" title="{{ __('Whole pay weeks, from the week holding the first date to the week holding the last') }}">
            {!! $svg('<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>', '2') !!}
            <input type="date" name="from" value="{{ $from }}" aria-label="{{ __('From') }}" id="rpFrom">
            <span class="dash">–</span>
            <input type="date" name="to" value="{{ $to }}" aria-label="{{ __('To') }}" id="rpTo">
        </label>
        <label class="rp-sel">
            {!! $svg('<path d="M12 22s7-6.5 7-12a7 7 0 10-14 0c0 5.5 7 12 7 12z"/>', '2') !!}
            <select name="site" aria-label="{{ __('Site') }}" id="rpSite">
                <option value="">{{ __('All sites') }}</option>
                @foreach($sites as $s)
                    <option value="{{ $s->id }}" @selected($siteId === $s->id)>{{ $s->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="rp-sel">
            {!! $svg('<circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/>', '2') !!}
            <input class="q" type="search" name="q" value="{{ $q }}" placeholder="{{ __('Employee name or ID') }}" aria-label="{{ __('Employee') }}" id="rpQ">
        </label>
        <span class="sp"></span>
        <a class="rp-lnk" href="{{ route('payroll-reports.index', ['report' => $report]) }}">{{ __('Reset') }}</a>
    </form>

    <section class="rp-kpis" aria-label="{{ __('Totals') }}">
        @foreach($kpis as [$label, $value, $sub, $delta, $kcls])
            <div class="rp-kpi">
                <span>{{ __($label) }}</span>
                <b class="{{ $kcls }}">{{ $value }}</b>
                <small title="{{ $sub }}">
                    @if($sub !== null)
                        {{ $sub }}
                    @elseif($delta !== null)
                        {{ __('vs previous') }} <span class="{{ $delta >= 0 ? 'up' : 'down' }}">{{ $delta >= 0 ? '↑' : '↓' }} {{ number_format(abs($delta), 1) }}%</span>
                    @else
                        {{ __('No earlier period to compare') }}
                    @endif
                </small>
            </div>
        @endforeach
    </section>

    <section class="rp-card rp-chart" aria-label="{{ __('Chart') }}">
        <div class="rp-ch-h"><h3>{{ __($chart['title']) }}</h3><small>{{ $chart['sub'] }}</small></div>
        @if(! empty($chart['legend']))
            <div class="rp-legend">
                @foreach($chart['legend'] as [$n, $c])<span><i style="background:{{ $c }}"></i>{{ __($n) }}</span>@endforeach
            </div>
        @endif
        @if($chart['kind'] === 'cols')
            @if($chart['cols'])
                <div class="rp-cols">
                    @foreach($chart['cols'] as $c)
                        <div class="rp-col">
                            <span class="cv">{{ $c['v'] >= 1e4 ? '₱' . number_format($c['v'] / 1e3, 1) . 'k' : '₱' . number_format($c['v'], 2) }}</span>
                            <i class="{{ $c['cur'] ? 'cur' : '' }}" style="height:{{ max(2, round($c['v'] / $chart['max'] * 116)) }}px" data-tip="{{ $c['tip'] }}"></i>
                        </div>
                    @endforeach
                </div>
                <div class="rp-col-x">@foreach($chart['cols'] as $c)<span>{{ $c['label'] }}</span>@endforeach</div>
            @else
                <div class="rp-empty">{{ __('No payroll in this period.') }}</div>
            @endif
        @else
            @if($chart['items'])
                <div class="rp-hbars">
                    @foreach($chart['items'] as $x)
                        <div class="rp-hb">
                            <span class="nm" title="{{ $x['label'] }}">{{ $x['label'] }}</span>
                            <span class="tr">
                                @foreach($x['parts'] as $pt)
                                    @if($pt['v'] > 0)<i style="width:{{ round($pt['v'] / $chart['max'] * 100, 3) }}%;background:{{ $pt['c'] }}" data-tip="{{ $x['label'] }} · {{ $pt['n'] }}: {{ $pt['fmt'] }}"></i>@endif
                                @endforeach
                            </span>
                            <span class="v">{{ $x['fmt'] }}</span>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="rp-empty">{{ __('Nothing to show for this period.') }}</div>
            @endif
        @endif
    </section>

    <section class="rp-card rp-tcard" aria-label="{{ __($table['title']) }}">
        <div class="rp-t-h"><h3>{{ __($reports[$report]) }}</h3><small>{{ $periodText }}</small></div>
        <div class="rp-wrap">
            <table class="rp-table" id="rpTable">
                <thead>
                    <tr>
                        @foreach($table['cols'] as $i => $col)
                            <th class="{{ ! empty($col[3]) ? 'l' : '' }}" aria-sort="none">
                                <button type="button" data-sort="{{ $i }}">@if(! empty($col[4]))<span class="rp-sw" style="background:{{ $col[4] }}"></span>@endif{{ __($col[1]) }}{!! $arrow !!}</button>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse($table['rows'] as $row)
                        <tr>
                            @foreach($table['cols'] as $col)
                                @php $val = $row[$col[0]] ?? null; @endphp
                                <td class="{{ $cls($col, $val) }}" data-v="{{ $sortVal($val) }}">{!! $cell($col, $val) !!}</td>
                            @endforeach
                        </tr>
                    @empty
                        <tr><td class="rp-empty" colspan="{{ count($table['cols']) }}">{{ __('No records for these filters.') }}</td></tr>
                    @endforelse
                </tbody>
                @if($table['rows'])
                    <tfoot>
                        <tr>
                            @foreach($table['foot'] as $i => $f)
                                @php $col = $table['cols'][$i]; @endphp
                                <td class="{{ $i === 0 ? 'l' : ($col[2] === 'minus' && (float) $f > 0 ? 'ded' : '') }}">{{ $i === 0 ? $f : R::fmt($col[2] === 'net0' || $col[2] === 'money0' ? 'money' : $col[2], $f) }}</td>
                            @endforeach
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </section>
</div>
<div class="rp-tip" id="rpTip" hidden></div>
@endsection

@push('scripts')
<script>
(function () {
    // The filters apply as they change: a date pair, a site, a name.
    const form = document.getElementById('rpFilters');
    if (form) {
        const preset = document.getElementById('rpPreset');
        const from = document.getElementById('rpFrom'), to = document.getElementById('rpTo');
        const go = () => form.requestSubmit ? form.requestSubmit() : form.submit();
        [from, to].forEach(el => el && el.addEventListener('change', () => {
            if (from.value && to.value) { preset.value = ''; go(); }
        }));
        const site = document.getElementById('rpSite');
        site && site.addEventListener('change', go);
        const q = document.getElementById('rpQ');
        let t;
        q && q.addEventListener('input', () => { clearTimeout(t); t = setTimeout(go, 600); });
    }

    // Sort by any column; again to reverse.
    const table = document.getElementById('rpTable');
    if (table) {
        let sorted = null, dir = -1;
        table.querySelector('thead').addEventListener('click', e => {
            const b = e.target.closest('[data-sort]');
            if (!b) return;
            const i = +b.dataset.sort;
            dir = sorted === i ? -dir : -1;
            sorted = i;
            const body = table.tBodies[0];
            const rows = [...body.rows].filter(r => r.cells.length > 1);
            const num = s => /^-?\d+(\.\d+)?$/.test(s);
            rows.sort((a, c) => {
                const x = a.cells[i].dataset.v || '', y = c.cells[i].dataset.v || '';
                return (num(x) && num(y) ? parseFloat(x) - parseFloat(y) : x.localeCompare(y)) * dir;
            });
            rows.forEach(r => body.appendChild(r));
            table.querySelectorAll('thead th').forEach((th, j) => {
                th.classList.toggle('sorted', j === i);
                th.setAttribute('aria-sort', j === i ? (dir > 0 ? 'ascending' : 'descending') : 'none');
            });
        });
    }

    // The chart's figures on hover.
    const tip = document.getElementById('rpTip');
    document.addEventListener('pointermove', e => {
        const t = e.target.closest && e.target.closest('.rp [data-tip]');
        if (!t) { tip.hidden = true; return; }
        tip.textContent = t.dataset.tip;
        tip.hidden = false;
        tip.style.left = e.clientX + 'px';
        tip.style.top = e.clientY + 'px';
    });
})();
</script>
@endpush
