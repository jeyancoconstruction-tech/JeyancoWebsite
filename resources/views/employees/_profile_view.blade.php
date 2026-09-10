{{--
    The read-only twin of _profile_fields.blade.php.

    View Details shows the record in the same four sections, in the same order,
    with the same labels and the same column widths as Register Employee — so
    the page someone reads is laid out like the page they filled in.

    KEEP IN STEP WITH _profile_fields.blade.php. Adding a field there means
    adding it here. The two are separate because one renders inputs and the
    other renders answers: a disabled input is a poor way to show a value,
    since its placeholder reads as content that was never entered.

    Expects $employee.
--}}
@php
    /* One blank rule for the whole page: an unanswered field says so instead
       of leaving an empty box for the reader to interpret. */
    $show = function ($value, string $blank = 'Not recorded') {
        $value = is_string($value) ? trim($value) : $value;
        return ($value === null || $value === '')
            ? ['text' => __($blank), 'empty' => true]
            : ['text' => $value, 'empty' => false];
    };
@endphp

{{-- ════════════════════ PERSONAL INFORMATION ════════════════════ --}}
<section class="ep-section">
    <header class="ep-section-head">
        <span class="ep-section-icon"><i class="fas fa-user"></i></span>
        <div>
            <h3 class="ep-section-title">{{ __('Personal Information') }}</h3>
            <p class="ep-section-sub">{{ __('Basic details about the worker.') }}</p>
        </div>
    </header>

    <div class="row g-3">
        <div class="col-md-6 col-lg-3">
            <span class="ep-label">{{ __('Date of Birth') }}</span>
            @php $f = $show($employee->birth_date?->format('M d, Y')); @endphp
            <span class="ep-value {{ $f['empty'] ? 'is-empty' : '' }}">
                {{ $f['text'] }}@if(! $f['empty'])<span class="ep-aside">· {{ $employee->birth_date->age }} {{ __('yrs') }}</span>@endif
            </span>
        </div>
        <div class="col-md-6 col-lg-4">
            <span class="ep-label">{{ __('Place of Birth') }}</span>
            @php $f = $show($employee->birth_place); @endphp
            <span class="ep-value {{ $f['empty'] ? 'is-empty' : '' }}">{{ $f['text'] }}</span>
        </div>
        <div class="col-md-4 col-lg-2">
            <span class="ep-label">{{ __('Gender') }}</span>
            @php $f = $show($employee->gender); @endphp
            <span class="ep-value {{ $f['empty'] ? 'is-empty' : '' }}">{{ $f['text'] }}</span>
        </div>
        <div class="col-md-4 col-lg-2">
            <span class="ep-label">{{ __('Civil Status') }}</span>
            @php $f = $show($employee->civil_status); @endphp
            <span class="ep-value {{ $f['empty'] ? 'is-empty' : '' }}">{{ $f['text'] }}</span>
        </div>
        <div class="col-md-4 col-lg-1">
            <span class="ep-label">{{ __('Blood') }}</span>
            @php $f = $show($employee->blood_type, '—'); @endphp
            <span class="ep-value {{ $f['empty'] ? 'is-empty' : '' }}">{{ $f['text'] }}</span>
        </div>
        <div class="col-md-6 col-lg-3">
            <span class="ep-label">{{ __('Nationality') }}</span>
            @php $f = $show($employee->nationality); @endphp
            <span class="ep-value {{ $f['empty'] ? 'is-empty' : '' }}">{{ $f['text'] }}</span>
        </div>
    </div>
</section>

