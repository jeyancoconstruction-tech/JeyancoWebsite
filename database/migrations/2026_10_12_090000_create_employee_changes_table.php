<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A worker's site, shift or labor type, changed from a date.
 *
 * The Employees page moves several workers at once — to another site, onto
 * the night shift, to another labor type — and each move is written here as
 * well as on the worker: what they were, what they became, and the day it
 * took effect. Three things read it:
 *
 *   - payroll, which prices a day by the labor type in force on that day, so
 *     a worker made a mason on Monday is still paid as a laborer for the week
 *     before;
 *   - Undo, which puts back exactly what was there;
 *   - a change dated ahead, which waits here until its day comes.
 *
 * `batch` ties together the rows one press of Confirm wrote. A bonus given to
 * several workers at once is a row per worker too (`to_id` is the bonus), so
 * the same Undo can take it back.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('employee_changes')) {
            return;
        }

        Schema::create('employee_changes', function (Blueprint $table) {
            $table->id();
            $table->uuid('batch')->index();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('kind', 16);                    // site | shift | labor | bonus
            $table->date('effective_on');
            $table->unsignedBigInteger('from_id')->nullable();
            $table->unsignedBigInteger('to_id')->nullable();
            // Labor: the rate and position before and after, for Undo and for
            // pricing the days before the change.
            $table->json('detail')->nullable();
            // Null while the change is still waiting for its day.
            $table->timestamp('applied_at')->nullable();
            $table->string('created_by')->nullable();
            $table->timestamps();

            $table->index(['kind', 'employee_id', 'effective_on']);
            $table->index(['applied_at', 'effective_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_changes');
    }
};
