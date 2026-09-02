<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A ceiling on how much of a day's pay a vale may take.
     *
     * A cash advance is deducted in full today, so a worker who borrowed more
     * than they earned goes home with nothing — or with a negative figure on
     * the payslip. An office that lends against wages needs a floor under the
     * take-home, and this is it: the most a vale may swallow, as a percentage
     * of what is left after the statutory deductions. Whatever it does not
     * take stays owed, so nothing is written off.
     *
     * Dated like every other payroll number. 100 is no ceiling, which is what
     * the code did before it existed.
     */
    public function up(): void
    {
        Schema::table('payroll_rates', function (Blueprint $table) {
            $table->unsignedTinyInteger('vale_ceiling_percent')->default(100);
        });
    }

    public function down(): void
    {
        Schema::table('payroll_rates', function (Blueprint $table) {
            $table->dropColumn('vale_ceiling_percent');
        });
    }
};
