<?php

namespace App\Jobs;

use App\Constants\BuyLabelConstants;
use App\Models\Order;
use App\Models\Timeline;
use App\Models\User;
use App\Services\ShippoService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BuyLabelShipEngine implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $orderId;
    public int $sellerId;
    public int $tries = 3;
    public int $timeout = 60;

    public function __construct(int $orderId, int $sellerId)
    {
        $this->orderId = $orderId;
        $this->sellerId = $sellerId;
    }

    public function handle(): void
    {
        Log::info("Queue buy label started", [
            BuyLabelConstants::FIELD_ORDER_ID => $this->orderId,
            'seller_id' => $this->sellerId,
        ]);

        try {
            $order = Order::with(['items.product', 'seller.profile'])->find($this->orderId);

            if (!$order) {
                Log::warning(BuyLabelConstants::ORDER_NOT_FOUND, [
                    BuyLabelConstants::FIELD_ORDER_ID => $this->orderId
                ]);
                return;
            }

            $user = User::find($this->sellerId);

            if (!$user) {
                Log::warning("Seller not found", ['seller_id' => $this->sellerId]);
                return;
            }

            // Check eligibility
            if (!empty($order->shipping_label)) {
                Log::info("Label already exists, skipping", [
                    BuyLabelConstants::FIELD_ORDER_ID => $this->orderId,
                ]);
                return;
            }

            if (!empty($order->tracking_id)) {
                Log::info("Tracking already exists, skipping", [
                    BuyLabelConstants::FIELD_ORDER_ID => $this->orderId,
                ]);
                return;
            }

            if (empty($order->address_1)) {
                Log::warning("No shipping address", [
                    BuyLabelConstants::FIELD_ORDER_ID => $this->orderId,
                ]);
                return;
            }

            if (!$order->seller->profile || !$order->seller->profile->production) {
                Log::warning("No production permission", [
                    BuyLabelConstants::FIELD_ORDER_ID => $this->orderId,
                ]);
                return;
            }

            // Use service to create label
            $shippoService = new ShippoService();

            $toAddress = $this->prepareToAddress($order);
            $fromAddress = $shippoService->getDefaultFromAddress();
            $package = $this->calculatePackage($order, $shippoService);
            $serviceCode = $this->determineServiceCode($order);

            Log::info("Calling Shippo API from queue", [
                BuyLabelConstants::FIELD_ORDER_ID => $this->orderId,
                'service_code' => $serviceCode,
            ]);

            $response = $shippoService->createLabel(
                toAddress: $toAddress,
                fromAddress: $fromAddress,
                package: $package,
                serviceCode: $serviceCode
            );

            Log::info("Label created successfully from queue", [
                BuyLabelConstants::FIELD_ORDER_ID => $this->orderId,
                BuyLabelConstants::FIELD_TRACKING_NUMBER => $response['tracking_number'] ?? null,
            ]);

            // Update order and create timeline in transaction
            DB::transaction(function () use ($order, $response, $user) {
                $this->updateOrderWithLabel($order, $response);
                $this->createTimeline($order, $user);
            });

            // Send success notification
            $trackingNumber = $response['tracking_number'] ?? 'N/A';
            $this->sendSuccessNotification($this->orderId, $trackingNumber);

            // Trigger webhook if not admin
            if ($user->id != 2) {
                WebhookJob::dispatch($order->id)
                    ->delay(now()->addSeconds(2))
                    ->onQueue('webhook');

                Log::info("Webhook job dispatched", [
                    BuyLabelConstants::FIELD_ORDER_ID => $this->orderId
                ]);
            }

            Log::info("Queue buy label completed", [
                BuyLabelConstants::FIELD_ORDER_ID => $this->orderId,
            ]);

            // Dispatch postLabelConvert after 1 second delay
            dispatch(function () use ($order) {
                try {
                    $order->refresh();
                    \App\Jobs\ProcessOrderLabelShip::postLabelConvert($order);
                    Log::info("Post label convert dispatched successfully (batch)", [
                        'order_id' => $order->id
                    ]);
                } catch (\Exception $e) {
                    Log::error("Post label convert failed (batch)", [
                        'order_id' => $order->id,
                        'error' => $e->getMessage()
                    ]);
                }
            })->delay(now()->addSecond());
        } catch (Exception $e) {
            Log::error("Queue buy label failed", [
                BuyLabelConstants::FIELD_ORDER_ID => $this->orderId,
                'seller_id' => $this->sellerId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Send error notification
            $this->sendErrorNotification($this->orderId, $e->getMessage());

            throw $e;
        }
    }

    private function prepareToAddress(Order $order): array
    {
        $fullName = trim(($order->first_name ?? '') . ' ' . ($order->last_name ?? ''));

        $toAddress = [
            'name' => $fullName ?: BuyLabelConstants::DEFAULT_CUSTOMER_NAME,
            'address_line1' => $order->address_1,
            'address_line2' => $order->address_2 ?? '',
            'city_locality' => $order->city ?? '',
            'state_province' => $order->state ?? '',
            'postal_code' => $order->postcode ?? '',
            'country_code' => $order->country ?? 'US',
        ];

        if (!empty($order->phone)) {
            $toAddress['phone'] = $order->phone;
        }

        Log::info("Prepared address", [
            BuyLabelConstants::FIELD_ORDER_ID => $order->id,
            'to_address' => $toAddress,
        ]);

        return $toAddress;
    }

    private function calculatePackage(Order $order, ShippoService $shippoService): array
    {
        $totalWeightOz = 0;
        $itemCount = 0;

        foreach ($order->items as $item) {
            if ($item->product && $item->product->weight) {
                $totalWeightOz += $item->product->weight * ($item->quantity ?? 1);
                $itemCount++;
            }
        }

        if ($totalWeightOz <= 0) {
            $totalWeightOz = BuyLabelConstants::DEFAULT_WEIGHT_OZ;
            Log::warning("No product weight found, using default", [
                BuyLabelConstants::FIELD_ORDER_ID => $order->id,
                'default_weight_oz' => $totalWeightOz,
            ]);
        }

        $weightInPounds = $shippoService->convertOzToLb($totalWeightOz);
        $height = $itemCount > 1
            ? BuyLabelConstants::PACKAGE_HEIGHT_MULTIPLE
            : BuyLabelConstants::PACKAGE_HEIGHT_SINGLE;

        $package = [
            'weight' => [
                'value' => $weightInPounds,
                'unit' => BuyLabelConstants::WEIGHT_UNIT_POUND,
            ],
            'dimensions' => [
                'length' => BuyLabelConstants::PACKAGE_LENGTH,
                'width' => BuyLabelConstants::PACKAGE_WIDTH,
                'height' => $height,
                'unit' => BuyLabelConstants::PACKAGE_UNIT,
            ],
        ];

        Log::info("Package details", [
            BuyLabelConstants::FIELD_ORDER_ID => $order->id,
            'weight_oz' => $totalWeightOz,
            'weight_lb' => $weightInPounds,
            'item_count' => $itemCount,
        ]);

        return $package;
    }

    private function determineServiceCode(Order $order): string
    {
        $serviceCode = BuyLabelConstants::SERVICE_GROUND_ADVANTAGE;

        if ($order->shipping_method === BuyLabelConstants::METHOD_PRIORITY) {
            $serviceCode = BuyLabelConstants::SERVICE_PRIORITY_MAIL;
        }

        Log::info("Service selected", [
            BuyLabelConstants::FIELD_ORDER_ID => $order->id,
            'shipping_method' => $order->shipping_method,
            'service_code' => $serviceCode,
        ]);

        return $serviceCode;
    }

    private function updateOrderWithLabel(Order $order, array $response): void
    {
        $labelUrl = $response['label_download']['href'] ??
            $response['label_download']['pdf'] ??
            $response['label_url'] ??
            null;

        $order->shipping_label = $labelUrl;

        if (empty($order->tracking_id)) {
            $order->tracking_id = $response['tracking_number'] ?? null;
        }

        $order->shipping_service = BuyLabelConstants::SHIPPING_SERVICE_USPS;
        $order->shipping_json = json_encode($response);
        $order->save();

        Log::info("Order updated with label info", [
            BuyLabelConstants::FIELD_ORDER_ID => $order->id,
            BuyLabelConstants::FIELD_LABEL_URL => $order->shipping_label,
            BuyLabelConstants::FIELD_TRACKING_NUMBER => $order->tracking_id,
        ]);
    }

    private function createTimeline(Order $order, User $user): void
    {
        Timeline::create([
            'object' => BuyLabelConstants::TIMELINE_OBJECT_ORDER,
            'object_id' => $order->id,
            'owner_id' => $user->id,
            'action' => BuyLabelConstants::TIMELINE_ACTION_BUY_LABEL,
            'note' => sprintf(BuyLabelConstants::TIMELINE_NOTE_AUTO, $order->id),
        ]);

        Log::info("Timeline entry created", [
            BuyLabelConstants::FIELD_ORDER_ID => $order->id,
            'type' => 'auto',
        ]);
    }

    /**
     * Send Telegram notification
     */
    private function sendTelegramNotification(string $text): void
    {
        try {
            $chatId = config('services.telegram.chat_id');
            $botToken = config('services.telegram.bot_token');

            if (!$chatId || !$botToken) {
                Log::warning('Telegram config missing');
                return;
            }

            $response = \Illuminate\Support\Facades\Http::timeout(5)
                ->post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => trim($text),
                ]);

            if (!$response->successful()) {
                Log::error('Telegram send failed', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Telegram exception', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send success notification
     */
    private function sendSuccessNotification(int $orderId, string $trackingNumber): void
    {
        $url = env('FRONTEND_URL', 'https://manage.lemiex.us') . "/orders?order_id={$orderId}";
        $text = "Mua Label Thành Công \n\nOrder ID: {$orderId}\nLink: {$url}\nTracking: {$trackingNumber}";
        $this->sendTelegramNotification($text);
    }

    /**
     * Send error notification
     */
    private function sendErrorNotification(int $orderId, string $error): void
    {
        $url = env('FRONTEND_URL', 'https://manage.lemiex.us') . "/orders?order_id={$orderId}";
        $text = "Mua Label Thất Bại ❌\n\nOrder ID: {$orderId}\nLink: {$url}\nLỗi: {$error}";
        $this->sendTelegramNotification($text);
    }

    public function failed(Exception $exception): void
    {
        Log::error("Buy Label job failed permanently", [
            BuyLabelConstants::FIELD_ORDER_ID => $this->orderId,
            'seller_id' => $this->sellerId,
            'error' => $exception->getMessage(),
        ]);
    }
}
