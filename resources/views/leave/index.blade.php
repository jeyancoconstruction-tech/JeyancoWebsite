@extends('layouts')
@section('page_title', 'Leave & Advances')

@section('content')
@php
    // The subtitle and the one button follow the open tab.
    $head = $tab === 'advances'
        ? [
            'sub'    => __('Cash handed to a worker ahead of pay and collected back over several payrolls. Payroll Processing takes the instalment due; the balance moves only when a run is finalised.'),
            'modal'  => 'advanceModal',
            'button' => __('New Cash Advance'),
        ]
        : [
            'sub'    => __('Filed leave and the decisions on it. Approved leave is picked up by Payroll Processing and never writes an attendance record. Overtime is not filed — payroll counts it from attendance.'),
            'modal'  => 'leaveModal',
            'button' => __('File Leave'),
        ];
@endphp
<div class="mod-page">

    @include('modules._head', [
        'title'   => __('Leave & Advances'),
        'sub'     => $head['sub'],
        'actions' => '<button type="button" class="mod-btn primary" data-bs-toggle="modal" data-bs-target="#'
                     . $head['modal'] . '"><i class="fas fa-plus"></i> ' . $head['button'] . '</button>',
    ])

    @include('modules._flash')

    <div class="mod-tabs">
        <a class="mod-tab {{ $tab === 'leave' ? 'active' : '' }}" href="{{ route('leave.index', ['tab' => 'leave']) }}">
            <i class="fas fa-calendar-day"></i> {{ __('Leave') }}
            @if($counts['leave_pending'])<span class="mod-tab-count">{{ $counts['leave_pending'] }}</span>@endif
        </a>
        @if($canAdvances)
            <a class="mod-tab {{ $tab === 'advances' ? 'active' : '' }}" href="{{ route('leave.index', ['tab' => 'advances']) }}">
                <i class="fas fa-hand-holding-dollar"></i> {{ __('Cash Advances') }}
            </a>
        @endif
    </div>

    @if($tab === 'leave')
        <div class="mod-card">
            <form method="GET" class="mod-filters">
                <input type="hidden" name="tab" value="leave">
                <div class="mod-filter mod-filter-grow">
                    <label for="lq">{{ __('Employee') }}</label>
                    <input id="lq" class="form-control" type="text" name="q" value="{{ request('q') }}" placeholder="{{ __('Search a name') }}">
                </div>
                <div class="mod-filter">
                    <label for="lstatus">{{ __('Status') }}</label>
                    <select id="lstatus" class="form-select" name="status">
                        <option value="">{{ __('All') }}</option>
                        @foreach(\App\Models\LeaveRequest::STATUSES as $k => $v)
                            <option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mod-filter">
                    <label for="ltype">{{ __('Type') }}</label>
                    <select id="ltype" class="form-select" name="type">
                        <option value="">{{ __('All') }}</option>
                        @foreach(\App\Models\LeaveRequest::TYPES as $k => $v)
                            <option value="{{ $k }}" @selected(request('type') === $k)>{{ $v }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mod-filter">
                    <label for="lfrom">{{ __('From') }}</label>
                    <input id="lfrom" class="form-control" type="date" name="from" value="{{ request('from') }}">
                </div>
                <div class="mod-filter">
                    <label for="lto">{{ __('To') }}</label>
                    <input id="lto" class="form-control" type="date" name="to" value="{{ request('to') }}">
                </div>
                <div class="mod-filter-actions">
                    <button class="mod-btn primary" type="submit"><i class="fas fa-magnifying-glass"></i> {{ __('Apply') }}</button>
                    <a class="mod-btn" href="{{ route('leave.index', ['tab' => 'leave']) }}">{{ __('Reset') }}</a>
                </div>
            </form>

            <div class="mod-table-wrap">
                <table class="mod-table">
                    <thead>
                        <tr>
                            <th>{{ __('Employee') }}</th>
                            <th>{{ __('Type') }}</th>
                            <th>{{ __('Period') }}</th>
                            <th class="num">{{ __('Days') }}</th>
                            <th>{{ __('Pay') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Approved by') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($leave as $row)
                        <tr>
                            <td>@include('modules._person', ['name' => $row->employee->name ?? '—', 'sub' => $row->employee->position ?? ''])</td>
                            <td class="muted">{{ $row->type_label }}</td>
                            <td class="muted">{{ $row->starts_on->format('M d') }} – {{ $row->ends_on->format('M d, Y') }}</td>
                            <td class="num strong">{{ rtrim(rtrim(number_format($row->days, 2), '0'), '.') }}</td>
                            <td>
                                <span class="mod-badge {{ $row->is_paid ? 'info' : 'muted' }}">
                                    {{ $row->is_paid ? __('Paid') : __('Unpaid') }}
                                </span>
                            </td>
                            <td>
                                @php $tone = ['approved' => 'ok', 'rejected' => 'danger', 'cancelled' => 'muted'][$row->status] ?? 'warn'; @endphp
                                <span class="mod-badge {{ $tone }}"><span class="dot"></span>{{ $row->status_label }}</span>
                            </td>
                            <td class="muted">
                                {{ $row->approver->name ?? '—' }}
                                @if($row->approved_at)<div class="mod-person-sub">{{ $row->approved_at->format('M d, Y') }}</div>@endif
                            </td>
                            <td>
                                @if($row->status === 'pending')
                                    <div class="mod-row-actions">
                                        <form method="POST" action="{{ route('leave.decide', ['id' => $row->id]) }}">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="decision" value="approved">
                                            <button class="mod-btn sm ok" type="submit"><i class="fas fa-check"></i> {{ __('Approve') }}</button>
                                        </form>
                                        <form method="POST" action="{{ route('leave.decide', ['id' => $row->id]) }}">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="decision" value="rejected">
                                            <button class="mod-btn sm danger" type="submit"><i class="fas fa-xmark"></i> {{ __('Reject') }}</button>
                                        </form>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        @include('modules._empty', ['cols' => 8, 'icon' => 'fa-calendar-day',
                            'title' => __('No leave filed'), 'sub' => __('Filed leave appears here for approval.')])
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if($leave->hasPages())<div class="mod-pager">{{ $leave->links() }}</div>@endif
        </div>
    @else
        <div class="mod-note">
            <i class="fas fa-circle-info"></i>
            <div>{{ __('Vale is a separate instrument, settled inside one pay period, and is still handled in Payroll Settings. Nothing here changes it.') }}</div>
        </div>

        <div class="mod-stats">
            <div class="mod-stat">
                <p class="mod-stat-label">{{ __('Active') }}</p>
                <p class="mod-stat-value">{{ $summary['active'] }}</p>
                <p class="mod-stat-sub">{{ __('still being collected') }}</p>
            </div>
            <div class="mod-stat">
                <p class="mod-stat-label">{{ __('Outstanding') }}</p>
                <p class="mod-stat-value is-warn">₱{{ number_format($summary['outstanding'], 2) }}</p>
                <p class="mod-stat-sub">{{ __('owed across all workers') }}</p>
            </div>
            <div class="mod-stat">
                <p class="mod-stat-label">{{ __('Total Issued') }}</p>
                <p class="mod-stat-value">₱{{ number_format($summary['issued'], 2) }}</p>
            </div>
            <div class="mod-stat">
                <p class="mod-stat-label">{{ __('Collected') }}</p>
                <p class="mod-stat-value is-ok">₱{{ number_format($summary['collected'], 2) }}</p>
            </div>
        </div>

        <div class="mod-card">
            <form method="GET" class="mod-filters">
                <input type="hidden" name="tab" value="advances">
                <div class="mod-filter mod-filter-grow">
                    <label for="fq">{{ __('Employee') }}</label>
                    <input id="fq" class="form-control" type="text" name="q" value="{{ request('q') }}" placeholder="{{ __('Search a name') }}">
                </div>
                <div class="mod-filter">
                    <label for="fstatus">{{ __('Status') }}</label>
                    <select id="fstatus" class="form-select" name="status">
                        <option value="">{{ __('All') }}</option>
                        @foreach(\App\Models\Loan::STATUSES as $k => $v)
                            <option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mod-filter-actions">
                    <button class="mod-btn primary" type="submit"><i class="fas fa-magnifying-glass"></i> {{ __('Apply') }}</button>
                    <a class="mod-btn" href="{{ route('leave.index', ['tab' => 'advances']) }}">{{ __('Reset') }}</a>
                </div>
            </form>

            <div class="mod-table-wrap">
                <table class="mod-table">
                    <thead>
                        <tr>
                            <th>{{ __('Employee') }}</th>
                            <th class="num">{{ __('Amount') }}</th>
                            <th class="num">{{ __('Balance') }}</th>
                            <th class="num">{{ __('Instalment') }}</th>
                            <th>{{ __('Schedule') }}</th>
                            <th>{{ __('Issued') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($advances as $advance)
                        <tr>
                            <td>@include('modules._person', ['name' => $advance->employee->name ?? '—', 'sub' => $advance->reference ?: ''])</td>
                            <td class="num">₱{{ number_format($advance->principal, 2) }}</td>
                            <td class="num strong">
                                ₱{{ number_format($advance->balance, 2) }}
                                <div class="mod-person-sub">{{ $advance->progress }}% {{ __('paid') }}</div>
                            </td>
                            <td class="num">₱{{ number_format($advance->installment, 2) }}</td>
                            <td class="muted">{{ $advance->schedule === 'monthly' ? __('Monthly') : __('Per payroll') }}</td>
                            <td class="muted">{{ $advance->issued_on->format('M d, Y') }}</td>
                            <td>
                                @php $tone = ['paid' => 'ok', 'cancelled' => 'muted', 'on_hold' => 'warn'][$advance->status] ?? 'info'; @endphp
                                <span class="mod-badge {{ $tone }}"><span class="dot"></span>{{ $advance->status_label }}</span>
                            </td>
                            <td>
                                @if($advance->status === 'active')
                                    <div class="mod-row-actions">
                                        <button class="mod-btn sm" type="button" data-bs-toggle="modal"
                                                data-bs-target="#payModal{{ $advance->id }}">
                                            <i class="fas fa-peso-sign"></i> {{ __('Record payment') }}
                                        </button>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        @include('modules._empty', ['cols' => 8, 'icon' => 'fa-hand-holding-dollar',
                            'title' => __('No cash advances recorded'), 'sub' => __('Advances appear here with their running balance.')])
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if($advances->hasPages())<div class="mod-pager">{{ $advances->links() }}</div>@endif
        </div>
    @endif
</div>

@if($tab === 'leave')
{{-- ── File leave ──────────────────────────────────────────────────────── --}}
<div class="modal fade" id="leaveModal" tabindex="-1" aria-hidden="true" aria-labelledby="leaveModalTitle">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable emp-dialog">
    <div class="modal-content emp-modal">
      <form method="POST" action="{{ route('leave.store') }}">
        @csrf
        <div class="emp-head">
            <span class="emp-head-icon" aria-hidden="true"><i class="fas fa-calendar-day"></i></span>
            <div class="emp-head-text">
                <h6 class="emp-head-title" id="leaveModalTitle">{{ __('File Leave') }}</h6>
                <p class="emp-head-sub">{{ __('Recorded as pending until someone approves it.') }}</p>
            </div>
            <button type="button" class="emp-head-x" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body emp-body">
            <div class="mod-form-grid">
                <div class="emp-field full">
                    <label class="ep-label" for="lv_emp">{{ __('Employee') }} <span class="ep-req">*</span></label>
                    <select class="form-select" id="lv_emp" name="employee_id" required>
                        <option value="">{{ __('— Select —') }}</option>
                        @foreach($employees as $e)<option value="{{ $e->id }}">{{ $e->name }}</option>@endforeach
                    </select>
                </div>
                <div class="emp-field">
                    <label class="ep-label" for="lv_type">{{ __('Leave Type') }} <span class="ep-req">*</span></label>
                    <select class="form-select" id="lv_type" name="leave_type" required>
                        @foreach(\App\Models\LeaveRequest::TYPES as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
                    </select>
                </div>
                <div class="emp-field">
                    <label class="ep-label" for="lv_days">{{ __('Days') }}</label>
                    <input class="form-control" id="lv_days" type="number" step="0.5" min="0" name="days" placeholder="{{ __('Auto from dates') }}">
                    <span class="ep-hint">{{ __('Leave blank to count calendar days.') }}</span>
                </div>
                <div class="emp-field">
                    <label class="ep-label" for="lv_from">{{ __('Start Date') }} <span class="ep-req">*</span></label>
                    <input class="form-control" id="lv_from" type="date" name="starts_on" required>
                </div>
                <div class="emp-field">
                    <label class="ep-label" for="lv_to">{{ __('End Date') }} <span class="ep-req">*</span></label>
                    <input class="form-control" id="lv_to" type="date" name="ends_on" required>
                </div>
                <div class="emp-field full">
                    <label class="ep-label" for="lv_reason">{{ __('Reason') }}</label>
                    <textarea class="form-control" id="lv_reason" name="reason" rows="2" style="height:auto;padding:9px 13px;"></textarea>
                </div>
                <div class="emp-field full">
                    <label class="ep-label" style="display:flex;align-items:center;gap:8px;text-transform:none;letter-spacing:0;font-size:13px;">
                        <input type="checkbox" name="is_paid" value="1" checked>
                        {{ __('Paid leave — credit these days in payroll') }}
                    </label>
                </div>
            </div>
        </div>
        <div class="emp-foot">
            <p class="emp-foot-note"><span class="ep-req">*</span> {{ __('Required') }}</p>
            <button type="button" class="emp-btn-cancel" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
            <button type="submit" class="emp-btn-save"><i class="fas fa-check"></i> <span>{{ __('File Leave') }}</span></button>
        </div>
      </form>
    </div>
  </div>
</div>
@else
{{-- A record-payment dialog per active advance: the amount is capped at that
     advance's own balance, which a single shared form could not enforce. --}}
@foreach($advances as $advance)
    @if($advance->status === 'active')
    <div class="modal fade" id="payModal{{ $advance->id }}" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered emp-dialog" style="max-width:460px;">
        <div class="modal-content emp-modal">
          <form method="POST" action="{{ route('loans.payment', $advance) }}">
            @csrf
            <div class="emp-head">
                <span class="emp-head-icon"><i class="fas fa-peso-sign"></i></span>
                <div class="emp-head-text">
                    <h6 class="emp-head-title">{{ __('Record Payment') }}</h6>
                    <p class="emp-head-sub">{{ $advance->employee->name ?? '' }} &middot; {{ __('balance') }} ₱{{ number_format($advance->balance, 2) }}</p>
                </div>
                <button type="button" class="emp-head-x" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body emp-body">
                <div class="mod-note">
                    <i class="fas fa-circle-info"></i>
                    <div>{{ __('For a payment made outside payroll. Payroll posts its own collections when a run is finalised.') }}</div>
                </div>
                <div class="emp-field">
                    <label class="ep-label" for="amt{{ $advance->id }}">{{ __('Amount') }} <span class="ep-req">*</span></label>
                    <input class="form-control" id="amt{{ $advance->id }}" type="number" step="0.01" min="0.01"
                           max="{{ $advance->balance }}" name="amount" required>
                </div>
                <div class="emp-field">
                    <label class="ep-label" for="don{{ $advance->id }}">{{ __('Date') }} <span class="ep-req">*</span></label>
                    <input class="form-control" id="don{{ $advance->id }}" type="date" name="deducted_on" value="{{ now()->toDateString() }}" required>
                </div>
                <div class="emp-field">
                    <label class="ep-label" for="nt{{ $advance->id }}">{{ __('Note') }}</label>
                    <input class="form-control" id="nt{{ $advance->id }}" type="text" name="note" maxlength="255">
                </div>
            </div>
            <div class="emp-foot">
                <button type="button" class="emp-btn-cancel" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                <button type="submit" class="emp-btn-save"><i class="fas fa-check"></i> <span>{{ __('Record') }}</span></button>
            </div>
          </form>
        </div>
      </div>
    </div>
    @endif
@endforeach

{{-- ── New cash advance ────────────────────────────────────────────────── --}}
<div class="modal fade" id="advanceModal" tabindex="-1" aria-hidden="true" aria-labelledby="advanceModalTitle">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable emp-dialog">
    <div class="modal-content emp-modal">
      <form method="POST" action="{{ route('loans.store') }}">
        @csrf
        <div class="emp-head">
            <span class="emp-head-icon"><i class="fas fa-hand-holding-dollar"></i></span>
            <div class="emp-head-text">
                <h6 class="emp-head-title" id="advanceModalTitle">{{ __('New Cash Advance') }}</h6>
                <p class="emp-head-sub">{{ __('The balance starts at the full amount and falls as payroll collects.') }}</p>
            </div>
            <button type="button" class="emp-head-x" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body emp-body">
            <div class="mod-form-grid">
                <div class="emp-field full">
                    <label class="ep-label" for="ca_emp">{{ __('Employee') }} <span class="ep-req">*</span></label>
                    <select class="form-select" id="ca_emp" name="employee_id" required>
                        <option value="">{{ __('— Select —') }}</option>
                        @foreach($employees as $e)<option value="{{ $e->id }}">{{ $e->name }}</option>@endforeach
                    </select>
                </div>
                <div class="emp-field">
                    <label class="ep-label" for="ca_amt">{{ __('Amount') }} <span class="ep-req">*</span></label>
                    <input class="form-control" id="ca_amt" type="number" step="0.01" min="1" name="principal" required>
                </div>
                <div class="emp-field">
                    <label class="ep-label" for="ca_inst">{{ __('Instalment') }} <span class="ep-req">*</span></label>
                    <input class="form-control" id="ca_inst" type="number" step="0.01" min="1" name="installment" required>
                    <span class="ep-hint">{{ __('Taken each payroll until the balance is nil.') }}</span>
                </div>
                <div class="emp-field">
                    <label class="ep-label" for="ca_sched">{{ __('Schedule') }} <span class="ep-req">*</span></label>
                    <select class="form-select" id="ca_sched" name="schedule" required>
                        <option value="per_payroll">{{ __('Every payroll') }}</option>
                        <option value="monthly">{{ __('Monthly') }}</option>
                    </select>
                </div>
                <div class="emp-field">
                    <label class="ep-label" for="ca_ref">{{ __('Reference') }}</label>
                    <input class="form-control" id="ca_ref" type="text" name="reference" maxlength="40">
                </div>
                <div class="emp-field">
                    <label class="ep-label" for="ca_issued">{{ __('Date Issued') }} <span class="ep-req">*</span></label>
                    <input class="form-control" id="ca_issued" type="date" name="issued_on" value="{{ now()->toDateString() }}" required>
                </div>
                <div class="emp-field">
                    <label class="ep-label" for="ca_starts">{{ __('Start Collecting') }}</label>
                    <input class="form-control" id="ca_starts" type="date" name="starts_on">
                    <span class="ep-hint">{{ __('Blank collects from the next payroll.') }}</span>
                </div>
                <div class="emp-field full">
                    <label class="ep-label" for="ca_notes">{{ __('Notes') }}</label>
                    <textarea class="form-control" id="ca_notes" name="notes" rows="2" style="height:auto;padding:9px 13px;"></textarea>
                </div>
            </div>
        </div>
        <div class="emp-foot">
            <p class="emp-foot-note"><span class="ep-req">*</span> {{ __('Required') }}</p>
            <button type="button" class="emp-btn-cancel" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
            <button type="submit" class="emp-btn-save"><i class="fas fa-check"></i> <span>{{ __('Record') }}</span></button>
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
