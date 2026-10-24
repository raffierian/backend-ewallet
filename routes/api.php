<?php

use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\FamilyController;
use App\Http\Controllers\Api\V1\TransactionController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::prefix('v1')->group(function () {
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::get('/test-headers', function (\Illuminate\Http\Request $request) {
        return response()->json([
            'headers' => $request->headers->all(),
            'bearer_token' => $request->bearerToken(),
            'has_auth_header' => $request->hasHeader('Authorization'),
            'server_vars' => collect($_SERVER)->only([
                'HTTP_AUTHORIZATION',
                'REDIRECT_HTTP_AUTHORIZATION',
                'Authorization',
                'HTTP_X_AUTHORIZATION',
                'REDIRECT_HTTP_X_AUTHORIZATION'
            ])->toArray(),
        ]);
    });
    
    // Temporary route to run migrations on production
    Route::get('/migrate-db', function () {
        try {
            \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
            return response()->json([
                'success' => true,
                'message' => 'Database migrated successfully',
                'output' => \Illuminate\Support\Facades\Artisan::output()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    });
});

// Protected routes
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::put('/me', [AuthController::class, 'updateProfile']);
    Route::post('/me/pin', [AuthController::class, 'setPin']);

    // Families
    Route::apiResource('families', FamilyController::class);
    Route::post('/families/{family}/invite', [FamilyController::class, 'inviteMember']);
    Route::delete('/families/{family}/members/{userId}', [FamilyController::class, 'removeMember']);

    // Accounts
    Route::apiResource('accounts', AccountController::class);
    Route::post('/accounts/transfer', [AccountController::class, 'transfer']);

    // Categories
    Route::apiResource('categories', CategoryController::class);

    // Transactions
    Route::apiResource('transactions', TransactionController::class);

    // Dashboard
    Route::get('/dashboard/summary', [DashboardController::class, 'summary']);
});
