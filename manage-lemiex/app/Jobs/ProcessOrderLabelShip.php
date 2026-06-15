<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemMeta;
use App\Models\ProductVariant;
use App\Services\OrderService;
use App\Services\OrderPricingService;
use App\Services\FeeCalculationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Exception;

class ProcessOrderLabelShip implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 300; // 5 minutes (longer for PES conversion)

    protected $orderId;
    protected $lineItems;
    protected $tier;
    protected $store;

    public function __construct(int $orderId, array $lineItems, int $tier, $store)
    {
        $this->orderId = $orderId;
        $this->lineItems = $lineItems;
        $this->tier = $tier;
        $this->store = $store;
    }

    public function handle(
        OrderService $orderService,
        OrderPricingService $pricingService,
        FeeCalculationService $feeService,
        \App\Services\OrderProcessingService $processingService,
        \App\Services\WoodMergeImageService $woodMergeService
    ): void {
        try {
            $order = Order::find($this->orderId);

            if (!$order) {
                Log::error('Order not found in job', ['order_id' => $this->orderId]);
                return;
            }

            Log::info('Processing LABEL SHIP order', ['order_id' => $this->orderId]);

            // Step 6-7: Create order items with design files
            $itemsResult = $orderService->createOrderItemsWithDesign($order, $this->lineItems);
            if (!$itemsResult['success']) {
                throw new Exception('Failed to create order items: ' . $itemsResult['message']);
            }

            // Step 8: Backup shipping label
            Log::info('Starting label backup', ['order_id' => $this->orderId]);
            $this->backupShippingLabel($order);

            // Reload order to get updated shipping_label from B2
            $order->refresh();

            // Step 9: Post label convert
            Log::info('Starting label convert', ['order_id' => $this->orderId]);
            $this->postLabelConvert($order);

            // Step 10: Create QR codes (wood orders: batch + simple layout)
            Log::info('Starting QR code creation', ['order_id' => $this->orderId]);
            $processingService->createQRCodesBatchSimple($order);

            // Step 10.1: Generate merge_image (copy design PDF to B2 with customer-facing name)
            Log::info('Starting wood merge image generation', ['order_id' => $this->orderId]);
            $woodMergeService->generateForOrder($order);

            // Step 11: Create production records
            Log::info('Starting production records creation', ['order_id' => $this->orderId]);
            $productionResult = $orderService->createProductionRecords($order);
            if (!$productionResult['success'] && !isset($productionResult['skipped'])) {
                Log::warning('Failed to create production records', [
                    'order_id' => $this->orderId,
                    'error' => $productionResult['error'] ?? 'Unknown'
                ]);
            }

            // Step 12: Calculate pricing (with design fees)
            Log::info('Starting pricing calculation', ['order_id' => $this->orderId]);
            $pricingResult = $pricingService->calculateOrderPricingWithDesign($order, $this->tier, $this->lineItems);
            if (!$pricingResult['success']) {
                throw new Exception('Failed to calculate pricing: ' . $pricingResult['error']);
            }

            // Step 13: Create timeline
            $username = $this->store->user->username ?? 'Unknown';
            $orderService->createTimeline(
                $order,
                'create order',
                "{$username} create {$order->order_stt} order"
            );

            // Step 14: Process PES conversion
            Log::info('Starting PES conversion', ['order_id' => $this->orderId]);
            $convertResult = $processingService->processConvert($order, $feeService, $this->tier);

            if ($convertResult > 0) {
                Log::info('PES conversion completed successfully', ['order_id' => $this->orderId]);
            } else {
                Log::info('PES conversion skipped or failed', ['order_id' => $this->orderId]);
            }

            // Step 15: Dispatch sync jobs
            // SyncDropBox::dispatch($this->orderId, 'auto')->delay(now()->addMinute());
            // SyncPesDropBox::dispatch($this->orderId, 'auto')->delay(now()->addMinute());

            Log::info('Successfully processed LABEL SHIP order', [
                'order_id' => $this->orderId,
                'total_cost' => $order->total_cost ?? 0
            ]);
        } catch (Exception $e) {
            Log::error('Failed to process LABEL SHIP order', [
                'order_id' => $this->orderId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Update order status to failed
            if ($order ?? null) {
                $order->update(['fulfill_status' => 'on_hold']);
            }

            throw $e;
        }
    }

    /**
     * Backup shipping label to B2
     * Public static để có thể gọi từ UpdateOrder
     */
    public static function backupShippingLabel(Order $order): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'backup_label_');

        try {
            if (strpos($order->shipping_label, 'open-fs.tiktokshops.us') === false) {
                Log::info("Order {$order->id}: Shipping label is not TikTok URL, skip backup.");
                return;
            }

            $client = new \GuzzleHttp\Client([
                'timeout' => 60,
                'verify' => false,
            ]);

            Log::info("Order {$order->id}: Starting to download shipping label from: {$order->shipping_label}");

            $response = $client->get($order->shipping_label, [
                'sink' => $tempFile,
                'stream' => false, // Ensure full download before continuing
            ]);

            if ($response->getStatusCode() != 200) {
                throw new \Exception("Failed to download label from URL: {$order->shipping_label}, Status: {$response->getStatusCode()}");
            }

            // Ensure file is written and closed
            clearstatcache(true, $tempFile);

            Log::info("Order {$order->id}: Successfully downloaded shipping label to temp file");

            // Verify temp file has content
            $fileSize = filesize($tempFile);
            if ($fileSize === 0) {
                throw new \Exception("Downloaded file is empty (0 bytes)");
            }

            Log::info("Order {$order->id}: Temp file size: {$fileSize} bytes");

            // Read file content
            $fileContent = file_get_contents($tempFile);
            if (empty($fileContent)) {
                throw new \Exception("Failed to read content from temp file");
            }

            // Upload to B2
            $filename = "/label/original_{$order->id}_label.jpg";
            Storage::disk('b2')->put($filename, $fileContent, 'public');
            $uploadedUrl = env('B2_URL', 'https://s3.us-east-005.backblazeb2.com') . $filename;

            Log::info("Order {$order->id}: Shipping label uploaded to B2 - {$uploadedUrl}");

            Order::where('id', $order->id)->update([
                'shipping_label' => $uploadedUrl,
            ]);

            Log::info("Order {$order->id}: convert_label updated in database");
        } catch (\Exception $e) {
            Log::error("Order {$order->id}: Error backing up label - " . $e->getMessage());
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
                Log::debug("Temporary file removed: {$tempFile}");
            }
        }
    }

    /**
     * Post label convert to external service
     * Gọi API convert label và cập nhật tracking_id từ barcode (full tracking number)
     * Public static để có thể gọi từ UpdateOrder
     */
    public static function postLabelConvert(Order $order)
    {
        try {
            // Prepare items data
            $items = $order->items->map(function ($item) {
                return [
                    'order_id'   => (int) $item->order_id,
                    'quantity'   => (int) $item->quantity,
                    'variant_id' => (int) $item->variant_id,
                    'style'      => $item->productVariant->style ?? 'Unknown',
                ];
            })->toArray();

            $requestData = [
                'id' => $order->id,
                'shipping_label' => $order->shipping_label,
                'order_stt' => $order->order_stt,
                'items' => $items
            ];

            Log::info('Posting label convert request', [
                'order_id' => $order->id,
                'requestData' => $requestData
            ]);

            $labelConvertUrl = env('LABEL_CONVERT_SERVICE_URL', 'https://manage.lemiex.us/pes-api/label/convert');
            $response = Http::timeout(30)
                ->post($labelConvertUrl, $requestData);

            Log::info('Label convert request sent', [
                'order_id' => $order->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            // Xử lý response: cập nhật tracking_id từ barcode (full tracking number)
            if ($response->successful()) {
                $data = $response->json();

                if (!empty($data['tracking_id'])) {
                    $barcodeTracking = $data['tracking_id'];
                    $currentTracking = $order->tracking_id;

                    if ($barcodeTracking !== $currentTracking) {
                        Order::where('id', $order->id)->update([
                            'tracking_id' => $barcodeTracking,
                        ]);

                        Log::info('Tracking ID updated from label barcode', [
                            'order_id' => $order->id,
                            'old_tracking' => $currentTracking,
                            'new_tracking' => $barcodeTracking,
                        ]);
                    }
                }

                // Cập nhật convert_label nếu có
                if (!empty($data['link'])) {
                    Order::where('id', $order->id)->update([
                        'convert_label' => $data['link'],
                    ]);

                    Log::info('Convert label updated', [
                        'order_id' => $order->id,
                        'convert_label' => $data['link'],
                    ]);
                }
            }

            return $response;
        } catch (Exception $e) {
            Log::warning('Failed to post label convert', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Create QR codes for each item unit
     */
    protected function createQRCodes(Order $order): void
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
     * Create single QR code
     */
    protected function createSingleQR(Order $order, OrderItem $item, $variant, int $stt, int $total, int $itemIndex = 1): void
    {
        // Normalize color name: convert to PascalCase (no spaces) for QR service
        $color = $variant->color ?? 'Unknown';
        $color = str_replace(['_', ' '], ' ', $color); // Normalize separators to space
        $color = ucwords(strtolower($color)); // Title Case first
        $color = str_replace(' ', '', $color); // Remove all spaces → "ForestGreen"

        // Sanitize style, size, color for filename
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
            'pageqr' => env('FRONTEND_TRACKING_URL', env('FRONTEND_URL')) . "/track/{$order->id}?stt={$stt}&item_id={$item->id}&item_stt={$itemIndex}"
        ];

        // Call external QR service with retry
        $attempts = 0;
        $maxAttempts = 3;
        $qrImage = null;

        while ($attempts < $maxAttempts && !$qrImage) {
            try {
                $attempts++;

                $qrStorageUrl = env('QR_STORAGE_URL', 'https://s3.us-east-005.backblazeb2.com/Lemiex-Fulfillment/convert_qr');
                $qrData['dst_url'] = "{$qrStorageUrl}/{$order->id}_{$item->id}_{$sStyle}_{$sSize}_{$sColor}_{$stt}_{$total}.png";

                // Gửi GET request với cấu hình SSL
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
            Storage::disk('b2')->put($fileName, $qrImage['data'], 'public'); // ← Thêm 'public'
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
     * Process PES conversion
     */

    protected function processConvert(Order $order, FeeCalculationService $feeService): int
    {
        try {
            $urls = [];
            $checkedPES = 0;
            $itemOnePrice = [];

            // Step 14.1: Collect PES files and identify items with 1 side
            $items = OrderItem::with('metas')->where('order_id', $order->id)->get();

            foreach ($items as $item) {
                $itemMetas = $item->metas->whereIn('meta_key', ['front', 'back', 'sleeve_left', 'sleeve_right', 'neck']);

                // Identify items with only 1 side for refund calculation
                if ($itemMetas->count() == 1) {
                    $itemOnePrice[] = $item->id;
                }

                foreach ($itemMetas as $meta) {
                    // Convert PES to JSON first
                    $this->convertPesToJson($meta->meta_value, $item->id, $meta->meta_key);

                    $urls[] = [
                        'side' => $meta->meta_key,
                        'item_id' => $item->id,
                        'url' => $meta->meta_value
                    ];

                    if (preg_match('/\.pes$/i', $meta->meta_value)) {
                        $checkedPES = 1;
                    }
                }
            }

            // Step 14.2: Check if we have PES files
            if ($checkedPES == 0) {
                Log::info('No PES files found, skipping conversion', ['order_id' => $order->id]);
                return 0;
            }

            // Step 14.4: Convert PES to DST
            $jsonData = [
                'urls' => $urls,
                'order_id' => $order->id,
                'include_dst' => true
            ];

            $rs = $this->convertPesToDst(json_encode($jsonData));

            if (isset($rs->error)) {
                Log::error('Error from conversion service', [
                    'order_id' => $order->id,
                    'error' => $rs->error
                ]);
                return 0;
            }

            // Step 14.5: Process conversion results
            $extraFee = 0;
            $refundFee = 0;
            $embroideryFee = 0;
            $tierId = $this->tier;

            // Collect embroidery types from the embroidery_type COLUMN on PES/EMB meta records
            // The embroidery_type is stored as a column on meta records with keys like:
            // 'front', 'back', 'front_emb', 'back_emb', etc.
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

                Log::channel('cal-stitch')->info(
                    "Order ID: {$order->id} - Item ID: {$itemId} - " .
                        "Side: {$side} - Stitch Count: {$stitchCount}"
                );

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
                        'meta_value' => $file->info_image_url,
                    ]
                );

                // Calculate extra fee
                $extraFee += $feeService->calExtraFee($tierId, $stitchCount);

                // Calculate refund fee for items with 1 side only
                if (in_array($itemId, $itemOnePrice)) {
                    $refundFee += $feeService->calRefundFee($tierId, $stitchCount);
                }

                // Calculate embroidery fee based on embroidery type from the meta column
                $embType = $embroideryTypes[$itemId][$side] ?? 'standard';
                $embroideryFee += $feeService->calEmbroideryFee($tierId, $embType, $stitchCount);

                Log::info('Embroidery fee for side', [
                    'order_id' => $order->id,
                    'item_id' => $itemId,
                    'side' => $side,
                    'embroidery_type' => $embType,
                    'stitch_count' => $stitchCount,
                    'fee' => $feeService->calEmbroideryFee($tierId, $embType, $stitchCount)
                ]);
            }

            // Step 14.6: Update order with fees
            $order->merged_url = $rs->merged_jpg_url ?? null;
            $order->extra_fee = $extraFee;
            $order->refund_fee = $refundFee;
            $order->embroidery_fee = $embroideryFee;

            // Adjust total cost
            if ($order->refund_fee > 0) {
                $order->total_cost -= $order->refund_fee;
            }
            if ($order->extra_fee > 0) {
                $order->total_cost += $order->extra_fee;
            }
            if ($order->embroidery_fee > 0) {
                $order->total_cost += $order->embroidery_fee;
            }

            $order->save();

            Log::info('Order updated with conversion fees', [
                'order_id' => $order->id,
                'extra_fee' => $order->extra_fee,
                'refund_fee' => $order->refund_fee,
                'embroidery_fee' => $order->embroidery_fee,
                'total_cost' => $order->total_cost
            ]);

            return 1; // Success

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
     * Convert PES to JSON
     */
    protected function convertPesToJson(string $url, int $orderItemId, string $side): void
    {
        try {
            $pesJsonUrl = env('PES_TO_JSON_SERVICE_URL', 'http://feline.ink/convert-pes-to-json');
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

                    Log::info('PES to JSON conversion successful', [
                        'order_item_id' => $orderItemId,
                        'side' => $side,
                        'json_url' => $jsonUrl
                    ]);
                }
            } else {
                Log::warning('PES to JSON conversion failed', [
                    'order_item_id' => $orderItemId,
                    'side' => $side,
                    'status' => $response->status()
                ]);
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
     * Convert PES to DST using HTTP client
     */
    protected function convertPesToDst(string $json): object
    {
        try {
            $pesDstUrl = env('PES_TO_DST_SERVICE_URL', 'http://feline.ink/process');

            // Log request details for debugging
            $requestData = json_decode($json, true);
            Log::info('Converting PES to DST (Job)', [
                'service_url' => $pesDstUrl,
                'order_id' => $requestData['order_id'] ?? 'missing',
                'urls_count' => isset($requestData['urls']) ? count($requestData['urls']) : 0,
            ]);

            $response = Http::timeout(300)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                ])
                ->withBody($json, 'application/json')
                ->post($pesDstUrl);

            if ($response->failed()) {
                $errorMsg = "HTTP request failed with status: " . $response->status();
                Log::error("Convert PES to DST Error: " . $errorMsg, [
                    'status_code' => $response->status(),
                    'response_body' => substr($response->body(), 0, 2000),
                    'service_url' => $pesDstUrl,
                    'order_id' => $requestData['order_id'] ?? 'missing',
                ]);
                return (object)['error' => $errorMsg];
            }

            $result = json_decode($response->body());

            Log::info('PES to DST conversion successful (Job)', [
                'order_id' => $requestData['order_id'] ?? 'missing',
                'files_count' => isset($result->files) ? count($result->files) : 0,
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
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessOrderLabelShip job failed permanently', [
            'order_id' => $this->orderId,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);

        $order = Order::find($this->orderId);
        if ($order) {
            $order->update(['fulfill_status' => 'on_hold']);
        }
    }
}
