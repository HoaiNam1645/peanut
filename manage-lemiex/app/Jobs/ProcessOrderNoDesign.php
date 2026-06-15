<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemMeta;
use App\Services\OrderService;
use App\Services\OrderPricingService;
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

class ProcessOrderNoDesign implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 120; // 2 minutes

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

    public function handle(OrderService $orderService, OrderPricingService $pricingService): void
    {
        try {
            $order = Order::find($this->orderId);

            if (!$order) {
                Log::error('Order not found in job', ['order_id' => $this->orderId]);
                return;
            }

            // Log::info('Processing NO DESIGN order', ['order_id' => $this->orderId]);

            // // Step 6-7: Create order items
            $itemsResult = $orderService->createOrderItems($order, $this->lineItems);
            if (!$itemsResult['success']) {
                throw new Exception('Failed to create order items: ' . $itemsResult['message']);
            }

            // // Step 9: Create QR codes
            Log::info('Starting QR code creation', ['order_id' => $this->orderId]);
            $this->createQRCodes($order);
            Log::info('Completed QR code creation', ['order_id' => $this->orderId]);

            // Step 10: Create production records
            Log::info('Starting production records creation', ['order_id' => $this->orderId]);
            $productionResult = $orderService->createProductionRecords($order);
            if (!$productionResult['success'] && !isset($productionResult['skipped'])) {
                Log::warning('Failed to create production records', [
                    'order_id' => $this->orderId,
                    'error' => $productionResult['error'] ?? 'Unknown'
                ]);
            }

            // Step 11: Calculate pricing
            $pricingResult = $pricingService->calculateOrderPricing($order, $this->tier);
            if (!$pricingResult['success']) {
                throw new Exception('Failed to calculate pricing: ' . $pricingResult['error']);
            }

            // Step 12: Create timeline
            $username = $this->store->user->username ?? 'Unknown';
            $orderService->createTimeline(
                $order,
                'create order',
                "{$username} create {$order->order_stt} order"
            );

            // // Step 13: Skip convert process (no PES files for NO DESIGN)
            // Log::info('Skipping convert process for NO DESIGN order', ['order_id' => $this->orderId]);

            // Step 14: Dispatch sync jobs
            // SyncDropBox::dispatch($this->orderId, 'auto')->delay(now()->addMinute());
            // SyncPesDropBox::dispatch($this->orderId, 'auto')->delay(now()->addMinute());

            Log::info('Successfully processed NO DESIGN order', [
                'order_id' => $this->orderId,
                'total_cost' => $pricingResult['total_cost'] ?? 0
            ]);
        } catch (Exception $e) {
            Log::error('Failed to process NO DESIGN order', [
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
     * Backup shipping label from TikTok to B2
     */
    protected function backupShippingLabel(Order $order): void
    {
        try {
            // Check if it's TikTok URL
            if (!str_contains($order->shipping_label, 'open-fs.tiktokshops.us')) {
                Log::info('Skipping label backup - not TikTok URL', ['order_id' => $order->id]);
                return;
            }

            // Download label with retry
            $attempts = 0;
            $maxAttempts = 3;
            $labelContent = null;

            while ($attempts < $maxAttempts && !$labelContent) {
                try {
                    $attempts++;
                    Log::info('Attempting to download shipping label', [
                        'order_id' => $order->id,
                        'attempt' => $attempts,
                        'url' => $order->shipping_label
                    ]);

                    $response = Http::timeout(10)
                        ->withHeaders([
                            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                            'Accept' => 'image/*, application/pdf, */*',
                            'Accept-Language' => 'en-US,en;q=0.9',
                        ])
                        ->withOptions([
                            'verify' => false, // Skip SSL verification for testing
                        ])
                        ->get($order->shipping_label);

                    Log::info('HTTP Response details', [
                        'order_id' => $order->id,
                        'status' => $response->status(),
                        'headers' => $response->headers(),
                        'body_size' => strlen($response->body())
                    ]);

                    if ($response->successful()) {
                        $labelContent = $response->body();
                        Log::info('Successfully downloaded shipping label', ['order_id' => $order->id]);
                        break;
                    } else {
                        Log::warning('HTTP request failed', [
                            'order_id' => $order->id,
                            'status' => $response->status(),
                            'body' => substr($response->body(), 0, 500) // First 500 chars
                        ]);

                        // If 404, don't retry
                        if ($response->status() === 404) {
                            Log::info('Label URL not found (404), stopping retries', [
                                'order_id' => $order->id,
                                'url' => $order->shipping_label
                            ]);
                            break;
                        }
                    }
                } catch (Exception $e) {
                    Log::warning('Failed to download shipping label', [
                        'order_id' => $order->id,
                        'attempt' => $attempts,
                        'error' => $e->getMessage()
                    ]);

                    if ($attempts < $maxAttempts) {
                        sleep(2); // Fixed 2 second delay
                    }
                }
            }

            if (!$labelContent) {
                Log::warning('Failed to download TikTok label after retries, creating placeholder', ['order_id' => $order->id]);

                // Create placeholder label info
                $placeholderUrl = $order->shipping_label; // Keep original URL

                // Update order with placeholder info (optional)
                DB::transaction(function () use ($order, $placeholderUrl) {
                    // Could save to order_metas if needed
                    DB::table('order_metas')->updateOrInsert(
                        [
                            'object_id' => $order->id,
                            'meta_key' => 'shipping_label_backup_status'
                        ],
                        [
                            'meta_value' => json_encode([
                                'status' => 'failed',
                                'original_url' => $placeholderUrl,
                                'attempts' => 3,
                                'last_attempt' => now()->toIso8601String()
                            ]),
                            'updated_at' => now()
                        ]
                    );
                });

                return;
            }

            // Save to storage
            $fileName = "label/original_{$order->id}_label.jpg";
            Storage::disk('public')->put($fileName, $labelContent);

            // TODO: Upload to B2 if configured
            // $b2Url = $this->uploadToB2($fileName, $labelContent);

            // For now, use local storage URL
            $newUrl = asset('storage/' . $fileName);

            // Update order with new URL
            DB::transaction(function () use ($order, $newUrl) {
                $order->update(['shipping_label' => $newUrl]);
            });

            Log::info('Successfully backed up shipping label', [
                'order_id' => $order->id,
                'new_url' => $newUrl
            ]);
        } catch (Exception $e) {
            Log::warning('Failed to backup shipping label', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
            // Don't throw - this shouldn't block the order
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
                $variant = \App\Models\ProductVariant::where('variant_id', $item->variant_id)->first();

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
        // Replace spaces in color to avoid URL encoding issues
        $color = $variant->color ?? 'Unknown';
        $color = str_replace(' ', '_', $color);

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

        // Call external QR service with retry
        $attempts = 0;
        $maxAttempts = 3;
        $qrImage = null;

        while ($attempts < $maxAttempts && !$qrImage) {
            try {
                $attempts++;

                Log::info('Attempting to generate QR code', [
                    'order_id' => $order->id,
                    'stt' => $stt,
                    'attempt' => $attempts
                ]);

                // Tạo đường dẫn lưu QR
                $qrStorageUrl = env('QR_STORAGE_URL', 'https://s3.us-east-005.backblazeb2.com/Lemiex-Fulfillment/convert_qr');
                $qrData['dst_url'] = "{$qrStorageUrl}/{$order->id}_{$item->id}_{$stt}_{$total}.png";

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

                // Response OK?
                if ($response->successful()) {
                    $body = $response->body();

                    // Thử parse JSON
                    $json = json_decode($body, true);

                    if (json_last_error() === JSON_ERROR_NONE && isset($json['url'])) {
                        // API trả về đường dẫn ảnh
                        $qrImage = [
                            'type' => 'url',
                            'data' => $json['url']
                        ];
                    } else {
                        // API trả về binary PNG
                        $qrImage = [
                            'type' => 'binary',
                            'data' => $body
                        ];
                    }

                    Log::info('Successfully generated QR code', [
                        'order_id' => $order->id,
                        'stt'      => $stt,
                        'type'     => $qrImage['type'],
                    ]);

                    break;
                }

                // Response FAIL (400/500)
                Log::warning('QR API returned error', [
                    'order_id' => $order->id,
                    'stt'      => $stt,
                    'status'   => $response->status(),
                    'body'     => $response->body()
                ]);
            } catch (Exception $e) {
                Log::warning('Failed to generate QR code - Exception caught', [
                    'order_id' => $order->id,
                    'stt'      => $stt,
                    'attempt'  => $attempts,
                    'error'    => $e->getMessage(),
                    'error_class' => get_class($e),
                    'trace'    => $e->getTraceAsString()
                ]);

                if ($attempts < $maxAttempts) {
                    sleep(2); // retry sau 2 giây
                }
            }
        }



        if (!$qrImage) {
            Log::warning('Failed to generate QR from external service, creating placeholder', [
                'order_id' => $order->id,
                'stt' => $stt
            ]);

            // Create placeholder QR meta without actual image
            DB::transaction(function () use ($item, $order, $stt, $total) {
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

        // Handle QR image based on type
        if ($qrImage['type'] === 'url') {
            // Use the external URL directly
            $qrUrl = $qrImage['data'];

            Log::info('Using external QR URL', [
                'order_id' => $order->id,
                'stt' => $stt,
                'url' => $qrUrl
            ]);
        } else {
            // Save binary image data locally
            $fileName = "convert_qr/{$order->id}_{$item->id}_{$stt}_{$total}_qr.png";
            Storage::disk('public')->put($fileName, $qrImage['data']);

            // TODO: Upload to B2 if configured
            $qrUrl = config('app.url') . '/storage/' . $fileName;

            Log::info('Saved QR image locally', [
                'order_id' => $order->id,
                'stt' => $stt,
                'file' => $fileName
            ]);
        }

        // Save to OrderItemMeta
        DB::transaction(function () use ($item, $order, $stt, $total, $qrUrl) {
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
     * Handle job failure
     */
    public function failed(Exception $exception): void
    {
        Log::error('ProcessOrderNoDesign job failed permanently', [
            'order_id' => $this->orderId,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);

        // Update order status
        $order = Order::find($this->orderId);
        if ($order) {
            $order->update(['fulfill_status' => 'on_hold']);
        }

        // TODO: Send alert to admin
    }
}
