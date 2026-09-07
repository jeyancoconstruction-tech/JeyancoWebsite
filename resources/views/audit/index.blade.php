@extends('layouts')
@section('page_title', 'Audit Logs')

@section('content')
<div class="mod-page">

    @include('modules._head', [
        'title' => __('Audit Logs'),
        'sub'   => __('Who did what, and when. Append-only — nothing in the system edits or deletes an entry, and this screen is read-only for everyone.'),
    ])

    @include('modules._flash')

    <div class="mod-card">
        <form method="GET" class="mod-filters">
            <div class="mod-filter mod-filter-grow">
                <label for="gq">{{ __('Description') }}</label>
                <input id="gq" class="form-control" type="text" name="q" value="{{ request('q') }}" placeholder="{{ __('Search the log') }}">
            </div>
            <div class="mod-filter">
                <label for="gmod">{{ __('Module') }}</label>
                <select id="gmod" class="form-select" name="module">
                    <option value="">{{ __('All') }}</option>
                    @foreach($modules as $m)
                        <option value="{{ $m }}" @selected(request('module') === $m)>{{ $m }}</option>
                    @endforeach
                </select>
            </div>
            <div class="mod-filter">
                <label for="gact">{{ __('Action') }}</label>
                <select id="gact" class="form-select" name="action">
                    <option value="">{{ __('All') }}</option>
                    @foreach($actions as $a)
                        <option value="{{ $a }}" @selected(request('action') === $a)>{{ ucfirst($a) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="mod-filter">
                <label for="guser">{{ __('User') }}</label>
                <select id="guser" class="form-select" name="user_id">
                    <option value="">{{ __('Everyone') }}</option>
                    @foreach($users as $u)
                        <option value="{{ $u->id }}" @selected(request('user_id') == $u->id)>{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="mod-filter">
                <label for="gfrom">{{ __('From') }}</label>
                <input id="gfrom" class="form-control" type="date" name="from" value="{{ request('from') }}">
            </div>
            <div class="mod-filter">
                <label for="gto">{{ __('To') }}</label>
                <input id="gto" class="form-control" type="date" name="to" value="{{ request('to') }}">
            </div>
            <div class="mod-filter-actions">
                <button class="mod-btn primary" type="submit"><i class="fas fa-magnifying-glass"></i> {{ __('Apply') }}</button>
                <a class="mod-btn" href="{{ route('audit-logs.index') }}">{{ __('Reset') }}</a>
            </div>
        </form>

        <div class="mod-table-wrap">
            <table class="mod-table">
                <thead>
                    <tr>
                        <th>{{ __('When') }}</th>
                        <th>{{ __('User') }}</th>
                        <th>{{ __('Module') }}</th>
                        <th>{{ __('Action') }}</th>
                        <th>{{ __('Description') }}</th>
                        <th>{{ __('IP') }}</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($logs as $log)
                    <tr>
                        <td class="muted" style="white-space:nowrap;">
                            {{ $log->created_at->format('M d, Y') }}
                            <div class="mod-person-sub">{{ $log->created_at->format('g:i A') }}</div>
                        </td>
                        <td>@include('modules._person', ['name' => $log->user_name ?: ($log->user->name ?? 'System'), 'sub' => ''])</td>
                        <td class="muted">{{ $log->module }}</td>
                        <td><span class="mod-badge {{ $log->tone === 'ok' ? 'ok' : ($log->tone === 'danger' ? 'danger' : ($log->tone === 'warn' ? 'warn' : 'muted')) }}">{{ ucfirst($log->action) }}</span></td>
                        <td>{{ $log->description }}</td>
                        <td class="muted" style="font-variant-numeric:tabular-nums;">{{ $log->ip_address ?: '—' }}</td>
                    </tr>
                @empty
                    @include('modules._empty', ['cols' => 6, 'icon' => 'fa-scroll',
                        'title' => __('Nothing logged yet'), 'sub' => __('Approvals, payroll runs and permission changes appear here as they happen.')])
                @endforelse
                </tbody>
            </table>
        </div>
        @if($logs->hasPages())<div class="mod-pager">{{ $logs->links() }}</div>@endif
    </div>
</div>

@include('modules._kit')
@endsection

