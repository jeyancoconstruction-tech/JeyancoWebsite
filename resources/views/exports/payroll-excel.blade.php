<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        table { border-collapse: collapse; }
        th, td { border: 0.5pt solid #94a3b8; padding: 4px 8px; font-family: Calibri, Arial, sans-serif; font-size: 11pt; }
        .title { font-size: 15pt; font-weight: bold; color: #ffffff; background: #1e3a8a; text-align: center; }
        .meta  { font-weight: bold; background: #eef2ff; }
        .head  { font-weight: bold; background: #dbe3f4; text-align: center; }
        .num   { mso-number-format: "#,##0.00"; text-align: right; }
        .int   { mso-number-format: "0"; text-align: right; }
        .total { font-weight: bold; background: #f1f5f9; }
    </style>
</head>
<body>
    <table>
        <tr><td class="title" colspan="12">Jeyanco Construction — Payroll Records</td></tr>
        <tr>
            <td class="meta" colspan="3">Period ({{ ucfirst($period['mode']) }})</td>
            <td colspan="7">{{ $period['label'] }}</td>
            <td class="meta" colspan="1">Generated</td>
            <td colspan="1">{{ now()->format('M d, Y') }}</td>
        </tr>
        <tr><td colspan="12"></td></tr>
        <tr class="head">
            <td>Employee ID</td><td>Name</td><td>Position</td>
            <td>Workdays</td><td>Hours</td><td>Gross Pay</td><td>Overtime</td>
            <td>Holiday Pay</td><td>Rest Day Pay</td><td>Bonus</td><td>Deductions</td><td>Net Pay</td>
        </tr>
        @foreach($employees as $e)
            @php $t = $e['totals']; @endphp
            <tr>
                <td>#{{ str_pad($e['employee_id'], 4, '0', STR_PAD_LEFT) }}</td>
                <td>{{ $e['name'] }}</td>
                <td>{{ $e['position'] }}</td>
                <td class="int">{{ $t['workdays'] }}</td>
                <td class="num">{{ number_format($t['hours'], 2, '.', '') }}</td>
                <td class="num">{{ number_format($t['gross'], 2, '.', '') }}</td>
                <td class="num">{{ number_format($t['overtime'], 2, '.', '') }}</td>
                <td class="num">{{ number_format($t['holidayPay'], 2, '.', '') }}</td>
                <td class="num">{{ number_format($t['restDayPay'] ?? 0, 2, '.', '') }}</td>
                <td class="num">{{ number_format($t['bonus'], 2, '.', '') }}</td>
                <td class="num">{{ number_format($t['totalDeductions'], 2, '.', '') }}</td>
                <td class="num">{{ number_format($t['net'], 2, '.', '') }}</td>
            </tr>
        @endforeach
        <tr class="total">
            <td colspan="3">TOTAL — {{ $totals['employee_count'] }} employee(s)</td>
            <td class="int">{{ $totals['workdays'] }}</td>
            <td class="num">{{ number_format($totals['hours'], 2, '.', '') }}</td>
            <td class="num">{{ number_format($totals['gross'], 2, '.', '') }}</td>
            <td class="num">{{ number_format($totals['overtime'], 2, '.', '') }}</td>
            <td class="num">{{ number_format($totals['holidayPay'], 2, '.', '') }}</td>
            <td class="num">{{ number_format($totals['restDayPay'], 2, '.', '') }}</td>
            <td class="num">{{ number_format($totals['bonus'], 2, '.', '') }}</td>
            <td class="num">{{ number_format($totals['totalDeductions'], 2, '.', '') }}</td>
            <td class="num">{{ number_format($totals['net'], 2, '.', '') }}</td>
        </tr>
    </table>
</body>
</html>
