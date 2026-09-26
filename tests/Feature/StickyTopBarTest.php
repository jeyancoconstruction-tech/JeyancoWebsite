<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The top bar stays at the top of the screen on every page (Michael,
 * 2026-09-26).
 *
 * It was always `position: sticky`, but <body> had `overflow-x: hidden`,
 * which makes it a scroll container. The page scrolls on <html>, so <body>
 * never scrolled, and a sticky element sticks to its nearest scroll
 * container: on nine pages the bar went up with the page. `clip` hides the
 * same sideways overflow without being a scroll container. Checked in Chrome
 * on nineteen pages at 1536, 1366 and 400 wide.
 */
class StickyTopBarTest extends TestCase
{
    public function test_the_top_bar_is_sticky(): void
    {
        $css = file_get_contents(public_path('layouts.css'));

        $this->assertMatchesRegularExpression('/\.topbar\s*\{[^}]*position:\s*sticky;[^}]*top:\s*0;/', $css);
    }

    /** ui-fixes.css loads after layouts.css and design-tokens.css, so its body rule is the one that holds. */
    public function test_body_clips_instead_of_becoming_a_scroll_container(): void
    {
        $css  = file_get_contents(public_path('ui-fixes.css'));
        $body = preg_match('/\nbody \{(.*?)\n\}/s', $css, $m) ? preg_replace('~/\*.*?\*/~s', '', $m[1]) : '';

        $this->assertStringContainsString('overflow-x: clip;', $body);
        $this->assertStringContainsString('overflow-y: visible;', $body);
        $this->assertStringNotContainsString('overflow-x: hidden', $body);
    }

    /** Held to the bottom now that sticky works, the employee form's Save bar leaves room for the chat button. */
    public function test_the_employee_save_bar_clears_the_chat_button(): void
    {
        $css = file_get_contents(resource_path('views/employees/_profile_styles.blade.php'));

        $this->assertStringContainsString('.ep-actions { padding-right: 84px; }', $css);
    }
}
