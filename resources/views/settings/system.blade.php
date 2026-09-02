@extends('layouts')

@section('page_title', 'System Settings')

@push('styles')
<style>
    /* Self-contained. The payroll settings page's .pr-* chrome lives inside its
       own template, so borrowing it here would render unstyled. Same design
       language, written again: tokens throughout, 6px radius, flat surfaces. */
    .sy-page { padding: 20px 28px 48px; }
    @media (max-width: 768px) { .sy-page { padding: 16px; } }

    .sy-header h1 { font-size: 1.6rem; font-weight: 800; color: var(--text-primary); margin: 0; letter-spacing: -0.3px; }
    .sy-header p  { color: var(--text-secondary); font-size: 0.9rem; margin: 2px 0 0; }

    .sy-card {
        background: var(--surface); border: 1px solid var(--border);
        border-radius: 6px; margin-bottom: 18px; overflow: hidden;
    }
    .sy-card-head {
        display: flex; align-items: flex-start; gap: 12px;
        padding: 16px 20px; border-bottom: 1px solid var(--border);
    }
    .sy-card-head > i { font-size: 1.05rem; color: var(--brand); margin-top: 2px; }
    .sy-card-head h6 { margin: 0; font-size: 1rem; font-weight: 700; color: var(--text-primary); }
    .sy-card-head p  { margin: 2px 0 0; font-size: .8rem; color: var(--text-secondary); }
    .sy-card-body { padding: 18px 20px; }

    .sy-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
    @media (max-width: 700px) { .sy-grid { grid-template-columns: 1fr; } }

    .sy-field label {
        display: block; margin-bottom: 4px;
        font-size: .78rem; font-weight: 600; color: var(--text-secondary);
    }
    .sy-input {
        width: 100%; padding: 9px 12px; border-radius: 6px;
        border: 1px solid var(--border); background: var(--bg-subtle);
        color: var(--text-primary); font-size: .9rem;
    }
    .sy-input:focus { outline: none; border-color: var(--brand); }
    .sy-hint { margin: 10px 0 0; font-size: .75rem; line-height: 1.6; color: var(--text-muted); }

    /* The logo is the one setting you can only check by looking at it. */
    .sy-logo-row { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }
    .sy-logo-preview {
        width: 56px; height: 56px; border-radius: 50%; object-fit: cover;
        border: 1px solid var(--border); background: var(--bg-subtle); flex: none;
    }

    .sy-foot {
        display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
        padding-top: 16px; border-top: 1px solid var(--border);
    }
    .sy-updated {
        margin-right: auto; display: inline-flex; align-items: center; gap: 7px;
        font-size: .78rem; color: var(--text-muted);
    }
    .sy-save {
        padding: 9px 22px; border: none; border-radius: 6px;
        background: var(--brand); color: #fff; font-weight: 600; font-size: .85rem; cursor: pointer;
    }
    .sy-save:hover { background: var(--brand-strong); }
</style>
@endpush

@section('content')
<div class="sy-page">

    <div class="sy-header mb-3">
        <h1>System Settings</h1>
        <p>Company identity and account security. Pay rates are in Payroll Settings.</p>
    </div>

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

    <form method="POST" action="{{ route('system-settings.update') }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <div class="sy-card">
            <div class="sy-card-head">
                <i class="fas fa-building"></i>
                <div>
                    <h6>Company identity</h6>
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

        <div class="sy-card">
            <div class="sy-card-head">
                <i class="fas fa-shield-halved"></i>
                <div>
                    <h6>Account &amp; security</h6>
                    <p>How long a session lasts, and how hard it is to guess a password</p>
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
            <button type="submit" class="sy-save"><i class="fas fa-floppy-disk me-1"></i> Save settings</button>
        </div>
    </form>
</div>
@endsection
