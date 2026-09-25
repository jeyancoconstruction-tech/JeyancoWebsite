<?php

namespace App\Support;

use App\Models\Attendance;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * One workday as the Attendance page draws it: the four scans a shift is made
 * of, where the day stands, a timeline against its shift, and what payroll
 * makes of its hours.
 *
 * A day is stored as stretches (see AttendanceDay) — however many times the
 * worker touched the kiosk. The office reads it the way a shift is laid out:
 * the first session's time in and time out, a break, the second session's
 * time in and time out. That is the shape it is put into here. Nothing is
 * thrown away: every stretch is still listed with the day's scans.
 *
 * Hours are never measured here. They are read from what PayrollService
 * worked out for the same rows ($priced), so the page and the payslip cannot
 * disagree. Lateness is the same rule payroll reports (WorkSchedule::
 * lateMinutes), worked out here only for a stretch still open, which payroll
 * has not priced yet.
 */
final class AttendanceDayView
{
    public const SLOTS = ['in', 'bo', 'bi', 'out'];

    /** The shift's schedule, or null for a day worked before shifts had one. */
    private ?array $sched = null;

    /** ['AM' => [start, end], 'PM' => [start, end]] for this workday. */
    private ?array $w = null;

    /** Stretches of the first session, and of the second, in time order. */
    private Collection $first;
    private Collection $second;

    /** Each slot's scan: ['at' => Carbon, 'row' => Attendance, 'guessed' => bool, 'edited' => bool]. */
    private array $slots;

    /** Worked straight through the break: one stretch, no break scans. */
    private bool $through = false;

    /** Where the day stands: ['key', 'label', 'tone', 'live', 'over'?]. */
    private array $status;

    /** The slot a missing time out belongs in, when the day is waiting on one. */
    private ?string $missing = null;

    /**
     * @param  bool  $live    the day view, where "now" is drawn and a break is running
     * @param  array<int, array{minutes: int, ot_minutes: int, late_minutes: int}>  $priced  by attendance id
     */
    public function __construct(
        public readonly AttendanceDay $day,
        private readonly Carbon $now,
        private readonly bool $live = false,
        private readonly array $priced = [],
    ) {
        $schedule = $day->shift()?->schedule();

        if (WorkSchedule::has($schedule)) {
            $this->sched = $schedule;
            $this->w     = WorkSchedule::windows($schedule, $day->date()->toDateString());
        }

        $stretches    = $day->stretches();
        $this->first  = $stretches->filter(fn (Attendance $r) => $this->sessionOf($r) === 'AM')->values();
        $this->second = $stretches->filter(fn (Attendance $r) => $this->sessionOf($r) === 'PM')->values();

        $this->slots  = $this->layOut();
        $this->status = $this->readStatus();
    }

    /**
     * Wrap a gathered list of days.
     *
     * @param  Collection<int, AttendanceDay>  $days
     * @return Collection<int, self>
     */
    public static function all(Collection $days, Carbon $now, bool $live, array $priced): Collection
    {
        return $days->map(fn (AttendanceDay $d) => new self($d, $now, $live, $priced))->values();
    }

    // ── The day's shape ──────────────────────────────────────────────────────

    /** The half of the shift a stretch belongs to — the one stamped on it. */
    public function sessionOf(Attendance $row): string
    {
        if (in_array($row->session, ['AM', 'PM'], true)) {
            return $row->session;
        }

        $in = AttendanceDay::momentIn($row);

        return $this->sched
            ? WorkSchedule::sessionAt($this->sched, $in)
            : ($in->hour < 12 ? 'AM' : 'PM');
    }

