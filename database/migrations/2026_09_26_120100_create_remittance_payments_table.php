<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A month's remittance to one agency, marked paid in the Remittance Tracker.
     *
     * One row per agency per month: the contributions for August go to SSS
     * once. What was owed is not stored here — it is worked out from payroll
     * whenever the tracker is opened — only what the office says it paid, when,
     * through what, and the reference the agency gave back.
     */
    public function up(): void
    {
        if (Schema::hasTable('remittance_payments')) {
            return;
        }

        Schema::create('remittance_payments', function (Blueprint $table) {
            $table->id();
            $table->string('agency', 16);           // sss | philhealth | pagibig | bir
            $table->date('period');                 // the first day of the month the contributions are for
            $table->decimal('amount', 12, 2);       // what was paid
            $table->date('paid_on');
            $table->string('reference', 64);        // reference or PRN number
            $table->string('channel', 64);          // what it was paid through
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['agency', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('remittance_payments');
    }
};
