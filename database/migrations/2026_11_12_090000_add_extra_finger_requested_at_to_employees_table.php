<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A second finger on request (2026-11-12). One finger is enough to work; the
 * office asks for another from the worker's profile ("Add second
 * fingerprint"), and from then the kiosk lists the worker under ENROLL until
 * it is on file. This is when that was asked; null when it was not.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('employees', 'extra_finger_requested_at')) {
            return;
        }

        Schema::table('employees', function (Blueprint $table) {
            $table->timestamp('extra_finger_requested_at')->nullable();
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('employees', 'extra_finger_requested_at')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->dropColumn('extra_finger_requested_at');
            });
        }
    }
};
