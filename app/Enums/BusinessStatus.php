<?php

namespace App\Enums;

enum BusinessStatus: string
{
    case PENDING = 'pending';
    case ACTIVE = 'active';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending Review',
            self::ACTIVE => 'Active Listing',
        };
    }
}
