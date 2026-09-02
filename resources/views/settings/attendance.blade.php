@extends('layouts')

@section('page_title', 'Attendance')

@push('styles')
@include('settings._system-styles')
@endpush

@section('content')
<div class="sy-page">

    <div class="sy-header mb-3">
        <h1>Attendance</h1>
        <p>Schedules, grace periods, and the basis for lates and overtime.</p>
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
            <strong>Nothing was saved.</strong>
            <ul class="mb-0 mt-1">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('system-settings.attendance.update') }}">
        @csrf
        @method('PUT')

        <div class="sy-card">
            <div class="sy-card-head">
                <i class="fas fa-clock"></i>
                <div>
                    <h6>Work schedule</h6>
                    <p>When a shift is meant to start, and what a day's rate buys</p>
                </div>
            </div>
            <div class="sy-card-body">
                <div class="sy-grid">
                    <div class="sy-field">
                        <label for="expected_time_in">Expected time-in</label>
                        <input type="time" class="sy-input" id="expected_time_in" name="expected_time_in"
                               value="{{ old('expected_time_in', substr($system->expected_time_in, 0, 5)) }}" required>
                    </div>
                    <div class="sy-field">
                        <label for="grace_period_minutes">Grace period (minutes) <span class="text-muted">bago mag-late</span></label>
                        <input type="number" class="sy-input" id="grace_period_minutes" name="grace_period_minutes"
                               min="0" max="120" value="{{ old('grace_period_minutes', $system->grace_period_minutes) }}" required>
                    </div>
                    <div class="sy-field">
                        <label for="standard_hours_per_day">Standard hours / day</label>
                        <input type="number" step="0.25" class="sy-input" id="standard_hours_per_day" name="standard_hours_per_day"
                               min="1" max="24" value="{{ old('standard_hours_per_day', $system->standard_hours_per_day) }}" required>
                    </div>
                </div>

                <p class="sy-hint">
                    The standard hours divide a labour type's daily rate into an hourly one, and mark where
                    overtime begins. Lateness is <strong>reported, not deducted</strong> — a worker is already
                    paid only for the hours they worked, and docking on top would cut wages twice.
                </p>
            </div>
        </div>

        <div class="sy-card">
            <div class="sy-card-head">
                <i class="fas fa-calendar-week"></i>
                <div>
                    <h6>Payroll cutoff</h6>
                    <p>Which days a pay period covers</p>
                </div>
            </div>
            <div class="sy-card-body">
                <div class="sy-grid">
                    <div class="sy-field">
                        <label for="payroll_cycle">Cycle</label>
                        <select class="sy-input" id="payroll_cycle" name="payroll_cycle" required>
                            <option value="weekly" @selected(old('payroll_cycle', $system->payroll_cycle) === 'weekly')>Weekly</option>
                            <option value="daily"  @selected(old('payroll_cycle', $system->payroll_cycle) === 'daily')>Daily</option>
                        </select>
                    </div>
                    <div class="sy-field">
                        <label for="week_starts_on">Week starts</label>
                        <select class="sy-input" id="week_starts_on" name="week_starts_on" required>
                            @foreach([1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday',
                                      5 => 'Friday', 6 => 'Saturday', 0 => 'Sunday'] as $n => $label)
                                <option value="{{ $n }}" @selected((int) old('week_starts_on', $system->week_starts_on) === $n)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="sy-field mt-3">
                    <label for="auto_count_overtime">Auto-count OT beyond standard</label>
                    <div class="ps-toggle-row">
                        <label class="ps-toggle-switch">
                            <input type="checkbox" name="auto_count_overtime" value="1" id="auto_count_overtime"
                                   {{ old('auto_count_overtime', $system->auto_count_overtime) ? 'checked' : '' }}>
                            <span class="ps-toggle-slider"></span>
                        </label>
                        <div class="ps-toggle-label">
                            Hours past the standard become overtime on their own
                        </div>
                    </div>
                </div>

                <p class="sy-hint">
                    The cycle is the period Payroll Records opens on. Changing where the week starts regroups
                    every period on screen — it does not alter what any day was paid, but a week already
                    handed out will be split differently from the payslip that went with it.
                    Turn auto-overtime off and the extra hours still pay, at the plain rate.
                </p>
            </div>
        </div>

        <div class="sy-foot">
            <span class="sy-updated">
                <i class="fas fa-clock-rotate-left"></i>
                @if($system->exists)
                    Last updated {{ $system->updated_at?->format('Y-m-d') }}
                @else
                    Never changed — showing the built-in defaults
                @endif
            </span>
            <button type="submit" class="sy-save"><i class="fas fa-floppy-disk me-1"></i> Save</button>
        </div>
    </form>

    </div>
    </div>
</div>
@endsection
