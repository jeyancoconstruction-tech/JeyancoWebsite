@extends('layouts')
@section('page_title', 'Complaints')

@push('styles')
<style>
/* Complaints (2026-10-20 revision) — an inbox that fills the screen: the
   figures and the filters in one band, then the list and the one picked side
   by side, each scrolling on its own. Theme tokens only, so it follows light
   and dark; the worker icon is black on safety yellow in both. */
.cpx { display: flex; flex-direction: column; gap: 12px; height: calc(100vh - 120px); min-height: 560px; }

.cpx-top { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }
.cpx-title { flex: 1; min-width: 240px; }
html[data-bs-theme] .cpx-title h1 { margin: 0; font-size: 22px; font-weight: 700; letter-spacing: -.02em; color: var(--text-primary); }
.cpx-title p { margin: 3px 0 0; font-size: 13px; color: var(--text-muted); }
.cpx-kpis { display: flex; background: var(--surface); border: 1px solid var(--border); border-radius: 10px; }
.cpx-kpis div { padding: 9px 18px; min-width: 0; }
.cpx-kpis div + div { border-left: 1px solid var(--border); }
.cpx-kpis span { display: block; font-size: 11px; font-weight: 600; letter-spacing: .05em; text-transform: uppercase; color: var(--text-muted); white-space: nowrap; }
.cpx-kpis b { display: block; margin-top: 2px; font-size: 19px; font-weight: 600; font-variant-numeric: tabular-nums; color: var(--text-primary); }
.cpx-kpis .bad b { color: var(--danger); }

.cpx-bar { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; padding: 8px; background: var(--surface); border: 1px solid var(--border); border-radius: 10px; }
.cpx-seg { display: inline-flex; padding: 3px; background: var(--bg-subtle); border: 1px solid var(--border); border-radius: 8px; }
.cpx-seg a { display: inline-flex; align-items: center; gap: 7px; padding: 5px 12px; border-radius: 6px; font-size: 13px; font-weight: 600; color: var(--text-muted); text-decoration: none; }
.cpx-seg a:hover { color: var(--text-primary); }
.cpx-seg a.on { background: var(--surface); color: var(--text-primary); box-shadow: 0 1px 2px rgba(16,24,40,.12), inset 0 0 0 1px var(--border); }
.cpx-seg em { font-style: normal; font-size: 11px; font-weight: 600; color: var(--text-muted); font-variant-numeric: tabular-nums; }
.cpx-seg a.on em { color: var(--brand); }
.cpx-bar .lbl { margin-left: 6px; font-size: 12px; font-weight: 600; color: var(--text-muted); }
.cpx-bar select, .cpx-bar input[type=search] { height: 34px; padding: 0 10px; border: 1px solid var(--border-md); border-radius: 8px; background: var(--surface); color: var(--text-primary); font-size: 13px; }
.cpx-bar input[type=search] { width: 220px; }
.cpx-bar .sp { flex: 1; }
.cpx-bar .reset { font-size: 13px; font-weight: 600; color: var(--brand); text-decoration: none; padding: 0 6px; }

.cpx-main { flex: 1; min-height: 0; display: grid; grid-template-columns: minmax(0, 1fr) 440px; gap: 12px; }
.cpx-list { min-height: 0; overflow-y: auto; background: var(--surface); border: 1px solid var(--border); border-radius: 12px; }
.cpx-grp { position: sticky; top: 0; z-index: 1; display: flex; align-items: center; gap: 8px; padding: 8px 16px; background: var(--bg-subtle);
           border-bottom: 1px solid var(--border); font-size: 11.5px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; color: var(--text-secondary); }
.cpx-grp small { margin-left: auto; font-size: 11.5px; font-weight: 500; letter-spacing: 0; text-transform: none; color: var(--text-muted); }
.cpx-row { position: relative; display: grid; grid-template-columns: 38px 170px minmax(0, 1fr) 128px 82px; gap: 14px; align-items: center;
           padding: 12px 16px; border-bottom: 1px solid var(--border); text-decoration: none; color: inherit; }
