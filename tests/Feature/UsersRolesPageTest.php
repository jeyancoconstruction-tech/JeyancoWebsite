<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Users & Roles, redesigned 2026-09-27: one accounts card carrying its own
 * filters, the access matrix under it, the selected account beside both. The
 * status strip and the role tiles are gone; their figures are on the tabs.
 * The page itself was checked in Chrome at desktop widths and on a phone.
 */
class UsersRolesPageTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Aldrin Admin', 'username' => 'aldrin', 'email' => 'aldrin@example.com', 'password' => 'secret123',
            'role' => User::ROLE_ADMIN, 'is_admin' => true, 'is_active' => true,
        ]);
    }

    private function hr(string $name, string $username, array $extra = []): User
    {
        return User::create(array_merge([
            'name' => $name, 'username' => $username, 'password' => 'secret123',
            'role' => User::ROLE_HR, 'is_admin' => false, 'is_active' => true,
        ], $extra));
    }

    public function test_the_filters_carry_the_counts_the_tiles_used_to(): void
    {
        $admin = $this->admin();
        $this->hr('Hazel Ramos', 'hr.hazel');
        $this->hr('Off Duty', 'off.duty', ['is_active' => false]);

        $html = $this->actingAs($admin)->get(route('users-roles.index'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('~<a class="on" href="[^"]*">All <span class="n">3</span></a>~', $html);
        $this->assertMatchesRegularExpression('~>Administrator <span class="n">1</span></a>~', $html);
        $this->assertMatchesRegularExpression('~>HR <span class="n">2</span></a>~', $html);
        $this->assertMatchesRegularExpression('~>Disabled <span class="n">1</span></a>~', $html);
        $this->assertStringContainsString('3 accounts in 2 roles', $html);
        $this->assertStringContainsString('at least one is always kept', $html);

        // What the redesign took out.
        foreach (['class="sx-status', 'class="ur-tiles', 'class="sx-idx', 'class="sx-kbd', 'sorted by name'] as $gone) {
            $this->assertStringNotContainsString($gone, $html, $gone);
        }
    }

    public function test_the_role_filter_and_search_still_narrow_the_list(): void
    {
        $admin = $this->admin();
        $this->hr('Hazel Ramos', 'hr.hazel');

        $this->actingAs($admin)->get(route('users-roles.index', ['role' => 'hr']))
             ->assertOk()->assertSee('Hazel Ramos')->assertDontSee('aldrin@example.com');

        $this->get(route('users-roles.index', ['q' => 'hazel']))
             ->assertOk()->assertSee('hr.hazel')->assertDontSee('aldrin@example.com');
    }

    public function test_an_account_picked_from_the_list_comes_first_on_a_phone(): void
    {
        $admin = $this->admin();
        $hazel = $this->hr('Hazel Ramos', 'hr.hazel');

        $this->actingAs($admin)->get(route('users-roles.index'))->assertOk()
             ->assertSee('class="ur-grid "', false);
        $this->get(route('users-roles.index', ['account' => $hazel->id]))->assertOk()
             ->assertSee('class="ur-grid picked"', false);

        $this->assertStringContainsString('.ur-grid.picked .ins { order: -1; }', file_get_contents(public_path('mobile.css')));
    }

    public function test_a_role_from_before_there_were_two_reads_as_hr(): void
    {
        $admin = $this->admin();
        $old   = $this->hr('Paolo Villanueva', 'payroll.paolo', ['role' => User::ROLE_PAYROLL]);

        $this->actingAs($admin)->get(route('users-roles.index', ['account' => $old->id]))->assertOk()
             ->assertSee('data-role="hr"', false)
             ->assertDontSee('data-role="payroll_officer"', false)
             ->assertSee('Can open · 5 of 7');
    }
}