{{-- ════════════════════ CONTACT INFORMATION ════════════════════ --}}
<section class="ep-section">
    <header class="ep-section-head">
        <span class="ep-section-icon"><i class="fas fa-phone"></i></span>
        <div>
            <h3 class="ep-section-title">{{ __('Contact Information') }}</h3>
            <p class="ep-section-sub">{{ __('How to reach the worker, and who to call in an emergency.') }}</p>
        </div>
    </header>

    <div class="row g-3">
        <div class="col-md-6 col-lg-4">
            <span class="ep-label">{{ __('Mobile Number') }}</span>
            @php $f = $show($employee->phone); @endphp
            <span class="ep-value {{ $f['empty'] ? 'is-empty' : '' }}">{{ $f['text'] }}</span>
        </div>
        <div class="col-md-6 col-lg-4">
            <span class="ep-label">{{ __('Email Address') }}</span>
            @php $f = $show($employee->email, '—'); @endphp
            <span class="ep-value {{ $f['empty'] ? 'is-empty' : '' }}">{{ $f['text'] }}</span>
        </div>
    </div>

    <p class="ep-subhead">{{ __('In Case of Emergency') }}</p>

    <div class="row g-3">
        <div class="col-md-6 col-lg-4">
            <span class="ep-label">{{ __('Contact Person') }}</span>
            @php $f = $show($employee->emergency_contact_name); @endphp
            <span class="ep-value {{ $f['empty'] ? 'is-empty' : '' }}">{{ $f['text'] }}</span>
        </div>
        <div class="col-md-6 col-lg-3">
            <span class="ep-label">{{ __('Relationship') }}</span>
            @php $f = $show($employee->emergency_contact_relation); @endphp
            <span class="ep-value {{ $f['empty'] ? 'is-empty' : '' }}">{{ $f['text'] }}</span>
        </div>
        <div class="col-md-6 col-lg-4">
            <span class="ep-label">{{ __('Contact Number') }}</span>
            @php $f = $show($employee->emergency_contact_phone); @endphp
            <span class="ep-value {{ $f['empty'] ? 'is-empty' : '' }}">{{ $f['text'] }}</span>
        </div>
    </div>
</section>

{{-- ════════════════════ ADDRESS ════════════════════ --}}
<section class="ep-section">
    <header class="ep-section-head">
        <span class="ep-section-icon"><i class="fas fa-house"></i></span>
        <div>
            <h3 class="ep-section-title">{{ __('Address') }}</h3>
            <p class="ep-section-sub">{{ __('The current home address of the worker.') }}</p>
        </div>
    </header>

    <div class="row g-3">
        <div class="col-md-6 col-lg-3">
            <span class="ep-label">{{ __('Province') }}</span>
            @php $f = $show($employee->address_province); @endphp
            <span class="ep-value {{ $f['empty'] ? 'is-empty' : '' }}">{{ $f['text'] }}</span>
        </div>
        <div class="col-md-6 col-lg-3">
            <span class="ep-label">{{ __('City / Municipality') }}</span>
            @php $f = $show($employee->address_city); @endphp
            <span class="ep-value {{ $f['empty'] ? 'is-empty' : '' }}">{{ $f['text'] }}</span>
        </div>
        <div class="col-md-6 col-lg-2">
            <span class="ep-label">{{ __('Barangay') }}</span>
            @php $f = $show($employee->address_barangay); @endphp
            <span class="ep-value {{ $f['empty'] ? 'is-empty' : '' }}">{{ $f['text'] }}</span>
        </div>
        <div class="col-md-6 col-lg-3">
            <span class="ep-label">{{ __('House No. / Street') }}</span>
            @php $f = $show($employee->address_street); @endphp
            <span class="ep-value {{ $f['empty'] ? 'is-empty' : '' }}">{{ $f['text'] }}</span>
        </div>
        <div class="col-md-6 col-lg-1">
            <span class="ep-label">{{ __('ZIP Code') }}</span>
            @php $f = $show($employee->address_postal, '—'); @endphp
            <span class="ep-value ep-mono {{ $f['empty'] ? 'is-empty' : '' }}">{{ $f['text'] }}</span>
        </div>
    </div>
</section>

{{-- ════════════════════ GOVERNMENT IDS ════════════════════ --}}
<section class="ep-section">
    <header class="ep-section-head">
        <span class="ep-section-icon"><i class="fas fa-id-card"></i></span>
        <div>
            <h3 class="ep-section-title">{{ __('Government IDs') }}</h3>
            <p class="ep-section-sub">{{ __('Needed for statutory deductions and remittances. A blank one usually means the number has not been issued yet.') }}</p>
        </div>
    </header>

    <div class="row g-3">
        @foreach([
            'SSS Number'        => $employee->sss_number,
            'PhilHealth Number' => $employee->philhealth_number,
            'Pag-IBIG Number'   => $employee->pagibig_number,
            'TIN'               => $employee->tin_number,
        ] as $label => $number)
            <div class="col-md-6 col-lg-3">
                <span class="ep-label">{{ __($label) }}</span>
                @php $f = $show($number, 'Not issued yet'); @endphp
                <span class="ep-value ep-mono {{ $f['empty'] ? 'is-empty' : '' }}">{{ $f['text'] }}</span>
            </div>
        @endforeach
    </div>
</section>
