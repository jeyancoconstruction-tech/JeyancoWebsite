@extends('layouts')
@section('page_title', 'Payslips')

@section('content')
<div class="mod-page">

    @include('modules._head', [
        'title' => __('Payslips'),
        'sub'   => __('Issued from a payroll run the office has approved or finalised, so a slip reads the same whenever it is reprinted.'),
    ])

    @include('modules._flash')

    @if($runs->isEmpty())
        <div class="mod-card"><div class="mod-empty">
            <i class="fas fa-file-invoice"></i>
            <p class="mod-empty-title">{{ __('No payslips yet') }}</p>
            <p class="mod-empty-sub">{{ __('Approve a payroll run in Payroll Processing and its payslips appear here.') }}</p>
        </div></div>
    @else
        <div class="mod-card">
            <form method="GET" class="mod-filters">
                <div class="mod-filter">
                    <label for="prun">{{ __('Payroll Run') }}</label>
                    <select id="prun" class="form-select" name="run" onchange="this.form.submit()">
                        @foreach($runs as $r)
                            <option value="{{ $r->id }}" @selected($run && $run->id === $r->id)>
                                {{ $r->code }} — {{ $r->period_label }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="mod-filter mod-filter-grow">
                    <label for="pq">{{ __('Employee') }}</label>
                    <input id="pq" class="form-control" type="text" name="q" value="{{ request('q') }}" placeholder="{{ __('Search a name') }}">
                </div>
                <div class="mod-filter-actions">
                    <button class="mod-btn primary" type="submit"><i class="fas fa-magnifying-glass"></i> {{ __('Apply') }}</button>
                    @if($run)
                        <a class="mod-btn" href="{{ route('payslips.print-run', $run) }}" target="_blank" rel="noopener">
                            <i class="fas fa-print"></i> {{ __('Print All') }}
                        </a>
                    @endif
                </div>
            </form>

            <div class="mod-table-wrap">
                <table class="mod-table">
                    <thead>
                        <tr>
                            <th>{{ __('Employee') }}</th>
                            <th>{{ __('Site') }}</th>
                            <th class="num">{{ __('Gross') }}</th>
                            <th class="num">{{ __('Deductions') }}</th>
                            <th class="num">{{ __('Net Pay') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($items ?? [] as $i)
                        <tr>
                            <td>@include('modules._person', ['name' => $i->employee_name ?: ($i->employee->name ?? '—'), 'sub' => $i->position ?: ''])</td>
                            <td class="muted">{{ $i->site->name ?? '—' }}</td>
                            <td class="num">₱{{ number_format($i->gross_pay, 2) }}</td>
                            <td class="num">₱{{ number_format($i->total_deductions, 2) }}</td>
                            <td class="num strong" style="color:var(--brand);">₱{{ number_format($i->net_pay, 2) }}</td>
                            <td>
                                <div class="mod-row-actions">
                                    <a class="mod-btn sm" href="{{ route('payslips.show', $i) }}"><i class="fas fa-eye"></i> {{ __('View') }}</a>
                                    <a class="mod-btn sm" href="{{ route('payslips.print', $i) }}" target="_blank" rel="noopener"><i class="fas fa-print"></i> {{ __('Print') }}</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        @include('modules._empty', ['cols' => 6, 'icon' => 'fa-file-invoice',
                            'title' => __('No payslips in this run'), 'sub' => __('The run computed no lines for this period.')])
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if($items && $items->hasPages())<div class="mod-pager">{{ $items->links() }}</div>@endif
        </div>
    @endif
</div>

@include('modules._kit')
@endsection

