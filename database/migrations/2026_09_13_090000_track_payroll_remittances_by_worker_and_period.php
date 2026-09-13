<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Remittances are tracked against the worker and the period, not a run item.
 *
 * They were tied to a finalised run, so nothing could be marked until the
 * period had been processed, approved and finalised — and the bar that did
 * that is off the page. A contribution is owed on a worker's pay for a period
 * whether or not a run was ever cut for it, so that is the key now.
 *
 * The table is a day old. It is rebuilt rather than altered, which reads the
 * same on MySQL and on the SQLite the tests run on, and any row already
 * recorded is carried across under its run's worker and period.
 */
return new class extends Migration
{
    public function up(): void
    {
        $old = DB::table('payroll_remittances as r')
            ->join('payroll_run_items as i', 'i.id', '=', 'r.payroll_run_item_id')
            ->join('payroll_runs as p', 'p.id', '=', 'i.payroll_run_id')
            ->get([
                'i.employee_id', 'p.period_start', 'p.period_end',
                'r.kind', 'r.status', 'r.amount',
                'r.submitted_by', 'r.submitted_at', 'r.completed_by', 'r.completed_at',
                'r.created_at', 'r.updated_at',
            ]);

        Schema::drop('payroll_remittances');

        Schema::create('payroll_remittances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');

            $table->string('kind', 20);                        // sss|philhealth|pagibig|bir|net_pay
            $table->string('status', 20)->default('pending');  // pending|submitted|done

            // The line as it stood when it was last moved.
            $table->decimal('amount', 12, 2)->default(0);

            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            // Named: the generated name runs past MySQL's 64 characters.
            $table->unique(['employee_id', 'period_start', 'period_end', 'kind'], 'payroll_remittances_line_unique');
        });

        foreach ($old as $row) {
            $row = (array) $row;
            $row['period_start'] = substr((string) $row['period_start'], 0, 10);
            $row['period_end']   = substr((string) $row['period_end'], 0, 10);

            DB::table('payroll_remittances')->insertOrIgnore($row);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_remittances');

        Schema::create('payroll_remittances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_item_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20);
            $table->string('status', 20)->default('pending');
            $table->decimal('amount', 12, 2)->default(0);
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['payroll_run_item_id', 'kind']);
        });
    }
};
