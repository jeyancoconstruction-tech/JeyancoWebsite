<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The settings on Michael's jeyanco-settings.html that had nothing behind
 * them yet (2026-09-27): the employer TIN, the accent colour, table density,
 * the sign-in intro, Google sign-in, "sign out everyone", three kiosk rules
 * and the Notifications section. Each default keeps things working exactly
 * as they did before the column existed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->string('company_tin', 40)->nullable();

            $table->string('accent_color', 12)->default('blue');
            $table->string('table_density', 12)->default('comfortable');
            $table->boolean('signin_intro')->default(true);

            $table->boolean('google_sign_in')->default(true);
            // Sessions that began before this moment end on their next request.
            $table->timestamp('sessions_revoked_at')->nullable();

            $table->boolean('kiosk_repeat_guard_on')->default(true);
            $table->boolean('kiosk_unknown_alert')->default(true);
            // The alert used to fire after two minutes without a heartbeat.
            $table->unsignedSmallInteger('kiosk_offline_alert_minutes')->default(10);

            $table->boolean('notify_missing_scans')->default(true);
            $table->boolean('notify_remittances')->default(true);
            $table->boolean('notify_payroll')->default(true);
            $table->boolean('notify_email')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->dropColumn([
                'company_tin', 'accent_color', 'table_density', 'signin_intro',
                'google_sign_in', 'sessions_revoked_at',
                'kiosk_repeat_guard_on', 'kiosk_unknown_alert', 'kiosk_offline_alert_minutes',
                'notify_missing_scans', 'notify_remittances', 'notify_payroll', 'notify_email',
            ]);
        });
    }
};
