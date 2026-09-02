<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which shift the office runs.
     *
     * It is not decoration next to the expected time-in: a night shift crosses
     * midnight, and lateness has to know that. A crew expected at 10 PM whose
     * worker clocks in at 12:30 AM is two and a half hours late — measured on
     * the clock alone it reads as twenty-one hours early, and the day comes
     * back with nobody late at all.
     *
     * The night differential does not depend on this. It is 10 PM to 6 AM by
     * the clock, whoever is working and whatever the office calls the shift.
     *
     * Default 'day', which is what every existing record was computed as.
     */
    public function up(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->string('shift', 10)->default('day');
        });
    }

    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->dropColumn('shift');
        });
    }
};
