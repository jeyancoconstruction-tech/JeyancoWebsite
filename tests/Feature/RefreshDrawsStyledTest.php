<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * A refresh draws the page while the browser waits on the scripts in the body
 * (Michael, 2026-10-03). Whatever is on screen by then must already be styled:
 *
 *  - the bell's CSS sat at the end of the body, so its menu showed open and
 *    unstyled — "Mark all read · Delete all · Loading…" — until it arrived;
 *  - the icons were drawn only after four scripts had loaded, so the sidebar,
 *    the theme switch and the bell were blank boxes until then.
 */
class RefreshDrawsStyledTest extends TestCase
{
    use RefreshDatabase;

    private function page(): string
    {
        $admin = User::create([
            'name' => 'Admin', 'username' => 'admin.refresh', 'password' => Hash::make('secret123'),
            'is_admin' => true, 'is_active' => true,
        ]);

        return $this->actingAs($admin)->get(route('dashboard'))->assertOk()->getContent();
    }

    public function test_the_bell_menu_is_hidden_by_css_in_the_head(): void
    {
        $html = $this->page();
        $head = strpos($html, '</head>');

        $rule = strpos($html, '.notif-dropdown {');
        $this->assertNotFalse($rule);
        $this->assertLessThan($head, $rule, 'the rule that hides the menu is read before anything is drawn');

        // Still after every stylesheet, so it wins the same ties as before.
        $this->assertLessThan($rule, strpos($html, 'mobile.css'));
        $this->assertSame(1, preg_match_all('/^\.notif-dropdown \{/m', $html), 'one copy, not two');
    }

    public function test_the_icons_are_drawn_before_the_first_script_in_the_body(): void
    {
        $html  = $this->page();
        $first = strpos($html, 'lucide.createIcons()', strpos($html, '<body'));

        $this->assertNotFalse($first);
        $this->assertLessThan(strpos($html, 'chatbot-move.js'), $first);
    }
}
