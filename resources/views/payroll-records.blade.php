@extends('layouts')

@section('page_title', 'Payroll Records')

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<style>
    .pr-page { padding: 20px 28px 48px; }
    @media (max-width: 768px) { .pr-page { padding: 16px; } }

    .pr-header h1 { font-size: 1.6rem; font-weight: 800; color: var(--text-primary); margin: 0; letter-spacing: -0.3px; }
    .pr-header p  { color: var(--text-secondary); font-size: 0.9rem; margin: 2px 0 0; }

    .filter-bar { background: var(--bg-subtle); border: 1px solid var(--border); border-radius: 6px; padding: 16px 18px; }

    .report-modes { display: flex; gap: 6px; flex-wrap: wrap; }
    .mode-btn { border: 1px solid var(--border); background: var(--surface); color: var(--text-secondary); font-size: 13px; font-weight: 600; padding: 7px 14px; border-radius: 6px; cursor: pointer; transition: all 0.15s; }
    .mode-btn:hover  { background: var(--brand-subtle); }
    .mode-btn.active { background: var(--brand); color: #fff; border-color: var(--brand); }

    /* ── Summary bar ─────────────────────────────────────────────────────── */
    .pr-summary-bar { display: flex; flex-wrap: wrap; background: var(--surface); border: 1px solid var(--border); border-radius: 6px; overflow: hidden; margin-bottom: 20px; }
    .pr-stat { flex: 1; min-width: 110px; padding: 14px 18px; border-right: 1px solid var(--border); }
    .pr-stat:last-child { border-right: none; }
    .pr-stat .k { font-size: 10.5px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.4px; color: var(--text-secondary); margin-bottom: 4px; }
    .pr-stat .v { font-size: 1.15rem; font-weight: 600; line-height: 1.2; color: var(--text-primary); font-variant-numeric: tabular-nums; }
    @media (max-width: 768px) { .pr-stat { min-width: 50%; border-bottom: 1px solid var(--border); } }

    /* ── Tabs ────────────────────────────────────────────────────────────── */
    .pr-tabs { border-bottom: 2px solid var(--border); margin: 4px 0 20px; gap: 4px; }
    .pr-tabs .nav-link { color: var(--text-secondary); border: none; padding: 12px 20px; font-weight: 700; font-size: 14px; }
    .pr-tabs .nav-link:hover  { color: var(--brand); }
    .pr-tabs .nav-link.active { color: var(--brand); border-bottom: 3px solid var(--brand); background: none; }

    /* ── Weekly cards ────────────────────────────────────────────────────── */
    .payroll-card { background: var(--surface); border: 1px solid var(--border); border-radius: 6px; cursor: pointer; transition: all 0.2s ease; }
    .payroll-card:hover { transform: translateY(-3px); box-shadow: 0 12px 26px rgba(0,0,0,0.08) !important; border-color: var(--border-md); }

    /* ── Dark mode ───────────────────────────────────────────────────────── */
    [data-bs-theme="dark"] .pr-header h1  { color: var(--text-primary); }
    [data-bs-theme="dark"] .filter-bar    { background: var(--surface); border-color: var(--border); }
    [data-bs-theme="dark"] .mode-btn      { background: var(--bg-subtle); border-color: var(--border); color: var(--text-secondary); }
    [data-bs-theme="dark"] .mode-btn:hover  { background: var(--bg-subtle); }
    [data-bs-theme="dark"] .mode-btn.active { background: var(--brand); border-color: var(--brand); color: #fff; }
    [data-bs-theme="dark"] .pr-tabs       { border-bottom-color: var(--border); }
    [data-bs-theme="dark"] .pr-tabs .nav-link        { color: var(--text-secondary); }
    [data-bs-theme="dark"] .pr-tabs .nav-link.active { color: var(--brand); border-bottom-color: var(--brand); }
    [data-bs-theme="dark"] .pr-summary-bar { background: var(--surface); border-color: var(--border); }
    [data-bs-theme="dark"] .pr-stat        { border-right-color: var(--border); border-bottom-color: var(--border); }
    [data-bs-theme="dark"] .payroll-card   { background: var(--surface); border-color: var(--border); }
    [data-bs-theme="dark"] .payroll-card:hover { border-color: var(--brand); }
</style>
@endpush

@section('content')
<div class="pr-page">

    {{-- ── Page header ─────────────────────────────────────────────────────── --}}
    <div class="pr-header d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1>Payroll Records</h1>
            <p>Reports, employee payroll, and pay periods — all in one place</p>
        </div>
        <button type="button" class="btn btn-success fw-600"
                data-bs-toggle="modal" data-bs-target="#exportPreviewModal">
            <i class="fas fa-file-excel me-1"></i> Preview &amp; Download
        </button>
    </div>

    {{-- ── Filter bar ──────────────────────────────────────────────────────── --}}
    <div class="filter-bar mb-3">
        <form method="GET" action="{{ route('payroll-records') }}" id="prFilter">
            <input type="hidden" name="mode" id="mode" value="{{ $period['mode'] }}">

            <div class="report-modes mb-3">
                <button type="button" class="mode-btn {{ $period['mode'] === 'weekly' ? 'active' : '' }}" data-mode="weekly">
                    <i class="fas fa-calendar-week me-1"></i> Weekly (Mon–Sun)
                </button>
                <button type="button" class="mode-btn {{ $period['mode'] === 'daily' ? 'active' : '' }}" data-mode="daily">
                    <i class="fas fa-calendar-day me-1"></i> Daily
                </button>
                <button type="button" class="mode-btn {{ $period['mode'] === 'custom' ? 'active' : '' }}" data-mode="custom">
                    <i class="fas fa-calendar-alt me-1"></i> Custom Range
                </button>
            </div>

            <div class="row g-3 align-items-end">
                <div class="col-auto mode-field" data-for="weekly">
                    <label class="form-label small fw-bold text-muted mb-1">Week</label>
                    <input type="week" name="week" value="{{ $period['week'] }}" class="form-control" style="border-color: var(--border);">
                </div>
                <div class="col-auto mode-field" data-for="daily">
                    <label class="form-label small fw-bold text-muted mb-1">Date</label>
                    <input type="date" name="date" value="{{ $period['date'] }}" class="form-control" style="border-color: var(--border);">
                </div>
                <div class="col-auto mode-field" data-for="custom">
                    <label class="form-label small fw-bold text-muted mb-1">From</label>
                    <input type="date" name="from" value="{{ $period['custom_from'] }}" class="form-control" style="border-color: var(--border);">
                </div>
                <div class="col-auto mode-field" data-for="custom">
                    <label class="form-label small fw-bold text-muted mb-1">To</label>
                    <input type="date" name="to" value="{{ $period['custom_to'] }}" class="form-control" style="border-color: var(--border);">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted mb-1">Employee (name or ID)</label>
                    <input type="text" name="employee" value="{{ $search }}" placeholder="All employees"
                           autocomplete="off" class="form-control" style="border-color: var(--border);">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary fw-600">
                        <i class="fas fa-magnifying-glass me-1"></i> Apply
                    </button>
                </div>
            </div>
        </form>

        <div class="mt-3">
            <span class="badge p-2 border" style="background:var(--surface);color:var(--brand);border-color:var(--border);">
                <i class="fas fa-calendar-alt me-1"></i> {{ ucfirst($period['mode']) }} &middot; {{ $period['label'] }}
            </span>
            @if($selectedEmployee)
            <span class="badge p-2 border ms-1" style="background:var(--brand-subtle);color:var(--brand);border-color:var(--border);">
                <i class="fas fa-user me-1"></i> {{ $selectedEmployee['name'] }} (#{{ $selectedEmployee['employee_id'] }})
            </span>
            @endif
        </div>
    </div>

    {{-- ── Summary bar (all key figures in one row) ────────────────────────── --}}
    <div class="pr-summary-bar mb-4">
        <div class="pr-stat">
            <div class="k">Net Payroll</div>
            <div class="v" style="color:var(--brand);">&#8369;{{ number_format($summary['net'], 2) }}</div>
        </div>
        <div class="pr-stat">
            <div class="k">Gross Pay</div>
            <div class="v" style="color:var(--text-primary);">&#8369;{{ number_format($summary['gross'], 2) }}</div>
        </div>
        <div class="pr-stat">
            <div class="k">Deductions</div>
            <div class="v" style="color:var(--danger);">&#8369;{{ number_format($summary['totalDeductions'], 2) }}</div>
        </div>
        <div class="pr-stat">
            <div class="k">Overtime</div>
            <div class="v" style="color:var(--text-primary);">&#8369;{{ number_format($summary['overtime'], 2) }}</div>
        </div>
        <div class="pr-stat">
            <div class="k">Holiday Pay</div>
            <div class="v" style="color:var(--text-primary);">&#8369;{{ number_format($summary['holidayPay'], 2) }}</div>
        </div>
        <div class="pr-stat">
            <div class="k">Rest Day Pay</div>
            <div class="v" style="color:var(--text-primary);">&#8369;{{ number_format($summary['restDayPay'] ?? 0, 2) }}</div>
        </div>
        <div class="pr-stat">
            <div class="k">Bonus</div>
            <div class="v" style="color:var(--text-primary);">&#8369;{{ number_format($summary['bonus'], 2) }}</div>
        </div>
        <div class="pr-stat">
            <div class="k">Employees</div>
            <div class="v" style="color:var(--text-primary);">{{ $summary['employee_count'] }}</div>
        </div>
        <div class="pr-stat">
            <div class="k">Hours / Days</div>
            <div class="v" style="color:var(--text-secondary);">
                {{ $summary['hours'] }}<span style="font-size:0.8rem;opacity:.75;">h / {{ $summary['workdays'] }}d</span>
            </div>
        </div>
    </div>

    {{-- ── Tabs ─────────────────────────────────────────────────────────────── --}}
    <ul class="nav nav-tabs pr-tabs" role="tablist">
        <li class="nav-item">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-reports" type="button">
                <i class="fas fa-chart-line me-1"></i> Reports
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-employees" type="button">
                <i class="fas fa-users me-1"></i> By Employee
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-periods" type="button">
                <i class="fas fa-calendar-week me-1"></i> Pay Periods
            </button>
        </li>
    </ul>

    <div class="tab-content">

        {{-- ===== REPORTS: daily breakdown table ============================= --}}
        <div class="tab-pane fade show active" id="tab-reports" role="tabpanel">
            <div class="card table-card">
                <div class="table-card-header">
                    <h6><i class="fas fa-calendar-day"></i> Daily Breakdown</h6>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle table-hover mb-0">
                        <thead>
                            <tr>
                                <th class="ps-4">Date</th>
                                <th>Employee</th>
                                <th class="text-end">Hours</th>
                                <th class="text-end">Daily Rate</th>
                                <th class="text-end">Rest Day Pay</th>
                                <th class="text-end">Bonus</th>
                                <th class="text-end">Gross</th>
                                <th class="text-end">Deductions</th>
                                <th class="text-end pe-4">Net</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($days as $day)
                                @foreach($day['details'] as $d)
                                <tr>
                                    <td class="ps-4 text-muted">{{ \Carbon\Carbon::parse($day['date'])->format('m/d/Y (D)') }}</td>
                                    <td class="fw-semibold">{{ $d['name'] }}</td>
                                    <td class="text-end">{{ $d['hours'] }}</td>
                                    <td class="text-end" style="color:var(--text-primary);">&#8369;{{ number_format($d['dailyRate'], 2) }}</td>
                                    <td class="text-end" style="color:var(--text-primary);">&#8369;{{ number_format($d['restDayPay'], 2) }}</td>
                                    <td class="text-end" style="color:var(--text-primary);">&#8369;{{ number_format($d['bonus'], 2) }}</td>
                                    <td class="text-end" style="color:var(--text-secondary);">&#8369;{{ number_format($d['gross'], 2) }}</td>
                                    <td class="text-end" style="color:var(--danger);">&#8369;{{ number_format($d['totalDeductions'], 2) }}</td>
                                    <td class="text-end pe-4 fw-semibold" style="color:var(--brand);">&#8369;{{ number_format($d['net'], 2) }}</td>
                                </tr>
                                @endforeach
                            @empty
                            <tr>
                                <td colspan="9" class="text-center py-5 text-muted">No records for this period.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- ===== BY EMPLOYEE ============================================== --}}
        <div class="tab-pane fade" id="tab-employees" role="tabpanel">
            <div class="card table-card">
                <div class="table-card-header d-flex justify-content-between align-items-center">
                    <h6><i class="fas fa-users"></i> By Employee</h6>
                    <span class="text-muted small">{{ count($employees) }} employee(s)</span>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle table-hover mb-0">
                        <thead>
                            <tr>
                                <th class="ps-4">Employee</th>
                                <th>Emp ID</th>
                                <th class="text-center">Workdays</th>
                                <th class="text-end">Hours</th>
                                <th class="text-end">Gross</th>
                                <th class="text-end">Deductions</th>
                                <th class="text-end">Net Pay</th>
                                <th class="text-end pe-4"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($employees as $emp)
                            <tr>
                                <td class="ps-4 fw-semibold">{{ $emp['name'] }}</td>
                                <td class="text-muted">#{{ $emp['employee_id'] }}</td>
                                <td class="text-center">{{ $emp['totals']['workdays'] }}</td>
                                <td class="text-end">{{ $emp['totals']['hours'] }}</td>
                                <td class="text-end" style="color:var(--text-secondary);">&#8369;{{ number_format($emp['totals']['gross'], 2) }}</td>
                                <td class="text-end" style="color:var(--danger);">&#8369;{{ number_format($emp['totals']['totalDeductions'], 2) }}</td>
                                <td class="text-end fw-bold" style="color:var(--brand);">&#8369;{{ number_format($emp['totals']['net'], 2) }}</td>
                                <td class="text-end pe-4">
                                    <a href="{{ route('payslip.show', ['employee' => $emp['employee_id'], 'from' => $period['from'], 'to' => $period['to']]) }}"
                                       class="btn btn-sm rounded-pill px-3"
                                       style="background:var(--brand-subtle);color:var(--brand);border:1px solid var(--border);font-weight:600;">
                                        <i class="fas fa-file-invoice me-1"></i>Payslip
                                    </a>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">No payroll data for this period.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- ===== PAY PERIODS: weekly cards ================================= --}}
        <div class="tab-pane fade" id="tab-periods" role="tabpanel">
            <div class="row g-3">
                @forelse($weeks as $i => $week)
                @php
                    // week_range is "m/d/Y - m/d/Y" — derive ISO dates for the batch print link.
                    [$wStart, $wEnd] = array_pad(array_map('trim', explode(' - ', $week['week_range'])), 2, null);
                    try { $wFrom = \Carbon\Carbon::createFromFormat('m/d/Y', $wStart)->toDateString(); } catch (\Throwable $e) { $wFrom = $period['from']; }
                    try { $wTo   = \Carbon\Carbon::createFromFormat('m/d/Y', $wEnd)->toDateString();   } catch (\Throwable $e) { $wTo   = $period['to']; }
                @endphp
                <div class="col-xl-4 col-lg-6">
                    <div class="payroll-card p-4 shadow-sm"
                         data-bs-toggle="modal" data-bs-target="#weeklyModal{{ $i }}">
                        <div class="mb-2" style="color:var(--brand);font-weight:700;">
                            <i class="fas fa-calendar-week me-2"></i>{{ $week['week_range'] }}
                        </div>
                        <div class="text-muted small fw-600">Weekly Payroll</div>
                        <div style="font-size:1.8rem;font-weight:900;color:var(--brand);">
                            &#8369;{{ number_format($week['total_payroll'], 2) }}
                        </div>
                        <div class="mt-2 d-flex gap-3 small text-muted">
                            <span><i class="fas fa-users me-1" style="color:var(--brand);"></i>{{ $week['employee_count'] }}</span>
                            <span><i class="fas fa-calendar-check me-1" style="color:var(--text-secondary);"></i>{{ $week['working_days'] }} day(s)</span>
                        </div>
                    </div>
                </div>

                <div class="modal fade" id="weeklyModal{{ $i }}" tabindex="-1">
                    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                        <div class="modal-content border-0">
                            <div class="modal-header"
                                 style="background:var(--brand);color:#fff;border:none;">
                                <h6 class="modal-title fw-bold">
                                    <i class="fas fa-calendar-week me-2"></i>{{ $week['week_range'] }}
                                </h6>
                                <div class="d-flex align-items-center gap-2">
                                    <a href="{{ route('payslip.batch', ['from' => $wFrom, 'to' => $wTo]) }}"
                                       target="_blank" rel="noopener"
                                       class="btn btn-sm fw-600"
                                       style="background:#fff;color:var(--brand);border:none;white-space:nowrap;"
                                       title="Print all payslips for this period on A4 (cut-out slips)">
                                        <i class="fas fa-print me-1"></i> Print A4 Payslips
                                    </a>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                </div>
                            </div>
                            <div class="modal-body p-3">
                                <table class="table align-middle table-hover">
                                    <thead>
                                        <tr class="text-muted small">
                                            <th>Employee</th>
                                            <th class="text-end">Gross</th>
                                            <th class="text-end">Deductions</th>
                                            <th class="text-end">Net</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($week['details'] as $detail)
                                        <tr class="fw-semibold">
                                            <td>{{ $detail['name'] }}</td>
                                            <td class="text-end" style="color:var(--text-secondary);">&#8369;{{ number_format($detail['gross'], 2) }}</td>
                                            <td class="text-end" style="color:var(--danger);">&#8369;{{ number_format($detail['totalDeductions'], 2) }}</td>
                                            <td class="text-end" style="color:var(--brand);">&#8369;{{ number_format($detail['net'], 2) }}</td>
                                            <td class="text-end">
                                                <a href="{{ route('payslip.show', ['employee' => $detail['employee_id'], 'from' => $period['from'], 'to' => $period['to']]) }}"
                                                   class="btn btn-sm rounded-pill px-3"
                                                   style="background:var(--brand-subtle);color:var(--brand);border:1px solid var(--border);font-weight:600;">
                                                    Payslip
                                                </a>
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                @empty
                <div class="col-12 text-center py-5 text-muted">No weekly records for this period.</div>
                @endforelse
            </div>
        </div>

    </div>{{-- /tab-content --}}

    {{-- ── Export preview + download (Excel / CSV) ─────────────────────────── --}}
    <div class="modal fade" id="exportPreviewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content border-0">
                <div class="modal-header text-white" style="background:var(--brand);">
                    <div>
                        <h6 class="modal-title fw-bold mb-0"><i class="fas fa-file-excel me-2"></i>Payroll Export Preview</h6>
                        <small style="opacity:.85;">{{ ucfirst($period['mode']) }} &middot; {{ $period['label'] }} &middot; {{ count($employees) }} employee{{ count($employees) === 1 ? '' : 's' }}</small>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0" style="font-size:12.5px;white-space:nowrap;">
                            <thead style="position:sticky;top:0;z-index:2;background:var(--border);">
                                <tr>
                                    <th class="ps-3">ID</th><th>Name</th><th>Position</th>
                                    <th class="text-end">Workdays</th><th class="text-end">Hours</th>
                                    <th class="text-end">Gross</th><th class="text-end">Overtime</th>
                                    <th class="text-end">Holiday</th><th class="text-end">Rest Day</th>
                                    <th class="text-end">Bonus</th><th class="text-end">Deductions</th>
                                    <th class="text-end pe-3">Net Pay</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($employees as $e)
                                    @php $t = $e['totals']; @endphp
                                    <tr>
                                        <td class="ps-3 text-muted">#{{ str_pad($e['employee_id'], 4, '0', STR_PAD_LEFT) }}</td>
                                        <td class="fw-semibold">{{ $e['name'] }}</td>
                                        <td>{{ $e['position'] }}</td>
                                        <td class="text-end">{{ $t['workdays'] }}</td>
                                        <td class="text-end">{{ number_format($t['hours'], 2) }}</td>
                                        <td class="text-end">₱{{ number_format($t['gross'], 2) }}</td>
                                        <td class="text-end">₱{{ number_format($t['overtime'], 2) }}</td>
                                        <td class="text-end">₱{{ number_format($t['holidayPay'], 2) }}</td>
                                        <td class="text-end">₱{{ number_format($t['restDayPay'] ?? 0, 2) }}</td>
                                        <td class="text-end">₱{{ number_format($t['bonus'], 2) }}</td>
                                        <td class="text-end text-danger">₱{{ number_format($t['totalDeductions'], 2) }}</td>
                                        <td class="text-end pe-3 fw-bold">₱{{ number_format($t['net'], 2) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="12" class="text-center py-4 text-muted">No payroll data for this period.</td></tr>
                                @endforelse
                            </tbody>
                            @if(count($employees))
                                <tfoot style="position:sticky;bottom:0;background:var(--border);font-weight:700;">
                                    <tr>
                                        <td class="ps-3" colspan="3">TOTAL</td>
                                        <td class="text-end">{{ $summary['workdays'] }}</td>
                                        <td class="text-end">{{ number_format($summary['hours'], 2) }}</td>
                                        <td class="text-end">₱{{ number_format($summary['gross'], 2) }}</td>
                                        <td class="text-end">₱{{ number_format($summary['overtime'], 2) }}</td>
                                        <td class="text-end">₱{{ number_format($summary['holidayPay'], 2) }}</td>
                                        <td class="text-end">₱{{ number_format($summary['restDayPay'], 2) }}</td>
                                        <td class="text-end">₱{{ number_format($summary['bonus'], 2) }}</td>
                                        <td class="text-end">₱{{ number_format($summary['totalDeductions'], 2) }}</td>
                                        <td class="text-end pe-3">₱{{ number_format($summary['net'], 2) }}</td>
                                    </tr>
                                </tfoot>
                            @endif
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <span class="me-auto text-muted" style="font-size:12px;">This is a preview of the file you'll download.</span>
                    <a href="{{ route('payroll-records.export', request()->query()) }}" class="btn btn-light border fw-600">
                        <i class="fas fa-file-csv me-1"></i> Download CSV
                    </a>
                    <a href="{{ route('payroll-records.export.excel', request()->query()) }}" class="btn btn-success fw-600">
                        <i class="fas fa-file-excel me-1"></i> Download Excel
                    </a>
                </div>
            </div>
        </div>
    </div>

</div>{{-- /pr-page --}}
@endsection

@push('scripts')
<script>
(function () {
    const modeInput = document.getElementById('mode');
    const buttons   = document.querySelectorAll('.mode-btn');
    const fields    = document.querySelectorAll('.mode-field');

    function applyMode(mode) {
        modeInput.value = mode;
        buttons.forEach(b => b.classList.toggle('active', b.dataset.mode === mode));
        fields.forEach(f => { f.style.display = (f.dataset.for === mode) ? '' : 'none'; });
    }

    buttons.forEach(b => b.addEventListener('click', () => {
        applyMode(b.dataset.mode);
        document.getElementById('prFilter').submit();
    }));
    applyMode(modeInput.value || 'weekly');
})();

document.addEventListener('shown.bs.modal', function () {
    if (typeof lucide !== 'undefined') lucide.createIcons();
});
</script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
// Date inputs — display MM-DD-YYYY, send YYYY-MM-DD to server
flatpickr('input[name="from"], input[name="to"], input[name="date"]', {
    dateFormat : 'Y-m-d',   // value sent to server
    altInput   : true,       // show a separate formatted display input
    altFormat  : 'm-d-Y',   // MM-DD-YYYY
    allowInput : true,
});
</script>
@endpush
