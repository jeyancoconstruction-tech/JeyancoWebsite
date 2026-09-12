<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where each deduction on a payslip went, and whether the worker was paid.
 *
 * A finalised run records what was deducted. It does not record whether the
 * SSS share was remitted, whether BIR has had the tax, or whether the worker
 * was handed the net — which is what the office gets asked about afterwards.
 *
 * One row per payslip line that has somewhere to go, created the first time
 * somebody moves it: pending until then, then submitted, then done, each step
 * signed and dated. Net pay skips "submitted"; it is paid or it is not.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_remittances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_item_id')->constrained()->cascadeOnDelete();

            $table->string('kind', 20);                        // sss|philhealth|pagibig|bir|net_pay
            $table->string('status', 20)->default('pending');  // pending|submitted|done

            // The line as it stood when it was last moved.
            $table->decimal('amount', 12, 2)->default(0);

            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            $table->unique(['payroll_run_item_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_remittances');
    }
};
