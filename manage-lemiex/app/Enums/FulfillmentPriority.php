<?php

namespace App\Enums;

use ReflectionClass;

class FulfillmentPriority
{
    const NORMAL = 'normal';
    const HIGH = 'high';
    const URGENT = 'urgent';

    public static function all(): array
    {
        return [
            self::NORMAL,
            self::HIGH,
            self::URGENT,
        ];
    }
}
