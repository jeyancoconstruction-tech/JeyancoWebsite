@extends('layouts')
@section('page_title', 'Payslip')

@section('content')
<div class="mod-page">
    <x-page-header :title="__('Payslip')" class="no-print">
        <x-slot:actions>
            <a class="mod-btn" href="{{ route('payslips.index', ['run' => $run->id]) }}">
                <i class="fas fa-arrow-left"></i> {{ __('All Payslips') }}
            </a>
            <a class="mod-btn primary" href="{{ route('payslips.print', $item) }}" target="_blank" rel="noopener">
                <i class="fas fa-print"></i> {{ __('Print / Save as PDF') }}
            </a>
        </x-slot:actions>
    </x-page-header>

    @include('payslips._slip', ['item' => $item, 'run' => $run, 'company' => $company])
</div>

@include('modules._kit')
@include('payslips._slip_styles')
@endsection

