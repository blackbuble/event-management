<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case BankTransfer = 'bank_transfer';
    case VirtualAccount = 'virtual_account';
    case EWallet = 'ewallet';
    case Qris = 'qris';
    case CreditCard = 'credit_card';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $method) => $method->value, self::cases());
    }

    public function label(string $locale): string
    {
        $isId = $locale === 'id';

        return match ($this) {
            self::BankTransfer => $isId ? 'Transfer Bank' : 'Bank Transfer',
            self::VirtualAccount => $isId ? 'Virtual Account' : 'Virtual Account',
            self::EWallet => $isId ? 'E-Wallet' : 'E-Wallet',
            self::Qris => 'QRIS',
            self::CreditCard => $isId ? 'Kartu Kredit/Debit' : 'Credit/Debit Card',
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(string $locale): array
    {
        return array_map(
            fn (self $method) => ['value' => $method->value, 'label' => $method->label($locale)],
            self::cases(),
        );
    }
}
