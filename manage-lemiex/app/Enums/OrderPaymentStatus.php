<?php

namespace App\Enums;

class OrderPaymentStatus
{
    const PENDING = 'pending';
    const PAID = 'paid';
    const FULL_REFUND = 'full_refund';
    const PARTIAL_REFUND = 'partial_refund';

    public static function all(): array
    {
        return [
            self::PENDING,
            self::PAID,
            self::FULL_REFUND,
            self::PARTIAL_REFUND,
        ];
    }
}
