<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * System Settings → Kiosks → "Reject scans out of range" (2026-09-30). On, a
 * kiosk outside its site's radius refuses the scan, as it always has. Off,
 * for when the kiosk's location is broken, workers can still scan in. The
 * default keeps things exactly as they were.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->boolean('kiosk_location_check')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->dropColumn('kiosk_location_check');
        });
    }
};
