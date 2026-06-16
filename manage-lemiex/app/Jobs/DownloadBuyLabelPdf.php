<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\Timeline;
use App\Services\ShipDvxService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Downloads the generated label PDF from ShipDVX and stores it on B2, then
 * finalizes the order (shipping_label url + post-label convert).
 *
 * Triggered from BuyLabelWebhookController when an order reaches GENERATED.
 */
class DownloadBuyLabelPdf implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;

    public function __construct(
        public int $orderId,
        public string $providerOrderId,
    ) {}

    public function handle(ShipDvxService $shipDvx): void
    {
        $order = Order::find($this->orderId);
        if (!$order) {
            Log::warning('DownloadBuyLabelPdf: order not found', ['order_id' => $this->orderId]);
            return;
        }

        // Idempotent: already has a stored label
        if (!empty($order->shipping_label)) {
            Log::info('DownloadBuyLabelPdf: label already present, skipping', ['order_id' => $order->id]);
            return;
        }

        $pdf = $shipDvx->downloadLabel($this->providerOrderId);
        if ($pdf === null || $pdf === '') {
            // Throw to retry — label may not be ready the instant the webhook fires
            throw new Exception("Empty label PDF for provider order {$this->providerOrderId}");
        }

        $filename = 'buy_label/' . ($order->ref_id ?: 'order-' . $order->id) . '-' . $this->providerOrderId . '.pdf';
        Storage::disk('b2')->put($filename, $pdf, 'public');
        $labelUrl = Storage::disk('b2')->url($filename);

        $order->shipping_label = $labelUrl;
        $order->save();

        Log::info('DownloadBuyLabelPdf: label stored', [
            'order_id' => $order->id,
            'label_url' => $labelUrl,
        ]);

        Timeline::create([
            'object' => 'order',
            'object_id' => $order->id,
            'owner_id' => $order->seller_id,
            'action' => 'Buy label',
            'note' => sprintf('Label generated via ShipDVX for order %d', $order->id),
        ]);

        // Reuse existing post-label pipeline (QR / convert / merge)
        try {
            $order->refresh();
            ProcessOrderLabelShip::postLabelConvert($order);
        } catch (Exception $e) {
            Log::error('DownloadBuyLabelPdf: postLabelConvert failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }

        $this->notify($order->id, (string) $order->tracking_id);
    }

    private function notify(int $orderId, string $tracking): void
    {
        try {
            $botToken = config('services.telegram.bot_token');
            $chatId = config('services.telegram.chat_id');
            if (!$botToken || !$chatId) {
                return;
            }
            $url = config('services.telegram.frontend_url', config('app.url')) . "/orders?order_id={$orderId}";
            $text = "Mua Label Thành Công (ShipDVX)\n\nOrder ID: {$orderId}\nLink: {$url}\nTracking: {$tracking}";
            \Illuminate\Support\Facades\Http::timeout(5)->post(
                "https://api.telegram.org/bot{$botToken}/sendMessage",
                ['chat_id' => $chatId, 'text' => trim($text)]
            );
        } catch (\Throwable $e) {
            Log::error('DownloadBuyLabelPdf: telegram notify failed', ['error' => $e->getMessage()]);
        }
    }

    public function failed(Exception $e): void
    {
        Log::error('DownloadBuyLabelPdf failed permanently', [
            'order_id' => $this->orderId,
            'provider_order_id' => $this->providerOrderId,
            'error' => $e->getMessage(),
        ]);
    }
}
