<?php

namespace App\Enums;

class ProductCategoryType
{
    const WOOD = 'wood';

    public static function all(): array
    {
        return [
            self::WOOD,
        ];
    }

    public static function labels(): array
    {
        return [
            self::WOOD => 'Print (In)',
        ];
    }

    public static function getLabel(string $type): string
    {
        return self::labels()[$type] ?? $type;
    }
}
