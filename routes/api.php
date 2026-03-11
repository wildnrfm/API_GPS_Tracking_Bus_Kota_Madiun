<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/post', function () {
    dd('tes api');
});

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\UserController;
use App\Http\Controllers\API\DriverController;
use App\Http\Controllers\API\StudentController;
use App\Http\Controllers\API\AdminController;
use App\Http\Controllers\API\BusController;
use App\Http\Controllers\API\HalteController;
use App\Http\Controllers\API\BusDriverController;
use App\Http\Controllers\API\RouteHalteController;
use App\Http\Controllers\API\StudentBusController;
use App\Http\Controllers\API\GpsTrackController;
use App\Http\Controllers\API\AttendanceController;
use App\Http\Controllers\API\DailyReportController;
use App\Http\Controllers\API\ReportController;
use App\Http\Controllers\API\ActivityController;

// --- ALL USER ---
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:5,1')->name('login');
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:3,1');
    Route::middleware(['auth:api', 'check.token.expiration'])->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
        Route::post('change-password', [AuthController::class, 'changePassword']);
        Route::put('profile', [AuthController::class, 'updateProfile']);
        Route::post('profile/photo', [AuthController::class, 'uploadPhoto']);
    });
});

// --- ADMIN ONLY ---
Route::middleware(['auth:api', 'admin'])->group(function () {
    // admins
    Route::get('admins', [AdminController::class, 'index']);
    Route::get('admins/{id}', [AdminController::class, 'show']);
    Route::post('admins', [AdminController::class, 'store']);
    Route::put('admins/{id}', [AdminController::class, 'update']);
    Route::delete('admins/{id}', [AdminController::class, 'destroy']);

    // users
    Route::get('users', [UserController::class, 'index']);
    Route::get('users/{id}', [UserController::class, 'show']);
    Route::delete('users/{id}', [UserController::class, 'destroy']);

    // drivers
    Route::get('drivers', [DriverController::class, 'index']);
    Route::get('drivers/{id}', [DriverController::class, 'show']);
    Route::post('drivers', [DriverController::class, 'store']);
    Route::put('drivers/{id}', [DriverController::class, 'update']);
    Route::delete('drivers/{id}', [DriverController::class, 'destroy']);
    Route::get('drivers/{id}/history', [DriverController::class, 'history']);

    // students
    Route::get('students', [StudentController::class, 'index']);
    Route::get('students/pending', [StudentController::class, 'pending']);
    Route::get('students/{id}', [StudentController::class, 'show']);
    Route::post('students', [StudentController::class, 'store']);
    Route::put('students/{id}', [StudentController::class, 'update']);
    Route::post('students/{id}/approve', [StudentController::class, 'approve']);
    Route::post('students/{id}/reject', [StudentController::class, 'reject']);
    Route::post('students/{id}/suspend', [StudentController::class, 'suspend']);
    Route::post('students/{id}/unsuspend', [StudentController::class, 'unsuspend']);
    Route::delete('students/{id}', [StudentController::class, 'destroy']);
    Route::get('students/{id}/barcode', [StudentController::class, 'barcode']);

    // buses
    Route::get('buses', [BusController::class, 'index']);
    Route::get('buses/{id}', [BusController::class, 'show']);
    Route::post('buses', [BusController::class, 'store']);
    Route::put('buses/{id}', [BusController::class, 'update']);
    Route::delete('buses/{id}', [BusController::class, 'destroy']);
    Route::get('buses/{id}/students', [BusController::class, 'students']);
    Route::post('buses/{id}/students', [StudentBusController::class, 'assignStudentToBus']);
    Route::put('buses/{id}/students/{studentId}', [StudentBusController::class, 'update']);
    Route::delete('buses/{id}/students/{studentId}', [StudentBusController::class, 'destroy']);
    Route::get('buses/{id}/drivers', [BusController::class, 'drivers']);
    Route::get('buses/{id}/driver', [BusController::class, 'activeDriver']);
    Route::post('buses/{id}/drivers', [BusController::class, 'assignDriver']);

    // haltes
    Route::get('haltes', [HalteController::class, 'index']);
    Route::get('haltes/{id}', [HalteController::class, 'show']);
    Route::post('haltes', [HalteController::class, 'store']);
    Route::put('haltes/{id}', [HalteController::class, 'update']);
    Route::delete('haltes/{id}', [HalteController::class, 'destroy']);

    // route-haltes (urutan halte dalam rute)
    Route::post('routes/{id}/haltes', [RouteHalteController::class, 'storeHalteToRoute']);
    Route::put('route-haltes/{id}', [RouteHalteController::class, 'update']);
    Route::delete('route-haltes/{id}', [RouteHalteController::class, 'destroy']);

    // bus-driver (penugasan)
    Route::get('bus-driver', [BusDriverController::class, 'index']);
    Route::post('bus-driver', [BusDriverController::class, 'store']);
    Route::put('bus-driver/{id}', [BusDriverController::class, 'update']);
    Route::delete('bus-driver/{id}', [BusDriverController::class, 'destroy']);

    // student-bus
    Route::get('student-bus', [StudentBusController::class, 'index']);

    // gps-tracks
    Route::get('gps-tracks', [GpsTrackController::class, 'index']);
    Route::get('gps-tracks/latest', [GpsTrackController::class, 'latest']);
    Route::get('gps-tracks/dashboard', [GpsTrackController::class, 'dashboard']);
    Route::get('buses/{id}/gps/latest', [GpsTrackController::class, 'latestByBus']);
    Route::get('buses/{id}/gps', [GpsTrackController::class, 'history']);

    // Offline queue management
    Route::post('gps/process-offline-queue', [GpsTrackController::class, 'processOfflineQueue']);

    // attendance
    Route::get('attendance', [AttendanceController::class, 'index']);
    Route::get('attendance/{id}', [AttendanceController::class, 'show']);
    Route::delete('attendance/{id}', [AttendanceController::class, 'destroy']);
    Route::get('buses/{id}/attendance/today', [AttendanceController::class, 'byBusToday']);
    Route::get('students/{id}/attendance/today', [AttendanceController::class, 'studentTodayAttendance']);

    // daily reports
    Route::get('daily-reports', [DailyReportController::class, 'index']);
    Route::get('daily-reports/{id}', [DailyReportController::class, 'show']);
    Route::post('daily-reports', [DailyReportController::class, 'store']);
    Route::put('daily-reports/{id}', [DailyReportController::class, 'update']);
    Route::delete('daily-reports/{id}', [DailyReportController::class, 'destroy']);
    Route::get('reports/generate', [DailyReportController::class, 'generateAll']);

    // Activity logs - Security audit trail
    Route::prefix('activity')->group(function () {
        Route::get('logs', [ActivityController::class, 'index']);
        Route::get('dashboard', [ActivityController::class, 'dashboard']);
        Route::get('users/{userId}', [ActivityController::class, 'userActivity']);
        Route::get('export', [ActivityController::class, 'export']);
        Route::delete('cleanup', [ActivityController::class, 'cleanup']);
    });

    // Laporan Harian
    Route::prefix('reports')->group(function () {
        Route::get('admin', [ReportController::class, 'getAdminReport']);
        Route::match(['get','post'], 'admin/download-pdf', [ReportController::class, 'downloadAdminReportPDF']);
        Route::match(['get','post'], 'admin/download-excel', [ReportController::class, 'downloadAdminReportExcel']);
    });
});

