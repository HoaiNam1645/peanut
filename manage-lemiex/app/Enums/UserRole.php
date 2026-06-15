<?php

namespace App\Enums;

class UserRole
{
    const ADMIN = 1;
    const SELLER = 2;
    const STAFF = 3;
    const SUPPLIER = 4;
    const SUPPORT = 5;
    const DESIGNER = 6;
    const FINANCE = 7;
    const QC = 8;
    const PACKING = 9;
    const SHIPOUT = 10;
    const HR = 11;

    public static function all(): array
    {
        return [
            self::ADMIN => 'Admin',
            self::SELLER => 'Seller',
            self::SUPPLIER => 'Supplier',
            self::STAFF => 'Staff',
            self::SUPPORT => 'Support',
            self::DESIGNER => 'Designer',
            self::FINANCE => 'Finance',
            self::QC => 'QC',
            self::PACKING => 'Packing',
            self::SHIPOUT => 'Shipout',
            self::HR => 'HR',
        ];
    }
}
