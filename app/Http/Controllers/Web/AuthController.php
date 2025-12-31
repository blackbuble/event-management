<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\AuthService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;

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
     * Handles both login and registration automatically.
     */
    public function authenticate(Request $request)
    {
        $request->validate([
            'identity' => 'required|string',
        ]);

        $identity = $request->identity;
        $isEmail = filter_var($identity, FILTER_VALIDATE_EMAIL);

        // Find or create user
        $user = $this->authService->findOrCreateByIdentity($identity);

        if ($isEmail) {
            $this->authService->generateMagicLink($user->email);
            return back()->with('message', 'Tautan login telah dikirim ke email Anda.');
        } else {
            // For phone, generate OTP
            $this->authService->generateOtp($user->email ?? $user->phone);
            return redirect()->route('otp.login', ['phone' => $identity])->with('message', 'Kode verifikasi telah dikirim ke WhatsApp Anda.');
        }
    }

    /**
     * Show OTP verification page
     */
    public function showOtp(Request $request)
    {
        return Inertia::render('Auth/VerifyOtp', [
            'identity' => $request->phone
        ]);
    }

    /**
     * Verify OTP and log user in
     */
    public function otpLogin(Request $request)
    {
        $request->validate([
            'identity' => 'required|string',
            'otp' => 'required|string|size:6',
        ]);

        try {
            $result = $this->authService->loginWithOtp($request->identity, $request->otp);
            
            Auth::login($result['user']);
            $request->session()->regenerate();

            return redirect()->intended('/dashboard');
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
