{{-- A payroll report for Excel: the table on screen, as text. The cells are
     written by PayrollReportController::fmt, the same as the page's. --}}
@php use App\Http\Controllers\PayrollReportController as R; @endphp
<html>
<head><meta charset="UTF-8"></head>
<body>
<table border="1" cellspacing="0" cellpadding="4">
    <tr><td colspan="{{ count($table['cols']) }}"><b>{{ $reports[$report] }}</b></td></tr>
    <tr><td colspan="{{ count($table['cols']) }}">{{ $fromC->format('M d, Y') }} – {{ $toC->format('M d, Y') }} · {{ $weeks }} {{ $weeks === 1 ? 'pay week' : 'pay weeks' }} · Generated {{ now('Asia/Manila')->format('M d, Y g:i A') }}</td></tr>
    <tr>
        @foreach($table['cols'] as $col)
            <th style="background:#e8eef7">{{ $col[1] }}</th>
            @if($col[2] === 'emp')<th style="background:#e8eef7">Employee ID</th>@endif
        @endforeach
    </tr>
    @foreach($table['rows'] as $row)
        <tr>
            @foreach($table['cols'] as $col)
                @php $val = $row[$col[0]] ?? null; @endphp
                <td>{{ R::fmt($col[2], $val) }}</td>
                @if($col[2] === 'emp')<td>{{ $val['code'] ?? '' }}</td>@endif
            @endforeach
        </tr>
    @endforeach
    @if($table['rows'])
        <tr>
            @foreach($table['foot'] as $i => $f)
                @php $col = $table['cols'][$i]; @endphp
                <td><b>{{ $i === 0 ? $f : R::fmt(in_array($col[2], ['net0', 'money0'], true) ? 'money' : $col[2], $f) }}</b></td>
                @if($col[2] === 'emp')<td></td>@endif
            @endforeach
        </tr>
    @endif
</table>
</body>
</html>
