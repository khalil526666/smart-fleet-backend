<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AlertController;
use App\Http\Controllers\Api\MaintenancePredictionController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DriverController;
use App\Http\Controllers\Api\EmailLogController;
use App\Http\Controllers\Api\FleetAlertController;
use App\Http\Controllers\Api\FleetLiveController;
use App\Http\Controllers\Api\FuelRecordController;
use App\Http\Controllers\Api\IncidentController;
use App\Http\Controllers\Api\MaintenanceController;
use App\Http\Controllers\Api\MissionController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OsrmProxyController;
use App\Http\Controllers\Api\PusherAuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\VehicleController;
use Illuminate\Support\Facades\Route;

// Some auth middlewares expect a named "login" route for guest redirects.
// This API returns JSON instead of redirecting to a web login page.
Route::get('/login', fn () => response()->json(['message' => 'Unauthenticated.'], 401))->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::put('/user/profile', [AuthController::class, 'updateProfile']);
    Route::post('/user/photo', [AuthController::class, 'updatePhoto']);
    Route::delete('/user/photo', [AuthController::class, 'deletePhoto']);
    Route::post('/user/cin-photo', [AuthController::class, 'uploadCinPhoto']);
    Route::put('/user/password', [AuthController::class, 'changePassword']);

    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::middleware('role:admin,gestionnaire')->get('/alerts', [AlertController::class, 'index']);
    Route::middleware('role:admin,gestionnaire')->group(function () {
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
        Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead']);
        Route::get('/email-logs', [EmailLogController::class, 'index']);
    });

    // Pusher private channel auth (admin/gestionnaire only)
    Route::post('/pusher/auth', [PusherAuthController::class, 'auth']);

    // Missions (role-based)
    Route::get('/missions', [MissionController::class, 'index']);
    Route::get('/missions/{mission}/trip-report', [MissionController::class, 'tripReport']);
    Route::get('/missions/{mission}', [MissionController::class, 'show']);
    Route::post('/missions', [MissionController::class, 'store']); // admin/gestionnaire only (enforced in controller)
    Route::post('/missions/{mission}/start', [MissionController::class, 'start']); // chauffeur (role user) only
    Route::post('/missions/{mission}/complete', [MissionController::class, 'complete']); // chauffeur (role user) only
    Route::get('/chauffeurs', [MissionController::class, 'chauffeurs']); // admin/gestionnaire only (enforced in controller)
    Route::post('/missions/{mission}/trajets', [MissionController::class, 'addTrajetPoint']); // chauffeur only

    // OSRM route proxy (same engine as Leaflet Routing Machine; auth required)
    Route::get('/osrm/route', [OsrmProxyController::class, 'drivingRoute']);

    Route::middleware('role:admin,gestionnaire')->group(function () {
        Route::get('/fleet/live', [FleetLiveController::class, 'index']);
        Route::get('/fleet/alerts', [FleetAlertController::class, 'index']);
    });

    // Incidents (chauffeur can report, admin/gestionnaire can too)
    Route::get('/incidents', [IncidentController::class, 'index']);
    Route::post('/incidents', [IncidentController::class, 'store']);

    // Vehicles: Admin/Gestionnaire only (chauffeur cannot access)
    Route::middleware('role:admin,gestionnaire')->group(function () {
        Route::get('/vehicles', [VehicleController::class, 'index']);
        Route::get('/vehicles/{vehicle}', [VehicleController::class, 'show']);
    });
    Route::middleware('role:admin')->group(function () {
        Route::post('/vehicles', [VehicleController::class, 'store']);
        Route::put('/vehicles/{vehicle}', [VehicleController::class, 'update']);
        Route::delete('/vehicles/{vehicle}', [VehicleController::class, 'destroy']);
    });

    // Drivers: admin + gestionnaire (CRUD); suppression réservée à l’admin
    Route::middleware('role:admin,gestionnaire')->group(function () {
        Route::get('/drivers', [DriverController::class, 'index']);
        Route::post('/drivers', [DriverController::class, 'store']);
        Route::get('/drivers/{driver}', [DriverController::class, 'show']);
        Route::put('/drivers/{driver}', [DriverController::class, 'update']);
        Route::patch('/drivers/{driver}', [DriverController::class, 'update']);
    });
    Route::middleware('role:admin')->delete('/drivers/{driver}', [DriverController::class, 'destroy']);

    // Maintenance: Admin/Gestionnaire only (chauffeur cannot access)
    Route::middleware('role:admin,gestionnaire')->group(function () {
        Route::get('/maintenances', [MaintenanceController::class, 'index']);
        Route::get('/maintenances/{maintenance}', [MaintenanceController::class, 'show']);
        Route::post('/maintenances', [MaintenanceController::class, 'store']);
    });
    Route::middleware('role:admin')->group(function () {
        Route::put('/maintenances/{maintenance}', [MaintenanceController::class, 'update']);
        Route::delete('/maintenances/{maintenance}', [MaintenanceController::class, 'destroy']);
    });

    // ML Predictions: Admin/Gestionnaire only
    Route::middleware('role:admin,gestionnaire')->group(function () {
        Route::get('/predictions/maintenance', [MaintenancePredictionController::class, 'index']);
        Route::post('/predictions/maintenance/{vehicle}/refresh', [MaintenancePredictionController::class, 'refresh']);
        Route::get('/predictions/maintenance/{vehicle}/history', [MaintenancePredictionController::class, 'history']);
    });

    // Fuel: Admin/Gestionnaire only (chauffeur cannot access)
    Route::middleware('role:admin,gestionnaire')->group(function () {
        Route::get('/fuel-records', [FuelRecordController::class, 'index']);
        Route::get('/fuel-records/{fuelRecord}', [FuelRecordController::class, 'show']);
        Route::post('/fuel-records', [FuelRecordController::class, 'store']);
    });
    Route::middleware('role:admin')->group(function () {
        Route::put('/fuel-records/{fuelRecord}', [FuelRecordController::class, 'update']);
        Route::delete('/fuel-records/{fuelRecord}', [FuelRecordController::class, 'destroy']);
    });

    // User management (admin-only)
    Route::middleware(['isAdmin'])->group(function () {
        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users/{id}/role', [UserController::class, 'setRole']);
        Route::post('/users/{id}/make-gestionnaire', [UserController::class, 'makeGestionnaire']);
        Route::post('/users/{id}/remove-gestionnaire', [UserController::class, 'removeGestionnaire']);
        Route::post('/users/{id}/make-chauffeur', [UserController::class, 'makeChauffeur']);
        Route::delete('/users/{id}', [UserController::class, 'destroy']);
    });
});
