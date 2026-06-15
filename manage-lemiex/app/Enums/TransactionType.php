<?php

namespace App\Enums;

class TransactionType
{
    const DEPOSIT = 'deposit';
    const PAYMENT = 'payment';
    const REFUND = 'refund';
    const SURCHARGE = 'surcharge';
    const REFUND_NOT_WALLET = 'refundnotwallet';

    public static function all(): array
    {
        return [
            self::DEPOSIT,
            self::PAYMENT,
            self::REFUND,
            self::SURCHARGE,
            self::REFUND_NOT_WALLET,
        ];
    }
}
