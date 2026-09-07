@extends('layouts')
@section('page_title', 'Deductions & Contributions')

@section('content')
<div class="mod-page">

    @include('modules._head', [
        'title' => __('Deductions & Contributions'),
        'sub'   => __('Everything payroll may take off a worker, in one place. Each row says who owns its figure, so nothing here can quietly override payroll.'),
        'actions' => '<button type="button" class="mod-btn primary" data-bs-toggle="modal" data-bs-target="#dedModal"><i class="fas fa-plus"></i> ' . __('Add Deduction') . '</button>',
    ])

    @include('modules._flash')

    <div class="mod-note">
        <i class="fas fa-circle-info"></i>
        <div>
            {{ __('The statutory rates are set in Payroll Settings and computed by the payroll engine. They are shown here for reference and are not editable from this screen.') }}
            @if(auth()->user()?->isAdmin())
                <a href="{{ route('settings.index') }}" style="color:var(--brand);font-weight:600;">{{ __('Open Payroll Settings') }}</a>
            @endif
        </div>
    </div>

    <div class="mod-stats">
        <div class="mod-stat">
            <p class="mod-stat-label">{{ __('Outstanding Loans') }}</p>
            <p class="mod-stat-value is-warn">₱{{ number_format($ledger['loans'], 2) }}</p>
            <p class="mod-stat-sub">{{ __('collected per payroll') }}</p>
        </div>
        <div class="mod-stat">
            <p class="mod-stat-label">{{ __('Outstanding Advances') }}</p>
            <p class="mod-stat-value is-warn">₱{{ number_format($ledger['advances'], 2) }}</p>
        </div>
        @foreach(['sss' => 'SSS', 'philhealth' => 'PhilHealth', 'pagibig' => 'Pag-IBIG'] as $k => $label)
            @if(isset($rates[$k]))
                <div class="mod-stat">
                    <p class="mod-stat-label">{{ $label }} {{ __('(from settings)') }}</p>
                    <p class="mod-stat-value">{{ is_numeric($rates[$k]) ? number_format((float) $rates[$k], 2) : $rates[$k] }}</p>
                </div>
            @endif
        @endforeach
    </div>

    <div class="mod-card">
        <div class="mod-card-head">
            <h2 class="mod-card-title"><i class="fas fa-percent"></i> {{ __('Deduction Catalogue') }}</h2>
        </div>
        <div class="mod-table-wrap">
            <table class="mod-table">
                <thead>
                    <tr>
                        <th>{{ __('Deduction') }}</th>
                        <th>{{ __('Category') }}</th>
                        <th>{{ __('Figure owned by') }}</th>
                        <th>{{ __('Rule') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @forelse($types as $t)
                    <tr>
                        <td class="strong">
                            {{ $t->name }}
                            @if($t->description)<div class="mod-person-sub">{{ $t->description }}</div>@endif
                        </td>
                        <td><span class="mod-badge muted">{{ $t->category_label }}</span></td>
                        <td class="muted">{{ $t->source_label }}</td>
                        <td class="muted">{{ $t->rule_label }}</td>
                        <td>
                            <span class="mod-badge {{ $t->is_active ? 'ok' : 'muted' }}">
                                <span class="dot"></span>{{ $t->is_active ? __('Active') : __('Disabled') }}
                            </span>
                        </td>
                        <td>
                            <form method="POST" action="{{ route('deductions.toggle', $t) }}" class="mod-row-actions">
                                @csrf @method('PATCH')
                                <button class="mod-btn sm" type="submit">
                                    <i class="fas {{ $t->is_active ? 'fa-toggle-on' : 'fa-toggle-off' }}"></i>
                                    {{ $t->is_active ? __('Disable') : __('Enable') }}
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    @include('modules._empty', ['cols' => 6, 'icon' => 'fa-percent', 'title' => __('No deductions configured')])
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="dedModal" tabindex="-1" aria-hidden="true" aria-labelledby="dedTitle">
  <div class="modal-dialog modal-dialog-centered emp-dialog">
    <div class="modal-content emp-modal">
      <form method="POST" action="{{ route('deductions.store') }}">
        @csrf
        <div class="emp-head">
            <span class="emp-head-icon"><i class="fas fa-percent"></i></span>
            <div class="emp-head-text">
                <h6 class="emp-head-title" id="dedTitle">{{ __('Add Deduction') }}</h6>
                <p class="emp-head-sub">{{ __('A company deduction with a figure set here — a uniform, a tool bond.') }}</p>
            </div>
            <button type="button" class="emp-head-x" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body emp-body">
            <div class="mod-form-grid">
                <div class="emp-field">
                    <label class="ep-label" for="d_code">{{ __('Code') }} <span class="ep-req">*</span></label>
                    <input class="form-control" id="d_code" type="text" name="code" maxlength="40" required placeholder="uniform">
                </div>
                <div class="emp-field">
                    <label class="ep-label" for="d_cat">{{ __('Category') }} <span class="ep-req">*</span></label>
                    <select class="form-select" id="d_cat" name="category" required>
                        @foreach(\App\Models\DeductionType::CATEGORIES as $k => $v)
                            <option value="{{ $k }}" @selected($k === 'company')>{{ $v }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="emp-field full">
                    <label class="ep-label" for="d_name">{{ __('Name') }} <span class="ep-req">*</span></label>
                    <input class="form-control" id="d_name" type="text" name="name" maxlength="120" required>
                </div>
                <div class="emp-field">
                    <label class="ep-label" for="d_amt">{{ __('Fixed Amount') }}</label>
                    <input class="form-control" id="d_amt" type="number" step="0.01" min="0" name="amount">
                </div>
                <div class="emp-field">
                    <label class="ep-label" for="d_pct">{{ __('Or Percent of Gross') }}</label>
                    <input class="form-control" id="d_pct" type="number" step="0.001" min="0" max="100" name="percentage">
                    <span class="ep-hint">{{ __('Set one or the other, not both.') }}</span>
                </div>
                <div class="emp-field full">
                    <label class="ep-label" for="d_desc">{{ __('Description') }}</label>
                    <textarea class="form-control" id="d_desc" name="description" rows="2" style="height:auto;padding:9px 13px;"></textarea>
                </div>
            </div>
        </div>
        <div class="emp-foot">
            <p class="emp-foot-note"><span class="ep-req">*</span> {{ __('Required') }}</p>
            <button type="button" class="emp-btn-cancel" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
            <button type="submit" class="emp-btn-save"><i class="fas fa-check"></i> <span>{{ __('Add') }}</span></button>
        </div>
      </form>
    </div>
  </div>
</div>

@include('modules._kit')
@include('employees._profile_styles')
@endsection

