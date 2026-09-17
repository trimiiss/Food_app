<?php

use App\Http\Controllers\Api\V1\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — version 1
|--------------------------------------------------------------------------
| Everything is served under /api/v1. Versioning from day one costs nothing
| and means a breaking change later can ship as /api/v2 without stranding
| existing clients.
|
|   public         -> anyone
|   auth:sanctum   -> any signed-in user (customer or admin)
|   admin          -> signed-in users with role=admin (401 if anonymous, 403 if customer)
*/

Route::prefix('v1')->group(function () {

    // ---- Authentication -------------------------------------------------
    // Throttled to slow down credential stuffing / brute force.
    Route::middleware('throttle:10,1')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('admin/register', [AuthController::class, 'registerAdmin']);
        Route::post('login', [AuthController::class, 'login']);
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });
});
