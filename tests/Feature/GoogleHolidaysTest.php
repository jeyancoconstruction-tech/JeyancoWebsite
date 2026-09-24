<?php

namespace Tests\Feature;

use App\Models\GoogleHoliday;
use App\Models\Holiday;
use App\Models\User;
use App\Support\GoogleHolidays;
use App\Support\PhilippineHolidays;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The Holidays tab filled from Google Calendar.
 *
 * The rule that matters most: a sync moves only the days still ahead. Payroll
 * Records re-prices any period on demand, so a holiday appearing or vanishing
 * on a date already passed would quietly change pay already given out.
 */
class GoogleHolidaysTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-24 10:00:00');
        config(['services.google_calendar.key' => 'test-key']);
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function admin(): User
    {
        return User::firstOrCreate(
            ['username' => 'admin.holidays'],
            ['name' => 'Admin', 'password' => Hash::make('secret123'), 'role' => User::ROLE_ADMIN, 'is_active' => true]
        );
    }

    /** One Google Calendar event, the way the API sends it. */
    private function event(string $date, string $title, string $description = 'Public holiday'): array
    {
        return [
            'kind'        => 'calendar#event',
            'status'      => 'confirmed',
            'summary'     => $title,
            'description' => $description,
            'start'       => ['date' => $date],
            'end'         => ['date' => Carbon::parse($date)->addDay()->toDateString()],
        ];
    }

    /** What Google lists right now. */
    private array $events = [];

    private bool $faked = false;

    /**
     * Have Google list these events. A second Http::fake() would not replace
     * the first — the earliest matching stub answers — so the one fake reads
     * whatever the test last said.
     */
    private function googleSays(array $events): void
    {
        $this->events = $events;

        if (! $this->faked) {
            Http::fake([
                'www.googleapis.com/calendar/v3/*' => fn () => Http::response(['kind' => 'calendar#events', 'items' => $this->events]),
            ]);
            $this->faked = true;
        }
    }

    private function calendar(): array
    {
        return [
            $this->event('2026-01-01', "New Year's Day"),
            $this->event('2026-02-17', "Lunar New Year's Day"),
            $this->event('2026-02-25', 'People Power Anniversary', "Observance\nTo hide observances, go to Google Calendar Settings > Holidays in Philippines"),
            $this->event('2026-03-20', 'Eid al-Fitr Holiday'),
            $this->event('2026-04-05', 'Easter Sunday', "Observance\nTo hide observances, go to Google Calendar Settings > Holidays in Philippines"),
            $this->event('2026-06-16', 'Amun Jadid'),
            $this->event('2026-11-01', "All Saints' Day"),
            $this->event('2026-12-24', 'Christmas Eve'),
            $this->event('2026-12-25', 'Christmas Day'),
            $this->event('2027-03-10', 'Eid al-Fitr (tentative)', "Public holiday\nDate is tentative and may change."),
            $this->event('2027-04-09', 'The Day of Valor'),
            $this->event('2027-05-17', 'Eid al-Adha (tentative)', "Public holiday\nDate is tentative and may change."),
            $this->event('2027-05-18', 'Eid al-Adha Holiday (tentative)', "Public holiday\nDate is tentative and may change."),
        ];
    }

    private function byDate(int $year): array
    {
        return collect(Holiday::calendarFor($year))->keyBy('date')->all();
    }

    // ── What comes in ────────────────────────────────────────────────────────

    public function test_days_ahead_follow_google(): void
    {
        $this->googleSays($this->calendar());

        GoogleHolidays::sync();

        $days = $this->byDate(2026);

        // Christmas Eve is a proclamation day the offline list never had.
        $this->assertSame('Christmas Eve', $days['2026-12-24']['title']);
        $this->assertSame('special', $days['2026-12-24']['type']);
        $this->assertTrue($days['2026-12-24']['is_active']);
        $this->assertSame('regular', $days['2026-12-25']['type']);

        // Ahead of today Google is the whole list: Bonifacio Day was not in
        // this feed, so it is not a holiday.
        $this->assertArrayNotHasKey('2026-11-30', $days);

        // A year still wholly ahead is Google's too — the Eid it knows is
        // there, and the EDSA day it calls an observance is not.
        $next = $this->byDate(2027);
        $this->assertSame('regular', $next['2027-03-10']['type']);
        $this->assertTrue($next['2027-03-10']['is_active']);
        $this->assertSame('regular', $next['2027-04-09']['type']);
        $this->assertArrayNotHasKey('2027-02-25', $next);
    }

    public function test_the_twin_day_google_adds_comes_in_switched_off(): void
    {
        $this->googleSays($this->calendar());

        GoogleHolidays::sync();

        $days = $this->byDate(2027);

        // One Eid al-Adha is a regular holiday; the "… Holiday" beside it is
        // shown, and costs nothing until someone turns it on.
        $this->assertTrue($days['2027-05-17']['is_active']);
        $this->assertFalse($days['2027-05-18']['is_active']);
        $this->assertSame('regular', $days['2027-05-18']['type']);

        // The Eid al-Fitr "Holiday" of 2026 has no public twin — Google marks
        // the day after it an observance — so it is the day itself.
        $this->assertTrue(GoogleHolidays::fetch(2026, 2027)['2026-03-20']['is_active']);
    }

    public function test_observances_and_regional_holidays_are_left_out(): void
    {
        $this->googleSays($this->calendar());

        GoogleHolidays::sync();

        $this->assertDatabaseMissing('google_holidays', ['date' => '2026-06-16']);   // Amun Jadid: BARMM only
        $this->assertDatabaseMissing('google_holidays', ['date' => '2026-04-05']);   // Easter Sunday: an observance
    }

    public function test_a_past_day_keeps_the_holiday_it_was_paid_under(): void
    {
        $this->googleSays($this->calendar());

        GoogleHolidays::sync();

        $days = $this->byDate(2026);

        // Google calls the EDSA anniversary an observance, but February was
        // paid with it as a special day, and it stays one.
        $this->assertTrue($days['2026-02-25']['is_active']);
        $this->assertSame('special', $days['2026-02-25']['type']);

        // Holidays only Google knew about arrive switched off.
        $this->assertFalse($days['2026-02-17']['is_active']);
        $this->assertFalse($days['2026-03-20']['is_active']);
        $this->assertSame('regular', $days['2026-03-20']['type']);

        $map = Holiday::typeMap();
        $this->assertArrayNotHasKey('2026-02-17', $map);
        $this->assertSame('special', $map['2026-02-25']);
        $this->assertSame('special', $map['2026-12-24']);
    }

    public function test_later_syncs_move_only_the_days_still_ahead(): void
    {
        $this->googleSays($this->calendar());
        GoogleHolidays::sync();

        // Weeks later Google firms up the Eid date, drops Christmas Eve and
        // renames a day that has gone by.
        Carbon::setTestNow('2026-11-05 10:00:00');
        $this->googleSays([
            $this->event('2026-01-01', "New Year's Day"),
            $this->event('2026-02-17', 'Chinese New Year'),
            $this->event('2026-12-25', 'Christmas Day'),
            $this->event('2027-03-11', 'Eid al-Fitr'),
            $this->event('2027-04-09', 'The Day of Valor'),
            $this->event('2027-05-17', 'Eid al-Adha (tentative)', "Public holiday\nDate is tentative and may change."),
            $this->event('2027-05-18', 'Eid al-Adha Holiday (tentative)', "Public holiday\nDate is tentative and may change."),
        ]);

        $counts = GoogleHolidays::sync();

        $days = $this->byDate(2026);
        $this->assertArrayNotHasKey('2026-12-24', $days);            // ahead: follows Google
        $this->assertArrayHasKey('2026-11-01', $days);               // passed since: kept
        $this->assertSame("Lunar New Year's Day", $days['2026-02-17']['title']);
        $this->assertFalse($days['2026-02-17']['is_active']);

        $next = $this->byDate(2027);
        $this->assertArrayNotHasKey('2027-03-10', $next);
        $this->assertSame('Eid al-Fitr', $next['2027-03-11']['title']);

        $this->assertSame(1, $counts['added']);
        $this->assertSame(2, $counts['removed']);
    }

    public function test_titles_decide_regular_or_special(): void
    {
        foreach (["New Year's Day", 'Maundy Thursday', 'Good Friday', 'The Day of Valor', 'Labor Day',
                  'Independence Day', 'National Heroes Day', 'Bonifacio Day', 'Christmas Day', 'Rizal Day',
                  'Eid al-Fitr', 'Eid al-Adha (tentative)', "Eid'l Fitr"] as $title) {
            $this->assertSame('regular', PhilippineHolidays::typeOf($title), $title);
        }

        foreach (["Lunar New Year's Day", 'Black Saturday', 'Ninoy Aquino Day', "All Saints' Day",
                  'Christmas Eve', "New Year's Eve", 'Feast of the Immaculate Conception', 'National Elections'] as $title) {
            $this->assertSame('special', PhilippineHolidays::typeOf($title), $title);
        }

        foreach (['Lailatul Isra Wal Mi Raj', 'Amun Jadid', 'Maulid un-Nabi'] as $title) {
            $this->assertTrue(PhilippineHolidays::isRegional($title), $title);
        }
        $this->assertFalse(PhilippineHolidays::isRegional('Eid al-Adha'));
    }

    // ── Turning them on and off ──────────────────────────────────────────────

    public function test_a_holiday_that_arrived_off_can_be_turned_on_and_back(): void
    {
        $this->googleSays($this->calendar());
        GoogleHolidays::sync();

        $this->actingAs($this->admin())
             ->postJson(route('holidays.toggle'), ['date' => '2026-02-17'])
             ->assertOk()->assertJson(['is_active' => true]);

        $this->assertSame('special', Holiday::typeMap()['2026-02-17']);

        $this->actingAs($this->admin())
             ->postJson(route('holidays.toggle'), ['date' => '2026-02-17'])
             ->assertOk()->assertJson(['is_active' => false]);

        // Back where the calendar put it, so no override is left behind.
        $this->assertDatabaseMissing('holidays', ['date' => '2026-02-17']);
    }

    public function test_enable_all_and_disable_all_cover_google_days(): void
    {
        $this->googleSays($this->calendar());
        GoogleHolidays::sync();

        $this->actingAs($this->admin())
             ->postJson(route('holidays.bulk-toggle'), ['action' => 'disable', 'year' => 2026])
             ->assertOk();
        $this->assertSame([], array_filter(Holiday::calendarFor(2026), fn ($h) => $h['is_active']));

        $this->actingAs($this->admin())
             ->postJson(route('holidays.bulk-toggle'), ['action' => 'enable', 'year' => 2026])
             ->assertOk();
        $this->assertSame([], array_filter(Holiday::calendarFor(2026), fn ($h) => ! $h['is_active']));
    }

    public function test_an_override_for_a_day_google_dropped_does_not_linger(): void
    {
        // Disabled while it was still an official day…
        Holiday::create(['date' => '2027-02-25', 'title' => 'EDSA People Power Anniversary', 'type' => 'special', 'is_official' => true, 'is_active' => false]);

        // …and then 2027 comes from Google, which does not list it.
        $this->googleSays($this->calendar());
        GoogleHolidays::sync();

        $this->assertArrayNotHasKey('2027-02-25', $this->byDate(2027));
    }

    // ── The button and the daily refresh ─────────────────────────────────────

    public function test_the_sync_button_returns_the_new_calendar(): void
    {
        $this->googleSays($this->calendar());

        $this->actingAs($this->admin())
             ->postJson(route('holidays.sync'), ['year' => 2026])
             ->assertOk()
             ->assertJson(['success' => true, 'year' => 2026])
             ->assertJsonPath('counts.removed', 0)
             ->assertJsonFragment(['date' => '2026-12-24', 'title' => 'Christmas Eve']);

        $this->assertNotNull(GoogleHolidays::syncedAt());
    }

    public function test_the_sync_button_says_why_it_failed(): void
    {
        Http::fake([
            'www.googleapis.com/calendar/v3/*' => Http::response(['error' => [
                'code' => 400, 'message' => 'API key not valid. Please pass a valid API key.', 'status' => 'INVALID_ARGUMENT',
            ]], 400),
        ]);

        $this->actingAs($this->admin())
             ->postJson(route('holidays.sync'), ['year' => 2026])
             ->assertStatus(422)
             ->assertJson(['success' => false, 'message' => 'Google Calendar refused the request: API key not valid. Please pass a valid API key.']);

        // Nothing was written, so the offline calendar still stands.
        $this->assertSame(0, GoogleHoliday::count());
        $this->assertArrayHasKey('2026-11-30', $this->byDate(2026));
    }

    public function test_the_command_syncs_and_reports(): void
    {
        $this->googleSays($this->calendar());

        $this->artisan('holidays:sync')
             ->expectsOutputToContain('Synced from Google Calendar:')
             ->assertExitCode(0);

        $this->assertArrayHasKey('2026-12-24', $this->byDate(2026));

        config(['services.google_calendar.key' => null]);

        $this->artisan('holidays:sync')
             ->expectsOutputToContain('GOOGLE_CALENDAR_API_KEY')
             ->assertExitCode(1);
    }

    public function test_without_a_key_nothing_is_asked_of_google(): void
    {
        config(['services.google_calendar.key' => null]);
        Http::fake();

        $this->actingAs($this->admin())->get(route('settings.index', ['tab' => 'holiday']))->assertOk();

        $this->actingAs($this->admin())
             ->postJson(route('holidays.sync'), ['year' => 2026])
             ->assertStatus(422)
             ->assertJsonFragment(['success' => false]);

        Http::assertNothingSent();
        $this->assertArrayHasKey('2026-11-30', $this->byDate(2026));   // offline Bonifacio Day
    }

    public function test_opening_the_page_syncs_once_a_day(): void
    {
        $this->googleSays($this->calendar());

        $this->actingAs($this->admin())->get(route('settings.index', ['tab' => 'holiday']))->assertOk();
        $this->actingAs($this->admin())->get(route('settings.index', ['tab' => 'holiday']))->assertOk();

        Http::assertSentCount(1);
        $this->assertArrayHasKey('2026-12-24', $this->byDate(2026));

        // A day later it goes again.
        Cache::forget('holidays.google.fresh');
        $this->actingAs($this->admin())->get(route('settings.index', ['tab' => 'holiday']))->assertOk();

        Http::assertSentCount(2);
    }
}
