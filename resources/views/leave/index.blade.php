@extends('layouts')
@section('page_title', 'Leave & Advances')

@section('content')
@php
    // The header's one button follows the open tab.
    $head = $tab === 'advances'
        ? [
            'modal'  => 'advanceModal',
            'button' => __('New Cash Advance'),
        ]
        : [
            'modal'  => 'leaveModal',
            'button' => __('File Leave'),
        ];
@endphp
<div class="mod-page">

    {{-- Cash Advances and Leave are sub-items of Leave & Advances in the
         sidebar (2026-09-26); the tab row that sat here is gone, and the
         title names the section open. --}}
    <x-page-header :title="$tab === 'advances' ? __('Cash Advances') : __('Leave')">
        <x-slot:actions>
            <button type="button" class="mod-btn primary" data-bs-toggle="modal" data-bs-target="#{{ $head['modal'] }}"><i class="fas fa-plus"></i> {{ $head['button'] }}</button>
        </x-slot:actions>
    </x-page-header>

    @include('modules._flash')


    @if($tab === 'leave')
        <div class="mod-card" data-fill-screen>
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

            <div class="mod-table-wrap" id="leaveList" data-live="leave employees">
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

        {{-- Balances move as payroll collects and as payments are recorded,
             both of which can happen while this page is open. --}}
        <div class="mod-stats" id="advanceStats" data-live="advances payroll">
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

        <div class="mod-card" data-fill-screen>
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

            <div class="mod-table-wrap" id="advanceList" data-live="advances payroll employees">
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
                                {{-- Coloured from what the label says, not from the
                                     stored column, so the two cannot disagree. --}}
                                @php $tone = ['paid' => 'ok', 'cancelled' => 'muted', 'on_hold' => 'warn'][$advance->settled ? 'paid' : $advance->status] ?? 'info'; @endphp
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
                                        {{-- Last, and set apart: the one thing on this menu
                                             that cannot be taken back. The dialog says what
                                             it does to payroll before anything happens —
                                             this week's instalment comes back, and weeks
                                             already closed keep theirs. --}}
                                        @php
                                            $taken   = $advance->takenAround(now()->toDateString());
                                            $confirm = ($advance->employee->name ?? __('This worker')) . "'s ₱" . number_format($advance->principal, 2)
                                                . ' ' . __('cash advance is removed from Cash Advances.') . ' '
                                                . ($taken['this_week'] > 0
                                                    ? __("This week's") . ' ₱' . number_format($taken['this_week'], 2) . ' ' . __("instalment comes off this week's payroll.")
                                                    : __('Nothing has been deducted from this week\'s payroll.'))
                                                . ($taken['closed_weeks'] > 0
                                                    ? ' ' . __('Payroll weeks already closed keep the') . ' ₱' . number_format($taken['closed_weeks'], 2) . ' ' . __('they deducted.')
                                                    : '');
                                        @endphp
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <form method="POST" action="{{ route('loans.destroy', $advance) }}"
                                                  data-confirm="{{ $confirm }}"
                                                  data-confirm-title="{{ __('Delete this cash advance?') }}"
                                                  data-confirm-label="{{ __('Delete') }}"
                                                  data-confirm-tone="danger">
                                                @csrf @method('DELETE')
                                                <button class="dropdown-item text-danger" type="submit">
                                                    <i class="fas fa-trash"></i> {{ __('Delete') }}
                                                </button>
                                            </form>
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
                    <span class="ep-hint" id="lv_days_hint">{{ __('Leave blank to count the days picked.') }}</span>
                </div>
                {{-- The days off, picked one by one on a calendar: a click
                     marks a day or clears it, and a shift-click marks every
                     day from the last one clicked. Days apart from each other
                     are filed as separate leaves, one per unbroken run, so
                     the days between them are not paid as leave. --}}
                <div class="emp-field full">
                    <span class="ep-label" id="lv_cal_label">{{ __('Leave Dates') }} <span class="ep-req">*</span></span>
                    <div class="lvc" id="lvCal" role="group" aria-labelledby="lv_cal_label">
                        <div class="lvc-head">
                            <button type="button" class="lvc-nav" data-step="-1" aria-label="{{ __('Previous month') }}"><i class="fas fa-chevron-left"></i></button>
                            <b class="lvc-month" aria-live="polite"></b>
                            <button type="button" class="lvc-nav" data-step="1" aria-label="{{ __('Next month') }}"><i class="fas fa-chevron-right"></i></button>
                        </div>
                        <div class="lvc-grid lvc-dow" aria-hidden="true">
                            <span>{{ __('Sun') }}</span><span>{{ __('Mon') }}</span><span>{{ __('Tue') }}</span><span>{{ __('Wed') }}</span><span>{{ __('Thu') }}</span><span>{{ __('Fri') }}</span><span>{{ __('Sat') }}</span>
                        </div>
                        <div class="lvc-grid lvc-days"></div>
                        <div class="lvc-foot">
                            <span class="lvc-count">{{ __('No days picked yet') }}</span>
                            <button type="button" class="lvc-clear" hidden>{{ __('Clear') }}</button>
                        </div>
                        <div class="lvc-picked" aria-live="polite"></div>
                    </div>
                    <span class="ep-hint">{{ __('Click each day off. Shift-click to mark every day from the last one clicked.') }}</span>
                    <div id="lvDates"></div>
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
<div id="advanceDialogs" data-live="advances payroll employees">
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
                                    {{-- A deferred instalment is a week payroll took nothing
                                         in, because the pay could not cover it: amber, and
                                         the amount carried rather than an amount taken. --}}
                                    <span class="mod-badge {{ ['payroll' => 'info', 'deferred' => 'warn'][$line['type']] ?? 'ok' }}">
                                        <span class="dot"></span>{{ __($line['label']) }}
                                    </span>
                                    @if(filled($line['note'] ?? null))
                                        <div class="mod-person-sub">{{ $line['note'] }}</div>
                                    @endif
                                </td>
                                <td class="num">
                                    @if($line['type'] === 'deferred')
                                        <span class="muted">₱{{ number_format($line['deferred'], 2) }} {{ __('not taken') }}</span>
                                    @else
                                        ₱{{ number_format($line['amount'], 2) }}
                                    @endif
                                </td>
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
</div>

