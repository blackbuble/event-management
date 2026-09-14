<?php

namespace App\Repositories;

use App\Models\User;
use App\Models\WhatsAppTopUp;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class WhatsAppRepository
{
    /**
     * Record a paid top-up and credit the organizer's quota atomically.
     */
    public function credit(User $user, array $attributes): WhatsAppTopUp
    {
        return DB::transaction(function () use ($user, $attributes) {
            $topUp = WhatsAppTopUp::create([
                'user_id' => $user->id,
                'package' => $attributes['package'],
                'amount' => $attributes['amount'],
                'quota' => $attributes['quota'],
                'payment_method' => $attributes['payment_method'] ?? null,
                'status' => 'paid',
            ]);

            User::query()
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->increment('whatsapp_quota', (int) $attributes['quota']);

            return $topUp;
        });
    }

    /**
     * Atomically consume quota; returns false when the balance is insufficient
     * (so a send is skipped rather than going negative).
     */
    public function consume(User $user, int $units = 1): bool
    {
        if ($units < 1) {
            return true;
        }

        $affected = User::query()
            ->whereKey($user->getKey())
            ->where('whatsapp_quota', '>=', $units)
            ->decrement('whatsapp_quota', $units);

        return $affected > 0;
    }

    public function history(User $user, int $limit = 10): Collection
    {
        return WhatsAppTopUp::query()
            ->where('user_id', $user->id)
            ->latest()
            ->limit($limit)
            ->get();
    }
}
