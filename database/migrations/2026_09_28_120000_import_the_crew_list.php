<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The crew list (all_employees.csv, 2026-09-28): 47 workers, all at Site A.
 *
 * Payroll pays a worker the daily rate of their labour type, and a labour type
 * has one rate — but the crew is paid by the person: Laborers from ₱450 to
 * ₱650, Skilled from ₱600 to ₱1,000. So each position-and-rate pair is its own
 * labour type ("Laborer ₱500"), and everyone is paid exactly their own rate.
 *
 * They arrive as PENDING, like anyone registered on the web: they become
 * active when their finger is enrolled at the Site A kiosk, where they now
 * appear. The name is stored whole, as the kiosk stores it; the edit form
 * splits it into first / middle / last when someone opens it.
 *
 * Safe to run twice: a worker whose name is already on file is left alone,
 * and a labour type that already exists is reused.
 */
return new class extends Migration
{
    /** [name, position, daily rate, rate per hour] — as in the file. */
    private const CREW = [
        ['Jay Ann Alpapara', 'Engineering Staff', 600, 75.00],
        ['Allan Alperez', 'Laborer', 500, 62.50],
        ['Jay-R Alvarado', 'Laborer', 500, 62.50],
        ['James Michael Arevalo', 'Skilled', 750, 93.75],
        ['Pascual Balasta Jr.', 'Skilled', 600, 75.00],
        ['Marvin Barrameda', 'Laborer', 500, 62.50],
        ['Alvin Barredo', 'Skilled', 650, 81.25],
        ['Rodolfo Bequio Jr.', 'Laborer', 500, 62.50],
        ['Alvin Bocacao', 'Laborer', 600, 75.00],
        ['Jessa Bomalay', 'Engineering Staff', 600, 75.00],
        ['Norman Bragais', 'Laborer', 450, 56.25],
        ['Norman Bueta', 'Laborer', 600, 75.00],
        ['Enrico Carpio', 'Skilled', 650, 81.25],
        ['Ken Castilio', 'Skilled', 750, 93.75],
        ['Marck Catimbang', 'Laborer', 500, 62.50],
        ['Jayson Dava', 'Laborer', 500, 62.50],
        ['John Glenn Digamon', 'Laborer', 500, 62.50],
        ['Gerry Boy Ecot', 'Laborer', 500, 62.50],
        ['Jeffrey Efondo', 'Skilled', 650, 81.25],
        ['Randy Ersolada', 'Laborer', 500, 62.50],
        ['Reynaldo Ersolada', 'Skilled', 600, 75.00],
        ['Roy Ersolada', 'Skilled', 650, 81.25],
        ['Sylvia Imperial', 'Engineering Staff', 800, 100.00],
        ['Michael Martinez', 'Skilled', 700, 87.50],
        ['Christian Menes', 'Skilled', 750, 93.75],
        ['Robert Menes', 'Skilled', 800, 100.00],
        ['Romar Menes', 'Laborer', 600, 75.00],
        ['Jhon Mike Noga', 'Laborer', 500, 62.50],
        ['Emilio Nuñez Jr.', 'Laborer', 600, 75.00],
        ['Emilio Nuñez Sr.', 'Skilled', 1000, 125.00],
        ['Jeorge Nuñez', 'Skilled', 750, 93.75],
        ['Leomar Nuñez', 'Skilled', 750, 93.75],
        ['Leonil Nuñez', 'Skilled', 750, 93.75],
        ['Giovanes Osabel', 'Laborer', 600, 75.00],
        ['Cris Palmones', 'Skilled', 600, 75.00],
        ['Ericson Pefanio', 'Laborer', 500, 62.50],
        ['Marvin Resare', 'Laborer', 600, 75.00],
        ['Rolly Revilla', 'Laborer', 500, 62.50],
        ['Argie Reñono', 'Laborer', 500, 62.50],
        ['Ryan Reñono', 'Skilled', 600, 75.00],
        ['Rosito Riñono', 'Skilled', 750, 93.75],
        ['Eugenio Rosela', 'Laborer', 450, 56.25],
        ['Camila Samlero', 'Staff', 450, 56.25],
        ['Edcel Sarmiento', 'Laborer', 650, 81.25],
        ['Leo Sta. Rosa', 'Laborer', 550, 68.75],
        ['Alexander Supe', 'Laborer', 500, 62.50],
        ['Jhon Carlo Tayangona', 'Laborer', 500, 62.50],
    ];

    private const SITE = 'Site A';

    /** "Laborer ₱500" — the labour type for one position at one rate. */
    public static function laborTypeName(string $position, float $rate): string
    {
        return $position . ' ₱' . number_format($rate, 0);
    }

    public function up(): void
    {
        // A test database starts empty on purpose, and every test counts on
        // it. The import has its own test, which runs it by hand (import()).
        if (app()->runningUnitTests()) {
            return;
        }

        $this->import();
    }

    public function import(): void
    {
        if (! Schema::hasTable('employees') || ! Schema::hasTable('labor_types')) {
            return;
        }

        $now = now();

        $siteId = Schema::hasTable('sites')
            ? (DB::table('sites')->where('name', self::SITE)->value('id')
               ?? DB::table('sites')->insertGetId(['name' => self::SITE, 'created_at' => $now, 'updated_at' => $now]))
            : null;

        $shiftId = Schema::hasTable('shifts')
            ? DB::table('shifts')->where('crosses_midnight', false)->orderBy('id')->value('id')
            : null;

        $has = fn (string $column) => Schema::hasColumn('employees', $column);
        $trashed = $has('deleted_at');

        // Names already on file, compared without case or extra spaces.
        $norm = fn (string $name) => mb_strtolower(preg_replace('/\s+/', ' ', trim($name)));
        $existing = DB::table('employees')
            ->when($trashed, fn ($q) => $q->whereNull('deleted_at'))
            ->pluck('name')
            ->map($norm)
            ->flip();

        $types = [];

        foreach (self::CREW as [$name, $position, $daily, $hourly]) {
            $typeName = self::laborTypeName($position, $daily);

            $types[$typeName] ??= DB::table('labor_types')->where('name', $typeName)->value('id')
                ?? DB::table('labor_types')->insertGetId([
                    'name' => $typeName, 'daily_rate' => $daily, 'created_at' => $now, 'updated_at' => $now,
                ]);

            if ($existing->has($norm($name))) {
                continue;
            }

            $row = [
                'name'          => $name,
                'position'      => $position,
                'rate_per_hour' => $hourly,
                'labor_type_id' => $types[$typeName],
                'status'        => 'pending',
                'created_at'    => $now,
                'updated_at'    => $now,
            ];
            if ($has('employment_type')) $row['employment_type'] = 'daily';
            if ($has('site_id'))         $row['site_id'] = $siteId;
            if ($has('shift_id'))        $row['shift_id'] = $shiftId;
            if ($has('job_title'))       $row['job_title'] = $position;
            if ($has('vale'))            $row['vale'] = 0;

            DB::table('employees')->insert($row);
            $existing->put($norm($name), true);
        }
    }

    /**
     * Takes back only what nothing has touched since: a worker from this list
     * with no fingerprint and no attendance, and a labour type left unused.
     */
    public function down(): void
    {
        if (! Schema::hasTable('employees')) {
            return;
        }

        $names = array_column(self::CREW, 0);
        $ids = DB::table('employees')->whereIn('name', $names)->whereNull('fingerprint_id')->pluck('id');
        if (Schema::hasTable('attendances')) {
            $ids = $ids->diff(DB::table('attendances')->whereIn('employee_id', $ids)->pluck('employee_id'));
        }
        DB::table('employees')->whereIn('id', $ids)->delete();

        foreach (self::CREW as [, $position, $daily]) {
            $typeId = DB::table('labor_types')->where('name', self::laborTypeName($position, $daily))->value('id');
            if ($typeId && ! DB::table('employees')->where('labor_type_id', $typeId)->exists()) {
                DB::table('labor_types')->where('id', $typeId)->delete();
            }
        }
    }
};
