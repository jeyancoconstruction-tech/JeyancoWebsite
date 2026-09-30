<?php

namespace Tests\Feature;

use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The crew workers saved with "N/A" as a middle name get a stand-in middle
 * name instead (Michael, 2026-09-30), brothers sharing one; nobody else is
 * touched.
 */
class FillNaMiddleNamesTest extends TestCase
{
    use RefreshDatabase;

    private function worker(string $first, ?string $middle, string $last, ?string $suffix = null): Employee
    {
        return Employee::create([
            'first_name' => $first, 'middle_name' => $middle, 'last_name' => $last, 'name_suffix' => $suffix,
            'name' => Employee::composeName($first, $middle, $last, $suffix),
            'status' => Employee::STATUS_ACTIVE, 'rate_per_hour' => 100,
        ]);
    }

    private function run_(): int
    {
        return (require database_path('migrations/2026_09_30_140000_fill_na_middle_names.php'))->fill();
    }

    public function test_n_a_becomes_a_middle_name_and_brothers_share_one(): void
    {
        $robert = $this->worker('Robert', 'N/A', 'Menes');
        $romar  = $this->worker('Romar', 'n/a', 'Menes');
        $leomar = $this->worker('Leomar', 'NA', 'Nuñez');
        $other  = $this->worker('Juan', 'N/A', 'Tamad', 'Jr.');
        $kept   = $this->worker('Aldrin', 'Santos', 'Sapugay');
        $none   = $this->worker('Marvin', null, 'Resare');

        $this->assertSame(4, $this->run_());

        $this->assertSame('Robert Dizon Menes', $robert->fresh()->name);
        $this->assertSame('Dizon', $romar->fresh()->middle_name);
        $this->assertSame('Leomar Cruz Nuñez', $leomar->fresh()->name);
        $this->assertStringEndsWith(' Tamad Jr.', $other->fresh()->name, 'someone not on the list gets one too, suffix kept');
        $this->assertNotSame('N/A', $other->fresh()->middle_name);
        $this->assertSame('Aldrin Santos Sapugay', $kept->fresh()->name);
        $this->assertNull($none->fresh()->middle_name, 'a blank middle name is not invented');

        $this->assertSame(0, $this->run_(), 'running it again changes nothing');
    }
}
