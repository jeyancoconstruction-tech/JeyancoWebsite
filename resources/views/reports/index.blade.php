@extends('layouts')
@section('page_title', 'Payroll Reports')

@section('content')
<div class="mod-page">

    @include('modules._head', [
        'title' => __('Payroll Reports'),
        'sub'   => __('Built from the figures payroll runs froze, so a report and the payslip it summarises always agree.'),
        'actions' => '<button type="button" class="mod-btn no-print" onclick="window.print()"><i class="fas fa-print"></i> ' . __('Print') . '</button>',
    ])

    @include('modules._flash')

    <div class="mod-tabs no-print">
        @foreach($reports as $key => $label)
            <a class="mod-tab {{ $report === $key ? 'active' : '' }}"
               href="{{ route('payroll-reports.index', array_merge(request()->except('page'), ['report' => $key])) }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    <div class="mod-card">
        <form method="GET" class="mod-filters no-print">
            <input type="hidden" name="report" value="{{ $report }}">
            <div class="mod-filter">
                <label for="rrun">{{ __('Payroll Run') }}</label>
                <select id="rrun" class="form-select" name="run">
                    <option value="">{{ __('By date range') }}</option>
                    @foreach($allRuns as $r)
                        <option value="{{ $r->id }}" @selected($runId == $r->id)>{{ $r->code }}</option>
                    @endforeach
                </select>
            </div>
            <div class="mod-filter">
                <label for="rfrom">{{ __('From') }}</label>
                <input id="rfrom" class="form-control" type="date" name="from" value="{{ $from }}">
            </div>
            <div class="mod-filter">
                <label for="rto">{{ __('To') }}</label>
                <input id="rto" class="form-control" type="date" name="to" value="{{ $to }}">
            </div>
            <div class="mod-filter">
                <label for="rsite">{{ __('Site') }}</label>
                <select id="rsite" class="form-select" name="site_id">
                    <option value="">{{ __('All sites') }}</option>
                    @foreach($sites as $s)
                        <option value="{{ $s->id }}" @selected($siteId == $s->id)>{{ $s->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="mod-filter mod-filter-grow">
                <label for="rq">{{ __('Employee') }}</label>
                <input id="rq" class="form-control" type="text" name="q" value="{{ request('q') }}" placeholder="{{ __('Search a name') }}">
            </div>
            <div class="mod-filter-actions">
                <button class="mod-btn primary" type="submit"><i class="fas fa-magnifying-glass"></i> {{ __('Apply') }}</button>
                <a class="mod-btn" href="{{ route('payroll-reports.index', ['report' => $report]) }}">{{ __('Reset') }}</a>
            </div>
        </form>

        <div class="mod-card-body" style="border-bottom:1px solid var(--border);">
            <dl class="mod-dl">
                <div><dt>{{ __('Report') }}</dt><dd>{{ $reports[$report] }}</dd></div>
                <div><dt>{{ __('Runs Covered') }}</dt><dd>{{ $runs->count() }}</dd></div>
                <div><dt>{{ __('Workers') }}</dt><dd>{{ $totals['headcount'] }}</dd></div>
                <div><dt>{{ __('Gross') }}</dt><dd>₱{{ number_format($totals['gross'], 2) }}</dd></div>
                <div><dt>{{ __('Deductions') }}</dt><dd>₱{{ number_format($totals['deductions'], 2) }}</dd></div>
                <div><dt>{{ __('Net') }}</dt><dd style="color:var(--brand);">₱{{ number_format($totals['net'], 2) }}</dd></div>
            </dl>
        </div>

        <div class="mod-table-wrap">
            <table class="mod-table">
                <thead>
                    <tr>
                        <th>{{ $report === 'site' ? __('Site') : ($report === 'summary' ? __('Run') : __('Name')) }}</th>
                        <th>{{ __('Detail') }}</th>
                        <th class="num">{{ __('Lines') }}</th>
                        <th class="num">{{ $report === 'overtime' ? __('Hours') : __('Days') }}</th>
                        <th class="num">{{ $report === 'loans' ? __('Principal') : __('Gross') }}</th>
                        <th class="num">{{ $report === 'loans' ? __('Paid') : __('Deductions') }}</th>
                        <th class="num">{{ $report === 'loans' ? __('Balance') : __('Net') }}</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td class="strong">{{ $row['label'] }}</td>
                        <td class="muted">{{ $row['sub'] }}</td>
                        <td class="num">{{ $row['count'] }}</td>
                        <td class="num">{{ $row['days'] ? rtrim(rtrim(number_format($row['days'], 2), '0'), '.') : '—' }}</td>
                        <td class="num">{{ $row['gross'] ? '₱' . number_format($row['gross'], 2) : '—' }}</td>
                        <td class="num">{{ $row['deductions'] ? '₱' . number_format($row['deductions'], 2) : '—' }}</td>
                        <td class="num strong">₱{{ number_format($row['net'], 2) }}</td>
                    </tr>
                @empty
                    @include('modules._empty', ['cols' => 7, 'icon' => 'fa-file-lines',
                        'title' => __('Nothing to report'),
                        'sub' => __('No approved or finalised payroll run falls in this range.')])
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@include('modules._kit')
<style>
@media print {
    .sidebar, .topbar, .chatbot-fab, .chatbot-window, .no-print { display: none !important; }
    .main-content { margin-left: 0 !important; }
    body { background: #fff !important; }
    .mod-card { border: none; }
}
</style>
@endsection

