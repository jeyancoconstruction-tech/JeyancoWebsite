@extends('layouts')
@section('page_title', 'Complaints')

@push('styles')
<style>
/* Complaints (2026-11-13 revision, "people") — the page lists the workers,
   not the reports: every active worker is there whether or not they have
   written, the ones waiting for an answer on top with a count in their
   topic's colour. Picking a worker opens all they have reported as tickets,
   and the one picked has the answer box. With nobody picked, the right side
   shows the month by topic and the last answers, so no space is left empty. */
/* One colour only: black (white in the dark theme). Topics, waits and
   answers are told apart by their words, not by colours. */
.cpx { --cx-ink: #111827; --cx-ink-2: #111827; --cx-on-ink: #fff; --cx-line: var(--border, #e4e7ec); --cx-soft: var(--bg-subtle, #f8f9fb);
       --t-pay: var(--cx-ink); --t-attendance: var(--cx-ink); --t-safety: var(--cx-ink); --t-equipment: var(--cx-ink); --t-treatment: var(--cx-ink); --t-other: var(--cx-ink);
       --cx-ok: var(--text-muted); --cx-warn: var(--cx-ink);
       display: flex; flex-direction: column; gap: 16px; height: calc(100vh - 120px); min-height: 600px; }
[data-bs-theme="dark"] .cpx { --cx-ink: #e8edf5; --cx-ink-2: #e8edf5; --cx-on-ink: #0f172a; }
.cpx [data-t] { --t: var(--t-other); }
.cpx [data-t="pay"] { --t: var(--t-pay); }
.cpx [data-t="attendance"] { --t: var(--t-attendance); }
.cpx [data-t="safety"] { --t: var(--t-safety); }
.cpx [data-t="equipment"] { --t: var(--t-equipment); }
.cpx [data-t="treatment"] { --t: var(--t-treatment); }

/* Head. */
.cpx-top { display: flex; align-items: flex-end; justify-content: space-between; gap: 20px 32px; flex-wrap: wrap; }
.cpx-top > div:first-child { flex: 1 1 320px; min-width: 0; }
.cpx-eyebrow { font-size: 11.5px; font-weight: 600; letter-spacing: .08em; text-transform: uppercase; color: var(--text-muted); }
html[data-bs-theme] .cpx-top h1 { margin: 2px 0 0; font-size: 26px; font-weight: 700; letter-spacing: -.02em; color: var(--text-primary); }
.cpx-top p { margin: 4px 0 0; font-size: 13px; color: var(--text-muted); }
.cpx-strip { display: flex; border: 1px solid var(--cx-line); border-radius: 10px; background: var(--surface); overflow: hidden; }
.cpx-strip > div { padding: 10px 18px 11px; min-width: 112px; }
.cpx-strip > div + div { border-left: 1px solid var(--cx-line); }
.cpx-strip span { display: block; font-size: 11.5px; color: var(--text-muted); white-space: nowrap; }
.cpx-strip b { display: block; margin-top: 1px; font-size: 22px; font-weight: 700; line-height: 1.15; font-variant-numeric: tabular-nums; color: var(--text-primary); }
.cpx-strip b small { font-size: 12px; font-weight: 600; color: var(--text-muted); margin-left: 2px; }
.cpx-strip .alarm { background: color-mix(in srgb, var(--t-safety) 9%, var(--surface)); box-shadow: inset 3px 0 0 var(--t-safety); }
.cpx-strip .alarm b, .cpx-strip .alarm span { color: var(--t-safety); }

/* Tabs and filters. */
.cpx-bar { display: flex; align-items: center; gap: 8px 22px; flex-wrap: wrap; border-bottom: 1px solid var(--cx-line); }
.cpx-tabs { display: flex; gap: 22px; }
.cpx-tabs a { display: inline-flex; align-items: center; gap: 7px; padding: 10px 0 11px; margin-bottom: -1px; border-bottom: 2px solid transparent;
              font-size: 13.5px; font-weight: 500; color: var(--text-muted); text-decoration: none; }
.cpx-tabs a:hover { color: var(--text-primary); }
.cpx-tabs a.on { color: var(--text-primary); font-weight: 600; border-color: var(--cx-ink-2); }
.cpx-tabs em { font-style: normal; min-width: 20px; padding: 0 6px; border-radius: 6px; text-align: center; font-size: 11.5px; font-weight: 600;
               line-height: 18px; background: var(--cx-soft); color: var(--text-muted); font-variant-numeric: tabular-nums; }
.cpx-tabs a.on em { background: var(--cx-ink); color: var(--cx-on-ink); }
.cpx-tabs a.hot em { background: color-mix(in srgb, var(--cx-warn) 16%, transparent); color: var(--cx-warn); }
.cpx-tabs a.hot.on em { background: var(--cx-warn); color: var(--cx-on-ink); }
.cpx-flt { margin-left: auto; display: flex; gap: 8px; align-items: center; padding: 6px 0 8px; }
.cpx-flt select, .cpx-flt input[type=search] { height: 34px; padding: 0 10px; border: 1px solid var(--border-md); border-radius: 8px; background: var(--surface); color: var(--text-primary); font-size: 12.5px; }
.cpx-flt input[type=search] { width: 210px; padding-left: 30px;
    background: var(--surface) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' fill='none' stroke='%23889' stroke-width='2' viewBox='0 0 24 24'%3E%3Ccircle cx='11' cy='11' r='7'/%3E%3Cpath d='m20 20-4-4'/%3E%3C/svg%3E") 10px center no-repeat; }
.cpx-flt .reset { font-size: 12.5px; font-weight: 600; color: var(--cx-ink-2); text-decoration: none; padding: 0 4px; }

/* The two panes. */
.cpx-main { flex: 1; min-height: 0; display: grid; grid-template-columns: 360px minmax(0, 1fr); gap: 16px; }
.cpx-card { min-height: 0; background: var(--surface); border: 1px solid var(--cx-line); border-radius: 12px; }

/* The workers. */
.cpx-list { overflow-y: auto; }
.cpx-grp { position: sticky; top: 0; z-index: 1; display: flex; align-items: center; gap: 8px; padding: 11px 16px 7px; background: var(--surface);
           border-bottom: 1px solid var(--cx-line); font-size: 11px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: var(--text-muted); }
.cpx-grp i { font-size: 10px; opacity: .7; }
.cpx-grp em { margin-left: auto; font-style: normal; font-variant-numeric: tabular-nums; letter-spacing: 0; }
.cpx-grp.hot { color: var(--cx-warn); }
.cpx-emp { position: relative; display: flex; gap: 11px; align-items: flex-start; padding: 10px 14px 10px 16px; border-bottom: 1px solid var(--cx-line); text-decoration: none; color: inherit; }
.cpx-emp:hover { background: var(--cx-soft); color: inherit; }
.cpx-emp.sel { background: color-mix(in srgb, var(--cx-ink-2) 8%, var(--surface)); }
.cpx-emp.sel::before { content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 3px; background: var(--cx-ink-2); }
.cpx-emp.open.sel::before { background: var(--t); }
.cpx-ico { flex: none; width: 34px; height: 34px; border-radius: 9px; display: grid; place-items: center; background: var(--cx-soft); }
.cpx-b { flex: 1; min-width: 0; }
.cpx-n { display: flex; align-items: center; gap: 8px; font-size: 13.5px; font-weight: 600; color: var(--text-primary); }
.cpx-n > span:first-child { min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.cpx-sub { display: block; margin-top: 1px; font-size: 11.5px; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.cpx-cnt { margin-left: auto; flex: none; min-width: 22px; height: 20px; padding: 0 7px; border-radius: 10px; display: inline-grid; place-items: center;
           font-size: 11.5px; font-weight: 700; font-variant-numeric: tabular-nums; color: var(--cx-on-ink); background: var(--t); }
.cpx-cnt.mute { color: var(--text-muted); background: var(--cx-soft); border: 1px solid var(--cx-line); font-weight: 600; }
.cpx-tx { display: block; margin-top: 5px; font-size: 12px; line-height: 1.4; color: var(--text-secondary, var(--text-muted));
          display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.cpx-meta { display: flex; align-items: center; gap: 8px; margin-top: 4px; font-size: 11.5px; color: var(--text-muted); }
.cpx-t { display: inline-flex; align-items: center; gap: 5px; font-weight: 600; color: var(--t); }
.cpx-t::before { content: ''; width: 6px; height: 6px; border-radius: 2px; background: currentColor; }
.cpx-wait { color: var(--cx-warn); font-weight: 600; }
.cpx-nobody { padding: 18px 16px; font-size: 12.5px; color: var(--text-muted); }

/* A worker opened. */
.cpx-det { display: flex; flex-direction: column; overflow: hidden; }
.cpx-det-h { flex: none; display: flex; align-items: center; gap: 14px; flex-wrap: wrap; padding: 16px 22px; border-bottom: 1px solid var(--cx-line); }
.cpx-det-h .cpx-ico { width: 46px; height: 46px; border-radius: 12px; }
.cpx-who { flex: 1; min-width: 200px; }
.cpx-who b { display: block; font-size: 18px; font-weight: 700; letter-spacing: -.01em; color: var(--text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.cpx-who small { display: block; margin-top: 1px; font-size: 12.5px; color: var(--text-muted); }
.cpx-tally { display: flex; gap: 18px; font-size: 11.5px; color: var(--text-muted); }
.cpx-tally b { display: block; font-size: 16px; font-weight: 700; color: var(--text-primary); font-variant-numeric: tabular-nums; }
.cpx-tally .w b { color: var(--cx-warn); }
.cpx-links { display: flex; gap: 6px; }

.cpx-det-b { flex: 1; min-height: 0; overflow-y: auto; padding: 16px 22px; display: flex; flex-direction: column; gap: 10px; background: var(--cx-soft); }
.cpx-det-b > *, .cpx-over > * { flex: none; }
.cpx-sec { margin: 6px 0 0; font-size: 11px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: var(--text-muted); }
.cpx-sec:first-child { margin-top: 0; }
.cpx-tkt { position: relative; display: block; padding: 12px 16px 12px 18px; border: 1px solid var(--cx-line); border-radius: 10px; background: var(--surface);
           text-decoration: none; color: inherit; overflow: hidden; }
.cpx-tkt::before { content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 3px; background: var(--t); }
.cpx-tkt:hover { border-color: var(--border-md); color: inherit; }
.cpx-tkt.sel { border-color: var(--t); box-shadow: 0 0 0 3px color-mix(in srgb, var(--t) 16%, transparent); }
.cpx-tkt.done { opacity: .82; }
.cpx-tkt.done.sel { opacity: 1; }
.cpx-tk { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; font-size: 12px; }
.cpx-no { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 12px; font-weight: 600; color: var(--text-muted); }
.cpx-pill { display: inline-flex; align-items: center; gap: 6px; height: 21px; padding: 0 8px; border-radius: 6px; font-size: 11.5px; font-weight: 700;
            color: var(--t); background: color-mix(in srgb, var(--t) 12%, transparent); }
.cpx-pill i { font-size: 10.5px; }
.cpx-st { display: inline-flex; align-items: center; gap: 6px; font-weight: 600; color: var(--cx-warn); }
.cpx-st::before { content: ''; width: 7px; height: 7px; border-radius: 50%; background: currentColor; }
.cpx-st.done { color: var(--cx-ok); }
.cpx-tk time { margin-left: auto; color: var(--text-muted); font-variant-numeric: tabular-nums; }
.cpx-q { margin-top: 8px; font-size: 14px; line-height: 1.55; color: var(--text-primary); white-space: pre-line; }
.cpx-q strong { font-weight: 600; }
.cpx-q span { display: block; margin-top: 2px; color: var(--text-secondary, var(--text-primary)); }
.cpx-a { margin-top: 10px; padding: 9px 12px; border-radius: 8px; background: var(--cx-soft); font-size: 13px; line-height: 1.5; color: var(--text-primary); white-space: pre-line; }
.cpx-a small { display: block; margin-bottom: 2px; font-size: 11px; font-weight: 600; color: var(--text-muted); white-space: normal; }
.cpx-a.draft { background: transparent; border: 1px dashed var(--border-md); }
.cpx-blank { display: flex; gap: 14px; align-items: flex-start; padding: 6px 2px; }
.cpx-blank .ic { flex: none; width: 38px; height: 38px; border-radius: 10px; display: grid; place-items: center; font-size: 15px; color: var(--cx-ok);
                 background: color-mix(in srgb, var(--cx-ok) 12%, transparent); }
.cpx-blank b { display: block; font-size: 14.5px; color: var(--text-primary); }
.cpx-blank p { margin: 3px 0 0; max-width: 520px; font-size: 12.5px; line-height: 1.5; color: var(--text-muted); }

/* The answer box. */
.cpx-form { flex: none; border-top: 1px solid var(--cx-line); background: var(--surface); }
.cpx-ans { padding: 12px 22px 0; }
.cpx-ans label { display: flex; align-items: baseline; gap: 8px; margin-bottom: 8px; font-size: 13px; font-weight: 600; color: var(--text-primary); min-width: 0; }
.cpx-ans label small { font-weight: 400; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.cpx-quick { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 8px; }
.cpx-quick button { height: 26px; padding: 0 10px; border-radius: 6px; border: 1px solid var(--cx-line); background: var(--cx-soft);
                    font-size: 12px; color: var(--text-secondary, var(--text-primary)); cursor: pointer; }
.cpx-quick button:hover { border-color: var(--border-md); color: var(--text-primary); }
.cpx-ans textarea { display: block; width: 100%; min-height: 72px; padding: 10px 12px; border: 1px solid var(--border-md); border-radius: 10px; background: var(--surface);
                    color: var(--text-primary); font-size: 13.5px; line-height: 1.5; resize: vertical; }
.cpx-ans textarea:focus { outline: none; border-color: var(--cx-ink-2); box-shadow: 0 0 0 3px color-mix(in srgb, var(--cx-ink-2) 16%, transparent); }
.cpx-foot { display: flex; align-items: center; gap: 8px; padding: 10px 22px 14px; }
.cpx-foot .s { flex: 1; min-width: 0; font-size: 12px; color: var(--text-muted); }
.cpx-btn { height: 36px; padding: 0 14px; border-radius: 8px; display: inline-flex; align-items: center; gap: 7px; font-size: 13px; font-weight: 600; text-decoration: none; cursor: pointer;
           border: 1px solid var(--border-md); background: var(--surface); color: var(--text-primary); white-space: nowrap; }
.cpx-btn:hover { background: var(--cx-soft); color: var(--text-primary); }
.cpx-btn.sm { height: 30px; padding: 0 10px; font-size: 12px; }
.cpx-btn.pri { background: var(--cx-ink); border-color: var(--cx-ink); color: var(--cx-on-ink); }
.cpx-btn.pri:hover { opacity: .86; color: var(--cx-on-ink); }
.cpx-btn i { font-size: 11.5px; opacity: .85; }

/* Nobody picked: the month at a glance. */
.cpx-over { overflow-y: auto; padding: 20px 24px; display: flex; flex-direction: column; gap: 24px; }
.cpx-over h3 { display: flex; align-items: baseline; justify-content: space-between; margin: 0 0 10px; font-size: 13px; font-weight: 700; color: var(--text-primary); }
.cpx-over h3 small, .cpx-over h3 a { font-size: 12px; font-weight: 500; color: var(--text-muted); text-decoration: none; }
.cpx-over h3 a { color: var(--cx-ink-2); font-weight: 600; }
.cpx-bars { display: grid; grid-template-columns: max-content 1fr 32px; align-items: center; gap: 9px 14px; font-size: 12.5px; }
.cpx-bars span { color: var(--text-secondary, var(--text-primary)); }
.cpx-bars .tr { height: 8px; border-radius: 4px; background: var(--cx-soft); overflow: hidden; }
.cpx-bars .tr i { display: block; height: 100%; border-radius: 4px; background: var(--t); }
.cpx-bars b { text-align: right; font-variant-numeric: tabular-nums; color: var(--text-primary); }
.cpx-over .cpx-tkt { margin-bottom: 8px; }
.cpx-quiet { font-size: 12.5px; color: var(--text-muted); }

@media (max-width: 1100px) {
    .cpx { height: auto; }
    .cpx-main { grid-template-columns: 1fr; }
    .cpx-det, .cpx-det-b, .cpx-over { overflow: visible; }
    .cpx-list { max-height: 380px; }
}
@media (max-width: 760px) {
    .cpx-strip { width: 100%; } .cpx-strip > div { flex: 1; min-width: 0; padding: 9px 12px; }
    .cpx-tabs { gap: 16px; }
    .cpx-flt { width: 100%; margin-left: 0; flex-wrap: wrap; } .cpx-flt input[type=search] { width: 100%; }
    .cpx-foot { flex-wrap: wrap; } .cpx-foot .s { flex-basis: 100%; }
    .cpx-det-h, .cpx-det-b, .cpx-over { padding: 14px 16px; } .cpx-ans { padding: 12px 16px 0; } .cpx-foot { padding: 10px 16px 12px; }
}
</style>
@endpush

@section('content')
@php
    $keep = array_filter(['show' => $show !== 'all' ? $show : null, 'category' => $category, 'site' => $siteId, 'q' => $q !== '' ? $q : null]);
    $url  = fn (array $over) => route('complaints.index', array_filter(array_merge($keep, $over), fn ($v) => $v !== null && $v !== ''));
    $when = fn ($d) => $d ? ($d->isToday() ? __('Today') . ', ' . $d->format('g:i A') : ($d->isYesterday() ? __('Yesterday') . ', ' . $d->format('g:i A') : $d->format('M j, g:i A'))) : '';
    $short = fn ($d) => $d ? ($d->isToday() ? $d->format('g:i A') : ($d->isYesterday() ? __('Yesterday') : $d->format('M j'))) : '';
    // How long something has waited, once it is worth saying (an hour or more).
    $waited = fn ($d) => $d && $d->lt(now()->subHour()) ? $d->diffForHumans(null, \Carbon\CarbonInterface::DIFF_ABSOLUTE, true) : null;
    $from  = fn ($c) => $c->kiosk ? ($c->kiosk->name ?: $c->kiosk->code) : __('Kiosk');
    $first = fn ($name) => \Illuminate\Support\Str::before($name ?? __('the worker'), ' ');
    $no    = fn ($c) => '#' . str_pad($c->id, 4, '0', STR_PAD_LEFT);
    $split = fn ($m) => array_pad(explode(' — ', (string) $m, 2), 2, null);
    $icons = ['pay' => 'peso-sign', 'attendance' => 'calendar-check', 'safety' => 'helmet-safety', 'equipment' => 'screwdriver-wrench', 'treatment' => 'people-arrows', 'other' => 'comment'];
    $canDtr = \Illuminate\Support\Facades\Route::has('payroll-reports.dtr') && auth()->user()?->canAccessModule('payroll-reports');
    $avg = $stats['avgHours'];
    // Answers the office writes again and again, one tap into the box.
    $quick = [__('Checked and fixed in your DTR.'), __('Please see the office tomorrow.'), __('We are looking into it.'), __('Reported to the site engineer.')];
@endphp
<div class="cpx">
    <div class="cpx-top">
        <div>
            <div class="cpx-eyebrow">{{ __('Worker reports') }}</div>
            <h1>{{ __('Complaints') }}</h1>
            <p>{{ __("Every worker is listed; those waiting for an answer come first. Your answer appears on the kiosk the next time they scan.") }}</p>
        </div>
        <div class="cpx-strip">
            <div><span>{{ __('Open') }}</span><b>{{ $stats['open'] }}</b></div>
            <div class="{{ $stats['safety'] ? 'alarm' : '' }}"><span>{{ __('Safety open') }}</span><b>{{ $stats['safety'] }}</b></div>
            <div><span>{{ __('Answered this week') }}</span><b>{{ $stats['week'] }}</b></div>
            <div><span>{{ __('Avg. answer time') }}</span><b>@if($avg === null)—@elseif($avg < 1){{ round($avg * 60) }}<small>min</small>@else{{ $avg }}<small>h</small>@endif</b></div>
        </div>
    </div>

    @include('modules._flash')

    <form class="cpx-bar" method="GET" action="{{ route('complaints.index') }}">
        @if($show !== 'all')<input type="hidden" name="show" value="{{ $show }}">@endif
        <nav class="cpx-tabs" aria-label="{{ __('Show') }}">
            <a class="{{ $show === 'all' ? 'on' : '' }}" href="{{ $url(['show' => null]) }}">{{ __('Everyone') }}<em>{{ $counts['all'] }}</em></a>
            <a class="{{ $show === 'open' ? 'on' : '' }} {{ $counts['open'] ? 'hot' : '' }}" href="{{ $url(['show' => 'open']) }}">{{ __('Needs an answer') }}<em>{{ $counts['open'] }}</em></a>
            <a class="{{ $show === 'reported' ? 'on' : '' }}" href="{{ $url(['show' => 'reported']) }}">{{ __('Has reported') }}<em>{{ $counts['reported'] }}</em></a>
        </nav>
        <div class="cpx-flt">
            <select name="site" onchange="this.form.submit()" aria-label="{{ __('Site') }}">
                <option value="">{{ __('All sites') }}</option>
                @foreach($sites as $s)<option value="{{ $s->id }}" @selected($siteId === $s->id)>{{ $s->name }}</option>@endforeach
            </select>
            <select name="category" onchange="this.form.submit()" aria-label="{{ __('Topic') }}">
                <option value="">{{ __('All topics') }}</option>
                @foreach($categories as $k => $label)<option value="{{ $k }}" @selected($category === $k)>{{ $label }}</option>@endforeach
            </select>
            <input type="search" name="q" value="{{ $q }}" placeholder="{{ __('Search an employee') }}" aria-label="{{ __('Employee') }}">
            @if($keep)<a class="reset" href="{{ route('complaints.index') }}">{{ __('Reset') }}</a>@endif
        </div>
    </form>

    <div class="cpx-main">
        {{-- The workers --}}
        <div class="cpx-card cpx-list">
            @if($waiting->isNotEmpty())
                <div class="cpx-grp hot"><i class="fas fa-hourglass-half"></i><span>{{ __('Needs an answer') }}</span><em>{{ $waiting->count() }}</em></div>
                @foreach($waiting as $e)
                    @php $o = $e->oldest_open; [$ask] = $split($o?->message); $w = $waited($o?->created_at); @endphp
                    <a class="cpx-emp open {{ $employee && $employee->id === $e->id ? 'sel' : '' }}" data-t="{{ $o?->category }}" href="{{ $url(['e' => $e->id]) }}">
                        <span class="cpx-ico">@include('partials.worker-icon', ['size' => '60%'])</span>
                        <span class="cpx-b">
                            <span class="cpx-n"><span>{{ $e->name }}</span><span class="cpx-cnt" title="{{ __('Open') }}">{{ $e->open_n }}</span></span>
                            <span class="cpx-sub">{{ collect([$e->position, $e->site?->name])->filter()->implode(' · ') }}</span>
                            <span class="cpx-tx">{{ $ask }}</span>
                            <span class="cpx-meta"><span class="cpx-t">{{ $o?->categoryLabel() }}</span>@if($w)<span class="cpx-wait">{{ __('waiting :t', ['t' => $w]) }}</span>@endif</span>
                        </span>
                    </a>
                @endforeach
            @endif
            @foreach($bySite as $siteName => $people)
                <div class="cpx-grp"><i class="fas fa-location-dot"></i><span>{{ $siteName }}</span><em>{{ $people->count() }}</em></div>
                @foreach($people as $e)
                    <a class="cpx-emp {{ $employee && $employee->id === $e->id ? 'sel' : '' }}" href="{{ $url(['e' => $e->id]) }}">
                        <span class="cpx-ico">@include('partials.worker-icon', ['size' => '60%'])</span>
                        <span class="cpx-b">
                            <span class="cpx-n"><span>{{ $e->name }}</span>@if($e->total_n)<span class="cpx-cnt mute" title="{{ __(':n reports, all answered', ['n' => $e->total_n]) }}">{{ $e->total_n }}</span>@endif</span>
                            <span class="cpx-sub">{{ $e->position ?: __('No position') }}@if($e->last_at) · {{ __('last report :d', ['d' => $short($e->last_at)]) }}@endif</span>
                        </span>
                    </a>
                @endforeach
            @endforeach
            @if($waiting->isEmpty() && $bySite->isEmpty())
                <div class="cpx-nobody">{{ $keep ? __('Nobody matches these filters.') : __('No workers yet.') }} @if($keep)<a href="{{ route('complaints.index') }}">{{ __('Clear filters') }}</a>@endif</div>
            @endif
        </div>

        {{-- A worker opened, or the month at a glance --}}
        @if($employee)
            @php
                $openN = $thread->filter->isOpen()->count();
                $doneN = $thread->count() - $openN;
            @endphp
            <aside class="cpx-card cpx-det">
                <div class="cpx-det-h">
                    <span class="cpx-ico">@include('partials.worker-icon', ['size' => '60%'])</span>
                    <div class="cpx-who">
                        <b>{{ $employee->name }}</b>
                        <small>#{{ str_pad($employee->id, 4, '0', STR_PAD_LEFT) }}{{ $employee->position ? ' · ' . $employee->position : '' }}{{ $employee->site ? ' · ' . $employee->site->name : '' }}</small>
                    </div>
                    <div class="cpx-tally">
                        <div class="{{ $openN ? 'w' : '' }}"><b>{{ $openN }}</b>{{ __('open') }}</div>
                        <div><b>{{ $doneN }}</b>{{ __('answered') }}</div>
                    </div>
                    <div class="cpx-links">
                        <a class="cpx-btn sm" href="{{ route('employees.show', $employee->id) }}"><i class="fas fa-id-card"></i>{{ __('Profile') }}</a>
                        @if($canDtr)<a class="cpx-btn sm" href="{{ route('payroll-reports.index', ['report' => 'dtr', 'q' => $employee->name]) }}"><i class="fas fa-calendar-days"></i>{{ __('DTR') }}</a>@endif
                    </div>
                </div>

                <div class="cpx-det-b">
                    @forelse($thread as $c)
                        @if($loop->first && $c->isOpen())<div class="cpx-sec">{{ __('Waiting for an answer') }}</div>@endif
                        @if(! $c->isOpen() && ($loop->first || $thread[$loop->index - 1]->isOpen()))<div class="cpx-sec">{{ __('Answered') }}</div>@endif
                        @php [$ask, $note] = $split($c->message); $w = $c->isOpen() ? $waited($c->created_at) : null; @endphp
                        <a class="cpx-tkt {{ $selected && $selected->id === $c->id ? 'sel' : '' }} {{ $c->isOpen() ? '' : 'done' }}" data-t="{{ $c->category }}" href="{{ $url(['e' => $employee->id, 'c' => $c->id]) }}">
                            <div class="cpx-tk">
                                <span class="cpx-no">{{ $no($c) }}</span>
                                <span class="cpx-pill"><i class="fas fa-{{ $icons[$c->category] ?? 'comment' }}"></i>{{ $c->categoryLabel() }}</span>
                                @if($c->isOpen())<span class="cpx-st">{{ __('Open') }}@if($w) · {{ __('waiting :t', ['t' => $w]) }}@endif</span>
                                @else<span class="cpx-st done">{{ __('Resolved') }}</span>@endif
                                <time>{{ $when($c->created_at) }} · {{ $from($c) }}</time>
                            </div>
                            <div class="cpx-q">@if($note)<strong>{{ $ask }}</strong><span>{{ $note }}</span>@else{{ $c->message }}@endif</div>
                            @if($c->response)
                                <div class="cpx-a {{ $c->isOpen() ? 'draft' : '' }}"><small>@if($c->isOpen()){{ __('Answer saved, not sent yet') }}@else{{ __('Answered by :name', ['name' => $c->resolver->name ?? __('the office')]) }} · {{ $when($c->resolved_at) }}@endif</small>{{ $c->response }}</div>
                            @endif
                        </a>
                    @empty
                        <div class="cpx-blank">
                            <span class="ic"><i class="fas fa-check"></i></span>
                            <div><b>{{ __('No reports from :name', ['name' => $first($employee->name)]) }}</b>
                                <p>{{ __("When :name sends one from the kiosk's Report tab, it shows here and you are alerted in the bell.", ['name' => $first($employee->name)]) }}</p></div>
                        </div>
                    @endforelse
                </div>

                @if($selected)
                    <form class="cpx-form" method="POST" action="{{ route('complaints.update', $selected) }}">
                        @csrf @method('PATCH')
                        @foreach($keep as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
                        <div class="cpx-ans">
                            <label for="cpxAns">{{ $selected->isOpen() ? __('Answer :no', ['no' => $no($selected)]) : __('Edit the answer to :no', ['no' => $no($selected)]) }}<small>{{ $split($selected->message)[0] }} · {{ __('shown to :name at the kiosk on their next scan', ['name' => $first($employee->name)]) }}</small></label>
                            @if($selected->isOpen())
                                <div class="cpx-quick">@foreach($quick as $line)<button type="button" data-line="{{ $line }}">{{ $line }}</button>@endforeach</div>
                            @endif
                            <textarea id="cpxAns" name="response" maxlength="1000" placeholder="{{ __('What was done, or what they should do next') }}">{{ old('response', $selected->response) }}</textarea>
                        </div>
                        <div class="cpx-foot">
                            <div class="s">@if($selected->isOpen()){{ $selected->response ? __('Answer saved, not resolved yet') : __('Not answered yet') }}@else{{ __('Resolved') }} · {{ $when($selected->resolved_at) }}@if($selected->resolver) · {{ $selected->resolver->name }}@endif @endif</div>
                            @if($selected->isOpen())
                                <button class="cpx-btn" type="submit" name="action" value="save">{{ __('Save answer') }}</button>
                                <button class="cpx-btn pri" type="submit" name="action" value="resolve"><i class="fas fa-paper-plane"></i>{{ __('Answer & resolve') }}</button>
                            @else
                                <button class="cpx-btn" type="submit" name="action" value="reopen"><i class="fas fa-rotate-left"></i>{{ __('Reopen') }}</button>
                                <button class="cpx-btn pri" type="submit" name="action" value="save">{{ __('Update answer') }}</button>
                            @endif
                        </div>
                    </form>
                @endif
            </aside>
        @else
            <aside class="cpx-card cpx-over">
                <div class="cpx-blank">
                    <span class="ic"><i class="fas fa-check"></i></span>
                    <div><b>{{ __('Nobody is waiting for an answer') }}</b>
                        <p>{{ __('Pick a worker on the left to see what they have reported. New reports from the kiosk put the worker on top of the list, with an alert in the bell.') }}</p></div>
                </div>
                <section>
                    <h3>{{ __('Reported in the last 30 days') }}<small>{{ __(':n in all', ['n' => $topics->sum()]) }}</small></h3>
                    @if($topics->isEmpty())
                        <p class="cpx-quiet">{{ __('Nothing reported in the last 30 days.') }}</p>
                    @else
                        <div class="cpx-bars">
                            @foreach($topics as $k => $n)
                                <span>{{ $categories[$k] ?? ucfirst($k) }}</span>
                                <span class="tr" data-t="{{ $k }}"><i style="width: {{ max(4, round($n / max(1, $topics->max()) * 100)) }}%"></i></span>
                                <b>{{ $n }}</b>
                            @endforeach
                        </div>
                    @endif
                </section>
                <section>
                    <h3>{{ __('Answered last') }}<a href="{{ route('complaints.index', ['show' => 'reported']) }}">{{ __('Everyone who has reported') }}</a></h3>
                    @forelse($recent as $c)
                        @php [$ask] = $split($c->message); @endphp
                        <a class="cpx-tkt done" data-t="{{ $c->category }}" href="{{ route('complaints.index', ['e' => $c->employee_id, 'c' => $c->id]) }}">
                            <div class="cpx-tk"><span class="cpx-no">{{ $no($c) }}</span><b>{{ $c->employee->name ?? __('Unknown') }}</b><span class="cpx-pill">{{ $c->categoryLabel() }}</span><time>{{ $short($c->resolved_at) }}</time></div>
                            <div class="cpx-a"><small>{{ $ask }}</small>{{ $c->response ?: __('Resolved without a written answer.') }}</div>
                        </a>
                    @empty
                        <p class="cpx-quiet">{{ __('Nothing answered yet.') }}</p>
                    @endforelse
                </section>
            </aside>
        @endif
    </div>
</div>

@push('scripts')
<script>
// A quick answer goes into the box, after anything already written.
document.querySelectorAll('.cpx-quick [data-line]').forEach(b => b.addEventListener('click', () => {
    const box = document.getElementById('cpxAns');
    box.value = box.value.trim() ? box.value.trim() + ' ' + b.dataset.line : b.dataset.line;
    box.focus();
}));
// Keep the picked worker in view in a long list.
document.querySelector('.cpx-emp.sel')?.scrollIntoView({ block: 'nearest' });
</script>
@endpush
@endsection
