<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\LaborType;
use App\Models\Shift;
use App\Models\Site;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Payroll Records narrows to one site's crew — or shows every site as a
 * whole — and the register and the batch of payslips follow it.
 */
class PayrollRecordsSiteFilterTest extends TestCase
{
    use RefreshDatabase;

    private Site $a;
    private Site $b;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-09-18 18:00:00', 'Asia/Manila'));

        $this->a = Site::create(['name' => 'Site Alpha']);
        $this->b = Site::create(['name' => 'Site Beta']);

        $this->worked($this->worker('Ana Alpha', $this->a));
        $this->worked($this->worker('Ben Beta', $this->b));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function worker(string $name, Site $site): Employee
    {
        return Employee::create([
            'name' => $name, 'site_id' => $site->id, 'status' => Employee::STATUS_ACTIVE,
            'employment_type' => Employee::EMPLOYMENT_DAILY, 'rate_per_hour' => 100,
            'labor_type_id' => LaborType::firstOrCreate(['name' => 'Mason'], ['daily_rate' => 800])->id,
            'shift_id' => Shift::where('crosses_midnight', false)->orderBy('id')->firstOrFail()->id,
        ]);
    }

    private function worked(Employee $e): void
    {
        foreach ([['AM', '08:00:00', '12:00:00'], ['PM', '13:00:00', '17:00:00']] as [$s, $in, $out]) {
            Attendance::create(['employee_id' => $e->id, 'shift_id' => $e->shift_id, 'site_id' => $e->site_id,
                'date' => '2026-09-16', 'session' => $s, 'time_in' => $in, 'time_out' => $out]);
        }
    }

    private function page(array $query = [])
    {
        $admin = User::create(['name' => 'Admin', 'username' => 'pr.admin', 'password' => 'secret123',
            'role' => User::ROLE_ADMIN, 'is_admin' => true, 'is_active' => true]);

        return $this->actingAs($admin)->get(route('payroll-records', $query + ['mode' => 'weekly', 'week' => '2026-W38']))->assertOk();
    }

    public function test_all_sites_shows_every_site_as_a_whole(): void
    {
        $names = array_column($this->page()->viewData('employees'), 'name');

        $this->assertEqualsCanonicalizing(['Ana Alpha', 'Ben Beta'], $names);
    }

    public function test_one_site_shows_only_its_crew_and_its_totals(): void
    {
        $page = $this->page(['site' => $this->a->id]);

        $this->assertSame(['Ana Alpha'], array_column($page->viewData('employees'), 'name'));
        $this->assertSame(1, $page->viewData('summary')['employee_count']);
        $page->assertSee('All sites (as a whole)')->assertSee('Site Alpha');
    }

    public function test_the_register_and_the_payslips_follow_the_site(): void
    {
        $admin = User::create(['name' => 'Admin', 'username' => 'pr.admin2', 'password' => 'secret123',
            'role' => User::ROLE_ADMIN, 'is_admin' => true, 'is_active' => true]);

        $xls = $this->actingAs($admin)->get(route('payroll-records.export.excel', ['mode' => 'weekly', 'week' => '2026-W38', 'site' => $this->b->id]))->assertOk();
        $this->assertStringContainsString('Ben Beta', $xls->getContent());
        $this->assertStringNotContainsString('Ana Alpha', $xls->getContent());

        $this->actingAs($admin)->get(route('payslip.batch', ['from' => '2026-09-14', 'to' => '2026-09-20', 'site' => $this->b->id]))
            ->assertOk()->assertSee('Ben Beta')->assertDontSee('Ana Alpha');
    }
}
