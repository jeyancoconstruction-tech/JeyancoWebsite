<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The floating chat button can be dragged out of the way.
 *
 * It sat in the bottom-right corner, over the last column of a table's
 * buttons. public/js/chatbot-move.js lets it be dragged anywhere and
 * remembers the spot in the browser. The dragging itself was checked in
 * Chrome (mouse and touch); this keeps the wiring it depends on in place.
 */
class ChatbotMoveTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_page_loads_the_script_right_after_the_button(): void
    {
        $admin = User::create([
            'name' => 'Chat Admin', 'username' => 'chat.admin', 'password' => 'secret123',
            'role' => User::ROLE_ADMIN, 'is_admin' => true, 'is_active' => true,
        ]);

        $html = $this->actingAs($admin)->get('/dashboard')->assertOk()->getContent();

        $fab    = strpos($html, 'id="chatbot-fab"');
        $window = strpos($html, 'id="chatbot-window"');
        $script = strpos($html, 'js/chatbot-move.js');

        $this->assertNotFalse($fab);
        $this->assertNotFalse($script, 'the drag script is loaded');
        // After both elements exist, and not deferred: a saved spot has to be
        // applied before the page is first drawn, or the button jumps.
        $this->assertGreaterThan($window, $script);
        $this->assertDoesNotMatchRegularExpression('/<script[^>]*chatbot-move\.js[^>]*\b(defer|async)\b/', $html);
    }

    public function test_the_script_keeps_a_drag_from_opening_the_chat(): void
    {
        $js = file_get_contents(public_path('js/chatbot-move.js'));

        $this->assertStringContainsString("'jeyanco-chatbot-pos'", $js);          // remembered per browser
        $this->assertStringContainsString("addEventListener('pointerdown'", $js); // mouse, finger and pen
        // The click that ends a drag is swallowed before the layout's handler.
        $this->assertMatchesRegularExpression("/addEventListener\('click',[\s\S]*?stopPropagation\(\)[\s\S]*?\}, true\);/", $js);
        // A picture or link under the button must not start a drag of its own.
        $this->assertStringContainsString("addEventListener('dragstart'", $js);

        $css = file_get_contents(public_path('layouts.css'));
        $this->assertMatchesRegularExpression('/\.chatbot-fab\s*\{[^}]*touch-action:\s*none/', $css);
    }
}
