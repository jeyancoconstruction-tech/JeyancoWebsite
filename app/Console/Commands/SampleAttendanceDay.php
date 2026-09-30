<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Support\Live;
use App\Support\WorkSchedule;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * A normal day of attendance for every active worker (Michael, 2026-09-30).
 *
 *     php artisan attendance:sample-day          # write it
 *     php artisan attendance:sample-day --undo   # take it back
 *
 * Each worker gets their last shift that has fully finished, overtime
 * included: the day crew's today once the evening is over, the night crew's
 * last complete night. It is laid out as the kiosk records a day, a time in
 * and out for each session, and varied the way a real crew is. Most arrive a
 * little early, a few are late in the morning or back late from the break,
 * some stay for overtime, and one or two leave early. The same day always
 * comes out the same, so a rerun after --undo gives the same figures.
 *
 * A worker who already has attendance on that day is left alone. The rows
 * carry no kiosk, which real scans always do; --undo removes exactly those
 * rows on the days this command writes.
 */
class SampleAttendanceDay extends Command
{
    protected $signature = 'attendance:sample-day {--undo : Remove the sample rows written for those days}';

    protected $description = 'Give every active worker a normal day of attendance (a few late, some overtime) on their last finished shift';

    /** How long after the shift's end overtime may run, so a day counts as finished. */
    private const OVERTIME_ROOM = 150;

    public function handle(): int
    {
        $now     = Carbon::now('Asia/Manila');
        $workers = Employee::active()->with('shift')->orderBy('name')->get()->filter(fn (Employee $e) => $e->shift?->hasSchedule());

        $written = $skipped = $removed = 0;
        $days    = [];

        foreach ($workers as $worker) {
            $sched = $worker->shift->schedule();
            $day   = $this->lastFinishedDay($sched, $now);
            if ($day === null) {
                continue;
            }
            $days[$day] = true;

            $rows = Attendance::where('employee_id', $worker->id)->whereDate('date', $day);

            if ($this->option('undo')) {
                $removed += (clone $rows)->whereNull('kiosk_id')->delete();
                continue;
            }

            if ($rows->exists()) {
                $skipped++;
                continue;
            }

            $times = $this->dayFor($worker, $sched, $day);
            Attendance::withoutEvents(function () use ($worker, $day, $times) {
                foreach (['AM', 'PM'] as $session) {
                    Attendance::create([
                        'employee_id' => $worker->id,
                        'shift_id'    => $worker->shift_id,
                        'site_id'     => $worker->site_id,
                        'date'        => $day,
                        'session'     => $session,
                        'time_in'     => $times[$session][0]->format('Y-m-d H:i:s'),
                        'time_out'    => $times[$session][1]->format('Y-m-d H:i:s'),
                    ]);
                }
            });
            $this->line(sprintf('  %-28s %s  %s–%s  %s–%s', mb_strimwidth($worker->name, 0, 28), $day,
                $times['AM'][0]->format('g:i A'), $times['AM'][1]->format('g:i A'),
                $times['PM'][0]->format('g:i A'), $times['PM'][1]->format('g:i A')));
            $written++;
        }

        // Written straight from here, not through a request: the pages and
        // the payroll cache hear of it this way.
        Live::bump('attendance', 'payroll');

        $on = implode(', ', array_keys($days));
        if ($this->option('undo')) {
            AuditLog::record('Attendance', 'deleted', "Sample attendance removed: {$removed} rows on {$on}.");
            $this->info("{$removed} sample attendance rows removed ({$on}).");
        } else {
            AuditLog::record('Attendance', 'created', "Sample attendance written for {$written} workers on {$on}.");
            $this->info("{$written} workers given a day of attendance ({$on}); {$skipped} already had one and were left alone.");
        }

        return self::SUCCESS;
    }

    /** The latest shift day, today or before, whose second session and its overtime are over. */
    private function lastFinishedDay(array $sched, Carbon $now): ?string
    {
        for ($back = 0; $back <= 3; $back++) {
            $day = $now->copy()->subDays($back)->toDateString();
            if (WorkSchedule::sessionEnd($sched, 'PM', $day)->copy()->addMinutes(self::OVERTIME_ROOM)->lte($now)) {
                return $day;
            }
        }

        return null;
    }

    /**
     * The four scans of one worker's day. Seeded on the worker and the day,
     * so the crew is varied but a rerun is identical.
     *
     * @return array{AM: array{0: Carbon, 1: Carbon}, PM: array{0: Carbon, 1: Carbon}}
     */
    private function dayFor(Employee $worker, array $sched, string $day): array
    {
        mt_srand(crc32($worker->id . '|' . $day));
        $at = fn (Carbon $base, int $from, int $to) => $base->copy()->addMinutes(mt_rand($from, $to))->addSeconds(mt_rand(0, 59));

        $amStart = WorkSchedule::sessionStart($sched, 'AM', $day);
        $amEnd   = WorkSchedule::sessionEnd($sched, 'AM', $day);
        $pmStart = WorkSchedule::sessionStart($sched, 'PM', $day);
        $pmEnd   = WorkSchedule::sessionEnd($sched, 'PM', $day);

        // A little early, straight out at the break, back a little early,
        // home on time: the ordinary day, which most of the crew has.
        $times = [
            'AM' => [$at($amStart, -20, -3), $at($amEnd, 0, 4)],
            'PM' => [$at($pmStart, -12, -2), $at($pmEnd, 0, 6)],
        ];

        $roll = mt_rand(0, 99);
        match (true) {
            $roll < 12 => $times['AM'][0] = $at($amStart, 4, 38),     // late in the morning
            $roll < 16 => $times['PM'][0] = $at($pmStart, 6, 20),     // back late from the break
            $roll < 30 => $times['PM'][1] = $at($pmEnd, 60, 140),     // overtime
            $roll < 34 => $times['PM'][1] = $at($pmEnd, -50, -15),    // left early
            default    => null,
        };

        return $times;
    }
}
