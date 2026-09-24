<?php

namespace App\Mail;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;

/**
 * Sends mail through the Gmail API, over HTTPS.
 *
 * Railway blocks outgoing SMTP on its non-Pro plans — `mail:test` against
 * smtp.gmail.com:587 timed out from the container on 2026-09-24 while every
 * setting was right — so the same Gmail account sends over port 443 instead,
 * where nothing is blocked.
 *
 * It signs in as the account with a refresh token (GMAIL_REFRESH_TOKEN, from
 * `php artisan mail:gmail-connect`) on the OAuth client Sign in with Google
 * already uses, and posts the finished message as Gmail's `raw`. The message
 * goes out from that account, so MAIL_FROM_ADDRESS should be its address —
 * Gmail rewrites any other From to it.
 */
final class GmailApiTransport extends AbstractTransport
{
    private const SEND_URL  = 'https://gmail.googleapis.com/gmail/v1/users/me/messages/send';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    public function __construct(
        private readonly ?string $clientId,
        private readonly ?string $clientSecret,
        private readonly ?string $refreshToken,
    ) {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        // base64url, as the API asks, with the padding left off.
        $raw = rtrim(strtr(base64_encode($message->toString()), '+/', '-_'), '=');

        try {
            $response = Http::withToken($this->accessToken())
                ->timeout(20)
                ->acceptJson()
                ->post(self::SEND_URL, ['raw' => $raw]);
        } catch (ConnectionException $e) {
            throw new TransportException('Gmail could not be reached: ' . $e->getMessage(), 0, $e);
        }

        if ($response->failed()) {
            throw new TransportException('Gmail refused the message: ' . ($response->json('error.message') ?: 'HTTP ' . $response->status()));
        }
    }

    /**
     * A short-lived access token from the refresh token, kept for most of the
     * hour Google gives it. Keyed on the refresh token, so connecting a new
     * account is not answered with the old one's token.
     */
    private function accessToken(): string
    {
        if (blank($this->clientId) || blank($this->clientSecret) || blank($this->refreshToken)) {
            throw new TransportException('Gmail sending is not connected: GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET and GMAIL_REFRESH_TOKEN are all needed. Run php artisan mail:gmail-connect.');
        }

        return Cache::remember('mail.gmail.token.' . md5($this->refreshToken), now()->addMinutes(50), function () {
            try {
                $response = Http::asForm()->timeout(15)->post(self::TOKEN_URL, [
                    'client_id'     => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'refresh_token' => $this->refreshToken,
                    'grant_type'    => 'refresh_token',
                ]);
            } catch (ConnectionException $e) {
                throw new TransportException('Google could not be reached to sign in for sending: ' . $e->getMessage(), 0, $e);
            }

            if ($response->failed() || blank($response->json('access_token'))) {
                // invalid_grant: the token was revoked — the Gmail password
                // changed, or access was removed in the Google account.
                $why = $response->json('error_description') ?: $response->json('error') ?: 'HTTP ' . $response->status();

                throw new TransportException("Google would not sign in for sending ({$why}). Run php artisan mail:gmail-connect again.");
            }

            return (string) $response->json('access_token');
        });
    }

    public function __toString(): string
    {
        return 'gmail+api://default';
    }
}
