<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Put the period bonus on the dated rate row.
     *
     * It was the last payroll number still living on the single `settings`
     * row, which meant raising it rewrote history: reopening a period already
     * paid recomputed it at today's bonus and disagreed with the payslip the
     * worker was handed. Everything else on that form was moved onto
     * `payroll_rates` for exactly this reason, and the bonus is now entered on
     * the same dated card, so it has to answer the same way.
     *
     * Only alters a table two earlier migrations already created and reads
     * `settings`, which is older still — no forward reference for MySQL to
     * reject.
     */
    public function up(): void
    {
        Schema::table('payroll_rates', function (Blueprint $table) {
            // A flat amount added once per employee per pay period. 0 is a real
            // answer — an office that pays no bonus has said so — so it is not
            // nullable the way the wage floor is.
            $table->decimal('bonus', 10, 2)->default(0);
        });

        // Carry the settings value onto every existing row, back to the opening
        // one. Leaving them at 0 would drop the bonus from periods that were
        // paid with it, which is the exact rewrite this table exists to stop.
        $bonus = DB::table('settings')->value('bonus');

        if ($bonus !== null && (float) $bonus > 0) {
            DB::table('payroll_rates')->update(['bonus' => $bonus]);
        }
    }

    public function down(): void
    {
        Schema::table('payroll_rates', function (Blueprint $table) {
            $table->dropColumn('bonus');
        });
    }
};
