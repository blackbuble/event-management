<?php

use App\Http\Middleware\BookingAccess;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureProfileComplete;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust the front proxy/LB so `$request->ip()` reads X-Forwarded-For
        // (real client IP) instead of the proxy address.
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'profile.complete' => EnsureProfileComplete::class,
            'booking.access' => BookingAccess::class,
            'admin' => EnsureAdmin::class,
        ]);
        $middleware->web(append: [
            SetLocale::class,
            HandleInertiaRequests::class,
            EnsureAccountIsActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
