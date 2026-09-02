<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The settings that are not payroll.
     *
     * The `settings` table is the payroll one — the rest of that page's numbers
     * moved to `payroll_rates`, and what is left there answers for pay. These
     * are the ones an office changes about the system itself: who it says it is
     * on a payslip, and how strict it is about getting in.
     *
     * One row, like `settings`. The defaults here are the values that were
     * hardcoded before it existed, so a fresh install behaves as it did.
     *
     * Creates a table and reads nothing — no forward reference for MySQL.
     */
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();

            // What the company calls itself on a payslip, a receipt and an
            // export. These were hardcoded into four views.
            $table->string('company_name', 120)->default('JEYANCO CONSTRUCTION');
            $table->string('company_tagline', 160)->default('Payroll Dept. · Panganiban, PH');
            $table->string('company_address', 255)->nullable();

            // Null means the logo that ships with the app, so an office that has
            // not uploaded one is not left with a broken image.
            $table->string('logo_path', 255)->nullable();

            // Minutes of inactivity before a session is dropped. The default is
            // config('session.lifetime'), which is what it was.
            $table->unsignedSmallInteger('session_timeout_minutes')->default(120);

            // Password rule, applied wherever one is set or reset.
            $table->unsignedTinyInteger('password_min_length')->default(8);

            // Failed logins before the throttle bites, and how long it holds.
            $table->unsignedTinyInteger('max_login_attempts')->default(5);
            $table->unsignedSmallInteger('lockout_seconds')->default(60);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
