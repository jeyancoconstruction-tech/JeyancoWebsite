<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Record WHERE each clock happened.
 *
 * One kiosk device is carried between sites, so the site can no longer be read
 * off the employee: a worker's site_id is stamped once at registration and never
 * moves, while attendance is site-specific by nature. Without this column, every
 * scan taken at Site B is indistinguishable from one taken at Site A.
 *
 * kiosk_id rides along so a multi-device deployment can tell the stations apart
 * later without another migration.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            if (! Schema::hasColumn('attendances', 'site_id')) {
                $table->unsignedBigInteger('site_id')->nullable()->after('employee_id');
                $table->foreign('site_id')->references('id')->on('sites')->nullOnDelete();
            }
            if (! Schema::hasColumn('attendances', 'kiosk_id')) {
                $table->unsignedBigInteger('kiosk_id')->nullable()->after('site_id');
                $table->foreign('kiosk_id')->references('id')->on('kiosks')->nullOnDelete();
            }
        });

        // Existing rows predate the travelling kiosk, so the worker's own site is
        // the best available answer for where they clocked in.
        // Correlated subquery rather than a JOIN-UPDATE: the test suite runs on
        // SQLite, which has no multi-table UPDATE syntax.
        DB::table('attendances')->whereNull('site_id')->update([
            'site_id' => DB::raw('(SELECT e.site_id FROM employees e WHERE e.id = attendances.employee_id)'),
        ]);
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            if (Schema::hasColumn('attendances', 'site_id')) {
                $table->dropForeign(['site_id']);
                $table->dropColumn('site_id');
            }
            if (Schema::hasColumn('attendances', 'kiosk_id')) {
                $table->dropForeign(['kiosk_id']);
                $table->dropColumn('kiosk_id');
            }
        });
    }
};
