<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The official holiday calendar as Google Calendar last gave it.
 *
 * One row per holiday date. A year with rows here is the official calendar for
 * that year, in place of the one App\Support\PhilippineHolidays computes
 * offline — Google knows the dates a proclamation sets (Eid'l Fitr, Chinese
 * New Year, a moved Ninoy Aquino Day) and the computation cannot.
 *
 * `is_active` is whether the day counts before anybody touches it. A holiday
 * that Google names for a date already past arrives switched off, so a sync
 * never changes what a week that has already been paid comes to. The admin's
 * own on/off stays in `holidays`, as it always has.
 *
 * No foreign keys: nothing here points anywhere.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('google_holidays')) {
            return;
        }

        Schema::create('google_holidays', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->string('title');
            $table->string('type', 20);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_holidays');
    }
};
