<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Throwable;

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

        // Before anything is looked up: can this deployment send mail at all?
        // With MAIL_MAILER unset Laravel falls back to the `log` mailer, which
        // writes the message into storage/logs/laravel.log and reports success
        // — so the form would promise a link that was never posted. The answer
        // does not depend on who asked, so saying it out loud reveals nothing
        // about the account, and it is the one failure a person can act on.
        if (! $this->canSendMail()) {
            Log::warning('Password reset requested, but this deployment cannot send mail.', [
                'mailer' => config('mail.default'),
                'hint'   => 'Set MAIL_MAILER=gmail (Railway blocks SMTP; see php artisan mail:gmail-connect), then run: php artisan mail:test <address>',
            ]);

            return back()->withErrors(['login' =>
                'Email is not set up on this system yet, so no reset link can be sent. '
                . 'Please ask an Admin to reset your password in Account Management.']);
        }

        $login = trim($request->input('login'));

        // Same rule as the login box: an "@" can only be an email, because
        // usernames are not allowed to contain one.
        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        $user = User::where($field, $login)->first();

        // Only a live account with an address on file gets a link — and only
        // one that signs in with a password: a Google-only account has none
        // to reset. Everything else falls through to the same reply below, so
        // this form cannot be used to discover which usernames exist.
        if ($user && $user->is_active && ! empty($user->email) && $user->usesPassword()) {
            try {
                Password::sendResetLink(['email' => $user->email]);
            } catch (Throwable $e) {
                // A refused SMTP handshake throws out of sendResetLink. Left
                // alone it renders a 500 page — and only ever for an address
                // that exists, which turns a broken mailer into exactly the
                // account oracle the branch above is written to avoid. So it
                // is swallowed into the same reply as every other outcome,
                // and the detail goes to the log for whoever runs the system.
                Log::error('Password reset email could not be sent.', [
                    'user_id' => $user->id,
                    'mailer'  => config('mail.default'),
                    'error'   => $e->getMessage(),
                ]);
            }
        }

        return back()->with('success',
            'If that account exists and has an email on file, a reset link is on its way. '
            . 'The link expires in 60 minutes.');
    }

    /** Step 3 — the form behind the emailed link. */
    public function reset(Request $request, string $token)
    {
        return view('auth.reset-password', [
            'token'     => $token,
            'email'     => $request->query('email'),
            // The form states the rule it will be judged by, rather than
            // naming a length the setting may no longer agree with.
            'minLength' => SystemSetting::current()->password_min_length,
        ]);
    }

    /** Step 4 — store the new password. */
    public function update(Request $request)
    {
        $request->validate([
            'token'    => ['required'],
            'email'    => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(SystemSetting::current()->password_min_length)->letters()->numbers()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                // A deactivated account must not be able to talk its way back in
                // with a link that was issued while it was still live.
                if (! $user->is_active) {
                    return;
                }

                // Chosen by the person, from their own inbox: nothing left to change.
                $user->forceFill([
                    'password'             => Hash::make($password),
                    'remember_token'       => Str::random(60),
                    'must_change_password' => false,
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

    /**
     * Whether a message sent from here would reach an inbox.
     *
     * `log` is Laravel's default when MAIL_MAILER is absent, and `array` and
     * `null` are the test mailers; all three accept a message and deliver it
     * nowhere, without raising anything. Every other mailer is taken at its
     * word — whether it can actually connect is only knowable by trying,
     * which is what the send itself finds out.
     */
    private function canSendMail(): bool
    {
        return ! in_array(config('mail.default'), ['log', 'array', 'null'], true);
    }
}
