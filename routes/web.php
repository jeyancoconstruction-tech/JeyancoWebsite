<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\PayrollRecordsController;
use App\Http\Controllers\PayslipController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\AIController;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\NotificationController;


Route::get('/', function () {
    return redirect()->route('login');
});

Route::middleware('guest')->group(function () {

    // LOGIN
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.post');

    // REGISTER (AUTH USER)
    Route::get('/register', [AuthController::class, 'showRegisterForm'])->name('register');
    Route::post('/register', [AuthController::class, 'registerPost'])->name('register.post');

});

// LOGOUT (accessible to all authenticated users)
Route::middleware('auth')->post('/logout', [AuthController::class, 'logout'])->name('logout');

// ADMIN ONLY ROUTES - Role-Based Access Control
Route::middleware(['auth', 'is_admin'])->group(function () {

    // DASHBOARD
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // EMPLOYEES (CRUD + Biometric Register)
    // The bulk-delete and enrollment routes are declared before the resource so they
    // are not captured by the "/employees/{employee}" wildcard. Enrollment lives under
    // "/employees/register" so it no longer collides with the guest "/register" page.
    Route::delete('/employees/bulk-delete', [EmployeeController::class, 'bulkDelete'])->name('employees.bulk-delete');
    Route::delete('/employees/delete-all',  [EmployeeController::class, 'deleteAll'])->name('employees.delete-all');
    Route::get('/employees/register', [EmployeeController::class, 'register'])->name('employees.register');
    Route::get('/employees/register/live', [EmployeeController::class, 'registerLive'])->name('employees.register.live');

    // Employee lifecycle (Register & Manage hub). Declared before the resource
    // so their suffixed paths are never swallowed by "/employees/{employee}".
    Route::post  ('/employees/{employee}/complete', [EmployeeController::class, 'complete'])->name('employees.complete');
    Route::post  ('/employees/{employee}/vale',     [EmployeeController::class, 'updateVale'])->name('employees.vale');
    Route::patch ('/employees/{employee}/archive',  [EmployeeController::class, 'archive'])->name('employees.archive');
    Route::patch ('/employees/{employee}/activate', [EmployeeController::class, 'activate'])->name('employees.activate');
    Route::patch ('/employees/{employee}/restore',  [EmployeeController::class, 'restore'])->name('employees.restore');
    Route::delete('/employees/{employee}/force',    [EmployeeController::class, 'forceDelete'])->name('employees.force-delete');

    Route::resource('employees', EmployeeController::class);

    // SITES (dedicated Site Management module)
    Route::get('/sites',         [SiteController::class, 'index'])->name('sites.index');  // HTML page
    Route::get('/sites/list',    [SiteController::class, 'list'])->name('sites.list');    // JSON list
    Route::post('/sites',        [SiteController::class, 'store'])->name('sites.store');
    Route::put('/sites/{id}',    [SiteController::class, 'update'])->name('sites.update');
    Route::delete('/sites/{id}', [SiteController::class, 'destroy'])->name('sites.delete');
    
    // PAYROLL — old Pay Periods URL redirects to the unified module; the
    // inline vale/deduction AJAX editors are still served here.
    Route::get('/payroll', [PayrollController::class, 'index'])->name('payroll');
    Route::post('/payroll/update-vale/{id}', [PayrollController::class, 'updateVale']);
    Route::post('/payroll/update-deductions/{id}', [PayrollController::class, 'updateDeductions']);

    // PAYROLL RECORDS — unified module (Reports / By Employee / Pay Periods)
    Route::get('/payroll-records', [PayrollRecordsController::class, 'index'])->name('payroll-records');
    Route::get('/payroll-records/export', [PayrollRecordsController::class, 'export'])->name('payroll-records.export');
    Route::get('/payroll-records/export/excel', [PayrollRecordsController::class, 'exportExcel'])->name('payroll-records.export.excel');

    // Reports were consolidated into Payroll Records — keep old links working.
    Route::get('/reports', fn () => redirect()->route('payroll-records'))->name('reports');

    // PAYSLIPS (per-employee, per-period) — view / print / export
    Route::get('/payslip/{employee}', [PayslipController::class, 'show'])->name('payslip.show');
    Route::get('/payslip/{employee}/export', [PayslipController::class, 'export'])->name('payslip.export');

    // ATTENDANCE
    Route::get   ('/attendance',                     [AttendanceController::class, 'index'])->name('attendance');
    Route::delete('/attendance/history/bulk-delete', [AttendanceController::class, 'bulkDeleteHistory'])->name('attendance.history.bulk-delete');
    Route::delete('/attendance/history/delete-all',  [AttendanceController::class, 'deleteAllHistory'])->name('attendance.history.delete-all');

    // --- INSIGHTS & AI ROUTES ---
    Route::get('/analytics', [\App\Http\Controllers\AnalyticsController::class, 'index'])->name('analytics');

    Route::get('/ai-assistant', function () {
        return view('ai-assistant');
    })->name('ai-assistant');

    Route::post('/ai/chat', [AIController::class, 'chat'])->name('ai.chat');
    Route::get('/ai/history', [AIController::class, 'history'])->name('ai.history');
    Route::post('/ai/clear-old', [AIController::class, 'clearOldMessages'])->name('ai.clear-old');

    // --- SETTINGS MODULE ---
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::put('/settings/update', [SettingsController::class, 'update'])->name('settings.update');
    
    // Labor Types
    Route::post('/labor-types/store', [SettingsController::class, 'storeLaborType'])->name('labor-types.store');
    Route::put('/labor-types/{id}', [SettingsController::class, 'updateLaborType'])->name('labor-types.update');
    Route::delete('/labor-types/{id}', [SettingsController::class, 'deleteLaborType'])->name('labor-types.delete');
    Route::get('/labor-types/{id}/rates', [SettingsController::class, 'getLaborTypeRates'])->name('labor-types.rates');

    // Holidays (global, date-based; auto-synced with the official PH calendar)
    Route::post('/holidays', [SettingsController::class, 'storeHoliday'])->name('holidays.store');
    Route::post('/holidays/toggle', [SettingsController::class, 'toggleHoliday'])->name('holidays.toggle');
    Route::post('/holidays/bulk-toggle', [SettingsController::class, 'bulkToggleHolidays'])->name('holidays.bulk-toggle');
    Route::get('/holidays/calendar', [SettingsController::class, 'holidayCalendar'])->name('holidays.calendar');
    Route::put('/holidays/{id}', [SettingsController::class, 'editHoliday'])->name('holidays.edit');
    Route::delete('/holidays/{id}', [SettingsController::class, 'deleteHoliday'])->name('holidays.delete');

    // --- SEARCH FUNCTIONALITY ---
    Route::get('/search', [SearchController::class, 'search'])->name('search');
    Route::get('/search/suggestions', [SearchController::class, 'suggestions'])->name('search.suggestions');

    // --- NOTIFICATIONS ---
    Route::get('/notifications',              [NotificationController::class, 'index'])->name('notifications.index');
    Route::patch('/notifications/read-all',   [NotificationController::class, 'readAll'])->name('notifications.readAll');
    Route::delete('/notifications/delete-all',[NotificationController::class, 'deleteAll'])->name('notifications.deleteAll');
    Route::patch('/notifications/{id}/read',  [NotificationController::class, 'markRead'])->name('notifications.read');

});