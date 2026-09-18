<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Kiosk;
use App\Models\LaborType;
use App\Models\Shift;
use App\Models\Site;
use App\Models\User;
use App\Support\Live;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The pages keep themselves up to date.
 *
 * A change made anywhere — the office, or the kiosk out on site — raises the
 * revision of the topics it belongs to, and that is the whole of what travels:
 * a page that sees a number move asks for its own current contents. So these
 * tests are about the feed and the connection over it, not about any page's
 * markup, which each page's own test covers.
 */
class LiveUpdatesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-18 08:00:00', 'Asia/Manila'));
        Live::listenAgain();

        $this->admin = User::create([
            'name' => 'Admin', 'username' => 'admin.live', 'password' => 'secret123',
            'role' => User::ROLE_ADMIN, 'is_admin' => true, 'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ── The feed ────────────────────────────────────────────────────────────

    public function test_a_write_raises_the_topics_it_belongs_to(): void
    {
        $before = Live::revisions();

        $worker = $this->worker('Lawrence Bernas');

        $after = Live::revisions();

        // An employee is the workforce, the attendance board and the payroll.
        foreach (['employees', 'attendance', 'payroll'] as $topic) {
            $this->assertGreaterThan($before[$topic] ?? 0, $after[$topic] ?? 0, "{$topic} did not move");
        }

        // And nothing it has nothing to do with.
        $this->assertSame($before['sites'] ?? 0, $after['sites'] ?? 0);

        $attendanceBefore = $after['attendance'];

        Attendance::create([
            'employee_id' => $worker->id,
            'date'        => '2026-09-18',
            'time_in'     => '2026-09-18 08:00:00',
            'session'     => 'AM',
        ]);

        $this->assertGreaterThan($attendanceBefore, Live::revisions()['attendance']);
    }

    public function test_one_request_speaks_once_however_many_rows_it_writes(): void
    {
        $this->worker('One');
        $this->worker('Two');

        $before = Live::revisions()['employees'];

        // Three employees archived in a single request is one announcement:
        // the page re-reads itself either way.
        $this->actingAs($this->admin)
            ->delete(route('employees.bulk-delete'), ['ids' => Employee::pluck('id')->all()]);

        $this->assertSame($before + 1, Live::revisions()['employees']);
    }

    public function test_the_kiosk_moves_the_same_feed_the_office_reads(): void
    {
        $worker = $this->worker('Aldrin Sapugay');
        $worker->forceFill(['fingerprint_id' => 77, 'status' => Employee::STATUS_ACTIVE])->save();

        $before = Live::revisions();

        $this->postJson('/api/kiosk/clock', [
            'fingerprint_id' => '77',
            'type'           => 'time_in',
        ])->assertSuccessful();

        $after = Live::revisions();

        $this->assertGreaterThan($before['attendance'] ?? 0, $after['attendance'] ?? 0);
        $this->assertGreaterThan($before['payroll'] ?? 0, $after['payroll'] ?? 0);
    }

    public function test_a_kiosk_position_is_announced_though_nothing_is_saved(): void
    {
        $site  = Site::create(['name' => 'Bacoor', 'latitude' => 14.45, 'longitude' => 120.95]);
        $kiosk = Kiosk::firstOrCreate(['code' => 'SITE_A'], ['name' => 'Kiosk A']);
        $kiosk->forceFill(['site_id' => $site->id])->save();

        $before = Live::revisions()['kiosk'] ?? 0;

        $this->postJson('/api/location', [
            'kiosk_id' => $kiosk->code,
            'lat'      => 14.4501,
            'lng'      => 120.9502,
            'status'   => 'fix',
        ])->assertSuccessful();

        $this->assertGreaterThan($before, Live::revisions()['kiosk'] ?? 0);
    }

    public function test_the_feed_stays_quiet_when_its_table_is_missing(): void
    {
        DB::statement('DROP TABLE live_changes');

        // A deploy is live for the minutes before its migration is run. The
        // save still has to work; the page simply will not update itself.
        $worker = $this->worker('Before the migration');

        $this->assertTrue($worker->exists);
        $this->assertSame([], Live::revisions());

        $this->actingAs($this->admin)->get(route('dashboard'))->assertOk();
    }

    // ── The connection ──────────────────────────────────────────────────────

    public function test_only_a_signed_in_account_may_listen(): void
    {
        $this->get(route('live.revisions'))->assertRedirect(route('login'));
        $this->get(route('live.stream'))->assertRedirect(route('login'));
    }

    public function test_the_revisions_answer_says_where_everything_stands(): void
    {
        $this->worker('Someone');

        $this->actingAs($this->admin)
            ->get(route('live.revisions'))
            ->assertOk()
            ->assertJsonStructure(['revisions', 'stream', 'poll_ms', 'at'])
            ->assertJsonPath('revisions.employees', Live::revisions()['employees']);
    }

    public function test_the_stream_is_an_event_stream_that_opens_with_where_things_stand(): void
    {
        config(['live.seconds' => 0]);

        $response = $this->actingAs($this->admin)->get(route('live.stream'));

        $response->assertOk();
        $this->assertStringContainsString('text/event-stream', (string) $response->headers->get('Content-Type'));
        $this->assertSame('no', $response->headers->get('X-Accel-Buffering'));

        $body = $response->streamedContent();

        $this->assertStringContainsString('retry: ', $body);
        $this->assertStringContainsString('event: hello', $body);
        $this->assertStringContainsString('event: bye', $body);
    }

    public function test_a_page_that_reconnects_is_told_what_it_missed(): void
    {
        config(['live.seconds' => 0]);

        $this->worker('Filed while the page was away');

        $now = Live::revisions();

        // The page last heard "attendance:1" and has been away since.
        $body = $this->actingAs($this->admin)
            ->get(route('live.stream') . '?since=' . urlencode('attendance:1'))
            ->streamedContent();

        $this->assertStringContainsString('event: change', $body);
        $this->assertStringContainsString('"attendance":' . $now['attendance'], $body);
    }

    public function test_a_page_hears_nothing_when_it_is_already_current(): void
    {
        config(['live.seconds' => 0]);

        $this->worker('Already on the page');

        $since = collect(Live::revisions())->map(fn ($r, $t) => $t . ':' . $r)->implode(',');

        $body = $this->actingAs($this->admin)
            ->get(route('live.stream') . '?since=' . urlencode($since))
            ->streamedContent();

        $this->assertStringNotContainsString('event: change', $body);
    }

    public function test_a_full_house_tells_the_page_to_ask_instead_of_listen(): void
    {
        // Every place for a stream taken: the remaining workers are for pages
        // to load with, so the tab asks what changed every few seconds.
        config(['live.max_streams' => 0]);

        $body = $this->actingAs($this->admin)->get(route('live.stream'))->streamedContent();

        $this->assertStringContainsString('event: poll', $body);
        $this->assertStringNotContainsString('event: hello', $body);
    }

    public function test_the_places_for_streams_are_given_back(): void
    {
        config(['live.seconds' => 0, 'live.max_streams' => 1]);

        $this->actingAs($this->admin)->get(route('live.stream'))->streamedContent();

        // The one place is free again, so the next tab gets a stream rather
        // than being sent away to ask.
        $body = $this->actingAs($this->admin)->get(route('live.stream'))->streamedContent();

        $this->assertStringContainsString('event: hello', $body);
    }

    // ── The pages ───────────────────────────────────────────────────────────

    public function test_every_page_is_wired_for_it(): void
    {
        $page = $this->actingAs($this->admin)->get(route('dashboard'));

        $page->assertOk()
            ->assertSee('js/live.js', false)
            ->assertSee('window.LiveConfig', false)
            ->assertSee('streamUrl', false)
            ->assertSee('live\/stream', false);
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function worker(string $name): Employee
    {
        return Employee::create([
            'name'            => $name,
            'status'          => Employee::STATUS_ACTIVE,
            'employment_type' => Employee::EMPLOYMENT_DAILY,
            'labor_type_id'   => LaborType::create(['name' => 'Mason ' . $name, 'daily_rate' => 800, 'ot_rate' => 125])->id,
            'shift_id'        => Shift::where('crosses_midnight', false)->firstOrFail()->id,
            'rate_per_hour'   => 100,
            'fingerprint_id'  => (string) random_int(1000, 9999),
        ]);
    }
}
