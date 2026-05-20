<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\UserController;
use App\Http\Controllers\API\DriverController;
use App\Http\Controllers\API\StudentController;
use App\Http\Controllers\API\AdminController;
use App\Http\Controllers\API\BusController;
use App\Http\Controllers\API\HalteController;
use App\Http\Controllers\API\BusDriverController;
use App\Http\Controllers\API\RouteController;
use App\Http\Controllers\API\RouteHalteController;
use App\Http\Controllers\API\StudentBusController;
use App\Http\Controllers\API\GpsTrackController;
use App\Http\Controllers\API\AttendanceController;
use App\Http\Controllers\API\DailyReportController;
use App\Http\Controllers\API\ReportController;
use App\Http\Controllers\API\ActivityController;

Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:3,1');
    Route::post('check-approval', [AuthController::class, 'checkApproval'])->middleware('throttle:30,1');
    Route::middleware(['auth:api', 'check.token.expiration'])->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
        Route::post('change-password', [AuthController::class, 'changePassword']);
        Route::put('profile', [AuthController::class, 'updateProfile']);
        Route::post('profile/photo', [AuthController::class, 'uploadPhoto']);
        Route::delete('profile/photo', [AuthController::class, 'deletePhoto']);
    });
});

Route::middleware('auth:api')->group(function () {
    Route::get('buses/{busId}/route', [RouteController::class, 'getByBus']);

    Route::get('routes/{id}/haltes', [RouteHalteController::class, 'getHaltesByRoute']);

    Route::prefix('reports')->group(function () {
        Route::match(['get', 'post'], 'driver', [ReportController::class, 'getDriverReport']);
        Route::match(['get', 'post'], 'driver/download-pdf', [ReportController::class, 'downloadDriverReportPDF']);
        Route::match(['get', 'post'], 'driver/download-excel', [ReportController::class, 'downloadDriverReportExcel']);
    });

    Route::post('daily-reports', [DailyReportController::class, 'store']);
});

