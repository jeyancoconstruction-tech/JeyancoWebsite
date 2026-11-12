<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Complaint;
use App\Models\Employee;
use App\Models\EmployeeFingerprint;
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
 * The panel's recommendations of 2026-10-18 for the kiosk: real-time
 * attendance only, several fingers per worker, the worker's own DTR and
 * complaints at the kiosk, and the kiosk screen hosted on the web.
 */
class KioskRealtimeRscTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'test-device-key';
    private const HOME = ['lat' => 13.6240, 'lng' => 123.1850];

    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-19 08:05:00', 'Asia/Manila'));

        $this->site = Site::create(['name' => 'Site Alpha', 'latitude' => self::HOME['lat'], 'longitude' => self::HOME['lng'], 'geofence_radius' => 150]);
        $kiosk = Kiosk::updateOrCreate(['code' => 'SITE_A'], ['name' => 'Site A Kiosk', 'site_id' => $this->site->id, 'is_active' => true]);
        Cache::put('kiosk_location_' . $kiosk->id, ['lat' => self::HOME['lat'], 'lng' => self::HOME['lng'], 'status' => 'fix',
            'last_seen' => now()->subSeconds(5)->toIso8601String()], now()->addDay());
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function worker(string $fp = '7'): Employee
    {
        return Employee::create([
            'name' => 'Ana Villanueva', 'status' => Employee::STATUS_ACTIVE, 'employment_type' => Employee::EMPLOYMENT_DAILY,
            'site_id' => $this->site->id, 'fingerprint_id' => $fp, 'rate_per_hour' => 100,
            'labor_type_id' => LaborType::create(['name' => 'Mason', 'daily_rate' => 800])->id,
            'shift_id' => Shift::orderBy('id')->firstOrFail()->id,
        ]);
    }

    private function pi(): array
    {
        return ['X-Kiosk-Key' => self::KEY];
    }

    private function scan(string $fp): array
    {
        return $this->postJson('/api/kiosk/scan-attendance', ['fingerprint_id' => $fp, 'kiosk_code' => 'SITE_A'], $this->pi())->json();
    }

    // ── Real-time attendance only ────────────────────────────────────────

    public function test_with_a_device_key_only_a_pi_can_report_a_scan(): void
    {
        config(['kiosk.device_key' => self::KEY]);
        $this->worker();

        $this->postJson('/api/kiosk/scan-attendance', ['fingerprint_id' => '7', 'kiosk_code' => 'SITE_A'])
            ->assertStatus(401)->assertJsonPath('code', 'device_key');
        $this->getJson('/api/kiosk/active-fingerprints')->assertStatus(401);
        $this->getJson('/api/kiosk/my-payroll/7')->assertStatus(401);

        $this->assertNotEmpty($this->scan('7')['scan_token']);
    }

    public function test_attendance_needs_a_fresh_scan_of_the_same_worker(): void
    {
        config(['kiosk.device_key' => self::KEY]);
        $ana = $this->worker();

        // Typed by hand with an employee id: nothing is recorded.
        $this->postJson('/api/kiosk/attendance', ['employee_id' => $ana->id, 'type' => 'time_in', 'kiosk_code' => 'SITE_A'])
            ->assertJsonPath('success', false)->assertJsonPath('code', 'stale_scan');
        $this->assertSame(0, Attendance::count());

        // Somebody else's scan does not open Ana's day either.
        $ben = Employee::create(['name' => 'Ben Cruz', 'status' => Employee::STATUS_ACTIVE, 'site_id' => $this->site->id,
            'fingerprint_id' => '8', 'rate_per_hour' => 100, 'shift_id' => $ana->shift_id]);
        $bens = $this->scan('8')['scan_token'];
        $this->postJson('/api/kiosk/attendance', ['employee_id' => $ana->id, 'type' => 'time_in', 'kiosk_code' => 'SITE_A', 'scan_token' => $bens])
            ->assertJsonPath('code', 'stale_scan');

        // Her own scan does — once.
        $token = $this->scan('7')['scan_token'];
        $this->postJson('/api/kiosk/attendance', ['employee_id' => $ana->id, 'type' => 'time_in', 'kiosk_code' => 'SITE_A', 'scan_token' => $token])
            ->assertJsonPath('success', true);
        $this->assertSame(1, Attendance::count());
        $this->assertSame('2026-10-19 08:05:00', Carbon::parse(Attendance::sole()->time_in)->format('Y-m-d H:i:s'), 'the server\'s time');

        $this->postJson('/api/kiosk/attendance', ['employee_id' => $ana->id, 'type' => 'time_out', 'kiosk_code' => 'SITE_A', 'scan_token' => $token])
            ->assertJsonPath('code', 'stale_scan');
    }

    public function test_a_scan_token_expires(): void
    {
        config(['kiosk.device_key' => self::KEY, 'kiosk.scan_token_ttl' => 60]);
        $ana = $this->worker();
        $token = $this->scan('7')['scan_token'];

        Carbon::setTestNow(now()->addSeconds(61));

        $this->postJson('/api/kiosk/attendance', ['employee_id' => $ana->id, 'type' => 'time_in', 'kiosk_code' => 'SITE_A', 'scan_token' => $token])
            ->assertJsonPath('code', 'stale_scan');
    }

    public function test_without_a_device_key_the_kiosk_works_as_before(): void
    {
        config(['kiosk.device_key' => '']);
        $ana = $this->worker();

        $this->postJson('/api/kiosk/attendance', ['employee_id' => $ana->id, 'type' => 'time_in', 'kiosk_code' => 'SITE_A'])
            ->assertJsonPath('success', true);
    }

    // ── Several fingers per worker ───────────────────────────────────────

    public function test_a_second_finger_is_kept_beside_the_first_and_clocks_in(): void
    {
        $ana = $this->worker('7');

        $this->postJson('/api/kiosk/save-fingerprint', ['employee_id' => $ana->id, 'fingerprint_id' => '21'])
            ->assertJsonPath('success', true)->assertJsonPath('fingers', 2)->assertJsonPath('complete', true);

        $this->assertSame('7', $ana->fresh()->fingerprint_id, 'the first finger is never replaced');
        $this->assertSame(['7', '21'], $ana->fresh()->fingerprintSlots());

        $this->postJson('/api/kiosk/scan-attendance', ['fingerprint_id' => '21', 'kiosk_code' => 'SITE_A'])
            ->assertJsonPath('success', true)->assertJsonPath('employee.id', $ana->id);

        $ids = $this->getJson('/api/kiosk/active-fingerprints')->json('fingerprint_ids');
        $this->assertContains(7, $ids);
        $this->assertContains(21, $ids, 'the sync must not delete the second finger');
    }

    /**
     * 2026-11-12: one finger is enough to work. The kiosk lists a worker under
     * ENROLL for their first finger, and for a second only once the office
     * has asked for it from the worker's profile.
     */
    public function test_one_finger_is_enough_until_the_office_asks_for_a_second(): void
    {
        config(['kiosk.fingers_required' => 2]);
        $ana  = $this->worker('7');
        $none = Employee::create(['name' => 'Ben Cruz', 'status' => Employee::STATUS_PENDING,
            'site_id' => $this->site->id, 'rate_per_hour' => 100, 'shift_id' => $ana->shift_id]);
        $rows = fn () => collect($this->getJson('/api/kiosk/roster?kiosk_code=SITE_A')->json('employees'))->keyBy('id');

        $this->assertTrue($rows()[$ana->id]['complete'], 'one finger: off the list');
        $this->assertSame(1, $rows()[$ana->id]['fingers_required']);
        $this->assertFalse($rows()[$none->id]['complete'], 'no finger at all: listed for the first');

        $ana->forceFill(['extra_finger_requested_at' => now()])->save();
        $this->assertFalse($rows()[$ana->id]['complete'], 'asked for a second: listed');
        $this->assertSame(2, $rows()[$ana->id]['fingers_required']);

        $this->postJson('/api/kiosk/save-fingerprint', ['employee_id' => $ana->id, 'fingerprint_id' => '31'])
            ->assertJsonPath('fingers', 2)->assertJsonPath('complete', true);

        $this->assertNull($ana->fresh()->extra_finger_requested_at, 'the request is answered');
        $this->assertTrue($rows()[$ana->id]['complete'], 'two fingers: off the list');
    }

    /** The web shows "1 of 2" and asks the kiosk for the second from the profile. */
    public function test_the_office_asks_for_a_second_finger_from_the_profile(): void
    {
        config(['kiosk.fingers_required' => 2]);
        $ana   = $this->worker('7');
        $admin = User::create(['name' => 'Admin', 'username' => 'admin.fingers', 'password' => 'secret123',
            'role' => User::ROLE_ADMIN, 'is_admin' => true, 'is_active' => true]);

        $this->actingAs($admin)->get(route('employees.show', $ana->id))->assertOk()
            ->assertSee('1 of 2')
            ->assertSee(route('employees.finger-request', $ana->id), false)
            ->assertSee('Add second fingerprint');

        $this->actingAs($admin)->post(route('employees.finger-request', $ana->id))->assertSessionHas('success');
        $this->assertTrue($ana->fresh()->extraFingerRequested());

        $this->actingAs($admin)->get(route('employees.show', $ana->id))->assertOk()
            ->assertSee('Waiting at the kiosk')
            ->assertDontSee('Add second fingerprint');

        $this->actingAs($admin)->delete(route('employees.finger-request.cancel', $ana->id))->assertSessionHas('success');
        $this->assertFalse($ana->fresh()->extraFingerRequested());

        // Nothing to ask with no finger yet, or with the number already on file.
        EmployeeFingerprint::create(['employee_id' => $ana->id, 'slot' => '22']);
        $this->actingAs($admin)->post(route('employees.finger-request', $ana->id))->assertSessionHas('error');
        $this->assertFalse($ana->fresh()->extraFingerRequested());
    }

    public function test_a_finger_of_another_worker_is_refused(): void
    {
        $ana = $this->worker('7');
        $ben = Employee::create(['name' => 'Ben Cruz', 'status' => Employee::STATUS_ACTIVE, 'site_id' => $this->site->id,
            'fingerprint_id' => '8', 'rate_per_hour' => 100, 'shift_id' => $ana->shift_id]);
        EmployeeFingerprint::create(['employee_id' => $ben->id, 'slot' => '30']);

        $this->postJson('/api/kiosk/save-fingerprint', ['employee_id' => $ana->id, 'fingerprint_id' => '30'])
            ->assertJsonPath('success', false);
        $this->assertSame(['7'], $ana->fresh()->fingerprintSlots());
    }

    public function test_no_more_fingers_than_the_most_allowed(): void
    {
        config(['kiosk.fingers_required' => 2, 'kiosk.fingers_max' => 2]);
        $ana = $this->worker('7');
        EmployeeFingerprint::create(['employee_id' => $ana->id, 'slot' => '22']);

        $this->postJson('/api/kiosk/save-fingerprint', ['employee_id' => $ana->id, 'fingerprint_id' => '23'])
            ->assertJsonPath('code', 'fingers_full');
    }

    // ── The worker's own DTR and complaints ──────────────────────────────

    public function test_the_dtr_opens_only_with_a_scan(): void
    {
        config(['kiosk.device_key' => self::KEY]);
        $this->worker();

        $this->getJson('/api/kiosk/my-dtr?scan_token=' . str_repeat('x', 40), $this->pi())->assertJsonPath('code', 'stale_scan');

        $token = $this->scan('7')['scan_token'];
        $this->getJson('/api/kiosk/my-dtr?scan_token=' . $token, $this->pi())
            ->assertJsonPath('success', true)
            ->assertJsonPath('employee.name', 'Ana Villanueva')
            ->assertJsonCount(7, 'days');
    }

    public function test_a_complaint_reaches_the_office_and_its_answer_reaches_the_worker(): void
    {
        config(['kiosk.device_key' => self::KEY]);
        $ana = $this->worker();
        $admin = User::create(['name' => 'Admin', 'username' => 'admin.rsc', 'password' => 'secret123',
            'role' => User::ROLE_ADMIN, 'is_admin' => true, 'is_active' => true]);

        $token = $this->scan('7')['scan_token'];
        $this->postJson('/api/kiosk/complaints', ['scan_token' => $token, 'category' => 'attendance',
            'message' => 'My time in is missing', 'kiosk_code' => 'SITE_A'], $this->pi())
            ->assertJsonPath('success', true);

        $c = Complaint::sole();
        $this->assertSame($ana->id, $c->employee_id);
        $this->assertSame($this->site->id, $c->site_id);
        $this->assertSame(1, $admin->notifications()->count());

        $this->actingAs($admin)->get(route('complaints.index'))->assertOk()->assertSee('My time in is missing');
        $this->actingAs($admin)->patch(route('complaints.update', $c), ['action' => 'resolve', 'response' => 'Added, thanks.'])
            ->assertRedirect();

        $this->getJson('/api/kiosk/complaints?scan_token=' . $token, $this->pi())
            ->assertJsonPath('complaints.0.status', 'resolved')
            ->assertJsonPath('complaints.0.response', 'Added, thanks.');
    }

    // ── Device Monitoring follows the kiosk's taps ───────────────────────

    public function test_device_monitoring_sees_the_tab_the_kiosk_is_on(): void
    {
        $admin = User::create(['name' => 'Admin', 'username' => 'admin.view', 'password' => 'secret123',
            'role' => User::ROLE_ADMIN, 'is_admin' => true, 'is_active' => true]);
        $kiosk = Kiosk::where('code', 'SITE_A')->firstOrFail();

        $this->getJson('/api/kiosk/settings?kiosk_code=SITE_A&tab=dtr&view=' . urlencode(json_encode(['tab' => 'dtr', 'who' => 'Ana Villanueva', 'week' => 1])))
            ->assertOk();

        $this->actingAs($admin)->getJson(route('devices.live', ['kiosk' => $kiosk->id, 'light' => 1]))
            ->assertJsonPath('screen.view.tab', 'dtr')
            ->assertJsonPath('screen.view.who', 'Ana Villanueva')
            ->assertJsonPath('screen.view.week', 1)
            ->assertJsonPath('screen.on', true);

        // A minute later it is no longer "now".
        Carbon::setTestNow(now()->addSeconds(70));
        $this->actingAs($admin)->getJson(route('devices.live', ['kiosk' => $kiosk->id, 'light' => 1]))
            ->assertJsonPath('screen.view', null);
    }

    // ── The kiosk screen, online ─────────────────────────────────────────

    public function test_the_kiosk_screen_is_served_without_signing_in_and_carries_the_server_clock(): void
    {
        $this->get('/kiosk')
            ->assertOk()
            ->assertSee('kiosk-app/', false)
            ->assertSee('"server_time":' . now()->getTimestampMs(), false)
            ->assertSee('id="tab-dtr"', false)
            ->assertSee('id="tab-report"', false)
            ->assertDontSee('id="tab-summary"', false);
    }
}
