<?php
// =============================================
// routes/api.php — COMPLETE updated version
// =============================================

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\KioskController;
use App\Http\Controllers\KioskAiController;
use App\Http\Controllers\KioskLocationController;

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

// ✅ Kiosk GPS (NEO-M8L on the Pi, posted every ~30s). Cache-only: latest fix wins.
//    The literal /location/latest must precede /location/{kioskId}, otherwise
//    "latest" is captured as a kiosk id.
Route::post('/location',                  [KioskLocationController::class, 'store']);
Route::get ('/location/latest',           [KioskLocationController::class, 'latestByQuery']); // dashboard map
Route::get ('/location/{kioskId}/status', [KioskLocationController::class, 'status']);        // anti-theft state
Route::get ('/location/{kioskId}',        [KioskLocationController::class, 'latest']);

// ✅ Kiosk payroll AI assistant (Claude). Answers only about the scanned employee.
Route::post('/kiosk/ask',                     [KioskAiController::class, 'ask']);
Route::get ('/employees/by-finger/{fingerId}', [KioskAiController::class, 'byFinger']);