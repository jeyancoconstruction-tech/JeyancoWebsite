<?php

namespace App\Support;

use App\Models\User;

/**
 * Which role may open which of the NEW modules.
 *
 * Scope, deliberately: this map guards only the modules added alongside it.
 * The routes that existed before keep the guards they already had — 'auth',
 * 'active' and 'is_admin' — because narrowing them would take access away from
 * accounts that have it today, which is a different change from adding
 * features. Admin remains total.
 */
final class Modules
{
    // Keys used on routes and in the sidebar.
    public const LEAVE       = 'leave';
    public const LOANS       = 'loans';
    public const ASSIGNMENTS = 'assignments';
    public const SITE_ATT    = 'site-attendance';
    public const PROCESSING  = 'payroll-processing';
    public const PAYSLIPS    = 'payslips';
    public const DEDUCTIONS  = 'deductions';
    public const REPORTS     = 'payroll-reports';
    public const USERS       = 'users-roles';
    public const AUDIT       = 'audit-logs';
    public const DEVICES     = 'devices';

    /**
     * role => modules it may open. Admin is absent on purpose: it is answered
     * before this map is consulted.
     */
    private const MATRIX = [
        // The role every existing non-admin account already has. It keeps
        // everything it could reach before, and gains the operational modules
        // — but not user administration, the audit trail, or the device rail.
        User::ROLE_STAFF => [
            self::LEAVE, self::LOANS, self::ASSIGNMENTS, self::SITE_ATT,
            self::PROCESSING, self::PAYSLIPS, self::DEDUCTIONS, self::REPORTS,
        ],

        User::ROLE_PAYROLL => [
            self::LEAVE, self::LOANS, self::SITE_ATT,
            self::PROCESSING, self::PAYSLIPS, self::DEDUCTIONS, self::REPORTS,
        ],

        User::ROLE_HR => [
            self::LEAVE, self::LOANS, self::ASSIGNMENTS, self::SITE_ATT,
        ],

        User::ROLE_SUPERVISOR => [
            self::LEAVE, self::ASSIGNMENTS, self::SITE_ATT, self::DEVICES,
        ],

        // Sees their own records only. The controllers narrow the query; this
        // just decides which doors open at all.
        User::ROLE_EMPLOYEE => [
            self::LEAVE, self::PAYSLIPS,
        ],
    ];

    /** Modules only an Admin ever opens, whatever the matrix says. */
    private const ADMIN_ONLY = [self::USERS, self::AUDIT];

    public static function allows(?User $user, string $module): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        if (in_array($module, self::ADMIN_ONLY, true)) {
            return false;
        }

        return in_array($module, self::MATRIX[$user->role] ?? [], true);
    }

    /** Every module a role may open — used to render the role matrix screen. */
    public static function forRole(string $role): array
    {
        if ($role === User::ROLE_ADMIN) {
            return self::all();
        }

        return self::MATRIX[$role] ?? [];
    }

    public static function all(): array
    {
        return array_keys(self::labels());
    }

    /** Human labels, matching the sidebar wording exactly. */
    public static function labels(): array
    {
        return [
            self::LEAVE       => 'Leave & Overtime',
            self::LOANS       => 'Loans & Advances',
            self::ASSIGNMENTS => 'Project Assignment',
            self::SITE_ATT    => 'Site Attendance',
            self::PROCESSING  => 'Payroll Processing',
            self::PAYSLIPS    => 'Payslips',
            self::DEDUCTIONS  => 'Deductions & Contributions',
            self::REPORTS     => 'Payroll Reports',
            self::USERS       => 'Users & Roles',
            self::AUDIT       => 'Audit Logs',
            self::DEVICES     => 'Device Monitoring',
        ];
    }
}