{{-- ── New cash advance ────────────────────────────────────────────────── --}}
<div class="modal fade" id="advanceModal" tabindex="-1" aria-hidden="true" aria-labelledby="advanceModalTitle"
     @if(old('_form') === 'advance' && $errors->any()) data-reopen @endif>
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable emp-dialog">
    <div class="modal-content emp-modal">
      <form method="POST" action="{{ route('loans.store') }}" id="advanceForm">
        @csrf
        {{-- Which form was sent, so a refusal reopens this one with what was
             typed — and only this one. Edit instalment posts an "installment"
             too, and must not leave its value or its error in here. --}}
        <input type="hidden" name="_form" value="advance">
        @php
            $sent = old('_form') === 'advance';
            $was  = fn (string $k, $fallback = null) => $sent ? old($k, $fallback) : $fallback;
            $err  = fn (string $k) => $sent ? $errors->first($k) : null;
        @endphp
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
                    <select class="form-select {{ $err('employee_id') ? 'is-invalid' : '' }}" id="ca_emp" name="employee_id" required>
                        <option value="">{{ __('— Select —') }}</option>
                        @foreach($employees as $e)<option value="{{ $e->id }}" @selected((string) $was('employee_id') === (string) $e->id)>{{ $e->name }}</option>@endforeach
                    </select>
                    @if($err('employee_id'))<span class="emp-err" role="alert"><i class="fas fa-circle-exclamation" aria-hidden="true"></i>{{ $err('employee_id') }}</span>@endif
                </div>
                <div class="emp-field">
                    <label class="ep-label" for="ca_amt">{{ __('Amount') }} <span class="ep-req">*</span></label>
                    <input class="form-control {{ $err('principal') ? 'is-invalid' : '' }}" id="ca_amt" type="number" step="0.01" min="1"
                           max="{{ \App\Models\Loan::LIMIT_PER_EMPLOYEE }}" name="principal" value="{{ $was('principal') }}" required>
                    <span class="ep-hint" id="ca_amt_hint"
                          data-limit="{{ \App\Models\Loan::LIMIT_PER_EMPLOYEE }}"
                          data-owed="{{ json_encode((object) ($owed ?? [])) }}">
                        {{ __('Up to') }} ₱{{ number_format(\App\Models\Loan::LIMIT_PER_EMPLOYEE, 2) }} {{ __('per employee, less what they still owe.') }}
                    </span>
                    @if($err('principal'))<span class="emp-err" role="alert"><i class="fas fa-circle-exclamation" aria-hidden="true"></i>{{ $err('principal') }}</span>@endif
                </div>
                <div class="emp-field">
                    <label class="ep-label" for="ca_inst">{{ __('Instalment') }} <span class="ep-req">*</span></label>
                    <input class="form-control {{ $err('installment') ? 'is-invalid' : '' }}" id="ca_inst" type="number" step="0.01" min="1"
                           name="installment" value="{{ $was('installment') }}" required>
                    <span class="ep-hint">{{ __('Taken each payroll until the balance is nil — no more than the amount.') }}</span>
                    {{-- Filled by the page as the two figures are typed, and by
                         the server when it refuses them. --}}
                    <span class="emp-err" id="ca_inst_err" role="alert" @if(! $err('installment')) hidden @endif><i class="fas fa-circle-exclamation" aria-hidden="true"></i><span id="ca_inst_err_text">{{ $err('installment') }}</span></span>
                </div>
                <div class="emp-field">
                    <label class="ep-label" for="ca_sched">{{ __('Schedule') }} <span class="ep-req">*</span></label>
                    <select class="form-select" id="ca_sched" name="schedule" required>
                        <option value="per_payroll" @selected($was('schedule', 'per_payroll') === 'per_payroll')>{{ __('Every payroll') }}</option>
                        <option value="monthly" @selected($was('schedule') === 'monthly')>{{ __('Monthly') }}</option>
                    </select>
                </div>
                <div class="emp-field">
                    <label class="ep-label" for="ca_ref">{{ __('Reference') }}</label>
                    <input class="form-control" id="ca_ref" type="text" name="reference" maxlength="40" value="{{ $was('reference') }}">
                </div>
                <div class="emp-field">
                    <label class="ep-label" for="ca_issued">{{ __('Date Issued') }} <span class="ep-req">*</span></label>
                    <input class="form-control {{ $err('issued_on') ? 'is-invalid' : '' }}" id="ca_issued" type="date" name="issued_on"
                           value="{{ $was('issued_on', now()->toDateString()) }}" required>
                    @if($err('issued_on'))<span class="emp-err" role="alert"><i class="fas fa-circle-exclamation" aria-hidden="true"></i>{{ $err('issued_on') }}</span>@endif
                </div>
                <div class="emp-field">
                    <label class="ep-label" for="ca_starts">{{ __('Start Collecting') }}</label>
                    <input class="form-control {{ $err('starts_on') ? 'is-invalid' : '' }}" id="ca_starts" type="date" name="starts_on" value="{{ $was('starts_on') }}">
                    <span class="ep-hint">{{ __('Blank collects from the next payroll.') }}</span>
                    @if($err('starts_on'))<span class="emp-err" role="alert"><i class="fas fa-circle-exclamation" aria-hidden="true"></i>{{ $err('starts_on') }}</span>@endif
                </div>
                <div class="emp-field full">
                    <label class="ep-label" for="ca_notes">{{ __('Notes') }}</label>
                    <textarea class="form-control" id="ca_notes" name="notes" rows="2" style="height:auto;padding:9px 13px;">{{ $was('notes') }}</textarea>
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
@include('modules._fill_screen')
@include('employees._profile_styles')
@include('employees._modal_styles')

