<?php

namespace App\Enums;

class ProductionStatus
{
    const PENDING = 'pending';
    const MAPPED = 'mapped';
    const COMPLETED = 'completed';
    const CANCELED = 'canceled';
    const PICKUP = 'pickup';

    public static function all(): array
    {
        return [
            self::PENDING,
            self::MAPPED,
            self::COMPLETED,
            self::CANCELED,
            self::PICKUP,
        ];
    }
}
