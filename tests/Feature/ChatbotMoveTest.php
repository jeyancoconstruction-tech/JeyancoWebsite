<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The floating chat button can be dragged out of the way.
 *
 * It sat in the bottom-right corner, over the last column of a table's
 * buttons. public/js/chatbot-move.js lets it be dragged anywhere while the
 * page is open; every page load puts it back in its corner. The dragging
 * itself was checked in Chrome (mouse and touch); this keeps the wiring it
 * depends on in place.
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
        // After both elements it works on exist.
        $this->assertGreaterThan($window, $script);
    }

    /** Michael's call: a reload starts the button in its corner again. */
    public function test_a_drag_is_not_remembered_past_the_page(): void
    {
        $js = file_get_contents(public_path('js/chatbot-move.js'));

        $this->assertStringNotContainsString('localStorage.setItem', $js);
        $this->assertStringNotContainsString('localStorage.getItem', $js);
        $this->assertStringNotContainsString('sessionStorage', $js);
    }

    public function test_the_script_keeps_a_drag_from_opening_the_chat(): void
    {
        $js = file_get_contents(public_path('js/chatbot-move.js'));

        $this->assertStringContainsString("addEventListener('pointerdown'", $js); // mouse, finger and pen
        // The click that ends a drag is swallowed before the layout's handler.
        $this->assertMatchesRegularExpression("/addEventListener\('click',[\s\S]*?stopPropagation\(\)[\s\S]*?\}, true\);/", $js);
        // A picture or link under the button must not start a drag of its own.
        $this->assertStringContainsString("addEventListener('dragstart'", $js);

        $css = file_get_contents(public_path('layouts.css'));
        $this->assertMatchesRegularExpression('/\.chatbot-fab\s*\{[^}]*touch-action:\s*none/', $css);
    }
}
