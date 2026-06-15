<?php

namespace App\Enums;

class ShippingService
{
    const USPS = 'USPS';
    const FEDEX = 'FedEx';
    const UPS = 'UPS';

    public static function all(): array
    {
        return [
            self::USPS,
            self::FEDEX,
            self::UPS,
        ];
    }
}
