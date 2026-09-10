<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The working day, written down.
     *
     * A shift knew only when it started, so nothing could say where lunch was,
     * when the day ended, or that the hour after it is overtime. It now carries
     * both sessions:
     *
     *   Day    08:00–12:00 · 13:00–17:00
     *   Night  20:00–00:00 · 01:00–05:00
     *
     * Attendance gains the marks the kiosk needs to close a day nobody closed
     * (AUTO, for the office to review), and the office gets one date from which
     * payroll counts hours this way. Days before that date are paid exactly as
     * they were — the old start time is kept for their lateness too.
     */
    public function up(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->time('am_starts_at')->nullable();
            $table->time('am_ends_at')->nullable();
            $table->time('pm_starts_at')->nullable();
            $table->time('pm_ends_at')->nullable();
            // TIME IN opens this long before the shift starts.
            $table->unsignedSmallInteger('time_in_opens_minutes')->default(120);
            // What starts_at was before this, for lateness on older days.
            $table->time('legacy_starts_at')->nullable();
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->string('close_type', 10)->nullable();        // null = closed by the worker, 'auto', 'admin'
            $table->boolean('needs_review')->default(false);
            $table->string('close_reason')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
        });

        Schema::table('system_settings', function (Blueprint $table) {
            $table->date('schedule_rules_from')->nullable();
        });

        foreach (DB::table('shifts')->get() as $shift) {
            $night = (bool) $shift->crosses_midnight;

            DB::table('shifts')->where('id', $shift->id)->update([
                'legacy_starts_at' => $shift->starts_at,
                'starts_at'        => $night ? '20:00:00' : '08:00:00',
                'am_starts_at'     => $night ? '20:00:00' : '08:00:00',
                'am_ends_at'       => $night ? '00:00:00' : '12:00:00',
                'pm_starts_at'     => $night ? '01:00:00' : '13:00:00',
                'pm_ends_at'       => $night ? '05:00:00' : '17:00:00',
            ]);
        }

        // The new count starts with the next pay week, so no period is paid
        // half one way and half the other.
        $settings = DB::table('system_settings')->first();
        if ($settings) {
            $weekStart = (int) ($settings->week_starts_on ?? 1);
            $from      = Carbon::now('Asia/Manila')->startOfDay()->addDay();
            while ($from->dayOfWeek !== $weekStart) {
                $from->addDay();
            }

            DB::table('system_settings')->where('id', $settings->id)
                ->update(['schedule_rules_from' => $from->toDateString()]);
        }
    }

    public function down(): void
    {
        foreach (DB::table('shifts')->get() as $shift) {
            if ($shift->legacy_starts_at) {
                DB::table('shifts')->where('id', $shift->id)->update(['starts_at' => $shift->legacy_starts_at]);
            }
        }

        Schema::table('system_settings', function (Blueprint $table) {
            $table->dropColumn('schedule_rules_from');
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn(['close_type', 'needs_review', 'close_reason', 'reviewed_by', 'reviewed_at']);
        });

        Schema::table('shifts', function (Blueprint $table) {
            $table->dropColumn(['am_starts_at', 'am_ends_at', 'pm_starts_at', 'pm_ends_at',
                                'time_in_opens_minutes', 'legacy_starts_at']);
        });
    }
};
