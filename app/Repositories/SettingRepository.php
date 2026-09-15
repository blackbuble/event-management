<?php

namespace App\Repositories;

use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;

class SettingRepository
{
    /**
     * Keys whose values are encrypted at rest (API keys, tokens, secrets).
     *
     * @var array<int, string>
     */
    private const SENSITIVE_KEYS = [
        'payment_gateway.secret_key',
        'whatsapp_provider.token',
    ];

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return Setting::query()
            ->get()
            ->mapWithKeys(fn (Setting $setting) => [$setting->key => $this->decode($setting->value, $setting->key)])
            ->all();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $setting = Setting::query()->where('key', $key)->first();

        return $setting ? $this->decode($setting->value, $key) : $default;
    }

    public function put(string $key, mixed $value): void
    {
        Setting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $this->encode($value, $key)],
        );
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function putMany(array $values): void
    {
        foreach ($values as $key => $value) {
            $this->put($key, $value);
        }
    }

    private function encode(mixed $value, string $key): string
    {
        $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: 'null';

        // Secrets are encrypted at rest; non-sensitive config stays readable JSON.
        return $this->isSensitive($key) && $json !== 'null'
            ? Crypt::encryptString($json)
            : $json;
    }

    private function decode(?string $value, string $key): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($this->isSensitive($key)) {
            try {
                $value = Crypt::decryptString($value);
            } catch (\Throwable) {
                // Legacy plaintext value — fall through and decode as-is.
            }
        }

        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }

    private function isSensitive(string $key): bool
    {
        return in_array($key, self::SENSITIVE_KEYS, true);
    }
}
