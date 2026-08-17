<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(array $attributes = []): User
    {
        return User::create(array_merge([
            'name'      => 'Admin One',
            'username'  => 'admin.one',
            'email'     => 'admin.one@jeyanco.test',
            'password'  => Hash::make('secret123'),
            'role'      => User::ROLE_ADMIN,
            'is_active' => true,
        ], $attributes));
    }

    private function staff(array $attributes = []): User
    {
        return User::create(array_merge([
            'name'      => 'Staff One',
            'username'  => 'staff.one',
            'password'  => Hash::make('secret123'),
            'role'      => User::ROLE_STAFF,
            'is_active' => true,
        ], $attributes));
    }

    public function test_role_keeps_the_legacy_is_admin_flag_in_sync(): void
    {
        $user = $this->staff();
        $this->assertFalse($user->fresh()->is_admin);

        $user->update(['role' => User::ROLE_ADMIN]);
        $this->assertTrue($user->fresh()->is_admin);
    }

    public function test_admin_can_create_a_staff_account_that_can_log_in(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('accounts.store'), [
            'name'                  => 'Maria Santos',
            'username'              => 'maria.santos',
            'email'                 => 'maria@jeyanco.test',
            'role'                  => User::ROLE_STAFF,
            'is_active'             => 1,
            'password'              => 'payroll2026',
            'password_confirmation' => 'payroll2026',
        ])->assertRedirect(route('accounts.index'));

        $created = User::where('username', 'maria.santos')->first();
        $this->assertNotNull($created);
        $this->assertSame(User::ROLE_STAFF, $created->role);
        $this->assertFalse($created->is_admin);
        $this->assertSame($admin->id, $created->created_by);

        // The whole point: the new account can actually sign in. The admin has
        // to step out of the session first — /login is guest-only.
        $this->app['auth']->logout();
        $this->flushSession();

        $this->post(route('login.post'), [
            'username' => 'maria.santos',
            'password' => 'payroll2026',
        ])->assertRedirect('dashboard');

        $this->assertAuthenticatedAs($created);
    }

    public function test_admin_can_create_an_account_without_an_email(): void
    {
        $this->actingAs($this->admin())->post(route('accounts.store'), [
            'name'                  => 'No Email',
            'username'              => 'no.email',
            'email'                 => '',
            'role'                  => User::ROLE_STAFF,
            'password'              => 'payroll2026',
            'password_confirmation' => 'payroll2026',
        ])->assertRedirect(route('accounts.index'));

        $this->assertNull(User::where('username', 'no.email')->first()->email);
    }

    public function test_admin_can_edit_account_details_and_reset_the_password(): void
    {
        $admin = $this->admin();
        $staff = $this->staff();

        $this->actingAs($admin)->put(route('accounts.update', $staff), [
            'name'                  => 'Renamed Staff',
            'username'              => 'renamed.staff',
            'email'                 => 'renamed@jeyanco.test',
            'role'                  => User::ROLE_ADMIN,
            'is_active'             => 1,
            'password'              => 'brandnew2026',
            'password_confirmation' => 'brandnew2026',
        ])->assertRedirect(route('accounts.index'));

        $staff->refresh();
        $this->assertSame('Renamed Staff', $staff->name);
        $this->assertSame('renamed.staff', $staff->username);
        $this->assertSame(User::ROLE_ADMIN, $staff->role);
        $this->assertTrue($staff->is_admin);
        $this->assertTrue(Hash::check('brandnew2026', $staff->password));
    }

    public function test_editing_without_a_password_keeps_the_existing_one(): void
    {
        $staff = $this->staff();

        $this->actingAs($this->admin())->put(route('accounts.update', $staff), [
            'name'      => 'Staff One',
            'username'  => 'staff.one',
            'email'     => '',
            'role'      => User::ROLE_STAFF,
            'is_active' => 1,
            'password'  => '',
        ])->assertRedirect(route('accounts.index'));

        $this->assertTrue(Hash::check('secret123', $staff->fresh()->password));
    }

    public function test_duplicate_usernames_are_rejected(): void
    {
        $this->staff();

        $this->actingAs($this->admin())
            ->post(route('accounts.store'), [
                'name'                  => 'Copycat',
                'username'              => 'staff.one',
                'role'                  => User::ROLE_STAFF,
                'password'              => 'payroll2026',
                'password_confirmation' => 'payroll2026',
            ])
            ->assertSessionHasErrors('username');
    }

    public function test_deactivated_accounts_cannot_log_in(): void
    {
        $this->staff(['is_active' => false]);

        $this->post(route('login.post'), [
            'username' => 'staff.one',
            'password' => 'secret123',
        ])->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_deactivating_an_account_ends_its_live_session(): void
    {
        $staff = $this->staff();

        // Sites is a plain staff-accessible page — the Dashboard's queries use
        // MySQL-only date functions that SQLite cannot run.
        $this->actingAs($staff)->get(route('sites.index'))->assertSuccessful();

        $staff->update(['is_active' => false]);

        $this->actingAs($staff)->get(route('sites.index'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_staff_reach_operational_pages_but_not_admin_pages(): void
    {
        $staff = $this->staff();

        $this->actingAs($staff)->get(route('sites.index'))->assertSuccessful();
        $this->actingAs($staff)->get(route('employees.index'))->assertSuccessful();
        $this->actingAs($staff)->get(route('accounts.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('settings.index'))->assertForbidden();
        $this->actingAs($staff)->post(route('accounts.store'), [])->assertForbidden();
    }

    public function test_admin_reaches_account_management(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('accounts.index'))->assertSuccessful();
        $this->actingAs($admin)->get(route('accounts.create'))->assertSuccessful();
    }

    public function test_admin_cannot_delete_or_deactivate_their_own_account(): void
    {
        $admin = $this->admin();
        $this->admin(['username' => 'admin.two', 'email' => 'two@jeyanco.test']);

        $this->actingAs($admin)->delete(route('accounts.destroy', $admin));
        $this->assertNotNull(User::find($admin->id));

        $this->actingAs($admin)->patch(route('accounts.toggle', $admin));
        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_the_last_admin_cannot_be_removed_or_demoted(): void
    {
        $admin = $this->admin();
        $other = $this->admin(['username' => 'admin.two', 'email' => 'two@jeyanco.test']);

        // With two admins, deleting one is allowed.
        $this->actingAs($admin)->delete(route('accounts.destroy', $other));
        $this->assertNull(User::find($other->id));

        // The remaining admin is now protected from demotion by a peer's action.
        $peer = $this->admin(['username' => 'admin.three', 'email' => 'three@jeyanco.test']);
        $this->actingAs($peer)->delete(route('accounts.destroy', $admin));
        $this->assertNull(User::find($admin->id));

        // Only "peer" is left — it cannot demote itself.
        $this->actingAs($peer)->put(route('accounts.update', $peer), [
            'name'      => $peer->name,
            'username'  => $peer->username,
            'email'     => $peer->email,
            'role'      => User::ROLE_STAFF,
            'is_active' => 1,
            'password'  => '',
        ])->assertSessionHasErrors('role');

        $this->assertSame(User::ROLE_ADMIN, $peer->fresh()->role);
    }

    public function test_public_self_registration_is_closed(): void
    {
        $this->assertFalse(app('router')->has('register'));
        $this->post('/register', [
            'username' => 'intruder',
            'password' => 'letmein123',
        ])->assertNotFound();
    }
}
