<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Payslips — {{ $periodLabel }}</title>
<style>
    * { box-sizing: border-box; }
    html, body { margin: 0; padding: 0; background: #f1f5f9; color: #0f172a;
        font-family: "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }

    /* A4 sheet */
    @page { size: A4; margin: 8mm; }
    .sheet { width: 194mm; margin: 12px auto; }

    /* Screen-only toolbar */
    .toolbar { width: 194mm; margin: 16px auto 0; display: flex; justify-content: space-between;
        align-items: center; gap: 10px; flex-wrap: wrap; }
    .toolbar .t-title { font-weight: 700; font-size: 15px; }
    .toolbar .t-sub { color: #64748b; font-size: 12.5px; }
    .btn { border: none; border-radius: 6px; padding: 9px 16px; font-size: 13px; font-weight: 600;
        cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 7px; }
    .btn-print { background: #1E5C9B; color: #fff; }
    .btn-back  { background: #fff; color: #334155; border: 1px solid #cbd5e1; }
    .hint { width: 194mm; margin: 8px auto 0; color: #64748b; font-size: 12px; }

    /* Cut-out slips: 2 per row, dashed border = cut guide */
    .slips { display: grid; grid-template-columns: 1fr 1fr; gap: 0; }
    .slip {
        border: 1px dashed #94a3b8;
        padding: 8px 10px 10px;
        break-inside: avoid;
        page-break-inside: avoid;
        background: #fff;
        position: relative;
    }
    .slip::before { content: "\2702"; position: absolute; top: -8px; left: 6px; font-size: 11px;
        color: #94a3b8; background: #fff; padding: 0 3px; }

    .slip-head { display: flex; align-items: center; gap: 8px; border-bottom: 1.5px solid #1E5C9B;
        padding-bottom: 6px; margin-bottom: 6px; }
    .slip-logo { width: 30px; height: 30px; border-radius: 50%; object-fit: cover; flex-shrink: 0;
        border: 1px solid #e2e8f0; }
    .slip-co   { flex: 1; min-width: 0; }
    .slip-co .name { font-size: 12px; font-weight: 800; color: #1E5C9B; line-height: 1.1; letter-spacing: .2px; }
    .slip-co .sub  { font-size: 8.5px; color: #64748b; }
    .slip-doc { text-align: right; }
    .slip-doc .lbl { font-size: 11px; font-weight: 800; letter-spacing: 1.5px; color: #334155; }
    .slip-doc .per { font-size: 8px; color: #64748b; }

    .slip-emp { display: flex; justify-content: space-between; gap: 6px; font-size: 10px; margin-bottom: 6px; }
    .slip-emp .who { font-weight: 700; color: #0f172a; }
    .slip-emp .meta { color: #64748b; }

    .cols { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
    .cols h6 { margin: 0 0 3px; font-size: 8.5px; text-transform: uppercase; letter-spacing: .5px;
        color: #64748b; font-weight: 800; border-bottom: 1px solid #e2e8f0; padding-bottom: 2px; }
    .ln { display: flex; justify-content: space-between; font-size: 9.5px; padding: 1.5px 0;
        font-variant-numeric: tabular-nums; }
    .ln .k { color: #475569; } .ln .v { color: #0f172a; font-weight: 600; }
    .ln.sum { border-top: 1px solid #cbd5e1; margin-top: 2px; padding-top: 3px; font-weight: 800; }

    .net { display: flex; justify-content: space-between; align-items: center; margin-top: 7px;
        border: 1.5px solid #1E5C9B; border-radius: 5px; padding: 5px 9px; }
    .net .k { font-size: 9.5px; font-weight: 800; letter-spacing: .5px; color: #1E5C9B; }
    .net .v { font-size: 14px; font-weight: 900; color: #1E5C9B; font-variant-numeric: tabular-nums; }

    .sign { display: flex; justify-content: space-between; gap: 10px; margin-top: 9px; }
    .sign .box { flex: 1; text-align: center; }
    .sign .line { border-top: 1px solid #94a3b8; margin-top: 16px; padding-top: 2px;
        font-size: 7.5px; color: #64748b; text-transform: uppercase; letter-spacing: .4px; }

    .empty { grid-column: 1 / -1; text-align: center; padding: 40px; color: #64748b; }

    @media print {
        html, body { background: #fff; }
        .toolbar, .hint { display: none !important; }
        .sheet { margin: 0; width: auto; }
        .slip { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
</style>
</head>
<body>

    <div class="toolbar">
        <div>
            <div class="t-title">Payslips — {{ $periodLabel }}</div>
            <div class="t-sub">{{ count($slips) }} employee(s) &middot; A4 &middot; gupitin sa may putol-putol na linya (&#9986;)</div>
        </div>
        <div style="display:flex;gap:8px;">
            <a href="javascript:history.back()" class="btn btn-back">&larr; Back</a>
            <button class="btn btn-print" onclick="window.print()">&#128424; Print A4</button>
        </div>
    </div>
    <div class="hint">Tip: sa print dialog piliin ang <b>A4</b> at i-off ang "Headers and footers" para malinis ang gupit.</div>

    <div class="sheet">
        <div class="slips">
            @forelse($slips as $s)
                <div class="slip">
                    <div class="slip-head">
                        <img class="slip-logo" src="{{ asset('images/JeyancoLogo.png') }}" alt="">
                        <div class="slip-co">
                            <div class="name">JEYANCO CONSTRUCTION</div>
                            <div class="sub">Payroll Dept. &middot; Panganiban, PH</div>
                        </div>
                        <div class="slip-doc">
                            <div class="lbl">PAYSLIP</div>
                            <div class="per">{{ $periodLabel }}</div>
                        </div>
                    </div>

                    <div class="slip-emp">
                        <span class="who">{{ $s['name'] }}</span>
                        <span class="meta">#{{ str_pad($s['employee_id'], 4, '0', STR_PAD_LEFT) }}
                            @if($s['position']) &middot; {{ $s['position'] }} @endif
                            &middot; {{ $s['workdays'] }}d / {{ number_format($s['hours'], 1) }}h</span>
                    </div>

                    <div class="cols">
                        <div>
                            <h6>Earnings</h6>
                            <div class="ln"><span class="k">Regular</span><span class="v">&#8369;{{ number_format($s['regular'], 2) }}</span></div>
                            <div class="ln"><span class="k">Overtime</span><span class="v">&#8369;{{ number_format($s['overtime'], 2) }}</span></div>
                            <div class="ln"><span class="k">Holiday</span><span class="v">&#8369;{{ number_format($s['holidayPay'], 2) }}</span></div>
                            <div class="ln"><span class="k">Rest Day</span><span class="v">&#8369;{{ number_format($s['restDayPay'], 2) }}</span></div>
                            <div class="ln"><span class="k">Bonus</span><span class="v">&#8369;{{ number_format($s['bonus'], 2) }}</span></div>
                            <div class="ln sum"><span class="k">Gross</span><span class="v">&#8369;{{ number_format($s['gross'], 2) }}</span></div>
                        </div>
                        <div>
                            <h6>Deductions</h6>
                            <div class="ln"><span class="k">SSS</span><span class="v">&#8369;{{ number_format($s['ded']['sss'], 2) }}</span></div>
                            <div class="ln"><span class="k">PhilHealth</span><span class="v">&#8369;{{ number_format($s['ded']['philhealth'], 2) }}</span></div>
                            <div class="ln"><span class="k">PAG-IBIG</span><span class="v">&#8369;{{ number_format($s['ded']['pagibig'], 2) }}</span></div>
                            <div class="ln"><span class="k">Vale/Utang</span><span class="v">&#8369;{{ number_format($s['ded']['vale'], 2) }}</span></div>
                            <div class="ln"><span class="k">Other</span><span class="v">&#8369;{{ number_format($s['ded']['other'], 2) }}</span></div>
                            <div class="ln sum"><span class="k">Total</span><span class="v">&#8369;{{ number_format($s['totalDeductions'], 2) }}</span></div>
                        </div>
                    </div>

                    <div class="net">
                        <span class="k">NET PAY</span>
                        <span class="v">&#8369;{{ number_format($s['net'], 2) }}</span>
                    </div>

                    <div class="sign">
                        <div class="box"><div class="line">Received by (Employee)</div></div>
                        <div class="box"><div class="line">Approved by (Admin)</div></div>
                    </div>
                </div>
            @empty
                <div class="empty">Walang employee record para sa period na ito.</div>
            @endforelse
        </div>
    </div>

    <script>
        // Auto-open the print dialog once, when opened as a print tab.
        window.addEventListener('load', function () {
            if (new URLSearchParams(location.search).get('print') !== '0') {
                setTimeout(function () { window.print(); }, 350);
            }
        });
    </script>
</body>
</html>
