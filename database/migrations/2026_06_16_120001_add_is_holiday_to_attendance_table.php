<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Flags an attendance record as worked on a holiday so payroll can apply
     * the configurable holiday multiplier. Defaults to false so existing
     * payroll calculations are completely unchanged.
     */
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            if (!Schema::hasColumn('attendances', 'is_holiday')) {
                $table->boolean('is_holiday')->default(false)->after('deductions');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn('is_holiday');
        });
    }
};
