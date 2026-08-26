<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The agreed amount a contractual worker earns for each day present.
     *
     * Nullable and opt-in: with no rate set, a worker is paid exactly as
     * before, even if they are already tagged contractual. Nothing about
     * existing payroll changes until someone fills this in.
     */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            if (! Schema::hasColumn('employees', 'contract_rate')) {
                $table->decimal('contract_rate', 10, 2)
                      ->nullable()
                      ->after('employment_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            if (Schema::hasColumn('employees', 'contract_rate')) {
                $table->dropColumn('contract_rate');
            }
        });
    }
};
