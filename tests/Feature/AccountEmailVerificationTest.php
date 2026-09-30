<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\AccountCreatedEmail;
use App\Notifications\EmailVerificationCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * A new account's email is proven before the account exists (Michael,
 * 2026-09-30): Send code mails six digits to it, the admin types them in,
 * and only then does Create account go through. The new account is then
 * sent a welcome email at that address. Changing an account's email on Edit
 * asks for the same proof.
 */
class AccountEmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    private const EMAIL = 'maria.santos@gmail.com';

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function admin(): User
    {
        return User::firstOrCreate(['username' => 'admin.otp'], [
            'name' => 'Admin Person', 'password' => Hash::make('secret123'), 'role' => User::ROLE_ADMIN, 'is_active' => true,
        ]);
    }

    private function account(array $overrides = []): array
    {
        return $overrides + [
            'first_name' => 'Maria', 'last_name' => 'Santos', 'username' => 'maria.santos', 'email' => self::EMAIL,
            'login_method' => User::LOGIN_PASSWORD, 'role' => User::ROLE_HR, 'is_active' => 1,
            'password' => 'payroll2026', 'password_confirmation' => 'payroll2026', 'must_change_password' => '1',
        ];
    }

    /** Press Send code and return the code the email carried. */
    private function sendCode(string $email = self::EMAIL): string
    {
        $this->actingAs($this->admin())->postJson(route('accounts.email-code'), ['email' => $email])
             ->assertOk()->assertJson(['success' => true, 'resend_in' => 60]);

        $code = null;
        Notification::assertSentOnDemand(EmailVerificationCode::class, function ($n, $channels, AnonymousNotifiable $to) use ($email, &$code) {
            if (($to->routes['mail'] ?? null) !== $email) {
                return false;
            }
            $code = $n->code;
            return true;
        });

        return $code;
    }

    private function verify(string $code, string $email = self::EMAIL)
    {
        return $this->actingAs($this->admin())->postJson(route('accounts.email-code.verify'), ['email' => $email, 'code' => $code]);
    }

    public function test_the_whole_way_through_ends_with_a_welcome_email(): void
    {
        $code = $this->sendCode();
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);

        $this->verify($code)->assertOk()->assertJson(['verified' => true]);

        $this->post(route('accounts.store'), $this->account())
             ->assertRedirect(route('accounts.index'))
             ->assertSessionHas('success', fn ($m) => str_contains($m, 'A welcome email went to ' . self::EMAIL . '.'));

        $maria = User::where('username', 'maria.santos')->firstOrFail();
        $this->assertSame(self::EMAIL, $maria->email);
        Notification::assertSentTo($maria, AccountCreatedEmail::class);

        // The welcome says who they are and how to sign in, never the password.
        $mail = (new AccountCreatedEmail)->toMail($maria)->render();
        $this->assertStringContainsString('maria.santos', $mail);
        $this->assertStringContainsString('choose a password of your own', $mail);
        $this->assertStringNotContainsString('payroll2026', $mail);
    }

    public function test_an_unproven_email_is_refused(): void
    {
        $this->actingAs($this->admin())->post(route('accounts.store'), $this->account())
             ->assertSessionHasErrors(['email' => 'Verify this email first: press Send code and enter the 6-digit code that arrives.']);

        $this->assertNull(User::where('username', 'maria.santos')->first());
        Notification::assertNothingSent();
    }

    public function test_a_proven_address_does_not_prove_another(): void
    {
        $this->verify($this->sendCode())->assertOk();

        $this->post(route('accounts.store'), $this->account(['email' => 'someone.else@gmail.com']))
             ->assertSessionHasErrors('email');
    }

    public function test_a_wrong_code_is_refused_and_five_lock_it(): void
    {
        $code  = $this->sendCode();
        $wrong = $code === '000000' ? '111111' : '000000';

        for ($i = 1; $i <= 4; $i++) {
            $this->verify($wrong)->assertStatus(422)->assertJson(['verified' => false, 'message' => 'That code is not right. Check the email and try again.']);
        }
        $this->verify($wrong)->assertStatus(422)->assertJson(['message' => 'Too many wrong codes. Send a new one.']);
        $this->verify($code)->assertStatus(422)->assertJson(['message' => 'Too many wrong codes. Send a new one.'], 'even the right one, once locked');
    }

    public function test_a_code_expires_after_ten_minutes(): void
    {
        $code = $this->sendCode();
        Carbon::setTestNow(now()->addMinutes(11));

        $this->verify($code)->assertStatus(422)->assertJson(['message' => 'That code has expired. Send a new one.']);
    }

    public function test_another_code_waits_a_minute(): void
    {
        $this->sendCode();

        $this->postJson(route('accounts.email-code'), ['email' => self::EMAIL])
             ->assertStatus(429)->assertJson(['success' => false]);

        Carbon::setTestNow(now()->addSeconds(61));
        $this->postJson(route('accounts.email-code'), ['email' => self::EMAIL])->assertOk();
    }

    public function test_an_address_already_on_an_account_gets_no_code(): void
    {
        User::create(['name' => 'Taken', 'username' => 'taken', 'email' => self::EMAIL, 'password' => Hash::make('x1234567')]);

        $this->actingAs($this->admin())->postJson(route('accounts.email-code'), ['email' => self::EMAIL])
             ->assertStatus(422)->assertJsonValidationErrors(['email' => 'That email is already used by another account.']);
        Notification::assertNothingSent();
    }

    public function test_only_an_admin_can_send_codes(): void
    {
        $hr = User::create(['name' => 'Hr Person', 'username' => 'hr.otp', 'password' => Hash::make('secret123'), 'role' => User::ROLE_HR, 'is_active' => true]);

        $this->actingAs($hr)->postJson(route('accounts.email-code'), ['email' => self::EMAIL])->assertForbidden();
    }

    public function test_changing_the_email_on_edit_asks_for_the_proof_keeping_it_does_not(): void
    {
        $maria = User::create(['first_name' => 'Maria', 'last_name' => 'Santos', 'username' => 'maria.santos', 'email' => self::EMAIL,
            'password' => Hash::make('payroll2026'), 'login_method' => User::LOGIN_PASSWORD, 'role' => User::ROLE_HR, 'is_active' => true]);
        $edit = fn (string $email) => $this->actingAs($this->admin())->put(route('accounts.update', $maria),
            $this->account(['email' => $email, 'password' => '', 'password_confirmation' => '']));

        $edit(self::EMAIL)->assertSessionHasNoErrors();
        $edit('maria.new@gmail.com')->assertSessionHasErrors('email');
        $this->assertSame(self::EMAIL, $maria->fresh()->email);

        $this->verify($this->sendCode('maria.new@gmail.com'), 'maria.new@gmail.com')->assertOk();
        $edit('maria.new@gmail.com')->assertSessionHasNoErrors();
        $this->assertSame('maria.new@gmail.com', $maria->fresh()->email);
    }

    public function test_the_form_has_the_send_code_button(): void
    {
        $this->actingAs($this->admin())->get(route('accounts.create'))->assertOk()
             ->assertSee('id="caSendCode"', false)
             ->assertSee('id="caCode"', false)
             ->assertSee('autocomplete="one-time-code"', false);
    }
}
