<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use App\Models\SystemSetting;

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

    // Ipakita ang Login Form
    public function showLoginForm() {
        return view('login');
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

            return $this->failed($request, "Too many failed attempts. Please try again in {$seconds} second(s).");
        }

        $remember = $request->boolean('remember');

        if (Auth::attempt($credentials, $remember)) {
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

                RateLimiter::hit($throttleKey, $decaySeconds);

                return $this->failed($request, 'This account has been deactivated. Please contact your administrator.');
            }

            // Success: clear throttle + regenerate session (prevents fixation).
            RateLimiter::clear($throttleKey);
            $request->session()->regenerate();

            Auth::user()->forceFill(['last_login_at' => now()])->saveQuietly();

            return redirect()->intended(route('dashboard'));
        }

        // Failed attempt — record it and return a generic message.
        RateLimiter::hit($throttleKey, $decaySeconds);

        return $this->failed($request, 'Invalid username/email or password.');
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
    // access to payroll data.
}
