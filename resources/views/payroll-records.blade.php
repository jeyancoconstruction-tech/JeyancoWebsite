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

    /* ── Dark mode ───────────────────────────────────────────────────────── */
    [data-bs-theme="dark"] .pr-header h1  { color: var(--text-primary); }
    [data-bs-theme="dark"] .filter-bar    { background: var(--surface); border-color: var(--border); }
    [data-bs-theme="dark"] .mode-btn      { background: var(--bg-subtle); border-color: var(--border); color: var(--text-secondary); }
    [data-bs-theme="dark"] .mode-btn:hover  { background: var(--bg-subtle); }
    [data-bs-theme="dark"] .mode-btn.active { background: var(--brand); border-color: var(--brand); color: #fff; }
    [data-bs-theme="dark"] .pr-summary-bar { background: var(--surface); border-color: var(--border); }
    [data-bs-theme="dark"] .pr-stat        { border-right-color: var(--border); border-bottom-color: var(--border); }

    /* ── The receipt: a payslip laid out inside the modal ─────────────────── */
    .emp-slip { background: var(--surface); border: 1px solid var(--border); border-radius: 6px; padding: 14px 16px; height: 100%; }
    .emp-slip-head { display: flex; align-items: center; gap: 10px; border-bottom: 1.5px solid var(--brand); padding-bottom: 8px; margin-bottom: 10px; }
    .emp-slip-logo { width: 34px; height: 34px; border-radius: 50%; object-fit: cover; border: 1px solid var(--border); flex-shrink: 0; }
    .emp-slip-co { flex: 1; min-width: 0; }
    .emp-slip-co .co  { font-size: 12.5px; font-weight: 800; color: var(--brand); letter-spacing: .3px; line-height: 1.1; }
    .emp-slip-co .sub { font-size: 9.5px; color: var(--text-secondary); }
    .emp-slip-doc { text-align: right; }
    .emp-slip-doc .lbl { font-size: 12px; font-weight: 800; letter-spacing: 2px; color: var(--text-secondary); }
    .emp-slip-doc .per { font-size: 9px; color: var(--text-secondary); }
    .emp-slip-emp { display: flex; justify-content: space-between; gap: 8px; margin-bottom: 10px; flex-wrap: wrap; }
    .emp-slip-emp .who  { font-weight: 700; color: var(--text-primary); font-size: 13px; }
    .emp-slip-emp .meta { color: var(--text-secondary); font-size: 11px; }
    .emp-slip-cols { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
    .emp-slip-cols h6 { margin: 0 0 5px; font-size: 9.5px; text-transform: uppercase; letter-spacing: .5px; color: var(--text-secondary); font-weight: 800; border-bottom: 1px solid var(--border); padding-bottom: 3px; }
    .emp-slip .ln { display: flex; justify-content: space-between; font-size: 11.5px; padding: 2.5px 0; font-variant-numeric: tabular-nums; }
    .emp-slip .ln .k { color: var(--text-secondary); }
    .emp-slip .ln .v { color: var(--text-primary); font-weight: 600; }
    .emp-slip .ln.sum { border-top: 1px solid var(--border-md); margin-top: 3px; padding-top: 5px; font-weight: 800; }
    .emp-slip-net { display: flex; justify-content: space-between; align-items: center; margin-top: 10px; border: 1.5px solid var(--brand); border-radius: 6px; padding: 8px 12px; background: var(--brand-subtle); }
    .emp-slip-net .k { font-size: 11px; font-weight: 800; letter-spacing: .5px; color: var(--brand); }
    .emp-slip-net .v { font-size: 1.15rem; font-weight: 900; color: var(--brand); font-variant-numeric: tabular-nums; }

    /* ── Table readability ───────────────────────────────────────────────────
       enterprise.css paints every table body cell --text-secondary and every
       header --text-muted. That is a caption colour, and on a payroll table the
       names and the figures are the content, not a caption. Both themes are
       written here so neither drifts from the other later. */
    html[data-bs-theme="dark"]  .table-card .table tbody td,
    html[data-bs-theme="light"] .table-card .table tbody td { color: var(--text-primary); }
    html[data-bs-theme="dark"]  .table-card .table thead th,
    html[data-bs-theme="light"] .table-card .table thead th { color: var(--text-secondary) !important; }

    /* Bootstrap paints a row hover with an inset box-shadow, not a background,
       so the background override in enterprise.css never reaches it — the row
       washed out to near-white and took the text with it. These are the two
       variables it actually reads. */
    /* Page-scoped, so it reaches the payslip and export tables in the modals
       too — they hover the same way and washed out the same way. */
    .table {
        --bs-table-hover-bg: var(--brand-subtle);
        --bs-table-hover-color: var(--text-primary);
    }

    /* ── Day receipt ─────────────────────────────────────────────────────────
       A breakdown row is nine figures wide and stops short of saying how they
       were arrived at. Clicking one opens the rest of the arithmetic. */
    .pr-row { cursor: pointer; }
    .rc-basis {
        margin: 0 0 12px; padding: 8px 10px; border-radius: 6px;
        background: var(--bg-subtle); border: 1px solid var(--border);
        font-size: 11.5px; color: var(--text-secondary); font-variant-numeric: tabular-nums;
    }
    .rc-math {
        display: grid; grid-template-columns: repeat(3, 1fr); gap: 6px 12px;
        margin-top: 12px; padding: 9px 12px; border-radius: 6px;
        background: var(--bg-subtle); border: 1px solid var(--border);
        font-size: 11.5px; font-variant-numeric: tabular-nums;
    }
    .rc-math span { color: var(--text-secondary); display: block; }
    .rc-math b    { color: var(--text-primary); font-weight: 700; }
    .rc-rates { margin-top: 12px; border-top: 1px solid var(--border); padding-top: 10px; }
    .rc-rates-head {
        font-size: 9.5px; font-weight: 800; letter-spacing: .5px; text-transform: uppercase;
        color: var(--text-secondary); margin-bottom: 7px;
    }
    .rc-badge {
        margin-left: 6px; padding: 1px 7px; border-radius: 999px; letter-spacing: .3px;
        background: rgba(22,163,74,.12); color: var(--success);
    }
    .rc-rates-grid {
        display: grid; grid-template-columns: repeat(2, minmax(0, 1fr) auto); gap: 4px 10px;
        font-size: 11px; font-variant-numeric: tabular-nums;
    }
    .rc-rates-grid span { color: var(--text-muted); }
    .rc-rates-grid b    { color: var(--text-secondary); font-weight: 700; text-align: right; }
    .rc-note { margin: 10px 0 0; font-size: 11px; line-height: 1.6; color: var(--text-muted); }
</style>
@endpush

@section('content')
<div class="pr-page">

    {{-- The table lists a row per worker per day either way; what changes is how
         much of the calendar it covers, so the heading follows the mode rather
         than always claiming the same span. --}}
    @php $isDaily = $period['mode'] === 'daily'; @endphp

    {{-- ── Page header ─────────────────────────────────────────────────────── --}}
    <div class="pr-header d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1>Payroll Records</h1>
            <p>{{ $isDaily ? 'Daily' : 'Weekly' }} breakdown of pay, for the period below</p>
        </div>
        <button type="button" class="btn btn-success fw-600"
                data-bs-toggle="modal" data-bs-target="#exportPreviewModal">
            <i class="fas fa-file-excel me-1"></i> Preview &amp; Download
        </button>
    </div>

    {{-- ── Filter bar ──────────────────────────────────────────────────────── --}}
    <div class="filter-bar mb-3">
        <form method="GET" action="{{ route('payroll-records') }}" id="prFilter">
            {{-- A week or a single day, not a loose range: the daily breakdown is
                 the days inside a pay period, and a range that is not one breaks
                 down into days nobody is paid on. The controller falls a URL
                 asking for custom back to the week, so the mode here is only
                 ever one the form offers. --}}
            <input type="hidden" name="mode" id="mode" value="{{ $period['mode'] }}">

            <div class="report-modes mb-3">
                <button type="button" class="mode-btn {{ $period['mode'] === 'weekly' ? 'active' : '' }}" data-mode="weekly">
                    <i class="fas fa-calendar-week me-1"></i> Weekly (Mon–Sun)
                </button>
                <button type="button" class="mode-btn {{ $period['mode'] === 'daily' ? 'active' : '' }}" data-mode="daily">
                    <i class="fas fa-calendar-day me-1"></i> Daily
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

    {{-- ── The breakdown ───────────────────────────────────────────────────
         The whole page. By Employee and Pay Periods were removed with the
         Payroll Records destination they belonged to; a week is now one row
         per worker here, and a day is one row per shift. --}}
        <div class="card table-card">
            <div class="table-card-header">
                <h6>
                    <i class="fas {{ $isDaily ? 'fa-calendar-day' : 'fa-calendar-week' }}"></i>
                    {{ $isDaily ? 'Daily' : 'Weekly' }} Breakdown
                </h6>
            </div>
            <div class="table-responsive">
                <table class="table align-middle table-hover mb-0">
                    <thead>
                        <tr>
                            <th class="ps-4">{{ $isDaily ? 'Date' : 'Week' }}</th>
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
                        @if($isDaily)
                            @forelse($days as $day)
                                @foreach($day['details'] as $d)
                                {{-- The whole computation for the row is already
                                     here, so the receipt is built from it rather
                                     than fetched again. --}}
                                <tr class="pr-row"
                                    data-slip="{{ json_encode($d) }}"
                                    data-date="{{ \Carbon\Carbon::parse($day['date'])->format('l, F j, Y') }}">
                                    <td class="ps-4 text-muted">{{ \Carbon\Carbon::parse($day['date'])->format('m/d/Y (D)') }}</td>
                                    <td class="fw-semibold">{{ $d['name'] }}</td>
                                    <td class="text-end">{{ $d['hours'] }}</td>
                                    <td class="text-end" style="color:var(--text-primary);">&#8369;{{ number_format($d['dailyRate'], 2) }}</td>
                                    <td class="text-end" style="color:var(--text-primary);">&#8369;{{ number_format($d['restDayPay'], 2) }}</td>
                                    <td class="text-end" style="color:var(--text-primary);">&#8369;{{ number_format($d['bonus'], 2) }}</td>
                                    <td class="text-end" style="color:var(--text-primary);">&#8369;{{ number_format($d['gross'], 2) }}</td>
                                    <td class="text-end" style="color:var(--danger);">&#8369;{{ number_format($d['totalDeductions'], 2) }}</td>
                                    <td class="text-end pe-4 fw-semibold" style="color:var(--brand);">&#8369;{{ number_format($d['net'], 2) }}</td>
                                </tr>
                                @endforeach
                            @empty
                            <tr>
                                <td colspan="9" class="text-center py-5 text-muted">No records for this period.</td>
                            </tr>
                            @endforelse
                        @else
                            {{-- A week is one row per worker, not one per shift.
                                 The daily rate belongs to the worker rather than
                                 to a day, so it is read off any day they worked. --}}
                            @php
                                $rateOf = [];
                                foreach ($days as $day) {
                                    foreach ($day['details'] as $d) {
                                        $rateOf[$d['employee_id']] ??= $d['dailyRate'];
                                    }
                                }
                            @endphp
                            @forelse($employees as $emp)
                                @php
                                    $t    = $emp['totals'];
                                    $rate = $rateOf[$emp['employee_id']] ?? 0;
                                @endphp
                                <tr class="pr-row"
                                    data-slip="{{ json_encode([
                                        'employee_id' => $emp['employee_id'],
                                        'name'        => $emp['name'],
                                        'dailyRate'   => $rate,
                                    ]) }}"
                                    data-date="{{ $period['label'] }}">
                                    <td class="ps-4 text-muted">{{ $period['label'] }}</td>
                                    <td class="fw-semibold">{{ $emp['name'] }}</td>
                                    <td class="text-end">{{ number_format($t['hours'], 2) }}</td>
                                    <td class="text-end" style="color:var(--text-primary);">&#8369;{{ number_format($rate, 2) }}</td>
                                    <td class="text-end" style="color:var(--text-primary);">&#8369;{{ number_format($t['restDayPay'] ?? 0, 2) }}</td>
                                    <td class="text-end" style="color:var(--text-primary);">&#8369;{{ number_format($t['bonus'], 2) }}</td>
                                    <td class="text-end" style="color:var(--text-primary);">&#8369;{{ number_format($t['gross'], 2) }}</td>
                                    <td class="text-end" style="color:var(--danger);">&#8369;{{ number_format($t['totalDeductions'], 2) }}</td>
                                    <td class="text-end pe-4 fw-semibold" style="color:var(--brand);">&#8369;{{ number_format($t['net'], 2) }}</td>
                                </tr>
                            @empty
                            <tr>
                                <td colspan="9" class="text-center py-5 text-muted">No records for this period.</td>
                            </tr>
                            @endforelse
                        @endif
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ── Payslip receipt ──────────────────────────────────────────
             One shell, filled from whichever employee was clicked. A modal
             each would be a few hundred on a busy week, all for the one
             that gets opened. --}}
        <div class="modal fade" id="dayReceipt" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
                <div class="modal-content" style="background:var(--surface);border:1px solid var(--border);">
                    <div class="modal-body p-3 p-md-4">
                        <div class="emp-slip" style="border:none;padding:0;">
                            <div class="emp-slip-head">
                                <img class="emp-slip-logo" src="{{ $company?->logoUrl() ?? asset('images/JeyancoLogo.png') }}" alt="">
                                <div class="emp-slip-co">
                                    <div class="co">{{ $company?->company_name ?? 'JEYANCO CONSTRUCTION' }}</div>
                                    <div class="sub">{{ $company?->company_tagline ?? 'Payroll Dept. · Panganiban, PH' }}</div>
                                </div>
                                <div class="emp-slip-doc">
                                    <div class="lbl">PAYSLIP</div>
                                    <div class="per">{{ $period['label'] }}</div>
                                </div>
                            </div>

                            <div class="emp-slip-emp">
                                <span class="who" id="rcName">&mdash;</span>
                                <span class="meta" id="rcMeta">&mdash;</span>
                            </div>

                            {{-- What the hours were paid at. Without it the
                                 earnings column is a list of figures with
                                 nothing to check them against. --}}
                            <div class="rc-basis" id="rcBasis">&mdash;</div>

                            <div class="emp-slip-cols">
                                <div>
                                    <h6>Earnings</h6>
                                    <div class="ln"><span class="k" id="rcBasicK">Regular pay</span><span class="v" id="rcBasic">&mdash;</span></div>
                                    <div class="ln"><span class="k" id="rcOtK">Overtime</span><span class="v" id="rcOt">&mdash;</span></div>
                                    <div class="ln"><span class="k" id="rcNightK">Night differential</span><span class="v" id="rcNight">&mdash;</span></div>
                                    <div class="ln"><span class="k" id="rcHolidayK">Holiday pay</span><span class="v" id="rcHoliday">&mdash;</span></div>
                                    <div class="ln"><span class="k" id="rcRestK">Rest day pay</span><span class="v" id="rcRest">&mdash;</span></div>
                                    <div class="ln sum"><span class="k">Gross pay</span><span class="v" id="rcGross">&mdash;</span></div>
                                </div>
                                <div>
                                    <h6>Deductions</h6>
                                    <div class="ln"><span class="k" id="rcSssK">SSS</span><span class="v" id="rcSss">&mdash;</span></div>
                                    <div class="ln"><span class="k" id="rcPhilK">PhilHealth</span><span class="v" id="rcPhil">&mdash;</span></div>
                                    <div class="ln"><span class="k" id="rcPagK">Pag-IBIG</span><span class="v" id="rcPag">&mdash;</span></div>
                                    <div class="ln"><span class="k" id="rcTaxK">Withholding tax</span><span class="v" id="rcTax">&mdash;</span></div>
                                    <div class="ln"><span class="k">Vale / cash advance</span><span class="v" id="rcVale">&mdash;</span></div>
                                    <div class="ln"><span class="k">Other adjustments</span><span class="v" id="rcOther">&mdash;</span></div>
                                    <div class="ln sum"><span class="k">Total deductions</span><span class="v" id="rcDed" style="color:var(--danger);">&mdash;</span></div>
                                </div>
                            </div>

                            {{-- The bonus is added to net, not to gross, so
                                 putting it in the earnings column would make
                                 the Gross line stop adding up. It belongs in
                                 the arithmetic that reaches net. --}}
                            <div class="rc-math">
                                <span>Gross</span><b id="rcMGross">&mdash;</b>
                                <span>&minus; Deductions</span><b id="rcMDed">&mdash;</b>
                                <span>+ Bonus</span><b id="rcMBonus">&mdash;</b>
                            </div>

                            <div class="emp-slip-net">
                                <span class="k">NET PAY &middot; {{ $period['label'] }}</span>
                                <span class="v" id="rcNet">&mdash;</span>
                            </div>

                            {{-- The settings the period was computed at. The
                                 figures above are only checkable against the
                                 numbers that produced them. --}}
                            <div class="rc-rates">
                                <div class="rc-rates-head">
                                    Rates applied
                                    @if($rates['uses_defaults'])<span class="rc-badge">statutory defaults</span>@endif
                                </div>
                                <div class="rc-rates-grid">
                                    <span>Overtime</span><b>&times;{{ number_format($rates['ot_multiplier'], 2) }}</b>
                                    <span>Night differential</span><b>&times;{{ number_format($rates['night_diff_multiplier'], 2) }}</b>
                                    <span>Rest day</span><b>&times;{{ number_format($rates['rest_day_multiplier'], 2) }}</b>
                                    <span>Regular holiday</span><b>&times;{{ number_format($rates['regular_holiday_multiplier'], 2) }}</b>
                                    <span>SSS</span><b>{{ number_format($rates['sss_rate'], 2) }}%</b>
                                    <span>PhilHealth</span><b>{{ number_format($rates['philhealth_rate'], 2) }}%</b>
                                    <span>Pag-IBIG</span><b>{{ number_format($rates['pagibig_rate'], 2) }}%</b>
                                    <span>Withholding tax</span><b>{{ $rates['withholding_tax'] ? 'BIR table' : 'off' }}</b>
                                    <span>Bonus</span><b>&#8369;{{ number_format($rates['bonus'], 2) }}/period</b>
                                    <span>Wage floor</span><b>{{ $rates['daily_rate'] ? '&#8369;' . number_format($rates['daily_rate'], 2) : 'none set' }}</b>
                                </div>
                            </div>

                            <p class="rc-note">
                                Night differential is 10:00 PM &ndash; 6:00 AM. Contributions are the employee share
                                on gross; the withholding tax is the BIR daily table on what is left after them.
                                Vale and adjustments are entered per attendance record, not here.
                            </p>

                            <div class="d-flex gap-2 mt-3">
                                <button type="button" class="btn btn-sm fw-600"
                                        data-bs-dismiss="modal"
                                        style="background:var(--bg-subtle);color:var(--text-secondary);border:1px solid var(--border);">
                                    Close
                                </button>
                                <a href="#" target="_blank" rel="noopener" id="rcPrint"
                                   class="btn btn-sm fw-600 flex-grow-1"
                                   style="background:var(--brand);color:#fff;border:none;">
                                    <i class="fas fa-print me-1"></i> Print / Save as PDF
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

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
                    <span class="me-auto text-muted" style="font-size:12px;">This is exactly what the Excel file will contain.</span>
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
@php
    // One slip per employee for the period on screen, built from the same
    // totals the By Employee cards use. The deductions are summed from the
    // weekly rows because only those carry them itemised.
    $slipMap = [];
    foreach ($employees as $emp) {
        $t = $emp['totals'];
        $sss = $phil = $pag = $tax = $vale = $other = 0;
        foreach ($emp['periods'] as $pp) {
            $sss   += $pp['sssDeduction'];
            $phil  += $pp['philhealthDeduction'];
            $pag   += $pp['pagibigDeduction'];
            $tax   += $pp['withholdingTax'];
            $vale  += $pp['vale'];
            $other += $pp['manualDeductions'];
        }

        // Gross is basic + overtime + holiday + rest day + night differential.
        // The bonus is added to net, not to gross, so it is not subtracted here.
        $regular = round($t['gross'] - $t['overtime'] - $t['holidayPay']
                       - ($t['restDayPay'] ?? 0) - ($t['nightDiffPay'] ?? 0), 2);

        $slipMap[(string) $emp['employee_id']] = [
            'name'      => $emp['name'],
            'position'  => $emp['position'] ?? '',
            'workdays'  => $t['workdays'],
            'hours'     => $t['hours'],
            'regular'   => $regular,
            'overtime'  => $t['overtime'],
            'night'     => $t['nightDiffPay'] ?? 0,
            'holiday'   => $t['holidayPay'],
            'rest'      => $t['restDayPay'] ?? 0,
            'bonus'     => $t['bonus'],
            'gross'     => $t['gross'],
            'sss'       => round($sss, 2),
            'phil'      => round($phil, 2),
            'pag'       => round($pag, 2),
            'tax'       => round($tax, 2),
            'vale'      => round($vale, 2),
            'other'     => round($other, 2),
            'ded'       => $t['totalDeductions'],
            'net'       => $t['net'],
        ];
    }
