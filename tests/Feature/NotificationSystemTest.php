<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * One notification system for every CRUD action.
 *
 * Before this the app answered a Create, Update or Delete in five different
 * ways: the browser's own alert() and confirm(), four hand-rolled toasts (two
 * of them literal copies of each other, all with hardcoded light-mode hexes
 * that were unreadable in dark mode), and a Bootstrap banner echoed inline on
 * nine pages. The same delete looked different depending on the screen.
 *
 * Two of these tests read the Blade sources rather than a response. That is
 * deliberate: the point is that no page anywhere reintroduces a browser
 * dialog, and only a sweep over the files can say that.
 */
class NotificationSystemTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name'      => 'Admin',
            'username'  => 'admin.notify',
            'password'  => Hash::make('secret123'),
            'is_admin'  => true,
            'is_active' => true,
        ]);
    }

    /**
     * Every .blade.php with its comments stripped.
     *
     * The comments matter: several of them explain what replaced the browser
     * dialogs, and say "confirm()" while doing so. Scanning the prose would
     * report the explanation as the offence.
     */
    private function bladeSources(): array
    {
        $out = [];
        foreach (File::allFiles(resource_path('views')) as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) continue;

            $src = $file->getContents();
            $src = preg_replace('/\{\{--.*?--\}\}/s', '', $src);   // Blade comments
            $src = preg_replace('#/\*.*?\*/#s', '', $src);         // CSS and JS block comments
            $src = preg_replace('#^\s*//.*$#m', '', $src);         // JS line comments

            $out[$file->getRelativePathname()] = $src;
        }
        return $out;
    }

    public function test_no_page_still_uses_a_browser_alert_or_confirm(): void
    {
        $offenders = [];

        foreach ($this->bladeSources() as $name => $src) {
            // \b so Notify.confirm(), confirmLabel and data-confirm are not
            // mistaken for the browser's own dialogs.
            if (preg_match('/(?<![\w.])alert\s*\(/', $src))   $offenders[] = "$name: alert()";
            if (preg_match('/(?<![\w.-])confirm\s*\(/', $src)) $offenders[] = "$name: confirm()";
        }

        $this->assertSame(
            [],
            $offenders,
            "browser dialogs are not the app's notification system:\n" . implode("\n", $offenders)
        );
    }

    /**
     * Four copies of the same toast, each with its own container, its own
     * keyframes and its own light-mode palette. They are one function now, so
     * none of those names should still exist anywhere.
     *
     * The palettes themselves are not searched for: #fee2e2 is also an
     * ordinary error-field background in several places, and flagging those
     * would be a false alarm rather than a finding.
     */
    public function test_the_hand_rolled_toasts_are_gone(): void
    {
        $dead = [
            'site-toast-wrap', 'emp-toast-wrap', 'hcal-flash-wrap', 'rmToast',
            'siteToastIn', 'empToastIn', 'hcalFlash',
            'acct-flash', 'emp-flash', 'rm-alert-ok',
        ];

        $offenders = [];
        foreach ($this->bladeSources() as $name => $src) {
            foreach ($dead as $token) {
                if (str_contains($src, $token)) $offenders[] = "$name: $token";
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "these belonged to the per-page toasts the shared notifier replaced:\n" . implode("\n", $offenders)
        );
    }

    public function test_the_notifier_is_on_every_page_that_uses_the_main_layout(): void
    {
        $html = $this->actingAs($this->admin())
            ->get(route('employees.index'))
            ->assertOk()
            ->getContent();

        $head = substr($html, 0, strpos($html, '</head>'));

        // The CSS is included in the head directly, not pushed: the layout
        // renders @stack('styles') before the body include, so a push from
        // there would be written out after the stack and silently dropped.
        $this->assertStringContainsString('#jy-toasts {', $head);

        $this->assertStringContainsString('id="jy-toasts"', $html);
        $this->assertStringContainsString('id="jy-confirm"', $html);
        $this->assertStringContainsString('window.Notify = {', $html);
    }

    /** Notify must exist before any page script that might call it. */
    public function test_notify_is_defined_before_the_page_content(): void
    {
        $html = $this->actingAs($this->admin())
            ->get(route('employees.index'))
            ->assertOk()
            ->getContent();

        $notify  = strpos($html, 'window.Notify = {');
        $content = strpos($html, '</body>');

        $this->assertNotFalse($notify);
        $this->assertLessThan($content, $notify);
    }

    public function test_a_flash_message_comes_through_as_a_toast(): void
    {
        $html = $this->actingAs($this->admin())
            ->withSession(['success' => 'Employee removed.'])
            ->get(route('employees.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString("tone: 'success'", $html);
        $this->assertStringContainsString('Employee removed.', $html);
    }

    /**
     * A worker called O'Brien, or a site with an ampersand in its name, must
     * not be able to close the string it is being written into. @json handles
     * the server half; the client half builds the toast with textContent.
     */
    public function test_a_flash_message_carrying_quotes_is_escaped(): void
    {
        $html = $this->actingAs($this->admin())
            ->withSession(['success' => 'Removed O\'Brien & <b>Sons</b>.'])
            ->get(route('employees.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString("'Removed O'Brien", $html);
        $this->assertStringContainsString('createTextNode(message)', $html);
    }

    /** The destructive actions ask through the shared dialog, not the browser. */
    public function test_destructive_actions_carry_the_declarative_confirm_hook(): void
    {
        $html = $this->actingAs($this->admin())
            ->get(route('employees.register'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('form[data-confirm]', $html, 'the delegated submit hook should be present');
        $this->assertStringContainsString("closest('[data-confirm]')", $html, 'the delegated click hook should be present');
    }

    /**
     * The tone is not decoration. A delete that cannot be undone and a rate
     * that saved should not arrive looking the same.
     */
    public function test_the_notifier_offers_all_four_tones_and_a_confirm(): void
    {
        $src = File::get(resource_path('views/_notify.blade.php'));

        foreach (['success:', 'error:', 'warning:', 'info:', 'confirm: ask'] as $api) {
            $this->assertStringContainsString($api, $src, "Notify.$api should exist");
        }

        $styles = File::get(resource_path('views/_notify_styles.blade.php'));

        // Theme tokens, not hexes: one block that follows light and dark.
        $this->assertStringContainsString('var(--success', $styles);
        $this->assertStringContainsString('var(--danger', $styles);
        $this->assertStringContainsString('var(--warning', $styles);
        $this->assertStringContainsString('var(--brand', $styles);
        $this->assertStringNotContainsString('[data-bs-theme="dark"]', $styles);
    }
}
