<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FulfillmentPriority extends Model
{
    protected $table = 'fulfillment_priorities';

    protected $fillable = [
        'name',
        'display_name',
        'description',
        'tier_id',
        'price',
        'active',
    ];

    protected $casts = [
        'tier_id' => 'integer',
        'price' => 'decimal:2',
        'active' => 'boolean',
    ];

    /**
     * Get price for a specific priority and tier
     */
    /**
     * Get price for a specific priority and tier
     */
    public static function getPriceForTier(string $priorityName, int $tierId): float
    {
        $priority = self::where('name', $priorityName)
            ->where('tier_id', $tierId)
            ->where('active', true)
            ->first();

        return $priority ? (float) $priority->price : 0;
    }

    /**
     * Get all priorities for a specific tier
     */
    public static function getForTier(int $tierId): \Illuminate\Database\Eloquent\Collection
    {
        return self::where('tier_id', $tierId)
            ->where('active', true)
            ->get();
    }

    /**
     * Get all priority types grouped by name
     */
    public static function getAllGrouped(): array
    {
        $priorities = self::where('active', true)
            ->orderBy('name')
            ->orderBy('tier_id')
            ->get();

        $grouped = [];
        foreach ($priorities as $priority) {
            if (!isset($grouped[$priority->name])) {
                $grouped[$priority->name] = [
                    'name' => $priority->name,
                    'display_name' => $priority->display_name,
                    'description' => $priority->description,
                    'prices' => [],
                ];
            }
            $grouped[$priority->name]['prices'][$priority->tier_id] = (float) $priority->price;
        }

        return array_values($grouped);
    }

    /**
     * Tier names mapping
     */
    public static function getTierNames(): array
    {
        return [
            0 => 'Silver',
            1 => 'Gold',
            2 => 'Platinum',
            3 => 'Diamond',
        ];
    }
}
