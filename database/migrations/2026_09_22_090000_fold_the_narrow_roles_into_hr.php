<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Two roles: Administrator and HR.
 *
 * Staff, Payroll Officer and Site Supervisor are folded into HR. Every account
 * is kept as it is - active or not, with its history - and only its role
 * changes. HR opens what the office needs (see App\Support\Modules), so no
 * account loses a screen it used; the Site Supervisor and Payroll Officer
 * accounts gain the few modules their narrower role had left out.
 * Who was moved is written to the Audit Log, names included.
 */
return new class extends Migration
{
    private const FOLDED = ['staff', 'payroll_officer', 'site_supervisor'];

    public function up(): void
    {
        $accounts = DB::table('users')->whereIn('role', self::FOLDED)->get(['id', 'name', 'username']);

        if ($accounts->isEmpty()) {
            return;
        }

        DB::table('users')->whereIn('id', $accounts->pluck('id'))->update([
            'role'       => 'hr',
            'is_admin'   => false,
            'updated_at' => now(),
        ]);

        if (Schema::hasTable('audit_logs')) {
            $names = $accounts->map(fn ($a) => $a->name ?: $a->username)->take(20)->implode(', ');

            DB::table('audit_logs')->insert([
                'user_name'   => 'System',
                'module'      => 'Users',
                'action'      => 'updated',
                'description' => 'Roles reduced to Administrator and HR. Moved ' . $accounts->count() . ' '
                    . ($accounts->count() === 1 ? 'account' : 'accounts') . ' to HR: ' . $names
                    . ($accounts->count() > 20 ? ' and ' . ($accounts->count() - 20) . ' more' : ''),
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Which role each account had before is in the Audit Log. Nothing is
        // put back automatically: the old roles no longer exist in the code.
    }
};
