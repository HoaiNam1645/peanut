<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemMeta;
use App\Models\ProductVariant;
use App\Services\FeeCalculationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Exception;

class OrderProcessingService
{
    /**
     * Create QR codes for order items
     */
    public function createQRCodes(Order $order): void
    {
        try {
            $items = OrderItem::where('order_id', $order->id)->get();
            $totalQuantity = $items->sum('quantity');
            $stt = 1;
            $itemIndex = 1; // Sequential item number (1, 2, 3, ...)

            foreach ($items as $item) {
                $variant = ProductVariant::where('variant_id', $item->variant_id)->first();

                for ($i = 0; $i < $item->quantity; $i++) {
                    try {
                        $this->createSingleQR($order, $item, $variant, $stt, $totalQuantity, $itemIndex);
                        $stt++;
                    } catch (Exception $e) {
                        Log::error('Failed to create QR for item unit', [
                            'order_id' => $order->id,
                            'item_id' => $item->id,
                            'stt' => $stt,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
                $itemIndex++; // Increment after each item
            }
        } catch (Exception $e) {
            Log::error('Failed to create QR codes', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Create QR codes for wood orders using the simple/batch endpoint.
     * Sends all units in ONE request and persists the returned URLs.
     */
    public function createQRCodesBatchSimple(Order $order): void
    {
        $serviceUrl = env('QR_SERVICE_URL_WOOD', 'https://manage.lemiex.us/pes-api/qr/generate-simple');
        $trackingBase = env('FRONTEND_TRACKING_URL', config('app.url'));

        $items = OrderItem::where('order_id', $order->id)->get();
        $totalQuantity = (int) $items->sum('quantity');

        // Build payload + a parallel map so we know which OrderItem each result belongs to.
        $payloadItems = [];
        $itemRefs = []; // index => ['item_id' => ..., 'stt' => ...]
        $stt = 1;
        $itemIndex = 1;

        foreach ($items as $item) {
            $variant = ProductVariant::where('variant_id', $item->variant_id)->first();
            $units = (int) $item->quantity;

            for ($i = 0; $i < $units; $i++) {
                $pageqr = "{$trackingBase}/track/{$order->id}?stt={$stt}&item_id={$item->id}&item_stt={$itemIndex}";

                $payloadItems[] = [
                    'item_id' => $item->id,
                    'stt' => $stt,
                    'total' => $totalQuantity,
                    'style' => $variant->style ?? '',
                    'color' => $variant->color ?? '',
                    'size' => $variant->size ?? '',
                    'pageqr' => $pageqr,
                ];
                $itemRefs[] = ['item_id' => $item->id, 'stt' => $stt];
                $stt++;
            }
            $itemIndex++;
        }

        if (empty($payloadItems)) {
            Log::info('Wood QR batch: no items', ['order_id' => $order->id]);
            return;
        }

        Log::info('Calling QR batch service', [
            'order_id' => $order->id,
            'url' => $serviceUrl,
            'count' => count($payloadItems),
        ]);

        try {
            $response = Http::timeout(60)
                ->withOptions(['verify' => config('app.http_verify_ssl', true)])
                ->post($serviceUrl, [
                    'order_id' => $order->id,
                    'items' => $payloadItems,
                ]);

            if (!$response->successful()) {
                Log::error('QR batch service failed', [
                    'order_id' => $order->id,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return;
            }

            $urls = $response->json('urls') ?? [];

            foreach ($urls as $idx => $url) {
                $ref = $itemRefs[$idx] ?? null;
                if (!$ref || empty($url)) {
                    continue;
                }
                OrderItemMeta::create([
                    'order_item_id' => $ref['item_id'],
                    'meta_key' => 'special_design_qr',
                    'meta_value' => $url,
                    'switch' => 0,
                    'status' => false,
                ]);
                Log::info('Created QR (batch)', [
                    'order_id' => $order->id,
                    'item_id' => $ref['item_id'],
                    'stt' => $ref['stt'],
                    'url' => $url,
                ]);
            }
        } catch (Exception $e) {
            Log::error('Exception calling QR batch service', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Re-create QR codes for specific items
     */
    public function createQRCodesForItems(Order $order, array $itemIds): array
    {
        $log = [];
        try {
            // Use the SAME QR generator as the main order-creation flow
            // (createQRCodesBatchSimple → /pes-api/qr/generate-simple) so remade QR
            // matches freshly-created QR. The old per-unit createSingleQR (/qr/generate)
            // produced a different ("old") format.
            $serviceUrl = env('QR_SERVICE_URL_WOOD', 'https://manage.lemiex.us/pes-api/qr/generate-simple');
            $trackingBase = env('FRONTEND_TRACKING_URL', config('app.url'));

            $items = OrderItem::where('order_id', $order->id)->get();
            $totalQuantity = (int) $items->sum('quantity');

            // Clear existing QR metas (+ stored files) for the rework items.
            foreach ($items as $item) {
                if (in_array($item->id, $itemIds)) {
                    $existingMetas = OrderItemMeta::where('order_item_id', $item->id)
                        ->where('meta_key', 'special_design_qr')
                        ->get();
                    foreach ($existingMetas as $meta) {
                        $this->deleteOldQrFile($meta->meta_value);
                        $meta->delete();
                    }
                }
            }

            // Build the batch payload. stt/itemIndex count across ALL items (to keep the
            // same numbering as creation); only the rework items go into the payload.
            $payloadItems = [];
            $itemRefs = [];
            $stt = 1;
            $itemIndex = 1;
            foreach ($items as $item) {
                $variant = ProductVariant::where('variant_id', $item->variant_id)->first();
                $units = (int) $item->quantity;
                for ($i = 0; $i < $units; $i++) {
                    if (in_array($item->id, $itemIds)) {
                        $payloadItems[] = [
                            'item_id' => $item->id,
                            'stt' => $stt,
                            'total' => $totalQuantity,
                            'style' => $variant->style ?? '',
                            'color' => $variant->color ?? '',
                            'size' => $variant->size ?? '',
                            'pageqr' => "{$trackingBase}/track/{$order->id}?stt={$stt}&item_id={$item->id}&item_stt={$itemIndex}",
                        ];
                        $itemRefs[] = ['item_id' => $item->id, 'stt' => $stt];
                    }
                    $stt++;
                }
                $itemIndex++;
            }

            if (empty($payloadItems)) {
                return $log;
            }

            $response = Http::timeout(60)
                ->withOptions(['verify' => config('app.http_verify_ssl', true)])
                ->post($serviceUrl, [
                    'order_id' => $order->id,
                    'items' => $payloadItems,
                ]);

            if (!$response->successful()) {
                Log::error('Remake QR batch service failed', [
                    'order_id' => $order->id,
                    'status' => $response->status(),
                    'body' => mb_substr((string) $response->body(), 0, 500),
                ]);
                throw new Exception('QR batch service failed: ' . $response->status());
            }

            $urls = $response->json('urls') ?? [];
            foreach ($urls as $idx => $url) {
                $ref = $itemRefs[$idx] ?? null;
                if (!$ref || empty($url)) {
                    continue;
                }
                OrderItemMeta::create([
                    'order_item_id' => $ref['item_id'],
                    'meta_key' => 'special_design_qr',
                    'meta_value' => $url,
                    'switch' => 0,
                    'status' => false,
                ]);
                $log[] = ['status' => true, 'stt' => $ref['stt'], 'item_id' => $ref['item_id'], 'url' => $url];
            }
        } catch (Exception $e) {
            Log::error('Failed to recreate QR codes (batch)', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
        return $log;
    }

    /**
     * Delete old QR file from storage
     */
    protected function deleteOldQrFile(?string $url): void
    {
        if (empty($url)) return;

        try {
            // Check if file is stored in our B2 convert_qr folder
            if (str_contains($url, 'convert_qr/')) {
                // Extract relative path: convert_qr/filename.png
                $parts = explode('convert_qr/', $url);
                if (count($parts) > 1) {
                    $filePath = 'convert_qr/' . $parts[1];

                    // Delete from B2
                    if (Storage::disk('b2')->exists($filePath)) {
                        Storage::disk('b2')->delete($filePath);
                        Log::info('Deleted old QR file', ['path' => $filePath]);
                    }
                }
            }
        } catch (Exception $e) {
            Log::warning('Failed to delete old QR file', ['url' => $url, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Create single QR code
     */
    protected function createSingleQR(Order $order, OrderItem $item, $variant, int $stt, int $total, int $itemIndex = 1): void
    {
        // Normalize color name: convert to PascalCase (no spaces) for QR service
        $color = $variant->color ?? 'Unknown';
        $color = str_replace(['_', ' '], ' ', $color); // Normalize separators to space
        $color = ucwords(strtolower($color)); // Title Case first
        $color = str_replace(' ', '', $color); // Remove all spaces → "ForestGreen"

        // Sanitize style and size for filename
        $style = $variant->style ?? 'Unknown';
        $sStyle = preg_replace('/[^a-zA-Z0-9]/', '', $style);
        $size = $variant->size ?? 'Unknown';
        $sSize = preg_replace('/[^a-zA-Z0-9]/', '', $size);
        $sColor = preg_replace('/[^a-zA-Z0-9]/', '', $color);

        $qrData = [
            'order_item_id' => $item->id,
            'order_id' => $order->id,
            'stt' => $stt,
            'total' => $total,
            'style' => $variant->style ?? 'Unknown',
            'color' => $color,
            'size' => $variant->size ?? 'Unknown',
            // stt = quantity index, item_id = database ID, item_stt = page index
            'pageqr' => env('FRONTEND_TRACKING_URL', config('app.url')) . "/track/{$order->id}?stt={$stt}&item_id={$item->id}&item_stt={$itemIndex}"
        ];
        Log::info('QR pageqr generated', [
            'pageqr' => $qrData['pageqr'],
            'itemIndex' => $itemIndex
        ]);
        // Call external QR service with retry
        $attempts = 0;
        $maxAttempts = 3;
        $qrImage = null;

        while ($attempts < $maxAttempts && !$qrImage) {
            try {
                $attempts++;
                $qrStorageUrl = env('QR_STORAGE_URL', 'https://s3.us-east-005.backblazeb2.com/Lemiex-Fulfillment/convert_qr');
                $qrData['dst_url'] = "{$qrStorageUrl}/{$order->id}_{$item->id}_{$sStyle}_{$sSize}_{$sColor}_{$stt}_{$total}.png";

                $qrServiceUrl = env('QR_SERVICE_URL', 'https://manage.lemiex.us/pes-api/qr/generate');
                Log::info('Calling QR service', [
                    'order_id' => $order->id,
                    'stt' => $stt,
                    'url' => $qrServiceUrl,
                    'params' => $qrData
                ]);

                $response = Http::timeout(15)
                    ->withOptions([
                        'verify' => config('app.http_verify_ssl', true),
                        'allow_redirects' => true,
                        'connect_timeout' => 10,
                    ])
                    ->withHeaders([
                        'User-Agent' => 'Laravel-OrderSystem/1.0',
                        'Accept' => '*/*',
                    ])
                    ->get($qrServiceUrl, $qrData);

                Log::info('QR service response', [
                    'order_id' => $order->id,
                    'stt' => $stt,
                    'status' => $response->status(),
                    'successful' => $response->successful(),
                    'body_length' => strlen($response->body())
                ]);

                if ($response->successful()) {
                    $body = $response->body();
                    $json = json_decode($body, true);

                    if (json_last_error() === JSON_ERROR_NONE && isset($json['url'])) {
                        $qrImage = ['type' => 'url', 'data' => $json['url']];
                    } else {
                        $qrImage = ['type' => 'binary', 'data' => $body];
                    }
                    break;
                }
            } catch (Exception $e) {
                Log::warning('Failed to generate QR code', [
                    'order_id' => $order->id,
                    'stt' => $stt,
                    'attempt' => $attempts,
                    'error' => $e->getMessage()
                ]);

                if ($attempts < $maxAttempts) {
                    sleep(2);
                }
            }
        }

        if (!$qrImage) {
            Log::warning('Failed to generate QR, creating placeholder', [
                'order_id' => $order->id,
                'stt' => $stt
            ]);

            DB::transaction(function () use ($item, $order, $stt) {
                OrderItemMeta::create([
                    'order_item_id' => $item->id,
                    'meta_key' => 'special_design_qr',
                    'meta_value' => "placeholder_qr_{$order->id}_{$stt}",
                    'switch' => 0,
                    'status' => false
                ]);
            });
            return;
        }

        // Handle QR image
        if ($qrImage['type'] === 'url') {
            $qrUrl = $qrImage['data'];
        } else {
            $fileName = "convert_qr/{$order->id}_{$item->id}_{$sStyle}_{$sSize}_{$sColor}_{$stt}_{$total}_qr.png";
            Storage::disk('b2')->put($fileName, $qrImage['data'], 'public');
            $qrUrl = Storage::disk('b2')->url($fileName);
        }

        // Save to OrderItemMeta
        DB::transaction(function () use ($item, $qrUrl) {
            OrderItemMeta::create([
                'order_item_id' => $item->id,
                'meta_key' => 'special_design_qr',
                'meta_value' => $qrUrl,
                'switch' => 0,
                'status' => false
            ]);
        });

        Log::info('Created QR code', [
            'order_id' => $order->id,
            'stt' => $stt,
            'url' => $qrUrl
        ]);
    }

    /**
     * Process PES conversion to JSON and DST
     */
    public function processConvert(Order $order, FeeCalculationService $feeService, int $tier): int
    {
        try {
            $urls = [];
            $checkedPES = 0;
            $itemOnePrice = [];

            // Collect PES files
            $items = OrderItem::with('metas')->where('order_id', $order->id)->get();
            $pushService = app(\App\Services\PushFileJsonToBackblazeService::class);

            foreach ($items as $item) {
                $itemMetas = $item->metas->whereIn('meta_key', ['front', 'back', 'sleeve_left', 'sleeve_right', 'neck']);

                if ($itemMetas->count() == 1) {
                    $itemOnePrice[] = $item->id;
                }

                foreach ($itemMetas as $meta) {
                    $pesUrl = $meta->meta_value;

                    // Convert Google Drive URL to B2 if needed
                    if (str_contains($pesUrl, 'drive.google.com')) {
                        try {
                            // Get variant for filename
                            $variant = ProductVariant::where('variant_id', $item->variant_id)->first();
                            $sStyle = preg_replace('/[^a-zA-Z0-9]/', '', $variant->style ?? 'Unknown');
                            $sSize = preg_replace('/[^a-zA-Z0-9]/', '', $variant->size ?? 'Unknown');
                            $sColor = preg_replace('/[^a-zA-Z0-9]/', '', $variant->color ?? 'Unknown');

                            $fileName = "{$order->id}_{$item->id}_{$meta->meta_key}_{$sStyle}_{$sSize}_{$sColor}.pes";
                            $result = $pushService->pushPesToBlaze(
                                $pesUrl,
                                $fileName,
                                env('B2_BUCKET', 'Lemiex-Fulfillment')
                            );
                            $pesUrl = $result['fileName'];

                            // Update meta value in DB
                            $meta->update(['meta_value' => $pesUrl]);

                            Log::info('processConvert: Converted Google Drive to B2', [
                                'order_id' => $order->id,
                                'item_id' => $item->id,
                                'meta_key' => $meta->meta_key,
                                'b2_url' => $pesUrl
                            ]);
                        } catch (Exception $e) {
                            Log::error('processConvert: Failed to convert Google Drive URL', [
                                'order_id' => $order->id,
                                'item_id' => $item->id,
                                'url' => $pesUrl,
                                'error' => $e->getMessage()
                            ]);
                            continue; // Skip this file
                        }
                    }

                    // Convert PES to JSON first
                    $this->convertPesToJson($pesUrl, $item->id, $meta->meta_key);

                    $urls[] = [
                        'side' => $meta->meta_key,
                        'item_id' => $item->id,
                        'url' => $pesUrl
                    ];

                    if (preg_match('/\.pes$/i', $pesUrl)) {
                        $checkedPES = 1;
                    }
                }
            }

            if ($checkedPES == 0) {
                Log::info('No PES files found, skipping conversion', ['order_id' => $order->id]);
                return 0;
            }

            // Convert PES to DST
            $jsonData = json_encode([
                'urls' => $urls,
                'order_id' => $order->id,
                'include_dst' => true
            ]);

            $rs = $this->convertPesToDst($jsonData);

            if (isset($rs->error)) {
                Log::error('Error from conversion service', [
                    'order_id' => $order->id,
                    'error' => $rs->error
                ]);
                return 0;
            }

            // Process conversion results
            $extraFee = 0;
            $refundFee = 0;
            $embroideryFee = 0;

            // Collect embroidery types from the embroidery_type COLUMN on PES/EMB meta records
            $embroideryTypes = [];
            foreach ($items as $item) {
                foreach (
                    $item->metas->whereIn('meta_key', [
                        'front',
                        'back',
                        'sleeve_left',
                        'sleeve_right',
                        'neck',
                        'front_emb',
                        'back_emb',
                        'sleeve_left_emb',
                        'sleeve_right_emb',
                        'neck_emb'
                    ]) as $meta
                ) {
                    if (!empty($meta->embroidery_type)) {
                        // Extract side name: 'front_emb' -> 'front', 'front' -> 'front'
                        $side = str_replace('_emb', '', $meta->meta_key);
                        $embroideryTypes[$item->id][$side] = $meta->embroidery_type;
                    }
                }
            }

            Log::info('Collected embroidery types for fee calculation', [
                'order_id' => $order->id,
                'embroidery_types' => $embroideryTypes
            ]);

            foreach ($rs->files as $file) {
                $itemId = $file->item_id;
                $side = $file->side;
                $stitchCount = $file->metadata->stitch_count ?? 0;

                Log::info("Order ID: {$order->id} - Item ID: {$itemId} - Side: {$side} - Stitch Count: {$stitchCount}");

                // Save DST URL with stitch count
                OrderItemMeta::updateOrCreate(
                    [
                        'order_item_id' => $itemId,
                        'meta_key' => $side . '_dst',
                    ],
                    [
                        'meta_value' => $file->dst_url,
                        'switch' => $stitchCount
                    ]
                );

                // Save info image
                OrderItemMeta::updateOrCreate(
                    [
                        'order_item_id' => $itemId,
                        'meta_key' => $side . '_pdf',
                    ],
                    [
                        'meta_value' => $file->info_image_url ?? '',
                        'switch' => 0
                    ]
                );

                // Calculate extra fee
                $extraFee += $feeService->calExtraFee($tier, $stitchCount);

                // Calculate embroidery fee based on embroidery type from the meta column
                $embType = $embroideryTypes[$itemId][$side] ?? 'standard';
                $embroideryFee += $feeService->calEmbroideryFee($tier, $embType, $stitchCount);

                Log::info('Embroidery fee for side', [
                    'order_id' => $order->id,
                    'item_id' => $itemId,
                    'side' => $side,
                    'embroidery_type' => $embType,
                    'stitch_count' => $stitchCount,
                    'fee' => $feeService->calEmbroideryFee($tier, $embType, $stitchCount)
                ]);
            }

            // Calculate refund fee for items with only 1 side
            foreach ($itemOnePrice as $itemId) {
                // Get stitch count for this item (use first side's stitch count)
                $itemMeta = OrderItemMeta::where('order_item_id', $itemId)
                    ->whereIn('meta_key', ['front_dst', 'back_dst', 'sleeve_left_dst', 'sleeve_right_dst', 'neck_dst'])
                    ->first();

                $stitchCount = $itemMeta ? $itemMeta->switch : 0;
                $refundFee += $feeService->calRefundFee($tier, $stitchCount);
            }

            // Update order with fees (including embroidery_fee)
            DB::transaction(function () use ($order, $extraFee, $refundFee, $embroideryFee) {
                $order->update([
                    'extra_fee' => $extraFee,
                    'refund_fee' => $refundFee,
                    'embroidery_fee' => $embroideryFee,
                ]);

                // Calculate total_cost: print + shipping + extra + embroidery - refund
                $order->refresh();
                $totalCost = $order->print_cost + $order->shipping_cost + $extraFee + $embroideryFee - $refundFee;
                $order->update(['total_cost' => $totalCost]);
            });

            Log::info('Updated order with conversion fees', [
                'order_id' => $order->id,
                'extra_fee' => $extraFee,
                'refund_fee' => $refundFee,
                'embroidery_fee' => $embroideryFee,
                'total_cost' => $order->total_cost
            ]);

            return 1;
        } catch (Exception $e) {
            Log::error('Failed to process PES conversion', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 0;
        }
    }

    /**
     * Convert PES to DST
     */
    public function convertPesToDst(string $json): object
    {
        try {
            $pesDstUrl = env('PES_TO_DST_SERVICE_URL', 'https://manage.lemiex.us/pes-api/convert-pes-to-dst');

            // Log request details for debugging
            $requestData = json_decode($json, true);
            Log::info('Converting PES to DST', [
                'service_url' => $pesDstUrl,
                'order_id' => $requestData['order_id'] ?? 'missing',
                'urls_count' => isset($requestData['urls']) ? count($requestData['urls']) : 0,
                'urls' => isset($requestData['urls']) ? array_map(fn($u) => [
                    'side' => $u['side'] ?? 'missing',
                    'item_id' => $u['item_id'] ?? 'missing',
                    'url' => $u['url'] ?? 'missing'
                ], $requestData['urls']) : [],
            ]);

            $response = Http::timeout(300)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->withBody($json, 'application/json')
                ->post($pesDstUrl);

            if ($response->failed()) {
                $errorMsg = "HTTP request failed with status: " . $response->status();
                Log::error("Convert PES to DST Error: " . $errorMsg, [
                    'status_code' => $response->status(),
                    'response_body' => substr($response->body(), 0, 2000), // Limit to 2000 chars
                    'service_url' => $pesDstUrl,
                    'request_url' => $requestData['url'] ?? 'missing',
                ]);
                return (object)['error' => $errorMsg];
            }

            $result = json_decode($response->body());

            Log::info('PES to DST conversion successful', [
                'dst_url' => $result->dst_url ?? 'missing',
                'pes_url' => $requestData['url'] ?? 'missing',
            ]);

            return $result;
        } catch (Exception $e) {
            Log::error("Convert PES to DST Error: " . $e->getMessage(), [
                'exception_class' => get_class($e),
                'trace' => substr($e->getTraceAsString(), 0, 1000),
            ]);
            return (object)['error' => $e->getMessage()];
        }
    }

    /**
     * Convert PES to JSON
     */
    public function convertPesToJson(string $url, int $orderItemId, string $side): void
    {
        try {
            $pesJsonUrl = env('PES_TO_JSON_SERVICE_URL', 'https://manage.lemiex.us/pes-api/convert-pes-to-json');
            $response = Http::timeout(30)->post($pesJsonUrl, [
                'url' => $url,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $jsonUrl = $data['url'] ?? '';

                if ($jsonUrl) {
                    OrderItemMeta::updateOrCreate(
                        [
                            'order_item_id' => $orderItemId,
                            'meta_key' => $side . '_json',
                        ],
                        [
                            'meta_value' => $jsonUrl,
                            'switch' => 0
                        ]
                    );
                }
            }
        } catch (Exception $e) {
            Log::error('Failed to convert PES to JSON', [
                'order_item_id' => $orderItemId,
                'side' => $side,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Process PES conversion for specific metas (used by remake feature)
     */
    public function processConvertForMetas($metas, Order $order, FeeCalculationService $feeService, int $tier): array
    {
        try {
            $urls = [];
            $hasPes = false;

            foreach ($metas as $meta) {
                $pesUrl = $meta->meta_value;

                // Check if PES file needs to be renamed (doesn't match expected format)
                if (preg_match('/\.pes$/i', $pesUrl)) {
                    $hasPes = true;

                    // Get variant for filename
                    $orderItem = OrderItem::find($meta->order_item_id);
                    $variant = ProductVariant::where('variant_id', $orderItem->variant_id)->first();
                    $sStyle = preg_replace('/[^a-zA-Z0-9]/', '', $variant->style ?? 'Unknown');
                    $sSize = preg_replace('/[^a-zA-Z0-9]/', '', $variant->size ?? 'Unknown');
                    $sColor = preg_replace('/[^a-zA-Z0-9]/', '', $variant->color ?? 'Unknown');

                    $expectedFileName = "{$order->id}_{$meta->order_item_id}_{$meta->meta_key}_{$sStyle}_{$sSize}_{$sColor}.pes";

                    // Check if current URL doesn't contain expected filename
                    if (!str_contains($pesUrl, $expectedFileName)) {
                        try {
                            $pushService = app(\App\Services\PushFileJsonToBackblazeService::class);
                            $bucketName = env('B2_BUCKET', 'Lemiex-Fulfillment');

                            $result = $pushService->pushPesToBlaze(
                                $pesUrl,
                                $expectedFileName,
                                $bucketName
                            );

                            // Delete old file if it's on B2
                            if (str_contains($pesUrl, 'backblazeb2.com') && str_contains($pesUrl, $bucketName . '/')) {
                                $parts = explode($bucketName . '/', $pesUrl, 2);
                                if (isset($parts[1])) {
                                    $oldPath = urldecode($parts[1]);
                                    Storage::disk('b2')->delete($oldPath);
                                    Log::info('Remake: Deleted old B2 file', ['path' => $oldPath]);
                                }
                            }

                            $pesUrl = $result['fileName'];

                            // Update meta value in DB with new URL
                            $meta->update(['meta_value' => $pesUrl]);

                            Log::info('Remake: Re-uploaded PES with new format', [
                                'order_id' => $order->id,
                                'item_id' => $meta->order_item_id,
                                'old_url' => $meta->getOriginal('meta_value'),
                                'new_url' => $pesUrl,
                                'expected_filename' => $expectedFileName
                            ]);
                        } catch (Exception $e) {
                            Log::error('Remake: Failed to re-upload PES', [
                                'url' => $pesUrl,
                                'expected_filename' => $expectedFileName,
                                'error' => $e->getMessage()
                            ]);
                            // Continue with original URL
                        }
                    }
                }

                $this->convertPesToJson($pesUrl, $meta->order_item_id, $meta->meta_key);

                $urls[] = [
                    'side' => $meta->meta_key,
                    'item_id' => $meta->order_item_id,
                    'url' => $pesUrl,
                    'meta_id' => $meta->id
                ];
            }

            if (!$hasPes) {
                return ['success' => false, 'message' => 'No PES files to convert'];
            }

            $jsonData = json_encode([
                'urls' => $urls,
                'order_id' => $order->id,
                'include_dst' => true
            ]);

            $result = $this->convertPesToDst($jsonData);

            if (isset($result->error)) {
                Log::error('Conversion service error', ['order_id' => $order->id, 'error' => $result->error]);
                return ['success' => false, 'message' => 'Conversion service error: ' . $result->error];
            }

            $processedMetas = [];

            foreach ($result->files as $file) {
                OrderItemMeta::updateOrCreate(
                    ['order_item_id' => $file->item_id, 'meta_key' => $file->side . '_dst'],
                    ['meta_value' => $file->dst_url, 'switch' => $file->metadata->stitch_count ?? 0]
                );

                if (isset($file->info_image_url)) {
                    OrderItemMeta::updateOrCreate(
                        ['order_item_id' => $file->item_id, 'meta_key' => $file->side . '_pdf'],
                        ['meta_value' => $file->info_image_url, 'switch' => 0]
                    );
                }

                $processedMetas[] = [
                    'item_id' => $file->item_id,
                    'side' => $file->side,
                    'stitch_count' => $file->metadata->stitch_count ?? 0,
                    'dst_url' => $file->dst_url
                ];
            }

            $this->recalculateOrderFees($order, $feeService, $tier);
            Log::info('Data remake file pes success', [
                'data' => $processedMetas,
            ]);
            return ['success' => true, 'processed_metas' => $processedMetas];
        } catch (Exception $e) {
            Log::error('PES conversion failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Recalculate order fees after remake
     */
    public function recalculateOrderFees(Order $order, FeeCalculationService $feeService, int $tier): void
    {
        $extraFee = 0;
        $refundFee = 0;
        $embroideryFee = 0;

        $items = OrderItem::with('metas')->where('order_id', $order->id)->get();

        // Collect embroidery types from the embroidery_type COLUMN on PES/EMB meta records
        $embroideryTypes = [];
        foreach ($items as $item) {
            foreach (
                $item->metas->whereIn('meta_key', [
                    'front',
                    'back',
                    'sleeve_left',
                    'sleeve_right',
                    'neck',
                    'front_emb',
                    'back_emb',
                    'sleeve_left_emb',
                    'sleeve_right_emb',
                    'neck_emb'
                ]) as $meta
            ) {
                if (!empty($meta->embroidery_type)) {
                    $side = str_replace('_emb', '', $meta->meta_key);
                    $embroideryTypes[$item->id][$side] = $meta->embroidery_type;
                }
            }
        }

        foreach ($items as $item) {
            $dstMetas = $item->metas->filter(fn($meta) => str_ends_with($meta->meta_key, '_dst'));
            $sideCount = $dstMetas->count();

            foreach ($dstMetas as $meta) {
                $stitchCount = $meta->switch ?? 0;
                $extraFee += $feeService->calExtraFee($tier, $stitchCount);

                if ($sideCount == 1) {
                    $refundFee += $feeService->calRefundFee($tier, $stitchCount);
                }

                // Calculate embroidery fee: extract side from meta_key (e.g. 'front_dst' -> 'front')
                $side = str_replace('_dst', '', $meta->meta_key);
                $embType = $embroideryTypes[$item->id][$side] ?? 'standard';
                $embroideryFee += $feeService->calEmbroideryFee($tier, $embType, $stitchCount);
            }
        }

        DB::transaction(function () use ($order, $extraFee, $refundFee, $embroideryFee) {
            $order->extra_fee = $extraFee;
            $order->refund_fee = $refundFee;
            $order->embroidery_fee = $embroideryFee;
            $order->total_cost = $order->print_cost + $order->shipping_cost + $extraFee + $embroideryFee - $refundFee;
            $order->save();
        });

        Log::info('Recalculated order fees', [
            'order_id' => $order->id,
            'extra_fee' => $extraFee,
            'refund_fee' => $refundFee,
            'embroidery_fee' => $embroideryFee,
            'total_cost' => $order->total_cost
        ]);
    }
}
