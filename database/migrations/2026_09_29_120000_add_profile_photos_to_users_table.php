<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A face for each account.
 *
 *  - google_avatar: the picture Google gives for the address, refreshed at
 *    every Google sign-in.
 *  - photo: one the person chose, which wins over Google's. Kept in the row
 *    as a small data URL (the page shrinks it to 256px first) because the
 *    deployment's disk is wiped on every deploy — a stored file would vanish.
 *
 * Neither set: the first letter of the name, as before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'google_avatar')) {
                $table->string('google_avatar', 1024)->nullable();
            }
            if (! Schema::hasColumn('users', 'photo')) {
                $table->mediumText('photo')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['google_avatar', 'photo'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
