<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Bonus;
use App\Models\Employee;
use App\Models\EmployeeChange;
use App\Models\LaborType;
use App\Models\PayrollRate;
use App\Models\Shift;
use App\Models\Site;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\PayrollService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The bar under a selection on Employees (Michael's mockup, 2026-10-12):
 * transfer site, change shift, change labor type, add bonus — each for
 * everybody ticked, each from a date, each with an Undo.
 *
 * The rule under all four is his: the past is not changed. A day already
 * worked keeps the site and shift it was clocked under, and a labor type
 * changed today does not reprice last week — which it would have, payroll
 * reading the rate off the worker's labor type as it stands. So a change is
 * dated and kept (EmployeeChange), payroll prices a day by the labor type in
 * force on it (LaborHistory), and no date before today is accepted.
 */
class EmployeeBulkActionsTest extends TestCase
{
    use RefreshDatabase;

    /** Week 38: Monday the 14th to Sunday the 20th. */
    private const WEEK = ['2026-09-14', '2026-09-20'];

    private const TODAY = '2026-09-17';

    private User $admin;
    private Site $siteA;
    private Site $siteB;
    private Shift $day;
    private Shift $night;
    private LaborType $laborer;
    private LaborType $mason;

    protected function setUp(): void
    {
        parent::setUp();

        // Thursday of week 38, in the afternoon.
        Carbon::setTestNow(Carbon::parse(self::TODAY . ' 14:00:00', 'Asia/Manila'));
        SystemSetting::current()->forceFill(['schedule_rules_from' => '2026-09-01'])->save();

        PayrollRate::create(array_merge(PayrollRate::DEFAULTS, [
            'effective_from' => '2026-01-01', 'created_by' => 'test',
        ]));

        $this->admin = User::create([
            'name' => 'Admin', 'username' => 'admin.bulk', 'password' => 'secret123',
            'role' => User::ROLE_ADMIN, 'is_admin' => true, 'is_active' => true,
        ]);

        $this->siteA   = Site::firstOrCreate(['name' => 'Site A']);
        $this->siteB   = Site::firstOrCreate(['name' => 'Site B']);
        $this->day     = Shift::where('crosses_midnight', false)->firstOrFail();
        $this->night   = Shift::where('crosses_midnight', true)->firstOrFail();
        $this->laborer = LaborType::create(['name' => 'Bulk Laborer', 'daily_rate' => 800, 'ot_rate' => 125]);
        $this->mason   = LaborType::create(['name' => 'Bulk Mason', 'daily_rate' => 1200, 'ot_rate' => 187.5]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** A laborer at Site A on the day shift: ₱800 a day, ₱100 an hour. */
    private function worker(string $name, array $days = [], array $with = []): Employee
    {
        $e = Employee::create($with + [
            'name'            => $name,
            'status'          => Employee::STATUS_ACTIVE,
            'employment_type' => Employee::EMPLOYMENT_DAILY,
            'labor_type_id'   => $this->laborer->id,
            'position'        => $this->laborer->name,
            'job_title'       => $this->laborer->name,
            'site_id'         => $this->siteA->id,
            'shift_id'        => $this->day->id,
            'rate_per_hour'   => 100,
            'fingerprint_id'  => (string) random_int(1000, 8999),
        ]);

        foreach ($days as $d) {
            $this->clock($e, $d);
        }

        return $e;
    }

    /** Eight to five on a day, at the worker's site and shift as they stand. */
    private function clock(Employee $e, string $date): Attendance
    {
        $e = $e->fresh();

        return Attendance::create([
            'employee_id' => $e->id, 'shift_id' => $e->shift_id, 'site_id' => $e->site_id,
            'date' => $date, 'session' => 'AM',
            'time_in' => "{$date} 08:00:00", 'time_out' => "{$date} 17:00:00",
        ]);
    }

    private function send(string $action, array $body, ?User $as = null)
    {
        return $this->actingAs($as ?? $this->admin)->postJson(route('employees.bulk-' . $action), $body);
    }

    private function ids(Employee ...$workers): array
    {
        return array_map(fn (Employee $e) => $e->id, $workers);
    }

    /** One worker's gross for a range, as every payroll screen computes it. */
    private function gross(Employee $e, string $from, ?string $to = null): float
    {
        $row = collect(app(PayrollService::class)->computeForRange($from, $to ?? $from)['employees'])
            ->firstWhere('employee_id', $e->id);

        return (float) ($row['totals']['gross'] ?? 0);
    }

    // ── The page ─────────────────────────────────────────────────────────

    /** The bar, its dialog, the site picker and the hint are on the page; the list itself is as it was. */
    public function test_the_page_carries_the_bar_and_its_dialog(): void
    {
        $e    = $this->worker('Ana Reyes');
        $html = $this->actingAs($this->admin)->get(route('employees.register'))->assertOk()->getContent();

        $this->assertStringContainsString('id="rmxActs"', $html);
        foreach (['site' => 'Transfer site', 'shift' => 'Change shift', 'labor' => 'Change labor type', 'bonus' => 'Add bonus'] as $act => $label) {
            $this->assertMatchesRegularExpression('/<button type="button" class="rmx-abtn" data-act="' . $act . '">.*?' . $label . '<\/button>/s', $html);
        }
        $this->assertStringContainsString('id="rmxBulkModal"', $html);
        $this->assertStringContainsString('id="rmxSite"', $html);
        $this->assertStringContainsString('Tick employees or use the header box to select everyone shown', $html);

        // What the dialog compares against, on the worker's row.
        $this->assertStringContainsString(
            'data-site-id="' . $this->siteA->id . '" data-shift-id="' . $this->day->id . '" data-labor-id="' . $this->laborer->id . '"',
            $html
        );

        // What was there before is still there.
        $this->assertSame(1, substr_count($html, 'id="rmxSelect"'));
        $this->assertStringContainsString('js-bulk-remove', $html);
        $this->assertStringContainsString('class="rmx-icon-btn rmx-gift js-add-bonus"', $html);
        $this->assertStringContainsString('data-filter="site"', $html);
        $this->assertStringContainsString('id="rmxSearch"', $html);

        // The dialog offers the office's own sites, shifts, labor types and rates.
        $this->assertStringContainsString('"name":"Bulk Mason","rate":150', $html);
        // As the page's script receives them: json_encode writes the dash as an escape.
        $this->assertStringContainsString('"label":' . json_encode('Sep 14 – Sep 20, 2026'), $html);
        $this->assertStringContainsString('"label":' . json_encode('Sep 21 – Sep 27, 2026'), $html);
        $this->assertStringContainsString('min="' . self::TODAY . '"', $html, 'no date before today can be picked');
    }

    /** The site picker sits before Search, and Search before Select. */
    public function test_the_site_picker_sits_with_search(): void
    {
        $this->worker('Ana Reyes');
        $html = $this->actingAs($this->admin)->get(route('employees.register'))->getContent();

        $this->assertLessThan(strpos($html, 'id="rmxSearch"'), strpos($html, 'id="rmxSite"'));
        $this->assertLessThan(strpos($html, 'id="rmxSelect"'), strpos($html, 'id="rmxSearch"'));
        $this->assertMatchesRegularExpression('/<select id="rmxSite"[^>]*>\s*<option value="\*">All sites<\/option>/', $html);
        $this->assertStringContainsString('<option value="Site B">Site B</option>', $html);
        // A see-through select needs its list coloured, or dark mode opens it white.
        $this->assertStringContainsString('.rmx-sitepick select option { background: var(--surface); color: var(--text-primary); }', $html);
    }

    // ── Transfer site ────────────────────────────────────────────────────

    public function test_a_transfer_moves_everybody_ticked_and_skips_who_is_already_there(): void
    {
        $ana = $this->worker('Ana Reyes');
        $ben = $this->worker('Ben Cruz');
        $cy  = $this->worker('Cy Dizon', [], ['site_id' => $this->siteB->id]);

        $this->send('site', ['ids' => $this->ids($ana, $ben, $cy), 'site_id' => $this->siteB->id, 'effective_on' => self::TODAY])
            ->assertOk()
            ->assertJson(['success' => true, 'changed' => 2, 'skipped' => 1, 'later' => false, 'message' => '2 moved to Site B']);

        $this->assertSame($this->siteB->id, $ana->fresh()->site_id);
        $this->assertSame($this->siteB->id, $ben->fresh()->site_id);

        $rows = EmployeeChange::where('kind', EmployeeChange::SITE)->get();
        $this->assertCount(2, $rows, 'nothing is on file for the worker who did not move');
        $this->assertSame([$this->siteA->id], $rows->pluck('from_id')->unique()->all());
        $this->assertSame(1, $rows->pluck('batch')->unique()->count(), 'one press, one batch');
        $this->assertTrue($rows->every(fn ($r) => $r->applied_at !== null));

        $this->assertSame(
            'Transferred 2 employees to Site B, effective Sep 17, 2026: Ana Reyes, Ben Cruz.',
            AuditLog::where('module', 'Employees')->sole()->description,
            'one line for the lot, not one per worker'
        );
    }

    public function test_a_transfer_leaves_past_attendance_and_pay_alone(): void
    {
        $ana    = $this->worker('Ana Reyes', ['2026-09-15', '2026-09-16']);
        $before = $this->gross($ana, ...self::WEEK);
        $scans  = Attendance::where('employee_id', $ana->id)->get()->toArray();

        $this->send('site', ['ids' => $this->ids($ana), 'site_id' => $this->siteB->id, 'effective_on' => self::TODAY])->assertOk();

        $this->assertSame($scans, Attendance::where('employee_id', $ana->id)->get()->toArray(), 'not a column of a past day moved');
        $this->assertSame([$this->siteA->id], Attendance::where('employee_id', $ana->id)->pluck('site_id')->unique()->all());
        $this->assertEqualsWithDelta($before, $this->gross($ana, ...self::WEEK), 0.001);
    }

    public function test_workers_can_be_taken_off_their_site(): void
    {
        $ana = $this->worker('Ana Reyes');

        $this->send('site', ['ids' => $this->ids($ana), 'site_id' => null, 'effective_on' => self::TODAY])
            ->assertOk()->assertJson(['message' => '1 moved to Unassigned']);

        $this->assertNull($ana->fresh()->site_id);
    }

    // ── Change shift ─────────────────────────────────────────────────────

    public function test_a_shift_change_is_for_the_days_to_come(): void
    {
        $ana  = $this->worker('Ana Reyes', ['2026-09-15']);
        $past = Attendance::where('employee_id', $ana->id)->sole();

        $this->send('shift', ['ids' => $this->ids($ana), 'shift_id' => $this->night->id, 'effective_on' => self::TODAY])
            ->assertOk()->assertJson(['changed' => 1, 'message' => '1 changed to ' . $this->night->name]);

        $this->assertSame($this->night->id, $ana->fresh()->shift_id);
        $this->assertSame($this->day->id, $past->fresh()->shift_id, 'the day already worked keeps the shift it was worked under');

        // The next clock is stamped with the shift they are on now.
        $next = Attendance::create(['employee_id' => $ana->id, 'date' => self::TODAY, 'time_in' => self::TODAY . ' 20:00:00']);
        $this->assertSame($this->night->id, $next->shift_id);
    }

    // ── Change labor type ────────────────────────────────────────────────

    /** The heart of it: last week is not repriced by a labor type changed today. */
    public function test_a_labor_type_change_prices_only_the_days_from_its_date(): void
    {
        $ana = $this->worker('Ana Reyes', ['2026-09-15', '2026-09-16']);

        $tuesday = $this->gross($ana, '2026-09-15');
        $this->assertGreaterThan(0, $tuesday);

        $this->send('labor', [
            'ids' => $this->ids($ana), 'labor_type_id' => $this->mason->id, 'use_rate' => true, 'effective_on' => self::TODAY,
        ])->assertOk()->assertJson(['changed' => 1, 'message' => '1 changed to Bulk Mason']);

        $ana->refresh();
        $this->assertSame($this->mason->id, $ana->labor_type_id);
        $this->assertSame('Bulk Mason', $ana->position);
        $this->assertSame('Bulk Mason', $ana->job_title);
        $this->assertEqualsWithDelta(150.0, $ana->rate_per_hour, 0.001, 'the mason rate, ₱1,200 a day over eight hours');

        // The days before the change: to the centavo what they were.
        $this->assertEqualsWithDelta($tuesday, $this->gross($ana, '2026-09-15'), 0.001);
        $this->assertEqualsWithDelta($tuesday, $this->gross($ana, '2026-09-16'), 0.001);

        // From the date: the mason's rate, half as much again.
        $this->clock($ana, self::TODAY);
        $this->assertEqualsWithDelta($tuesday * 1.5, $this->gross($ana, self::TODAY), 0.011);

        $this->assertSame(
            'Changed the labor type of 1 employee to Bulk Mason at ₱150.00 / hr, effective Sep 17, 2026: Ana Reyes.',
            AuditLog::where('module', 'Employees')->sole()->description
        );
    }

    /** The old type's rate still applies to the old days when the office later changes it. */
    public function test_days_before_a_change_follow_the_type_held_then(): void
    {
        $ana = $this->worker('Ana Reyes', ['2026-09-15']);
        $ben = $this->worker('Ben Cruz', ['2026-09-15']);

        $this->send('labor', ['ids' => $this->ids($ana), 'labor_type_id' => $this->mason->id, 'effective_on' => self::TODAY])->assertOk();

        // Both were laborers that Tuesday, and are still paid alike for it.
        $this->assertEqualsWithDelta($this->gross($ben, '2026-09-15'), $this->gross($ana, '2026-09-15'), 0.001);
    }

    public function test_workers_may_keep_their_own_rate(): void
    {
        $ana     = $this->worker('Ana Reyes', ['2026-09-15']);
        $tuesday = $this->gross($ana, '2026-09-15');

        $this->send('labor', [
            'ids' => $this->ids($ana), 'labor_type_id' => $this->mason->id, 'use_rate' => false, 'effective_on' => self::TODAY,
        ])->assertOk();

        $ana->refresh();
        $this->assertSame($this->mason->id, $ana->labor_type_id, 'a mason by name');
        $this->assertEqualsWithDelta(100.0, $ana->rate_per_hour, 0.001, 'at the rate they had');

        // And payroll pays what the list shows: today as Tuesday.
        $this->clock($ana, self::TODAY);
        $this->assertEqualsWithDelta($tuesday, $this->gross($ana, self::TODAY), 0.001);

        $this->assertStringContainsString('each keeping their own rate', AuditLog::where('module', 'Employees')->sole()->description);

        // Until something sets their rate again — the Edit form fills it from
        // the labor type — and then they are paid as the type is.
        $ana->update(['rate_per_hour' => 150]);
        $this->assertEqualsWithDelta($tuesday * 1.5, $this->gross($ana, self::TODAY), 0.011);
        $this->assertEqualsWithDelta($tuesday, $this->gross($ana, '2026-09-15'), 0.001, 'the days before still as they were');
    }

    // ── From a date ──────────────────────────────────────────────────────

    public function test_no_change_reaches_back_before_today(): void
    {
        $ana = $this->worker('Ana Reyes');

        foreach ([
            'site'  => ['site_id' => $this->siteB->id],
            'shift' => ['shift_id' => $this->night->id],
            'labor' => ['labor_type_id' => $this->mason->id],
        ] as $action => $to) {
            $this->send($action, $to + ['ids' => $this->ids($ana), 'effective_on' => '2026-09-16'])
                ->assertStatus(422)
                ->assertJsonPath('errors.effective_on.0', 'Pick today or a later date. Days already worked are not changed.');
        }

        $this->assertSame(0, EmployeeChange::count());
        $this->assertSame($this->siteA->id, $ana->fresh()->site_id);
    }

    public function test_a_change_dated_ahead_waits_for_its_day(): void
    {
        $ana = $this->worker('Ana Reyes', ['2026-09-15']);

        $this->send('site', ['ids' => $this->ids($ana), 'site_id' => $this->siteB->id, 'effective_on' => '2026-09-21'])
            ->assertOk()
            ->assertJson(['changed' => 1, 'later' => true, 'message' => '1 will move to Site B on Sep 21, 2026']);

        $this->assertSame($this->siteA->id, $ana->fresh()->site_id, 'nothing moves yet');
        $this->assertTrue(EmployeeChange::sole()->isWaiting());

        // Their row says what is coming, with a way to call it off.
        $html = $this->actingAs($this->admin)->get(route('employees.register'))->getContent();
        $this->assertStringContainsString('Site B from Sep 21', $html);
        $this->assertStringContainsString(route('employees.changes.cancel', EmployeeChange::sole()), $html);

        // The Friday before: still waiting.
        Carbon::setTestNow(Carbon::parse('2026-09-18 09:00:00', 'Asia/Manila'));
        $this->actingAs($this->admin)->get(route('employees.register'))->assertOk();
        $this->assertSame($this->siteA->id, $ana->fresh()->site_id);

        // Monday: the first request of the day makes it.
        Carbon::setTestNow(Carbon::parse('2026-09-21 06:00:00', 'Asia/Manila'));
        $this->actingAs($this->admin)->get(route('dashboard'))->assertOk();

        $this->assertSame($this->siteB->id, $ana->fresh()->site_id);
        $this->assertFalse(EmployeeChange::sole()->isWaiting());
        $this->assertStringContainsString('Ana Reyes: moved to Site B, as scheduled by Admin.',
            AuditLog::where('user_name', 'System')->sole()->description);
    }

    public function test_a_labor_type_dated_ahead_prices_from_that_day(): void
    {
        $ana     = $this->worker('Ana Reyes', ['2026-09-15']);
        $tuesday = $this->gross($ana, '2026-09-15');

        $this->send('labor', ['ids' => $this->ids($ana), 'labor_type_id' => $this->mason->id, 'effective_on' => '2026-09-21'])->assertOk();

        // Today is still a laborer's day.
        $this->clock($ana, self::TODAY);
        $this->assertEqualsWithDelta($tuesday, $this->gross($ana, self::TODAY), 0.001);

        // Payroll opened on the Monday, before any page was: the change is
        // made first, and Monday is a mason's day. The days before are not.
        Carbon::setTestNow(Carbon::parse('2026-09-21 18:00:00', 'Asia/Manila'));
        $this->clock($ana, '2026-09-21');

        $this->assertEqualsWithDelta($tuesday * 1.5, $this->gross($ana, '2026-09-21'), 0.011);
        $this->assertSame($this->mason->id, $ana->fresh()->labor_type_id);
        $this->assertEqualsWithDelta($tuesday, $this->gross($ana, self::TODAY), 0.001);
        $this->assertEqualsWithDelta($tuesday, $this->gross($ana, '2026-09-15'), 0.001);
    }

    public function test_a_waiting_change_can_be_cancelled_or_replaced(): void
    {
        $ana = $this->worker('Ana Reyes');

        $this->send('site', ['ids' => $this->ids($ana), 'site_id' => $this->siteB->id, 'effective_on' => '2026-09-21'])->assertOk();
        $waiting = EmployeeChange::sole();

        $this->actingAs($this->admin)->from(route('employees.register'))
            ->delete(route('employees.changes.cancel', $waiting))
            ->assertRedirect(route('employees.register'))
            ->assertSessionHas('success', 'Ana Reyes stays as they are. The change dated Sep 21, 2026 was cancelled.');
        $this->assertSame(0, EmployeeChange::count());

        // A newer decision replaces one still waiting.
        $siteC = Site::firstOrCreate(['name' => 'Site C']);
        $this->send('site', ['ids' => $this->ids($ana), 'site_id' => $this->siteB->id, 'effective_on' => '2026-09-21'])->assertOk();
        $this->send('site', ['ids' => $this->ids($ana), 'site_id' => $siteC->id, 'effective_on' => '2026-09-22'])->assertOk();

        $this->assertSame($siteC->id, EmployeeChange::sole()->to_id);

        // One already made cannot be "cancelled" from the row.
        $this->send('shift', ['ids' => $this->ids($ana), 'shift_id' => $this->night->id, 'effective_on' => self::TODAY])->assertOk();
        $made = EmployeeChange::where('kind', EmployeeChange::SHIFT)->sole();
        $this->actingAs($this->admin)->delete(route('employees.changes.cancel', $made))->assertNotFound();
    }

    // ── Undo ─────────────────────────────────────────────────────────────

    public function test_undo_puts_back_exactly_what_was_there(): void
    {
        $ana = $this->worker('Ana Reyes', ['2026-09-15']);
        $ben = $this->worker('Ben Cruz', [], ['site_id' => null]);
        $this->clock($ana, self::TODAY);

        $asWas   = fn (Employee $e) => $e->fresh()->only(['site_id', 'shift_id', 'labor_type_id', 'rate_per_hour', 'position', 'job_title']);
        $before  = [$asWas($ana), $asWas($ben)];
        $tuesday = $this->gross($ana, '2026-09-15');
        $today   = $this->gross($ana, self::TODAY);

        $batches = [
            $this->send('site',  ['ids' => $this->ids($ana, $ben), 'site_id' => $this->siteB->id, 'effective_on' => self::TODAY])->json('batch'),
            $this->send('shift', ['ids' => $this->ids($ana, $ben), 'shift_id' => $this->night->id, 'effective_on' => self::TODAY])->json('batch'),
            $this->send('labor', ['ids' => $this->ids($ana, $ben), 'labor_type_id' => $this->mason->id, 'effective_on' => self::TODAY])->json('batch'),
        ];
        $this->assertNotSame($before[0], $asWas($ana));
        $this->assertEqualsWithDelta($today * 1.5, $this->gross($ana, self::TODAY), 0.011);

        foreach ($batches as $batch) {
            $this->send('undo', ['batch' => $batch])->assertOk()->assertJson(['success' => true, 'undone' => 2, 'message' => 'Undone for 2 employees']);
        }

        $this->assertSame($before, [$asWas($ana), $asWas($ben)]);
        $this->assertNull($ben->fresh()->site_id, 'no site is put back as no site');
        $this->assertSame(0, EmployeeChange::count());
        $this->assertEqualsWithDelta($tuesday, $this->gross($ana, '2026-09-15'), 0.001);
        $this->assertEqualsWithDelta($today, $this->gross($ana, self::TODAY), 0.001);

        // Pressed twice, the second finds nothing.
        $this->send('undo', ['batch' => $batches[0]])->assertNotFound();
    }

    public function test_undo_leaves_alone_a_worker_changed_again_since(): void
    {
        $ana = $this->worker('Ana Reyes');
        $ben = $this->worker('Ben Cruz');

        $batch = $this->send('site', ['ids' => $this->ids($ana, $ben), 'site_id' => $this->siteB->id, 'effective_on' => self::TODAY])->json('batch');

        $siteC = Site::firstOrCreate(['name' => 'Site C']);
        $ben->update(['site_id' => $siteC->id]);

        $this->send('undo', ['batch' => $batch])->assertOk()
            ->assertJson(['undone' => 1, 'message' => 'Undone for 1 employee; 1 changed again since and left as they are']);

        $this->assertSame($this->siteA->id, $ana->fresh()->site_id);
        $this->assertSame($siteC->id, $ben->fresh()->site_id);
    }

    /** Once a day has gone by on a change, taking it back would reprice that day. */
    public function test_undo_is_for_the_same_day(): void
    {
        $ana   = $this->worker('Ana Reyes');
        $batch = $this->send('labor', ['ids' => $this->ids($ana), 'labor_type_id' => $this->mason->id, 'effective_on' => self::TODAY])->json('batch');

        Carbon::setTestNow(Carbon::parse('2026-09-18 08:00:00', 'Asia/Manila'));

        $this->send('undo', ['batch' => $batch])->assertStatus(422)
            ->assertJson(['message' => 'That change has been in effect since Sep 17, 2026. Change them again from today instead.']);

        $this->assertSame($this->mason->id, $ana->fresh()->labor_type_id);
    }

    // ── Add bonus ────────────────────────────────────────────────────────

    public function test_a_bonus_is_given_to_everybody_ticked(): void
    {
        $ana = $this->worker('Ana Reyes', ['2026-09-15']);
        $ben = $this->worker('Ben Cruz', ['2026-09-15']);
        $net = fn (Employee $e) => (float) collect(app(PayrollService::class)->computeForRange(...self::WEEK)['employees'])
            ->firstWhere('employee_id', $e->id)['totals']['net'];
        $before = $net($ana);

        $this->send('bonus', ['ids' => $this->ids($ana, $ben), 'amount' => 500, 'reason' => 'Perfect attendance', 'period' => 'current'])
            ->assertOk()->assertJson(['success' => true, 'changed' => 2, 'message' => '₱500.00 bonus added to 2 employees']);

        // The grant a worker's own row gives, once for each: so each row
        // counts its own, and can take its own back.
        $this->assertSame(2, Bonus::count());
        foreach ([$ana, $ben] as $e) {
            $bonus = Bonus::whereHas('employees', fn ($q) => $q->whereKey($e->id))->sole();
            $this->assertSame([$e->id], $bonus->employees->pluck('id')->all());
            $this->assertSame(self::TODAY, $bonus->effective_on->toDateString());
            $this->assertSame('Perfect attendance', $bonus->note);
            $this->assertFalse((bool) $bonus->all_employees);
        }
        $this->assertEqualsWithDelta($before + 500, $net($ana), 0.011, 'on top of this week\'s net');

        $html = $this->actingAs($this->admin)->get(route('employees.register'))->getContent();
        $this->assertSame(2, substr_count($html, '<span class="rmx-gift-n">1</span>'), 'each row shows the bonus it was given');

        $this->assertSame(
            'Bonus of ₱500.00 each for 2 employees, pay period Sep 14 – Sep 20, 2026 — Perfect attendance: Ana Reyes, Ben Cruz.',
            AuditLog::where('module', 'Payroll')->where('action', 'created')->sole()->description
        );
        $this->assertSame(0, AuditLog::where('module', 'Payroll Settings')->count(), 'and not a line per bonus beside it');
    }

    public function test_a_bonus_for_the_next_period_is_dated_into_it(): void
    {
        $ana = $this->worker('Ana Reyes');

        $this->send('bonus', ['ids' => $this->ids($ana), 'amount' => 250, 'reason' => 'Holiday', 'period' => 'next'])->assertOk();

        $this->assertSame('2026-09-21', Bonus::sole()->effective_on->toDateString());
    }

    public function test_undo_takes_a_bonus_back(): void
    {
        $ana   = $this->worker('Ana Reyes');
        $ben   = $this->worker('Ben Cruz');
        $batch = $this->send('bonus', ['ids' => $this->ids($ana, $ben), 'amount' => 500, 'reason' => 'Performance', 'period' => 'current'])->json('batch');

        $this->send('undo', ['batch' => $batch])->assertOk()->assertJson(['undone' => 2]);

        $this->assertSame(0, Bonus::count());
        $this->assertSame(0, \DB::table('bonus_employee')->count());
        $this->assertSame(0, EmployeeChange::count());
    }

    public function test_what_a_bonus_refuses(): void
    {
        $ana = $this->worker('Ana Reyes');
        $ask = fn (array $with) => $this->send('bonus', $with + ['ids' => $this->ids($ana), 'amount' => 500, 'reason' => 'Performance', 'period' => 'current']);

        $ask(['amount' => 0])->assertStatus(422)->assertJsonPath('errors.amount.0', 'A bonus of nothing is not a bonus.');
        $ask(['reason' => ''])->assertStatus(422)->assertJsonValidationErrors('reason');
        $ask(['period' => 'last'])->assertStatus(422)->assertJsonValidationErrors('period');

        $this->assertSame(0, Bonus::count());
    }

    // ── Who may ──────────────────────────────────────────────────────────

    /** HR runs the workforce, so HR moves it; money is the administrator's. */
    public function test_hr_changes_the_workforce_but_gives_no_bonus(): void
    {
        $ana = $this->worker('Ana Reyes');
        $hr  = User::create([
            'name' => 'HR', 'username' => 'hr.bulk', 'password' => 'secret123',
            'role' => User::ROLE_HR, 'is_active' => true,
        ]);

        $html = $this->actingAs($hr)->get(route('employees.register'))->assertOk()->getContent();
        $this->assertStringContainsString('data-act="site"', $html);
        $this->assertStringNotContainsString('<button type="button" class="rmx-abtn" data-act="bonus">', $html);

        $this->send('site', ['ids' => $this->ids($ana), 'site_id' => $this->siteB->id, 'effective_on' => self::TODAY], $hr)->assertOk();
        $this->send('bonus', ['ids' => $this->ids($ana), 'amount' => 500, 'reason' => 'Performance', 'period' => 'current'], $hr)->assertForbidden();

        // Nor may HR take back a bonus the administrator gave.
        $batch = $this->send('bonus', ['ids' => $this->ids($ana), 'amount' => 500, 'reason' => 'Performance', 'period' => 'current'])->json('batch');
        $this->send('undo', ['batch' => $batch], $hr)->assertForbidden();
        $this->assertSame(1, Bonus::count());
    }

    public function test_only_the_active_list_is_changed(): void
    {
        $ana     = $this->worker('Ana Reyes');
        $pending = $this->worker('Newly Detected');
        $pending->forceFill(['status' => Employee::STATUS_PENDING])->save();
        $removed = $this->worker('Gone Home');
        $removed->delete();

        $this->send('site', ['ids' => $this->ids($ana, $pending, $removed), 'site_id' => $this->siteB->id, 'effective_on' => self::TODAY])
            ->assertOk()->assertJson(['changed' => 1]);
        $this->send('bonus', ['ids' => $this->ids($ana, $pending, $removed), 'amount' => 100, 'reason' => 'Performance', 'period' => 'current'])
            ->assertOk()->assertJson(['changed' => 1]);

        $this->assertSame($this->siteA->id, $pending->fresh()->site_id);
        $this->assertSame($this->siteA->id, Employee::withTrashed()->find($removed->id)->site_id);
        $this->assertSame(1, Bonus::count());

        // Everybody already there: said, and nothing written.
        $this->send('site', ['ids' => $this->ids($ana), 'site_id' => $this->siteB->id, 'effective_on' => self::TODAY])
            ->assertStatus(422)->assertJson(['message' => 'Everybody selected is already at Site B.']);
    }

    // ── A deploy before its migration ────────────────────────────────────

    /** Code lands on Railway before the migration is run: nothing may fail for want of the table. */
    public function test_payroll_and_the_page_work_before_the_table_exists(): void
    {
        $ana     = $this->worker('Ana Reyes', ['2026-09-15']);
        $tuesday = $this->gross($ana, '2026-09-15');

        Schema::drop('employee_changes');

        $this->assertEqualsWithDelta($tuesday, $this->gross($ana, '2026-09-15'), 0.001);
        $this->actingAs($this->admin)->get(route('employees.register'))->assertOk();
        $this->actingAs($this->admin)->get(route('dashboard'))->assertOk();
    }
}
