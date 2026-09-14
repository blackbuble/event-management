<?php

namespace App\Enums;

enum EventCategory: string
{
    case Music = 'music';
    case Sports = 'sports';
    case Technology = 'technology';
    case Business = 'business';
    case Education = 'education';
    case Arts = 'arts';
    case Food = 'food';
    case Community = 'community';
    case Other = 'other';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $category) => $category->value, self::cases());
    }

    public function label(string $locale): string
    {
        $isId = $locale === 'id';

        return match ($this) {
            self::Music => $isId ? 'Musik' : 'Music',
            self::Sports => $isId ? 'Olahraga' : 'Sports',
            self::Technology => $isId ? 'Teknologi' : 'Technology',
            self::Business => $isId ? 'Bisnis' : 'Business',
            self::Education => $isId ? 'Pendidikan' : 'Education',
            self::Arts => $isId ? 'Seni & Budaya' : 'Arts & Culture',
            self::Food => $isId ? 'Kuliner' : 'Food & Drink',
            self::Community => $isId ? 'Komunitas' : 'Community',
            self::Other => $isId ? 'Lainnya' : 'Other',
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(string $locale): array
    {
        return array_map(
            fn (self $category) => ['value' => $category->value, 'label' => $category->label($locale)],
            self::cases(),
        );
    }
}
