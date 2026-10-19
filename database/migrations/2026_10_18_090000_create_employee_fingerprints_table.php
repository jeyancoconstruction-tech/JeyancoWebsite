<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * More than one finger per worker (RSC 2026-10-18: "Must register at least
 * multiple fingers per employee").
 *
 * employees.fingerprint_id stays the worker's first finger — every page,
 * report and test that reads it keeps working. The second and later fingers
 * live here, one sensor slot per row. A slot belongs to one worker only,
 * across both places; Employee::releaseFingerprint() checks the two together.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('employee_fingerprints')) {
            return;
        }

        Schema::create('employee_fingerprints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('slot', 32)->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_fingerprints');
    }
};
