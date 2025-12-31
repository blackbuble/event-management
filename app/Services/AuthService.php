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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use App\Mail\LoginNotification;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\RateLimiter;

class AuthService
{
    protected $userRepository;

    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    /**
     * Find or create user by identity (Email or Phone).
     * Wrapped in DB Transaction to prevent Race Conditions.
     */
    public function findOrCreateByIdentity(string $identity): User
    {
        $identity = $this->normalizeIdentity($identity);
        return DB::transaction(function () use ($identity) {
            $isEmail = filter_var($identity, FILTER_VALIDATE_EMAIL);
            
            $user = $isEmail 
                ? $this->userRepository->findByEmail($identity) 
                : $this->userRepository->findByPhone($identity);

            if (!$user) {
                // Double check using direct query to be absolutely sure within transaction (Locking)
                $query = User::query();
                if ($isEmail) {
                    $query->where('email', $identity);
                } else {
                    $query->where('phone', $identity);
                }
                
                $user = $query->lockForUpdate()->first();

                if (!$user) {
                    $user = $this->userRepository->create([
                        'name' => $isEmail ? explode('@', $identity)[0] : 'User ' . substr($identity, -4),
                        'email' => $isEmail ? $identity : null,
                        'phone' => !$isEmail ? $identity : null,
                        'password' => Hash::make(Str::random(32)),
                        'email_verified_at' => $isEmail ? now() : null,
                    ]);

                    try {
                        $user->assignRole('attendee');
                    } catch (\Exception $e) {
                        Log::error("Failed to assign role to unified user: " . $e->getMessage());
                    }
                }
            }

            return $user;
        });
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
        
        // Use explicit assignment to bypass Mass Assignment protection for internal fields
        $user->otp = Hash::make($otp);
        $user->otp_expires_at = Carbon::now()->addMinutes(10);
        $user->save();

        // DEV LOG: In production, trigger WhatsApp API with full international number
        Log::info("WA_OTP_SENDING: Code generated for WhatsApp number {$identity}");
    }

    /**
     * Validate OTP and prepare login result.
     * Prevents N+1 by eager loading roles if needed (handled in controller or result).
     */
    public function loginWithOtp(string $identity, string $otp): array
    {
        $identity = $this->normalizeIdentity($identity);
        $isEmail = filter_var($identity, FILTER_VALIDATE_EMAIL);
        $user = $isEmail ? $this->userRepository->findByEmail($identity) : $this->userRepository->findByPhone($identity);

        if (!$user || !$user->otp || !$user->otp_expires_at || $user->otp_expires_at->isPast()) {
             throw ValidationException::withMessages([
                'otp' => ['Kode OTP tidak valid atau sudah kadaluarsa.'],
            ]);
        }

        // Standard attempt limiting
        $key = 'otp_attempts_' . $user->id;
        $attempts = Cache::get($key, 0) + 1;
        Cache::put($key, $attempts, now()->addMinutes(10));

        if (!Hash::check($otp, $user->otp)) {
             if ($attempts >= 5) {
                 $user->otp = null;
                 $user->otp_expires_at = null;
                 $user->save();
                 Cache::forget($key);
                 throw ValidationException::withMessages([
                    'otp' => ['Terlalu banyak percobaan salah. Silakan minta kode baru.'],
                ]);
             }

             throw ValidationException::withMessages([
                'otp' => ['Kode OTP salah. Sisa percobaan: ' . (5 - $attempts)],
            ]);
        }

        // Clean up
        return DB::transaction(function () use ($user, $isEmail, $key) {
            $user->otp = null;
            $user->otp_expires_at = null;
            if ($isEmail && !$user->email_verified_at) {
                $user->email_verified_at = now();
            }
            $user->save();

            Cache::forget($key);

            // Send Security Notification
            $this->sendSecurityNotification($user);

            return [
                'user' => $user->load('roles'), // load() is fine for single entity, not N+1
            ];
        });
    }

    /**
     * Social Authentication logic.
     */
    public function socialLogin(array $socialData): array
    {
        return DB::transaction(function () use ($socialData) {
            $user = User::where('social_id', $socialData['social_id'])
                        ->where('social_type', $socialData['social_type'])
                        ->lockForUpdate()
                        ->first();

            if (!$user) {
                $user = User::where('email', $socialData['email'])->lockForUpdate()->first();

                if ($user) {
                    // Security Check: If user already has a DIFFERENT social ID for this provider, block it
                    if ($user->social_id && $user->social_id !== $socialData['social_id']) {
                        throw new \Exception("Akun ini sudah terhubung dengan sosial media lain.");
                    }

                    $user->social_id = $socialData['social_id'];
                    $user->social_type = $socialData['social_type'];
                    $user->social_avatar = $socialData['social_avatar'] ?? $user->avatar;
                    $user->save();
                } else {
                    $user = new User();
                    $user->name = $socialData['name'];
                    $user->email = $socialData['email'];
                    $user->social_id = $socialData['social_id'];
                    $user->social_type = $socialData['social_type'];
                    $user->social_avatar = $socialData['social_avatar'];
                    $user->password = Hash::make(Str::random(32));
                    $user->email_verified_at = now();
                    $user->save();

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
        });
    }

    /**
     * Generate Magic Link.
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
        // DEV LOG: Keep it for internal debugging, but in production this should be hashed or removed
        Log::info("Magic Link generated for {$email}");
        
        return $url;
    }

    /**
     * Validate Magic Link.
     */
    public function validateMagicLink(string $token): array
    {
        return DB::transaction(function () use ($token) {
            $magicToken = MagicLinkToken::where('token', $token)
                ->where('used', false)
                ->where('expires_at', '>', Carbon::now())
                ->lockForUpdate()
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

            // Send Security Notification
            $this->sendSecurityNotification($user);

            return [
                'user' => $user->load('roles'),
            ];
        });
    }
    /**
     * Normalize identity (ensure phone numbers have '+' prefix)
     */
    private function normalizeIdentity(string $identity): string
    {
        $identity = trim($identity);
        $identity = str_replace([' ', '-', '(', ')'], '', $identity);
        $isEmail = filter_var($identity, FILTER_VALIDATE_EMAIL);

        if (!$isEmail && preg_match('/^[0-9+]+$/', $identity)) {
            // Handle Indonesian local prefix '0' -> '+62'
            if (str_starts_with($identity, '0')) {
                $identity = '+62' . substr($identity, 1);
            } 
            // Handle accidental '+0' prefix -> '+62'
            elseif (str_starts_with($identity, '+0')) {
                $identity = '+62' . substr($identity, 2);
            }
            // Ensure phone starts with '+'
            elseif (!str_starts_with($identity, '+')) {
                $identity = '+' . $identity;
            }
        }
        
        return $identity;
    }

    /**
     * Send security notification email on successful login
     */
    private function sendSecurityNotification(User $user): void
    {
        // Only send if user has an email
        if (!$user->email) {
            return;
        }

        try {
            $details = [
                'time' => Carbon::now()->toDayDateTimeString(),
                'ip' => Request::ip(),
                'user_agent' => Request::userAgent(),
            ];

            Mail::to($user->email)->send(new LoginNotification($user, $details));
        } catch (\Exception $e) {
            // Silently fail to not block login if mail server is down
            Log::error("Failed to send security notification: " . $e->getMessage());
        }
    }
}
