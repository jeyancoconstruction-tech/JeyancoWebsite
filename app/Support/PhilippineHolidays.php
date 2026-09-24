<?php

namespace App\Support;

use App\Models\GoogleHoliday;
use Carbon\Carbon;
use Illuminate\Database\QueryException;

/**
 * Official Philippine holiday calendar.
 *
 * A year Google Calendar has been synced for (App\Support\GoogleHolidays) is
 * read from `google_holidays`: Google knows the dates that only a presidential
 * proclamation sets — Eid'l Fitr, Eid'l Adha, Chinese New Year, a holiday
 * moved to make a long weekend — and nothing computed offline can.
 *
 * Any other year is computed here, fully offline. Fixed-date holidays are
 * listed directly; movable ones are derived:
 *   - Maundy Thursday / Good Friday / Black Saturday → from Easter (computus)
 *   - National Heroes Day → last Monday of August
 * Proclamation-based holidays are missing from that list; admins add those
 * manually in the Holidays tab when there is no Google Calendar key.
 */
class PhilippineHolidays
{
    public const REGULAR = 'regular';
    public const SPECIAL = 'special';

    /**
     * The regular holidays, by name. The Labor Code fixes the list (Art. 94,
     * as amended by RA 9177, 9492 and 9849) and a proclamation only ever moves
     * or adds special days — so a holiday Google names that is not one of
     * these is a special (non-working) day. Google does not say which is which.
     */
    private const REGULAR_TITLES = [
        '/^new year.?s day/i',            // not Lunar New Year's Day
        '/maundy thursday/i',
        '/good friday/i',
        '/day of valou?r|kagitingan/i',
        '/labou?r day/i',
        '/independence day/i',
        '/national heroes/i',
        '/bonifacio/i',
        '/christmas day/i',
        '/rizal day/i',
        '/\beid\b.*\b(fitr|adha)\b/i',
    ];

    /**
     * Muslim holidays kept only in the Muslim provinces and cities (PD 1083,
     * Art. 170). Google's national calendar lists them anyway; the two Eids,
     * which are national, are not among them.
     */
    private const REGIONAL_TITLES = [
        '/\bisra\b|mi.?raj/i',
        '/amun jadid|islamic new year|muharram/i',
        '/maulid|mawlid/i',
    ];

    /**
     * All official holidays for a year as
     * ['Y-m-d' => ['title' => ..., 'type' => ..., 'is_active' => bool]].
     *
     * `is_active` is whether the day counts before an admin turns it on or
     * off. It is only ever false for a holiday Google named after its date had
     * already passed — see GoogleHolidays::sync().
     */
    public static function forYear(int $year): array
    {
        return self::fromGoogle($year) ?? self::computed($year);
    }

    /**
     * Regular or special, from a holiday's name.
     */
    public static function typeOf(string $title): string
    {
        foreach (self::REGULAR_TITLES as $pattern) {
            if (preg_match($pattern, $title)) {
                return self::REGULAR;
            }
        }

        return self::SPECIAL;
    }

    /**
     * Whether a holiday is kept only in the Muslim provinces.
     */
    public static function isRegional(string $title): bool
    {
        foreach (self::REGIONAL_TITLES as $pattern) {
            if (preg_match($pattern, $title)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The synced year, or null when Google has not been synced for it —
     * including before the migration that makes the table has been run.
     */
    private static function fromGoogle(int $year): ?array
    {
        try {
            $rows = GoogleHoliday::query()
                ->whereBetween('date', ["$year-01-01", "$year-12-31"])
                ->orderBy('date')
                ->get(['date', 'title', 'type', 'is_active']);
        } catch (QueryException) {
            return null;
        }

        if ($rows->isEmpty()) {
            return null;
        }

        $holidays = [];

        foreach ($rows as $row) {
            $holidays[substr((string) $row->date, 0, 10)] = [
                'title'     => $row->title,
                'type'      => $row->type,
                'is_active' => (bool) $row->is_active,
            ];
        }

        return $holidays;
    }

    /**
     * The holidays that can be worked out without Google, for any year.
     */
    public static function computed(int $year): array
    {
        $holidays = [];
        $add = function (string $date, string $title, string $type) use (&$holidays) {
            $holidays[$date] = ['title' => $title, 'type' => $type, 'is_active' => true];
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
