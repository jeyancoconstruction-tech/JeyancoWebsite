<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Leave has no approval step.
 *
 * It is filed by the owner, HR and staff — the people who would approve it —
 * so filing it is the decision, and Pending and Rejected are gone from the
 * app. A row still on either would be stranded: invisible to payroll, and
 * with no button left that could move it.
 *
 * Pending becomes approved, as it would be if filed today, credited to whoever
 * filed it at the time it was filed. Rejected becomes cancelled — the other
 * status a leave that does not stand can have, and pays nothing, as before.
 *
 * Data only. The column's own default still reads "pending"; the model sets
 * "approved" on every row it writes, and changing a column default is the
 * kind of schema change that behaves differently on MySQL and SQLite.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('leave_requests')) {
            return;
        }

        $pending  = DB::table('leave_requests')->where('status', 'pending')->count();
        $rejected = DB::table('leave_requests')->where('status', 'rejected')->count();

        if ($pending === 0 && $rejected === 0) {
            return;
        }

        DB::table('leave_requests')->where('status', 'pending')->orderBy('id')->each(function ($row) {
            DB::table('leave_requests')->where('id', $row->id)->update([
                'status'      => 'approved',
                'approved_by' => $row->approved_by ?? $row->filed_by,
                'approved_at' => $row->approved_at ?? $row->created_at,
                'updated_at'  => now(),
            ]);
        });

        DB::table('leave_requests')->where('status', 'rejected')->update([
            'status'     => 'cancelled',
            'updated_at' => now(),
        ]);

        if (Schema::hasTable('audit_logs')) {
            DB::table('audit_logs')->insert([
                'user_name'   => 'System',
                'module'      => 'Leave',
                'action'      => 'updated',
                'description' => 'Leave approval step removed — filed leave counts as filed. Approved '
                    . $pending . ' pending ' . ($pending === 1 ? 'request' : 'requests')
                    . ' and marked ' . $rejected . ' rejected ' . ($rejected === 1 ? 'request' : 'requests') . ' cancelled.',
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }
    }

    /**
     * Not reversible: once converted, nothing records which approved rows
     * were pending and which cancelled ones were rejected.
     */
    public function down(): void
    {
    }
};
