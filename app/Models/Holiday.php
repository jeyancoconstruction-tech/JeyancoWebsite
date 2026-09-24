<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Support\PhilippineHolidays;
use Carbon\Carbon;

class Holiday extends Model
{
    protected $fillable = [
        'date',
        'title',
        'type',
        'is_official',
        'is_active',
    ];

    protected $casts = [
        'date'        => 'date',
        'is_official' => 'boolean',
        'is_active'   => 'boolean',
    ];

    /**
     * Return all ACTIVE holidays as ['Y-m-d' => 'regular'|'special'].
     * Used by PayrollService to apply the correct type-based multiplier.
     */
    public static function typeMap(): array
    {
        $map = [];

        foreach (self::relevantYears() as $year) {
            foreach (self::calendarFor($year) as $holiday) {
                if ($holiday['is_active']) {
                    $map[$holiday['date']] = $holiday['type'];
                }
            }
        }

        return $map;
    }

    /**
     * Return all ACTIVE holiday dates as normalised 'Y-m-d' strings.
     * Kept for backwards compatibility; prefer typeMap() for payroll math.
     */
    public static function dateList(): array
    {
        return array_keys(self::typeMap());
    }

    /**
     * Build the holiday calendar for a single year: every official Philippine
     * holiday, each annotated with its effective active/disabled status and
     * the id of the admin's override row, if there is one.
     *
     * There are no custom holidays. A row in `holidays` is only ever the on/off
     * an admin chose for an official day; one left on a date that is no longer
     * official — a custom holiday from before they were removed, or an Eid
     * Google has since moved — neither shows nor counts.
     *
     * @return array<int, array{date:string,title:string,type:string,is_official:bool,is_active:bool,id:?int}>
     */
    public static function calendarFor(int $year): array
    {
        $official = PhilippineHolidays::forYear($year);

        $rows = self::whereYear('date', $year)->get()
            ->keyBy(fn ($h) => Carbon::parse($h->date)->toDateString());

        $calendar = [];

        // As the calendar has them (on, except a past day Google named late)
        // unless an admin override row says otherwise.
        foreach ($official as $date => $info) {
            $row = $rows->get($date);
            $calendar[$date] = [
                'date'        => $date,
                'title'       => $row && $row->title ? $row->title : $info['title'],
                'type'        => $info['type'],
                'is_official' => true,
                'is_active'   => $row ? (bool) $row->is_active : ($info['is_active'] ?? true),
                'id'          => $row?->id,
            ];
        }

        ksort($calendar);

        return array_values($calendar);
    }

    /**
     * Years to consider for holiday recognition: every year present in
     * attendance plus the current year (so the calendar is never empty).
     */
    public static function relevantYears(): array
    {
        // The year is taken in PHP rather than in SQL. YEAR() is MySQL's; it
        // does not exist in SQLite, so this one line made every page that
        // reads a holiday — attendance among them — impossible to cover with
        // a test, and it failed with "no such function: YEAR" rather than
        // anything that named the cause.
        //
        // Distinct dates, not rows: a date column has at most 366 values a
        // year, so this is a small result whatever the size of the table.
        $years = Attendance::query()
            ->select('date')
            ->distinct()
            ->pluck('date')
            ->map(fn ($d) => (int) substr((string) $d, 0, 4))
            ->all();

        $years[] = (int) now()->year;
        $years = array_values(array_unique(array_filter($years)));
        sort($years);

        return $years;
    }
}
