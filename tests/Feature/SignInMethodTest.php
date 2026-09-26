<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\ResetPasswordEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Tests\TestCase;

/**
 * How an account signs in — both, Google only, or password only — and the
 * rest of the Create Account page: the name in two parts, Pending until the
 * first Google sign-in and Linked after, and a password the admin set being
 * replaced before anything else opens.
 */
class SignInMethodTest extends TestCase
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

    private function admin(): User
    {
        return User::firstOrCreate(
            ['username' => 'admin.method'],
            ['name' => 'Admin Person', 'password' => Hash::make('secret123'), 'role' => User::ROLE_ADMIN, 'is_active' => true]
        );
    }

    private function create(array $fields): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin())->post(route('accounts.store'), $fields + [
            'first_name' => 'Maria', 'last_name' => 'Santos', 'username' => 'maria.santos',
            'role' => User::ROLE_HR, 'is_active' => 1,
        ]);
    }

    private function googleSays(string $email): void
    {
        $google = (new GoogleUser)
            ->setRaw(['sub' => '42', 'email' => $email, 'email_verified' => true])
            ->map(['id' => '42', 'email' => $email]);

        Socialite::shouldReceive('driver->user')->andReturn($google);
    }

    private function signOut(): void
    {
        $this->app['auth']->logout();
        $this->flushSession();
    }

    // ── The page ─────────────────────────────────────────────────────────────

    public function test_the_page_offers_the_three_ways_in_and_what_the_role_opens(): void
    {
        $this->actingAs($this->admin())->get(route('accounts.create'))
             ->assertOk()
             ->assertSee('How they sign in')
             ->assertSee('data-method="both"', false)
             ->assertSee('data-method="google"', false)
             ->assertSee('data-method="password"', false)
             ->assertSee('name="login_method" id="caMethod" value="both"', false)
             ->assertSee('name="first_name"', false)
             ->assertSee('name="last_name"', false)
             ->assertSee('Ask them to change the password on first sign-in')
             ->assertSee('<b id="modCount">5</b>', false);
    }

    public function test_google_only_is_not_offered_until_google_is_set_up(): void
    {
        config(['services.google.client_secret' => null]);

        $this->actingAs($this->admin())->get(route('accounts.create'))
             ->assertOk()
             ->assertSee('Needs Sign in with Google to be set up on this system first.');

        $this->create(['login_method' => User::LOGIN_GOOGLE, 'email' => 'maria@gmail.com'])
             ->assertSessionHasErrors('login_method');

        $this->assertNull(User::where('username', 'maria.santos')->first());
    }

    // ── The name ─────────────────────────────────────────────────────────────

    public function test_the_name_is_kept_in_two_parts_and_read_as_one(): void
    {
        $this->create(['login_method' => User::LOGIN_PASSWORD, 'password' => 'payroll2026', 'password_confirmation' => 'payroll2026'])
             ->assertRedirect(route('accounts.index'));

        $maria = User::where('username', 'maria.santos')->firstOrFail();
        $this->assertSame('Maria', $maria->first_name);
        $this->assertSame('Santos', $maria->last_name);
        $this->assertSame('Maria Santos', $maria->name);

        $this->create(['first_name' => '', 'last_name' => '', 'username' => 'nobody', 'login_method' => User::LOGIN_PASSWORD, 'password' => 'payroll2026', 'password_confirmation' => 'payroll2026'])
             ->assertSessionHasErrors(['first_name', 'last_name']);
    }

    public function test_a_whole_name_is_split_the_filipino_way(): void
    {
        $this->assertSame(['Juan', 'dela Cruz'], User::splitName('Juan dela Cruz'));
        $this->assertSame(['Maria Clara', 'Santos'], User::splitName('Maria Clara Santos'));
        $this->assertSame(['Jose', 'de los Reyes'], User::splitName('Jose de los Reyes'));
        $this->assertSame(['Admin', null], User::splitName('Admin'));

        // Made anywhere with only a whole name, the parts follow.
        $u = User::create(['name' => 'Ana de la Paz', 'username' => 'ana', 'password' => Hash::make('x1234567')]);
        $this->assertSame('Ana', $u->first_name);
        $this->assertSame('de la Paz', $u->last_name);
    }

    // ── Google only ──────────────────────────────────────────────────────────

    public function test_a_google_only_account_needs_its_email_but_no_password(): void
    {
        $this->create(['login_method' => User::LOGIN_GOOGLE, 'email' => ''])
             ->assertSessionHasErrors('email');

        $this->create(['login_method' => User::LOGIN_GOOGLE, 'email' => 'maria.santos@gmail.com'])
             ->assertRedirect(route('accounts.index'))
             ->assertSessionHas('success', 'Account for Maria Santos created. They sign in with Google as maria.santos@gmail.com.');

        $maria = User::where('username', 'maria.santos')->firstOrFail();
        $this->assertSame(User::LOGIN_GOOGLE, $maria->login_method);
        $this->assertFalse($maria->must_change_password);
        $this->assertSame('pending', $maria->googleStatus());
    }

    public function test_a_google_only_account_is_sent_to_google_from_the_password_form(): void
    {
        $this->create(['login_method' => User::LOGIN_GOOGLE, 'email' => 'maria.santos@gmail.com']);
        $this->signOut();

        $this->post(route('login.post'), ['username' => 'maria.santos', 'password' => 'anything1'])
             ->assertRedirect(route('login'))
             ->assertSessionHasErrors(['username' => 'This account signs in with Google. Use Sign in with Google below.']);

        $this->assertGuest();
    }

    public function test_the_first_google_sign_in_links_the_account(): void
    {
        $this->create(['login_method' => User::LOGIN_GOOGLE, 'email' => 'maria.santos@gmail.com']);
        $this->signOut();

        $this->actingAs($this->admin())->get(route('users-roles.index'))->assertSee('Google pending');
        $this->signOut();

        $this->googleSays('maria.santos@gmail.com');
        $this->get(route('login.google.callback', ['state' => 'x', 'code' => 'y']))->assertRedirect(route('dashboard'));

        $maria = User::where('username', 'maria.santos')->firstOrFail();
        $this->assertAuthenticatedAs($maria);
        $this->assertNotNull($maria->google_linked_at);
        $this->assertSame('linked', $maria->googleStatus());

        $this->signOut();
        $this->actingAs($this->admin())->get(route('users-roles.index', ['account' => $maria->id]))
             ->assertSee('Google linked')
             ->assertSee('Linked');
    }

    public function test_a_new_email_is_pending_again(): void
    {
        $this->create(['login_method' => User::LOGIN_GOOGLE, 'email' => 'maria.santos@gmail.com']);
        $maria = User::where('username', 'maria.santos')->firstOrFail();
        $maria->forceFill(['google_linked_at' => now()])->saveQuietly();

        $this->actingAs($this->admin())->put(route('accounts.update', $maria), [
            'first_name' => 'Maria', 'last_name' => 'Santos', 'username' => 'maria.santos',
            'email' => 'maria.new@gmail.com', 'login_method' => User::LOGIN_GOOGLE,
            'role' => User::ROLE_HR, 'is_active' => 1,
        ])->assertRedirect(route('accounts.index'));

        $this->assertNull($maria->fresh()->google_linked_at);
    }

    public function test_leaving_google_only_takes_a_password(): void
    {
        $this->create(['login_method' => User::LOGIN_GOOGLE, 'email' => 'maria.santos@gmail.com']);
        $maria = User::where('username', 'maria.santos')->firstOrFail();

        $payload = [
            'first_name' => 'Maria', 'last_name' => 'Santos', 'username' => 'maria.santos',
            'email' => 'maria.santos@gmail.com', 'login_method' => User::LOGIN_BOTH,
            'role' => User::ROLE_HR, 'is_active' => 1,
        ];

        $this->actingAs($this->admin())->put(route('accounts.update', $maria), $payload + ['password' => ''])
             ->assertSessionHasErrors('password');

        $this->actingAs($this->admin())->put(route('accounts.update', $maria), $payload + [
            'password' => 'payroll2026', 'password_confirmation' => 'payroll2026', 'must_change_password' => '0',
        ])->assertRedirect(route('accounts.index'));

        $this->signOut();
        $this->post(route('login.post'), ['username' => 'maria.santos', 'password' => 'payroll2026'])
             ->assertRedirect(route('dashboard'));
    }

    // ── Password only ────────────────────────────────────────────────────────

    public function test_a_password_only_account_is_refused_at_the_google_door(): void
    {
        $this->create(['login_method' => User::LOGIN_PASSWORD, 'email' => 'maria.santos@gmail.com', 'password' => 'payroll2026', 'password_confirmation' => 'payroll2026']);
        $this->signOut();

        $this->googleSays('maria.santos@gmail.com');
        $this->get(route('login.google.callback', ['state' => 'x', 'code' => 'y']))
             ->assertRedirect(route('login'))
             ->assertSessionHasErrors(['username' => 'This account signs in with a username and password, not Google.']);

        $this->assertGuest();
        $this->assertSame(
            'Google sign-in refused for “maria.santos@gmail.com” — this account signs in with a password only',
            AuditLog::where('action', 'failed')->latest('id')->value('description')
        );
    }

    public function test_both_needs_the_google_address(): void
    {
        $this->create(['login_method' => User::LOGIN_BOTH, 'email' => '', 'password' => 'payroll2026', 'password_confirmation' => 'payroll2026'])
             ->assertSessionHasErrors(['email' => 'Sign in with Google needs the Google account email.']);
    }

    // ── A password of their own ──────────────────────────────────────────────

    public function test_a_password_the_admin_set_is_replaced_before_anything_opens(): void
    {
        $this->create([
            'login_method' => User::LOGIN_PASSWORD,
            'password' => 'given2026', 'password_confirmation' => 'given2026', 'must_change_password' => '1',
        ]);
        $this->signOut();

        $this->post(route('login.post'), ['username' => 'maria.santos', 'password' => 'given2026'])->assertRedirect(route('dashboard'));

        // Every page waits…
        $this->get(route('sites.index'))->assertRedirect(route('account.password'));
        $this->getJson(route('sites.index'))->assertForbidden();
        $this->get(route('account.password'))->assertOk()->assertSee('Choose your own password');

        // …the one they were given does not count…
        $this->post(route('account.password.update'), ['password' => 'given2026', 'password_confirmation' => 'given2026'])
             ->assertSessionHasErrors(['password' => 'Choose a different password from the one you were given.']);

        // …and their own opens everything.
        $this->post(route('account.password.update'), ['password' => 'mine2026x', 'password_confirmation' => 'mine2026x'])
             ->assertRedirect(route('dashboard'));

        $maria = User::where('username', 'maria.santos')->firstOrFail();
        $this->assertFalse($maria->must_change_password);
        $this->assertTrue(Hash::check('mine2026x', $maria->password));
        $this->get(route('sites.index'))->assertOk();
    }

    public function test_signing_out_is_always_possible_while_the_password_waits(): void
    {
        $this->create([
            'login_method' => User::LOGIN_PASSWORD,
            'password' => 'given2026', 'password_confirmation' => 'given2026', 'must_change_password' => '1',
        ]);
        $this->signOut();

        $this->post(route('login.post'), ['username' => 'maria.santos', 'password' => 'given2026']);
        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_unticking_the_box_lets_them_straight_in(): void
    {
        $this->create([
            'login_method' => User::LOGIN_PASSWORD,
            'password' => 'given2026', 'password_confirmation' => 'given2026', 'must_change_password' => '0',
        ]);
        $this->signOut();

        $this->post(route('login.post'), ['username' => 'maria.santos', 'password' => 'given2026']);
        $this->get(route('sites.index'))->assertOk();
    }

    // ── Forgot password ──────────────────────────────────────────────────────

    public function test_a_google_only_account_is_sent_no_reset_link(): void
    {
        config(['mail.default' => 'smtp']);
        Notification::fake();

        $this->create(['login_method' => User::LOGIN_GOOGLE, 'email' => 'maria.santos@gmail.com']);
        $this->create(['first_name' => 'Pedro', 'last_name' => 'Reyes', 'username' => 'pedro.reyes', 'login_method' => User::LOGIN_BOTH,
                       'email' => 'pedro.reyes@gmail.com', 'password' => 'payroll2026', 'password_confirmation' => 'payroll2026']);
        $this->signOut();

        $this->post(route('password.email'), ['login' => 'maria.santos@gmail.com'])->assertRedirect();
        $this->post(route('password.email'), ['login' => 'pedro.reyes@gmail.com'])->assertRedirect();

        Notification::assertNotSentTo(User::where('username', 'maria.santos')->first(), ResetPasswordEmail::class);
        Notification::assertSentTo(User::where('username', 'pedro.reyes')->first(), ResetPasswordEmail::class);
    }
}
