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
    /**
     * The night differential window, which the Labor Code fixes at ten in the
     * evening to six in the morning (Art. 86). Not a setting: it is the law,
     * the same for every shift and every office, and a figure typed into a
     * form could only ever make it wrong.
     *
     * Named here because it was written out twice — once for the scheduled
     * count and once for the flat one — and two copies of a rule are two
     * rules waiting to disagree.
     */
    public const NIGHT_FROM_HOUR = 22;
    public const NIGHT_TO_HOUR   = 6;

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

    /**
     * What the daily rate buys: the shift's regular hours, or both sessions
     * when it does not name a figure.
     *
     * This is the divisor for the hourly rate and the length of a full day,
     * so it has to be the paid part — on an eleven-hour shift that pays eight
     * at the plain rate, a day's wage buys eight hours, not eleven.
     */
    public static function paidHours(array $s): float
    {
        $w        = self::windows($s, '2026-01-05');
        $sessions = self::hours($w['AM'][0], $w['AM'][1]) + self::hours($w['PM'][0], $w['PM'][1]);
        $cap      = self::regularMinutes($s);

        return $cap === null ? $sessions : min($sessions, $cap / 60);
    }

    /**
     * How many paid minutes a shift counts as regular before the rest of it
     * becomes overtime. Null is the older rule: all of it.
     */
    public static function regularMinutes(array $s): ?int
    {
        $v = $s['regular_minutes'] ?? null;

        return ($v === null || $v === '') ? null : max(0, (int) $v);
    }

    /**
     * Split a stretch worked into what the day's rate pays for and the
     * overtime. Arriving early and the break count as neither.
     *
     * Once a shift says how many of its hours the daily rate buys, that
     * figure is what overtime means: the hours past it. A crew on from eight
     * in the morning to eight at night works eleven paid hours; if the day
     * buys eight, the last three are overtime even though the shift has not
     * ended. The changeover is counted in paid time, so the break does not
     * bring it forward.
     *
     * It cuts the other way too, and that is the part worth stating. Somebody
     * who starts at half past one and leaves at nine has worked six and a
     * half hours — under the eight the day buys — so none of it is overtime,
     * including the hour after the shift ended. Paying a premium there would
     * be paying overtime to a worker who had not worked a full day, which is
     * neither what the setting says nor what the law asks for.
     *
     * A shift with no figure set keeps the older rule: regular until the
     * shift ends, overtime after, because there is nothing else to measure
     * against.
     *
     * $regularUsed is how many regular minutes earlier records of the same
     * workday have already taken. PayrollService works it out for the whole
     * range up front; a caller looking at one stretch on its own can leave
     * it at zero.
     *
     * @return array{regular: float, ot: float, segments: list<array{0: Carbon, 1: Carbon, 2: bool}>}
     */
    public static function split(array $s, Carbon $in, Carbon $out, string $shiftDay, int $regularUsed = 0): array
    {
        $w       = self::windows($s, $shiftDay);
        $otStart = $w['PM'][1]->copy();

        // Minutes of regular time still to be bought by the day's rate.
        //
        // Never more than the two sessions hold: a figure larger than the
        // shift cannot be reached inside it, and letting the hours after the
        // shift make up the difference would pay them at the plain rate
        // instead of as the overtime they are.
        $cap = self::regularMinutes($s);

        if ($cap !== null) {
            $cap = min($cap, (int) round(
                (self::hours($w['AM'][0], $w['AM'][1]) + self::hours($w['PM'][0], $w['PM'][1])) * 60
            ));
        }

        // What is left of it after earlier stretches of the same day. A day
        // arrives as several records — a morning, an afternoon after lunch,
        // a stretch begun again after a mistaken time-out — and its regular
        // hours are bought once between them, not afresh for each.
        $left = $cap === null ? null : max(0, $cap - $regularUsed);

        $bands = [
            [$w['AM'][0], $w['AM'][1], false],
            [$w['PM'][0], $w['PM'][1], false],
            // Past the end of the shift. With a figure to measure against
            // this is paid time like any other until that figure runs out;
            // without one there is nothing to compare it to, so it is
            // overtime from its first minute, as it always was.
            [$otStart, $otStart->copy()->addDay(), $cap === null],
        ];

        $regular  = 0.0;
        $ot       = 0.0;
        $segments = [];

        $keep = function (Carbon $from, Carbon $to, bool $isOt) use (&$regular, &$ot, &$segments) {
            $segments[] = [$from, $to, $isOt];
            $isOt ? $ot += self::hours($from, $to) : $regular += self::hours($from, $to);
        };

        foreach ($bands as [$b1, $b2, $isOt]) {
            $from = $in->greaterThan($b1) ? $in->copy() : $b1->copy();
            $to   = $out->lessThan($b2) ? $out->copy() : $b2->copy();

            if (! $to->greaterThan($from)) {
                continue;
            }

            // Past the end of the shift, or no figure set: unchanged.
            if ($isOt || $left === null) {
                $keep($from, $to, $isOt);
                continue;
            }

            $minutes = (int) round(self::hours($from, $to) * 60);

            if ($minutes <= $left) {
                $keep($from, $to, false);
                $left -= $minutes;
                continue;
            }

            // The day's regular hours run out partway through this session.
            if ($left > 0) {
                $turns = $from->copy()->addMinutes($left);
                $keep($from, $turns, false);
                $keep($turns, $to, true);
            } else {
                $keep($from, $to, true);
            }

            $left = 0;
        }

        return ['regular' => $regular, 'ot' => $ot, 'segments' => $segments];
    }

    /** When the first overtime of a stretch begins, if any was worked. */
    public static function overtimeStart(array $split): ?Carbon
    {
        foreach ($split['segments'] as [$from, , $isOt]) {
            if ($isOt) {
                return $from;
            }
        }

        return null;
    }

    /** Hours inside the night window within one stretch — the differential. */
    public static function nightHoursIn(Carbon $from, Carbon $to): float
    {
        $total = 0.0;
        $day   = $from->copy()->subDay()->startOfDay();
        $span  = 24 - self::NIGHT_FROM_HOUR + self::NIGHT_TO_HOUR;

        while ($day->lessThan($to)) {
            $n1 = $day->copy()->setTime(self::NIGHT_FROM_HOUR, 0);
            $n2 = $n1->copy()->addHours($span);

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
        // Counted in whole minutes. The kiosk stores the second of each scan,
        // but every screen shows clock times to the minute, so a stretch that
        // reads "8:00 PM – 8:01 PM" on Attendance is one minute here too, not
        // the minute and fifty seconds its seconds would make it. moment()
        // itself keeps the seconds: the kiosk's double-read guard needs them.
        $a = self::moment($in, $date)->startOfMinute();
        $b = self::moment($out, $date)->startOfMinute();

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
