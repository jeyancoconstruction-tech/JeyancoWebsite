<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A payroll run, and the frozen per-employee figures it produced.
 *
 * PayrollService::computeForRange() stays the single source of payroll
 * arithmetic and is not touched. A run CALLS it and writes the answer down,
 * which is the thing the existing Payroll Records screen cannot do: it
 * recomputes from attendance every time it is opened, so a payslip issued last
 * month silently changes when a rate is edited today.
 *
 * A run therefore moves through states, and only a finalised one is a
 * historical fact:
 *   draft      created, nothing computed yet
 *   calculated figures written, still freely recalculable
 *   approved   signed off; recalculation now needs a deliberate reopen
 *   finalised  closed; payslips issue from here and the numbers never move
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();       // e.g. PR-2026-0001
            $table->string('title', 120)->nullable();

            $table->date('period_start');
            $table->date('period_end');

            // Optional narrowing. Null means every active worker.
            $table->foreignId('site_id')->nullable()->constrained()->nullOnDelete();

            $table->string('status', 20)->default('draft'); // draft|calculated|approved|finalized

            $table->decimal('total_gross', 14, 2)->default(0);
            $table->decimal('total_deductions', 14, 2)->default(0);
            $table->decimal('total_net', 14, 2)->default(0);
            $table->unsignedInteger('employee_count')->default(0);

            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('calculated_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finalized_at')->nullable();

            $table->timestamps();

            $table->index(['status', 'period_start']);
        });

        Schema::create('payroll_run_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();

            // Copied off the worker at calculation time so a payslip still
            // reads correctly after a transfer or a rename.
            $table->string('employee_name', 160)->nullable();
            $table->string('position', 100)->nullable();
            $table->foreignId('site_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('daily_rate', 10, 2)->default(0);
            $table->decimal('hourly_rate', 10, 2)->default(0);

            // Attendance, as PayrollService read it.
            $table->decimal('days_worked', 6, 2)->default(0);
            $table->decimal('regular_hours', 8, 2)->default(0);
            $table->decimal('ot_hours', 8, 2)->default(0);
            $table->unsignedInteger('late_minutes')->default(0);
            $table->decimal('absent_days', 6, 2)->default(0);
            $table->decimal('leave_days', 6, 2)->default(0);
            $table->decimal('paid_leave_days', 6, 2)->default(0);

            // Earnings.
            $table->decimal('basic_pay', 12, 2)->default(0);
            $table->decimal('overtime_pay', 12, 2)->default(0);
            $table->decimal('holiday_pay', 12, 2)->default(0);
            $table->decimal('rest_day_pay', 12, 2)->default(0);
            $table->decimal('night_diff_pay', 12, 2)->default(0);
            $table->decimal('leave_pay', 12, 2)->default(0);
            $table->decimal('bonus', 12, 2)->default(0);
            $table->decimal('other_earnings', 12, 2)->default(0);

            // Deductions.
            $table->decimal('sss', 12, 2)->default(0);
            $table->decimal('philhealth', 12, 2)->default(0);
            $table->decimal('pagibig', 12, 2)->default(0);
            $table->decimal('tax', 12, 2)->default(0);
            $table->decimal('vale', 12, 2)->default(0);
            $table->decimal('loan_deduction', 12, 2)->default(0);
            $table->decimal('advance_deduction', 12, 2)->default(0);
            $table->decimal('other_deductions', 12, 2)->default(0);

            // Totals.
            $table->decimal('gross_pay', 12, 2)->default(0);
            $table->decimal('total_deductions', 12, 2)->default(0);
            $table->decimal('net_pay', 12, 2)->default(0);

            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->unique(['payroll_run_id', 'employee_id']);
            $table->index('employee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_run_items');
        Schema::dropIfExists('payroll_runs');
    }
};

