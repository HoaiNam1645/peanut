<?php

namespace App\Enums;

class SupportStatus
{
    const NEW = 0;
    const SOLVED = 1;

    public static function all(): array
    {
        return [
            self::NEW,
            self::SOLVED,
        ];
    }

    public static function labels(): array
    {
        return [
            self::NEW => 'New',
            self::SOLVED => 'Solved',
        ];
    }
}
