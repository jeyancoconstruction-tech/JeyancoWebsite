{{--
    Shared create/edit form, after Michael's design in docs/create-account.html.

    $account      — the User being edited, or null when creating
    $googleSignIn — whether Sign in with Google is set up; a Google-only account
                    is refused without it (AccountController::rules)

    An Admin editing their own account cannot change its role or status — that
    is the quickest way to lock everyone out.
--}}
@php
    use App\Models\User;
    use App\Support\Modules;

    $account = $account ?? null;
    $editing = $account !== null;
    $isSelf  = $editing && $account->id === auth()->id();
    $google  = (bool) ($googleSignIn ?? false);

    // The name in two parts. An account from before the split that somehow
    // has none is shown split the same way the migration would.
    [$splitFirst, $splitLast] = $editing && blank($account->first_name) ? User::splitName((string) $account->name) : [null, null];

    $state = [
        'method'         => old('login_method', $account->login_method ?? User::LOGIN_BOTH),
        'role'           => $isSelf ? $account->role : old('role', $account->role ?? User::ROLE_HR),
        'active'         => $isSelf ? true : (bool) old('is_active', $account->is_active ?? true),
        'editing'        => $editing,
        'originalMethod' => $account->login_method ?? null,
        'originalEmail'  => $account->email ?? '',
        'linked'         => (bool) ($account?->google_linked_at),
        'googleReady'    => $google,
    ];

    // What each role opens, straight from the permission map.
    $labels = Modules::labels();
    $access = [];
    foreach (array_keys(User::ROLES) as $r) {
        $access[$r] = Modules::forRole($r);
    }
    $mods = [];
    foreach (Modules::groups() as $group => $keys) {
        foreach ($keys as $key) {
            $mods[] = ['key' => $key, 'label' => $labels[$key], 'group' => Modules::isAdminOnly($key) ? 'Admin only' : $group];
        }
    }
    $openNow = $access[$state['role']] ?? [];

    $fieldError = fn (string $f) => $errors->has($f) ? 'bad' : '';
    $json = fn ($v) => json_encode($v, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);

    $checkSvg = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12l5 5L20 7"/></svg>';
    $lockSvg  = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>';
    $gLogo    = '<svg width="18" height="18" viewBox="0 0 48 48" aria-hidden="true"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/></svg>';
@endphp

