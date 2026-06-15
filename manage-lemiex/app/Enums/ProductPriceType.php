<?php

namespace App\Enums;

class ProductPriceType
{
    const BASE_COST = 'base_cost';
    const FRONT = 'front';
    const BACK = 'back';
    const SLEEVE_LEFT = 'sleeve_left';
    const SLEEVE_RIGHT = 'sleeve_right';
    const SPECIAL = 'special';
    const SELLER_SHIPPING = 'seller_shipping';
    const TIKTOK_SHIPPING = 'tiktok_shipping';
    const PRIORITY_SHIPPING = 'priority_shipping';
    const ADDITIONAL_STANDARD = 'additional_standard';
    const ADDITIONAL_PRIORITY = 'additional_priority';
    const SHIPPING_COST = 'shipping_cost';

    public static function all(): array
    {
        return [
            self::BASE_COST,
            self::FRONT,
            self::BACK,
            self::SLEEVE_LEFT,
            self::SLEEVE_RIGHT,
            self::SPECIAL,
            self::SELLER_SHIPPING,
            self::TIKTOK_SHIPPING,
            self::PRIORITY_SHIPPING,
            self::ADDITIONAL_STANDARD,
            self::ADDITIONAL_PRIORITY,
            self::SHIPPING_COST,
        ];
    }
}
