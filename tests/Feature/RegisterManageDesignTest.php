<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\LaborType;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Register & Manage, redrawn to the supplied design.
 *
 * The layout changed — three stat cards, segmented tabs with one Select
 * toggle beside them, and explicit edit and delete buttons where a kebab menu
 * used to hide them — but every job the page did before it still does. These
 * pin the new shape and the old jobs together.
 */
class RegisterManageDesignTest extends TestCase
{
    use RefreshDatabase;

    private ?User $admin = null;

    private function admin(): User
    {
        return $this->admin ??= User::create([
            'name' => 'Admin', 'username' => 'admin.rmdesign', 'password' => Hash::make('secret123'),
            'is_admin' => true, 'is_active' => true,
        ]);
    }

    private function worker(string $name, string $labor = 'Mason', array $extra = []): Employee
    {
        static $fp = 0;

        return Employee::create($extra + [
            'name'           => $name,
            'position'       => $labor,
            'labor_type_id'  => LaborType::firstOrCreate(['name' => $labor], ['daily_rate' => 1000, 'ot_rate' => 125])->id,
            'site_id'        => Site::firstOrCreate(['name' => 'Site A'])->id,
            'rate_per_hour'  => 125,
            'fingerprint_id' => (string) ++$fp,
            'status'         => Employee::STATUS_ACTIVE,
        ]);
    }

    private function page(string $tab = null): string
    {
        return $this->actingAs($this->admin())
            ->get(route('employees.register', $tab ? ['tab' => $tab] : []))
            ->assertOk()
            ->getContent();
    }

    public function test_the_page_follows_the_design(): void
    {
        $this->worker('Carmella Sarsogo Bio');

        $this->actingAs($this->admin())->get(route('employees.register'))->assertOk()
            ->assertSee('Register & manage employees')
            ->assertSee('Register employee')
            ->assertSee('Pending from kiosk')
            ->assertSee('Carmella Sarsogo Bio')
            ->assertSee('Site A')
            ->assertSee('₱125.00');

        $html = $this->page();

        // Three stat cards that switch tabs, and one Select toggle for all tabs.
        foreach (['active', 'pending', 'removed'] as $tab) {
            $this->assertStringContainsString('class="rmx-stat rmx-stat-' . $tab . '" data-tab="' . $tab . '"', $html);
        }
        $this->assertSame(1, substr_count($html, 'id="rmxSelect"'));
        $this->assertStringContainsString('class="ti ti-bucket"', $html, 'a mason carries the bucket');
    }

    public function test_a_row_has_edit_and_delete_buttons_instead_of_a_menu(): void
    {
        $e    = $this->worker('Marvin De Leon', 'Electrician');
        $html = $this->page();

        $this->assertStringContainsString('href="' . route('employees.edit', $e->id) . '" class="rmx-icon-btn rmx-edit"', $html);
        $this->assertStringContainsString('action="' . route('employees.destroy', $e->id) . '"', $html);
        $this->assertStringContainsString('data-confirm-title="Remove this worker?"', $html);
        $this->assertStringContainsString('class="ti ti-bolt"', $html, 'an electrician carries the bolt');
        $this->assertStringNotContainsString('rm-menu', $html, 'the kebab menu is gone');
    }

    public function test_a_removed_worker_can_be_restored_or_deleted_for_good(): void
    {
        $e = $this->worker('Ryan Ventura Basagre');
        $e->delete();

        $html = $this->page('removed');

        $this->assertStringContainsString('class="rm-pane active" data-pane="removed"', $html);
        $this->assertStringContainsString('action="' . route('employees.restore', $e->id) . '"', $html);
        $this->assertStringContainsString('action="' . route('employees.force-delete', $e->id) . '"', $html);
        $this->assertStringContainsString('data-confirm-tone="danger"', $html);
    }

    public function test_a_trade_the_office_adds_later_still_gets_an_icon(): void
    {
        $this->worker('Rico Rigger', 'Rigger');

        $this->assertStringContainsString('class="ti ti-tool"', $this->page());
    }

    /**
     * A Blade comment was opened twice and closed once, so the tail of it —
     * "full form cannot drift apart. --}}" — was printed into the page head.
     */
    public function test_no_stray_comment_text_reaches_the_page(): void
    {
        $this->assertStringNotContainsString('cannot drift apart. --}}', $this->page());
    }
}
