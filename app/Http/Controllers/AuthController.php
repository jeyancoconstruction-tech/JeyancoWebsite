<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
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
            // Admin-only area: reject non-admin accounts gracefully (instead of a
            // post-login 403) without leaving them authenticated.
            if (! Auth::user()->is_admin) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                RateLimiter::hit($throttleKey, self::DECAY_SECONDS);

                return back()->withErrors([
                    'username' => 'This account does not have administrator access.',
                ])->onlyInput('username');
            }

            // Success: clear throttle + regenerate session (prevents fixation).
            RateLimiter::clear($throttleKey);
            $request->session()->regenerate();

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
    public function showRegisterForm() {
        return view('auth_register'); // Gawin mo ring blade ito
    }

    public function registerPost(Request $request) {
        $request->validate([
            'username' => 'required|unique:users,username',
            'password' => 'required|min:6',
        ]);

        User::create([
            'name'     => $request->username,
            'username' => $request->username,
            'password' => Hash::make($request->password), // Password encryption
        ]);

        return redirect()->route('login')->with('success', 'Account Created.');
    }
}