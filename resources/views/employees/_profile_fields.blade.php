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

{{-- ════════════════════ PERSONAL INFORMATION ════════════════════ --}}
<section class="ep-section">
    <header class="ep-section-head">
        <span class="ep-section-icon"><i class="fas fa-user"></i></span>
        <div>
            <h3 class="ep-section-title">Personal Information</h3>
            <p class="ep-section-sub">Basic details about the worker.</p>
        </div>
    </header>

    <div class="row g-3">
        <div class="col-md-6 col-lg-3">
            <label class="ep-label" for="birth_date">Date of Birth</label>
            <input type="date" id="birth_date" name="birth_date"
                   class="form-control @error('birth_date') is-invalid @enderror"
                   value="{{ $val('birth_date') }}">
            @error('birth_date')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6 col-lg-4">
            <label class="ep-label" for="birth_place">Place of Birth</label>
            <input type="text" id="birth_place" name="birth_place"
                   class="form-control @error('birth_place') is-invalid @enderror"
                   value="{{ $val('birth_place') }}" placeholder="City / Municipality, Province">
            @error('birth_place')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4 col-lg-2">
            <label class="ep-label" for="gender">Gender</label>
            <select id="gender" name="gender" class="form-select @error('gender') is-invalid @enderror">
                <option value="">—</option>
                @foreach(\App\Models\Employee::GENDERS as $g)
                    <option value="{{ $g }}" {{ $val('gender') === $g ? 'selected' : '' }}>{{ $g }}</option>
                @endforeach
            </select>
            @error('gender')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4 col-lg-2">
            <label class="ep-label" for="civil_status">Civil Status</label>
            <select id="civil_status" name="civil_status" class="form-select @error('civil_status') is-invalid @enderror">
                <option value="">—</option>
                @foreach(\App\Models\Employee::CIVIL_STATUSES as $c)
                    <option value="{{ $c }}" {{ $val('civil_status') === $c ? 'selected' : '' }}>{{ $c }}</option>
                @endforeach
            </select>
            @error('civil_status')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4 col-lg-1">
            <label class="ep-label" for="blood_type">Blood</label>
            <input type="text" id="blood_type" name="blood_type"
                   class="form-control @error('blood_type') is-invalid @enderror"
                   value="{{ $val('blood_type') }}" placeholder="O+" maxlength="5">
            @error('blood_type')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6 col-lg-3">
            <label class="ep-label" for="nationality">Nationality</label>
            <input type="text" id="nationality" name="nationality"
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
            <h3 class="ep-section-title">Contact Information</h3>
            <p class="ep-section-sub">How to reach the worker, and who to call in an emergency.</p>
        </div>
    </header>

    <div class="row g-3">
        <div class="col-md-6 col-lg-4">
            <label class="ep-label" for="phone">Mobile Number</label>
            <input type="text" id="phone" name="phone"
                   class="form-control @error('phone') is-invalid @enderror"
                   value="{{ $val('phone') }}" placeholder="09XX XXX XXXX">
            @error('phone')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6 col-lg-4">
            <label class="ep-label" for="email">Email Address</label>
            <input type="email" id="email" name="email"
                   class="form-control @error('email') is-invalid @enderror"
                   value="{{ $val('email') }}" placeholder="name@example.com">
            @error('email')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
    </div>

    <p class="ep-subhead">In Case of Emergency</p>

    <div class="row g-3">
        <div class="col-md-6 col-lg-4">
            <label class="ep-label" for="emergency_contact_name">Contact Person</label>
            <input type="text" id="emergency_contact_name" name="emergency_contact_name"
                   class="form-control @error('emergency_contact_name') is-invalid @enderror"
                   value="{{ $val('emergency_contact_name') }}" placeholder="Full name">
            @error('emergency_contact_name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6 col-lg-3">
            <label class="ep-label" for="emergency_contact_relation">Relationship</label>
            <input type="text" id="emergency_contact_relation" name="emergency_contact_relation"
                   class="form-control @error('emergency_contact_relation') is-invalid @enderror"
                   value="{{ $val('emergency_contact_relation') }}" placeholder="e.g. Spouse">
            @error('emergency_contact_relation')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6 col-lg-4">
            <label class="ep-label" for="emergency_contact_phone">Contact Number</label>
            <input type="text" id="emergency_contact_phone" name="emergency_contact_phone"
                   class="form-control @error('emergency_contact_phone') is-invalid @enderror"
                   value="{{ $val('emergency_contact_phone') }}" placeholder="09XX XXX XXXX">
            @error('emergency_contact_phone')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
    </div>
</section>

{{-- ════════════════════ ADDRESS ════════════════════ --}}
<section class="ep-section">
    <header class="ep-section-head">
        <span class="ep-section-icon"><i class="fas fa-house"></i></span>
        <div>
            <h3 class="ep-section-title">Address</h3>
            <p class="ep-section-sub">The worker's current home address.</p>
        </div>
    </header>

    <div class="row g-3">
        <div class="col-md-6 col-lg-4">
            <label class="ep-label" for="address_street">House No. / Street</label>
            <input type="text" id="address_street" name="address_street"
                   class="form-control @error('address_street') is-invalid @enderror"
                   value="{{ $val('address_street') }}" placeholder="e.g. 123 Rizal St.">
            @error('address_street')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6 col-lg-2">
            <label class="ep-label" for="address_barangay">Barangay</label>
            <input type="text" id="address_barangay" name="address_barangay"
                   class="form-control @error('address_barangay') is-invalid @enderror"
                   value="{{ $val('address_barangay') }}">
            @error('address_barangay')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6 col-lg-2">
            <label class="ep-label" for="address_city">City / Municipality</label>
            <input type="text" id="address_city" name="address_city"
                   class="form-control @error('address_city') is-invalid @enderror"
                   value="{{ $val('address_city') }}">
            @error('address_city')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6 col-lg-2">
            <label class="ep-label" for="address_province">Province</label>
            <input type="text" id="address_province" name="address_province"
                   class="form-control @error('address_province') is-invalid @enderror"
                   value="{{ $val('address_province') }}">
            @error('address_province')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6 col-lg-2">
            <label class="ep-label" for="address_postal">ZIP Code</label>
            <input type="text" id="address_postal" name="address_postal"
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
            <h3 class="ep-section-title">Government IDs</h3>
            <p class="ep-section-sub">Needed for statutory deductions and remittances.</p>
        </div>
    </header>

    <div class="row g-3">
        <div class="col-md-6 col-lg-3">
            <label class="ep-label" for="sss_number">SSS Number</label>
            <input type="text" id="sss_number" name="sss_number"
                   class="form-control ep-mono @error('sss_number') is-invalid @enderror"
                   value="{{ $val('sss_number') }}" placeholder="00-0000000-0">
            @error('sss_number')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6 col-lg-3">
            <label class="ep-label" for="philhealth_number">PhilHealth Number</label>
            <input type="text" id="philhealth_number" name="philhealth_number"
                   class="form-control ep-mono @error('philhealth_number') is-invalid @enderror"
                   value="{{ $val('philhealth_number') }}" placeholder="00-000000000-0">
            @error('philhealth_number')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6 col-lg-3">
            <label class="ep-label" for="pagibig_number">Pag-IBIG Number</label>
            <input type="text" id="pagibig_number" name="pagibig_number"
                   class="form-control ep-mono @error('pagibig_number') is-invalid @enderror"
                   value="{{ $val('pagibig_number') }}" placeholder="0000-0000-0000">
            @error('pagibig_number')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6 col-lg-3">
            <label class="ep-label" for="tin_number">TIN</label>
            <input type="text" id="tin_number" name="tin_number"
                   class="form-control ep-mono @error('tin_number') is-invalid @enderror"
                   value="{{ $val('tin_number') }}" placeholder="000-000-000-000">
            @error('tin_number')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
    </div>
</section>