<div class="ca-page">
    <header class="ca-top">
        <div>
            <nav class="ca-crumb" aria-label="Breadcrumb">
                <a href="{{ route('users-roles.index') }}">{{ __('Users & Roles') }}</a>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>
                <b>{{ $editing ? __('Edit Account') : __('Create Account') }}</b>
            </nav>
            <h1>{{ $editing ? __('Edit Account') : __('Create Account') }}</h1>
            <p class="ca-sub">
                @if($editing)
                    {{ __('Update :name’s account. Leave the password blank to keep the current one.', ['name' => $account->name]) }}
                @else
                    {{ __('Add someone who can sign in to Jeyanco Payroll. Only accounts listed here can use Sign in with Google.') }}
                @endif
            </p>
        </div>
        <a class="ca-btn" href="{{ route('users-roles.index') }}">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5"/><path d="M11 18l-6-6 6-6"/></svg>
            {{ __('Back to Accounts') }}
        </a>
    </header>

    @if($errors->any())
        <div class="ca-errors" role="alert">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>
            <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="ca-grid">
        <form class="ca-card ca-form" id="caForm" method="POST" autocomplete="off" novalidate
              action="{{ $editing ? route('accounts.update', $account) : route('accounts.store') }}">
            @csrf
            @if($editing) @method('PUT') @endif

            <input type="hidden" name="login_method" id="caMethod" value="{{ $state['method'] }}">
            <input type="hidden" name="role" id="caRole" value="{{ $state['role'] }}">
            <input type="hidden" name="is_active" id="caActive" value="{{ $state['active'] ? 1 : 0 }}">

            {{-- ── 1 · Profile ─────────────────────────────────────────── --}}
            <section class="ca-sec">
                <div class="ca-sh"><div><span class="ca-num">1</span><h2>{{ __('Profile') }}</h2></div></div>
                <div class="ca-cols">
                    <div class="ca-field">
                        <label class="ca-l" for="first_name">{{ __('First name') }} <span class="ca-req">*</span></label>
                        <input id="first_name" name="first_name" type="text" class="ca-in {{ $fieldError('first_name') }}" maxlength="100"
                               value="{{ old('first_name', $account->first_name ?? $splitFirst) }}" placeholder="{{ __('e.g., Maria') }}">
                        @error('first_name')<span class="ca-err">{{ $message }}</span>@enderror
                    </div>
                    <div class="ca-field">
                        <label class="ca-l" for="last_name">{{ __('Last name') }} <span class="ca-req">*</span></label>
                        <input id="last_name" name="last_name" type="text" class="ca-in {{ $fieldError('last_name') }}" maxlength="100"
                               value="{{ old('last_name', $account->last_name ?? $splitLast) }}" placeholder="{{ __('e.g., Santos') }}">
                        @error('last_name')<span class="ca-err">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div class="ca-cols">
                    <div class="ca-field">
                        <label class="ca-l" for="username">{{ __('Username') }} <span class="ca-req">*</span></label>
                        <input id="username" name="username" type="text" class="ca-in ca-mono {{ $fieldError('username') }}" minlength="3" maxlength="50"
                               value="{{ old('username', $account->username ?? '') }}" placeholder="{{ __('e.g., maria.santos') }}" spellcheck="false" autocapitalize="none">
                        @error('username')
                            <span class="ca-err">{{ $message }}</span>
                        @else
                            <span class="ca-hint">{{ $editing ? __('Letters, numbers, dots, dashes and underscores.') : __('Auto-filled from the name. Letters, numbers, dots, dashes and underscores.') }}</span>
                        @enderror
                    </div>
                </div>
            </section>

            {{-- ── 2 · How they sign in ────────────────────────────────── --}}
            <section class="ca-sec">
                <div class="ca-sh"><div><span class="ca-num">2</span><h2>{{ __('How they sign in') }}</h2></div><span class="ca-hint">{{ __('You can change this later') }}</span></div>

                <div class="ca-choices" role="group" aria-label="{{ __('Sign-in method') }}">
                    <button type="button" class="ca-choice" data-method="{{ User::LOGIN_BOTH }}" aria-pressed="false">
                        <span class="ca-head">
                            <span class="ca-ic"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 15l6-6"/><path d="M11 6l.46-.54a5 5 0 0 1 7.07 7.07L18 13"/><path d="M13 18l-.4.53a5.07 5.07 0 0 1-7.12 0 4.97 4.97 0 0 1 0-7.07L6 11"/></svg></span>
                            <span class="ca-rec">{{ __('RECOMMENDED') }}</span>
                        </span>
                        <span class="ca-t">{{ __('Both') }}</span>
                        <span class="ca-d">{{ __('Google, with a password as backup if they lose access to their Gmail.') }}</span>
                    </button>
                    <button type="button" class="ca-choice" data-method="{{ User::LOGIN_GOOGLE }}" aria-pressed="false" @disabled(! $google)>
                        <span class="ca-head"><span class="ca-ic g">{!! $gLogo !!}</span></span>
                        <span class="ca-t">{{ __('Google only') }}</span>
                        <span class="ca-d">{{ $google ? __('No password to manage. Signs in with their Gmail.') : __('Needs Sign in with Google to be set up on this system first.') }}</span>
                    </button>
                    <button type="button" class="ca-choice" data-method="{{ User::LOGIN_PASSWORD }}" aria-pressed="false">
                        <span class="ca-head"><span class="ca-ic">{!! $lockSvg !!}</span></span>
                        <span class="ca-t">{{ __('Password only') }}</span>
                        <span class="ca-d">{{ __('Username and password. For staff without Gmail.') }}</span>
                    </button>
                </div>
                @error('login_method')<span class="ca-err">{{ $message }}</span>@enderror

                <div class="ca-field">
                    <label class="ca-l" for="email"><span id="caEmailLabel">{{ __('Google account email') }}</span> <span class="ca-req" id="caEmailReq">*</span><span class="ca-opt" id="caEmailOpt" hidden>{{ __('optional') }}</span></label>
                    <div class="ca-iconin">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/></svg>
                        <input id="email" name="email" type="email" class="ca-in {{ $fieldError('email') }}" maxlength="255" spellcheck="false" autocapitalize="none"
                               value="{{ old('email', $account->email ?? '') }}" placeholder="{{ __('e.g., maria.santos@gmail.com') }}">
                    </div>
                    @error('email')
                        <span class="ca-err">{{ $message }}</span>
                    @else
                        <span class="ca-hint" id="caEmailHelp">{{ __('Must be the exact Gmail or Workspace address they will pick on the Google screen.') }}</span>
                    @enderror
                </div>

                <div class="ca-note" id="caGoogleNote">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8h.01"/><path d="M11 12h1v4h1"/></svg>
                    <span id="caNoteText"></span>
                </div>

                <div class="ca-pw" id="caPwBlock">
                    <div class="ca-cols">
                        <div class="ca-field">
                            <label class="ca-l" for="password">
                                {{ $editing ? __('New password') : __('Password') }}
                                <span class="ca-req" id="caPwReq">*</span><span class="ca-opt" id="caPwOpt" hidden>{{ __('leave blank to keep') }}</span>
                            </label>
                            <div class="ca-row">
                                <input id="password" name="password" type="password" class="ca-in {{ $fieldError('password') }}" autocomplete="new-password"
                                       placeholder="{{ __('At least :n characters', ['n' => \App\Models\SystemSetting::current()->password_min_length ?: 8]) }}">
                                <button type="button" class="ca-btn" id="caGen">{{ __('Generate') }}</button>
                            </div>
                            @error('password')<span class="ca-err">{{ $message }}</span>@enderror
                        </div>
                        <div class="ca-field">
                            <label class="ca-l" for="password_confirmation">{{ __('Confirm password') }} <span class="ca-req" id="caPw2Req">*</span></label>
                            <input id="password_confirmation" name="password_confirmation" type="password" class="ca-in" autocomplete="new-password" placeholder="{{ __('Type it again') }}">
                        </div>
                    </div>
                    <label class="ca-check">
                        <input type="hidden" name="must_change_password" value="0">
                        <input id="must_change_password" name="must_change_password" type="checkbox" value="1" @checked(old('must_change_password', '1') === '1')>
                        {{ __('Ask them to change the password on first sign-in') }}
                    </label>
                </div>
            </section>

            {{-- ── 3 · Access level ────────────────────────────────────── --}}
            <section class="ca-sec">
                <div class="ca-sh"><div><span class="ca-num">3</span><h2>{{ __('Access level') }}</h2></div></div>
                @if($isSelf)
                    <div class="ca-note">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8h.01"/><path d="M11 12h1v4h1"/></svg>
                        <span>{{ __('This is your own account, so its access level and status are locked. Ask another administrator if these need to change.') }}</span>
                    </div>
                @endif
                <div class="ca-roles" role="group" aria-label="{{ __('Access level') }}">
                    <button type="button" class="ca-choice ca-role" data-role="{{ User::ROLE_HR }}" aria-pressed="false" @disabled($isSelf)>
                        <span class="ca-ic"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M6 21v-2a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v2"/></svg></span>
                        <span class="ca-txt"><span class="ca-t">{{ __('HR') }}</span><span class="ca-d">{{ __('Day-to-day: attendance, employees, leave, advances, projects, payroll and reports. No Settings, Users & Roles or Audit Logs.') }}</span></span>
                    </button>
                    <button type="button" class="ca-choice ca-role" data-role="{{ User::ROLE_ADMIN }}" aria-pressed="false" @disabled($isSelf)>
                        <span class="ca-ic"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3l8 4v5c0 5-3.5 8.5-8 9-4.5-.5-8-4-8-9V7z"/><path d="M9 12l2 2 4-4"/></svg></span>
                        <span class="ca-txt"><span class="ca-t">{{ __('Administrator') }}</span><span class="ca-d">{{ __('Full access, including Settings, payroll configuration and managing accounts.') }}</span></span>
                    </button>
                </div>
                @error('role')<span class="ca-err">{{ $message }}</span>@enderror
            </section>

            <section class="ca-sec ca-status">
                <div>
                    <div class="ca-t">{{ __('Account is active') }}</div>
                    <div class="ca-d">{{ __('Turn off to block sign-in (password and Google) without deleting history.') }}</div>
                    @error('is_active')<span class="ca-err">{{ $message }}</span>@enderror
                </div>
                <button type="button" class="ca-switch" id="caSwitch" aria-pressed="true" aria-label="{{ __('Account is active') }}" @disabled($isSelf)><span></span></button>
            </section>

            <div class="ca-foot">
                <a class="ca-btn ghost" href="{{ route('users-roles.index') }}">{{ __('Cancel') }}</a>
                <button type="submit" class="ca-btn primary">
                    {!! $checkSvg !!}
                    {{ $editing ? __('Save changes') : __('Create account') }}
                </button>
            </div>
        </form>

        <aside class="ca-aside">
            <div class="ca-card ca-side">
                <span class="ca-eyebrow">{{ __('ACCOUNT PREVIEW') }}</span>
                <div class="ca-who">
                    <span class="ca-av" id="pvAv"></span>
                    <div><div class="ca-n" id="pvName"></div><div class="ca-u" id="pvUser"></div></div>
                </div>
                <div class="ca-pills"><span class="ca-pill role" id="pvRole"></span><span class="ca-pill" id="pvStatus"></span></div>
                <div class="ca-hr"></div>
                <span class="ca-eyebrow">{{ __('THEIR SIGN-IN SCREEN') }}</span>
                <div class="ca-mini" aria-hidden="true">
                    <span class="ca-logo">JEYANCO</span>
                    <div class="ca-mpw" id="mPw">
                        <div class="ca-mf">{{ __('Username') }}</div><div class="ca-mf">{{ __('Password') }}</div><div class="ca-ms">{{ __('Sign in') }}</div>
                    </div>
                    <div class="ca-mor" id="mOr"><i></i>{{ __('or') }}<i></i></div>
                    <div class="ca-mg" id="mG">{!! $gLogo !!} {{ __('Sign in with Google') }}</div>
                </div>
            </div>

            <div class="ca-card ca-side">
                <div class="ca-sh"><span class="ca-eyebrow">{{ __('CAN OPEN') }}</span><span class="ca-count"><b id="modCount">{{ count($openNow) }}</b> {{ __('of :n modules', ['n' => count($mods)]) }}</span></div>
                <div class="ca-mods">
                    @foreach($mods as $m)
                        @php $on = in_array($m['key'], $openNow, true); @endphp
                        <div class="ca-mod {{ $on ? '' : 'off' }}" data-module="{{ $m['key'] }}">
                            <span class="ca-mk on">{!! $checkSvg !!}</span><span class="ca-mk lock">{!! $lockSvg !!}</span>
                            <span>{{ $m['label'] }}</span><span class="ca-grp">{{ $m['group'] }}</span>
                        </div>
                    @endforeach
                </div>
                <span class="ca-hint">{{ __('Dashboard, Attendance, Employees, Sites, Payroll Records, Analytics and Jeyanco AI are open to every role.') }}</span>
            </div>
        </aside>
    </div>
