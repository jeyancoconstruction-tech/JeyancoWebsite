{{-- Payroll Records › Sheet view (2026-11-25). The week's breakdown laid out
     as a spreadsheet: column letters, row numbers, a formula bar and a status
     bar. Every figure is the one PayrollService computed — the formulas show
     how each was made, they do not compute anything of their own. Only the
     Administrator can change a cell, and only Bonus, only in the pay period
     running now: it adds a bonus grant (the same record Employees › Bonus
     makes), so the payroll pays it the way it pays any other.
     Included from payroll-records.blade.php (weekly view only). --}}
@php
    $shCols = [['k' => 'name', 'h' => __('Employee')], ['k' => 'shift', 'h' => __('Shift')], ['k' => 'days', 'h' => __('Days')],
               ['k' => 'hours', 'h' => __('Hours')], ['k' => 'rate', 'h' => __('Rate / hr')], ['k' => 'regular', 'h' => __('Basic')],
               ['k' => 'overtime', 'h' => __('Overtime')]];
    if ($showNight) { $shCols[] = ['k' => 'night', 'h' => __('Night diff')]; }
    $shCols[] = ['k' => 'rest', 'h' => __('Rest day')];
    $shCols[] = ['k' => 'holiday', 'h' => __('Holiday')];
    if ($showLeave) { $shCols[] = ['k' => 'leave', 'h' => __('Paid leave')]; }
    foreach ([['gross', __('Gross')], ['ded', __('Deductions')], ['bonus', __('Bonus')], ['net', __('Net pay')]] as [$k, $h]) { $shCols[] = ['k' => $k, 'h' => $h]; }
    $L = [];
    foreach ($shCols as $i => $c) { $L[$c['k']] = chr(65 + $i); }
    $earnKeys  = array_values(array_filter(array_column($shCols, 'k'), fn ($k) => in_array($k, ['regular', 'overtime', 'night', 'rest', 'holiday', 'leave'], true)));
    $firstEarn = $L[$earnKeys[0]];
    $lastEarn  = $L[end($earnKeys)];
    $num = fn ($v) => number_format((float) $v, 2, '.', ',');
    $lastRow = count($employees) + 1;

    // The pay period running now is the only one a bonus can be added to.
    $shToday  = now()->toDateString();
    $canBonus = (bool) auth()->user()?->isAdmin() && $shToday >= $start->toDateString() && $shToday <= $end->toDateString();
    $bonusWhy = ! auth()->user()?->isAdmin() ? __('Only the Administrator can change a cell.')
              : ($canBonus ? __('Double-click to add a bonus for this week.') : __('This week is closed: a bonus can be added only to the pay period running now.'));

    $sheetRows = [];
    foreach (array_values($employees) as $i => $emp) {
        $s = $slips[$emp['employee_id']];
        $r = $i + 2;
        $E = $L['rate'] . $r;
        $parts = array_filter(['SSS' => $s['sss'], 'PhilHealth' => $s['phil'], 'Pag-IBIG' => $s['pag'], 'Tax' => $s['tax'],
                               'Vale' => $s['vale'], 'Advance' => $s['advance'], 'Other' => $s['other']], fn ($v) => (float) $v > 0);
        $dedF = $parts ? '=−(' . implode(' + ', array_map(fn ($k, $v) => $k . ' ' . $num($v), array_keys($parts), $parts)) . ')' : '=0';
        $sheetRows[] = ['id' => $s['id'], 'r' => $r, 'cells' => [
            'name'     => ['t' => $s['name'], 'sub' => $s['code'], 'f' => __('From the employee record'), 'lock' => true],
            'shift'    => ['t' => $s['shift'] ?? '—', 'f' => __('From the employee\'s shift'), 'lock' => true],
            'days'     => ['t' => $s['workdays'], 'v' => $s['workdays'], 'f' => __('Days worked, from attendance'), 'lock' => true],
            'hours'    => ['t' => $s['duration'], 'sub' => $s['otDur'] !== '' ? '+' . $s['otDur'] . ' OT' : '', 'f' => __('Hours worked, from attendance'), 'lock' => true],
            'rate'     => ['v' => $s['rate'], 'f' => __('From the labor type') . ' · ' . $peso($s['dailyRate']) . '/' . __('day') . ' ÷ 8', 'lock' => true],
            'regular'  => ['v' => $s['regular'], 'f' => '=ROUND(regular hours × ' . $E . ', 2)'],
            'overtime' => ['v' => $s['overtime'], 'f' => '=ROUND(OT hours × ' . $E . ' × ' . $otPct . '%, 2)'],
            'night'    => ['v' => $s['night'], 'f' => '=ROUND(night hours × ' . $E . ' × night differential, 2)'],
            'rest'     => ['v' => $s['rest'], 'f' => '=ROUND(rest-day hours × ' . $E . ' × ' . $restPct . '%, 2)'],
            'holiday'  => ['v' => $s['holiday'], 'f' => '=ROUND(holiday hours × ' . $E . ' × holiday rate, 2)'],
            'leave'    => ['v' => $s['leave'], 'f' => '=ROUND(leave days × daily rate, 2)'],
            'gross'    => ['v' => $s['gross'], 'f' => '=SUM(' . $firstEarn . $r . ':' . $lastEarn . $r . ')', 'strong' => true],
            'ded'      => ['v' => -$s['ded'], 'f' => $dedF, 'neg' => true],
            'bonus'    => ['v' => $s['bonus'], 'f' => $s['bonus'] ? __('Bonus grants this week') : __('No bonus this week'), 'edit' => true],
            'net'      => ['v' => $s['net'], 'f' => '=' . $L['gross'] . $r . '+' . $L['ded'] . $r . '+' . $L['bonus'] . $r, 'strong' => true, 'net' => true],
        ]];
    }
    $totals = ['days' => $summary['workdays'], 'regular' => $sumOf('regular'), 'overtime' => $summary['overtime'], 'night' => $sumOf('night'),
               'rest' => $summary['restDayPay'] ?? 0, 'holiday' => $summary['holidayPay'], 'leave' => $sumOf('leave'),
               'gross' => $summary['gross'], 'ded' => -$summary['totalDeductions'], 'bonus' => $summary['bonus'], 'net' => $summary['net']];
