<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('fingerprint_id')->nullable()->unique()->after('labor_type_id');
            $table->string('photo')->nullable()->after('fingerprint_id');
        });
    }

    public function down(): void {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropUnique(['fingerprint_id']);
            $table->dropColumn(['fingerprint_id', 'photo']);
        });
    }
};
