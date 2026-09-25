@extends('layouts')

@section('page_title', 'Attendance Monitoring')

@push('styles')
<style>
/* ── Attendance Monitoring ────────────────────────────────────────────────
   Built on the design tokens alone, so one block serves both themes. The
   break has no token of its own; it borrows the palette's violet, which is
   defined for light and dark alike. */
/* The full width the layout gives, as every other page takes it — no padding
   of its own on top of the layout's. */
.attendance-container.atm { max-width:none; width:100%; margin:0; padding:0; }

.atm {
    --atm-brk:      var(--violet);
    --atm-brk-soft: var(--violet-soft);
    --atm-hatch:    color-mix(in srgb, var(--violet) 26%, transparent);
    --atm-track:    var(--border);
}

/* ── Heading ─────────────────────────────────────────────────────────────── */
.atm-head {
    display:flex; justify-content:space-between; align-items:center;
    gap:12px; flex-wrap:wrap; margin-bottom:16px;
}
.atm-head-right { display:flex; flex-direction:column; align-items:flex-end; gap:8px; max-width:100%; }
.atm-date { display:flex; align-items:center; gap:7px; font-size:13px; color:var(--text-muted); }

/* The shifts in one line, and the way to change them. The page reads every
   day against these hours, so they are stated where the reading happens. */
.atm-sched {
    display:flex; align-items:center; gap:12px; max-width:100%;
    padding:8px 10px 8px 12px; border:1px solid var(--border); border-radius:var(--radius-lg);
    background:var(--surface); box-shadow:var(--shadow-xs);
    color:inherit; text-decoration:none; text-align:left;
}
a.atm-sched:hover { border-color:var(--brand); color:inherit; text-decoration:none; }
.atm-sched > i { font-size:16px; color:var(--brand); flex:none; }
.atm-sched-txt { display:flex; flex-direction:column; gap:1px; min-width:0; }
.atm-sched-txt b { font-size:12.5px; color:var(--text-primary); }
.atm-sched-txt small { font-size:11.5px; color:var(--text-muted); }
.atm-sched-go {
    display:flex; align-items:center; gap:6px; white-space:nowrap;
    font-size:12px; font-weight:700; color:var(--brand);
    background:var(--brand-subtle); border-radius:var(--radius-sm); padding:6px 9px 6px 10px;
}
.atm-sched-go i { font-size:10px; }

/* ── Cards ───────────────────────────────────────────────────────────────
   Each card asks a question, so each card is a link — to the same status the
   control under the tabs sets. Anchors, not buttons: the answer is a URL the
   office can bookmark or send to somebody. */
.atm-stats { display:grid; grid-template-columns:repeat(4, minmax(0, 1fr)); gap:12px; margin-bottom:16px; }
.atm-stat {
    display:flex; flex-direction:column; gap:6px; padding:14px 16px;
    background:var(--surface); border:1px solid var(--border); border-radius:var(--radius-lg);
    box-shadow:var(--shadow-xs); color:inherit; text-decoration:none;
    transition:border-color .15s, box-shadow .15s;
}
.atm-stat:hover { border-color:var(--border-md); color:inherit; text-decoration:none; }
.atm-stat.is-active { border-color:var(--atm-dot); box-shadow:inset 0 0 0 1px var(--atm-dot); }
.atm-stat-lbl {
    display:flex; align-items:center; gap:8px;
    font-size:11px; font-weight:700; letter-spacing:.08em; text-transform:uppercase;
    color:var(--text-secondary);
}
.atm-dot { width:8px; height:8px; border-radius:50%; background:var(--atm-dot); flex:none; }
.atm-stat-num { font-size:28px; font-weight:800; line-height:1.1; color:var(--text-primary); font-variant-numeric:tabular-nums; }
.atm-stat-sub { font-size:12px; color:var(--text-muted); }
.atm .is-brand { --atm-dot:var(--brand); }
.atm .is-good  { --atm-dot:var(--success); }
.atm .is-brk   { --atm-dot:var(--atm-brk); }
.atm .is-bad   { --atm-dot:var(--danger); }

/* ── The records card: tabs, filters, the list and its key ──────────────── */
.atm-card {
    display:flex; flex-direction:column; min-width:0;
    background:var(--surface); border:1px solid var(--border); border-radius:var(--radius-lg);
    box-shadow:var(--shadow-xs);
}
.atm-tabs { display:flex; gap:4px; margin:0; padding:6px 16px 0; border-bottom:1px solid var(--border); }
.atm-tab {
    display:flex; align-items:center; gap:8px; margin-bottom:-1px; padding:10px 14px;
    border:0; border-bottom:2px solid transparent; border-radius:0; background:none;
    font-size:13.5px; font-weight:600; color:var(--text-muted);
}
.atm-tab:hover { color:var(--text-primary); }
.atm-tab.active { color:var(--brand); border-bottom-color:var(--brand); }
.atm-count { font-size:11px; font-weight:700; border-radius:999px; padding:1px 7px; background:var(--danger-soft); color:var(--danger); }
.atm-count[hidden] { display:none; }

