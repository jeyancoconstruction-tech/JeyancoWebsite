<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\Kiosk;
use App\Models\LaborType;
use App\Models\Shift;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * 2026-09-28: the office sets where the kiosk stands (System Settings →
 * Kiosks → Kiosk site), the kiosk records only that site's workers, and only
 * while its current — or last known — position is inside the site's radius.
 */
class KioskSiteFromWebTest extends TestCase
{
    use RefreshDatabase;

    private const HOME = ['lat' => 13.6240, 'lng' => 123.1850];

    private function admin(): User
    {
        return User::create(['name' => 'Site Admin', 'username' => 'site.admin', 'password' => 'secret123',
            'role' => User::ROLE_ADMIN, 'is_admin' => true, 'is_active' => true]);
    }

    private function pinned(string $name, float $lat = self::HOME['lat'], float $lng = self::HOME['lng']): Site
    {
        return Site::create(['name' => $name, 'latitude' => $lat, 'longitude' => $lng, 'geofence_radius' => 150]);
    }

    private function worker(?Site $site): Employee
    {
        return Employee::create([
            'name' => 'Ana Villanueva', 'status' => Employee::STATUS_ACTIVE, 'employment_type' => Employee::EMPLOYMENT_DAILY,
            'site_id' => $site?->id, 'fingerprint_id' => '7', 'rate_per_hour' => 100,
            'labor_type_id' => LaborType::create(['name' => 'Mason', 'daily_rate' => 800])->id,
            'shift_id' => Shift::orderBy('id')->firstOrFail()->id,
        ]);
    }

    private function kioskAt(Site $site, ?array $fix = self::HOME, string $status = 'fix', int $ago = 5): Kiosk
    {
        $kiosk = Kiosk::updateOrCreate(['code' => 'SITE_A'], ['name' => 'Site A Kiosk', 'site_id' => $site->id, 'is_active' => true]);
        Cache::forget('kiosk_location_' . $kiosk->id);
        if ($fix) {
            Cache::put('kiosk_location_' . $kiosk->id, ['lat' => $fix['lat'], 'lng' => $fix['lng'], 'status' => $status,
                'last_seen' => now()->subSeconds($ago)->toIso8601String()], now()->addDay());
        }

        return $kiosk;
    }

    private function timeIn(Employee $e): array
    {
        return $this->postJson('/api/kiosk/attendance', ['employee_id' => $e->id, 'type' => 'time_in', 'kiosk_code' => 'SITE_A'])->json();
    }

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-09-28 08:05:00', 'Asia/Manila'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_the_office_sets_the_kiosk_site_and_the_kiosk_is_told(): void
    {
        $a = $this->pinned('Site Alpha');
        $b = $this->pinned('Site Beta', 13.63, 123.20);
        $kiosk = $this->kioskAt($a);

        $this->actingAs($this->admin())
            ->patchJson(route('system-settings.kiosk.site', $kiosk), ['site_id' => $b->id])
            ->assertOk()->assertJsonPath('kiosk.site', 'Site Beta')->assertJsonPath('kiosk.site_id', $b->id);

        $this->assertSame($b->id, $kiosk->fresh()->site_id);
        $this->assertTrue(AuditLog::where('description', 'like', '%set to Site Beta (was Site Alpha)%')->exists());

        $this->getJson('/api/kiosk/settings?kiosk_code=SITE_A')
            ->assertJsonPath('site.id', $b->id)->assertJsonPath('site.slug', 'site-beta');
    }

    public function test_a_site_id_sent_by_the_kiosk_does_not_move_it(): void
    {
        $a = $this->pinned('Site Alpha');
        $b = $this->pinned('Site Beta');
        $kiosk = $this->kioskAt($a);
        $e = $this->worker($a);

        $this->postJson('/api/kiosk/attendance', ['employee_id' => $e->id, 'type' => 'time_in', 'kiosk_code' => 'SITE_A', 'site_id' => $b->id])
            ->assertJsonPath('success', true);

        $this->assertSame($a->id, $kiosk->fresh()->site_id);
        $this->assertSame($a->id, Attendance::sole()->site_id);
    }

    public function test_a_worker_of_this_site_is_recorded(): void
    {
        $a = $this->pinned('Site Alpha');
        $this->kioskAt($a);

        $this->assertTrue($this->timeIn($this->worker($a))['success']);
    }

