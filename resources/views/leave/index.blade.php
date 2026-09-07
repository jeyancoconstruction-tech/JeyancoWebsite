@extends('layouts')
@section('page_title', 'Leave & Overtime')

@section('content')
<div class="mod-page">

    @include('modules._head', [
        'title' => __('Leave & Overtime'),
        'sub'   => __('Filed leave and overtime claims, and the decisions on them. Approved rows are picked up by Payroll Processing; neither one writes an attendance record.'),
        'actions' => '<button type="button" class="mod-btn primary" data-bs-toggle="modal" data-bs-target="#'
                     . ($tab === 'overtime' ? 'otModal' : 'leaveModal') . '">'
                     . '<i class="fas fa-plus"></i> ' . ($tab === 'overtime' ? __('File Overtime') : __('File Leave'))
                     . '</button>',
    ])

    @include('modules._flash')

    <div class="mod-tabs">
        <a class="mod-tab {{ $tab === 'leave' ? 'active' : '' }}" href="{{ route('leave.index', ['tab' => 'leave']) }}">
            <i class="fas fa-calendar-day"></i> {{ __('Leave') }}
            @if($counts['leave_pending'])<span class="mod-tab-count">{{ $counts['leave_pending'] }}</span>@endif
        </a>
        <a class="mod-tab {{ $tab === 'overtime' ? 'active' : '' }}" href="{{ route('leave.index', ['tab' => 'overtime']) }}">
            <i class="fas fa-clock"></i> {{ __('Overtime') }}
            @if($counts['ot_pending'])<span class="mod-tab-count">{{ $counts['ot_pending'] }}</span>@endif
        </a>
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
                                        <form method="POST" action="{{ route('leave.decide', ['kind' => 'leave', 'id' => $row->id]) }}">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="decision" value="approved">
                                            <button class="mod-btn sm ok" type="submit"><i class="fas fa-check"></i> {{ __('Approve') }}</button>
                                        </form>
                                        <form method="POST" action="{{ route('leave.decide', ['kind' => 'leave', 'id' => $row->id]) }}">
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
        <div class="mod-card">
            <form method="GET" class="mod-filters">
                <input type="hidden" name="tab" value="overtime">
                <div class="mod-filter mod-filter-grow">
                    <label for="oq">{{ __('Employee') }}</label>
                    <input id="oq" class="form-control" type="text" name="q" value="{{ request('q') }}" placeholder="{{ __('Search a name') }}">
                </div>
                <div class="mod-filter">
                    <label for="ostatus">{{ __('Status') }}</label>
                    <select id="ostatus" class="form-select" name="status">
                        <option value="">{{ __('All') }}</option>
                        @foreach(\App\Models\OvertimeRequest::STATUSES as $k => $v)
                            <option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mod-filter">
                    <label for="osite">{{ __('Site') }}</label>
                    <select id="osite" class="form-select" name="site_id">
                        <option value="">{{ __('All sites') }}</option>
                        @foreach($sites as $s)
                            <option value="{{ $s->id }}" @selected(request('site_id') == $s->id)>{{ $s->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mod-filter">
                    <label for="ofrom">{{ __('From') }}</label>
                    <input id="ofrom" class="form-control" type="date" name="from" value="{{ request('from') }}">
                </div>
                <div class="mod-filter">
                    <label for="oto">{{ __('To') }}</label>
                    <input id="oto" class="form-control" type="date" name="to" value="{{ request('to') }}">
                </div>
                <div class="mod-filter-actions">
                    <button class="mod-btn primary" type="submit"><i class="fas fa-magnifying-glass"></i> {{ __('Apply') }}</button>
                    <a class="mod-btn" href="{{ route('leave.index', ['tab' => 'overtime']) }}">{{ __('Reset') }}</a>
                </div>
            </form>

            <div class="mod-table-wrap">
                <table class="mod-table">
                    <thead>
                        <tr>
                            <th>{{ __('Employee') }}</th>
                            <th>{{ __('Date') }}</th>
                            <th>{{ __('Site') }}</th>
                            <th>{{ __('Hours') }}</th>
                            <th class="num">{{ __('Rate') }}</th>
                            <th class="num">{{ __('Amount') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($overtime as $row)
                        <tr>
                            <td>@include('modules._person', ['name' => $row->employee->name ?? '—', 'sub' => $row->employee->position ?? ''])</td>
                            <td class="muted">{{ $row->date->format('M d, Y') }}</td>
                            <td class="muted">{{ $row->site->name ?? '—' }}</td>
                            <td class="strong">
                                {{ rtrim(rtrim(number_format($row->hours, 2), '0'), '.') }}h
                                @if($row->starts_at && $row->ends_at)
                                    <div class="mod-person-sub">{{ \Carbon\Carbon::parse($row->starts_at)->format('g:i A') }} – {{ \Carbon\Carbon::parse($row->ends_at)->format('g:i A') }}</div>
                                @endif
                            </td>
                            <td class="num muted">₱{{ number_format($row->hourly_rate, 2) }} &times; {{ rtrim(rtrim(number_format($row->multiplier, 2), '0'), '.') }}</td>
                            <td class="num strong">₱{{ number_format($row->amount, 2) }}</td>
                            <td>
                                @php $tone = ['approved' => 'ok', 'rejected' => 'danger'][$row->status] ?? 'warn'; @endphp
                                <span class="mod-badge {{ $tone }}"><span class="dot"></span>{{ $row->status_label }}</span>
                            </td>
                            <td>
                                @if($row->status === 'pending')
                                    <div class="mod-row-actions">
                                        <form method="POST" action="{{ route('leave.decide', ['kind' => 'overtime', 'id' => $row->id]) }}">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="decision" value="approved">
                                            <button class="mod-btn sm ok" type="submit"><i class="fas fa-check"></i> {{ __('Approve') }}</button>
                                        </form>
                                        <form method="POST" action="{{ route('leave.decide', ['kind' => 'overtime', 'id' => $row->id]) }}">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="decision" value="rejected">
                                            <button class="mod-btn sm danger" type="submit"><i class="fas fa-xmark"></i> {{ __('Reject') }}</button>
                                        </form>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        @include('modules._empty', ['cols' => 8, 'icon' => 'fa-clock',
                            'title' => __('No overtime filed'), 'sub' => __('Approved overtime is added to payroll as a separate earning.')])
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if($overtime->hasPages())<div class="mod-pager">{{ $overtime->links() }}</div>@endif
        </div>
    @endif
</div>

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

{{-- ── File overtime ───────────────────────────────────────────────────── --}}
<div class="modal fade" id="otModal" tabindex="-1" aria-hidden="true" aria-labelledby="otModalTitle">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable emp-dialog">
    <div class="modal-content emp-modal">
      <form method="POST" action="{{ route('overtime.store') }}">
        @csrf
        <div class="emp-head">
            <span class="emp-head-icon" aria-hidden="true"><i class="fas fa-clock"></i></span>
            <div class="emp-head-text">
                <h6 class="emp-head-title" id="otModalTitle">{{ __('File Overtime') }}</h6>
                <p class="emp-head-sub">{{ __('A claim on top of the day the kiosk already recorded.') }}</p>
            </div>
            <button type="button" class="emp-head-x" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body emp-body">
            <div class="mod-form-grid">
                <div class="emp-field full">
                    <label class="ep-label" for="ot_emp">{{ __('Employee') }} <span class="ep-req">*</span></label>
                    <select class="form-select" id="ot_emp" name="employee_id" required>
                        <option value="">{{ __('— Select —') }}</option>
                        @foreach($employees as $e)<option value="{{ $e->id }}">{{ $e->name }}</option>@endforeach
                    </select>
                </div>
                <div class="emp-field">
                    <label class="ep-label" for="ot_date">{{ __('Date') }} <span class="ep-req">*</span></label>
                    <input class="form-control" id="ot_date" type="date" name="date" required>
                </div>
                <div class="emp-field">
                    <label class="ep-label" for="ot_site">{{ __('Site') }}</label>
                    <select class="form-select" id="ot_site" name="site_id">
                        <option value="">{{ __('— None —') }}</option>
                        @foreach($sites as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
                    </select>
                </div>
                <div class="emp-field">
                    <label class="ep-label" for="ot_start">{{ __('Start Time') }}</label>
                    <input class="form-control" id="ot_start" type="time" name="starts_at">
                </div>
                <div class="emp-field">
                    <label class="ep-label" for="ot_end">{{ __('End Time') }}</label>
                    <input class="form-control" id="ot_end" type="time" name="ends_at">
                </div>
                <div class="emp-field">
                    <label class="ep-label" for="ot_hours">{{ __('Total Hours') }}</label>
                    <input class="form-control" id="ot_hours" type="number" step="0.25" min="0" name="hours" placeholder="{{ __('Auto from times') }}">
                </div>
                <div class="emp-field">
                    <label class="ep-label" for="ot_mult">{{ __('OT Multiplier') }}</label>
                    <input class="form-control" id="ot_mult" type="number" step="0.05" min="1" name="multiplier" value="1.25">
                    <span class="ep-hint">{{ __('Applied to the worker\'s hourly rate.') }}</span>
                </div>
                <div class="emp-field full">
                    <label class="ep-label" for="ot_reason">{{ __('Reason') }}</label>
                    <textarea class="form-control" id="ot_reason" name="reason" rows="2" style="height:auto;padding:9px 13px;"></textarea>
                </div>
            </div>
        </div>
        <div class="emp-foot">
            <p class="emp-foot-note"><span class="ep-req">*</span> {{ __('Required') }}</p>
            <button type="button" class="emp-btn-cancel" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
            <button type="submit" class="emp-btn-save"><i class="fas fa-check"></i> <span>{{ __('File Overtime') }}</span></button>
        </div>
      </form>
    </div>
  </div>
</div>

@include('modules._kit')
@include('employees._profile_styles')
@include('employees._modal_styles')
@endsection