@endphp
<script>
// ── Payslip receipt ─────────────────────────────────────────────────────────
// The page already holds every figure for the period, so clicking a breakdown
// row only has to lay them out. Nothing is fetched.
(function () {
    const modalEl = document.getElementById('dayReceipt');
    if (!modalEl) return;

    const SLIPS = @json($slipMap);
    const RATES = @json($rates);
    const printBase = @json(route('payslip.batch', ['from' => $period['from'], 'to' => $period['to']]));

    const peso  = new Intl.NumberFormat('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const money = n => '₱' + peso.format(Number(n) || 0);
    const set   = (id, text) => { const el = document.getElementById(id); if (el) el.textContent = text; };

    function open(row) {
        let d;
        try { d = JSON.parse(row.dataset.slip); } catch { return; }

        const id = String(d.employee_id ?? '');
        const s  = SLIPS[id];
        if (!s) return;

        set('rcName', s.name || '');
        set('rcMeta', '#' + id.padStart(4, '0')
                    + (s.position ? ' · ' + s.position : '')
                    + ' · ' + s.workdays + 'd / ' + peso.format(s.hours) + 'h');

        // The rate the days were priced at, taken from the row that was clicked
        // — a labour type's rate is per worker, not per period.
        const daily = Number(d.dailyRate) || 0;
        set('rcBasis', money(daily) + '/day · ' + money(daily / 8) + '/hr · '
                     + s.workdays + ' day' + (s.workdays === 1 ? '' : 's') + ' pasok');

        // Each line names what produced it, because "why is this 250" is the
        // question a column of totals cannot answer.
        set('rcBasicK',   'Regular pay (' + s.workdays + 'd)');
        set('rcOtK',      'Overtime (×' + Number(RATES.ot_multiplier).toFixed(2) + ')');
        set('rcNightK',   'Night differential (×' + Number(RATES.night_diff_multiplier).toFixed(2) + ')');
        set('rcHolidayK', 'Holiday pay (×' + Number(RATES.regular_holiday_multiplier).toFixed(2) + ')');
        set('rcRestK',    'Rest day pay (×' + Number(RATES.rest_day_multiplier).toFixed(2) + ')');
        set('rcSssK',     'SSS (' + Number(RATES.sss_rate).toFixed(2) + '%)');
        set('rcPhilK',    'PhilHealth (' + Number(RATES.philhealth_rate).toFixed(2) + '%)');
        set('rcPagK',     'Pag-IBIG (' + Number(RATES.pagibig_rate).toFixed(2) + '%)');
        set('rcTaxK',     RATES.withholding_tax ? 'Withholding tax (BIR)' : 'Withholding tax (off)');

        set('rcBasic',   money(s.regular));
        set('rcOt',      money(s.overtime));
        set('rcNight',   money(s.night));
        set('rcHoliday', money(s.holiday));
        set('rcRest',    money(s.rest));
        set('rcGross',   money(s.gross));

        set('rcSss',   money(s.sss));
        set('rcPhil',  money(s.phil));
        set('rcPag',   money(s.pag));
        set('rcTax',   money(s.tax));
        set('rcVale',  money(s.vale));
        set('rcOther', money(s.other));
        set('rcDed',   money(s.ded));

        set('rcMGross', money(s.gross));
        set('rcMDed',   money(s.ded));
        set('rcMBonus', money(s.bonus));
        set('rcNet',    money(s.net));

        const print = document.getElementById('rcPrint');
        if (print) print.href = printBase + '&employee=' + encodeURIComponent(id);

        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }

    document.querySelectorAll('.pr-row').forEach(row => {
        row.addEventListener('click', () => open(row));
    });
})();
</script>

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