    private function layOut(): array
    {
        $slots = array_fill_keys(self::SLOTS, null);

        if ($this->first->isNotEmpty()) {
            $slots['in'] = $this->scan($this->first->first(), 'in');

            $last = $this->first->last();

            if (! empty($last->time_out)) {
                // A first stretch that ran past the start of the afternoon,
                // with nothing after it, is the whole day worked straight
                // through: its time out is the day's end, not a lunch break.
                $this->through = $this->second->isEmpty()
                    && $this->w
                    && AttendanceDay::momentOut($last)->greaterThan($this->w['PM'][0]);

                $slots[$this->through ? 'out' : 'bo'] = $this->scan($last, 'out');
            }
        }

        if ($this->second->isNotEmpty()) {
            $slots['bi'] = $this->scan($this->second->first(), 'in');

            $last = $this->second->last();

            if (! empty($last->time_out)) {
                $slots['out'] = $this->scan($last, 'out');
            }
        }

        return $slots;
    }

    private function scan(Attendance $row, string $which): array
    {
        $out = $which === 'out';

        return [
            'at'      => $out ? AttendanceDay::momentOut($row) : AttendanceDay::momentIn($row),
            'row'     => $row,
            'guessed' => $out && (bool) $row->needs_review,
            'edited'  => $out && $row->close_type === 'admin',
        ];
    }

    public function slot(string $slot): ?array
    {
        return $this->slots[$slot] ?? null;
    }

    public function workedThrough(): bool
    {
        return $this->through;
    }

    public function hasSchedule(): bool
    {
        return $this->w !== null;
    }

    /** When a slot's scan is expected, by the shift. */
    public function expected(string $slot): ?Carbon
    {
        if (! $this->w) {
            return null;
        }

        return match ($slot) {
            'in'  => $this->w['AM'][0]->copy(),
            'bo'  => $this->w['AM'][1]->copy(),
            'bi'  => $this->w['PM'][0]->copy(),
            default => $this->w['PM'][1]->copy(),
        };
    }

    /**
     * "AM" or "PM" for a half of the shift, as the clock reads its start.
     *
     * The session column names the first and second half, which for a night
     * crew is not what the clock says: their first half starts in the
     * evening. On screen it is the clock the office reads by.
     */
    public function clockOf(string $session): string
    {
        return $this->w ? $this->w[$session][0]->format('A') : $session;
    }

    // ── Where the day stands ─────────────────────────────────────────────────

    /**
     * Stretches the office still has to settle: a time out the system had to
     * guess, and one still open past the end of its shift.
     */
    public function problems(): Collection
    {
        return $this->day->stretches()
            ->filter(fn (Attendance $r) => $r->needs_review || $r->signOutOverdue($this->now))
            ->values();
    }

    private function readStatus(): array
    {
        $problem = $this->problems()->first();

        if ($problem) {
            $inFirst = $this->sessionOf($problem) === 'AM';

            if (empty($problem->time_out)) {
                $this->missing = $inFirst && $this->second->isNotEmpty() ? 'bo' : 'out';
            }

            return [
                'key'   => 'review',
                'label' => ! $inFirst ? __('No 2nd session out')
                         : ($this->second->isNotEmpty() ? __('No 1st session out') : __('No time out')),
                'tone'  => 'bad',
                'live'  => false,
            ];
        }

        $open = $this->day->stretches()->first(fn (Attendance $r) => empty($r->time_out));

        if ($open) {
            // Which half the clock is in, not which half the stretch began in:
            // somebody who never scanned out for lunch is in the afternoon now.
            $half = $this->sessionOf($open) === 'PM'
                 || ($this->w && $this->now->greaterThanOrEqualTo($this->w['PM'][0])) ? 'PM' : 'AM';

            return ['key' => 'work', 'label' => __('Working') . ' · ' . $this->clockOf($half), 'tone' => 'good', 'live' => true, 'half' => $half];
        }

        if ($this->onBreak()) {
            $over = $this->breakOverBy();

            return $over > 0
                ? ['key' => 'break', 'label' => __('Overbreak') . ' ' . WorkSchedule::duration($over), 'tone' => 'warn', 'live' => true, 'over' => true]
                : ['key' => 'break', 'label' => __('On break'), 'tone' => 'brk', 'live' => true, 'over' => false];
        }

        return ['key' => 'done', 'label' => __('Present'), 'tone' => 'good', 'live' => false];
    }

