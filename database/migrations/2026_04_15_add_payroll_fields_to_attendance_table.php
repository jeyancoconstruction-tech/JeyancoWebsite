<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('attendances', function (Blueprint $table) {
            $table->decimal('vale', 8, 2)->default(0)->after('time_out');
            $table->decimal('deductions', 8, 2)->default(0)->after('vale');
        });
    }

    public function down(): void {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn(['vale', 'deductions']);
        });
    }
};
