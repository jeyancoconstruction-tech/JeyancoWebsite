<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * How far from its pin a site counts as on-site, in metres.
     *
     * One radius used to cover every site (KIOSK_GEOFENCE_RADIUS, 150 m). A
     * tower on a city block and a road job strung along a kilometre do not
     * fit the same circle, so each site carries its own. Null means the site
     * was never given one and still uses that office-wide figure — which is
     * every site that exists when this runs, so nothing changes for them.
     */
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            if (! Schema::hasColumn('sites', 'geofence_radius')) {
                $table->unsignedSmallInteger('geofence_radius')->nullable()->after('longitude');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            if (Schema::hasColumn('sites', 'geofence_radius')) {
                $table->dropColumn('geofence_radius');
            }
        });
    }
};
