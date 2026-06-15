<?php

namespace App\Enums;

class OrderType
{
    const WOOD = 'Wood';
    const TUMBLER = 'Tumbler';

    public static function all(): array
    {
        return [
            self::WOOD,
            self::TUMBLER,
        ];
    }
}
