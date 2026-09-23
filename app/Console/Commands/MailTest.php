<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Prove — or disprove — that this deployment can actually send mail.
 *
 * The password reset link is the only email this system sends, and when it
 * fails it fails silently: Laravel's default mailer is `log`, which writes the
 * whole message into storage/logs/laravel.log and reports success. The reset
 * form then tells the person a link is on its way, and nothing ever arrives.
 * That is indistinguishable, from the outside, from a link that was delivered
 * to spam — so the only way to tell them apart is to ask the mailer directly.
 *
 *     php artisan mail:test someone@example.com
 *
 * It prints the settings in force, sends one message, and says which of the
 * two happened. Run it after changing MAIL_* on Railway; there is no need to
 * go through the reset form to find out whether the change took.
 */
class MailTest extends Command
{
    protected $signature = 'mail:test {to : The address to send the test message to}';

    protected $description = 'Send a test email and report whether mail is actually configured';

    public function handle(): int
    {
        $to      = $this->argument('to');
        $mailer  = config('mail.default');
        $from    = config('mail.from.address');

        $this->newLine();
        $this->line('  <options=bold>Mail settings in force</>');
        $this->table(['Setting', 'Value'], [
            ['MAIL_MAILER',       $mailer],
            ['MAIL_HOST',         config('mail.mailers.smtp.host')   ?: '(not set)'],
            ['MAIL_PORT',         config('mail.mailers.smtp.port')   ?: '(not set)'],
            ['MAIL_SCHEME',       config('mail.mailers.smtp.scheme') ?: '(not set)'],
            ['MAIL_USERNAME',     config('mail.mailers.smtp.username') ?: '(not set)'],
            ['MAIL_PASSWORD',     config('mail.mailers.smtp.password') ? '(set, hidden)' : '(not set)'],
            ['MAIL_FROM_ADDRESS', $from ?: '(not set)'],
            ['APP_URL',           config('app.url')],
        ]);

        // The single most common reason no mail arrives, and the one that
        // leaves no error behind. Stop here rather than "succeeding" into a
        // log file and letting the person think delivery works.
        if ($mailer === 'log') {
            $this->newLine();
            $this->error('  MAIL_MAILER is "log" — nothing is being emailed.');
            $this->line('  Messages are written to storage/logs/laravel.log instead of being sent.');
            $this->line('  Set MAIL_MAILER=smtp and the MAIL_* settings above, then run this again.');
            $this->newLine();

            return self::FAILURE;
        }

        if ($mailer === 'array' || $mailer === 'null') {
            $this->newLine();
            $this->error("  MAIL_MAILER is \"{$mailer}\" — mail is discarded, not sent.");
            $this->newLine();

            return self::FAILURE;
        }

        $this->line("  Sending to <options=bold>{$to}</> …");

        try {
            Mail::raw(
                "This is a test message from the Jeyanco Construction system.\n\n"
                . "If you are reading this in your inbox, email delivery works and the\n"
                . "\"Forgot password\" link will reach staff.\n\n"
                . 'Sent: ' . now()->toDayDateTimeString(),
                fn ($message) => $message->to($to)->subject('Jeyanco — mail test')
            );
        } catch (Throwable $e) {
            $this->newLine();
            $this->error('  Send failed: ' . $e->getMessage());
            $this->newLine();
            $this->line('  Common causes:');
            $this->line('   • Gmail needs an App Password (16 characters), not the account password.');
            $this->line('   • 2-Step Verification must be on before App Passwords can be created.');
            $this->line('   • Port 587 with MAIL_SCHEME=tls, or port 465 with MAIL_SCHEME=smtps.');
            $this->newLine();

            return self::FAILURE;
        }

        $this->newLine();
        $this->info("  Sent. Check {$to} — including the spam folder.");
        $this->line('  If it does not arrive, the mail provider accepted it and then dropped it;');
        $this->line('  check the sending account for a security alert.');
        $this->newLine();

        return self::SUCCESS;
    }
}
