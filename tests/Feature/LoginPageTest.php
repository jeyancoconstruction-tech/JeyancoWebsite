<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The sign-in page: Michael's login-loader design.
 *
 * Opened fresh it plays the intro — the site built, the sign lowered, the
 * drawing shrinking into the left panel as the form slides in — with a Skip.
 * A reload, the back button, a link from inside the app, or a refused
 * sign-in coming back open on the finished page instead. Sign in shows a
 * spinner in its button and nothing else. The form, its routes and the
 * Google button are as they were.
 *
 * The animation is the browser's to run; what PHPUnit holds is the wiring.
 */
class LoginPageTest extends TestCase
{
    use RefreshDatabase;

    private function page(): string
    {
        return $this->get(route('login'))->assertOk()->getContent();
    }

    private function headOf(string $html): string
    {
        return substr($html, 0, strpos($html, '</head>'));
    }

    // ── When the intro plays ─────────────────────────────────────────────

    /** Decided in the head, before anything is painted, and only for a fresh "navigate". */
    public function test_the_intro_plays_only_when_the_page_is_opened_fresh(): void
    {
        $head = $this->headOf($this->page());

        $this->assertStringContainsString("performance.getEntriesByType('navigation')[0]", $head);
        $this->assertStringContainsString("nav.type === 'navigate'", $head, 'not on reload, not on back or forward');
        $this->assertStringContainsString('new URL(document.referrer).origin === location.origin', $head, 'not from inside the app');
        $this->assertStringContainsString("prefers-reduced-motion: reduce", $head);
        $this->assertStringContainsString("d.classList.add(play ? 'intro' : 'in')", $head);

        $this->assertLessThan(strpos($head, '<style>'), strpos($head, "d.classList.add(play ? 'intro' : 'in')"),
            'the class is set before the styles are read, so nothing flashes');
    }

    /** A refused sign-in comes back as a "navigate" too — it must not replay the intro. */
    public function test_a_refused_sign_in_does_not_replay_it(): void
    {
        $this->assertStringContainsString('!false', $this->headOf($this->page()), 'a clean visit may play it');

        $html = $this->from(route('login'))->followingRedirects()
            ->post(route('login.post'), ['username' => 'nobody', 'password' => 'wrong-password'])
            ->getContent();

        $this->assertStringContainsString('!true', $this->headOf($html), 'a page with an error on it never plays it');
        $this->assertStringContainsString('class="alert alert-error"', $html);
    }

    public function test_there_is_a_skip_and_no_replay(): void
    {
        $html = $this->page();

        $this->assertStringContainsString('<button class="skip" id="skip" type="button" aria-label="Skip the intro">', $html);
        $this->assertStringContainsString('Skip intro', $html);
        $this->assertStringContainsString("skip.addEventListener('click', function () { show(true); })", $html);
        $this->assertStringContainsString("e.key === 'Escape'", $html, 'Esc skips too');
        $this->assertStringNotContainsString('Replay', $html);
        $this->assertStringNotContainsString('id="replay"', $html);
    }

    /**
     * Skip lands on the finished page at once — the same page a reload
     * shows — rather than starting the hand-over, which ran on for seconds
     * and read as Skip not working.
     */
    public function test_skip_goes_straight_to_the_finished_page(): void
    {
        $src = File::get(resource_path('views/login.blade.php'));

        $fast = substr($src, strpos($src, 'if (fast) {'));
        $fast = substr($fast, 0, strpos($fast, 'return;'));

        $this->assertStringContainsString("d.classList.remove('intro')", $fast, 'it becomes the no-intro page');
        $this->assertStringContainsString("d.classList.add('in', 'landed')", $fast);
        $this->assertStringNotContainsString('flyTitle', $fast, 'no hand-over');
        $this->assertStringContainsString('.snap *, .snap *::before, .snap *::after { transition: none !important; }', $src);
    }

    // ── Signing in ───────────────────────────────────────────────────────

