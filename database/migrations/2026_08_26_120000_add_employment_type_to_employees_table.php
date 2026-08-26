<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Records whether a worker is paid by the day or engaged on a contract.
     *
     * Payroll is unchanged by this: every existing worker is paid per day
     * worked, so they all default to 'daily' and the computation carries on
     * exactly as before. The column exists so the office can mark who is
     * contractual now, while the rules for paying them are still being
     * settled — a label first, a formula later.
     */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            if (! Schema::hasColumn('employees', 'employment_type')) {
                $table->string('employment_type', 20)
                      ->default('daily')
                      ->after('position');
            }
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            if (Schema::hasColumn('employees', 'employment_type')) {
                $table->dropColumn('employment_type');
            }
        });
    }
};
