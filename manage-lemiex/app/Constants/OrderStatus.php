<?php

namespace App\Constants;

class OrderStatus
{
    // Fulfill Status Constants
    public const NEW_ORDER = 'new_order';
    public const CONFIRM = 'confirm';
    public const PENDING_STOCK = 'pending_stock';
    public const IN_STOCK = 'in_stock';
    public const PRODUCING = 'producing';
    public const QC_PASS = 'qc_pass';
    public const PACKED = 'packed';
    public const SHIPPED = 'shipped';
    public const CANCELLED = 'cancelled';
    public const CANCELLED_REFUND_SHIPPING = 'cancelled_refund_shipping';
    public const ON_HOLD = 'on_hold';
    public const TEST_ORDER = 'test_order';
    public const RETURN_TO_SUPPORT = 'return_to_support';
    public const CLOSED = 'closed';

    // Payment Status Constants
    public const PAYMENT_PENDING = 'pending';
    public const PAYMENT_PAID = 'paid';
    public const PAYMENT_FULL_REFUND = 'full_refund';
    public const PAYMENT_PARTIAL_REFUND = 'partial_refund';

    // Processing Status Constants
    public const PROCESSING_CREATING = 'creating';
    public const PROCESSING_PROCESSING = 'processing';
    public const PROCESSING_COMPLETED = 'completed';
    public const PROCESSING_FAILED = 'failed';

    /**
     * Get all fulfill statuses that are eligible for stock allocation
     */
    public static function getEligibleForAllocation(): array
    {
        return [
            self::NEW_ORDER,
            self::CONFIRM,
            self::PENDING_STOCK,
            self::IN_STOCK,
        ];
    }

    /**
     * Get all fulfill statuses that should be excluded from allocation
     */
    public static function getExcludedFromAllocation(): array
    {
        return [
            self::PRODUCING,
            self::QC_PASS,
            self::PACKED,
            self::SHIPPED,
            self::CANCELLED,
            self::CLOSED,
            self::TEST_ORDER,
        ];
    }

    /**
     * Check if status is eligible for stock allocation
     */
    public static function isEligibleForAllocation(string $status): bool
    {
        return in_array($status, self::getEligibleForAllocation());
    }

    /**
     * Check if status is a final state (no more changes)
     */
    public static function isFinalState(string $status): bool
    {
        return in_array($status, [
            self::SHIPPED,
            self::CANCELLED,
            self::CANCELLED_REFUND_SHIPPING,
            self::CLOSED,
        ]);
    }

    /**
     * Get the next status when all items are allocated
     */
    public static function getNextStatusWhenAllocated(string $currentStatus): string
    {
        return match ($currentStatus) {
            self::NEW_ORDER => self::CONFIRM,
            self::PENDING_STOCK => self::IN_STOCK,
            self::CONFIRM, self::IN_STOCK => $currentStatus, // No change
            default => $currentStatus,
        };
    }

    /**
     * Get the next status when not all items are allocated
     */
    public static function getNextStatusWhenPending(string $currentStatus): string
    {
        return match ($currentStatus) {
            self::NEW_ORDER, self::CONFIRM => self::PENDING_STOCK,
            self::PENDING_STOCK, self::IN_STOCK => self::PENDING_STOCK,
            default => $currentStatus,
        };
    }

    /**
     * Get human-readable label for status
     */
    public static function getLabel(string $status): string
    {
        return match ($status) {
            self::NEW_ORDER => 'New Order',
            self::CONFIRM => 'Confirmed',
            self::PENDING_STOCK => 'Pending Stock',
            self::IN_STOCK => 'In Stock',
            self::PRODUCING => 'Producing',
            self::QC_PASS => 'QC Pass',
            self::PACKED => 'Packed',
            self::SHIPPED => 'Shipped',
            self::CANCELLED => 'Cancelled',
            self::CANCELLED_REFUND_SHIPPING => 'Cancelled (Refund Shipping)',
            self::ON_HOLD => 'On Hold',
            self::CLOSED => 'Closed',
            self::TEST_ORDER => 'Test Order',
            default => ucfirst(str_replace('_', ' ', $status)),
        };
    }

    /**
     * Get color code for status (for UI)
     */
    public static function getColor(string $status): string
    {
        return match ($status) {
            self::NEW_ORDER => '#3b82f6', // Blue
            self::CONFIRM => '#8b5cf6', // Purple
            self::PENDING_STOCK => '#f59e0b', // Orange
            self::IN_STOCK => '#10b981', // Green
            self::PRODUCING => '#06b6d4', // Cyan
            self::QC_PASS => '#14b8a6', // Teal
            self::PACKED => '#a855f7', // Violet
            self::SHIPPED => '#22c55e', // Success Green
            self::CANCELLED => '#ef4444', // Red
            self::CANCELLED_REFUND_SHIPPING => '#f87171', // Light Red
            self::ON_HOLD => '#f97316', // Dark Orange
            self::CLOSED => '#374151', // Dark Gray
            self::TEST_ORDER => '#6b7280', // Gray
            default => '#9ca3af', // Light Gray
        };
    }
}
