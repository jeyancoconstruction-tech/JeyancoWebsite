<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Let an office turn the withholding tax off.
     *
     * The BIR table is not a rate anyone sets, so it was applied unconditionally
     * — which is right for a payroll that withholds, and wrong for one that does
     * not yet, or whose workers are all below the exempt threshold and are being
     * shown a deduction they never see. Whether to withhold is a decision; the
     * table it uses is not.
     *
     * Dated like every other payroll number: switching it off must not go back
     * and un-withhold a period already paid and already remitted.
     *
     * Existing rows default to on, which is what they were doing.
     *
     * Only alters a table an earlier migration created; no forward reference.
     */
    public function up(): void
    {
        Schema::table('payroll_rates', function (Blueprint $table) {
            $table->boolean('withholding_tax')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('payroll_rates', function (Blueprint $table) {
            $table->dropColumn('withholding_tax');
        });
    }
};
