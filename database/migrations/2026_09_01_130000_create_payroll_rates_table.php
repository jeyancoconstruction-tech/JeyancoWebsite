<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Everything payroll reads that a circular can change, dated.
     *
     * The multipliers and the contribution rates lived on the single `settings`
     * row, so raising one rewrote history: reopening last month's payroll
     * recomputed it at today's numbers and disagreed with the payslips already
     * handed out. A wage order or an SSS circular takes effect on a date and
     * does not reach backwards, and payroll has to be able to say the same.
     *
     * So a rate change is an INSERT, never an UPDATE. Each row is the set in
     * force from `effective_from` onward, and payroll resolves the row
     * effective on the attendance date it is computing — which is why nothing
     * here is unique on anything but the date.
     *
     * Only creates a table and reads `settings`, which every earlier migration
     * has already created — no forward reference for MySQL to reject.
     */
    public function up(): void
    {
        Schema::create('payroll_rates', function (Blueprint $table) {
            $table->id();
            $table->date('effective_from')->index();

            // Premiums, as multiples of the plain hourly rate.
            $table->decimal('ot_multiplier', 5, 2)->default(1.25);
            $table->decimal('night_diff_multiplier', 5, 2)->default(1.10);
            $table->decimal('rest_day_multiplier', 5, 2)->default(1.30);
            $table->decimal('regular_holiday_multiplier', 5, 2)->default(2.00);

            // The wage order's daily floor. Nullable, and null means no floor:
            // an office that has not entered one must not have every labour
            // type quietly lifted to a rate nobody agreed to.
            $table->decimal('daily_rate', 10, 2)->nullable();

            // Employee-share contributions, percent of gross. Only the employee
            // share is deducted here — the employer share never touches a
            // payslip.
            $table->decimal('sss_rate', 5, 2)->default(5.00);
            $table->decimal('philhealth_rate', 5, 2)->default(2.50);
            $table->decimal('pagibig_rate', 5, 2)->default(2.00);

            $table->string('created_by', 120)->nullable();
            $table->timestamps();
        });

        // Carry the current settings across as the opening row, back-dated far
        // enough to cover every attendance already recorded. Without it the
        // first payroll run after this migration would find no set in force and
        // fall back to defaults, quietly changing figures that were right — the
        // contribution rates especially, which are 0 in a fresh install and
        // would start deducting from everyone.
        $settings = DB::table('settings')->first();

        DB::table('payroll_rates')->insert([
            'effective_from'             => '2000-01-01',
            'ot_multiplier'              => $settings->ot_multiplier ?? 1.25,
            'night_diff_multiplier'      => 1.10,
            'rest_day_multiplier'        => $settings->ot_premium_multiplier ?? 1.30,
            'regular_holiday_multiplier' => 2.00,
            'daily_rate'                 => $settings->daily_rate ?? null,
            'sss_rate'                   => $settings->sss ?? 0,
            'philhealth_rate'            => $settings->philhealth ?? 0,
            'pagibig_rate'               => $settings->pagibig ?? 0,
            'created_by'                 => 'migration',
            'created_at'                 => now(),
            'updated_at'                 => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_rates');
    }
};