    /**
     * Out for the break and not back: the first session closed, nothing after
     * it, and the second session not yet over. Only on the day view — a day in
     * the history that stops at lunch is a half day, not a break.
     */
    private function onBreak(): bool
    {
        return $this->live
            && $this->w
            && $this->second->isEmpty()
            && $this->slots['bo'] !== null
            && $this->now->greaterThanOrEqualTo($this->slots['bo']['at'])
            && $this->now->lessThan($this->w['PM'][1]);
    }

    /** When the second session has to be started by, grace included. */
    public function backBy(): ?Carbon
    {
        return $this->w ? $this->w['PM'][0]->copy()->addMinutes((int) ($this->sched['grace'] ?? 0)) : null;
    }

    /** Minutes past the start of the second session, once the grace is spent. */
    private function breakOverBy(): int
    {
        return $this->now->greaterThan($this->backBy())
            ? (int) $this->w['PM'][0]->diffInMinutes($this->now)
            : 0;
    }

    public function status(): array
    {
        return $this->status;
    }

    public function key(): string
    {
        return $this->status['key'];
    }

    // ── What each slot says ──────────────────────────────────────────────────

    /** Late, overbreak, undertime or a guessed time — the note under a scan. */
    public function tag(string $slot): ?array
    {
        $scan = $this->slots[$slot] ?? null;

        if (! $scan) {
            return $this->placeholder($slot);
        }

        if ($scan['guessed']) {
            return ['text' => __('Not scanned'), 'tone' => 'bad',
                    'title' => __('Closed by the system at the end of the session. Confirm or correct it under the row.')];
        }

        if ($slot === 'in' && ($late = $this->lateOf($scan['row'])) > 0) {
            return ['text' => __('Late') . ' ' . WorkSchedule::duration($late), 'tone' => 'warn'];
        }

        if ($slot === 'bi' && ($late = $this->lateOf($scan['row'])) > 0) {
            return ['text' => __('Overbreak') . ' ' . WorkSchedule::duration($late), 'tone' => 'warn'];
        }

        if ($slot === 'out' && $this->w && $scan['at']->lessThan($this->w['PM'][1])) {
            return ['text' => __('Undertime') . ' ' . WorkSchedule::duration($scan['at']->diffInMinutes($this->w['PM'][1])), 'tone' => 'warn'];
        }

        return null;
    }

    /** What an empty slot says: missing, due, expected — or nothing. */
    private function placeholder(string $slot): ?array
    {
        if ($this->status['key'] === 'review' && $slot === $this->missing) {
            return ['text' => __('Missing'), 'tone' => 'bad'];
        }

        if ($this->through && in_array($slot, ['bo', 'bi'], true)) {
            return ['text' => __('No break scan'), 'tone' => 'mute'];
        }

        if (! $this->live || ! $this->w || ! in_array($this->status['key'], ['work', 'break'], true)) {
            return null;
        }

        if ($this->status['key'] === 'break' && $slot === 'bi') {
            return ['text' => __('due') . ' ' . WorkSchedule::label($this->backBy()), 'tone' => $this->status['over'] ? 'warn' : 'brk'];
        }

        // Still ahead of the worker today.
        $filled = array_keys(array_filter($this->slots));
        $after  = array_search(end($filled) ?: 'in', self::SLOTS, true);

        return array_search($slot, self::SLOTS, true) > $after && $this->expected($slot)->greaterThan($this->now)
            ? ['text' => __('exp.') . ' ' . WorkSchedule::label($this->expected($slot)), 'tone' => 'mute']
            : null;
    }

    /** Minutes late on a session's first time in, as payroll reports it. */
    private function lateOf(Attendance $row): int
    {
        if (isset($this->priced[$row->id])) {
            return (int) $this->priced[$row->id]['late_minutes'];
        }

        return $this->sched
            ? WorkSchedule::lateMinutes($this->sched, AttendanceDay::momentIn($row), $this->sessionOf($row), $this->day->date()->toDateString())
            : 0;
    }

    // ── Hours ────────────────────────────────────────────────────────────────

