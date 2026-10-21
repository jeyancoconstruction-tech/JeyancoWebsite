<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A worker at more than one site (Michael, 2026-10-21: "allowing employee
 * have a multiple sites").
 *
 * employees.site_id stays the worker's main site — the one every list,
 * report and payroll page reads, and the one Transfer site moves. The other
 * sites they also work at live here, one row per site. The kiosk at any of
 * them takes their scans (KioskController::siteGate).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('employee_site')) {
            return;
        }

        Schema::create('employee_site', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['employee_id', 'site_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_site');
    }
};
