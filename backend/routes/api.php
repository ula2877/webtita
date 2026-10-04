<?php

use App\Http\Controllers\Api\Admin\ArrearController;
use App\Http\Controllers\Api\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Api\Admin\ExportController;
use App\Http\Controllers\Api\Admin\ImportController;
use App\Http\Controllers\Api\Admin\PeriodController;
use App\Http\Controllers\Api\Admin\PetugasController;
use App\Http\Controllers\Api\Admin\PetugasImportController;
use App\Http\Controllers\Api\Admin\WilayahController;
use App\Http\Controllers\Api\Admin\WilayahImportController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\Petugas\DashboardController as PetugasDashboardController;
use App\Http\Controllers\Api\Petugas\PelangganController;
use App\Http\Controllers\Api\Petugas\TaskController;
use App\Http\Controllers\Api\Petugas\WilayahController as PetugasWilayahController;
use App\Http\Controllers\Api\PhotoController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);
Route::get('/login-options', [AuthController::class, 'loginOptions']);
Route::get('/health', [HealthController::class, 'check']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);
    Route::put('/profile/password', [AuthController::class, 'updatePassword']);
    Route::get('/photos/{path}', [PhotoController::class, 'show'])->where('path', '.*');

    // Daftar periode tersedia untuk admin maupun petugas (selector periode).
    Route::get('/periods', [PeriodController::class, 'index']);

    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index']);
        Route::get('/dashboard/petugas/{petugas}/review', [AdminDashboardController::class, 'petugasReview']);
        Route::get('/periods', [PeriodController::class, 'index']);

        Route::post('/import', [ImportController::class, 'store']);
        Route::post('/import/petugas', [PetugasImportController::class, 'store']);
        Route::post('/import/wilayah', [WilayahImportController::class, 'store']);
        Route::get('/imports', [ImportController::class, 'index']);
        Route::get('/imports/{importLog}', [ImportController::class, 'show']);

        Route::get('/wilayah', [WilayahController::class, 'index']);

        Route::get('/arrears', [ArrearController::class, 'index']);
        Route::delete('/arrears/bulk', [ArrearController::class, 'destroyBulk']);
        Route::get('/arrears/{arrear}', [ArrearController::class, 'show']);
        Route::put('/arrears/{arrear}', [ArrearController::class, 'update']);
        Route::delete('/arrears/{arrear}', [ArrearController::class, 'destroy']);
        Route::post('/arrears/assign', [ArrearController::class, 'assign']);

        Route::get('/petugas', [PetugasController::class, 'index']);
        Route::post('/petugas', [PetugasController::class, 'store']);
        Route::put('/petugas/{petugas}', [PetugasController::class, 'update']);
        Route::patch('/petugas/{petugas}/status', [PetugasController::class, 'toggleStatus']);

        Route::get('/export', [ExportController::class, 'download']);
    });

    Route::middleware('role:petugas')->prefix('petugas')->group(function () {
        Route::get('/dashboard', [PetugasDashboardController::class, 'index']);
        Route::get('/wilayah', [PetugasWilayahController::class, 'index']);
        Route::get('/pelanggan', [PelangganController::class, 'index']);
        Route::get('/tasks', [TaskController::class, 'index']);
        Route::get('/tasks/{arrear}', [TaskController::class, 'show']);
        Route::post('/tasks/{arrear}/visit', [TaskController::class, 'visit']);
    });
});