@if($tab === 'leave')
<style>
/* ── The leave calendar (File Leave) ─────────────────────────────────────── */
.lvc { border: 1px solid var(--border-md); border-radius: 10px; background: var(--surface); padding: 8px; }
.lvc-head { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 6px; }
.lvc-month { font-size: 13px; font-weight: 700; color: var(--text-primary); }
.lvc-nav {
    width: 28px; height: 28px; border-radius: 7px; border: 1px solid var(--border); background: var(--surface);
    color: var(--text-secondary); display: grid; place-items: center; cursor: pointer; font-size: 11px;
}
.lvc-nav:hover { border-color: var(--brand); color: var(--brand); }
.lvc-grid { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 2px; }
.lvc-dow span { text-align: center; font-size: 10px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: var(--text-muted); padding: 2px 0 4px; }
.lvc-day {
    height: 28px; border: 1px solid transparent; border-radius: 7px; background: none; cursor: pointer;
    font-size: 12.5px; font-weight: 600; color: var(--text-primary); font-variant-numeric: tabular-nums;
}
.lvc-day:hover { background: var(--bg-subtle); border-color: var(--border-md); }
.lvc-day.is-out { color: var(--text-muted); opacity: .45; }
.lvc-day.is-today { border-color: var(--brand); }
.lvc-day.is-on { background: var(--brand); border-color: var(--brand); color: #fff; }
.lvc-day:focus-visible { outline: 2px solid var(--brand); outline-offset: 1px; }
.lvc-foot { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-top: 8px; padding-top: 8px; border-top: 1px dashed var(--border); }
.lvc-count { font-size: 12px; font-weight: 600; color: var(--text-secondary); }
.lvc-count.has { color: var(--brand); }
.lvc-clear { border: 0; background: none; padding: 0; font-size: 12px; font-weight: 600; color: var(--danger); cursor: pointer; }
.lvc-picked { display: flex; flex-wrap: wrap; gap: 5px; margin-top: 6px; }
.lvc-picked:empty { display: none; }
.lvc-chip { font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 999px; background: var(--brand-subtle); color: var(--brand); white-space: nowrap; }
.lvc.is-invalid { border-color: var(--danger); box-shadow: 0 0 0 3px var(--danger-soft); }
</style>
@endif
@endsection

@push('scripts')
<script>

// ── The leave calendar ───────────────────────────────────────────────────────
// HR clicks each day the worker is off; a shift-click marks every day from the
// last one clicked. Each picked day goes to the server as dates[], which files
// one leave per unbroken run. The Days box is one figure, so it is offered
// only while the days picked are a single run.
(function () {
    const cal = document.getElementById('lvCal');
    if (!cal) return;

    const form    = cal.closest('form');
    const grid    = cal.querySelector('.lvc-days');
    const label   = cal.querySelector('.lvc-month');
    const count   = cal.querySelector('.lvc-count');
    const clear   = cal.querySelector('.lvc-clear');
    const chips   = cal.querySelector('.lvc-picked');
    const holder  = document.getElementById('lvDates');
    const days    = document.getElementById('lv_days');
    const hint    = document.getElementById('lv_days_hint');
    const hintTxt = hint ? hint.textContent : '';

    const pad = n => String(n).padStart(2, '0');
    const key = d => d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
    const parse = k => { const [y, m, d] = k.split('-').map(Number); return new Date(y, m - 1, d); };
    const short = k => parse(k).toLocaleDateString(undefined, { month: 'short', day: 'numeric' });

    const picked = new Set();
    const today  = key(new Date());
    let view     = new Date(); view.setDate(1);
    let anchor   = null;

    // Picked days as unbroken runs, the way the server files them.
    function runs() {
        const out = [];
        [...picked].sort().forEach(k => {
            const last = out[out.length - 1];
            if (last) {
                const next = parse(last[1]); next.setDate(next.getDate() + 1);
                if (key(next) === k) { last[1] = k; return; }
            }
            out.push([k, k]);
        });
        return out;
    }

    function render() {
        label.textContent = view.toLocaleDateString(undefined, { month: 'long', year: 'numeric' });

        const start = new Date(view); start.setDate(1 - view.getDay());
        grid.innerHTML = '';
        for (let i = 0; i < 42; i++) {
            const d = new Date(start); d.setDate(start.getDate() + i);
            const k = key(d);
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'lvc-day'
                + (d.getMonth() !== view.getMonth() ? ' is-out' : '')
                + (k === today ? ' is-today' : '')
                + (picked.has(k) ? ' is-on' : '');
            b.textContent = d.getDate();
            b.dataset.day = k;
            b.setAttribute('aria-pressed', picked.has(k) ? 'true' : 'false');
            b.setAttribute('aria-label', d.toLocaleDateString(undefined, { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' }));
            grid.appendChild(b);
        }
        sync();
    }

    function sync() {
        const r = runs();
        const n = picked.size;

        count.textContent = n === 0
            ? @json(__('No days picked yet'))
            : n + ' ' + (n === 1 ? @json(__('day')) : @json(__('days'))) + ' ' + @json(__('picked'))
              + (r.length > 1 ? ' · ' + r.length + ' ' + @json(__('separate leaves')) : '');
        count.classList.toggle('has', n > 0);
        clear.hidden = n === 0;

        chips.innerHTML = '';
        r.forEach(([a, b]) => {
            const c = document.createElement('span');
            c.className = 'lvc-chip';
            c.textContent = a === b ? short(a) : short(a) + ' – ' + short(b);
            chips.appendChild(c);
        });

        holder.innerHTML = '';
        [...picked].sort().forEach(k => {
            const input = document.createElement('input');
            input.type = 'hidden'; input.name = 'dates[]'; input.value = k;
            holder.appendChild(input);
        });

        if (days) {
            const many = r.length > 1;
            days.disabled = many;
            if (many) days.value = '';
            days.placeholder = n ? String(n) : @json(__('Auto from dates'));
            if (hint) hint.textContent = many ? @json(__('Counted per leave — one day for each day picked.')) : hintTxt;
        }
        if (n) cal.classList.remove('is-invalid');
    }

    grid.addEventListener('click', e => {
        const b = e.target.closest('.lvc-day');
        if (!b) return;
        const k = b.dataset.day;

        if (e.shiftKey && anchor && anchor !== k) {
            let [a, z] = [anchor, k].sort();
            for (let d = parse(a); key(d) <= z; d.setDate(d.getDate() + 1)) picked.add(key(d));
        } else if (picked.has(k)) {
            picked.delete(k);
        } else {
            picked.add(k);
        }
        anchor = k;

        // A day from the next or last month turns the page to it.
        const d = parse(k);
        if (d.getMonth() !== view.getMonth()) { view = new Date(d.getFullYear(), d.getMonth(), 1); }
        render();
        const again = grid.querySelector('[data-day="' + k + '"]');
        if (again) again.focus();
    });

    cal.querySelectorAll('.lvc-nav').forEach(btn => btn.addEventListener('click', () => {
        view.setMonth(view.getMonth() + Number(btn.dataset.step));
        render();
    }));

    clear.addEventListener('click', () => { picked.clear(); anchor = null; render(); });

    form.addEventListener('submit', e => {
        if (picked.size) return;
        e.preventDefault();
        e.stopImmediatePropagation();
        cal.classList.add('is-invalid');
        const first = grid.querySelector('.lvc-day:not(.is-out)');
        if (first) first.focus();
    }, true);

    // A fresh form each time it opens, on the month of today.
    const modal = document.getElementById('leaveModal');
    if (modal) modal.addEventListener('show.bs.modal', () => {
        picked.clear(); anchor = null;
        view = new Date(); view.setDate(1);
        cal.classList.remove('is-invalid');
        render();
    });

    render();
})();

// ── The cash advance limit, as the worker is picked ─────────────────────────
// ₱30,000 a worker, on what they owe. Choosing somebody sets the Amount box's
// ceiling to what is left of it and says so, so the limit is met while the
// form is being filled in rather than as an error after Record. The server
// checks the same figure whatever this does.
(function () {
    const pick = document.getElementById('ca_emp');
    const amt  = document.getElementById('ca_amt');
    const hint = document.getElementById('ca_amt_hint');
    if (!pick || !amt || !hint) return;

    const limit  = Number(hint.dataset.limit) || 0;
    const owedBy = (() => { try { return JSON.parse(hint.dataset.owed || '{}'); } catch (e) { return {}; } })();
    const peso   = n => '₱' + Number(n).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const first  = hint.textContent.trim();

    pick.addEventListener('change', () => {
        if (!pick.value) {
            amt.max = limit;
            hint.textContent = first;
            return;
        }

        const name = pick.options[pick.selectedIndex].text;
        const owed = Number(owedBy[pick.value] || 0);
        const room = Math.max(0, Math.round((limit - owed) * 100) / 100);

        amt.max = room;
        hint.textContent = owed <= 0
            ? 'Up to ' + peso(limit) + '.'
            : room <= 0
                ? name + ' still owes ' + peso(owed) + ' — nothing more until it is paid down.'
                : 'Up to ' + peso(room) + ' — ' + name + ' still owes ' + peso(owed) + ' of the ' + peso(limit) + ' limit.';
    });
})();

// ── The instalment is never more than the amount ───────────────────────────
// Checked as either figure is typed, with the same words the server uses, and
// held on the field as its validity — so the browser will not send the form
// while it is wrong, and says why beside the box rather than in a tooltip.
(function () {
    const amt  = document.getElementById('ca_amt');
    const inst = document.getElementById('ca_inst');
    const box  = document.getElementById('ca_inst_err');
    const text = document.getElementById('ca_inst_err_text');
    if (!amt || !inst || !box || !text) return;

    const peso = n => '₱' + Number(n).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    function check(typed) {
        const a = parseFloat(amt.value);
        const i = parseFloat(inst.value);
        const over = !isNaN(a) && !isNaN(i) && i > a;
        const msg  = over
            ? 'The instalment cannot be more than the amount borrowed (' + peso(a) + '). Enter an instalment equal to or lower than it.'
            : '';

        inst.setCustomValidity(msg);

        // A message the server sent stays until one of the figures is typed
        // over; from then on this check owns the box.
        if (typed === true) box.dataset.live = '1';

        if (over || box.dataset.live) {
            inst.classList.toggle('is-invalid', over);
            text.textContent = msg;
            box.hidden = !over;
        }
        return !over;
    }

    amt.addEventListener('input', () => check(true));
    inst.addEventListener('input', () => check(true));

    // Pressing Record on a wrong pair: stop, and put the reader on the box.
    inst.form?.addEventListener('submit', e => {
        if (!check()) { e.preventDefault(); inst.focus(); }
    }, true);

    check();
})();

// ── A refused advance reopens with what was typed ──────────────────────────
// The server said no — the instalment over the amount, the limit, a date.
// The form comes back open, filled in, with the reason under the field, so
// only the figure that was wrong has to be touched.
(function () {
    const modal = document.querySelector('#advanceModal[data-reopen]');
    if (!modal || !window.bootstrap) return;

    document.getElementById('ca_emp')?.dispatchEvent(new Event('change'));
    bootstrap.Modal.getOrCreateInstance(modal).show();
})();

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

// ── Filters apply themselves ────────────────────────────────────────────────
// There is no Apply button. Picking a status, a type or a date reloads the
// list at once. A name reloads once typing pauses — on every keystroke the
// page would reload under the reader mid-word — or at once on Enter, and the
// cursor is put back at the end of what was typed, so the search reads as
// one continuous box rather than one that throws you out after each letter.
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
