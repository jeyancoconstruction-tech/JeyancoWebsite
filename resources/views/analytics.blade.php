@extends('layouts')
@section('page_title', 'Analytics')

@section('content')
<div class="an-page">

    {{-- ── Page header ─────────────────────────────────────────────────────── --}}
    <div class="an-header">
        <div>
            <h1 class="an-title">Analytics & Insights</h1>
            <p class="an-sub">Performance overview for <strong>{{ $monthLabel }}</strong></p>
        </div>
        <span class="an-period-chip">
            <i class="fas fa-calendar-alt"></i> {{ $monthLabel }}
        </span>
    </div>

    {{-- ── KPI Row ──────────────────────────────────────────────────────────── --}}
    <div class="an-kpi-grid">

        <div class="an-kpi-card">
            <div class="an-kpi-icon" style="background:#eff6ff;color:#3b82f6;">
                <i class="fas fa-users"></i>
            </div>
            <div class="an-kpi-body">
                <p class="an-kpi-label">Total Employees</p>
                <p class="an-kpi-value">{{ $totalEmployees }}</p>
                <p class="an-kpi-sub">{{ $activeSites }} active site{{ $activeSites !== 1 ? 's' : '' }}</p>
            </div>
        </div>

        <div class="an-kpi-card">
            <div class="an-kpi-icon" style="background:#f0fdf4;color:#16a34a;">
                <i class="fas fa-user-check"></i>
            </div>
            <div class="an-kpi-body">
                <p class="an-kpi-label">Present Today</p>
                <p class="an-kpi-value">{{ $presentToday }}</p>
                <p class="an-kpi-sub">
                    @php $absentToday = max(0, $totalEmployees - $presentToday); @endphp
                    {{ $absentToday }} absent
                </p>
            </div>
        </div>

        <div class="an-kpi-card">
            <div class="an-kpi-icon" style="background:#f0fdf4;color:#059669;">
                <i class="fas fa-money-bill-wave"></i>
            </div>
            <div class="an-kpi-body">
                <p class="an-kpi-label">Net Payroll (Month)</p>
                <p class="an-kpi-value">₱{{ number_format($monthlyNet, 0) }}</p>
                @if($netChange !== null)
                <p class="an-kpi-sub {{ $netChange >= 0 ? 'an-up' : 'an-down' }}">
                    <i class="fas fa-arrow-{{ $netChange >= 0 ? 'up' : 'down' }}"></i>
                    {{ abs($netChange) }}% vs last month
                </p>
                @else
                <p class="an-kpi-sub">No prior month data</p>
                @endif
            </div>
        </div>

        <div class="an-kpi-card">
            <div class="an-kpi-icon" style="background:#fefce8;color:#ca8a04;">
                <i class="fas fa-calendar-check"></i>
            </div>
            <div class="an-kpi-body">
                <p class="an-kpi-label">Attendance Rate</p>
                <p class="an-kpi-value">{{ $attendanceRate }}%</p>
                <p class="an-kpi-sub">This month</p>
            </div>
            <div class="an-kpi-ring" style="--pct:{{ $attendanceRate }};--clr:#ca8a04;">
                <svg viewBox="0 0 36 36"><circle class="an-ring-bg" cx="18" cy="18" r="15.9"/><circle class="an-ring-fg" cx="18" cy="18" r="15.9" style="stroke:var(--clr);stroke-dasharray:{{ $attendanceRate }} {{ 100 - $attendanceRate }};"/></svg>
            </div>
        </div>

        <div class="an-kpi-card">
            <div class="an-kpi-icon" style="background:#fdf4ff;color:#9333ea;">
                <i class="fas fa-business-time"></i>
            </div>
            <div class="an-kpi-body">
                <p class="an-kpi-label">Overtime Hours</p>
                <p class="an-kpi-value">{{ number_format($overtimeHours, 1) }}<span class="an-kpi-unit">h</span></p>
                <p class="an-kpi-sub">₱{{ number_format($monthlyOTPay, 0) }} OT pay</p>
            </div>
        </div>

        <div class="an-kpi-card">
            <div class="an-kpi-icon" style="background:#fff7ed;color:#ea580c;">
                <i class="fas fa-chart-pie"></i>
            </div>
            <div class="an-kpi-body">
                <p class="an-kpi-label">Gross Payroll</p>
                <p class="an-kpi-value">₱{{ number_format($monthlyGross, 0) }}</p>
                <p class="an-kpi-sub">₱{{ number_format($monthlyHoliday, 0) }} holiday pay</p>
            </div>
        </div>

    </div>

    {{-- ── Attendance Trend ─────────────────────────────────────────────────── --}}
    <div class="an-card an-card-full">
        <div class="an-card-head">
            <div>
                <p class="an-card-title">Attendance Trend</p>
                <p class="an-card-sub">Daily employee presence over the last 30 days</p>
            </div>
            <span class="an-badge-blue">30-day view</span>
        </div>
        <div class="an-chart-wrap" style="height:220px;">
            <canvas id="attendanceTrendChart"></canvas>
        </div>
    </div>

    {{-- ── Row 2: Weekly Payroll + Labor Type ──────────────────────────────── --}}
    <div class="an-row-2">
        <div class="an-card">
            <div class="an-card-head">
                <div>
                    <p class="an-card-title">Weekly Payroll</p>
                    <p class="an-card-sub">Gross vs Net — last 4 weeks</p>
                </div>
                <span class="an-badge-green">₱ Pesos</span>
            </div>
            <div class="an-chart-wrap" style="height:240px;">
                <canvas id="weeklyPayrollChart"></canvas>
            </div>
        </div>

        <div class="an-card">
            <div class="an-card-head">
                <div>
                    <p class="an-card-title">Labor Type Distribution</p>
                    <p class="an-card-sub">Employees by position</p>
                </div>
            </div>
            @if($laborDist->isEmpty())
            <div class="an-empty">No employee data</div>
            @else
            <div class="an-chart-wrap" style="height:240px;">
                <canvas id="laborTypeChart"></canvas>
            </div>
            @endif
        </div>
    </div>

    {{-- ── Row 3: Top OT + Deductions ──────────────────────────────────────── --}}
    <div class="an-row-2">
        <div class="an-card">
            <div class="an-card-head">
                <div>
                    <p class="an-card-title">Top Overtime Earners</p>
                    <p class="an-card-sub">Overtime pay (₱) this month</p>
                </div>
                <span class="an-badge-purple">Top 5</span>
            </div>
            @if($topOT->isEmpty() || $topOT->every(fn($e) => $e['ot'] == 0))
            <div class="an-empty">No overtime recorded this month</div>
            @else
            <div class="an-chart-wrap" style="height:240px;">
                <canvas id="overtimeChart"></canvas>
            </div>
            @endif
        </div>

        <div class="an-card">
            <div class="an-card-head">
                <div>
                    <p class="an-card-title">Deduction Breakdown</p>
                    <p class="an-card-sub">Monthly statutory & voluntary</p>
                </div>
            </div>
            @php $totalDeductions = $sssTot + $philTot + $pagibigTot + $valeTot + $otherTot; @endphp
            @if($totalDeductions == 0)
            <div class="an-empty">No deductions recorded this month</div>
            @else
            <div style="display:flex;align-items:center;gap:20px;flex-wrap:wrap;">
                <div class="an-chart-wrap" style="height:200px;flex:1;min-width:120px;">
                    <canvas id="deductionChart"></canvas>
                </div>
                <div class="an-deduction-legend">
                    <div class="an-dl-row"><span class="an-dl-dot" style="background:#3b82f6;"></span><span class="an-dl-label">SSS</span><span class="an-dl-val">₱{{ number_format($sssTot, 0) }}</span></div>
                    <div class="an-dl-row"><span class="an-dl-dot" style="background:#10b981;"></span><span class="an-dl-label">PhilHealth</span><span class="an-dl-val">₱{{ number_format($philTot, 0) }}</span></div>
                    <div class="an-dl-row"><span class="an-dl-dot" style="background:#14b8a6;"></span><span class="an-dl-label">Pag-IBIG</span><span class="an-dl-val">₱{{ number_format($pagibigTot, 0) }}</span></div>
                    <div class="an-dl-row"><span class="an-dl-dot" style="background:#ef4444;"></span><span class="an-dl-label">Vale</span><span class="an-dl-val">₱{{ number_format($valeTot, 0) }}</span></div>
                    @if($otherTot > 0)
                    <div class="an-dl-row"><span class="an-dl-dot" style="background:#64748b;"></span><span class="an-dl-label">Other</span><span class="an-dl-val">₱{{ number_format($otherTot, 0) }}</span></div>
                    @endif
                    <div class="an-dl-total">Total: ₱{{ number_format($totalDeductions, 0) }}</div>
                </div>
            </div>
            @endif
        </div>
    </div>

    {{-- ── Row 4: Site Distribution ─────────────────────────────────────────── --}}
    @if($siteDist->isNotEmpty())
    <div class="an-card an-card-full">
        <div class="an-card-head">
            <div>
                <p class="an-card-title">Workforce by Site</p>
                <p class="an-card-sub">Employee headcount per work site</p>
            </div>
        </div>
        <div class="an-site-bars">
            @foreach($siteDist as $siteName => $count)
            @php $pct = $totalEmployees > 0 ? ($count / $totalEmployees) * 100 : 0; @endphp
            <div class="an-site-row">
                <span class="an-site-name">{{ $siteName }}</span>
                <div class="an-site-bar-wrap">
                    <div class="an-site-bar-fill" style="width:{{ $pct }}%;"></div>
                </div>
                <span class="an-site-count">{{ $count }} emp{{ $count !== 1 ? 's' : '' }}</span>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- ── Employee Performance Table ───────────────────────────────────────── --}}
    @if($empTable->isNotEmpty())
    <div class="an-card an-card-full">
        <div class="an-card-head">
            <div>
                <p class="an-card-title">Employee Performance — {{ $monthLabel }}</p>
                <p class="an-card-sub">Individual payroll summary sorted by net pay</p>
            </div>
            <span class="an-badge-blue">{{ $empTable->count() }} employees</span>
        </div>
        <div class="table-responsive">
            <table class="an-table">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th class="text-center">Workdays</th>
                        <th class="text-center">Hours</th>
                        <th class="text-end">Gross</th>
                        <th class="text-end">OT Pay</th>
                        <th class="text-end">Holiday</th>
                        <th class="text-end">Deductions</th>
                        <th class="text-end">Net Pay</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($empTable as $emp)
                    <tr>
                        <td>
                            <div class="an-emp-cell">
                                <div class="an-emp-avatar">{{ strtoupper(substr($emp['name'], 0, 1)) }}</div>
                                <div>
                                    <p class="an-emp-name">{{ $emp['name'] }}</p>
                                    <p class="an-emp-pos">{{ $emp['position'] ?: '—' }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="text-center an-td-num">{{ $emp['totals']['workdays'] }}</td>
                        <td class="text-center an-td-num">{{ number_format($emp['totals']['hours'], 1) }}h</td>
                        <td class="text-end an-td-num">₱{{ number_format($emp['totals']['gross'], 2) }}</td>
                        <td class="text-end an-td-num">
                            @if($emp['totals']['overtime'] > 0)
                            <span class="an-ot-badge">₱{{ number_format($emp['totals']['overtime'], 2) }}</span>
                            @else
                            <span class="an-td-muted">—</span>
                            @endif
                        </td>
                        <td class="text-end an-td-num">
                            @if($emp['totals']['holidayPay'] > 0)
                            <span class="an-holiday-badge">₱{{ number_format($emp['totals']['holidayPay'], 2) }}</span>
                            @else
                            <span class="an-td-muted">—</span>
                            @endif
                        </td>
                        <td class="text-end an-td-num an-td-ded">₱{{ number_format($emp['totals']['totalDeductions'], 2) }}</td>
                        <td class="text-end">
                            <span class="an-net-val">₱{{ number_format($emp['totals']['net'], 2) }}</span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="an-tfoot">
                        <td><strong>TOTAL</strong></td>
                        <td class="text-center">{{ $empTable->sum(fn($e) => $e['totals']['workdays']) }}</td>
                        <td class="text-center">{{ number_format($empTable->sum(fn($e) => $e['totals']['hours']), 1) }}h</td>
                        <td class="text-end">₱{{ number_format($monthlyGross, 2) }}</td>
                        <td class="text-end">₱{{ number_format($monthlyOTPay, 2) }}</td>
                        <td class="text-end">₱{{ number_format($monthlyHoliday, 2) }}</td>
                        <td class="text-end">₱{{ number_format($empTable->sum(fn($e) => $e['totals']['totalDeductions']), 2) }}</td>
                        <td class="text-end"><strong>₱{{ number_format($monthlyNet, 2) }}</strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    @endif

</div>

{{-- ── Styles ──────────────────────────────────────────────────────────────── --}}
<style>
/* ── Page shell ──────────────────────────────────────────────────────────── */
.an-page { max-width: none; width: 100%; margin: 0; padding-bottom: 40px; }

/* ── Page header ─────────────────────────────────────────────────────────── */
.an-header {
    display: flex; align-items: flex-start;
    justify-content: space-between; flex-wrap: wrap; gap: 12px;
    margin-bottom: 24px;
}
.an-title { font-size: 1.5rem; font-weight: 700; color: #0f172a; margin: 0 0 4px; }
.an-sub   { font-size: 13.5px; color: #64748b; margin: 0; }
.an-period-chip {
    display: inline-flex; align-items: center; gap: 6px;
    font-size: 12px; font-weight: 600; color: #2563eb;
    background: #eff6ff; border: 1px solid #bfdbfe;
    padding: 5px 12px; border-radius: 20px;
}

/* ── KPI grid ────────────────────────────────────────────────────────────── */
.an-kpi-grid {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 14px;
    margin-bottom: 16px;
}
@media (max-width: 1100px) { .an-kpi-grid { grid-template-columns: repeat(3, 1fr); } }
@media (max-width: 680px)  { .an-kpi-grid { grid-template-columns: repeat(2, 1fr); } }

.an-kpi-card {
    background: #fff; border: 1px solid #e2e8f0; border-radius: 14px;
    padding: 18px 16px; display: flex; gap: 12px; align-items: flex-start;
    position: relative; overflow: hidden;
    transition: box-shadow .15s, transform .15s;
}
.an-kpi-card:hover { box-shadow: 0 4px 18px rgba(0,0,0,.07); transform: translateY(-1px); }
.an-kpi-card::after {
    content: ''; position: absolute; top: 0; left: 0; right: 0;
    height: 3px; background: linear-gradient(90deg, #3b82f6, #3b82f6);
    border-radius: 14px 14px 0 0;
}

.an-kpi-icon {
    width: 40px; height: 40px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 16px; flex-shrink: 0;
}
.an-kpi-body { flex: 1; min-width: 0; }
.an-kpi-label {
    font-size: 10px; font-weight: 700; letter-spacing: .6px;
    text-transform: uppercase; color: #94a3b8; margin: 0 0 4px;
}
.an-kpi-value {
    font-size: 1.4rem; font-weight: 800; color: #0f172a;
    line-height: 1.1; margin: 0 0 4px; letter-spacing: -.5px;
}
.an-kpi-unit { font-size: 1rem; font-weight: 600; color: #94a3b8; }
.an-kpi-sub  { font-size: 11px; color: #94a3b8; margin: 0; }
.an-up   { color: #16a34a !important; }
.an-down { color: #dc2626 !important; }

/* Donut ring for attendance rate card */
.an-kpi-ring {
    position: absolute; right: 12px; top: 12px;
    width: 44px; height: 44px; opacity: .35;
}
.an-kpi-ring svg { transform: rotate(-90deg); }
.an-ring-bg  { fill: none; stroke: #e2e8f0; stroke-width: 3; }
.an-ring-fg  { fill: none; stroke-width: 3; stroke-linecap: round; stroke-dashoffset: 0; }

/* ── Cards ───────────────────────────────────────────────────────────────── */
.an-card {
    background: #fff; border: 1px solid #e2e8f0; border-radius: 14px;
    padding: 20px 22px; margin-bottom: 16px;
}
.an-card-full { width: 100%; }

.an-row-2 {
    display: grid; grid-template-columns: 1fr 1fr;
    gap: 16px; margin-bottom: 16px;
}
@media (max-width: 860px) { .an-row-2 { grid-template-columns: 1fr; } }

.an-card-head {
    display: flex; align-items: flex-start;
    justify-content: space-between; gap: 12px;
    margin-bottom: 18px; flex-wrap: wrap;
}
.an-card-title { font-size: 14px; font-weight: 700; color: #0f172a; margin: 0 0 2px; }
.an-card-sub   { font-size: 12px; color: #94a3b8; margin: 0; }

.an-chart-wrap { position: relative; }
.an-empty {
    display: flex; align-items: center; justify-content: center;
    height: 140px; font-size: 13px; color: #94a3b8;
    font-style: italic;
}

/* Badges */
.an-badge-blue   { font-size: 11px; font-weight: 700; color: #2563eb; background: #eff6ff; border: 1px solid #bfdbfe; padding: 3px 10px; border-radius: 20px; white-space: nowrap; }
.an-badge-green  { font-size: 11px; font-weight: 700; color: #15803d; background: #f0fdf4; border: 1px solid #bbf7d0; padding: 3px 10px; border-radius: 20px; white-space: nowrap; }
.an-badge-purple { font-size: 11px; font-weight: 700; color: #2563eb; background: #eff6ff; border: 1px solid #bfdbfe; padding: 3px 10px; border-radius: 20px; white-space: nowrap; }

/* ── Deduction legend ─────────────────────────────────────────────────────── */
.an-deduction-legend { flex-shrink: 0; min-width: 160px; }
.an-dl-row {
    display: flex; align-items: center; gap: 8px;
    margin-bottom: 8px; font-size: 13px;
}
.an-dl-dot   { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
.an-dl-label { flex: 1; color: #374151; }
.an-dl-val   { font-weight: 700; color: #0f172a; }
.an-dl-total {
    font-size: 12px; font-weight: 700; color: #64748b;
    border-top: 1px solid #e2e8f0; padding-top: 8px; margin-top: 4px;
}

/* ── Site bars ───────────────────────────────────────────────────────────── */
.an-site-bars { display: flex; flex-direction: column; gap: 10px; }
.an-site-row  { display: flex; align-items: center; gap: 12px; }
.an-site-name { font-size: 13px; font-weight: 600; color: #374151; width: 80px; flex-shrink: 0; }
.an-site-bar-wrap {
    flex: 1; height: 10px; background: #f1f5f9;
    border-radius: 6px; overflow: hidden;
}
.an-site-bar-fill {
    height: 100%; background: linear-gradient(90deg, #3b82f6, #3b82f6);
    border-radius: 6px; transition: width .6s ease;
    min-width: 4px;
}
.an-site-count { font-size: 12px; color: #64748b; width: 70px; text-align: right; flex-shrink: 0; }

/* ── Employee table ──────────────────────────────────────────────────────── */
.an-table { width: 100%; border-collapse: collapse; }
.an-table thead th {
    font-size: 11px; font-weight: 700; letter-spacing: .5px;
    text-transform: uppercase; color: #64748b;
    background: #f8fafc; padding: 10px 12px;
    border-bottom: 1px solid #e2e8f0; white-space: nowrap;
}
.an-table tbody td {
    padding: 11px 12px; border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
}
.an-table tbody tr:last-child td { border-bottom: none; }
.an-table tbody tr:hover td { background: #f8fafc; }
.an-tfoot td {
    padding: 10px 12px; font-size: 13px; font-weight: 700;
    border-top: 2px solid #e2e8f0; color: #0f172a;
    background: #f8fafc;
}

.an-emp-cell { display: flex; align-items: center; gap: 10px; }
.an-emp-avatar {
    width: 34px; height: 34px; border-radius: 50%; flex-shrink: 0;
    background: linear-gradient(135deg, #3b82f6, #3b82f6);
    color: #fff; font-size: 13px; font-weight: 700;
    display: flex; align-items: center; justify-content: center;
}
.an-emp-name { font-size: 13px; font-weight: 600; color: #0f172a; margin: 0; }
.an-emp-pos  { font-size: 11px; color: #94a3b8; margin: 0; }

.an-td-num  { font-size: 13px; color: #374151; }
.an-td-muted { font-size: 13px; color: #cbd5e1; }
.an-td-ded  { color: #dc2626 !important; }

.an-ot-badge {
    font-size: 12px; font-weight: 600; color: #2563eb;
    background: #eff6ff; padding: 2px 7px; border-radius: 5px;
}
.an-holiday-badge {
    font-size: 12px; font-weight: 600; color: #d97706;
    background: #fefce8; padding: 2px 7px; border-radius: 5px;
}
.an-net-val {
    font-size: 14px; font-weight: 800; color: #059669;
}

/* ── Dark mode ───────────────────────────────────────────────────────────── */
[data-bs-theme="dark"] .an-title         { color: #e8edf5; }
[data-bs-theme="dark"] .an-sub           { color: #6b7d96; }
[data-bs-theme="dark"] .an-period-chip   { background: #172554; border-color: #3b82f6; color: #93c5fd; }
[data-bs-theme="dark"] .an-kpi-card      { background: #151d2e; border-color: #283449; }
[data-bs-theme="dark"] .an-kpi-card:hover { box-shadow: 0 4px 18px rgba(0,0,0,.3); }
[data-bs-theme="dark"] .an-kpi-label     { color: #6b7d96; }
[data-bs-theme="dark"] .an-kpi-value     { color: #e8edf5; }
[data-bs-theme="dark"] .an-kpi-sub       { color: #6b7d96; }
[data-bs-theme="dark"] .an-ring-bg       { stroke: #283449; }
[data-bs-theme="dark"] .an-card          { background: #151d2e; border-color: #283449; }
[data-bs-theme="dark"] .an-card-title    { color: #e8edf5; }
[data-bs-theme="dark"] .an-card-sub      { color: #6b7d96; }
[data-bs-theme="dark"] .an-empty         { color: #475569; }
[data-bs-theme="dark"] .an-badge-blue    { background: #172554; border-color: #3b82f6; color: #93c5fd; }
[data-bs-theme="dark"] .an-badge-green   { background: #052e16; border-color: #166534; color: #86efac; }
[data-bs-theme="dark"] .an-badge-purple  { background: #172554; border-color: #2563eb; color: #93c5fd; }
[data-bs-theme="dark"] .an-dl-label      { color: #cdd7e5; }
[data-bs-theme="dark"] .an-dl-val        { color: #e8edf5; }
[data-bs-theme="dark"] .an-dl-total      { color: #6b7d96; border-top-color: #283449; }
[data-bs-theme="dark"] .an-site-name     { color: #cdd7e5; }
[data-bs-theme="dark"] .an-site-bar-wrap { background: #1c2740; }
[data-bs-theme="dark"] .an-site-count    { color: #6b7d96; }
[data-bs-theme="dark"] .an-table thead th { background: #1c2740; color: #6b7d96; border-bottom-color: #283449; }
[data-bs-theme="dark"] .an-table tbody td { border-bottom-color: #1a2336; }
[data-bs-theme="dark"] .an-table tbody tr:hover td { background: #1a2336; }
[data-bs-theme="dark"] .an-tfoot td      { background: #1c2740; color: #e8edf5; border-top-color: #283449; }
[data-bs-theme="dark"] .an-emp-name      { color: #e8edf5; }
[data-bs-theme="dark"] .an-emp-pos       { color: #6b7d96; }
[data-bs-theme="dark"] .an-td-num        { color: #cdd7e5; }
[data-bs-theme="dark"] .an-td-muted      { color: #283449; }
</style>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function () {
    const isDark    = document.documentElement.getAttribute('data-bs-theme') === 'dark';
    const gridColor = isDark ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.04)';
    const textColor = isDark ? '#9fb0c7' : '#64748b';
    const tooltipBg = isDark ? '#0f1729' : '#fff';
    const tooltipBd = isDark ? '#283449' : '#e2e8f0';

    Chart.defaults.font.family = "'Inter', sans-serif";
    Chart.defaults.font.size   = 12;

    const tooltipPlugin = {
        backgroundColor: tooltipBg,
        titleColor:      isDark ? '#e8edf5' : '#0f172a',
        bodyColor:       isDark ? '#9fb0c7' : '#64748b',
        borderColor:     tooltipBd,
        borderWidth:     1,
        padding:         10,
        cornerRadius:    8,
    };

    // ── 1. Attendance Trend ────────────────────────────────────────────────
    const trendLabels = @json($trendLabels);
    const trendData   = @json($trendData);

    new Chart(document.getElementById('attendanceTrendChart'), {
        type: 'line',
        data: {
            labels: trendLabels,
            datasets: [{
                label: 'Employees Present',
                data: trendData,
                borderColor: '#3b82f6',
                backgroundColor: isDark ? 'rgba(59,130,246,0.10)' : 'rgba(59,130,246,0.08)',
                borderWidth: 2.5,
                fill: true,
                tension: 0.4,
                pointRadius: 3,
                pointHoverRadius: 6,
                pointBackgroundColor: '#3b82f6',
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: tooltipPlugin,
            },
            scales: {
                x: { grid: { color: gridColor }, ticks: { color: textColor, maxTicksLimit: 10 } },
                y: { grid: { color: gridColor }, ticks: { color: textColor, stepSize: 1, precision: 0 }, beginAtZero: true },
            }
        }
    });

    // ── 2. Weekly Payroll ──────────────────────────────────────────────────
    const weekLabels = @json($weekLabels);
    const weekGross  = @json($weekGross);
    const weekNet    = @json($weekNet);

    new Chart(document.getElementById('weeklyPayrollChart'), {
        type: 'bar',
        data: {
            labels: weekLabels,
            datasets: [
                {
                    label: 'Gross Pay',
                    data: weekGross,
                    backgroundColor: isDark ? 'rgba(59,130,246,0.55)' : 'rgba(59,130,246,0.65)',
                    borderColor: '#3b82f6',
                    borderWidth: 1.5,
                    borderRadius: 6,
                },
                {
                    label: 'Net Pay',
                    data: weekNet,
                    backgroundColor: isDark ? 'rgba(16,185,129,0.55)' : 'rgba(16,185,129,0.65)',
                    borderColor: '#10b981',
                    borderWidth: 1.5,
                    borderRadius: 6,
                }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: {
                legend: { position: 'top', labels: { color: textColor, usePointStyle: true, pointStyleWidth: 10, padding: 16 } },
                tooltip: { ...tooltipPlugin, callbacks: { label: ctx => ' ₱' + ctx.parsed.y.toLocaleString() } },
            },
            scales: {
                x: { grid: { display: false }, ticks: { color: textColor, maxTicksLimit: 4 } },
                y: { grid: { color: gridColor }, ticks: { color: textColor, callback: v => '₱' + (v/1000).toFixed(0) + 'K' }, beginAtZero: true },
            }
        }
    });

    // ── 3. Labor Type Doughnut ─────────────────────────────────────────────
    const laborLabels = @json($laborDist->keys()->values());
    const laborData   = @json($laborDist->values()->values());
    const PALETTE     = ['#3b82f6','#10b981','#14b8a6','#ef4444','#64748b','#06b6d4','#0ea5e9','#14b8a6'];

    if (document.getElementById('laborTypeChart')) {
        new Chart(document.getElementById('laborTypeChart'), {
            type: 'doughnut',
            data: {
                labels: laborLabels,
                datasets: [{
                    data: laborData,
                    backgroundColor: PALETTE,
                    hoverOffset: 6,
                    borderWidth: 2,
                    borderColor: isDark ? '#151d2e' : '#fff',
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                cutout: '62%',
                plugins: {
                    legend: { position: 'bottom', labels: { color: textColor, usePointStyle: true, padding: 12, font: { size: 12 } } },
                    tooltip: tooltipPlugin,
                }
            }
        });
    }

    // ── 4. Overtime Horizontal Bar ─────────────────────────────────────────
    const otNames = @json($topOT->pluck('name'));
    const otPay   = @json($topOT->pluck('ot'));

    if (document.getElementById('overtimeChart') && otNames.length) {
        new Chart(document.getElementById('overtimeChart'), {
            type: 'bar',
            data: {
                labels: otNames,
                datasets: [{
                    label: 'OT Pay (₱)',
                    data: otPay,
                    backgroundColor: isDark ? 'rgba(139,92,246,0.6)' : 'rgba(124,58,237,0.65)',
                    borderColor: '#2563eb',
                    borderWidth: 1.5,
                    borderRadius: 6,
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true, maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: { ...tooltipPlugin, callbacks: { label: ctx => ' ₱' + ctx.parsed.x.toLocaleString() } },
                },
                scales: {
                    x: { grid: { color: gridColor }, ticks: { color: textColor, callback: v => '₱' + v.toLocaleString() }, beginAtZero: true },
                    y: { grid: { display: false }, ticks: { color: textColor } },
                }
            }
        });
    }

    // ── 5. Deduction Breakdown Doughnut ────────────────────────────────────
    const dedLabels = ['SSS', 'PhilHealth', 'Pag-IBIG', 'Vale', 'Other'];
    const dedData   = [
        {{ $sssTot }}, {{ $philTot }}, {{ $pagibigTot }},
        {{ $valeTot }}, {{ $otherTot }}
    ].map((v, i) => v);
    const dedColors = ['#3b82f6', '#10b981', '#14b8a6', '#ef4444', '#64748b'];

    if (document.getElementById('deductionChart')) {
        const filteredLabels = [];
        const filteredData   = [];
        const filteredColors = [];
        dedData.forEach((v, i) => {
            if (v > 0) {
                filteredLabels.push(dedLabels[i]);
                filteredData.push(v);
                filteredColors.push(dedColors[i]);
            }
        });

        if (filteredData.length) {
            new Chart(document.getElementById('deductionChart'), {
                type: 'doughnut',
                data: {
                    labels: filteredLabels,
                    datasets: [{
                        data: filteredData,
                        backgroundColor: filteredColors,
                        hoverOffset: 6,
                        borderWidth: 2,
                        borderColor: isDark ? '#151d2e' : '#fff',
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    cutout: '65%',
                    plugins: {
                        legend: { display: false },
                        tooltip: { ...tooltipPlugin, callbacks: { label: ctx => ' ₱' + ctx.parsed.toLocaleString() } },
                    }
                }
            });
        }
    }

})();
</script>
@endpush
@endsection
