<?php
// =============================================
// routes/api.php — the kiosk's API
// =============================================

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\KioskController;
use App\Http\Controllers\KioskAiController;
use App\Http\Controllers\KioskLocationController;
use App\Http\Controllers\KioskPortalController;
use App\Http\Middleware\KioskDevice;
use App\Http\Middleware\RecordKioskEvent;

// ─────────────────────────────────────────────────────────────────────────
// What the kiosk SCREEN reads straight from the web (it runs in a browser and
// holds no secret): its settings and the server's clock, the sites, the
// roster to enrol from and today's board.
// ─────────────────────────────────────────────────────────────────────────
Route::get ('/kiosk/labor-types',      [KioskController::class, 'getLaborTypes']);
Route::get ('/kiosk/settings',         [KioskController::class, 'getSettings']);
Route::get ('/kiosk/projects',         [KioskController::class, 'getProjects']);
Route::get ('/kiosk/sites',            [KioskController::class, 'getSites']);
Route::post('/kiosk/active-site',      [KioskController::class, 'setActiveSite']);
Route::get ('/kiosk/roster',           [KioskController::class, 'roster']);
Route::get ('/kiosk/today-attendance', [KioskController::class, 'todayAttendance']);

// The write. With a device key set (config kiosk.device_key) it records only
// with a scan token from a finger read moments ago — see App\Support\KioskScan.
Route::post('/kiosk/attendance',       [KioskController::class, 'attendance'])->middleware(RecordKioskEvent::class);

// ─────────────────────────────────────────────────────────────────────────
// Only a kiosk Pi (X-Kiosk-Key, when a key is set): everything that takes a
// fingerprint slot as "this finger was just read", or hands out one worker's
// records. The screen reaches these through the Pi's fingerprint service.
// ─────────────────────────────────────────────────────────────────────────
Route::middleware(KioskDevice::class)->group(function () {
    // Identify a scanned finger (no write) / the older clock-by-finger.
    Route::post('/kiosk/scan-attendance', [KioskController::class, 'scanAttendance'])->middleware(RecordKioskEvent::class);
    Route::post('/kiosk/clock',           [KioskController::class, 'clock'])->middleware(RecordKioskEvent::class);

    // Enrolment, and the sensor sync (the Pi deletes slots missing from here).
    Route::post('/kiosk/save-fingerprint',    [KioskController::class, 'saveFingerprint']);
    Route::post('/kiosk/register-employee',   [KioskController::class, 'registerEmployee']);
    Route::get ('/kiosk/active-fingerprints', [KioskController::class, 'activeFingerprints']);

    // Lookups that name people, their rates or their records.
    Route::get ('/kiosk/employees',         [KioskController::class, 'getEmployees']);
    Route::get ('/kiosk/employees-details', [KioskController::class, 'getEmployeesWithDetails']);
    Route::post('/kiosk/biometric',         [KioskController::class, 'getEmployeeByBiometric']);
    Route::get ('/kiosk/attendance',        [KioskController::class, 'getAttendanceRecords']);

    // A worker's own DTR and complaints, opened with their finger (scan token).
    Route::get ('/kiosk/my-dtr',     [KioskPortalController::class, 'dtr']);
    Route::get ('/kiosk/complaints', [KioskPortalController::class, 'complaints']);
    Route::post('/kiosk/complaints', [KioskPortalController::class, 'storeComplaint'])->middleware('throttle:20,1');

    // Older payroll screens, addressed by fingerprint slot.
    Route::post('/kiosk/ask',                      [KioskAiController::class, 'ask']);
    Route::get ('/employees/by-finger/{fingerId}', [KioskAiController::class, 'byFinger']);
    Route::get ('/kiosk/my-payroll/{fingerId}',    [KioskAiController::class, 'summary']);
});

// ✅ Kiosk GPS (NEO-M8L on the Pi, posted every ~30s). Cache-only: latest fix wins.
//    The literal /location/latest must precede /location/{kioskId}, otherwise
//    "latest" is captured as a kiosk id.
Route::post('/location',                  [KioskLocationController::class, 'store']);
Route::get ('/location/latest',           [KioskLocationController::class, 'latestByQuery']); // dashboard map
Route::get ('/location/{kioskId}/status', [KioskLocationController::class, 'status']);        // anti-theft state
Route::get ('/location/{kioskId}',        [KioskLocationController::class, 'latest']);
