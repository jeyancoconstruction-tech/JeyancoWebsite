<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The rail has to fit on one screen.
 *
 * Counting is the part worth automating. The heights below are the values in
 * public/nav-fit.css, and they only stay true if the number of things on the
 * rail stays what it is — so this test reads the rendered sidebar rather than
 * trusting a tally, and fails the moment someone adds a menu item that pushes
 * System Settings back below the fold.
 *
 * If it fails, the fix is not to raise the budget: it is to tighten a tier in
 * nav-fit.css, or to decide the new item belongs inside a page rather than on
 * the rail.
 */
class SidebarFitsTest extends TestCase
{
    use RefreshDatabase;

    /** The tight tier in nav-fit.css — what a short laptop gets. */
    private const ROW_H     = 28;   // 5 + 5 padding + 18 line
    private const LABEL_H   = 20;   // 12 line + 7 + 2 margin, rounded up
    private const CHROME_H  = 24;   // .sidebar-top padding, 12 top + 12 bottom
    private const BRAND_H   = 48;   // 30 mark + 2 + 10 padding + 1 border + margin

    /** The shortest screen the office realistically uses. */
    private const SHORTEST_VIEWPORT = 768;

    protected function setUp(): void
    {
        parent::setUp();

        // Holiday.php uses MySQL's YEAR() in raw SQL and the dashboard reaches
        // it. Pre-existing production code, so the shim lives here rather than
        // a change being made to it. Same shim the other feature tests carry.
        $pdo = \DB::connection()->getPdo();
        if (\DB::connection()->getDriverName() === 'sqlite' && method_exists($pdo, 'sqliteCreateFunction')) {
            $pdo->sqliteCreateFunction('YEAR',  fn ($d) => $d ? (int) date('Y', strtotime($d)) : null, 1);
            $pdo->sqliteCreateFunction('MONTH', fn ($d) => $d ? (int) date('n', strtotime($d)) : null, 1);
        }
    }

    private function sidebarHeightFor(User $user): array
    {
        $html = $this->actingAs($user)->get('/dashboard')->getContent();

        // Only the rail, not the page: the dashboard has its own .nav-link-ish
        // markup and counting that would make this test lie.
        $start = strpos($html, '<nav class="nav-menu">');
        $end   = strpos($html, '</nav>', $start);
        $this->assertNotFalse($start, 'sidebar nav not found in the response');
        $nav = substr($html, $start, $end - $start);

        $links  = substr_count($nav, 'class="nav-link');
        $labels = substr_count($nav, 'class="menu-section"');

        // The first label is hidden by nav-fit.css (MAIN, which labels one item).
        $visibleLabels = max(0, $labels - 1);

        $height = self::CHROME_H + self::BRAND_H
                + ($links * self::ROW_H)
                + ($visibleLabels * self::LABEL_H);

        return ['links' => $links, 'labels' => $labels, 'height' => $height];
    }

    public function test_admin_sidebar_fits_the_shortest_screen(): void
    {
        $admin = User::create([
            'name' => 'Nav Admin', 'username' => 'navadmin', 'password' => 'secret123',
            'role' => User::ROLE_ADMIN, 'is_active' => true,
        ]);

        $m = $this->sidebarHeightFor($admin);

        $this->assertLessThanOrEqual(
            self::SHORTEST_VIEWPORT,
            $m['height'],
            sprintf(
                'the sidebar needs %dpx for %d links and %d labels, which scrolls on a %dpx screen',
                $m['height'], $m['links'], $m['labels'], self::SHORTEST_VIEWPORT
            )
        );
    }

    /** Every role's rail is shorter than the admin's, so all of them fit. */
    public function test_every_role_sidebar_fits(): void
    {
        foreach (array_keys(User::ROLES) as $i => $role) {
            $u = User::create([
                'name' => 'Nav ' . $role, 'username' => 'nav' . $i,
                'password' => 'secret123', 'role' => $role, 'is_active' => true,
            ]);

            $m = $this->sidebarHeightFor($u);

            $this->assertLessThanOrEqual(
                self::SHORTEST_VIEWPORT, $m['height'],
                "{$role}'s sidebar needs {$m['height']}px ({$m['links']} links)"
            );
        }
    }

    /** The stylesheet doing the work has to actually be on the page. */
    public function test_the_density_stylesheet_is_loaded_after_the_tokens(): void
    {
        $admin = User::create([
            'name' => 'Nav Admin2', 'username' => 'navadmin2', 'password' => 'secret123',
            'role' => User::ROLE_ADMIN, 'is_active' => true,
        ]);

        $html = $this->actingAs($admin)->get('/dashboard')->getContent();

        $tokens = strpos($html, 'design-tokens.css');
        $navfit = strpos($html, 'nav-fit.css');

        $this->assertNotFalse($navfit, 'nav-fit.css is not linked');
        $this->assertGreaterThan(
            $tokens, $navfit,
            'nav-fit.css must load after design-tokens.css or its rules lose the tie'
        );
    }
}
