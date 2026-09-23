<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The screen the site opens on, and the page it hands over to.
 *
 * It runs when jeyancopayroll.me is OPENED — a typed address, a bookmark, a
 * link from outside. A click inside the app, a form submit and the back
 * button all skip it. That is the whole distinction: this is an arrival, not
 * a page load.
 *
 * The hand-over is the part worth holding still. The loading screen does not
 * simply vanish: the dial pulls forward and blurs out while the page builds
 * itself in underneath, piece by piece, off the jp-ready class the loader
 * sets. Signing in runs it in reverse — the overlay comes back up so the wait
 * for the dashboard is the screen you arrived on rather than a form frozen
 * mid-press.
 *
 * The animation is the browser's to run and PHPUnit cannot watch it. What it
 * can hold is the wiring: the marks, the classes, and the pages they are on.
 */
class LoadingScreenTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name'      => 'Admin',
            'username'  => 'admin.loading',
            'password'  => Hash::make('secret123'),
            'is_admin'  => true,
            'is_active' => true,
        ]);
    }

    private function appPage(): string
    {
        return $this->actingAs($this->admin())
            ->get(route('employees.index'))
            ->assertOk()
            ->getContent();
    }

    private function loginPage(): string
    {
        return $this->get(route('login'))->assertOk()->getContent();
    }

    private function headOf(string $html): string
    {
        return substr($html, 0, strpos($html, '</head>'));
    }

    private function source(): string
    {
        return File::get(resource_path('views/_loading.blade.php'));
    }

    private function chrome(): string
    {
        return File::get(resource_path('views/_loading_head.blade.php'));
    }

    // ── It is on the pages people arrive on ──────────────────────────────

    public function test_it_is_on_both_layouts(): void
    {
        // The sign-in page first: /login is guest-only, and acting as the
        // admin for the dashboard would redirect it away.
        $pages = ['the sign-in page' => $this->loginPage()];
        $pages['the dashboard layout'] = $this->appPage();

        foreach ($pages as $where => $html) {
            $this->assertStringContainsString('id="jp-loader"', $html, $where);
            $this->assertStringContainsString('JEYANCO <span>PAYROLL</span>', $html, $where);
            $this->assertStringContainsString('jp-dial', $html, $where);
        }
    }

    /**
     * The mark comes from /images, not from copies of the same file inlined
     * as base64 — which is how the design arrived, at about 25KB a copy,
     * three times over on one page.
     */
    public function test_the_mark_is_served_not_inlined(): void
    {
        $html = $this->loginPage();

        $this->assertStringContainsString('images/logo-mark.png', $html);
        $this->assertStringNotContainsString('data:image/png;base64', $html,
            'the logo is a file, cached once, not bytes in the HTML');
        $this->assertFileExists(public_path('images/logo-mark.png'));
    }

    // ── Opened, or navigated to? ─────────────────────────────────────────

    /**
     * The check has to stamp <html> BEFORE the styles are read. Later, and
     * the overlay paints for a frame on every internal click — the flash it
     * exists to avoid.
     */
    public function test_the_entry_check_runs_before_the_styles_it_controls(): void
    {
        $head = $this->headOf($this->loginPage());

        $check = strpos($head, 'classList.add((internal');
        $style = strpos($head, '.jp-loading #jp-loader');

        $this->assertNotFalse($check, 'the entry check belongs in the head');
        $this->assertNotFalse($style, 'and so does the rule it sets up');
        $this->assertLessThan($style, $check, 'the class has to be set before the rule is read');
    }

    public function test_an_internal_referrer_and_the_back_button_skip_it(): void
    {
        $head = $this->headOf($this->loginPage());

        $this->assertStringContainsString('document.referrer', $head);
        $this->assertStringContainsString('location.origin', $head);
        $this->assertStringContainsString("nav.type === 'back_forward'", $head);

        // Two states, never both: arriving, or already here.
        $this->assertStringContainsString("'jp-ready' : 'jp-loading'", $head);
    }

    // ── The hand-over ────────────────────────────────────────────────────

    /**
     * The page is not simply revealed. Anything marked .rv waits hidden while
     * the loader runs and then rises in on its own delay, and the skyline
     * draws itself like a blueprint — all of it off the jp-ready class the
     * loader sets as it leaves.
     */
    public function test_the_page_builds_itself_in_behind_the_loader(): void
    {
        $chrome = $this->chrome();

        $this->assertStringContainsString('.jp-ready .rv', $chrome, 'the reveal is tied to the loader leaving');
        $this->assertStringContainsString('.jp-ready .draw', $chrome, 'and so is the line art');
        $this->assertStringContainsString('is-leaving', $chrome);

        $html = $this->loginPage();
        $this->assertGreaterThanOrEqual(8, preg_match_all('/class="[^"]*\brv\b/', $html),
            'the panel, the pitch, the fields and the foot each arrive in turn');
        $this->assertStringContainsString('pathLength="1"', $html, 'the skyline draws itself in');
    }

    public function test_the_loader_steps_through_what_it_is_doing(): void
    {
        $html = $this->loginPage();

        foreach ([
            'Connecting to server',
            'Syncing attendance logs',
            'Loading payroll modules',
            'Securing your session',
        ] as $step) {
            $this->assertStringContainsString($step, $html);
        }

        // A real percentage, not a bar that travels and says nothing.
        $this->assertMatchesRegularExpression('/pct: \d+/', $html);
        $this->assertStringContainsString('id="jp-bar"', $html);
    }

    /** Shown for an instant and snatched away reads worse than not at all. */
    public function test_it_stays_long_enough_to_have_been_seen(): void
    {
        preg_match('/MIN_SHOW\s*=\s*(\d+)/', $this->source(), $m);

        $this->assertNotEmpty($m, 'the floor should be one named number');
        $this->assertGreaterThanOrEqual(1000, (int) $m[1], 'too brief and it reads as a glitch');
        $this->assertLessThanOrEqual(2500, (int) $m[1], 'too long and it is a toll on every arrival');
    }

    // ── Signing in runs it in reverse ────────────────────────────────────

    /**
     * The wait for the dashboard is the same screen the site opened on. The
     * overlay goes up, then the form is submitted for real — the beat between
     * is only long enough for the fade to start.
     */
    public function test_signing_in_brings_the_loader_back(): void
    {
        $src = File::get(resource_path('views/login.blade.php'));

        $this->assertStringContainsString('JeyancoLoader.show', $src);
        $this->assertStringContainsString('HTMLFormElement.prototype.submit.call(form)', $src,
            'the real submit still happens — the loader does not replace it');

        preg_match('/submit\.call\(form\); \}, (\d+)\)/', $src, $m);
        $this->assertNotEmpty($m, 'the beat before submitting should be explicit');
        $this->assertLessThanOrEqual(400, (int) $m[1],
            'a long pause here is dead time added to every sign-in');
    }

    /** window.JeyancoLoader is the contract the form leans on. */
    public function test_the_loader_offers_show_and_hide(): void
    {
        $this->assertStringContainsString('window.JeyancoLoader = { show: show, hide: hide }', $this->source());
    }

    // ── It can always take itself down ───────────────────────────────────

    public function test_it_gets_out_of_the_way_once_the_page_has_loaded(): void
    {
        $src = $this->source();

        $this->assertStringContainsString("window.addEventListener('load', hide)", $src);
        $this->assertStringContainsString("document.readyState === 'complete'", $src,
            'a page that already finished loading still has to be uncovered');
    }

    /**
     * Coming back from the browser's cache restores the DOM as it was when
     * the page left — which, after a sign-in, is mid-flight with the overlay
     * up and nothing left running to take it down.
     */
    public function test_the_back_button_does_not_strand_the_overlay(): void
    {
        $this->assertStringContainsString("'pageshow'", $this->source());
        $this->assertStringContainsString('e.persisted', $this->source());
    }

    // ── The usual courtesies ─────────────────────────────────────────────

    public function test_the_movement_is_dropped_for_anyone_who_asked_for_less(): void
    {
        $chrome = $this->chrome();

        $this->assertStringContainsString('prefers-reduced-motion: reduce', $chrome);

        $block = substr($chrome, strpos($chrome, 'prefers-reduced-motion: reduce'));
        foreach (['#jp-loader', '.rv', '.draw', '.fade'] as $moving) {
            $this->assertStringContainsString($moving, $block, "{$moving} should hold still when asked");
        }
    }

    /**
     * Every colour the overlay uses is its own. The partial is included on
     * the dashboard layout, which loads the app's design tokens, so a name
     * shared between the two would have one of them repainting the other.
     */
    public function test_the_overlay_keeps_its_palette_to_itself(): void
    {
        preg_match_all('/var\((--[a-z0-9-]+)/i', $this->chrome(), $m);
        $used = array_unique($m[1]);

        $this->assertNotEmpty($used);

        foreach ($used as $token) {
            $this->assertStringStartsWith('--jp-', $token,
                "{$token} is not namespaced, so it can collide with the app's tokens");
        }
    }

    public function test_it_sits_above_the_toasts_and_the_dialog(): void
    {
        preg_match('/#jp-loader \{.*?z-index: (\d+)/s', $this->chrome(), $m);

        $this->assertGreaterThan(9700, (int) $m[1], 'the notifier reserves up to 9700');
    }

    public function test_a_reader_is_told_what_is_happening(): void
    {
        $html = $this->loginPage();

        $this->assertStringContainsString('role="status"', $html);
        $this->assertStringContainsString('aria-live="polite"', $html);
        $this->assertStringContainsString('aria-label="Loading Jeyanco Payroll"', $html);
    }
}
