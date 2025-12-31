<?php

namespace App\Services;

use App\Models\User;
use App\Models\MagicLinkToken;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

class AuthService
{
    protected $userRepository;

    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    /**
     * Find or create user by identity (Email or Phone).
     * Used for unified registration and login.
     */
    public function findOrCreateByIdentity(string $identity): User
    {
        $isEmail = filter_var($identity, FILTER_VALIDATE_EMAIL);
        
        if ($isEmail) {
            $user = $this->userRepository->findByEmail($identity);
        } else {
            $user = $this->userRepository->findByPhone($identity);
        }

        if (!$user) {
            $userData = [
                'name' => $isEmail ? explode('@', $identity)[0] : 'User ' . substr($identity, -4),
                'email' => $isEmail ? $identity : null,
                'phone' => !$isEmail ? $identity : null,
                'password' => Hash::make(Str::random(32)),
                'email_verified_at' => $isEmail ? now() : null,
            ];

            $user = $this->userRepository->create($userData);

            try {
                $user->assignRole('attendee');
            } catch (\Exception $e) {
                Log::error("Failed to assign role to unified user: " . $e->getMessage());
            }
        }

        return $user;
    }

    /**
     * Generate OTP for login (WhatsApp/Email).
     */
    public function generateOtp(string $identity): void
    {
        $isEmail = filter_var($identity, FILTER_VALIDATE_EMAIL);
        $user = $isEmail ? $this->userRepository->findByEmail($identity) : $this->userRepository->findByPhone($identity);

        if (!$user) return;

        $otp = (string) rand(100000, 999999);
        $user->otp = Hash::make($otp);
        $user->otp_expires_at = Carbon::now()->addMinutes(10);
        $user->save();

        // DEV LOG: In production, send via WhatsApp/Email API
        Log::info("OTP for {$identity}: {$otp}");
    }

    /**
     * Validate OTP and prepare login result.
     */
    public function loginWithOtp(string $identity, string $otp): array
    {
        $isEmail = filter_var($identity, FILTER_VALIDATE_EMAIL);
        $user = $isEmail ? $this->userRepository->findByEmail($identity) : $this->userRepository->findByPhone($identity);

        if (!$user || !$user->otp || !$user->otp_expires_at || $user->otp_expires_at->isPast()) {
             throw ValidationException::withMessages([
                'otp' => ['Kode OTP tidak valid atau sudah kadaluarsa.'],
            ]);
        }

        if (!Hash::check($otp, $user->otp)) {
             // Increment failure count or just invalidate after X tries? 
             // Let's simply invalidate after 5 failed tries if we had a counter, 
             // but for now, we'll rely on the throttle. 
             // Improved: Let's invalidate the OTP after any failure to be super safe? 
             // No, that's too annoying for users.
             
             // Dynamic: We could store attempts in session or cache.
             $key = 'otp_attempts_' . $user->id;
             $attempts = \Illuminate\Support\Facades\Cache::get($key, 0) + 1;
             \Illuminate\Support\Facades\Cache::put($key, $attempts, now()->addMinutes(10));

             if ($attempts >= 5) {
                 $user->otp = null;
                 $user->otp_expires_at = null;
                 $user->save();
                 \Illuminate\Support\Facades\Cache::forget($key);
                 throw ValidationException::withMessages([
                    'otp' => ['Terlalu banyak percobaan salah. Silakan minta kode baru.'],
                ]);
             }

             throw ValidationException::withMessages([
                'otp' => ['Kode OTP salah. Sisa percobaan: ' . (5 - $attempts)],
            ]);
        }

        // Clean OTP after success
        $user->otp = null;
        $user->otp_expires_at = null;
        if (!$user->email_verified_at && $isEmail) $user->email_verified_at = now();
        $user->save();
        \Illuminate\Support\Facades\Cache::forget('otp_attempts_' . $user->id);

        return [
            'user' => $user->load('roles'),
        ];
    }

    /**
     * Social Authentication logic.
     */
    public function socialLogin(array $socialData): array
    {
        $user = User::where('social_id', $socialData['social_id'])
                    ->where('social_type', $socialData['social_type'])
                    ->first();

        if (!$user) {
            $user = User::where('email', $socialData['email'])->first();

            if ($user) {
                $user->update([
                    'social_id' => $socialData['social_id'],
                    'social_type' => $socialData['social_type'],
                    'social_avatar' => $socialData['social_avatar'] ?? $user->avatar,
                ]);
            } else {
                $user = User::create([
                    'name' => $socialData['name'],
                    'email' => $socialData['email'],
                    'social_id' => $socialData['social_id'],
                    'social_type' => $socialData['social_type'],
                    'social_avatar' => $socialData['social_avatar'],
                    'email_verified_at' => now(),
                    'password' => Hash::make(Str::random(32)),
                ]);

                try {
                    $user->assignRole('attendee');
                } catch (\Exception $e) {
                    Log::error("Failed to assign role to social user: " . $e->getMessage());
                }
            }
        }

        return [
            'user' => $user->load('roles'),
        ];
    }

    /**
     * Generate Magic Link for Email login.
     */
    public function generateMagicLink(string $email): string
    {
        $token = Str::random(64);
        
        MagicLinkToken::create([
            'email' => $email,
            'token' => $token,
            'expires_at' => Carbon::now()->addMinutes(30),
        ]);

        $url = url("/auth/magic-link?token={$token}");
        
        // DEV LOG: In production, send via Email API
        Log::info("Magic Link for {$email}: {$url}");
        
        return $url;
    }

    /**
     * Validate Magic Link and prepare login result.
     */
    public function validateMagicLink(string $token): array
    {
        $magicToken = MagicLinkToken::where('token', $token)
            ->where('used', false)
            ->where('expires_at', '>', Carbon::now())
            ->first();

        if (!$magicToken) {
            throw ValidationException::withMessages([
                'token' => ['Tautan login tidak valid atau sudah kadaluarsa.'],
            ]);
        }

        $magicToken->update(['used' => true]);
        $user = User::where('email', $magicToken->email)->first();

        if (!$user) {
            $user = $this->findOrCreateByIdentity($magicToken->email);
        }

        return [
            'user' => $user->load('roles'),
        ];
    }
}
