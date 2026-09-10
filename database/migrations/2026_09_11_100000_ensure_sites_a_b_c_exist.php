<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The kiosk is carried between Site A, Site B and Site C.
     *
     * The kiosk's site bar lists whatever sites the web has — nothing on the
     * Pi names them — and the web was down to Site A alone, so there was
     * nothing to switch to. This puts back Site B and Site C if they are
     * missing. A site that already exists, under any capitalisation, is left
     * exactly as it is; nothing is renamed or removed.
     *
     * They start without a location. Pin each one on the dashboard map (or
     * edit it on the Sites page) so the map and the GPS check know where it is.
     */
    public function up(): void
    {
        $existing = DB::table('sites')->pluck('name')
            ->map(fn ($n) => strtolower(trim($n)))
            ->all();

        foreach (['Site A', 'Site B', 'Site C'] as $name) {
            if (in_array(strtolower($name), $existing, true)) {
                continue;
            }

            DB::table('sites')->insert([
                'name'       => $name,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Left alone on purpose: by the time anyone rolls back, attendance and
     * workers may be filed under these sites, and deleting them would orphan
     * that history. Remove a site on the Sites page if it is not wanted.
     */
    public function down(): void
    {
    }
};
