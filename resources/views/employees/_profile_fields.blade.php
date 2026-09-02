{{--
    The personnel half of the employee form: who the worker is, how to reach
    them and their next of kin, where they live, and the ID numbers payroll
    needs for statutory deductions. Shared by Register Employee and Edit
    Employee so the two never drift apart.

    Expects an optional $employee. On create it is absent and every field
    falls back to old() then blank; on edit the stored value shows through.
--}}
@php
    $emp = $employee ?? null;

    // old() wins so a failed validation never loses what was typed, then the
    // stored value, then blank.
    $val = function (string $field, $default = '') use ($emp) {
        $current = $emp?->{$field};
        if ($current instanceof \Illuminate\Support\Carbon) {
            $current = $current->format('Y-m-d');
        }
        return old($field, $current ?? $default);
    };
@endphp

{{-- Marks a post as coming from a full profile form, which is what makes the
     office's "fill everything in" rule apply. Only Register Employee and Edit
     Employee include this partial; the quick-edit modal on Register & Manage
     and the kiosk's complete endpoint reach the same controller methods with
     just a handful of pay fields, and must keep working. --}}
<input type="hidden" name="profile_form" value="1">

{{-- ════════════════════ PERSONAL INFORMATION ════════════════════ --}}
<section class="ep-section">
    <header class="ep-section-head">
        <span class="ep-section-icon"><i class="fas fa-user"></i></span>
        <div>
            <h3 class="ep-section-title">{{ __('Personal Information') }}</h3>
            <p class="ep-section-sub">{{ __('Basic details about the worker. Everything here is required.') }}</p>
        </div>
    </header>

    <div class="row g-3">
        <div class="col-md-6 col-lg-3">
            <label class="ep-label" for="birth_date">{{ __('Date of Birth') }} <span class="ep-req">*</span></label>
            <input type="date" id="birth_date" name="birth_date" required
                   class="form-control @error('birth_date') is-invalid @enderror"
                   value="{{ $val('birth_date') }}">
            @error('birth_date')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6 col-lg-4">
            <label class="ep-label" for="birth_place">{{ __('Place of Birth') }} <span class="ep-req">*</span></label>
            <input type="text" id="birth_place" name="birth_place" required
                   class="form-control @error('birth_place') is-invalid @enderror"
                   value="{{ $val('birth_place') }}" placeholder="{{ __('City / Municipality, Province') }}">
            @error('birth_place')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4 col-lg-2">
            <label class="ep-label" for="gender">{{ __('Gender') }} <span class="ep-req">*</span></label>
            <select id="gender" name="gender" required class="form-select @error('gender') is-invalid @enderror">
                <option value="">—</option>
                @foreach(\App\Models\Employee::GENDERS as $g)
                    <option value="{{ $g }}" {{ $val('gender') === $g ? 'selected' : '' }}>{{ $g }}</option>
                @endforeach
            </select>
            @error('gender')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4 col-lg-2">
            <label class="ep-label" for="civil_status">{{ __('Civil Status') }} <span class="ep-req">*</span></label>
            <select id="civil_status" name="civil_status" required class="form-select @error('civil_status') is-invalid @enderror">
                <option value="">—</option>
                @foreach(\App\Models\Employee::CIVIL_STATUSES as $c)
                    <option value="{{ $c }}" {{ $val('civil_status') === $c ? 'selected' : '' }}>{{ $c }}</option>
                @endforeach
            </select>
            @error('civil_status')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4 col-lg-1">
            <label class="ep-label" for="blood_type">{{ __('Blood') }} <span class="ep-req">*</span></label>
            <input type="text" id="blood_type" name="blood_type" required
                   class="form-control @error('blood_type') is-invalid @enderror"
                   value="{{ $val('blood_type') }}" placeholder="O+" maxlength="5">
            @error('blood_type')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6 col-lg-3">
            <label class="ep-label" for="nationality">{{ __('Nationality') }} <span class="ep-req">*</span></label>
            <input type="text" id="nationality" name="nationality" required
                   class="form-control @error('nationality') is-invalid @enderror"
                   value="{{ $val('nationality', 'Filipino') }}">
            @error('nationality')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
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
            <label class="ep-label" for="phone">{{ __('Mobile Number') }} <span class="ep-req">*</span></label>
            <input type="text" id="phone" name="phone" required
                   class="form-control @error('phone') is-invalid @enderror"
                   value="{{ $val('phone') }}" placeholder="{{ __('09XX XXX XXXX') }}">
            @error('phone')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6 col-lg-4">
            <label class="ep-label" for="email">{{ __('Email Address') }} <span class="ep-req">*</span></label>
            <input type="email" id="email" name="email" required
                   class="form-control @error('email') is-invalid @enderror"
                   value="{{ $val('email') }}" placeholder="name@example.com">
            @error('email')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
    </div>

    <p class="ep-subhead">{{ __('In Case of Emergency') }}</p>

    <div class="row g-3">
        <div class="col-md-6 col-lg-4">
            <label class="ep-label" for="emergency_contact_name">{{ __('Contact Person') }} <span class="ep-req">*</span></label>
            <input type="text" id="emergency_contact_name" name="emergency_contact_name" required
                   class="form-control @error('emergency_contact_name') is-invalid @enderror"
                   value="{{ $val('emergency_contact_name') }}" placeholder="{{ __('Full name') }}">
            @error('emergency_contact_name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6 col-lg-3">
            <label class="ep-label" for="emergency_contact_relation">{{ __('Relationship') }} <span class="ep-req">*</span></label>
            <input type="text" id="emergency_contact_relation" name="emergency_contact_relation" required
                   class="form-control @error('emergency_contact_relation') is-invalid @enderror"
                   value="{{ $val('emergency_contact_relation') }}" placeholder="{{ __('e.g. Spouse') }}">
            @error('emergency_contact_relation')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6 col-lg-4">
            <label class="ep-label" for="emergency_contact_phone">{{ __('Contact Number') }} <span class="ep-req">*</span></label>
            <input type="text" id="emergency_contact_phone" name="emergency_contact_phone" required
                   class="form-control @error('emergency_contact_phone') is-invalid @enderror"
                   value="{{ $val('emergency_contact_phone') }}" placeholder="{{ __('09XX XXX XXXX') }}">
            @error('emergency_contact_phone')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
    </div>
</section>

{{-- ════════════════════ ADDRESS ════════════════════ --}}
<section class="ep-section">
    <header class="ep-section-head">
        <span class="ep-section-icon"><i class="fas fa-house"></i></span>
        <div>
            <h3 class="ep-section-title">{{ __('Address') }}</h3>
            <p class="ep-section-sub">{{ __('The worker\'s current home address.') }}</p>
        </div>
    </header>

    {{-- Province leads, because the three that follow are answers to it: the
         city list is whatever is inside the chosen province, and the barangay
         list whatever is inside the chosen city. Filling them in the other
         order would mean typing a barangay before anything knows where to look
         for it. House No. / Street is last of the cascade and plain text —
         there is no register of street names to suggest from.
         The lists come from PSGC via public/js/address-picker.js. --}}
    <div class="row g-3">
        <div class="col-md-6 col-lg-3">
            <label class="ep-label" for="address_province">{{ __('Province') }} <span class="ep-req">*</span></label>
            <div class="ap-field">
                <input type="text" id="address_province" name="address_province" required
                       class="form-control @error('address_province') is-invalid @enderror"
                       value="{{ $val('address_province') }}"
                       placeholder="{{ __('Type to search, e.g. Camarines') }}">
            </div>
            @error('address_province')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6 col-lg-3">
            <label class="ep-label" for="address_city">{{ __('City / Municipality') }} <span class="ep-req">*</span></label>
            <div class="ap-field">
                <input type="text" id="address_city" name="address_city" required
                       class="form-control @error('address_city') is-invalid @enderror"
                       value="{{ $val('address_city') }}"
                       placeholder="{{ __('Pick a province first') }}">
            </div>
            @error('address_city')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6 col-lg-2">
            <label class="ep-label" for="address_barangay">{{ __('Barangay') }} <span class="ep-req">*</span></label>
            <div class="ap-field">
                <input type="text" id="address_barangay" name="address_barangay" required
                       class="form-control @error('address_barangay') is-invalid @enderror"
                       value="{{ $val('address_barangay') }}"
                       placeholder="{{ __('Pick a city first') }}">
            </div>
            @error('address_barangay')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6 col-lg-3">
            <label class="ep-label" for="address_street">{{ __('House No. / Street') }} <span class="ep-req">*</span></label>
            <input type="text" id="address_street" name="address_street" required
                   class="form-control @error('address_street') is-invalid @enderror"
                   value="{{ $val('address_street') }}" placeholder="{{ __('e.g. 123 Rizal St.') }}">
            @error('address_street')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6 col-lg-1">
            <label class="ep-label" for="address_postal">{{ __('ZIP Code') }} <span class="ep-req">*</span></label>
            <input type="text" id="address_postal" name="address_postal" required
                   class="form-control @error('address_postal') is-invalid @enderror"
                   value="{{ $val('address_postal') }}">
            @error('address_postal')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
    </div>
</section>

{{-- ════════════════════ GOVERNMENT IDS ════════════════════ --}}
<section class="ep-section">
    <header class="ep-section-head">
        <span class="ep-section-icon"><i class="fas fa-id-card"></i></span>
        <div>
            <h3 class="ep-section-title">{{ __('Government IDs') }}</h3>
            <p class="ep-section-sub">{{ __('Needed for statutory deductions and remittances. Optional — a new hire is often still waiting to be issued these, and that must not hold up their registration.') }}</p>
        </div>
    </header>

    <div class="row g-3">
        <div class="col-md-6 col-lg-3">
            <label class="ep-label" for="sss_number">{{ __('SSS Number') }} <span class="ep-optional">{{ __('(optional)') }}</span></label>
            <input type="text" id="sss_number" name="sss_number"
                   class="form-control ep-mono @error('sss_number') is-invalid @enderror"
                   value="{{ $val('sss_number') }}" placeholder="00-0000000-0">
            @error('sss_number')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6 col-lg-3">
            <label class="ep-label" for="philhealth_number">{{ __('PhilHealth Number') }} <span class="ep-optional">{{ __('(optional)') }}</span></label>
            <input type="text" id="philhealth_number" name="philhealth_number"
                   class="form-control ep-mono @error('philhealth_number') is-invalid @enderror"
                   value="{{ $val('philhealth_number') }}" placeholder="00-000000000-0">
            @error('philhealth_number')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6 col-lg-3">
            <label class="ep-label" for="pagibig_number">{{ __('Pag-IBIG Number') }} <span class="ep-optional">{{ __('(optional)') }}</span></label>
            <input type="text" id="pagibig_number" name="pagibig_number"
                   class="form-control ep-mono @error('pagibig_number') is-invalid @enderror"
                   value="{{ $val('pagibig_number') }}" placeholder="0000-0000-0000">
            @error('pagibig_number')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6 col-lg-3">
            <label class="ep-label" for="tin_number">{{ __('TIN') }} <span class="ep-optional">{{ __('(optional)') }}</span></label>
            <input type="text" id="tin_number" name="tin_number"
                   class="form-control ep-mono @error('tin_number') is-invalid @enderror"
                   value="{{ $val('tin_number') }}" placeholder="000-000-000-000">
            @error('tin_number')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
    </div>
</section>
