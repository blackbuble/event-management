<?php

use App\Http\Controllers\Web\AuthController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
});

// Authentication Routes (Unified Flow)
Route::middleware('guest')->group(function () {
    // Single gate for both Login and Register
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'authenticate'])
        ->middleware('throttle:5,1')
        ->name('auth.unified');
    
    // Alias for register to point to login
    Route::get('/register', function() {
        return redirect()->route('login');
    });

    // Verification Flow
    Route::get('/otp/login', [AuthController::class, 'showOtp'])->name('otp.login');
    Route::post('/otp/login', [AuthController::class, 'otpLogin'])
        ->middleware('throttle:5,1');
    Route::get('/auth/magic-link', [AuthController::class, 'authViaMagicLink'])->name('magic.verify');

    // Social Authentication
    Route::get('/auth/{provider}/redirect', [AuthController::class, 'redirectToProvider'])->name('social.redirect');
    Route::get('/auth/{provider}/callback', [AuthController::class, 'handleProviderCallback'])->name('social.callback');
});

Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Protected Routes (Dashboard & Profile)
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    Route::get('/profile', function () {
        return Inertia::render('Profile/Edit');
    })->name('profile.edit');
});
