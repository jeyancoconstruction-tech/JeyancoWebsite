@extends('layouts')
@section('page_title', 'Complaints')

@push('styles')
<style>
/* Complaints (2026-11-13 revision, "chat") — read like a messaging app. On
   the left the conversations: the worker, the last line and when; the ones
   waiting for an answer on top, bold, with a count; the workers who have
   never written below them. On the right the conversation with the worker
   picked, oldest at the top: what the worker sent on the left in grey, the
   office's answers on the right in the accent colour, and the box to answer
   at the bottom. The accent is the one picked in System Settings →
   Appearance (the same tokens as the main buttons), so Black still gives a
   black-and-grey page and the other colours tint it. */
.cpx { --cx-ink: var(--btn-ink, var(--brand, #1668DC)); --cx-on-ink: var(--btn-on, #fff);
       --cx-tint: color-mix(in srgb, var(--cx-ink) 11%, var(--surface, #fff));
       --cx-line: var(--border, #e4e7ec); --cx-soft: var(--bg-subtle, #f8f9fb);
       --cx-bub: #eef0f3; --cx-mute: var(--text-muted, #667085);
       display: flex; flex-direction: column; gap: 14px; height: calc(100vh - 120px); min-height: 600px; }
[data-bs-theme="dark"] .cpx { --cx-bub: #1f2a3c; --cx-tint: color-mix(in srgb, var(--cx-ink) 18%, var(--surface, #111827)); }

/* Head. */
.cpx-top { display: flex; align-items: flex-end; justify-content: space-between; gap: 16px 32px; flex-wrap: wrap; }
.cpx-top > div:first-child { flex: 1 1 320px; min-width: 0; }
.cpx-eyebrow { font-size: 11.5px; font-weight: 600; letter-spacing: .08em; text-transform: uppercase; color: var(--cx-mute); }
html[data-bs-theme] .cpx-top h1 { margin: 2px 0 0; font-size: 26px; font-weight: 700; letter-spacing: -.02em; color: var(--text-primary); }
.cpx-top p { margin: 4px 0 0; font-size: 13px; color: var(--cx-mute); }
.cpx-strip { display: flex; border: 1px solid var(--cx-line); border-radius: 12px; background: var(--surface); overflow: hidden; }
.cpx-strip > div { padding: 9px 18px 10px; min-width: 108px; }
.cpx-strip > div + div { border-left: 1px solid var(--cx-line); }
.cpx-strip span { display: block; font-size: 11.5px; color: var(--cx-mute); white-space: nowrap; }
.cpx-strip b { display: block; margin-top: 1px; font-size: 21px; font-weight: 700; line-height: 1.15; font-variant-numeric: tabular-nums; color: var(--text-primary); }
.cpx-strip b small { font-size: 12px; font-weight: 600; color: var(--cx-mute); margin-left: 2px; }
.cpx-strip .alarm { background: var(--cx-tint); box-shadow: inset 3px 0 0 var(--cx-ink); }

/* The two panes. */
.cpx-main { flex: 1; min-height: 0; display: grid; grid-template-columns: 340px minmax(0, 1fr); gap: 14px; }
.cpx-card { min-height: 0; background: var(--surface); border: 1px solid var(--cx-line); border-radius: 16px; }

/* Conversations. */
.cpx-side { display: flex; flex-direction: column; overflow: hidden; }
.cpx-side-h { flex: none; padding: 14px 14px 10px; border-bottom: 1px solid var(--cx-line); }
.cpx-search { display: flex; align-items: center; gap: 8px; height: 36px; padding: 0 12px; border-radius: 18px; background: var(--cx-soft); }
.cpx-search i { font-size: 12px; color: var(--cx-mute); }
.cpx-search input { flex: 1; min-width: 0; border: 0; background: transparent; outline: none; font-size: 13px; color: var(--text-primary); }
.cpx-tabs { display: flex; gap: 6px; margin-top: 10px; }
.cpx-tabs a { display: inline-flex; align-items: center; gap: 6px; height: 28px; padding: 0 11px; border-radius: 14px; font-size: 12.5px; font-weight: 600;
              color: var(--text-secondary, var(--text-primary)); background: var(--cx-soft); text-decoration: none; }
.cpx-tabs a:hover { color: var(--text-primary); }
.cpx-tabs a.on { background: var(--cx-ink); color: var(--cx-on-ink); }
.cpx-tabs em { font-style: normal; font-weight: 500; opacity: .7; font-variant-numeric: tabular-nums; }
.cpx-flt { display: flex; gap: 6px; margin-top: 8px; }
.cpx-flt select { flex: 1; min-width: 0; height: 30px; padding: 0 8px; border: 1px solid var(--cx-line); border-radius: 8px; background: var(--surface); color: var(--text-primary); font-size: 12px; }
.cpx-flt .reset { align-self: center; font-size: 12px; font-weight: 600; color: var(--text-primary); text-decoration: underline; text-underline-offset: 3px; }

.cpx-list { flex: 1; min-height: 0; overflow-y: auto; padding: 6px; }
.cpx-chat { position: relative; display: flex; align-items: center; gap: 11px; padding: 9px 10px; border-radius: 12px; text-decoration: none; color: inherit; }
.cpx-chat:hover { background: var(--cx-soft); color: inherit; }
.cpx-chat.sel { background: var(--cx-tint); }
.cpx-av { flex: none; width: 42px; height: 42px; border-radius: 50%; display: grid; place-items: center; background: var(--cx-soft); border: 1px solid var(--cx-line); }
.cpx-chat.sel .cpx-av { background: var(--surface); }
.cpx-b { flex: 1; min-width: 0; }
.cpx-n { display: flex; align-items: baseline; gap: 8px; font-size: 13.5px; font-weight: 500; color: var(--text-primary); }
.cpx-n > span { min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.cpx-n time { margin-left: auto; flex: none; font-size: 11.5px; color: var(--cx-mute); font-variant-numeric: tabular-nums; }
.cpx-pv { display: block; margin-top: 2px; font-size: 12.5px; color: var(--cx-mute); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.cpx-chat.unread .cpx-n { font-weight: 700; }
.cpx-chat.unread .cpx-pv { color: var(--text-primary); font-weight: 600; }
.cpx-chat.unread .cpx-n time { color: var(--text-primary); font-weight: 600; }
.cpx-dot { flex: none; min-width: 20px; height: 20px; padding: 0 6px; border-radius: 10px; display: grid; place-items: center; font-size: 11px; font-weight: 700;
           background: var(--cx-ink); color: var(--cx-on-ink); font-variant-numeric: tabular-nums; }
.cpx-div { padding: 14px 10px 6px; font-size: 11px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: var(--cx-mute); }
.cpx-nobody { padding: 16px 10px; font-size: 12.5px; color: var(--cx-mute); }

/* The conversation. */
.cpx-conv { display: flex; flex-direction: column; overflow: hidden; }
.cpx-conv-h { flex: none; display: flex; align-items: center; gap: 12px; padding: 12px 18px; border-bottom: 1px solid var(--cx-line); }
.cpx-conv-h .cpx-av { width: 44px; height: 44px; }
.cpx-who { flex: 1; min-width: 0; }
.cpx-who b { display: block; font-size: 15.5px; font-weight: 700; color: var(--text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.cpx-who small { display: block; margin-top: 1px; font-size: 12px; color: var(--cx-mute); }
.cpx-ibtn { width: 36px; height: 36px; border-radius: 50%; display: grid; place-items: center; color: var(--text-primary); background: var(--cx-soft); text-decoration: none; font-size: 13px; }
.cpx-ibtn:hover { background: var(--cx-bub); color: var(--text-primary); }

.cpx-thread { flex: 1; min-height: 0; overflow-y: auto; padding: 18px 20px 10px; display: flex; flex-direction: column; gap: 4px; }
.cpx-thread > * { flex: none; }
.cpx-day { align-self: center; margin: 12px 0 8px; font-size: 11.5px; font-weight: 600; color: var(--cx-mute); }
.cpx-msg { display: flex; align-items: flex-end; gap: 8px; max-width: 78%; text-decoration: none; color: inherit; }
.cpx-msg:hover { color: inherit; }
.cpx-msg .cpx-av { width: 28px; height: 28px; }
.cpx-bub { padding: 9px 13px; border-radius: 18px; font-size: 14px; line-height: 1.45; white-space: pre-line; overflow-wrap: anywhere; }
.cpx-msg.in .cpx-bub { background: var(--cx-bub); color: var(--text-primary); border-bottom-left-radius: 6px; }
.cpx-msg.in:hover .cpx-bub { filter: brightness(.98); }
.cpx-msg.in.sel .cpx-bub { box-shadow: 0 0 0 2px var(--surface), 0 0 0 4px var(--cx-ink); }
.cpx-bub .tp { display: block; margin-bottom: 3px; font-size: 11px; font-weight: 600; color: var(--cx-mute); white-space: normal; }
.cpx-bub strong { display: block; font-weight: 600; }
.cpx-msg.out { align-self: flex-end; }
.cpx-msg.out .cpx-bub { background: var(--cx-ink); color: var(--cx-on-ink); border-bottom-right-radius: 6px; }
.cpx-msg.out.open .cpx-bub { background: var(--surface); color: var(--text-primary); border: 1.5px solid var(--cx-ink); }
.cpx-meta { margin: 3px 0 10px 36px; font-size: 11px; color: var(--cx-mute); }
.cpx-meta.out { align-self: flex-end; margin: 3px 4px 10px 0; text-align: right; }
.cpx-meta b { font-weight: 600; color: var(--text-primary); }
.cpx-meta .wait { display: inline-flex; align-items: center; gap: 5px; font-weight: 600; color: var(--text-primary); }
.cpx-meta .wait::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: var(--cx-ink); }
.cpx-hello { margin: auto; max-width: 360px; text-align: center; color: var(--cx-mute); font-size: 13px; line-height: 1.5; }
.cpx-hello .cpx-av { width: 72px; height: 72px; margin: 0 auto 10px; }
.cpx-hello b { display: block; font-size: 15px; color: var(--text-primary); }

/* The answer box. */
.cpx-compose { flex: none; padding: 10px 16px 14px; border-top: 1px solid var(--cx-line); }
.cpx-to { display: flex; align-items: center; gap: 8px; margin: 0 4px 8px; font-size: 12px; color: var(--cx-mute); min-width: 0; }
.cpx-to i { font-size: 11px; }
.cpx-to span { min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.cpx-to b { color: var(--text-primary); font-weight: 600; }
.cpx-quick { display: flex; gap: 6px; overflow-x: auto; margin: 0 0 8px; padding-bottom: 2px; scrollbar-width: none; }
.cpx-quick button { flex: none; height: 28px; padding: 0 12px; border-radius: 14px; border: 1px solid var(--cx-line); background: var(--surface);
                    font-size: 12px; color: var(--text-primary); cursor: pointer; }
.cpx-quick button:hover { background: var(--cx-soft); }
.cpx-inp { display: flex; align-items: flex-end; gap: 8px; }
.cpx-inp textarea { flex: 1; min-width: 0; min-height: 40px; max-height: 140px; padding: 10px 16px; border: 0; border-radius: 20px; background: var(--cx-soft);
                    color: var(--text-primary); font-size: 14px; line-height: 1.4; resize: none; overflow-y: auto; }
.cpx-inp textarea:focus { outline: none; box-shadow: 0 0 0 2px var(--cx-line) inset; }
.cpx-btn { height: 40px; padding: 0 16px; border-radius: 20px; display: inline-flex; align-items: center; gap: 7px; font-size: 13px; font-weight: 600; cursor: pointer;
           border: 1px solid var(--cx-line); background: var(--surface); color: var(--text-primary); white-space: nowrap; }
.cpx-btn:hover { background: var(--cx-soft); color: var(--text-primary); }
.cpx-btn.pri { background: var(--cx-ink); border-color: var(--cx-ink); color: var(--cx-on-ink); }
.cpx-btn.pri:hover { opacity: .86; color: var(--cx-on-ink); }
.cpx-btn i { font-size: 12px; }

/* Nobody picked: the month at a glance. */
.cpx-over { overflow-y: auto; padding: 20px 24px; display: flex; flex-direction: column; gap: 24px; }
.cpx-over > * { flex: none; }
.cpx-over h3 { display: flex; align-items: baseline; justify-content: space-between; margin: 0 0 10px; font-size: 13px; font-weight: 700; color: var(--text-primary); }
.cpx-over h3 small { font-size: 12px; font-weight: 500; color: var(--cx-mute); }
/* Each topic as a line: name and count, then a thin bar for its share of
   all reports (not of the biggest topic, so two topics of one each read as
   half and half instead of two full black slabs). */
.cpx-bars { display: flex; flex-direction: column; gap: 14px; max-width: 520px; }
.cpx-bar .hd { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; font-size: 13px; }
.cpx-bar .hd span { color: var(--text-secondary, var(--text-primary)); }
.cpx-bar .hd b { font-weight: 600; font-variant-numeric: tabular-nums; color: var(--text-primary); }
.cpx-bar .hd em { font-style: normal; font-weight: 500; color: var(--cx-mute); margin-left: 6px; }
.cpx-bar .tr { margin-top: 6px; height: 4px; border-radius: 2px; background: var(--cx-line); overflow: hidden; }
.cpx-bar .tr { background: var(--cx-tint); }
.cpx-bar .tr i { display: block; height: 100%; border-radius: 2px; background: var(--cx-ink); }
.cpx-quiet { font-size: 12.5px; color: var(--cx-mute); }

@media (max-width: 1100px) {
    .cpx { height: auto; }
    .cpx-main { grid-template-columns: 1fr; }
    .cpx-side { max-height: 420px; }
    .cpx-conv { height: 640px; }
}
@media (max-width: 760px) {
    .cpx-strip { width: 100%; } .cpx-strip > div { flex: 1; min-width: 0; padding: 8px 12px; }
    .cpx-msg { max-width: 92%; }
    .cpx-inp { flex-wrap: wrap; } .cpx-inp textarea { flex-basis: 100%; } .cpx-inp .cpx-btn { flex: 1; justify-content: center; }
}
</style>
@endpush

@section('content')
@php
    $keep = array_filter(['show' => $show !== 'all' ? $show : null, 'category' => $category, 'site' => $siteId, 'q' => $q !== '' ? $q : null]);
    $url  = fn (array $over) => route('complaints.index', array_filter(array_merge($keep, $over), fn ($v) => $v !== null && $v !== ''));
    // A chat app's times: the time today, "Yesterday", the weekday this week, the date before.
    $short = fn ($d) => $d ? ($d->isToday() ? $d->format('g:i A') : ($d->isYesterday() ? __('Yesterday') : ($d->gt(now()->subDays(6)->startOfDay()) ? $d->format('D') : $d->format('M j')))) : '';
    $day   = fn ($d) => $d->isToday() ? __('Today') : ($d->isYesterday() ? __('Yesterday') : $d->format('D, M j, Y'));
    $waited = fn ($d) => $d && $d->lt(now()->subHour()) ? $d->diffForHumans(null, \Carbon\CarbonInterface::DIFF_ABSOLUTE, true) : null;
    $from  = fn ($c) => $c->kiosk ? ($c->kiosk->name ?: $c->kiosk->code) : __('Kiosk');
    $first = fn ($name) => \Illuminate\Support\Str::before($name ?? __('the worker'), ' ');
    $no    = fn ($c) => '#' . str_pad($c->id, 4, '0', STR_PAD_LEFT);
    $split = fn ($m) => array_pad(explode(' — ', (string) $m, 2), 2, null);
    $canDtr = \Illuminate\Support\Facades\Route::has('payroll-reports.dtr') && auth()->user()?->canAccessModule('payroll-reports');
    $avg = $stats['avgHours'];
    $avatar = fn ($size = '58%') => view('partials.worker-icon', ['size' => $size])->render();
    // The line under a worker's name: their last report, or the answer to it.
    $preview = function ($e) {
        $l = $e->latest;
        if (! $l) return null;
        if ($l->response && ! $l->isOpen()) return __('You') . ': ' . $l->response;
        return $l->message;
    };
    // Answers the office writes again and again, one tap into the box.
    $quick = [__('Checked and fixed in your DTR.'), __('Please see the office tomorrow.'), __('We are looking into it.'), __('Reported to the site engineer.')];
@endphp
<div class="cpx">
    <div class="cpx-top">
        <div>
            <div class="cpx-eyebrow">{{ __('Worker reports') }}</div>
            <h1>{{ __('Complaints') }}</h1>
            <p>{{ __("Workers write from the kiosk's Report tab; your answer shows on the kiosk the next time they scan.") }}</p>
        </div>
        <div class="cpx-strip">
            <div><span>{{ __('Open') }}</span><b>{{ $stats['open'] }}</b></div>
            <div class="{{ $stats['safety'] ? 'alarm' : '' }}"><span>{{ __('Safety open') }}</span><b>{{ $stats['safety'] }}</b></div>
            <div><span>{{ __('Answered this week') }}</span><b>{{ $stats['week'] }}</b></div>
            <div><span>{{ __('Avg. answer time') }}</span><b>@if($avg === null)—@elseif($avg < 1){{ round($avg * 60) }}<small>min</small>@else{{ $avg }}<small>h</small>@endif</b></div>
        </div>
    </div>

    @include('modules._flash')

    <div class="cpx-main">
        {{-- Conversations --}}
        <div class="cpx-card cpx-side">
            <form class="cpx-side-h" method="GET" action="{{ route('complaints.index') }}">
                @if($show !== 'all')<input type="hidden" name="show" value="{{ $show }}">@endif
                <label class="cpx-search"><i class="fas fa-magnifying-glass"></i>
                    <input type="search" name="q" value="{{ $q }}" placeholder="{{ __('Search an employee') }}" aria-label="{{ __('Employee') }}"></label>
                <nav class="cpx-tabs" aria-label="{{ __('Show') }}">
                    <a class="{{ $show === 'all' ? 'on' : '' }}" href="{{ $url(['show' => null]) }}">{{ __('All') }} <em>{{ $counts['all'] }}</em></a>
                    <a class="{{ $show === 'open' ? 'on' : '' }}" href="{{ $url(['show' => 'open']) }}">{{ __('Unanswered') }} <em>{{ $counts['open'] }}</em></a>
                    <a class="{{ $show === 'reported' ? 'on' : '' }}" href="{{ $url(['show' => 'reported']) }}">{{ __('Has written') }} <em>{{ $counts['reported'] }}</em></a>
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
                    @if($keep)<a class="reset" href="{{ route('complaints.index') }}">{{ __('Reset') }}</a>@endif
                </div>
            </form>

            <div class="cpx-list">
                @foreach($chats as $e)
                    <a class="cpx-chat {{ $e->open_n ? 'unread' : '' }} {{ $employee && $employee->id === $e->id ? 'sel' : '' }}" href="{{ $url(['e' => $e->id]) }}">
                        <span class="cpx-av">{!! $avatar() !!}</span>
                        <span class="cpx-b">
                            <span class="cpx-n"><span>{{ $e->name }}</span><time>{{ $short($e->active_at) }}</time></span>
                            <span class="cpx-pv">{{ $preview($e) }}</span>
                        </span>
                        @if($e->open_n)<span class="cpx-dot" title="{{ __('Unanswered') }}">{{ $e->open_n }}</span>@endif
                    </a>
                @endforeach
                @if($others->isNotEmpty())
                    <div class="cpx-div">{{ __('No messages yet') }}</div>
                    @foreach($others as $e)
                        <a class="cpx-chat {{ $employee && $employee->id === $e->id ? 'sel' : '' }}" href="{{ $url(['e' => $e->id]) }}">
                            <span class="cpx-av">{!! $avatar() !!}</span>
                            <span class="cpx-b">
                                <span class="cpx-n"><span>{{ $e->name }}</span></span>
                                <span class="cpx-pv">{{ collect([$e->position, $e->site?->name])->filter()->implode(' · ') ?: __('Worker') }}</span>
                            </span>
                        </a>
                    @endforeach
                @endif
                @if($chats->isEmpty() && $others->isEmpty())
                    <div class="cpx-nobody">{{ $keep ? __('Nobody matches these filters.') : __('No workers yet.') }}</div>
                @endif
            </div>
        </div>

        {{-- The conversation, or the month at a glance --}}
        @if($employee)
            <section class="cpx-card cpx-conv">
                <div class="cpx-conv-h">
                    <span class="cpx-av">{!! $avatar() !!}</span>
                    <div class="cpx-who">
                        <b>{{ $employee->name }}</b>
                        <small>#{{ str_pad($employee->id, 4, '0', STR_PAD_LEFT) }}{{ $employee->position ? ' · ' . $employee->position : '' }}{{ $employee->site ? ' · ' . $employee->site->name : '' }}</small>
                    </div>
                    <a class="cpx-ibtn" href="{{ route('employees.show', $employee->id) }}" title="{{ __('Profile') }}" aria-label="{{ __('Profile') }}"><i class="fas fa-user"></i></a>
                    @if($canDtr)<a class="cpx-ibtn" href="{{ route('payroll-reports.index', ['report' => 'dtr', 'q' => $employee->name]) }}" title="{{ __('DTR') }}" aria-label="{{ __('DTR') }}"><i class="fas fa-calendar-days"></i></a>@endif
                </div>

                <div class="cpx-thread" id="cpxThread">
                    @forelse($thread as $c)
                        @if($loop->first || ! $c->created_at->isSameDay($thread[$loop->index - 1]->created_at))
                            <div class="cpx-day">{{ $day($c->created_at) }}</div>
                        @endif
                        @php [$ask, $note] = $split($c->message); $w = $c->isOpen() && ! $c->response ? $waited($c->created_at) : null; @endphp
                        <a class="cpx-msg in {{ $selected && $selected->id === $c->id ? 'sel' : '' }}" href="{{ $url(['e' => $employee->id, 'c' => $c->id]) }}" title="{{ __('Answer this') }}">
                            <span class="cpx-av">{!! $avatar() !!}</span>
                            <span class="cpx-bub"><span class="tp">{{ $c->categoryLabel() }} · {{ $no($c) }}</span>@if($note)<strong>{{ $ask }}</strong>{{ $note }}@else{{ $c->message }}@endif</span>
                        </a>
                        <div class="cpx-meta">{{ $c->created_at->format('g:i A') }} · {{ $from($c) }}@if($c->isOpen() && ! $c->response) · <span class="wait">{{ $w ? __('waiting :t', ['t' => $w]) : __('waiting for your answer') }}</span>@endif</div>
                        @if($c->response)
                            <div class="cpx-msg out {{ $c->isOpen() ? 'open' : '' }}"><span class="cpx-bub">{{ $c->response }}</span></div>
                            <div class="cpx-meta out">
                                @if($c->isOpen()){{ __('Sent') }} · {{ __('still open') }}@else<b>{{ $c->resolver->name ?? __('Office') }}</b> · {{ $short($c->resolved_at) }} · <i class="fas fa-check-double"></i> {{ __('Resolved') }}@endif
                            </div>
                        @endif
                    @empty
                        <div class="cpx-hello">
                            <span class="cpx-av">{!! $avatar() !!}</span>
                            <b>{{ $employee->name }}</b>
                            {{ __("No messages yet. When :name writes from the kiosk's Report tab, it shows here and you are alerted in the bell.", ['name' => $first($employee->name)]) }}
                        </div>
                    @endforelse
                </div>

                @if($selected)
                    @php [$selAsk] = $split($selected->message); @endphp
                    <form class="cpx-compose" method="POST" action="{{ route('complaints.update', $selected) }}">
                        @csrf @method('PATCH')
                        @foreach($keep as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
                        <div class="cpx-to"><i class="fas fa-reply"></i><span>{{ $selected->isOpen() ? __('Answering') : __('Editing the answer to') }} <b>{{ $no($selected) }}</b> · {{ $selAsk }}</span></div>
                        @if($selected->isOpen())
                            <div class="cpx-quick">@foreach($quick as $line)<button type="button" data-line="{{ $line }}">{{ $line }}</button>@endforeach</div>
                        @endif
                        <div class="cpx-inp">
                            <textarea id="cpxAns" name="response" rows="1" maxlength="1000" placeholder="{{ __('Write to :name…', ['name' => $first($employee->name)]) }}">{{ old('response', $selected->response) }}</textarea>
                            @if($selected->isOpen())
                                <button class="cpx-btn" type="submit" name="action" value="save" title="{{ __('Send, and keep it open') }}">{{ __('Send') }}</button>
                                <button class="cpx-btn pri" type="submit" name="action" value="resolve"><i class="fas fa-paper-plane"></i>{{ __('Send & resolve') }}</button>
                            @else
                                <button class="cpx-btn" type="submit" name="action" value="reopen"><i class="fas fa-rotate-left"></i>{{ __('Reopen') }}</button>
                                <button class="cpx-btn pri" type="submit" name="action" value="save"><i class="fas fa-paper-plane"></i>{{ __('Update') }}</button>
                            @endif
                        </div>
                    </form>
                @endif
            </section>
        @else
            <aside class="cpx-card cpx-over">
                <div class="cpx-hello" style="margin: 0; text-align: left; max-width: none">
                    <b>{{ __('Nobody is waiting for an answer') }}</b>
                    {{ __('Pick a worker on the left to open the conversation. A new report from the kiosk puts the worker on top of the list, with an alert in the bell.') }}
                </div>
                <section>
                    <h3>{{ __('Reported in the last 30 days') }}<small>{{ __(':n in all', ['n' => $topics->sum()]) }}</small></h3>
                    @if($topics->isEmpty())
                        <p class="cpx-quiet">{{ __('Nothing reported in the last 30 days.') }}</p>
                    @else
                        <div class="cpx-bars">
                            @foreach($topics as $k => $n)
                                @php($pct = (int) round($n / max(1, $topics->sum()) * 100))
                                <div class="cpx-bar">
                                    <div class="hd"><span>{{ $categories[$k] ?? ucfirst($k) }}</span><b>{{ $n }}<em>{{ $pct }}%</em></b></div>
                                    <span class="tr"><i style="width: {{ max(3, $pct) }}%"></i></span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </section>
            </aside>
        @endif
    </div>
</div>

@push('scripts')
<script>
(function () {
    // Open at the newest message, or at the one being answered.
    const thread = document.getElementById('cpxThread');
    if (thread) {
        const sel = thread.querySelector('.cpx-msg.sel');
        if (sel && sel !== thread.querySelectorAll('.cpx-msg.in')[thread.querySelectorAll('.cpx-msg.in').length - 1]) sel.scrollIntoView({ block: 'center' });
        else thread.scrollTop = thread.scrollHeight;
    }
    document.querySelector('.cpx-chat.sel')?.scrollIntoView({ block: 'nearest' });

    // The box grows with what is written, as a chat box does.
    const box = document.getElementById('cpxAns');
    const fit = () => { box.style.height = 'auto'; box.style.height = Math.min(box.scrollHeight, 140) + 'px'; };
    if (box) { box.addEventListener('input', fit); fit(); }

    // A quick answer goes into the box, after anything already written.
    document.querySelectorAll('.cpx-quick [data-line]').forEach(b => b.addEventListener('click', () => {
        box.value = box.value.trim() ? box.value.trim() + ' ' + b.dataset.line : b.dataset.line;
        fit(); box.focus();
    }));
})();
</script>
@endpush
@endsection
