<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which worker is on which site, for how long, at what rate.
 *
 * employees.site_id already says where a worker belongs NOW, and the kiosk
 * still stamps each clock with the site it was taken at. Neither is touched.
 * This table is the history around them: a posting with a start, an end and
 * the rate agreed for it, so "who was on Site A in August" has an answer after
 * the crew has moved on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();

            // Frozen at assignment. The worker's position may change later;
            // what they were posted as does not.
            $table->string('position', 100)->nullable();

            $table->decimal('rate', 10, 2)->default(0);
            $table->string('rate_type', 10)->default('daily');   // daily|hourly

            $table->date('starts_on');
            $table->date('ends_on')->nullable();                 // null = open-ended

            $table->string('employment_type', 20)->nullable();   // mirrors employees.employment_type
            $table->string('status', 20)->default('active');     // active|completed|cancelled

            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['site_id', 'status']);
            $table->index(['employee_id', 'starts_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_assignments');
    }
};

