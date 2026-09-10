<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * The shape of a shift's working day: two sessions with a break between them,
 * and overtime once the second one ends.
 *
 * Works on the plain array Shift::lookup() produces, so payroll (which reads
 * that lookup) and the kiosk (which holds the model) measure a day the same
 * way — one set of arithmetic, not two that drift apart.
 *
 *   Day    AM 08:00–12:00 · break · PM 13:00–17:00 · overtime after 17:00
 *   Night  AM 20:00–00:00 · break · PM 01:00–05:00 · overtime after 05:00
 *
 * "AM" and "PM" name the first and second half of the shift, which for the
 * night crew is not what the clock says. The attendance `session` column
 * keeps that meaning.
 */
final class WorkSchedule
{
    /** A schedule is only usable with all four boundaries set. */
    public static function has(?array $s): bool
    {
        return $s !== null
            && ! empty($s['am_starts_at']) && ! empty($s['am_ends_at'])
            && ! empty($s['pm_starts_at']) && ! empty($s['pm_ends_at']);
    }

    /**
     * The two sessions of the shift day that starts on $date, as moments.
     *
     * A boundary that reads earlier than the one before it is the next
     * morning — that is how a night shift's 12:00 and 5:00 follow its 8:00 PM.
     *
     * @return array{AM: array{0: Carbon, 1: Carbon}, PM: array{0: Carbon, 1: Carbon}}
     */
    public static function windows(array $s, string $date): array
    {
        $points = [];
        $prev   = null;

        foreach (['am_starts_at', 'am_ends_at', 'pm_starts_at', 'pm_ends_at'] as $key) {
            $time = (string) $s[$key];

            if ($prev === null) {
                $at = Carbon::parse($date . ' ' . $time);
            } else {
                $at = $prev->copy()->setTimeFromTimeString($time);
                if ($at->lessThanOrEqualTo($prev)) {
                    $at->addDay();
                }
            }

            $points[] = $at;
            $prev     = $at;
        }

        return ['AM' => [$points[0], $points[1]], 'PM' => [$points[2], $points[3]]];
    }

    /** How long before the shift starts TIME IN opens. */
    private static function opensMinutes(array $s): int
    {
        return (int) ($s['opens'] ?? 120);
    }

    /**
     * The shift day a moment belongs to: the date the shift started on. A night
     * shift's 3 AM belongs to the evening before.
     */
    public static function shiftDayFor(array $s, Carbon $at): string
    {
        $today = $at->toDateString();
        $opens = self::windows($s, $today)['AM'][0]->subMinutes(self::opensMinutes($s));

        return $at->lessThan($opens) ? $at->copy()->subDay()->toDateString() : $today;
    }

    /**
     * When TIME IN is accepted for the shift day containing $at: from a while
     * before the shift starts until it ends.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function timeInWindow(array $s, Carbon $at): array
    {
        $w = self::windows($s, self::shiftDayFor($s, $at));

        return [$w['AM'][0]->copy()->subMinutes(self::opensMinutes($s)), $w['PM'][1]->copy()];
    }

    public static function acceptsTimeInAt(array $s, Carbon $at): bool
    {
        [$open, $close] = self::timeInWindow($s, $at);

        return $at->greaterThanOrEqualTo($open) && $at->lessThan($close);
    }

    /** The session a time-in at $at opens: AM until the first half ends, PM after. */
    public static function sessionAt(array $s, Carbon $at): string
    {
        $w = self::windows($s, self::shiftDayFor($s, $at));

        return $at->lessThan($w['AM'][1]) ? 'AM' : 'PM';
    }

    /** Where a session ends on a given shift day. */
    public static function sessionEnd(array $s, string $session, string $shiftDay): Carbon
    {
        $w = self::windows($s, $shiftDay);

        return ($session === 'AM' ? $w['AM'][1] : $w['PM'][1])->copy();
    }

    /** Where a session starts on a given shift day. */
    public static function sessionStart(array $s, string $session, string $shiftDay): Carbon
    {
        $w = self::windows($s, $shiftDay);

        return ($session === 'AM' ? $w['AM'][0] : $w['PM'][0])->copy();
    }

    /** What the daily rate buys: the two sessions added together. */
    public static function paidHours(array $s): float
    {
        $w = self::windows($s, '2026-01-05');

        return self::hours($w['AM'][0], $w['AM'][1]) + self::hours($w['PM'][0], $w['PM'][1]);
    }

    /**
     * Split a stretch worked into what the day's rate pays for and the overtime
     * after the shift ends. Arriving early and the break count as neither.
     *
     * @return array{regular: float, ot: float, segments: list<array{0: Carbon, 1: Carbon, 2: bool}>}
     */
    public static function split(array $s, Carbon $in, Carbon $out, string $shiftDay): array
    {
        $w       = self::windows($s, $shiftDay);
        $otStart = $w['PM'][1]->copy();

        $bands = [
            [$w['AM'][0], $w['AM'][1], false],
            [$w['PM'][0], $w['PM'][1], false],
            [$otStart, $otStart->copy()->addDay(), true],
        ];

        $regular  = 0.0;
        $ot       = 0.0;
        $segments = [];

        foreach ($bands as [$b1, $b2, $isOt]) {
            $from = $in->greaterThan($b1) ? $in->copy() : $b1->copy();
            $to   = $out->lessThan($b2) ? $out->copy() : $b2->copy();

            if ($to->greaterThan($from)) {
                $segments[] = [$from, $to, $isOt];
                $isOt ? $ot += self::hours($from, $to) : $regular += self::hours($from, $to);
            }
        }

        return ['regular' => $regular, 'ot' => $ot, 'segments' => $segments];
    }

    /** Hours between 10 PM and 6 AM inside one stretch — the night differential. */
    public static function nightHoursIn(Carbon $from, Carbon $to): float
    {
        $total = 0.0;
        $day   = $from->copy()->subDay()->startOfDay();

        while ($day->lessThan($to)) {
            $n1 = $day->copy()->setTime(22, 0);
            $n2 = $n1->copy()->addHours(8);

            $a = $from->greaterThan($n1) ? $from : $n1;
            $b = $to->lessThan($n2) ? $to : $n2;
            if ($b->greaterThan($a)) {
                $total += self::hours($a, $b);
            }

            $day->addDay();
        }

        return $total;
    }

    /**
     * A stored clock value as a moment. Older rows keep only the time; the
     * kiosk has always stored the full date and time.
     */
    public static function moment($value, string $date): Carbon
    {
        $v = trim((string) $value);

        return preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $v)
            ? Carbon::parse($date . ' ' . $v)
            : Carbon::parse($v);
    }

    /**
     * A record's in and out as moments. A time-only out that reads earlier than
     * the in is the next morning.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function stretch($in, $out, string $date): array
    {
        $a = self::moment($in, $date);
        $b = self::moment($out, $date);

        if ($b->lessThan($a)) {
            $b->addDay();
        }

        return [$a, $b];
    }

    public static function hours(Carbon $a, Carbon $b): float
    {
        return abs($a->diffInSeconds($b)) / 3600;
    }

    /** "8:00 AM" — how the kiosk and its messages write a time. */
    public static function label(Carbon $at): string
    {
        return $at->format('g:i A');
    }
}
