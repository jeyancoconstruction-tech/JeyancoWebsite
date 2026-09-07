@extends('layouts')
@section('page_title', 'Site Attendance')

@section('content')
<div class="mod-page">

    @include('modules._head', [
        'title' => __('Site Attendance'),
        'sub'   => __('The kiosk\'s own attendance records, read per construction site. This page has no way to create, edit or delete a clock — the kiosk remains the only thing that records one.'),
    ])

    @include('modules._flash')

    <div class="mod-stats">
        @foreach($tally as $t)
            <div class="mod-stat">
                <p class="mod-stat-label">{{ $t['site']->name }}</p>
                <p class="mod-stat-value">{{ $t['present'] }}<span style="font-size:.7em;color:var(--text-muted);font-weight:600;"> / {{ $t['assigned'] }}</span></p>
                <p class="mod-stat-sub">
                    {{ $t['records'] }} {{ __('record(s)') }}
                    @if($t['open']) &middot; <span style="color:var(--warning);">{{ $t['open'] }} {{ __('still in') }}</span>@endif
                    @if($t['overtime'] > 0) &middot; {{ rtrim(rtrim(number_format($t['overtime'], 2), '0'), '.') }}h OT @endif
                </p>
            </div>
        @endforeach
        @if(empty($tally))
            <div class="mod-stat">
                <p class="mod-stat-label">{{ __('Sites') }}</p>
                <p class="mod-stat-value">0</p>
            </div>
        @endif
    </div>

    <div class="mod-card">
        <form method="GET" class="mod-filters">
            <div class="mod-filter mod-filter-grow">
                <label for="sq">{{ __('Employee') }}</label>
                <input id="sq" class="form-control" type="text" name="q" value="{{ request('q') }}" placeholder="{{ __('Search a name') }}">
            </div>
            <div class="mod-filter">
                <label for="ssite">{{ __('Site') }}</label>
                <select id="ssite" class="form-select" name="site_id">
                    <option value="">{{ __('All sites') }}</option>
                    @foreach($sites as $s)
                        <option value="{{ $s->id }}" @selected(request('site_id') == $s->id)>{{ $s->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="mod-filter">
                <label for="sdate">{{ __('From') }}</label>
                <input id="sdate" class="form-control" type="date" name="date" value="{{ $date }}">
            </div>
            <div class="mod-filter">
                <label for="sto">{{ __('To') }}</label>
                <input id="sto" class="form-control" type="date" name="to" value="{{ $to }}">
            </div>
            <div class="mod-filter-actions">
                <button class="mod-btn primary" type="submit"><i class="fas fa-magnifying-glass"></i> {{ __('Apply') }}</button>
                <a class="mod-btn" href="{{ route('site-attendance.index') }}">{{ __('Today') }}</a>
            </div>
        </form>

        <div class="mod-table-wrap">
            <table class="mod-table">
                <thead>
                    <tr>
                        <th>{{ __('Employee') }}</th>
                        <th>{{ __('Site') }}</th>
                        <th>{{ __('Date') }}</th>
                        <th>{{ __('Session') }}</th>
                        <th>{{ __('Time In') }}</th>
                        <th>{{ __('Time Out') }}</th>
                        <th>{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($records as $r)
                    <tr>
                        <td>@include('modules._person', ['name' => $r->employee->name ?? '—', 'sub' => $r->employee->position ?? ''])</td>
                        <td class="strong">{{ $r->site->name ?? '—' }}</td>
                        <td class="muted">{{ \Carbon\Carbon::parse($r->date)->format('M d, Y') }}</td>
                        <td class="muted">{{ $r->session ? ucfirst($r->session) : '—' }}</td>
                        <td>{{ $r->time_in ? \Carbon\Carbon::parse($r->time_in)->format('g:i A') : '—' }}</td>
                        <td>{{ $r->time_out ? \Carbon\Carbon::parse($r->time_out)->format('g:i A') : '—' }}</td>
                        <td>
                            @php
                                // Attendance::getStatusAttribute() already derives this;
                                // the badge only chooses a colour for what it says.
                                $st = $r->status;
                                $tone = ['present' => 'ok', 'active' => 'info', 'invalid' => 'danger'][$st] ?? 'muted';
                            @endphp
                            <span class="mod-badge {{ $tone }}"><span class="dot"></span>{{ ucfirst($st) }}</span>
                        </td>
                    </tr>
                @empty
                    @include('modules._empty', ['cols' => 7, 'icon' => 'fa-hard-hat',
                        'title' => __('No attendance for this range'), 'sub' => __('Records appear as the kiosk sends them.')])
                @endforelse
                </tbody>
            </table>
        </div>
        @if($records->hasPages())<div class="mod-pager">{{ $records->links() }}</div>@endif
    </div>
</div>

@include('modules._kit')
@endsection
