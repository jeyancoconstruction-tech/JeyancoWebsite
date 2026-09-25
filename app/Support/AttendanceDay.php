<?php

namespace App\Support;

use App\Models\Attendance;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * One worker's one workday, however many times they touched the kiosk.
 *
 * A day is already stored as several rows — a morning, an afternoon after
 * lunch, a stretch begun again after a mistaken time-out — because that is
 * what actually happened and payroll needs every minute of it. What it is
 * NOT is several attendances: the rate buys the day once, lateness is asked
 * once per session, and the office reads "who worked Tuesday", not "which of
 * these four lines is Tuesday".
 *
 * The screens listed the rows, so one man's Tuesday arrived as four entries
 * stacked under each other, each carrying its own date and status, and there
 * was no way to see the day itself. This gathers them back into the day they
 * belong to WITHOUT flattening them: the stretches stay, in order, so the AM
 * and PM times are still there to read.
 *
 * Payroll is not changed by any of this and does not use this class — it has
 * always counted a day once (PayrollService keeps a running total of the
 * regular minutes a day has already bought, and counts workdays by distinct
 * date). This is the same day, said out loud on screen.
 */
class AttendanceDay
{
    /** @param Collection<int, Attendance> $rows one employee, one workday, in time order */
    private function __construct(public readonly Collection $rows)
    {
    }

    /**
     * Gather rows into days, newest workday first, each day's stretches in the
     * order they were worked.
     *
     * Keyed by employee and the workday the rows are filed under — which for
     * the night crew is the evening they came in on, not the calendar date
     * their morning time-out fell on.
     *
     * @param  iterable<int, Attendance> $rows
     * @return Collection<int, self>
     */
    public static function gather(iterable $rows): Collection
    {
        return collect($rows)
            ->groupBy(fn (Attendance $r) => $r->employee_id . '|' . Carbon::parse($r->date)->toDateString())
            ->map(fn (Collection $group) => new self(
                // In the order they were WORKED, which is not the order of
                // their clock faces: a night crew's second half reads 1:00 AM
                // and follows their first half at 8:00 PM. A row with no
                // time-in has no moment at all and goes at the end.
                $group->sortBy(fn (Attendance $r) => $r->time_in
                        ? '0' . self::momentIn($r)->format('Y-m-d H:i:s')
                        : '9')
                      ->values()
            ))
            ->values();
    }

    /**
     * When a stretch began, as a moment rather than a reading on a clock.
     *
     * Rows from before the kiosk stored full timestamps keep the time only,
     * and a time is resolved against the date the row is filed under. That is
     * right for a day crew and wrong by a whole day for a night one: their
     * workday is filed under the evening it opened, so their 1:00 AM is the
     * NEXT morning. Anything reading earlier than the workday's own start is
     * therefore the following day — which is the same rule Shift::layOut uses
     * to decide that a shift crosses midnight.
     *
     * Without it a night sorted second-half-first and read "1:00 AM – 12:00
     * AM +1", a day run backwards.
     */
    public static function momentIn(Attendance $row): Carbon
    {
        return self::resolve($row, $row->time_in);
    }

    /** The same, for a time-out. */
    public static function momentOut(Attendance $row): ?Carbon
    {
        if (empty($row->time_out)) {
            return null;
        }

        $out = self::resolve($row, $row->time_out);
        $in  = self::momentIn($row);

        // A time-out reading earlier than its own time-in is the next
        // morning — the rule WorkSchedule::stretch has always applied.
        return $out->lessThan($in) ? $out->addDay() : $out;
    }

    private static function resolve(Attendance $row, $value): Carbon
    {
        $at   = WorkSchedule::moment($value, (string) $row->date)->startOfMinute();
        $sched = $row->shift?->schedule();

        if (! WorkSchedule::has($sched)) {
            return $at;
        }

        $opens = WorkSchedule::windows($sched, Carbon::parse($row->date)->toDateString())['AM'][0];

        return $at->lessThan($opens) ? $at->addDay() : $at;
    }

