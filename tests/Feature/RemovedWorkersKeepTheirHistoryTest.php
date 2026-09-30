<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\LaborType;
use App\Models\Shift;
use App\Models\Site;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\PayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Deleting a worker takes them off the lists, never out of the history
 * (Michael, 2026-09-30). The days they worked and the pay those days earned
 * happened. Past weeks cannot change because somebody left afterwards.
 *
 * Remove puts a worker in the Removed tab, where they can be restored.
 * Delete permanently takes them out of the Removed tab for good and frees
 * their finger for somebody else, but their row stays, so the attendance and
 * payroll they earned keep their name. Before this, Remove quietly dropped
 * the worker out of the payroll figures, and Delete permanently erased their
 * attendance along with every payroll line tied to it.
 */
class RemovedWorkersKeepTheirHistoryTest extends TestCase
{
    use RefreshDatabase;

    private Shift $day;
    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-15 10:00:00'));
        SystemSetting::current()->forceFill(['schedule_rules_from' => '2026-09-01'])->save();

        $this->site = Site::firstOrCreate(['name' => 'Site A']);
        $this->day  = Shift::where('crosses_midnight', false)->firstOrFail();
        $this->day->forceFill(Shift::layOut('08:00', '17:00', '12:00', '13:00') + ['regular_minutes' => 480])->save();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private ?User $admin = null;

    private function admin(): User
    {
        return $this->admin ??= User::create([
            'name'      => 'Admin',
            'username'  => 'admin.history',
            'password'  => Hash::make('secret123'),
            'is_admin'  => true,
            'is_active' => true,
        ]);
    }

    private function worker(string $name, string $status = Employee::STATUS_ACTIVE, ?string $finger = '7'): Employee
    {
        return Employee::create([
            'name'            => $name,
            'position'        => 'Mason',
            'status'          => $status,
            'employment_type' => Employee::EMPLOYMENT_DAILY,
            'labor_type_id'   => LaborType::firstOrCreate(['name' => 'Mason'], ['daily_rate' => 800, 'ot_rate' => 125])->id,
            'shift_id'        => $this->day->id,
            'site_id'         => $this->site->id,
            'rate_per_hour'   => 100,
            'fingerprint_id'  => $finger,
        ]);
    }

    /** In at eight, out for lunch, back at one, home at five. */
    private function fullDay(Employee $e, string $date): void
    {
        foreach ([['AM', '08:00:00', '12:00:00'], ['PM', '13:00:00', '17:00:00']] as [$session, $in, $out]) {
            Attendance::create([
                'employee_id' => $e->id, 'site_id' => $this->site->id, 'shift_id' => $e->shift_id,
                'date' => $date, 'session' => $session, 'time_in' => $in, 'time_out' => $out,
            ]);
        }
    }

    private function paid(Employee $e): ?array
    {
        return collect(app(PayrollService::class)->computeForRange('2026-09-10', '2026-09-10')['employees'])
            ->firstWhere('employee_id', $e->id);
    }

    private function historyNames(): array
    {
        return $this->actingAs($this->admin())
            ->get(route('attendance', ['tab' => 'history']))
            ->assertOk()
            ->viewData('historyDays')
            ->map(fn ($d) => $d->employee()?->name)
            ->all();
    }

    private function remove(Employee $e): void
    {
        $this->actingAs($this->admin())->delete(route('employees.destroy', $e->id))->assertRedirect();
    }

    private function deleteForGood(Employee $e): void
    {
        $this->actingAs($this->admin())->delete(route('employees.force-delete', $e->id))->assertRedirect();
    }

    // ── Remove ───────────────────────────────────────────────────────────

    public function test_a_removed_workers_past_pay_is_still_in_the_payroll(): void
    {
        $emp = $this->worker('Juan Dela Cruz');
        $this->fullDay($emp, '2026-09-10');
        $before = $this->paid($emp);

        $this->remove($emp);
        $after = $this->paid($emp);

        $this->assertNotNull($after, 'a worker removed today still worked last Thursday');
        $this->assertEqualsWithDelta(8.0, $after['totals']['hours'], 0.01);
        $this->assertEqualsWithDelta($before['totals']['gross'], $after['totals']['gross'], 0.01,
            'and last week costs what it cost before they left');
    }

    public function test_a_removed_workers_days_stay_in_the_attendance_history(): void
    {
        $emp = $this->worker('Juan Dela Cruz');
        $this->fullDay($emp, '2026-09-10');

        $this->remove($emp);

        $this->assertSame(['Juan Dela Cruz'], $this->historyNames());
    }

