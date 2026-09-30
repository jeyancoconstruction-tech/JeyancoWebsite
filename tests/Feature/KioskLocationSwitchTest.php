<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\Kiosk;
use App\Models\LaborType;
use App\Models\Shift;
use App\Models\Site;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * System Settings → Kiosks → "Reject scans out of range" (Michael,
 * 2026-09-30). On, which is how it has always been, a kiosk that cannot
 * place itself inside its site's radius refuses the scan. Off, for when the
 * kiosk's location is broken, the crew can still scan in. The kiosk still
 * records only its own site's workers either way.
 */
class KioskLocationSwitchTest extends TestCase
{
    use RefreshDatabase;

    private const HOME = ['lat' => 13.6240, 'lng' => 123.1850];
    private const FAR  = ['lat' => 13.6300, 'lng' => 123.1850];   // about 670 m north

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-09-28 08:05:00', 'Asia/Manila'));
        config(['kiosk.enforce_location' => true]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function admin(): User
    {
        return User::create(['name' => 'Site Admin', 'username' => 'site.admin', 'password' => 'secret123',
            'role' => User::ROLE_ADMIN, 'is_admin' => true, 'is_active' => true]);
    }

    private function site(string $name = 'Site Alpha', bool $pinned = true): Site
    {
        return Site::create($pinned
            ? ['name' => $name, 'latitude' => self::HOME['lat'], 'longitude' => self::HOME['lng'], 'geofence_radius' => 150]
            : ['name' => $name]);
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

    private function kioskAt(Site $site, ?array $fix): Kiosk
    {
        $kiosk = Kiosk::updateOrCreate(['code' => 'SITE_A'], ['name' => 'Site A Kiosk', 'site_id' => $site->id, 'is_active' => true]);
        Cache::forget('kiosk_location_' . $kiosk->id);
        if ($fix) {
            Cache::put('kiosk_location_' . $kiosk->id, ['lat' => $fix['lat'], 'lng' => $fix['lng'], 'status' => 'fix',
                'last_seen' => now()->subSeconds(5)->toIso8601String()], now()->addDay());
        }

        return $kiosk;
    }

    private function timeIn(Employee $e): array
    {
        return $this->postJson('/api/kiosk/attendance', ['employee_id' => $e->id, 'type' => 'time_in', 'kiosk_code' => 'SITE_A'])->json();
    }

    private function switchOff(): void
    {
        (SystemSetting::first() ?? new SystemSetting(SystemSetting::DEFAULTS))->forceFill(['kiosk_location_check' => false])->save();
        SystemSetting::forget();
    }

    // ── On: as it has always been ────────────────────────────────────────

    public function test_it_is_on_unless_somebody_turns_it_off(): void
    {
        $this->assertTrue(SystemSetting::current()->checksKioskLocation());

        $a = $this->site();
        $this->kioskAt($a, self::FAR);

        $this->assertSame('outside_location', $this->timeIn($this->worker($a))['code']);
        $this->assertSame(0, Attendance::count());
    }

    // ── Off: the crew can still scan in ──────────────────────────────────

    public function test_off_a_kiosk_outside_the_radius_records_the_scan(): void
    {
        $this->switchOff();
        $a = $this->site();
        $this->kioskAt($a, self::FAR);

        $this->assertTrue($this->timeIn($this->worker($a))['success']);
        $this->assertSame(1, Attendance::count());
    }

    public function test_off_a_kiosk_with_no_gps_records_the_scan(): void
    {
        $this->switchOff();
        $a = $this->site();
        $this->kioskAt($a, null);

        $this->assertTrue($this->timeIn($this->worker($a))['success']);
    }

    public function test_off_a_site_with_no_location_records_the_scan(): void
    {
        $this->switchOff();
        $a = $this->site('Site Gamma', pinned: false);
        $this->kioskAt($a, self::FAR);

        $this->assertTrue($this->timeIn($this->worker($a))['success']);
    }

    /** The scan offers TIME IN instead of turning the worker away, and a clock records. */
    public function test_off_the_fingerprint_scan_and_clock_go_through_too(): void
    {
        $this->switchOff();
        $a = $this->site();
        $this->kioskAt($a, self::FAR);
        $this->worker($a);

        $this->postJson('/api/kiosk/scan-attendance', ['fingerprint_id' => '7', 'kiosk_code' => 'SITE_A'])
            ->assertJsonPath('success', true)->assertJsonPath('type', 'time_in');

        $this->postJson('/api/kiosk/clock', ['fingerprint_id' => '7', 'type' => 'time_in', 'kiosk_code' => 'SITE_A'])
            ->assertJsonPath('success', true);
        $this->assertSame(1, Attendance::count());
    }

    public function test_off_a_worker_from_another_site_is_still_refused(): void
    {
        $this->switchOff();
        $a = $this->site();
        $b = $this->site('Site Beta');
        $this->kioskAt($a, self::FAR);

        $this->assertSame('wrong_site', $this->timeIn($this->worker($b))['code']);
    }

    // ── The switch in System Settings ────────────────────────────────────

    public function test_the_office_turns_it_off_in_system_settings(): void
    {
        $this->kioskAt($this->site(), self::HOME);

        $this->actingAs($this->admin())->get(route('system-settings.kiosk'))->assertOk()
            ->assertSee('Reject scans out of range')
            ->assertSee('name="kiosk_location_check" value="1" checked', false);

        $this->put(route('system-settings.kiosk.update'), [
            'kiosk_attendance_mode' => 'buttons', 'kiosk_idle_return_seconds' => 60, 'kiosk_location_check' => '0',
        ])->assertSessionHasNoErrors();

        SystemSetting::forget();
        $this->assertFalse(SystemSetting::current()->checksKioskLocation());
        $this->assertTrue(AuditLog::where('description', 'like', '%reject scans out of range on → off%')->exists());

        // Each kiosk's card says the check is off instead of "refusing scans".
        $this->get(route('system-settings.kiosk'))
            ->assertSee('Location check is off')
            ->assertDontSee('data-ks-check="1"', false);
    }

    public function test_a_save_without_the_switch_leaves_it_as_it_was(): void
    {
        $this->switchOff();

        $this->actingAs($this->admin())->put(route('system-settings.kiosk.update'), [
            'kiosk_attendance_mode' => 'buttons', 'kiosk_idle_return_seconds' => 60,
        ])->assertSessionHasNoErrors();

        SystemSetting::forget();
        $this->assertFalse(SystemSetting::current()->checksKioskLocation());
    }
}
