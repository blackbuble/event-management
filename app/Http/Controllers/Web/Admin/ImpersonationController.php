<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class ImpersonationController extends Controller
{
    /**
     * Admin logs in as an organizer to debug/support them.
     * Admins cannot impersonate other admins, nor suspended accounts.
     */
    public function start(Request $request, User $user): RedirectResponse
    {
        $admin = $request->user();

        $context = [
            'admin_id' => $admin?->id,
            'admin_email' => $admin?->email,
            'target_id' => $user->id,
            'target_email' => $user->email,
            'target_roles' => $user->getRoleNames()->all(),
            'target_suspended' => $user->isSuspended(),
            'session_id' => $request->session()->getId(),
            'ip' => $request->ip(),
            'path' => $request->path(),
        ];

        Log::info('impersonation.attempt', $context);

        // Re-authentication: the admin must confirm their own password before
        // taking over another account (defends against session hijacking).
        $request->validate(['password' => ['required', 'string']]);

        if (! Hash::check((string) $request->input('password'), (string) $admin->password)) {
            Log::warning('impersonation.bad_password', $context);

            return back()->withErrors(['password' => 'Password admin salah.'])->with('error', 'Password admin salah.');
        }

        if ($user->hasRole('admin')) {
            Log::warning('impersonation.blocked_admin', $context);

            return back()->with('error', 'Admin tidak dapat menyamar sebagai admin lain.');
        }

        if ($user->isSuspended()) {
            Log::warning('impersonation.blocked_suspended', $context);

            return back()->with('error', 'Akun yang ditangguhkan tidak dapat di-impersonate.');
        }

        $request->session()->put('impersonator_id', $admin->id);

        Auth::login($user);
        $request->session()->regenerate();

        Log::info('impersonation.started', $context + [
            'previous_user_id' => $admin->id,
            'new_user_id' => Auth::id(),
            'session_id' => $request->session()->getId(),
        ]);

        return redirect()->route('dashboard')
            ->with('message', "Menyamar sebagai {$user->name}.");
    }

    /**
     * Return to the original admin account.
     */
    public function stop(Request $request): RedirectResponse
    {
        $current = $request->user();
        $adminId = $request->session()->pull('impersonator_id');
        $admin = $adminId ? User::query()->find($adminId) : null;

        $context = [
            'acting_user_id' => $current?->id,
            'acting_user_email' => $current?->email,
            'impersonator_id' => $adminId,
            'session_id' => $request->session()->getId(),
            'ip' => $request->ip(),
        ];

        if (! $admin || ! $admin->hasRole('admin')) {
            Log::warning('impersonation.stop_failed', $context);

            return redirect()->route('dashboard');
        }

        Auth::login($admin);
        $request->session()->regenerate();

        Log::info('impersonation.stopped', $context + [
            'restored_admin_id' => $admin->id,
            'session_id' => $request->session()->getId(),
        ]);

        return redirect()->route('admin.dashboard')->with('message', 'Kembali ke akun admin.');
    }
}
