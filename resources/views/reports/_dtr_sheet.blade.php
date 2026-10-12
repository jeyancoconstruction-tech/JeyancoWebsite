{{--
    One worker's Daily Time Record, as a page (Michael, 2026-10-12).

    Shown in the pop-up on Payroll Reports › Employees DTR and drawn by
    dompdf for "Download PDF" (reports/dtr-pdf), so the two cannot differ.
    That is also why it is laid out with tables and nothing newer: dompdf has
    no flex or grid. The colours are each page's own — theme tokens on
    screen, plain ink in the PDF.

    Expects what PayrollReportController::sheet() gives: $e, $code, $days,
    $totals, $period, $labor, $site, $shift, $company, $generated. Every
    figure is DtrService's.
--}}
@php
    use App\Support\WorkSchedule;
    use Carbon\Carbon;

    // A length of time, in whole minutes: nothing where the day has none to
    // give, a dash where it has and it is zero.
    $dur = fn ($m) => $m === null ? '' : ($m > 0 ? WorkSchedule::duration($m) : '—');
    $hasEdit = collect($days)->contains(fn ($d) => ! empty($d['edited']));
    $address = \App\Models\SystemSetting::current()->address ?? null;
@endphp
<div class="dtr-sheet">
    <table class="dtr-top">
        <tr>
            <td>
                <div class="dtr-co">{{ $company }}</div>
                @if($address)<div class="dtr-addr">{{ $address }}</div>@endif
                <div class="dtr-doc">{{ __('Daily Time Record') }}</div>
            </td>
            <td class="r">
                <div class="dtr-k">{{ __('Period') }}</div>
                <div class="dtr-per">{{ $period }}</div>
            </td>
        </tr>
    </table>

    <table class="dtr-info">
        <tr>
            <th>{{ __('Employee') }}</th>
            <td><b>{{ $e->name }}</b></td>
            <th>{{ __('Employee ID') }}</th>
            <td>{{ $code }}</td>
        </tr>
        <tr>
            <th>{{ __('Labor type') }}</th>
            <td>{{ $labor }}</td>
            <th>{{ __('Site') }}</th>
            <td>{{ $site }}</td>
        </tr>
        <tr>
            <th>{{ __('Shift schedule') }}</th>
            <td colspan="3">{{ $shift }}</td>
        </tr>
    </table>

    <table class="dtr-days">
        <thead>
            <tr>
                <th rowspan="2" class="l">{{ __('Date') }}</th>
                <th colspan="2">{{ __('1st session') }}</th>
                <th colspan="2">{{ __('2nd session') }}</th>
                <th rowspan="2">{{ __('Hours') }}</th>
                <th rowspan="2">{{ __('Late') }}</th>
                <th rowspan="2">{{ __('Undertime') }}</th>
                <th rowspan="2">{{ __('OT') }}</th>
                <th rowspan="2">{{ __('Night diff') }}</th>
                <th rowspan="2" class="l rem">{{ __('Remarks') }}</th>
            </tr>
            <tr>
                <th>{{ __('In') }}</th>
                <th>{{ __('Out') }}</th>
                <th>{{ __('In') }}</th>
                <th>{{ __('Out') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($days as $d)
                @php
                    $on = Carbon::parse($d['date']);
                    // A day on the list gets a dash where nothing was scanned;
                    // one before the worker was taken on, or still ahead, is left empty.
                    $blank = $d['remark'] === '' ? '' : '—';
                @endphp
                <tr class="is-{{ $d['kind'] }}">
                    <td class="l date"><b>{{ $on->format('M j') }}</b><span>{{ $on->format('D') }}</span></td>
                    @foreach(['in1', 'out1', 'in2', 'out2'] as $slot)
                        @if(in_array($slot, $d['miss'], true))
                            <td class="miss">{{ __('No scan') }}</td>
                        @elseif($d[$slot])
                            <td>{{ $d[$slot] }}@if(in_array($slot, $d['edited'], true))*@endif</td>
                        @else
                            <td class="z">{{ $blank }}</td>
                        @endif
                    @endforeach
                    <td class="{{ $d['worked'] ? '' : 'z' }}">{{ $dur($d['worked']) }}</td>
                    <td class="{{ $d['late'] ? 'late' : 'z' }}">{{ $dur($d['late']) }}</td>
                    <td class="{{ $d['under'] ? '' : 'z' }}">{{ $dur($d['under']) }}</td>
                    <td class="{{ $d['ot'] ? 'ot' : 'z' }}">{{ $dur($d['ot']) }}</td>
                    <td class="{{ $d['night'] ? '' : 'z' }}">{{ $dur($d['night']) }}</td>
                    <td class="l rem">@if($d['remark'] !== '')<span class="dtr-rem {{ $d['tone'] }}">{{ __($d['remark']) }}</span>@endif @if($d['note'])<small>{{ __($d['note']) }}</small>@endif</td>
                </tr>
            @endforeach
            <tr class="total">
                <td class="l" colspan="5">{{ __('Total') }} · {{ $totals['days'] }} {{ $totals['days'] === 1 ? __('day worked') : __('days worked') }}</td>
                <td>{{ $dur($totals['worked']) }}</td>
                <td class="{{ $totals['late'] ? 'late' : 'z' }}">{{ $dur($totals['late']) }}</td>
                <td class="{{ $totals['under'] ? '' : 'z' }}">{{ $dur($totals['under']) }}</td>
                <td class="{{ $totals['ot'] ? 'ot' : 'z' }}">{{ $dur($totals['ot']) }}</td>
                <td class="{{ $totals['night'] ? '' : 'z' }}">{{ $dur($totals['night']) }}</td>
                <td class="l rem">{{ $totals['absent'] }} {{ $totals['absent'] === 1 ? __('absence') : __('absences') }} · {{ $totals['missing'] }} {{ $totals['missing'] === 1 ? __('missing scan') : __('missing scans') }}</td>
            </tr>
        </tbody>
    </table>
    @if($hasEdit)
        <p class="dtr-note">* {{ __('Time out set by the office.') }}</p>
    @endif

    <table class="dtr-sign">
        <tr>
            <td>
                <div class="line"></div>
                <b>{{ $e->name }}</b>
                <span>{{ __('Employee') }}</span>
            </td>
            <td class="gap"></td>
            <td>
                <div class="line"></div>
                <b>&nbsp;</b>
                <span>{{ __('Verified by') }}</span>
            </td>
        </tr>
    </table>

    <div class="dtr-gen">{{ __('Generated') }} {{ $generated->format('M j, Y g:i A') }} · {{ __('Regular hours') }} {{ $dur($totals['regular']) }}</div>
</div>
