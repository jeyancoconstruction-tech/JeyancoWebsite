<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            // The meal period inside a working day. Standard hours is the whole
            // day from clock-in to clock-out; this is the part of it nobody is
            // paid for, so a 6am–3pm day with an hour for lunch is nine hours
            // on site and eight hours of wage.
            //
            // Zero by default, which leaves every existing computation exactly
            // as it was: standard hours minus nothing is standard hours.
            $table->unsignedSmallInteger('unpaid_break_minutes')->default(0)->after('standard_hours_per_day');
        });
    }

    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->dropColumn('unpaid_break_minutes');
        });
    }
};
