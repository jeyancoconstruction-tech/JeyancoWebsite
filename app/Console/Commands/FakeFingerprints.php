<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Employee;
use Illuminate\Console\Command;

/**
 * Give every pending worker a stand-in fingerprint, as if the kiosk had
 * enrolled them (Michael, 2026-09-30).
 *
 *     php artisan employees:fake-fingerprints          # enrol them all
 *     php artisan employees:fake-fingerprints --undo   # put them back
 *
 * It does what enrolment does (KioskController::saveFingerprint): the worker
 * gets a fingerprint slot and moves from Pending to Active. The slots start at
 * Employee::FAKE_FINGERPRINT_FROM, far above what any sensor holds, so no real
 * finger can ever match one and the kiosk never picks one for a real
 * enrolment. Only pending workers with no finger yet are touched. A kiosk
 * sign-up already holds a real finger and waits for Confirm instead.
 *
 * A worker with a stand-in is "enrolled" as far as the kiosk is concerned, so
 * their real finger cannot be taken until --undo gives the slot back.
 */
class FakeFingerprints extends Command
{
    protected $signature = 'employees:fake-fingerprints {--undo : Take the stand-in fingerprints back and return those workers to Pending}';

    protected $description = 'Enrol every pending worker with a stand-in fingerprint (9001 and up), or undo it';

    public function handle(): int
    {
        return $this->option('undo') ? $this->undo() : $this->enrol();
    }

    private function enrol(): int
    {
        $workers = Employee::pending()->whereNull('fingerprint_id')->orderBy('id')->get();
        if ($workers->isEmpty()) {
            $this->info('No pending worker is waiting for a fingerprint.');

            return self::SUCCESS;
        }

        $taken = Employee::withTrashed()->whereNotNull('fingerprint_id')->pluck('fingerprint_id')->map(fn ($f) => (string) $f)->flip();
        $slot  = Employee::FAKE_FINGERPRINT_FROM;

        foreach ($workers as $worker) {
            while (isset($taken[(string) $slot])) {
                $slot++;
            }
            $worker->forceFill(['fingerprint_id' => (string) $slot, 'status' => Employee::STATUS_ACTIVE])->save();
            $taken[(string) $slot] = true;
            $this->line("  #{$slot}  {$worker->name}");
        }

        AuditLog::record('Employees', 'updated',
            "Stand-in fingerprints given to {$workers->count()} pending workers; they are now active.");
        $this->info("{$workers->count()} pending workers enrolled with stand-in fingerprints and made active.");

        return self::SUCCESS;
    }

    private function undo(): int
    {
        $workers = Employee::withTrashed()->get()->filter(fn (Employee $e) => Employee::isFakeFingerprint($e->fingerprint_id));
        if ($workers->isEmpty()) {
            $this->info('Nobody holds a stand-in fingerprint.');

            return self::SUCCESS;
        }

        foreach ($workers as $worker) {
            $worker->forceFill([
                'fingerprint_id' => null,
                // Back to waiting for the kiosk, unless they have left since.
                'status' => $worker->isActive() ? Employee::STATUS_PENDING : $worker->status,
            ])->save();
        }

        AuditLog::record('Employees', 'updated',
            "Stand-in fingerprints taken back from {$workers->count()} workers; they are pending again.");
        $this->info("{$workers->count()} stand-in fingerprints taken back; those workers are pending again.");

        return self::SUCCESS;
    }
}
