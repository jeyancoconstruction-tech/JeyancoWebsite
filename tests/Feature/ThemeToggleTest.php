<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The header's theme toggle switches the theme with a circle that grows out
 * of the button over the whole page (View Transitions), fades in a browser
 * without them, and switches at once for somebody who has asked for less
 * motion. How the theme is applied and remembered is unchanged.
 *
 * The animation itself runs in the browser, which PHPUnit cannot see; what
 * it can hold is that every page carries the wiring, and that the saved
 * preference is still written the way the head script reads it back.
 */
class ThemeToggleTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Theme Admin', 'username' => 'theme.admin', 'password' => 'secret123',
            'role' => User::ROLE_ADMIN, 'is_admin' => true, 'is_active' => true,
        ]);
    }

    /** The toggle lives in the shared layout, so every page has the reveal. */
    public function test_every_page_carries_the_toggle_and_its_reveal(): void
    {
        $admin = $this->admin();

        foreach (['/dashboard', '/employees', '/attendance', '/leave-advances', '/payroll-records', '/settings'] as $url) {
            $html = $this->actingAs($admin)->get($url)->assertOk()->getContent();

            $this->assertStringContainsString('id="themeToggle"', $html, $url);
            $this->assertStringContainsString('document.startViewTransition(function() { applyTheme(next); })', $html, $url);
            $this->assertStringContainsString("pseudoElement: '::view-transition-new(root)'", $html, $url);
            $this->assertStringContainsString("'circle(0px at '", $html, "{$url}: grows from the button");
        }
    }

    /** No reveal where it cannot run or is not wanted, and one switch at a time. */
    public function test_it_falls_back_and_guards_against_double_clicks(): void
    {
        $html = $this->actingAs($this->admin())->get('/dashboard')->getContent();

        $this->assertStringContainsString("matchMedia('(prefers-reduced-motion: reduce)')", $html);
        $this->assertStringContainsString("if (reduce) { applyTheme(next); return; }", $html, 'reduced motion switches at once');
        $this->assertStringContainsString('if (!document.startViewTransition) {', $html, 'older browsers fade');
        $this->assertStringContainsString("html.classList.add('theme-transition');", $html);
        $this->assertStringContainsString('if (switching) return;', $html, 'a second click mid-reveal is ignored');
    }

    /** The saved choice is written exactly as the head script reads it back. */
    public function test_the_saved_preference_is_unchanged(): void
    {
        $html = $this->actingAs($this->admin())->get('/dashboard')->getContent();

        $this->assertStringContainsString("try { localStorage.setItem('jeyanco-theme', next); } catch (e) {}", $html);
        $this->assertStringContainsString("localStorage.getItem('jeyanco-theme')", $html);
        $this->assertStringContainsString("html.setAttribute('data-bs-theme', next);", $html);
    }

    /** The styles: only the circle runs, the icons turn, and less motion means none. */
    public function test_the_stylesheet_carries_the_reveal_and_the_icon_swap(): void
    {
        $css = file_get_contents(public_path('dark-mode.css'));

        $this->assertMatchesRegularExpression('/::view-transition-old\(root\),\s*::view-transition-new\(root\)\s*\{\s*animation: none;/', $css);
        $this->assertStringContainsString('::view-transition-new(root) { z-index: 2; }', $css);

        // The moon in light mode, the sun in dark, each turning in and out.
        $this->assertStringContainsString('.theme-switch .ts-sun  { color: #f59e0b; transform: rotate(-90deg) scale(0.4); opacity: 0; }', $css);
        $this->assertStringContainsString('[data-bs-theme="dark"] .theme-switch .ts-moon { transform: rotate(90deg) scale(0.4); opacity: 0; }', $css);
        $this->assertStringContainsString('[data-bs-theme="dark"] .theme-switch .ts-sun  { transform: none; opacity: 1; }', $css);

        $this->assertMatchesRegularExpression('/@media \(prefers-reduced-motion: reduce\) \{\s*\.theme-switch,/', $css);
    }
}
