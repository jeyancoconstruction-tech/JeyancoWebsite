<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

/**
 * Self-service password reset.
 *
 * Staff sign in with a username, but an account may also carry an email — and
 * the email is the only thing a reset link can be delivered to. So the request
 * form accepts either identifier, resolves it to an account, and mails the link
 * to whatever address is on file. Accounts with no email (and deactivated ones)
 * get no link; those still go through an Admin in Account Management.
 */
class PasswordResetController extends Controller
{
    /** Step 1 — ask who is resetting. */
    public function request()
    {
        return view('auth.forgot-password');
    }

    /** Step 2 — resolve the account and send the link. */
    public function email(Request $request)
    {
        $request->validate([
            'login' => ['required', 'string'],
        ], [], ['login' => 'username or email']);

        $login = trim($request->input('login'));

        // Same rule as the login box: an "@" can only be an email, because
        // usernames are not allowed to contain one.
        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        $user = User::where($field, $login)->first();

        // Only a live account with an address on file gets a link. Everything
        // else falls through to the same reply below, so this form cannot be
        // used to discover which usernames exist.
        if ($user && $user->is_active && ! empty($user->email)) {
            Password::sendResetLink(['email' => $user->email]);
        }

        return back()->with('success',
            'If that account exists and has an email on file, a reset link is on its way. '
            . 'The link expires in 60 minutes.');
    }

    /** Step 3 — the form behind the emailed link. */
    public function reset(Request $request, string $token)
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email'),
        ]);
    }

    /** Step 4 — store the new password. */
    public function update(Request $request)
    {
        $request->validate([
            'token'    => ['required'],
            'email'    => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                // A deactivated account must not be able to talk its way back in
                // with a link that was issued while it was still live.
                if (! $user->is_active) {
                    return;
                }

                $user->forceFill([
                    'password'       => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')
                ->with('success', 'Your password has been reset. You can sign in with it now.');
        }

        return back()->withErrors(['email' => __($status)])->withInput($request->only('email'));
    }
}
