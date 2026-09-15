<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How the attendance kiosk records a scan, set from System Settings → Kiosk.
 *
 *   buttons  the worker presses TIME IN or TIME OUT, then scans (as before)
 *   auto     the worker only scans; the web decides IN or OUT
 *
 * Buttons stays the default, so deploying this changes nothing on the kiosk
 * until someone chooses Automatic.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('system_settings', 'kiosk_attendance_mode')) {
                $table->string('kiosk_attendance_mode', 12)->default('buttons');
            }
            // A second scan by the same worker this soon is ignored in Automatic.
            if (! Schema::hasColumn('system_settings', 'kiosk_repeat_guard_seconds')) {
                $table->unsignedSmallInteger('kiosk_repeat_guard_seconds')->default(180);
            }
            // Idle time on another tab before the kiosk returns to Attendance.
            if (! Schema::hasColumn('system_settings', 'kiosk_idle_return_seconds')) {
                $table->unsignedSmallInteger('kiosk_idle_return_seconds')->default(60);
            }
        });
    }

    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            foreach (['kiosk_attendance_mode', 'kiosk_repeat_guard_seconds', 'kiosk_idle_return_seconds'] as $column) {
                if (Schema::hasColumn('system_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
