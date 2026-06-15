<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class ShipEngineService
{
    private string $apiKey;
    private string $baseUrl = 'https://api.shipengine.com/v1';
    private bool $testMode;
    private ?string $carrierId;

    public function __construct()
    {
        $this->apiKey = config('services.shipengine.api_key');
        $this->testMode = config('services.shipengine.test_mode', false);
        
        // Use test_carrier_id for test mode, otherwise use production carrier_id
        $this->carrierId = $this->testMode 
            ? config('services.shipengine.test_carrier_id')
            : config('services.shipengine.carrier_id');
    }

    /**
     * Create shipping label
     */
    public function createLabel(
        array $toAddress,
        ?array $fromAddress = null,
        ?array $package = null,
        string $serviceCode = 'usps_ground_advantage',
        ?array $customsInfo = null,
        ?bool $testLabel = null
    ): array {
        try {
            $fromAddress = $fromAddress ?? $this->getDefaultFromAddress();
            $package = $package ?? $this->getDefaultPackage();
            $testLabel = $testLabel ?? $this->testMode;

            $payload = [
                'shipment' => [
                    'validate_address' => 'validate_and_clean',
                    'service_code' => $serviceCode,
                    'ship_to' => $this->formatShipEngineAddress($toAddress),
                    'ship_from' => $this->formatShipEngineAddress($fromAddress),
                    'packages' => [$package],
                ],
                'label_format' => 'pdf',
                'label_layout' => '4x6',
                'test_label' => $testLabel,
            ];

            // Add carrier_id inside shipment (required when account has multiple carriers)
            if ($this->carrierId) {
                $payload['shipment']['carrier_id'] = $this->carrierId;
            }

            // Add customs info for international shipments
            if ($customsInfo) {
                $payload['shipment']['customs'] = $customsInfo;
            } elseif (isset($toAddress['country_code']) && $toAddress['country_code'] !== 'US') {
                $payload['shipment']['customs'] = $this->getDefaultCustomsInfo();
            }

            Log::info('ShipEngine API Request', [
                'service_code' => $serviceCode,
                'test_mode' => $testLabel,
                'to_address' => $toAddress,
            ]);

            $response = Http::withHeaders([
                'API-Key' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(30)->post("{$this->baseUrl}/labels", $payload);

            if (!$response->successful()) {
                $error = $response->json();
                Log::error('ShipEngine API Error', [
                    'status' => $response->status(),
                    'error' => $error,
                    'payload' => $payload,
                ]);
                throw new Exception($error['message'] ?? 'ShipEngine API request failed');
            }

            $result = $response->json();
            
            Log::info('ShipEngine API Response Success', [
                'label_id' => $result['label_id'] ?? null,
                'tracking_number' => $result['tracking_number'] ?? null,
            ]);

            return $result;

        } catch (Exception $e) {
            Log::error('ShipEngine createLabel Exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Format address for ShipEngine API
     */
    private function formatShipEngineAddress(array $address): array
    {
        $formatted = [
            'name' => $address['name'] ?? '',
            'address_line1' => $address['address_line1'] ?? $address['address_1'] ?? '',
            'city_locality' => $address['city_locality'] ?? $address['city'] ?? '',
            'state_province' => $this->normalizeState($address['state_province'] ?? $address['state'] ?? ''),
            'postal_code' => $address['postal_code'] ?? $address['postcode'] ?? $address['zip'] ?? '',
            'country_code' => $address['country_code'] ?? $address['country'] ?? 'US',
        ];

        // Optional fields
        if (!empty($address['address_line2']) || !empty($address['address_2'])) {
            $formatted['address_line2'] = $address['address_line2'] ?? $address['address_2'];
        }

        if (!empty($address['phone'])) {
            $formatted['phone'] = $address['phone'];
        }

        if (!empty($address['company_name'])) {
            $formatted['company_name'] = $address['company_name'];
        }

        if (!empty($address['email'])) {
            $formatted['email'] = $address['email'];
        }

        if (isset($address['address_residential_indicator'])) {
            $formatted['address_residential_indicator'] = $address['address_residential_indicator'];
        }

        return $formatted;
    }

    /**
     * Normalize state name to 2-letter code
     */
    public function normalizeState(string $state): string
    {
        $stateMap = [
            'alabama' => 'AL', 'alaska' => 'AK', 'arizona' => 'AZ', 'arkansas' => 'AR',
            'california' => 'CA', 'colorado' => 'CO', 'connecticut' => 'CT', 'delaware' => 'DE',
            'florida' => 'FL', 'georgia' => 'GA', 'hawaii' => 'HI', 'idaho' => 'ID',
            'illinois' => 'IL', 'indiana' => 'IN', 'iowa' => 'IA', 'kansas' => 'KS',
            'kentucky' => 'KY', 'louisiana' => 'LA', 'maine' => 'ME', 'maryland' => 'MD',
            'massachusetts' => 'MA', 'michigan' => 'MI', 'minnesota' => 'MN', 'mississippi' => 'MS',
            'missouri' => 'MO', 'montana' => 'MT', 'nebraska' => 'NE', 'nevada' => 'NV',
            'new hampshire' => 'NH', 'new jersey' => 'NJ', 'new mexico' => 'NM', 'new york' => 'NY',
            'north carolina' => 'NC', 'north dakota' => 'ND', 'ohio' => 'OH', 'oklahoma' => 'OK',
            'oregon' => 'OR', 'pennsylvania' => 'PA', 'rhode island' => 'RI', 'south carolina' => 'SC',
            'south dakota' => 'SD', 'tennessee' => 'TN', 'texas' => 'TX', 'utah' => 'UT',
            'vermont' => 'VT', 'virginia' => 'VA', 'washington' => 'WA', 'west virginia' => 'WV',
            'wisconsin' => 'WI', 'wyoming' => 'WY',
            'district of columbia' => 'DC', 'puerto rico' => 'PR',
        ];

        $stateLower = strtolower(trim($state));
        
        // If already 2-letter code, return uppercase
        if (strlen($state) === 2) {
            return strtoupper($state);
        }

        return $stateMap[$stateLower] ?? strtoupper($state);
    }

    /**
     * Convert ounces to pounds
     */
    public function convertOzToLb(float $weightInOz): float
    {
        $pounds = $weightInOz / 16;
        return max($pounds, 0.1); // Minimum 0.1 pound
    }

    /**
     * Get default FROM address (warehouse)
     */
    public function getDefaultFromAddress(): array
    {
        return [
            'name' => 'LEMIEX LLC',
            'company_name' => 'LEMIEX LLC Scorp',
            'phone' => '5208783081',
            'address_line1' => '2800 W Division St Ste A6',
            'address_line2' => '',
            'city_locality' => 'Arlington',
            'state_province' => 'TX',
            'postal_code' => '76012-6604',
            'country_code' => 'US',
            'address_residential_indicator' => 'no',
            'email' => 'lemiex.usa@gmail.com',
        ];
    }

    /**
     * Get default package dimensions
     */
    public function getDefaultPackage(): array
    {
        return [
            'weight' => [
                'value' => 1.0,
                'unit' => 'pound',
            ],
            'dimensions' => [
                'length' => 15,
                'width' => 14,
                'height' => 2,
                'unit' => 'inch',
            ],
        ];
    }

    /**
     * Get default customs info for international shipments
     */
    private function getDefaultCustomsInfo(): array
    {
        return [
            'contents' => 'merchandise',
            'non_delivery' => 'return_to_sender',
            'customs_items' => [
                [
                    'description' => 'Merchandise',
                    'quantity' => 1,
                    'value' => [
                        'currency' => 'usd',
                        'amount' => 10.00,
                    ],
                    'weight' => [
                        'value' => 0.5,
                        'unit' => 'pound',
                    ],
                ],
            ],
        ];
    }
}
