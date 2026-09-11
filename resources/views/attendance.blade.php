@extends('layouts')

@section('page_title', 'Attendance Monitoring')

@push('styles')
<style>
/* ── Stat cards ───────────────────────────────────────────────────────────
   An accent edge carries the meaning — brand, green, amber — instead of a
   filled panel, so three of them side by side read as one row rather than as
   three competing blocks. Tokens throughout: one block, both themes. */
.att-stats {
    display:grid; grid-template-columns:repeat(auto-fit, minmax(190px, 1fr));
    gap:12px; margin-bottom:20px;
}
.att-stat {
    padding:15px 18px;
    background:var(--bg-subtle,#f8f9fb);
    border-left:3px solid var(--border-md,#d0d5dd);
    border-radius:0 12px 12px 0;
}
.att-stat-head {
    display:flex; align-items:center; gap:8px; margin-bottom:9px;
    font-size:.72rem; font-weight:600; letter-spacing:.05em; text-transform:uppercase;
    color:var(--text-secondary,#344054);
}
.att-stat-head i { font-size:1rem; }
.att-stat-value {
    font-size:1.75rem; font-weight:600; line-height:1;
    color:var(--text-primary,#101828); font-variant-numeric:tabular-nums;
}
.att-stat-sub { margin-top:5px; font-size:.75rem; color:var(--text-muted,#667085); }
.att-stat-brand   { border-left-color:var(--brand,#1668dc); }
.att-stat-success { border-left-color:var(--success,#027a48); }
.att-stat-warning { border-left-color:var(--warning,#b54708); }
.att-stat-brand   .att-stat-head i { color:var(--brand,#1668dc); }
.att-stat-success .att-stat-head i { color:var(--success,#027a48); }
.att-stat-warning .att-stat-head i { color:var(--warning,#b54708); }

/* ── Control row ──────────────────────────────────────────────────────── */
.att-controls {
    display:flex; align-items:center; justify-content:space-between;
    gap:10px; flex-wrap:wrap; margin-bottom:14px;
}
.att-controls-left, .att-controls-right {
    display:flex; align-items:center; gap:10px; flex-wrap:wrap; margin:0;
}
.att-controls-right[hidden] { display:none; }

/* Site: a soft pill with the select sitting inside it, borderless, so the
   pill is the control rather than a label next to one. */
.att-pick {
    display:flex; align-items:center; gap:8px; height:36px;
    padding:0 6px 0 12px; border-radius:9px;
    background:var(--bg-subtle,#f8f9fb); border:1px solid var(--border,#e4e7ec);
}
.att-pick i { font-size:.85rem; color:var(--text-muted,#667085); }
.att-pick select {
    height:34px; border:none; background-color:transparent; box-shadow:none;
    padding-right:4px; font-size:.86rem; font-weight:500;
    color:var(--text-primary,#101828); cursor:pointer;
}
.att-pick select:focus { outline:none; }

/* The open list is drawn by the browser, and setting any background on the
   select is enough for Chrome to stop theming it — so the popup came back
   white while the option text stayed near-white from color-scheme: dark.
   Light grey on white, which is how "All sites" arrived unreadable. Both
   colours are stated here so the list follows the theme either way. */
.att-pick select option {
    background-color:var(--bg-elevated,#fff);
    color:var(--text-primary,#101828);
}

/* The ring belongs on the pill. Left to itself the browser drew it tight
   around the select alone, with the map pin stranded outside it. */
.att-pick:focus-within {
    border-color:var(--brand,#1668dc);
    box-shadow:0 0 0 3px color-mix(in srgb, var(--brand,#1668dc) 20%, transparent);
}

/* Shift: a segmented control built from radios, so the choice is submitted
   with the form and works without JavaScript. */
.att-seg {
    display:inline-flex; align-items:center; gap:2px; padding:3px;
    border-radius:9px; background:var(--bg-subtle,#f8f9fb);
    border:1px solid var(--border,#e4e7ec);
}
.att-seg input { position:absolute; opacity:0; pointer-events:none; }
.att-seg label {
    display:inline-flex; align-items:center; gap:6px; margin:0;
    padding:6px 14px; border-radius:6px; cursor:pointer;
    font-size:.82rem; color:var(--text-secondary,#344054);
    transition:background .13s, color .13s;
}
.att-seg label i { font-size:.85rem; }
.att-seg label:hover { color:var(--text-primary,#101828); }
.att-seg input:checked + label {
    background:var(--bg-elevated,#fff); color:var(--text-primary,#101828);
    font-weight:600; box-shadow:0 1px 2px rgba(16,24,40,.08);
}
.att-seg input:focus-visible + label { outline:2px solid var(--brand,#1668dc); outline-offset:1px; }

.att-ghost-btn {
    display:inline-flex; align-items:center; gap:6px; height:36px; padding:0 14px;
    border-radius:9px; cursor:pointer; text-decoration:none;
    font-size:.82rem; font-weight:600;
    background:var(--bg-subtle,#f8f9fb); border:1px solid var(--border,#e4e7ec);
    color:var(--text-secondary,#344054);
}
.att-ghost-btn:hover { background:var(--bg-elevated,#fff); color:var(--text-primary,#101828); }

@media (max-width:620px) {
    .att-controls-left, .att-controls-right { width:100%; }
    .att-pick, .att-seg { width:100%; }
    .att-pick select { flex:1; }
    .att-seg label { flex:1; justify-content:center; }
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

    /* Time-out that landed the next morning. The columns print clock times
       only, so a night shift read as "8:00 PM – 7:00 AM" — a day apparently
       run backwards — with nothing to say the out was the following day. */
    .att-nextday { display:inline-block; margin-left:5px; padding:1px 5px; border-radius:5px; background:#e0e7ff; color:#3730a3; font-size:10.5px; font-weight:700; vertical-align:middle; }
    [data-bs-theme="dark"] .att-nextday { background:#1e1b4b; color:#a5b4fc; }

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

    {{-- STAT CARDS — an accent edge and an icon rather than a plain box, and
         a short line under each number saying what it counts. They follow the
         filters below: the numbers and the rows always describe the same set
         of records. --}}
    <div class="att-stats">
        <div class="att-stat att-stat-brand">
            <div class="att-stat-head">
                <i class="fas fa-user-check"></i>
                <span>{{ __('Present today') }}</span>
            </div>
            <div class="att-stat-value">{{ $presentToday }}</div>
            <div class="att-stat-sub">{{ __('Clocked in today') }}</div>
        </div>
        <div class="att-stat att-stat-success">
            <div class="att-stat-head">
                <i class="fas fa-clock"></i>
                <span>{{ __('Currently clocked in') }}</span>
            </div>
            <div class="att-stat-value">{{ $clockedIn }}</div>
            <div class="att-stat-sub">{{ __('On-site, no time-out') }}</div>
        </div>
        <div class="att-stat att-stat-warning">
            <div class="att-stat-head">
                <i class="fas fa-triangle-exclamation"></i>
                <span>{{ __('Invalid attendance') }}</span>
            </div>
            <div class="att-stat-value">{{ $invalidCount }}</div>
            <div class="att-stat-sub">{{ __('Missed sign-out') }}</div>
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

    {{-- ── Control row ─────────────────────────────────────────────────────
         Site and Shift filter the whole page — both tabs and all three cards
         above — even though the row sits under the tabs.

         A GET form rather than JavaScript row-hiding: History is paginated
         fifteen at a time, so hiding rows in the browser would filter the
         page you can see and quietly ignore the rest of the result.

         The history actions share the row and are hidden while Today's
         Attendance is open, because there is nothing on that tab to delete. --}}
    <div class="att-controls">
        <form method="GET" action="{{ route('attendance') }}" class="att-controls-left" id="attFilters">
            <div class="att-pick">
                <i class="fas fa-location-dot"></i>
                <select name="site" id="attSite" aria-label="{{ __('Filter by site') }}">
                    <option value="">{{ __('All sites') }}</option>
                    @foreach($sites as $s)
                        <option value="{{ $s->id }}" @selected($siteId === $s->id)>{{ $s->name }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Radios, not buttons: the choice survives without JavaScript,
                 and the browser gives it keyboard handling for free. --}}
            <div class="att-seg" role="group" aria-label="{{ __('Filter by shift') }}">
                <input type="radio" name="shift" id="attShiftAll" value="" @checked(! $shiftId)>
                <label for="attShiftAll">{{ __('All shifts') }}</label>
                @foreach($shifts as $sh)
                    <input type="radio" name="shift" id="attShift{{ $sh->id }}" value="{{ $sh->id }}" @checked($shiftId === $sh->id)>
                    <label for="attShift{{ $sh->id }}">
                        <i class="fas {{ $sh->crosses_midnight ? 'fa-moon' : 'fa-sun' }}"></i>{{ $sh->name }}
                    </label>
                @endforeach
            </div>

            {{-- The tab rides along, so changing a filter while reading
                 History does not drop you back on Today's Attendance. --}}
            <input type="hidden" name="tab" id="attTabField" value="{{ request('tab') === 'history' ? 'history' : 'today' }}">

            <noscript><button type="submit" class="att-ghost-btn">{{ __('Apply') }}</button></noscript>

            {{-- No Clear button. Both controls already carry their own way
                 back — All sites and All shifts — so a third control that
                 only appears once a filter is on was a button that came and
                 went for no reason the reader could see. --}}
        </form>

        <div class="att-controls-right" id="attHistoryActions" @if($openTab !== 'history') hidden @endif>
            <button id="markModeBtn" type="button" class="att-mark-btn">
                <i class="fas fa-check-square me-1"></i>{{ __('Mark for Deletion') }}
            </button>
            <button id="deleteSelectedBtn" type="button" class="att-del-sel-btn" style="display:none;" disabled>
                <i class="fas fa-trash me-1"></i>{{ __('Delete Selected (') }}<span id="selCount">0</span>)
            </button>
            <button id="cancelMarkBtn" type="button" class="att-cancel-btn" style="display:none;">
                {{ __('Cancel') }}
            </button>
            <button id="deleteAllBtn" type="button" class="att-del-all-btn">
                <i class="fas fa-trash-alt me-1"></i>{{ __('Delete all') }}
            </button>
        </div>
    </div>

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
                                @if($record->out_days_later > 0)
                                    <span class="att-nextday" title="{{ __('Timed out the next morning — the same workday') }}">+{{ $record->out_days_later }}</span>
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

            {{-- The toolbar moved up into the control row it shares with the
                 filters; the ids are unchanged, so the script below still
                 finds every button. --}}

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
                                    @if($record->out_days_later > 0)
                                        <span class="att-nextday" title="{{ __('Timed out the next morning — the same workday') }}">+{{ $record->out_days_later }}</span>
                                    @endif
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

    // Site is a select, Shift is a group of radios; either one reloads with
    // the choice applied. The noscript Apply button covers the case where
    // this never runs at all.
    form.querySelectorAll('select, input[type="radio"]').forEach(el => {
        el.addEventListener('change', () => form.submit());
    });

    // Keep the hidden field and the address bar in step with the open tab, so
    // changing a filter while reading History comes back to History — and so
    // does a refresh. The delete controls share the row with the filters and
    // only mean anything on History, so they come and go with it.
    const historyActions = document.getElementById('attHistoryActions');

    document.querySelectorAll('.att-tabs [data-tab]').forEach(btn => {
        btn.addEventListener('shown.bs.tab', () => {
            const tab = btn.dataset.tab;
            if (tabField) tabField.value = tab;
            if (historyActions) historyActions.hidden = (tab !== 'history');

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
