<?php

namespace App\Enums;

class ShippingMethod
{
    const STANDARD = 'standard';
    const PRIORITY = 'priority';

    public static function all(): array
    {
        return [
            self::STANDARD,
            self::PRIORITY,
        ];
    }
}
