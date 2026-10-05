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
 *     php artisan attendance:sample-day                  # write it
 *     php artisan attendance:sample-day --undo           # take it back
 *     php artisan attendance:sample-day --today          # today, as far as it has gone
 *     php artisan attendance:sample-day --today --undo
 *
 * Each worker gets their last shift that has fully finished, overtime
 * included: the day crew's today once the evening is over, the night crew's
 * last complete night. A day is eight paid hours: home when the regular
 * hours are done, and only past that is overtime. It is laid out as the kiosk records a day, a time in
 * and out for each session, and varied the way a real crew is. Most arrive a
 * little early, a few are late in the morning or back late from the break,
 * some stay for overtime, and one or two leave early. The same day always
 * comes out the same, so a rerun after --undo gives the same figures.
 *
 * --today is the day each crew is working now, the one the Attendance page
 * calls today (Michael, 2026-10-05). Only the scans the clock has reached are
 * written: at eleven in the morning everybody is timed in and nobody is out.
 * Run it again later and the same workers go to their break, come back and go
 * home. A day left unfinished is finished by the next run, with or without
 * --today, even after the system closed it as a missed time out.
 *
 * A worker who already has attendance on that day is left alone. The rows
 * carry no kiosk, which real scans always do; --undo removes exactly those
 * rows on the days this command writes.
 */
class SampleAttendanceDay extends Command
{
    protected $signature = 'attendance:sample-day
        {--today : The day each crew is working now, written as far as the clock has gone}
        {--undo : Remove the sample rows written for those days}';

    protected $description = 'Give every active worker a normal day of attendance (a few late, some overtime) on their last finished shift, or on today so far';

    /** How long after the shift's end overtime may run, so a day counts as finished. */
    private const OVERTIME_ROOM = 150;

    public function handle(): int
    {
        $now     = Carbon::now('Asia/Manila');
        $today   = (bool) $this->option('today');
        $workers = Employee::active()->with('shift')->orderBy('name')->get()->filter(fn (Employee $e) => $e->shift?->hasSchedule());

        $written = $skipped = $waiting = $removed = 0;
        $days    = [];

        foreach ($workers as $worker) {
            $sched = $worker->shift->schedule();
            $day   = $today ? WorkSchedule::shiftDayFor($sched, $now) : $this->lastFinishedDay($sched, $now);
            if ($day === null) {
                continue;
            }
            $days[$day] = true;

            if ($this->option('undo')) {
                $removed += Attendance::where('employee_id', $worker->id)->whereDate('date', $day)->whereNull('kiosk_id')->delete();
                continue;
            }

            $times = $this->dayFor($worker, $sched, $day);
            $scans = $this->fill($worker, $day, $times, $now);

            if ($scans === 0 && $times['AM'][0]->greaterThan($now)) {
                $waiting++;
                continue;
            }
            if (! $scans) {
                $skipped++;
                continue;
            }

            $at = fn (Carbon $t) => $t->greaterThan($now) ? '…' : $t->format('g:i A');
            $this->line(sprintf('  %-28s %s  %s–%s  %s–%s', mb_strimwidth($worker->name, 0, 28), $day,
                $at($times['AM'][0]), $at($times['AM'][1]), $at($times['PM'][0]), $at($times['PM'][1])));
            $written++;
        }

        // Written straight from here, not through a request: the pages and
        // the payroll cache hear of it this way.
        Live::bump('attendance', 'payroll');

        $on = implode(', ', array_keys($days));
        if ($this->option('undo')) {
            AuditLog::record('Attendance', 'deleted', "Sample attendance removed: {$removed} rows on {$on}.");
            $this->info("{$removed} sample attendance rows removed ({$on}).");
        } elseif ($today) {
            AuditLog::record('Attendance', 'created', "Sample attendance written for {$written} workers on {$on}, up to {$now->format('g:i A')}.");
            $this->info("{$written} workers brought up to {$now->format('g:i A')} ({$on}); {$skipped} already had attendance and were left alone"
                . ($waiting ? "; {$waiting} are not due in yet." : '.'));
        } else {
            AuditLog::record('Attendance', 'created', "Sample attendance written for {$written} workers on {$on}.");
            $this->info("{$written} workers given a day of attendance ({$on}); {$skipped} already had one and were left alone.");
        }

        return self::SUCCESS;
    }

    /** The latest shift day, today or before, whose regular hours and a stretch of overtime are over. */
    private function lastFinishedDay(array $sched, Carbon $now): ?string
    {
        for ($back = 0; $back <= 3; $back++) {
            $day = $now->copy()->subDays($back)->toDateString();
            if (WorkSchedule::regularEnd($sched, $day)->addMinutes(self::OVERTIME_ROOM)->lte($now)) {
                return $day;
            }
        }

        return null;
    }

    /**
     * Bring one worker's day up to the clock: every scan of it that has
     * happened by now and is not on file yet is written. On a finished day
     * that is all four. On a day still running it is what the kiosk would
     * hold at this hour, and a later run adds the rest to the same rows.
     *
     * Null when the day holds anything this command did not write: that
     * worker is present already and is left alone.
     *
     * @param  array{AM: array{0: Carbon, 1: Carbon}, PM: array{0: Carbon, 1: Carbon}}  $times
     * @return int|null  scans written
     */
    private function fill(Employee $worker, string $day, array $times, Carbon $now): ?int
    {
        $stamp = fn (Carbon $t) => $t->format('Y-m-d H:i:s');
        $rows  = Attendance::where('employee_id', $worker->id)->whereDate('date', $day)->get();

        $own = fn (Attendance $r) => $r->kiosk_id === null
            && isset($times[$r->session])
            && (string) $r->time_in === $stamp($times[$r->session][0]);

        if (! $rows->every($own)) {
            return null;
        }

        $rows  = $rows->keyBy('session');
        $scans = 0;

        Attendance::withoutEvents(function () use ($worker, $day, $times, $now, $stamp, $rows, &$scans) {
            foreach (['AM', 'PM'] as $session) {
                [$in, $out] = $times[$session];
                if ($in->greaterThan($now)) {
                    break;
                }

                $row = $rows[$session] ?? null;
                if ($row === null) {
                    $row = Attendance::create([
                        'employee_id' => $worker->id,
                        'shift_id'    => $worker->shift_id,
                        'site_id'     => $worker->site_id,
                        'date'        => $day,
                        'session'     => $session,
                        'time_in'     => $stamp($in),
                    ]);
                    $scans++;
                }

                // Still open, or closed by the system's guess because nobody
                // ran this again before the day went stale.
                if ($out->lessThanOrEqualTo($now) && (empty($row->time_out) || $row->close_type === 'auto')) {
                    $row->forceFill([
                        'time_out'     => $stamp($out),
                        'close_type'   => null,
                        'needs_review' => false,
                        'close_reason' => null,
                    ])->save();
                    $scans++;
                }
            }
        });

        return $scans;
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
        // Home once the regular hours are done (eight, on this crew), not at
        // the end of a longer shift: past that is overtime.
        $pmEnd   = WorkSchedule::regularEnd($sched, $day);

        // A little early, straight out at the break, back a little early,
        // home on time: the ordinary day, which most of the crew has.
        $times = [
            'AM' => [$at($amStart, -20, -3), $at($amEnd, 0, 4)],
            // Out within the minute the regular hours end: pay is counted by
            // the whole minute, so 5:02 would already be two minutes of overtime.
            'PM' => [$at($pmStart, -12, -2), $at($pmEnd, 0, 0)],
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
