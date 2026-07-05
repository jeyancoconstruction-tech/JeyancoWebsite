<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds configurable payroll rate components:
     *  - ot_multiplier      : overtime hourly multiplier (default 1.25 = current behaviour)
     *  - holiday_multiplier : pay multiplier for days flagged as holidays (default 2.00)
     *  - bonus              : flat bonus added per employee per pay period (default 0)
     */
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            if (!Schema::hasColumn('settings', 'ot_multiplier')) {
                $table->decimal('ot_multiplier', 5, 2)->default(1.25)->after('daily_rate');
            }
            if (!Schema::hasColumn('settings', 'holiday_multiplier')) {
                $table->decimal('holiday_multiplier', 5, 2)->default(2.00)->after('ot_multiplier');
            }
            if (!Schema::hasColumn('settings', 'bonus')) {
                $table->decimal('bonus', 10, 2)->default(0)->after('pagibig');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['ot_multiplier', 'holiday_multiplier', 'bonus']);
        });
    }
};
