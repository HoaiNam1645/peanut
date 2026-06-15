<?php

namespace App\Enums;

class OversizeSite
{
    const LEFT = 'left';
    const RIGHT = 'right';
    const CENTER = 'center';

    public static function all(): array
    {
        return [
            self::LEFT,
            self::RIGHT,
            self::CENTER,
        ];
    }
}
