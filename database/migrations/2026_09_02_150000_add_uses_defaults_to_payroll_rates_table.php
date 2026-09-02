<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Let a rate set say "the statutory ones" instead of naming numbers.
     *
     * An office that has not been told otherwise is on the figures the Labor
     * Code and the contribution circulars state outright. Typing them in by
     * hand means they are a copy: correct the day it was made, and silently
     * stale the day a circular moves. A row that says it is on defaults reads
     * them from PayrollRate's constants at compute time, so it follows.
     *
     * Existing rows stay off it. Their numbers were entered deliberately —
     * including the opening row's 0% contributions, which are a real answer —
     * and turning them into defaults would change pay nobody agreed to change.
     *
     * Only alters a table an earlier migration created; no forward reference.
     */
    public function up(): void
    {
        Schema::table('payroll_rates', function (Blueprint $table) {
            $table->boolean('uses_defaults')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('payroll_rates', function (Blueprint $table) {
            $table->dropColumn('uses_defaults');
        });
    }
};
