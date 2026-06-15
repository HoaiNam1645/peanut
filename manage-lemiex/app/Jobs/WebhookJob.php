<?php

namespace App\Jobs;

use App\Models\Order;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $orderId;
    public int $tries = 3;
    public int $timeout = 30;

    /**
     * Create a new job instance.
     */
    public function __construct(int $orderId)
    {
        $this->orderId = $orderId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info("Start webhook notification", ['order_id' => $this->orderId]);

        try {
            $order = Order::with(['seller.profile', 'store'])->find($this->orderId);

            if (!$order) {
                Log::warning("Order not found", ['order_id' => $this->orderId]);
                return;
            }

            // Get webhook URL from seller's profile
            if (!$order->seller || !$order->seller->profile || empty($order->seller->profile->webhook_url)) {
                Log::info("No webhook URL configured", [
                    'order_id' => $this->orderId,
                    'seller_id' => $order->seller_id,
                ]);
                return;
            }

            $webhookUrl = $order->seller->profile->webhook_url;
            $apiKey = $order->store->api_key ?? '';

            $payload = [
                'id' => $order->id,
                'ref_id' => $order->ref_id,
                'status' => $order->fulfill_status,
                'total_price' => $order->total_cost,
                'tracking' => $order->tracking_id,
                'api_key' => $apiKey,
            ];

            Log::info("Sending webhook", [
                'order_id' => $this->orderId,
                'url' => $webhookUrl,
                'payload' => $payload,
            ]);

            $response = Http::timeout(15)->post($webhookUrl, $payload);

            if ($response->successful()) {
                Log::info("Webhook sent successfully", [
                    'order_id' => $this->orderId,
                    'status' => $response->status(),
                    'response_body' => $response->body(),
                ]);
            } else {
                Log::warning("Webhook failed", [
                    'order_id' => $this->orderId,
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);
            }

        } catch (Exception $e) {
            Log::error("Webhook exception", [
                'order_id' => $this->orderId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e; // Re-throw to trigger retry
        }
    }

    /**
     * Handle job failure
     */
    public function failed(Exception $exception): void
    {
        Log::error("Webhook job failed permanently", [
            'order_id' => $this->orderId,
            'error' => $exception->getMessage(),
        ]);
    }
}
