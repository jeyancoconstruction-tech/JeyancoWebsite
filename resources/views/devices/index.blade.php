@extends('layouts')
@section('page_title', 'Device Monitoring')

@section('content')
<div class="mod-page">

    @include('modules._head', [
        'title' => __('Device Monitoring'),
        'sub'   => __('The attendance kiosks and whether they are still talking to us. Read-only: nothing here changes how a kiosk works or what it records.'),
    ])

    @include('modules._flash')

    <div class="mod-stats">
        <div class="mod-stat">
            <p class="mod-stat-label">{{ __('Devices') }}</p>
            <p class="mod-stat-value">{{ $summary['total'] }}</p>
        </div>
        <div class="mod-stat">
            <p class="mod-stat-label">{{ __('Online') }}</p>
            <p class="mod-stat-value is-ok">{{ $summary['online'] }}</p>
            <p class="mod-stat-sub">{{ __('heard from in the last') }} {{ round($offlineAfter / 60) }} {{ __('min') }}</p>
        </div>
        <div class="mod-stat">
            <p class="mod-stat-label">{{ __('Offline') }}</p>
            <p class="mod-stat-value {{ $summary['offline'] ? 'is-danger' : '' }}">{{ $summary['offline'] }}</p>
        </div>
    </div>

    <div class="mod-card">
        <div class="mod-card-head">
            <h2 class="mod-card-title"><i class="fas fa-desktop"></i> {{ __('Kiosks') }}</h2>
            <span class="mod-sub" style="margin:0;">{{ __('Refresh the page for the latest heartbeat.') }}</span>
        </div>
        <div class="mod-table-wrap">
            <table class="mod-table">
                <thead>
                    <tr>
                        <th>{{ __('Device') }}</th>
                        <th>{{ __('Assigned Site') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Last Connection') }}</th>
                        <th>{{ __('GPS') }}</th>
                        <th>{{ __('Last Attendance') }}</th>
                        <th class="num">{{ __('Today') }}</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($devices as $d)
                    <tr>
                        <td>
                            <div class="mod-person">
                                <div class="mod-avatar" style="background:{{ $d['online'] ? 'var(--success-soft)' : 'var(--bg-subtle)' }};color:{{ $d['online'] ? 'var(--success)' : 'var(--text-muted)' }};">
                                    <i class="fas fa-desktop" style="font-size:11px;"></i>
                                </div>
                                <div style="min-width:0;">
                                    <div class="mod-person-name">{{ $d['kiosk']->name }}</div>
                                    <div class="mod-person-sub">{{ $d['kiosk']->code }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="muted">{{ $d['kiosk']->site->name ?? __('Unassigned') }}</td>
                        <td>
                            <span class="mod-badge {{ $d['online'] ? 'ok' : 'danger' }}">
                                <span class="dot"></span>{{ $d['online'] ? __('Online') : __('Offline') }}
                            </span>
                        </td>
                        <td class="muted">
                            @if($d['last_seen'])
                                {{ \Carbon\Carbon::parse($d['last_seen'])->diffForHumans() }}
                                <div class="mod-person-sub">{{ \Carbon\Carbon::parse($d['last_seen'])->format('M d, g:i A') }}</div>
                            @else
                                {{ __('Never') }}
                            @endif
                        </td>
                        <td>
                            @if($d['has_fix'])
                                <span class="mod-badge info"><span class="dot"></span>{{ __('Fix') }}</span>
                                <div class="mod-person-sub" style="font-variant-numeric:tabular-nums;">
                                    {{ number_format((float) $d['lat'], 5) }}, {{ number_format((float) $d['lng'], 5) }}
                                </div>
                            @elseif($d['last_seen'])
                                <span class="mod-badge warn"><span class="dot"></span>{{ __('No signal') }}</span>
                            @else
                                <span class="mod-badge muted">—</span>
                            @endif
                        </td>
                        <td class="muted">
                            @if($d['last_attendance'])
                                {{ $d['last_attendance']->updated_at?->diffForHumans() ?? '—' }}
                                <div class="mod-person-sub">{{ \Carbon\Carbon::parse($d['last_attendance']->date)->format('M d, Y') }}</div>
                            @else
                                {{ __('None recorded') }}
                            @endif
                        </td>
                        <td class="num strong">{{ $d['today_count'] }}</td>
                    </tr>
                @empty
                    @include('modules._empty', ['cols' => 7, 'icon' => 'fa-desktop',
                        'title' => __('No kiosks registered'),
                        'sub' => __('A kiosk appears here once it has been added to the system.')])
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@include('modules._kit')
@endsection