.atm-toolbar { display:flex; align-items:center; gap:10px; flex-wrap:wrap; margin:0; padding:14px 16px; }
.atm-field {
    display:flex; align-items:center; gap:8px; height:38px; padding:0 10px;
    border:1px solid var(--border); border-radius:var(--radius-md); background:var(--surface);
}
.atm-field[hidden] { display:none; }
.atm-field > i { font-size:13px; color:var(--text-muted); }
.atm-field input, .atm-field select {
    height:36px; min-width:0; border:0; outline:none; box-shadow:none; background:transparent;
    font-size:13px; font-weight:500; color:var(--text-primary);
}
.atm-field input { width:170px; }
.atm-field input::placeholder { color:var(--text-muted); }
/* The open list is drawn by the browser, and a background on the select is
   enough for Chrome to stop theming it — the popup came back white under
   near-white text in dark mode. Both colours are stated so it follows. */
.atm-field select option { background-color:var(--bg-elevated); color:var(--text-primary); }
.atm-field:focus-within { border-color:var(--brand); box-shadow:0 0 0 3px var(--brand-subtle); }
/* The ring is the field's, drawn above. The theme's own focus ring on the
   input inside it read as a second box within the first. */
html[data-bs-theme] .atm-field input:focus-visible,
html[data-bs-theme] .atm-field select:focus-visible { box-shadow:none !important; outline:none !important; border-radius:0; }

/* A row of choices where one is always on. Radios underneath, so the form
   submits them like any other field and the keyboard moves between them. */
.atm-seg { display:flex; overflow:hidden; border:1px solid var(--border); border-radius:var(--radius-md); background:var(--surface); }
.atm-seg label { margin:0; }
.atm-seg label[hidden] { display:none; }
.atm-seg input { position:absolute; opacity:0; pointer-events:none; }
.atm-seg span {
    display:flex; align-items:center; gap:6px; height:36px; padding:0 12px; cursor:pointer;
    font-size:12.5px; font-weight:600; color:var(--text-muted); white-space:nowrap;
}
.atm-seg span:hover { color:var(--text-primary); }
.atm-seg input:checked + span { background:var(--brand-subtle); color:var(--brand); }
.atm-seg input:focus-visible + span { outline:2px solid var(--brand); outline-offset:-2px; }

