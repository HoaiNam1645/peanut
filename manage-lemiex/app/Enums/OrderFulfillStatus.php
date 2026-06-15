<?php

namespace App\Enums;

class OrderFulfillStatus
{
    const NEW_ORDER = 'new_order';
    const PRODUCING = 'producing';
    const QC_PASS = 'qc_pass';
    const PACKED = 'packed';
    const CONFIRM = 'confirm';
    const PENDING_STOCK = 'pending_stock';
    const ON_HOLD = 'on_hold';
    const SHIPPED = 'shipped';
    const RETURN_TO_SUPPORT = 'return_to_support';
    const CANCELLED = 'cancelled';
    const CANCELLED_REFUND_SHIPPING = 'cancelled_refund_shipping';
    const IN_STOCK = 'in_stock';
    const CLOSED = 'closed';
    const TEST_ORDER = 'test_order';

    public static function all(): array
    {
        return [
            self::NEW_ORDER,
            self::PRODUCING,
            self::QC_PASS,
            self::PACKED,
            self::CONFIRM,
            self::PENDING_STOCK,
            self::ON_HOLD,
            self::SHIPPED,
            self::RETURN_TO_SUPPORT,
            self::CANCELLED,
            self::CANCELLED_REFUND_SHIPPING,
            self::IN_STOCK,
            self::CLOSED,
            self::TEST_ORDER,
        ];
    }

    public static function allWithLabels(): array
    {
        return [
            ['value' => self::NEW_ORDER, 'label' => 'New Order'],
            ['value' => self::PRODUCING, 'label' => 'Producing'],
            ['value' => self::QC_PASS, 'label' => 'QC Pass'],
            ['value' => self::PACKED, 'label' => 'Packed'],
            ['value' => self::CONFIRM, 'label' => 'Confirm'],
            ['value' => self::PENDING_STOCK, 'label' => 'Pending Stock'],
            ['value' => self::ON_HOLD, 'label' => 'On Hold'],
            ['value' => self::SHIPPED, 'label' => 'Shipped'],
            ['value' => self::RETURN_TO_SUPPORT, 'label' => 'Return To Support'],
            ['value' => self::CANCELLED, 'label' => 'Cancelled'],
            ['value' => self::CANCELLED_REFUND_SHIPPING, 'label' => 'Cancelled (Refund Shipping)'],
            ['value' => self::IN_STOCK, 'label' => 'In Stock'],
            ['value' => self::CLOSED, 'label' => 'Closed'],
            ['value' => self::TEST_ORDER, 'label' => 'Test Order'],
        ];
    }
}
