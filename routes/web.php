<?php

use App\Http\Controllers\HealthController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\Web\Admin\AdminAuthController;
use App\Http\Controllers\Web\Admin\AnalyticsController as AdminAnalyticsController;
use App\Http\Controllers\Web\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Web\Admin\CityController as AdminCityController;
use App\Http\Controllers\Web\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Web\Admin\ImpersonationController;
use App\Http\Controllers\Web\Admin\PackageController as AdminPackageController;
use App\Http\Controllers\Web\Admin\SettingController as AdminSettingController;
use App\Http\Controllers\Web\Admin\UserController as AdminUserController;
use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\BookingController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\EventController;
use App\Http\Controllers\Web\OnboardingController;
use App\Http\Controllers\Web\ProfileController;
use App\Http\Controllers\Web\ReviewController;
use App\Http\Controllers\Web\WhatsAppController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Liveness / readiness probes (public, no auth)
Route::get('/health/live', [HealthController::class, 'live'])->name('health.live');
Route::get('/health/ready', [HealthController::class, 'ready'])->name('health.ready');

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

// Ticket Booking — seamless: guests can buy without an account.
// Transaction page: enter attendee names before checkout
Route::get('/events/{event}/book', [BookingController::class, 'create'])
    ->middleware('throttle:20,1')
    ->name('bookings.create');
Route::post('/events/{event}/book', [BookingController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('bookings.store');

// Checkout & receipt: owner (policy) OR signed URL emailed to the buyer
Route::get('/bookings/{booking}', [BookingController::class, 'show'])
    ->middleware('booking.access:view')
    ->name('bookings.show');
Route::get('/bookings/{booking}/pay', [BookingController::class, 'pay'])
    ->middleware('booking.access:pay')
    ->name('bookings.pay');
Route::post('/bookings/{booking}/pay', [BookingController::class, 'processPayment'])
    ->middleware(['booking.access:pay', 'throttle:10,1'])
    ->name('bookings.pay.store');

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
        Route::get('/dashboard/events/{event}/analytics', [EventController::class, 'analytics'])->name('events.analytics');
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

        // Ticket type management (event-scoped, owner/admin via Form Request policy)
        Route::post('/dashboard/events/{event}/tickets', [TicketController::class, 'store'])
            ->middleware('throttle:10,1')
            ->name('events.tickets.store');
        Route::patch('/dashboard/events/{event}/tickets/{ticket}', [TicketController::class, 'update'])
            ->middleware('throttle:10,1')
            ->name('events.tickets.update');
        Route::delete('/dashboard/events/{event}/tickets/{ticket}', [TicketController::class, 'destroy'])
            ->middleware('throttle:10,1')
            ->name('events.tickets.destroy');

        // WhatsApp quota (organizer/admin): top up ticket-delivery balance
        Route::get('/dashboard/whatsapp', [WhatsAppController::class, 'index'])->name('whatsapp.index');
        Route::post('/dashboard/whatsapp/topup', [WhatsAppController::class, 'topUp'])
            ->middleware('throttle:10,1')
            ->name('whatsapp.topup');
    });

    // Leave impersonation (available to the impersonated user, not admin-gated).
    Route::post('/impersonation/stop', [ImpersonationController::class, 'stop'])->name('impersonation.stop');
});

/*
|--------------------------------------------------------------------------
| Admin Panel
|--------------------------------------------------------------------------
| Admin login is password + OTP (see AdminAuthController). All panel routes
| require the `admin` role.
*/
Route::prefix('admin')->group(function () {
    // No `guest` middleware: an authenticated organizer must be able to reach
    // the admin login and switch into the (separate) admin session.
    Route::get('/login', [AdminAuthController::class, 'showLogin'])->name('admin.login');
    Route::post('/login', [AdminAuthController::class, 'login'])
        ->middleware('throttle:5,1')
        ->name('admin.login.store');
    Route::get('/otp', [AdminAuthController::class, 'showOtp'])->name('admin.otp');
    Route::post('/otp', [AdminAuthController::class, 'verifyOtp'])
        ->middleware('throttle:5,1')
        ->name('admin.otp.verify');
    Route::post('/otp/resend', [AdminAuthController::class, 'resendOtp'])
        ->middleware('throttle:5,1')
        ->name('admin.otp.resend');
});

Route::middleware(['auth', 'admin'])->prefix('admin')->group(function () {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('admin.dashboard');
    Route::get('/analytics', [AdminAnalyticsController::class, 'index'])->name('admin.analytics');
    Route::get('/users', [AdminUserController::class, 'index'])->name('admin.users');
    Route::post('/users/{user}/suspend', [AdminUserController::class, 'suspend'])->name('admin.users.suspend');
    Route::post('/users/{user}/activate', [AdminUserController::class, 'activate'])->name('admin.users.activate');
    Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])->name('admin.users.destroy');
    Route::post('/impersonate/{user}', [ImpersonationController::class, 'start'])->name('admin.impersonate');
    Route::post('/logout', [AdminAuthController::class, 'logout'])->name('admin.logout');

    // Settings
    Route::get('/settings', [AdminSettingController::class, 'edit'])->name('admin.settings');
    Route::put('/settings/general', [AdminSettingController::class, 'updateGeneral'])->name('admin.settings.general');
    Route::put('/settings/payment-gateway', [AdminSettingController::class, 'updatePaymentGateway'])->name('admin.settings.payment');
    Route::put('/settings/whatsapp', [AdminSettingController::class, 'updateWhatsApp'])->name('admin.settings.whatsapp');
    Route::put('/settings/platform-fee', [AdminSettingController::class, 'updatePlatformFee'])->name('admin.settings.fee');

    // Categories
    Route::get('/categories', [AdminCategoryController::class, 'index'])->name('admin.categories');
    Route::post('/categories', [AdminCategoryController::class, 'store'])->name('admin.categories.store');
    Route::put('/categories/{category}', [AdminCategoryController::class, 'update'])->name('admin.categories.update');
    Route::delete('/categories/{category}', [AdminCategoryController::class, 'destroy'])->name('admin.categories.destroy');

    // Cities
    Route::get('/cities', [AdminCityController::class, 'index'])->name('admin.cities');
    Route::post('/cities', [AdminCityController::class, 'store'])->name('admin.cities.store');
    Route::put('/cities/{city}', [AdminCityController::class, 'update'])->name('admin.cities.update');
    Route::delete('/cities/{city}', [AdminCityController::class, 'destroy'])->name('admin.cities.destroy');

    // WhatsApp packages
    Route::get('/packages', [AdminPackageController::class, 'index'])->name('admin.packages');
    Route::post('/packages', [AdminPackageController::class, 'store'])->name('admin.packages.store');
    Route::put('/packages/{package}', [AdminPackageController::class, 'update'])->name('admin.packages.update');
    Route::delete('/packages/{package}', [AdminPackageController::class, 'destroy'])->name('admin.packages.destroy');
});
