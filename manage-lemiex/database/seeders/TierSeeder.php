<?php

namespace Database\Seeders;

use App\Models\Tier;
use Illuminate\Database\Seeder;

class TierSeeder extends Seeder
{
    public function run(): void
    {
        $tiers = [
            ['id' => 1, 'tier_id' => 0, 'name' => 'Silver'],
            ['id' => 2, 'tier_id' => 1, 'name' => 'Gold'],
            ['id' => 3, 'tier_id' => 2, 'name' => 'Platinum'],
            ['id' => 4, 'tier_id' => 3, 'name' => 'Diamond'],
        ];

        foreach ($tiers as $tier) {
            Tier::create($tier);
        }
    }
}
