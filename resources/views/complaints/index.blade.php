@extends('layouts')
@section('page_title', 'Complaints')

@push('styles')
<style>
/* Complaints (Leave & Advances) — the list on the left, grouped by site or by
   worker; the one picked on the right with its answer box. Theme tokens only,
   so it follows light and dark. */
.cpx { display: flex; flex-direction: column; gap: 14px; }
.cpx-sum { display: flex; background: var(--surface); border: 1px solid var(--border); border-radius: 12px; }
.cpx-sum div { flex: 1; padding: 13px 18px; border-left: 1px solid var(--border); min-width: 0; }
.cpx-sum div:first-child { border-left: 0; }
.cpx-sum span { display: block; font-size: 12.5px; color: var(--text-muted); font-weight: 500; }
.cpx-sum b { display: block; font-size: 21px; font-weight: 700; margin-top: 2px; color: var(--text-primary); font-variant-numeric: tabular-nums; }
.cpx-sum .bad b { color: var(--danger); }
.cpx-tabs { display: flex; gap: 4px; border-bottom: 1px solid var(--border); }
.cpx-tabs a { padding: 9px 12px 10px; font-weight: 600; font-size: 13.5px; color: var(--text-muted); text-decoration: none; display: inline-flex; gap: 7px; align-items: center; }
.cpx-tabs a.on { color: var(--text-primary); box-shadow: inset 0 -2px 0 var(--brand); }
.cpx-tabs em { font-style: normal; font-size: 11.5px; color: var(--text-muted); background: var(--bg-subtle); border: 1px solid var(--border); border-radius: 10px; padding: 0 7px; }
.cpx-bar { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.cpx-bar .lbl { font-size: 12.5px; font-weight: 600; color: var(--text-muted); }
.cpx-seg { display: inline-flex; background: var(--bg-subtle); border: 1px solid var(--border); border-radius: 9px; padding: 3px; }
.cpx-seg a { padding: 5px 12px; border-radius: 7px; font-weight: 600; font-size: 13px; color: var(--text-muted); text-decoration: none; }
.cpx-seg a.on { background: var(--surface); color: var(--text-primary); box-shadow: 0 1px 2px rgba(16,24,40,.08); }
.cpx-bar select, .cpx-bar input { height: 36px; border: 1px solid var(--border-md); border-radius: 9px; background: var(--surface); color: var(--text-primary); padding: 0 10px; font-size: 13.5px; }
.cpx-bar input { width: 240px; }
.cpx-bar .sp { flex: 1; }
.cpx-bar .reset { color: var(--brand); font-weight: 600; font-size: 13px; text-decoration: none; }
.cpx-main { display: grid; grid-template-columns: minmax(0, 1fr) 380px; gap: 14px; align-items: start; }
.cpx-list { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; }
.cpx-grp { display: flex; align-items: center; gap: 8px; padding: 9px 16px; background: var(--bg-subtle); border-bottom: 1px solid var(--border); font-weight: 600; font-size: 12.5px; color: var(--text-secondary); }
.cpx-grp small { color: var(--text-muted); font-weight: 500; }
.cpx-row { display: grid; grid-template-columns: 34px 160px minmax(0, 1fr) 118px 70px; gap: 14px; align-items: center; padding: 12px 16px; border-bottom: 1px solid var(--border); text-decoration: none; color: inherit; }
.cpx-row:hover { background: var(--bg-subtle); }
.cpx-row.sel { background: var(--brand-subtle); box-shadow: inset 3px 0 0 var(--brand); }
.cpx-av { width: 34px; height: 34px; border-radius: 50%; background: var(--bg-subtle); border: 1px solid var(--border); color: var(--text-secondary); display: grid; place-items: center; font-weight: 700; font-size: 11.5px; }
.cpx-nm b { display: block; font-weight: 600; color: var(--text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.cpx-nm small, .cpx-when small { display: block; color: var(--text-muted); font-size: 12px; }
.cpx-msg { min-width: 0; }
.cpx-msg b { display: block; font-weight: 500; color: var(--text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.cpx-topic { font-size: 12px; color: var(--text-muted); display: inline-flex; align-items: center; gap: 6px; }
.cpx-topic i { width: 7px; height: 7px; border-radius: 50%; background: var(--text-muted); opacity: .6; }
.cpx-topic.safety i { background: var(--danger); opacity: 1; }
.cpx-when { font-size: 12.5px; color: var(--text-secondary); }
.cpx-st { justify-self: end; font-size: 11.5px; font-weight: 600; padding: 2px 9px; border-radius: 999px; white-space: nowrap; }
.cpx-st.open { background: var(--warning-soft); color: var(--warning); }
.cpx-st.resolved { background: var(--success-soft); color: var(--success); }
.cpx-empty { padding: 48px 20px; text-align: center; color: var(--text-muted); }
.cpx-empty b { display: block; color: var(--text-secondary); font-size: 14px; margin-bottom: 4px; }
.cpx-det { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 18px; display: flex; flex-direction: column; gap: 14px; position: sticky; top: 16px; }
.cpx-det-h { display: flex; gap: 12px; align-items: center; }
.cpx-det-h .cpx-av { width: 42px; height: 42px; font-size: 13px; }
.cpx-det-h b { display: block; font-size: 15px; color: var(--text-primary); }
.cpx-det-h small { color: var(--text-muted); }
.cpx-det-h .cpx-st { margin-left: auto; }
.cpx-meta { display: grid; grid-template-columns: 92px 1fr; gap: 6px 10px; font-size: 12.5px; margin: 0; }
.cpx-meta dt { color: var(--text-muted); font-weight: 500; }
.cpx-meta dd { margin: 0; color: var(--text-primary); }
.cpx-quote { background: var(--bg-subtle); border: 1px solid var(--border); border-radius: 9px; padding: 12px 14px; font-size: 13.5px; color: var(--text-primary); white-space: pre-line; }
.cpx-tl { border-left: 2px solid var(--border); margin-left: 4px; padding-left: 14px; display: flex; flex-direction: column; gap: 8px; font-size: 12.5px; color: var(--text-secondary); }
.cpx-tl div { position: relative; }
.cpx-tl div::before { content: ''; position: absolute; left: -19px; top: 5px; width: 8px; height: 8px; border-radius: 50%; background: var(--border-md); }
.cpx-tl div.ok::before { background: var(--success); }
.cpx-det label { font-weight: 600; font-size: 12.5px; color: var(--text-secondary); margin-bottom: 5px; display: block; }
.cpx-det textarea { width: 100%; min-height: 92px; border: 1px solid var(--border-md); border-radius: 9px; padding: 10px 12px; font-size: 13.5px; background: var(--surface); color: var(--text-primary); resize: vertical; }
.cpx-det .hint { font-size: 12px; color: var(--text-muted); margin-top: 4px; }
.cpx-acts { display: flex; gap: 8px; justify-content: flex-end; flex-wrap: wrap; }
.cpx-btn { height: 36px; padding: 0 14px; border-radius: 9px; border: 1px solid var(--border-md); background: var(--surface); color: var(--text-secondary); font-weight: 600; font-size: 13px; display: inline-flex; align-items: center; gap: 7px; text-decoration: none; cursor: pointer; }
.cpx-btn.pri { background: var(--brand); border-color: var(--brand); color: #fff; }
@media (max-width: 1200px) { .cpx-main { grid-template-columns: 1fr; } .cpx-det { position: static; } }
@media (max-width: 760px) { .cpx-row { grid-template-columns: 34px minmax(0, 1fr) 70px; } .cpx-row .cpx-msg, .cpx-row .cpx-when { display: none; } .cpx-sum { flex-wrap: wrap; } }
</style>
@endpush

@section('content')
@php
    $keep = array_filter(['status' => $status !== 'open' ? $status : null, 'group' => $group !== 'site' ? $group : null,
                          'category' => $category, 'site' => $siteId, 'q' => $q !== '' ? $q : null]);
    $url  = fn (array $over) => route('complaints.index', array_filter(array_merge($keep, $over), fn ($v) => $v !== null && $v !== ''));
    $ini  = fn (?string $n) => collect(preg_split('/\s+/', trim((string) $n)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('') ?: '?';
    $when = fn ($d) => $d ? ($d->isToday() ? __('Today') . ', ' . $d->format('g:i A') : ($d->isYesterday() ? __('Yesterday') . ', ' . $d->format('g:i A') : $d->format('M j, g:i A'))) : '';
@endphp
<div class="cpx">
    <x-page-header :title="__('Complaints')" />
    <p class="text-muted" style="margin:-8px 0 0;font-size:13.5px">{{ __("Sent by workers from the kiosk's Report tab. Your answer appears on the kiosk the next time they scan.") }}</p>

    @include('modules._flash')

    <div class="cpx-sum">
        <div><span>{{ __('Open') }}</span><b>{{ $stats['open'] }}</b></div>
        <div><span>{{ __('Answered this week') }}</span><b>{{ $stats['week'] }}</b></div>
        <div class="{{ $stats['safety'] ? 'bad' : '' }}"><span>{{ __('Safety reports open') }}</span><b>{{ $stats['safety'] }}</b></div>
        <div><span>{{ __('Average time to answer') }}</span><b>{{ $stats['avgHours'] === null ? '—' : ($stats['avgHours'] < 1 ? round($stats['avgHours'] * 60) . ' min' : $stats['avgHours'] . ' h') }}</b></div>
    </div>

    <nav class="cpx-tabs">
        <a class="{{ $status === 'open' ? 'on' : '' }}" href="{{ $url(['status' => null, 'c' => null]) }}">{{ __('Open') }} <em>{{ $counts['open'] }}</em></a>
        <a class="{{ $status === 'resolved' ? 'on' : '' }}" href="{{ $url(['status' => 'resolved', 'c' => null]) }}">{{ __('Resolved') }} <em>{{ $counts['resolved'] }}</em></a>
        <a class="{{ $status === 'all' ? 'on' : '' }}" href="{{ $url(['status' => 'all', 'c' => null]) }}">{{ __('All') }} <em>{{ $counts['all'] }}</em></a>
    </nav>

    <form class="cpx-bar" method="GET" action="{{ route('complaints.index') }}">
        @if($status !== 'open')<input type="hidden" name="status" value="{{ $status }}">@endif
        <input type="hidden" name="group" value="{{ $group }}">
        <span class="lbl">{{ __('Group by') }}</span>
        <div class="cpx-seg">
            <a class="{{ $group === 'site' ? 'on' : '' }}" href="{{ $url(['group' => null]) }}">{{ __('Site') }}</a>
            <a class="{{ $group === 'employee' ? 'on' : '' }}" href="{{ $url(['group' => 'employee']) }}">{{ __('Employee') }}</a>
        </div>
        <select name="site" onchange="this.form.submit()" aria-label="{{ __('Site') }}">
            <option value="">{{ __('All sites') }}</option>
            @foreach($sites as $s)<option value="{{ $s->id }}" @selected($siteId === $s->id)>{{ $s->name }}</option>@endforeach
        </select>
        <select name="category" onchange="this.form.submit()" aria-label="{{ __('Topic') }}">
            <option value="">{{ __('All topics') }}</option>
            @foreach($categories as $k => $label)<option value="{{ $k }}" @selected($category === $k)>{{ $label }}</option>@endforeach
        </select>
        <input type="search" name="q" value="{{ $q }}" placeholder="{{ __('Search an employee') }}" aria-label="{{ __('Employee') }}">
        <span class="sp"></span>
        @if($keep)<a class="reset" href="{{ route('complaints.index') }}">{{ __('Reset') }}</a>@endif
    </form>

    <div class="cpx-main">
        <div class="cpx-list">
            @forelse($groups as $name => $items)
                <div class="cpx-grp">{{ $name }} <small>· {{ $items->count() }} {{ $items->count() === 1 ? __('report') : __('reports') }}</small></div>
                @foreach($items as $c)
                    <a class="cpx-row {{ $selected && $selected->id === $c->id ? 'sel' : '' }}" href="{{ $url(['c' => $c->id]) }}">
                        <span class="cpx-av">{{ $ini($c->employee->name ?? '') }}</span>
                        <span class="cpx-nm"><b>{{ $c->employee->name ?? __('Unknown') }}</b><small>{{ $group === 'site' ? ($c->employee->position ?? '') : ($c->site->name ?? '') }}</small></span>
                        <span class="cpx-msg"><b>{{ $c->message }}</b><span class="cpx-topic {{ $c->category === 'safety' ? 'safety' : '' }}"><i></i>{{ $c->categoryLabel() }}</span></span>
                        <span class="cpx-when">{{ $when($c->created_at) }}<small>{{ $c->kiosk ? ($c->kiosk->name ?: $c->kiosk->code) : __('Kiosk') }}</small></span>
                        <span class="cpx-st {{ $c->isOpen() ? 'open' : 'resolved' }}">{{ $c->isOpen() ? __('Open') : __('Resolved') }}</span>
                    </a>
                @endforeach
            @empty
                <div class="cpx-empty"><b>{{ $status === 'open' ? __('No open complaints') : __('Nothing here') }}</b>{{ __('Workers send them from the kiosk, in the Report tab.') }}</div>
            @endforelse
        </div>

        @if($selected)
            <aside class="cpx-det">
                <div class="cpx-det-h">
                    <span class="cpx-av">{{ $ini($selected->employee->name ?? '') }}</span>
                    <div><b>{{ $selected->employee->name ?? __('Unknown') }}</b><small>{{ $selected->employee->position ?? '' }}@if($selected->site) · {{ $selected->site->name }}@endif</small></div>
                    <span class="cpx-st {{ $selected->isOpen() ? 'open' : 'resolved' }}">{{ $selected->isOpen() ? __('Open') : __('Resolved') }}</span>
                </div>
                <dl class="cpx-meta">
                    <dt>{{ __('Reference') }}</dt><dd>#{{ $selected->id }}</dd>
                    <dt>{{ __('Topic') }}</dt><dd>{{ $selected->categoryLabel() }}</dd>
                    <dt>{{ __('Sent from') }}</dt><dd>{{ $selected->kiosk ? ($selected->kiosk->name ?: $selected->kiosk->code) : __('Kiosk') }} · {{ $when($selected->created_at) }}</dd>
                </dl>
                <div class="cpx-quote">{{ $selected->message }}</div>
                <div class="cpx-tl">
                    <div><b>{{ __('Received') }}</b> · {{ $when($selected->created_at) }}</div>
                    @if($selected->isOpen())
                        <div>{{ $selected->response ? __('Answer saved, not resolved yet') : __('Not answered yet') }}</div>
                    @else
                        <div class="ok"><b>{{ __('Resolved') }}</b> · {{ $when($selected->resolved_at) }}@if($selected->resolver) · {{ $selected->resolver->name }}@endif</div>
                    @endif
                </div>
                <form method="POST" action="{{ route('complaints.update', $selected) }}">
                    @csrf @method('PATCH')
                    @foreach($keep as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
                    <label for="cpxAns">{{ __('Answer for the worker') }}</label>
                    <textarea id="cpxAns" name="response" maxlength="1000" placeholder="{{ __('What was done, or what they should do next') }}">{{ old('response', $selected->response) }}</textarea>
                    <div class="hint">{{ __('Shown on the kiosk when :name scans in the Report tab.', ['name' => \Illuminate\Support\Str::before($selected->employee->name ?? __('the worker'), ' ')]) }}</div>
                    <div class="cpx-acts" style="margin-top:12px">
                        @if($selected->employee && \Illuminate\Support\Facades\Route::has('payroll-reports.dtr') && auth()->user()?->canAccessModule('payroll-reports'))
                            <a class="cpx-btn" href="{{ route('payroll-reports.index', ['report' => 'dtr', 'q' => $selected->employee->name]) }}">{{ __('Open DTR') }}</a>
                        @endif
                        @if($selected->isOpen())
                            <button class="cpx-btn" type="submit" name="action" value="save">{{ __('Save answer') }}</button>
                            <button class="cpx-btn pri" type="submit" name="action" value="resolve">{{ __('Answer & resolve') }}</button>
                        @else
                            <button class="cpx-btn" type="submit" name="action" value="save">{{ __('Update answer') }}</button>
                            <button class="cpx-btn" type="submit" name="action" value="reopen">{{ __('Reopen') }}</button>
                        @endif
                    </div>
                </form>
            </aside>
        @endif
    </div>
</div>
@endsection
