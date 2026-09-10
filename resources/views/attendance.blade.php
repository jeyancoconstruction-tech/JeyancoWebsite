@extends('layouts')

@section('page_title', 'Attendance Monitoring')

@push('styles')
<style>
/* ── Global filter bar ─────────────────────────────────────────────────── */
.att-filters {
    display:flex; align-items:flex-end; gap:14px; flex-wrap:wrap;
    padding:13px 16px; margin-bottom:16px;
    background:var(--bg-surface,#fff); border:1px solid var(--border,#e4e7ec);
    border-radius:10px;
}
.att-filters-label {
    display:inline-flex; align-items:center; gap:7px; padding-bottom:8px;
    font-size:.72rem; font-weight:700; letter-spacing:.04em; text-transform:uppercase;
    color:var(--text-muted,#667085);
}
.att-filters-label i { font-size:.8rem; color:var(--brand,#1668dc); }
.att-filter { display:flex; flex-direction:column; gap:4px; min-width:0; }
.att-filter label {
    font-size:.7rem; font-weight:700; letter-spacing:.03em; text-transform:uppercase;
    color:var(--text-secondary,#344054);
}
.att-filter-input {
    min-width:180px; height:36px; padding:0 30px 0 11px;
    font-size:.86rem; color:var(--text-primary,#101828);
    background:var(--bg-elevated,#fff); border:1px solid var(--border-md,#d0d5dd);
    border-radius:8px; cursor:pointer;
    appearance:none;
    background-image:url("data:image/svg+xml;charset=utf-8,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23667085'%3E%3Cpath d='M4.5 6.5 8 10l3.5-3.5z'/%3E%3C/svg%3E");
    background-repeat:no-repeat; background-position:right 9px center; background-size:15px;
}
.att-filter-input:focus {
    outline:none; border-color:var(--brand,#1668dc);
    box-shadow:0 0 0 3px color-mix(in srgb, var(--brand,#1668dc) 18%, transparent);
}
.att-filter-apply, .att-filter-clear {
    height:36px; padding:0 15px; border-radius:8px; cursor:pointer;
    display:inline-flex; align-items:center; gap:6px;
    font-size:.82rem; font-weight:600; text-decoration:none;
    background:var(--bg-subtle,#f8f9fb); border:1px solid var(--border-md,#d0d5dd);
    color:var(--text-secondary,#344054);
}
.att-filter-apply:hover, .att-filter-clear:hover {
    background:var(--bg-elevated,#fff); color:var(--text-primary,#101828);
}
.att-filter-note {
    padding-bottom:9px; font-size:.78rem; color:var(--text-muted,#667085);
}
@media (max-width:620px) {
    .att-filters { gap:10px; }
    .att-filter, .att-filter-input { width:100%; min-width:0; }
}

.att-site { display:inline-flex; align-items:center; gap:5px; font-size:12px; font-weight:600;
    color:#0f766e; background:#f0fdfa; border:1px solid #ccfbf1; border-radius:8px; padding:2px 8px; white-space:nowrap; }
.att-site i { font-size:10px; }

    .att-tabs { border-bottom: 2px solid #e5e7eb; gap: 4px; margin-bottom: 18px; }
    .att-tabs .nav-link { color: #64748b; border: none; font-weight: 700; padding: 10px 18px; }
    .att-tabs .nav-link:hover { color: #3b82f6; }
    .att-tabs .nav-link.active { color: #3b82f6; border-bottom: 3px solid #3b82f6; background: none; }
    [data-bs-theme="dark"] .att-tabs { border-bottom-color: #283449; }
    [data-bs-theme="dark"] .att-tabs .nav-link { color: #9fb0c7; }
    [data-bs-theme="dark"] .att-tabs .nav-link.active { color: #93c5fd; border-bottom-color: #93c5fd; }

    /* History toolbar */
    .att-hist-toolbar { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px; margin-bottom:12px; }
    .att-mark-btn   { background:#f1f5f9; color:#475569; border:1px solid #e2e8f0; font-size:12.5px; font-weight:700; border-radius:7px; padding:5px 12px; cursor:pointer; transition:background .15s; }
    .att-mark-btn:hover   { background:#e2e8f0; }
    .att-cancel-btn { background:#f1f5f9; color:#475569; border:1px solid #e2e8f0; font-size:12.5px; font-weight:700; border-radius:7px; padding:5px 12px; cursor:pointer; }
    .att-del-sel-btn { background:#fef2f2; color:#dc2626; border:1px solid #fecaca; font-size:12.5px; font-weight:700; border-radius:7px; padding:5px 12px; cursor:pointer; transition:background .15s; }
    .att-del-sel-btn:not(:disabled):hover { background:#fee2e2; }
    .att-del-sel-btn:disabled { opacity:.5; cursor:not-allowed; }
    .att-del-all-btn { background:#dc2626; color:#fff; border:none; font-size:12.5px; font-weight:700; border-radius:7px; padding:5px 14px; cursor:pointer; transition:background .15s; }
    .att-del-all-btn:hover { background:#b91c1c; }
    [data-bs-theme="dark"] .att-mark-btn, [data-bs-theme="dark"] .att-cancel-btn { background:#1c2740; color:#9fb0c7; border-color:#283449; }
    [data-bs-theme="dark"] .att-mark-btn:hover { background:#283449; }
    [data-bs-theme="dark"] .att-del-sel-btn { background:#450a0a; color:#fca5a5; border-color:#7f1d1d; }
    [data-bs-theme="dark"] .att-del-sel-btn:not(:disabled):hover { background:#7f1d1d; }

    /* Checkbox column — hidden until mark mode */
    .att-check-col { display:none; width:36px; text-align:center; }
    .att-check-col input[type=checkbox] { cursor:pointer; width:15px; height:15px; accent-color:#dc2626; }
    body.att-mark-mode .att-check-col { display:table-cell; }
    body.att-mark-mode tr.att-marked  { background:rgba(220,38,38,.07); }
    [data-bs-theme="dark"] body.att-mark-mode tr.att-marked { background:rgba(220,38,38,.13); }
</style>
@endpush

@section('content')
@php
    // status key -> [label, css class]
    $statusBadge = function ($status) {
        return match ($status) {
            'present' => ['Present', 'badge-present'],
            'active'  => ['Active', 'badge-active'],
            'invalid' => ['Invalid Attendance', 'badge-invalid'],
            default   => ['Absent', 'badge-absent'],
        };
    };
@endphp

<div class="attendance-container p-4">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="attendance-title mb-0">{{ __('Attendance Monitoring') }}</h3>
        <span class="text-muted small"><i class="fas fa-calendar-day me-1"></i>{{ now()->format('l, m/d/Y') }}</span>
    </div>

    {{-- ── Global filters ──────────────────────────────────────────────────
         Above the cards on purpose: they govern the whole page, not one tab.
         Site and Shift are applied in the controller to both tables and all
         three counts, so the cards always describe the rows underneath them.

         A GET form rather than JavaScript row-hiding: History is paginated
         fifteen at a time, so hiding rows in the browser would filter the
         page you can see and quietly ignore the rest. --}}
    <form method="GET" action="{{ route('attendance') }}" class="att-filters" id="attFilters">
        <span class="att-filters-label"><i class="fas fa-filter"></i>{{ __('Filters') }}</span>

        <div class="att-filter">
            <label for="attSite">{{ __('Site') }}</label>
            <select name="site" id="attSite" class="att-filter-input">
                <option value="">{{ __('All Sites') }}</option>
                @foreach($sites as $s)
                    <option value="{{ $s->id }}" @selected($siteId === $s->id)>{{ $s->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="att-filter">
            <label for="attShift">{{ __('Shift') }}</label>
            <select name="shift" id="attShift" class="att-filter-input">
                <option value="">{{ __('All Shifts') }}</option>
                @foreach($shifts as $sh)
                    <option value="{{ $sh->id }}" @selected($shiftId === $sh->id)>{{ $sh->name }}</option>
                @endforeach
            </select>
        </div>

        {{-- The tab rides along so changing a filter while reading History
             does not drop you back on Today's Attendance. --}}
        <input type="hidden" name="tab" id="attTabField" value="{{ request('tab') === 'history' ? 'history' : 'today' }}">

        {{-- Submits on change; this is for anyone without JavaScript. --}}
        <noscript><button type="submit" class="att-filter-apply">{{ __('Apply') }}</button></noscript>

        @if($siteId || $shiftId)
            <a href="{{ route('attendance', ['tab' => request('tab')]) }}" class="att-filter-clear">
                <i class="fas fa-xmark"></i>{{ __('Clear') }}
            </a>
            <span class="att-filter-note">{{ __('Showing a filtered view — the counts below follow it.') }}</span>
        @endif
    </form>

    <!-- STAT CARDS -->
    <div class="analytics-row">
        <div class="analytics-card">
            <h5>{{ __('Present Today') }}</h5>
            <div class="value">{{ $presentToday }}</div>
            <small class="text-muted">{{ __('Employees clocked in today') }}</small>
        </div>
        <div class="analytics-card">
            <h5>{{ __('Currently Clocked In') }}</h5>
            <div class="value">{{ $clockedIn }}</div>
            <small class="text-muted">{{ __('Still on-site (no time-out yet)') }}</small>
        </div>
        <div class="analytics-card flagged">
            <h5>{{ __('Invalid Attendance') }}</h5>
            <div class="value">{{ $invalidCount }}</div>
            <small class="text-muted">{{ __('Missed sign-out on previous days') }}</small>
        </div>
    </div>

    <!-- TABS -->
    @php $openTab = request('tab') === 'history' ? 'history' : 'today'; @endphp
    <ul class="nav nav-tabs att-tabs" role="tablist">
        <li class="nav-item">
            <button class="nav-link {{ $openTab === 'today' ? 'active' : '' }}" data-bs-toggle="tab"
                    data-bs-target="#att-today" type="button" data-tab="today">
                <i class="fas fa-calendar-day me-1"></i> {{ __('Today\'s Attendance') }}
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link {{ $openTab === 'history' ? 'active' : '' }}" data-bs-toggle="tab"
                    data-bs-target="#att-history" type="button" data-tab="history">
                <i class="fas fa-clock-rotate-left me-1"></i> {{ __('History') }}
            </button>
        </li>
    </ul>

    <div class="tab-content">

        <!-- ===== TODAY ===== -->
        <div class="tab-pane fade {{ $openTab === 'today' ? 'show active' : '' }}" id="att-today" role="tabpanel">
            <div class="table-card">
                <div class="table-responsive">
                <table class="attendance-table w-100">
                    <thead>
                        <tr>
                            <th class="p-2 text-start">{{ __('Employee') }}</th>
                            <th class="p-2 text-start">{{ __('Site') }}</th>
                            <th class="p-2 text-start">{{ __('Session') }}</th>
                            <th class="p-2 text-start">{{ __('Time In / Out') }}</th>
                            <th class="p-2 text-center">{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($todayAttendances as $record)
                        @php
                            [$label, $cls] = $statusBadge($record->status);
                            $isHoliday = in_array(\Carbon\Carbon::parse($record->date)->toDateString(), $holidayDates ?? []);
                        @endphp
                        <tr>
                            <td class="fw-bold p-2">{{ $record->employee->name ?? 'Unknown' }}</td>
                            <td class="p-2">
                                @if($record->site)
                                    <span class="att-site"><i class="fas fa-map-marker-alt"></i> {{ $record->site->name }}</span>
                                @else
                                    <span class="text-muted">&mdash;</span>
                                @endif
                            </td>
                            <td class="p-2">
                                <span class="session-label {{ $record->session == 'AM' ? 'badge-am' : 'badge-pm' }}">{{ $record->session }}</span>
                            </td>
                            <td class="p-2">
                                {{ date('h:i A', strtotime($record->time_in)) }}
                                &ndash;
                                {{ $record->time_out ? date('h:i A', strtotime($record->time_out)) : '--' }}
                            </td>
                            <td class="text-center p-2">
                                <span class="badge-attendance {{ $cls }}">{{ $label }}</span>
                                @if($isHoliday)
                                    <span class="badge-attendance badge-holiday ms-1" title="{{ __('Holiday (Settings)') }}"><i class="fas fa-star me-1"></i>{{ __('Holiday') }}</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted">
                                <i class="fas fa-user-clock mb-2 d-block" style="font-size: 1.75rem; opacity: 0.3;"></i>
                                No employees have clocked in today yet.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
                </div>
            </div>
        </div>

        <!-- ===== HISTORY ===== -->
        <div class="tab-pane fade {{ $openTab === 'history' ? 'show active' : '' }}" id="att-history" role="tabpanel">

            {{-- Toolbar --}}
            <div class="att-hist-toolbar">
                <div class="d-flex gap-2 align-items-center flex-wrap">
                    <button id="markModeBtn" type="button" class="att-mark-btn">
                        <i class="fas fa-check-square me-1"></i>{{ __('Mark for Deletion') }}
                    </button>
                    <button id="deleteSelectedBtn" type="button" class="att-del-sel-btn" style="display:none;" disabled>
                        <i class="fas fa-trash me-1"></i>{{ __('Delete Selected (') }}<span id="selCount">0</span>)
                    </button>
                    <button id="cancelMarkBtn" type="button" class="att-cancel-btn" style="display:none;">
                        Cancel
                    </button>
                </div>
                <button id="deleteAllBtn" type="button" class="att-del-all-btn">
                    <i class="fas fa-trash-alt me-1"></i>{{ __('Delete All') }}
                </button>
            </div>

            <div class="table-card">
                <div class="table-responsive">
                <table class="attendance-table w-100" id="historyTable">
                    <thead>
                        <tr>
                            <th class="att-check-col p-2">
                                <input type="checkbox" id="selectAllChk" title="{{ __('Select all on this page') }}">
                            </th>
                            <th class="p-2 text-start">{{ __('Employee') }}</th>
                            <th class="p-2 text-start">{{ __('Site') }}</th>
                            <th class="p-2 text-start">{{ __('Date') }}</th>
                            <th class="p-2 text-start">{{ __('Session') }}</th>
                            <th class="p-2 text-start">{{ __('Time In / Out') }}</th>
                            <th class="p-2 text-center">{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($historyAttendances as $record)
                        @php
                            [$label, $cls] = $statusBadge($record->status);
                            $isHoliday = in_array(\Carbon\Carbon::parse($record->date)->toDateString(), $holidayDates ?? []);
                        @endphp
                        <tr data-id="{{ $record->id }}">
                            <td class="att-check-col p-2">
                                <input type="checkbox" class="row-chk" value="{{ $record->id }}">
                            </td>
                            <td class="fw-bold p-2">{{ $record->employee->name ?? 'Unknown' }}</td>
                            <td class="p-2">
                                @if($record->site)
                                    <span class="att-site"><i class="fas fa-map-marker-alt"></i> {{ $record->site->name }}</span>
                                @else
                                    <span class="text-muted">&mdash;</span>
                                @endif
                            </td>
                            <td class="p-2">{{ \Carbon\Carbon::parse($record->date)->format('m/d/Y') }}</td>
                            <td class="p-2">
                                <span class="session-label {{ $record->session == 'AM' ? 'badge-am' : 'badge-pm' }}">{{ $record->session }}</span>
                            </td>
                            <td class="p-2">
                                @if($record->time_in)
                                    {{ date('h:i A', strtotime($record->time_in)) }}
                                    &ndash;
                                    {{ $record->time_out ? date('h:i A', strtotime($record->time_out)) : '--' }}
                                @else
                                    <span class="text-muted fst-italic">{{ __('No time-in') }}</span>
                                @endif
                            </td>
                            <td class="text-center p-2">
                                <span class="badge-attendance {{ $cls }}">{{ $label }}</span>
                                @if($isHoliday)
                                    <span class="badge-attendance badge-holiday ms-1" title="{{ __('Holiday (Settings)') }}"><i class="fas fa-star me-1"></i>{{ __('Holiday') }}</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">{{ __('No previous attendance records.') }}</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
                </div>

                <div class="mt-3">
                    {{-- appends(): these links live in the History pane, so
                         page 2 has to carry the tab as well as the filters or
                         it lands on Today's Attendance. --}}
                    {{ $historyAttendances->appends(['tab' => 'history'])->links() }}
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
// ── Global filters ───────────────────────────────────────────────────────────
(function () {
    const form = document.getElementById('attFilters');
    if (!form) return;

    const tabField = document.getElementById('attTabField');

    // Changing either dropdown reloads the page with it applied. The noscript
    // Apply button covers the case where this never runs.
    form.querySelectorAll('select').forEach(sel => {
        sel.addEventListener('change', () => form.submit());
    });

    // Keep the hidden field and the address bar in step with the open tab, so
    // changing a filter while reading History comes back to History — and so
    // does a refresh.
    document.querySelectorAll('.att-tabs [data-tab]').forEach(btn => {
        btn.addEventListener('shown.bs.tab', () => {
            const tab = btn.dataset.tab;
            if (tabField) tabField.value = tab;

            const url = new URL(window.location);
            if (tab === 'history') url.searchParams.set('tab', 'history');
            else                   url.searchParams.delete('tab');
            history.replaceState(null, '', url);
        });
    });
})();

    if (typeof lucide !== 'undefined') lucide.createIcons();

    (function () {
        const markBtn      = document.getElementById('markModeBtn');
        const cancelBtn    = document.getElementById('cancelMarkBtn');
        const delSelBtn    = document.getElementById('deleteSelectedBtn');
        const delAllBtn    = document.getElementById('deleteAllBtn');
        const selCountEl   = document.getElementById('selCount');
        const selectAllChk = document.getElementById('selectAllChk');

        if (!markBtn) return; // history tab might not be in DOM on this page load

        function getChecked() {
            return [...document.querySelectorAll('#historyTable .row-chk:checked')];
        }

        function updateSelCount() {
            const n = getChecked().length;
            selCountEl.textContent = n;
            delSelBtn.disabled = n === 0;
        }

        function enterMarkMode() {
            document.body.classList.add('att-mark-mode');
            markBtn.style.display   = 'none';
            cancelBtn.style.display = '';
            delSelBtn.style.display = '';
            updateSelCount();
        }

        function exitMarkMode() {
            document.body.classList.remove('att-mark-mode');
            markBtn.style.display   = '';
            cancelBtn.style.display = 'none';
            delSelBtn.style.display = 'none';
            // uncheck everything
            document.querySelectorAll('#historyTable .row-chk').forEach(c => c.checked = false);
            if (selectAllChk) selectAllChk.checked = false;
            document.querySelectorAll('#historyTable tbody tr').forEach(r => r.classList.remove('att-marked'));
            updateSelCount();
        }

        markBtn.addEventListener('click', enterMarkMode);
        cancelBtn.addEventListener('click', exitMarkMode);

        // Row checkbox change
        document.addEventListener('change', function (e) {
            if (e.target.classList.contains('row-chk')) {
                const row = e.target.closest('tr');
                if (row) row.classList.toggle('att-marked', e.target.checked);
                updateSelCount();
                if (selectAllChk) {
                    const all = document.querySelectorAll('#historyTable .row-chk');
                    selectAllChk.checked = all.length > 0 && all.length === getChecked().length;
                }
            }
        });

        // Select-all checkbox
        if (selectAllChk) {
            selectAllChk.addEventListener('change', function () {
                document.querySelectorAll('#historyTable .row-chk').forEach(c => {
                    c.checked = this.checked;
                    const row = c.closest('tr');
                    if (row) row.classList.toggle('att-marked', this.checked);
                });
                updateSelCount();
            });
        }

        // Click row (anywhere except the checkbox cell) to toggle in mark mode
        document.getElementById('historyTable')?.addEventListener('click', function (e) {
            if (!document.body.classList.contains('att-mark-mode')) return;
            const td = e.target.closest('td');
            if (!td || td.classList.contains('att-check-col')) return;
            const row = td.closest('tr[data-id]');
            if (!row) return;
            const chk = row.querySelector('.row-chk');
            if (chk) { chk.checked = !chk.checked; chk.dispatchEvent(new Event('change', { bubbles: true })); }
        });

        async function doDelete(url, body, successMsg) {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
            try {
                const res  = await fetch(url, {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                    body: body ? JSON.stringify(body) : undefined,
                });
                const data = await res.json();
                if (data.success) {
                    // Parked, not shown: location.reload() is about to take
                    // this page away, and with it the toast.
                    Notify.afterReload('success', successMsg.replace('{n}', data.deleted));
                    location.reload();
                } else {
                    Notify.error(data.message || 'Something went wrong.');
                }
            } catch (err) {
                Notify.error('Request failed: ' + err.message);
            }
        }

        delSelBtn.addEventListener('click', async function () {
            const ids = getChecked().map(c => c.value);
            // Nothing ticked used to be a silent no-op, which reads as a
            // broken button rather than as an empty selection.
            if (!ids.length) { Notify.warning('Tick the records you want to delete first.'); return; }

            const ok = await Notify.confirm({
                title:        'Delete selected records?',
                message:      `${ids.length} attendance record(s) will be deleted. This cannot be undone.`,
                confirmLabel: 'Delete',
                tone:         'danger',
            });
            if (!ok) return;
            doDelete('{{ route("attendance.history.bulk-delete") }}', { ids }, 'Deleted {n} record(s).');
        });

        delAllBtn.addEventListener('click', async function () {
            const ok = await Notify.confirm({
                title:        'Delete ALL history?',
                message:      'Every attendance record in the history is deleted. This cannot be undone.',
                confirmLabel: 'Delete everything',
                tone:         'danger',
            });
            if (!ok) return;
            doDelete('{{ route("attendance.history.delete-all") }}', null, 'Deleted {n} record(s).');
        });
    })();
</script>
@endpush

@endsection
