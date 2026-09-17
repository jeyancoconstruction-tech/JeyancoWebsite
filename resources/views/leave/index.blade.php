@extends('layouts')
@section('page_title', 'Leave & Advances')

@section('content')
@php
    // The subtitle and the one button follow the open tab.
    $head = $tab === 'advances'
        ? [
            'sub'    => __('Cash handed to a worker ahead of pay and collected back over several payrolls. Payroll takes the instalment the application asked for, every payroll, until nothing is left.'),
            'modal'  => 'advanceModal',
            'button' => __('New Cash Advance'),
        ]
        : [
            'sub'    => __('Leave counts as soon as it is filed. Paid leave is picked up by Payroll Processing as its days come round and never writes an attendance record. Overtime is not filed — payroll counts it from attendance.'),
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
        </a>
        @if($canAdvances)
            <a class="mod-tab {{ $tab === 'advances' ? 'active' : '' }}" href="{{ route('leave.index', ['tab' => 'advances']) }}">
                <i class="fas fa-hand-holding-dollar"></i> {{ __('Cash Advances') }}
            </a>
        @endif
    </div>

    @if($tab === 'leave')
        <div class="mod-card">
            <form method="GET" class="mod-filters" data-autoload>
                <input type="hidden" name="tab" value="leave">
                <div class="mod-filter mod-filter-grow">
                    <label for="lq">{{ __('Employee') }}</label>
                    <input id="lq" class="form-control" type="text" name="q" value="{{ request('q') }}" placeholder="{{ __('Search a name') }}">
                </div>
                <div class="mod-filter">
                    <label for="lstatus">{{ __('Status') }}</label>
                    <select id="lstatus" class="form-select" name="status">
                        <option value="">{{ __('All') }}</option>
                        @foreach(\App\Models\LeaveRequest::DISPLAY_STATUSES as $k => $v)
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
                {{-- No Apply and no Reset: the list follows the filters as
                     they change. Every control already has its own way back
                     — All, or an empty box — the same as on Attendance. --}}
                <noscript><div class="mod-filter-actions"><button class="mod-btn primary" type="submit">{{ __('Apply') }}</button></div></noscript>
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
                            <th>{{ __('Filed by') }}</th>
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
                                {{-- The Cash Advances tab's colours: blue while it
                                     runs, green once it is over, grey if called off. --}}
                                @php $tone = ['completed' => 'ok', 'cancelled' => 'muted'][$row->display_status] ?? 'info'; @endphp
                                <span class="mod-badge {{ $tone }}"><span class="dot"></span>{{ $row->status_label }}</span>
                            </td>
                            <td class="muted">
                                {{-- Whoever entered it. Filing is the decision
                                     now, so there is no separate approver to
                                     name; an older row filed before that falls
                                     back to the person who approved it. --}}
                                {{ $row->filer->name ?? $row->approver->name ?? '—' }}
                                <div class="mod-person-sub">{{ $row->created_at?->format('M d, Y') }}</div>
                            </td>
                            <td>
                                @if($row->status === 'cancelled')
                                    <form method="POST" action="{{ route('leave.decide', ['id' => $row->id]) }}" class="mod-row-actions">
                                        @csrf @method('PATCH')
                                        <input type="hidden" name="decision" value="approved">
                                        <button class="mod-btn sm" type="submit"><i class="fas fa-rotate-left"></i> {{ __('Restore') }}</button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('leave.decide', ['id' => $row->id]) }}" class="mod-row-actions"
                                          data-confirm="{{ $row->is_paid
                                              ? __('Its days stop being paid. A payroll already finalised keeps what it paid.')
                                              : __('It stays on record, marked cancelled.') }}"
                                          data-confirm-title="{{ __('Cancel this leave?') }}"
                                          data-confirm-label="{{ __('Cancel leave') }}"
                                          data-confirm-tone="danger">
                                        @csrf @method('PATCH')
                                        <input type="hidden" name="decision" value="cancelled">
                                        <button class="mod-btn sm danger" type="submit"><i class="fas fa-xmark"></i> {{ __('Cancel') }}</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        @include('modules._empty', ['cols' => 8, 'icon' => 'fa-calendar-day',
                            'title' => __('No leave filed'), 'sub' => __('Leave filed here counts straight away.')])
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
            <form method="GET" class="mod-filters" data-autoload>
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
                <noscript><div class="mod-filter-actions"><button class="mod-btn primary" type="submit">{{ __('Apply') }}</button></div></noscript>
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
                                ₱{{ number_format($advance->outstanding, 2) }}
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
                                <div class="mod-row-actions dropdown">
                                    {{-- Measured against the viewport, and taken out of the
                                         card's overflow by the script at the foot of the page:
                                         the card hides what spills out of it and the table
                                         scrolls, so a menu laid out inside either is cut off
                                         whenever the list is short. --}}
                                    <button class="mod-btn sm mod-dots" type="button" data-bs-toggle="dropdown"
                                            aria-expanded="false"
                                            aria-label="{{ __('Actions for') }} {{ $advance->employee->name ?? '' }}">
                                        <i class="fas fa-ellipsis-vertical"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end mod-dropdown">
                                        @unless($advance->settled)
                                            <li>
                                                <button class="dropdown-item" type="button" data-bs-toggle="modal"
                                                        data-bs-target="#payModal{{ $advance->id }}">
                                                    <i class="fas fa-peso-sign"></i> {{ __('Add payment') }}
                                                </button>
                                            </li>
                                            <li>
                                                <button class="dropdown-item" type="button" data-bs-toggle="modal"
                                                        data-bs-target="#instModal{{ $advance->id }}">
                                                    <i class="fas fa-pen"></i> {{ __('Edit instalment') }}
                                                </button>
                                            </li>
                                        @endunless
                                        <li>
                                            <button class="dropdown-item" type="button" data-bs-toggle="modal"
                                                    data-bs-target="#histModal{{ $advance->id }}">
                                                <i class="fas fa-clock-rotate-left"></i> {{ __('Payment history') }}
                                            </button>
                                        </li>
                                    </ul>
                                </div>
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
                <p class="emp-head-sub">{{ __('Counts as soon as it is filed — no approval step.') }}</p>
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
{{-- A record-payment dialog per unsettled advance: the amount is capped at
     that advance's own balance, which a single shared form could not enforce.
     Alongside it, the history of everything that has come off it. --}}