    /**
     * What payroll makes of the day, in whole minutes — or null while a
     * stretch of it is still open and there is nothing to price yet.
     */
    public function hours(): ?array
    {
        if ($this->day->isOpen()) {
            return null;
        }

        $by = ['AM' => 0, 'PM' => 0];
        $ot = 0;

        foreach ($this->day->stretches() as $row) {
            $p = $this->priced[$row->id] ?? null;

            if ($p === null) {
                return null;
            }

            $by[$this->sessionOf($row)] += (int) $p['minutes'];
            $ot += (int) $p['ot_minutes'];
        }

        $worked = $by['AM'] + $by['PM'];

        return [
            'worked'  => $worked,
            'regular' => $worked - $ot,
            'ot'      => $ot,
            'first'   => $by['AM'],
            'second'  => $by['PM'],
            'break'   => $this->slots['bo'] && $this->slots['bi']
                ? (int) $this->slots['bo']['at']->diffInMinutes($this->slots['bi']['at'])
                : null,
            'allowed' => $this->w ? (int) $this->w['AM'][1]->diffInMinutes($this->w['PM'][0]) : null,
        ];
    }

    /** "The first 8h paid are regular…" — how the hours were split, in words. */
    public function hoursRule(): ?string
    {
        if (! $this->w) {
            return null;
        }

        $regular = WorkSchedule::regularMinutes($this->sched);
        $start   = WorkSchedule::label($this->w['AM'][0]);

        return $regular === null
            ? __('Paid time after :end is overtime. Time before :start and the break are not paid.',
                ['end' => WorkSchedule::label($this->w['PM'][1]), 'start' => $start])
            : __('The first :regular paid are regular; paid time past that is overtime. Time before :start and the break are not paid.',
                ['regular' => WorkSchedule::duration($regular), 'start' => $start]);
    }

    // ── The timeline ─────────────────────────────────────────────────────────

    /**
     * The day drawn against its shift, an hour either side: what was worked,
     * the break window, lateness, a stretch still running, and now.
     *
     * Positions are percentages of the span, worked out here so the row is
     * drawn by CSS alone.
     */
    public function timeline(): ?array
    {
        if (! $this->w) {
            return null;
        }

        $lo   = $this->w['AM'][0]->copy()->subHour();
        $hi   = $this->w['PM'][1]->copy()->addHour();
        $span = max(1, $lo->diffInMinutes($hi));

        $at  = fn (Carbon $t) => max(0, min(100, round($lo->diffInMinutes($t, false) / $span * 100, 2)));
        $bar = function (?Carbon $from, ?Carbon $to, string $cls) use ($at) {
            if (! $from || ! $to || ! $to->greaterThan($from)) {
                return null;
            }

            $l = $at($from);
            $r = $at($to);

            return $r > $l ? ['cls' => $cls, 'left' => $l, 'width' => round(max(0.6, $r - $l), 2)] : null;
        };

        $pieces = [
            $bar($this->w['AM'][0], $this->w['PM'][1], 'base'),
            $bar($this->w['AM'][1], $this->w['PM'][0], 'bw'),
        ];

        if ($this->slots['in'] && $this->lateOf($this->slots['in']['row']) > 0) {
            $pieces[] = $bar($this->w['AM'][0], $this->slots['in']['at'], 'late');
        }

        foreach ($this->day->stretches() as $row) {
            [$in, $out] = $this->day->partsOf($row);

            if ($out) {
                $pieces[] = $bar($in, $out, $row->needs_review ? 'w miss' : 'w');
            } elseif ($row->signOutOverdue($this->now)) {
                $pieces[] = $bar($in, WorkSchedule::sessionEnd($this->sched, $this->sessionOf($row), $this->day->date()->toDateString()), 'w miss');
            } elseif ($this->live) {
                $pieces[] = $bar($in, $this->now, 'w live');
            }
        }

        if ($this->status['key'] === 'break') {
            $due = $this->backBy();
            $pieces[] = $bar($this->slots['bo']['at'], $this->now->lessThan($due) ? $this->now : $due, 'w br');

            if ($this->now->greaterThan($due)) {
                $pieces[] = $bar($due, $this->now, 'w ob');
            }
        }

        $short = fn (Carbon $t) => $t->format($t->minute === 0 ? 'g A' : 'g:i A');

        return [
            'pieces' => array_values(array_filter($pieces)),
            'now'    => $this->live && $this->now->between($lo, $hi) ? $at($this->now) : null,
            'axis'   => [$short($lo), $short($this->w['AM'][1]), $short($hi)],
            'title'  => $this->shiftHours() . ' · ' . __('break') . ' '
                      . WorkSchedule::label($this->w['AM'][1]) . ' – ' . WorkSchedule::label($this->w['PM'][0]),
        ];
    }

