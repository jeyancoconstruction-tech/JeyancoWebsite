{{--
    Shared create/edit form.

    $account  — the User being edited, or null when creating
    $isSelf   — true when an Admin is editing their own account; role and status
                are locked in that case so nobody can lock themselves out.
--}}
@php
    $account = $account ?? null;
    $isSelf  = $account && $account->id === auth()->id();
    $roleOld = old('role', $account->role ?? \App\Models\User::ROLE_STAFF);
    $active  = (bool) old('is_active', $account->is_active ?? true);
@endphp

<div class="acctf-page">

    <div class="acctf-header">
        <div>
            <h1 class="acctf-title">{{ $account ? 'Edit Account' : 'Create Account' }}</h1>
            <p class="acctf-sub">
                {{ $account
                    ? 'Update the details for this account. Leave the password blank to keep the current one.'
                    : 'Issue a new login. The account can sign in as soon as you save it.' }}
            </p>
        </div>
        <a href="{{ route('accounts.index') }}" class="acctf-back">
            <i class="fas fa-arrow-left"></i> Back to Accounts
        </a>
    </div>

    @if($errors->any())
        <div class="acctf-errors">
            <i class="fas fa-exclamation-circle"></i>
            <ul>
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form method="POST"
          action="{{ $account ? route('accounts.update', $account) : route('accounts.store') }}"
          class="acctf-card" autocomplete="off">
        @csrf
        @if($account) @method('PUT') @endif

        {{-- ── Identity ────────────────────────────────────────────────────── --}}
        <div class="acctf-section">
            <div class="acctf-section-label"><i class="fas fa-id-card"></i> Account Details</div>

            <div class="acctf-grid">
                <div class="acctf-field">
                    <label for="name">Full Name <span class="req">*</span></label>
                    <input type="text" id="name" name="name" value="{{ old('name', $account->name ?? '') }}"
                           class="acctf-input @error('name') bad @enderror"
                           placeholder="e.g., Maria Santos" required maxlength="255">
                    @error('name')<span class="acctf-err">{{ $message }}</span>@enderror
                </div>

                <div class="acctf-field">
                    <label for="username">Username <span class="req">*</span></label>
                    <input type="text" id="username" name="username" value="{{ old('username', $account->username ?? '') }}"
                           class="acctf-input @error('username') bad @enderror"
                           placeholder="e.g., maria.santos" required minlength="3" maxlength="50">
                    @error('username')
                        <span class="acctf-err">{{ $message }}</span>
                    @else
                        <span class="acctf-hint">This is what they type to sign in. Letters, numbers, dots, dashes and underscores.</span>
                    @enderror
                </div>

                <div class="acctf-field acctf-span-2">
                    <label for="email">Email <span class="opt">optional</span></label>
                    <input type="email" id="email" name="email" value="{{ old('email', $account->email ?? '') }}"
                           class="acctf-input @error('email') bad @enderror"
                           placeholder="e.g., maria@jeyanco.com" maxlength="255">
                    @error('email')<span class="acctf-err">{{ $message }}</span>@enderror
                </div>
            </div>
        </div>

        {{-- ── Access ──────────────────────────────────────────────────────── --}}
        <div class="acctf-section">
            <div class="acctf-section-label"><i class="fas fa-shield-halved"></i> Access Level</div>

            @if($isSelf)
                <div class="acctf-note">
                    <i class="fas fa-info-circle"></i>
                    This is your own account, so its role and status are locked. Ask another
                    administrator if these need to change.
                </div>
            @endif

            <div class="acctf-roles">
                <label class="acctf-role {{ $roleOld === \App\Models\User::ROLE_STAFF ? 'picked' : '' }} {{ $isSelf ? 'locked' : '' }}">
                    <input type="radio" name="role" value="{{ \App\Models\User::ROLE_STAFF }}"
                           {{ $roleOld === \App\Models\User::ROLE_STAFF ? 'checked' : '' }}
                           {{ $isSelf ? 'disabled' : '' }}>
                    <span class="acctf-role-body">
                        <span class="acctf-role-name"><i class="fas fa-user"></i> Staff</span>
                        <span class="acctf-role-desc">
                            Dashboard, Attendance, Employees, Sites, Payroll Records, Analytics and
                            Jeyanco AI. No access to Settings or Account Management.
                        </span>
                    </span>
                </label>

                <label class="acctf-role {{ $roleOld === \App\Models\User::ROLE_ADMIN ? 'picked' : '' }} {{ $isSelf ? 'locked' : '' }}">
                    <input type="radio" name="role" value="{{ \App\Models\User::ROLE_ADMIN }}"
                           {{ $roleOld === \App\Models\User::ROLE_ADMIN ? 'checked' : '' }}
                           {{ $isSelf ? 'disabled' : '' }}>
                    <span class="acctf-role-body">
                        <span class="acctf-role-name"><i class="fas fa-user-shield"></i> Administrator</span>
                        <span class="acctf-role-desc">
                            Full access, including Settings, payroll configuration and the ability to
                            create and manage accounts.
                        </span>
                    </span>
                </label>
            </div>

            @if($isSelf)
                {{-- Disabled radios submit nothing — keep the current role in the payload. --}}
                <input type="hidden" name="role" value="{{ $account->role }}">
            @endif

            <label class="acctf-toggle {{ $isSelf ? 'locked' : '' }}">
                <input type="checkbox" name="is_active" value="1"
                       {{ $active ? 'checked' : '' }} {{ $isSelf ? 'disabled' : '' }}>
                <span>
                    <strong>Account is active</strong>
                    <small>Turn this off to block sign-in without deleting the account or its history.</small>
                </span>
            </label>
        </div>

        {{-- ── Password ────────────────────────────────────────────────────── --}}
        <div class="acctf-section">
            <div class="acctf-section-label">
                <i class="fas fa-key"></i>
                {{ $account ? 'Reset Password' : 'Password' }}
            </div>

            <div class="acctf-grid">
                <div class="acctf-field">
                    <label for="password">
                        {{ $account ? 'New Password' : 'Password' }}
                        @if($account)<span class="opt">leave blank to keep current</span>@else<span class="req">*</span>@endif
                    </label>
                    <div class="acctf-pw">
                        <input type="password" id="password" name="password"
                               class="acctf-input @error('password') bad @enderror"
                               placeholder="At least 8 characters" {{ $account ? '' : 'required' }}
                               autocomplete="new-password">
                        <button type="button" class="acctf-pw-eye" data-target="password" title="Show / hide">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    @error('password')
                        <span class="acctf-err">{{ $message }}</span>
                    @else
                        <span class="acctf-hint">Minimum 8 characters, with at least one letter and one number.</span>
                    @enderror
                </div>

                <div class="acctf-field">
                    <label for="password_confirmation">Confirm Password</label>
                    <div class="acctf-pw">
                        <input type="password" id="password_confirmation" name="password_confirmation"
                               class="acctf-input" placeholder="Re-type the password"
                               {{ $account ? '' : 'required' }} autocomplete="new-password">
                        <button type="button" class="acctf-pw-eye" data-target="password_confirmation" title="Show / hide">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
            </div>

            @unless($account)
                <button type="button" id="acctfGenerate" class="acctf-gen">
                    <i class="fas fa-wand-magic-sparkles"></i> Suggest a strong password
                </button>
                <span class="acctf-gen-out" id="acctfGenOut"></span>
            @endunless
        </div>

        {{-- ── Actions ─────────────────────────────────────────────────────── --}}
        <div class="acctf-actions">
            <a href="{{ route('accounts.index') }}" class="acctf-btn ghost">Cancel</a>
            <button type="submit" class="acctf-btn primary">
                <i class="fas fa-{{ $account ? 'save' : 'user-plus' }}"></i>
                {{ $account ? 'Save Changes' : 'Create Account' }}
            </button>
        </div>
    </form>
