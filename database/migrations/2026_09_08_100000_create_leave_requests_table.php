<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Leave filed for a worker, and the decision on it.
 *
 * Attendance is not touched: the kiosk stays the only thing that records a
 * clock. Approved leave is read alongside attendance when payroll runs, so a
 * paid leave day can be credited without inventing a time-in for it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();

            // A string rather than a lookup table: the set is small, stable,
            // and its labels live in one constant beside the model.
            $table->string('leave_type', 40)->default('vacation');

            $table->date('starts_on');
            $table->date('ends_on');

            // Stored, not derived. A half day, or a range crossing a holiday
            // the office chose not to charge, is not end-minus-start.
            $table->decimal('days', 5, 2)->default(0);

            // Whether payroll should credit these days. Unpaid leave still
            // needs recording: it explains an absence.
            $table->boolean('is_paid')->default(true);

            $table->text('reason')->nullable();
            $table->string('status', 20)->default('pending'); // pending|approved|rejected|cancelled

            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('decision_note')->nullable();

            $table->foreignId('filed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Payroll asks "what leave touches this range, for these people".
            $table->index(['employee_id', 'status']);
            $table->index(['starts_on', 'ends_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
    }
};

