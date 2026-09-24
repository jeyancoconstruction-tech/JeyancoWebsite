<?php

namespace Tests\Feature;

use App\Models\Holiday;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * There are no custom holidays.
 *
 * Google Calendar supplies the official ones, proclamation days included, so
 * the Holidays tab only turns official days on and off. A custom holiday left
 * in the table from before must neither show nor change anybody's pay.
 */
class NoCustomHolidaysTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-24 10:00:00');
        config(['services.google_calendar.key' => null]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function admin(): User
    {
        return User::firstOrCreate(
            ['username' => 'admin.nocustom'],
            ['name' => 'Admin', 'password' => Hash::make('secret123'), 'role' => User::ROLE_ADMIN, 'is_active' => true]
        );
    }

    public function test_the_tab_offers_no_way_to_add_one(): void
    {
        $this->actingAs($this->admin())
             ->get(route('settings.index', ['tab' => 'holiday']))
             ->assertOk()
             ->assertDontSee('Add Custom')
             ->assertDontSee('Custom Holiday')
             ->assertDontSee('addHolidayModal')
             ->assertSee('Sync Google');

        $this->actingAs($this->admin())->post('/holidays', ['date' => '2026-10-15', 'title' => 'Company Day']);

        $this->assertSame(0, Holiday::count());
    }

    public function test_a_custom_holiday_from_before_neither_shows_nor_counts(): void
    {
        Holiday::create(['date' => '2026-10-15', 'title' => 'Company Day', 'type' => 'custom', 'is_official' => false, 'is_active' => true]);

        $this->assertNotContains('2026-10-15', array_column(Holiday::calendarFor(2026), 'date'));
        $this->assertArrayNotHasKey('2026-10-15', Holiday::typeMap());

        // The official days around it are untouched.
        $this->assertSame('regular', Holiday::typeMap()['2026-11-30']);
    }

    public function test_only_a_holiday_can_be_toggled(): void
    {
        $this->actingAs($this->admin())
             ->postJson(route('holidays.toggle'), ['date' => '2026-10-15'])
             ->assertStatus(422)
             ->assertJson(['success' => false]);

        $this->assertSame(0, Holiday::count());

        $this->actingAs($this->admin())
             ->postJson(route('holidays.toggle'), ['date' => '2026-11-30'])
             ->assertOk()
             ->assertJson(['is_active' => false]);
    }
}
