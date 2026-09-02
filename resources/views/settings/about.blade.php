@extends('layouts')

@section('page_title', 'Company')

@push('styles')
@include('settings._system-styles')
@endpush

@section('content')
<div class="sy-page">

    <div class="sy-header mb-3">
        <h1>{{ __('Company') }}</h1>
        <p>{{ __('Who the company says it is on a payslip, a receipt and an export.') }}</p>
    </div>

    <div class="hub">
    @include('settings._hub')
    <div>

    @if(session('success'))
        <div class="alert alert-success d-flex align-items-center gap-2" role="alert">
            <i class="fas fa-circle-check"></i> {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger" role="alert">
            <strong>{{ __('Nothing was saved.') }}</strong>
            <ul class="mb-0 mt-1">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('system-settings.about.update') }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <div class="sy-card">
            <div class="sy-card-head">
                <i class="fas fa-building"></i>
                <div>
                    <h6>{{ __('Company identity') }}</h6>
                    <p>What the payslips, the receipt and the exports say the company is</p>
                </div>
            </div>
            <div class="sy-card-body">
                <div class="sy-grid">
                    <div class="sy-field">
                        <label for="company_name">Company name</label>
                        <input type="text" class="sy-input" id="company_name" name="company_name"
                               value="{{ old('company_name', $system->company_name) }}" required>
                    </div>
                    <div class="sy-field">
                        <label for="company_tagline">Line under the name</label>
                        <input type="text" class="sy-input" id="company_tagline" name="company_tagline"
                               value="{{ old('company_tagline', $system->company_tagline) }}" required>
                    </div>
                    <div class="sy-field" style="grid-column: 1 / -1;">
                        <label for="company_address">Address <span class="text-muted">(optional)</span></label>
                        <input type="text" class="sy-input" id="company_address" name="company_address"
                               placeholder="Not printed unless set"
                               value="{{ old('company_address', $system->company_address) }}">
                    </div>
                </div>

                <div class="sy-logo-row mt-3">
                    <img class="sy-logo-preview" src="{{ $system->logoUrl() }}" alt="Current logo">
                    <div class="sy-field flex-grow-1" style="min-width:220px;">
                        <label for="logo">Logo</label>
                        <input type="file" class="sy-input" id="logo" name="logo" accept="image/*">
                    </div>
                </div>

                <p class="sy-hint">
                    The name and the line under it print on every payslip and on the receipt in Payroll
                    Records. Leave the logo alone to keep the one shown; uploading replaces it everywhere.
                </p>
            </div>
        </div>

        <div class="sy-foot">
            <span class="sy-updated">
                <i class="fas fa-clock-rotate-left"></i>
                @if($system->exists)
                    Last updated {{ $system->updated_at?->format('Y-m-d') }}
                @else
                    {{ __('Never changed — showing the built-in defaults') }}
                @endif
            </span>
            <button type="submit" class="sy-save"><i class="fas fa-floppy-disk me-1"></i> {{ __('Save') }}</button>
        </div>
    </form>
    </div>
    </div>
</div>
@endsection
