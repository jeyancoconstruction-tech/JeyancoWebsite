<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeChange;
use App\Models\LaborType;
use App\Models\Shift;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * RSC 2026-10-18: "The process must be: after adding a site, group of
 * employee must be added then assign schedule."
 *
 * A new site on the Sites page goes straight on to a third step: tick the
 * crew, choose their shift and the day it starts. The page sends that to the
 * Employees bulk actions (transfer site, or add another site, then change
 * shift), so it is dated, logged and undone the same way as from Employees.
 * A site card's "Add workers" button opens the same step any time after.
 */
class SiteCrewStepTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin', 'username' => 'admin.crew', 'password' => 'secret123',
            'role' => User::ROLE_ADMIN, 'is_admin' => true, 'is_active' => true,
        ]);
    }

    private function worker(string $name, ?Site $site = null): Employee
    {
        return Employee::create([
            'name' => $name, 'status' => Employee::STATUS_ACTIVE, 'employment_type' => Employee::EMPLOYMENT_DAILY,
            'site_id' => $site?->id, 'rate_per_hour' => 100,
            'labor_type_id' => LaborType::firstOrCreate(['name' => 'Mason'], ['daily_rate' => 800])->id,
            'shift_id' => Shift::orderBy('id')->firstOrFail()->id,
        ]);
    }

    public function test_the_page_carries_the_step_with_the_crew_and_the_shifts(): void
    {
        $old = Site::create(['name' => 'Tower 1']);
        $ana = $this->worker('Ana Villanueva', $old);
        $this->worker('Pending Pat')->forceFill(['status' => Employee::STATUS_PENDING])->save();

        $html = $this->actingAs($this->admin())->get(route('sites.index'))->assertOk()->getContent();

        foreach (['id="smCrewModal"', 'id="smCrewList"', 'id="smCrewShift"', 'id="smCrewCal"', 'id="smCrewSites"', 'id="smCrewSave"', 'id="smCrewMode"',
                  'Step 3 workers &amp; schedule', 'data-crew=', 'Skip for now'] as $part) {
            $this->assertStringContainsString($part, $html, $part);
        }

        // The step goes through the Employees bulk actions.
        foreach (['employees.bulk-site', 'employees.bulk-other-site', 'employees.bulk-shift', 'employees.bulk-undo'] as $name) {
            $this->assertStringContainsString(json_encode(route($name)), $html, $name);
        }

        // Every active worker, with where they are now; not a pending one.
        $this->assertStringContainsString('"name":"Ana Villanueva"', $html);
        $this->assertStringContainsString('"where":"Tower 1"', $html);
        $this->assertStringNotContainsString('Pending Pat', $html);

        // Every shift is a choice.
        foreach (Shift::all() as $shift) {
            $this->assertMatchesRegularExpression('/<option value="' . $shift->id . '">' . preg_quote(e($shift->name), '/') . '/', $html);
        }

        // A new site opens the step by itself once it is saved.
        $this->assertStringContainsString('if (editing == null) openCrew(data.site.id);', $html);
    }

    /** What the step sends, end to end: the crew onto the new site, then their shift. */
    public function test_the_crew_is_moved_to_the_new_site_and_given_their_shift(): void
    {
        $admin = $this->admin();
        $site  = Site::create(['name' => 'Tower 2']);
        $night = Shift::orderByDesc('id')->firstOrFail();
        $crew  = collect([$this->worker('Ana Villanueva'), $this->worker('Roy Ersolada')]);
        $ids   = $crew->pluck('id')->all();
        $today = now()->toDateString();

        $this->actingAs($admin)->postJson(route('employees.bulk-site'), ['ids' => $ids, 'site_id' => $site->id, 'effective_on' => $today])
            ->assertOk()->assertJsonPath('success', true)->assertJsonPath('changed', 2);

        $answer = $this->postJson(route('employees.bulk-shift'), ['ids' => $ids, 'shift_id' => $night->id, 'effective_on' => $today]);
        if ($crew->every(fn ($e) => (int) $e->shift_id === (int) $night->id)) {
            // Only one shift on file: already on it, said and not an error.
            $answer->assertStatus(422)->assertJsonMissingPath('errors');
        } else {
            $answer->assertOk()->assertJsonPath('changed', 2);
        }

        foreach ($crew as $e) {
            $this->assertSame($site->id, $e->fresh()->site_id);
            $this->assertSame($night->id, $e->fresh()->shift_id);
        }

        // The site's card counts them.
        $this->getJson(route('sites.list'))->assertJsonPath('sites.0.employees_count', 2);
    }

    /** Another site for a set time: from the start date to the end date, then off by itself. */
    public function test_another_site_with_an_end_date_comes_off_by_itself(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-11-09 09:00', 'Asia/Manila'));
        $admin = $this->admin();
        $home  = Site::create(['name' => 'Tower 1']);
        $away  = Site::create(['name' => 'Tower 2']);
        $ana   = $this->worker('Ana Villanueva', $home);

        $this->actingAs($admin)->postJson(route('employees.bulk-other-site'), [
            'ids' => [$ana->id], 'site_id' => $away->id, 'mode' => 'add',
            'effective_on' => '2026-11-09', 'until' => '2026-11-20',
        ])->assertOk()->assertJsonPath('until', '2026-11-20')
          ->assertJsonPath('message', '1 can now also scan at Tower 2 until Nov 20');

        $this->assertSame([$home->id, $away->id], $ana->fresh()->siteIds());
        $off = EmployeeChange::waiting()->where('employee_id', $ana->id)->sole();
        $this->assertSame(EmployeeChange::SITE_DROP, $off->kind);
        $this->assertSame('2026-11-21', $off->effective_on->toDateString(), 'taken off the day after the last day');

        // The last day: still there.
        $this->travelTo(\Carbon\Carbon::parse('2026-11-20 17:00', 'Asia/Manila'));
        EmployeeChange::applyDue();
        $this->assertContains($away->id, $ana->fresh()->siteIds());

        // The day after: off, by itself; the main site stays.
        $this->travelTo(\Carbon\Carbon::parse('2026-11-21 06:00', 'Asia/Manila'));
        EmployeeChange::applyDue();
        $this->assertSame([$home->id], $ana->fresh()->siteIds());
    }

    public function test_the_end_date_cannot_be_before_the_start_and_undo_takes_back_both(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-11-09 09:00', 'Asia/Manila'));
        $admin = $this->admin();
        $away  = Site::create(['name' => 'Tower 2']);
        $ana   = $this->worker('Ana Villanueva', Site::create(['name' => 'Tower 1']));
        $body  = ['ids' => [$ana->id], 'site_id' => $away->id, 'mode' => 'add', 'effective_on' => '2026-11-12'];

        $this->actingAs($admin)->postJson(route('employees.bulk-other-site'), $body + ['until' => '2026-11-10'])
            ->assertStatus(422)->assertJsonValidationErrors(['until' => 'The end date cannot be before the start date.']);

        $batch = $this->postJson(route('employees.bulk-other-site'), $body + ['until' => '2026-11-15'])->assertOk()->json('batch');
        $this->assertSame(2, EmployeeChange::waiting()->where('batch', $batch)->count(), 'the start and the end, both waiting');

        $this->postJson(route('employees.bulk-undo'), ['batch' => $batch])->assertOk()->assertJsonPath('undone', 2);
        $this->assertSame(0, EmployeeChange::where('batch', $batch)->count());
    }
}
