<?php

namespace App\Enums;

class TimelineObject
{
    const ORDER = 'order';
    const TICKET = 'ticket';
    const USER = 'user';
    const PRODUCT = 'product';
    const TRANSACTION = 'transaction';

    public static function all(): array
    {
        return [
            self::ORDER,
            self::TICKET,
            self::USER,
            self::PRODUCT,
            self::TRANSACTION,
        ];
    }
}
