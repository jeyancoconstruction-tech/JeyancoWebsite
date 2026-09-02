<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The language the system speaks.
     *
     * 'en' is what every template was written in, so an existing install reads
     * exactly as it did. 'tl' resolves against lang/tl.json.
     */
    public function up(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->string('locale', 5)->default('en');
        });
    }

    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->dropColumn('locale');
        });
    }
};
