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
 * It runs when the site is OPENED — a typed address, a bookmark, a link from
 * outside. A click inside the app, a form submit and the back button all skip
 * it. That is the whole distinction: this is an arrival, not a page load.
 *
 * Signing in does not bring it back. It used to: the overlay went up over the
 * form and the submit waited 220 ms behind it. On 2026-09-23 Michael asked
 * for no loading screen on Sign In — and when the arrival one went with it,
 * he asked for that one back. So: on opening, yes; on Sign In, no.
 *
 * Two things it must never do again. Its CSS once styled .fade, which is
 * Bootstrap's class for every dialog and tab, and gave them all a one-second
 * transition that Bootstrap waits on. And its entrance transitions once stayed
 * on the page for good, so the Sign In button pressed in slow motion.
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

    /** The chrome's CSS, without the comments that talk about it. */
    private function styles(): string
    {
        $src = $this->chrome();
        $css = substr($src, strpos($src, '<style>'), strpos($src, '</style>') - strpos($src, '<style>'));

        return preg_replace('#/\*.*?\*/#s', '', $css);
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

        $check = strpos($head, "d.classList.add('jp-loading')");
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
        $this->assertStringContainsString("nav.type !== 'back_forward'", $head);
        $this->assertStringContainsString("if (!internal && nav.type !== 'back_forward') d.classList.add('jp-loading')", $head,
            'only an arrival is marked; everything else is left alone');
    }

    // ── Not on Sign In ───────────────────────────────────────────────────

    /**
     * Pressing Sign In submits the form, then and there. The button says it
     * is working; nothing covers the page and nothing waits before sending.
     */
    public function test_signing_in_does_not_bring_it_back(): void
    {
        $src = File::get(resource_path('views/login.blade.php'));

        $this->assertStringNotContainsString('JeyancoLoader', $src);
        $this->assertStringNotContainsString('HTMLFormElement.prototype.submit', $src,
            'the browser submits the form itself; the script does not hold it back');
        $this->assertDoesNotMatchRegularExpression('/setTimeout\([^)]*submit/', $src);

        // The feedback that stays is on the button.
        $this->assertStringContainsString("btn.classList.add('loading')", $src);
        $this->assertStringContainsString('Signing in…', $src);

        // And the loader has nothing left to raise itself with.
        $this->assertStringContainsString('window.JeyancoLoader = { hide: hide }', $this->source());
        $this->assertStringNotContainsString('function show(', $this->source());
        $this->assertStringNotContainsString('is-on', $this->chrome());
    }

    // ── The hand-over ────────────────────────────────────────────────────

    /**
     * The page is not simply revealed. Anything marked .rv waits hidden while
     * the loader runs and then rises in on its own delay, and the skyline
     * draws itself like a blueprint — all of it as the loader leaves.
     */
    public function test_the_page_builds_itself_in_behind_the_loader(): void
    {
        $css = $this->styles();

        $this->assertStringContainsString('.jp-loading .rv', $css, 'hidden while the loader is up');
        $this->assertStringContainsString('.jp-entering .rv', $css, 'and risen in as it leaves');
        $this->assertStringContainsString('.jp-entering .draw', $css, 'and so is the line art');
        $this->assertStringContainsString('is-leaving', $css);
        $this->assertStringContainsString("root.classList.add('jp-entering')", $this->source());

        $html = $this->loginPage();
        $this->assertGreaterThanOrEqual(8, preg_match_all('/class="[^"]*\brv\b/', $html),
            'the panel, the pitch, the fields and the foot each arrive in turn');
        $this->assertStringContainsString('pathLength="1"', $html, 'the skyline draws itself in');
    }

    /**
     * Bootstrap animates dialogs and tabs with .fade and waits for the
     * transition to finish before it completes them. Anything else styling
     * .fade decides how long every dialog and tab in the app takes.
     */
    public function test_it_leaves_bootstraps_classes_alone(): void
    {
        $css = $this->styles();

        foreach (['fade', 'show', 'active', 'modal', 'collapse'] as $class) {
            $this->assertDoesNotMatchRegularExpression('/(^|[\s,{}>+~])\.' . $class . '\b/m', $css,
                "the loading screen must not style Bootstrap's .{$class}");
        }

        $html = $this->loginPage();
        $this->assertStringNotContainsString(' fade"', $html, 'the skyline uses jp-fade, not fade');
        $this->assertStringContainsString('jp-fade', $html);
    }

    /** Nothing is hidden unless the arrival check marked the page. */
    public function test_nothing_is_hidden_without_the_arrival_mark(): void
    {
        // Keyframe steps are the overlay's own animations, not the page.
        $rules = preg_replace('/@keyframes[^{]+\{(?:[^{}]*\{[^{}]*\})*[^{}]*\}/', '', $this->styles());

        preg_match_all('/([^{}]+)\{[^{}]*(opacity:\s*0|stroke-dashoffset:\s*1)\b[^{}]*\}/', $rules, $m);

        $this->assertNotEmpty($m[1]);
        foreach ($m[1] as $selector) {
            if (str_contains($selector, '#jp-loader') || str_contains($selector, 'jp-status')) {
                continue;   // the overlay's own parts, not the page's
            }
            $this->assertStringContainsString('.jp-loading', $selector,
                "{$selector} hides the page without the arrival mark");
        }
    }

    /**
     * The entrance's transitions apply while it plays and not after, so the
     * Sign In button presses on its own quick timing once the page is in.
     */
    public function test_the_entrance_lets_go_of_the_page_when_it_is_done(): void
    {
        $this->assertMatchesRegularExpression(
            "/setTimeout\(function \(\) \{ root\.classList\.remove\('jp-entering'\); \}, \d+\)/", $this->source());

        preg_match_all('/([^{}]+)\{[^{}]*transition[^{}]*\}/', $this->styles(), $m);
        foreach ($m[1] as $selector) {
            if (str_contains($selector, 'jp-') && ! str_contains($selector, '.rv') && ! str_contains($selector, '.draw')) {
                continue;   // the overlay's own parts
            }
            $this->assertStringContainsString('.jp-entering', $selector,
                "{$selector} would keep the entrance's transition on the page for good");
        }
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

    // ── How long ─────────────────────────────────────────────────────────

    /** Shown for an instant and snatched away reads worse than not at all. */
    public function test_it_stays_long_enough_to_have_been_seen(): void
    {
        preg_match('/MIN_SHOW\s*=\s*(\d+)/', $this->source(), $m);

        $this->assertNotEmpty($m, 'the floor should be one named number');
        $this->assertGreaterThanOrEqual(1000, (int) $m[1], 'too brief and it reads as a glitch');
        $this->assertLessThanOrEqual(2500, (int) $m[1], 'too long and it is a toll on every arrival');
    }

    /**
     * It lifts when the page has loaded — but the load event also waits for
     * every image and map tile, so once the page has been read it is never
     * held past a ceiling.
     */
    public function test_it_gets_out_of_the_way_once_the_page_has_loaded(): void
    {
        $src = $this->source();

        $this->assertStringContainsString("window.addEventListener('load', hide)", $src);
        $this->assertStringContainsString("document.readyState === 'complete'", $src,
            'a page that already finished loading still has to be uncovered');

        preg_match('/MAX_WAIT\s*=\s*(\d+)/', $src, $max);
        preg_match('/MIN_SHOW\s*=\s*(\d+)/', $src, $min);
        $this->assertNotEmpty($max, 'a slow image must not keep the page covered');
        $this->assertGreaterThanOrEqual((int) $min[1], (int) $max[1]);
        $this->assertLessThanOrEqual(4000, (int) $max[1]);
        $this->assertStringContainsString("addEventListener('DOMContentLoaded', capped)", $src);
    }

    /**
     * Coming back from the browser's cache restores the DOM as it was when
     * the page left — possibly mid-flight, with the overlay up and nothing
     * left running to take it down.
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
        foreach (['#jp-loader', '.rv', '.draw', '.jp-fade'] as $moving) {
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
