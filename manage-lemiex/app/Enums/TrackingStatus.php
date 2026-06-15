<?php

namespace App\Enums;

class TrackingStatus
{
    const PENDING = 'pending';
    const INFO_RECEIVED = 'info_received';
    const INFO_RECEIVED_ALT = 'InfoReceived';
    const IN_TRANSIT = 'in_transit';
    const OUT_FOR_DELIVERY = 'out_for_delivery';
    const DELIVERED = 'delivered';
    const EXCEPTION = 'exception';
    const NOT_FOUND = 'not_found';
    const TRACKING = 'Tracking...';

    public static function all(): array
    {
        return [
            self::PENDING,
            self::INFO_RECEIVED,
            self::INFO_RECEIVED_ALT,
            self::IN_TRANSIT,
            self::OUT_FOR_DELIVERY,
            self::DELIVERED,
            self::EXCEPTION,
            self::NOT_FOUND,
            self::TRACKING,
        ];
    }
}
