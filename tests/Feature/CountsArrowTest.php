<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Employees and Attendance put their counts behind an arrow at the side.
 * The counts are out whenever the page loads: the arrow puts them away only
 * until the next load, and nothing about it is kept in the browser.
 *
 * The folding itself happens in the browser; what PHPUnit can hold is that
 * the page arrives open and that nothing reads a remembered state.
 */
class CountsArrowTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name'      => 'Admin',
            'username'  => 'admin.arrow',
            'password'  => Hash::make('secret123'),
            'is_admin'  => true,
            'is_active' => true,
        ]);
    }

    public static function pages(): array
    {
        return [
            'employees'  => ['employees.register', 'rmxHandle', 'rmxSum', 'rmx-sumwrap'],
            'attendance' => ['attendance', 'attStatsHandle', 'attStatsFold', 'atm-fold'],
        ];
    }

    #[DataProvider('pages')]
    public function test_the_counts_are_out_when_the_page_loads(string $route, string $handle, string $fold, string $class): void
    {
        $html = $this->actingAs($this->admin())->get(route($route))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/id="' . $handle . '" aria-expanded="true" aria-controls="' . $fold . '"/', $html);
        $this->assertStringContainsString('class="' . $class . ' open" id="' . $fold . '"', $html);
        $this->assertStringNotContainsString('jeyanco-employees-summary', $html, 'Open or shut is not remembered');
    }

    public function test_the_attendance_fold_sits_outside_what_a_fetch_replaces(): void
    {
        // The filters and the live feed replace the inside of #attStats; the
        // fold wraps it, so a fetch never opens counts somebody put away.
        $html = $this->actingAs($this->admin())->get(route('attendance'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/id="attStatsFold">\s*<div class="atm-fold-in">\s*<div class="atm-stats" id="attStats" data-live=/',
            $html
        );
    }
}
