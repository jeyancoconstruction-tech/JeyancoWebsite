<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The shape of a working day, which payroll had been assuming.
     *
     * Eight hours and a Monday week were written into PayrollService as bare
     * numbers — correct for this office, but not a decision anybody could see
     * or change. The expected time-in and the grace period are new: nothing
     * measured lateness before, so a worker who came in at ten was paid for the
     * hours they worked and the office had no figure for the ones they did not.
     *
     * The defaults are exactly what the code did before, so a fresh install and
     * an existing one compute identically until somebody changes them.
     */
    public function up(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            // When a shift is meant to start, and how long after it before the
            // day counts as late. Grace is a real payroll concept: a worker two
            // minutes behind a jeepney is not the same as one an hour late.
            $table->time('expected_time_in')->default('08:00:00');
            $table->unsignedSmallInteger('grace_period_minutes')->default(15);

            // The hours a daily rate buys. It divides the rate into an hourly
            // one and marks where overtime begins — both were 8.
            $table->decimal('standard_hours_per_day', 4, 2)->default(8);

            // Hours past the standard become overtime on their own. An office
            // that wants overtime approved rather than assumed turns this off,
            // and the extra hours pay at the plain rate.
            $table->boolean('auto_count_overtime')->default(true);

            // The pay period. 1 = Monday, matching Carbon's ISO day numbers,
            // which is what startOfWeek() was hardcoded to.
            $table->unsignedTinyInteger('week_starts_on')->default(1);
            $table->string('payroll_cycle', 10)->default('weekly');
        });
    }

    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->dropColumn([
                'expected_time_in', 'grace_period_minutes', 'standard_hours_per_day',
                'auto_count_overtime', 'week_starts_on', 'payroll_cycle',
            ]);
        });
    }
};
