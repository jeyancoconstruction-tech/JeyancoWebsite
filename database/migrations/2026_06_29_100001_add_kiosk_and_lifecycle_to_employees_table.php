<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Employee lifecycle + kiosk tracing.
 *
 *  - kiosk_id      → which kiosk detected/registered this worker (Site A, etc.)
 *  - status        → pending (detected by kiosk, info incomplete)
 *                    active  (fully registered, counts toward the workforce)
 *                    archived(left the company; records preserved, hidden from active views)
 *  - archived_at   → when the worker was archived
 *  - deleted_at    → soft delete, so "remove" never destroys payroll history
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->unsignedBigInteger('kiosk_id')->nullable()->after('site_id');
            $table->string('status')->default('active')->after('kiosk_id');
            $table->timestamp('archived_at')->nullable()->after('photo');
            $table->softDeletes();

            $table->foreign('kiosk_id')
                  ->references('id')->on('kiosks')
                  ->nullOnDelete();
        });

        // Every existing employee is a fully-registered, active worker.
        DB::table('employees')->update(['status' => 'active']);
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['kiosk_id']);
            $table->dropColumn(['kiosk_id', 'status', 'archived_at', 'deleted_at']);
        });
    }
};
