<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            return redirect()->route('admin.login');
        }

        if (! $request->user()->hasRole('admin')) {
            // Friendlier than a raw 403: non-admins are pointed at the admin
            // login (they may be signed in as an organizer in this session).
            return redirect()->route('admin.login')
                ->with('error', 'Akun ini tidak memiliki akses admin. Silakan masuk dengan akun admin.');
        }

        return $next($request);
    }
}
