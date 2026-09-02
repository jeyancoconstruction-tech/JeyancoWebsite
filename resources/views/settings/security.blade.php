@extends('layouts')

@section('page_title', 'Security')

@push('styles')
@include('settings._system-styles')
@endpush

@section('content')
<div class="sy-page">

    <div class="sy-header mb-3">
        <h1>Security</h1>
        <p>How long a session lasts, and how hard it is to guess a password.</p>
    </div>

    @include('settings._system-tabs')

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

    <form method="POST" action="{{ route('system-settings.security.update') }}">
        @csrf
        @method('PUT')

        <div class="sy-card">
            <div class="sy-card-head">
                <i class="fas fa-lock"></i>
                <div>
                    <h6>Account &amp; security</h6>
                    <p>Applied to every login and to the next password set or reset</p>
                </div>
            </div>
            <div class="sy-card-body">
                <div class="sy-grid">
                    <div class="sy-field">
                        <label for="session_timeout_minutes">Session timeout (minutes)</label>
                        <input type="number" class="sy-input" id="session_timeout_minutes" name="session_timeout_minutes"
                               min="5" max="1440" value="{{ old('session_timeout_minutes', $system->session_timeout_minutes) }}" required>
                    </div>
                    <div class="sy-field">
                        <label for="password_min_length">Minimum password length</label>
                        <input type="number" class="sy-input" id="password_min_length" name="password_min_length"
                               min="8" max="64" value="{{ old('password_min_length', $system->password_min_length) }}" required>
                    </div>
                    <div class="sy-field">
                        <label for="max_login_attempts">Failed logins before lockout</label>
                        <input type="number" class="sy-input" id="max_login_attempts" name="max_login_attempts"
                               min="3" max="20" value="{{ old('max_login_attempts', $system->max_login_attempts) }}" required>
                    </div>
                    <div class="sy-field">
                        <label for="lockout_seconds">Lockout length (seconds)</label>
                        <input type="number" class="sy-input" id="lockout_seconds" name="lockout_seconds"
                               min="30" max="3600" value="{{ old('lockout_seconds', $system->lockout_seconds) }}" required>
                    </div>
                </div>

                <p class="sy-hint">
                    A password already on file is not re-checked, so raising the minimum applies to the next
                    one set or reset rather than locking anybody out. The lockout counts failures per
                    username and IP together, so one person getting it wrong does not lock out the rest.
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
@endsection
