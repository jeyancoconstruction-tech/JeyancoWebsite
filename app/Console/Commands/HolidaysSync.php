<?php

namespace App\Console\Commands;

use App\Support\GoogleHolidays;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Pull the official holidays from Google Calendar now.
 *
 *     php artisan holidays:sync
 *
 * The same sync the Sync Google button runs, and the one a page load runs once
 * a day — for after GOOGLE_CALENDAR_API_KEY is set or changed on Railway, so
 * the calendar can be filled and checked without signing in to the site.
 */
class HolidaysSync extends Command
{
    protected $signature = 'holidays:sync';

    protected $description = 'Pull the official holidays from Google Calendar and report what changed';

    public function handle(): int
    {
        try {
            $counts = GoogleHolidays::sync();
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Synced from Google Calendar: {$counts['added']} added, {$counts['updated']} updated, {$counts['removed']} removed.");

        return self::SUCCESS;
    }
}
