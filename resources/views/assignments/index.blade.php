@extends('layouts')
@section('page_title', 'Project Assignment')

@section('content')
<div class="mod-page">

    @include('modules._head', [
        'title' => __('Project Assignment'),
        'sub'   => __('Who is posted to which site, for how long, and at what rate. The kiosk still stamps each clock with the site it was taken at; this is the posting behind it.'),
        'actions' => '<button type="button" class="mod-btn primary" data-bs-toggle="modal" data-bs-target="#asgModal"><i class="fas fa-plus"></i> ' . __('New Assignment') . '</button>',
    ])

    @include('modules._flash')

    <div class="mod-stats">
        @foreach($bySite as $site)
            <div class="mod-stat">
                <p class="mod-stat-label">{{ $site->name }}</p>
                <p class="mod-stat-value">{{ $site->active_assignments }}</p>
                <p class="mod-stat-sub">{{ __('assigned') }} &middot; {{ $site->employees_count }} {{ __('on roster') }}</p>
            </div>
        @endforeach
        @if($bySite->isEmpty())
            <div class="mod-stat">
                <p class="mod-stat-label">{{ __('Sites') }}</p>
                <p class="mod-stat-value">0</p>
                <p class="mod-stat-sub">{{ __('add a site first') }}</p>
            </div>
        @endif
    </div>

    <div class="mod-card">
        <form method="GET" class="mod-filters">
            <div class="mod-filter mod-filter-grow">
                <label for="aq">{{ __('Employee') }}</label>
                <input id="aq" class="form-control" type="text" name="q" value="{{ request('q') }}" placeholder="{{ __('Search a name') }}">
            </div>
            <div class="mod-filter">
                <label for="asite">{{ __('Site') }}</label>
                <select id="asite" class="form-select" name="site_id">
                    <option value="">{{ __('All sites') }}</option>
                    @foreach($sites as $s)
                        <option value="{{ $s->id }}" @selected(request('site_id') == $s->id)>{{ $s->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="mod-filter">
                <label for="astatus">{{ __('Status') }}</label>
                <select id="astatus" class="form-select" name="status">
                    <option value="">{{ __('All') }}</option>
                    @foreach(\App\Models\ProjectAssignment::STATUSES as $k => $v)
                        <option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>
                    @endforeach
                </select>
            </div>
            <div class="mod-filter-actions">
                <button class="mod-btn primary" type="submit"><i class="fas fa-magnifying-glass"></i> {{ __('Apply') }}</button>
                <a class="mod-btn" href="{{ route('assignments.index') }}">{{ __('Reset') }}</a>
            </div>
        </form>

        <div class="mod-table-wrap">
            <table class="mod-table">
                <thead>
                    <tr>
                        <th>{{ __('Employee') }}</th>
                        <th>{{ __('Position') }}</th>
                        <th>{{ __('Site') }}</th>
                        <th class="num">{{ __('Rate') }}</th>
                        <th>{{ __('Assigned') }}</th>
                        <th>{{ __('Ends') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @forelse($assignments as $a)
                    <tr>
                        <td>@include('modules._person', ['name' => $a->employee->name ?? '—', 'sub' => $a->employment_type ?: ''])</td>
                        <td class="muted">{{ $a->position ?: '—' }}</td>
                        <td class="strong">{{ $a->site->name ?? '—' }}</td>
                        <td class="num">{{ $a->rate_label }}</td>
                        <td class="muted">{{ $a->starts_on->format('M d, Y') }}</td>
                        <td class="muted">{{ $a->ends_on?->format('M d, Y') ?? __('Open-ended') }}</td>
                        <td>
                            @php $tone = ['completed' => 'muted', 'cancelled' => 'danger'][$a->status] ?? 'ok'; @endphp
                            <span class="mod-badge {{ $tone }}"><span class="dot"></span>{{ $a->status_label }}</span>
                        </td>
                        <td>
                            @if($a->status === 'active')
                                <form method="POST" action="{{ route('assignments.end', $a) }}" class="mod-row-actions"
                                      data-confirm="{{ __('The worker stays on record; only this assignment is closed.') }}"
                                      data-confirm-title="{{ __('Close this assignment?') }}"
                                      data-confirm-label="{{ __('Close') }}"
                                      data-confirm-tone="warning">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="status" value="completed">
                                    <button class="mod-btn sm" type="submit"><i class="fas fa-flag-checkered"></i> {{ __('End') }}</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    @include('modules._empty', ['cols' => 8, 'icon' => 'fa-clipboard-list',
                        'title' => __('No assignments yet'), 'sub' => __('Post a worker to a site to see them here.')])
                @endforelse
                </tbody>
            </table>
        </div>
        @if($assignments->hasPages())<div class="mod-pager">{{ $assignments->links() }}</div>@endif
    </div>
</div>

<div class="modal fade" id="asgModal" tabindex="-1" aria-hidden="true" aria-labelledby="asgModalTitle">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable emp-dialog">
    <div class="modal-content emp-modal">
      <form method="POST" action="{{ route('assignments.store') }}">
        @csrf
        <div class="emp-head">
            <span class="emp-head-icon"><i class="fas fa-clipboard-list"></i></span>
            <div class="emp-head-text">
                <h6 class="emp-head-title" id="asgModalTitle">{{ __('New Assignment') }}</h6>
                <p class="emp-head-sub">{{ __('Posts a worker to a site for a period, at an agreed rate.') }}</p>
            </div>
            <button type="button" class="emp-head-x" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body emp-body">
            <div class="mod-form-grid">
                <div class="emp-field full">
                    <label class="ep-label" for="as_emp">{{ __('Employee') }} <span class="ep-req">*</span></label>
                    <select class="form-select" id="as_emp" name="employee_id" required>
                        <option value="">{{ __('— Select —') }}</option>
                        @foreach($employees as $e)
                            <option value="{{ $e->id }}" data-position="{{ $e->position }}" data-rate="{{ round($e->rate_per_hour * 8, 2) }}">
                                {{ $e->name }}@if($e->position) — {{ $e->position }}@endif
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="emp-field">
                    <label class="ep-label" for="as_site">{{ __('Site / Project') }} <span class="ep-req">*</span></label>
                    <select class="form-select" id="as_site" name="site_id" required>
                        <option value="">{{ __('— Select —') }}</option>
                        @foreach($sites as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
                    </select>
                </div>
                <div class="emp-field">
                    <label class="ep-label" for="as_pos">{{ __('Position') }}</label>
                    <input class="form-control" id="as_pos" type="text" name="position" maxlength="100" placeholder="{{ __('From the worker') }}">
                </div>
                <div class="emp-field">
                    <label class="ep-label" for="as_rate">{{ __('Rate') }} <span class="ep-req">*</span></label>
                    <input class="form-control" id="as_rate" type="number" step="0.01" min="0" name="rate" required>
                </div>
                <div class="emp-field">
                    <label class="ep-label" for="as_rtype">{{ __('Rate Basis') }} <span class="ep-req">*</span></label>
                    <select class="form-select" id="as_rtype" name="rate_type" required>
                        <option value="daily">{{ __('Per day') }}</option>
                        <option value="hourly">{{ __('Per hour') }}</option>
                    </select>
                </div>
                <div class="emp-field">
                    <label class="ep-label" for="as_from">{{ __('Assignment Date') }} <span class="ep-req">*</span></label>
                    <input class="form-control" id="as_from" type="date" name="starts_on" value="{{ now()->toDateString() }}" required>
                </div>
                <div class="emp-field">
                    <label class="ep-label" for="as_to">{{ __('End Date') }}</label>
                    <input class="form-control" id="as_to" type="date" name="ends_on">
                    <span class="ep-hint">{{ __('Blank means open-ended.') }}</span>
                </div>
                <div class="emp-field full">
                    <label class="ep-label" style="display:flex;align-items:center;gap:8px;text-transform:none;letter-spacing:0;font-size:13px;">
                        <input type="checkbox" name="move_employee" value="1" checked>
                        {{ __('Also move this worker\'s current site to match') }}
                    </label>
                    <span class="ep-hint">{{ __('Only the site changes. Rate, labor type and shift are left alone.') }}</span>
                </div>
                <div class="emp-field full">
                    <label class="ep-label" for="as_notes">{{ __('Notes') }}</label>
                    <textarea class="form-control" id="as_notes" name="notes" rows="2" style="height:auto;padding:9px 13px;"></textarea>
                </div>
            </div>
        </div>
        <div class="emp-foot">
            <p class="emp-foot-note"><span class="ep-req">*</span> {{ __('Required') }}</p>
            <button type="button" class="emp-btn-cancel" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
            <button type="submit" class="emp-btn-save"><i class="fas fa-check"></i> <span>{{ __('Assign') }}</span></button>
        </div>
      </form>
    </div>
  </div>
</div>

@include('modules._kit')
@include('employees._profile_styles')
@include('employees._modal_styles')

@push('scripts')
<script>
// Fill position and daily rate from the chosen worker, so the common case is
// one click. Both stay editable: an assignment may be at a different rate.
(function () {
    var emp = document.getElementById('as_emp'),
        pos = document.getElementById('as_pos'),
        rate = document.getElementById('as_rate');
    if (!emp) return;
    emp.addEventListener('change', function () {
        var o = emp.options[emp.selectedIndex];
        if (!o || !o.value) return;
        if (pos && !pos.value) pos.value = o.dataset.position || '';
        if (rate && !rate.value) rate.value = o.dataset.rate || '';
    });
})();
</script>
@endpush
@endsection

