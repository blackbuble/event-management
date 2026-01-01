<?php

use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\ProfileController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/language/{locale}', function ($locale) {
    if (in_array($locale, ['id', 'en'])) {
        session()->put('locale', $locale);
    }
    return back();
})->name('language.switch');

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
    })->middleware(['auth', 'verified', 'profile.complete'])->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/onboarding', [App\Http\Controllers\Web\OnboardingController::class, 'show'])->name('onboarding.show');
    Route::patch('/onboarding', [App\Http\Controllers\Web\OnboardingController::class, 'update'])->name('onboarding.update');

    Route::resource('organizations', \App\Http\Controllers\OrganizationController::class)->only(['create', 'store', 'show', 'edit', 'update']);
    Route::post('/organizations/{organization}/switch', [\App\Http\Controllers\OrganizationController::class, 'switch'])->name('organizations.switch');
    Route::post('/organizations/{organization}/members', [\App\Http\Controllers\OrganizationController::class, 'invite'])->name('organizations.members.invite');
});
