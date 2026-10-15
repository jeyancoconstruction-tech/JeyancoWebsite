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

    /**
     * The map shows every site and kiosk, as the dashboard's does (Michael,
     * 2026-10-03), with the very same layer and the very same data. It no
     * longer hides behind "Add a project name to open the map"; a tap still
     * places a pin only once the new site has a name.
     */
    public function test_the_map_shows_every_site_and_kiosk_like_the_dashboard(): void
    {
        $html = $this->actingAs($this->admin())->get(route('sites.index'))->assertOk()->getContent();

        $this->assertStringContainsString('js/site-map.js', $html);
        $this->assertStringContainsString('site-map.css', $html);
        $this->assertStringContainsString('JeyancoSiteMap(map', $html);
        $this->assertStringContainsString(json_encode(route('dashboard.map')), $html, 'the dashboard map\'s own data');
        $this->assertStringContainsString('id="smKiosks"', $html, 'the kiosks\' standing in the map bar');

        $this->assertStringNotContainsString('Add a project name to open the map', $html);
        $this->assertStringNotContainsString('id="smVeil"', $html);
        $this->assertStringContainsString('if (!S.opened) {', $html, 'an unnamed site is not pinned by a stray tap');
    }

    /** What the Sites page draws, and who may read it: anyone who opens the page. */
    public function test_the_map_data_carries_the_sites_and_the_kiosks(): void
    {
        $site  = $this->site(['geofence_radius' => 300]);
        $kiosk = Kiosk::firstOrCreate(['code' => 'SITE_A'], ['name' => 'Site A Kiosk', 'site_id' => $site->id]);
        $kiosk->forceFill(['site_id' => $site->id])->save();

        $map = $this->actingAs($this->admin())->getJson(route('dashboard.map'))->assertOk()->json();

        $this->assertSame(300, collect($map['sites'])->firstWhere('id', $site->id)['radius_m']);
        $this->assertSame($site->id, collect($map['kiosks'])->firstWhere('id', $kiosk->id)['site_id']);
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

    // ── Budget and expected length (Michael, 2026-10-15) ─────────────────

    public function test_the_form_asks_for_a_budget_and_an_expected_length(): void
    {
        $html = $this->actingAs($this->admin())->get(route('sites.index'))->assertOk()->getContent();

        $this->assertStringContainsString('id="smBudget"', $html);
        $this->assertStringContainsString('id="smDur"', $html);
        foreach (['days' => 'Days', 'weeks' => 'Weeks', 'months' => 'Months'] as $unit => $label) {
            $this->assertMatchesRegularExpression('/<option value="' . $unit . '"[^>]*>' . $label . '<\/option>/', $html);
        }
        $this->assertMatchesRegularExpression('/<option value="months" selected>Months<\/option>/', $html, 'months unless picked otherwise');
        // Both optional: no star on either label.
        $this->assertMatchesRegularExpression('/<label class="sm-label" for="smBudget">Budget<\/label>/', $html);
        $this->assertMatchesRegularExpression('/<label class="sm-label" for="smDur">Expected length<\/label>/', $html);
    }

    public function test_a_site_is_added_with_its_budget_and_expected_length(): void
    {
        $this->actingAs($this->admin())
             ->postJson(route('sites.store'), [
                 'name' => 'Tower 2', 'location' => 'Naga City Hall',
                 'budget' => 2500000.5, 'duration' => 6, 'duration_unit' => 'months',
             ])
             ->assertOk()
             ->assertJsonPath('site.budget', 2500000.5)
             ->assertJsonPath('site.duration', 6)
             ->assertJsonPath('site.duration_unit', 'months')
             ->assertJsonPath('site.length', '6 months');

        $site = Site::where('name', 'Tower 2')->sole();
        $this->assertSame('2500000.50', $site->budget);
        $this->assertSame(6, $site->duration);
        $this->assertSame('months', $site->duration_unit);
    }

    public function test_the_budget_and_length_are_optional(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
             ->postJson(route('sites.store'), ['name' => 'Tower 2', 'location' => 'Naga', 'budget' => null, 'duration' => null, 'duration_unit' => null])
             ->assertOk()
             ->assertJsonPath('site.budget', null)
             ->assertJsonPath('site.length', null);

        // A unit sent with no number is not kept.
        $this->actingAs($admin)
             ->postJson(route('sites.store'), ['name' => 'Tower 3', 'location' => 'Naga', 'duration_unit' => 'months'])
             ->assertOk();
        $this->assertNull(Site::where('name', 'Tower 3')->sole()->duration_unit);
    }

    public function test_one_of_a_unit_reads_singular(): void
    {
        foreach (['days' => '1 day', 'weeks' => '1 week', 'months' => '1 month'] as $unit => $words) {
            $this->assertSame($words, (new Site(['duration' => 1, 'duration_unit' => $unit]))->lengthLabel());
        }
        $this->assertSame('18 weeks', (new Site(['duration' => 18, 'duration_unit' => 'weeks']))->lengthLabel());
        $this->assertNull((new Site())->lengthLabel());
    }

    public function test_a_budget_or_length_that_does_not_read_is_refused(): void
    {
        $admin = $this->admin();
        $send  = fn (array $extra) => $this->actingAs($admin)->postJson(route('sites.store'), ['name' => 'Tower X', 'location' => 'Naga'] + $extra);

        $send(['budget' => -1])->assertStatus(422)->assertJsonValidationErrors('budget');
        $send(['budget' => 'a lot'])->assertStatus(422)->assertJsonValidationErrors('budget');
        $send(['duration' => 0, 'duration_unit' => 'months'])->assertStatus(422)->assertJsonValidationErrors('duration');
        $send(['duration' => 2.5, 'duration_unit' => 'months'])->assertStatus(422)->assertJsonValidationErrors('duration');
        $send(['duration' => 6])->assertStatus(422)->assertJsonValidationErrors('duration_unit');
        $send(['duration' => 6, 'duration_unit' => 'years'])->assertStatus(422)->assertJsonValidationErrors('duration_unit');

        // Ten years at most, whatever the unit.
        $send(['duration' => 121, 'duration_unit' => 'months'])->assertStatus(422)->assertJsonValidationErrors('duration');
        $send(['duration' => 521, 'duration_unit' => 'weeks'])->assertStatus(422)->assertJsonValidationErrors('duration');
        $send(['duration' => 3651, 'duration_unit' => 'days'])->assertStatus(422)->assertJsonValidationErrors('duration');
        $send(['duration' => 120, 'duration_unit' => 'months'])->assertOk();
    }

    public function test_editing_changes_or_clears_the_budget_and_length(): void
    {
        $site  = $this->site(['budget' => 1000000, 'duration' => 6, 'duration_unit' => 'months']);
        $admin = $this->admin();

        $this->actingAs($admin)
             ->putJson(route('sites.update', $site->id), ['name' => 'Tower 2', 'budget' => 1500000, 'duration' => 18, 'duration_unit' => 'weeks'])
             ->assertOk()
             ->assertJsonPath('site.length', '18 weeks');
        $site->refresh();
        $this->assertSame('1500000.00', $site->budget);
        $this->assertSame('weeks', $site->duration_unit);

        // Cleared from the form: the number goes, and its unit with it.
        $this->actingAs($admin)
             ->putJson(route('sites.update', $site->id), ['name' => 'Tower 2', 'budget' => null, 'duration' => null, 'duration_unit' => 'months'])
             ->assertOk();
        $site->refresh();
        $this->assertNull($site->budget);
        $this->assertNull($site->duration);
        $this->assertNull($site->duration_unit);
    }

    public function test_a_save_that_leaves_them_out_keeps_the_budget_and_length(): void
    {
        $site = $this->site(['budget' => 1000000, 'duration' => 6, 'duration_unit' => 'months']);

        // The dashboard map saves a pin, a rename sends the name alone.
        $this->actingAs($this->admin())
             ->putJson(route('sites.update', $site->id), ['name' => 'Tower Two', 'latitude' => self::NORTH['lat'], 'longitude' => self::NORTH['lng']])
             ->assertOk();

        $site->refresh();
        $this->assertSame('1000000.00', $site->budget);
        $this->assertSame('6 months', $site->lengthLabel());
    }

    public function test_the_kiosk_is_never_told_a_budget(): void
    {
        $this->site(['budget' => 1000000, 'duration' => 6, 'duration_unit' => 'months']);

        $site = $this->getJson('/api/kiosk/sites')->assertOk()->json('sites.0');
        $this->assertArrayNotHasKey('budget', $site);
        $this->assertArrayNotHasKey('duration', $site);
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
            'site_id'         => $site->id,
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
        $this->assertStringContainsString('limit 100 m', $answer['message']);
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
