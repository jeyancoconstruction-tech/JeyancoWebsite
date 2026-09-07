{{-- One payslip. Shared by the on-screen view, the single print and the batch
     print, so the three can never disagree about what a slip says. --}}
<div class="slip">
    <div class="slip-head">
        <div class="slip-brand">
            <img class="slip-logo" src="{{ $company?->logoUrl() ?? asset('images/JeyancoLogo.png') }}" alt="">
            <div>
                <div class="slip-company">{{ $company?->company_name ?? 'Jeyanco Construction' }}</div>
                @if($company?->address)<div class="slip-addr">{{ $company->address }}</div>@endif
            </div>
        </div>
        <div class="slip-meta">
            <div class="slip-doc">{{ __('PAYSLIP') }}</div>
            <div class="slip-ref">{{ $run->code }}</div>
            <div class="slip-period">{{ $run->period_label }}</div>
        </div>
    </div>

    <div class="slip-emp">
        <div><span>{{ __('Employee') }}</span><strong>{{ $item->employee_name ?: ($item->employee->name ?? '—') }}</strong></div>
        <div><span>{{ __('Position') }}</span><strong>{{ $item->position ?: '—' }}</strong></div>
        <div><span>{{ __('Project / Site') }}</span><strong>{{ $item->site->name ?? '—' }}</strong></div>
        <div><span>{{ __('Days Worked') }}</span><strong>{{ rtrim(rtrim(number_format($item->days_worked, 2), '0'), '.') }}</strong></div>
    </div>

    <div class="slip-cols">
        <div class="slip-col">
            <div class="slip-col-head">{{ __('Earnings') }}</div>
            @forelse($item->earningLines() as $label => $amount)
                <div class="slip-line"><span>{{ $label }}</span><b>{{ number_format($amount, 2) }}</b></div>
            @empty
                <div class="slip-line muted"><span>{{ __('No earnings') }}</span><b>0.00</b></div>
            @endforelse
            <div class="slip-total"><span>{{ __('Gross Pay') }}</span><b>₱{{ number_format($item->gross_pay, 2) }}</b></div>
        </div>

        <div class="slip-col">
            <div class="slip-col-head">{{ __('Deductions') }}</div>
            @forelse($item->deductionLines() as $label => $amount)
                <div class="slip-line"><span>{{ $label }}</span><b>{{ number_format($amount, 2) }}</b></div>
            @empty
                <div class="slip-line muted"><span>{{ __('No deductions') }}</span><b>0.00</b></div>
            @endforelse
            <div class="slip-total"><span>{{ __('Total Deductions') }}</span><b>₱{{ number_format($item->total_deductions, 2) }}</b></div>
        </div>
    </div>

    <div class="slip-net">
        <span>{{ __('NET PAY') }}</span>
        <b>₱{{ number_format($item->net_pay, 2) }}</b>
    </div>

    <div class="slip-foot">
        <div class="slip-sign"><span></span>{{ __('Received by') }}</div>
        <div class="slip-issued">{{ __('Issued') }} {{ now()->format('M d, Y') }}</div>
    </div>
</div>

