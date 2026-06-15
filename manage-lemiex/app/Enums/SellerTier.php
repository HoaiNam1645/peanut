<?php

namespace App\Enums;

class SellerTier
{
    const SILVER = 0;
    const GOLD = 1;
    const PLATINUM = 2;
    const DIAMOND = 3;

    public static function all(): array
    {
        return [
            self::SILVER => 'Silver',
            self::GOLD => 'Gold',
            self::PLATINUM => 'Platinum',
            self::DIAMOND => 'Diamond',
        ];
    }
}
