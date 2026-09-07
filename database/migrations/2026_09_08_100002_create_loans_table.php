<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Loans and salary advances, and the ledger of what payroll has collected.
 *
 * The `vale` column on employees and the vale_advances table behind Payroll
 * Settings are left exactly as they are: payroll already handles those, and
 * they are a different instrument. This is the longer-lived kind, a sum issued
 * once and collected over several payrolls.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();

            $table->string('type', 20)->default('loan');   // loan|advance
            $table->string('reference', 40)->nullable();

            $table->decimal('principal', 12, 2);

            // A column rather than principal-minus-collected, so a correction
            // can be made without rewriting the ledger, and so a payroll run
            // reads one number instead of an aggregate.
            $table->decimal('balance', 12, 2);

            $table->decimal('installment', 12, 2)->default(0);
            $table->string('schedule', 20)->default('per_payroll'); // per_payroll|monthly

            $table->date('issued_on');
            $table->date('starts_on')->nullable();          // first payroll to collect from

            $table->string('status', 20)->default('active');  // active|paid|cancelled|on_hold
            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'status']);
        });

        // Every peso payroll takes off a loan, and the run that took it. This
        // is what makes a balance defensible when a worker disputes it.
        Schema::create('loan_deductions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();

            // Not a foreign key: payroll_runs is created by the next migration,
            // and a collection recorded by hand has no run behind it at all.
            $table->unsignedBigInteger('payroll_run_id')->nullable();

            $table->decimal('amount', 12, 2);
            $table->date('deducted_on');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['loan_id', 'deducted_on']);
            $table->index('payroll_run_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_deductions');
        Schema::dropIfExists('loans');
    }
};

