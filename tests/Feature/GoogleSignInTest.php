<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as GoogleUser;
use Tests\TestCase;

/**
 * Sign in with Google.
 *
 * Google says who someone is; the admin says whether they get in. Only a
 * Google address that is the email on an account made in Account Management
 * is admitted — signing in never creates an account.
 */
class GoogleSignInTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.google.client_id'     => 'test-client.apps.googleusercontent.com',
            'services.google.client_secret' => 'test-secret',
        ]);
    }

    private function account(array $overrides = []): User
    {
        return User::create($overrides + [
            'name'      => 'Maria Santos',
            'username'  => 'maria.santos',
            'email'     => 'maria.santos@gmail.com',
            'password'  => Hash::make('secret123'),
            'role'      => User::ROLE_ADMIN,
            'is_active' => true,
            'login_method' => User::LOGIN_BOTH,
        ]);
    }

    /** Google hands back this person. */
    private function googleSays(string $email, bool $verified = true): void
    {
        $google = (new GoogleUser)
            ->setRaw(['sub' => '1234567890', 'email' => $email, 'email_verified' => $verified, 'name' => 'Someone'])
            ->map(['id' => '1234567890', 'email' => $email, 'name' => 'Someone']);

        Socialite::shouldReceive('driver->user')->andReturn($google);
    }

    private function comeBackFromGoogle()
    {
        return $this->get(route('login.google.callback', ['state' => 'x', 'code' => 'y']));
    }

    // ── The door ─────────────────────────────────────────────────────────────

    public function test_the_button_shows_only_once_google_is_set_up(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Sign in with Google')->assertSee('id="google"', false);

        config(['services.google.client_secret' => null]);

        $this->get(route('login'))->assertOk()->assertDontSee('Sign in with Google')->assertDontSee('id="google"', false);
        $this->get(route('login.google'))->assertRedirect(route('login'));
    }

    public function test_it_sends_the_visitor_to_google_to_choose_an_account(): void
    {
        $response = $this->get(route('login.google'));

        $to = $response->headers->get('Location');
        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/auth', $to);
        $this->assertStringContainsString('prompt=select_account', $to);
        $this->assertStringContainsString('client_id=test-client.apps.googleusercontent.com', $to);
        $this->assertStringContainsString(urlencode(url('/auth/google/callback')), $to);
    }

    // ── Who gets in ──────────────────────────────────────────────────────────

    public function test_an_address_on_an_account_signs_in(): void
    {
        $maria = $this->account();
        $this->googleSays('maria.santos@gmail.com');

        $this->comeBackFromGoogle()->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($maria);
        $this->assertNotNull($maria->fresh()->last_login_at);
        $this->assertTrue(AuditLog::where('action', 'signed in')->where('description', 'Signed in with Google')->exists());
    }

    public function test_the_address_is_matched_without_case(): void
    {
        $maria = $this->account(['email' => 'Maria.Santos@Gmail.com']);
        $this->googleSays('maria.santos@gmail.com');

        $this->comeBackFromGoogle()->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($maria);
    }

    public function test_an_address_on_no_account_is_turned_away_and_nothing_is_created(): void
    {
        $this->account();
        $this->googleSays('stranger@gmail.com');

        $this->comeBackFromGoogle()
             ->assertRedirect(route('login'))
             ->assertSessionHasErrors(['username' => 'stranger@gmail.com is not registered. Ask an administrator to add it to your account.']);

        $this->assertGuest();
        $this->assertSame(1, User::count());

        $entry = AuditLog::where('action', 'failed')->latest('id')->first();
        $this->assertSame('Google sign-in refused for “stranger@gmail.com” — no account has that email', $entry->description);
    }

    public function test_a_deactivated_account_is_turned_away(): void
    {
        $this->account(['is_active' => false]);
        $this->googleSays('maria.santos@gmail.com');

        $this->comeBackFromGoogle()
             ->assertRedirect(route('login'))
             ->assertSessionHasErrors(['username' => 'This account has been deactivated. Please contact your administrator.']);

        $this->assertGuest();
    }

    public function test_an_unverified_google_address_is_turned_away(): void
    {
        $this->account();
        $this->googleSays('maria.santos@gmail.com', verified: false);

        $this->comeBackFromGoogle()->assertRedirect(route('login'))->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_an_account_with_no_email_cannot_be_reached_through_google(): void
    {
        $this->account(['email' => null]);
        $this->googleSays('maria.santos@gmail.com');

        $this->comeBackFromGoogle()->assertRedirect(route('login'));

        $this->assertGuest();
    }

    // ── When it goes wrong ───────────────────────────────────────────────────

    public function test_cancelling_on_google_comes_back_quietly(): void
    {
        $this->get(route('login.google.callback', ['error' => 'access_denied']))
             ->assertRedirect(route('login'))
             ->assertSessionHasErrors(['username' => 'Google sign-in was cancelled.']);

        $this->assertGuest();
    }

    public function test_a_stale_callback_asks_to_try_again(): void
    {
        Socialite::shouldReceive('driver->user')->andThrow(new InvalidStateException);

        $this->comeBackFromGoogle()
             ->assertRedirect(route('login'))
             ->assertSessionHasErrors(['username' => 'Google sign-in did not go through. Please try again.']);

        $this->assertGuest();
    }

    public function test_the_password_form_still_works_alongside_it(): void
    {
        $maria = $this->account();

        $this->post(route('login.post'), ['username' => 'maria.santos', 'password' => 'secret123'])
             ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($maria);
        $this->assertTrue(AuditLog::where('action', 'signed in')->where('description', 'Signed in')->exists());
    }
}
