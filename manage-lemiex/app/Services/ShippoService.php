<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class ShippoService
{
    private string $apiKey;
    private string $baseUrl = 'https://api.goshippo.com';
    private bool $testMode;

    public function __construct()
    {
        $this->apiKey = config('services.shippo.api_key');
        $this->testMode = config('services.shippo.test_mode', false);
    }

    /**
     * Create shipping label using Shippo API
     * Returns same format as ShipEngineService for compatibility
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

            // Step 1: Create Shipment
            $shipmentPayload = [
                'address_from' => $this->formatShippoAddress($fromAddress),
                'address_to' => $this->formatShippoAddress($toAddress),
                'parcels' => [$this->formatShippoParcel($package)],
                'async' => false,
            ];

            // Add customs for international
            if ($customsInfo) {
                $shipmentPayload['customs_declaration'] = $this->formatShippoCustoms($customsInfo);
            } elseif (isset($toAddress['country_code']) && strtoupper($toAddress['country_code']) !== 'US') {
                $shipmentPayload['customs_declaration'] = $this->getDefaultCustomsDeclaration();
            }

            Log::info('Shippo API Create Shipment Request', [
                'service_code' => $serviceCode,
                'test_mode' => $testLabel,
                'to_address' => $toAddress,
            ]);

            $shipmentResponse = Http::withHeaders([
                'Authorization' => "ShippoToken {$this->apiKey}",
                'Content-Type' => 'application/json',
            ])->timeout(30)->post("{$this->baseUrl}/shipments", $shipmentPayload);

            if (!$shipmentResponse->successful()) {
                $error = $shipmentResponse->json();
                Log::error('Shippo Create Shipment Error', [
                    'status' => $shipmentResponse->status(),
                    'error' => $error,
                ]);
                throw new Exception($error['detail'] ?? $error['message'] ?? 'Shippo shipment creation failed');
            }

            $shipment = $shipmentResponse->json();

            // Step 2: Find the rate that matches our service type
            $rate = $this->findBestRate($shipment['rates'] ?? [], $serviceCode);

            if (!$rate) {
                throw new Exception("No rate found for service: {$serviceCode}");
            }

            // Step 3: Create Transaction (Buy Label)
            // Use PDF_4x6 for standard 4x6 thermal label format (portrait orientation)
            $transactionPayload = [
                'rate' => $rate['object_id'],
                'label_file_type' => 'PDF_4x6',
                'async' => false,
            ];

            $transactionResponse = Http::withHeaders([
                'Authorization' => "ShippoToken {$this->apiKey}",
                'Content-Type' => 'application/json',
            ])->timeout(30)->post("{$this->baseUrl}/transactions", $transactionPayload);

            if (!$transactionResponse->successful()) {
                $error = $transactionResponse->json();
                Log::error('Shippo Create Transaction Error', [
                    'status' => $transactionResponse->status(),
                    'error' => $error,
                ]);
                throw new Exception($error['detail'] ?? $error['message'] ?? 'Shippo transaction failed');
            }

            $transaction = $transactionResponse->json();

            // Check transaction status
            if ($transaction['status'] !== 'SUCCESS') {
                $messages = $transaction['messages'] ?? [];
                $errorMsg = !empty($messages) ? $messages[0]['text'] ?? 'Transaction failed' : 'Transaction failed';
                throw new Exception($errorMsg);
            }

            Log::info('Shippo API Response Success', [
                'transaction_id' => $transaction['object_id'] ?? null,
                'tracking_number' => $transaction['tracking_number'] ?? null,
            ]);

            // Return in ShipEngine-compatible format
            return [
                'label_id' => $transaction['object_id'],
                'tracking_number' => $transaction['tracking_number'],
                'label_download' => [
                    'href' => $transaction['label_url'],
                    'pdf' => $transaction['label_url'],
                ],
                'shipment_cost' => [
                    'amount' => $rate['amount'] ?? 0,
                    'currency' => $rate['currency'] ?? 'USD',
                ],
                'carrier_code' => $rate['provider'] ?? 'usps',
                'service_code' => $rate['servicelevel']['token'] ?? $serviceCode,
                // Original Shippo data
                '_shippo' => [
                    'transaction' => $transaction,
                    'rate' => $rate,
                ],
            ];
        } catch (Exception $e) {
            Log::error('Shippo createLabel Exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Format address for Shippo API
     */
    private function formatShippoAddress(array $address): array
    {
        $formatted = [
            'name' => $address['name'] ?? '',
            'street1' => $address['address_line1'] ?? $address['address_1'] ?? $address['street1'] ?? '',
            'city' => $address['city_locality'] ?? $address['city'] ?? '',
            'state' => $this->normalizeState($address['state_province'] ?? $address['state'] ?? ''),
            'zip' => $address['postal_code'] ?? $address['postcode'] ?? $address['zip'] ?? '',
            'country' => $address['country_code'] ?? $address['country'] ?? 'US',
        ];

        // Optional fields
        if (!empty($address['address_line2']) || !empty($address['address_2']) || !empty($address['street2'])) {
            $formatted['street2'] = $address['address_line2'] ?? $address['address_2'] ?? $address['street2'];
        }

        if (!empty($address['phone'])) {
            $formatted['phone'] = $address['phone'];
        }

        if (!empty($address['company_name']) || !empty($address['company'])) {
            $formatted['company'] = $address['company_name'] ?? $address['company'];
        }

        if (!empty($address['email'])) {
            $formatted['email'] = $address['email'];
        }

        return $formatted;
    }

    /**
     * Format parcel for Shippo API
     */
    private function formatShippoParcel(array $package): array
    {
        $weight = $package['weight']['value'] ?? 1.0;
        $weightUnit = $package['weight']['unit'] ?? 'lb';

        // Convert weight unit to Shippo format
        if ($weightUnit === 'pound') {
            $weightUnit = 'lb';
        }

        // Convert dimension unit to Shippo format ('inch' -> 'in')
        $dimensionUnit = $package['dimensions']['unit'] ?? 'in';
        if ($dimensionUnit === 'inch') {
            $dimensionUnit = 'in';
        }

        return [
            'length' => (string)($package['dimensions']['length'] ?? 15),
            'width' => (string)($package['dimensions']['width'] ?? 14),
            'height' => (string)($package['dimensions']['height'] ?? 2),
            'distance_unit' => $dimensionUnit,
            'weight' => (string)$weight,
            'mass_unit' => $weightUnit,
        ];
    }

    /**
     * Find best matching rate based on service code
     * Strictly matches the correct service level without incorrect fallback
     */
    private function findBestRate(array $rates, string $serviceCode): ?array
    {
        // Map service codes to acceptable Shippo service levels
        // Ground Advantage should NOT fallback to Priority
        $serviceMapping = [
            'usps_ground_advantage' => ['usps_ground_advantage'],
            'usps_priority_mail' => ['usps_priority'],
            'usps_priority_mail_express' => ['usps_priority_express'],
            'usps_first_class_mail' => ['usps_first'],
            'usps_parcel_select' => ['usps_parcel_select'],
        ];

        $acceptableServices = $serviceMapping[$serviceCode] ?? [$serviceCode];

        Log::info('Finding rate for service', [
            'requested_service' => $serviceCode,
            'acceptable_services' => $acceptableServices,
            'available_rates' => array_map(fn($r) => [
                'service' => $r['servicelevel']['token'] ?? 'unknown',
                'provider' => $r['provider'] ?? 'unknown',
                'amount' => $r['amount'] ?? 0,
            ], $rates),
        ]);

        // Find matching rate - strict match only
        foreach ($rates as $rate) {
            $rateService = $rate['servicelevel']['token'] ?? '';
            if (in_array($rateService, $acceptableServices)) {
                Log::info('Rate matched', [
                    'selected_service' => $rateService,
                    'amount' => $rate['amount'] ?? 0,
                ]);
                return $rate;
            }
        }

        // If no exact match found, log and return null (will cause error)
        Log::warning('No matching rate found for requested service', [
            'requested_service' => $serviceCode,
            'available_services' => array_map(fn($r) => $r['servicelevel']['token'] ?? 'unknown', $rates),
        ]);

        return null;
    }

    /**
     * Normalize state name to 2-letter code
     */
    public function normalizeState(string $state): string
    {
        $stateMap = [
            'alabama' => 'AL',
            'alaska' => 'AK',
            'arizona' => 'AZ',
            'arkansas' => 'AR',
            'california' => 'CA',
            'colorado' => 'CO',
            'connecticut' => 'CT',
            'delaware' => 'DE',
            'florida' => 'FL',
            'georgia' => 'GA',
            'hawaii' => 'HI',
            'idaho' => 'ID',
            'illinois' => 'IL',
            'indiana' => 'IN',
            'iowa' => 'IA',
            'kansas' => 'KS',
            'kentucky' => 'KY',
            'louisiana' => 'LA',
            'maine' => 'ME',
            'maryland' => 'MD',
            'massachusetts' => 'MA',
            'michigan' => 'MI',
            'minnesota' => 'MN',
            'mississippi' => 'MS',
            'missouri' => 'MO',
            'montana' => 'MT',
            'nebraska' => 'NE',
            'nevada' => 'NV',
            'new hampshire' => 'NH',
            'new jersey' => 'NJ',
            'new mexico' => 'NM',
            'new york' => 'NY',
            'north carolina' => 'NC',
            'north dakota' => 'ND',
            'ohio' => 'OH',
            'oklahoma' => 'OK',
            'oregon' => 'OR',
            'pennsylvania' => 'PA',
            'rhode island' => 'RI',
            'south carolina' => 'SC',
            'south dakota' => 'SD',
            'tennessee' => 'TN',
            'texas' => 'TX',
            'utah' => 'UT',
            'vermont' => 'VT',
            'virginia' => 'VA',
            'washington' => 'WA',
            'west virginia' => 'WV',
            'wisconsin' => 'WI',
            'wyoming' => 'WY',
            'district of columbia' => 'DC',
            'puerto rico' => 'PR',
        ];

        $stateLower = strtolower(trim($state));

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
        return max($pounds, 0.1);
    }

    /**
     * Get default FROM address (warehouse)
     */
    public function getDefaultFromAddress(): array
    {
        return [
            'name' => 'LEMIEX LLC',
            'company' => 'LEMIEX LLC Scorp',
            'phone' => '5208783081',
            'street1' => '2800 W Division St Ste A6',
            'street2' => '',
            'city' => 'Arlington',
            'state' => 'TX',
            'zip' => '76012-6604',
            'country' => 'US',
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
                'unit' => 'lb',
            ],
            'dimensions' => [
                'length' => 15,
                'width' => 14,
                'height' => 2,
                'unit' => 'in',
            ],
        ];
    }

    /**
     * Get default customs declaration for international shipments
     */
    private function getDefaultCustomsDeclaration(): array
    {
        return [
            'contents_type' => 'MERCHANDISE',
            'non_delivery_option' => 'RETURN',
            'certify' => true,
            'certify_signer' => 'LEMIEX LLC',
            'items' => [
                [
                    'description' => 'Apparel',
                    'quantity' => 1,
                    'net_weight' => '0.5',
                    'mass_unit' => 'lb',
                    'value_amount' => '10.00',
                    'value_currency' => 'USD',
                    'origin_country' => 'US',
                ],
            ],
        ];
    }

    /**
     * Format customs info from ShipEngine format to Shippo format
     */
    private function formatShippoCustoms(?array $customsInfo): ?array
    {
        if (!$customsInfo) {
            return null;
        }

        $items = [];
        foreach ($customsInfo['customs_items'] ?? [] as $item) {
            $items[] = [
                'description' => $item['description'] ?? 'Merchandise',
                'quantity' => $item['quantity'] ?? 1,
                'net_weight' => (string)($item['weight']['value'] ?? 0.5),
                'mass_unit' => $item['weight']['unit'] === 'pound' ? 'lb' : ($item['weight']['unit'] ?? 'lb'),
                'value_amount' => (string)($item['value']['amount'] ?? 10),
                'value_currency' => strtoupper($item['value']['currency'] ?? 'USD'),
                'origin_country' => 'US',
            ];
        }

        return [
            'contents_type' => strtoupper($customsInfo['contents'] ?? 'MERCHANDISE'),
            'non_delivery_option' => $customsInfo['non_delivery'] === 'return_to_sender' ? 'RETURN' : 'ABANDON',
            'certify' => true,
            'certify_signer' => 'LEMIEX LLC',
            'items' => $items,
        ];
    }
}
