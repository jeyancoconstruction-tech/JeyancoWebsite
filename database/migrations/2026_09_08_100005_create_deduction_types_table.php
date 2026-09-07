<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The catalogue of things payroll may take off a worker.
 *
 * The statutory four already have their rates in Payroll Settings, and
 * PayrollService reads them from there. This table does NOT restate those
 * numbers; it records which deductions the office applies, in what order they
 * appear on a payslip, and lets a company-specific one (a uniform, a tool
 * bond) be added without a code change.
 *
 * `source` says who computes it, so nothing here can quietly override payroll:
 *   settings  PayrollService owns the figure  (SSS, PhilHealth, Pag-IBIG, tax)
 *   ledger    a balance elsewhere owns it     (loans, advances, vale)
 *   manual    a fixed amount or percent set here
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deduction_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 120);
            $table->string('category', 40)->default('other'); // statutory|loan|company|other
            $table->string('source', 20)->default('manual');  // settings|ledger|manual

            // Only read when source = manual. One or the other, not both.
            $table->decimal('amount', 12, 2)->nullable();
            $table->decimal('percentage', 6, 3)->nullable();

            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // The statutory and ledger-backed rows, so the screen opens on the
        // deductions the system already applies rather than an empty page.
        // Figures are deliberately absent: their owners hold them.
        $now = now();
        $rows = [
            ['sss',        'SSS Contribution',         'statutory', 'settings', 10],
            ['philhealth', 'PhilHealth Contribution',  'statutory', 'settings', 20],
            ['pagibig',    'Pag-IBIG Contribution',    'statutory', 'settings', 30],
            ['tax',        'Withholding Tax',          'statutory', 'settings', 40],
            ['vale',       'Vale',                     'loan',      'ledger',   50],
            ['loan',       'Loan Repayment',           'loan',      'ledger',   60],
            ['advance',    'Salary Advance',           'loan',      'ledger',   70],
        ];

        foreach ($rows as [$code, $name, $category, $source, $order]) {
            \Illuminate\Support\Facades\DB::table('deduction_types')->insert([
                'code'       => $code,
                'name'       => $name,
                'category'   => $category,
                'source'     => $source,
                'is_active'  => true,
                'sort_order' => $order,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('deduction_types');
    }
};

