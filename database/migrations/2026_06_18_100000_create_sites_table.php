<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        // Seed default sites
        $now = now();
        \Illuminate\Support\Facades\DB::table('sites')->insert([
            ['name' => 'Site A', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Site B', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Site C', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Site D', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Site E', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('sites');
    }
};
