<?php

namespace App\Enums;

class TransactionTypeSurcharge
{
    const EXTRA_FEE = 'extra_fee';
    const REFUND_FEE = 'refund_fee';

    public static function all(): array
    {
        return [
            self::EXTRA_FEE,
            self::REFUND_FEE,
        ];
    }
}