.cpx-row:hover { background: var(--bg-subtle); }
.cpx-row.sel { background: color-mix(in srgb, var(--brand) 9%, transparent); box-shadow: inset 3px 0 0 var(--brand); }
.cpx-row.safety::after { content: ''; position: absolute; right: 0; top: 10px; bottom: 10px; width: 3px; border-radius: 2px 0 0 2px; background: var(--danger); }
.cpx-ico { width: 38px; height: 38px; border-radius: 9px; display: grid; place-items: center; background: #f5b73b; color: #111; box-shadow: inset 0 0 0 1px rgba(0,0,0,.14); }
.cpx-nm { min-width: 0; }
.cpx-nm b { display: block; font-size: 13.5px; font-weight: 600; color: var(--text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.cpx-nm small, .cpx-when small { display: block; font-size: 12px; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.cpx-msg { min-width: 0; }
.cpx-msg b { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; font-size: 13.5px; font-weight: 500; line-height: 1.4; color: var(--text-primary); }
.cpx-topic { display: inline-flex; align-items: center; gap: 6px; margin-top: 4px; padding: 1px 8px; border: 1px solid var(--border-md); border-radius: 5px; font-size: 11.5px; font-weight: 600; color: var(--text-secondary); }
.cpx-topic i { width: 6px; height: 6px; border-radius: 50%; background: var(--text-muted); }
.cpx-topic.safety { color: var(--danger); border-color: color-mix(in srgb, var(--danger) 45%, transparent); }
.cpx-topic.safety i { background: var(--danger); }
.cpx-when { font-size: 12.5px; color: var(--text-secondary); font-variant-numeric: tabular-nums; min-width: 0; }
.cpx-st { justify-self: end; display: inline-flex; align-items: center; gap: 6px; padding: 3px 9px; border-radius: 999px; font-size: 11.5px; font-weight: 600; white-space: nowrap; border: 1px solid var(--border-md); color: var(--text-secondary); }
.cpx-st::before { content: ''; width: 6px; height: 6px; border-radius: 50%; }
.cpx-st.open::before { background: var(--warning, #d97706); }
.cpx-st.resolved::before { background: var(--success, #12b76a); }
.cpx-empty { display: grid; place-items: center; height: 100%; min-height: 240px; padding: 40px 20px; text-align: center; color: var(--text-muted); }
.cpx-empty b { display: block; margin-bottom: 4px; font-size: 14px; color: var(--text-secondary); }

/* The one picked: its own column, the answer and the buttons at its foot. */
.cpx-det { min-height: 0; display: flex; flex-direction: column; overflow: hidden; background: var(--surface); border: 1px solid var(--border); border-radius: 12px; }
.cpx-det::before { content: ''; flex: none; height: 4px; background: repeating-linear-gradient(-45deg, #f5b73b 0 12px, #1a1a1a 12px 24px); }
.cpx-det-h { flex: none; display: flex; align-items: center; gap: 12px; padding: 16px 18px; border-bottom: 1px solid var(--border); }
.cpx-det-h .cpx-ico { width: 46px; height: 46px; border-radius: 11px; }
.cpx-det-h div { min-width: 0; flex: 1; }
.cpx-det-h b { display: block; font-size: 16px; font-weight: 700; letter-spacing: -.01em; color: var(--text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.cpx-det-h small { display: block; font-size: 12.5px; color: var(--text-muted); }
.cpx-det-b { flex: 1; min-height: 0; overflow-y: auto; padding: 16px 18px; display: flex; flex-direction: column; gap: 16px; }
.cpx-meta { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); margin: 0; border: 1px solid var(--border); border-radius: 9px; }
.cpx-meta div { padding: 9px 12px; min-width: 0; }
.cpx-meta div + div { border-left: 1px solid var(--border); }
.cpx-meta dt { font-size: 11px; font-weight: 600; letter-spacing: .05em; text-transform: uppercase; color: var(--text-muted); }
.cpx-meta dd { margin: 3px 0 0; font-size: 13px; font-weight: 500; color: var(--text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.cpx-quote { position: relative; padding: 14px 16px; border-radius: 4px 12px 12px 12px; background: var(--bg-subtle); border: 1px solid var(--border);
             font-size: 14px; line-height: 1.55; color: var(--text-primary); white-space: pre-line; }
.cpx-quote small { display: block; margin-bottom: 6px; font-size: 11.5px; font-weight: 600; color: var(--text-muted); white-space: normal; }
.cpx-reply { padding: 12px 14px; border-radius: 12px 4px 12px 12px; margin-left: 28px; background: color-mix(in srgb, var(--brand) 9%, transparent);
             border: 1px solid color-mix(in srgb, var(--brand) 30%, transparent); font-size: 13.5px; line-height: 1.5; color: var(--text-primary); white-space: pre-line; }
.cpx-reply small { display: block; margin-bottom: 5px; font-size: 11.5px; font-weight: 600; color: var(--brand); white-space: normal; }
.cpx-tl { display: flex; flex-direction: column; gap: 9px; margin: 0 0 0 4px; padding-left: 16px; border-left: 2px solid var(--border); font-size: 12.5px; color: var(--text-secondary); }
.cpx-tl div { position: relative; }
.cpx-tl div::before { content: ''; position: absolute; left: -21px; top: 4px; width: 10px; height: 10px; border-radius: 50%; background: var(--surface); border: 2px solid var(--border-md); }
.cpx-tl div.ok::before { border-color: var(--success, #12b76a); background: var(--success, #12b76a); }
.cpx-tl div.now::before { border-color: var(--warning, #d97706); }
.cpx-tl b { color: var(--text-primary); font-weight: 600; }

.cpx-form { flex: none; padding: 14px 18px 16px; border-top: 1px solid var(--border); background: var(--bg-subtle); }
.cpx-form label { display: block; margin-bottom: 6px; font-size: 12.5px; font-weight: 600; color: var(--text-secondary); }
.cpx-form textarea { width: 100%; min-height: 96px; padding: 10px 12px; border: 1px solid var(--border-md); border-radius: 9px; background: var(--surface);
                     color: var(--text-primary); font-size: 13.5px; resize: vertical; }
.cpx-form textarea:focus { outline: none; border-color: var(--brand); box-shadow: 0 0 0 3px color-mix(in srgb, var(--brand) 18%, transparent); }
.cpx-form .hint { margin-top: 5px; font-size: 12px; color: var(--text-muted); }
.cpx-acts { display: flex; gap: 8px; margin-top: 12px; flex-wrap: wrap; }
.cpx-acts .sp { flex: 1; }
.cpx-btn { height: 36px; padding: 0 14px; border-radius: 8px; display: inline-flex; align-items: center; gap: 7px; font-size: 13px; font-weight: 600; text-decoration: none; cursor: pointer;
           border: 1px solid var(--border-md); background: var(--surface); color: var(--text-primary); }
.cpx-btn:hover { background: var(--bg-subtle); color: var(--text-primary); }
.cpx-btn.pri { background: var(--brand); border-color: var(--brand); color: #fff; }
.cpx-btn.pri:hover { filter: brightness(1.08); color: #fff; }
.cpx-btn i { font-size: 12px; opacity: .85; }
.cpx-none { display: grid; place-items: center; height: 100%; padding: 30px; text-align: center; color: var(--text-muted); font-size: 13px; }

@media (max-width: 1280px) { .cpx-row { grid-template-columns: 38px 150px minmax(0, 1fr) 82px; } .cpx-row .cpx-when { display: none; } }
@media (max-width: 1100px) {
    .cpx { height: auto; }
    .cpx-main { grid-template-columns: 1fr; }
    .cpx-list, .cpx-det { overflow: visible; }
    .cpx-det-b { overflow: visible; }
}
@media (max-width: 760px) {
    .cpx-row { grid-template-columns: 38px minmax(0, 1fr) 82px; } .cpx-row .cpx-msg { display: none; }
    .cpx-kpis { width: 100%; } .cpx-kpis div { flex: 1; padding: 8px 10px; }
    .cpx-bar input[type=search] { width: 100%; }
}
</style>
@endpush

@section('content')
@php
    $keep = array_filter(['status' => $status !== 'open' ? $status : null, 'group' => $group !== 'site' ? $group : null,
                          'category' => $category, 'site' => $siteId, 'q' => $q !== '' ? $q : null]);
    $url  = fn (array $over) => route('complaints.index', array_filter(array_merge($keep, $over), fn ($v) => $v !== null && $v !== ''));
    $when = fn ($d) => $d ? ($d->isToday() ? __('Today') . ', ' . $d->format('g:i A') : ($d->isYesterday() ? __('Yesterday') . ', ' . $d->format('g:i A') : $d->format('M j, g:i A'))) : '';
    $from = fn ($c) => $c->kiosk ? ($c->kiosk->name ?: $c->kiosk->code) : __('Kiosk');
@endphp
<div class="cpx">
    <div class="cpx-top">
        <div class="cpx-title">
            <h1>{{ __('Complaints') }}</h1>
            <p>{{ __("Sent by workers from the kiosk's Report tab. Your answer appears on the kiosk the next time they scan.") }}</p>
        </div>
        <div class="cpx-kpis">
            <div><span>{{ __('Open') }}</span><b>{{ $stats['open'] }}</b></div>
            <div><span>{{ __('Answered this week') }}</span><b>{{ $stats['week'] }}</b></div>
            <div class="{{ $stats['safety'] ? 'bad' : '' }}"><span>{{ __('Safety open') }}</span><b>{{ $stats['safety'] }}</b></div>
            <div><span>{{ __('Avg. answer time') }}</span><b>{{ $stats['avgHours'] === null ? '—' : ($stats['avgHours'] < 1 ? round($stats['avgHours'] * 60) . ' min' : $stats['avgHours'] . ' h') }}</b></div>
        </div>
    </div>

    @include('modules._flash')

    <form class="cpx-bar" method="GET" action="{{ route('complaints.index') }}">
        @if($status !== 'open')<input type="hidden" name="status" value="{{ $status }}">@endif
        <input type="hidden" name="group" value="{{ $group }}">
        <div class="cpx-seg" role="tablist" aria-label="{{ __('Status') }}">
            <a class="{{ $status === 'open' ? 'on' : '' }}" href="{{ $url(['status' => null, 'c' => null]) }}">{{ __('Open') }} <em>{{ $counts['open'] }}</em></a>
            <a class="{{ $status === 'resolved' ? 'on' : '' }}" href="{{ $url(['status' => 'resolved', 'c' => null]) }}">{{ __('Resolved') }} <em>{{ $counts['resolved'] }}</em></a>
            <a class="{{ $status === 'all' ? 'on' : '' }}" href="{{ $url(['status' => 'all', 'c' => null]) }}">{{ __('All') }} <em>{{ $counts['all'] }}</em></a>
        </div>
        <span class="lbl">{{ __('Group by') }}</span>
        <div class="cpx-seg">
            <a class="{{ $group === 'site' ? 'on' : '' }}" href="{{ $url(['group' => null]) }}">{{ __('Site') }}</a>
            <a class="{{ $group === 'employee' ? 'on' : '' }}" href="{{ $url(['group' => 'employee']) }}">{{ __('Employee') }}</a>
        </div>
        <span class="sp"></span>
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
    </form>

    <div class="cpx-main">
        <div class="cpx-list">
            @forelse($groups as $name => $items)
                <div class="cpx-grp">{{ $name }} <small>{{ $items->count() }} {{ $items->count() === 1 ? __('report') : __('reports') }}</small></div>
                @foreach($items as $c)
                    <a class="cpx-row {{ $selected && $selected->id === $c->id ? 'sel' : '' }} {{ $c->category === 'safety' ? 'safety' : '' }}" href="{{ $url(['c' => $c->id]) }}">
                        <span class="cpx-ico">@include('partials.worker-icon', ['size' => '64%'])</span>
                        <span class="cpx-nm"><b>{{ $c->employee->name ?? __('Unknown') }}</b><small>{{ $group === 'site' ? ($c->employee->position ?? '') : ($c->site->name ?? '') }}</small></span>
                        <span class="cpx-msg"><b>{{ $c->message }}</b><span class="cpx-topic {{ $c->category === 'safety' ? 'safety' : '' }}"><i></i>{{ $c->categoryLabel() }}</span></span>
                        <span class="cpx-when">{{ $when($c->created_at) }}<small>{{ $from($c) }}</small></span>
                        <span class="cpx-st {{ $c->isOpen() ? 'open' : 'resolved' }}">{{ $c->isOpen() ? __('Open') : __('Resolved') }}</span>
                    </a>
                @endforeach
            @empty
                <div class="cpx-empty"><div><b>{{ $status === 'open' ? __('No open complaints') : __('Nothing here') }}</b>{{ __('Workers send them from the kiosk, in the Report tab.') }}</div></div>
            @endforelse
        </div>

        @if($selected)
            <aside class="cpx-det">
                <div class="cpx-det-h">
                    <span class="cpx-ico">@include('partials.worker-icon', ['size' => '64%'])</span>
                    <div><b>{{ $selected->employee->name ?? __('Unknown') }}</b><small>{{ $selected->employee->position ?? '' }}@if($selected->site) · {{ $selected->site->name }}@endif</small></div>
                    <span class="cpx-st {{ $selected->isOpen() ? 'open' : 'resolved' }}">{{ $selected->isOpen() ? __('Open') : __('Resolved') }}</span>
                </div>
                <div class="cpx-det-b">
                    <dl class="cpx-meta">
                        <div><dt>{{ __('Reference') }}</dt><dd>#{{ $selected->id }}</dd></div>
                        <div><dt>{{ __('Topic') }}</dt><dd>{{ $selected->categoryLabel() }}</dd></div>
                        <div><dt>{{ __('Sent from') }}</dt><dd>{{ $from($selected) }}</dd></div>
                    </dl>
                    <div class="cpx-quote"><small>{{ \Illuminate\Support\Str::before($selected->employee->name ?? __('Worker'), ' ') }} · {{ $when($selected->created_at) }}</small>{{ $selected->message }}</div>
                    @if($selected->response)
                        <div class="cpx-reply"><small>{{ __('Your answer') }}@if($selected->resolver) · {{ $selected->resolver->name }}@endif</small>{{ $selected->response }}</div>
                    @endif
                    <div class="cpx-tl">
                        <div class="ok"><b>{{ __('Received') }}</b> · {{ $when($selected->created_at) }}</div>
                        @if($selected->isOpen())
                            <div class="now">{{ $selected->response ? __('Answer saved, not resolved yet') : __('Not answered yet') }}</div>
                        @else
                            <div class="ok"><b>{{ __('Resolved') }}</b> · {{ $when($selected->resolved_at) }}@if($selected->resolver) · {{ $selected->resolver->name }}@endif</div>
                        @endif
                    </div>
                </div>
                <form class="cpx-form" method="POST" action="{{ route('complaints.update', $selected) }}">
                    @csrf @method('PATCH')
                    @foreach($keep as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
                    <label for="cpxAns">{{ __('Answer for the worker') }}</label>
                    <textarea id="cpxAns" name="response" maxlength="1000" placeholder="{{ __('What was done, or what they should do next') }}">{{ old('response', $selected->response) }}</textarea>
                    <div class="hint">{{ __('Shown on the kiosk when :name scans in the Report tab.', ['name' => \Illuminate\Support\Str::before($selected->employee->name ?? __('the worker'), ' ')]) }}</div>
                    <div class="cpx-acts">
                        @if($selected->employee && \Illuminate\Support\Facades\Route::has('payroll-reports.dtr') && auth()->user()?->canAccessModule('payroll-reports'))
                            <a class="cpx-btn" href="{{ route('payroll-reports.index', ['report' => 'dtr', 'q' => $selected->employee->name]) }}"><i class="fas fa-calendar-days"></i>{{ __('Open DTR') }}</a>
                        @endif
                        <span class="sp"></span>
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
            <aside class="cpx-det"><div class="cpx-none">{{ __('Pick a report on the left to read and answer it.') }}</div></aside>
        @endif
    </div>
</div>
@endsection
