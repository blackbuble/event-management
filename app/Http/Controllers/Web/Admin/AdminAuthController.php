<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin login is two-factor by policy: password first, then an OTP sent to the
 * admin's email. Only accounts with the `admin` role may complete the flow.
 */
class AdminAuthController extends Controller
{
    private const SESSION_KEY = 'admin_2fa_user_id';

    public function __construct(
        private readonly AuthService $authService,
    ) {}

    public function showLogin(Request $request): Response|RedirectResponse
    {
        if ($request->user()?->hasRole('admin')) {
            return redirect()->route('admin.dashboard');
        }

        return Inertia::render('Admin/Login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()->where('email', $credentials['email'])->first();

        if (! $user || ! $user->hasRole('admin') || ! Hash::check($credentials['password'], (string) $user->password)) {
            return back()->withErrors(['email' => 'Kredensial admin tidak valid.'])->onlyInput('email');
        }

        $request->session()->put(self::SESSION_KEY, $user->id);

        // Reuse the existing OTP pipeline (logged locally until a provider is set).
        $this->authService->generateOtp($user->email);

        return redirect()->route('admin.otp')->with('message', 'Kode OTP telah dikirim ke email admin.');
    }

    public function showOtp(Request $request): Response|RedirectResponse
    {
        $userId = $request->session()->get(self::SESSION_KEY);

        if (! $userId) {
            return redirect()->route('admin.login')->withErrors(['email' => 'Sesi verifikasi berakhir. Silakan masuk kembali.']);
        }

        $user = User::query()->find($userId);

        return Inertia::render('Admin/VerifyOtp', [
            'email' => $user?->email,
            'has_phone' => (bool) $user?->phone,
        ]);
    }

    /**
     * Resend the OTP through the requested channel (email or WhatsApp).
     * The code is always stored on the same admin user, so verification by
     * email keeps working regardless of the delivery channel chosen.
     */
    public function resendOtp(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'channel' => ['required', 'in:email,whatsapp'],
        ]);

        $userId = $request->session()->get(self::SESSION_KEY);
        $user = $userId ? User::query()->find($userId) : null;

        if (! $user || ! $user->hasRole('admin')) {
            return redirect()->route('admin.login')->withErrors(['email' => 'Sesi verifikasi berakhir. Silakan masuk kembali.']);
        }

        if ($data['channel'] === 'whatsapp') {
            if (! $user->phone) {
                return back()->with('error', 'Nomor WhatsApp tidak tersedia untuk akun admin ini.');
            }

            $this->authService->generateOtp($user->phone);

            return back()->with('message', 'Kode OTP baru dikirim via WhatsApp.');
        }

        $this->authService->generateOtp($user->email);

        return back()->with('message', 'Kode OTP baru dikirim ke email admin.');
    }

    public function verifyOtp(Request $request): RedirectResponse
    {
        $request->validate(['otp' => ['required', 'string', 'size:6']]);

        $userId = $request->session()->get(self::SESSION_KEY);
        $user = $userId ? User::query()->find($userId) : null;

        if (! $user || ! $user->hasRole('admin')) {
            return redirect()->route('admin.login')->withErrors(['email' => 'Sesi verifikasi berakhir. Silakan masuk kembali.']);
        }

        try {
            $result = $this->authService->loginWithOtp($user->email, $request->otp);
        } catch (\Exception $e) {
            return back()->withErrors(['otp' => $e->getMessage()]);
        }

        if ((int) $result['user']->id !== (int) $user->id) {
            return redirect()->route('admin.login')->withErrors(['email' => 'Verifikasi tidak cocok.']);
        }

        Auth::login($user);
        $request->session()->forget(self::SESSION_KEY);
        $request->session()->regenerate();

        return redirect()->intended(route('admin.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