@endphp

<div class="psh" id="prSheet" hidden data-can-bonus="{{ $canBonus ? 1 : 0 }}"
     data-bonus-url="{{ route('employees.bonus.store', ['employee' => '__ID__']) }}" data-bonus-why="{{ $bonusWhy }}">
    <div class="psh-fbar">
        <span class="psh-name" id="pshName">{{ $L['net'] }}2</span>
        <span class="psh-fx">fx</span>
        <span class="psh-f" id="pshF">{{ __('Pick a cell to see how it is computed') }}</span>
        <span class="psh-val" id="pshV"></span>
    </div>
    <div class="psh-wrap" data-fill-scroll>
        <table class="psh-grid">
            <thead>
                <tr><th class="psh-corner"></th>@foreach($shCols as $c)<th class="psh-col" data-c="{{ $L[$c['k']] }}">{{ $L[$c['k']] }}</th>@endforeach</tr>
            </thead>
            <tbody>
                <tr class="psh-head"><th class="psh-rn">1</th>@foreach($shCols as $c)<td>{{ $c['h'] }}</td>@endforeach</tr>
                @forelse($sheetRows as $row)
                    <tr data-emp="{{ $row['id'] }}">
                        <th class="psh-rn">{{ $row['r'] }}</th>
                        @foreach($shCols as $c)
                            @php
                                $cell = $row['cells'][$c['k']];
                                $isMoney = array_key_exists('v', $cell) && ! isset($cell['t']);
                                $v = (float) ($cell['v'] ?? 0);
                                $cls = trim(($isMoney || isset($cell['v']) ? 'n' : 't')
                                    . (! empty($cell['lock']) ? ' lock' : '')
                                    . (! empty($cell['strong']) ? ' strong' : '')
                                    . (! empty($cell['neg']) && $v ? ' neg' : '')
                                    . (! empty($cell['net']) ? ' netc' : '')
                                    . (! empty($cell['edit']) ? ' edit' . ($canBonus ? ' can' : '') : '')
                                    . ($isMoney && ! $v ? ' zero' : ''));
                            @endphp
                            <td class="{{ $cls }}" tabindex="-1" data-c="{{ $L[$c['k']] }}" data-r="{{ $row['r'] }}"
                                data-f="{{ $cell['f'] }}" @if(isset($cell['v'])) data-v="{{ $v }}" @endif>@if($isMoney){{ $v ? ($v < 0 ? '−' : '') . $peso(abs($v)) : '0.00' }}@else{{ $cell['t'] }}@if(! empty($cell['sub']))<small>{{ $cell['sub'] }}</small>@endif @endif</td>
                        @endforeach
                    </tr>
                @empty
                    <tr><th class="psh-rn">2</th><td class="t" colspan="{{ count($shCols) }}">{{ __('No payroll records for this week.') }}</td></tr>
                @endforelse
                @if(count($sheetRows))
                    <tr class="psh-tot">
                        <th class="psh-rn">{{ $lastRow + 1 }}</th>
                        @foreach($shCols as $c)
                            @php $tk = $c['k']; @endphp
                            @if($tk === 'name')
                                <td class="t strong" data-c="A" data-r="{{ $lastRow + 1 }}" data-f="{{ __('Total') }} · {{ count($sheetRows) }}">{{ __('Total') }} · {{ count($sheetRows) }}</td>
                            @elseif(array_key_exists($tk, $totals))
                                <td class="n strong{{ $tk === 'ded' && $totals[$tk] ? ' neg' : '' }}" tabindex="-1" data-c="{{ $L[$tk] }}" data-r="{{ $lastRow + 1 }}"
                                    data-f="=SUM({{ $L[$tk] }}2:{{ $L[$tk] }}{{ $lastRow }})" data-v="{{ (float) $totals[$tk] }}">@if($tk === 'days'){{ $totals[$tk] }}@else{{ $totals[$tk] < 0 ? '−' : '' }}{{ $peso(abs((float) $totals[$tk])) }}@endif</td>
                            @else
                                <td></td>
                            @endif
                        @endforeach
                    </tr>
                @endif
            </tbody>
        </table>
    </div>
    <div class="psh-status">
        <span class="psh-leg"><i class="lk"></i>{{ __('From attendance') }}</span>
        <span class="psh-leg"><i></i>{{ __('Formula') }}</span>
        <span class="psh-leg"><i class="ed"></i>{{ __('Admin can edit') }}</span>
        <span class="sp"></span>
        <span id="pshSel">—</span>
        <span>{{ __('Sum') }}: <b id="pshSum">—</b></span>
        <span>{{ __('Count') }}: <b id="pshCount">0</b></span>
        <span>{{ __('Average') }}: <b id="pshAvg">—</b></span>
    </div>

    {{-- Add a bonus (Administrator, pay period running now). Posts to the
         same Employees › Bonus action, so it is logged and paid the same way. --}}
    <form method="POST" class="psh-pop" id="pshPop" hidden>
        @csrf
        <b id="pshPopWho">{{ __('Add a bonus') }}</b>
        <label>{{ __('Amount') }}<input type="number" name="amount" min="0.01" step="0.01" required></label>
        <label>{{ __('Reason') }}<input type="text" name="note" maxlength="160" required placeholder="{{ __('e.g. performance bonus') }}"></label>
        <small>{{ __('Added to this week\'s pay after deductions. Recorded in the Audit Log.') }}</small>
        <div class="psh-popb">
            <button type="button" class="prx-btn" data-psh-cancel>{{ __('Cancel') }}</button>
            <button type="submit" class="prx-btn pri">{{ __('Add bonus') }}</button>
        </div>
    </form>
</div>
