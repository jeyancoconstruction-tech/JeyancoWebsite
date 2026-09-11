<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The meal period belongs to the shift, not to the office.
     *
     * It was one figure for everybody, which only holds while every crew
     * works the same day. Now that a shift carries its own hours and its own
     * regular figure, the break is the last part of a working day still being
     * answered for all of them at once — and a twelve-hour night is not owed
     * the same lunch as a short day.
     *
     * `system_settings.unpaid_break_minutes` stays, and stays frozen. It is
     * the divisor for days before `schedule_rules_from` and for a shift with
     * no schedule on file, so changing it would quietly re-cut wages already
     * paid. It is simply no longer edited.
     */
    public function up(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->unsignedSmallInteger('break_minutes')->default(0)->after('pm_ends_at');
        });

        $office = (int) (DB::table('system_settings')->value('unpaid_break_minutes') ?? 0);

        foreach (DB::table('shifts')->get() as $shift) {
            // What this shift's own gap already is, which is the truth of what
            // it is running — the office figure is only the fallback for a
            // shift whose sessions were never laid out.
            $gap = $office;

            if ($shift->am_ends_at && $shift->pm_starts_at) {
                $amEnds   = strtotime('2026-01-05 ' . $shift->am_ends_at);
                $pmStarts = strtotime('2026-01-05 ' . $shift->pm_starts_at);

                if ($pmStarts <= $amEnds) {
                    $pmStarts += 86400;
                }

                $gap = (int) round(($pmStarts - $amEnds) / 60);
            }

            DB::table('shifts')->where('id', $shift->id)->update(['break_minutes' => $gap]);
        }
    }

    public function down(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->dropColumn('break_minutes');
        });
    }
};
