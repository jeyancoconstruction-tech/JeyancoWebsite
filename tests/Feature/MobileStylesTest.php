<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phones and tablets get their own stylesheet, and desktop is left exactly
 * as it was. What makes that true is that every rule in public/mobile.css
 * sits inside a max-width query no wider than 1024px — the width at which
 * the sidebar already goes off-canvas — so a desktop or laptop screen never
 * matches one. A rule added outside such a query would reach every screen;
 * this is what stops it.
 *
 * How the pages look on a phone is checked in a browser (see the memory
 * note on the headless check); PHPUnit holds the line that keeps desktop
 * safe and the wiring that every page needs.
 */
class MobileStylesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Mobile Admin', 'username' => 'mobile.admin', 'password' => 'secret123',
            'role' => User::ROLE_ADMIN, 'is_admin' => true, 'is_active' => true,
        ]);
    }

    /** @return list<string> the prelude of every top-level block in the sheet */
    private function topLevelBlocks(string $css): array
    {
        $css = preg_replace('~/\*.*?\*/~s', '', $css);
        $blocks = [];
        $depth = 0;
        $start = 0;
        for ($i = 0, $n = strlen($css); $i < $n; $i++) {
            if ($css[$i] === '{') {
                if ($depth === 0) {
                    $blocks[] = trim(substr($css, $start, $i - $start));
                }
                $depth++;
            } elseif ($css[$i] === '}') {
                $depth--;
                $this->assertGreaterThanOrEqual(0, $depth, 'mobile.css has an unmatched }');
                if ($depth === 0) {
                    $start = $i + 1;
                }
            }
        }
        $this->assertSame(0, $depth, 'mobile.css has an unclosed {');
        $this->assertSame('', trim(substr($css, $start)), 'mobile.css has a declaration outside any block');

        return $blocks;
    }

    public function test_every_rule_is_inside_a_query_no_wider_than_a_tablet(): void
    {
        $blocks = $this->topLevelBlocks(file_get_contents(public_path('mobile.css')));

        $this->assertNotEmpty($blocks);
        foreach ($blocks as $prelude) {
            $this->assertStringStartsWith('@media', $prelude, "Outside a media query, so it reaches desktop: {$prelude}");
            // A comma is "or": `(max-width: 767px), print` would match a desktop printing.
            $this->assertStringNotContainsString(',', $prelude, "A query list can match desktop: {$prelude}");
            $this->assertDoesNotMatchRegularExpression('/\bnot\b|min-width/', $prelude, "Can match desktop: {$prelude}");
            $this->assertMatchesRegularExpression('/\(max-width:\s*(\d+(?:\.\d+)?)px\)/', $prelude, "No max-width: {$prelude}");

            preg_match('/\(max-width:\s*(\d+(?:\.\d+)?)px\)/', $prelude, $m);
            $this->assertLessThanOrEqual(1024, (float) $m[1], "Wider than a tablet, so it reaches a laptop: {$prelude}");
        }
    }

    /** Loaded on every signed-in page, after the page's own styles. */
    public function test_every_page_loads_the_mobile_styles_last(): void
    {
        $admin = $this->admin();

        foreach (['/dashboard', '/employees/register', '/attendance', '/leave-advances', '/payroll-records', '/settings', '/users-roles'] as $url) {
            $html = $this->actingAs($admin)->get($url)->assertOk()->getContent();
            $head = substr($html, 0, strpos($html, '</head>'));

            $this->assertStringContainsString('mobile.css?v=', $head, $url);
            $this->assertGreaterThan(strpos($head, 'live.css'), strpos($head, 'mobile.css'), "{$url}: after the theme files");
            $this->assertStringContainsString('js/mobile.js?v=', $html, $url);
        }
    }

    /** The script does its work on a phone only, and keeps up with rows that arrive later. */
    public function test_the_script_only_acts_on_a_phone(): void
    {
        $js = file_get_contents(public_path('js/mobile.js'));

        $this->assertStringContainsString("window.matchMedia('(max-width: 767.98px)')", $js);
        $this->assertStringContainsString('if (!phone.matches) {', $js, 'start() stops on a wider screen');
        $this->assertStringContainsString("document.addEventListener('live:updated', function () { if (phone.matches) { labelAll(); } });", $js);
        $this->assertStringContainsString("cells[c].setAttribute('data-mlabel', name);", $js);
    }

    /** A list turned into cards names its lines from the table's own header row. */
    public function test_the_lists_drawn_as_cards_keep_their_headings(): void
    {
        $admin = $this->admin();
        $css = file_get_contents(public_path('mobile.css'));

        foreach (['/leave-advances' => 'mod-table', '/employees/register' => 'rmx-table', '/attendance' => 'atm-table'] as $url => $table) {
            $html = $this->actingAs($admin)->get($url)->assertOk()->getContent();

            $this->assertMatchesRegularExpression('/<table class="' . $table . '[^"]*"[^>]*>\s*<thead>/', $html, "{$url}: {$table} has a header to name its lines");
            $this->assertStringContainsString('.' . $table, $css, "{$table} is drawn as cards");
        }
    }
}
