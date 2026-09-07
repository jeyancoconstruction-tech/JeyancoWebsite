@extends('layouts')
@section('page_title', 'Payslip')

@section('content')
<div class="mod-page">
    <div class="mod-head no-print">
        <div>
            <h1 class="mod-title">{{ __('Payslip') }}</h1>
            <p class="mod-sub">{{ $item->employee_name }} &middot; {{ $run->code }} &middot; {{ $run->period_label }}</p>
        </div>
        <div class="mod-head-actions">
            <a class="mod-btn" href="{{ route('payslips.index', ['run' => $run->id]) }}">
                <i class="fas fa-arrow-left"></i> {{ __('All Payslips') }}
            </a>
            <a class="mod-btn primary" href="{{ route('payslips.print', $item) }}" target="_blank" rel="noopener">
                <i class="fas fa-print"></i> {{ __('Print / Save as PDF') }}
            </a>
        </div>
    </div>

    @include('payslips._slip', ['item' => $item, 'run' => $run, 'company' => $company])
</div>

@include('modules._kit')
@include('payslips._slip_styles')
@endsection

