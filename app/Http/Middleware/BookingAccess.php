<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Allows booking access either through the policy (authenticated owner) or a
 * valid signed URL (seamless guest checkout — the signature is emailed to the
 * buyer's contact address, so no account/login is required to buy or pay).
 */
class BookingAccess
{
    public function handle(Request $request, Closure $next, string $ability = 'view'): Response
    {
        $booking = $request->route('booking');

        if ($booking !== null && $request->user() && $request->user()->can($ability, $booking)) {
            return $next($request);
        }

        if ($request->hasValidSignature()) {
            return $next($request);
        }

        abort(403);
    }
}
