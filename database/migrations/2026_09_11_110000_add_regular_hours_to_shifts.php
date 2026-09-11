<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Where overtime begins inside a shift.
     *
     * Until now a shift was regular for the whole of its two sessions and
     * overtime only started after it ended. That describes an eight-to-five
     * day, and stops describing anything the moment the office runs a longer
     * one: a crew on from eight in the morning to eight at night is eleven
     * paid hours, all of it at the plain rate, with overtime unreachable
     * until they had been on site for twelve.
     *
     * A shift now says how many of its paid hours the daily rate buys. Past
     * that, and up to the end of the shift, the time is overtime.
     *
     * Backfilled to each shift's whole paid span, so nothing about what is
     * being run changes until somebody sets a shorter figure.
     */
    public function up(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            // Null means the old rule: the whole shift is regular.
            $table->unsignedSmallInteger('regular_minutes')->nullable()->after('pm_ends_at');
        });

        $break = (int) (DB::table('system_settings')->value('unpaid_break_minutes') ?? 0);

        foreach (DB::table('shifts')->get() as $shift) {
            if (! $shift->am_starts_at || ! $shift->pm_ends_at) {
                continue;
            }

            $span = \App\Models\Shift::spanMinutes(
                substr((string) $shift->am_starts_at, 0, 5),
                substr((string) $shift->pm_ends_at, 0, 5),
            );

            DB::table('shifts')->where('id', $shift->id)
                ->update(['regular_minutes' => max(60, $span - $break)]);
        }
    }

    public function down(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->dropColumn('regular_minutes');
        });
    }
};
