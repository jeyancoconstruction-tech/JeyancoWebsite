<?php

use App\Models\Attendance;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Days already on file that were scanned in and out with nothing at the
 * break now wait in "needs review" too (Michael, 2026-09-27) — from here on
 * the kiosk flags them as the time out is scanned. Only days counted by the
 * shift's sessions; earlier ones keep the old flat rule.
 */
return new class extends Migration
{
    public function up(): void
    {
        $from = DB::table('system_settings')->value('schedule_rules_from') ?: '2026-09-11';

        Attendance::with('shift')
            ->whereNotNull('time_in')
            ->whereNotNull('time_out')
            ->whereNull('close_type')
            ->where('needs_review', false)
            ->where('date', '>=', $from)
            ->orderBy('id')
            ->each(fn (Attendance $row) => $row->flagIfBreakUnscanned());
    }

    public function down(): void
    {
        DB::table('attendances')
            ->where('needs_review', true)
            ->where('close_reason', Attendance::NO_BREAK)
            ->update(['needs_review' => false, 'close_reason' => null]);
    }
};
