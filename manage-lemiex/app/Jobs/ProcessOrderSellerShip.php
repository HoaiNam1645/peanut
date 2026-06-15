<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\OrderService;
use App\Services\OrderPricingService;
use App\Services\FeeCalculationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Exception;

class ProcessOrderSellerShip implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 300;

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
    ): void
    {
        try {
            $order = Order::find($this->orderId);

            if (!$order) {
                Log::error('Order not found in job', ['order_id' => $this->orderId]);
                return;
            }

            Log::info('Processing SELLER SHIP order', ['order_id' => $this->orderId]);

            // Step 6-7: Create order items with design files
            $itemsResult = $orderService->createOrderItemsWithDesign($order, $this->lineItems);
            if (!$itemsResult['success']) {
                throw new Exception('Failed to create order items: ' . $itemsResult['message']);
            }

            // Step 8: Skip backup shipping label (no label for seller ship)
            Log::info('Skipping label backup for SELLER SHIP', ['order_id' => $this->orderId]);

            // Step 9: Skip label convert (no label)
            Log::info('Skipping label convert for SELLER SHIP', ['order_id' => $this->orderId]);

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

            // Step 12: Calculate pricing with SELLER shipping type
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
                "{$username} create {$order->order_stt} order (SELLER SHIP)"
            );

            // Step 14: Process PES conversion
            Log::info('Starting PES conversion', ['order_id' => $this->orderId]);
            $convertResult = $processingService->processConvert($order, $feeService, $this->tier);

            if ($convertResult > 0) {
                Log::info('PES conversion completed successfully', ['order_id' => $this->orderId]);
            } else {
                Log::info('PES conversion skipped or failed', ['order_id' => $this->orderId]);
            }

            Log::info('Successfully processed SELLER SHIP order', [
                'order_id' => $this->orderId,
                'total_cost' => $order->total_cost ?? 0
            ]);

        } catch (Exception $e) {
            Log::error('Failed to process SELLER SHIP order', [
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
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessOrderSellerShip job failed permanently', [
            'order_id' => $this->orderId,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);

        try {
            $order = Order::find($this->orderId);
            if ($order) {
                $order->update(['fulfill_status' => 'on_hold']);
            }
        } catch (Exception $e) {
            Log::error('Failed to update order status after job failure', [
                'order_id' => $this->orderId,
                'error' => $e->getMessage()
            ]);
        }
    }
}