@foreach($advances as $advance)
    @php $ledger = $advance->walk(now()->toDateString(), \App\Models\Loan::payWeekStartsOn()); @endphp

    {{-- ── Payment / deduction history ──────────────────────────────────── --}}
    <div class="modal fade" id="histModal{{ $advance->id }}" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable emp-dialog" style="max-width:620px;">
        <div class="modal-content emp-modal">
            <div class="emp-head">
                <span class="emp-head-icon"><i class="fas fa-clock-rotate-left"></i></span>
                <div class="emp-head-text">
                    <h6 class="emp-head-title">{{ __('Payment history') }}</h6>
                    <p class="emp-head-sub">
                        {{ $advance->employee->name ?? '' }} &middot;
                        ₱{{ number_format($advance->principal, 2) }} {{ __('advanced') }} &middot;
                        ₱{{ number_format($advance->installment, 2) }} {{ __('per payroll') }}
                    </p>
                </div>
                <button type="button" class="emp-head-x" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body emp-body">
                <div class="mod-note">
                    <i class="fas fa-circle-info"></i>
                    <div>
                        {{ __('Collected') }} <b>₱{{ number_format($advance->paid_amount, 2) }}</b>
                        {{ __('of') }} ₱{{ number_format($advance->principal, 2) }} &middot;
                        <b>₱{{ number_format($ledger['outstanding'], 2) }}</b> {{ __('still owed') }}.
                        {{ __('Payroll instalments are the ones payroll takes for each week; payments are the ones handed in at the office.') }}
                    </div>
                </div>

                <div class="mod-table-wrap">
                    <table class="mod-table">
                        <thead>
                            <tr>
                                <th>{{ __('Date') }}</th>
                                <th>{{ __('Type') }}</th>
                                <th class="num">{{ __('Amount') }}</th>
                                <th class="num">{{ __('Balance after') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse($ledger['lines'] as $line)
                            <tr>
                                <td class="muted">{{ \Carbon\Carbon::parse($line['date'])->format('M d, Y') }}</td>
                                <td>
                                    <span class="mod-badge {{ $line['type'] === 'payroll' ? 'info' : 'ok' }}">
                                        <span class="dot"></span>{{ __($line['label']) }}
                                    </span>
                                    @if(filled($line['note'] ?? null))
                                        <div class="mod-person-sub">{{ $line['note'] }}</div>
                                    @endif
                                </td>
                                <td class="num">₱{{ number_format($line['amount'], 2) }}</td>
                                <td class="num strong">₱{{ number_format($line['balance'], 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="muted" style="text-align:center;padding:18px;">
                                    {{ __('Nothing collected yet.') }}
                                    {{ __('Collection starts') }} {{ $advance->collectionOpensOn()->format('M d, Y') }}.
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="emp-foot">
                <button type="button" class="emp-btn-cancel" data-bs-dismiss="modal">{{ __('Close') }}</button>
            </div>
        </div>
      </div>
    </div>

    @unless($advance->settled)
    {{-- ── Correct the instalment ───────────────────────────────────────── --}}
    <div class="modal fade" id="instModal{{ $advance->id }}" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable emp-dialog">
        <div class="modal-content emp-modal">
          <form method="POST" action="{{ route('loans.update', $advance) }}">
            @csrf @method('PUT')
            <div class="emp-head">
                <span class="emp-head-icon"><i class="fas fa-pen"></i></span>
                <div class="emp-head-text">
                    <h6 class="emp-head-title">{{ __('Edit instalment') }}</h6>
                    <p class="emp-head-sub">
                        {{ $advance->employee->name ?? '' }} &middot;
                        ₱{{ number_format($advance->principal, 2) }} {{ __('advanced') }}
                    </p>
                </div>
                <button type="button" class="emp-head-x" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body emp-body">
                <div class="mod-note">
                    <i class="fas fa-triangle-exclamation"></i>
                    <div>{{ __('For correcting an instalment that was entered wrongly. The schedule is worked out again from the first payroll, so what earlier payrolls collected changes with it — this is not the way to re-negotiate an advance that is part paid.') }}</div>
                </div>
                {{-- Laid out as the form that recorded the advance is: the
                     same grid, the same hint under the field, the same note
                     in the footer. It is the same instalment being entered. --}}
                <div class="mod-form-grid">
                    <div class="emp-field">
                        <label class="ep-label" for="amt-of{{ $advance->id }}">{{ __('Amount') }}</label>
                        <input class="form-control" id="amt-of{{ $advance->id }}" type="text"
                               value="₱{{ number_format($advance->principal, 2) }}" readonly>
                        <span class="ep-hint">{{ __('What was handed over. Not editable here.') }}</span>
                    </div>
                    <div class="emp-field">
                        <label class="ep-label" for="inst{{ $advance->id }}">{{ __('Instalment') }} <span class="ep-req">*</span></label>
                        <input class="form-control" id="inst{{ $advance->id }}" type="number" step="0.01" min="1"
                               max="{{ $advance->principal }}" name="installment"
                               value="{{ number_format($advance->installment, 2, '.', '') }}" required>
                        <span class="ep-hint">
                            {{ __('At the figure on file this advance takes') }}
                            {{ max(1, (int) ceil($advance->principal / max(0.01, (float) $advance->installment))) }}
                            {{ __('payrolls to collect.') }}
                        </span>
                    </div>
                </div>
            </div>
            <div class="emp-foot">
                <p class="emp-foot-note"><span class="ep-req">*</span> {{ __('Required') }}</p>
                <button type="button" class="emp-btn-cancel" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                <button type="submit" class="emp-btn-save"><i class="fas fa-check"></i> <span>{{ __('Save') }}</span></button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="modal fade" id="payModal{{ $advance->id }}" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable emp-dialog">
        <div class="modal-content emp-modal">
          <form method="POST" action="{{ route('loans.payment', $advance) }}" data-once>
            @csrf
            <div class="emp-head">
                <span class="emp-head-icon"><i class="fas fa-peso-sign"></i></span>
                <div class="emp-head-text">
                    <h6 class="emp-head-title">{{ __('Record Payment') }}</h6>
                    <p class="emp-head-sub">{{ $advance->employee->name ?? '' }} &middot; {{ __('balance') }} ₱{{ number_format($ledger["outstanding"], 2) }}</p>
                </div>
                <button type="button" class="emp-head-x" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body emp-body">
                <div class="mod-note">
                    <i class="fas fa-circle-info"></i>
                    <div>{{ __('For a payment handed in at the office, on top of what payroll collects. It comes straight off the balance, and payroll takes less — or nothing — from then on.') }}</div>
                </div>
                <div class="mod-form-grid">
                    <div class="emp-field">
                        <label class="ep-label" for="amt{{ $advance->id }}">{{ __('Amount') }} <span class="ep-req">*</span></label>
                        <input class="form-control" id="amt{{ $advance->id }}" type="number" step="0.01" min="0.01"
                               max="{{ $ledger['outstanding'] }}" name="amount" required>
                        <span class="ep-hint">{{ __('At most the') }} ₱{{ number_format($ledger['outstanding'], 2) }} {{ __('still outstanding.') }}</span>
                    </div>
                    <div class="emp-field">
                        <label class="ep-label" for="don{{ $advance->id }}">{{ __('Date') }} <span class="ep-req">*</span></label>
                        <input class="form-control" id="don{{ $advance->id }}" type="date" name="deducted_on" value="{{ now()->toDateString() }}"
                               min="{{ $advance->issued_on->toDateString() }}" max="{{ now()->toDateString() }}" required>
                        <span class="ep-hint">{{ __('The day it was handed in — not before') }} {{ $advance->issued_on->format('M d, Y') }}.</span>
                    </div>
                    <div class="emp-field full">
                        <label class="ep-label" for="nt{{ $advance->id }}">{{ __('Note') }}</label>
                        <input class="form-control" id="nt{{ $advance->id }}" type="text" name="note" maxlength="255">
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
    @endunless
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

@push('scripts')
<script>
// ── Filters apply themselves ────────────────────────────────────────────────
// There is no Apply button. Picking a status, a type or a date reloads the
// list at once. A name reloads once typing pauses — on every keystroke the
// page would reload under the reader mid-word — or at once on Enter, and the
// cursor is put back at the end of what was typed, so the search reads as
// one continuous box rather than one that throws you out after each letter.
// ── A payment is sent once ─────────────────────────────────────────────────
// A double click on Record sent the form twice and wrote the payment twice.
// The button goes quiet the moment the form is on its way. (The server
// refuses an identical payment seconds apart as well, for whatever gets past
// this — a resubmitted refresh, say.)
document.querySelectorAll('form[data-once]').forEach(form => {
    form.addEventListener('submit', e => {
        if (form.dataset.sent) { e.preventDefault(); return; }
        form.dataset.sent = '1';
        form.querySelectorAll('button[type="submit"]').forEach(b => { b.disabled = true; });
    });
});

(function () {
    const KEY = 'leaveFilterFocus';

    document.querySelectorAll('form.mod-filters[data-autoload]').forEach(form => {
        const go = () => (form.requestSubmit ? form.requestSubmit() : form.submit());

        form.querySelectorAll('select').forEach(el => {
            el.addEventListener('change', go);
        });

        // A date picked from the calendar is one change. A date typed by hand
        // is several: Chrome reports each digit of the year as a whole valid
        // date — 0002, 0020, 0202 — and sending the first would reload the
        // page three keystrokes early. So a date waits for a real year, or
        // for an empty box, and for the typing to settle.
        form.querySelectorAll('input[type="date"]').forEach(el => {
            let timer = null;

            el.addEventListener('change', () => {
                clearTimeout(timer);
                if (el.value !== '' && el.value.slice(0, 4) < '1900') return;
                timer = setTimeout(go, 400);
            });
        });

        form.querySelectorAll('input[type="text"]').forEach(el => {
            let timer = null;
            const sent = el.value;

            const send = () => {
                clearTimeout(timer);
                if (el.value.trim() === sent.trim()) return;
                try { sessionStorage.setItem(KEY, el.id); } catch (e) {}
                go();
            };

            el.addEventListener('input', () => {
                clearTimeout(timer);
                timer = setTimeout(send, 600);
            });

            el.addEventListener('keydown', e => {
                if (e.key === 'Enter') { e.preventDefault(); send(); }
            });
        });
    });

    // Back where the reader was typing, caret at the end.
    let id = null;
    try { id = sessionStorage.getItem(KEY); sessionStorage.removeItem(KEY); } catch (e) {}

    const box = id && document.getElementById(id);
    if (box) {
        box.focus();
        const end = box.value.length;
        try { box.setSelectionRange(end, end); } catch (e) {}
    }
})();

(function () {
    // A row's menu is laid out inside a table that scrolls sideways, inside a
    // card that hides whatever spills out of it. Either one cuts the menu off
    // when it opens past the last row — which is every time the list is short.
    //
    // Popper's fixed strategy is what gets it out: the menu is positioned
    // against the viewport instead, and an element positioned that way is not
    // clipped by an ancestor's overflow. Bootstrap has no attribute for it, so
    // each menu is built here rather than left to the click handler. The
    // function form is handed Bootstrap's own defaults and only adds to them,
    // so the placement and the flip it already does are kept.
    if (typeof bootstrap === 'undefined') return;

    document.querySelectorAll('.mod-dots[data-bs-toggle="dropdown"]').forEach(function (toggle) {
        bootstrap.Dropdown.getOrCreateInstance(toggle, {
            popperConfig: function () { return { strategy: 'fixed' }; },
        });
    });
})();
</script>
@endpush