Route::middleware('auth:api')->prefix('reports')->group(function () {
    Route::match(['get','post'], 'driver', [ReportController::class, 'getDriverReport']);
    Route::match(['get','post'], 'driver/download-pdf', [ReportController::class, 'downloadDriverReportPDF']);
    Route::match(['get','post'], 'driver/download-excel', [ReportController::class, 'downloadDriverReportExcel']);
});

// --- DRIVER ONLY ---
Route::middleware(['auth:api', 'driver'])->prefix('driver')->group(function () {
    // Driver profile & buses
    Route::get('profile', [DriverController::class, 'meDriver']);
    Route::get('buses', [DriverController::class, 'myBuses']);

    // GPS tracking
    Route::post('gps', [GpsTrackController::class, 'storeByDriver']);
    Route::patch('gps', [DriverController::class, 'toggleGpsStatus']);

    // Offline support - sync data kapan koneksi kembali
    Route::get('gps/offline-queue', [GpsTrackController::class, 'getOfflineQueue']);
    Route::get('gps/pending-syncs', [GpsTrackController::class, 'getPendingSyncs']);
    Route::post('gps/confirm-sync', [GpsTrackController::class, 'confirmSync']);
    Route::post('gps/log-status', [GpsTrackController::class, 'logGpsStatus']);
    Route::get('gps/status', [GpsTrackController::class, 'getGpsStatus']);

    // Attendance - Scan siswa masuk & keluar dari bus
    Route::post('attendance/scan', [AttendanceController::class, 'scan']);
    Route::put('attendance/checkout', [AttendanceController::class, 'checkOut']);

    // Laporan Perjalanan Harian
    Route::get('report', [ReportController::class, 'getDriverReport']);
    Route::get('report/download-pdf', [ReportController::class, 'downloadDriverReportPDF']);
    Route::get('report/download-excel', [ReportController::class, 'downloadDriverReportExcel']);

    // Daily report
    Route::get('buses/{busId}/report', [DriverController::class, 'dailyReport']);
});


// --- STUDENT ONLY ---
Route::middleware(['auth:api', 'student'])->prefix('student')->group(function () {
    // Student profile & bus info
    Route::get('profile', [StudentController::class, 'meStudent']);
    Route::post('barcode', [StudentController::class, 'myBarcode']);
    Route::get('bus', [StudentController::class, 'myBus']);
    Route::get('bus/tracking', [StudentController::class, 'getBusTracking']);
});

Route::get('routes/{id}/haltes', [RouteHalteController::class, 'getHaltesByRoute']);