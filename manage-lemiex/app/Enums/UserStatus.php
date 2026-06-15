<?php

namespace App\Enums;

class UserStatus
{
    const UNCONFIRMED = 'Unconfirmed';
    const ACTIVE = 'Active';
    const BANNED = 'Banned';

    public static function all(): array
    {
        return [
            self::UNCONFIRMED,
            self::ACTIVE,
            self::BANNED,
        ];
    }
}