</div>

<style>
/* Create / Edit Account. Built on the theme tokens so it reads right in light
   and dark alike; the layout, sizes and pieces are the design's. */
.ca-page { max-width: 1280px; width: 100%; margin: 0 auto; display: flex; flex-direction: column; gap: 24px; color: var(--text-primary); }
.ca-top { display: flex; justify-content: space-between; align-items: flex-end; gap: 20px; flex-wrap: wrap; }
.ca-crumb { display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--text-muted); }
.ca-crumb a { color: var(--text-muted); text-decoration: none; }
.ca-crumb a:hover { color: var(--text-primary); }
.ca-crumb b { color: var(--brand); font-weight: 600; }
.ca-page h1 { margin: 6px 0 4px; font-size: 30px; font-weight: 800; letter-spacing: -.02em; text-wrap: balance; color: var(--text-primary); }
.ca-sub { margin: 0; color: var(--text-secondary); max-width: 62ch; }

.ca-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; height: 46px; padding: 0 18px; border-radius: 12px; font-size: 14px; font-weight: 600; cursor: pointer; text-decoration: none; border: 1px solid var(--border-md); background: var(--surface); color: var(--text-primary); white-space: nowrap; }
.ca-btn:hover { border-color: var(--text-muted); color: var(--text-primary); }
.ca-btn.primary { background: var(--brand); border-color: var(--brand); color: #fff; font-weight: 700; }
.ca-btn.primary:hover { background: var(--brand-strong); border-color: var(--brand-strong); color: #fff; }
.ca-btn.ghost { background: transparent; }

.ca-errors { display: flex; gap: 12px; align-items: flex-start; padding: 14px 16px; border-radius: 12px; background: var(--danger-soft); color: var(--danger); font-size: 13.5px; }
.ca-errors svg { flex-shrink: 0; margin-top: 1px; }
.ca-errors ul { margin: 0; padding-left: 18px; }

.ca-grid { display: grid; grid-template-columns: minmax(0, 1fr) 360px; gap: 24px; align-items: start; }
.ca-card { background: var(--surface); border: 1px solid var(--border); border-radius: 18px; }
.ca-form { overflow: hidden; }
.ca-sec { padding: 26px 28px; display: flex; flex-direction: column; gap: 18px; border-bottom: 1px solid var(--border); }
.ca-sh { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
.ca-sh > div { display: flex; align-items: center; gap: 12px; }
.ca-num { width: 28px; height: 28px; border-radius: 8px; background: var(--brand-subtle); color: var(--brand); display: grid; place-items: center; font-size: 13px; font-weight: 700; }
.ca-page h2 { margin: 0; font-size: 16px; font-weight: 700; color: var(--text-primary); }
.ca-hint { font-size: 12px; color: var(--text-muted); }
.ca-err { font-size: 12px; color: var(--danger); }
.ca-cols { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px; }
.ca-field { display: flex; flex-direction: column; gap: 8px; }
.ca-l { font-size: 14px; font-weight: 600; display: flex; gap: 6px; align-items: center; margin: 0; color: var(--text-primary); }
.ca-req { color: var(--danger); }
.ca-opt { font-size: 12px; color: var(--text-muted); font-weight: 500; }
.ca-in { height: 48px; width: 100%; padding: 0 16px; border-radius: 12px; border: 1px solid var(--border-md); background: var(--bg-subtle); color: var(--text-primary); font-size: 15px; }
.ca-in::placeholder { color: var(--text-muted); opacity: .7; }
.ca-in:focus { outline: none; border-color: var(--brand); box-shadow: 0 0 0 3px color-mix(in srgb, var(--brand) 18%, transparent); }
.ca-in.bad { border-color: var(--danger); }
.ca-mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace !important; }
.ca-iconin { position: relative; }
.ca-iconin svg { position: absolute; left: 15px; top: 15px; color: var(--text-muted); }
.ca-iconin .ca-in { padding-left: 44px; }
.ca-row { display: flex; gap: 8px; }
.ca-row .ca-in { flex: 1; min-width: 0; }
.ca-row .ca-btn { height: 48px; }
/* Edge draws its own reveal eye inside a password box. */
.ca-in::-ms-reveal, .ca-in::-ms-clear { display: none; }

.ca-choices { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
.ca-roles { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
.ca-choice { text-align: left; padding: 16px; border-radius: 14px; border: 1.5px solid var(--border); background: var(--bg-subtle); color: var(--text-primary); display: flex; flex-direction: column; gap: 8px; cursor: pointer; font: inherit; transition: border-color .15s, background .15s; }
.ca-choice:hover:not(:disabled) { border-color: var(--border-md); }
.ca-choice[aria-pressed="true"] { border-color: var(--brand); background: var(--brand-subtle); }
.ca-choice:disabled { cursor: not-allowed; opacity: .55; }
.ca-choice:focus-visible, .ca-switch:focus-visible, .ca-btn:focus-visible { outline: 2px solid var(--brand); outline-offset: 2px; }
.ca-t { font-size: 15px; font-weight: 700; color: var(--text-primary); }
.ca-d { font-size: 13px; color: var(--text-secondary); line-height: 1.45; }
.ca-head { display: flex; justify-content: space-between; align-items: center; width: 100%; }
.ca-ic { width: 34px; height: 34px; border-radius: 10px; background: var(--brand-subtle); color: var(--brand); display: grid; place-items: center; flex-shrink: 0; }
.ca-ic.g { background: #fff; border: 1px solid var(--border); }
.ca-rec { font-size: 11px; font-weight: 700; letter-spacing: .05em; color: var(--success); background: var(--success-soft); padding: 4px 8px; border-radius: 999px; }
.ca-role { flex-direction: row; gap: 14px; padding: 18px; }
.ca-role .ca-ic { width: 38px; height: 38px; }
.ca-txt { display: flex; flex-direction: column; gap: 6px; }

.ca-note { display: flex; gap: 12px; padding: 14px 16px; border-radius: 12px; background: var(--brand-subtle); border: 1px solid color-mix(in srgb, var(--brand) 28%, transparent); font-size: 13px; color: var(--text-secondary); line-height: 1.5; }
.ca-note svg { flex-shrink: 0; margin-top: 1px; color: var(--brand); }
.ca-note .pending { color: var(--warning); }
.ca-note .linked { color: var(--success); }
.ca-pw { display: flex; flex-direction: column; gap: 14px; }
.ca-check { display: flex; align-items: center; gap: 10px; font-size: 14px; color: var(--text-secondary); cursor: pointer; margin: 0; }
.ca-check input[type="checkbox"] { width: 18px; height: 18px; accent-color: var(--brand); }

.ca-status { flex-direction: row; align-items: center; justify-content: space-between; padding: 22px 28px; border-bottom: 0; }
.ca-switch { width: 52px; height: 30px; border-radius: 999px; border: 0; padding: 3px; display: flex; flex-shrink: 0; cursor: pointer; background: var(--success); justify-content: flex-end; transition: background .15s; }
.ca-switch[aria-pressed="false"] { background: var(--border-md); justify-content: flex-start; }
.ca-switch:disabled { cursor: not-allowed; opacity: .6; }
.ca-switch span { width: 24px; height: 24px; border-radius: 999px; background: #fff; box-shadow: 0 1px 2px rgba(0,0,0,.2); }
.ca-foot { padding: 18px 28px; background: var(--bg-subtle); border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 12px; flex-wrap: wrap; }

.ca-aside { display: flex; flex-direction: column; gap: 16px; position: sticky; top: 76px; }
.ca-side { padding: 22px; display: flex; flex-direction: column; gap: 16px; }
.ca-eyebrow { font-size: 12px; font-weight: 700; letter-spacing: .08em; color: var(--text-muted); }
.ca-who { display: flex; align-items: center; gap: 12px; }
.ca-av { width: 46px; height: 46px; border-radius: 999px; background: var(--brand); color: #fff; display: grid; place-items: center; font-weight: 700; font-size: 17px; flex-shrink: 0; }
.ca-n { font-weight: 700; font-size: 16px; overflow-wrap: anywhere; color: var(--text-primary); }
.ca-u { font-size: 13px; color: var(--text-muted); font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; overflow-wrap: anywhere; }
.ca-pills { display: flex; flex-wrap: wrap; gap: 8px; }
.ca-pill { font-size: 12px; font-weight: 600; padding: 5px 10px; border-radius: 999px; }
.ca-pill.role { background: var(--brand-subtle); color: var(--brand); font-weight: 700; }
.ca-pill.warn { background: var(--warning-soft); color: var(--warning); }
.ca-pill.ok   { background: var(--success-soft); color: var(--success); }
.ca-pill.bad  { background: var(--danger-soft); color: var(--danger); }
.ca-hr { height: 1px; background: var(--border); }
.ca-mini { background: var(--bg); border: 1px solid var(--border); border-radius: 14px; padding: 18px; display: flex; flex-direction: column; gap: 10px; }
.ca-logo { font-size: 13px; font-weight: 800; letter-spacing: .12em; text-align: center; color: var(--text-secondary); }
.ca-mpw { display: flex; flex-direction: column; gap: 8px; }
.ca-mf { height: 34px; border-radius: 8px; background: var(--surface); border: 1px solid var(--border); font-size: 12px; color: var(--text-muted); display: flex; align-items: center; padding: 0 10px; }
.ca-ms { height: 34px; border-radius: 8px; background: var(--brand); font-size: 12px; font-weight: 700; color: #fff; display: grid; place-items: center; }
.ca-mor { display: flex; align-items: center; gap: 8px; font-size: 11px; color: var(--text-muted); }
.ca-mor i { flex: 1; height: 1px; background: var(--border); }
.ca-mg { height: 36px; border-radius: 8px; background: #fff; border: 1px solid var(--border); color: #1f2937; font-size: 12px; font-weight: 700; display: flex; align-items: center; justify-content: center; gap: 8px; }
.ca-mg svg { width: 14px; height: 14px; }
/* The hidden attribute has to beat each piece's own display. */
.ca-page [hidden] { display: none !important; }
.ca-mods { display: flex; flex-direction: column; gap: 2px; }
.ca-mod { display: flex; align-items: center; gap: 10px; padding: 7px 10px; border-radius: 10px; font-size: 14px; color: var(--text-primary); }
.ca-mod .ca-mk { display: inline-flex; }
.ca-mod .ca-mk.on { color: var(--success); }
.ca-mod .ca-mk.lock, .ca-mod.off .ca-mk.on { display: none; }
.ca-mod.off .ca-mk.lock { display: inline-flex; color: var(--text-muted); }
.ca-mod.off { color: var(--text-muted); }
.ca-grp { margin-left: auto; font-size: 11px; color: var(--text-muted); }
.ca-count { font-size: 13px; color: var(--text-secondary); }
.ca-count b { color: var(--brand); font-size: 16px; font-variant-numeric: tabular-nums; }

@media (max-width: 1100px) {
    .ca-grid { grid-template-columns: 1fr; }
    .ca-aside { position: static; }
}
@media (max-width: 680px) {
    .ca-sec, .ca-foot, .ca-status { padding-left: 18px; padding-right: 18px; }
    .ca-cols, .ca-choices, .ca-roles { grid-template-columns: 1fr; }
    .ca-page h1 { font-size: 26px; }
    .ca-foot .ca-btn { flex: 1; }
}
@media (prefers-reduced-motion: reduce) { .ca-page * { transition: none !important; } }
</style>

<script>
(function () {
    const S       = {!! $json($state) !!};
    const ACCESS  = {!! $json($access) !!};
    const ROLES   = {!! $json(User::ROLES) !!};
    const $       = id => document.getElementById(id);

    const TEXT = {
        gLabel: @json(__('Google account email')),
        eLabel: @json(__('Email')),
        gHelp:  @json(__('Must be the exact Gmail or Workspace address they will pick on the Google screen.')),
        eHelp:  @json(__('Used only for password reset links.')),
    };

    function initials(n) {
        const p = n.trim().split(/\s+/).filter(Boolean);
        return ((p[0] || '?')[0] + (p.length > 1 ? p[p.length - 1][0] : '')).toUpperCase();
    }

    // Linked only while the address is the one that signed in.
    const linkedNow = () => S.linked && $('email').value.trim().toLowerCase() === (S.originalEmail || '').toLowerCase();

    function render() {
        const g = S.method !== 'password', pw = S.method !== 'google';

        document.querySelectorAll('[data-method]').forEach(b => b.setAttribute('aria-pressed', String(b.dataset.method === S.method)));
        document.querySelectorAll('[data-role]').forEach(b => b.setAttribute('aria-pressed', String(b.dataset.role === S.role)));
        $('caMethod').value = S.method;
        $('caRole').value   = S.role;
        $('caActive').value = S.active ? 1 : 0;
        $('caSwitch').setAttribute('aria-pressed', String(S.active));

        $('caEmailLabel').textContent = g ? TEXT.gLabel : TEXT.eLabel;
        $('caEmailReq').hidden = !g; $('caEmailOpt').hidden = g;
        if ($('caEmailHelp')) $('caEmailHelp').textContent = g ? TEXT.gHelp : TEXT.eHelp;

        $('caGoogleNote').hidden = !g;
        $('caNoteText').innerHTML = linkedNow()
            ? @json(__('Already <strong class="linked">Linked</strong> — this address has signed in with Google. Changing the email sends it back to <strong class="pending">Pending</strong>.'))
            : @json(__('The account shows as <strong class="pending">Pending</strong> until they sign in with Google for the first time. After that it shows <strong class="linked">Linked</strong> in Users & Roles.'));

        // A password is needed wherever one will be used and none exists yet.
        $('caPwBlock').hidden = !pw;
        const pwNeeded = !S.editing || S.originalMethod === 'google';
        $('caPwReq').hidden = !pwNeeded; $('caPw2Req').hidden = !pwNeeded; $('caPwOpt').hidden = pwNeeded;

        $('mPw').hidden = !pw; $('mG').hidden = !g; $('mOr').hidden = S.method !== 'both';

        let t = @json(__('Pending first Google sign-in')), c = 'warn';
        if (S.method === 'google' && linkedNow()) { t = @json(__('Linked')); c = 'ok'; }
        if (S.method === 'password') { t = @json(__('Ready to sign in')); c = 'ok'; }
        if (S.method === 'both') {
            t = linkedNow() ? @json(__('Password ready · Google linked')) : @json(__('Password ready · Google pending'));
            c = linkedNow() ? 'ok' : 'warn';
        }
        if (!S.active) { t = @json(__('Inactive')); c = 'bad'; }
        $('pvStatus').textContent = t; $('pvStatus').className = 'ca-pill ' + c;

        $('pvRole').textContent = ROLES[S.role] || S.role;
        const open = ACCESS[S.role] || [];
        document.querySelectorAll('.ca-mod').forEach(el => el.classList.toggle('off', !open.includes(el.dataset.module)));
        $('modCount').textContent = open.length;
    }

    document.querySelectorAll('[data-method]').forEach(b => b.addEventListener('click', () => { if (!b.disabled) { S.method = b.dataset.method; render(); } }));
    document.querySelectorAll('[data-role]').forEach(b => b.addEventListener('click', () => { if (!b.disabled) { S.role = b.dataset.role; render(); } }));
    $('caSwitch').addEventListener('click', () => { if (!$('caSwitch').disabled) { S.active = !S.active; render(); } });
    $('email').addEventListener('input', render);

    // Name → preview, and → username until someone types one of their own.
    let userEdited = S.editing || $('username').value !== '';
    const slug = s => s.trim().toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/ñ/g, 'n').replace(/[^a-z0-9]+/g, '');
    function onName() {
        const f = $('first_name').value.trim(), l = $('last_name').value.trim();
        const full = [f, l].filter(Boolean).join(' ') || @json(__('Full name'));
        $('pvName').textContent = full; $('pvAv').textContent = initials(full);
        if (!userEdited) {
            $('username').value = [slug(f), slug(l)].filter(Boolean).join('.');
        }
        $('pvUser').textContent = $('username').value || @json(__('username'));
    }
    $('first_name').addEventListener('input', onName);
    $('last_name').addEventListener('input', onName);
    $('username').addEventListener('input', e => { userEdited = e.target.value !== ''; $('pvUser').textContent = e.target.value || @json(__('username')); });

    // Generate: always a letter and a number, which the password rule needs.
    $('caGen').addEventListener('click', () => {
        const cs = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789!@#$%';
        let p = '';
        do {
            const a = new Uint32Array(12); crypto.getRandomValues(a);
            p = Array.from(a, x => cs[x % cs.length]).join('');
        } while (!/[A-Za-z]/.test(p) || !/[0-9]/.test(p));
        $('password').value = p; $('password_confirmation').value = p;
        $('password').type = 'text'; $('password_confirmation').type = 'text';
    });

    onName();
    render();
})();
</script>
