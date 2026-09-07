@extends('layouts')
@section('page_title', 'Loans & Advances')

@section('content')
<div class="mod-page">

    @include('modules._head', [
        'title' => __('Loans & Advances'),
        'sub'   => __('Sums issued to a worker and collected back over several payrolls. Payroll Processing takes the instalment due; the balance moves only when a run is finalised.'),
        'actions' => '<button type="button" class="mod-btn primary" data-bs-toggle="modal" data-bs-target="#loanModal"><i class="fas fa-plus"></i> ' . __('New Loan / Advance') . '</button>',
    ])

    @include('modules._flash')

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
            <div class="mod-filter mod-filter-grow">
                <label for="fq">{{ __('Employee') }}</label>
                <input id="fq" class="form-control" type="text" name="q" value="{{ request('q') }}" placeholder="{{ __('Search a name') }}">
            </div>
            <div class="mod-filter">
                <label for="ftype">{{ __('Type') }}</label>
                <select id="ftype" class="form-select" name="type">
                    <option value="">{{ __('All') }}</option>
                    @foreach(\App\Models\Loan::TYPES as $k => $v)
                        <option value="{{ $k }}" @selected(request('type') === $k)>{{ $v }}</option>
                    @endforeach
                </select>
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
                <a class="mod-btn" href="{{ route('loans.index') }}">{{ __('Reset') }}</a>
            </div>
        </form>

        <div class="mod-table-wrap">
            <table class="mod-table">
                <thead>
                    <tr>
                        <th>{{ __('Employee') }}</th>
                        <th>{{ __('Type') }}</th>
                        <th class="num">{{ __('Principal') }}</th>
                        <th class="num">{{ __('Balance') }}</th>
                        <th class="num">{{ __('Instalment') }}</th>
                        <th>{{ __('Schedule') }}</th>
                        <th>{{ __('Issued') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @forelse($loans as $loan)
                    <tr>
                        <td>@include('modules._person', ['name' => $loan->employee->name ?? '—', 'sub' => $loan->reference ?: ''])</td>
                        <td class="muted">{{ $loan->type_label }}</td>
                        <td class="num">₱{{ number_format($loan->principal, 2) }}</td>
                        <td class="num strong">
                            ₱{{ number_format($loan->balance, 2) }}
                            <div class="mod-person-sub">{{ $loan->progress }}% {{ __('paid') }}</div>
                        </td>
                        <td class="num">₱{{ number_format($loan->installment, 2) }}</td>
                        <td class="muted">{{ $loan->schedule === 'monthly' ? __('Monthly') : __('Per payroll') }}</td>
                        <td class="muted">{{ $loan->issued_on->format('M d, Y') }}</td>
                        <td>
                            @php $tone = ['paid' => 'ok', 'cancelled' => 'muted', 'on_hold' => 'warn'][$loan->status] ?? 'info'; @endphp
                            <span class="mod-badge {{ $tone }}"><span class="dot"></span>{{ $loan->status_label }}</span>
                        </td>
                        <td>
                            @if($loan->status === 'active')
                                <div class="mod-row-actions">
                                    <button class="mod-btn sm" type="button" data-bs-toggle="modal"
                                            data-bs-target="#payModal{{ $loan->id }}">
                                        <i class="fas fa-peso-sign"></i> {{ __('Record payment') }}
                                    </button>
                                </div>
                            @endif
                        </td>
                    </tr>
                @empty
                    @include('modules._empty', ['cols' => 9, 'icon' => 'fa-hand-holding-dollar',
                        'title' => __('No loans recorded'), 'sub' => __('Issued loans and advances appear here with their running balance.')])
                @endforelse
                </tbody>
            </table>
        </div>
        @if($loans->hasPages())<div class="mod-pager">{{ $loans->links() }}</div>@endif
    </div>
</div>

{{-- A record-payment dialog per active loan: the amount is capped at that
     loan's own balance, which a single shared form could not enforce. --}}
@foreach($loans as $loan)
    @if($loan->status === 'active')
    <div class="modal fade" id="payModal{{ $loan->id }}" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered emp-dialog" style="max-width:460px;">
        <div class="modal-content emp-modal">
          <form method="POST" action="{{ route('loans.payment', $loan) }}">
            @csrf
            <div class="emp-head">
                <span class="emp-head-icon"><i class="fas fa-peso-sign"></i></span>
                <div class="emp-head-text">
                    <h6 class="emp-head-title">{{ __('Record Payment') }}</h6>
                    <p class="emp-head-sub">{{ $loan->employee->name ?? '' }} &middot; {{ __('balance') }} ₱{{ number_format($loan->balance, 2) }}</p>
                </div>
                <button type="button" class="emp-head-x" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body emp-body">
                <div class="mod-note">
                    <i class="fas fa-circle-info"></i>
                    <div>{{ __('For a payment made outside payroll. Payroll posts its own collections when a run is finalised.') }}</div>
                </div>
                <div class="emp-field">
                    <label class="ep-label" for="amt{{ $loan->id }}">{{ __('Amount') }} <span class="ep-req">*</span></label>
                    <input class="form-control" id="amt{{ $loan->id }}" type="number" step="0.01" min="0.01"
                           max="{{ $loan->balance }}" name="amount" required>
                </div>
                <div class="emp-field">
                    <label class="ep-label" for="don{{ $loan->id }}">{{ __('Date') }} <span class="ep-req">*</span></label>
                    <input class="form-control" id="don{{ $loan->id }}" type="date" name="deducted_on" value="{{ now()->toDateString() }}" required>
                </div>
                <div class="emp-field">
                    <label class="ep-label" for="nt{{ $loan->id }}">{{ __('Note') }}</label>
                    <input class="form-control" id="nt{{ $loan->id }}" type="text" name="note" maxlength="255">
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

{{-- ── New loan ────────────────────────────────────────────────────────── --}}
<div class="modal fade" id="loanModal" tabindex="-1" aria-hidden="true" aria-labelledby="loanModalTitle">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable emp-dialog">
    <div class="modal-content emp-modal">
      <form method="POST" action="{{ route('loans.store') }}">
        @csrf
        <div class="emp-head">
            <span class="emp-head-icon"><i class="fas fa-hand-holding-dollar"></i></span>
            <div class="emp-head-text">
                <h6 class="emp-head-title" id="loanModalTitle">{{ __('New Loan or Advance') }}</h6>
                <p class="emp-head-sub">{{ __('The balance starts at the full amount and falls as payroll collects.') }}</p>
            </div>
            <button type="button" class="emp-head-x" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body emp-body">
            <div class="mod-form-grid">
                <div class="emp-field full">
                    <label class="ep-label" for="ln_emp">{{ __('Employee') }} <span class="ep-req">*</span></label>
                    <select class="form-select" id="ln_emp" name="employee_id" required>
                        <option value="">{{ __('— Select —') }}</option>
                        @foreach($employees as $e)<option value="{{ $e->id }}">{{ $e->name }}</option>@endforeach
                    </select>
                </div>
                <div class="emp-field">
                    <label class="ep-label" for="ln_type">{{ __('Type') }} <span class="ep-req">*</span></label>
                    <select class="form-select" id="ln_type" name="type" required>
                        @foreach(\App\Models\Loan::TYPES as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
                    </select>
                </div>
                <div class="emp-field">
                    <label class="ep-label" for="ln_ref">{{ __('Reference') }}</label>
                    <input class="form-control" id="ln_ref" type="text" name="reference" maxlength="40">
                </div>
                <div class="emp-field">
                    <label class="ep-label" for="ln_amt">{{ __('Amount') }} <span class="ep-req">*</span></label>
                    <input class="form-control" id="ln_amt" type="number" step="0.01" min="1" name="principal" required>
                </div>
                <div class="emp-field">
                    <label class="ep-label" for="ln_inst">{{ __('Instalment') }} <span class="ep-req">*</span></label>
                    <input class="form-control" id="ln_inst" type="number" step="0.01" min="1" name="installment" required>
                    <span class="ep-hint">{{ __('Taken each payroll until the balance is nil.') }}</span>
                </div>
                <div class="emp-field">
                    <label class="ep-label" for="ln_sched">{{ __('Schedule') }} <span class="ep-req">*</span></label>
                    <select class="form-select" id="ln_sched" name="schedule" required>
                        <option value="per_payroll">{{ __('Every payroll') }}</option>
                        <option value="monthly">{{ __('Monthly') }}</option>
                    </select>
                </div>
                <div class="emp-field">
                    <label class="ep-label" for="ln_issued">{{ __('Date Issued') }} <span class="ep-req">*</span></label>
                    <input class="form-control" id="ln_issued" type="date" name="issued_on" value="{{ now()->toDateString() }}" required>
                </div>
                <div class="emp-field">
                    <label class="ep-label" for="ln_starts">{{ __('Start Collecting') }}</label>
                    <input class="form-control" id="ln_starts" type="date" name="starts_on">
                    <span class="ep-hint">{{ __('Blank collects from the next payroll.') }}</span>
                </div>
                <div class="emp-field full">
                    <label class="ep-label" for="ln_notes">{{ __('Notes') }}</label>
                    <textarea class="form-control" id="ln_notes" name="notes" rows="2" style="height:auto;padding:9px 13px;"></textarea>
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

@include('modules._kit')
@include('employees._profile_styles')
@include('employees._modal_styles')
@endsection

