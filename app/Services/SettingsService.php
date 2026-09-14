<?php

namespace App\Services;

use App\Repositories\SettingRepository;

class SettingsService
{
    private const DEFAULTS = [
        'payment_gateway.enabled' => false,
        'payment_gateway.provider' => '',
        'payment_gateway.api_key' => '',
        'payment_gateway.secret_key' => '',

        'whatsapp_provider.enabled' => false,
        'whatsapp_provider.provider' => '',
        'whatsapp_provider.token' => '',
        'whatsapp_provider.sender' => '',

        'platform_fee.type' => 'fixed',
        'platform_fee.amount' => 0,
    ];

    public function __construct(
        private readonly SettingRepository $settingRepository,
    ) {}

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->settingRepository->get($key, $default ?? (self::DEFAULTS[$key] ?? null));
    }

    /**
     * @return array{app_name: string, contact_center: string, contact_email: string}
     */
    public function general(): array
    {
        return [
            'app_name' => (string) $this->get('general.app_name', config('app.name')),
            'contact_center' => (string) $this->get('general.contact_center', ''),
            'contact_email' => (string) $this->get('general.contact_email', ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateGeneral(array $data): void
    {
        $this->settingRepository->putMany([
            'general.app_name' => trim((string) ($data['app_name'] ?? '')),
            'general.contact_center' => trim((string) ($data['contact_center'] ?? '')),
            'general.contact_email' => trim((string) ($data['contact_email'] ?? '')),
        ]);
    }

    /**
     * @return array{enabled: bool, provider: string, api_key: string, secret_key: bool}
     */
    public function paymentGateway(): array
    {
        return [
            'enabled' => (bool) $this->get('payment_gateway.enabled'),
            'provider' => (string) $this->get('payment_gateway.provider'),
            'api_key' => (string) $this->get('payment_gateway.api_key'),
            'secret_key' => $this->get('payment_gateway.secret_key') !== '',
        ];
    }

    /**
     * @return array{enabled: bool, provider: string, token: string, sender: string}
     */
    public function whatsappProvider(): array
    {
        return [
            'enabled' => (bool) $this->get('whatsapp_provider.enabled'),
            'provider' => (string) $this->get('whatsapp_provider.provider'),
            'token' => $this->get('whatsapp_provider.token') !== '',
            'sender' => (string) $this->get('whatsapp_provider.sender'),
        ];
    }

    /**
     * @return array{type: string, amount: float}
     */
    public function platformFee(): array
    {
        return [
            'type' => (string) $this->get('platform_fee.type', 'fixed'),
            'amount' => (float) $this->get('platform_fee.amount', 0),
        ];
    }

    /**
     * Compute the platform fee for a base amount (0 when unset).
     */
    public function platformFeeFor(float $base): float
    {
        $fee = $this->platformFee();

        return match ($fee['type']) {
            'percent' => round($base * ($fee['amount'] / 100), 2),
            default => round($fee['amount'], 2),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updatePaymentGateway(array $data): void
    {
        $values = [
            'payment_gateway.enabled' => (bool) ($data['enabled'] ?? false),
            'payment_gateway.provider' => (string) ($data['provider'] ?? ''),
            'payment_gateway.api_key' => (string) ($data['api_key'] ?? ''),
        ];

        // Blank secret means "keep the existing value".
        if (! empty($data['secret_key'])) {
            $values['payment_gateway.secret_key'] = (string) $data['secret_key'];
        }

        $this->settingRepository->putMany($values);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateWhatsAppProvider(array $data): void
    {
        $values = [
            'whatsapp_provider.enabled' => (bool) ($data['enabled'] ?? false),
            'whatsapp_provider.provider' => (string) ($data['provider'] ?? ''),
            'whatsapp_provider.sender' => (string) ($data['sender'] ?? ''),
        ];

        if (! empty($data['token'])) {
            $values['whatsapp_provider.token'] = (string) $data['token'];
        }

        $this->settingRepository->putMany($values);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updatePlatformFee(array $data): void
    {
        $this->settingRepository->putMany([
            'platform_fee.type' => in_array($data['type'] ?? 'fixed', ['fixed', 'percent'], true)
                ? $data['type']
                : 'fixed',
            'platform_fee.amount' => max(0, (float) ($data['amount'] ?? 0)),
        ]);
    }

    /**
     * Admin-facing snapshot. Secrets are never returned — only a set/not-set flag.
     *
     * @return array<string, mixed>
     */
    public function adminPayload(): array
    {
        return [
            'general' => $this->general(),
            'payment_gateway' => $this->paymentGateway(),
            'whatsapp_provider' => $this->whatsappProvider(),
            'platform_fee' => $this->platformFee(),
        ];
    }
}
