<?php

namespace App\Services;

use App\Constants\BuyLabelConstants;
use App\Constants\HttpCode;
use App\Constants\ShipDvxConstants;
use App\Enums\OrderFulfillStatus;
use App\Jobs\BuyLabelShipEngine;
use App\Models\Order;
use App\Models\Timeline;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BuyLabelService
{
    private ShippoService $shippoService;
    private ShipDvxService $shipDvxService;

    public function __construct(ShippoService $shippoService, ShipDvxService $shipDvxService)
    {
        $this->shippoService = $shippoService;
        $this->shipDvxService = $shipDvxService;
    }

    /**
     * Buy label / create shipping order(s) via ShipDVX (replaces the Shippo flow).
     * Async: returns a jobId; the actual label/tracking arrive via webhook.
     * Forwards existing TikTok labels (HAS_LABEL) — shippingPartner = TIKTOK.
     */
    public function buyLabelViaShipDvx(array $orderIds, User $user): array
    {
        try {
            $isAdmin = $user->role && strtolower($user->role->name) === 'admin';

            $query = Order::with(['items.productVariant'])->whereIn('id', $orderIds);
            if (!$isAdmin) {
                $query->where('seller_id', $user->id);
            }
            $orders = $query->get();

            if ($orders->isEmpty()) {
                return [
                    'code' => HttpCode::NOT_FOUND,
                    'status' => false,
                    'message' => BuyLabelConstants::ORDER_NOT_FOUND,
                ];
            }

            $payloads = [];
            foreach ($orders as $order) {
                $payloads[] = $this->buildShipDvxPayload($order);
            }

            Log::info('ShipDVX create-orders request', [
                'order_ids' => $orders->pluck('id')->toArray(),
                'count' => count($payloads),
                'payload' => $payloads,
            ]);

            $result = $this->shipDvxService->createOrders($payloads);

            Log::info('ShipDVX create-orders response', ['result' => $result]);

            $jobId = $result['jobId'] ?? null;
            foreach ($orders as $order) {
                $order->provider_order_number = $order->ref_id;
                $order->provider_job_id = $jobId;
                $order->label_status = ShipDvxConstants::STATUS_PENDING;
                $order->save();
            }

            return [
                'code' => HttpCode::SUCCESS,
                'status' => true,
                'message' => BuyLabelConstants::LABELS_DISPATCHED_SUCCESS,
                'data' => [
                    'job_id' => $jobId,
                    'success' => $result['success'] ?? null,
                    BuyLabelConstants::FIELD_TOTAL_ORDERS => $orders->count(),
                    BuyLabelConstants::FIELD_DISPATCHED => $orders->count(),
                ],
            ];
        } catch (Exception $e) {
            Log::error('ShipDVX create-orders failed', [
                'order_ids' => $orderIds,
                'error' => $e->getMessage(),
            ]);

            return [
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => BuyLabelConstants::LABEL_CREATION_FAILED . ': ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Build one ShipDVX create-orders payload from a local order.
     * NOTE: `barcode` / `labelUrl` key names are best-guess (HAS_LABEL/TikTok) —
     * confirm exact JSON keys with ShipDVX, then adjust here.
     */
    private function buildShipDvxPayload(Order $order): array
    {
        $name = trim(($order->first_name ?? '') . ' ' . ($order->last_name ?? ''));

        $payload = [
            'orderNumber' => $order->ref_id,
            'shippingPartner' => ShipDvxConstants::PARTNER_TIKTOK,
            'recipient' => [
                'name' => $name ?: BuyLabelConstants::DEFAULT_CUSTOMER_NAME,
                'phone' => $order->phone ?: '',
                'address1' => $order->address_1 ?? '',
                'address2' => $order->address_2 ?? '',
                'city' => $order->city ?? '',
                'state' => $order->state ?? '',
                'postalCode' => $order->postcode ?? '',
                'country' => $order->country ?? 'US',
            ],
            'items' => $this->buildShipDvxItems($order),
        ];

        // HAS_LABEL: forward the existing TikTok barcode + label
        if (!empty($order->tracking_id)) {
            $payload['barcode'] = $order->tracking_id;
        }
        if (!empty($order->shipping_label)) {
            $payload['labelUrl'] = $order->shipping_label;
        }

        return $payload;
    }

    /**
     * Build items[] from order items. Weight in gram, dims in cm, value in USD.
     * Uses per-item (per-unit) values + quantity, per ShipDVX convention.
     */
    private function buildShipDvxItems(Order $order): array
    {
        $items = [];
        foreach ($order->items as $item) {
            $variant = $item->productVariant;
            $items[] = [
                'skuNumber' => $item->variant_id,
                'name' => $item->product_name ?: ($item->variant_id ?: 'Item'),
                'quantity' => (int) ($item->quantity ?: 1),
                'description' => $item->product_name ?: '',
                'weight' => (float) ($variant?->weight ?: ShipDvxConstants::DEFAULT_ITEM_WEIGHT_G),
                'length' => (float) ($variant?->length ?: 0),
                'width' => (float) ($variant?->width ?: 0),
                'height' => (float) ($variant?->height ?: 0),
                'combinable' => false,
                'value' => (float) ($item->price ?: ShipDvxConstants::DEFAULT_ITEM_VALUE_USD),
                'taxPercentage' => 0,
            ];
        }

        if (empty($items)) {
            $items[] = [
                'skuNumber' => 'ITEM',
                'name' => 'Item',
                'quantity' => 1,
                'description' => 'Item',
                'weight' => ShipDvxConstants::DEFAULT_ITEM_WEIGHT_G,
                'length' => 0,
                'width' => 0,
                'height' => 0,
                'combinable' => false,
                'value' => ShipDvxConstants::DEFAULT_ITEM_VALUE_USD,
                'taxPercentage' => 0,
            ];
        }

        return $items;
    }

    /**
     * Buy label for single order (synchronous)
     */
    public function buyLabelForOrder(int $orderId, User $user): array
    {
        Log::info("Manual buy label request", [
            BuyLabelConstants::FIELD_ORDER_ID => $orderId,
            'user_id' => $user->id,
        ]);

        try {
            // Load order
            $order = Order::with(['items.product', 'seller.profile'])->find($orderId);

            if (!$order) {
                Log::warning(BuyLabelConstants::ORDER_NOT_FOUND, [
                    BuyLabelConstants::FIELD_ORDER_ID => $orderId
                ]);
                return [
                    'code' => HttpCode::NOT_FOUND,
                    'status' => false,
                    'message' => BuyLabelConstants::ORDER_NOT_FOUND,
                ];
            }

            // Check authorization
            $authCheck = $this->checkAuthorization($order, $user);

            if (!$authCheck['authorized']) {
                return [
                    'code' => HttpCode::FORBIDDEN,
                    'status' => false,
                    'message' => $authCheck['message'],
                ];
            }

            // Validate order eligibility
            $eligibilityCheck = $this->checkOrderEligibility($order);
            if (!$eligibilityCheck['eligible']) {
                return [
                    'code' => HttpCode::BAD_REQUEST,
                    'status' => false,
                    'message' => $eligibilityCheck['message'],
                ];
            }

            Log::info("Starting manual label creation", [
                BuyLabelConstants::FIELD_ORDER_ID => $orderId
            ]);

            // Prepare addresses
            $toAddress = $this->prepareToAddress($order);
            $fromAddress = $this->shippoService->getDefaultFromAddress();

            // Calculate package details
            $package = $this->calculatePackage($order);

            // Determine service code
            $serviceCode = $this->determineServiceCode($order);

            Log::info("Calling Shippo API", [
                BuyLabelConstants::FIELD_ORDER_ID => $orderId,
                'service_code' => $serviceCode,
            ]);

            // Call Shippo API
            $response = $this->shippoService->createLabel(
                toAddress: $toAddress,
                fromAddress: $fromAddress,
                package: $package,
                serviceCode: $serviceCode
            );

            Log::info("Label created successfully", [
                BuyLabelConstants::FIELD_ORDER_ID => $orderId,
                BuyLabelConstants::FIELD_TRACKING_NUMBER => $response['tracking_number'] ?? null,
            ]);

            // Update order and create timeline in transaction
            DB::transaction(function () use ($order, $response, $user) {
                $this->updateOrderWithLabel($order, $response);
                $this->createTimeline($order, $user, 'manual');
            });

            // Dispatch postLabelConvert after 1 second delay
            // Using dispatch to run async so it doesn't block the response
            dispatch(function () use ($order) {
                try {
                    // Reload order to get latest shipping_label
                    $order->refresh();
                    \App\Jobs\ProcessOrderLabelShip::postLabelConvert($order);
                    Log::info("Post label convert dispatched successfully", [
                        'order_id' => $order->id
                    ]);
                } catch (Exception $e) {
                    Log::error("Post label convert failed", [
                        'order_id' => $order->id,
                        'error' => $e->getMessage()
                    ]);
                }
            })->delay(now()->addSecond());

            Log::info("Manual buy label completed", [
                BuyLabelConstants::FIELD_ORDER_ID => $orderId,
                'user_id' => $user->id,
            ]);

            $this->sendSuccessNotification($orderId, $order->tracking_id ?? 'N/A');

            return [
                'code' => HttpCode::SUCCESS,
                'status' => true,
                'message' => BuyLabelConstants::LABEL_CREATED_SUCCESS,
                'data' => [
                    BuyLabelConstants::FIELD_ORDER_ID => $order->id,
                    BuyLabelConstants::FIELD_TRACKING_NUMBER => $order->tracking_id,
                    BuyLabelConstants::FIELD_LABEL_URL => $order->shipping_label,
                    BuyLabelConstants::FIELD_SHIPPING_SERVICE => $order->shipping_service,
                ],
            ];
        } catch (Exception $e) {
            Log::error("Manual buy label failed", [
                BuyLabelConstants::FIELD_ORDER_ID => $orderId,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Send error notification
            $this->sendErrorNotification($orderId, $e->getMessage());

            return [
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => BuyLabelConstants::LABEL_CREATION_FAILED . ': ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Dispatch batch buy label jobs
     */
    public function dispatchBatchBuyLabel(array $orderIds, User $user): array
    {
        Log::info("Batch buy label request", [
            'user_id' => $user->id,
            'order_ids' => $orderIds,
        ]);

        try {
            // Check if user is admin
            $isAdmin = $user->role && strtolower($user->role->name) === 'admin';

            // Verify user has access to all orders
            $query = Order::whereIn('id', $orderIds);

            if (!$isAdmin) {
                $query->where('seller_id', $user->id);
            }

            $orders = $query->get();

            if ($orders->count() !== count($orderIds)) {
                Log::warning(BuyLabelConstants::SOME_ORDERS_UNAUTHORIZED, [
                    'requested' => count($orderIds),
                    'found' => $orders->count(),
                ]);
                return [
                    'code' => HttpCode::FORBIDDEN,
                    'status' => false,
                    'message' => BuyLabelConstants::SOME_ORDERS_UNAUTHORIZED,
                ];
            }

            // Dispatch jobs with 1 second delay
            $dispatched = 0;
            foreach ($orders as $order) {
                try {
                    BuyLabelShipEngine::dispatch($order->id, $order->seller_id)
                        ->delay(now()->addSeconds($dispatched));

                    Log::info("Batch job dispatched", [
                        BuyLabelConstants::FIELD_ORDER_ID => $order->id,
                        'delay_seconds' => $dispatched,
                    ]);

                    $dispatched++;
                } catch (Exception $e) {
                    Log::error("Failed to dispatch batch job", [
                        BuyLabelConstants::FIELD_ORDER_ID => $order->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            Log::info("Batch buy label completed", [
                'total_requested' => count($orderIds),
                'total_dispatched' => $dispatched,
            ]);

            return [
                'code' => HttpCode::SUCCESS,
                'status' => true,
                'message' => BuyLabelConstants::LABELS_DISPATCHED_SUCCESS,
                'data' => [
                    BuyLabelConstants::FIELD_TOTAL_ORDERS => count($orderIds),
                    BuyLabelConstants::FIELD_DISPATCHED => $dispatched,
                ],
            ];
        } catch (Exception $e) {
            Log::error("Batch buy label failed", [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => BuyLabelConstants::BATCH_DISPATCH_FAILED . ': ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Check which orders are eligible for buying labels
     */
    public function checkEligibleOrders(array $orderIds, User $user): array
    {
        try {
            // Check if user is admin
            $isAdmin = $user->role && strtolower($user->role->name) === 'admin';

            $query = Order::with('seller.profile')->whereIn('id', $orderIds);

            if (!$isAdmin) {
                $query->where('seller_id', $user->id);
            }

            $orders = $query->get();

            $eligible = [];
            $ineligible = [];

            foreach ($orders as $order) {
                $eligibilityCheck = $this->checkOrderEligibility($order);

                if ($eligibilityCheck['eligible']) {
                    $eligible[] = [
                        BuyLabelConstants::FIELD_ORDER_ID => $order->id,
                        BuyLabelConstants::FIELD_REF_ID => $order->ref_id,
                    ];
                } else {
                    $ineligible[] = [
                        BuyLabelConstants::FIELD_ORDER_ID => $order->id,
                        BuyLabelConstants::FIELD_REF_ID => $order->ref_id,
                        BuyLabelConstants::FIELD_REASONS => $eligibilityCheck['reasons'],
                    ];
                }
            }

            return [
                'code' => HttpCode::SUCCESS,
                'status' => true,
                'message' => BuyLabelConstants::ELIGIBLE_ORDERS_RETRIEVED,
                'data' => [
                    BuyLabelConstants::FIELD_ELIGIBLE => $eligible,
                    BuyLabelConstants::FIELD_INELIGIBLE => $ineligible,
                    BuyLabelConstants::FIELD_TOTAL_ELIGIBLE => count($eligible),
                    BuyLabelConstants::FIELD_TOTAL_INELIGIBLE => count($ineligible),
                ],
            ];
        } catch (Exception $e) {
            Log::error("Check eligible orders failed", [
                'error' => $e->getMessage(),
            ]);

            return [
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => BuyLabelConstants::CHECK_ELIGIBLE_FAILED . ': ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Check if user is authorized to buy label for order
     */
    private function checkAuthorization(Order $order, User $user): array
    {
        $isAdmin = $user->role && strtolower($user->role->name) === 'admin';
        $isOwner = $order->seller_id === $user->id;

        if (!$isOwner && !$isAdmin) {
            Log::warning(BuyLabelConstants::UNAUTHORIZED, [
                BuyLabelConstants::FIELD_ORDER_ID => $order->id,
                'user_id' => $user->id,
                'seller_id' => $order->seller_id,
            ]);

            return [
                'authorized' => false,
                'message' => BuyLabelConstants::UNAUTHORIZED,
            ];
        }

        // Check if seller has production permission
        if (!$order->seller->profile || !$order->seller->profile->production) {
            Log::warning(BuyLabelConstants::NO_PRODUCTION_PERMISSION, [
                BuyLabelConstants::FIELD_ORDER_ID => $order->id,
                'seller_id' => $order->seller_id,
            ]);

            return [
                'authorized' => false,
                'message' => BuyLabelConstants::NO_PRODUCTION_PERMISSION,
            ];
        }

        return ['authorized' => true];
    }

    /**
     * Check if order is eligible for buying label
     */
    private function checkOrderEligibility(Order $order): array
    {
        $reasons = [];

        if (!empty($order->shipping_label)) {
            $reasons[] = BuyLabelConstants::REASON_LABEL_EXISTS;
        }

        if (!empty($order->tracking_id)) {
            $reasons[] = BuyLabelConstants::REASON_TRACKING_EXISTS;
        }

        if (empty($order->address_1)) {
            $reasons[] = BuyLabelConstants::REASON_NO_ADDRESS;
        }

        if (!$order->seller->profile || !$order->seller->profile->production) {
            $reasons[] = BuyLabelConstants::REASON_NO_PERMISSION;
        }

        if (empty($reasons)) {
            return [
                'eligible' => true,
                'reasons' => [],
            ];
        }

        return [
            'eligible' => false,
            'message' => implode(', ', $reasons),
            'reasons' => $reasons,
        ];
    }

    /**
     * Prepare TO address from order
     */
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

    /**
     * Calculate package details from order items
     */
    private function calculatePackage(Order $order): array
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

        $weightInPounds = $this->shippoService->convertOzToLb($totalWeightOz);
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
            'package' => $package,
        ]);

        return $package;
    }

    /**
     * Determine service code based on shipping method
     */
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

    /**
     * Update order with label information
     */
    private function updateOrderWithLabel(Order $order, array $response): void
    {
        // Extract label URL from Shippo response (compatible format)
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

    /**
     * Create timeline entry
     */
    private function createTimeline(Order $order, User $user, string $type = 'auto'): void
    {
        $note = $type === 'manual'
            ? sprintf(BuyLabelConstants::TIMELINE_NOTE_MANUAL, $user->username)
            : sprintf(BuyLabelConstants::TIMELINE_NOTE_AUTO, $order->id);

        Timeline::create([
            'object' => BuyLabelConstants::TIMELINE_OBJECT_ORDER,
            'object_id' => $order->id,
            'owner_id' => $user->id,
            'action' => BuyLabelConstants::TIMELINE_ACTION_BUY_LABEL,
            'note' => $note,
        ]);

        Log::info("Timeline entry created", [
            BuyLabelConstants::FIELD_ORDER_ID => $order->id,
            'type' => $type,
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
        $text = "Mua Label Thành Công (Manual)\n\nOrder ID: {$orderId}\nLink: {$url}\nTracking: {$trackingNumber}";
        $this->sendTelegramNotification($text);
    }

    /**
     * Send error notification
     */
    private function sendErrorNotification(int $orderId, string $error): void
    {
        $url = env('FRONTEND_URL', 'https://manage.lemiex.us') . "/orders?order_id={$orderId}";
        $text = "Mua Label Thất Bại ❌ (Manual)\n\nOrder ID: {$orderId}\nLink: {$url}\nLỗi: {$error}";
        $this->sendTelegramNotification($text);
    }
}
