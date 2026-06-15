<?php

namespace Database\Seeders;

use App\Models\Country;
use Illuminate\Database\Seeder;

class CountrySeeder extends Seeder
{
    public function run(): void
    {
        $countries = [
            // North America
            ['name' => 'United States', 'iso_code' => 'US', 'currency_code' => 'USD', 'phone_code' => '+1', 'flag' => '🇺🇸', 'active' => true],
            ['name' => 'Canada', 'iso_code' => 'CA', 'currency_code' => 'CAD', 'phone_code' => '+1', 'flag' => '🇨🇦', 'active' => true],
            ['name' => 'Mexico', 'iso_code' => 'MX', 'currency_code' => 'MXN', 'phone_code' => '+52', 'flag' => '🇲🇽', 'active' => true],
            
            // Europe
            ['name' => 'United Kingdom', 'iso_code' => 'GB', 'currency_code' => 'GBP', 'phone_code' => '+44', 'flag' => '🇬🇧', 'active' => true],
            ['name' => 'Germany', 'iso_code' => 'DE', 'currency_code' => 'EUR', 'phone_code' => '+49', 'flag' => '🇩🇪', 'active' => true],
            ['name' => 'France', 'iso_code' => 'FR', 'currency_code' => 'EUR', 'phone_code' => '+33', 'flag' => '🇫🇷', 'active' => true],
            ['name' => 'Italy', 'iso_code' => 'IT', 'currency_code' => 'EUR', 'phone_code' => '+39', 'flag' => '🇮🇹', 'active' => true],
            ['name' => 'Spain', 'iso_code' => 'ES', 'currency_code' => 'EUR', 'phone_code' => '+34', 'flag' => '🇪🇸', 'active' => true],
            ['name' => 'Netherlands', 'iso_code' => 'NL', 'currency_code' => 'EUR', 'phone_code' => '+31', 'flag' => '🇳🇱', 'active' => true],
            
            // Asia
            ['name' => 'Vietnam', 'iso_code' => 'VN', 'currency_code' => 'VND', 'phone_code' => '+84', 'flag' => '🇻🇳', 'active' => true],
            ['name' => 'China', 'iso_code' => 'CN', 'currency_code' => 'CNY', 'phone_code' => '+86', 'flag' => '🇨🇳', 'active' => true],
            ['name' => 'Japan', 'iso_code' => 'JP', 'currency_code' => 'JPY', 'phone_code' => '+81', 'flag' => '🇯🇵', 'active' => true],
            ['name' => 'South Korea', 'iso_code' => 'KR', 'currency_code' => 'KRW', 'phone_code' => '+82', 'flag' => '🇰🇷', 'active' => true],
            ['name' => 'Singapore', 'iso_code' => 'SG', 'currency_code' => 'SGD', 'phone_code' => '+65', 'flag' => '🇸🇬', 'active' => true],
            ['name' => 'Thailand', 'iso_code' => 'TH', 'currency_code' => 'THB', 'phone_code' => '+66', 'flag' => '🇹🇭', 'active' => true],
            ['name' => 'Malaysia', 'iso_code' => 'MY', 'currency_code' => 'MYR', 'phone_code' => '+60', 'flag' => '🇲🇾', 'active' => true],
            ['name' => 'Indonesia', 'iso_code' => 'ID', 'currency_code' => 'IDR', 'phone_code' => '+62', 'flag' => '🇮🇩', 'active' => true],
            ['name' => 'Philippines', 'iso_code' => 'PH', 'currency_code' => 'PHP', 'phone_code' => '+63', 'flag' => '🇵🇭', 'active' => true],
            ['name' => 'India', 'iso_code' => 'IN', 'currency_code' => 'INR', 'phone_code' => '+91', 'flag' => '🇮🇳', 'active' => true],
            
            // Oceania
            ['name' => 'Australia', 'iso_code' => 'AU', 'currency_code' => 'AUD', 'phone_code' => '+61', 'flag' => '🇦🇺', 'active' => true],
            ['name' => 'New Zealand', 'iso_code' => 'NZ', 'currency_code' => 'NZD', 'phone_code' => '+64', 'flag' => '🇳🇿', 'active' => true],
        ];

        foreach ($countries as $country) {
            Country::create($country);
        }
    }
}
