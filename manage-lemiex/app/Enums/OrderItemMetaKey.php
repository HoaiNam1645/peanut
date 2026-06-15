<?php

namespace App\Enums;

class OrderItemMetaKey
{
    const FRONT_DESIGN = 'front_design';
    const BACK_DESIGN = 'back_design';
    const SLEEVE_LEFT_DESIGN = 'sleeve_left_design';
    const SLEEVE_RIGHT_DESIGN = 'sleeve_right_design';
    const SPECIAL_DESIGN = 'special_design';
    const DESIGN_FILE = 'design_file';
    const FRONT_DESIGN_QR = 'front_design_qr';
    const BACK_DESIGN_QR = 'back_design_qr';
    const SLEEVE_LEFT_DESIGN_QR = 'sleeve_left_design_qr';
    const SLEEVE_RIGHT_DESIGN_QR = 'sleeve_right_design_qr';
    const SPECIAL_DESIGN_QR = 'special_design_qr';

    public static function all(): array
    {
        return [
            self::FRONT_DESIGN,
            self::BACK_DESIGN,
            self::SLEEVE_LEFT_DESIGN,
            self::SLEEVE_RIGHT_DESIGN,
            self::SPECIAL_DESIGN,
            self::DESIGN_FILE,
            self::FRONT_DESIGN_QR,
            self::BACK_DESIGN_QR,
            self::SLEEVE_LEFT_DESIGN_QR,
            self::SLEEVE_RIGHT_DESIGN_QR,
            self::SPECIAL_DESIGN_QR,
        ];
    }
}
