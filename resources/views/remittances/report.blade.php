<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    {{-- A month's employee contributions per agency, for Excel. Read by
         RemittanceController::report(); the same shape as the Payroll
         Records export. --}}
    <style>
        table { border-collapse: collapse; }
        th, td { border: 0.5pt solid #94a3b8; padding: 4px 8px; font-family: Calibri, Arial, sans-serif; font-size: 11pt; }
        .title { font-size: 15pt; font-weight: bold; color: #ffffff; background: #1e3a8a; text-align: center; }
        .agency { font-size: 12pt; font-weight: bold; color: #ffffff; background: #334155; }
        .meta  { font-weight: bold; background: #eef2ff; }
        .head  { font-weight: bold; background: #dbe3f4; text-align: center; }
        .num   { mso-number-format: "#,##0.00"; text-align: right; }
        .txt   { mso-number-format: "\@"; }
        .total { font-weight: bold; background: #f1f5f9; }
    </style>
</head>
<body>
    <table>
        <tr><td class="title" colspan="5">{{ __('Jeyanco Construction — Remittances') }} · {{ $month->format('F Y') }}</td></tr>
        <tr>
            <td class="meta">{{ __('Pay weeks') }}</td>
            <td colspan="2">{{ $from->format('M d, Y') }} – {{ $to->format('M d, Y') }}</td>
            <td class="meta">{{ __('Generated') }}</td>
            <td>{{ now('Asia/Manila')->format('M d, Y') }}</td>
        </tr>

        @foreach($sections as $s)
            <tr><td colspan="5"></td></tr>
            <tr>
                <td class="agency" colspan="3">{{ $s['a']['name'] }} — {{ $s['a']['full'] }} ({{ $s['a']['form'] }})</td>
                <td class="agency">{{ __('Due') }}</td>
                <td class="agency">{{ $s['due']->format('M d, Y') }}</td>
            </tr>
            <tr class="head">
                <td>#</td><td>{{ __('Employee ID') }}</td><td>{{ __('Name') }}</td><td>{{ $s['a']['id_label'] }}</td><td>{{ __('Employee contribution') }}</td>
            </tr>
            @forelse($s['people'] as $i => $p)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>#{{ str_pad($p['id'], 4, '0', STR_PAD_LEFT) }}</td>
                    <td>{{ $p['name'] }}</td>
                    <td class="txt">{{ $p['id_number'] !== '' ? $p['id_number'] : __('none on file') }}</td>
                    <td class="num">{{ number_format($p['amount'], 2, '.', '') }}</td>
                </tr>
            @empty
                <tr><td colspan="5">{{ __('No contributions this month.') }}</td></tr>
            @endforelse
            <tr class="total">
                <td colspan="4">{{ __('TOTAL') }} · {{ count($s['people']) }} {{ count($s['people']) === 1 ? __('employee') : __('employees') }}</td>
                <td class="num">{{ number_format($s['total'], 2, '.', '') }}</td>
            </tr>
        @endforeach
    </table>
</body>
</html>
