<?php

namespace App\Services;

use App\Models\User;
use Spatie\Permission\Models\Role;
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
     * Register a new user.
     */
    public function register(array $data): array
    {
        $data['password'] = Hash::make($data['password']);
        
        $user = $this->userRepository->create($data);

        // Assign default role (Attendee) using Spatie
        // Ensure roles are seeded (created via Migration/Seeder first)
        try {
            $user->assignRole('attendee');
        } catch (\Spatie\Permission\Exceptions\RoleDoesNotExist $e) {
            // Handle case if role doesn't exist, maybe create it or log
            // For now, silently fail or ensure your seeds run
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return [
            'user' => $user->load('roles'),
            'token' => $token,
        ];
    }

    /**
     * Login with email and password.
     */
    public function login(string $email, string $password): array
    {
        $user = $this->userRepository->findByEmail($email);

        if (! $user || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials do not match our records.'],
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return [
            'user' => $user->load('roles'),
            'token' => $token,
        ];
    }

    /**
     * Generate OTP for login.
     */
    public function generateOtp(string $email): void
    {
        $user = $this->userRepository->findByEmail($email);

        if (! $user) {
            // Optional: Don't reveal user existence
            return; 
        }

        $otp = (string) rand(100000, 999999);
        $user->otp = Hash::make($otp); // Hash OTP for security
        $user->otp_expires_at = Carbon::now()->addMinutes(10);
        $user->save();

        // Send OTP via Email (or WhatsApp)
        // Mail::to($user->email)->send(new OtpMail($otp));
        Log::info("OTP for {$user->email}: {$otp}"); // For Demo/Dev
    }

    /**
     * Login with OTP.
     */
    public function loginWithOtp(string $email, string $otp): array
    {
        $user = $this->userRepository->findByEmail($email);

        if (! $user || ! $user->otp || ! $user->otp_expires_at || $user->otp_expires_at->isPast()) {
             throw ValidationException::withMessages([
                'otp' => ['Invalid or expired OTP.'],
            ]);
        }

        if (! Hash::check($otp, $user->otp)) {
             throw ValidationException::withMessages([
                'otp' => ['Invalid OTP.'],
            ]);
        }

        // Clear OTP
        $user->otp = null;
        $user->otp_expires_at = null;
        $user->save();

        $token = $user->createToken('auth_token')->plainTextToken;

        return [
            'user' => $user->load('roles'),
            'token' => $token,
        ];
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()->delete();
    }
}
