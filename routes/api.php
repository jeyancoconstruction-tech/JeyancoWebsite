<?php
// =============================================
// routes/api.php — COMPLETE updated version
// =============================================

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\KioskController;

// Existing routes
Route::get('/kiosk/employees',         [KioskController::class, 'getEmployees']);
Route::get('/kiosk/employees-details', [KioskController::class, 'getEmployeesWithDetails']);
Route::get('/kiosk/labor-types',       [KioskController::class, 'getLaborTypes']);
Route::get('/kiosk/settings',          [KioskController::class, 'getSettings']);
Route::post('/kiosk/biometric',        [KioskController::class, 'getEmployeeByBiometric']);
Route::post('/kiosk/register-employee',[KioskController::class, 'registerEmployee']);
Route::post('/kiosk/attendance',       [KioskController::class, 'attendance']);

// ✅ NEW — idagdag ito
Route::get('/kiosk/projects',          [KioskController::class, 'getProjects']);
Route::get('/kiosk/attendance',        [KioskController::class, 'getAttendanceRecords']);
Route::post('/kiosk/save-fingerprint', [KioskController::class, 'saveFingerprint']);

// ✅ Primary fingerprint clock — find-or-create (pending) + record attendance.
//   /clock           → caller supplies type (time_in|time_out)
//   /scan-attendance → server auto-decides type (used by the Pi scan loop)
Route::post('/kiosk/clock',            [KioskController::class, 'clock']);
Route::post('/kiosk/scan-attendance',  [KioskController::class, 'scanAttendance']);

// ✅ Realtime "who is on site today" board (AM/PM in-out + overtime).
Route::get('/kiosk/today-attendance',  [KioskController::class, 'todayAttendance']);