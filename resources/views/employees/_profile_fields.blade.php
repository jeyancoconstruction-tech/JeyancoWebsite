{{--
    The resume half of the employee form: who the worker is, how to reach
    them, where they live, what they studied, where they worked and what they
    can do. Shared by Register Employee and Edit Employee so the two never
    drift apart.

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

    // Repeatable sections always render at least one row so there is
    // something to type into.
    $educationRows = old('education', $emp?->education ?: []);
    $educationRows = empty($educationRows) ? [[]] : $educationRows;

    $experienceRows = old('work_experience', $emp?->work_experience ?: []);
    $experienceRows = empty($experienceRows) ? [[]] : $experienceRows;

    $skillsValue = old('skills', collect($emp?->skills ?: [])->implode(', '));
@endphp

{{-- ════════════════════ POSITION DETAILS ════════════════════ --}}
<div class="ep-section">
    <div class="ep-section-head">
        <span class="ep-section-icon"><i class="fas fa-id-badge"></i></span>
        <div>
            <h3 class="ep-section-title">Position Details</h3>
            <p class="ep-section-sub">The worker's job title and when they joined.</p>
        </div>
    </div>
    <div class="row g-3">
        <div class="col-md-8">
            <label class="ep-label" for="job_title">Position / Job Title</label>
            <input type="text" id="job_title" name="job_title" class="form-control @error('job_title') is-invalid @enderror"
                   value="{{ $val('job_title') }}" placeholder="e.g. Mason, Site Foreman, Heavy Equipment Operator">
            <small class="ep-hint">Descriptive title for the worker's record. Pay still comes from the Labor Type above.</small>
            @error('job_title')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
            <label class="ep-label" for="date_hired">Date Hired</label>
            <input type="date" id="date_hired" name="date_hired" class="form-control @error('date_hired') is-invalid @enderror"
                   value="{{ $val('date_hired') }}">
            @error('date_hired')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
    </div>
</div>

{{-- ════════════════════ PERSONAL INFORMATION ════════════════════ --}}
<div class="ep-section">
    <div class="ep-section-head">
        <span class="ep-section-icon"><i class="fas fa-user"></i></span>
        <div>
            <h3 class="ep-section-title">Personal Information</h3>
            <p class="ep-section-sub">Basic details about the worker.</p>
        </div>
    </div>
    <div class="row g-3">
        <div class="col-md-4">
            <label class="ep-label" for="birth_date">Date of Birth</label>
            <input type="date" id="birth_date" name="birth_date" class="form-control @error('birth_date') is-invalid @enderror"
                   value="{{ $val('birth_date') }}">
            @error('birth_date')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-8">
            <label class="ep-label" for="birth_place">Place of Birth</label>
            <input type="text" id="birth_place" name="birth_place" class="form-control @error('birth_place') is-invalid @enderror"
                   value="{{ $val('birth_place') }}" placeholder="City / Municipality, Province">
            @error('birth_place')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>

        <div class="col-md-3">
            <label class="ep-label" for="gender">Gender</label>
            <select id="gender" name="gender" class="form-select @error('gender') is-invalid @enderror">
                <option value="">— Select —</option>
                @foreach(\App\Models\Employee::GENDERS as $g)
                    <option value="{{ $g }}" {{ $val('gender') === $g ? 'selected' : '' }}>{{ $g }}</option>
                @endforeach
            </select>
            @error('gender')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-3">
            <label class="ep-label" for="civil_status">Civil Status</label>
            <select id="civil_status" name="civil_status" class="form-select @error('civil_status') is-invalid @enderror">
                <option value="">— Select —</option>
                @foreach(\App\Models\Employee::CIVIL_STATUSES as $c)
                    <option value="{{ $c }}" {{ $val('civil_status') === $c ? 'selected' : '' }}>{{ $c }}</option>
                @endforeach
            </select>
            @error('civil_status')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-3">
            <label class="ep-label" for="nationality">Nationality</label>
            <input type="text" id="nationality" name="nationality" class="form-control @error('nationality') is-invalid @enderror"
                   value="{{ $val('nationality', 'Filipino') }}">
            @error('nationality')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-3">
            <label class="ep-label" for="blood_type">Blood Type</label>
            <input type="text" id="blood_type" name="blood_type" class="form-control @error('blood_type') is-invalid @enderror"
                   value="{{ $val('blood_type') }}" placeholder="e.g. O+" maxlength="5">
            @error('blood_type')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
            <label class="ep-label" for="religion">Religion</label>
            <input type="text" id="religion" name="religion" class="form-control @error('religion') is-invalid @enderror"
                   value="{{ $val('religion') }}">
            @error('religion')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
    </div>
</div>

{{-- ════════════════════ CONTACT INFORMATION ════════════════════ --}}
<div class="ep-section">
    <div class="ep-section-head">
        <span class="ep-section-icon"><i class="fas fa-phone"></i></span>
        <div>
            <h3 class="ep-section-title">Contact Information</h3>
            <p class="ep-section-sub">How to reach the worker, and who to call in an emergency.</p>
        </div>
    </div>
    <div class="row g-3">
        <div class="col-md-6">
            <label class="ep-label" for="phone">Mobile Number</label>
            <input type="text" id="phone" name="phone" class="form-control @error('phone') is-invalid @enderror"
                   value="{{ $val('phone') }}" placeholder="09XX XXX XXXX">
            @error('phone')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label class="ep-label" for="email">Email Address</label>
            <input type="email" id="email" name="email" class="form-control @error('email') is-invalid @enderror"
                   value="{{ $val('email') }}" placeholder="name@example.com">
            @error('email')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>

        <div class="col-12"><hr class="ep-divider"><p class="ep-subhead">Emergency Contact</p></div>

        <div class="col-md-5">
            <label class="ep-label" for="emergency_contact_name">Contact Person</label>
            <input type="text" id="emergency_contact_name" name="emergency_contact_name"
                   class="form-control @error('emergency_contact_name') is-invalid @enderror"
                   value="{{ $val('emergency_contact_name') }}" placeholder="Full name">
            @error('emergency_contact_name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-3">
            <label class="ep-label" for="emergency_contact_relation">Relationship</label>
            <input type="text" id="emergency_contact_relation" name="emergency_contact_relation"
                   class="form-control @error('emergency_contact_relation') is-invalid @enderror"
                   value="{{ $val('emergency_contact_relation') }}" placeholder="e.g. Spouse">
            @error('emergency_contact_relation')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
            <label class="ep-label" for="emergency_contact_phone">Contact Number</label>
            <input type="text" id="emergency_contact_phone" name="emergency_contact_phone"
                   class="form-control @error('emergency_contact_phone') is-invalid @enderror"
                   value="{{ $val('emergency_contact_phone') }}" placeholder="09XX XXX XXXX">
            @error('emergency_contact_phone')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
    </div>
</div>

{{-- ════════════════════ ADDRESS ════════════════════ --}}
<div class="ep-section">
    <div class="ep-section-head">
        <span class="ep-section-icon"><i class="fas fa-house"></i></span>
        <div>
            <h3 class="ep-section-title">Address</h3>
            <p class="ep-section-sub">The worker's current home address.</p>
        </div>
    </div>
    <div class="row g-3">
        <div class="col-md-6">
            <label class="ep-label" for="address_street">House No. / Street</label>
            <input type="text" id="address_street" name="address_street" class="form-control @error('address_street') is-invalid @enderror"
                   value="{{ $val('address_street') }}" placeholder="e.g. 123 Rizal St.">
            @error('address_street')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label class="ep-label" for="address_barangay">Barangay</label>
            <input type="text" id="address_barangay" name="address_barangay" class="form-control @error('address_barangay') is-invalid @enderror"
                   value="{{ $val('address_barangay') }}">
            @error('address_barangay')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-5">
            <label class="ep-label" for="address_city">City / Municipality</label>
            <input type="text" id="address_city" name="address_city" class="form-control @error('address_city') is-invalid @enderror"
                   value="{{ $val('address_city') }}">
            @error('address_city')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-5">
            <label class="ep-label" for="address_province">Province</label>
            <input type="text" id="address_province" name="address_province" class="form-control @error('address_province') is-invalid @enderror"
                   value="{{ $val('address_province') }}">
            @error('address_province')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-2">
            <label class="ep-label" for="address_postal">ZIP Code</label>
            <input type="text" id="address_postal" name="address_postal" class="form-control @error('address_postal') is-invalid @enderror"
                   value="{{ $val('address_postal') }}">
            @error('address_postal')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
    </div>
</div>

{{-- ════════════════════ GOVERNMENT IDS ════════════════════ --}}
<div class="ep-section">
    <div class="ep-section-head">
        <span class="ep-section-icon"><i class="fas fa-id-card"></i></span>
        <div>
            <h3 class="ep-section-title">Government IDs</h3>
            <p class="ep-section-sub">Needed for statutory deductions and remittances.</p>
        </div>
    </div>
    <div class="row g-3">
        <div class="col-md-3">
            <label class="ep-label" for="sss_number">SSS Number</label>
            <input type="text" id="sss_number" name="sss_number" class="form-control @error('sss_number') is-invalid @enderror"
                   value="{{ $val('sss_number') }}">
            @error('sss_number')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-3">
            <label class="ep-label" for="philhealth_number">PhilHealth Number</label>
            <input type="text" id="philhealth_number" name="philhealth_number" class="form-control @error('philhealth_number') is-invalid @enderror"
                   value="{{ $val('philhealth_number') }}">
            @error('philhealth_number')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-3">
            <label class="ep-label" for="pagibig_number">Pag-IBIG Number</label>
            <input type="text" id="pagibig_number" name="pagibig_number" class="form-control @error('pagibig_number') is-invalid @enderror"
                   value="{{ $val('pagibig_number') }}">
            @error('pagibig_number')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-3">
            <label class="ep-label" for="tin_number">TIN</label>
            <input type="text" id="tin_number" name="tin_number" class="form-control @error('tin_number') is-invalid @enderror"
                   value="{{ $val('tin_number') }}">
            @error('tin_number')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
    </div>
</div>

{{-- ════════════════════ EDUCATIONAL BACKGROUND ════════════════════ --}}
<div class="ep-section">
    <div class="ep-section-head">
        <span class="ep-section-icon"><i class="fas fa-graduation-cap"></i></span>
        <div>
            <h3 class="ep-section-title">Educational Background</h3>
            <p class="ep-section-sub">Add a row for each level completed. Blank rows are ignored.</p>
        </div>
    </div>

    <div class="ep-repeat" data-repeat="education">
        @foreach($educationRows as $i => $row)
        <div class="ep-repeat-row">
            <button type="button" class="ep-row-x js-row-remove" aria-label="Remove this entry"><i class="fas fa-times"></i></button>
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="ep-label">Level</label>
                    <select name="education[{{ $i }}][level]" class="form-select">
                        <option value="">— Select —</option>
                        @foreach(['Elementary', 'High School', 'Senior High School', 'Vocational / TESDA', 'College', 'Post Graduate'] as $lvl)
                            <option value="{{ $lvl }}" {{ ($row['level'] ?? '') === $lvl ? 'selected' : '' }}>{{ $lvl }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="ep-label">School</label>
                    <input type="text" name="education[{{ $i }}][school]" class="form-control"
                           value="{{ $row['school'] ?? '' }}" placeholder="Name of school">
                </div>
                <div class="col-md-3">
                    <label class="ep-label">Course / Strand</label>
                    <input type="text" name="education[{{ $i }}][course]" class="form-control"
                           value="{{ $row['course'] ?? '' }}" placeholder="If applicable">
                </div>
                <div class="col-md-2">
                    <label class="ep-label">Year Graduated</label>
                    <input type="text" name="education[{{ $i }}][year_graduated]" class="form-control"
                           value="{{ $row['year_graduated'] ?? '' }}" placeholder="e.g. 2018">
                </div>
            </div>
        </div>
        @endforeach
    </div>
    <button type="button" class="ep-add-btn js-row-add" data-target="education">
        <i class="fas fa-plus"></i> Add education entry
    </button>
</div>

{{-- ════════════════════ WORK EXPERIENCE ════════════════════ --}}
<div class="ep-section">
    <div class="ep-section-head">
        <span class="ep-section-icon"><i class="fas fa-briefcase"></i></span>
        <div>
            <h3 class="ep-section-title">Work Experience</h3>
            <p class="ep-section-sub">Previous employers, most recent first. Blank rows are ignored.</p>
        </div>
    </div>

    <div class="ep-repeat" data-repeat="work_experience">
        @foreach($experienceRows as $i => $row)
        <div class="ep-repeat-row">
            <button type="button" class="ep-row-x js-row-remove" aria-label="Remove this entry"><i class="fas fa-times"></i></button>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="ep-label">Company</label>
                    <input type="text" name="work_experience[{{ $i }}][company]" class="form-control"
                           value="{{ $row['company'] ?? '' }}" placeholder="Employer name">
                </div>
                <div class="col-md-4">
                    <label class="ep-label">Position</label>
                    <input type="text" name="work_experience[{{ $i }}][position]" class="form-control"
                           value="{{ $row['position'] ?? '' }}" placeholder="Job title held">
                </div>
                <div class="col-md-2">
                    <label class="ep-label">From</label>
                    <input type="text" name="work_experience[{{ $i }}][from]" class="form-control"
                           value="{{ $row['from'] ?? '' }}" placeholder="e.g. 2019">
                </div>
                <div class="col-md-2">
                    <label class="ep-label">To</label>
                    <input type="text" name="work_experience[{{ $i }}][to]" class="form-control"
                           value="{{ $row['to'] ?? '' }}" placeholder="e.g. 2023">
                </div>
                <div class="col-12">
                    <label class="ep-label">Duties / Responsibilities</label>
                    <textarea name="work_experience[{{ $i }}][duties]" class="form-control" rows="2"
                              placeholder="Brief description of the work done">{{ $row['duties'] ?? '' }}</textarea>
                </div>
            </div>
        </div>
        @endforeach
    </div>
    <button type="button" class="ep-add-btn js-row-add" data-target="work_experience">
        <i class="fas fa-plus"></i> Add work experience
    </button>
</div>

{{-- ════════════════════ SKILLS & OTHER INFORMATION ════════════════════ --}}
<div class="ep-section">
    <div class="ep-section-head">
        <span class="ep-section-icon"><i class="fas fa-screwdriver-wrench"></i></span>
        <div>
            <h3 class="ep-section-title">Skills &amp; Other Information</h3>
            <p class="ep-section-sub">Trades, certifications, licences — anything else worth keeping on file.</p>
        </div>
    </div>
    <div class="row g-3">
        <div class="col-12">
            <label class="ep-label" for="skills">Skills</label>
            <input type="text" id="skills" name="skills" class="form-control @error('skills') is-invalid @enderror"
                   value="{{ $skillsValue }}"
                   placeholder="e.g. Masonry, Welding, Scaffolding, Driving (Professional Licence)">
            <small class="ep-hint">Separate each skill with a comma.</small>
            @error('skills')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="col-12">
            <label class="ep-label" for="notes">Additional Notes</label>
            <textarea id="notes" name="notes" rows="3" class="form-control @error('notes') is-invalid @enderror"
                      placeholder="Certifications, trainings, medical conditions, or anything else the office should know.">{{ $val('notes') }}</textarea>
            @error('notes')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
    </div>
</div>