    /** "8:00 AM – 5:00 PM" — the shift this day was worked under. */
    public function shiftHours(): ?string
    {
        return $this->w
            ? WorkSchedule::label($this->w['AM'][0]) . ' – ' . WorkSchedule::label($this->w['PM'][1])
            : null;
    }

    // ── The scans behind the row ─────────────────────────────────────────────

    /**
     * Every scan of the day, in the order it happened, with where it came
     * from and whether a person or the system set it.
     *
     * @return list<array{at: Carbon, what: string, where: string, tag: string, tone: string}>
     */
    public function scans(): array
    {
        $list = [];

        foreach ($this->day->stretches() as $row) {
            [$in, $out] = $this->day->partsOf($row);
            $where = $row->kiosk ? $row->kiosk->name . ' · ' . __('Fingerprint') : __('Control Panel');

            $list[] = ['at' => $in, 'what' => $this->scanLabel($row, 'in'), 'where' => $where, 'tag' => __('OK'), 'tone' => 'good'];

            if (! $out) {
                continue;
            }

            $list[] = match (true) {
                (bool) $row->needs_review => [
                    'at' => $out, 'what' => __('Closed by the system — no time out was scanned'),
                    'where' => __('System'), 'tag' => __('Guessed'), 'tone' => 'bad',
                ],
                $row->close_type === 'admin' => [
                    'at' => $out, 'what' => $this->scanLabel($row, 'out'),
                    'where' => __('Set by :name', ['name' => $row->reviewer?->name ?? __('the office')]),
                    'tag' => __('Edited'), 'tone' => 'brk',
                ],
                default => [
                    'at' => $out, 'what' => $this->scanLabel($row, 'out'), 'where' => $where,
                    'tag' => __('OK'), 'tone' => 'good',
                ],
            };
        }

        return $list;
    }

    private function scanLabel(Attendance $row, string $which): string
    {
        $inFirst = $this->sessionOf($row) === 'AM';
        $group   = $inFirst ? $this->first : $this->second;
        $n       = $inFirst ? __('1st') : __('2nd');

        if ($which === 'in') {
            return $group->first()?->is($row)
                ? __(':n session time in', ['n' => $n])
                : __('Time in again');
        }

        if (! $group->last()?->is($row)) {
            return __('Time out');
        }

        return $this->through && $inFirst
            ? __('Time out · worked through the break')
            : __(':n session time out', ['n' => $n]);
    }

    /**
     * The time outs the office can settle from the row: each stretch waiting
     * on one, with what the shift says it should have been and, when the
     * system already closed it, the time it guessed.
     *
     * @return list<array{row: Attendance, label: string, scheduled: ?Carbon, guess: ?Carbon, in: Carbon}>
     */
    public function fixes(): array
    {
        return $this->problems()->map(function (Attendance $row) {
            $inFirst = $this->sessionOf($row) === 'AM';

            return [
                'row'       => $row,
                'label'     => ! $inFirst ? __('2nd session time out')
                             : ($this->second->isNotEmpty() ? __('1st session time out') : __('Time out')),
                'scheduled' => $this->sched
                    ? WorkSchedule::sessionEnd($this->sched, $this->sessionOf($row), $this->day->date()->toDateString())
                    : null,
                'guess'     => $row->needs_review ? AttendanceDay::momentOut($row) : null,
                'in'        => AttendanceDay::momentIn($row),
            ];
        })->all();
    }
}
