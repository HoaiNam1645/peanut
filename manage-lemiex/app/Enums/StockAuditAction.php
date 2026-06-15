<?php

namespace App\Enums;

class StockAuditAction
{
    const INCREASE = 'increase';
    const DECREASE = 'decrease';
    const ADJUST = 'adjust';
    const IMPORT = 'import';

    public static function all(): array
    {
        return [
            self::INCREASE,
            self::DECREASE,
            self::ADJUST,
            self::IMPORT,
        ];
    }
}
