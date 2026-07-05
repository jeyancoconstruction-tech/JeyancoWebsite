<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Official Philippine holiday calendar.
 *
 * Computes the nationwide regular and special (non-working) holidays for any
 * year — fully offline (no API / Composer package). Fixed-date holidays are
 * listed directly; movable ones are derived:
 *   - Maundy Thursday / Good Friday / Black Saturday → from Easter (computus)
 *   - National Heroes Day → last Monday of August
 *
 * NOTE: Lunar / proclamation-based holidays (Chinese New Year, Eid'l Fitr,
 * Eid'l Adha) vary yearly by presidential proclamation and cannot be computed
 * reliably offline — admins add those manually in the Holidays tab.
 */
class PhilippineHolidays
{
    public const REGULAR = 'regular';
    public const SPECIAL = 'special';

    /**
     * All official holidays for a year as ['Y-m-d' => ['title' => ..., 'type' => ...]].
     */
    public static function forYear(int $year): array
    {
        $holidays = [];
        $add = function (string $date, string $title, string $type) use (&$holidays) {
            $holidays[$date] = ['title' => $title, 'type' => $type];
        };

        // --- Regular holidays (fixed date) ---
        $add("$year-01-01", "New Year's Day", self::REGULAR);
        $add("$year-04-09", "Araw ng Kagitingan (Day of Valor)", self::REGULAR);
        $add("$year-05-01", "Labor Day", self::REGULAR);
        $add("$year-06-12", "Independence Day", self::REGULAR);
        $add("$year-11-30", "Bonifacio Day", self::REGULAR);
        $add("$year-12-25", "Christmas Day", self::REGULAR);
        $add("$year-12-30", "Rizal Day", self::REGULAR);

        // National Heroes Day — last Monday of August
        $heroes = Carbon::create($year, 8, 31);
        while (! $heroes->isMonday()) {
            $heroes->subDay();
        }
        $add($heroes->toDateString(), "National Heroes Day", self::REGULAR);

        // Easter-derived holidays
        $easter = self::easter($year);
        $add($easter->copy()->subDays(3)->toDateString(), "Maundy Thursday", self::REGULAR);
        $add($easter->copy()->subDays(2)->toDateString(), "Good Friday", self::REGULAR);
        $add($easter->copy()->subDays(1)->toDateString(), "Black Saturday", self::SPECIAL);

        // --- Special (non-working) holidays (fixed date) ---
        $add("$year-02-25", "EDSA People Power Anniversary", self::SPECIAL);
        $add("$year-08-21", "Ninoy Aquino Day", self::SPECIAL);
        $add("$year-11-01", "All Saints' Day", self::SPECIAL);
        $add("$year-11-02", "All Souls' Day", self::SPECIAL);
        $add("$year-12-08", "Feast of the Immaculate Conception", self::SPECIAL);
        $add("$year-12-31", "Last Day of the Year", self::SPECIAL);

        ksort($holidays);

        return $holidays;
    }

    /**
     * Official holiday info for a specific date, or null if it isn't one.
     */
    public static function infoFor(string $date): ?array
    {
        $date = Carbon::parse($date)->toDateString();
        $year = (int) substr($date, 0, 4);

        return self::forYear($year)[$date] ?? null;
    }

    /**
     * Human-readable label for a holiday type.
     */
    public static function typeLabel(?string $type): string
    {
        return match ($type) {
            self::REGULAR => 'Regular Holiday',
            self::SPECIAL => 'Special (Non-Working)',
            default       => 'Custom Holiday',
        };
    }

    /**
     * Easter Sunday (Gregorian) via the Meeus/Jones/Butcher algorithm.
     */
    private static function easter(int $year): Carbon
    {
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;

        return Carbon::create($year, $month, $day);
    }
}
