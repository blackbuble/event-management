<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;

Route::prefix('v1')->group(function () {
    // Public Routes
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']); // Email + Password
    
    // OTP Routes
    Route::post('/otp/generate', [AuthController::class, 'generateOtp']);
    Route::post('/otp/login', [AuthController::class, 'loginWithOtp']);

    // Protected Routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        
        // Example User Management Routes (Admin only via Policy/Middleware)
        // Route::apiResource('users', UserController::class); // Implement UserController if needed
    });
});
