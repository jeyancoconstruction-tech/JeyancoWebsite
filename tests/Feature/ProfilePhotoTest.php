<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Tests\TestCase;

/**
 * The face on an account: Google's picture for a Google sign-in, one the
 * person chose (which wins), else the first letter — and they can change it.
 */
class ProfilePhotoTest extends TestCase
{
    use RefreshDatabase;

    /** A 1×1 PNG, as the page sends it. */
    private const PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    private function account(array $overrides = []): User
    {
        // The pictures are not mass assignable; they are set as the code sets them.
        $pictures = array_intersect_key($overrides, array_flip(['google_avatar', 'photo']));
        $user = User::create(array_diff_key($overrides, $pictures) + [
            'name' => 'Maria Santos', 'username' => 'maria.santos', 'email' => 'maria.santos@gmail.com',
            'password' => Hash::make('secret123'), 'role' => User::ROLE_ADMIN, 'is_active' => true,
            'login_method' => User::LOGIN_BOTH,
        ]);

        return $pictures ? tap($user->forceFill($pictures))->save() : $user;
    }

    public function test_a_google_sign_in_brings_the_google_picture(): void
    {
        config(['services.google.client_id' => 'x.apps.googleusercontent.com', 'services.google.client_secret' => 's']);
        $user = $this->account();

        $google = (new GoogleUser)
            ->setRaw(['sub' => '1', 'email' => 'maria.santos@gmail.com', 'email_verified' => true])
            ->map(['id' => '1', 'email' => 'maria.santos@gmail.com', 'name' => 'Maria', 'avatar' => 'https://lh3.googleusercontent.com/a/abc=s96-c']);
        Socialite::shouldReceive('driver->user')->andReturn($google);

        $this->get(route('login.google.callback', ['state' => 'x', 'code' => 'y']));

        $this->assertSame('https://lh3.googleusercontent.com/a/abc=s256-c', $user->fresh()->google_avatar);
        $this->assertSame('https://lh3.googleusercontent.com/a/abc=s256-c', $user->fresh()->avatarUrl());
    }

    public function test_a_picture_from_elsewhere_is_not_kept(): void
    {
        config(['services.google.client_id' => 'x.apps.googleusercontent.com', 'services.google.client_secret' => 's']);
        $user = $this->account();

        $google = (new GoogleUser)
            ->setRaw(['sub' => '1', 'email' => 'maria.santos@gmail.com', 'email_verified' => true])
            ->map(['id' => '1', 'email' => 'maria.santos@gmail.com', 'avatar' => 'http://example.com/me.png']);
        Socialite::shouldReceive('driver->user')->andReturn($google);

        $this->get(route('login.google.callback', ['state' => 'x', 'code' => 'y']));

        $this->assertNull($user->fresh()->google_avatar);
    }

    public function test_an_account_without_a_picture_shows_its_letter(): void
    {
        $user = $this->account(['login_method' => User::LOGIN_PASSWORD]);

        $html = $this->actingAs($user)->get(route('dashboard'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#data-me-avatar>\s*M\s*</div>#', $html, 'the top bar shows the letter');
        $this->assertSame('M', $user->initial());
    }

    public function test_the_person_can_choose_a_photo_and_it_wins_over_google(): void
    {
        $user = $this->account(['google_avatar' => 'https://lh3.googleusercontent.com/a/abc=s256-c']);

        $this->actingAs($user)->postJson(route('profile.photo.store'), ['photo' => self::PNG])
            ->assertOk()->assertJsonPath('success', true);

        $this->assertSame(self::PNG, $user->fresh()->avatarUrl());
        $html = $this->actingAs($user->fresh())->get(route('dashboard'))->getContent();
        $this->assertMatchesRegularExpression('#data-me-avatar>\s*<img class="u-av-img" src="data:image/png#', $html, 'the top bar shows the photo');
    }

    public function test_removing_it_brings_google_back_or_the_letter(): void
    {
        $google = $this->account(['google_avatar' => 'https://lh3.googleusercontent.com/a/abc=s256-c', 'photo' => self::PNG]);
        $this->actingAs($google)->deleteJson(route('profile.photo.destroy'))
            ->assertOk()->assertJsonPath('avatar', 'https://lh3.googleusercontent.com/a/abc=s256-c');

        $plain = $this->account(['username' => 'staff.one', 'email' => 'staff@example.com', 'photo' => self::PNG]);
        $this->actingAs($plain)->deleteJson(route('profile.photo.destroy'))
            ->assertOk()->assertJsonPath('avatar', null);
        $this->assertNull($plain->fresh()->photo);
    }

    public function test_only_a_real_small_picture_is_accepted(): void
    {
        $user = $this->account();

        $this->actingAs($user)->postJson(route('profile.photo.store'), ['photo' => 'data:text/html;base64,PHNjcmlwdD4='])->assertStatus(422);
        $this->actingAs($user)->postJson(route('profile.photo.store'), ['photo' => 'data:image/png;base64,bm90IGFuIGltYWdl'])->assertStatus(422);
        $this->assertNull($user->fresh()->photo);
    }

    public function test_the_photo_is_never_serialised(): void
    {
        $user = $this->account(['photo' => self::PNG]);

        $this->assertArrayNotHasKey('photo', $user->toArray());
    }
}
