<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemMeta;
use App\Models\ProductVariant;
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

/**
 * Process Tumbler orders
 * 
 * Tumbler orders are simpler than embroidery orders:
 * - Only accept image files (PNG/JPG), no PES/EMB
 * - No stitch count, no extra/refund fees
 * - No PES conversion needed
 */
class ProcessOrderTumbler implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 120; // 2 minutes (shorter than embroidery)

    protected $orderId;
    protected $lineItems;
    protected $tier;
    protected $store;
    protected $hasShippingLabel;

    public function __construct(int $orderId, array $lineItems, int $tier, $store, bool $hasShippingLabel = false)
    {
        $this->orderId = $orderId;
        $this->lineItems = $lineItems;
        $this->tier = $tier;
        $this->store = $store;
        $this->hasShippingLabel = $hasShippingLabel;
    }

    public function handle(
        OrderService $orderService,
        OrderPricingService $pricingService,
        \App\Services\OrderProcessingService $processingService
    ): void {
        try {
            $order = Order::find($this->orderId);

            if (!$order) {
                Log::error('Tumbler order not found in job', ['order_id' => $this->orderId]);
                return;
            }

            Log::info('Processing TUMBLER order', [
                'order_id' => $this->orderId,
                'has_shipping_label' => $this->hasShippingLabel
            ]);

            // Step 1: Create order items with design images (no PES/EMB)
            $itemsResult = $this->createOrderItemsForTumbler($order, $this->lineItems);
            if (!$itemsResult['success']) {
                throw new Exception('Failed to create tumbler order items: ' . $itemsResult['message']);
            }

            // Step 2: Handle shipping label (if label_ship)
            if ($this->hasShippingLabel) {
                Log::info('Processing shipping label for Tumbler', ['order_id' => $this->orderId]);
                ProcessOrderLabelShip::backupShippingLabel($order);
                $order->refresh();
                ProcessOrderLabelShip::postLabelConvert($order);
            }

            // Step 3: Create QR codes
            Log::info('Creating QR codes for Tumbler order', ['order_id' => $this->orderId]);
            $processingService->createQRCodes($order);

            // Step 3.5: Merge Design & QR
            $this->mergeTumblerImages($order);

            // Step 4: Create production records
            Log::info('Creating production records for Tumbler', ['order_id' => $this->orderId]);
            $productionResult = $orderService->createProductionRecords($order);
            if (!$productionResult['success'] && !isset($productionResult['skipped'])) {
                Log::warning('Failed to create production records for Tumbler', [
                    'order_id' => $this->orderId,
                    'error' => $productionResult['error'] ?? 'Unknown'
                ]);
            }

            // Step 5: Calculate pricing (simpler for Tumbler - no extra fees)
            Log::info('Calculating pricing for Tumbler order', ['order_id' => $this->orderId]);
            $pricingResult = $this->calculateTumblerPricing($order, $pricingService);
            if (!$pricingResult['success']) {
                throw new Exception('Failed to calculate Tumbler pricing: ' . $pricingResult['error']);
            }

            // Step 6: Create timeline
            $username = $this->store->user->username ?? 'Unknown';
            $orderService->createTimeline(
                $order,
                'create order',
                "{$username} created Tumbler order {$order->order_stt}"
            );

            // No PES conversion needed for Tumbler orders

            Log::info('Successfully processed TUMBLER order', [
                'order_id' => $this->orderId,
                'total_cost' => $order->total_cost ?? 0
            ]);
        } catch (Exception $e) {
            Log::error('Failed to process TUMBLER order', [
                'order_id' => $this->orderId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            if ($order ?? null) {
                $order->update(['fulfill_status' => 'on_hold']);
            }

            throw $e;
        }
    }

    /**
     * Create order items for Tumbler (only images, no PES/EMB)
     */
    protected function createOrderItemsForTumbler(Order $order, array $lineItems): array
    {
        try {
            DB::beginTransaction();

            // Delete old items if any (for retry scenarios)
            OrderItem::where('order_id', $order->id)->delete();

            $createdItems = [];

            foreach ($lineItems as $item) {
                // Create OrderItem
                $orderItem = OrderItem::create([
                    'order_id' => $order->id,
                    'variant_id' => $item['variant_id'],
                    'product_name' => $item['product_name'],
                    'quantity' => $item['quantity'],
                    'mockup' => $item['mockup'] ?? null,
                    'mockup_back' => null, // Tumbler typically only has one view
                    'price' => null,
                    'status' => false,
                    'sides' => 1 // Tumbler has only 1 print surface
                ]);

                // Process print_files - only images for Tumbler
                $printFiles = $item['print_files'] ?? [];
                foreach ($printFiles as $file) {
                    $key = $file['key']; // wrap, front
                    $url = $file['url'] ?? null;

                    if (!$url) {
                        continue;
                    }

                    // Save image URL to metas
                    // For Tumbler, we save as {key}_image to differentiate from embroidery
                    OrderItemMeta::create([
                        'order_item_id' => $orderItem->id,
                        'meta_key' => $key . '_image',
                        'meta_value' => $url,
                        'switch' => 0,
                        'status' => false
                    ]);

                    Log::info('Saved Tumbler design image', [
                        'order_item_id' => $orderItem->id,
                        'key' => $key,
                        'url' => $url
                    ]);
                }

                $createdItems[] = $orderItem;
            }

            DB::commit();

            return [
                'success' => true,
                'items' => $createdItems
            ];
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to create Tumbler order items', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Calculate pricing for Tumbler orders
     * Simpler than embroidery - no extra/refund/embroidery fees
     */
    protected function calculateTumblerPricing(Order $order, OrderPricingService $pricingService): array
    {
        try {
            DB::beginTransaction();

            $totalPrintCost = 0;
            $items = OrderItem::where('order_id', $order->id)->get();

            foreach ($items as $item) {
                $itemCost = $this->calculateTumblerItemCost($item, $this->tier);
                $item->update(['price' => $itemCost]);
                $totalPrintCost += $itemCost;
            }

            // Calculate shipping cost (same logic as embroidery)
            $shippingCost = $this->calculateShippingCost($order, $items->first(), $this->tier);

            // Calculate priority fee
            $priorityFee = $order->priority_fee ?? 0;

            // Total cost (no extra/refund fees for Tumbler)
            $totalCost = $totalPrintCost + $shippingCost + $priorityFee;

            // Update order
            $order->update([
                'print_cost' => $totalPrintCost,
                'shipping_cost' => $shippingCost,
                'total_cost' => $totalCost,
                'extra_fee' => 0.00,
                'refund_fee' => 0.00,
                'embroidery_fee' => 0.00
            ]);

            DB::commit();

            Log::info('Tumbler pricing calculated', [
                'order_id' => $order->id,
                'print_cost' => $totalPrintCost,
                'shipping_cost' => $shippingCost,
                'priority_fee' => $priorityFee,
                'total_cost' => $totalCost
            ]);

            return [
                'success' => true,
                'print_cost' => $totalPrintCost,
                'shipping_cost' => $shippingCost,
                'total_cost' => $totalCost
            ];
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to calculate Tumbler pricing', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Calculate cost for single Tumbler item
     */
    protected function calculateTumblerItemCost(OrderItem $item, int $tier): float
    {
        $variant = ProductVariant::with('priceVariants')
            ->where('variant_id', $item->variant_id)
            ->first();

        if (!$variant) {
            Log::warning('Tumbler product variant not found', [
                'variant_id' => $item->variant_id,
                'order_item_id' => $item->id
            ]);
            return 0;
        }

        // Get base cost based on tier
        $baseCostPrice = $variant->priceVariants
            ->where('tier_id', $tier)
            ->where('type', 'base_cost')
            ->first();

        $baseCost = $baseCostPrice ? $baseCostPrice->price : ($variant->supplier_price ?? 0);

        // Tumbler: only base cost × quantity (no two-side, sleeve fees)
        return round($baseCost * $item->quantity, 2);
    }

    /**
     * Calculate shipping cost for Tumbler
     */
    protected function calculateShippingCost(Order $order, ?OrderItem $firstItem, int $tier): float
    {
        if (!$firstItem) {
            return 0;
        }

        $variant = ProductVariant::with('priceVariants')
            ->where('variant_id', $firstItem->variant_id)
            ->first();

        if (!$variant) {
            return 0;
        }

        $hasShippingLabel = !empty($order->shipping_label);

        if ($hasShippingLabel) {
            // TikTok/Label shipping
            $tiktokShipping = $variant->priceVariants
                ->where('tier_id', $tier)
                ->where('type', 'tiktok_shipping')
                ->first();

            if ($tiktokShipping) {
                return round($tiktokShipping->price, 2);
            }

            // Fallback rates
            return match ($tier) {
                3 => 0.75,
                2 => 0.50,
                1 => 0.50,
                default => 0.50
            };
        }

        // Seller shipping
        $sellerShipping = $variant->priceVariants
            ->where('tier_id', $tier)
            ->where('type', 'seller_shipping')
            ->first();

        $baseShipping = $sellerShipping ? $sellerShipping->price : 0;

        // Add additional items cost
        $items = OrderItem::where('order_id', $order->id)->get();
        $additionalCost = 0;

        foreach ($items as $index => $item) {
            if ($index === 0) {
                continue; // Skip first item
            }

            // Tumbler additional item rate (can be adjusted)
            $rate = 1.99;
            $additionalCost += $rate * $item->quantity;
        }

        return round($baseShipping + $additionalCost, 2);
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessOrderTumbler job failed permanently', [
            'order_id' => $this->orderId,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);

        $order = Order::find($this->orderId);
        if ($order) {
            $order->update(['fulfill_status' => 'on_hold']);
        }
    }

    /**
     * Merge Tumbler design with QR codes
     */
    protected function mergeTumblerImages(Order $order): void
    {
        Log::info('Merging Tumbler images', ['order_id' => $order->id]);

        $items = OrderItem::where('order_id', $order->id)->get();

        foreach ($items as $item) {
            // 1. Get design image (front or wrap)
            $designMeta = OrderItemMeta::where('order_item_id', $item->id)
                ->whereIn('meta_key', ['front_image', 'wrap_image'])
                ->first();

            if (!$designMeta) {
                // Try finding any image meta
                $designMeta = OrderItemMeta::where('order_item_id', $item->id)
                    ->where('meta_key', 'like', '%_image')
                    ->where('meta_key', '!=', 'merge_image')
                    ->first();
            }

            if (!$designMeta) {
                Log::warning('No design image found for item', ['item_id' => $item->id]);
                continue;
            }

            $designUrl = $designMeta->meta_value;

            // 2. Get QR codes
            $qrMetas = OrderItemMeta::where('order_item_id', $item->id)
                ->where('meta_key', 'special_design_qr')
                ->get();

            if ($qrMetas->isEmpty()) {
                Log::warning('No QR codes found for item', ['item_id' => $item->id]);
                continue;
            }

            $qrUrls = $qrMetas->pluck('meta_value')->toArray();

            // 3. Call Merge Service
            try {
                $mergeUrl = env('MERGE_IMAGE_SERVICE_URL', 'https://manage.lemiex.us/pes-api/merge-image');

                $payload = [
                    'url_image' => $designUrl,
                    'url_qr' => $qrUrls
                ];

                Log::info('Calling Merge Image Service', [
                    'order_id' => $order->id,
                    'item_id' => $item->id,
                    'qr_count' => count($qrUrls),
                    'url' => $mergeUrl
                ]);

                $response = Http::timeout(120) // Long timeout for image processing
                    ->withOptions(['verify' => config('app.http_verify_ssl', true)])
                    ->post($mergeUrl, $payload);

                if ($response->successful()) {
                    $data = $response->json();
                    $mergedUrls = $data['urls'] ?? [];

                    Log::info('Merge Service successful', [
                        'item_id' => $item->id,
                        'merged_count' => count($mergedUrls)
                    ]);

                    // 4. Save merged images to metas
                    DB::transaction(function () use ($item, $mergedUrls) {
                        foreach ($mergedUrls as $url) {
                            OrderItemMeta::create([
                                'order_item_id' => $item->id,
                                'meta_key' => 'merge_image',
                                'meta_value' => $url,
                                'switch' => 0,
                                'status' => false
                            ]);
                        }
                    });
                } else {
                    Log::error('Merge Service failed', [
                        'item_id' => $item->id,
                        'status' => $response->status(),
                        'body' => $response->body()
                    ]);
                }
            } catch (Exception $e) {
                Log::error('Exception calling Merge Service', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }
}
