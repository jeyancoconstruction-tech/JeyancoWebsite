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
        // Drop ot_rate from labor_types if it exists
        Schema::table('labor_types', function (Blueprint $table) {
            if (Schema::hasColumn('labor_types', 'ot_rate')) {
                $table->dropColumn('ot_rate');
            }
        });

        // Drop ot_rate from settings if it exists
        Schema::table('settings', function (Blueprint $table) {
            if (Schema::hasColumn('settings', 'ot_rate')) {
                $table->dropColumn('ot_rate');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This is a destructive migration - no reversal
    }
};
