<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether a worker may take overtime.
 *
 * Overtime used to be counted for anybody who worked past their regular
 * hours. The office now says who may: the Employees page allows it for the
 * workers ticked, and from the day the first of them is allowed, a worker
 * who is not has the hours past their regular hours left unrecorded.
 *
 * This column is where a worker stands today, for the list and the dialog to
 * show. What payroll reads is the dated record of each change
 * (employee_changes, kind "overtime"), so a day is counted by what was
 * allowed on that day and the weeks already paid do not move.
 *
 * Off for everybody to begin with. Nothing changes for anyone until somebody
 * is allowed: until then there is no rule, and overtime is counted as it
 * always was.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('employees', 'overtime_allowed')) {
            return;
        }

        Schema::table('employees', function (Blueprint $table) {
            $table->boolean('overtime_allowed')->default(false);
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('employees', 'overtime_allowed')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->dropColumn('overtime_allowed');
            });
        }
    }
};
