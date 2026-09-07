@extends('layouts')
@section('page_title', 'Payroll Processing')

@section('content')
<div class="mod-page">

    @include('modules._head', [
        'title' => __('Payroll Processing'),
        'sub'   => __('Create a run for a period, review the figures it produces, then approve and finalise it. Nothing is finalised without a confirmation.'),
        'actions' => '<button type="button" class="mod-btn primary" data-bs-toggle="modal" data-bs-target="#runModal"><i class="fas fa-plus"></i> ' . __('New Payroll Run') . '</button>',
    ])

    @include('modules._flash')

    <div class="mod-note">
        <i class="fas fa-circle-info"></i>
        <div>{{ __('Figures come from the same engine as Payroll Records, on the run\'s own period. A run then freezes them, so a payslip issued today still reads the same after a rate is edited tomorrow.') }}</div>
    </div>

    <div class="mod-stats">
        <div class="mod-stat">
            <p class="mod-stat-label">{{ __('Open') }}</p>
            <p class="mod-stat-value is-warn">{{ $counts['draft'] }}</p>
            <p class="mod-stat-sub">{{ __('draft or calculated') }}</p>
        </div>
        <div class="mod-stat">
            <p class="mod-stat-label">{{ __('Approved') }}</p>
            <p class="mod-stat-value">{{ $counts['approved'] }}</p>
            <p class="mod-stat-sub">{{ __('awaiting finalisation') }}</p>
        </div>
        <div class="mod-stat">
            <p class="mod-stat-label">{{ __('Finalised') }}</p>
            <p class="mod-stat-value is-ok">{{ $counts['finalized'] }}</p>
            <p class="mod-stat-sub">{{ __('payslips issued') }}</p>
        </div>
    </div>

    <div class="mod-card">
        <form method="GET" class="mod-filters">
            <div class="mod-filter">
                <label for="pstatus">{{ __('Status') }}</label>
                <select id="pstatus" class="form-select" name="status">
                    <option value="">{{ __('All') }}</option>
                    @foreach(\App\Models\PayrollRun::STATUSES as $k => $v)
                        <option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>
                    @endforeach
                </select>
            </div>
            <div class="mod-filter-actions">
                <button class="mod-btn primary" type="submit"><i class="fas fa-magnifying-glass"></i> {{ __('Apply') }}</button>
                <a class="mod-btn" href="{{ route('payroll-processing.index') }}">{{ __('Reset') }}</a>
            </div>
        </form>

        <div class="mod-table-wrap">
            <table class="mod-table">
                <thead>
                    <tr>
                        <th>{{ __('Run') }}</th>
                        <th>{{ __('Period') }}</th>
                        <th>{{ __('Site') }}</th>
                        <th class="num">{{ __('Workers') }}</th>
                        <th class="num">{{ __('Gross') }}</th>
                        <th class="num">{{ __('Deductions') }}</th>
                        <th class="num">{{ __('Net') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @forelse($runs as $run)
                    <tr>
                        <td class="strong">
                            {{ $run->code }}
                            @if($run->title)<div class="mod-person-sub">{{ $run->title }}</div>@endif
                        </td>
                        <td class="muted">{{ $run->period_label }}</td>
                        <td class="muted">{{ $run->site->name ?? __('All sites') }}</td>
                        <td class="num">{{ $run->employee_count }}</td>
                        <td class="num">₱{{ number_format($run->total_gross, 2) }}</td>
                        <td class="num">₱{{ number_format($run->total_deductions, 2) }}</td>
                        <td class="num strong">₱{{ number_format($run->total_net, 2) }}</td>
                        <td>
                            @php $tone = ['finalized' => 'ok', 'approved' => 'info', 'calculated' => 'warn'][$run->status] ?? 'muted'; @endphp
                            <span class="mod-badge {{ $tone }}"><span class="dot"></span>{{ $run->status_label }}</span>
                        </td>
                        <td>
                            <div class="mod-row-actions">
                                <a class="mod-btn sm" href="{{ route('payroll-processing.show', $run) }}">
                                    <i class="fas fa-eye"></i> {{ __('Review') }}
                                </a>
                            </div>
                        </td>
                    </tr>
                @empty
                    @include('modules._empty', ['cols' => 9, 'icon' => 'fa-calculator',
                        'title' => __('No payroll runs yet'), 'sub' => __('Create one for a period to compute and freeze its figures.')])
                @endforelse
                </tbody>
            </table>
        </div>
        @if($runs->hasPages())<div class="mod-pager">{{ $runs->links() }}</div>@endif
    </div>
</div>

<div class="modal fade" id="runModal" tabindex="-1" aria-hidden="true" aria-labelledby="runModalTitle">
  <div class="modal-dialog modal-dialog-centered emp-dialog">
    <div class="modal-content emp-modal">
      <form method="POST" action="{{ route('payroll-processing.store') }}">
        @csrf
        <div class="emp-head">
            <span class="emp-head-icon"><i class="fas fa-calculator"></i></span>
            <div class="emp-head-text">
                <h6 class="emp-head-title" id="runModalTitle">{{ __('New Payroll Run') }}</h6>
                <p class="emp-head-sub">{{ __('Figures are computed straight away; nothing is paid until you finalise.') }}</p>
            </div>
            <button type="button" class="emp-head-x" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body emp-body">
            <div class="mod-form-grid">
                <div class="emp-field">
                    <label class="ep-label" for="pr_from">{{ __('Period Start') }} <span class="ep-req">*</span></label>
                    <input class="form-control" id="pr_from" type="date" name="period_start"
                           value="{{ now()->startOfMonth()->toDateString() }}" required>
                </div>
                <div class="emp-field">
                    <label class="ep-label" for="pr_to">{{ __('Period End') }} <span class="ep-req">*</span></label>
                    <input class="form-control" id="pr_to" type="date" name="period_end"
                           value="{{ now()->toDateString() }}" required>
                </div>
                <div class="emp-field">
                    <label class="ep-label" for="pr_site">{{ __('Site') }}</label>
                    <select class="form-select" id="pr_site" name="site_id">
                        <option value="">{{ __('All sites') }}</option>
                        @foreach($sites as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
                    </select>
                </div>
                <div class="emp-field">
                    <label class="ep-label" for="pr_title">{{ __('Label') }}</label>
                    <input class="form-control" id="pr_title" type="text" name="title" maxlength="120"
                           placeholder="{{ __('e.g. September 1st half') }}">
                </div>
                <div class="emp-field full">
                    <label class="ep-label" for="pr_notes">{{ __('Notes') }}</label>
                    <textarea class="form-control" id="pr_notes" name="notes" rows="2" style="height:auto;padding:9px 13px;"></textarea>
                </div>
            </div>
        </div>
        <div class="emp-foot">
            <p class="emp-foot-note"><span class="ep-req">*</span> {{ __('Required') }}</p>
            <button type="button" class="emp-btn-cancel" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
            <button type="submit" class="emp-btn-save"><i class="fas fa-check"></i> <span>{{ __('Create & Calculate') }}</span></button>
        </div>
      </form>
    </div>
  </div>
</div>

@include('modules._kit')
@include('employees._profile_styles')
@include('employees._modal_styles')
@endsection

