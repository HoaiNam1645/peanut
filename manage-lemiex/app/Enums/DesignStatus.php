<?php

namespace App\Enums;

class DesignStatus
{
    const NEW = 1;
    const ON_HOLD = 2;
    const PROCESSING = 3;
    const DONE = 4;

    public static function all(): array
    {
        return [
            self::NEW => 'New',
            self::ON_HOLD => 'On Hold',
            self::PROCESSING => 'Processing',
            self::DONE => 'Done',
        ];
    }
}
