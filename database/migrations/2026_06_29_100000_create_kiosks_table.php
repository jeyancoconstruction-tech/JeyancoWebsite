<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Fingerprint kiosks. Each physical kiosk lives at one site (Site A for now,
 * Site B later). Employees and their attendance are traced back to the kiosk
 * that detected them via employees.kiosk_id.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('kiosks', function (Blueprint $table) {
            $table->id();
            $table->string('name');                       // "Site A Kiosk"
            $table->string('code')->unique();             // stable machine key, e.g. "SITE_A"
            $table->unsignedBigInteger('site_id')->nullable();
            $table->string('location')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_seen_at')->nullable(); // updated on every kiosk ping
            $table->timestamps();

            $table->foreign('site_id')
                  ->references('id')->on('sites')
                  ->nullOnDelete();
        });

        // ── Ensure Site A exists, then register the Site A kiosk ────────────────
        $siteAId = DB::table('sites')->where('name', 'Site A')->value('id');
        if (!$siteAId) {
            $siteAId = DB::table('sites')->insertGetId([
                'name'       => 'Site A',
                'location'   => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('kiosks')->insert([
            'name'         => 'Site A Kiosk',
            'code'         => 'SITE_A',
            'site_id'      => $siteAId,
            'location'     => 'Site A',
            'is_active'    => true,
            'last_seen_at' => null,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('kiosks');
    }
};