    public function test_a_worker_of_another_site_is_refused_by_name(): void
    {
        $a = $this->pinned('Site Alpha');
        $b = $this->pinned('Site Beta');
        $this->kioskAt($a);
        $e = $this->worker($b);

        $answer = $this->timeIn($e);
        $this->assertSame('wrong_site', $answer['code']);
        $this->assertStringContainsString('assigned to Site Beta, not Site Alpha', $answer['message']);
        $this->assertSame(0, Attendance::count());

        // The scan itself says so, with the worker, before any button is pressed.
        $this->postJson('/api/kiosk/scan-attendance', ['fingerprint_id' => '7', 'kiosk_code' => 'SITE_A'])
            ->assertJsonPath('code', 'wrong_site')->assertJsonPath('employee.name', 'Ana Villanueva');
    }

    public function test_a_worker_with_no_site_is_refused(): void
    {
        $this->kioskAt($this->pinned('Site Alpha'));

        $this->assertSame('wrong_site', $this->timeIn($this->worker(null))['code']);
    }

    public function test_outside_the_radius_is_refused(): void
    {
        config(['kiosk.enforce_location' => true]);
        $a = $this->pinned('Site Alpha');
        $this->kioskAt($a, ['lat' => 13.6300, 'lng' => 123.1850]);   // about 670 m north

        $answer = $this->timeIn($this->worker($a));
        $this->assertSame('outside_location', $answer['code']);
        $this->assertFalse($answer['last_known']);
    }

    /** Moved to Site Beta with no signal: the last position is still Alpha's, so Beta refuses. */
    public function test_the_last_known_position_counts_when_there_is_no_fix(): void
    {
        config(['kiosk.enforce_location' => true]);
        $this->pinned('Site Alpha');
        $b = $this->pinned('Site Beta', 13.6400, 123.2000);
        $this->kioskAt($b, self::HOME, 'no_fix', 900);               // last fix: at Site Alpha, 15 min ago

        $answer = $this->timeIn($this->worker($b));
        $this->assertSame('outside_location', $answer['code']);
        $this->assertTrue($answer['last_known']);
        $this->assertStringContainsString('last known position', $answer['message']);
    }

    public function test_the_last_known_position_inside_the_radius_is_accepted(): void
    {
        config(['kiosk.enforce_location' => true]);
        $a = $this->pinned('Site Alpha');
        $this->kioskAt($a, self::HOME, 'no_fix', 900);

        $this->assertTrue($this->timeIn($this->worker($a))['success']);
    }

    public function test_a_kiosk_that_never_reported_a_position_is_refused(): void
    {
        config(['kiosk.enforce_location' => true]);
        $a = $this->pinned('Site Alpha');
        $this->kioskAt($a, null);

        $this->assertSame('no_gps', $this->timeIn($this->worker($a))['code']);
    }

    public function test_the_office_can_add_a_kiosk(): void
    {
        $b = $this->pinned('Site Beta');

        $this->actingAs($this->admin())
            ->post(route('system-settings.kiosk.store'), ['name' => 'Kiosk 2', 'code' => 'KIOSK_2', 'site_id' => $b->id])
            ->assertRedirect(route('system-settings.kiosk'));

        $this->assertSame($b->id, Kiosk::where('code', 'KIOSK_2')->value('site_id'));
    }

    public function test_the_kiosks_section_shows_the_site_switcher(): void
    {
        $this->kioskAt($this->pinned('Site Alpha'));

        $this->actingAs($this->admin())->get(route('system-settings.kiosk'))
            ->assertOk()->assertSee('Kiosk site')->assertSee('class="ks-site', false)->assertSee('Add kiosk');
    }

    public function test_hr_cannot_move_a_kiosk(): void
    {
        $a = $this->pinned('Site Alpha');
        $b = $this->pinned('Site Beta');
        $kiosk = $this->kioskAt($a);
        $hr = User::create(['name' => 'HR', 'username' => 'hr.one', 'password' => 'secret123', 'role' => User::ROLE_HR, 'is_active' => true]);

        $this->actingAs($hr)->patchJson(route('system-settings.kiosk.site', $kiosk), ['site_id' => $b->id]);
        $this->assertSame($a->id, $kiosk->fresh()->site_id);
    }

    public function test_an_extra_kiosk_can_be_removed_but_not_the_last(): void
    {
        $a = $this->pinned('Site Alpha');
        $only = $this->kioskAt($a);
        Kiosk::where('id', '!=', $only->id)->delete();
        $extra = Kiosk::create(['name' => 'Site B Kiosk', 'code' => 'SITE_B', 'site_id' => $a->id]);
        $admin = $this->admin();

        $this->actingAs($admin)->delete(route('system-settings.kiosk.destroy', $extra))->assertRedirect(route('system-settings.kiosk'));
        $this->assertNull(Kiosk::find($extra->id));

        $this->actingAs($admin)->delete(route('system-settings.kiosk.destroy', $only));
        $this->assertNotNull(Kiosk::find($only->id), 'the only kiosk stays');
    }
}
