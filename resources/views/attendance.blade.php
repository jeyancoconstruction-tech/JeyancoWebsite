@extends('layouts')

@section('page_title', 'Attendance Monitoring')

@push('styles')
<style>
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
        <h3 class="attendance-title mb-0">Attendance Monitoring</h3>
        <span class="text-muted small"><i class="fas fa-calendar-day me-1"></i>{{ now()->format('l, m/d/Y') }}</span>
    </div>

    <!-- STAT CARDS -->
    <div class="analytics-row">
        <div class="analytics-card">
            <h5>Present Today</h5>
            <div class="value">{{ $presentToday }}</div>
            <small class="text-muted">Employees clocked in today</small>
        </div>
        <div class="analytics-card">
            <h5>Currently Clocked In</h5>
            <div class="value">{{ $clockedIn }}</div>
            <small class="text-muted">Still on-site (no time-out yet)</small>
        </div>
        <div class="analytics-card flagged">
            <h5>Invalid Attendance</h5>
            <div class="value">{{ $invalidCount }}</div>
            <small class="text-muted">Missed sign-out on previous days</small>
        </div>
    </div>

    <!-- TABS -->
    <ul class="nav nav-tabs att-tabs" role="tablist">
        <li class="nav-item">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#att-today" type="button">
                <i class="fas fa-calendar-day me-1"></i> Today's Attendance
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#att-history" type="button">
                <i class="fas fa-clock-rotate-left me-1"></i> History
            </button>
        </li>
    </ul>

    <div class="tab-content">

        <!-- ===== TODAY ===== -->
        <div class="tab-pane fade show active" id="att-today" role="tabpanel">
            <div class="table-card">
                <table class="attendance-table w-100">
                    <thead>
                        <tr>
                            <th class="p-2 text-start">Employee</th>
                            <th class="p-2 text-start">Session</th>
                            <th class="p-2 text-start">Time In / Out</th>
                            <th class="p-2 text-center">Status</th>
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
                                    <span class="badge-attendance badge-holiday ms-1" title="Holiday (Settings)"><i class="fas fa-star me-1"></i>Holiday</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="text-center py-5 text-muted">
                                <i class="fas fa-user-clock mb-2 d-block" style="font-size: 1.75rem; opacity: 0.3;"></i>
                                No employees have clocked in today yet.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ===== HISTORY ===== -->
        <div class="tab-pane fade" id="att-history" role="tabpanel">

            {{-- Toolbar --}}
            <div class="att-hist-toolbar">
                <div class="d-flex gap-2 align-items-center flex-wrap">
                    <button id="markModeBtn" type="button" class="att-mark-btn">
                        <i class="fas fa-check-square me-1"></i>Mark for Deletion
                    </button>
                    <button id="deleteSelectedBtn" type="button" class="att-del-sel-btn" style="display:none;" disabled>
                        <i class="fas fa-trash me-1"></i>Delete Selected (<span id="selCount">0</span>)
                    </button>
                    <button id="cancelMarkBtn" type="button" class="att-cancel-btn" style="display:none;">
                        Cancel
                    </button>
                </div>
                <button id="deleteAllBtn" type="button" class="att-del-all-btn">
                    <i class="fas fa-trash-alt me-1"></i>Delete All
                </button>
            </div>

            <div class="table-card">
                <table class="attendance-table w-100" id="historyTable">
                    <thead>
                        <tr>
                            <th class="att-check-col p-2">
                                <input type="checkbox" id="selectAllChk" title="Select all on this page">
                            </th>
                            <th class="p-2 text-start">Employee</th>
                            <th class="p-2 text-start">Date</th>
                            <th class="p-2 text-start">Session</th>
                            <th class="p-2 text-start">Time In / Out</th>
                            <th class="p-2 text-center">Status</th>
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
                                    <span class="text-muted fst-italic">No time-in</span>
                                @endif
                            </td>
                            <td class="text-center p-2">
                                <span class="badge-attendance {{ $cls }}">{{ $label }}</span>
                                @if($isHoliday)
                                    <span class="badge-attendance badge-holiday ms-1" title="Holiday (Settings)"><i class="fas fa-star me-1"></i>Holiday</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">No previous attendance records.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>

                <div class="mt-3">
                    {{ $historyAttendances->links() }}
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
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
        document.getElementById('historyTable').addEventListener('click', function (e) {
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
                    alert(successMsg.replace('{n}', data.deleted));
                    location.reload();
                } else {
                    alert(data.message || 'Something went wrong.');
                }
            } catch (err) {
                alert('Request failed: ' + err.message);
            }
        }

        delSelBtn.addEventListener('click', function () {
            const ids = getChecked().map(c => c.value);
            if (!ids.length) return;
            if (!confirm(`Delete ${ids.length} selected record(s)? This cannot be undone.`)) return;
            doDelete('{{ route("attendance.history.bulk-delete") }}', { ids }, 'Deleted {n} record(s).');
        });

        delAllBtn.addEventListener('click', function () {
            if (!confirm('Delete ALL history records? This cannot be undone.')) return;
            doDelete('{{ route("attendance.history.delete-all") }}', null, 'Deleted {n} record(s).');
        });
    })();
</script>
@endpush

@endsection
