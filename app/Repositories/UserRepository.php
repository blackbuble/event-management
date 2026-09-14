<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class UserRepository
{
    /**
     * Resolve a passwordless attendee account by email for seamless guest
     * checkout. Existing accounts are reused untouched; new ones get an
     * attendee role and a random password (access is via magic link / OTP).
     *
     * Locked + transactional to make concurrent guest checkouts safe.
     */
    public function findOrCreateAttendeeByEmail(string $email, ?string $name = null): User
    {
        return DB::transaction(function () use ($email, $name) {
            $user = User::query()->where('email', $email)->lockForUpdate()->first();

            if ($user) {
                return $user;
            }

            $user = User::create([
                'name' => $name ?: Str::before($email, '@'),
                'email' => $email,
                'password' => Hash::make(Str::random(32)),
                'email_verified_at' => now(),
            ]);

            try {
                $user->assignRole('attendee');
            } catch (\Throwable $e) {
                Log::error('Failed to assign attendee role to guest buyer: '.$e->getMessage());
            }

            return $user;
        });
    }

    /**
     * Create a new user.
     */
    public function create(array $data): User
    {
        return User::create($data);
    }

    /**
     * Find a user by email.
     */
    public function findByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    /**
     * Find a user by primary key.
     */
    public function findById(int $id): ?User
    {
        return User::find($id);
    }

    /**
     * Find a user by uuid.
     */
    public function findByUuid(string $uuid): ?User
    {
        return User::where('uuid', $uuid)->first();
    }

    /**
     * Find a user by phone number.
     */
    public function findByPhone(string $phone): ?User
    {
        return User::where('phone', $phone)->first();
    }
}
