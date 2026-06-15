<?php

namespace App\Constants;

class OrderItemStatus
{
    // Order Item Status Constants
    public const UNPROCESSED = 0;
    public const PROCESSED = 1;

    /**
     * Check if item is unprocessed
     */
    public static function isUnprocessed(int $status): bool
    {
        return $status === self::UNPROCESSED;
    }

    /**
     * Check if item is processed
     */
    public static function isProcessed(int $status): bool
    {
        return $status === self::PROCESSED;
    }

    /**
     * Get human-readable label
     */
    public static function getLabel(int $status): string
    {
        return match ($status) {
            self::UNPROCESSED => 'Unprocessed',
            self::PROCESSED => 'Processed',
            default => 'Unknown',
        };
    }
}
