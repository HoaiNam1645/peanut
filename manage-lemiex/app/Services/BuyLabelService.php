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

            // Only send orders with a complete recipient address
            $valid = [];
            $invalid = [];
            foreach ($orders as $order) {
                $reasons = $this->validateShipDvxOrder($order);
                if (empty($reasons)) {
                    $valid[] = $order;
                } else {
                    $invalid[] = [
                        BuyLabelConstants::FIELD_ORDER_ID => $order->id,
                        BuyLabelConstants::FIELD_REF_ID => $order->ref_id,
                        BuyLabelConstants::FIELD_REASONS => $reasons,
                    ];
                }
            }

            if (empty($valid)) {
                return [
                    'code' => HttpCode::BAD_REQUEST,
                    'status' => false,
                    'message' => 'Không có đơn hợp lệ để tạo vận chuyển (thiếu thông tin giao hàng)',
                    'data' => [BuyLabelConstants::FIELD_INELIGIBLE => $invalid],
                ];
            }

            $payloads = [];
            foreach ($valid as $order) {
                $payloads[] = $this->buildShipDvxPayload($order);
            }

            Log::info('ShipDVX create-orders request', [
                'order_ids' => array_map(fn ($o) => $o->id, $valid),
                'count' => count($payloads),
                'payload' => $payloads,
            ]);

            try {
                $result = $this->shipDvxService->createOrders($payloads);
            } catch (Exception $e) {
                // Surface the rejected payload even with LOG_LEVEL=warning
                Log::warning('ShipDVX create-orders rejected', [
                    'error' => $e->getMessage(),
                    'payload' => $payloads,
                ]);
                throw $e;
            }

            Log::info('ShipDVX create-orders response', ['result' => $result]);

            $jobId = $result['jobId'] ?? null;
            foreach ($valid as $order) {
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
                    BuyLabelConstants::FIELD_TOTAL_ORDERS => count($valid),
                    BuyLabelConstants::FIELD_DISPATCHED => count($valid),
                    BuyLabelConstants::FIELD_INELIGIBLE => $invalid,
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
     * Preview ShipDVX shipping prices for orders WITHOUT creating them.
     * Returns per-order price + a total, plus any orders that can't be priced
     * (missing address).
     */
    public function previewShipDvxPrices(array $orderIds, User $user): array
    {
        try {
            $isAdmin = $user->role && strtolower($user->role->name) === 'admin';

            $query = Order::with(['items.productVariant'])->whereIn('id', $orderIds);
            if (!$isAdmin) {
                $query->where('seller_id', $user->id);
            }
            $orders = $query->get()->values();

            if ($orders->isEmpty()) {
                return [
                    'code' => HttpCode::NOT_FOUND,
                    'status' => false,
                    'message' => BuyLabelConstants::ORDER_NOT_FOUND,
                ];
            }

            // Build payloads only for orders with a complete address; keep mapping by index
            $payloads = [];
            $indexMap = []; // payload index => order meta
            $invalid = [];
            foreach ($orders as $order) {
                // Orders that already carry a label (e.g. TikTok-provided: they
                // have tracking_id + shipping_label) have no price to preview —
                // their payload would be a HAS_LABEL forward (shippingPartner
                // TIKTOK + barcode + labelUrl), which ShipDVX preview-prices
                // rejects ("orders validation error"), failing the whole batch.
                // Report them as ineligible instead of sending them.
                if (!empty($order->tracking_id) && !empty($order->shipping_label)) {
                    $invalid[] = [
                        BuyLabelConstants::FIELD_ORDER_ID => $order->id,
                        BuyLabelConstants::FIELD_REF_ID => $order->ref_id,
                        BuyLabelConstants::FIELD_REASONS => ['Đơn đã có label sẵn — không cần preview giá'],
                    ];
                    continue;
                }

                $reasons = $this->validateShipDvxOrder($order);
                if (!empty($reasons)) {
                    $invalid[] = [
                        BuyLabelConstants::FIELD_ORDER_ID => $order->id,
                        BuyLabelConstants::FIELD_REF_ID => $order->ref_id,
                        BuyLabelConstants::FIELD_REASONS => $reasons,
                    ];
                    continue;
                }
                $indexMap[count($payloads)] = [
                    BuyLabelConstants::FIELD_ORDER_ID => $order->id,
                    BuyLabelConstants::FIELD_REF_ID => $order->ref_id,
                ];
                $payloads[] = $this->buildShipDvxPayload($order);
            }

            $items = [];
            $total = 0.0;
            if (!empty($payloads)) {
                $prices = $this->shipDvxService->previewPrices($payloads);
                foreach ($prices as $p) {
                    $idx = $p['index'] ?? null;
                    $meta = $indexMap[$idx] ?? [];
                    $price = isset($p['calculatedPrice']) ? (float) $p['calculatedPrice'] : null;
                    $items[] = array_merge($meta, [
                        'calculated_price' => $price,
                        'chargeable_weight' => $p['chargeableWeight'] ?? null,
                        'shipping_partner' => $p['shippingPartner'] ?? null,
                        'is_zone9' => $p['isZone9'] ?? null,
                        // Provider returns a per-item `error` (e.g. RECIPIENT_COUNTRY_REQUIRED
                        // for some non-US destinations) when it can't price that order.
                        'error' => $p['error'] ?? null,
                    ]);
                    $total += $price ?? 0;
                }
            }

            return [
                'code' => HttpCode::SUCCESS,
                'status' => true,
                'message' => 'OK',
                'data' => [
                    'items' => $items,
                    'total' => round($total, 2),
                    'count' => count($items),
                    BuyLabelConstants::FIELD_INELIGIBLE => $invalid,
                ],
            ];
        } catch (Exception $e) {
            Log::error('ShipDVX preview-prices failed', [
                'order_ids' => $orderIds,
                'error' => $e->getMessage(),
            ]);

            return [
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => 'Không tính được giá: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Validate that an order has the recipient info ShipDVX requires.
     * Returns a list of reasons (empty = valid).
     */
    private function validateShipDvxOrder(Order $order): array
    {
        $reasons = [];
        if (empty($order->address_1)) {
            $reasons[] = 'Thiếu địa chỉ (address_1)';
        }
        if (empty($order->city)) {
            $reasons[] = 'Thiếu thành phố';
        }
        if ($this->resolveStateCode($order) === null) {
            $reasons[] = 'Bang (state) không hợp lệ — cần mã 2 ký tự (vd TX, CA)';
        }
        if (empty($order->postcode)) {
            $reasons[] = 'Thiếu mã bưu chính (postal code)';
        }
        if (empty($order->country)) {
            $reasons[] = 'Thiếu quốc gia';
        }
        return $reasons;
    }

    /**
     * Build one ShipDVX create-orders payload from a local order.
     * NOTE: `barcode` / `labelUrl` key names are best-guess (HAS_LABEL/TikTok) —
     * confirm exact JSON keys with ShipDVX, then adjust here.
     */
    private function buildShipDvxPayload(Order $order): array
    {
        $name = trim(($order->first_name ?? '') . ' ' . ($order->last_name ?? ''));

        // HAS_LABEL = order already carries a label + tracking (TikTok) → forward it.
        // Otherwise NO_LABEL (Etsy/seller-ship) → ShipDVX buys/generates the label.
        $hasLabel = !empty($order->tracking_id) && !empty($order->shipping_label);

        $payload = [
            'orderNumber' => $order->ref_id,
            'recipient' => [
                'name' => $name ?: BuyLabelConstants::DEFAULT_CUSTOMER_NAME,
                'phone' => $order->phone ?: '',
                'address1' => $order->address_1 ?? '',
                'address2' => $order->address_2 ?? '',
                'city' => $order->city ?? '',
                'state' => $this->resolveStateCode($order) ?? '',
                'postalCode' => $order->postcode ?? '',
                'country' => $order->country ?? 'US',
            ],
            'items' => $this->buildShipDvxItems($order),
        ];

        if ($hasLabel) {
            // Forward an existing label. Per ShipDVX docs (docs-api 2.1/2.2), a US
            // order that already has a TikTok-provided label uses the TIKTOK partner
            // + barcode + labelUrl so ShipDVX FORWARDS it (no new label bought, no
            // shipping charge). The earlier create-orders rejection was the long
            // item name (see buildShipDvxItems truncation), not this partner.
            $payload['shippingPartner'] = ShipDvxConstants::PARTNER_TIKTOK;
            $payload['barcode'] = $order->tracking_id;
            $payload['labelUrl'] = $order->shipping_label;
        } else {
            // Buy a new label — pick the last-mile partner from the address
            $payload['shippingPartner'] = $this->resolveShippingPartner($order);
        }

        return $payload;
    }

    /**
     * Pick the ShipDVX shipping partner for a buy-label (NO_LABEL) order based
     * on its destination: non-US → NON-US, otherwise USPS.
     *
     * All US destinations (incl. zone-9: AK/HI/PR/territories/APO-FPO) use USPS.
     * The provider auto-detects zone-9 from the address and prices it accordingly
     * (verified on prod: a HI address under USPS returns isZone9=true at a higher
     * rate). The dedicated REMOTE-US partner is INACTIVE on the account, so we must
     * never send it — doing so would fail order creation.
     */
    /**
     * Normalize recipient state to the 2-char code ShipDVX requires for US.
     * - US: returns a 2-letter code (as-is if already 2 chars, else mapped from full
     *   name); null when it can't be resolved (e.g. a city was entered) → caller treats
     *   the order as ineligible instead of failing at the provider.
     * - Non-US: returns the trimmed value as-is (province formats vary by country).
     */
    private function resolveStateCode(Order $order): ?string
    {
        $raw = strtoupper(trim((string) ($order->state ?? '')));
        if ($raw === '') {
            return null;
        }

        $country = strtoupper(trim((string) ($order->country ?? 'US')));
        if ($country !== '' && $country !== 'US') {
            return $raw;
        }

        if (strlen($raw) === 2) {
            return $raw;
        }

        return ShipDvxConstants::US_STATE_CODES[$raw] ?? null;
    }

    private function resolveShippingPartner(Order $order): string
    {
        $country = strtoupper(trim((string) ($order->country ?? 'US')));
        if ($country !== '' && $country !== 'US') {
            return ShipDvxConstants::PARTNER_NON_US;
        }

        return ShipDvxConstants::PARTNER_USPS;
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
                // ShipDVX caps item name length — truncate to a short customs
                // descriptor (full title is kept in `description` below).
                'name' => mb_substr($item->product_name ?: ($item->variant_id ?: 'Item'), 0, ShipDvxConstants::MAX_ITEM_NAME_LEN),
                'quantity' => (int) ($item->quantity ?: 1),
                // ShipDVX caps itemDescription at 50 chars (ORDER_FAILED otherwise).
                'description' => mb_substr($item->product_name ?: '', 0, ShipDvxConstants::MAX_ITEM_DESC_LEN),
                'weight' => (float) ($variant?->weight ?: ShipDvxConstants::DEFAULT_ITEM_WEIGHT_G),
                'length' => (float) ($variant?->length ?: ShipDvxConstants::DEFAULT_ITEM_LENGTH_CM),
                'width' => (float) ($variant?->width ?: ShipDvxConstants::DEFAULT_ITEM_WIDTH_CM),
                'height' => (float) ($variant?->height ?: ShipDvxConstants::DEFAULT_ITEM_HEIGHT_CM),
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
                'length' => ShipDvxConstants::DEFAULT_ITEM_LENGTH_CM,
                'width' => ShipDvxConstants::DEFAULT_ITEM_WIDTH_CM,
                'height' => ShipDvxConstants::DEFAULT_ITEM_HEIGHT_CM,
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
