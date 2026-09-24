<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Auth\Events\Failed;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Laravel\Socialite\Facades\Socialite;
use App\Models\SystemSetting;
use App\Models\User;
use Throwable;

class AuthController extends Controller
{
    /**
     * Max failed attempts before a temporary lockout, and the lockout window.
     * Both are set in System Settings; these are the fallback for a checkout
     * whose settings table does not exist yet, and they are the values that
     * were hardcoded here before it did.
     */
    private const MAX_ATTEMPTS = 5;
    private const DECAY_SECONDS = 60;

    /** The two throttle numbers in force, [attempts, seconds]. */
    private function throttleLimits(): array
    {
        $settings = SystemSetting::current();

        return [
            $settings->max_login_attempts ?: self::MAX_ATTEMPTS,
            $settings->lockout_seconds ?: self::DECAY_SECONDS,
        ];
    }

    /** Whether Sign in with Google has an OAuth client to use. */
    public static function googleConfigured(): bool
    {
        return filled(config('services.google.client_id')) && filled(config('services.google.client_secret'));
    }

    // Ipakita ang Login Form
    public function showLoginForm() {
        return view('login', ['googleSignIn' => self::googleConfigured()]);
    }

    // Logic para sa Login
    public function login(Request $request) {
        $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ], [], [
            'username' => 'username or email',
            'password' => 'password',
        ]);

        // Staff sign in with a username, but accounts may also carry an email
        // and people naturally type that instead. The single box accepts either
        // — anything shaped like an address is matched against the email
        // column, everything else against username. Usernames cannot contain
        // "@" (see AccountController::rules), so the two can never collide.
        $login = trim($request->input('username'));
        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        $credentials = [
            $field     => $login,
            'password' => $request->input('password'),
        ];

        // Brute-force protection: throttle by identifier + IP.
        $throttleKey = Str::lower($login) . '|' . $request->ip();
        [$maxAttempts, $decaySeconds] = $this->throttleLimits();

        if (RateLimiter::tooManyAttempts($throttleKey, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            // The Audit Log records the lockout; nothing else listens.
            event(new \Illuminate\Auth\Events\Lockout($request));

            return $this->failed($request, "Too many failed attempts. Please try again in {$seconds} second(s).");
        }

        // A Google-only account has no password anybody knows — its stored one
        // is random — so no attempt could succeed. Say where its door is
        // instead of calling a correct Google address a wrong password.
        $account = User::where($field, $login)->first();

        if ($account && $account->login_method === User::LOGIN_GOOGLE) {
            RateLimiter::hit($throttleKey, $decaySeconds);

            return $this->failed($request, 'This account signs in with Google. Use Sign in with Google below.');
        }

        $remember = $request->boolean('remember');

        if (Auth::attempt($credentials, $remember)) {
            return $this->admit($request, $throttleKey, $decaySeconds);
        }

        // Failed attempt — record it and return a generic message.
        RateLimiter::hit($throttleKey, $decaySeconds);

        return $this->failed($request, 'Invalid username/email or password.');
    }

    // ── Sign in with Google ─────────────────────────────────────────────────

    /**
     * Send the visitor to Google to choose an account. Always asks which one,
     * so a shared office computer signed in to someone's Gmail does not
     * quietly carry them straight through.
     */
    public function redirectToGoogle(Request $request)
    {
        if (! self::googleConfigured()) {
            return $this->failed($request, 'Sign in with Google is not set up yet.');
        }

        return Socialite::driver('google')
            ->with(['prompt' => 'select_account'])
            ->redirect();
    }

    /**
     * Where Google sends the visitor back.
     *
     * Google only says who this is; the admin decides whether they get in. The
     * Google address must be the email on an account created in Account
     * Management — nobody is registered by signing in, and a deactivated
     * account is refused here exactly as it is at the password form.
     */
    public function handleGoogleCallback(Request $request)
    {
        if (! self::googleConfigured()) {
            return $this->failed($request, 'Sign in with Google is not set up yet.');
        }

        // Backed out on Google's own screen: nothing to fix, nothing to record.
        if ($request->filled('error')) {
            return $this->failed($request, 'Google sign-in was cancelled.');
        }

        try {
            $google = Socialite::driver('google')->user();
        } catch (Throwable $e) {
            // An expired or replayed callback (the state no longer matches),
            // or Google refusing the code. Either way, starting again works.
            report($e);

            return $this->failed($request, 'Google sign-in did not go through. Please try again.');
        }

        $email = Str::lower(trim((string) $google->getEmail()));

        // Only an address Google has verified says whose account this is.
        if ($email === '' || ! ($google->getRaw()['email_verified'] ?? false)) {
            return $this->failed($request, 'That Google account has no verified email address.');
        }

        // Compared without case: the account may have been typed "Maria@…".
        $user = User::whereRaw('LOWER(email) = ?', [$email])->first();

        if (! $user) {
            // The Audit Log shows who tried, so an admin can add them if meant to.
            event(new Failed('web', null, ['email' => $email]));

            return $this->failed($request, "{$email} is not registered. Ask an administrator to add it to your account.");
        }

        if ($user->login_method === User::LOGIN_PASSWORD) {
            event(new Failed('web', $user, ['email' => $email]));

            return $this->failed($request, 'This account signs in with a username and password, not Google.');
        }

        Auth::login($user);

        $response = $this->admit($request);

        // The first Google sign-in links the account: Users & Roles stops
        // showing it as pending.
        if (Auth::check() && ! Auth::user()->google_linked_at) {
            Auth::user()->forceFill(['google_linked_at' => now()])->saveQuietly();
        }

        return $response;
    }

    // ── A password of their own ─────────────────────────────────────────────

    /**
     * The admin set this account's password, so the person picks their own
     * before anything else opens (see EnsurePasswordIsChosen).
     */
    public function showChoosePassword(Request $request)
    {
        if (! $request->user()->must_change_password) {
            return redirect()->route('dashboard');
        }

        return view('auth.choose-password', [
            'minLength' => SystemSetting::current()->password_min_length ?: 8,
        ]);
    }

    public function choosePassword(Request $request)
    {
        $user = $request->user();

        if (! $user->must_change_password) {
            return redirect()->route('dashboard');
        }

        $request->validate([
            'password' => ['required', 'confirmed', Password::min(SystemSetting::current()->password_min_length ?: 8)->letters()->numbers()],
        ], [], ['password' => 'new password']);

        // The point is a password only they know, so the one they were given
        // does not count.
        if (Hash::check($request->input('password'), $user->password)) {
            return back()->withErrors(['password' => 'Choose a different password from the one you were given.']);
        }

        $user->forceFill([
            'password'             => Hash::make($request->input('password')),
            'must_change_password' => false,
        ])->save();

        return redirect()->intended(route('dashboard'))->with('success', 'Your password is set. Use it from now on.');
    }

    /**
     * The steps after a password or Google has said who this is.
     *
     * A deactivated account is turned back here whichever way it came, so the
     * two doors cannot disagree about who may come in.
     */
    private function admit(Request $request, ?string $throttleKey = null, int $decaySeconds = 0)
    {
        // Deactivated accounts keep their records but lose access. Rejected
        // here so they never reach an authenticated page.
        if (! Auth::user()->is_active) {
            Auth::logout();

            // The session is thrown away, which also discards the "previous
            // URL" that back() relies on — so the redirect is aimed at the
            // login route explicitly. Errors and old input are flashed
            // AFTER the new token exists, otherwise the message is lost and
            // the visitor bounces to the form with nothing to explain why.
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($throttleKey) {
                RateLimiter::hit($throttleKey, $decaySeconds);
            }

            return $this->failed($request, 'This account has been deactivated. Please contact your administrator.');
        }

        // Success: clear throttle + regenerate session (prevents fixation).
        if ($throttleKey) {
            RateLimiter::clear($throttleKey);
        }
        $request->session()->regenerate();

        Auth::user()->forceFill(['last_login_at' => now()])->saveQuietly();

        // Start each session on the office's own default. The layout
        // normally lets a remembered choice outrank it — that is what makes
        // the theme toggle stick — so signing in has to say so explicitly,
        // or a browser holding the other theme would keep winning.
        //
        // It reads the setting rather than naming a theme: hardcoding
        // 'light' here made System Settings > Appearance > Default theme
        // do nothing, whatever it was set to.
        $request->session()->flash(
            'force_theme',
            SystemSetting::current()->default_theme ?? 'light'
        );

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Send the visitor back to the login form with a message.
     *
     * Always targets the named route rather than back(): a rejected sign-in may
     * have just invalidated the session, and back() would fall through to "/".
     * The remember checkbox is flashed along with the username so the form comes
     * back exactly as it was filled in — only the password is dropped.
     */
    private function failed(Request $request, string $message) {
        return redirect()->route('login')
            ->withErrors(['username' => $message])
            ->withInput($request->only('username', 'remember'));
    }

    // Logout Function
    public function logout(Request $request) {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login')->with('success', 'You have been signed out.');
    }

    // --- PARA SA REGISTER ---
    // Public self-registration is closed. Accounts are issued by an Admin from
    // Account Management (see AccountController) so nobody can grant themselves
    // access to payroll data — and that includes signing in with Google, which
    // only admits an address already on an account.
}
