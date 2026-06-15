<?php

namespace App\Enums;

use ReflectionClass;

class EmbroideryType
{
    const STANDARD = 'standard';
    const METALLIC = 'metallic';

    public static function all(): array
    {
        return [
            self::STANDARD,
            self::METALLIC,
        ];
    }
}
