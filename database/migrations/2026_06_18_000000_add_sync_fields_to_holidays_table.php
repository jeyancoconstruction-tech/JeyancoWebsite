<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds calendar-sync fields to the holidays table.
     *
     * With these, official Philippine holidays are recognised automatically
     * (computed by App\Support\PhilippineHolidays) while the table now stores
     * only overrides: a disabled official holiday (is_official = true,
     * is_active = false) or a manually added custom holiday (is_official = false).
     */
    public function up(): void
    {
        Schema::table('holidays', function (Blueprint $table) {
            if (! Schema::hasColumn('holidays', 'type')) {
                $table->string('type')->nullable()->after('title');
            }
            if (! Schema::hasColumn('holidays', 'is_official')) {
                $table->boolean('is_official')->default(false)->after('type');
            }
            if (! Schema::hasColumn('holidays', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('is_official');
            }
        });
    }

    public function down(): void
    {
        Schema::table('holidays', function (Blueprint $table) {
            foreach (['type', 'is_official', 'is_active'] as $col) {
                if (Schema::hasColumn('holidays', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
