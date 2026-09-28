<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Kiosk;
use App\Models\LaborType;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * System Settings → Kiosks shows what each kiosk's screen is showing
 * (2026-09-27): the "who is on site" board and the roster the kiosk reads
 * from us, and the last scan. A kiosk that has stopped its heartbeat is off,
 * and the monitor says only that. It reads; it never records a scan and never
 * counts as the kiosk checking in for its settings.
 */
class KioskMonitorTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Monitor Admin', 'username' => 'monitor.admin', 'password' => 'secret123',
            'role' => User::ROLE_ADMIN, 'is_admin' => true, 'is_active' => true,
        ]);
    }

    private function kiosk(string $code, ?int $secondsAgo): Kiosk
    {
        $site  = Site::create(['name' => 'Site ' . $code]);
        // The migrations already put a Site A kiosk in; it is taken over.
        $kiosk = Kiosk::updateOrCreate(['code' => $code], ['name' => $code . ' Kiosk', 'site_id' => $site->id, 'is_active' => true]);

        if ($secondsAgo !== null) {
            Cache::put('kiosk_location_' . $kiosk->id, [
                'last_seen' => now()->subSeconds($secondsAgo)->toIso8601String(), 'lat' => 13.6, 'lng' => 123.2, 'status' => 'fix',
            ], now()->addHour());
        }

        return $kiosk;
    }

    public function test_an_online_kiosk_shows_its_board_roster_and_last_scan(): void
    {
        $kiosk = $this->kiosk('SITE_A', 20);
        $emp = Employee::create([
            'name' => 'Ana Villanueva', 'status' => Employee::STATUS_ACTIVE, 'site_id' => $kiosk->site_id,
            'labor_type_id' => LaborType::create(['name' => 'Mason', 'daily_rate' => 800])->id, 'rate_per_hour' => 100,
        ]);
        Attendance::create(['employee_id' => $emp->id, 'date' => today()->toDateString(), 'time_in' => now()->subHour(),
            'site_id' => $kiosk->site_id, 'kiosk_id' => $kiosk->id]);

        $json = $this->actingAs($this->admin())
            ->getJson(route('devices.live', ['kiosk' => $kiosk->id]))
            ->assertOk()
            ->assertJsonPath('screen.on', true)
            ->assertJsonPath('screen.kiosk.state', 'ok')
            ->assertJsonPath('screen.last.name', 'Ana Villanueva')
            ->assertJsonPath('screen.last.type', 'in')
            ->json();

        $this->assertSame('Ana Villanueva', $json['screen']['board']['records'][0]['name']);
        $this->assertSame(1, $json['screen']['roster']['counts']['total']);
        $this->assertNull($kiosk->fresh()->settingsReadAt(), 'the monitor is not the kiosk checking in');
    }

    public function test_a_silent_kiosk_shows_only_that_it_is_off(): void
    {
        $this->kiosk('SITE_A', 20);
        $off = $this->kiosk('SITE_B', 3600);

        $this->actingAs($this->admin())
            ->getJson(route('devices.live', ['kiosk' => $off->id]))
            ->assertOk()
            ->assertJsonPath('screen.on', false)
            ->assertJsonPath('screen.kiosk.state', 'off')
            ->assertJsonMissingPath('screen.board')
            ->assertJsonPath('kiosks.1.state', 'off');
    }

    public function test_a_kiosk_that_never_reported_is_off(): void
    {
        $never = $this->kiosk('SITE_C', null);

        $this->actingAs($this->admin())
            ->getJson(route('devices.live'))
            ->assertOk()
            ->assertJsonPath('screen.kiosk.id', $never->id)
            ->assertJsonPath('screen.on', false)
            ->assertJsonPath('screen.kiosk.seen', null);
    }

    public function test_device_monitoring_holds_the_console(): void
    {
        $this->kiosk('SITE_A', 20);

        $this->actingAs($this->admin())->get(route('devices.index'))
            ->assertOk()
            ->assertSee('id="dvConsole"', false)
            ->assertSee(route('devices.live'), false)
            ->assertSee('id="dvMap"', false)
            ->assertSee('SITE_A Kiosk');
    }

    public function test_the_kiosks_section_says_which_kiosks_are_on_and_links_to_the_console(): void
    {
        $this->kiosk('SITE_A', 20);

        $this->actingAs($this->admin())->get(route('system-settings.kiosk'))
            ->assertOk()
            ->assertSee('Kiosks now')
            ->assertSee('SITE_A Kiosk')
            ->assertSee(route('devices.index') . '#dvConsole', false)
            ->assertDontSee('id="kioskMonitor"', false);
    }

    /** The map is measured on the server: the kiosk against its site's pin. */
    public function test_the_map_measures_the_kiosk_against_its_sites_geofence(): void
    {
        $kiosk = $this->kiosk('SITE_A', 10);
        $kiosk->site->update(['latitude' => 13.6, 'longitude' => 123.2, 'geofence_radius' => 150]);
        $far = $this->kiosk('SITE_B', 10);
        $far->site->update(['latitude' => 13.61, 'longitude' => 123.2, 'geofence_radius' => 150]);

        $near = $this->actingAs($this->admin())->getJson(route('devices.live', ['kiosk' => $kiosk->id, 'light' => 1]))
            ->assertOk()
            ->assertJsonPath('screen.kiosk.map.gps', 'fix')
            ->assertJsonPath('screen.kiosk.map.site.radius', 150)
            ->assertJsonPath('screen.kiosk.map.inside', true)
            ->json('screen.kiosk.map');
        $this->assertEquals(0, $near['distance']);

        $out = $this->getJson(route('devices.live', ['kiosk' => $far->id, 'light' => 1]))
            ->assertJsonPath('screen.kiosk.map.inside', false)
            ->json('screen.kiosk.map.distance');
        $this->assertEqualsWithDelta(1112, $out, 5, '0.01° of latitude is about 1.1 km');
    }

    public function test_hr_can_watch_the_kiosks_too(): void
    {
        $kiosk = $this->kiosk('SITE_A', 5);
        $hr = User::create([
            'name' => 'Monitor HR', 'username' => 'monitor.hr', 'password' => 'secret123',
            'role' => User::ROLE_HR, 'is_active' => true,
        ]);

        $this->actingAs($hr)->getJson(route('devices.live'))->assertOk();
        $this->actingAs($hr)->get(route('devices.screen', $kiosk))->assertOk();
        $this->actingAs($hr)->getJson(route('devices.screen-api', [$kiosk, 'roster']))->assertOk();
    }

    /**
     * Live: every scan the kiosk sends is noted as the web answers it, and the
     * monitor's quick poll hands it over — a time in, and a finger nobody knows.
     */
    public function test_scans_reach_the_monitor_as_they_happen(): void
    {
        $kiosk = $this->kiosk('SITE_A', 5);
        $emp = Employee::create([
            'name' => 'Ben Live', 'status' => Employee::STATUS_ACTIVE, 'site_id' => $kiosk->site_id,
            'labor_type_id' => LaborType::create(['name' => 'Mason', 'daily_rate' => 800])->id, 'rate_per_hour' => 100,
        ]);
        $admin = $this->admin();

        // Opening: only the number to start from, not what came before.
        $start = $this->actingAs($admin)->getJson(route('devices.live', ['kiosk' => $kiosk->id, 'since' => -1, 'light' => 1]))
            ->assertOk()->assertJsonCount(0, 'events')->json('seq');

        // The kiosk records a time in, exactly as the Pi posts it.
        $answer = $this->postJson('/api/kiosk/attendance', ['employee_id' => $emp->id, 'type' => 'time_in', 'kiosk_code' => 'SITE_A'])->json();
        $this->postJson('/api/kiosk/scan-attendance', ['fingerprint_id' => '999', 'kiosk_code' => 'SITE_A'])
            ->assertJson(['not_found' => true]);

        $events = $this->actingAs($admin)->getJson(route('devices.live', ['kiosk' => $kiosk->id, 'since' => $start, 'light' => 1]))
            ->assertOk()->json('events');

        $this->assertCount(2, $events);
        $this->assertSame($answer['success'] ? 'in' : 'rej', $events[0]['kind']);
        $this->assertSame('Ben Live', $events[0]['name']);
        $this->assertSame('unknown', $events[1]['kind']);

        // Asking again from the newest number: nothing new.
        $this->actingAs($admin)->getJson(route('devices.live', ['kiosk' => $kiosk->id, 'since' => $events[1]['seq'], 'light' => 1]))
            ->assertJsonCount(0, 'events');
    }

    /** Noting the scan never changes what the kiosk is told. */
    public function test_the_kiosk_gets_the_same_answer_with_the_monitor_listening(): void
    {
        $this->kiosk('SITE_A', 5);

        $this->postJson('/api/kiosk/scan-attendance', ['fingerprint_id' => '404', 'kiosk_code' => 'SITE_A'])
            ->assertOk()
            ->assertExactJsonStructure(array_keys($this->postJson('/api/kiosk/scan-attendance', ['fingerprint_id' => '404'])->json()));
    }

    /**
     * The monitor shows the kiosk's own screen: its v8 files, unchanged, with
     * kiosk-monitor.js standing in for the Pi, reading from this system.
     */
    public function test_the_kiosk_screen_runs_the_kiosks_own_files(): void
    {
        $kiosk = $this->kiosk('SITE_A', 5);

        $page = $this->actingAs($this->admin())->get(route('devices.screen', $kiosk))->assertOk();
        foreach (['kiosk-monitor.js', 'script.js', 'kiosk-clock.js', 'kiosk-summary.js', 'kiosk-screen.css', 'id="att-panel"', 'id="tab-summary"'] as $bit) {
            $page->assertSee($bit, false);
        }
        foreach (['script.js', 'kiosk-clock.js', 'kiosk-summary.js', 'style.css', 'kiosk-sites.css', 'kiosk-v3.css', 'kiosk-screen.css'] as $file) {
            $this->assertFileExists(public_path('kiosk-screen/' . $file));
        }
    }

    public function test_the_kiosk_screen_reads_this_kiosks_data_without_checking_in(): void
    {
        $kiosk = $this->kiosk('SITE_A', 5);
        $admin = $this->admin();

        $this->actingAs($admin)->getJson(route('devices.screen-api', [$kiosk, 'sites']))
            ->assertOk()->assertJsonPath('kiosk_code', 'SITE_A')->assertJsonPath('active.id', $kiosk->site_id);

        $v = $this->actingAs($admin)->getJson(route('devices.screen-api', [$kiosk, 'settings']))
            ->assertOk()->assertJsonStructure(['success', 'v', 'attendance' => ['mode', 'shifts']])->json('v');
        $this->actingAs($admin)->getJson(route('devices.screen-api', [$kiosk, 'settings']) . '?v=' . $v)
            ->assertJson(['same' => true]);

        $this->actingAs($admin)->getJson(route('devices.screen-api', [$kiosk, 'today-attendance']))->assertOk()->assertJsonStructure(['records']);
        $this->actingAs($admin)->getJson(route('devices.screen-api', [$kiosk, 'roster']))->assertOk()->assertJsonStructure(['employees']);

        $this->assertNull($kiosk->fresh()->settingsReadAt(), 'the monitor is not the kiosk checking in');

        // The kiosk itself still is.
        $this->getJson('/api/kiosk/settings?kiosk_code=SITE_A')->assertOk();
        $this->assertNotNull($kiosk->fresh()->settingsReadAt());
    }

    public function test_a_guest_cannot_see_the_kiosk_screen(): void
    {
        $kiosk = $this->kiosk('SITE_A', 5);

        $this->get(route('devices.screen', $kiosk))->assertRedirect();
        $this->getJson(route('devices.screen-api', [$kiosk, 'roster']))->assertUnauthorized();
    }

    public function test_a_guest_cannot_read_the_monitor(): void
    {
        $this->getJson(route('devices.live'))->assertUnauthorized();
    }
}
