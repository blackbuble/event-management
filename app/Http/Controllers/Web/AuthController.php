<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\AuthService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    protected $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * Show unified login/register entry point
     */
    public function showLogin()
    {
        return Inertia::render('Auth/Login');
    }

    /**
     * Unified authentication entry point (Email or WhatsApp)
     */
    public function authenticate(Request $request)
    {
        $request->validate([
            'identity' => 'required|string',
        ]);
        
        $identity = $request->identity;
        $isEmail = filter_var($identity, FILTER_VALIDATE_EMAIL);

        // EXTRA SECURITY: Identity-based rate limiting (per phone/email)
        $throttleKey = 'auth_gate_' . Str::slug($identity);
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return back()->withErrors([
                'identity' => "Terlalu banyak percobaan. Silakan coba lagi dalam {$seconds} detik."
            ]);
        }
        RateLimiter::hit($throttleKey, 60);

        // Find or create user
        $user = $this->authService->findOrCreateByIdentity($identity);

        if ($isEmail) {
            $this->authService->generateMagicLink($user->email);
            return back()->with('message', 'Tautan login telah dikirim ke email Anda.');
        } else {
            // Store identity in session for security
            $request->session()->put('auth_identity', $identity);
            
            // For phone, generate OTP
            $this->authService->generateOtp($user->email ?? $user->phone);
            return redirect()->route('otp.login')->with('message', 'Kode verifikasi telah dikirim ke WhatsApp Anda.');
        }
    }

    /**
     * Show OTP verification page
     */
    public function showOtp(Request $request)
    {
        $identity = $request->session()->get('auth_identity');

        if (!$identity) {
            return redirect()->route('login')->withErrors(['identity' => 'Sesi verifikasi telah berakhir. Silakan masuk kembali.']);
        }

        return Inertia::render('Auth/VerifyOtp', [
            'identity' => $identity
        ]);
    }

    /**
     * Verify OTP and log user in
     */
    public function otpLogin(Request $request)
    {
        $identity = $request->session()->get('auth_identity');

        if (!$identity) {
            return redirect()->route('login')->withErrors(['identity' => 'Sesi verifikasi telah berakhir. Silakan masuk kembali.']);
        }

        $request->validate([
            'otp' => 'required|string|size:6',
        ]);

        try {
            $result = $this->authService->loginWithOtp($identity, $request->otp);
            
            Auth::login($result['user']);
            $request->session()->forget('auth_identity');
            $request->session()->regenerate();

            return redirect()->intended('/dashboard');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return back()->withErrors(['otp' => $e->getMessage()]);
        }
    }

    /**
     * Verify Magic Link token and log user in
     */
    public function authViaMagicLink(Request $request)
    {
        $request->validate(['token' => 'required|string']);

        try {
            $result = $this->authService->validateMagicLink($request->token);
            
            Auth::login($result['user']);
            $request->session()->regenerate();

            return redirect()->intended('/dashboard');
        } catch (\Exception $e) {
            return redirect()->route('login')->withErrors(['email' => $e->getMessage()]);
        }
    }

    /**
     * Social Authentication
     */
    public function redirectToProvider($provider)
    {
        return \Laravel\Socialite\Facades\Socialite::driver($provider)->redirect();
    }

    public function handleProviderCallback($provider)
    {
        try {
            $socialUser = \Laravel\Socialite\Facades\Socialite::driver($provider)->user();
            
            $result = $this->authService->socialLogin([
                'name' => $socialUser->getName(),
                'email' => $socialUser->getEmail(),
                'social_id' => $socialUser->getId(),
                'social_type' => $provider,
                'social_avatar' => $socialUser->getAvatar(),
            ]);

            Auth::login($result['user']);
            request()->session()->regenerate();

            return redirect()->intended('/dashboard');

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Social Login Error ({$provider}): " . $e->getMessage());
            return redirect()->route('login')->withErrors([
                'email' => "Gagal masuk menggunakan {$provider}. Silakan coba lagi.",
            ]);
        }
    }

    /**
     * Terminate session
     */
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/');
    }
}
