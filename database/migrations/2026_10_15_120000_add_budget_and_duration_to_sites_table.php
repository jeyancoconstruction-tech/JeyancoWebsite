<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A project's budget and how long it is expected to run (Michael,
     * 2026-10-15: "in adding sites dapat meron din parang budget amount and
     * parang expected length ng project").
     *
     * The length is kept as it was given, a number and a unit: "6 months" is
     * how a contract states it, and 182 days would read back as something
     * nobody wrote. All three are optional; the sites already on file have
     * none and keep working as they are.
     */
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            if (! Schema::hasColumn('sites', 'budget')) {
                $table->decimal('budget', 14, 2)->nullable()->after('geofence_radius');
            }
            if (! Schema::hasColumn('sites', 'duration')) {
                $table->unsignedSmallInteger('duration')->nullable()->after('budget');
            }
            if (! Schema::hasColumn('sites', 'duration_unit')) {
                $table->string('duration_unit', 6)->nullable()->after('duration');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            foreach (['duration_unit', 'duration', 'budget'] as $column) {
                if (Schema::hasColumn('sites', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
