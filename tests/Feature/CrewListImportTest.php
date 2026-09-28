<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\LaborType;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The crew list (all_employees.csv, 2026-09-28) arrives by migration: 47
 * workers at Site A, each paid exactly their own rate through a labour type
 * for their position at that rate.
 */
class CrewListImportTest extends TestCase
{
    use RefreshDatabase;

    private function import(): void
    {
        (require database_path('migrations/2026_09_28_120000_import_the_crew_list.php'))->import();
    }

    public function test_the_crew_arrives_pending_at_site_a_on_their_own_rates(): void
    {
        $this->import();

        $site = Site::where('name', 'Site A')->firstOrFail();
        $crew = Employee::where('site_id', $site->id)->where('status', Employee::STATUS_PENDING)->get();
        $this->assertCount(47, $crew);

        $bragais = Employee::where('name', 'Norman Bragais')->firstOrFail();
        $this->assertSame('Laborer ₱450', $bragais->laborType->name);
        $this->assertEquals(450, $bragais->getDailyRate());
        $this->assertEquals(56.25, $bragais->rate_per_hour);
        $this->assertSame('Laborer', $bragais->position);

        $this->assertEquals(1000, Employee::where('name', 'Emilio Nuñez Sr.')->firstOrFail()->getDailyRate());
        $this->assertEquals(600, Employee::where('name', 'Emilio Nuñez Jr.')->firstOrFail()->getDailyRate());

        // One labour type per position-and-rate pair on the list.
        $this->assertSame(14, LaborType::where('name', 'like', '%₱%')->count());
    }

    public function test_running_it_twice_adds_no_one_twice(): void
    {
        Employee::create(['name' => 'allan  ALPEREZ', 'status' => Employee::STATUS_ACTIVE, 'rate_per_hour' => 70]);

        $this->import();
        $this->import();

        $this->assertSame(47, Employee::count(), 'the worker already on file is not added again');
        $this->assertEquals(70, Employee::where('name', 'allan  ALPEREZ')->first()->rate_per_hour, 'and is left as it was');
    }

    public function test_the_crew_shows_on_the_site_a_kiosk_for_enrolment(): void
    {
        $this->import();
        $site = Site::where('name', 'Site A')->firstOrFail();

        $names = collect($this->getJson('/api/kiosk/roster?site_id=' . $site->id)->assertOk()->json('employees'))->pluck('name');

        $this->assertTrue($names->contains('Jay Ann Alpapara'));
        $this->assertTrue($names->contains('Jhon Carlo Tayangona'));
    }
}