    public function test_payroll_records_still_list_a_removed_worker(): void
    {
        $emp = $this->worker('Juan Dela Cruz');
        $this->fullDay($emp, '2026-09-10');

        $this->remove($emp);

        $rows = $this->actingAs($this->admin())
            ->get(route('payroll-records', ['mode' => 'daily', 'date' => '2026-09-10']))
            ->assertOk()
            ->viewData('employees');

        $this->assertSame([$emp->id], array_column($rows, 'employee_id'));
    }

    // ── Delete permanently ───────────────────────────────────────────────

    public function test_deleting_for_good_keeps_the_attendance_and_the_pay(): void
    {
        $emp = $this->worker('Juan Dela Cruz');
        $this->fullDay($emp, '2026-09-10');
        $gross = $this->paid($emp)['totals']['gross'];

        $this->remove($emp);
        $this->deleteForGood($emp);

        $this->assertSame(2, Attendance::where('employee_id', $emp->id)->count(), 'both stretches are kept');
        $this->assertEqualsWithDelta($gross, $this->paid($emp)['totals']['gross'], 0.01);
        $this->assertSame(['Juan Dela Cruz'], $this->historyNames(), 'still under their own name');
    }

    public function test_deleted_for_good_is_off_the_removed_list_and_cannot_come_back(): void
    {
        $emp = $this->worker('Juan Dela Cruz');
        $this->remove($emp);
        $this->deleteForGood($emp);

        $removed = $this->actingAs($this->admin())->get(route('employees.register'))->assertOk()->viewData('removed');
        $this->assertCount(0, $removed);

        $this->actingAs($this->admin())->patch(route('employees.restore', $emp->id))->assertNotFound();
        $this->actingAs($this->admin())->patchJson(route('employees.bulk-restore'), ['ids' => [$emp->id]])
            ->assertOk()->assertJson(['restored' => 0]);

        $this->assertTrue(Employee::withTrashed()->find($emp->id)->trashed());
    }

    public function test_deleting_for_good_frees_the_finger(): void
    {
        // The kiosk brings a removed worker back when their finger is read.
        // Deleted for good, the finger belongs to nobody: the kiosk does not
        // know it, and the next worker can be enrolled on it.
        $emp = $this->worker('Juan Dela Cruz', finger: '7');
        $this->remove($emp);
        $this->deleteForGood($emp);

        $this->assertFalse(Employee::withTrashed()->where('fingerprint_id', '7')->exists());
        $this->assertNotNull($this->worker('Pedro Penduko', finger: '7')->id);
    }

    public function test_deleting_several_for_good_keeps_their_history_too(): void
    {
        $a = $this->worker('Juan Dela Cruz', finger: '7');
        $b = $this->worker('Pedro Penduko', finger: '8');
        $this->fullDay($a, '2026-09-10');
        $this->fullDay($b, '2026-09-10');
        $this->remove($a);
        $this->remove($b);

        $this->actingAs($this->admin())->deleteJson(route('employees.bulk-force-delete'), ['ids' => [$a->id, $b->id]])
            ->assertOk()->assertJson(['deleted' => 2]);

        $this->assertSame(4, Attendance::count());
        $this->assertEqualsCanonicalizing(['Juan Dela Cruz', 'Pedro Penduko'], $this->historyNames());
        $this->assertCount(0, $this->actingAs($this->admin())->get(route('employees.register'))->viewData('removed'));
    }

    public function test_a_pending_worker_deleted_for_good_really_goes(): void
    {
        // A pending worker was never on the payroll and their scans are shown
        // nowhere, so there is no history to keep. Their row goes.
        $emp = $this->worker('Kiosk Walk-in', Employee::STATUS_PENDING);
        $this->fullDay($emp, '2026-09-10');
        $this->remove($emp);
        $this->deleteForGood($emp);

        $this->assertNull(Employee::withTrashed()->find($emp->id));
        $this->assertSame(0, Attendance::count());
    }

    public function test_the_confirmation_says_the_records_are_kept(): void
    {
        $emp = $this->worker('Juan Dela Cruz');
        $this->remove($emp);

        $this->actingAs($this->admin())->get(route('employees.register', ['tab' => 'removed']))
            ->assertOk()
            ->assertDontSee('every attendance record they have')
            ->assertDontSee('Their attendance history and photos go too');
    }
}
