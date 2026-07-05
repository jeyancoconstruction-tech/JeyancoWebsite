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
     * Return all ACTIVE holidays as ['Y-m-d' => 'regular'|'special'|'custom'].
     * Used by PayrollService to apply the correct type-based multiplier.
     */
    public static function typeMap(): array
    {
        $map = [];

        foreach (self::relevantYears() as $year) {
            foreach (self::calendarFor($year) as $holiday) {
                if ($holiday['is_active']) {
                    $map[$holiday['date']] = $holiday['type'] ?? 'custom';
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
     * Build the merged holiday calendar for a single year: every official
     * Philippine holiday plus any manual entries, each annotated with its
     * effective active/disabled status and DB id (if a row exists).
     *
     * @return array<int, array{date:string,title:string,type:string,is_official:bool,is_active:bool,id:?int}>
     */
    public static function calendarFor(int $year): array
    {
        $official = PhilippineHolidays::forYear($year);

        $rows = self::whereYear('date', $year)->get()
            ->keyBy(fn ($h) => Carbon::parse($h->date)->toDateString());

        $calendar = [];

        // Official holidays — active unless an explicit disable override exists.
        foreach ($official as $date => $info) {
            $row = $rows->get($date);
            $calendar[$date] = [
                'date'        => $date,
                'title'       => $row && $row->title ? $row->title : $info['title'],
                'type'        => $info['type'],
                'is_official' => true,
                'is_active'   => $row ? (bool) $row->is_active : true,
                'id'          => $row?->id,
            ];
        }

        // Manual (custom) holidays that aren't part of the official list.
        foreach ($rows as $date => $row) {
            if (isset($calendar[$date])) {
                continue;
            }
            $calendar[$date] = [
                'date'        => $date,
                'title'       => $row->title ?: 'Custom Holiday',
                'type'        => $row->type ?: 'custom',
                'is_official' => false,
                'is_active'   => (bool) $row->is_active,
                'id'          => $row->id,
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
        $years = Attendance::query()
            ->selectRaw('DISTINCT YEAR(date) as y')
            ->pluck('y')
            ->map(fn ($y) => (int) $y)
            ->all();

        $years[] = (int) now()->year;
        $years = array_values(array_unique(array_filter($years)));
        sort($years);

        return $years;
    }
}
