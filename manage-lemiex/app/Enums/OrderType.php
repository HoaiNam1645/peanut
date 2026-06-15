<?php

namespace App\Enums;

class OrderType
{
    const PRINT = 'Print';
    const TUMBLER = 'Tumbler';

    public static function all(): array
    {
        return [
            self::PRINT,
            self::TUMBLER,
        ];
    }
}
