<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A bonus given to named people, on top of the standing one.
     *
     * `payroll_rates.bonus` is the office's standing bonus: the same amount to
     * everybody, every period. It stays exactly as it is — a bonus already paid
     * out under it must not change because this table now exists.
     *
     * This is the other kind: an amount, the period it lands in, and who gets
     * it. One worker, two, or everybody. Nothing here is edited after the fact —
     * a grant that was wrong is deleted, not rewritten, so a period recomputed
     * next year matches the payslip that went with it.
     *
     * `employees` and `users` are both older than this migration, so the two
     * foreign keys point backwards — nothing for MySQL to reject.
     */
    public function up(): void
    {
        Schema::create('bonuses', function (Blueprint $table) {
            $table->id();

            $table->decimal('amount', 10, 2);

            // The day the bonus belongs to. Payroll pays it in whichever pay
            // period contains this date, so it follows the week start setting
            // rather than fixing a range of its own.
            $table->date('effective_on')->index();

            // Everybody, without having to list them — and without the list
            // going stale when somebody is hired the next day.
            $table->boolean('all_employees')->default(false);

            $table->string('note', 160)->nullable();
            $table->string('created_by', 120)->nullable();
            $table->timestamps();
        });

        Schema::create('bonus_employee', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bonus_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();

            // One grant names a worker once.
            $table->unique(['bonus_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bonus_employee');
        Schema::dropIfExists('bonuses');
    }
};
