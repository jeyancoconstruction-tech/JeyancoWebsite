@extends('layouts')

@section('page_title', 'Remittance Tracker')

{{-- The Remittance Tracker: each month's SSS, PhilHealth, Pag-IBIG and BIR
     remittances. Laid out after the jeyanco-remittance-tracker.html mockup
     (2026-09-26); every figure is real — the employee contributions payroll
     deducted, the due dates set in Payroll Settings, and the payments the
     office recorded here. Colours are the theme's tokens. --}}

@push('styles')
<style>
.rmt { --rmt-mono: 'JetBrains Mono', ui-monospace, Consolas, monospace; display: flex; flex-direction: column; gap: 14px; }
html[data-bs-theme] .main-content .rmt > .page-head { margin-bottom: -2px !important; }

.rmt-card { background: var(--surface); border: 1px solid var(--border); border-radius: 14px; box-shadow: var(--shadow-xs); }
.rmt-btn {
    display: inline-flex; align-items: center; gap: 8px; height: 36px; padding: 0 14px; border-radius: 10px;
    border: 1px solid var(--border-md); background: var(--surface); color: var(--text-primary);
    font-size: 13px; font-weight: 600; white-space: nowrap; text-decoration: none; cursor: pointer;
}
.rmt-btn:hover { border-color: var(--brand); color: var(--text-primary); }
.rmt-btn.pri { background: var(--brand); border-color: var(--brand); color: #fff; }
.rmt-btn.pri:hover { filter: brightness(1.08); color: #fff; }
.rmt-btn.sm { height: 30px; padding: 0 10px; font-size: 12.5px; border-radius: 8px; }
.rmt-btn.danger { color: var(--danger); }
.rmt-btn.danger:hover { border-color: var(--danger); color: var(--danger); }
.rmt-btn svg { width: 16px; height: 16px; flex: none; }
.rmt-ib { width: 36px; height: 36px; border-radius: 9px; border: 1px solid var(--border-md); background: var(--surface); color: var(--text-secondary); display: grid; place-items: center; cursor: pointer; flex: none; }
.rmt-ib:hover { color: var(--text-primary); border-color: var(--brand); }
.rmt-ib svg { width: 16px; height: 16px; }
.rmt-sel { display: flex; align-items: center; gap: 8px; height: 36px; padding: 0 10px; margin: 0; border: 1px solid var(--border-md); border-radius: 10px; background: var(--surface); }
.rmt-sel:focus-within { border-color: var(--brand); box-shadow: 0 0 0 3px var(--brand-subtle); }
.rmt-sel svg { width: 15px; height: 15px; color: var(--text-muted); flex: none; }
html[data-bs-theme] .rmt-sel select {
    border: 0 !important; background: transparent !important; box-shadow: none !important; outline: none;
    height: 34px; padding: 0 4px 0 0; color: var(--text-primary); font-size: 13px; font-weight: 600; cursor: pointer;
}
html[data-bs-theme] .rmt-sel select option { background: var(--surface); color: var(--text-primary); }

/* ── Where the month stands ───────────────────────────────────────────── */
.rmt-stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; }
.rmt-stat { padding: 16px 18px; display: flex; flex-direction: column; gap: 4px; min-width: 0; }
.rmt-stat span { display: flex; align-items: center; gap: 7px; font-size: 10.5px; letter-spacing: .1em; text-transform: uppercase; font-weight: 700; color: var(--text-secondary); }
.rmt-stat span i { width: 8px; height: 8px; border-radius: 50%; background: var(--c); }
.rmt-stat b { font-size: 22px; font-weight: 800; font-variant-numeric: tabular-nums; color: var(--text-primary); }
.rmt-stat small { font-size: 12px; color: var(--text-muted); }
@media (max-width: 1100px) { .rmt-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); } }

/* ── Cards with a table ───────────────────────────────────────────────── */
.rmt-thead { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; padding: 14px 16px; border-bottom: 1px solid var(--border); }
html[data-bs-theme] .rmt .rmt-h {
    margin: 0 !important; font-size: 15px !important; font-weight: 700 !important; line-height: 1.3 !important;
    letter-spacing: 0 !important; color: var(--text-primary); display: flex; gap: 8px; align-items: center;
}
.rmt-h svg { width: 17px; height: 17px; color: var(--brand); }
.rmt-thead .sp { flex: 1; }
.rmt-note { font-size: 12px; color: var(--text-muted); }
.rmt-wrap { overflow-x: auto; }
.rmt-table { width: 100%; min-width: 900px; border-collapse: separate; border-spacing: 0; }
.rmt-table th, .rmt-table td { text-align: right; white-space: nowrap; }
.rmt-table .l { text-align: left; }
.rmt-table thead th {
    padding: 10px 12px; background: var(--bg-subtle); border-bottom: 1px solid var(--border);
    font-size: 10px; letter-spacing: .06em; text-transform: uppercase; font-weight: 700; color: var(--text-secondary);
}
.rmt-table tbody td { padding: 12px; border-bottom: 1px solid var(--border); font-size: 13px; font-variant-numeric: tabular-nums; color: var(--text-primary); vertical-align: middle; }
.rmt-table tr > :first-child { padding-left: 16px; }
.rmt-table tr > :last-child { padding-right: 16px; }
.rmt-table .sepl { border-left: 1px dashed var(--border-md); }
.rmt-table tbody tr[data-agency] { cursor: pointer; }
.rmt-table tbody tr[data-agency]:hover td { background: var(--bg-subtle); }
.rmt-table tbody tr[data-agency]:focus-visible { outline: 2px solid var(--brand); outline-offset: -2px; }
.rmt-table td.amt { font-weight: 800; font-size: 13.5px; }
.rmt-table tfoot td { padding: 12px; background: var(--bg-subtle); border-top: 1px solid var(--border-md); font-weight: 800; font-size: 13px; font-variant-numeric: tabular-nums; color: var(--text-primary); }
.rmt-table tfoot td.l { font-size: 11px; letter-spacing: .1em; text-transform: uppercase; color: var(--text-secondary); }
.rmt-ag { display: flex; gap: 10px; align-items: center; }
.rmt-lg { width: 36px; height: 36px; border-radius: 10px; display: grid; place-items: center; flex: none; font-weight: 800; font-size: 11px; letter-spacing: .02em; color: #fff; background: var(--c); }
.rmt-ag b { display: block; font-size: 14px; }
.rmt-ag small { display: block; color: var(--text-muted); font-size: 11.5px; }
.rmt-due { display: flex; flex-direction: column; gap: 1px; }
.rmt-due small { font-size: 11px; color: var(--text-muted); font-weight: 700; }
.rmt-due small.late { color: var(--danger); }
.rmt-due small.soon { color: var(--warning); }
.rmt-ref { font-family: var(--rmt-mono); font-size: 12px; }
.rmt-table td small.sub { display: block; font-size: 11px; color: var(--text-muted); }

.rmt-st { display: inline-flex; align-items: center; gap: 6px; padding: 3px 9px; border-radius: 999px; font-size: 11.5px; font-weight: 700; white-space: nowrap; background: var(--bg-subtle); color: var(--text-secondary); }
.rmt-st i { width: 6px; height: 6px; border-radius: 50%; background: var(--text-muted); }
.rmt-st.paid { background: var(--success-soft); color: var(--success); } .rmt-st.paid i { background: var(--success); }
.rmt-st.pend { background: var(--warning-soft); color: var(--warning); } .rmt-st.pend i { background: var(--warning); }
.rmt-st.late { background: var(--danger-soft); color: var(--danger); }   .rmt-st.late i { background: var(--danger); }

/* ── The year at a glance ─────────────────────────────────────────────── */
.rmt-year { display: grid; grid-template-columns: 150px repeat(12, minmax(44px, 1fr)); gap: 6px; padding: 14px 16px; min-width: 760px; }
.rmt-year .h { font-size: 10.5px; letter-spacing: .08em; text-transform: uppercase; color: var(--text-muted); font-weight: 700; text-align: center; }
.rmt-year .n { display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 600; color: var(--text-primary); }
.rmt-year .n i { width: 10px; height: 10px; border-radius: 3px; background: var(--c); }
.rmt-yc { height: 30px; border-radius: 7px; display: grid; place-items: center; background: var(--bg-subtle); text-decoration: none; }
a.rmt-yc:hover, a.rmt-yc:focus-visible { outline: 2px solid var(--border-md); outline-offset: 1px; }
.rmt-yc::after { content: ""; width: 8px; height: 8px; border-radius: 50%; grid-area: 1 / 1; }
.rmt-yc.paid { background: var(--success-soft); } .rmt-yc.paid::after { background: var(--success); }
.rmt-yc.pend { background: var(--warning-soft); } .rmt-yc.pend::after { background: var(--warning); }
.rmt-yc.late { background: var(--danger-soft); }  .rmt-yc.late::after { background: var(--danger); }
.rmt-yc.none::after { width: 10px; height: 2px; border-radius: 1px; background: var(--border-md); }
.rmt-yc.fut { opacity: .55; }
.rmt-yc.sel { outline: 2px solid var(--brand); outline-offset: 1px; }
.rmt-legend { display: flex; gap: 12px; flex-wrap: wrap; font-size: 12px; color: var(--text-muted); }
.rmt-legend span { display: flex; align-items: center; gap: 6px; }
.rmt-legend .rmt-yc { width: 12px; height: 12px; border-radius: 3px; }
.rmt-legend .rmt-yc::after { display: none; }
.rmt-legend .rmt-yc.paid { background: var(--success); } .rmt-legend .rmt-yc.pend { background: var(--warning); }
.rmt-legend .rmt-yc.late { background: var(--danger); } .rmt-legend .rmt-yc.none { background: var(--border-md); }
.rmt-legend .rmt-yc.fut { background: var(--bg-subtle); box-shadow: inset 0 0 0 1px var(--border-md); opacity: 1; }

/* ── An agency's month, in a side panel ───────────────────────────────── */
.rmt-drawer.offcanvas { --bs-offcanvas-width: min(540px, 100%); background: var(--surface); color: var(--text-primary); border-left: 1px solid var(--border); }
.rmt-dh { padding: 18px 22px; border-bottom: 1px solid var(--border); display: flex; gap: 12px; align-items: center; }
.rmt-dh .rmt-lg { width: 46px; height: 46px; border-radius: 12px; font-size: 12px; }
.rmt-dh b { display: block; font-size: 17px; }
.rmt-dh small { color: var(--text-muted); font-size: 12.5px; }
.rmt-dh .rmt-ib { margin-left: auto; }
.rmt-db { padding: 18px 22px; overflow: auto; display: flex; flex-direction: column; gap: 18px; flex: 1; }
.rmt-net { border-radius: 14px; padding: 16px 18px; border: 1px solid var(--border); background: linear-gradient(135deg, var(--brand-subtle), transparent); display: flex; justify-content: space-between; align-items: flex-end; gap: 10px; }
.rmt-net span { font-size: 11px; letter-spacing: .12em; text-transform: uppercase; font-weight: 700; color: var(--text-secondary); }
.rmt-net b { display: block; font-size: 30px; font-weight: 800; font-variant-numeric: tabular-nums; }
.rmt-net small { color: var(--text-secondary); font-size: 12.5px; text-align: right; }
.rmt-dsec h4 { margin: 0 0 8px !important; font-size: 11px !important; letter-spacing: .12em; text-transform: uppercase; color: var(--text-secondary); font-weight: 700; }
.rmt-lines { list-style: none; margin: 0; padding: 0; }
.rmt-lines li { display: flex; justify-content: space-between; align-items: center; gap: 10px; padding: 8px 0; border-bottom: 1px dashed var(--border); font-size: 13.5px; }
.rmt-lines li > span { color: var(--text-secondary); min-width: 0; }
.rmt-lines li > span small { display: block; font-size: 11.5px; color: var(--text-muted); }
.rmt-lines li > span small.missing { color: var(--warning); font-weight: 600; }
.rmt-lines li b { font-family: var(--rmt-mono); font-weight: 600; font-variant-numeric: tabular-nums; white-space: nowrap; }
.rmt-lines li.tot { border-bottom: 0; border-top: 1px solid var(--border-md); font-weight: 800; }
.rmt-lines li.tot > span { color: var(--text-primary); }
.rmt-lines a { color: var(--brand); font-weight: 600; text-decoration: none; }
.rmt-lines a:hover { text-decoration: underline; }
.rmt-df { padding: 14px 22px; border-top: 1px solid var(--border); display: flex; gap: 8px; justify-content: flex-end; flex-wrap: wrap; }
.rmt-df form { margin: 0 auto 0 0; }
.rmt-empty { padding: 16px; text-align: center; color: var(--text-muted); font-size: 13px; }
body:has(.rmt-drawer.show) .chatbot-fab, body:has(.rmt-drawer.showing) .chatbot-fab { visibility: hidden; }

/* ── Mark as paid ─────────────────────────────────────────────────────── */
.rmt-modal .modal-dialog { max-width: min(480px, calc(100% - 32px)); }
.rmt-modal .modal-content { background: var(--surface); color: var(--text-primary); border: 1px solid var(--border); border-radius: 18px; overflow: hidden; }
.rmt-mh { padding: 16px 20px; display: flex; gap: 12px; align-items: center; border-bottom: 1px solid var(--border); }
html[data-bs-theme] .rmt-modal .rmt-mh h3 { margin: 0 !important; font-size: 17px !important; font-weight: 700 !important; line-height: 1.3 !important; color: var(--text-primary); flex: 1; }
.rmt-mb { padding: 18px 20px; display: flex; flex-direction: column; gap: 12px; }
.rmt-g2 { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
.rmt-f { display: flex; flex-direction: column; gap: 6px; min-width: 0; }
.rmt-f label { font-size: 12.5px; font-weight: 600; color: var(--text-primary); margin: 0; }
html[data-bs-theme] .rmt-f input, html[data-bs-theme] .rmt-f select {
    height: 40px; padding: 0 12px; border: 1px solid var(--border-md) !important; border-radius: 10px !important;
    background: var(--bg-subtle) !important; color: var(--text-primary) !important; font-size: 13.5px; outline: none; box-shadow: none !important; width: 100%;
}
html[data-bs-theme] .rmt-f input[type=file] { height: auto; padding: 8px 12px; }
html[data-bs-theme] .rmt-f input:focus, html[data-bs-theme] .rmt-f select:focus { border-color: var(--brand) !important; box-shadow: 0 0 0 3px var(--brand-subtle) !important; }
.rmt-f small { font-size: 11.5px; color: var(--text-muted); }
.rmt-f .err { color: var(--danger); font-weight: 600; }
.rmt-mf { padding: 14px 20px; display: flex; gap: 8px; justify-content: flex-end; border-top: 1px solid var(--border); }
</style>
@endpush

@section('content')
@php
    $peso  = fn ($n) => '₱' . number_format((float) $n, 2);
    $label = ['paid' => 'Paid', 'pend' => 'Pending', 'late' => 'Overdue', 'none' => 'Nothing due', 'fut' => 'Not yet due'];
    $mon   = $month->format('M Y');
    [$wFrom, $wTo] = $weeks;

    $owing = collect($rows)->filter(fn ($r) => $r['total'] > 0 || $r['payment']);
    $paid  = collect($rows)->where('status', 'paid');
    $pend  = collect($rows)->where('status', 'pend');
    $late  = collect($rows)->where('status', 'late');
    $sum   = fn ($list) => $list->sum('total');

    $icon = [
        'cal'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>',
        'dl'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M14 3H7a2 2 0 00-2 2v14a2 2 0 002 2h10a2 2 0 002-2V8z"/><path d="M14 3v5h5M12 11v6M9 14l3 3 3-3"/></svg>',
        'list'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h10"/></svg>',
        'year'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="4" width="18" height="17" rx="2"/><path d="M3 9h18M8 13h2M12 13h2M16 13h2M8 17h2M12 17h2"/></svg>',
        'close' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>',
    ];

    // What the Mark as paid dialog needs to know about each agency.
    $payInfo = collect($rows)->map(fn ($r) => [
        'name'     => $r['a']['name'],
        'expected' => round($r['total'], 2),
        'channel'  => $r['a']['channel'],
        'prefix'   => strtoupper(substr($r['a']['name'], 0, 2)),
    ])->all();
@endphp

<div class="rmt">

    <x-page-header :title="__('Remittance Tracker')">
        <x-slot:actions>
            <label class="rmt-sel">
                {!! $icon['cal'] !!}
                <select id="rmtMonth" aria-label="{{ __('Contribution month') }}">
                    @foreach($months as $m)
                        <option value="{{ $m->format('Y-m') }}" @selected($m->format('Y-m') === $month->format('Y-m'))>{{ $m->format('M Y') }} {{ __('contributions') }}</option>
                    @endforeach
                </select>
            </label>
            <a class="rmt-btn" href="{{ route('remittances.report', ['month' => $month->format('Y-m')]) }}">{!! $icon['dl'] !!} {{ __('Download reports') }}</a>
        </x-slot:actions>
    </x-page-header>

    {{-- ── Where the month stands ──────────────────────────────────────── --}}
    <section class="rmt-stats" id="rmtStats" data-live="remittances settings" aria-label="{{ __('Remittance summary') }}">
        <div class="rmt-card rmt-stat" style="--c:var(--brand)">
            <span><i></i>{{ __('Total to remit') }}</span>
            <b>{{ $peso($sum(collect($rows))) }}</b>
            <small>{{ $mon }} · {{ __('employee contributions') }}</small>
        </div>
        <div class="rmt-card rmt-stat" style="--c:var(--success)">
            <span><i></i>{{ __('Paid') }}</span>
            <b>{{ $peso($paid->sum(fn ($r) => (float) $r['payment']->amount)) }}</b>
            <small>{{ $paid->count() }} {{ __('of') }} {{ max($owing->count(), $paid->count()) }} {{ $owing->count() === 1 ? __('agency') : __('agencies') }}</small>
        </div>
        <div class="rmt-card rmt-stat" style="--c:var(--warning)">
            <span><i></i>{{ __('Pending') }}</span>
            <b>{{ $peso($sum($pend)) }}</b>
            <small>{{ $pend->isNotEmpty()
                ? $pend->pluck('a.name')->join(', ') . ' · ' . __('due') . ' ' . $pend->min('due')->format('M j')
                : __('Nothing pending') }}</small>
        </div>
        <div class="rmt-card rmt-stat" style="--c:var(--danger)">
            <span><i></i>{{ __('Overdue') }}</span>
            <b>{{ $peso($sum($late)) }}</b>
            <small>{{ $late->isNotEmpty() ? $late->pluck('a.name')->join(', ') . ' · ' . __('penalties may apply') : __('All on time') }}</small>
        </div>
    </section>

    {{-- ── The month, agency by agency ─────────────────────────────────── --}}
    <section class="rmt-card" id="rmtList" data-live="remittances settings" aria-label="{{ __('Remittances') }}">
        <div class="rmt-thead">
            <h2 class="rmt-h">{!! $icon['list'] !!} {{ $mon }} {{ __('contributions') }}</h2>
            <span class="rmt-note" title="{{ __('A pay week counts in the month it ends in') }}">{{ __('Pay weeks ending in') }} {{ $month->format('F') }} · {{ $wFrom->format('M j') }} – {{ $wTo->format('M j') }}</span>
            <span class="sp"></span>
            <span class="rmt-note">{{ __("Click an agency to see each employee's contribution") }}</span>
        </div>
        <div class="rmt-wrap">
            <table class="rmt-table">
                <thead>
                    <tr>
                        <th class="l">{{ __('Agency') }}</th>
                        <th>{{ __('Employees') }}</th>
                        <th>{{ __('Amount') }}</th>
                        <th class="l sepl">{{ __('Due date') }}</th>
                        <th class="l">{{ __('Status') }}</th>
                        <th class="l">{{ __('Payment') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $r)
                        <tr data-agency="{{ $r['agency'] }}" tabindex="0">
                            <td class="l">
                                <div class="rmt-ag">
                                    <span class="rmt-lg" style="--c:{{ $r['a']['color'] }}">{{ strtoupper(substr($r['a']['name'], 0, 3)) }}</span>
                                    <div><b>{{ $r['a']['name'] }}</b><small>{{ $r['a']['form'] }}</small></div>
                                </div>
                            </td>
                            <td>{{ count($r['people']) }}</td>
                            <td class="amt">{{ $peso($r['total']) }}</td>
                            <td class="l sepl">
                                <div class="rmt-due">
                                    <span>{{ $r['due']->format('M j, Y') }}</span>
                                    @if($r['status'] === 'late')
                                        <small class="late">{{ abs($r['days']) }} {{ abs($r['days']) === 1 ? __('day late') : __('days late') }}</small>
                                    @elseif($r['status'] === 'pend')
                                        <small class="soon">{{ $r['days'] === 0 ? __('due today') : __('in') . ' ' . $r['days'] . ' ' . ($r['days'] === 1 ? __('day') : __('days')) }}</small>
                                    @endif
                                </div>
                            </td>
                            <td class="l"><span class="rmt-st {{ $r['status'] }}"><i></i>{{ __($label[$r['status']]) }}</span></td>
                            <td class="l">
                                @if($r['payment'])
                                    <span class="rmt-ref">{{ $r['payment']->reference }}</span>
                                    <small class="sub">{{ $r['payment']->paid_on->format('M j') }} · {{ $r['payment']->channel }}</small>
                                @elseif(in_array($r['status'], ['pend', 'late'], true))
                                    <button type="button" class="rmt-btn pri sm" data-rmt-pay="{{ $r['agency'] }}">{{ __('Mark as paid') }}</button>
                                @else
                                    <span class="rmt-note">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td class="l">{{ __('Total') }}</td>
                        <td></td>
                        <td>{{ $peso($sum(collect($rows))) }}</td>
                        <td class="sepl" colspan="3"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </section>

    {{-- ── The year at a glance ────────────────────────────────────────── --}}
    <section class="rmt-card" id="rmtYear" data-live="remittances settings" aria-label="{{ $year }} {{ __('at a glance') }}">
        <div class="rmt-thead">
            <h2 class="rmt-h">{!! $icon['year'] !!} {{ $year }} {{ __('at a glance') }}</h2>
            <span class="sp"></span>
            <span class="rmt-legend">
                <span><i class="rmt-yc paid"></i>{{ __('Paid') }}</span>
                <span><i class="rmt-yc pend"></i>{{ __('Pending') }}</span>
                <span><i class="rmt-yc late"></i>{{ __('Overdue') }}</span>
                <span><i class="rmt-yc none"></i>{{ __('Nothing due') }}</span>
                <span><i class="rmt-yc fut"></i>{{ __('Not yet due') }}</span>
            </span>
        </div>
        <div class="rmt-wrap">
            <div class="rmt-year">
                <div></div>
                @for($m = 1; $m <= 12; $m++)
                    <div class="h">{{ \Carbon\Carbon::create($year, $m, 1)->format('M') }}</div>
                @endfor
                @foreach($rows as $agency => $r)
                    <div class="n"><i style="--c:{{ $r['a']['color'] }}"></i>{{ $r['a']['name'] }}</div>
                    @foreach($grid[$agency] as $m => $cell)
                        @php
                            $cellLabel = $r['a']['name'] . ' ' . \Carbon\Carbon::create($year, $m, 1)->format('M') . ': ' . __($label[$cell['status']]);
                            $isSel     = $cell['key'] === $month->format('Y-m');
                        @endphp
                        @if($cell['tracked'])
                            <a class="rmt-yc {{ $cell['status'] }} {{ $isSel ? 'sel' : '' }}" href="{{ route('remittances.index', ['month' => $cell['key']]) }}"
                               aria-label="{{ $cellLabel }}" title="{{ $cellLabel }}" @if($isSel) aria-current="true" @endif></a>
                        @else
                            <span class="rmt-yc {{ $cell['status'] }}" role="img" aria-label="{{ $cellLabel }}" title="{{ $cellLabel }}"></span>
                        @endif
                    @endforeach
                @endforeach
            </div>
        </div>
    </section>

    {{-- ── An agency's month ───────────────────────────────────────────────
         One panel per agency, opened from its row: each employee's share, and
         the payment once it is recorded. --}}
    @foreach($rows as $r)
        <aside class="offcanvas offcanvas-end rmt-drawer" tabindex="-1" id="rmtAg-{{ $r['agency'] }}" aria-labelledby="rmtAgTitle-{{ $r['agency'] }}">
            <div class="rmt-dh">
                <span class="rmt-lg" style="--c:{{ $r['a']['color'] }}">{{ strtoupper(substr($r['a']['name'], 0, 3)) }}</span>
                <div>
                    <b id="rmtAgTitle-{{ $r['agency'] }}">{{ $r['a']['name'] }} · {{ $mon }}</b>
                    <small>{{ $r['a']['full'] }} · {{ $r['a']['form'] }}</small>
                </div>
                <button type="button" class="rmt-ib" data-bs-dismiss="offcanvas" aria-label="{{ __('Close') }}">{!! $icon['close'] !!}</button>
            </div>
            <div class="rmt-db">
                <div class="rmt-net">
                    <div><span>{{ __('Total remittance') }}</span><b>{{ $peso($r['total']) }}</b></div>
                    <small><span class="rmt-st {{ $r['status'] }}"><i></i>{{ __($label[$r['status']]) }}</span><br>{{ __('Due') }} {{ $r['due']->format('M j, Y') }}</small>
                </div>

                <div class="rmt-dsec">
                    <h4>{{ __('Per employee') }}</h4>
                    @if($r['people'])
                        <ul class="rmt-lines">
                            @foreach($r['people'] as $p)
                                <li>
                                    <span>{{ $p['name'] }}
                                        @if($p['id_number'] !== '')
                                            <small>{{ $p['code'] }} · {{ $r['a']['id_label'] }} {{ $p['id_number'] }}</small>
                                        @else
                                            <small class="missing">{{ $p['code'] }} · {{ __('No') }} {{ $r['a']['id_label'] }} {{ __('on file') }}</small>
                                        @endif
                                    </span>
                                    <b>{{ $peso($p['amount']) }}</b>
                                </li>
                            @endforeach
                            <li class="tot"><span>{{ __('Total') }}</span><b>{{ $peso($r['total']) }}</b></li>
                        </ul>
                    @else
                        <div class="rmt-empty">{{ __('Nobody had this deducted in') }} {{ $mon }}.</div>
                    @endif
                </div>

                @if($r['payment'])
                    @php $pay = $r['payment']; @endphp
                    <div class="rmt-dsec">
                        <h4>{{ __('Payment') }}</h4>
                        <ul class="rmt-lines">
                            <li><span>{{ __('Reference no.') }}</span><b>{{ $pay->reference }}</b></li>
                            <li><span>{{ __('Amount paid') }}</span><b>{{ $peso($pay->amount) }}</b></li>
                            <li><span>{{ __('Paid on') }}</span><b>{{ $pay->paid_on->format('M j, Y') }}</b></li>
                            <li><span>{{ __('Paid through') }}</span><b style="font-family:inherit">{{ $pay->channel }}</b></li>
                            <li><span>{{ __('Receipt') }}</span>
                                @if($pay->receipt)
                                    <a href="{{ route('remittances.receipt', $pay) }}" target="_blank" rel="noopener">{{ __('View') }} {{ \Illuminate\Support\Str::limit($pay->receipt->name, 32) }}</a>
                                @else
                                    <b style="font-family:inherit;color:var(--text-muted)">{{ __('None attached') }}</b>
                                @endif
                            </li>
                            <li><span>{{ __('Recorded by') }}</span><b style="font-family:inherit">{{ $pay->recorder->name ?? '—' }} · {{ $pay->created_at?->timezone('Asia/Manila')->format('M j, g:i A') }}</b></li>
                        </ul>
                    </div>
                @endif
            </div>
            <div class="rmt-df">
                @if($r['payment'])
                    <form method="POST" action="{{ route('remittances.destroy', $r['payment']) }}"
                          data-confirm="{{ __('The month goes back to unpaid, and the receipt is removed with it.') }}"
                          data-confirm-title="{{ __('Remove this payment?') }}"
                          data-confirm-label="{{ __('Remove') }}" data-confirm-tone="danger">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="rmt-btn danger">{{ __('Remove payment') }}</button>
                    </form>
                @endif
                <a class="rmt-btn" href="{{ route('remittances.report', ['month' => $month->format('Y-m'), 'agency' => $r['agency']]) }}">{!! $icon['dl'] !!} {{ __('Download report') }}</a>
                @if(! $r['payment'] && in_array($r['status'], ['pend', 'late'], true))
                    <button type="button" class="rmt-btn pri" data-rmt-pay="{{ $r['agency'] }}">{{ __('Mark as paid') }}</button>
                @endif
            </div>
        </aside>
    @endforeach

    {{-- ── Mark as paid ────────────────────────────────────────────────────
         One dialog, set to whichever agency's button opened it. --}}
    <div class="modal fade rmt-modal" id="rmtPay" tabindex="-1" aria-labelledby="rmtPayTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" method="POST" action="{{ route('remittances.store') }}" enctype="multipart/form-data" novalidate>
                @csrf
                <input type="hidden" name="agency" id="rmtPayAgency" value="{{ old('agency') }}">
                <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
                <div class="rmt-mh">
                    <h3 id="rmtPayTitle">{{ __('Mark as paid') }}</h3>
                    <button type="button" class="rmt-ib" data-bs-dismiss="modal" aria-label="{{ __('Close') }}">{!! $icon['close'] !!}</button>
                </div>
                <div class="rmt-mb">
                    <div class="rmt-g2">
                        <div class="rmt-f">
                            <label for="rmtAmt">{{ __('Amount paid (₱)') }}</label>
                            <input id="rmtAmt" name="amount" type="number" step="0.01" min="0" value="{{ old('amount') }}" required>
                            <small id="rmtExpected"></small>
                            @error('amount')<small class="err">{{ $message }}</small>@enderror
                        </div>
                        <div class="rmt-f">
                            <label for="rmtDate">{{ __('Date paid') }}</label>
                            <input id="rmtDate" name="paid_on" type="date" value="{{ old('paid_on', now('Asia/Manila')->toDateString()) }}" max="{{ now('Asia/Manila')->toDateString() }}" required>
                            @error('paid_on')<small class="err">{{ $message }}</small>@enderror
                        </div>
                    </div>
                    <div class="rmt-f">
                        <label for="rmtRef">{{ __('Reference / PRN no.') }}</label>
                        <input id="rmtRef" name="reference" value="{{ old('reference') }}" maxlength="64" autocomplete="off" required>
                        @error('reference')<small class="err">{{ $message }}</small>@enderror
                    </div>
                    <div class="rmt-f">
                        <label for="rmtCh">{{ __('Paid through') }}</label>
                        <select id="rmtCh" name="channel" data-old="{{ old('channel') }}"></select>
                        @error('channel')<small class="err">{{ $message }}</small>@enderror
                    </div>
                    <div class="rmt-f">
                        <label for="rmtFile">{{ __('Receipt (optional)') }}</label>
                        <input id="rmtFile" name="receipt" type="file" accept="image/jpeg,image/png,image/webp,application/pdf">
                        <small>{{ __('A photo or PDF, up to 2 MB. Kept with the payment.') }}</small>
                        @error('receipt')<small class="err">{{ $message }}</small>@enderror
                    </div>
                    @error('month')<small class="err" style="color:var(--danger);font-weight:600">{{ $message }}</small>@enderror
                    @error('agency')<small class="err" style="color:var(--danger);font-weight:600">{{ $message }}</small>@enderror
                </div>
                <div class="rmt-mf">
                    <button type="button" class="rmt-btn" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                    <button type="submit" class="rmt-btn pri">{{ __('Save payment') }}</button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection

@push('scripts')
@php
    $jsPay      = $payInfo;
    $jsChannels = $channels;
    $jsMonth    = $mon;
    $jsBase     = route('remittances.index');
    $jsReopen   = $errors->any() && old('agency') ? old('agency') : null;
@endphp
<script>
(function () {
    const root = document.querySelector('.rmt');
    if (!root) return;

    const PAY      = @json($jsPay);
    const CHANNELS = @json($jsChannels);
    const MONTH    = @json($jsMonth);
    const BASE     = @json($jsBase);
    const REOPEN   = @json($jsReopen);
    const fmt      = new Intl.NumberFormat('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    // The month picker opens that month.
    document.getElementById('rmtMonth').addEventListener('change', e => {
        location.href = BASE + '?month=' + encodeURIComponent(e.target.value);
    });

    // A row opens its agency's panel. Delegated, so rows the live feed has
    // replaced still open.
    function openAgency(agency) {
        const el = document.getElementById('rmtAg-' + agency);
        if (el) bootstrap.Offcanvas.getOrCreateInstance(el).show();
    }
    root.addEventListener('click', e => {
        if (e.target.closest('[data-rmt-pay], a, button')) return;
        const row = e.target.closest('tr[data-agency]');
        if (row) openAgency(row.dataset.agency);
    });
    root.addEventListener('keydown', e => {
        if ((e.key === 'Enter' || e.key === ' ') && e.target.matches('tr[data-agency]')) {
            e.preventDefault();
            openAgency(e.target.dataset.agency);
        }
    });

    // ── Mark as paid ────────────────────────────────────────────────────
    const modalEl = document.getElementById('rmtPay');

    function setUp(agency, keep) {
        const info = PAY[agency];
        if (!info) return;
        document.getElementById('rmtPayAgency').value = agency;
        document.getElementById('rmtPayTitle').textContent = 'Mark ' + info.name + ' ' + MONTH + ' as paid';
        document.getElementById('rmtExpected').textContent = 'Expected ₱' + fmt.format(info.expected);
        document.getElementById('rmtRef').placeholder = 'e.g. ' + info.prefix + '-' + MONTH.replace(/\s+/g, '').toUpperCase() + '-0000';

        const sel = document.getElementById('rmtCh');
        const want = keep ? sel.dataset.old : '';
        sel.innerHTML = '';
        [info.channel].concat(CHANNELS).forEach(c => {
            const o = document.createElement('option');
            o.value = c; o.textContent = c;
            if (c === want) o.selected = true;
            sel.appendChild(o);
        });

        if (!keep) {
            document.getElementById('rmtAmt').value = info.expected.toFixed(2);
            document.getElementById('rmtRef').value = '';
            document.getElementById('rmtFile').value = '';
        }
    }

    function openPay(agency, keep) {
        setUp(agency, keep);
        const show = () => bootstrap.Modal.getOrCreateInstance(modalEl).show();
        // One layer at a time: an open panel closes before the dialog opens.
        const open = document.querySelector('.rmt-drawer.show');
        if (open) {
            open.addEventListener('hidden.bs.offcanvas', show, { once: true });
            bootstrap.Offcanvas.getOrCreateInstance(open).hide();
        } else {
            show();
        }
    }

    document.addEventListener('click', e => {
        const b = e.target.closest('[data-rmt-pay]');
        if (b) { e.preventDefault(); openPay(b.dataset.rmtPay, false); }
    });
    modalEl.addEventListener('shown.bs.modal', () => document.getElementById('rmtRef').focus());

    // A payment the server sent back (a missing reference, a file too big)
    // opens again with what was typed, and the message beside its field.
    if (REOPEN) openPay(REOPEN, true);
})();
</script>
@endpush
