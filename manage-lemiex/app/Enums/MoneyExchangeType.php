<?php

namespace App\Enums;

class MoneyExchangeType
{
    const USD = 'usd';
    const VND = 'vnd';
    const PAYPAL = 'paypal';

    public static function all(): array
    {
        return [
            self::USD,
            self::VND,
            self::PAYPAL,
        ];
    }
}