Route::middleware(['auth:api', 'admin'])->group(function () {

    Route::get('admins', [AdminController::class, 'index']);
    Route::get('admins/{id}', [AdminController::class, 'show']);
    Route::post('admins', [AdminController::class, 'store']);
    Route::put('admins/{id}', [AdminController::class, 'update']);
    Route::delete('admins/{id}', [AdminController::class, 'destroy']);

    Route::get('users', [UserController::class, 'index']);
    Route::get('users/{id}', [UserController::class, 'show']);
    Route::delete('users/{id}', [UserController::class, 'destroy']);

    Route::get('drivers', [DriverController::class, 'index']);
    Route::get('drivers/{id}', [DriverController::class, 'show']);
    Route::post('drivers', [DriverController::class, 'store']);
    Route::put('drivers/{id}', [DriverController::class, 'update']);
    Route::delete('drivers/{id}', [DriverController::class, 'destroy']);
    Route::get('drivers/{id}/history', [DriverController::class, 'history']);

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

    Route::get('buses', [BusController::class, 'index']);
    Route::get('buses/{id}', [BusController::class, 'show']);
    Route::post('buses', [BusController::class, 'store']);
    Route::put('buses/{id}', [BusController::class, 'update']);
    Route::delete('buses/{id}', [BusController::class, 'destroy']);
    Route::post('buses/{id}/photo', [BusController::class, 'uploadPhoto']);
    Route::get('buses/{id}/students', [BusController::class, 'students']);
    Route::post('buses/{id}/students', [StudentBusController::class, 'assignStudentToBus']);
    Route::put('buses/{id}/students/{studentId}', [StudentBusController::class, 'update']);
    Route::delete('buses/{id}/students/{studentId}', [StudentBusController::class, 'destroy']);
    Route::get('buses/{id}/drivers', [BusController::class, 'drivers']);
    Route::get('buses/{id}/driver', [BusController::class, 'activeDriver']);
    Route::post('buses/{id}/drivers', [BusController::class, 'assignDriver']);

    Route::get('haltes', [HalteController::class, 'index']);
    Route::get('haltes/{id}', [HalteController::class, 'show']);
    Route::post('haltes', [HalteController::class, 'store']);
    Route::put('haltes/{id}', [HalteController::class, 'update']);
    Route::delete('haltes/{id}', [HalteController::class, 'destroy']);

    Route::get('routes', [RouteController::class, 'index']);
    Route::post('routes', [RouteController::class, 'store']);
    Route::get('routes/{id}', [RouteController::class, 'show']);
    Route::put('routes/{id}', [RouteController::class, 'update']);
    Route::delete('routes/{id}', [RouteController::class, 'destroy']);

    Route::post('routes/{id}/sync', [RouteController::class, 'syncRoute']);

    Route::post('routes/{id}/haltes', [RouteHalteController::class, 'storeHalteToRoute']);
    Route::put('route-haltes/{id}', [RouteHalteController::class, 'update']);
    Route::delete('route-haltes/{id}', [RouteHalteController::class, 'destroy']);

    Route::get('bus-driver', [BusDriverController::class, 'index']);
    Route::post('bus-driver', [BusDriverController::class, 'store']);
    Route::put('bus-driver/{id}', [BusDriverController::class, 'update']);
    Route::delete('bus-driver/{id}', [BusDriverController::class, 'destroy']);

    Route::get('student-bus', [StudentBusController::class, 'index']);

    Route::get('gps-tracks', [GpsTrackController::class, 'index']);
    Route::get('gps-tracks/latest', [GpsTrackController::class, 'latest']);
    Route::get('gps-tracks/dashboard', [GpsTrackController::class, 'dashboard']);
    Route::get('gps-tracks/stream', [GpsTrackController::class, 'stream']);
    Route::get('buses/{id}/gps/latest', [GpsTrackController::class, 'latestByBus']);
    Route::get('buses/{id}/gps', [GpsTrackController::class, 'history']);
    Route::post('gps/process-offline-queue', [GpsTrackController::class, 'processOfflineQueue']);

    Route::get('attendance', [AttendanceController::class, 'index']);
    Route::get('attendance/{id}', [AttendanceController::class, 'show']);
    Route::delete('attendance/{id}', [AttendanceController::class, 'destroy']);
    Route::get('buses/{id}/attendance/today', [AttendanceController::class, 'byBusToday']);
    Route::get('students/{id}/attendance/today', [AttendanceController::class, 'studentTodayAttendance']);

    Route::get('daily-reports', [DailyReportController::class, 'index']);
    Route::get('daily-reports/{id}', [DailyReportController::class, 'show']);
    Route::put('daily-reports/{id}', [DailyReportController::class, 'update']);
    Route::delete('daily-reports/{id}', [DailyReportController::class, 'destroy']);
    Route::get('reports/generate', [DailyReportController::class, 'generateAll']);

    Route::prefix('activity')->group(function () {
        Route::get('logs', [ActivityController::class, 'index']);
        Route::get('dashboard', [ActivityController::class, 'dashboard']);
        Route::get('users/{userId}', [ActivityController::class, 'userActivity']);
        Route::get('export', [ActivityController::class, 'export']);
        Route::delete('cleanup', [ActivityController::class, 'cleanup']);
    });

    Route::prefix('reports')->group(function () {
        Route::get('admin', [ReportController::class, 'getAdminReport']);
        Route::match(['get', 'post'], 'admin/download-pdf', [ReportController::class, 'downloadAdminReportPDF']);
        Route::match(['get', 'post'], 'admin/download-excel', [ReportController::class, 'downloadAdminReportExcel']);
    });
});

Route::middleware(['auth:api', 'driver'])->prefix('driver')->group(function () {
    Route::get('profile', [DriverController::class, 'meDriver']);
    Route::get('buses', [DriverController::class, 'myBuses']);
    Route::post('gps', [GpsTrackController::class, 'storeByDriver']);
    Route::patch('gps', [DriverController::class, 'toggleGpsStatus']);
    Route::get('gps/offline-queue', [GpsTrackController::class, 'getOfflineQueue']);
    Route::get('gps/pending-syncs', [GpsTrackController::class, 'getPendingSyncs']);
    Route::post('gps/confirm-sync', [GpsTrackController::class, 'confirmSync']);
    Route::post('gps/log-status', [GpsTrackController::class, 'logGpsStatus']);
    Route::get('gps/status', [GpsTrackController::class, 'getGpsStatus']);
    Route::post('attendance/scan', [AttendanceController::class, 'scan']);
    Route::put('attendance/checkout', [AttendanceController::class, 'checkOut']);
    Route::get('buses/{busId}/report', [DriverController::class, 'dailyReport']);
    Route::get('buses/{busId}/students', [DriverController::class, 'myBusStudents']);
    Route::get('buses/{busId}/attendance/today', [AttendanceController::class, 'byBusToday']);
});

Route::middleware(['auth:api', 'student'])->prefix('student')->group(function () {
    Route::get('profile', [StudentController::class, 'meStudent']);
    Route::post('barcode', [StudentController::class, 'myBarcode']);
    Route::get('bus', [StudentController::class, 'myBus']);
    Route::get('bus/tracking', [StudentController::class, 'getBusTracking']);
    Route::get('attendance/today', [AttendanceController::class, 'myAttendanceToday']);
});