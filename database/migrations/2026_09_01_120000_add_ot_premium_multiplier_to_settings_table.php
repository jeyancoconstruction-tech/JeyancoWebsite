<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Overtime carries two different premiums under the Labor Code, and the
     * settings table only held one.
     *
     *  - ot_multiplier         : OT on an ordinary working day, +25% (1.25)
     *  - ot_premium_multiplier : OT on a rest day, special day or holiday,
     *                            +30% of that day's rate (1.30) — NEW
     *
     * Payroll applied 1.25 to every overtime hour, which paid 250% for
     * overtime on a regular holiday where DOLE calls for 260%, and 162.5%
     * on a rest day where it calls for 169%.
     *
     * Only touches the settings table, which every earlier migration has
     * already created — no forward reference for MySQL to reject.
     */
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            if (! Schema::hasColumn('settings', 'ot_premium_multiplier')) {
                $table->decimal('ot_premium_multiplier', 5, 2)
                      ->default(1.30)
                      ->after('ot_multiplier');
            }
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            if (Schema::hasColumn('settings', 'ot_premium_multiplier')) {
                $table->dropColumn('ot_premium_multiplier');
            }
        });
    }
};
