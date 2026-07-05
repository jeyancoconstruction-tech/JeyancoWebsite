@extends('layouts')

@section('page_title', 'Payslip')

@push('styles')
<style>
    .payslip-toolbar { max-width: 800px; margin: 0 auto 16px; display: flex; justify-content: space-between; gap: 8px; flex-wrap: wrap; }

    .payslip-sheet {
        max-width: 800px;
        margin: 0 auto;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.06);
        overflow: hidden;
    }
    .payslip-head {
        background: linear-gradient(135deg, #1e3a8a, #1e40af);
        color: #fff;
        padding: 24px 28px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .payslip-head h2 { margin: 0; font-size: 1.25rem; font-weight: 800; letter-spacing: 0.3px; }
    .payslip-head .sub { font-size: 12px; opacity: 0.85; }
    .payslip-head .doc { text-align: right; }
    .payslip-head .doc .label { font-size: 18px; font-weight: 800; letter-spacing: 2px; }

    .payslip-body { padding: 28px; }
    .payslip-meta { display: grid; grid-template-columns: 1fr 1fr; gap: 14px 24px; margin-bottom: 24px; }
    .payslip-meta .row-item .k { font-size: 10.5px; text-transform: uppercase; letter-spacing: 0.5px; color: #94a3b8; font-weight: 700; }
    .payslip-meta .row-item .v { font-size: 14px; color: #0f172a; font-weight: 600; }

    .payslip-cols { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
    .payslip-cols h6 { font-size: 12px; text-transform: uppercase; letter-spacing: 0.6px; font-weight: 800; margin-bottom: 10px; }
    .payslip-line { display: flex; justify-content: space-between; padding: 7px 0; font-size: 13.5px; border-bottom: 1px dashed #e2e8f0; }
    .payslip-line.total { border-top: 2px solid #e2e8f0; border-bottom: none; font-weight: 800; margin-top: 4px; padding-top: 10px; }

    .payslip-net {
        margin-top: 24px;
        background: #f0fdf4;
        border: 1px solid #bbf7d0;
        border-radius: 12px;
        padding: 18px 24px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .payslip-net .label { font-weight: 800; color: #15803d; letter-spacing: 0.5px; }
    .payslip-net .amount { font-size: 1.75rem; font-weight: 900; color: #15803d; }

    .payslip-foot { padding: 0 28px 24px; color: #94a3b8; font-size: 11px; text-align: center; }

    /* Dark mode */
    [data-bs-theme="dark"] .payslip-sheet { background: #151d2e; border-color: #283449; }
    [data-bs-theme="dark"] .payslip-meta .row-item .v { color: #e8edf5; }
    [data-bs-theme="dark"] .payslip-line { border-color: #283449; }
    [data-bs-theme="dark"] .payslip-net { background: rgba(16,185,129,0.12); border-color: rgba(16,185,129,0.3); }
    [data-bs-theme="dark"] .payslip-net .label, [data-bs-theme="dark"] .payslip-net .amount { color: #4ade80; }

    /* Print: show only the payslip sheet */
    @media print {
        .sidebar, .topbar, .sidebar-toggle, .sidebar-overlay,
        .chatbot-fab, .chatbot-window, .payslip-toolbar, .no-print { display: none !important; }
        .main-content { margin-left: 0 !important; }
        body { background: #fff !important; }
        .container-fluid { padding: 0 !important; }
        .payslip-sheet { border: none; box-shadow: none; max-width: 100%; }
        .payslip-head { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
</style>
@endpush

@section('content')
<div class="container-fluid py-2">

    {{-- TOOLBAR (hidden in print) --}}
    <div class="payslip-toolbar no-print">
        <a href="{{ url()->previous() }}" class="btn btn-light border fw-600">
            <i class="fas fa-arrow-left me-1"></i> Back
        </a>
        <div class="d-flex gap-2">
            <a href="{{ route('payslip.export', ['employee' => $employee->id, 'from' => $from, 'to' => $to]) }}"
               class="btn btn-light border fw-600">
                <i class="fas fa-file-csv me-1"></i> Export CSV
            </a>
            <button type="button" onclick="window.print()" class="btn fw-600" style="background: linear-gradient(135deg, #1e3a8a, #1e40af); color: #fff; border: none;">
                <i class="fas fa-print me-1"></i> Print / Save PDF
            </button>
        </div>
    </div>

    {{-- PAYSLIP SHEET --}}
    <div class="payslip-sheet">
        <div class="payslip-head">
            <div>
                <h2>Jeyanco Construction</h2>
                <div class="sub">Payroll Department &middot; Panganiban, Philippines</div>
            </div>
            <div class="doc">
                <div class="label">PAYSLIP</div>
                <div class="sub">{{ $periodLabel }}</div>
            </div>
        </div>

        <div class="payslip-body">
            <div class="payslip-meta">
                <div class="row-item"><div class="k">Employee</div><div class="v">{{ $employee->name }}</div></div>
                <div class="row-item"><div class="k">Employee ID</div><div class="v">#{{ $employee->id }}</div></div>
                <div class="row-item"><div class="k">Position</div><div class="v">{{ $employee->position ?: '—' }}</div></div>
                <div class="row-item"><div class="k">Pay Period</div><div class="v">{{ $periodLabel }}</div></div>
                <div class="row-item"><div class="k">Total Workdays</div><div class="v">{{ $payslip['workdays'] }} day(s)</div></div>
                <div class="row-item"><div class="k">Total Hours</div><div class="v">{{ $payslip['hours'] }} hr(s)</div></div>
            </div>

            @if($payslip['workdays'] == 0 && $payslip['gross'] == 0)
                <div class="text-center text-muted py-4" style="border: 1px dashed #e2e8f0; border-radius: 10px;">
                    <i class="fas fa-info-circle me-1"></i> No attendance or payroll records for this pay period.
                </div>
            @endif

            <div class="payslip-cols">
                {{-- EARNINGS --}}
                <div>
                    <h6 style="color: #16a34a;"><i class="fas fa-coins me-1"></i>Earnings</h6>
                    <div class="payslip-line" style="color:#64748b;font-size:.8rem;"><span>Daily Rate</span><span>&#8369;{{ number_format($payslip['dailyRate'], 2) }}</span></div>
                    <div class="payslip-line"><span>Regular Pay</span><span>&#8369;{{ number_format($payslip['regular'], 2) }}</span></div>
                    <div class="payslip-line"><span>Overtime Pay</span><span>&#8369;{{ number_format($payslip['overtime'], 2) }}</span></div>
                    <div class="payslip-line"><span>Holiday Pay</span><span>&#8369;{{ number_format($payslip['holidayPay'], 2) }}</span></div>
                    <div class="payslip-line"><span>Rest Day Pay (Sun)</span><span>&#8369;{{ number_format($payslip['restDayPay'] ?? 0, 2) }}</span></div>
                    <div class="payslip-line"><span>Bonus</span><span>&#8369;{{ number_format($payslip['bonus'], 2) }}</span></div>
                    <div class="payslip-line total"><span>Gross Pay</span><span style="color: #16a34a;">&#8369;{{ number_format($payslip['gross'], 2) }}</span></div>
                </div>

                {{-- DEDUCTIONS --}}
                <div>
                    <h6 style="color: #dc2626;"><i class="fas fa-receipt me-1"></i>Deductions</h6>
                    <div class="payslip-line"><span>SSS</span><span>&#8369;{{ number_format($payslip['ded']['sss'], 2) }}</span></div>
                    <div class="payslip-line"><span>PhilHealth</span><span>&#8369;{{ number_format($payslip['ded']['philhealth'], 2) }}</span></div>
                    <div class="payslip-line"><span>PAG-IBIG</span><span>&#8369;{{ number_format($payslip['ded']['pagibig'], 2) }}</span></div>
                    <div class="payslip-line"><span>Vale / Utang</span><span>&#8369;{{ number_format($payslip['ded']['vale'], 2) }}</span></div>
                    <div class="payslip-line"><span>Other</span><span>&#8369;{{ number_format($payslip['ded']['other'], 2) }}</span></div>
                    <div class="payslip-line total"><span>Total Deductions</span><span style="color: #dc2626;">&#8369;{{ number_format($payslip['totalDeductions'], 2) }}</span></div>
                </div>
            </div>

            <div class="payslip-net">
                <span class="label">NET PAY</span>
                <span class="amount">&#8369;{{ number_format($payslip['net'], 2) }}</span>
            </div>
        </div>

        <div class="payslip-foot">
            Generated on {{ now()->format('m/d/Y h:i A') }} &middot; This is a system-generated payslip and does not require a signature.
        </div>
    </div>
</div>
@endsection
