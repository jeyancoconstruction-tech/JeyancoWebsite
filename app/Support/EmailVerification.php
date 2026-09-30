<?php

namespace App\Support;

use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Hash;

/**
 * The one-time codes that prove an email address before an account is
 * created with it (Michael, 2026-09-30). Kept in the admin's own session: a
 * code sent from one admin's screen cannot be used from another's, and
 * nothing is left in the database.
 *
 * A code lives MINUTES, can be tried TRIES times, and a new one can be sent
 * every RESEND_AFTER seconds. An address that passed stays good for
 * VERIFIED_FOR minutes, long enough to finish the form.
 */
class EmailVerification
{
    public const MINUTES      = 10;
    public const RESEND_AFTER = 60;
    public const TRIES        = 5;
    public const VERIFIED_FOR = 30;

    private const CODES    = 'account_email_codes';
    private const VERIFIED = 'account_verified_emails';

    public static function key(string $email): string
    {
        return sha1(mb_strtolower(trim($email)));
    }

    /**
     * A fresh code for the address, or how many seconds to wait before one
     * may be sent again.
     *
     * @return array{0: ?string, 1: int}  [code, seconds to wait]
     */
    public static function issue(Session $session, string $email): array
    {
        $key     = self::key($email);
        $pending = $session->get(self::CODES . '.' . $key);
        if ($pending && ($wait = $pending['sent_at'] + self::RESEND_AFTER - now()->getTimestamp()) > 0) {
            return [null, $wait];
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $session->put(self::CODES . '.' . $key, ['hash' => Hash::make($code), 'sent_at' => now()->getTimestamp(), 'tries' => 0]);

        return [$code, 0];
    }

    /** Take back a code that never reached the inbox, so a resend is not held up. */
    public static function cancel(Session $session, string $email): void
    {
        $session->forget(self::CODES . '.' . self::key($email));
    }

    /** 'ok', 'wrong', 'expired', 'locked' or 'none'. */
    public static function check(Session $session, string $email, string $code): string
    {
        $key     = self::key($email);
        $path    = self::CODES . '.' . $key;
        $pending = $session->get($path);

        if (! $pending) {
            return 'none';
        }
        if (now()->getTimestamp() - $pending['sent_at'] > self::MINUTES * 60) {
            $session->forget($path);
            return 'expired';
        }
        if ($pending['tries'] >= self::TRIES) {
            return 'locked';
        }
        if (! Hash::check(preg_replace('/\D/', '', $code), $pending['hash'])) {
            $pending['tries']++;
            $session->put($path, $pending);
            return $pending['tries'] >= self::TRIES ? 'locked' : 'wrong';
        }

        $session->forget($path);
        self::markVerified($session, $email);

        return 'ok';
    }

    public static function markVerified(Session $session, string $email): void
    {
        $session->put(self::VERIFIED . '.' . self::key($email), now()->getTimestamp());
    }

    public static function verified(Session $session, string $email): bool
    {
        $at = $session->get(self::VERIFIED . '.' . self::key($email));

        return $at !== null && now()->getTimestamp() - $at <= self::VERIFIED_FOR * 60;
    }

    /** Used up: the account now has the address. */
    public static function forget(Session $session, string $email): void
    {
        $session->forget(self::VERIFIED . '.' . self::key($email));
    }
}
