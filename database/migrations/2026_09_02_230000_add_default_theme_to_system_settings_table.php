<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The theme a screen opens on before anybody has chosen one.
     *
     * The boot script had 'dark' written into it, so a new phone, a fresh
     * browser or a kiosk that has never been touched always opened dark. It is
     * the office's default now. A viewer who has picked a theme still keeps it
     * — their choice is in their own browser and outranks this.
     *
     * Default 'dark', which is what the script did.
     */
    public function up(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->string('default_theme', 10)->default('dark');
        });
    }

    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->dropColumn('default_theme');
        });
    }
};
