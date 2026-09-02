<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The employees page builds a Bootstrap modal at parse time. The content
 * section renders above the Bootstrap bundle, so a script left there threw
 * before it could bind the Delete item in the three-dot menu.
 */
class EmployeeMenuScriptTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_page_script_runs_after_bootstrap_is_loaded(): void
    {
        $admin = User::firstOrCreate(
            ['username' => 'admin.menu'],
            ['name' => 'Admin', 'password' => Hash::make('secret123'), 'role' => User::ROLE_ADMIN, 'is_active' => true]
        );

        $html = $this->actingAs($admin)->get('/employees')->assertOk()->getContent();

        $bootstrapAt = strpos($html, 'bootstrap.bundle.min.js');
        $modalAt     = strpos($html, "new bootstrap.Modal(modalEl)");

        $this->assertNotFalse($bootstrapAt, 'the bundle is on the page');
        $this->assertNotFalse($modalAt, 'the delete modal is still built');
        $this->assertGreaterThan($bootstrapAt, $modalAt,
            'the page script has to come after the bundle, or bootstrap is undefined when it runs');
    }
}