.atm-btn {
    display:inline-flex; align-items:center; gap:6px; padding:6px 12px; cursor:pointer;
    border:1px solid var(--border-md); border-radius:var(--radius-sm); background:var(--surface);
    font-size:12.5px; font-weight:600; color:var(--text-primary);
}
.atm-btn:hover { background:var(--bg-subtle); }
.atm-btn.pri { background:var(--brand); border-color:var(--brand); color:#fff; }
.atm-btn.pri:hover { background:var(--brand-strong); border-color:var(--brand-strong); }
.atm-btn.danger { background:var(--danger-soft); border-color:color-mix(in srgb, var(--danger) 35%, transparent); color:var(--danger); }
.atm-btn:disabled { opacity:.5; cursor:not-allowed; }
.atm-toolbar .atm-btn { height:38px; }

/* While a filter is being fetched the lists dim, so the reader does not take
   the old rows for the answer. */
.atm-card.is-loading .tab-content { opacity:.55; transition:opacity .15s; }

/* ── The list ────────────────────────────────────────────────────────────
   In the page, not boxed: the whole section scrolls with it, cards and
   filters included, and the list is as long as the crew. Wide enough for
   its columns; narrower than that, it scrolls sideways inside the card. */
.atm-scroll { overflow-x:auto; }
.atm-table { width:100%; min-width:1180px; border-collapse:separate; border-spacing:0; }
.atm-table thead th {
    padding:10px 12px; text-align:left; white-space:nowrap;
    font-size:10.5px; font-weight:700; letter-spacing:.1em; text-transform:uppercase;
    color:var(--text-muted); background:var(--bg-subtle); border-bottom:1px solid var(--border);
}
.atm-table thead tr.atm-grp th { padding:8px 12px 0; border-bottom:0; font-size:10px; }
.atm-grp th.s { text-align:center; }
.atm-grp th.s span { display:block; padding-bottom:5px; border-bottom:1px solid var(--border); }
.atm-table tbody td {
    padding:12px; vertical-align:middle; font-size:13px; color:var(--text-primary);
    background:var(--surface); border-bottom:1px solid var(--border);
}
.atm-table td.atm-sep, .atm-table th.atm-sep { border-left:1px dashed var(--border); }
tr.atm-row { cursor:pointer; }
tr.atm-row:hover td, tr.atm-row.is-open td { background:var(--bg-subtle); }
tr.atm-row:focus-visible { outline:2px solid var(--brand); outline-offset:-2px; }
tr.atm-dayhead td {
    padding:8px 12px; font-size:12px; font-weight:700; letter-spacing:.03em;
    color:var(--text-secondary); background:var(--bg);
}
.atm-empty { padding:40px 16px !important; text-align:center; color:var(--text-muted) !important; }
.atm-empty i { display:block; margin-bottom:8px; font-size:1.6rem; opacity:.35; }

.atm-emp { display:flex; align-items:center; gap:10px; min-width:190px; }
.atm-ini {
    display:grid; place-items:center; flex:none; width:34px; height:34px; border-radius:50%;
    font-size:12px; font-weight:700; color:var(--text-secondary);
    background:var(--bg-subtle); border:1px solid var(--border);
}
.atm-emp b { display:block; font-size:13.5px; color:var(--text-primary); }
.atm-emp small { font-size:12px; color:var(--text-muted); }
.atm-site {
    display:inline-flex; align-items:center; gap:4px; margin-left:6px; padding:1px 6px; white-space:nowrap;
    font-size:11px; font-weight:600; color:var(--text-secondary);
    border:1px solid var(--border); border-radius:6px;
}
.atm-site i { font-size:9px; color:var(--text-muted); }

.atm-shift { display:inline-flex; align-items:center; gap:6px; padding:3px 8px; border-radius:7px; font-size:12px; font-weight:700; white-space:nowrap; }
.atm-shift i { font-size:11px; }
.atm-shift.day   { background:var(--warning-soft); color:var(--warning); }
.atm-shift.night { background:var(--brand-subtle); color:var(--brand); }
.atm-shift-hrs { display:block; margin-top:4px; font-size:11px; color:var(--text-muted); white-space:nowrap; }

.atm-punch { display:flex; flex-direction:column; gap:3px; min-width:84px; }
.atm-t { display:flex; align-items:center; gap:5px; font-size:13px; font-weight:600; font-variant-numeric:tabular-nums; white-space:nowrap; }
.atm-t.is-mute { color:var(--text-muted); font-weight:500; }
.atm-edited { display:inline-block; width:6px; height:6px; border-radius:50%; background:var(--brand); }
.atm-tag { display:inline-block; width:max-content; padding:1px 6px; border-radius:5px; font-size:10.5px; font-weight:700; white-space:nowrap; }
.atm-tag.warn { background:var(--warning-soft); color:var(--warning); }
.atm-tag.bad  { background:var(--danger-soft);  color:var(--danger); }
.atm-tag.good { background:var(--success-soft); color:var(--success); }
.atm-tag.brk  { background:var(--atm-brk-soft); color:var(--atm-brk); }
.atm-tag.mute { padding-left:0; font-weight:600; color:var(--text-muted); }

/* The day against its shift, an hour either side. Positions come from the
   server as percentages, so the bar is drawn by CSS alone. */
.atm-tl { position:relative; width:230px; height:22px; }
.atm-tl > span { position:absolute; display:block; }
.atm-tl .base { top:8px; height:6px; border-radius:3px; background:var(--atm-track); }
.atm-tl .bw   { top:5px; height:12px; border-radius:3px; background:repeating-linear-gradient(135deg, var(--atm-hatch) 0 4px, transparent 4px 8px); }
.atm-tl .w    { top:7px; height:8px; border-radius:4px; background:var(--brand); }
.atm-tl .w.live { background:linear-gradient(90deg, var(--brand), var(--brand) 70%, color-mix(in srgb, var(--brand) 35%, transparent)); }
.atm-tl .w.miss { top:6px; height:10px; background:transparent; border:1.5px dashed var(--danger); }
.atm-tl .w.br { background:var(--atm-brk); }
.atm-tl .w.ob { background:var(--warning); }
.atm-tl .late { top:10px; height:2px; background:var(--danger); }
.atm-tl .now  { top:0; bottom:0; width:2px; border-radius:1px; background:var(--text-primary); }
.atm-tl .now::after { content:""; position:absolute; top:-3px; left:-3px; width:8px; height:8px; border-radius:50%; background:var(--text-primary); }
/* The shift's own times under the bar, each at the point it marks: the start
   reads from its tick, the end up to its tick, the break centred on it. */
.atm-tlax { position:relative; width:230px; height:13px; margin-top:2px; font-size:10px; color:var(--text-muted); font-variant-numeric:tabular-nums; }
.atm-tlax span { position:absolute; top:0; white-space:nowrap; }
.atm-tlax .is-mid { transform:translateX(-50%); }
.atm-tlax .is-end { transform:translateX(-100%); }

.atm-hrs { font-size:13px; font-weight:600; font-variant-numeric:tabular-nums; white-space:nowrap; }
.atm-hrs small { display:block; font-size:11px; font-weight:500; color:var(--text-muted); }
.atm-hrs small.d-inline { display:inline; }
.atm-hrs .atm-tag { display:block; margin-top:4px; }

.atm-status { display:flex; align-items:center; justify-content:space-between; gap:8px; }
.atm-status-tags { display:flex; flex-direction:column; align-items:flex-start; gap:4px; }
.atm-pill { display:inline-flex; align-items:center; gap:6px; padding:4px 10px; border-radius:999px; font-size:12px; font-weight:700; white-space:nowrap; }
.atm-pill .atm-dot { width:7px; height:7px; }
.atm-pill.good    { background:var(--success-soft); color:var(--success); --atm-dot:var(--success); }
.atm-pill.warn    { background:var(--warning-soft); color:var(--warning); --atm-dot:var(--warning); }
.atm-pill.bad     { background:var(--danger-soft);  color:var(--danger);  --atm-dot:var(--danger); }
.atm-pill.brk     { background:var(--atm-brk-soft); color:var(--atm-brk); --atm-dot:var(--atm-brk); }
.atm-pill.neutral { background:var(--bg-subtle);    color:var(--text-muted); --atm-dot:var(--text-muted); }
.atm-pill.live .atm-dot { animation:atm-pulse 1.6s ease-in-out infinite; }
@keyframes atm-pulse { 50% { opacity:.35; } }
@media (prefers-reduced-motion: reduce) { .atm-pill.live .atm-dot { animation:none; } }
.atm-chev { font-size:11px; color:var(--text-muted); transition:transform .15s; }
tr.is-open .atm-chev { transform:rotate(90deg); }

/* ── Under a row: its scans, and its hours or its fixes ─────────────────── */
.atm-table tr.atm-detail td { padding:4px 12px 16px; background:var(--bg-subtle); cursor:default; }
.atm-dgrid { display:grid; grid-template-columns:minmax(0, 1.1fr) minmax(0, 1fr); gap:16px; }
.atm-dbox {
    display:flex; flex-direction:column; gap:10px; padding:14px 16px;
    background:var(--surface); border:1px solid var(--border); border-radius:var(--radius-lg);
}
.atm-dbox h4 { margin:0; font-size:11.5px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:var(--text-muted); }
.atm-scans { display:flex; flex-direction:column; margin:0; padding:0; list-style:none; }
.atm-scans li {
    display:grid; grid-template-columns:80px minmax(0, 1fr) auto; align-items:center; gap:10px;
    padding:7px 0; font-size:13px; border-bottom:1px dashed var(--border);
}
.atm-scans li:last-child { border-bottom:0; }
.atm-scans .k { display:block; font-size:12px; color:var(--text-muted); }
.atm-fix {
    display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin:0; padding:10px 12px;
    background:var(--danger-soft); border:1px solid color-mix(in srgb, var(--danger) 30%, transparent);
    border-radius:var(--radius-md);
}
.atm-fix b { flex:1 1 100%; font-size:13px; color:var(--text-primary); }
.atm-fix small { flex:1 1 100%; font-size:12px; color:var(--text-secondary); }
.atm-fix input[type=time] {
    padding:5px 8px; border:1px solid var(--border-md); border-radius:var(--radius-sm);
    background:var(--surface); color:var(--text-primary); color-scheme:inherit; font-variant-numeric:tabular-nums;
}
.atm-sum { display:grid; grid-template-columns:repeat(3, minmax(0, 1fr)); gap:10px; }
.atm-sum > div { display:flex; flex-direction:column; gap:2px; }
.atm-sum span { font-size:10.5px; font-weight:700; letter-spacing:.07em; text-transform:uppercase; color:var(--text-muted); }
.atm-sum b { font-size:15px; font-variant-numeric:tabular-nums; color:var(--text-primary); }
.atm-sum b.is-good { color:var(--success); }
.atm-note { margin:0; font-size:12px; color:var(--text-muted); }

.atm-legend {
    display:flex; flex-wrap:wrap; gap:8px 16px; padding:12px 16px;
    border-top:1px solid var(--border); font-size:12px; color:var(--text-muted);
}
.atm-legend span { display:flex; align-items:center; gap:6px; }
.atm-sw { display:inline-block; width:18px; height:7px; border-radius:3px; }

/* The card ends at the bottom of the screen, so its pager lands under the
   floating chat button (50px across, 28px in from the right). Kept clear of
   it, or the next-page arrow cannot be clicked. */
.att-pager { padding:0 76px 0 16px; }
.att-pager nav { padding-top:10px; }
.att-pager .pagination { margin-bottom:0; }


/* A laptop screen: the times, the hours and the status are what the row is
   for, so the timeline gives way and the cells close up rather than
   pushing Status off the side. */
@media (max-width:1440px) {
    .atm-col-tl { display:none; }
    .atm-table { min-width:940px; }
    .atm-table thead th, .atm-table tbody td { padding-left:9px; padding-right:9px; }
    .atm-punch { min-width:74px; }
}
@media (max-width:1100px) {
    .atm-stats { grid-template-columns:repeat(2, minmax(0, 1fr)); }
}
@media (max-width:700px) {
    .atm-head-right { align-items:stretch; width:100%; }
    .atm-sched-go { display:none; }
    .atm-toolbar { padding:12px; }
    .atm-field, .atm-field input { width:100%; }
    .atm-field input { flex:1; }
    .atm-seg { max-width:100%; overflow-x:auto; }
    .atm-dgrid { grid-template-columns:minmax(0, 1fr); }
}
@media (max-width:420px) {
    .atm-stats { grid-template-columns:minmax(0, 1fr); }
}
</style>
@endpush

@section('content')
@use('App\Support\WorkSchedule')
@php
    // A card is a question, and its answer is a list of people. Clicking one
    // narrows both tables to the rows behind its number; clicking it again
    // shows everybody. The site, shift and search already chosen are carried
    // along; the page number is not.
    $cardBase = request()->except(['view', 'tab', 'page']);
    $cardUrl  = fn (string $v) => route('attendance', $cardBase + ['view' => $todayView === $v ? 'all' : $v]);
    $cardOn   = fn (string $v) => $todayView === $v;

    // The status the control shows is the open tab's: each has its own
    // default until somebody picks one.
    $shownView = $openTab === 'history' ? $historyView : $todayView;

    // The shifts in a line: each one's hours, then the break and the regular
    // hours when every shift agrees on them.
    $scheduled = $shifts->filter->hasSchedule()->values();
    $hoursOf   = fn ($s) => $s->name . ' ' . WorkSchedule::label(\Carbon\Carbon::parse($s->starts_at))
                          . '–' . WorkSchedule::label(\Carbon\Carbon::parse($s->endsAt()));
    $sameBreak = $scheduled->pluck('break_minutes')->unique()->count() === 1;
    $sameReg   = $scheduled->pluck('regular_minutes')->unique()->count() === 1 && $scheduled->first()?->regular_minutes !== null;
    $schedLine = $scheduled->map($hoursOf)
        ->when($scheduled->isNotEmpty() && $sameBreak, fn ($l) => $l->push(__('Break') . ' ' . WorkSchedule::duration($scheduled->first()->break_minutes)))
        ->when($scheduled->isNotEmpty() && $sameReg, fn ($l) => $l->push(__('OT after') . ' ' . WorkSchedule::duration($scheduled->first()->regular_minutes)))
        ->implode(' · ');
    $canEdit = auth()->user()?->isAdmin();

    $statuses = [
        'all'        => __('All'),
        'clocked-in' => __('Working'),
        'break'      => __('On break'),
        'missed'     => __('Needs review'),
        'done'       => __('Completed'),
    ];
@endphp

<div class="attendance-container atm">

    <div class="atm-head">
        <h3 class="attendance-title mb-0">{{ __('Attendance Monitoring') }}</h3>

        <div class="atm-head-right">
            <span class="atm-date"><i class="fas fa-calendar-day"></i>{{ now()->format('l, m/d/Y') }}</span>

            @if($scheduled->isNotEmpty())
                @if($canEdit)
                    <a class="atm-sched" href="{{ route('settings.index', ['tab' => 'attendance']) }}"
                       aria-label="{{ __('Edit the shift schedule in Payroll Settings') }}">
                @else
                    <div class="atm-sched">
                @endif
                        <i class="far fa-clock"></i>
                        <span class="atm-sched-txt"><b>{{ __('Shift schedule') }}</b><small>{{ $schedLine }}</small></span>
                        @if($canEdit)
                            <span class="atm-sched-go">{{ __('Edit in Payroll Settings') }}<i class="fas fa-chevron-right"></i></span>
                        @endif
                @if($canEdit) </a> @else </div> @endif
            @endif
        </div>
    </div>

    {{-- ── Cards ────────────────────────────────────────────────────────────
         They follow the site and shift chosen below, so the numbers and the
         rows always describe the same crew. They do not follow the status or
         the search, which narrow the lists to a question about that crew. --}}
    <div class="atm-stats" id="attStats" data-live="attendance employees">
        <a class="atm-stat is-brand {{ $cardOn('present') ? 'is-active' : '' }}" href="{{ $cardUrl('present') }}" data-view="present"
           @if($cardOn('present')) aria-current="true" @endif>
            <span class="atm-stat-lbl"><span class="atm-dot"></span>{{ __('Present today') }}</span>
            <span class="atm-stat-num">{{ $presentToday }}</span>
            <span class="atm-stat-sub">
                {{ __('Scanned in') }}@if($presentToday) · {{ max(0, $presentToday - $nightCrew) }} {{ __('day') }}, {{ $nightCrew }} {{ __('night') }}@endif
            </span>
        </a>
        <a class="atm-stat is-good {{ $cardOn('clocked-in') ? 'is-active' : '' }}"
           href="{{ $cardUrl('clocked-in') }}" data-view="clocked-in"
           @if($cardOn('clocked-in')) aria-current="true" @endif>
            <span class="atm-stat-lbl"><span class="atm-dot"></span>{{ __('Working now') }}</span>
            <span class="atm-stat-num">{{ $clockedIn }}</span>
            <span class="atm-stat-sub">
                @if($clockedIn)
                    {{ $clockedIn - $inSecond }} {{ __('in 1st session') }} · {{ $inSecond }} {{ __('in 2nd') }}
                @else
                    {{ __('On site, in a session') }}
                @endif
            </span>
        </a>
        <a class="atm-stat is-brk {{ $cardOn('break') ? 'is-active' : '' }}"
           href="{{ $cardUrl('break') }}" data-view="break"
           @if($cardOn('break')) aria-current="true" @endif>
            <span class="atm-stat-lbl"><span class="atm-dot"></span>{{ __('On break') }}</span>
            <span class="atm-stat-num">{{ $onBreak }}</span>
            <span class="atm-stat-sub">
                {{ $overBreak ? $overBreak . ' ' . __('past the break') : __('Between sessions') }}
            </span>
        </a>
        <a class="atm-stat is-bad {{ $cardOn('missed') ? 'is-active' : '' }}"
           href="{{ $cardUrl('missed') }}" data-view="missed"
           @if($cardOn('missed')) aria-current="true" @endif>
            <span class="atm-stat-lbl"><span class="atm-dot"></span>{{ __('Needs review') }}</span>
            <span class="atm-stat-num">{{ $invalidCount }}</span>
            <span class="atm-stat-sub">{{ $reviewToday }} {{ __('today') }} · {{ $reviewEarlier }} {{ __('earlier this week') }}</span>
        </a>
    </div>

    <section class="atm-card" aria-label="{{ __('Attendance records') }}">

        <div class="atm-tabs" role="tablist">
            <button class="atm-tab {{ $openTab === 'today' ? 'active' : '' }}" type="button" role="tab"
                    data-bs-toggle="tab" data-bs-target="#att-today" data-tab="today"
                    aria-selected="{{ $openTab === 'today' ? 'true' : 'false' }}">
                <i class="fas fa-calendar-day"></i>{{ __('Today\'s Attendance') }}
            </button>
            <button class="atm-tab {{ $openTab === 'history' ? 'active' : '' }}" type="button" role="tab"
                    data-bs-toggle="tab" data-bs-target="#att-history" data-tab="history"
                    aria-selected="{{ $openTab === 'history' ? 'true' : 'false' }}">
                <i class="fas fa-clock-rotate-left"></i>{{ __('History') }}
                <span class="atm-count" id="attHistCount" data-live="attendance employees"
                      title="{{ __('Earlier days this week still waiting on a review') }}"
                      @if(! $reviewEarlier) hidden @endif>{{ $reviewEarlier }}</span>
            </button>
        </div>

        {{-- ── Filters ─────────────────────────────────────────────────────
             One GET form, so every choice combines with the others and the
             result is a URL. Script fetches it in place; without script the
             Apply button submits it. History is paginated fifteen days at a
             time, so the narrowing is the server's — hiding rows in the
             browser would filter the page you can see and ignore the rest. --}}
        <form method="GET" action="{{ route('attendance') }}" id="attFilters" class="atm-toolbar" role="search">

            <label class="atm-field">
                <i class="fas fa-magnifying-glass"></i>
                <input type="search" name="q" id="attSearch" value="{{ $search }}"
                       placeholder="{{ __('Search employee') }}" aria-label="{{ __('Search employee') }}" autocomplete="off">
            </label>

            <label class="atm-field">
                <i class="fas fa-location-dot"></i>
                <select name="site" id="attSite" aria-label="{{ __('Filter by site') }}">
                    <option value="">{{ __('All sites') }}</option>
                    @foreach($sites as $s)
                        <option value="{{ $s->id }}" @selected($siteId === $s->id)>{{ $s->name }}</option>
                    @endforeach
                </select>
            </label>

            <div class="atm-seg" role="radiogroup" aria-label="{{ __('Filter by shift') }}">
                <label><input type="radio" name="shift" value="" @checked($shiftId === null)><span>{{ __('All shifts') }}</span></label>
                @foreach($shifts as $sh)
                    <label>
                        <input type="radio" name="shift" value="{{ $sh->id }}" @checked($shiftId === $sh->id)>
                        <span><i class="fas {{ $sh->crosses_midnight ? 'fa-moon' : 'fa-sun' }}"></i>{{ $sh->name }}</span>
                    </label>
                @endforeach
            </div>

            <div class="atm-seg" role="radiogroup" aria-label="{{ __('Filter by status') }}">
                {{-- Working and On break are questions about now, so History,
                     where every day is over, has no buttons for them. --}}
                @foreach($statuses as $value => $label)
                    @php
                        $todayOnly = in_array($value, ['clocked-in', 'break'], true);
                        $labelAttr = $todayOnly ? ' data-today-only' . ($openTab === 'history' ? ' hidden' : '') : '';
                    @endphp
                    <label{!! $labelAttr !!}>
                        <input type="radio" name="view" value="{{ $value }}" @checked($shownView === $value)>
                        <span>{{ $label }}</span>
                    </label>
                @endforeach
            </div>

            {{-- How far back History reaches. It rides in the same form, so it
                 combines with the rest instead of clearing them. Shown only
                 while History is open — Today's Attendance is a single
                 workday. Hidden rather than removed, so the range survives a
                 trip to the other tab. --}}
            <label class="atm-field" id="attRangePick" @if($openTab !== 'history') hidden @endif>
                <i class="fas fa-calendar-days"></i>
                <select name="range" id="attRange" aria-label="{{ __('Filter history by date range') }}">
                    @foreach([
                        '7'    => __('Last 7 Days'),
                        '30'   => __('Last 30 Days'),
                        '3m'   => __('Last 3 Months'),
                        '6m'   => __('Last 6 Months'),
                        'year' => __('This Year'),
                        'all'  => __('All Time'),
                    ] as $key => $label)
                        {{-- (string) $key: PHP turns the two numeric keys into
                             ints, and a strict === against the string from the
                             query never matched them. --}}
                        <option value="{{ $key }}" @selected((string) $key === $range)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            {{-- The tab rides along, so a filter changed while reading History
                 comes back to History. --}}
            <input type="hidden" name="tab" id="attTabField" value="{{ $openTab }}">
            <noscript><button type="submit" class="atm-btn">{{ __('Apply') }}</button></noscript>

            {{-- No deleting from here. Every past day is what payroll is
                 computed from; a missing time out is fixed under its row. --}}
        </form>

        @php
            $groupHead = function () {
                return '<tr class="atm-grp">'
                    . '<th colspan="2"></th>'
                    . '<th colspan="2" class="s"><span>' . e(__('1st session')) . '</span></th>'
                    . '<th colspan="2" class="s atm-sep"><span>' . e(__('2nd session')) . '</span></th>'
                    . '<th colspan="3"></th></tr>';
            };
            $heads = '<th>' . e(__('Employee')) . '</th><th>' . e(__('Shift')) . '</th>'
                   . '<th>' . e(__('Time in')) . '</th><th>' . e(__('Time out')) . '</th>'
                   . '<th class="atm-sep">' . e(__('Time in')) . '</th><th>' . e(__('Time out')) . '</th>'
                   . '<th class="atm-col-tl">' . e(__('Timeline')) . '</th><th>' . e(__('Hours')) . '</th><th>' . e(__('Status')) . '</th>';
        @endphp

        <div class="tab-content">

            <!-- ===== TODAY ===== -->
            <div class="tab-pane {{ $openTab === 'today' ? 'active' : '' }}" id="att-today" role="tabpanel">
                <div class="atm-scroll" id="attTodayList" data-live="attendance employees sites">
                    <table class="atm-table" id="todayTable">
                        <thead>
                            {!! $groupHead() !!}
                            <tr>{!! $heads !!}</tr>
                        </thead>
                        <tbody>
                            @forelse($todayBoard as $d)
                                @include('attendance._day', ['d' => $d, 'tab' => 'today'])
                            @empty
                                <tr>
                                    <td colspan="9" class="atm-empty">
                                        <i class="fas fa-fingerprint"></i>
                                        {{ $search !== '' ? __('Nobody on today\'s list matches these filters.') : match ($todayView) {
                                            'clocked-in' => __('Nobody is clocked in right now.'),
                                            'break'      => __('Nobody is on break right now.'),
                                            'missed'     => __('Nothing on today\'s list is waiting on a review.'),
                                            'done'       => __('No finished days yet today.'),
                                            'all'        => __('Nobody on the roster matches these filters.'),
                                            default      => __('No fingerprint scans yet today.'),
                                        } }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ===== HISTORY ===== -->
            <div class="tab-pane {{ $openTab === 'history' ? 'active' : '' }}" id="att-history" role="tabpanel">
                <div class="atm-scroll" id="attHistoryList" data-live="attendance employees sites">
                        <table class="atm-table" id="historyTable">
                            <thead>
                                {!! $groupHead() !!}
                                <tr>{!! $heads !!}</tr>
                            </thead>
                            <tbody>
                                @forelse($historyBoard->groupBy(fn ($d) => $d->day->date()->toDateString()) as $date => $group)
                                    <tr class="atm-dayhead" data-live-key="date-{{ $date }}">
                                        <td colspan="9">{{ \Carbon\Carbon::parse($date)->format('l, m/d/Y') }}</td>
                                    </tr>
                                    @foreach($group as $d)
                                        @include('attendance._day', ['d' => $d, 'tab' => 'history'])
                                    @endforeach
                                @empty
                                    <tr>
                                        <td colspan="9" class="atm-empty">
                                            <i class="fas fa-clock-rotate-left"></i>
                                            {{-- "Nothing here" and "nothing here lately" are
                                                 different answers, and a reader who forgot
                                                 the range is on would read the first as the
                                                 second. --}}
                                            {{ $range === 'all'
                                                ? __('No previous attendance records.')
                                                : __('No attendance records in this date range.') }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                </div>

                {{-- appends(): these links live in the History pane, so page 2
                     has to carry the tab as well as the filters, or it lands on
                     Today's Attendance. --}}
                <div class="att-pager" id="attHistoryPager" data-live="attendance employees sites">{{ $historyAttendances->appends(array_filter(['tab' => 'history', 'view' => $view]))->links() }}</div>
            </div>
        </div>

        <div class="atm-legend">
            <span><i class="atm-sw" style="background:var(--brand)"></i>{{ __('Worked') }}</span>
            <span><i class="atm-sw" style="background:repeating-linear-gradient(135deg, var(--atm-hatch) 0 4px, transparent 4px 8px); border:1px solid var(--atm-brk)"></i>{{ __('Break window') }}</span>
            <span><i class="atm-sw" style="background:var(--atm-brk)"></i>{{ __('On break') }}</span>
            <span><i class="atm-sw" style="background:var(--warning)"></i>{{ __('Overbreak') }}</span>
            <span><i class="atm-sw" style="border:1.5px dashed var(--danger)"></i>{{ __('Missing or guessed scan') }}</span>
            <span><i class="atm-sw" style="height:2px; background:var(--danger)"></i>{{ __('Late') }}</span>
            <span>{{ __('Click a row to see its scans and fix missing ones.') }}</span>
        </div>
    </section>
</div>

@push('scripts')
<script>
(function () {
    const card     = document.querySelector('.atm-card');
    const form     = document.getElementById('attFilters');
    const search   = document.getElementById('attSearch');
    const tabField = document.getElementById('attTabField');
    const regions  = ['attStats', 'attTodayList', 'attHistoryList', 'attHistoryPager', 'attHistCount'];
    const csrf     = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    if (!form || !card) return;

    // ── Rows that are open ──────────────────────────────────────────────────
    // Kept here rather than in the markup, because the markup is replaced —
    // by a filter, a fix, or the live feed — and a row somebody opened should
    // still be open afterwards.
    const open = new Set();

    function applyOpen() {
        document.querySelectorAll('tr.atm-row').forEach(tr => {
            const on  = open.has(tr.dataset.day);
            const det = tr.nextElementSibling;
            tr.classList.toggle('is-open', on);
            tr.setAttribute('aria-expanded', on ? 'true' : 'false');
            if (det && det.matches('tr.atm-detail')) det.hidden = !on;
        });
    }

    function toggle(tr) {
        open.has(tr.dataset.day) ? open.delete(tr.dataset.day) : open.add(tr.dataset.day);
        applyOpen();
    }

    document.addEventListener('click', e => {
        const tr = e.target.closest('tr.atm-row');
        if (!tr) return;
        if (e.target.closest('input, button, a, label, select')) return;
        toggle(tr);
    });
    document.addEventListener('keydown', e => {
        if ((e.key === 'Enter' || e.key === ' ') && e.target.matches && e.target.matches('tr.atm-row')) {
            e.preventDefault();
            toggle(e.target);
        }
    });
    document.addEventListener('live:updated', applyOpen);

    // ── Filters, fetched in place ───────────────────────────────────────────
    // Any choice asks the server for the page as it would be, and swaps in
    // the parts that changed: the cards and both lists. The address bar
    // follows, so a refresh or a copied link lands on the same view.
    // Whether somebody has picked a status. Until they do, each tab shows its
    // own default — who is working now, and every past day — and the address
    // bar carries no status at all.
    const DEFAULTS = { today: 'clocked-in', history: 'all' };
    let chosen = new URL(location).searchParams.get('view');

    function showStatus(v) {
        document.querySelectorAll('input[name="view"]').forEach(r => { r.checked = r.value === v; });
    }

    function urlOf() {
        const params = new URLSearchParams(new FormData(form));
        for (const [k, v] of [...params]) {
            if (v === '' || (k === 'range' && v === 'all') || (k === 'tab' && v === 'today')) params.delete(k);
        }
        params.delete('view');
        if (chosen) params.set('view', chosen);
        const q = params.toString();
        return form.action + (q ? '?' + q : '');
    }

    let pending = null;

    async function reload(url) {
        pending?.abort();
        const ctrl = new AbortController();
        pending = ctrl;
        card.classList.add('is-loading');

        try {
            const res = await fetch(url, {
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                signal: ctrl.signal,
            });
            if (!res.ok) { location.href = url; return; }

            const fresh = new DOMParser().parseFromString(await res.text(), 'text/html');
            regions.forEach(id => {
                const here = document.getElementById(id);
                const next = fresh.getElementById(id);
                if (!here || !next) return;
                here.innerHTML = next.innerHTML;
                here.hidden = next.hidden;
            });

            history.replaceState(null, '', url);
            applyOpen();
        } catch (err) {
            if (err.name !== 'AbortError') location.href = url;
        } finally {
            if (pending === ctrl) {
                pending = null;
                card.classList.remove('is-loading');
            }
        }
    }

    form.addEventListener('change', e => {
        if (e.target === search) return;
        if (e.target.name === 'view') chosen = e.target.value;
        reload(urlOf());
    });

    let typing = null;
    search?.addEventListener('input', () => {
        clearTimeout(typing);
        typing = setTimeout(() => reload(urlOf()), 300);
    });
    form.addEventListener('submit', e => { e.preventDefault(); clearTimeout(typing); reload(urlOf()); });
    search?.addEventListener('keydown', e => {
        if (e.key === 'Enter') { e.preventDefault(); clearTimeout(typing); reload(urlOf()); }
    });

    // A card sets the status control, and the control fetches — so both
    // always show the same thing. Clicking the card that is on shows everybody.
    // Present has no button of its own, so while it is on none is lit.
    document.getElementById('attStats')?.addEventListener('click', e => {
        const a = e.target.closest('a[data-view]');
        if (!a || e.ctrlKey || e.metaKey || e.shiftKey) return;
        e.preventDefault();
        chosen = a.classList.contains('is-active') ? 'all' : a.dataset.view;
        showStatus(chosen);
        reload(urlOf());
    });

    // ── Tabs ────────────────────────────────────────────────────────────────
    // The range only means anything on History, and Working and On break
    // only on today, so they come and go with the tab; the tab rides in the
    // address bar too.
    const rangePick = document.getElementById('attRangePick');
    const todayOnly = ['clocked-in', 'break'];

    document.querySelectorAll('.atm-tab[data-tab]').forEach(btn => {
        btn.addEventListener('shown.bs.tab', () => {
            const tab = btn.dataset.tab;
            tabField.value   = tab;
            rangePick.hidden = tab !== 'history';
            document.querySelectorAll('[data-today-only]').forEach(el => { el.hidden = tab === 'history'; });

            const url = new URL(window.location);
            if (tab === 'history') url.searchParams.set('tab', 'history');
            else                   url.searchParams.delete('tab');
            history.replaceState(null, '', url);

            // Each tab shows the status it is filtered by: its own default
            // until somebody picks one, and on History never a button it
            // does not have — History reads those as everybody.
            const want = chosen || DEFAULTS[tab];
            showStatus(tab === 'history' && todayOnly.includes(want) ? 'all' : want);
        });
    });

    // ── Fixing a time out nobody scanned ────────────────────────────────────
    document.addEventListener('submit', async e => {
        const f = e.target.closest('form[data-fix]');
        if (!f) return;
        e.preventDefault();

        const btn  = e.submitter;
        const time = btn && btn.name === 'time' ? btn.value : f.querySelector('input[type="time"]').value;
        if (!time) { Notify.warning(@json(__('Enter a time first.'))); return; }

        f.querySelectorAll('button').forEach(b => b.disabled = true);
        try {
            const res  = await fetch(f.dataset.fix, {
                method: 'PATCH',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify({ time }),
            });
            const data = await res.json().catch(() => ({}));
            if (res.ok && data.success) {
                Notify.success(data.message);
                await reload(location.href);
            } else {
                Notify.error(data.message || @json(__('The time could not be saved.')));
                f.querySelectorAll('button').forEach(b => b.disabled = false);
            }
        } catch (err) {
            Notify.error(@json(__('Request failed:')) + ' ' + err.message);
            f.querySelectorAll('button').forEach(b => b.disabled = false);
        }
    });

    // ── Keeping "now" current ───────────────────────────────────────────────
    // The live feed re-reads the lists when somebody scans, but a break turns
    // into an overbreak with nobody scanning at all. Once a minute, while the
    // page is looked at, it is re-read for that alone.
    setInterval(() => {
        if (document.hidden || !window.Live || typeof window.Live.refresh !== 'function') return;
        window.Live.refresh();
    }, 60000);
})();
</script>
@endpush

@endsection
