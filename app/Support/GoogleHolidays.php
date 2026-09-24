<?php

namespace App\Support;

use App\Models\GoogleHoliday;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Fills the official holiday calendar from Google Calendar.
 *
 * Reads Google's public "Holidays in Philippines" calendar through the
 * Calendar API with the key in GOOGLE_CALENDAR_API_KEY, and keeps what it
 * finds in `google_holidays`, which PhilippineHolidays::forYear() prefers to
 * its own offline list. Google marks every day off the same way, so whether a
 * day is regular (200%) or special (130%) is decided by its name — the Labor
 * Code lists the regular ones. Observances (Easter Sunday, Ramadan Start) and
 * the holidays kept only in the Muslim provinces are left out.
 *
 * **A sync never changes a day that has already passed.** Payroll Records
 * re-prices any period on demand, so a holiday appearing, vanishing or
 * changing type on a past date would quietly move pay that was already
 * given out. So:
 *   - the first time a year is synced, its past days keep the holidays they
 *     were paid under, and a holiday only Google knows for a past date comes
 *     in switched off — clicking it on is a decision, not a side effect;
 *   - after that, only today and later follow Google. A tentative Eid date
 *     that Google moves is moved; a day that has gone by stays as it was.
 *
 * There is no scheduler on the server, so the daily refresh rides on a page
 * load (syncIfStale) and runs after the response has gone out.
 */
final class GoogleHolidays
{
    private const EVENTS_URL = 'https://www.googleapis.com/calendar/v3/calendars/%s/events';

    /** Present while the calendar is fresh enough not to be fetched again. */
    private const FRESH = 'holidays.google.fresh';

    /** When the last sync that went through finished. */
    private const SYNCED_AT = 'holidays.google.synced_at';

    /** Years either side of this one that a sync covers. */
    private const YEARS_BACK  = 2;
    private const YEARS_AHEAD = 5;

    public static function configured(): bool
    {
        return filled(config('services.google_calendar.key'));
    }

    public static function syncedAt(): ?Carbon
    {
        $at = Cache::get(self::SYNCED_AT);

        return $at ? Carbon::parse($at) : null;
    }

    /**
     * Sync once a day, after this request's response is sent. A failed sync
     * is logged and tried again an hour later rather than on every page.
     */
    public static function syncIfStale(): void
    {
        if (! self::configured()) {
            return;
        }

        try {
            // add() is atomic: only one request per window takes the job.
            if (! Cache::add(self::FRESH, true, now()->addHour())) {
                return;
            }
        } catch (Throwable $e) {
            report($e);

            return;
        }

        // Once: a long-lived app (the test suite, a worker) runs every
        // terminating callback ever registered at the end of each request.
        $done = false;

        app()->terminating(function () use (&$done) {
            if ($done) {
                return;
            }

            $done = true;

            try {
                self::sync();
            } catch (Throwable $e) {
                report($e);
            }
        });
    }

    /**
     * Fetch Google's holidays and bring `google_holidays` up to date.
     *
     * @return array{added:int, updated:int, removed:int}
     *
     * @throws RuntimeException with a message fit to show the admin
     */
    public static function sync(): array
    {
        $today    = now()->toDateString();
        $thisYear = (int) now()->year;
        $fromYear = $thisYear - self::YEARS_BACK;
        $toYear   = $thisYear + self::YEARS_AHEAD;

        $google = self::fetch($fromYear, $toYear);

        $stored = GoogleHoliday::query()
            ->whereBetween('date', ["$fromYear-01-01", "$toYear-12-31"])
            ->get()
            ->keyBy(fn ($row) => substr((string) $row->date, 0, 10));

        $counts = ['added' => 0, 'updated' => 0, 'removed' => 0];

        // One announcement for the lot, not one per row: the rows are written
        // without model events, and the pages re-read themselves either way.
        GoogleHoliday::withoutEvents(function () use ($google, $stored, $today, $fromYear, $toYear, &$counts) {
            DB::transaction(function () use ($google, $stored, $today, $fromYear, $toYear, &$counts) {
                foreach (range($fromYear, $toYear) as $year) {
                    $fromGoogle = array_filter(
                        $google,
                        fn ($date) => str_starts_with($date, "$year-"),
                        ARRAY_FILTER_USE_KEY
                    );

                    // Nothing from Google for the year: leave it on whatever it
                    // has now, whether that is an earlier sync or the offline list.
                    if ($fromGoogle === []) {
                        continue;
                    }

                    $mine = $stored->filter(fn ($row, $date) => str_starts_with($date, "$year-"));
                    $want = $mine->isEmpty()
                        ? self::firstSight($year, $fromGoogle, $today)
                        : self::ahead($fromGoogle, $today);

                    foreach ($want as $date => $info) {
                        $row = $mine->get($date);

                        if (! $row) {
                            GoogleHoliday::create(['date' => $date] + $info);
                            $counts['added']++;
                        } elseif ($row->title !== $info['title'] || $row->type !== $info['type'] || (bool) $row->is_active !== $info['is_active']) {
                            $row->update($info);
                            $counts['updated']++;
                        }
                    }

                    // Days ahead that Google no longer lists: a tentative date
                    // that moved, or a holiday withdrawn. Past days are kept.
                    foreach ($mine as $date => $row) {
                        if ($date >= $today && ! isset($want[$date])) {
                            $row->delete();
                            $counts['removed']++;
                        }
                    }
                }
            });
        });

        if (array_sum($counts) > 0) {
            Live::bump('payroll', 'settings');
        }

        Cache::put(self::FRESH, true, now()->addDay());
        Cache::forever(self::SYNCED_AT, now()->toIso8601String());

        return $counts;
    }

