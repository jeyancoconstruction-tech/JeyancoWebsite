<?php

namespace Tests\Feature;

use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * php artisan employees:fake-fingerprints (Michael, 2026-09-30): every
 * pending worker waiting for a finger is enrolled with a stand-in slot from
 * 9001 up and made active, as the kiosk's enrolment would; --undo takes the
 * slots back and returns them to Pending.
 */
class FakeFingerprintsCommandTest extends TestCase
{
    use RefreshDatabase;

    private function worker(string $name, string $status, ?string $finger = null): Employee
    {
        return Employee::create(['name' => $name, 'status' => $status, 'fingerprint_id' => $finger, 'rate_per_hour' => 100]);
    }

    public function test_pending_workers_are_enrolled_and_made_active(): void
    {
        // The crew-list migration leaves its own pending workers; start clean.
        Employee::query()->forceDelete();

        $a = $this->worker('Awaiting One', Employee::STATUS_PENDING);
        $b = $this->worker('Awaiting Two', Employee::STATUS_PENDING);
        $signup = $this->worker('Kiosk Sign-up', Employee::STATUS_PENDING, '12');   // has a real finger: waits for Confirm
        $active = $this->worker('Already Active', Employee::STATUS_ACTIVE, '3');
        $gone   = $this->worker('Removed Pending', Employee::STATUS_PENDING);
        $gone->delete();

        $this->artisan('employees:fake-fingerprints')->assertSuccessful();

        $this->assertSame(['9001', Employee::STATUS_ACTIVE], [$a->fresh()->fingerprint_id, $a->fresh()->status]);
        $this->assertSame(['9002', Employee::STATUS_ACTIVE], [$b->fresh()->fingerprint_id, $b->fresh()->status]);
        $this->assertSame(['12', Employee::STATUS_PENDING], [$signup->fresh()->fingerprint_id, $signup->fresh()->status]);
        $this->assertSame('3', $active->fresh()->fingerprint_id);
        $this->assertNull(Employee::withTrashed()->find($gone->id)->fingerprint_id, 'a removed worker is left alone');
    }

    public function test_a_slot_already_held_is_skipped(): void
    {
        Employee::query()->forceDelete();
        $this->worker('Holds 9001', Employee::STATUS_ACTIVE, '9001');
        $a = $this->worker('Awaiting', Employee::STATUS_PENDING);

        $this->artisan('employees:fake-fingerprints')->assertSuccessful();

        $this->assertSame('9002', $a->fresh()->fingerprint_id);
    }

    public function test_undo_returns_them_to_pending(): void
    {
        Employee::query()->forceDelete();
        $a    = $this->worker('Awaiting', Employee::STATUS_PENDING);
        $real = $this->worker('Real Finger', Employee::STATUS_ACTIVE, '4');

        $this->artisan('employees:fake-fingerprints')->assertSuccessful();
        $this->artisan('employees:fake-fingerprints', ['--undo' => true])->assertSuccessful();

        $this->assertSame([null, Employee::STATUS_PENDING], [$a->fresh()->fingerprint_id, $a->fresh()->status]);
        $this->assertSame(['4', Employee::STATUS_ACTIVE], [$real->fresh()->fingerprint_id, $real->fresh()->status]);
    }

    public function test_stand_ins_do_not_move_the_next_real_slot(): void
    {
        Employee::query()->forceDelete();
        $this->worker('Real Finger', Employee::STATUS_ACTIVE, '7');
        $this->worker('Awaiting', Employee::STATUS_PENDING);
        $this->artisan('employees:fake-fingerprints')->assertSuccessful();

        $admin = \App\Models\User::create(['name' => 'Admin', 'username' => 'admin.fp', 'password' => 'secret123',
            'is_admin' => true, 'is_active' => true]);

        $this->actingAs($admin)->get(route('employees.register'))->assertOk()
             ->assertSee('const nextFp   = "8";', false);
    }
}
