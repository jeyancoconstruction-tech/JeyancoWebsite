{{-- A worker's DTR for Excel: the days on the sheet, as text — an HTML table
     under an .xls name, the way reports/export writes (the deployment image
     has no ext-zip for a real .xlsx). The figures are DtrService's, written
     as the page writes them. --}}
@php
    use App\Support\WorkSchedule;
    use Carbon\Carbon;

    $dur  = fn ($m) => $m === null ? '' : ($m > 0 ? WorkSchedule::duration($m) : '—');
    $head = 'background:#e8eef7';
@endphp
<html>
<head><meta charset="UTF-8"></head>
<body>
<table border="1" cellspacing="0" cellpadding="4">
    <tr><td colspan="12"><b>{{ $company }}</b></td></tr>
    <tr><td colspan="12"><b>{{ __('Daily Time Record') }}</b> · {{ $period }}</td></tr>
    <tr><td colspan="2"><b>{{ __('Employee') }}</b></td><td colspan="4">{{ $e->name }}</td><td colspan="2"><b>{{ __('Employee ID') }}</b></td><td colspan="4">{{ $code }}</td></tr>
    <tr><td colspan="2"><b>{{ __('Labor type') }}</b></td><td colspan="4">{{ $labor }}</td><td colspan="2"><b>{{ __('Site') }}</b></td><td colspan="4">{{ $site }}</td></tr>
    <tr><td colspan="2"><b>{{ __('Shift schedule') }}</b></td><td colspan="10">{{ $shift }}</td></tr>
    <tr>
        @foreach(['Date', 'Day', '1st session in', '1st session out', '2nd session in', '2nd session out', 'Hours', 'Late', 'Undertime', 'OT', 'Night diff', 'Remarks'] as $h)
            <th style="{{ $head }}">{{ __($h) }}</th>
        @endforeach
    </tr>
    @foreach($days as $d)
        @php $on = Carbon::parse($d['date']); @endphp
        <tr>
            <td>{{ $on->format('M j, Y') }}</td>
            <td>{{ $on->format('D') }}</td>
            @foreach(['in1', 'out1', 'in2', 'out2'] as $slot)
                <td>{{ in_array($slot, $d['miss'], true) ? __('No scan') : ($d[$slot] ?? '') }}</td>
            @endforeach
            <td>{{ $dur($d['worked']) }}</td>
            <td>{{ $dur($d['late']) }}</td>
            <td>{{ $dur($d['under']) }}</td>
            <td>{{ $dur($d['ot']) }}</td>
            <td>{{ $dur($d['night']) }}</td>
            <td>{{ implode(' · ', array_filter([__($d['remark']), $d['note'] ? __($d['note']) : null])) }}</td>
        </tr>
    @endforeach
    <tr>
        <td colspan="6"><b>{{ __('Total') }} · {{ $totals['days'] }} {{ $totals['days'] === 1 ? __('day worked') : __('days worked') }}</b></td>
        <td><b>{{ $dur($totals['worked']) }}</b></td>
        <td><b>{{ $dur($totals['late']) }}</b></td>
        <td><b>{{ $dur($totals['under']) }}</b></td>
        <td><b>{{ $dur($totals['ot']) }}</b></td>
        <td><b>{{ $dur($totals['night']) }}</b></td>
        <td><b>{{ $totals['absent'] }} {{ $totals['absent'] === 1 ? __('absence') : __('absences') }} · {{ $totals['missing'] }} {{ $totals['missing'] === 1 ? __('missing scan') : __('missing scans') }}</b></td>
    </tr>
    <tr><td colspan="12"></td></tr>
    <tr><td colspan="6">{{ __('Employee') }}: ______________________________</td><td colspan="6">{{ __('Verified by') }}: ______________________________</td></tr>
    <tr><td colspan="12">{{ __('Generated') }} {{ $generated->format('M j, Y g:i A') }}</td></tr>
</table>
</body>
</html>