    /**
     * A year synced for the first time. Days already past keep the holidays
     * the offline calendar gave them — that is what they were paid under —
     * with Google's name where Google agrees; a past holiday only Google
     * knows arrives switched off. Today and later are Google's.
     */
    private static function firstSight(int $year, array $fromGoogle, string $today): array
    {
        $want = [];

        foreach (PhilippineHolidays::computed($year) as $date => $info) {
            if ($date < $today) {
                $want[$date] = [
                    'title'     => $fromGoogle[$date]['title'] ?? $info['title'],
                    'type'      => $info['type'],
                    'is_active' => true,
                ];
            }
        }

        foreach ($fromGoogle as $date => $info) {
            if ($date >= $today) {
                $want[$date] = $info;
            } elseif (! isset($want[$date])) {
                $want[$date] = ['is_active' => false] + $info;
            }
        }

        ksort($want);

        return $want;
    }

    /** A year synced before: only today and later follow Google. */
    private static function ahead(array $fromGoogle, string $today): array
    {
        $want = [];

        foreach ($fromGoogle as $date => $info) {
            if ($date >= $today) {
                $want[$date] = $info;
            }
        }

        return $want;
    }

    /**
     * The national holidays Google lists between two years, inclusive, as
     * ['Y-m-d' => ['title' => ..., 'type' => 'regular'|'special', 'is_active' => bool]].
     *
     * @throws RuntimeException
     */
    public static function fetch(int $fromYear, int $toYear): array
    {
        $key = config('services.google_calendar.key');

        if (blank($key)) {
            throw new RuntimeException('No Google Calendar API key is set. Add GOOGLE_CALENDAR_API_KEY to the environment.');
        }

        $url = sprintf(self::EVENTS_URL, rawurlencode((string) config('services.google_calendar.holidays')));

        $holidays  = [];
        $pageToken = null;
        $pages     = 0;

        do {
            try {
                $response = Http::timeout(15)->acceptJson()->get($url, array_filter([
                    'key'          => $key,
                    'timeMin'      => "$fromYear-01-01T00:00:00Z",
                    'timeMax'      => ($toYear + 1) . '-01-01T00:00:00Z',
                    'singleEvents' => 'true',
                    'orderBy'      => 'startTime',
                    'maxResults'   => 2500,
                    'pageToken'    => $pageToken,
                ]));
            } catch (ConnectionException $e) {
                throw new RuntimeException('Google Calendar could not be reached. Try again in a moment.', 0, $e);
            }

            if ($response->failed()) {
                $why = $response->json('error.message') ?: 'HTTP ' . $response->status();

                throw new RuntimeException('Google Calendar refused the request: ' . $why);
            }

            foreach ((array) $response->json('items', []) as $event) {
                self::take($holidays, (array) $event);
            }

            $pageToken = $response->json('nextPageToken');
        } while ($pageToken && ++$pages < 10);

        ksort($holidays);

        return self::twinsOff($holidays);
    }

    /**
     * Google lists some holidays twice on neighbouring days — "Eid al-Adha"
     * and then "Eid al-Adha Holiday", "Lunar New Year Holiday" beside
     * "Lunar New Year's Day" — where the Philippines keeps one day: every
     * Eid al-Adha from 2021 to 2025 was the first of the pair. The "… Holiday"
     * twin comes in switched off, to be turned on if a proclamation says so,
     * rather than paying two regular days for one. A "… Holiday" with no
     * twin beside it (a moved Ninoy Aquino Day) is the day itself, and counts.
     */
    private static function twinsOff(array $holidays): array
    {
        foreach ($holidays as $date => $holiday) {
            if (! preg_match('/^(.+?)\s+holiday\b/i', $holiday['title'], $m)) {
                continue;
            }

            $base = strtolower(trim($m[1]));

            foreach ([-1, 1] as $offset) {
                $next = CarbonImmutable::parse($date)->addDays($offset)->toDateString();
                $twin = $holidays[$next]['title'] ?? null;

                if ($twin !== null && str_starts_with(strtolower($twin), $base) && ! preg_match('/\bholiday\b/i', $twin)) {
                    $holidays[$date]['is_active'] = false;
                }
            }
        }

        return $holidays;
    }

    /** Add one Google event to the list, if it is a national day off. */
    private static function take(array &$holidays, array $event): void
    {
        if (($event['status'] ?? '') === 'cancelled') {
            return;
        }

        $start = $event['start']['date'] ?? null;   // holidays are all-day events
        $title = trim((string) ($event['summary'] ?? ''));

        if (! is_string($start) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || $title === '') {
            return;
        }

        // "Public holiday" or "Observance …". An observance is a day that is
        // marked, not a day off.
        if (stripos(ltrim((string) ($event['description'] ?? '')), 'observance') === 0) {
            return;
        }

        if (PhilippineHolidays::isRegional($title)) {
            return;
        }

        $type = PhilippineHolidays::typeOf($title);

        // An all-day event ends the morning after its last day.
        $day  = CarbonImmutable::parse($start);
        $end  = $event['end']['date'] ?? null;
        $last = is_string($end) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)
            ? CarbonImmutable::parse($end)->subDay()
            : $day;

        for ($i = 0; $i < 7 && $day->lte($last); $i++, $day = $day->addDay()) {
            $date = $day->toDateString();

            // Two holidays on one day: the regular one decides the pay.
            if (! isset($holidays[$date]) || ($type === PhilippineHolidays::REGULAR && $holidays[$date]['type'] !== PhilippineHolidays::REGULAR)) {
                $holidays[$date] = ['title' => $title, 'type' => $type, 'is_active' => true];
            }
        }
    }
}
