<?php

namespace Tests\Feature;

use App\Models\Kiosk;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * GET /dashboard/map: what the dashboard's Project Sites map draws.
 *
 * Every site with its range, and every kiosk with where its GPS last put it
 * against the range of the site it is set to. The fix is the one
 * KioskLocationController caches from the Pi's heartbeat; the map only reads.
 */
class DashboardMapTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Site $warehouse;
    private Site $bypass;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Map Admin', 'username' => 'map.admin', 'password' => 'secret123',
            'role' => User::ROLE_ADMIN, 'is_admin' => true, 'is_active' => true,
        ]);

        // The migrations already carry Sites A to E, unpinned. Pin two:
        // Naga City, and Pili about eleven kilometres to the south-east.
        $this->warehouse = Site::updateOrCreate(['name' => 'Site A'], ['latitude' => 13.6218, 'longitude' => 123.1948, 'geofence_radius' => null]);
        $this->bypass    = Site::updateOrCreate(['name' => 'Site B'], ['latitude' => 13.5829, 'longitude' => 123.2741, 'geofence_radius' => 300]);
        Site::updateOrCreate(['name' => 'Site C'], ['latitude' => null, 'longitude' => null]);   // not pinned yet
    }

    private function kiosk(string $code, ?Site $site): Kiosk
    {
        return Kiosk::create(['name' => "Kiosk {$code}", 'code' => $code, 'site_id' => $site?->id, 'is_active' => true]);
    }

    private function heard(string $code, ?float $lat, ?float $lng, string $status = 'fix', int $secondsAgo = 5): void
    {
        Cache::put('kiosk_location_' . $code, [
            'kiosk_id' => $code, 'lat' => $lat, 'lng' => $lng, 'status' => $status,
            'last_seen' => now()->subSeconds($secondsAgo)->toIso8601String(),
            'updated_at' => now()->subSeconds($secondsAgo)->toIso8601String(),
        ], now()->addHour());
    }

    /** @return array<string, array> the kiosks, by code */
    private function map(): array
    {
        $json = $this->actingAs($this->admin)->getJson('/dashboard/map')->assertOk()->json();

        return collect($json['kiosks'])->keyBy('code')->all();
    }

    public function test_each_kiosk_is_placed_against_its_own_sites_range(): void
    {
        $this->kiosk('IN', $this->warehouse);
        $this->kiosk('AWAY', $this->bypass);
        $this->kiosk('OUT', $this->bypass);
        $this->kiosk('NOSAT', $this->warehouse);
        $this->kiosk('QUIET', $this->warehouse);
        $this->kiosk('NEVER', $this->warehouse);

        $this->heard('IN', 13.6221, 123.1951);                    // ~46 m from Site A
        $this->heard('AWAY', 13.6219, 123.1949);                  // set to Site B, standing at Site A
        $this->heard('OUT', 13.5925, 123.2741);                   // ~1 km north of Site B
        $this->heard('NOSAT', null, null, 'no_fix');              // powered on, no satellites
        $this->heard('QUIET', 13.6221, 123.1951, 'fix', 7200);    // silent two hours

        $k = $this->map();

        $this->assertSame('in', $k['IN']['state']);
        $this->assertEqualsWithDelta(46, $k['IN']['distance_m'], 3);
        $this->assertSame(150, $k['IN']['radius_m'], 'a site with no radius of its own uses the office-wide one');

        $this->assertSame('elsewhere', $k['AWAY']['state']);
        $this->assertSame('Site A', $k['AWAY']['at_site']);
        $this->assertSame('Site B', $k['AWAY']['site']);

        $this->assertSame('out', $k['OUT']['state']);
        $this->assertEqualsWithDelta(1067, $k['OUT']['distance_m'], 10);
        $this->assertSame(300, $k['OUT']['radius_m']);
        $this->assertNull($k['OUT']['at_site']);

        $this->assertSame('nogps', $k['NOSAT']['state']);
        $this->assertNull($k['NOSAT']['lat']);

        $this->assertSame('offline', $k['QUIET']['state'], 'a kiosk silent past the offline limit is offline wherever it last was');
        $this->assertSame('stale', $k['QUIET']['gps']);
        $this->assertNotNull($k['QUIET']['lat'], 'its last position is kept, to show where it went quiet');

        $this->assertSame('offline', $k['NEVER']['state']);
        $this->assertNull($k['NEVER']['seen_ago']);
    }

    public function test_the_edge_of_the_range_is_inside(): void
    {
        $this->kiosk('EDGE', $this->bypass);
        // 300 m range; a point 299 m due north is in, 305 m is out.
        $this->heard('EDGE', 13.5829 + 299 / 111195, 123.2741);
        $this->assertSame('in', $this->map()['EDGE']['state']);

        $this->heard('EDGE', 13.5829 + 305 / 111195, 123.2741);
        $this->assertSame('out', $this->map()['EDGE']['state']);
    }

    /** A kiosk whose site has no pin is at whichever site's range it stands in. */
    public function test_a_kiosk_on_an_unpinned_site_is_placed_by_where_it_stands(): void
    {
        $unpinned = Site::where('name', 'Site C')->first();
        $this->kiosk('LOOSE', $unpinned);
        $this->heard('LOOSE', 13.6219, 123.1949);

        $k = $this->map()['LOOSE'];
        $this->assertSame('in', $k['state']);
        $this->assertSame('Site A', $k['at_site']);
        $this->assertNull($k['distance_m'], 'nothing to measure against on its own site');
    }

    /** Every site comes with its range and how many kiosks are set to it; unpinned ones say so. */
    public function test_sites_come_with_their_range(): void
    {
        $this->kiosk('K1', $this->bypass);
        $this->kiosk('K2', $this->bypass);

        $sites = collect($this->actingAs($this->admin)->getJson('/dashboard/map')->json('sites'))->keyBy('name');

        $this->assertSame(300, $sites['Site B']['radius_m']);
        $this->assertSame(2, $sites['Site B']['kiosks']);
        $this->assertSame(150, $sites['Site A']['radius_m']);
        $this->assertNull($sites['Site C']['lat'], 'not pinned: left off the map');
    }

    public function test_only_a_signed_in_user_can_read_it(): void
    {
        $this->getJson('/dashboard/map')->assertUnauthorized();
    }

    public function test_it_only_reads(): void
    {
        $this->kiosk('IN', $this->warehouse);
        $this->heard('IN', 13.6221, 123.1951);
        $before = Site::orderBy('id')->get(['id', 'latitude', 'longitude', 'geofence_radius'])->toArray();

        $this->map();

        $this->assertSame($before, Site::orderBy('id')->get(['id', 'latitude', 'longitude', 'geofence_radius'])->toArray());
        $this->actingAs($this->admin)->postJson('/dashboard/map')->assertStatus(405);
    }
}
