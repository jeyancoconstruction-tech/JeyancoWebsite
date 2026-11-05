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

        foreach (['id="smCrewModal"', 'id="smCrewList"', 'id="smCrewShift"', 'id="smCrewOn"', 'id="smCrewSave"', 'id="smCrewMode"',
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
}
