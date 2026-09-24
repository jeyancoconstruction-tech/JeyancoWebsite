<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Let the site send mail as a Gmail account, once.
 *
 *     php artisan mail:gmail-connect --save=token.txt
 *
 * Run on a computer with a browser — not on Railway. It opens Google's consent
 * screen for the account that should send (MAIL_FROM_ADDRESS), waits on
 * http://localhost:8000/auth/google/callback for Google to answer — the
 * redirect already listed on the Sign in with Google OAuth client — and
 * trades the answer for a refresh token. That token goes on Railway as
 * GMAIL_REFRESH_TOKEN, with MAIL_MAILER=gmail.
 *
 * Run it again if sending ever stops with "invalid_grant": changing the Gmail
 * password, or removing the app's access in the Google account, revokes it.
 */
class GmailConnect extends Command
{
    protected $signature = 'mail:gmail-connect
        {--port=8000 : The local port the OAuth client lists as http://localhost:PORT/auth/google/callback}
        {--save= : Write the refresh token to this file instead of printing it}
        {--timeout=600 : Seconds to wait for Google}
        {--no-open : Print the link instead of opening the browser}';

    protected $description = 'Connect a Gmail account for sending mail through the Gmail API';

    public function handle(): int
    {
        $clientId     = config('services.google.client_id');
        $clientSecret = config('services.google.client_secret');

        if (blank($clientId) || blank($clientSecret)) {
            $this->error('GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET are needed — the Sign in with Google OAuth client.');

            return self::FAILURE;
        }

        $port     = (int) $this->option('port');
        $redirect = "http://localhost:{$port}/auth/google/callback";
        $state    = Str::random(32);

        // "localhost" may be tried as IPv4 or IPv6 by the browser: listen on both.
        $servers = array_filter([
            @stream_socket_server("tcp://127.0.0.1:{$port}", $errno, $err),
            @stream_socket_server("tcp://[::1]:{$port}", $errno6, $err6),
        ]);

        if (! $servers) {
            $this->error("Port {$port} is busy ({$err}). Stop whatever is using it (php artisan serve?) and try again.");

            return self::FAILURE;
        }

        $url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'client_id'     => $clientId,
            'redirect_uri'  => $redirect,
            'response_type' => 'code',
            // gmail.send to send; openid + email only to say which account it is.
            'scope'         => 'openid email https://www.googleapis.com/auth/gmail.send',
            'access_type'   => 'offline',
            'prompt'        => 'consent',
            'login_hint'    => config('mail.from.address'),
            'state'         => $state,
        ]);

        $this->newLine();
        $this->line('  Sign in as <options=bold>' . (config('mail.from.address') ?: 'the sending account') . '</> and allow "Send email on your behalf".');
        $this->line('  If Google says the app is not verified: Advanced → Go to Jeyanco.');
        $this->newLine();
        $this->line('  ' . $url);
        $this->newLine();

        if (! $this->option('no-open')) {
            $this->openBrowser($url);
        }

        $code = $this->waitForCode($servers, $state, (int) $this->option('timeout'));

        if ($code === null) {
            $this->error('No answer from Google in time, or it was declined.');

            return self::FAILURE;
        }

        $response = Http::asForm()->timeout(20)->post('https://oauth2.googleapis.com/token', [
            'code'          => $code,
            'client_id'     => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri'  => $redirect,
            'grant_type'    => 'authorization_code',
        ]);

        $refresh = $response->json('refresh_token');

        if ($response->failed() || blank($refresh)) {
            $this->error('Google did not hand over a refresh token: ' . ($response->json('error_description') ?: $response->json('error') ?: 'HTTP ' . $response->status()));

            return self::FAILURE;
        }

        // Which account said yes — read from the ID token Google just sent
        // over TLS, so there is nothing to verify it against.
        $claims = json_decode(base64_decode(strtr(explode('.', (string) $response->json('id_token'))[1] ?? '', '-_', '+/')), true) ?: [];
        $email  = $claims['email'] ?? '(unknown)';

        if (! str_contains((string) $response->json('scope'), 'gmail.send')) {
            $this->error("Connected {$email}, but without permission to send — tick \"Send email on your behalf\" and run this again.");

            return self::FAILURE;
        }

        if ($save = $this->option('save')) {
            file_put_contents($save, $refresh);
            $this->info("  Connected {$email}. The refresh token is in {$save} — put it on Railway as GMAIL_REFRESH_TOKEN, then delete the file.");
        } else {
            $this->info("  Connected {$email}. Put this on Railway as GMAIL_REFRESH_TOKEN (it is a secret):");
            $this->line('  ' . $refresh);
        }

        if (config('mail.from.address') && strcasecmp($email, (string) config('mail.from.address')) !== 0) {
            $this->warn("  MAIL_FROM_ADDRESS is " . config('mail.from.address') . "; Gmail will send as {$email}. Make them the same.");
        }

        $this->line('  And set MAIL_MAILER=gmail.');
        $this->newLine();

        return self::SUCCESS;
    }

    /** Wait for Google's redirect and return its code, answering the browser. */
    private function waitForCode(array $servers, string $state, int $timeout): ?string
    {
        $until = time() + $timeout;

        while (time() < $until) {
            $read = $servers;
            $none = null;

            if (! @stream_select($read, $none, $none, 1)) {
                continue;
            }

            foreach ($read as $server) {
                $client = @stream_socket_accept($server, 5);
                if (! $client) {
                    continue;
                }

                $line = (string) fgets($client);
                preg_match('#^GET\s+(\S+)#', $line, $m);
                $path  = parse_url($m[1] ?? '/', PHP_URL_PATH);
                parse_str((string) parse_url($m[1] ?? '/', PHP_URL_QUERY), $query);

                if ($path !== '/auth/google/callback') {
                    fwrite($client, "HTTP/1.1 404 Not Found\r\nContent-Length: 0\r\nConnection: close\r\n\r\n");
                    fclose($client);
                    continue;
                }

                $ok   = ($query['state'] ?? null) === $state && filled($query['code'] ?? null);
                $body = $ok
                    ? '<h2 style="font-family:sans-serif">Connected. You can close this tab.</h2>'
                    : '<h2 style="font-family:sans-serif">Not connected: ' . e($query['error'] ?? 'the answer did not match') . '.</h2>';

                fwrite($client, "HTTP/1.1 200 OK\r\nContent-Type: text/html; charset=utf-8\r\nContent-Length: " . strlen($body) . "\r\nConnection: close\r\n\r\n" . $body);
                fclose($client);

                return $ok ? $query['code'] : null;
            }
        }

        return null;
    }

    private function openBrowser(string $url): void
    {
        // Quoted by hand on Windows: escapeshellarg() there turns every "%"
        // into a space, which would break the percent-encoded link.
        match (PHP_OS_FAMILY) {
            'Windows' => pclose(popen('start "" "' . str_replace('"', '', $url) . '"', 'r')),
            'Darwin'  => exec('open ' . escapeshellarg($url)),
            default   => exec('xdg-open ' . escapeshellarg($url) . ' >/dev/null 2>&1 &'),
        };
    }
}
