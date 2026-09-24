<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Kiosk;
use App\Models\LaborType;
use App\Models\Shift;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A site is a name, a pin and how far from the pin still counts as on-site.
 *
 * One radius used to cover every site. A tower on a city block and a road job
 * strung along a kilometre do not fit the same circle, so the radius is set
 * per site on the Sites page — and the kiosk's GPS check, its site list and the
 * dashboard ring all read the site's own figure. A site never given one keeps
 * the office-wide KIOSK_GEOFENCE_RADIUS it always had.
 */
class SiteManagementTest extends TestCase
{
    use RefreshDatabase;

    /** Naga City Hall, and a point 200 m due north of it. */
    private const HALL  = ['lat' => 13.6218, 'lng' => 123.1948];
    private const NORTH = ['lat' => 13.6235966, 'lng' => 123.1948];

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin', 'username' => 'admin.sites', 'password' => 'secret123',
            'is_admin' => true, 'is_active' => true,
        ]);
    }

    private function site(array $attrs = []): Site
    {
        return Site::create($attrs + [
            'name'      => 'Tower 2',
            'location'  => 'Naga City Hall, J. Miranda Avenue, Naga',
            'latitude'  => self::HALL['lat'],
            'longitude' => self::HALL['lng'],
        ]);
    }

    // ── The page ─────────────────────────────────────────────────────────

    public function test_the_page_is_the_map_and_the_site_cards(): void
    {
        $html = $this->actingAs($this->admin())->get(route('sites.index'))->assertOk()->getContent();

        foreach (['id="smForm"', 'id="smMap"', 'id="smRadius"', 'id="smSites"', 'leaflet@1.9.4', 'photon.komoot.io'] as $part) {
            $this->assertStringContainsString($part, $html);
        }
        // The Google picker needed a key that was never set, and hid the map.
        $this->assertStringNotContainsString('site-location-picker.js', $html);
        $this->assertStringNotContainsString('maps.googleapis.com', $html);
    }

    // ── Saving a site ────────────────────────────────────────────────────

    public function test_a_site_is_added_with_its_pin_and_its_radius(): void
    {
        $this->actingAs($this->admin())
             ->postJson(route('sites.store'), [
                 'name' => 'Tower 2', 'location' => 'Naga City Hall',
                 'latitude' => self::HALL['lat'], 'longitude' => self::HALL['lng'],
                 'geofence_radius' => 300,
             ])
             ->assertOk()
             ->assertJsonPath('site.geofence_radius', 300);

        $this->assertSame(300, Site::where('name', 'Tower 2')->sole()->geofence_radius);
    }

    public function test_a_site_given_no_radius_uses_the_office_wide_one(): void
    {
        config(['kiosk.geofence_radius' => 175]);
        $admin = $this->admin();

        $this->actingAs($admin)
             ->postJson(route('sites.store'), ['name' => 'Tower 2', 'location' => 'Typed only'])
             ->assertOk()
             ->assertJsonPath('site.geofence_radius', 175);

        $this->assertNull(Site::where('name', 'Tower 2')->sole()->geofence_radius, 'nothing stored — it follows the setting');

        $row = collect($this->actingAs($admin)->getJson(route('sites.list'))->json('sites'))->firstWhere('name', 'Tower 2');
        $this->assertSame(175, $row['geofence_radius']);
    }

    public function test_the_radius_stays_inside_what_the_page_offers(): void
    {
        $admin = $this->admin();

        foreach ([Site::RADIUS_MIN - 1, Site::RADIUS_MAX + 1] as $radius) {
            $this->actingAs($admin)
                 ->postJson(route('sites.store'), [
                     'name' => "Tower {$radius}", 'location' => 'Naga',
                     'latitude' => self::HALL['lat'], 'longitude' => self::HALL['lng'],
                     'geofence_radius' => $radius,
                 ])
                 ->assertStatus(422)
                 ->assertJsonValidationErrors('geofence_radius');
        }
    }

    public function test_a_pin_is_a_pair(): void
    {
        $this->actingAs($this->admin())
             ->postJson(route('sites.store'), ['name' => 'Half', 'location' => 'Naga', 'latitude' => self::HALL['lat']])
             ->assertStatus(422)
             ->assertJsonValidationErrors('longitude');
    }

    // ── Editing a site ───────────────────────────────────────────────────

    public function test_editing_moves_the_pin_and_changes_the_radius(): void
    {
        $site = $this->site(['geofence_radius' => 150]);

        $this->actingAs($this->admin())
             ->putJson(route('sites.update', $site->id), [
                 'name' => 'Tower 2', 'location' => 'North gate',
                 'latitude' => self::NORTH['lat'], 'longitude' => self::NORTH['lng'],
                 'geofence_radius' => 400,
             ])
             ->assertOk()
             ->assertJsonPath('site.geofence_radius', 400);

        $site->refresh();
        $this->assertSame('North gate', $site->location);
        $this->assertEqualsWithDelta(self::NORTH['lat'], $site->latitude, 1e-6);
        $this->assertSame(400, $site->geofence_radius);
    }

    public function test_editing_can_take_the_pin_away(): void
    {
        $site = $this->site(['geofence_radius' => 300]);

        $this->actingAs($this->admin())
             ->putJson(route('sites.update', $site->id), [
                 'name' => 'Tower 2', 'location' => 'Somewhere in Pili', 'latitude' => null, 'longitude' => null,
             ])
             ->assertOk();

        $site->refresh();
        $this->assertFalse($site->isPinned());
        $this->assertSame('Somewhere in Pili', $site->location);
    }

    /** The dashboard map saves a pin and sends no radius; that is not "no radius". */
    public function test_saving_a_pin_from_the_dashboard_keeps_the_radius(): void
    {
        $site = $this->site(['geofence_radius' => 300]);

        $this->actingAs($this->admin())
             ->putJson(route('sites.update', $site->id), [
                 'name' => 'Tower 2', 'location' => 'Moved a little',
                 'latitude' => self::NORTH['lat'], 'longitude' => self::NORTH['lng'],
             ])
             ->assertOk();

        $this->assertSame(300, $site->refresh()->geofence_radius);
    }

    public function test_a_rename_alone_keeps_everything_else(): void
    {
        $site = $this->site(['geofence_radius' => 300]);

        $this->actingAs($this->admin())
             ->putJson(route('sites.update', $site->id), ['name' => 'Tower Two'])
             ->assertOk();

        $site->refresh();
        $this->assertSame('Tower Two', $site->name);
        $this->assertSame('Naga City Hall, J. Miranda Avenue, Naga', $site->location);
        $this->assertTrue($site->isPinned());
        $this->assertSame(300, $site->geofence_radius);
    }

    // ── What the kiosk does with it ──────────────────────────────────────

    private function clockInFromTheNorth(Site $site): array
    {
        config(['kiosk.enforce_location' => true]);

        Kiosk::create(['name' => 'Test Kiosk', 'code' => 'TEST_GEO', 'site_id' => $site->id]);
        $emp = Employee::create([
            'name'            => 'Juan Dela Cruz',
            'status'          => Employee::STATUS_ACTIVE,
            'employment_type' => Employee::EMPLOYMENT_DAILY,
            'labor_type_id'   => LaborType::create(['name' => 'Mason', 'daily_rate' => 800, 'ot_rate' => 125])->id,
            'shift_id'        => Shift::orderBy('id')->firstOrFail()->id,
            'rate_per_hour'   => 100,
        ]);

        return $this->postJson('/api/kiosk/attendance', [
            'employee_id' => $emp->id, 'type' => 'time_in', 'kiosk_code' => 'TEST_GEO',
            'lat' => self::NORTH['lat'], 'lng' => self::NORTH['lng'],
        ])->json();
    }

    public function test_the_kiosk_accepts_a_clock_inside_the_sites_own_radius(): void
    {
        // 200 m out: outside the office-wide 150 m, inside this site's 300 m.
        config(['kiosk.geofence_radius' => 150]);
        $answer = $this->clockInFromTheNorth($this->site(['geofence_radius' => 300]));

        $this->assertNotSame('outside_location', $answer['code'] ?? null, 'the site says 300 m, so 200 m is on site');
    }

    public function test_the_kiosk_refuses_a_clock_outside_the_sites_own_radius(): void
    {
        config(['kiosk.geofence_radius' => 500]);
        $answer = $this->clockInFromTheNorth($this->site(['geofence_radius' => 100]));

        $this->assertSame('outside_location', $answer['code'] ?? null);
        $this->assertStringContainsString('limit 100m', $answer['message']);
    }

    public function test_the_kiosk_site_list_carries_each_sites_radius(): void
    {
        config(['kiosk.geofence_radius' => 150]);
        $wide  = $this->site(['name' => 'Road Job', 'geofence_radius' => 450]);
        $plain = $this->site(['name' => 'Tower 3']);

        $sites = collect($this->getJson('/api/kiosk/sites')->assertOk()->json('sites'))->keyBy('id');

        $this->assertSame(450, $sites[$wide->id]['radius']);
        $this->assertSame(150, $sites[$plain->id]['radius']);
    }
}
