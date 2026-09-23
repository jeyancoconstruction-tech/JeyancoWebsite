<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * What the system shows between a click and the next page.
 *
 * This is a server-rendered app on a small container a long way from the
 * office: every sidebar click is a full page load, and until now there was
 * nothing at all in between — the old page sat frozen with no sign the click
 * had been heard.
 *
 * Two pieces. A brand line across the top the moment the page starts to
 * leave, which for most navigations is the whole of it; and, only once the
 * wait passes half a second, the card with the mark sweeping round a dial.
 * A splash on every navigation would add a wait the system does not have.
 *
 * The animation itself is the browser's to run and PHPUnit cannot watch it.
 * What it can hold is everything that decides whether the thing appears at
 * the right moment, disappears reliably, and looks like this system.
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

    private function page(): string
    {
        return $this->actingAs($this->admin())
            ->get(route('employees.index'))
            ->assertOk()
            ->getContent();
    }

    private function source(): string
    {
        return File::get(resource_path('views/_loading.blade.php'));
    }

    // ── It is there, and it is not showing ───────────────────────────────

    public function test_every_page_on_the_main_layout_carries_it(): void
    {
        $html = $this->page();

        $this->assertStringContainsString('id="jy-load-bar"', $html, 'the line across the top');
        $this->assertStringContainsString('id="jy-loading"', $html, 'and the card behind it');
        $this->assertStringContainsString('jy-load-dial', $html);
    }

    /**
     * A loader visible on arrival is the worst version of this: every page
     * would open with a flash of "Loading…" over content already rendered.
     */
    public function test_it_starts_hidden_on_a_page_that_has_arrived(): void
    {
        $html = $this->page();

        $this->assertMatchesRegularExpression('#<div id="jy-loading" hidden#', $html,
            'the card is hidden until something is actually being waited for');
        $this->assertStringContainsString('width: 0;', $this->source(),
            'and the bar has no width until it starts');
    }

    // ── When it appears, and when it gives up ────────────────────────────

    /**
     * Hung off the page leaving rather than off a click.
     *
     * Plenty of clicks never navigate, and one that is stopped — the
     * unsaved-changes guard in settings cancels navigation to ask first —
     * would have left the loader sitting on screen over the dialog.
     */
    public function test_it_is_driven_by_the_page_leaving_not_by_a_click(): void
    {
        $src = $this->source();

        $this->assertStringContainsString("addEventListener('beforeunload', start)", $src);
        $this->assertStringNotContainsString("addEventListener('click'", $src,
            'a click is not a navigation');
    }

    /** The card waits: a fast answer should never be interrupted by a splash. */
    public function test_the_card_waits_before_it_shows_itself(): void
    {
        $src = $this->source();

        $this->assertMatchesRegularExpression('#CARD_AFTER\s*=\s*(\d+)#', $src);
        preg_match('#CARD_AFTER\s*=\s*(\d+)#', $src, $m);

        $this->assertGreaterThanOrEqual(300, (int) $m[1], 'too eager and every click flashes a splash');
        $this->assertLessThanOrEqual(900, (int) $m[1], 'too slow and a long wait says nothing at all');
    }

    /**
     * Three ways it could otherwise be left on screen for good: a wait that
     * never ends, a page restored from the browser's cache mid-navigation,
     * and a navigation the user cancelled at the browser's own prompt.
     */
    public function test_it_can_always_take_itself_down(): void
    {
        $src = $this->source();

        $this->assertStringContainsString('GIVE_UP', $src, 'a wait that never ends still ends');
        $this->assertStringContainsString("'pageshow'", $src, 'the back button restores it mid-flight');
        $this->assertStringContainsString("'focus'", $src, 'a cancelled navigation never unloads');
    }

    // ── It looks like this system ────────────────────────────────────────

    /**
     * Every colour comes from a token that actually exists.
     *
     * var(--invented-name, #f4f6fa) is not an error — it silently resolves to
     * the fallback, so a name nobody defined paints one hard-coded colour in
     * both themes. This file had exactly that: --bg-base, which does not
     * exist, and which would have washed the dark theme out with a light
     * overlay.
     */
    public function test_no_colour_falls_through_to_a_hard_coded_fallback(): void
    {
        preg_match_all('/var\((--[a-z0-9-]+)/i', $this->source(), $m);
        $used = array_unique($m[1]);
        $this->assertNotEmpty($used);

        $css = '';
        foreach (File::glob(public_path('*.css')) as $file) {
            $css .= File::get($file);
        }

        $dead = array_values(array_filter($used, fn ($t) => ! str_contains($css, $t . ':')));

        $this->assertSame([], $dead,
            "these CSS variables are never defined, so they paint their fallback:\n" . implode("\n", $dead));
    }

    /** Payroll, attendance and the company's own mark — not a generic spinner. */
    public function test_it_carries_the_systems_own_identity(): void
    {
        $html = $this->page();

        $this->assertStringContainsString('Jeyanco Payroll', $html);
        $this->assertStringContainsString('Payroll · Attendance', $html);
        $this->assertStringContainsString('JeyancoLogo.png', $html);

        // The dial: a ring, a sweep, and the quarters that make it read as a
        // clock rather than as a circle.
        $this->assertSame(4, substr_count($html, 'class="jy-load-tick"'),
            'four quarters — the CSS selectors of the same name do not count');
        $this->assertStringContainsString('jy-load-sweep', $html);
    }

    /** Movement is a nicety; a reader who asked for less gets the rest of it. */
    public function test_the_movement_is_dropped_for_anyone_who_asked_for_less(): void
    {
        $src = $this->source();

        $this->assertStringContainsString('prefers-reduced-motion: reduce', $src);

        $block = substr($src, strpos($src, 'prefers-reduced-motion: reduce'));
        $block = substr($block, 0, strpos($block, '@media (max-width'));

        foreach (['.jy-load-sweep', '.jy-load-track::after', '#jy-load-bar.on'] as $moving) {
            $this->assertStringContainsString($moving, $block,
                "{$moving} should stop moving when asked to");
        }
    }

    /** It has to sit above the toasts and the dialog, which reserve 9500–9700. */
    public function test_it_sits_above_everything_else(): void
    {
        $src = $this->source();

        preg_match('/#jy-loading \{[^}]*z-index: (\d+)/s', $src, $sheet);
        preg_match('/#jy-load-bar \{[^}]*z-index: (\d+)/s', $src, $bar);

        $this->assertGreaterThan(9700, (int) $sheet[1]);
        $this->assertGreaterThan((int) $sheet[1], (int) $bar[1], 'the line stays visible over the card');
    }
}
