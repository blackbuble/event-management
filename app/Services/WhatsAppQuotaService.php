<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Models\User;
use App\Models\WhatsAppPackage;
use App\Models\WhatsAppTopUp;
use App\Repositories\WhatsAppRepository;

class WhatsAppQuotaService
{
    public function __construct(
        private readonly WhatsAppRepository $whatsAppRepository,
    ) {}

    public function balance(User $user): int
    {
        return (int) $user->whatsapp_quota;
    }

    /**
     * Top-up packages for the dashboard, with locale-aware labels and prices.
     *
     * @return array<int, array{key: string, label: string, quota: int, amount: float, amount_label: string}>
     */
    public function packages(string $locale = 'en'): array
    {
        return WhatsAppPackage::query()
            ->active()
            ->orderBy('amount')
            ->get()
            ->map(fn (WhatsAppPackage $package) => [
                'key' => $package->slug,
                'label' => $package->label,
                'quota' => (int) $package->quota,
                'amount' => (float) $package->amount,
                'amount_label' => 'Rp '.number_format((float) $package->amount, 0, ',', '.'),
            ])
            ->all();
    }

    /**
     * Purchase a quota package. Payment is settled immediately (mock gateway),
     * mirroring the ticket checkout flow.
     */
    public function topUp(User $user, string $packageKey, PaymentMethod $method): WhatsAppTopUp
    {
        $package = WhatsAppPackage::query()
            ->where('slug', $packageKey)
            ->where('is_active', true)
            ->first();

        if (! $package) {
            throw new \InvalidArgumentException(
                app()->getLocale() === 'id'
                    ? 'Paket kuota tidak ditemukan.'
                    : 'The selected quota package was not found.'
            );
        }

        return $this->whatsAppRepository->credit($user, [
            'package' => $package->slug,
            'amount' => $package->amount,
            'quota' => $package->quota,
            'payment_method' => $method->value,
        ]);
    }

    /**
     * Consume one unit of quota per WhatsApp ticket message.
     */
    public function consume(User $user, int $units = 1): bool
    {
        return $this->whatsAppRepository->consume($user, $units);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function history(User $user, int $limit = 10): array
    {
        return $this->whatsAppRepository->history($user, $limit)
            ->map(fn (WhatsAppTopUp $topUp) => [
                'package' => $topUp->package,
                'quota' => (int) $topUp->quota,
                'amount' => (float) $topUp->amount,
                'payment_method' => $topUp->payment_method,
                'status' => $topUp->status,
                'created_at' => $topUp->created_at?->toIso8601String(),
            ])
            ->all();
    }
}
