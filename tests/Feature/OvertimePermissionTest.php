<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\EmployeeChange;
use App\Models\LaborType;
use App\Models\PayrollRate;
use App\Models\Shift;
use App\Models\Site;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\PayrollService;
use App\Support\OvertimePermission;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Allow overtime, on the bar under a selection on Employees (Michael,
 * 2026-10-12): "the chosen employees na allow, only sila lang yung
 * makakapag take ng overtime — kahit lumampas pa ng 8 hours yung ibang
 * employee, pag di naka on or allow is di recorded OT nila."
 *
 * So the office chooses who may take overtime. For a worker who is not
 * allowed, the hours past their regular hours are not recorded: they are
 * neither overtime nor pay.
 *
 * Two things keep that from reaching into what was already paid, which is
 * the rule under every action on that bar:
 *
 *   - There is no rule until somebody is allowed. Overtime was counted for
 *     whoever worked it, and it still is, up to the day the first allowance
 *     takes effect. Nothing changed for anybody on the day this was built.
 *   - Every change is dated, from today at the earliest. A day is counted by
 *     what was allowed on that day.
 */
class OvertimePermissionTest extends TestCase
{
    use RefreshDatabase;

    private const TUESDAY = '2026-09-15';
    private const TODAY   = '2026-09-17';   // Thursday
    private const FRIDAY  = '2026-09-18';

