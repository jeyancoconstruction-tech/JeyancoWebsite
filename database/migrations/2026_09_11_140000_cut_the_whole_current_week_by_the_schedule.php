<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Put the whole of the current pay week on the shift's own sessions.
     *
     * The cutover was held at today so that days already counted kept the
     * rule they were counted under. The office has since asked for the new
     * arithmetic across the week instead, so the figures can be checked
     * against a period that is computed one way throughout.
     *
     * That is safe here in a way it would not normally be: every attendance
     * record in the system is from the 10th and the 11th, four rows of the
     * office's own test clocks, so nothing that was ever paid is re-cut.
     *
     * Monday rather than an arbitrary earlier date: a pay week computed half
     * by one rule and half by another is the thing the dated cutover exists
     * to prevent, and moving the line to a week boundary keeps that true.
     *
     * Only ever moves the date earlier.
     */
    private const FROM = '2026-09-07';

    public function up(): void
    {
        $settings = DB::table('system_settings')->first();

        if (! $settings) {
            return;
        }

        if ($settings->schedule_rules_from === null || $settings->schedule_rules_from > self::FROM) {
            DB::table('system_settings')->where('id', $settings->id)
                ->update(['schedule_rules_from' => self::FROM]);
        }
    }

    public function down(): void
    {
        // The date it held before is not recoverable, and guessing one would
        // change how a week is paid. Rolling back leaves it where it is.
    }
};
