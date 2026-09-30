<?php

use App\Models\AuditLog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Eleven workers from the crew list were saved with "N/A" as their middle
 * name while the Edit page still required one. Michael asked for real-looking
 * middle names instead (2026-09-30). These are stand-ins, not taken from
 * any document. Brothers share one, as they would.
 *
 * Only a middle name that reads N/A is touched, and `name` is composed again
 * from the parts, so running this anywhere else changes nothing.
 */
return new class extends Migration
{
    /** "first last" (lower case) → middle name. */
    private const MIDDLE = [
        'michael martinez'  => 'Santos',
        'christian menes'   => 'Dizon',
        'robert menes'      => 'Dizon',
        'romar menes'       => 'Dizon',
        'jhon mike noga'    => 'Ramos',
        'jeorge nuñez'      => 'Cruz',
        'leomar nuñez'      => 'Cruz',
        'leonil nuñez'      => 'Cruz',
        'giovanes osabel'   => 'Bautista',
        'cris palmones'     => 'Villanueva',
        'ericson pefanio'   => 'Garcia',
    ];

    /** For an N/A the list above does not name. */
    private const POOL = ['Reyes', 'Mendoza', 'Aquino', 'Castillo', 'Rivera', 'Navarro', 'Torres', 'Flores'];

    public function up(): void
    {
        $this->fill();
    }

    public function fill(): int
    {
        if (! Schema::hasColumn('employees', 'middle_name')) {
            return 0;
        }

        $suffix = Schema::hasColumn('employees', 'name_suffix');
        $rows   = DB::table('employees')
            ->whereRaw("UPPER(REPLACE(REPLACE(TRIM(middle_name), '/', ''), '.', '')) = 'NA'")
            ->orderBy('id')
            ->get();

        foreach ($rows as $i => $row) {
            $key    = mb_strtolower(trim($row->first_name . ' ' . $row->last_name));
            $middle = self::MIDDLE[$key] ?? self::POOL[$i % count(self::POOL)];
            $name   = trim(preg_replace('/\s+/', ' ', implode(' ', array_filter([
                $row->first_name, $middle, $row->last_name, $suffix ? $row->name_suffix : null,
            ]))));

            DB::table('employees')->where('id', $row->id)->update([
                'middle_name' => $middle,
                'name'        => $name,
                'updated_at'  => now(),
            ]);
        }

        if ($rows->isNotEmpty() && Schema::hasTable('audit_logs')) {
            AuditLog::record('Employees', 'updated', "Middle names filled in for {$rows->count()} workers whose middle name read N/A.");
        }

        return $rows->count();
    }

    public function down(): void
    {
        // The N/A was a placeholder, not a value worth putting back.
    }
};
