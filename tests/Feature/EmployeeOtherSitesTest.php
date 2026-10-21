<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\EmployeeChange;
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
 * A worker at more than one site (Michael, 2026-10-21: "allowing employee
 * have a multiple sites pls add it on registering employee and on employee
 * list bulky actions").
 *
 * The site a worker has always had stays their main site: lists, reports and
 * payroll file them under it, and Transfer site moves it. Besides it they can
 * have other sites, picked on Register employee and Edit employee or added
 * and taken off from the bar under a selection. The kiosk at any of their
 * sites takes their scans and lists them; the day is stamped with the site
 * it was scanned at.
 */
class EmployeeOtherSitesTest extends TestCase
{
    use RefreshDatabase;

    private const HOME = ['lat' => 13.6240, 'lng' => 123.1850];

    private User $admin;
    private Site $a;
    private Site $b;
    private Site $c;
    private Shift $day;
    private LaborType $mason;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-21 08:05:00', 'Asia/Manila'));

        $this->admin = User::create([
            'name' => 'Admin', 'username' => 'admin.sites', 'password' => 'secret123',
            'role' => User::ROLE_ADMIN, 'is_admin' => true, 'is_active' => true,
        ]);

        // Pinned in one spot, so a kiosk there is inside each one's range.
        $pin = ['latitude' => self::HOME['lat'], 'longitude' => self::HOME['lng'], 'geofence_radius' => 150];
        $this->a = Site::create(['name' => 'Site Alpha'] + $pin);
        $this->b = Site::create(['name' => 'Site Beta'] + $pin);
        $this->c = Site::create(['name' => 'Site Gamma'] + $pin);

        $this->day   = Shift::where('crosses_midnight', false)->firstOrFail();
        $this->mason = LaborType::create(['name' => 'Sites Mason', 'daily_rate' => 800, 'ot_rate' => 125]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function worker(string $name, ?Site $main = null, array $others = [], string $fp = '7'): Employee
    {
        $e = Employee::create([
            'name' => $name, 'status' => Employee::STATUS_ACTIVE, 'employment_type' => Employee::EMPLOYMENT_DAILY,
            'labor_type_id' => $this->mason->id, 'position' => $this->mason->name, 'rate_per_hour' => 100,
            'site_id' => ($main ?? $this->a)->id, 'shift_id' => $this->day->id, 'fingerprint_id' => $fp,
        ]);
        $e->otherSites()->sync(collect($others)->pluck('id')->all());

        return $e;
    }

    /** What Register employee posts with its two required steps filled in. */
    private function form(array $with = []): array
    {
        return $with + [
            'profile_form'    => '1',
            'employment_type' => Employee::EMPLOYMENT_REGULAR,
            'first_name'      => 'Juan',
            'last_name'       => 'Dela Cruz',
            'labor_type_id'   => $this->mason->id,
            'job_title'       => 'Sites Mason',
            'site_id'         => $this->a->id,
            'shift_id'        => $this->day->id,
            'rate_per_hour'   => '100.00',
            'date_hired'      => '2026-10-12',
            'nationality'     => 'Filipino',
        ];
    }

    private function kioskAt(Site $site): Kiosk
    {
        $kiosk = Kiosk::updateOrCreate(['code' => 'SITE_A'], ['name' => 'Kiosk', 'site_id' => $site->id, 'is_active' => true]);
        Cache::put('kiosk_location_' . $kiosk->id, ['lat' => self::HOME['lat'], 'lng' => self::HOME['lng'], 'status' => 'fix',
            'last_seen' => now()->subSeconds(5)->toIso8601String()], now()->addDay());

        return $kiosk;
    }

    private function timeIn(Employee $e): array
    {
        return $this->postJson('/api/kiosk/attendance', ['employee_id' => $e->id, 'type' => 'time_in', 'kiosk_code' => 'SITE_A'])->json();
    }

    private function bulk(array $body)
    {
        return $this->actingAs($this->admin)->postJson(route('employees.bulk-other-site'), $body + [
            'effective_on' => '2026-10-21',
        ]);
    }

    private function others(Employee $e): array
    {
        return $e->fresh()->otherSiteList()->pluck('name')->all();
    }

    // ── Register and Edit ────────────────────────────────────────────────

    public function test_register_employee_saves_the_other_sites_but_never_the_main_one_twice(): void
    {
        $this->actingAs($this->admin)->postJson(route('employees.store'), $this->form([
            'other_site_ids' => ['', $this->b->id, $this->a->id],
        ]))->assertOk()->assertJsonPath('success', true);

        $e = Employee::where('name', 'like', 'Juan%')->sole();
        $this->assertSame('Site Alpha', $e->site->name, 'the site picked first stays the main site');
        $this->assertSame(['Site Beta'], $this->others($e));
        $this->assertSame([$this->a->id, $this->b->id], $e->fresh()->siteIds());
    }

    public function test_edit_changes_them_and_a_form_without_the_picker_leaves_them(): void
    {
        $e = $this->worker('Ana Reyes', $this->a, [$this->b]);
        $put = fn (array $with) => $this->actingAs($this->admin)->put(route('employees.update', $e), $this->form($with))->assertRedirect();

        $put(['other_site_ids' => ['', $this->c->id]]);
        $this->assertSame(['Site Gamma'], $this->others($e));

        // Nothing ticked: the blank entry still arrives, and means none.
        $put(['other_site_ids' => ['']]);
        $this->assertSame([], $this->others($e));

        // A form with no picker at all leaves them as they are.
        $e->otherSites()->sync([$this->b->id]);
        $put([]);
        $this->assertSame(['Site Beta'], $this->others($e));
    }

    public function test_both_forms_carry_the_same_picker_with_the_sites_ticked(): void
    {
        $e = $this->worker('Ana Reyes', $this->a, [$this->c]);

        $edit = $this->actingAs($this->admin)->get(route('employees.edit', $e))->assertOk()->getContent();
        $this->assertStringContainsString('<input type="hidden" name="other_site_ids[]" value="">', $edit);
        $this->assertStringContainsString('<input type="checkbox" name="other_site_ids[]" value="' . $this->c->id . '" checked><span>Site Gamma</span>', $edit);
        $this->assertStringContainsString('<input type="checkbox" name="other_site_ids[]" value="' . $this->b->id . '"><span>Site Beta</span>', $edit);
        $this->assertStringContainsString('data-main="site_select"', $edit);

        $page = $this->actingAs($this->admin)->get(route('employees.register'))->assertOk()->getContent();
        $this->assertStringContainsString('id="rwOtherSitesLabel">Other sites</span>', $page);
        $this->assertStringContainsString('<input type="checkbox" name="other_site_ids[]" value="' . $this->b->id . '"><span>Site Beta</span>', $page);
        // The review step names them.
        $this->assertStringContainsString("['Other sites', otherSites()]", $page);
    }

    // ── The kiosk ────────────────────────────────────────────────────────

    public function test_the_kiosk_at_another_of_their_sites_takes_the_scan_there(): void
    {
        $e = $this->worker('Ana Reyes', $this->a, [$this->b]);
        $this->kioskAt($this->b);

        $this->assertTrue($this->timeIn($e)['success']);
        $this->assertSame($this->b->id, Attendance::sole()->site_id, 'the day is stamped with the site it was scanned at');
        $this->assertSame($this->a->id, $e->fresh()->site_id, 'and the main site stays');
    }

    public function test_a_site_that_is_none_of_theirs_still_refuses_them_by_their_sites(): void
    {
        $e = $this->worker('Ana Reyes', $this->a, [$this->b]);
        $this->kioskAt($this->c);

        $answer = $this->timeIn($e);
        $this->assertSame('wrong_site', $answer['code']);
        $this->assertStringContainsString('assigned to Site Alpha and Site Beta, not Site Gamma', $answer['message']);
        $this->assertSame(0, Attendance::count());
    }

    public function test_each_of_their_sites_kiosks_lists_them(): void
    {
        $this->worker('Ana Reyes', $this->a, [$this->b], '7');
        $this->worker('Ben Cruz', $this->c, [], '8');

        foreach ([[$this->a, ['Ana Reyes']], [$this->b, ['Ana Reyes']], [$this->c, ['Ben Cruz']]] as [$site, $names]) {
            $this->kioskAt($site);
            $listed = collect($this->getJson('/api/kiosk/roster?kiosk_code=SITE_A')->json('employees'))->pluck('name')->all();
            $this->assertSame($names, $listed, "{$site->name}'s kiosk");
        }
    }

    // ── The bar under a selection ────────────────────────────────────────

    public function test_add_site_adds_it_beside_the_main_one_and_undo_takes_it_back(): void
    {
        $ana = $this->worker('Ana Reyes', $this->a, [], '7');
        $ben = $this->worker('Ben Cruz', $this->c, [], '8');          // Gamma is his main site already
        $cid = $this->worker('Cid Lopez', $this->a, [$this->c], '9');  // and one of Cid's

        $res = $this->bulk(['ids' => [$ana->id, $ben->id, $cid->id], 'site_id' => $this->c->id, 'mode' => 'add'])
            ->assertOk()->assertJsonPath('changed', 1)->assertJsonPath('skipped', 2)
            ->assertJsonPath('message', '1 can now also scan at Site Gamma');

        $this->assertSame(['Site Gamma'], $this->others($ana));
        $this->assertSame($this->a->id, $ana->fresh()->site_id);
        $this->assertSame([], $this->others($ben), 'a main site is not added again as another');

        $this->actingAs($this->admin)->postJson(route('employees.bulk-undo'), ['batch' => $res->json('batch')])
            ->assertOk()->assertJsonPath('undone', 1);
        $this->assertSame([], $this->others($ana));
        $this->assertSame(['Site Gamma'], $this->others($cid), 'Cid had it before, and keeps it');
    }

    public function test_remove_site_takes_off_another_site_only(): void
    {
        $ana = $this->worker('Ana Reyes', $this->a, [$this->b], '7');
        $ben = $this->worker('Ben Cruz', $this->b, [], '8');

        $res = $this->bulk(['ids' => [$ana->id, $ben->id], 'site_id' => $this->b->id, 'mode' => 'remove'])
            ->assertOk()->assertJsonPath('changed', 1)->assertJsonPath('message', '1 taken off Site Beta');

        $this->assertSame([], $this->others($ana));
        $this->assertSame($this->b->id, $ben->fresh()->site_id, 'a main site is moved by Transfer site, not here');

        // Nobody left with it as another site: nothing to do, said so.
        $this->bulk(['ids' => [$ana->id, $ben->id], 'site_id' => $this->b->id, 'mode' => 'remove'])
            ->assertStatus(422)->assertJsonPath('message', 'Nobody selected has Site Beta as another site. A main site is changed with Transfer site.');

        $this->actingAs($this->admin)->postJson(route('employees.bulk-undo'), ['batch' => $res->json('batch')])->assertOk();
        $this->assertSame(['Site Beta'], $this->others($ana));
    }

    public function test_a_date_ahead_waits_for_its_day_with_a_tag_on_the_row(): void
    {
        $ana = $this->worker('Ana Reyes', $this->a);

        $this->bulk(['ids' => [$ana->id], 'site_id' => $this->b->id, 'mode' => 'add', 'effective_on' => '2026-10-26'])
            ->assertOk()->assertJsonPath('later', true)->assertJsonPath('message', '1 can also scan at Site Beta from Oct 26, 2026');
        $this->assertSame([], $this->others($ana));

        $page = $this->actingAs($this->admin)->get(route('employees.register'))->getContent();
        $this->assertStringContainsString('Also at Site Beta from Oct 26', $page);

        Carbon::setTestNow(Carbon::parse('2026-10-26 07:00:00', 'Asia/Manila'));
        $this->assertSame(1, EmployeeChange::applyDue());
        $this->assertSame(['Site Beta'], $this->others($ana));
    }

    public function test_a_transfer_to_one_of_their_other_sites_does_not_list_it_twice(): void
    {
        $ana = $this->worker('Ana Reyes', $this->a, [$this->b]);

        $this->actingAs($this->admin)->postJson(route('employees.bulk-site'), [
            'ids' => [$ana->id], 'site_id' => $this->b->id, 'effective_on' => '2026-10-21',
        ])->assertOk();

        $this->assertSame('Site Beta', $ana->fresh()->site->name);
        $this->assertSame([], $this->others($ana));
        $this->assertSame([$this->b->id], $ana->fresh()->siteIds());
    }

    // ── Where they show ──────────────────────────────────────────────────

    public function test_the_list_the_card_and_the_profile_name_the_other_sites(): void
    {
        $ana = $this->worker('Ana Reyes', $this->a, [$this->b, $this->c]);

        $page = $this->actingAs($this->admin)->get(route('employees.register'))->assertOk()->getContent();
        // The Site filter finds her under each of her sites.
        $this->assertStringContainsString('data-sites="[&quot;Site Alpha&quot;,&quot;Site Beta&quot;,&quot;Site Gamma&quot;]"', $page);
        $this->assertStringContainsString('data-other-site-ids="' . $this->b->id . ',' . $this->c->id . '"', $page);
        $this->assertStringContainsString('valuesOf(tr, k).includes(f[k])', $page);
        // The Site column counts them beside the main site.
        $this->assertStringContainsString('<span class="rmx-pill rmx-more-sites" title="Also at Site Beta and Site Gamma">+2</span>', $page);
        // The worker card.
        $this->assertStringContainsString('&quot;otherSites&quot;:&quot;Site Beta, Site Gamma&quot;', $page);

        $this->actingAs($this->admin)->get(route('employees.show', $ana))->assertOk()
            ->assertSee('<dt>Other sites</dt><dd>Site Beta, Site Gamma</dd>', false);
    }

    public function test_the_bar_has_add_site_with_its_two_ways(): void
    {
        $this->worker('Ana Reyes');
        $page = $this->actingAs($this->admin)->get(route('employees.register'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<button type="button" class="rmx-abtn" data-act="sites">.*?Add site<\/button>/s', $page);
        $this->assertStringContainsString('<button type="button" data-mode="add" class="on" aria-pressed="true">', $page);
        $this->assertStringContainsString('<button type="button" data-mode="remove" aria-pressed="false">', $page);
        $this->assertStringContainsString(json_encode(route('employees.bulk-other-site')), $page);
    }
}
