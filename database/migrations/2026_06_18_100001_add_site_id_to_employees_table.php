<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('site_id')
                  ->nullable()
                  ->after('labor_type_id')
                  ->constrained('sites')
                  ->nullOnDelete();
        });

        // Assign all existing employees to Site A (id = 1)
        $siteA = \Illuminate\Support\Facades\DB::table('sites')->where('name', 'Site A')->value('id');
        if ($siteA) {
            \Illuminate\Support\Facades\DB::table('employees')->update(['site_id' => $siteA]);
        }
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['site_id']);
            $table->dropColumn('site_id');
        });
    }
};
