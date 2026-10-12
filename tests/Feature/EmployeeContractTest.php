<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\LaborType;
use App\Models\PayrollRate;
use App\Models\Shift;
use App\Models\Site;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\PayrollService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regular or Contractual: how a worker is hired, asked on the first step of
 * Register employee (Michael, 2026-10-12, "add on 1st page and use its
 * functions", with two screenshots of the step).
 *
 *   Regular      Stays on the Active list until removed.
 *   Contractual  Hired for a set number of months. Leaves the Active list
 *                when the contract ends.
 *
 * So a contract is the date hired and a number of months, 1 to 36. Its end
 * is worked out from those two and never typed, and the day after it the
 * worker leaves the Active list by themselves — to Removed, where their
 * records are kept and they can be restored, exactly as if Remove had been
 * pressed. Nothing about pay differs.
 *
 * This is not the Contractual that was removed on 2026-10-03, which carried
 * a contract amount and was left out of payroll. That one is still gone.
 */
class EmployeeContractTest extends TestCase
{
    use RefreshDatabase;

    private const TODAY = '2026-10-12';

    private User $admin;
    private LaborType $mason;
    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse(self::TODAY . ' 09:00:00', 'Asia/Manila'));
        SystemSetting::current()->forceFill(['schedule_rules_from' => '2026-09-01'])->save();
        PayrollRate::create(array_merge(PayrollRate::DEFAULTS, ['effective_from' => '2026-01-01', 'created_by' => 'test']));

        $this->admin = User::create([
            'name' => 'Admin', 'username' => 'admin.contract', 'password' => 'secret123',
            'role' => User::ROLE_ADMIN, 'is_admin' => true, 'is_active' => true,
        ]);
        $this->mason = LaborType::create(['name' => 'Contract Mason', 'daily_rate' => 800, 'ot_rate' => 125]);
        $this->site  = Site::create(['name' => 'Contract Yard']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** What Register employee posts. */
    private function form(array $with = []): array
    {
        return $with + [
            'profile_form'    => '1',
            'employment_type' => Employee::EMPLOYMENT_CONTRACTUAL,
            'date_hired'      => self::TODAY,
            'contract_months' => '6',
            'first_name'      => 'Ana',
            'last_name'       => 'Reyes',
            'labor_type_id'   => $this->mason->id,
            'job_title'       => 'Contract Mason',
            'site_id'         => $this->site->id,
            'shift_id'        => Shift::where('crosses_midnight', false)->value('id'),
            'rate_per_hour'   => '100.00',
        ];
    }

    private function register(array $with = [])
    {
        return $this->actingAs($this->admin)->postJson(route('employees.store'), $this->form($with));
    }

    /** Somebody already on the Active list, on a six-month contract from today unless told otherwise. */
    private function worker(string $name = 'Ana Reyes', array $with = []): Employee
    {
        return Employee::create($with + [
            'name'            => $name,
            'status'          => Employee::STATUS_ACTIVE,
            'employment_type' => Employee::EMPLOYMENT_CONTRACTUAL,
            'date_hired'      => self::TODAY,
            'end_of_contract' => '2027-04-12',
            'labor_type_id'   => $this->mason->id,
            'position'        => 'Contract Mason',
            'site_id'         => $this->site->id,
            'shift_id'        => Shift::where('crosses_midnight', false)->value('id'),
            'rate_per_hour'   => 100,
            'fingerprint_id'  => (string) random_int(1000, 8999),
        ]);
    }

    /** The first request of a day is what looks for finished contracts. */
    private function aDayBegins(string $at): void
    {
        Carbon::setTestNow(Carbon::parse($at, 'Asia/Manila'));
        $this->actingAs($this->admin)->get(route('dashboard'))->assertOk();
    }

    private function onActiveList(Employee $e): bool
    {
        return Employee::active()->whereKey($e->id)->exists();
    }

    // ── Registering ──────────────────────────────────────────────────────

    public function test_a_contract_ends_so_many_months_from_the_date_hired(): void
    {
        $this->register()->assertOk()->assertJson(['success' => true, 'contract_ends' => 'Apr 12, 2027']);

        $worker = Employee::sole();

        $this->assertTrue($worker->isContractual());
        $this->assertSame('2027-04-12', $worker->end_of_contract->toDateString());
        $this->assertSame(6, $worker->contractMonths());
        $this->assertFalse($worker->contractIsOver());
    }

    /** The same day of the month, or the last day of a month too short for it. */
    public function test_a_month_too_short_ends_on_its_last_day(): void
    {
        $this->register(['date_hired' => '2026-08-31', 'contract_months' => '6'])->assertOk();

        $worker = Employee::sole();

        $this->assertSame('2027-02-28', $worker->end_of_contract->toDateString(), 'not some day in March');
        $this->assertSame(6, $worker->contractMonths(), 'and it still reads back as six months');
    }

    public function test_a_regular_worker_has_no_end(): void
    {
        // Months left over from a change of mind are not a contract.
        $this->register(['employment_type' => Employee::EMPLOYMENT_REGULAR, 'contract_months' => '6'])
            ->assertOk()->assertJson(['contract_ends' => null]);

        $worker = Employee::sole();

        $this->assertFalse($worker->isContractual());
        $this->assertNull($worker->end_of_contract);
        $this->assertNull($worker->contractMonths());
    }

    public function test_what_a_contract_refuses(): void
    {
        $this->register(['contract_months' => ''])->assertStatus(422)
            ->assertJsonPath('errors.contract_months.0', 'Say how many months the contract runs.');

        foreach (['0', '37', '-3'] as $months) {
            $this->register(['contract_months' => $months])->assertStatus(422)
                ->assertJsonPath('errors.contract_months.0', 'A contract runs 1 to 36 months.');
        }
        $this->register(['contract_months' => '2.5'])->assertStatus(422)
            ->assertJsonPath('errors.contract_months.0', 'The contract length is a whole number of months.');

        $this->register(['date_hired' => ''])->assertStatus(422)
            ->assertJsonPath('errors.date_hired.0', 'A contract runs from the date hired, so it is needed.');

        // Saved, it would take them off the list on the very next request.
        $this->register(['date_hired' => '2025-01-01', 'contract_months' => '6'])->assertStatus(422)
            ->assertJsonPath('errors.contract_months.0',
                'That contract would already be over: it would have ended on Jul 01, 2025. Give it more months, or check the date hired.');

        $this->register(['employment_type' => 'seasonal'])->assertStatus(422)->assertJsonValidationErrors('employment_type');

        $this->assertSame(0, Employee::count());
    }

    /** A regular worker may still be saved with no date hired, as before. */
    public function test_only_a_contract_needs_the_date_hired(): void
    {
        $this->register(['employment_type' => Employee::EMPLOYMENT_REGULAR, 'date_hired' => ''])->assertOk();

        $this->assertNull(Employee::sole()->date_hired);
    }

    // ── The contract ends ────────────────────────────────────────────────

    public function test_the_worker_leaves_the_active_list_the_day_after_the_contract_ends(): void
    {
        $ana = $this->worker();
        Attendance::create([
            'employee_id' => $ana->id, 'shift_id' => $ana->shift_id, 'date' => self::TODAY, 'session' => 'AM',
            'time_in' => self::TODAY . ' 08:00:00', 'time_out' => self::TODAY . ' 17:00:00',
        ]);

        // Its last day is a day of the contract.
        $this->aDayBegins('2027-04-12 06:00:00');
        $this->assertTrue($this->onActiveList($ana), 'still on the list on the last day');

        $this->aDayBegins('2027-04-13 06:00:00');

        $this->assertFalse($this->onActiveList($ana));
        $this->assertTrue(Employee::removed()->whereKey($ana->id)->exists(), 'in Removed, where they can be restored');

        // Their records are kept.
        $this->assertSame(1, Attendance::where('employee_id', $ana->id)->count());
        $this->assertNotNull(
            collect(app(PayrollService::class)->computeForRange(self::TODAY, self::TODAY)['employees'])->firstWhere('employee_id', $ana->id),
            'the day they worked is still on the payroll it was paid in'
        );

        // Said in the Audit Log as the calendar's doing, and to the admins.
        $this->assertSame(
            'The contract of Ana Reyes ended on Apr 12, 2027. They were moved to Removed; their records are kept.',
            AuditLog::where('user_name', 'System')->where('module', 'Employees')->sole()->description
        );
        $alert = $this->admin->notifications()->get()->firstWhere('data.subtype', 'contract_ended');
        $this->assertNotNull($alert);
        $this->assertSame('The contract of Ana Reyes ended on Apr 12, 2027. They are in Removed now and can be restored from there.', $alert->data['message']);

        // And on the Removed tab.
        $html = $this->actingAs($this->admin)->get(route('employees.register', ['tab' => 'removed']))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/id="rmRemovedBody".*?Ana Reyes/s', $html);
    }

    /** A night crew's last day runs past midnight; removed, they could not clock out of it. */
    public function test_nobody_is_taken_off_the_list_while_still_clocked_in(): void
    {
        $night = Shift::where('crosses_midnight', true)->firstOrFail();
        $ana   = $this->worker('Ana Reyes', ['shift_id' => $night->id]);
        $open  = Attendance::create([
            'employee_id' => $ana->id, 'shift_id' => $night->id, 'date' => '2027-04-12', 'session' => 'AM',
            'time_in' => '2027-04-12 20:00:00',
        ]);

        $this->aDayBegins('2027-04-13 01:00:00');
        $this->assertTrue($this->onActiveList($ana), 'still on shift, still on the list');

        $open->update(['time_out' => '2027-04-13 05:00:00']);
        $this->aDayBegins('2027-04-13 05:05:00');

        $this->assertFalse($this->onActiveList($ana), 'clocked out, and gone on the next look');
    }

    public function test_only_a_finished_contract_takes_anybody_off_the_list(): void
    {
        $regular = $this->worker('Ben Cruz', ['employment_type' => Employee::EMPLOYMENT_REGULAR, 'end_of_contract' => null]);
        $longer  = $this->worker('Cy Dizon', ['end_of_contract' => '2028-10-12']);
        // Somebody the kiosk has not enrolled yet is not on the Active list to leave it.
        $pending = $this->worker('Dan Estrada', ['status' => Employee::STATUS_PENDING, 'fingerprint_id' => null]);
        // A row left from before 2026-10-03, contractual in name and with no end, is a worker like any other.
        $old     = $this->worker('Ed Flores', ['end_of_contract' => null]);

        $this->aDayBegins('2027-04-13 06:00:00');

        $this->assertTrue($this->onActiveList($regular));
        $this->assertTrue($this->onActiveList($longer));
        $this->assertTrue($this->onActiveList($old));
        $this->assertTrue(Employee::pending()->whereKey($pending->id)->exists());
        $this->assertSame(0, Employee::removed()->count());
    }

    /** Nothing about pay differs: a contract is how long, not how much. */
    public function test_a_contractual_worker_is_paid_like_anybody(): void
    {
        $ana = $this->worker('Ana Reyes');
        $ben = $this->worker('Ben Cruz', ['employment_type' => Employee::EMPLOYMENT_REGULAR, 'end_of_contract' => null]);

        foreach ([$ana, $ben] as $e) {
            Attendance::create([
                'employee_id' => $e->id, 'shift_id' => $e->shift_id, 'date' => self::TODAY, 'session' => 'AM',
                'time_in' => self::TODAY . ' 08:00:00', 'time_out' => self::TODAY . ' 17:00:00',
            ]);
        }

        $rows = collect(app(PayrollService::class)->computeForRange(self::TODAY, self::TODAY)['employees'])->keyBy('employee_id');

        $this->assertGreaterThan(0, $rows[$ana->id]['totals']['gross']);
        $this->assertEqualsWithDelta($rows[$ben->id]['totals']['gross'], $rows[$ana->id]['totals']['gross'], 0.001);
        $this->assertEqualsWithDelta($rows[$ben->id]['totals']['net'], $rows[$ana->id]['totals']['net'], 0.001);
    }

    // ── Coming back ──────────────────────────────────────────────────────

    /** Restored, they stay restored: the contract that ran out does not come back with them. */
    public function test_a_worker_restored_after_their_contract_comes_back_as_regular(): void
    {
        $ana = $this->worker();
        $this->aDayBegins('2027-04-13 06:00:00');
        $this->assertFalse($this->onActiveList($ana));

        $this->actingAs($this->admin)->from(route('employees.register', ['tab' => 'removed']))
            ->patch(route('employees.restore', $ana->id))
            ->assertSessionHas('success',
                'Ana Reyes was restored as a regular employee. Their contract ended on Apr 12, 2027; set a new one from Edit if they are back on contract.');

        $ana->refresh();
        $this->assertFalse($ana->isContractual());
        $this->assertNull($ana->end_of_contract);

        // And is not taken off again by the next look, today or after.
        $this->aDayBegins('2027-04-13 09:00:00');
        $this->aDayBegins('2027-04-14 06:00:00');
        $this->assertTrue($this->onActiveList($ana));
    }

    public function test_restoring_several_does_the_same_and_leaves_a_running_contract_alone(): void
    {
        $over    = $this->worker('Ana Reyes');
        $running = $this->worker('Ben Cruz', ['end_of_contract' => '2028-10-12']);

        $this->aDayBegins('2027-04-13 06:00:00');
        $running->delete();   // removed by hand, with a year and a half still to run

        $this->actingAs($this->admin)->patchJson(route('employees.bulk-restore'), ['ids' => [$over->id, $running->id]])
            ->assertOk()->assertJson(['restored' => 2]);

        $this->assertFalse($over->fresh()->isContractual());
        $this->assertTrue($running->fresh()->isContractual(), 'a contract still running is still their contract');
        $this->assertSame('2028-10-12', $running->fresh()->end_of_contract->toDateString());
    }

    // ── Edit Employee ────────────────────────────────────────────────────

    /** More months on Edit is how a contract is extended. */
    public function test_a_contract_is_extended_or_ended_from_edit(): void
    {
        $ana  = $this->worker();
        $edit = fn (array $with) => $this->actingAs($this->admin)->put(route('employees.update', $ana->id), $this->form($with));

        $html = $this->actingAs($this->admin)->get(route('employees.edit', $ana->id))->assertOk()->getContent();
        $this->assertStringContainsString('<option value="contractual" selected>Contractual</option>', $html);
        $this->assertMatchesRegularExpression('/name="contract_months"[^>]*\n?\s*value="6"/', $html);
        $this->assertMatchesRegularExpression('/id="contract_ends"[^>]*\n?\s*value="Apr 12, 2027"/', $html);

        $edit(['contract_months' => '12'])->assertSessionHasNoErrors();
        $this->assertSame('2027-10-12', $ana->fresh()->end_of_contract->toDateString());

        // Made a regular worker: on the list until somebody removes them.
        $edit(['employment_type' => Employee::EMPLOYMENT_REGULAR])->assertSessionHasNoErrors();
        $this->assertFalse($ana->fresh()->isContractual());
        $this->assertNull($ana->fresh()->end_of_contract);

        // And a contract that would already be over is refused here too.
        $edit(['date_hired' => '2025-01-01', 'contract_months' => '3'])->assertSessionHasErrors('contract_months');
        $this->assertFalse($ana->fresh()->isContractual());
    }

    /** The quick-edit modal says nothing about how they were hired, and changes nothing about it. */
    public function test_a_save_that_does_not_ask_leaves_the_contract_alone(): void
    {
        $ana = $this->worker();

        $this->actingAs($this->admin)->put(route('employees.update', $ana->id), [
            'first_name' => 'Ana', 'last_name' => 'Reyes', 'labor_type_id' => $this->mason->id, 'rate_per_hour' => '100.00', 'site_id' => $this->site->id,
        ])->assertSessionHasNoErrors();

        $this->assertTrue($ana->fresh()->isContractual());
        $this->assertSame('2027-04-12', $ana->fresh()->end_of_contract->toDateString());
    }

    // ── Where it shows ───────────────────────────────────────────────────

    public function test_the_list_says_who_is_on_contract_and_until_when(): void
    {
        $ana = $this->worker('Ana Reyes');
        $ben = $this->worker('Ben Cruz', ['employment_type' => Employee::EMPLOYMENT_REGULAR, 'end_of_contract' => null]);

        $row = fn (Employee $e, string $html) => \Illuminate\Support\Str::between($html, 'data-live-key="emp-' . $e->id . '"', '</tr>');

        $html = $this->actingAs($this->admin)->get(route('employees.register'))->assertOk()->getContent();

        $this->assertStringContainsString('Contract until Apr 12, 2027', $row($ana, $html));
        $this->assertMatchesRegularExpression('/class="rmx-pill\s+rmx-contract"/', $row($ana, $html), 'plainly, with months still to run');
        $this->assertStringNotContainsString('rmx-contract"', $row($ben, $html), 'a regular worker carries no tag');
        // As the card's data carries it: json_encode writes the dot as an escape.
        $this->assertStringContainsString(trim(json_encode('Contractual · until Apr 12, 2027'), '"'), $row($ana, $html), 'and their card says so');
        $this->assertStringContainsString('&quot;hiredAs&quot;:&quot;Regular&quot;', $row($ben, $html));

        // Amber in its last thirty days, so a renewal is not a surprise.
        Carbon::setTestNow(Carbon::parse('2027-03-20 09:00:00', 'Asia/Manila'));
        $html = $this->actingAs($this->admin)->get(route('employees.register'))->getContent();
        $this->assertMatchesRegularExpression('/class="rmx-pill rmx-pill-warn rmx-contract"/', $row($ana, $html));

        // The profile says it as well.
        $this->actingAs($this->admin)->get(route('employees.show', $ana->id))->assertOk()
            ->assertSeeInOrder(['Employment Type', 'Contractual', 'until Apr 12, 2027']);
        $this->actingAs($this->admin)->get(route('employees.show', $ben->id))->assertOk()
            ->assertSeeInOrder(['Employment Type', 'Regular']);
    }
}