    private User $admin;
    private LaborType $mason;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse(self::TODAY . ' 06:00:00', 'Asia/Manila'));
        SystemSetting::current()->forceFill(['schedule_rules_from' => '2026-09-01', 'auto_count_overtime' => true])->save();
        PayrollRate::create(array_merge(PayrollRate::DEFAULTS, ['effective_from' => '2026-01-01', 'created_by' => 'test']));

        $this->admin = User::create([
            'name' => 'Admin', 'username' => 'admin.overtime', 'password' => 'secret123',
            'role' => User::ROLE_ADMIN, 'is_admin' => true, 'is_active' => true,
        ]);
        $this->mason = LaborType::create(['name' => 'Overtime Mason', 'daily_rate' => 800, 'ot_rate' => 125]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** On the day shift, eight to five with an hour for lunch: eight paid hours. */
    private function worker(string $name): Employee
    {
        return Employee::create([
            'name'            => $name,
            'status'          => Employee::STATUS_ACTIVE,
            'employment_type' => Employee::EMPLOYMENT_DAILY,
            'labor_type_id'   => $this->mason->id,
            'position'        => 'Overtime Mason',
            'site_id'         => Site::firstOrCreate(['name' => 'Site A'])->id,
            'shift_id'        => Shift::where('crosses_midnight', false)->orderBy('id')->firstOrFail()->id,
            'rate_per_hour'   => 100,
            'fingerprint_id'  => (string) random_int(1000, 8999),
        ]);
    }

    /** A long day: in at eight, out at seven. Eight regular hours and two past them. */
    private function longDay(Employee $e, string $date): void
    {
        foreach ([['AM', '08:00:00', '12:00:00'], ['PM', '13:00:00', '19:00:00']] as [$session, $in, $out]) {
            Attendance::create([
                'employee_id' => $e->id, 'shift_id' => $e->shift_id, 'site_id' => $e->site_id, 'date' => $date, 'session' => $session,
                'time_in' => "{$date} {$in}", 'time_out' => "{$date} {$out}",
            ]);
        }
    }

    /** One worker's figures for a day, as every payroll screen computes them. */
    private function day(Employee $e, string $date): array
    {
        return collect(app(PayrollService::class)->computeForRange($date, $date)['employees'])
            ->firstWhere('employee_id', $e->id)['totals'];
    }

    private function send(array $body, ?User $as = null)
    {
        return $this->actingAs($as ?? $this->admin)->postJson(route('employees.bulk-overtime'), $body);
    }

    private function allow(Employee ...$workers)
    {
        return $this->send(['ids' => array_map(fn ($e) => $e->id, $workers), 'allow' => true, 'effective_on' => now()->toDateString()]);
    }

    // ── Before anybody is chosen ─────────────────────────────────────────

    /** Nothing changed for anybody when this was built: there is no rule until somebody is allowed. */
    public function test_until_somebody_is_allowed_overtime_is_counted_for_everybody(): void
    {
        $ana = $this->worker('Ana Reyes');
        $this->longDay($ana, self::TUESDAY);
        $this->longDay($ana, self::TODAY);

        $this->assertFalse($ana->fresh()->overtime_allowed, 'off for everybody to begin with');
        $this->assertNull(OvertimePermission::load()->startsOn());

        foreach ([self::TUESDAY, self::TODAY] as $date) {
            $this->assertGreaterThan(0, $this->day($ana, $date)['overtime'], "overtime is paid on {$date} as it always was");
            $this->assertSame(600, $this->day($ana, $date)['minutes'], 'all ten hours');
        }
    }

    // ── Choosing ─────────────────────────────────────────────────────────

    /** The heart of it: only the workers allowed take overtime, from the day the first of them is. */
    public function test_only_the_workers_allowed_take_overtime(): void
    {
        $ana = $this->worker('Ana Reyes');
        $ben = $this->worker('Ben Cruz');
        foreach ([$ana, $ben] as $e) {
            $this->longDay($e, self::TUESDAY);
            $this->longDay($e, self::TODAY);
        }
        $tuesday = $this->day($ben, self::TUESDAY);

        $this->allow($ana)->assertOk()->assertJson([
            'success' => true, 'changed' => 1, 'skipped' => 0, 'later' => false,
            'message' => '1 allowed to take overtime', 'overtime_rule' => self::TODAY,
        ]);

        $this->assertTrue($ana->fresh()->overtime_allowed);
        $this->assertFalse($ben->fresh()->overtime_allowed);

        $anaToday = $this->day($ana, self::TODAY);
        $benToday = $this->day($ben, self::TODAY);

        // Ana worked ten hours and is paid for ten, two of them overtime.
        $this->assertGreaterThan(0, $anaToday['overtime']);
        $this->assertSame(600, $anaToday['minutes']);

        // Ben worked the same ten. The two past his regular eight are not
        // recorded: not overtime, not hours, not pay.
        $this->assertEqualsWithDelta(0.0, $benToday['overtime'], 0.001);
        $this->assertSame(480, $benToday['minutes']);
        $this->assertEqualsWithDelta($anaToday['gross'] - $anaToday['overtime'], $benToday['gross'], 0.011, 'his eight regular hours, and nothing else');

        // The day before the choosing began is what it was, for both of them.
        $this->assertEquals($tuesday, $this->day($ben, self::TUESDAY));
        $this->assertGreaterThan(0, $this->day($ben, self::TUESDAY)['overtime']);

        // On file as a dated change, and in the Audit Log as one line.
        $change = EmployeeChange::where('kind', EmployeeChange::OVERTIME)->sole();
        $this->assertSame([$ana->id, 1, 0], [$change->employee_id, (int) $change->to_id, (int) $change->from_id]);
        $this->assertSame(
            'Allowed overtime for 1 employee, effective Sep 17, 2026: Ana Reyes.',
            AuditLog::where('module', 'Employees')->sole()->description
        );
    }

    /** Stopped from a date, a worker keeps the overtime of the days before it. */
    public function test_stopping_overtime_is_for_the_days_to_come(): void
    {
        $ana = $this->worker('Ana Reyes');
        $this->longDay($ana, self::TODAY);
        $this->longDay($ana, self::FRIDAY);
        $this->allow($ana)->assertOk();

        Carbon::setTestNow(Carbon::parse(self::FRIDAY . ' 06:00:00', 'Asia/Manila'));

        $this->send(['ids' => [$ana->id], 'allow' => false, 'effective_on' => self::FRIDAY])
            ->assertOk()->assertJson(['changed' => 1, 'message' => '1 no longer take overtime', 'overtime_rule' => self::TODAY]);

        $this->assertFalse($ana->fresh()->overtime_allowed);
        $this->assertGreaterThan(0, $this->day($ana, self::TODAY)['overtime'], 'Thursday was allowed, and stays paid');
        $this->assertEqualsWithDelta(0.0, $this->day($ana, self::FRIDAY)['overtime'], 0.001);
        $this->assertSame(480, $this->day($ana, self::FRIDAY)['minutes']);
    }

    public function test_an_allowance_dated_ahead_begins_on_its_day(): void
    {
        $ana = $this->worker('Ana Reyes');
        $ben = $this->worker('Ben Cruz');
        foreach ([$ana, $ben] as $e) {
            $this->longDay($e, self::TODAY);
            $this->longDay($e, '2026-09-21');
        }

        $this->send(['ids' => [$ana->id], 'allow' => true, 'effective_on' => '2026-09-21'])
            ->assertOk()->assertJson(['later' => true, 'message' => '1 will be allowed overtime from Sep 21, 2026', 'overtime_rule' => null]);

        // Nothing has begun: today is still counted for everybody.
        $this->assertFalse($ana->fresh()->overtime_allowed);
        $this->assertGreaterThan(0, $this->day($ben, self::TODAY)['overtime']);

        // The row says what is coming.
        $html = $this->actingAs($this->admin)->get(route('employees.register'))->getContent();
        $this->assertStringContainsString('Overtime allowed from Sep 21', $html);

        // On the day: Ana may, Ben may not, and Thursday is as it was.
        Carbon::setTestNow(Carbon::parse('2026-09-21 20:00:00', 'Asia/Manila'));

        $this->assertGreaterThan(0, $this->day($ana, '2026-09-21')['overtime']);
        $this->assertTrue($ana->fresh()->overtime_allowed, 'payroll made the change that had come due before pricing anything');
        $this->assertEqualsWithDelta(0.0, $this->day($ben, '2026-09-21')['overtime'], 0.001);
        $this->assertGreaterThan(0, $this->day($ben, self::TODAY)['overtime']);
    }

    /** Undo puts back exactly what was there — the rule itself, when it was the first allowance. */
    public function test_undoing_the_first_allowance_takes_the_rule_back_with_it(): void
    {
        $ana = $this->worker('Ana Reyes');
        $ben = $this->worker('Ben Cruz');
        $this->longDay($ben, self::TODAY);
        $before = $this->day($ben, self::TODAY);

        $batch = $this->allow($ana)->json('batch');
        $this->assertEqualsWithDelta(0.0, $this->day($ben, self::TODAY)['overtime'], 0.001);

        $this->actingAs($this->admin)->postJson(route('employees.bulk-undo'), ['batch' => $batch])
            ->assertOk()->assertJson(['undone' => 1, 'overtime_rule' => null]);

        $this->assertFalse($ana->fresh()->overtime_allowed);
        $this->assertSame(0, EmployeeChange::count());
        $this->assertEquals($before, $this->day($ben, self::TODAY), 'and Ben has his overtime again');
    }

    public function test_what_it_skips_and_refuses(): void
    {
        $ana = $this->worker('Ana Reyes');
        $ben = $this->worker('Ben Cruz');

        // Nobody is held back yet, so there is nobody to stop.
        $this->send(['ids' => [$ana->id], 'allow' => false, 'effective_on' => self::TODAY])
            ->assertStatus(422)->assertJson(['message' => 'Nobody selected is allowed overtime, so there is nothing to stop.']);

        $this->allow($ana)->assertOk();

        // Allowed already is left as it is, and counted.
        $this->allow($ana, $ben)->assertOk()->assertJson(['changed' => 1, 'skipped' => 1]);
        $this->allow($ana, $ben)->assertStatus(422)->assertJson(['message' => 'Everybody selected is already allowed overtime.']);

        // No day already worked is reached.
        $this->send(['ids' => [$ana->id], 'allow' => false, 'effective_on' => self::TUESDAY])
            ->assertStatus(422)
            ->assertJsonPath('errors.effective_on.0', 'Pick today or a later date. Days already worked are not changed.');

        $this->send(['ids' => [$ana->id], 'effective_on' => self::TODAY])->assertStatus(422)->assertJsonValidationErrors('allow');

        $this->assertSame(2, EmployeeChange::where('kind', EmployeeChange::OVERTIME)->count());
    }

    /** The office-wide switch still stands above it: off, nobody takes overtime, allowed or not. */
    public function test_the_office_switch_still_comes_first(): void
    {
        $ana = $this->worker('Ana Reyes');
        $this->longDay($ana, self::TODAY);
        $this->allow($ana)->assertOk();
        $this->assertGreaterThan(0, $this->day($ana, self::TODAY)['overtime']);

        SystemSetting::current()->forceFill(['auto_count_overtime' => false])->save();

        $this->assertEqualsWithDelta(0.0, $this->day($ana, self::TODAY)['overtime'], 0.001);
    }

    // ── Where it shows ───────────────────────────────────────────────────

    public function test_the_page_offers_it_and_says_who_is_allowed(): void
    {
        $ana = $this->worker('Ana Reyes');
        $ben = $this->worker('Ben Cruz');
        $row = fn (Employee $e, string $html) => \Illuminate\Support\Str::between($html, 'data-live-key="emp-' . $e->id . '"', '</tr>');

        $html = $this->actingAs($this->admin)->get(route('employees.register'))->assertOk()->getContent();

        // On the bar, between Change labor type and Add bonus.
        $this->assertMatchesRegularExpression('/data-act="labor">.*?data-act="overtime"><i class="ti ti-clock-plus" aria-hidden="true"><\/i>Allow overtime<\/button>.*?data-act="bonus">/s', $html);
        $this->assertStringContainsString('"overtime":' . json_encode(route('employees.bulk-overtime')), $html);
        $this->assertStringContainsString('"overtimeRule":null', $html, 'nobody has been chosen yet');
        $this->assertStringContainsString('data-ot="0"', $row($ana, $html));
        $this->assertStringNotContainsString('rmx-ot"', $row($ana, $html));

        $this->allow($ana)->assertOk();
        $html = $this->actingAs($this->admin)->get(route('employees.register'))->getContent();

        $this->assertStringContainsString('"overtimeRule":"' . self::TODAY . '"', $html);
        $this->assertStringContainsString('data-ot="1"', $row($ana, $html));
        $this->assertMatchesRegularExpression('/class="rmx-pill rmx-pill-ok rmx-ot"[^>]*>\s*<i[^>]*><\/i>Overtime allowed/', $row($ana, $html));
        $this->assertStringContainsString('data-ot="0"', $row($ben, $html));
        $this->assertStringNotContainsString('rmx-ot"', $row($ben, $html));

        // Their cards say so, now that there is something to say.
        $this->assertStringContainsString('&quot;overtime&quot;:&quot;Allowed&quot;', $row($ana, $html));
        $this->assertStringContainsString('&quot;overtime&quot;:&quot;Not allowed&quot;', $row($ben, $html));
    }

    /** HR runs the workforce, so HR chooses who may. */
    public function test_hr_chooses_too(): void
    {
        $ana = $this->worker('Ana Reyes');
        $hr  = User::create([
            'name' => 'HR', 'username' => 'hr.overtime', 'password' => 'secret123',
            'role' => User::ROLE_HR, 'is_active' => true,
        ]);

        $this->assertStringContainsString('data-act="overtime"', $this->actingAs($hr)->get(route('employees.register'))->assertOk()->getContent());
        $this->send(['ids' => [$ana->id], 'allow' => true, 'effective_on' => self::TODAY], $hr)->assertOk();
        $this->assertTrue($ana->fresh()->overtime_allowed);
    }

    /** The kiosk shows overtime only for somebody payroll will record it for. */
    public function test_the_kiosk_board_shows_overtime_only_for_the_workers_allowed(): void
    {
        $ana = $this->worker('Ana Reyes');
        $ben = $this->worker('Ben Cruz');
        $this->longDay($ana, self::TODAY);
        $this->longDay($ben, self::TODAY);
        $this->allow($ana)->assertOk();

        Carbon::setTestNow(Carbon::parse(self::TODAY . ' 19:30:00', 'Asia/Manila'));

        $this->getJson('/api/kiosk/today-attendance')->assertOk()
            ->assertJsonFragment(['employee_id' => $ana->id, 'total_hours' => 8.0, 'overtime_hours' => 2.0])
            ->assertJsonFragment(['employee_id' => $ben->id, 'total_hours' => 8.0, 'overtime_hours' => 0]);
    }

    // ── A deploy before its migrations ───────────────────────────────────

    /** Code lands on Railway before the migration is run: until then there is no rule, and nothing fails. */
    public function test_payroll_works_before_the_record_of_changes_exists(): void
    {
        $ana = $this->worker('Ana Reyes');
        $this->longDay($ana, self::TODAY);
        $before = $this->day($ana, self::TODAY);

        Schema::drop('employee_changes');

        $this->assertNull(OvertimePermission::load()->startsOn());
        $this->assertEquals($before, $this->day($ana, self::TODAY));
        $this->getJson('/api/kiosk/today-attendance')->assertOk();
    }
}
