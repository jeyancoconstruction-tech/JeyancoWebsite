<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Workers use the kiosk, not the web.
 *
 * The Employee role gave a worker a web login — one that, on the screens older
 * than the module map, opened as much as a Staff account does — so the role is
 * gone. Any account still carrying it is kept rather than deleted: it is
 * deactivated and moved to Staff, so its history stays, it can no longer sign
 * in, and bringing it back is an administrator's decision on Users & Roles.
 * What happened is written to the Audit Log, names included.
 */
return new class extends Migration
{
    public function up(): void
    {
        $accounts = DB::table('users')->where('role', 'employee')->get(['id', 'name', 'username']);

        if ($accounts->isEmpty()) {
            return;
        }

        DB::table('users')->whereIn('id', $accounts->pluck('id'))->update([
            'role'       => 'staff',
            'is_admin'   => false,
            'is_active'  => false,
            'updated_at' => now(),
        ]);

        if (Schema::hasTable('audit_logs')) {
            $names = $accounts->map(fn ($a) => $a->name ?: $a->username)->take(20)->implode(', ');

            DB::table('audit_logs')->insert([
                'user_name'   => 'System',
                'module'      => 'Users',
                'action'      => 'updated',
                'description' => 'Employee role retired — workers use the kiosk. Deactivated ' . $accounts->count()
                    . ' ' . ($accounts->count() === 1 ? 'account' : 'accounts') . ' and moved them to Staff: ' . $names
                    . ($accounts->count() > 20 ? ' and ' . ($accounts->count() - 20) . ' more' : ''),
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Which accounts were moved is in the Audit Log. Nothing is put back
        // automatically: a worker's web login is a decision, not a rollback.
    }
};
