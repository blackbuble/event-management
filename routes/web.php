<?php

use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\BookingController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\EventController;
use App\Http\Controllers\Web\OnboardingController;
use App\Http\Controllers\Web\ProfileController;
use App\Http\Controllers\Web\ReviewController;
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
    Route::get('/register', function () {
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

// Public Event Landing
Route::get('/events/{slug}', [EventController::class, 'show'])->name('events.show');

// Ticket Booking (authenticated attendees)
Route::post('/events/{event}/book', [BookingController::class, 'store'])
    ->middleware(['auth', 'throttle:10,1'])
    ->name('bookings.store');

// Event Reviews (authenticated attendees, eligibility enforced in service)
Route::post('/events/{event}/reviews', [ReviewController::class, 'store'])
    ->middleware(['auth', 'throttle:10,1'])
    ->name('events.reviews.store');

// Protected Routes (Dashboard & Profile)
// Note: `verified` middleware intentionally omitted — User does not implement
// MustVerifyEmail (unified OTP/magic-link auth), so it would be inert dead config.
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->middleware('profile.complete')
        ->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/onboarding', [OnboardingController::class, 'show'])->name('onboarding.show');
    Route::patch('/onboarding', [OnboardingController::class, 'update'])->name('onboarding.update');

    // Event Management (organizer/admin only, enforced via EventPolicy)
    Route::middleware(['profile.complete'])->group(function () {
        Route::get('/dashboard/events', [EventController::class, 'index'])->name('events.index');
        Route::get('/dashboard/events/create', [EventController::class, 'create'])->name('events.create');
        Route::post('/dashboard/events', [EventController::class, 'store'])
            ->middleware('throttle:10,1')
            ->name('events.store');
        Route::get('/dashboard/events/{event}/edit', [EventController::class, 'edit'])->name('events.edit');
        Route::patch('/dashboard/events/{event}', [EventController::class, 'update'])
            ->middleware('throttle:10,1')
            ->name('events.update');
        Route::patch('/dashboard/events/{event}/publish', [EventController::class, 'publish'])
            ->middleware('throttle:10,1')
            ->name('events.publish');
        Route::patch('/dashboard/events/{event}/cancel', [EventController::class, 'cancel'])
            ->middleware('throttle:10,1')
            ->name('events.cancel');
        Route::delete('/dashboard/events/{event}', [EventController::class, 'destroy'])
            ->middleware('throttle:10,1')
            ->name('events.destroy');
        Route::patch('/dashboard/events/{event}/meeting-link', [EventController::class, 'updateMeetingLink'])
            ->middleware('throttle:10,1')
            ->name('events.meeting-link.update');
        Route::post('/dashboard/events/{event}/meeting-link/send', [EventController::class, 'sendMeetingLink'])
            ->middleware('throttle:5,1')
            ->name('events.meeting-link.send');
    });
});
