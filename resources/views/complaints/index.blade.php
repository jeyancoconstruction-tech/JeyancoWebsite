@extends('layouts')
@section('page_title', 'Complaints')

@push('styles')
<style>
/* Complaints (2026-11-12 revision, "calm") — an inbox: the figures as plain
   numbers, the tabs underlined, the list as name / topic / first line / time,
   and the one picked with the message as a quote and one answer box. Navy is
   the one accent, red only for Safety. Theme tokens for surfaces and text. */
.cpx { --cx-navy: #1f3a5f; --cx-navy-ink: #1f3a5f; --cx-green: #2f7a4f; --cx-amber: #9a6418; --cx-red: #b4372b; --cx-red-bg: #fbeceA; --cx-line: var(--border, #e6e8ec);
       display: flex; flex-direction: column; gap: 18px; height: calc(100vh - 120px); min-height: 560px; }
[data-bs-theme="dark"] .cpx { --cx-navy: #2c5282; --cx-navy-ink: #8fb3df; --cx-green: #6cc08e; --cx-amber: #e3b467; --cx-red: #f08a7e; --cx-red-bg: rgba(240, 138, 126, .1); }

.cpx-top { display: flex; align-items: flex-end; gap: 24px; flex-wrap: wrap; }
.cpx-title { flex: 1; min-width: 260px; }
html[data-bs-theme] .cpx-title h1 { margin: 0; font-size: 24px; font-weight: 600; letter-spacing: -.015em; color: var(--text-primary); }
.cpx-title p { margin: 4px 0 0; font-size: 13px; color: var(--text-muted); }
.cpx-kpis { display: flex; }
.cpx-kpis div { padding: 0 0 0 22px; margin-left: 22px; border-left: 1px solid var(--cx-line); }
.cpx-kpis div:first-child { padding: 0; margin: 0; border: 0; }
.cpx-kpis span { display: block; font-size: 11.5px; color: var(--text-muted); white-space: nowrap; }
.cpx-kpis b { display: block; margin-top: 2px; font-size: 20px; font-weight: 600; font-variant-numeric: tabular-nums; color: var(--text-primary); }
.cpx-kpis .bad b { color: var(--cx-red); }

.cpx-bar { display: flex; align-items: center; gap: 22px; flex-wrap: wrap; border-bottom: 1px solid var(--cx-line); }
.cpx-tabs { display: flex; gap: 22px; }
.cpx-tabs a { padding: 10px 0 11px; margin-bottom: -1px; border-bottom: 2px solid transparent; font-size: 13.5px; font-weight: 500; color: var(--text-muted); text-decoration: none; }
.cpx-tabs a:hover { color: var(--text-primary); }
.cpx-tabs a.on { color: var(--text-primary); font-weight: 600; border-color: var(--text-primary); }
.cpx-tabs em { font-style: normal; margin-left: 5px; font-weight: 500; color: var(--text-muted); opacity: .8; font-variant-numeric: tabular-nums; }
.cpx-by { display: flex; align-items: center; gap: 4px; font-size: 12.5px; color: var(--text-muted); }
.cpx-by a { padding: 4px 8px; border-radius: 6px; color: var(--text-muted); text-decoration: none; font-weight: 500; }
.cpx-by a.on { color: var(--text-primary); font-weight: 600; background: var(--bg-subtle); }
.cpx-flt { margin-left: auto; display: flex; gap: 8px; align-items: center; padding: 6px 0 8px; }
.cpx-flt select, .cpx-flt input[type=search] { height: 34px; padding: 0 10px; border: 1px solid var(--border-md); border-radius: 8px; background: var(--surface); color: var(--text-primary); font-size: 12.5px; }
.cpx-flt input[type=search] { width: 220px; }
.cpx-flt .reset { font-size: 12.5px; font-weight: 600; color: var(--cx-navy-ink); text-decoration: none; padding: 0 4px; }

.cpx-main { flex: 1; min-height: 0; display: grid; grid-template-columns: 430px minmax(0, 1fr); gap: 20px; }
.cpx-main.is-empty { grid-template-columns: 1fr; }
.cpx-card { min-height: 0; background: var(--surface); border: 1px solid var(--cx-line); border-radius: 12px; }
.cpx-list { overflow-y: auto; }
.cpx-grp { position: sticky; top: 0; z-index: 1; display: flex; justify-content: space-between; padding: 14px 18px 8px; background: var(--surface);
           font-size: 12px; font-weight: 600; color: var(--text-muted); }
.cpx-grp + .cpx-row { border-top: 1px solid var(--cx-line); }
.cpx-row { position: relative; display: flex; gap: 12px; padding: 12px 18px; border-bottom: 1px solid var(--cx-line); text-decoration: none; color: inherit; }
.cpx-row:hover { background: var(--bg-subtle); }
.cpx-row.sel { background: color-mix(in srgb, var(--cx-navy-ink) 7%, var(--surface)); }
.cpx-row.sel::before { content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 3px; background: var(--cx-navy-ink); }
.cpx-ico { flex: none; width: 36px; height: 36px; border-radius: 9px; display: grid; place-items: center; }
.cpx-b { flex: 1; min-width: 0; }
.cpx-n { display: flex; justify-content: space-between; gap: 8px; font-size: 13.5px; font-weight: 600; color: var(--text-primary); }
.cpx-n span { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.cpx-n time { flex: none; font-size: 12px; font-weight: 400; color: var(--text-muted); }
.cpx-tp { margin-top: 1px; font-size: 12px; color: var(--text-muted); }
.cpx-tp.safety { color: var(--cx-red); font-weight: 600; }
.cpx-tp .done { color: var(--cx-green); font-weight: 600; }
.cpx-tx { margin-top: 4px; font-size: 13px; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

/* The one picked. */
.cpx-det { display: flex; flex-direction: column; overflow: hidden; }
.cpx-det-h { flex: none; display: flex; align-items: center; gap: 14px; padding: 20px 26px 18px; border-bottom: 1px solid var(--cx-line); }
.cpx-det-h .cpx-ico { width: 46px; height: 46px; border-radius: 11px; }
.cpx-det-h > div { min-width: 0; flex: 1; }
.cpx-det-h b { display: block; font-size: 16px; font-weight: 600; color: var(--text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.cpx-det-h small { display: block; margin-top: 2px; font-size: 12.5px; color: var(--text-muted); }
.cpx-tag { flex: none; display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; color: var(--text-muted); background: var(--bg-subtle); }
.cpx-tag::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
.cpx-tag.safety { color: var(--cx-red); background: var(--cx-red-bg); }
.cpx-tag.resolved { color: var(--cx-green); }
.cpx-det-b { flex: 1; min-height: 0; overflow-y: auto; padding: 22px 26px; display: flex; flex-direction: column; gap: 20px; }
.cpx-quote { padding-left: 16px; border-left: 3px solid var(--cx-line); font-size: 15px; line-height: 1.65; color: var(--text-primary); white-space: pre-line; }
.cpx-facts { display: flex; flex-wrap: wrap; gap: 14px 34px; font-size: 12.5px; }
.cpx-facts span { display: block; font-size: 11.5px; color: var(--text-muted); }
.cpx-facts b { display: block; margin-top: 3px; font-weight: 500; color: var(--text-primary); }
.cpx-facts a { color: var(--cx-navy-ink); font-weight: 600; text-decoration: none; }
.cpx-reply { padding: 12px 16px; border-radius: 10px; background: var(--bg-subtle); font-size: 13.5px; line-height: 1.55; color: var(--text-primary); white-space: pre-line; }
.cpx-reply small { display: block; margin-bottom: 4px; font-size: 11.5px; font-weight: 600; color: var(--text-muted); white-space: normal; }

.cpx-form { flex: none; }
.cpx-ans { padding: 0 26px 18px; }
.cpx-ans label { display: block; margin-bottom: 8px; font-size: 13px; font-weight: 600; color: var(--text-primary); }
.cpx-ans label small { margin-left: 6px; font-weight: 400; color: var(--text-muted); }
.cpx-ans textarea { width: 100%; min-height: 104px; padding: 12px 14px; border: 1px solid var(--border-md); border-radius: 10px; background: var(--surface);
                    color: var(--text-primary); font-size: 13.5px; resize: vertical; }
.cpx-ans textarea:focus { outline: none; border-color: var(--cx-navy-ink); box-shadow: 0 0 0 3px color-mix(in srgb, var(--cx-navy-ink) 16%, transparent); }
.cpx-foot { display: flex; align-items: center; gap: 10px; padding: 14px 26px; border-top: 1px solid var(--cx-line); }
.cpx-foot .s { flex: 1; min-width: 0; font-size: 12.5px; color: var(--text-muted); }
.cpx-btn { height: 36px; padding: 0 15px; border-radius: 8px; display: inline-flex; align-items: center; gap: 7px; font-size: 13px; font-weight: 600; text-decoration: none; cursor: pointer;
           border: 1px solid var(--border-md); background: transparent; color: var(--text-primary); white-space: nowrap; }
.cpx-btn:hover { background: var(--bg-subtle); color: var(--text-primary); }
.cpx-btn.pri { background: var(--cx-navy); border-color: var(--cx-navy); color: #fff; }
.cpx-btn.pri:hover { filter: brightness(1.12); color: #fff; }
.cpx-btn i { font-size: 12px; opacity: .85; }
.cpx-none { display: grid; place-items: center; height: 100%; padding: 30px; text-align: center; color: var(--text-muted); font-size: 13px; }

/* Nothing in the list: one calm message, and the last few answered. */
.cpx-empty { display: grid; place-items: center; height: 100%; padding: 40px 20px; overflow-y: auto; }
.cpx-empty > div { width: 100%; max-width: 520px; text-align: center; }
.cpx-empty .ic { width: 56px; height: 56px; border-radius: 50%; margin: 0 auto 14px; display: grid; place-items: center; font-size: 20px;
                 color: var(--cx-green); background: color-mix(in srgb, var(--cx-green) 12%, transparent); }
.cpx-empty h2 { margin: 0; font-size: 17px; font-weight: 600; color: var(--text-primary); }
.cpx-empty p { margin: 6px 0 0; font-size: 13px; color: var(--text-muted); }
.cpx-last { margin-top: 28px; text-align: left; }
.cpx-last h3 { display: flex; justify-content: space-between; margin: 0 0 4px; padding-bottom: 8px; border-bottom: 1px solid var(--cx-line); font-size: 12px; font-weight: 600; color: var(--text-muted); }
.cpx-last h3 a { color: var(--cx-navy-ink); text-decoration: none; }
.cpx-last .cpx-row { padding: 12px 0; }
.cpx-last .cpx-row:last-child { border-bottom: 0; }

@media (max-width: 1100px) {
    .cpx { height: auto; }
    .cpx-main { grid-template-columns: 1fr; }
    .cpx-list, .cpx-det, .cpx-det-b { overflow: visible; }
    .cpx-list { max-height: 420px; overflow-y: auto; }
}
@media (max-width: 760px) {
    .cpx-kpis { width: 100%; } .cpx-kpis div { flex: 1; padding-left: 12px; margin-left: 12px; }
    .cpx-flt { width: 100%; margin-left: 0; flex-wrap: wrap; } .cpx-flt input[type=search] { width: 100%; }
    .cpx-foot { flex-wrap: wrap; } .cpx-foot .s { flex-basis: 100%; }
}
</style>
@endpush

@section('content')
@php
    $keep = array_filter(['status' => $status !== 'open' ? $status : null, 'group' => $group !== 'site' ? $group : null,
                          'category' => $category, 'site' => $siteId, 'q' => $q !== '' ? $q : null]);
    $url  = fn (array $over) => route('complaints.index', array_filter(array_merge($keep, $over), fn ($v) => $v !== null && $v !== ''));
    $when = fn ($d) => $d ? ($d->isToday() ? __('Today') . ', ' . $d->format('g:i A') : ($d->isYesterday() ? __('Yesterday') . ', ' . $d->format('g:i A') : $d->format('M j, g:i A'))) : '';
    // The list's time: just the time today, a word yesterday, the date before.
    $short = fn ($d) => $d ? ($d->isToday() ? $d->format('g:i A') : ($d->isYesterday() ? __('Yesterday') : $d->format('M j'))) : '';
    $from = fn ($c) => $c->kiosk ? ($c->kiosk->name ?: $c->kiosk->code) : __('Kiosk');
    $first = fn ($name) => \Illuminate\Support\Str::before($name ?? __('the worker'), ' ');
    $canDtr = \Illuminate\Support\Facades\Route::has('payroll-reports.dtr') && auth()->user()?->canAccessModule('payroll-reports');
    $empty = $groups->isEmpty();
@endphp
<div class="cpx">
    <div class="cpx-top">
        <div class="cpx-title">
            <h1>{{ __('Complaints') }}</h1>
            <p>{{ __("Sent by workers from the kiosk's Report tab. Your answer appears on the kiosk the next time they scan.") }}</p>
        </div>
        <div class="cpx-kpis">
            <div><span>{{ __('Open') }}</span><b>{{ $stats['open'] }}</b></div>
            <div class="{{ $stats['safety'] ? 'bad' : '' }}"><span>{{ __('Safety open') }}</span><b>{{ $stats['safety'] }}</b></div>
            <div><span>{{ __('Answered this week') }}</span><b>{{ $stats['week'] }}</b></div>
            <div><span>{{ __('Avg. answer time') }}</span><b>{{ $stats['avgHours'] === null ? '—' : ($stats['avgHours'] < 1 ? round($stats['avgHours'] * 60) . ' min' : $stats['avgHours'] . ' h') }}</b></div>
        </div>
    </div>

    @include('modules._flash')

    <form class="cpx-bar" method="GET" action="{{ route('complaints.index') }}">
        @if($status !== 'open')<input type="hidden" name="status" value="{{ $status }}">@endif
        <input type="hidden" name="group" value="{{ $group }}">
        <nav class="cpx-tabs" aria-label="{{ __('Status') }}">
            <a class="{{ $status === 'open' ? 'on' : '' }}" href="{{ $url(['status' => null, 'c' => null]) }}">{{ __('Open') }}<em>{{ $counts['open'] }}</em></a>
            <a class="{{ $status === 'resolved' ? 'on' : '' }}" href="{{ $url(['status' => 'resolved', 'c' => null]) }}">{{ __('Resolved') }}<em>{{ $counts['resolved'] }}</em></a>
            <a class="{{ $status === 'all' ? 'on' : '' }}" href="{{ $url(['status' => 'all', 'c' => null]) }}">{{ __('All') }}<em>{{ $counts['all'] }}</em></a>
        </nav>
        <div class="cpx-by">{{ __('Group by') }}
            <a class="{{ $group === 'site' ? 'on' : '' }}" href="{{ $url(['group' => null]) }}">{{ __('Site') }}</a>
            <a class="{{ $group === 'employee' ? 'on' : '' }}" href="{{ $url(['group' => 'employee']) }}">{{ __('Employee') }}</a>
        </div>
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

    <div class="cpx-main {{ $empty ? 'is-empty' : '' }}">
        @if($empty)
            <div class="cpx-card cpx-empty">
                <div>
                    <div class="ic"><i class="fas {{ $status === 'open' && ! $keep ? 'fa-check' : 'fa-magnifying-glass' }}"></i></div>
                    <h2>{{ $status === 'open' ? __('No open complaints') : __('Nothing here') }}</h2>
                    <p>{{ $keep ? __('Nothing matches these filters.') : __('Workers send them from the kiosk, in the Report tab.') }}</p>
                    @if($recent->isNotEmpty())
                        <div class="cpx-last">
                            <h3><span>{{ __('Recently answered') }}</span><a href="{{ route('complaints.index', ['status' => 'resolved']) }}">{{ __('See all resolved') }}</a></h3>
                            @foreach($recent as $c)
                                <a class="cpx-row" href="{{ route('complaints.index', ['status' => 'resolved', 'c' => $c->id]) }}">
                                    <span class="cpx-ico">@include('partials.worker-icon', ['size' => '64%'])</span>
                                    <span class="cpx-b">
                                        <span class="cpx-n"><span>{{ $c->employee->name ?? __('Unknown') }}</span><time>{{ $short($c->resolved_at) }}</time></span>
                                        <span class="cpx-tp d-block">{{ $c->categoryLabel() }}</span>
                                        <span class="cpx-tx d-block">{{ $c->response ? __('Answered') . ': "' . $c->response . '"' : $c->message }}</span>
                                    </span>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        @else
            <div class="cpx-card cpx-list">
                @foreach($groups as $name => $items)
                    <div class="cpx-grp"><span>{{ $name }}</span><span>{{ $items->count() }}</span></div>
                    @foreach($items as $c)
                        <a class="cpx-row {{ $selected && $selected->id === $c->id ? 'sel' : '' }}" href="{{ $url(['c' => $c->id]) }}">
                            <span class="cpx-ico">@include('partials.worker-icon', ['size' => '64%'])</span>
                            <span class="cpx-b">
                                <span class="cpx-n"><span>{{ $group === 'site' ? ($c->employee->name ?? __('Unknown')) : ($c->site->name ?? $c->employee?->site?->name ?? __('No site')) }}</span><time>{{ $short($c->created_at) }}</time></span>
                                <span class="cpx-tp d-block {{ $c->category === 'safety' ? 'safety' : '' }}">{{ $c->categoryLabel() }}@if($status === 'all' && ! $c->isOpen()) · <span class="done">{{ __('Resolved') }}</span>@endif</span>
                                <span class="cpx-tx d-block">{{ $c->message }}</span>
                            </span>
                        </a>
                    @endforeach
                @endforeach
            </div>

            @if($selected)
                <aside class="cpx-card cpx-det">
                    <div class="cpx-det-h">
                        <span class="cpx-ico">@include('partials.worker-icon', ['size' => '64%'])</span>
                        <div>
                            <b>{{ $selected->employee->name ?? __('Unknown') }}</b>
                            <small>@if($selected->employee)#{{ str_pad($selected->employee->id, 4, '0', STR_PAD_LEFT) }}@endif{{ $selected->employee?->position ? ' / ' . $selected->employee->position : '' }}{{ $selected->site ? ' / ' . $selected->site->name : '' }}</small>
                        </div>
                        @if($selected->category === 'safety')
                            <span class="cpx-tag safety">{{ $selected->categoryLabel() }}</span>
                        @elseif(! $selected->isOpen())
                            <span class="cpx-tag resolved">{{ __('Resolved') }}</span>
                        @else
                            <span class="cpx-tag">{{ $selected->categoryLabel() }}</span>
                        @endif
                    </div>
                    <div class="cpx-det-b">
                        <div class="cpx-quote">{{ $selected->message }}</div>
                        <div class="cpx-facts">
                            <div><span>{{ __('Received') }}</span><b>{{ $when($selected->created_at) }}</b></div>
                            <div><span>{{ __('Sent from') }}</span><b>{{ $from($selected) }}</b></div>
                            <div><span>{{ __('Topic') }}</span><b>{{ $selected->categoryLabel() }}</b></div>
                            <div><span>{{ __('Reference') }}</span><b>#{{ $selected->id }}</b></div>
                            @if($selected->employee)
                                <div><span>{{ __('Worker') }}</span><b>
                                    <a href="{{ route('employees.show', $selected->employee->id) }}">{{ __('Open profile') }}</a>@if($canDtr) · <a href="{{ route('payroll-reports.index', ['report' => 'dtr', 'q' => $selected->employee->name]) }}">{{ __('Open DTR') }}</a>@endif
                                </b></div>
                            @endif
                        </div>
                        @if($selected->response && ! $selected->isOpen())
                            <div class="cpx-reply"><small>{{ __('Your answer') }}@if($selected->resolver) · {{ $selected->resolver->name }}@endif</small>{{ $selected->response }}</div>
                        @endif
                    </div>
                    <form class="cpx-form" method="POST" action="{{ route('complaints.update', $selected) }}">
                        @csrf @method('PATCH')
                        @foreach($keep as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
                        <div class="cpx-ans">
                            <label for="cpxAns">{{ __('Your answer') }}<small>{{ __('Shown to :name at the kiosk on their next scan.', ['name' => $first($selected->employee->name ?? null)]) }}</small></label>
                            <textarea id="cpxAns" name="response" maxlength="1000" placeholder="{{ __('What was done, or what they should do next') }}">{{ old('response', $selected->response) }}</textarea>
                        </div>
                        <div class="cpx-foot">
                            <div class="s">
                                @if($selected->isOpen())
                                    {{ $selected->response ? __('Answer saved, not resolved yet') : __('Not answered yet') }}
                                @else
                                    {{ __('Resolved') }} · {{ $when($selected->resolved_at) }}@if($selected->resolver) · {{ $selected->resolver->name }}@endif
                                @endif
                            </div>
                            @if($selected->isOpen())
                                <button class="cpx-btn" type="submit" name="action" value="save">{{ __('Save answer') }}</button>
                                <button class="cpx-btn pri" type="submit" name="action" value="resolve"><i class="fas fa-check"></i>{{ __('Answer & resolve') }}</button>
                            @else
                                <button class="cpx-btn" type="submit" name="action" value="reopen">{{ __('Reopen') }}</button>
                                <button class="cpx-btn pri" type="submit" name="action" value="save">{{ __('Update answer') }}</button>
                            @endif
                        </div>
                    </form>
                </aside>
            @else
                <aside class="cpx-card cpx-det"><div class="cpx-none">{{ __('Pick a report on the left to read and answer it.') }}</div></aside>
            @endif
        @endif
    </div>
</div>
@endsection
