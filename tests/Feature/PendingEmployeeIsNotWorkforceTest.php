<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\LaborType;
use App\Models\Shift;
use App\Models\SystemSetting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A pending registration is a name waiting for a finger, not an employee.
 *
 * Until registration is finished they are outside the directory, the counts,
 * attendance and payroll, and outside every dropdown that offers somebody to
 * file leave, a loan or an assignment against. The one place they must appear
 * is the kiosk roster, because that is where the registering happens.
 *
 * The leak this closes is not obvious: the kiosk's own sign-up creates a
 * pending worker who already holds a fingerprint. "We recognise this finger"
 * was therefore never the same question as "this person is registered", and
 * nothing was asking the second one — so a name nobody had accepted could
 * clock in, accrue hours, and be paid for them.
 */
class PendingEmployeeIsNotWorkforceTest extends TestCase
{
    use RefreshDatabase;

    private const NOW = '2026-09-11 09:00:00';

    protected function setUp(): void
    {
        parent::setUp();
        SystemSetting::current()->forceFill(['schedule_rules_from' => '2026-09-01'])->save();
        Carbon::setTestNow(Carbon::parse(self::NOW, 'Asia/Manila'));
    }

    private function employee(string $name, string $status, ?string $finger = null): Employee
    {
        return Employee::create([
            'name'            => $name,
            'status'          => $status,
            'employment_type' => Employee::EMPLOYMENT_DAILY,
            'labor_type_id'   => LaborType::create(['name' => 'Mason ' . $name, 'daily_rate' => 800, 'ot_rate' => 125])->id,
            'shift_id'        => Shift::where('crosses_midnight', false)->firstOrFail()->id,
            'rate_per_hour'   => 100,
            'fingerprint_id'  => $finger,
            // The roster is scoped to the site the kiosk is standing on.
            'site_id'         => \App\Models\Site::orderBy('id')->value('id'),
        ]);
    }

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin', 'username' => 'admin.pending', 'password' => 'secret123',
            'role' => User::ROLE_ADMIN, 'is_admin' => true, 'is_active' => true,
        ]);
    }

    // ── The kiosk will not clock them ────────────────────────────────────

    public function test_a_pending_worker_cannot_time_in(): void
    {
        $emp = $this->employee('Waiting', Employee::STATUS_PENDING);

        $this->postJson('/api/kiosk/attendance', ['employee_id' => $emp->id, 'type' => 'time_in'])
             ->assertOk()
             ->assertJson(['success' => false, 'code' => 'not_registered']);

        $this->assertSame(0, Attendance::where('employee_id', $emp->id)->count(),
            'nothing may be written for a name that is not registered');
    }

    /** The kiosk's own sign-up leaves a pending worker holding a finger. */
    public function test_holding_a_fingerprint_is_not_the_same_as_being_registered(): void
    {
        $emp = $this->employee('Signed Up At The Kiosk', Employee::STATUS_PENDING, 'FP-777');

        $this->postJson('/api/kiosk/clock', ['fingerprint_id' => 'FP-777', 'type' => 'time_in'])
             ->assertOk()
             ->assertJson(['success' => false, 'code' => 'not_registered']);

        $this->assertSame(0, Attendance::where('employee_id', $emp->id)->count());
    }

    public function test_the_scan_says_so_rather_than_offering_a_button(): void
    {
        $this->employee('Signed Up At The Kiosk', Employee::STATUS_PENDING, 'FP-777');

        $this->postJson('/api/kiosk/scan-attendance', ['fingerprint_id' => 'FP-777'])
             ->assertOk()
             ->assertJson(['success' => false, 'code' => 'not_registered']);
    }

    public function test_an_active_worker_is_unaffected(): void
    {
        $emp = $this->employee('Registered', Employee::STATUS_ACTIVE);

        $this->postJson('/api/kiosk/attendance', ['employee_id' => $emp->id, 'type' => 'time_in'])
             ->assertOk()
             ->assertJson(['success' => true]);
    }

    // ── Enrolling the finger is what lets them in ────────────────────────

    public function test_enrolling_the_fingerprint_makes_them_an_employee(): void
    {
        $emp = $this->employee('New Hire', Employee::STATUS_PENDING);

        $this->postJson('/api/kiosk/save-fingerprint', [
            'employee_id' => $emp->id, 'fingerprint_id' => 'FP-123',
        ])->assertOk()->assertJson(['success' => true]);

        $this->assertTrue($emp->fresh()->isActive(), 'registering the finger completes the registration');

        $this->postJson('/api/kiosk/attendance', ['employee_id' => $emp->id, 'type' => 'time_in'])
             ->assertOk()
             ->assertJson(['success' => true]);
    }

    // ── ...but the kiosk still has to list them, or nobody can enrol them ─

    public function test_the_kiosk_roster_still_offers_them_for_enrolment(): void
    {
        $this->employee('Waiting', Employee::STATUS_PENDING);

        $this->getJson('/api/kiosk/roster')
             ->assertOk()
             ->assertJsonFragment(['name' => 'Waiting']);
    }

    // ── Records already on file are left out too ─────────────────────────

    /** Rows recorded before this rule existed must not be counted or paid. */
    public function test_their_attendance_is_kept_out_of_the_board_and_the_payroll(): void
    {
        $pending = $this->employee('Waiting', Employee::STATUS_PENDING);
        $active  = $this->employee('Registered', Employee::STATUS_ACTIVE);

        foreach ([$pending, $active] as $e) {
            Attendance::create([
                'employee_id' => $e->id,
                'date'        => '2026-09-11',
                'session'     => 'AM',
                'time_in'     => '2026-09-11 08:00:00',
                'time_out'    => '2026-09-11 17:00:00',
            ]);
        }

        $page = $this->actingAs($this->admin())->get(route('attendance'))->assertOk();

        $this->assertCount(1, $page->viewData('todayAttendances'));
        $this->assertSame(1, $page->viewData('presentToday'));
        $page->assertDontSee('Waiting');

        $names = collect(app(\App\Services\PayrollService::class)
            ->computeForRange('2026-09-11', '2026-09-11')['days'][0]['details'])
            ->pluck('name');

        $this->assertTrue($names->contains('Registered'));
        $this->assertFalse($names->contains('Waiting'), 'a pending name cannot reach a payslip');
    }

    // ── And nothing can be filed against them ────────────────────────────

    public function test_they_are_offered_nowhere_a_worker_is_chosen(): void
    {
        $this->employee('Waiting', Employee::STATUS_PENDING);
        $this->employee('Registered', Employee::STATUS_ACTIVE);

        $admin = $this->admin();

        foreach (['/leave-advances', '/leave-advances?tab=advances', '/project-assignments'] as $url) {
            $page = $this->actingAs($admin)->get($url)->assertOk();

            $names = collect($page->viewData('employees'))->pluck('name');
            $this->assertTrue($names->contains('Registered'), "{$url} should offer registered workers");
            $this->assertFalse($names->contains('Waiting'), "{$url} must not offer a pending name");
        }
    }

    public function test_the_directory_and_its_counts_leave_them_out(): void
    {
        $this->employee('Waiting', Employee::STATUS_PENDING);
        $this->employee('Registered', Employee::STATUS_ACTIVE);

        $page = $this->actingAs($this->admin())->get('/employees')->assertOk();

        $this->assertSame(['Registered'], collect($page->viewData('employees'))->pluck('name')->all());
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
