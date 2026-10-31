<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\SyncController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

/*
| API routes are always registered. Feature endpoints abort when their kit flag
| is off so toggles work without redeploy / route:cache.
*/

Route::prefix('auth')->group(function (): void {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('api.auth.login');
    Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum')->name('api.auth.logout');
    Route::get('me', [AuthController::class, 'me'])->middleware('auth:sanctum')->name('api.auth.me');
});

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/me', function (\Illuminate\Http\Request $request) {
        return response()->json([
            'user' => $request->user()?->only(['id', 'name', 'email']),
        ]);
    })->name('api.me');

    Route::get('/users', [UserController::class, 'index'])->name('api.users.index');

    Route::prefix('sync')->name('api.sync.')->group(function (): void {
        Route::post('batch', [SyncController::class, 'batch'])->name('batch');
        Route::post('attachments', [SyncController::class, 'uploadAttachment'])->name('attachments');
        Route::get('pull', [SyncController::class, 'pull'])->name('pull');
        Route::get('warm', [SyncController::class, 'warm'])->name('warm');
        Route::get('status', [SyncController::class, 'status'])->name('status');
        Route::post('metrics', [SyncController::class, 'metrics'])->name('metrics');
        Route::post('token', [SyncController::class, 'issueToken'])->name('token');
        Route::delete('devices/{deviceId}', [SyncController::class, 'revokeDevice'])->name('devices.revoke');
    });
});

/*
| Oscar mobile / ordering API (from OscarWebsite).
| Mounted under /api so paths match the original app (e.g. /api/login, /api/food-menu).
*/
require __DIR__.'/oscar_api.php';
