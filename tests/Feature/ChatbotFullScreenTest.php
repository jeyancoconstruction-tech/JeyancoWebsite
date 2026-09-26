<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Jeyanco AI left the sidebar on 2026-09-26; the floating chat is how it is
 * reached. Its full-screen button grows the window into the Jeyanco AI page
 * (the same quick prompts, the same conversation) over the page it was
 * opened on, blurred behind it. The move itself was checked in Chrome on
 * desktop and phone, light and dark; this keeps its wiring in place.
 */
class ChatbotFullScreenTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, string $username): User
    {
        return User::create([
            'name' => ucfirst($role), 'username' => $username, 'password' => 'secret123',
            'role' => $role, 'is_admin' => $role === User::ROLE_ADMIN, 'is_active' => true,
        ]);
    }

    private function rail(string $html): string
    {
        $start = strpos($html, '<nav class="nav-menu">');
        $this->assertNotFalse($start, 'sidebar nav not found');

        return substr($html, $start, strpos($html, '</nav>', $start) - $start);
    }

    public function test_jeyanco_ai_is_off_the_sidebar_but_the_chat_stays(): void
    {
        foreach ([User::ROLE_ADMIN, User::ROLE_HR] as $i => $role) {
            $html = $this->actingAs($this->user($role, 'chat.full' . $i))->get('/dashboard')->assertOk()->getContent();
            $rail = $this->rail($html);

            $this->assertStringNotContainsString('ai-assistant', $rail, "{$role} still has Jeyanco AI in the sidebar");
            $this->assertStringNotContainsString('Jeyanco AI', $rail);
            $this->assertStringContainsString('id="chatbot-fab"', $html, "{$role} lost the floating chat");
        }

        // The page itself still opens, for a bookmark or the search.
        $this->actingAs($this->user(User::ROLE_ADMIN, 'chat.page'))->get('/ai-assistant')->assertOk();
    }

    public function test_the_chat_carries_the_full_screen_view(): void
    {
        $html = $this->actingAs($this->user(User::ROLE_ADMIN, 'chat.admin'))->get('/attendance')->assertOk()->getContent();

        $window = strpos($html, 'id="chatbot-window"');
        $this->assertNotFalse($window);
        $this->assertStringContainsString('id="chatbot-backdrop" class="chatbot-backdrop" hidden', $html);
        $this->assertStringContainsString('id="chatbot-full-btn"', $html);
        $this->assertStringContainsString('id="chatbot-prompts-btn"', $html);
        $this->assertStringContainsString('chatbot-full.css', $html);

        // The Jeyanco AI page's own prompts, from the partial they share.
        $this->assertStringContainsString('id="cb-prompts"', $html);
        $this->assertSame(40, substr_count($html, 'class="prompt-chip"'));

        // Loaded after the window it works on, with the drag script.
        $full = strpos($html, 'js/chatbot-full.js');
        $this->assertNotFalse($full);
        $this->assertGreaterThan($window, $full);
        $this->assertGreaterThan(strpos($html, 'js/chatbot-move.js'), $full);
    }

    public function test_the_ai_page_and_the_chat_offer_the_same_prompts(): void
    {
        $html = $this->actingAs($this->user(User::ROLE_ADMIN, 'chat.same'))->get('/ai-assistant')->assertOk()->getContent();

        // Forty on the page, forty in the chat's full-screen view.
        $this->assertSame(80, substr_count($html, 'class="prompt-chip"'));
        $this->assertStringContainsString("promptsPanel.querySelectorAll('.prompt-chip')", $html, 'the page wires only its own');
    }

    /** Like the corner the button starts in: every page opens the chat small. */
    public function test_full_screen_is_not_remembered_past_the_page(): void
    {
        $js = file_get_contents(public_path('js/chatbot-full.js'));

        $this->assertStringNotContainsString('localStorage', $js);
        $this->assertStringNotContainsString('sessionStorage', $js);
        $this->assertStringContainsString('prefers-reduced-motion', $js);
    }
}
