{{-- A payroll report on paper. Print / PDF on Payroll Reports shows this page
     in a frame and prints it from there, so the preview is the printout. The
     cells are PayrollReportController::fmt's, as on screen and in Excel. --}}
@php
    use App\Http\Controllers\PayrollReportController as R;

    $isDtr      = $report === 'dtr';
    $periodText = $isDtr ? $period['label'] : \App\Services\DtrService::label($fromC, $toC);
    $wide       = count($table['cols']) > 7;
    $isZero     = fn ($val) => ! is_array($val) && is_numeric($val) && (float) $val == 0.0;
    $text = function (array $col, $val) use ($isZero) {
        if (in_array($col[2], ['money0', 'net0', 'dur0', 'int0'], true) && $isZero($val)) {
            return '';
        }
        return R::fmt($col[2], $val);
    };
    $logo = \App\Models\SystemSetting::current()?->logoUrl() ?? asset('images/JeyancoLogo.png');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $reports[$report] }} — {{ $periodText }}</title>
<style>
    @page { size: letter {{ $wide ? 'landscape' : 'portrait' }}; margin: 12mm; }
    * { box-sizing: border-box; }
    html, body { margin: 0; padding: 0; color: #111827; font-family: "Segoe UI", Roboto, Helvetica, Arial, sans-serif; font-size: 11px; }
    @media screen {
        html { background: #e5e7eb; }
        .paper { width: {{ $wide ? '279mm' : '216mm' }}; min-height: {{ $wide ? '216mm' : '279mm' }}; margin: 16px auto; padding: 12mm; background: #fff; box-shadow: 0 6px 24px rgba(15, 23, 42, .14); }
    }
    @media print { .paper { width: auto; } }

    .head { display: flex; align-items: center; gap: 12px; border-bottom: 2px solid #1E5C9B; padding-bottom: 10px; margin-bottom: 12px; }
    .head img { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; }
    .head .co { flex: 1; }
    .head .co b { display: block; font-size: 15px; color: #1E5C9B; letter-spacing: .02em; }
    .head .co small { color: #6b7280; font-size: 10.5px; }
    .head .doc { text-align: right; }
    .head .doc b { display: block; font-size: 13px; letter-spacing: .08em; text-transform: uppercase; }
    .head .doc small { color: #4b5563; font-size: 11px; }

    .sum { display: flex; border: 1px solid #d1d5db; border-radius: 4px; margin-bottom: 12px; }
    .sum div { flex: 1; padding: 6px 10px; border-left: 1px solid #d1d5db; }
    .sum div:first-child { border-left: 0; }
    .sum span { display: block; font-size: 9.5px; color: #6b7280; font-weight: 600; }
    .sum b { font-size: 12.5px; font-variant-numeric: tabular-nums; }

    table { width: 100%; border-collapse: collapse; }
    th, td { padding: 5px 7px; border-bottom: 1px solid #e5e7eb; text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
    th { font-size: 9.5px; color: #4b5563; font-weight: 700; border-bottom: 1.5px solid #9ca3af; }
    th.l, td.l { text-align: left; }
    td small { display: block; color: #6b7280; font-size: 9.5px; }
    td.net { font-weight: 700; }
    td.ded { color: #b91c1c; }
    tbody tr { break-inside: avoid; }
    thead { display: table-header-group; }
    tfoot td { font-weight: 800; border-top: 1.5px solid #6b7280; border-bottom: 0; background: #f3f4f6; }
    .wk { display: inline-flex; gap: 2px; }
    .wk i { width: 13px; height: 13px; border: 1px solid #d1d5db; border-radius: 2px; font-style: normal; font-size: 7.5px; line-height: 11px; text-align: center; color: #6b7280; }
    .wk i.ok { background: #dcfce7; border-color: #86efac; color: #166534; }
    .wk i.abs { background: #fee2e2; border-color: #fca5a5; color: #991b1b; }
    .wk i.miss { background: #fef3c7; border-color: #fcd34d; color: #92400e; }
    .wk i.off { border-style: dashed; }
    .empty { text-align: center; color: #6b7280; padding: 24px; }

    .sign { display: flex; gap: 40px; margin-top: 34px; }
    .sign div { flex: 1; text-align: center; border-top: 1px solid #111827; padding-top: 4px; font-size: 9.5px; letter-spacing: .06em; text-transform: uppercase; color: #4b5563; }
    .gen { margin-top: 14px; font-size: 9.5px; color: #6b7280; }
    * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
</style>
</head>
<body>
<div class="paper">
    <div class="head">
        <img src="{{ $logo }}" alt="">
        <div class="co"><b>{{ $company }}</b><small>{{ __('Payroll Reports') }}@if($siteId) · {{ $sites->firstWhere('id', $siteId)?->name }}@endif @if($q !== '') · “{{ $q }}”@endif</small></div>
        <div class="doc"><b>{{ $reports[$report] }}</b><small>{{ $periodText }}</small></div>
    </div>

    <div class="sum">
        @foreach($kpis as [$label, $value])
            <div><span>{{ __($label) }}</span><b>{{ $value }}</b></div>
        @endforeach
    </div>

    <table>
        <thead>
            <tr>
                @foreach($table['cols'] as $col)
                    <th class="{{ ! empty($col[3]) ? 'l' : '' }}">{{ __($col[1]) }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($table['rows'] as $row)
                <tr>
                    @foreach($table['cols'] as $col)
                        @php $val = $row[$col[0]] ?? null; $zero = $isZero($val); @endphp
                        <td class="{{ trim((! empty($col[3]) ? 'l ' : '') . (in_array($col[2], ['net', 'net0'], true) && ! $zero ? 'net ' : '') . ($col[2] === 'minus' && ! $zero ? 'ded' : '')) }}">
                            @if($col[2] === 'emp')
                                <b>{{ $val['t'] }}</b><small>{{ $val['sub'] }}</small>
                            @elseif($col[2] === 'pair')
                                {{ $val['top'] }}<small>{{ $val['sub'] }}</small>
                            @elseif($col[2] === 'week')
                                <span class="wk">@foreach($val['days'] ?? [] as $d)<i class="{{ $d['c'] }}">{{ $d['d'] }}</i>@endforeach</span>
                            @else
                                {{ $text($col, $val) }}
                            @endif
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr><td class="empty" colspan="{{ count($table['cols']) }}">{{ __('No records for these filters.') }}</td></tr>
            @endforelse
        </tbody>
        @if($table['rows'])
            <tfoot>
                <tr>
                    @foreach($table['foot'] as $i => $f)
                        @php $col = $table['cols'][$i]; @endphp
                        <td class="{{ $i === 0 ? 'l' : '' }}">{{ $i === 0 ? $f : R::fmt(in_array($col[2], ['net0', 'money0'], true) ? 'money' : $col[2], $f) }}</td>
                    @endforeach
                </tr>
            </tfoot>
        @endif
    </table>

    <div class="sign">
        <div>{{ __('Prepared by') }}</div>
        <div>{{ __('Checked by') }}</div>
        <div>{{ __('Approved by') }}</div>
    </div>
    <div class="gen">{{ __('Generated') }} {{ $generated->format('M j, Y g:i A') }}</div>
</div>
</body>
</html>
