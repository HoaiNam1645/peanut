<?php

namespace App\Enums;

class DebitStatus
{
    const OWE = 0;
    const PAID_OFF = 1;

    public static function all(): array
    {
        return [
            self::OWE,
            self::PAID_OFF,
        ];
    }

    public static function labels(): array
    {
        return [
            self::OWE => 'Owe',
            self::PAID_OFF => 'Paid Off',
        ];
    }
}
