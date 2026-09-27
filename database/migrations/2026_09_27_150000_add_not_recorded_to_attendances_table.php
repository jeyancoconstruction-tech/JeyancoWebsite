<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A day the office declined (Michael, 2026-09-27): scanned in and out with
 * nothing at the break, and not accepted as worked straight through. It stays
 * on the Attendance page as "Not recorded", is not paid, and can be undone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->boolean('not_recorded')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn('not_recorded');
        });
    }
};
