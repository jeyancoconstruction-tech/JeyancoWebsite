<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /** Max failed attempts before a temporary lockout, and the lockout window. */
    private const MAX_ATTEMPTS = 5;
    private const DECAY_SECONDS = 60;

    // Ipakita ang Login Form
    public function showLoginForm() {
        return view('login');
    }

    // Logic para sa Login
    public function login(Request $request) {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        // Brute-force protection: throttle by username + IP.
        $throttleKey = Str::lower($request->input('username')) . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return back()->withErrors([
                'username' => "Too many failed attempts. Please try again in {$seconds} second(s).",
            ])->onlyInput('username');
        }

        $remember = $request->boolean('remember');

        if (Auth::attempt($credentials, $remember)) {
            // Deactivated accounts keep their records but lose access. Rejected
            // here so they never reach an authenticated page.
            if (! Auth::user()->is_active) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                RateLimiter::hit($throttleKey, self::DECAY_SECONDS);

                return back()->withErrors([
                    'username' => 'This account has been deactivated. Please contact your administrator.',
                ])->onlyInput('username');
            }

            // Success: clear throttle + regenerate session (prevents fixation).
            RateLimiter::clear($throttleKey);
            $request->session()->regenerate();

            Auth::user()->forceFill(['last_login_at' => now()])->saveQuietly();

            return redirect()->intended('dashboard');
        }

        // Failed attempt — record it and return a generic message.
        RateLimiter::hit($throttleKey, self::DECAY_SECONDS);

        return back()->withErrors([
            'username' => 'Invalid username or password.',
        ])->onlyInput('username');
    }

    // Logout Function
    public function logout(Request $request) {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }

    // --- PARA SA REGISTER ---
    // Public self-registration is closed. Accounts are issued by an Admin from
    // Account Management (see AccountController) so nobody can grant themselves
    // access to payroll data.
}