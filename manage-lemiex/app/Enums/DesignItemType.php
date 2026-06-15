<?php

namespace App\Enums;

class DesignItemType
{
    const FRONT = 'front';
    const BACK = 'back';
    const SLEEVE_LEFT = 'sleeve_left';
    const SLEEVE_RIGHT = 'sleeve_right';
    const SPECIAL = 'special';

    public static function all(): array
    {
        return [
            self::FRONT,
            self::BACK,
            self::SLEEVE_LEFT,
            self::SLEEVE_RIGHT,
            self::SPECIAL,
        ];
    }
}