</div>

<style>
.acctf-page { max-width: 860px; width: 100%; margin: 0; }

.acctf-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 20px; flex-wrap: wrap; }
.acctf-title { font-size: 1.45rem; font-weight: 700; color: #0f172a; margin: 0; }
.acctf-sub { font-size: 13.5px; color: #64748b; margin: 6px 0 0; max-width: 560px; }
.acctf-back {
    display: inline-flex; align-items: center; gap: 7px;
    height: 40px; padding: 0 16px; font-size: 13.5px; font-weight: 600;
    background: #fff; border: 1.5px solid #e2e8f0; border-radius: 8px;
    color: #475569; text-decoration: none; white-space: nowrap;
}
.acctf-back:hover { border-color: #cbd5e1; color: #1e293b; }

.acctf-errors {
    display: flex; gap: 10px; align-items: flex-start;
    background: #fee2e2; border: 1px solid #fecaca; color: #991b1b;
    padding: 12px 15px; border-radius: 10px; margin-bottom: 18px; font-size: 13.5px;
}
.acctf-errors ul { margin: 0; padding-left: 18px; }

.acctf-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; overflow: hidden; }
.acctf-section { padding: 22px 24px; border-bottom: 1px solid #f1f5f9; }
.acctf-section:last-of-type { border-bottom: none; }
.acctf-section-label {
    font-size: 12px; font-weight: 700; letter-spacing: .4px; text-transform: uppercase;
    color: #374151; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;
}
.acctf-section-label i { color: #1e3a8a; }

.acctf-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.acctf-span-2 { grid-column: 1 / -1; }
@media (max-width: 640px) { .acctf-grid { grid-template-columns: 1fr; } }

.acctf-field { display: flex; flex-direction: column; }
.acctf-field label { font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 6px; }
.acctf-field label .req { color: #dc2626; }
.acctf-field label .opt { font-weight: 500; font-size: 11.5px; color: #94a3b8; margin-left: 4px; }
.acctf-input {
    width: 100%; height: 42px; padding: 0 13px; font-size: 14px;
    border: 1.5px solid #e2e8f0; border-radius: 8px;
    background: #fff; color: #0f172a; outline: none;
    transition: border-color .15s, box-shadow .15s;
}
.acctf-input:focus { border-color: #1e3a8a; box-shadow: 0 0 0 3px rgba(30,58,138,.08); }
.acctf-input.bad { border-color: #dc2626; }
.acctf-err  { font-size: 12px; color: #dc2626; margin-top: 5px; }
.acctf-hint { font-size: 12px; color: #94a3b8; margin-top: 5px; }

.acctf-pw { position: relative; }
.acctf-pw-eye {
    position: absolute; right: 6px; top: 50%; transform: translateY(-50%);
    border: none; background: transparent; color: #94a3b8;
    padding: 7px 9px; border-radius: 6px; cursor: pointer; font-size: 13px;
}
.acctf-pw-eye:hover { color: #475569; background: #f1f5f9; }

.acctf-note {
    display: flex; gap: 9px; align-items: flex-start;
    background: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af;
    font-size: 12.5px; padding: 10px 13px; border-radius: 9px; margin-bottom: 14px;
}

.acctf-roles { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
@media (max-width: 640px) { .acctf-roles { grid-template-columns: 1fr; } }
.acctf-role {
    display: flex; gap: 11px; align-items: flex-start; cursor: pointer;
    border: 1.5px solid #e2e8f0; border-radius: 11px; padding: 14px 15px;
    background: #fff; transition: border-color .15s, background .15s;
}
.acctf-role:hover { border-color: #c7d2fe; }
.acctf-role.picked { border-color: #1e3a8a; background: #f8faff; }
.acctf-role.locked { opacity: .6; cursor: not-allowed; }
.acctf-role input { margin-top: 3px; flex-shrink: 0; }
.acctf-role-body { display: flex; flex-direction: column; gap: 4px; }
.acctf-role-name { font-size: 14px; font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 7px; }
.acctf-role-name i { color: #1e3a8a; font-size: 12px; }
.acctf-role-desc { font-size: 12px; color: #64748b; line-height: 1.5; }

.acctf-toggle {
    display: flex; gap: 11px; align-items: flex-start; cursor: pointer;
    margin-top: 14px; padding: 13px 15px;
    border: 1.5px solid #e2e8f0; border-radius: 11px; background: #fff;
}
.acctf-toggle.locked { opacity: .6; cursor: not-allowed; }
.acctf-toggle input { margin-top: 3px; flex-shrink: 0; }
.acctf-toggle strong { display: block; font-size: 13.5px; color: #1e293b; }
.acctf-toggle small  { display: block; font-size: 12px; color: #64748b; margin-top: 2px; }

.acctf-gen {
    margin-top: 14px; border: 1.5px dashed #cbd5e1; background: #f8fafc;
    color: #475569; font-size: 12.5px; font-weight: 600;
    padding: 9px 14px; border-radius: 8px; cursor: pointer;
    display: inline-flex; align-items: center; gap: 7px;
}
.acctf-gen:hover { border-color: #1e3a8a; color: #1e3a8a; }
.acctf-gen-out {
    display: inline-block; margin-left: 10px; font-size: 12.5px; color: #16a34a; font-weight: 600;
}

.acctf-actions {
    display: flex; justify-content: flex-end; gap: 10px;
    padding: 18px 24px; background: #f8fafc; border-top: 1px solid #f1f5f9;
}
.acctf-btn {
    height: 42px; padding: 0 20px; font-size: 13.5px; font-weight: 600;
    border-radius: 8px; border: 1.5px solid transparent; cursor: pointer;
    display: inline-flex; align-items: center; gap: 8px; text-decoration: none;
}
.acctf-btn.primary { background: #1e3a8a; color: #fff; }
.acctf-btn.primary:hover { background: #1e40af; color: #fff; }
.acctf-btn.ghost { background: #fff; border-color: #e2e8f0; color: #475569; }
.acctf-btn.ghost:hover { border-color: #cbd5e1; color: #1e293b; }

/* Dark mode */
[data-bs-theme="dark"] .acctf-title      { color: #e8edf5; }
[data-bs-theme="dark"] .acctf-sub        { color: #6b7d96; }
[data-bs-theme="dark"] .acctf-back       { background: #151d2e; border-color: #283449; color: #9fb0c7; }
[data-bs-theme="dark"] .acctf-back:hover { border-color: #3d4a63; color: #e8edf5; }
[data-bs-theme="dark"] .acctf-card       { background: #151d2e; border-color: #283449; }
[data-bs-theme="dark"] .acctf-section    { border-bottom-color: #1e2637; }
[data-bs-theme="dark"] .acctf-section-label { color: #9fb0c7; }
[data-bs-theme="dark"] .acctf-field label   { color: #cbd5e1; }
[data-bs-theme="dark"] .acctf-input      { background: #0f1a2e; border-color: #283449; color: #e8edf5; }
[data-bs-theme="dark"] .acctf-input:focus{ border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,.12); }
[data-bs-theme="dark"] .acctf-hint       { color: #64748b; }
[data-bs-theme="dark"] .acctf-pw-eye:hover { background: #283449; color: #cbd5e1; }
[data-bs-theme="dark"] .acctf-note       { background: #172554; border-color: #1e3a8a; color: #93c5fd; }
[data-bs-theme="dark"] .acctf-role,
[data-bs-theme="dark"] .acctf-toggle     { background: #1c2740; border-color: #283449; }
[data-bs-theme="dark"] .acctf-role:hover { border-color: #4f46e5; }
[data-bs-theme="dark"] .acctf-role.picked{ border-color: #3b82f6; background: #172554; }
[data-bs-theme="dark"] .acctf-role-name  { color: #e2e8f0; }
[data-bs-theme="dark"] .acctf-role-name i{ color: #60a5fa; }
[data-bs-theme="dark"] .acctf-role-desc,
[data-bs-theme="dark"] .acctf-toggle small { color: #6b7d96; }
[data-bs-theme="dark"] .acctf-toggle strong { color: #e2e8f0; }
[data-bs-theme="dark"] .acctf-gen        { background: #0f1a2e; border-color: #3d4a63; color: #9fb0c7; }
[data-bs-theme="dark"] .acctf-gen:hover  { border-color: #3b82f6; color: #93c5fd; }
[data-bs-theme="dark"] .acctf-actions    { background: #101828; border-top-color: #1e2637; }
[data-bs-theme="dark"] .acctf-btn.ghost  { background: #151d2e; border-color: #283449; color: #9fb0c7; }
[data-bs-theme="dark"] .acctf-btn.ghost:hover { border-color: #3d4a63; color: #e8edf5; }
[data-bs-theme="dark"] .acctf-errors     { background: #450a0a; border-color: #991b1b; color: #fca5a5; }
</style>

<script>
(function () {
    // Highlight the selected role card.
    const cards = document.querySelectorAll('.acctf-role');
    cards.forEach(card => {
        card.addEventListener('click', () => {
            const radio = card.querySelector('input[type=radio]');
            if (!radio || radio.disabled) return;
            cards.forEach(c => c.classList.remove('picked'));
            card.classList.add('picked');
        });
    });

    // Show / hide password fields.
    document.querySelectorAll('.acctf-pw-eye').forEach(btn => {
        btn.addEventListener('click', () => {
            const input = document.getElementById(btn.dataset.target);
            if (!input) return;
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            btn.innerHTML = `<i class="fas fa-eye${show ? '-slash' : ''}"></i>`;
        });
    });

    // Suggest a password that satisfies the rules, and fill both fields.
    const genBtn = document.getElementById('acctfGenerate');
    if (genBtn) {
        genBtn.addEventListener('click', () => {
            const chars  = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
            const bytes  = new Uint32Array(12);
            crypto.getRandomValues(bytes);
            // Force one letter and one digit so the generated value always passes.
            let pw = 'J' + '7';
            bytes.forEach(b => { pw += chars[b % chars.length]; });

            document.getElementById('password').value = pw;
            document.getElementById('password_confirmation').value = pw;
            document.getElementById('acctfGenOut').textContent = 'Filled in — copy it before saving: ' + pw;
        });
    }
})();
</script>
