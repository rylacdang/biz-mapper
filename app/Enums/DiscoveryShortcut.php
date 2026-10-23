<?php

namespace App\Enums;

enum DiscoveryShortcut: string
{
    case KAPE_MUNA = 'kape-muna';
    case FOOD_NEARBY = 'food-nearby';
    case EVERYDAY_ESSENTIALS = 'everyday-essentials';

    /**
     * Get the shortcut's display name.
     */
    public function label(): string
    {
        return match ($this) {
            self::KAPE_MUNA => 'Kape muna',
            self::FOOD_NEARBY => 'Food nearby',
            self::EVERYDAY_ESSENTIALS => 'Everyday essentials',
        };
    }

    /**
     * Get the icon used by the explorer.
     */
    public function icon(): string
    {
        return match ($this) {
            self::KAPE_MUNA => 'coffee',
            self::FOOD_NEARBY => 'utensils',
            self::EVERYDAY_ESSENTIALS => 'shopping-cart',
        };
    }

    /**
     * Get the category names included in this shortcut.
     */
    public function categories(): array
    {
        return match ($this) {
            self::KAPE_MUNA => ['Coffee Shops & Cafes'],
            self::FOOD_NEARBY => ['Carinderia & Eatery', 'Fast Food & Restaurant', 'Bakeries'],
            self::EVERYDAY_ESSENTIALS => ['Sari-Sari Store & Tindahan', 'Palengke & Retail', 'Grocery & Supermarket', 'Pharmacy & Botika'],
        };
    }
}
