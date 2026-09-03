<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A cash advance collected in instalments.
        //
        // The vale on an attendance row is the other kind: an amount taken out
        // of one day, entered against that day. This is a sum handed over once
        // and taken back across several pay periods, so a worker who borrows a
        // week's wage does not go home with nothing the week after.
        //
        // The schedule is fixed rather than a running balance: the amount, how
        // many weeks, and which week it starts. What a given week collects can
        // be worked out from those three, so a period reopened next year still
        // computes to the payslip that went with it — nothing depends on the
        // order the weeks happened to be calculated in.
        Schema::create('vale_advances', function (Blueprint $table) {
            $table->id();

            // What each named worker owes, not the sum across them.
            $table->decimal('amount', 12, 2);
            $table->unsignedSmallInteger('weeks');

            // The pay period the first instalment falls in. Any date inside it
            // will do — payroll resolves the week from wherever its own week
            // begins.
            $table->date('starts_on');

            $table->boolean('all_employees')->default(false);
            $table->string('note', 160)->nullable();
            $table->string('created_by', 100)->nullable();
            $table->timestamps();

            $table->index('starts_on');
        });

        Schema::create('employee_vale_advance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vale_advance_id')->constrained()->cascadeOnDelete();
            $table->unique(['employee_id', 'vale_advance_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_vale_advance');
        Schema::dropIfExists('vale_advances');
    }
};
