<?php

namespace App\Enums;

class MetaSwitch
{
    const OFF = 0;
    const ON = 1;
    const SPECIAL = 2;

    public static function all(): array
    {
        return [
            self::OFF,
            self::ON,
            self::SPECIAL,
        ];
    }

    public static function labels(): array
    {
        return [
            self::OFF => 'Off',
            self::ON => 'On',
            self::SPECIAL => 'Special',
        ];
    }
}
