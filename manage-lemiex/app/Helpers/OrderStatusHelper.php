<?php

namespace App\Helpers;

use App\Constants\OrderStatus;

class OrderStatusHelper
{
    /**
     * Get status badge HTML for frontend
     */
    public static function getBadgeHtml(string $status): string
    {
        $label = OrderStatus::getLabel($status);
        $color = OrderStatus::getColor($status);
        
        return sprintf(
            '<span style="background-color: %s; color: white; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600;">%s</span>',
            $color,
            $label
        );
    }

    /**
     * Get status info array for API responses
     */
    public static function getStatusInfo(string $status): array
    {
        return [
            'value' => $status,
            'label' => OrderStatus::getLabel($status),
            'color' => OrderStatus::getColor($status),
            'is_final' => OrderStatus::isFinalState($status),
            'is_eligible_for_allocation' => OrderStatus::isEligibleForAllocation($status),
        ];
    }

    /**
     * Get all statuses with their info
     */
    public static function getAllStatuses(): array
    {
        $statuses = [
            OrderStatus::NEW_ORDER,
            OrderStatus::CONFIRM,
            OrderStatus::PENDING_STOCK,
            OrderStatus::IN_STOCK,
            OrderStatus::PRODUCING,
            OrderStatus::SHIPPED,
            OrderStatus::CANCELLED,
            OrderStatus::ON_HOLD,
            OrderStatus::TEST_ORDER,
        ];

        return array_map(fn($status) => self::getStatusInfo($status), $statuses);
    }

    /**
     * Check if status transition is valid
     */
    public static function isValidTransition(string $from, string $to): bool
    {
        // Define valid transitions
        $validTransitions = [
            OrderStatus::NEW_ORDER => [
                OrderStatus::CONFIRM,
                OrderStatus::PENDING_STOCK,
                OrderStatus::CANCELLED,
            ],
            OrderStatus::CONFIRM => [
                OrderStatus::PRODUCING,
                OrderStatus::PENDING_STOCK,
                OrderStatus::CANCELLED,
            ],
            OrderStatus::PENDING_STOCK => [
                OrderStatus::IN_STOCK,
                OrderStatus::CANCELLED,
            ],
            OrderStatus::IN_STOCK => [
                OrderStatus::PRODUCING,
                OrderStatus::CANCELLED,
            ],
            OrderStatus::PRODUCING => [
                OrderStatus::SHIPPED,
                OrderStatus::ON_HOLD,
                OrderStatus::CANCELLED,
            ],
            OrderStatus::ON_HOLD => [
                OrderStatus::PRODUCING,
                OrderStatus::CANCELLED,
            ],
        ];

        return isset($validTransitions[$from]) && in_array($to, $validTransitions[$from]);
    }
}
