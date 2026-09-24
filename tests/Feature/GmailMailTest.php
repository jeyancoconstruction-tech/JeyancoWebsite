<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Mail through the Gmail API (MAIL_MAILER=gmail).
 *
 * Railway blocks outgoing SMTP below its Pro plan, so the reset link goes out
 * over HTTPS instead. These drive the real Forgot password form end to end,
 * with Google faked at the HTTP edge.
 */
class GmailMailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'mail.default'                  => 'gmail',
            'mail.from.address'             => 'jeyancoconstruction@gmail.com',
            'mail.from.name'                => 'Jeyanco Construction',   // as on Railway
            'services.google.client_id'     => 'client.apps.googleusercontent.com',
            'services.google.client_secret' => 'secret',
            'services.gmail.refresh_token'  => 'refresh-token',
        ]);
        Http::preventStrayRequests();
    }

    private function account(): User
    {
        return User::create([
            'first_name' => 'Maria', 'last_name' => 'Santos', 'username' => 'maria.santos',
            'email' => 'maria.santos@gmail.com', 'password' => Hash::make('payroll2026'),
            'role' => User::ROLE_HR, 'is_active' => true, 'login_method' => User::LOGIN_BOTH,
        ]);
    }

    private function googleAccepts(): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'access-1', 'expires_in' => 3599]),
            'gmail.googleapis.com/*'      => Http::response(['id' => 'msg-1', 'labelIds' => ['SENT']]),
        ]);
    }

    /** The message Gmail was asked to send, decoded. */
    private function sentMessage(): string
    {
        $send = collect(Http::recorded())->map(fn ($pair) => $pair[0])
            ->first(fn (Request $r) => str_contains($r->url(), 'messages/send'));

        $this->assertNotNull($send, 'nothing was sent to Gmail');
        $this->assertSame('Bearer access-1', $send->header('Authorization')[0]);

        return base64_decode(strtr($send['raw'], '-_', '+/'));
    }

    public function test_the_reset_link_goes_out_through_gmail(): void
    {
        $this->googleAccepts();
        $this->account();

        $this->post(route('password.email'), ['login' => 'maria.santos'])->assertRedirect();

        $mime = $this->sentMessage();
        $this->assertStringContainsString('To: maria.santos@gmail.com', $mime);
        $this->assertStringContainsString('jeyancoconstruction@gmail.com', $mime);
        $this->assertMatchesRegularExpression('#reset-password/[0-9a-f]{64}#', quoted_printable_decode($mime));
    }

    public function test_the_reset_email_is_the_companys_not_laravels(): void
    {
        $this->googleAccepts();
        config(['app.name' => 'Jeyanco Payroll']);
        $this->account();

        $this->post(route('password.email'), ['login' => 'maria.santos'])->assertRedirect();

        $mime = quoted_printable_decode($this->sentMessage());
        $this->assertStringContainsString('Subject: Reset your Jeyanco Payroll password', $mime);
        $this->assertStringContainsString('Hi Maria,', $mime);
        $this->assertStringContainsString('maria.santos', $mime);
        $this->assertStringContainsString('Choose a new password', $mime);

        // The stock notification — what phishing copies, and what Gmail was
        // putting in Spam — is gone.
        $this->assertStringNotContainsString('Reset Password Notification', $mime);
        $this->assertStringNotContainsString('Laravel', $mime);
    }

    public function test_the_sign_in_is_kept_between_messages(): void
    {
        $this->googleAccepts();

        Mail::raw('one', fn ($m) => $m->to('a@example.com')->subject('1'));
        Mail::raw('two', fn ($m) => $m->to('b@example.com')->subject('2'));

        $tokenCalls = collect(Http::recorded())->filter(fn ($pair) => str_contains($pair[0]->url(), 'oauth2.googleapis.com'))->count();
        $sendCalls  = collect(Http::recorded())->filter(fn ($pair) => str_contains($pair[0]->url(), 'messages/send'))->count();

        $this->assertSame(1, $tokenCalls);
        $this->assertSame(2, $sendCalls);
    }

    public function test_a_revoked_connection_says_what_to_do(): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant', 'error_description' => 'Token has been expired or revoked.'], 400),
        ]);

        try {
            Mail::raw('x', fn ($m) => $m->to('a@example.com')->subject('x'));
            $this->fail('the send should have failed');
        } catch (\Symfony\Component\Mailer\Exception\TransportException $e) {
            $this->assertStringContainsString('Token has been expired or revoked.', $e->getMessage());
            $this->assertStringContainsString('mail:gmail-connect', $e->getMessage());
        }
    }

    public function test_a_failed_send_never_shows_the_person_an_error_page(): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'access-1']),
            'gmail.googleapis.com/*'      => Http::response(['error' => ['code' => 403, 'message' => 'Gmail API has not been used in project 1 before or it is disabled.']], 403),
        ]);
        $this->account();

        // The same reply as any other request, so the form cannot tell anyone
        // which accounts exist — the failure is logged, not shown.
        $this->post(route('password.email'), ['login' => 'maria.santos'])
             ->assertRedirect()
             ->assertSessionHasNoErrors();
    }

    public function test_mail_test_reports_the_gmail_settings(): void
    {
        $this->googleAccepts();

        $this->artisan('mail:test', ['to' => 'jeyancoconstruction@gmail.com'])
             ->expectsOutputToContain('gmail (Gmail API over HTTPS)')
             ->expectsOutputToContain('Sent. Check jeyancoconstruction@gmail.com')
             ->assertExitCode(0);

        config(['services.gmail.refresh_token' => null]);
        app('mail.manager')->purge('gmail');

        $this->artisan('mail:test', ['to' => 'jeyancoconstruction@gmail.com'])
             ->expectsOutputToContain('Gmail sending is not connected')
             ->assertExitCode(1);
    }
}
