<?php

namespace App\Enums;

class DesignFileType
{
    const EMB = 'emb';
    const PES = 'pes';
    const DST = 'dst';

    public static function all(): array
    {
        return [
            self::EMB,
            self::PES,
            self::DST,
        ];
    }
}
