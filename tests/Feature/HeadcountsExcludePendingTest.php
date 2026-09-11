<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\LaborType;
use App\Models\Shift;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A headcount counts the workforce, and a pending registration is not in it.
 *
 * The shift cards read "5 workers" and "1 worker" against four active
 * employees and two pending ones — the two waiting names were being counted
 * as crew, one of them the whole of the night shift's apparent headcount.
 * Every count off a relation had the same hole: the relation is every row
 * that points at the shift or the site, whatever state it is in.
 */
class HeadcountsExcludePendingTest extends TestCase
{
    use RefreshDatabase;

    private function worker(string $name, string $status, Shift $shift, Site $site): Employee
    {
        return Employee::create([
            'name'            => $name,
            'status'          => $status,
            'employment_type' => Employee::EMPLOYMENT_DAILY,
            'labor_type_id'   => LaborType::firstOrCreate(['name' => 'Mason'], ['daily_rate' => 800, 'ot_rate' => 125])->id,
            'shift_id'        => $shift->id,
            'site_id'         => $site->id,
            'rate_per_hour'   => 100,
        ]);
    }

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin', 'username' => 'admin.heads', 'password' => 'secret123',
            'role' => User::ROLE_ADMIN, 'is_admin' => true, 'is_active' => true,
        ]);
    }

    public function test_a_shift_counts_its_crew_not_the_names_waiting_to_join_it(): void
    {
        $day   = Shift::where('crosses_midnight', false)->firstOrFail();
        $night = Shift::where('crosses_midnight', true)->firstOrFail();
        $site  = Site::orderBy('id')->firstOrFail();

        $this->worker('On The Crew', Employee::STATUS_ACTIVE, $day, $site);
        $this->worker('Waiting', Employee::STATUS_PENDING, $day, $site);
        $this->worker('Also Waiting', Employee::STATUS_PENDING, $night, $site);
        $this->worker('Left Last Year', Employee::STATUS_ARCHIVED, $day, $site);

        $shifts = $this->actingAs($this->admin())
            ->get(route('settings.index', ['tab' => 'attendance']))
            ->assertOk()
            ->viewData('shifts')
            ->keyBy('id');

        $this->assertSame(1, $shifts[$day->id]->employees_count, 'one man on the day crew');
        $this->assertSame(0, $shifts[$night->id]->employees_count,
            'a pending name is not a night shift of one');
    }

    public function test_a_site_counts_the_same_way(): void
    {
        $day  = Shift::where('crosses_midnight', false)->firstOrFail();
        $site = Site::orderBy('id')->firstOrFail();

        $this->worker('On The Crew', Employee::STATUS_ACTIVE, $day, $site);
        $this->worker('Waiting', Employee::STATUS_PENDING, $day, $site);

        // This endpoint answers JSON rather than a view.
        $rows = collect($this->actingAs($this->admin())->getJson('/sites/list')->assertOk()->json('sites'));

        $this->assertSame(1, $rows->firstWhere('id', $site->id)['employees_count']);
    }

    /** The headcounts and the directory have to answer the same number. */
    public function test_the_shift_cards_add_up_to_the_workforce(): void
    {
        $day   = Shift::where('crosses_midnight', false)->firstOrFail();
        $night = Shift::where('crosses_midnight', true)->firstOrFail();
        $site  = Site::orderBy('id')->firstOrFail();

        $this->worker('A', Employee::STATUS_ACTIVE, $day, $site);
        $this->worker('B', Employee::STATUS_ACTIVE, $day, $site);
        $this->worker('C', Employee::STATUS_ACTIVE, $night, $site);
        $this->worker('D', Employee::STATUS_PENDING, $night, $site);

        $shifts = $this->actingAs($this->admin())
            ->get(route('settings.index', ['tab' => 'attendance']))
            ->assertOk()
            ->viewData('shifts');

        $this->assertSame(Employee::active()->count(), $shifts->sum('employees_count'));
    }
}
