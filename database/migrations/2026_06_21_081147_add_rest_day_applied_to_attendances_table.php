<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            // Frozen rest-day-pay decision for this record.
            //   null  → follow the current global Sunday Rest Day setting
            //   true  → rest day pay was locked ON  for this record
            //   false → rest day pay was locked OFF for this record
            // Past Sundays are frozen when the global setting is toggled so
            // historical payroll never recalculates retroactively.
            $table->boolean('rest_day_applied')->nullable()->default(null)->after('deductions');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn('rest_day_applied');
        });
    }
};