    /**
     * After Sign in there is the button's spinner and nothing more — the
     * dashboard's arrival splash does not play on the page it lands on.
     * Through Google that page's referrer is Google, which read as somebody
     * opening the site; the sign-in's own flash now says otherwise.
     */
    public function test_the_page_after_signing_in_has_no_splash(): void
    {
        \App\Models\User::create([
            'name' => 'Sign In', 'username' => 'sign.in', 'password' => \Illuminate\Support\Facades\Hash::make('secret123'),
            'role' => \App\Models\User::ROLE_ADMIN, 'is_admin' => true, 'is_active' => true,
        ]);

        $this->post(route('login.post'), ['username' => 'sign.in', 'password' => 'secret123'])
             ->assertRedirect(route('dashboard'));

        $first = $this->get(route('dashboard'))->assertOk()->getContent();
        $this->assertStringContainsString('if (true) internal = true;', $first, 'the first page after signing in is not an arrival');

        $later = $this->get(route('dashboard'))->assertOk()->getContent();
        $this->assertStringContainsString('if (false) internal = true;', $later, 'opening the site later still is');
    }

    /** The spinner, and only the spinner — the intro is not played again. */
    public function test_signing_in_shows_only_a_spinner_in_the_button(): void
    {
        $src = File::get(resource_path('views/login.blade.php'));

        $this->assertStringContainsString("btn.classList.add('loading')", $src);
        $this->assertStringContainsString('.signin.loading .label, .signin.loading .arrow { display: none; }', $src);
        $this->assertStringContainsString('.signin.loading .spin { display: block; }', $src);

        // The submit handler does nothing with the intro.
        $handler = substr($src, strpos($src, "form.addEventListener('submit'"));
        $handler = substr($handler, 0, strpos($handler, '});'));
        foreach (['intro', 'show()', 'stage', 'play('] as $word) {
            $this->assertStringNotContainsString($word, $handler, "Sign in must not touch {$word}");
        }
    }

    /** The form posts where it always has, with the fields AuthController reads. */
    public function test_the_form_is_unchanged(): void
    {
        $html = $this->page();

        $this->assertStringContainsString('action="' . route('login.post') . '" method="POST" id="login-form"', $html);
        $this->assertStringContainsString('name="_token"', $html);
        $this->assertStringContainsString('id="username" name="username" type="text"', $html);
        $this->assertStringContainsString('id="password" name="password" type="password"', $html);
        $this->assertStringContainsString('name="remember"', $html);
        $this->assertStringContainsString('href="' . route('password.request') . '"', $html);
        $this->assertStringContainsString('id="caps"', $html, 'the Caps Lock warning stays');
        $this->assertStringContainsString('id="toggle-pw"', $html, 'and so does show / hide');
    }

    /** The Google button, exactly as it was, where Google sign-in is set up. */
    public function test_the_google_button_is_kept(): void
    {
        config(['services.google.client_id' => 'test-client.apps.googleusercontent.com', 'services.google.client_secret' => 'secret']);

        $html = $this->page();

        $this->assertMatchesRegularExpression('#<a class="google" id="google" href="' . preg_quote(route('login.google'), '#') . '"#', $html);
        $this->assertStringContainsString('Sign in with Google', $html);
        $this->assertStringContainsString('<path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85', $html);
    }

    // ── The page ─────────────────────────────────────────────────────────

    /** The real logo, served as a file. */
    public function test_it_uses_the_real_logo(): void
    {
        $html = $this->page();

        $this->assertStringContainsString('images/logo-mark.png', $html);
        $this->assertStringNotContainsString('>JC</text>', $html, 'not a drawn stand-in');
    }

    /** The screen, and no more, on a desktop; one column on a phone. */
    public function test_it_fits_the_screen(): void
    {
        $html = $this->page();

        $this->assertStringContainsString('@media (min-width: 901px) { html, body { height: 100dvh; overflow: hidden; } }', $html);
        $this->assertMatchesRegularExpression('/@media \(max-width: 900px\) \{.*?grid-template-columns: minmax\(0, 1fr\)/s', $html);
    }

    /** The generic arrival splash stays on the other pages; this one has its own. */
    public function test_it_has_its_own_intro_rather_than_the_splash(): void
    {
        $this->assertStringNotContainsString('id="jp-loader"', $this->page());
        $this->assertStringContainsString('id="jp-loader"', $this->get(route('password.request'))->getContent());
    }
}
