<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Payroll Settings draws itself in the app's theme tokens, like every other
 * page. It used to sit in a card of its own, a lighter navy (#1c2740) than
 * anything else, with indigo (#6366f1 / #818cf8) tabs and buttons and a navy
 * gradient Save, and read as a different application. This keeps those
 * colours from coming back, in the page or in dark-mode.css.
 */
class SettingsColoursTest extends TestCase
{
    use RefreshDatabase;

    private const OWN_PALETTE = ['#1c2740', '#151d2e', '#283449', '#6366f1', '#4f46e5', '#818cf8', '#a5b4fc', '#1e3a8a', '#eef2ff'];

    private function page(): string
    {
        $admin = User::create([
            'name' => 'Colour Admin', 'username' => 'colour.admin', 'password' => 'secret123',
            'role' => User::ROLE_ADMIN, 'is_admin' => true, 'is_active' => true,
        ]);

        return $this->actingAs($admin)->get('/settings')->assertOk()->getContent();
    }

    /** The page's own stylesheets name no colour of their own for its surfaces, tabs or buttons. */
    public function test_the_page_uses_the_theme_tokens(): void
    {
        $view = file_get_contents(resource_path('views/settings/index.blade.php'));
        preg_match_all('~<style>(.*?)</style>~s', $view, $m);
        $css = preg_replace('~/\*.*?\*/~s', '', implode("\n", $m[1]));   // rules, not the comments about them

        foreach (self::OWN_PALETTE as $hex) {
            $this->assertStringNotContainsStringIgnoringCase($hex, $css, "Settings paints {$hex} of its own");
        }
        $this->assertStringNotContainsString('linear-gradient', $css, 'Save is the flat brand blue, not a gradient');
        $this->assertStringNotContainsString('[data-bs-theme="dark"]', $css, 'no dark twin to keep in step: the tokens follow the theme');

        $this->assertStringContainsString('.settings-wrapper { background: none; padding: 0;', $css, 'no card of its own around the page');
        $this->assertStringContainsString('.ps-card { background: var(--surface); border: 1px solid var(--border);', $css);
    }

    /**
     * The tab row is gone (2026-09-26): its four sections are Payroll
     * Settings' sub-items in the sidebar, so the page carries no tab strip of
     * its own and its title names the open section.
     */
    public function test_the_sections_live_in_the_sidebar_not_a_tab_row(): void
    {
        $html = $this->page();

        $this->assertStringNotContainsString('settings-tabs', $html);
        $this->assertStringNotContainsString('data-bs-toggle="tab"', $html);
        $this->assertStringContainsString('<h1 class="page-head-title">Multipliers and Deductions</h1>', $html);
        $this->assertStringContainsString('id="navSubSettings"', $html);
    }

    /** dark-mode.css no longer carries a palette just for this page. */
    public function test_dark_mode_css_has_no_settings_palette(): void
    {
        $css = file_get_contents(public_path('dark-mode.css'));

        foreach (['.settings-wrapper', '.settings-tabs', '.settings-header', '.settings-content', '.payroll-formula', '.payroll-config-section'] as $class) {
            $this->assertStringNotContainsString($class, $css);
        }
    }
}
