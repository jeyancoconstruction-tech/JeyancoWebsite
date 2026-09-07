@extends('layouts')
@section('page_title', 'Payroll Run ' . $run->code)

@section('content')
<div class="mod-page">

    <div class="mod-head">
        <div>
            <h1 class="mod-title">{{ $run->code }}</h1>
            <p class="mod-sub">
                {{ $run->period_label }}
                @if($run->site) &middot; {{ $run->site->name }} @else &middot; {{ __('All sites') }} @endif
                @if($run->title) &middot; {{ $run->title }} @endif
            </p>
        </div>
        <div class="mod-head-actions">
            <a class="mod-btn" href="{{ route('payroll-processing.index') }}">
                <i class="fas fa-arrow-left"></i> {{ __('All Runs') }}
            </a>

            @if($run->isEditable())
                <form method="POST" action="{{ route('payroll-processing.calculate', $run) }}">
                    @csrf
                    <button class="mod-btn" type="submit"><i class="fas fa-rotate"></i> {{ __('Recalculate') }}</button>
                </form>
            @endif

            @if($run->status === 'calculated')
                <form method="POST" action="{{ route('payroll-processing.approve', $run) }}"
                      onsubmit="return confirm('{{ __('Approve this run? Figures can still be reopened afterwards.') }}')">
                    @csrf
                    <button class="mod-btn ok" type="submit"><i class="fas fa-check"></i> {{ __('Approve') }}</button>
                </form>
            @endif

            @if($run->status === 'approved')
                <form method="POST" action="{{ route('payroll-processing.reopen', $run) }}">
                    @csrf
                    <button class="mod-btn" type="submit"><i class="fas fa-lock-open"></i> {{ __('Reopen') }}</button>
                </form>
                <button class="mod-btn primary" type="button" data-bs-toggle="modal" data-bs-target="#finalModal">
                    <i class="fas fa-lock"></i> {{ __('Finalise') }}
                </button>
            @endif

            @if($run->isPayable())
                <a class="mod-btn" href="{{ route('payslips.index', ['run' => $run->id]) }}">
                    <i class="fas fa-file-invoice"></i> {{ __('Payslips') }}
                </a>
            @endif
        </div>
    </div>

    @include('modules._flash')

    @php $tone = ['finalized' => 'ok', 'approved' => 'info', 'calculated' => 'warn'][$run->status] ?? 'muted'; @endphp

    <div class="mod-card">
        <div class="mod-card-head">
            <h2 class="mod-card-title"><i class="fas fa-circle-info"></i> {{ __('Run Summary') }}</h2>
            <span class="mod-badge {{ $tone }}"><span class="dot"></span>{{ $run->status_label }}</span>
        </div>
        <div class="mod-card-body">
            <dl class="mod-dl">
                <div><dt>{{ __('Workers') }}</dt><dd>{{ $run->employee_count }}</dd></div>
                <div><dt>{{ __('Gross Pay') }}</dt><dd>₱{{ number_format($run->total_gross, 2) }}</dd></div>
                <div><dt>{{ __('Total Deductions') }}</dt><dd>₱{{ number_format($run->total_deductions, 2) }}</dd></div>
                <div><dt>{{ __('Net Pay') }}</dt><dd style="color:var(--brand);">₱{{ number_format($run->total_net, 2) }}</dd></div>
                <div><dt>{{ __('Created by') }}</dt><dd>{{ $run->creator->name ?? '—' }}</dd></div>
                <div><dt>{{ __('Calculated') }}</dt><dd>{{ $run->calculated_at?->format('M d, Y g:i A') ?? '—' }}</dd></div>
                <div><dt>{{ __('Approved by') }}</dt><dd>{{ $run->approver->name ?? '—' }}</dd></div>
                <div><dt>{{ __('Finalised') }}</dt><dd>{{ $run->finalized_at?->format('M d, Y g:i A') ?? '—' }}</dd></div>
            </dl>
            @if($run->notes)
                <div class="mod-note" style="margin:14px 0 0;">
                    <i class="fas fa-note-sticky"></i><div>{{ $run->notes }}</div>
                </div>
            @endif
        </div>
    </div>

    <div class="mod-card">
        <div class="mod-card-head">
            <h2 class="mod-card-title"><i class="fas fa-list"></i> {{ __('Payroll Register') }}</h2>
            <span class="mod-sub" style="margin:0;">{{ $run->items->count() }} {{ __('line(s)') }}</span>
        </div>
        <div class="mod-table-wrap">
            <table class="mod-table">
                <thead>
                    <tr>
                        <th>{{ __('Employee') }}</th>
                        <th class="num">{{ __('Days') }}</th>
                        <th class="num">{{ __('Hours') }}</th>
                        <th class="num">{{ __('Basic') }}</th>
                        <th class="num">{{ __('OT') }}</th>
                        <th class="num">{{ __('Leave') }}</th>
                        <th class="num">{{ __('Gross') }}</th>
                        <th class="num">{{ __('Loans') }}</th>
                        <th class="num">{{ __('Deductions') }}</th>
                        <th class="num">{{ __('Net Pay') }}</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($run->items as $i)
                    <tr>
                        <td>@include('modules._person', ['name' => $i->employee_name ?: ($i->employee->name ?? '—'), 'sub' => $i->position ?: ''])</td>
                        <td class="num">{{ rtrim(rtrim(number_format($i->days_worked, 2), '0'), '.') }}</td>
                        <td class="num">{{ rtrim(rtrim(number_format($i->regular_hours, 2), '0'), '.') }}</td>
                        <td class="num">₱{{ number_format($i->basic_pay, 2) }}</td>
                        <td class="num">₱{{ number_format($i->overtime_pay, 2) }}</td>
                        <td class="num">₱{{ number_format($i->leave_pay, 2) }}</td>
                        <td class="num strong">₱{{ number_format($i->gross_pay, 2) }}</td>
                        <td class="num">₱{{ number_format($i->loan_deduction + $i->advance_deduction, 2) }}</td>
                        <td class="num">₱{{ number_format($i->total_deductions, 2) }}</td>
                        <td class="num strong" style="color:var(--brand);">₱{{ number_format($i->net_pay, 2) }}</td>
                    </tr>
                @empty
                    @include('modules._empty', ['cols' => 10, 'icon' => 'fa-calculator',
                        'title' => __('Nothing computed for this period'),
                        'sub' => __('No attendance, approved leave or approved overtime falls inside it.')])
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@if($run->status === 'approved')
<div class="modal fade" id="finalModal" tabindex="-1" aria-hidden="true" aria-labelledby="finalTitle">
  <div class="modal-dialog modal-dialog-centered emp-dialog" style="max-width:520px;">
    <div class="modal-content emp-modal">
      <form method="POST" action="{{ route('payroll-processing.finalize', $run) }}">
        @csrf
        <div class="emp-head">
            <span class="emp-head-icon"><i class="fas fa-lock"></i></span>
            <div class="emp-head-text">
                <h6 class="emp-head-title" id="finalTitle">{{ __('Finalise') }} {{ $run->code }}</h6>
                <p class="emp-head-sub">{{ __('This cannot be undone.') }}</p>
            </div>
            <button type="button" class="emp-head-x" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body emp-body">
            <div class="mod-alert err" style="margin-bottom:14px;">
                <i class="fas fa-triangle-exclamation"></i>
                <div>
                    <strong>{{ __('Finalising does two things that cannot be reversed:') }}</strong>
                    <ul>
                        <li>{{ __('the figures are locked and can never be recalculated;') }}</li>
                        <li>{{ __('loan and advance instalments are collected, and balances fall.') }}</li>
                    </ul>
                </div>
            </div>
            <dl class="mod-dl">
                <div><dt>{{ __('Workers') }}</dt><dd>{{ $run->employee_count }}</dd></div>
                <div><dt>{{ __('Net Pay') }}</dt><dd>₱{{ number_format($run->total_net, 2) }}</dd></div>
            </dl>
            <div class="emp-field" style="margin-top:14px;">
                <label class="ep-label" style="display:flex;align-items:center;gap:8px;text-transform:none;letter-spacing:0;font-size:13px;">
                    <input type="checkbox" name="confirm" value="1" required>
                    {{ __('I have reviewed this run and want to finalise it.') }}
                </label>
            </div>
        </div>
        <div class="emp-foot">
            <button type="button" class="emp-btn-cancel" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
            <button type="submit" class="emp-btn-save"><i class="fas fa-lock"></i> <span>{{ __('Finalise Run') }}</span></button>
        </div>
      </form>
    </div>
  </div>
</div>
@endif

@include('modules._kit')
@include('employees._profile_styles')
@include('employees._modal_styles')
@endsection

