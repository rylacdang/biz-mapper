<?php

namespace App\Enums;

enum UserRole: string
{
    case CONSUMER = 'consumer';
    case OWNER = 'owner';

    public function label(): string
    {
        return match ($this) {
            self::CONSUMER => 'Consumer',
            self::OWNER => 'Business Owner',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
