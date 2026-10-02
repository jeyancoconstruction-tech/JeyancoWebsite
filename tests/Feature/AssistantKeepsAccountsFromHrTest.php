<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Jeyanco Bot does not tell HR what Users & Roles would not (2026-09-30):
 * the accounts, their emails and who holds admin rights. It used to list
 * every account to anybody who asked "list users". HR security test case:
 * the assistant declines a request beyond the role.
 */
class AssistantKeepsAccountsFromHrTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, string $username): User
    {
        return User::create([
            'name' => ucfirst($username), 'username' => $username, 'email' => $username . '@jeyanco.test',
            'password' => Hash::make('secret123'), 'role' => $role, 'is_active' => true,
        ]);
    }

    private function ask(User $who, string $message): string
    {
        return $this->actingAs($who)->postJson(route('ai.chat'), ['message' => $message])->assertOk()->json('reply');
    }

    public function test_hr_is_refused_the_accounts(): void
    {
        $this->user(User::ROLE_ADMIN, 'boss');
        $hr = $this->user(User::ROLE_HR, 'clerk');

        foreach (['list users', 'show all users', 'how many users', 'who are the admins', 'security overview'] as $question) {
            $reply = $this->ask($hr, $question);
            $this->assertStringContainsString('for administrators only', $reply, $question);
            $this->assertStringNotContainsString('boss@jeyanco.test', $reply, $question);
        }
    }

    public function test_an_admin_still_gets_them(): void
    {
        $admin = $this->user(User::ROLE_ADMIN, 'boss');
        $this->user(User::ROLE_HR, 'clerk');

        $reply = $this->ask($admin, 'list users');
        $this->assertStringContainsString('SYSTEM USERS', $reply);
        $this->assertStringContainsString('clerk@jeyanco.test', $reply);
    }
}
