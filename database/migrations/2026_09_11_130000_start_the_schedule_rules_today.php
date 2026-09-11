<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Bring the schedule rules forward to the day they were asked for.
     *
     * When the working day was written down, the cutover was set to the next
     * pay week — Monday the 14th — so that no period would be paid half by
     * the old flat rule and half by the shift's own sessions. The office has
     * since asked for the new count to start immediately.
     *
     * The 11th rather than the 7th on purpose: days already worked this week
     * keep the computation they were worked under. Bringing it back to
     * Monday would re-cut four days that have already been counted, and the
     * whole point of a dated cutover is that it never reaches backwards.
     *
     * Only ever moves the date earlier. An install that had already set an
     * earlier one is left alone.
     */
    private const FROM = '2026-09-11';

    public function up(): void
    {
        $settings = DB::table('system_settings')->first();

        if (! $settings) {
            return;
        }

        $current = $settings->schedule_rules_from;

        if ($current === null || $current > self::FROM) {
            DB::table('system_settings')->where('id', $settings->id)
                ->update(['schedule_rules_from' => self::FROM]);
        }
    }

    public function down(): void
    {
        // The date it held before this is not recoverable, and guessing one
        // would change how a week is paid. Rolling back leaves it where it is.
    }
};
