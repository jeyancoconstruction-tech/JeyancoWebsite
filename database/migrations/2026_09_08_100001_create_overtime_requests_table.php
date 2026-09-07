<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Overtime claimed for a worker on a given day, and the decision on it.
 *
 * This is a CLAIM, not a clock. The kiosk's time-in/time-out remains the
 * record of the day, and PayrollService already derives its own overtime from
 * those hours. An approved row here is the office saying "pay this on top",
 * which Payroll Processing reads as an additional earning. Nothing here feeds
 * back into attendance.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('overtime_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->nullable()->constrained()->nullOnDelete();

            $table->date('date');
            $table->time('starts_at')->nullable();
            $table->time('ends_at')->nullable();
            $table->decimal('hours', 6, 2)->default(0);

            // The rate at the moment of approval, and the amount it produces.
            // Both are frozen on the row: a later change to the worker's rate
            // must not silently restate overtime that has already been paid.
            $table->decimal('hourly_rate', 10, 2)->default(0);
            $table->decimal('multiplier', 5, 2)->default(1.25);
            $table->decimal('amount', 12, 2)->default(0);

            $table->text('reason')->nullable();
            $table->string('status', 20)->default('pending'); // pending|approved|rejected

            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('decision_note')->nullable();

            $table->foreignId('filed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'date']);
            $table->index(['status', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overtime_requests');
    }
};