    /** One stretch as it was worked: [in, out], the out null while it is open. */
    public function partsOf(Attendance $row): array
    {
        return [self::momentIn($row), self::momentOut($row)];
    }

    /** The row every day-level question is answered from: the first one worked. */
    public function first(): Attendance
    {
        return $this->rows->first();
    }

    public function employee()
    {
        return $this->first()->employee;
    }

    /**
     * The site the day was worked at.
     *
     * The first stretch's, because one kiosk is carried between sites and a
     * worker who moved at lunch has two. Naming the first is honest about
     * where the day started; the office opens the record for the rest.
     */
    public function site()
    {
        return $this->rows->first(fn (Attendance $r) => $r->site)?->site;
    }

    /**
     * The shift the day was worked under — the one stamped on the record, not
     * the worker's shift today, which may have changed since.
     *
     * The first stretch's, for the same reason as site(). Null on a record
     * from before shifts were stamped.
     */
    public function shift()
    {
        return $this->rows->first(fn (Attendance $r) => $r->shift)?->shift;
    }

    public function date(): Carbon
    {
        return Carbon::parse($this->first()->date);
    }

    /** Every attendance row behind this day — what a delete has to take. */
    public function ids(): array
    {
        return $this->rows->pluck('id')->all();
    }

    /** The sessions the day actually touched, in the order they were worked. */
    public function sessions(): array
    {
        return $this->rows->pluck('session')->filter()->unique()->values()->all();
    }

    /** Each stretch as it was worked: its session, its clock in, its clock out. */
    public function stretches(): Collection
    {
        return $this->rows->filter(fn (Attendance $r) => $r->time_in)->values();
    }

    /** Still on site: some stretch of the day was never closed. */
    public function isOpen(): bool
    {
        return $this->rows->contains(fn (Attendance $r) => $r->time_in && empty($r->time_out));
    }

    /**
     * The day's status, read off the day rather than off one of its rows.
     *
     * A day with an unclosed stretch is that stretch's answer — invalid if
     * the shift is well over, still active if it is not — because an
     * afternoon closed neatly does not settle a morning nobody closed.
     */
    public function status(): string
    {
        $open = $this->rows->first(fn (Attendance $r) => $r->time_in && empty($r->time_out));

        if ($open) {
            return $open->status;
        }

        return $this->rows->contains(fn (Attendance $r) => $r->time_in) ? 'present' : 'absent';
    }

    /** Any stretch the system had to close for them. */
    public function needsReview(): bool
    {
        return $this->rows->contains(fn (Attendance $r) => $r->needs_review);
    }

    /** Why, for the tooltip — the first stretch that says. */
    public function reviewReason(): ?string
    {
        return $this->rows->first(fn (Attendance $r) => $r->needs_review)?->close_reason;
    }

    /** When the day began. */
    public function firstIn(): ?Carbon
    {
        $first = $this->stretches()->first();

        return $first ? self::momentIn($first) : null;
    }

    /**
     * When the day ended, or null while a stretch of it is still open.
     *
     * The last stretch's time-out, not the greatest of them: they are already
     * in time order, and a night that ran into the morning has to keep the
     * date its own row carries.
     */
    public function lastOut(): ?Carbon
    {
        $last = $this->stretches()->last();

        return $last ? self::momentOut($last) : null;
    }

    /**
     * How many calendar days after the day began its last time-out landed —
     * 1 for a night crew that went home the following morning.
     *
     * The tables print clock times only, so without this a night reads as a
     * day run backwards.
     */
    public function outDaysLater(): int
    {
        $in  = $this->firstIn();
        $out = $this->lastOut();

        if (! $in || ! $out) {
            return 0;
        }

        return (int) $in->copy()->startOfDay()->diffInDays($out->copy()->startOfDay());
    }

    /** More than one stretch, so the day is worth spelling out underneath. */
    public function hasSeveralStretches(): bool
    {
        return $this->stretches()->count() > 1;
    }
}
