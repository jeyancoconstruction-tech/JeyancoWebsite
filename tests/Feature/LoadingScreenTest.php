<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The overlay the site opens on.
 *
 * It runs when jeyancopayroll.me is OPENED — a typed address, a bookmark, a
 * link from outside. A click inside the app, a form submit and the back
 * button all skip it. That is the whole distinction: this is an arrival, not
 * a page load, and an earlier attempt that ran on every navigation was the
 * wrong thing and was taken out.
 *
 * The animation is the browser's to run and PHPUnit cannot watch it. What it
 * can hold is what decides whether the thing appears at the right moment,
 * gets out of the way afterwards, and is on the pages people actually arrive
 * on.
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

    private function headOf(string $html): string
    {
        return substr($html, 0, strpos($html, '</head>'));
    }

    private function source(): string
    {
        return File::get(resource_path('views/_loading.blade.php'));
    }

    // ── It is on the pages people arrive on ──────────────────────────────

    public function test_it_is_on_the_dashboard_layout(): void
    {
        $html = $this->appPage();

        $this->assertStringContainsString('id="jp-loader"', $html);
        $this->assertStringContainsString('JEYANCO <span>PAYROLL</span>', $html);
    }

    /**
     * And on the sign-in page, which is where most arrivals actually land:
     * the address goes to /login when nobody is signed in, so a loader only
     * on the dashboard layout would never be seen by a visitor opening the
     * site cold.
     */
    public function test_it_is_on_the_sign_in_page_too(): void
    {
        $html = $this->get(route('login'))->assertOk()->getContent();

        $this->assertStringContainsString('id="jp-loader"', $html);
        $this->assertStringContainsString('jp-skip', $this->headOf($html));
    }

    // ── Opened, or navigated to? ─────────────────────────────────────────

    /**
     * The check has to stamp <html> BEFORE the styles are read. Later, and
     * the overlay paints for a frame on every internal click — the flash it
     * exists to avoid.
     */
    public function test_the_entry_check_runs_before_the_styles_it_controls(): void
    {
        $head = $this->headOf($this->appPage());

        $check = strpos($head, "classList.add('jp-skip')");
        $style = strpos($head, '.jp-skip #jp-loader');

        $this->assertNotFalse($check, 'the entry check belongs in the head');
        $this->assertNotFalse($style, 'and so does the rule it sets up');
        $this->assertLessThan($style, $check, 'the class has to be set before the rule is read');
    }

    /** A referrer from this same site is a click, not an arrival. */
    public function test_an_internal_referrer_and_the_back_button_skip_it(): void
    {
        $head = $this->headOf($this->appPage());

        $this->assertStringContainsString('document.referrer', $head);
        $this->assertStringContainsString('location.origin', $head);
        $this->assertStringContainsString("nav.type === 'back_forward'", $head);
        $this->assertStringContainsString('.jp-skip #jp-loader { display: none; }', $head);
    }

    /**
     * The earlier version put a bar and a card up on EVERY navigation. That
     * is not what was wanted and it is gone; nothing here should be listening
     * for the page leaving or for a click.
     */
    public function test_it_does_not_run_on_every_navigation(): void
    {
        $src = $this->source() . File::get(resource_path('views/_loading_head.blade.php'));

        $this->assertStringNotContainsString('beforeunload', $src,
            'an arrival is not the page leaving');
        $this->assertStringNotContainsString("addEventListener('click'", $src);
        $this->assertStringNotContainsString('jy-load-bar', $src,
            'the per-navigation loader was removed, not left alongside this one');
    }

    // ── It gets out of the way ───────────────────────────────────────────

    public function test_it_hides_itself_once_the_page_has_loaded(): void
    {
        $src = $this->source();

        $this->assertStringContainsString("window.addEventListener('load', api.hide)", $src);
        $this->assertStringContainsString("document.readyState === 'complete'", $src,
            'a page that already finished loading still has to be uncovered');
    }

    /** Shown for an instant and snatched away reads worse than not at all. */
    public function test_it_stays_long_enough_to_have_been_seen(): void
    {
        $this->assertMatchesRegularExpression('/700 - \(Date\.now\(\) - shownAt\)/', $this->source(),
            'a floor on how briefly it can appear');
    }

    // ── What it says ─────────────────────────────────────────────────────

    /**
     * One line, and a true one.
     *
     * It used to cycle four — syncing attendance logs, computing hours
     * worked, loading employee records, preparing payroll summary — and not
     * one of them described what was happening: nothing is synced or
     * computed while a page arrives. They were also unreadable, since the
     * overlay lifts as soon as the page has loaded and nobody got past the
     * first.
     */
    public function test_the_status_line_says_only_what_is_true(): void
    {
        $html = $this->appPage();

        $this->assertStringContainsString('Getting things ready', $html);

        foreach ([
            'Syncing attendance logs',
            'Computing hours worked',
            'Loading employee records',
            'Preparing payroll summary',
        ] as $claim) {
            $this->assertStringNotContainsString($claim, $html,
                'the screen should not claim work it is not doing');
        }

        $this->assertStringNotContainsString('setInterval', $html,
            'there is nothing left to cycle between');
    }

    /**
     * The same words in the markup and in the script. The markup is what is
     * on screen for the frame before the script runs, so a mismatch shows as
     * the line changing under the reader the instant the page starts.
     */
    public function test_the_line_on_screen_matches_the_one_the_script_sets(): void
    {
        $src = File::get(resource_path('views/_loading.blade.php'));

        preg_match('/id="jp-status-text">\{\{ __\(\x27([^\x27]+)\x27\) \}\}/', $src, $markup);
        preg_match('/var DEFAULT = \{ icon: \x27\w+\x27, text: @json\(__\(\x27([^\x27]+)\x27\)\) \}/', $src, $script);

        $this->assertNotEmpty($markup, 'the markup should carry the line');
        $this->assertNotEmpty($script, 'and so should the script');
        $this->assertSame($markup[1], $script[1], 'they have to be the same words');
    }

    /** The four icons stay: setMessage() can pin a real one to a slow job. */
    public function test_the_icons_are_still_available_to_set_message(): void
    {
        $html = $this->appPage();

        foreach (['calendar', 'clock', 'users', 'peso'] as $icon) {
            $this->assertStringContainsString($icon . ':', $html);
        }

        $this->assertStringContainsString('setMessage', $html);
    }

    /** Twelve hours on the dial, and the sweep that lights them. */
    public function test_the_dial_is_a_clock(): void
    {
        $html = $this->appPage();

        $this->assertSame(12, substr_count($html, 'class="jp-tick'), 'twelve hours');
        $this->assertStringContainsString('jp-orbit-tail', $html, 'and a hand sweeping them');
    }

    /** The badge should read as Jeyanco even if the file is not there. */
    public function test_the_mark_falls_back_to_the_monogram(): void
    {
        $html = $this->appPage();

        $this->assertStringContainsString('images/logo-mark.png', $html);
        $this->assertStringContainsString("classList.add('no-logo')", $html);
        $this->assertStringContainsString('jp-monogram', $html);

        $this->assertFileExists(public_path('images/logo-mark.png'),
            'the file the loader reaches for');
    }

    // ── The usual courtesies ─────────────────────────────────────────────

    public function test_the_movement_is_dropped_for_anyone_who_asked_for_less(): void
    {
        $css = File::get(resource_path('views/_loading_head.blade.php'));

        $this->assertStringContainsString('prefers-reduced-motion: reduce', $css);

        $block = substr($css, strpos($css, 'prefers-reduced-motion: reduce'));
        foreach (['.jp-orbit', '.jp-tick', '.jp-halo', '.jp-stage'] as $moving) {
            $this->assertStringContainsString($moving, $block, "{$moving} should hold still when asked");
        }
    }

    /** A screen that covers everything has to be above everything. */
    public function test_it_sits_above_the_toasts_and_the_dialog(): void
    {
        $css = File::get(resource_path('views/_loading_head.blade.php'));

        preg_match('/#jp-loader \{[^}]*z-index: (\d+)/s', $css, $m);

        $this->assertGreaterThan(9700, (int) $m[1],
            'the notifier reserves up to 9700');
    }

    public function test_a_reader_is_told_what_is_happening(): void
    {
        $html = $this->appPage();

        $this->assertStringContainsString('role="status"', $html);
        $this->assertStringContainsString('aria-live="polite"', $html);
        $this->assertStringContainsString('aria-label="Loading Jeyanco Payroll"', $html);
    }
}
