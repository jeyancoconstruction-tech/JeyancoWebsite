<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        // These columns may already exist on fresh installs because the
        // create_employees_table migration also defines them. Guard each
        // one so this migration is idempotent across environments.
        Schema::table('employees', function (Blueprint $table) {
            if (! Schema::hasColumn('employees', 'fingerprint_id')) {
                $table->string('fingerprint_id')->nullable()->unique()->after('labor_type_id');
            }
            if (! Schema::hasColumn('employees', 'photo')) {
                $table->string('photo')->nullable()->after('fingerprint_id');
            }
        });
    }

    public function down(): void {
        Schema::table('employees', function (Blueprint $table) {
            if (Schema::hasColumn('employees', 'fingerprint_id')) {
                $table->dropUnique(['fingerprint_id']);
                $table->dropColumn('fingerprint_id');
            }
            if (Schema::hasColumn('employees', 'photo')) {
                $table->dropColumn('photo');
            }
        });
    }
};
