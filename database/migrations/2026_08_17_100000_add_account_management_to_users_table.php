<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Turns "users" into a real account table the Admin can manage.
     *
     * Some environments already carry a stray `role` column — an
     * enum('super_admin','admin') that was added straight to the database and
     * never had a migration or any code behind it. Both shapes are handled so
     * this runs cleanly on a fresh database and on the existing ones.
     */
    public function up(): void
    {
        // role — plain string so the set of roles is owned by the app, not the
        // column definition. An existing enum is widened in place.
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'role')) {
                $table->string('role', 20)->default('staff')->change();
            } else {
                $table->string('role', 20)->default('staff')->after('password');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            // Deactivated accounts stay on file (and keep their audit trail)
            // but cannot sign in — safer than deleting a staff member.
            if (! Schema::hasColumn('users', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('role');
            }

            // Set when the Admin creates the account, for the "Added by" column.
            if (! Schema::hasColumn('users', 'created_by')) {
                $table->foreignId('created_by')->nullable()->after('is_active')
                      ->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('users', 'last_login_at')) {
                $table->timestamp('last_login_at')->nullable();
            }
        });

        // Email is optional for staff accounts — they sign in with a username.
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
        });

        // Backfill: is_admin has been the real source of truth until now, and
        // the stray 'super_admin' value folds into 'admin'.
        DB::table('users')->where('is_admin', 1)->update([
            'role' => 'admin',
            'is_active' => true,
        ]);

        // Non-admin rows predate this feature: until now the login screen
        // refused them outright, so they were dead accounts (including anything
        // left over from the old public /register page). Staff logins work from
        // here on, so they are parked as deactivated rather than silently
        // becoming live credentials. An Admin can switch any of them back on
        // from Account Management.
        DB::table('users')->where('is_admin', 0)->update([
            'role' => 'staff',
            'is_active' => false,
        ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'created_by')) {
                $table->dropConstrainedForeignId('created_by');
            }
            if (Schema::hasColumn('users', 'is_active')) {
                $table->dropColumn('is_active');
            }
        });

        // `role` and `last_login_at` predate this migration in some
        // environments, so they are intentionally left in place.
    }
};
