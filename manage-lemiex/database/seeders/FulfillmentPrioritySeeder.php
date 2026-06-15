<?php

namespace Database\Seeders;

use App\Models\FulfillmentPriority;
use Illuminate\Database\Seeder;

class FulfillmentPrioritySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Default prices for each priority type and tier
        // Tier IDs: 0=Silver, 1=Gold, 2=Platinum, 3=Diamond
        $priorities = [
            [
                'name' => 'normal',
                'display_name' => 'Normal',
                'description' => 'Standard fulfillment time (3-5 business days)',
                'prices' => [
                    0 => 0.00,  // Silver
                    1 => 0.00,  // Gold
                    2 => 0.00,  // Platinum
                    3 => 0.00,  // Diamond
                ],
            ],
            [
                'name' => 'priority',
                'display_name' => 'Priority',
                'description' => 'Expedited fulfillment (1-2 business days)',
                'prices' => [
                    0 => 3.00,  // Silver
                    1 => 2.50,  // Gold
                    2 => 2.00,  // Platinum
                    3 => 1.50,  // Diamond
                ],
            ],
        ];

        foreach ($priorities as $priority) {
            foreach ($priority['prices'] as $tierId => $price) {
                FulfillmentPriority::updateOrCreate(
                    [
                        'name' => $priority['name'],
                        'tier_id' => $tierId,
                    ],
                    [
                        'display_name' => $priority['display_name'],
                        'description' => $priority['description'],
                        'price' => $price,
                        'active' => true,
                    ]
                );
            }
        }

        $this->command->info('Fulfillment priorities seeded successfully!');
        $this->command->info('- Normal: $0 for all tiers');
        $this->command->info('- Priority: $3.00 (Silver), $2.50 (Gold), $2.00 (Platinum), $1.50 (Diamond)');
    }
}
