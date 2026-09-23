<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * No loading screen.
 *
 * There used to be one: a full-screen overlay that held every arrival for at
 * least 1.9 seconds, and came back up when Sign In was pressed. On 2026-09-23
 * Michael asked for it gone — no full loading screen on Sign In, and nothing
 * that blocks the page while it loads. What is left is the sign-in page's
 * entrance, which plays over a page that is already there and usable.
 *
 * It also took something else with it. Its CSS styled a class called .fade,
 * which is Bootstrap's class for every dialog and tab; loaded on the app's
 * layout, it made each tab switch wait a second and each dialog take two to
 * close. The entrance's classes are its own now, and this holds them there.
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

    private function loginPage(): string
    {
        return $this->get(route('login'))->assertOk()->getContent();
    }

    private function headOf(string $html): string
    {
        return substr($html, 0, strpos($html, '</head>'));
    }

    private function entrance(): string
    {
        return File::get(resource_path('views/_entrance.blade.php'));
    }

    /** The entrance's CSS, without the comments that talk about it. */
    private function styles(): string
    {
        $src = $this->entrance();
        $css = substr($src, strpos($src, '<style>'), strpos($src, '</style>') - strpos($src, '<style>'));

        return preg_replace('#/\*.*?\*/#s', '', $css);
    }

    // ── There is no loading screen ───────────────────────────────────────

    public function test_no_page_has_a_loading_overlay(): void
    {
        // The sign-in page first: /login is guest-only, and acting as the
        // admin for the dashboard would redirect it away.
        $pages = ['the sign-in page' => $this->loginPage()];
        $pages['the dashboard layout'] = $this->appPage();

        foreach ($pages as $where => $html) {
            $this->assertStringNotContainsString('id="jp-loader"', $html, $where);
            $this->assertStringNotContainsString('JeyancoLoader', $html, $where);
            $this->assertStringNotContainsString('MIN_SHOW', $html, "{$where} holds nobody on a splash");
        }

        $this->assertFileDoesNotExist(resource_path('views/_loading.blade.php'));
        $this->assertFileDoesNotExist(resource_path('views/_loading_head.blade.php'));
    }

    /**
     * Pressing Sign In submits the form, then and there. The button says it
     * is working; nothing covers the page and nothing waits before sending.
     */
    public function test_signing_in_submits_straight_away(): void
    {
        $src = File::get(resource_path('views/login.blade.php'));

        $this->assertStringNotContainsString('JeyancoLoader', $src);
        $this->assertStringNotContainsString('HTMLFormElement.prototype.submit', $src,
            'the browser submits the form itself; the script does not hold it back');
        $this->assertDoesNotMatchRegularExpression('/setTimeout\([^)]*submit/', $src);

        // The feedback that stays is on the button.
        $this->assertStringContainsString("btn.classList.add('loading')", $src);
        $this->assertStringContainsString('Signing in…', $src);

        $html = $this->loginPage();
        $this->assertStringContainsString('class="spin"', $html);
    }

    public function test_the_app_layout_carries_no_entrance_at_all(): void
    {
        $html = $this->appPage();

        $this->assertStringNotContainsString('jp-enter', $html);
        $this->assertStringNotContainsString('.jp-fade', $html);
    }

    // ── What the entrance may and may not do ─────────────────────────────

    /**
     * Bootstrap animates dialogs and tabs with .fade and waits for the
     * transition to finish before it completes them. Anything else styling
     * .fade decides how long every dialog and tab takes.
     */
    public function test_the_entrance_leaves_bootstraps_classes_alone(): void
    {
        $css = $this->styles();

        $this->assertDoesNotMatchRegularExpression('/(^|[\s,{}])\.fade\b/m', $css,
            'the entrance must not style Bootstrap\'s .fade');
        $this->assertDoesNotMatchRegularExpression('/(^|[\s,{}])\.show\b/m', $css);

        $html = $this->loginPage();
        $this->assertStringNotContainsString(' fade"', $html, 'the skyline uses jp-fade, not fade');
        $this->assertStringContainsString('jp-fade', $html);
    }

    /**
     * Nothing is hidden unless the arrival script has marked the page. If it
     * never runs, the page is simply there.
     */
    public function test_nothing_is_hidden_without_the_script(): void
    {
        preg_match_all('/([^{}]+)\{[^{}]*(opacity:\s*0|stroke-dashoffset:\s*1)\b[^{}]*\}/', $this->styles(), $m);

        $this->assertNotEmpty($m[1]);
        foreach ($m[1] as $selector) {
            $this->assertStringContainsString('.jp-enter', $selector,
                "{$selector} hides content without the arrival mark");
        }
    }

    /**
     * Its transitions apply while it plays and not after, so the button and
     * the fields keep their own timings once the page is in.
     */
    public function test_the_entrance_lets_go_of_the_page_when_it_is_done(): void
    {
        $src = $this->entrance();

        $this->assertStringContainsString("classList.replace('jp-enter', 'jp-entering')", $src);
        $this->assertMatchesRegularExpression("/setTimeout\(function \(\) \{ d\.classList\.remove\('jp-entering'\); \}, \d+\)/", $src);

        preg_match_all('/([^{}]+)\{[^{}]*transition[^{}]*\}/', $this->styles(), $m);
        foreach ($m[1] as $selector) {
            $this->assertStringContainsString('.jp-entering', $selector,
                "{$selector} would keep the entrance's transition on the page for good");
        }
    }

    // ── Opened, or navigated to? ─────────────────────────────────────────

    public function test_it_plays_only_on_an_arrival(): void
    {
        $head = $this->headOf($this->loginPage());

        $this->assertStringContainsString('document.referrer', $head);
        $this->assertStringContainsString('location.origin', $head);
        $this->assertStringContainsString("nav.type === 'back_forward'", $head);
        $this->assertStringContainsString("classList.add('jp-enter')", $head,
            'the mark is set in the head, before the page is drawn');
    }

    public function test_the_page_still_comes_in_piece_by_piece(): void
    {
        $html = $this->loginPage();

        $this->assertGreaterThanOrEqual(8, preg_match_all('/class="[^"]*\brv\b/', $html),
            'the panel, the pitch, the fields and the foot each arrive in turn');
        $this->assertStringContainsString('pathLength="1"', $html, 'the skyline draws itself in');
    }

    public function test_the_back_button_does_not_strand_a_hidden_page(): void
    {
        $this->assertStringContainsString("'pageshow'", $this->entrance());
        $this->assertStringContainsString('e.persisted', $this->entrance());
    }

    public function test_the_movement_is_dropped_for_anyone_who_asked_for_less(): void
    {
        $src   = $this->entrance();
        $block = substr($src, strpos($src, 'prefers-reduced-motion: reduce'));

        foreach (['.rv', '.draw', '.jp-fade'] as $moving) {
            $this->assertStringContainsString($moving, $block, "{$moving} should hold still when asked");
        }
    }

    /**
     * The mark comes from /images, not from copies of the same file inlined
     * as base64 — which is how the design arrived, at about 25KB a copy.
     */
    public function test_the_mark_is_served_not_inlined(): void
    {
        $html = $this->loginPage();

        $this->assertStringContainsString('images/logo-mark.png', $html);
        $this->assertStringNotContainsString('data:image/png;base64', $html,
            'the logo is a file, cached once, not bytes in the HTML');
        $this->assertFileExists(public_path('images/logo-mark.png'));
    }
}
