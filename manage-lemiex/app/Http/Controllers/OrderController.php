<?php

namespace App\Http\Controllers;

use App\Constants\HttpCode;
use App\Constants\MsgCode;
use App\Constants\OrderConstants;
use App\Constants\OrderStatus;
use App\Constants\ResponseMessage;
use App\Enums\OrderFulfillStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderType;
use App\Enums\ProductionStatus;
use App\Enums\ShippingService;
use App\Enums\UserRole;
use App\Http\Requests\CreateOrderLabelShipRequest;
use App\Http\Requests\CreateOrderNoDesignRequest;
use App\Http\Requests\UpdateOrderLabelShipRequest;
use App\Jobs\ProcessOrderLabelShip;
use App\Jobs\ProcessOrderNoDesign;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemMeta;
use App\Models\Timeline;
use App\Models\User;
use App\Services\OrderService;
use App\Services\SupportService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class OrderController extends Controller
{
    protected $orderService;

    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    /**
     * Create order - route to specific type handler
     */
    public function createOrder(Request $request): JsonResponse
    {
        // Resolve the seller up-front from the api_key so the limit gate is
        // per-seller. If we can't (missing/invalid key), we skip the gate and
        // let the type handler return its own auth error (re-running auth).
        $apiKey = $request->input('api_key');
        $sellerId = null;
        if (is_string($apiKey) && $apiKey !== '') {
            $authResult = $this->orderService->authenticateStore($apiKey);
            if (($authResult['success'] ?? false) === true) {
                $sellerId = $authResult['user']->id;
            }
        }

        $limitKey = null;
        if ($sellerId !== null) {
            $limitKey = 'orders:daily_count:' . $sellerId . ':' . today()->toDateString();
            if (!Cache::has($limitKey)) {
                $dbCount = $this->getSellerTodayOrderCount($sellerId);
                Cache::add($limitKey, $dbCount, today()->addDay());
            }

            $dailyLimit = $this->getSellerEffectiveLimit($sellerId);
            $current = Cache::increment($limitKey);
            if ($current > $dailyLimit) {
                Cache::decrement($limitKey);
                return response()->json([
                    'code' => 429,
                    'status' => false,
                    'message' => "Người bán đã đạt giới hạn {$dailyLimit} đơn/ngày. Vui lòng thử lại vào ngày mai."
                ], 429);
            }
        }

        try {
            $orderType = $request->input('order_type');

            $response = match ($orderType) {
                OrderConstants::ORDER_TYPE_NO_DESIGN => $this->createOrderNoDesign($request),
                OrderConstants::ORDER_TYPE_SELLER_SHIP => $this->createOrderSellerShop($request),
                OrderConstants::ORDER_TYPE_LABEL_SHIP => $this->createOrderLabelShip($request),
                default => response()->json([
                    'code' => HttpCode::BAD_REQUEST,
                    'status' => false,
                    'message' => ResponseMessage::INVALID_ORDER_TYPE
                ], HttpCode::BAD_REQUEST)
            };

            if ($limitKey !== null && $response->status() >= 400) {
                Cache::decrement($limitKey);
            }

            return $response;
        } catch (\Throwable $e) {
            if ($limitKey !== null) {
                Cache::decrement($limitKey);
            }
            throw $e;
        }
    }

    /**
     * Default base daily order limit (fallback). Used for sellers that have no
     * per-seller base configured. Configurable via settings, defaults to 50.
     */
    private function getDefaultBaseLimit(): int
    {
        $value = \App\Models\Setting::where('key', 'order_daily_base_limit')->value('value');
        $base = is_numeric($value) ? (int) $value : 50;

        return $base > 0 ? $base : 50;
    }

    /**
     * Setting key holding a seller's persistent base limit.
     */
    private function sellerBaseSettingKey(int $sellerId): string
    {
        return 'order_daily_base:' . $sellerId;
    }

    /**
     * A seller's persistent base limit. Falls back to the default base when the
     * seller has no per-seller base configured.
     */
    private function getSellerBaseLimit(int $sellerId): int
    {
        $value = \App\Models\Setting::where('key', $this->sellerBaseSettingKey($sellerId))->value('value');
        if (!is_numeric($value)) {
            return $this->getDefaultBaseLimit();
        }

        $base = (int) $value;

        return $base > 0 ? $base : $this->getDefaultBaseLimit();
    }

    /**
     * Setting key holding a seller's extra slots opened for TODAY only.
     */
    private function sellerExtraSettingKey(int $sellerId): string
    {
        return 'order_daily_extra:' . $sellerId;
    }

    /**
     * Extra order slots opened by admin for a SELLER for TODAY only. Stored
     * together with the date it applies to, so it automatically falls back to
     * 0 tomorrow.
     */
    private function getSellerExtraOrderLimit(int $sellerId): int
    {
        $raw = \App\Models\Setting::where('key', $this->sellerExtraSettingKey($sellerId))->value('value');
        if (!$raw) {
            return 0;
        }

        $data = json_decode($raw, true);
        if (!is_array($data) || ($data['date'] ?? null) !== today()->toDateString()) {
            return 0;
        }

        return max(0, (int) ($data['extra'] ?? 0));
    }

    /**
     * A seller's effective daily limit = that seller's base (or the default
     * base fallback) + that seller's extra opened for today.
     */
    private function getSellerEffectiveLimit(int $sellerId): int
    {
        return $this->getSellerBaseLimit($sellerId) + $this->getSellerExtraOrderLimit($sellerId);
    }

    /**
     * Number of orders already created today by a specific seller.
     */
    private function getSellerTodayOrderCount(int $sellerId): int
    {
        return Order::where('seller_id', $sellerId)
            ->where('created_at', '>=', today())
            ->where('created_at', '<', today()->addDay())
            ->count();
    }

    /**
     * Number of orders already created today (all sellers).
     */
    private function getTodayOrderCount(): int
    {
        return Order::where('created_at', '>=', today())
            ->where('created_at', '<', today()->addDay())
            ->count();
    }

    /**
     * Sum of every seller's extra opened for today (across all sellers).
     */
    private function getAllSellersExtraTodaySum(): int
    {
        $rows = \App\Models\Setting::where('key', 'LIKE', 'order_daily_extra:%')->pluck('value');
        $sum = 0;
        foreach ($rows as $raw) {
            $data = json_decode($raw, true);
            if (is_array($data) && ($data['date'] ?? null) === today()->toDateString()) {
                $sum += max(0, (int) ($data['extra'] ?? 0));
            }
        }

        return $sum;
    }

    /**
     * Per-seller daily-limit payload (config + that seller's usage today).
     */
    private function sellerDailyLimitPayload(int $sellerId): array
    {
        $base = $this->getSellerBaseLimit($sellerId);
        $extra = $this->getSellerExtraOrderLimit($sellerId);
        $effective = $base + $extra;
        $used = $this->getSellerTodayOrderCount($sellerId);

        return [
            'scope' => 'seller',
            'seller_id' => $sellerId,
            'base_limit' => $base,
            'extra_today' => $extra,
            'effective_limit' => $effective,
            'used_today' => $used,
            'remaining_today' => max(0, $effective - $used),
            'date' => today()->toDateString(),
        ];
    }

    /**
     * Global overview payload (no seller selected): the default base (fallback
     * for sellers without a per-seller base), the SUM of all sellers' extra
     * today, and the total orders created today across sellers.
     */
    private function globalDailyLimitPayload(): array
    {
        $base = $this->getDefaultBaseLimit();
        $extra = $this->getAllSellersExtraTodaySum();
        $effective = $base + $extra;
        $used = $this->getTodayOrderCount();

        return [
            'scope' => 'global',
            'seller_id' => null,
            'base_limit' => $base,
            'extra_today' => $extra,
            'effective_limit' => $effective,
            'used_today' => $used,
            'remaining_today' => max(0, $effective - $used),
            'date' => today()->toDateString(),
        ];
    }

    /**
     * GET /orders/config/daily-limit[?seller_id=]
     * With seller_id: that seller's config + usage today.
     * Without: the global overview (base + sum of all sellers' extra, total used).
     */
    public function getDailyLimit(Request $request): JsonResponse
    {
        $sellerId = $request->input('seller_id');
        $payload = ($sellerId !== null && $sellerId !== '')
            ? $this->sellerDailyLimitPayload((int) $sellerId)
            : $this->globalDailyLimitPayload();

        return response()->json([
            'code' => HttpCode::SUCCESS,
            'status' => true,
            'data' => $payload,
        ]);
    }

    /**
     * PUT /orders/config/daily-limit  (Admin only)
     * Body: { seller_id?: int, base_limit?: int >= 1, extra_today?: int >= 0 }
     *  - base_limit sets the SELLER's persistent base floor (REQUIRES seller_id).
     *  - extra_today opens extra slots for a SELLER for TODAY only (auto-resets
     *    tomorrow) and REQUIRES seller_id.
     */
    public function updateDailyLimit(Request $request): JsonResponse
    {
        $user = $request->user();
        $roleName = $user && $user->role ? $user->role->name : ($user->role_name ?? null);
        if ($roleName !== 'Admin') {
            return response()->json([
                'code' => HttpCode::FORBIDDEN,
                'status' => false,
                'message' => 'Chỉ admin mới được chỉnh giới hạn đơn hàng.',
            ], HttpCode::FORBIDDEN);
        }

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'extra_today' => 'nullable|integer|min:0|max:100000',
            'base_limit' => 'nullable|integer|min:1|max:100000',
            'seller_id' => 'nullable|integer|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => HttpCode::BAD_REQUEST,
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], HttpCode::BAD_REQUEST);
        }

        $sellerId = $request->filled('seller_id') ? (int) $request->input('seller_id') : null;

        // If a seller is targeted, make sure it really is a Seller account.
        if ($sellerId !== null) {
            $seller = User::with('role')->find($sellerId);
            $sellerRole = $seller && $seller->role ? $seller->role->name : null;
            if (!$seller || $sellerRole !== 'Seller') {
                return response()->json([
                    'code' => HttpCode::BAD_REQUEST,
                    'status' => false,
                    'message' => 'Người dùng được chọn không phải là người bán.',
                ], HttpCode::BAD_REQUEST);
            }
        }

        if ($request->filled('base_limit')) {
            if ($sellerId === null) {
                return response()->json([
                    'code' => HttpCode::BAD_REQUEST,
                    'status' => false,
                    'message' => 'Vui lòng chọn người bán trước khi đặt mức nền.',
                ], HttpCode::BAD_REQUEST);
            }

            \App\Models\Setting::updateOrCreate(
                ['key' => $this->sellerBaseSettingKey($sellerId)],
                ['value' => (string) (int) $request->input('base_limit')]
            );
        }

        if ($request->has('extra_today')) {
            if ($sellerId === null) {
                return response()->json([
                    'code' => HttpCode::BAD_REQUEST,
                    'status' => false,
                    'message' => 'Vui lòng chọn người bán trước khi mở thêm đơn.',
                ], HttpCode::BAD_REQUEST);
            }

            \App\Models\Setting::updateOrCreate(
                ['key' => $this->sellerExtraSettingKey($sellerId)],
                ['value' => json_encode([
                    'date' => today()->toDateString(),
                    'extra' => max(0, (int) $request->input('extra_today')),
                ])]
            );
        }

        return response()->json([
            'code' => HttpCode::SUCCESS,
            'status' => true,
            'message' => 'Đã cập nhật giới hạn đơn hàng.',
            'data' => $sellerId !== null
                ? $this->sellerDailyLimitPayload($sellerId)
                : $this->globalDailyLimitPayload(),
        ]);
    }

    /**
     * Create NO DESIGN order
     */
    public function createOrderNoDesign(Request $request): JsonResponse
    {
        try {
            // Validate request - this will throw HttpResponseException if validation fails
            $validated = app(CreateOrderNoDesignRequest::class)->validated();

            // Step 2: Authenticate store and check seller role
            $authResult = $this->orderService->authenticateStore($validated['api_key']);

            if (!$authResult['success']) {
                // Track failed authentication
                $this->trackFailedAuth($validated['api_key']);

                return response()->json([
                    'code' => $authResult['code'],
                    'status' => false,
                    'message' => $authResult['message']
                ], $authResult['code']);
            }

            $store = $authResult['store'];
            $user = $authResult['user'];
            $tier = $authResult['tier'];

            // Step 3: Prepare order data with address
            $address = $validated['address'];
            $nameParts = explode(' ', $address['name'], 2);

            $orderData = [
                'ref_id' => $validated['ref_id'],
                'seller_id' => $user->id,
                'seller_ref' => $validated['seller_ref'] ?? null,
                'store_id' => $store->id,
                'fulfill_status' => $this->orderService->convertOrderStatus($validated['order_status']),
                'shipping_method' => $validated['shipping_method'],
                'shipping_service' => $validated['shipping_service'] ?? ShippingService::USPS,
                'shipping_label' => null, // No shipping label for NO DESIGN
                'note' => $validated['note'] ?? null,
                'order_type' => OrderType::PRINT,
                'product_type' => $validated['product_type'] ?? null,
                'payment_status' => OrderPaymentStatus::PENDING,
                'extra_fee' => OrderConstants::DEFAULT_EXTRA_FEE,
                'refund_fee' => OrderConstants::DEFAULT_REFUND_FEE,
                // Address information
                'first_name' => $nameParts[0] ?? null,
                'last_name' => $nameParts[1] ?? null,
                'phone' => $address['phone'] ?? null,
                'address_1' => $address['street1'],
                'address_2' => $address['street2'] ?? null,
                'city' => $address['city'],
                'state' => $address['state'],
                'postcode' => $address['zip'],
                'country' => strtoupper($address['country']),
            ];

            // Step 4: Create order with transaction
            $createResult = $this->orderService->createOrder($orderData, $request->all());

            if (!$createResult['success']) {
                return response()->json([
                    'code' => $createResult['code'] ?? HttpCode::SERVER_ERROR,
                    'status' => false,
                    'message' => $createResult['message'],
                    'error' => $createResult['error'] ?? null
                ], $createResult['code'] ?? 500);
            }

            // Check if idempotent response (order already exists)
            if (isset($createResult['idempotent'])) {
                // Get the existing order to check its status
                $existingOrder = Order::find($createResult['order_id']);

                // Always return 409 Conflict when order already exists
                return response()->json([
                    'code' => HttpCode::CONFLICT,
                    'status' => false,
                    'message' => ResponseMessage::ORDER_ALREADY_EXISTS,
                    'data' => [
                        'order_id' => $createResult['order_id'],
                        'ref_id' => $existingOrder->ref_id ?? null,
                        'fulfill_status' => $existingOrder->fulfill_status ?? 'unknown',
                        'created_at' => $existingOrder->created_at ?? null
                    ]
                ], HttpCode::CONFLICT);
            }

            $order = $createResult['order'];

            // Step 5: Dispatch background jobs
            ProcessOrderNoDesign::dispatch(
                $order->id,
                $validated['line_items'],
                $tier,
                $store
            );


            Log::info('Order created successfully', [
                'order_id' => $order->id,
                'ref_id' => $order->ref_id,
                'seller_id' => $user->id
            ]);

            // Return response immediately
            return response()->json([
                'code' => HttpCode::SUCCESS,
                'status' => true,
                'message' => ResponseMessage::SUCCESS,
                'data' => [
                    'order_id' => $order->id
                ]
            ], HttpCode::SUCCESS);
        } catch (\Illuminate\Http\Exceptions\HttpResponseException $e) {
            // Re-throw validation response from CreateOrderNoDesignRequest
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to create NO DESIGN order', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => ResponseMessage::ORDER_CREATION_FAILED,
                'error' => config('app.debug') ? $e->getMessage() : null
            ], HttpCode::SERVER_ERROR);
        }
    }

    /**
     * Create LABEL SHIP order
     */
    public function createOrderLabelShip(Request $request): JsonResponse
    {
        try {
            // Validate request
            $validated = app(CreateOrderLabelShipRequest::class)->validated();

            // Step 2: Authenticate store and check seller role
            $authResult = $this->orderService->authenticateStore($validated['api_key']);

            if (!$authResult['success']) {
                $this->trackFailedAuth($validated['api_key']);

                return response()->json([
                    'code' => $authResult['code'],
                    'status' => false,
                    'message' => $authResult['message']
                ], $authResult['code']);
            }

            $store = $authResult['store'];
            $user = $authResult['user'];
            $tier = $authResult['tier'];

            // Calculate priority fee based on tier
            $fulfillmentPriority = $validated['fulfillment_priority'] ?? 'normal';
            $priorityFee = \App\Models\FulfillmentPriority::getPriceForTier($fulfillmentPriority, $tier);

            // Optional recipient address (ShipDVX needs it for customs even on LABEL SHIP)
            $labelAddress = $validated['address'] ?? null;
            $labelNameParts = !empty($labelAddress['name'])
                ? explode(' ', $labelAddress['name'], 2)
                : [];

            // Step 3: Prepare order data
            $orderData = [
                'ref_id' => $validated['ref_id'],
                'seller_id' => $user->id,
                'seller_ref' => $validated['seller_ref'] ?? null,
                'store_id' => $store->id,
                'fulfill_status' => $this->orderService->convertOrderStatus($validated['order_status']),
                'fulfillment_priority' => $fulfillmentPriority,
                'priority_fee' => $priorityFee,
                'shipping_method' => $validated['shipping_method'],
                'shipping_service' => $validated['shipping_service'] ?? ShippingService::USPS,
                'shipping_label' => $validated['shipping_label'], // Required for LABEL SHIP
                'note' => $validated['note'] ?? null,
                'order_type' => OrderType::PRINT,
                'product_type' => $validated['product_type'] ?? null,
                'payment_status' => OrderPaymentStatus::PENDING,
                'extra_fee' => OrderConstants::DEFAULT_EXTRA_FEE,
                'refund_fee' => OrderConstants::DEFAULT_REFUND_FEE,
                // Recipient address (optional for LABEL SHIP; provided for ShipDVX customs)
                'first_name' => $labelNameParts[0] ?? null,
                'last_name' => $labelNameParts[1] ?? null,
                'phone' => $labelAddress['phone'] ?? null,
                'address_1' => $labelAddress['street1'] ?? null,
                'address_2' => $labelAddress['street2'] ?? null,
                'city' => $labelAddress['city'] ?? null,
                'state' => $labelAddress['state'] ?? null,
                'postcode' => $labelAddress['zip'] ?? null,
                'country' => !empty($labelAddress['country'])
                    ? strtoupper($labelAddress['country'])
                    : null,
            ];

            // Step 4: Create order with transaction
            $createResult = $this->orderService->createOrder($orderData, $request->all());

            if (!$createResult['success']) {
                return response()->json([
                    'code' => $createResult['code'] ?? HttpCode::SERVER_ERROR,
                    'status' => false,
                    'message' => $createResult['message'],
                    'error' => $createResult['error'] ?? null
                ], $createResult['code'] ?? 500);
            }

            // Check if idempotent response (order already exists)
            if (isset($createResult['idempotent'])) {
                // Get the existing order to check its status
                $existingOrder = Order::find($createResult['order_id']);

                // Always return 409 Conflict when order already exists
                return response()->json([
                    'code' => HttpCode::CONFLICT,
                    'status' => false,
                    'message' => ResponseMessage::ORDER_ALREADY_EXISTS,
                    'data' => [
                        'order_id' => $createResult['order_id'],
                        'ref_id' => $existingOrder->ref_id ?? null,
                        'fulfill_status' => $existingOrder->fulfill_status ?? 'unknown',
                        'created_at' => $existingOrder->created_at ?? null
                    ]
                ], HttpCode::CONFLICT);
            }

            $order = $createResult['order'];

            // Step 5: Dispatch background jobs
            \App\Jobs\ProcessOrderLabelShip::dispatch(
                $order->id,
                $validated['line_items'],
                $tier,
                $store
            );


            Log::info('LABEL SHIP order created successfully', [
                'order_id' => $order->id,
                'ref_id' => $order->ref_id,
                'seller_id' => $user->id
            ]);

            // Return response immediately
            return response()->json([
                'code' => HttpCode::SUCCESS,
                'status' => true,
                'message' => ResponseMessage::SUCCESS,
                'data' => [
                    'order_id' => $order->id
                ]
            ], HttpCode::SUCCESS);
        } catch (\Illuminate\Http\Exceptions\HttpResponseException $e) {
            // Re-throw validation response
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to create LABEL SHIP order', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => ResponseMessage::ORDER_CREATION_FAILED,
                'error' => config('app.debug') ? $e->getMessage() : null
            ], HttpCode::SERVER_ERROR);
        }
    }

    /**
     * Create SELLER SHOP order
     */
    public function createOrderSellerShop(Request $request): JsonResponse
    {
        try {
            // Validate request
            $validated = app(\App\Http\Requests\CreateOrderSellerShopRequest::class)->validated();

            // Step 2: Authenticate store and check seller role
            $authResult = $this->orderService->authenticateStore($validated['api_key']);

            if (!$authResult['success']) {
                $this->trackFailedAuth($validated['api_key']);

                return response()->json([
                    'code' => $authResult['code'],
                    'status' => false,
                    'message' => $authResult['message']
                ], $authResult['code']);
            }

            $store = $authResult['store'];
            $user = $authResult['user'];
            $tier = $authResult['tier'];

            // Step 3: Prepare order data with address
            $address = $validated['address'];

            // Parse name into first_name and last_name
            $nameParts = explode(' ', $address['name'], 2);
            $firstName = $nameParts[0] ?? null;
            $lastName = $nameParts[1] ?? null;

            // Check for placeholder character
            $hasPlaceholder = false;
            $addressFields = ['name', 'street1', 'street2', 'city', 'state', 'zip', 'country'];

            foreach ($addressFields as $field) {
                $value = $address[$field] ?? '';
                if (str_contains($value, OrderConstants::ADDRESS_PLACEHOLDER)) {
                    $hasPlaceholder = true;
                    break;
                }
            }

            // If has placeholder, set all address fields to null
            if ($hasPlaceholder) {
                Log::warning('Address contains placeholder character', [
                    'ref_id' => $validated['ref_id'],
                    'address' => $address
                ]);

                $firstName = null;
                $lastName = null;
                $phone = null;
                $street1 = null;
                $street2 = null;
                $city = null;
                $state = null;
                $zip = null;
                $country = null;
            } else {
                $phone = $address['phone'] ?? null;
                $street1 = $address['street1'];
                $street2 = $address['street2'] ?? null;
                $city = $address['city'];
                $state = $address['state'];
                $zip = $address['zip'];
                $country = $address['country'];
            }

            // Calculate priority fee based on tier
            $fulfillmentPriority = $validated['fulfillment_priority'] ?? 'normal';
            $priorityFee = \App\Models\FulfillmentPriority::getPriceForTier($fulfillmentPriority, $tier);

            $orderData = [
                'ref_id' => $validated['ref_id'],
                'seller_id' => $user->id,
                'seller_ref' => $validated['seller_ref'] ?? null,
                'store_id' => $store->id,
                'fulfill_status' => $this->orderService->convertOrderStatus($validated['order_status']),
                'fulfillment_priority' => $fulfillmentPriority,
                'priority_fee' => $priorityFee,
                'shipping_method' => $validated['shipping_method'],
                'shipping_service' => $validated['shipping_service'] ?? ShippingService::USPS,
                'shipping_label' => null, // No shipping label for SELLER SHOP
                'note' => $validated['note'] ?? null,
                'order_type' => OrderType::PRINT,
                'product_type' => $validated['product_type'] ?? null,
                'payment_status' => OrderPaymentStatus::PENDING,
                'extra_fee' => OrderConstants::DEFAULT_EXTRA_FEE,
                'refund_fee' => OrderConstants::DEFAULT_REFUND_FEE,
                // Address information
                'first_name' => $firstName,
                'last_name' => $lastName,
                'phone' => $phone,
                'address_1' => $street1,
                'address_2' => $street2,
                'city' => $city,
                'state' => $state,
                'postcode' => $zip,
                'country' => $country,
            ];

            // Step 4: Create order with transaction
            $createResult = $this->orderService->createOrder($orderData, $request->all());

            if (!$createResult['success']) {
                return response()->json([
                    'code' => $createResult['code'] ?? HttpCode::SERVER_ERROR,
                    'status' => false,
                    'message' => $createResult['message'],
                    'error' => $createResult['error'] ?? null
                ], $createResult['code'] ?? 500);
            }

            // Check if idempotent response (order already exists)
            if (isset($createResult['idempotent'])) {
                // Get the existing order to check its status
                $existingOrder = Order::find($createResult['order_id']);

                // Always return 409 Conflict when order already exists
                return response()->json([
                    'code' => HttpCode::CONFLICT,
                    'status' => false,
                    'message' => ResponseMessage::ORDER_ALREADY_EXISTS,
                    'data' => [
                        'order_id' => $createResult['order_id'],
                        'ref_id' => $existingOrder->ref_id ?? null,
                        'fulfill_status' => $existingOrder->fulfill_status ?? 'unknown',
                        'created_at' => $existingOrder->created_at ?? null
                    ]
                ], HttpCode::CONFLICT);
            }

            $order = $createResult['order'];

            // Step 5: Dispatch background jobs
            \App\Jobs\ProcessOrderSellerShip::dispatch(
                $order->id,
                $validated['line_items'],
                $tier,
                $store
            );


            Log::info('SELLER SHOP order created successfully', [
                'order_id' => $order->id,
                'ref_id' => $order->ref_id,
                'seller_id' => $user->id,
                'has_address' => !$hasPlaceholder
            ]);

            // Return response immediately
            return response()->json([
                'code' => HttpCode::SUCCESS,
                'status' => true,
                'message' => ResponseMessage::SUCCESS,
                'data' => [
                    'order_id' => $order->id
                ]
            ], HttpCode::SUCCESS);
        } catch (\Illuminate\Http\Exceptions\HttpResponseException $e) {
            // Re-throw validation response
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to create SELLER SHOP order', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => ResponseMessage::ORDER_CREATION_FAILED,
                'error' => config('app.debug') ? $e->getMessage() : null
            ], HttpCode::SERVER_ERROR);
        }
    }

    /**
     * Create TUMBLER LABEL SHIP order
     * For tumbler/mug printing orders with pre-printed shipping labels
     */
    public function createOrderTumblerLabelShip(Request $request): JsonResponse
    {
        try {
            // Validate request
            $validated = app(\App\Http\Requests\CreateOrderTumblerLabelShipRequest::class)->validated();

            // Authenticate store
            $authResult = $this->orderService->authenticateStore($validated['api_key']);

            if (!$authResult['success']) {
                $this->trackFailedAuth($validated['api_key']);

                return response()->json([
                    'code' => $authResult['code'],
                    'status' => false,
                    'message' => $authResult['message']
                ], $authResult['code']);
            }

            $store = $authResult['store'];
            $user = $authResult['user'];
            $tier = $authResult['tier'];

            // Calculate priority fee
            $fulfillmentPriority = $validated['fulfillment_priority'] ?? 'normal';
            $priorityFee = \App\Models\FulfillmentPriority::getPriceForTier($fulfillmentPriority, $tier);

            // Prepare order data
            $orderData = [
                'ref_id' => $validated['ref_id'],
                'seller_id' => $user->id,
                'seller_ref' => $validated['seller_ref'] ?? null,
                'store_id' => $store->id,
                'fulfill_status' => $this->orderService->convertOrderStatus($validated['order_status']),
                'fulfillment_priority' => $fulfillmentPriority,
                'priority_fee' => $priorityFee,
                'shipping_method' => $validated['shipping_method'],
                'shipping_service' => $validated['shipping_service'] ?? ShippingService::USPS,
                'shipping_label' => $validated['shipping_label'],
                'note' => $validated['note'] ?? null,
                'order_type' => OrderType::PRINT,
                'product_type' => $validated['product_type'] ?? null,
                'payment_status' => OrderPaymentStatus::PENDING,
                'extra_fee' => 0.00, // No extra fee for Tumbler
                'refund_fee' => 0.00,
                // No address for LABEL SHIP
                'first_name' => null,
                'last_name' => null,
                'phone' => null,
                'address_1' => null,
                'address_2' => null,
                'city' => null,
                'state' => null,
                'postcode' => null,
                'country' => null,
            ];

            // Create order
            $createResult = $this->orderService->createOrder($orderData, $request->all());

            if (!$createResult['success']) {
                return response()->json([
                    'code' => $createResult['code'] ?? HttpCode::SERVER_ERROR,
                    'status' => false,
                    'message' => $createResult['message'],
                    'error' => $createResult['error'] ?? null
                ], $createResult['code'] ?? 500);
            }

            // Check idempotent response
            if (isset($createResult['idempotent'])) {
                $existingOrder = Order::find($createResult['order_id']);

                return response()->json([
                    'code' => HttpCode::CONFLICT,
                    'status' => false,
                    'message' => ResponseMessage::ORDER_ALREADY_EXISTS,
                    'data' => [
                        'order_id' => $createResult['order_id'],
                        'ref_id' => $existingOrder->ref_id ?? null,
                        'fulfill_status' => $existingOrder->fulfill_status ?? 'unknown',
                        'created_at' => $existingOrder->created_at ?? null
                    ]
                ], HttpCode::CONFLICT);
            }

            $order = $createResult['order'];

            // Dispatch Tumbler processing job
            \App\Jobs\ProcessOrderTumbler::dispatch(
                $order->id,
                $validated['line_items'],
                $tier,
                $store,
                true // hasShippingLabel
            );


            Log::info('TUMBLER LABEL SHIP order created successfully', [
                'order_id' => $order->id,
                'ref_id' => $order->ref_id,
                'seller_id' => $user->id
            ]);

            return response()->json([
                'code' => HttpCode::SUCCESS,
                'status' => true,
                'message' => ResponseMessage::SUCCESS,
                'data' => [
                    'order_id' => $order->id
                ]
            ], HttpCode::SUCCESS);
        } catch (\Illuminate\Http\Exceptions\HttpResponseException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to create TUMBLER LABEL SHIP order', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => ResponseMessage::ORDER_CREATION_FAILED,
                'error' => config('app.debug') ? $e->getMessage() : null
            ], HttpCode::SERVER_ERROR);
        }
    }

    /**
     * Create TUMBLER SELLER SHIP order
     * For tumbler/mug printing orders where seller provides shipping
     */
    public function createOrderTumblerSellerShip(Request $request): JsonResponse
    {
        try {
            // Validate request
            $validated = app(\App\Http\Requests\CreateOrderTumblerSellerShipRequest::class)->validated();

            // Authenticate store
            $authResult = $this->orderService->authenticateStore($validated['api_key']);

            if (!$authResult['success']) {
                $this->trackFailedAuth($validated['api_key']);

                return response()->json([
                    'code' => $authResult['code'],
                    'status' => false,
                    'message' => $authResult['message']
                ], $authResult['code']);
            }

            $store = $authResult['store'];
            $user = $authResult['user'];
            $tier = $authResult['tier'];

            // Parse address
            $address = $validated['address'];
            $nameParts = explode(' ', $address['name'], 2);
            $firstName = $nameParts[0] ?? null;
            $lastName = $nameParts[1] ?? null;

            // Check for placeholder character
            $hasPlaceholder = false;
            $addressFields = ['name', 'street1', 'street2', 'city', 'state', 'zip', 'country'];

            foreach ($addressFields as $field) {
                $value = $address[$field] ?? '';
                if (str_contains($value, OrderConstants::ADDRESS_PLACEHOLDER)) {
                    $hasPlaceholder = true;
                    break;
                }
            }

            if ($hasPlaceholder) {
                Log::warning('Tumbler order address contains placeholder', [
                    'ref_id' => $validated['ref_id'],
                    'address' => $address
                ]);

                $firstName = null;
                $lastName = null;
                $phone = null;
                $street1 = null;
                $street2 = null;
                $city = null;
                $state = null;
                $zip = null;
                $country = null;
            } else {
                $phone = $address['phone'] ?? null;
                $street1 = $address['street1'];
                $street2 = $address['street2'] ?? null;
                $city = $address['city'];
                $state = $address['state'];
                $zip = $address['zip'];
                $country = $address['country'];
            }

            // Calculate priority fee
            $fulfillmentPriority = $validated['fulfillment_priority'] ?? 'normal';
            $priorityFee = \App\Models\FulfillmentPriority::getPriceForTier($fulfillmentPriority, $tier);

            $orderData = [
                'ref_id' => $validated['ref_id'],
                'seller_id' => $user->id,
                'seller_ref' => $validated['seller_ref'] ?? null,
                'store_id' => $store->id,
                'fulfill_status' => $this->orderService->convertOrderStatus($validated['order_status']),
                'fulfillment_priority' => $fulfillmentPriority,
                'priority_fee' => $priorityFee,
                'shipping_method' => $validated['shipping_method'],
                'shipping_service' => $validated['shipping_service'] ?? ShippingService::USPS,
                'shipping_label' => null,
                'note' => $validated['note'] ?? null,
                'order_type' => OrderType::PRINT,
                'product_type' => $validated['product_type'] ?? null,
                'payment_status' => OrderPaymentStatus::PENDING,
                'extra_fee' => 0.00,
                'refund_fee' => 0.00,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'phone' => $phone,
                'address_1' => $street1,
                'address_2' => $street2,
                'city' => $city,
                'state' => $state,
                'postcode' => $zip,
                'country' => $country,
            ];

            // Create order
            $createResult = $this->orderService->createOrder($orderData, $request->all());

            if (!$createResult['success']) {
                return response()->json([
                    'code' => $createResult['code'] ?? HttpCode::SERVER_ERROR,
                    'status' => false,
                    'message' => $createResult['message'],
                    'error' => $createResult['error'] ?? null
                ], $createResult['code'] ?? 500);
            }

            // Check idempotent response
            if (isset($createResult['idempotent'])) {
                $existingOrder = Order::find($createResult['order_id']);

                return response()->json([
                    'code' => HttpCode::CONFLICT,
                    'status' => false,
                    'message' => ResponseMessage::ORDER_ALREADY_EXISTS,
                    'data' => [
                        'order_id' => $createResult['order_id'],
                        'ref_id' => $existingOrder->ref_id ?? null,
                        'fulfill_status' => $existingOrder->fulfill_status ?? 'unknown',
                        'created_at' => $existingOrder->created_at ?? null
                    ]
                ], HttpCode::CONFLICT);
            }

            $order = $createResult['order'];

            // Dispatch Tumbler processing job
            \App\Jobs\ProcessOrderTumbler::dispatch(
                $order->id,
                $validated['line_items'],
                $tier,
                $store,
                false // no shipping label
            );


            Log::info('TUMBLER SELLER SHIP order created successfully', [
                'order_id' => $order->id,
                'ref_id' => $order->ref_id,
                'seller_id' => $user->id,
                'has_address' => !$hasPlaceholder
            ]);

            return response()->json([
                'code' => HttpCode::SUCCESS,
                'status' => true,
                'message' => ResponseMessage::SUCCESS,
                'data' => [
                    'order_id' => $order->id
                ]
            ], HttpCode::SUCCESS);
        } catch (\Illuminate\Http\Exceptions\HttpResponseException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to create TUMBLER SELLER SHIP order', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => ResponseMessage::ORDER_CREATION_FAILED,
                'error' => config('app.debug') ? $e->getMessage() : null
            ], HttpCode::SERVER_ERROR);
        }
    }

    /**
     * Track failed authentication attempts
     */
    protected function trackFailedAuth(string $apiKey): void
    {
        $key = "api_key_failed_auth:{$apiKey}";
        $attempts = Cache::get($key, 0) + 1;

        Cache::put($key, $attempts, OrderConstants::FAILED_AUTH_CACHE_TTL);

        // Lock API key after max failed attempts
        if ($attempts >= OrderConstants::MAX_FAILED_AUTH_ATTEMPTS) {
            Cache::put("api_key_locked:{$apiKey}", OrderConstants::API_KEY_LOCK_TTL, OrderConstants::API_KEY_LOCK_TTL);

            Log::warning('API key locked due to failed authentication', [
                'api_key' => substr($apiKey, 0, 8) . '...',
                'attempts' => $attempts
            ]);
        }
    }

    /**
     * Get orders list with filters and pagination
     */
    public function getOrders(Request $request): JsonResponse
    {
        try {
            $perPage = $request->input('per_page', OrderConstants::DEFAULT_PER_PAGE);
            $page = $request->input('page', OrderConstants::DEFAULT_PAGE);

            // Get current user
            $user = Auth::user();
            // Check user role
            $userRole = $user->role->name ?? null;

            // Build optimized query with eager loading
            $query = Order::query()
                ->with([
                    'seller:id,username,email,role_id',
                    'seller.role:id,name',
                    'seller.profile:user_id,private_seller,first_name,last_name,wallet_balance',
                    'seller.profile.tier:id,tier_id,name',
                    'store:id,name,api_key',
                    'items:id,order_id,variant_id,product_name,quantity,price,mockup,mockup_back,status,sides',
                    'items.metas:id,order_item_id,meta_key,meta_value,switch,embroidery_type',
                    'items.productions:id,order_item_id,status,quantity',
                    'tracking:id,order_id,tracking_id,status,service,method,total_day,update_time',
                    'tickets:id,order_id',
                ])
                ->select([
                    'id',
                    'ref_id',
                    'seller_id',
                    'seller_ref',
                    'store_id',
                    'order_stt',
                    'fulfill_status',
                    'processing_status',
                    'payment_status',
                    'shipping_method',
                    'shipping_service',
                    'shipping_label',
                    'convert_label',
                    'tracking_id',
                    'tracking_link',
                    'total_cost',
                    'paid_cost',
                    'print_cost',
                    'shipping_cost',
                    'extra_fee',
                    'refund_fee',
                    'note',
                    'order_type',
                    'first_name',
                    'last_name',
                    'phone',
                    'address_1',
                    'city',
                    'state',
                    'postcode',
                    'country',
                    'created_at',
                    'updated_at',
                    'process_time',
                    'complete_time',
                    'shipped_at',
                ])
                ->withCount([
                    'items',
                    'items as total_quantity' => function ($query) {
                        $query->select(DB::raw('SUM(quantity)'));
                    },
                    'tickets'
                ]);

            // Apply role-based filtering
            // Seller: only see their own orders
            // Admin/Staff: see all orders
            if ($userRole === UserRole::all()[UserRole::SELLER]) {
                $query->where('seller_id', $user->id);
            }
            // Admin and Staff can see all orders (no additional filter)

            // Apply filters
            $this->applyOrderFilters($query, $request);

            // Apply sorting
            // Priority orders should appear first, then sort by user's criteria
            $sortBy = $request->input('sort_by', OrderConstants::DEFAULT_SORT_BY);
            $sortOrder = $request->input('sort_order', OrderConstants::DEFAULT_SORT_ORDER);

            // First, prioritize by fulfillment_priority (priority orders first)
            $query->orderByRaw("CASE WHEN fulfillment_priority = 'priority' THEN 0 ELSE 1 END ASC");
            // Then apply the user's sorting preference
            $query->orderBy($sortBy, $sortOrder);

            // Paginate
            $orders = $query->paginate($perPage, ['*'], 'page', $page);

            // Calculate summary - use total_cost from orders table (more accurate)
            // Build a fresh query for total calculation
            $summaryQuery = Order::query()
                ->whereNotIn('fulfill_status', ['new_order']);

            // Apply same filters as main query for consistency
            if ($userRole === 'Seller') {
                $summaryQuery->where('seller_id', $user->id);
            }
            if ($request->filled('fulfill_status')) {
                $statuses = is_array($request->input('fulfill_status'))
                    ? $request->input('fulfill_status')
                    : explode(',', $request->input('fulfill_status'));
                $summaryQuery->whereIn('fulfill_status', $statuses);
            }
            if ($request->filled('payment_status')) {
                $statuses = is_array($request->input('payment_status'))
                    ? $request->input('payment_status')
                    : explode(',', $request->input('payment_status'));
                $summaryQuery->whereIn('payment_status', $statuses);
            }
            if ($request->filled('date_from')) {
                $summaryQuery->whereDate('created_at', '>=', $request->input('date_from'));
            }
            if ($request->filled('date_to')) {
                $summaryQuery->whereDate('created_at', '<=', $request->input('date_to'));
            }

            // Sum total_cost from orders table directly
            $totalAmountAll = $summaryQuery->sum('total_cost');

            // Calculate current page total using total_cost from orders
            $currentPageOrderIds = collect($orders->items())->pluck('id');
            $currentPageTotal = Order::whereIn('id', $currentPageOrderIds)->sum('total_cost');

            // Transform data for frontend
            $orders->getCollection()->transform(function ($order) {
                return $this->transformOrderForList($order);
            });

            return response()->json([
                'code' => HttpCode::SUCCESS,
                'success' => true,
                'data' => [
                    'orders' => $orders->items(),
                    'pagination' => [
                        'current_page' => $orders->currentPage(),
                        'per_page' => $orders->perPage(),
                        'total' => $orders->total(),
                        'last_page' => $orders->lastPage(),
                        'from' => $orders->firstItem(),
                        'to' => $orders->lastItem(),
                    ],
                    'summary' => [
                        'total_amount_all' => round($totalAmountAll ?? 0, 2),
                        'total_amount_page' => round($currentPageTotal ?? 0, 2),
                        'orders_count' => $orders->total(),
                    ],
                    'filters' => [
                        'status' => $request->input('fulfill_status', 'all'),
                        'order_type' => $request->input('order_type', 'all'),
                        'date_range' => $this->getDateRangeLabel($request),
                    ],
                ],
                'message' => ResponseMessage::ORDERS_RETRIEVED
            ], HttpCode::SUCCESS);
        } catch (\Exception $e) {
            Log::error('Failed to get orders', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => ResponseMessage::ORDERS_RETRIEVED_FAILED,
                'error' => config('app.debug') ? $e->getMessage() : null
            ], HttpCode::SERVER_ERROR);
        }
    }

    /**
     * Get distinct embroidery types from embroidery_fee (for filter dropdown)
     */
    public function getEmbroideryTypes(): JsonResponse
    {
        try {
            $configuredTypes = DB::table('embroidery_fee')
                ->whereNotNull('embroidery_type')
                ->where('embroidery_type', '!=', '')
                ->distinct()
                ->pluck('embroidery_type')
                ->toArray();

            // Always include 'standard' as it's the default and might not be in fees table
            $types = array_unique(array_merge(['standard'], $configuredTypes));
            sort($types); // Alphabetical sort

            return response()->json([
                'code'    => 200,
                'success' => true,
                'data'    => array_values($types),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code'    => 500,
                'success' => false,
                'message' => 'Failed to fetch embroidery types: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all order IDs matching the filters (for bulk actions or ID export)
     */
    public function getOrderIds(Request $request): JsonResponse
    {
        try {
            // Get current user
            $user = Auth::user();
            // Check user role
            $userRole = $user->role->name ?? null;

            $query = Order::query();

            // Check user role
            if ($userRole === UserRole::all()[UserRole::SELLER]) {
                $query->where('seller_id', $user->id);
            }
            // Admin and Staff can see all orders (no additional filter)

            // Apply filters (reuses the same filter logic as getOrders)
            $this->applyOrderFilters($query, $request);

            // Get only IDs
            // Note: pluck returns a Collection, toArray converts it to array
            $ids = $query->pluck('id')->toArray();

            return response()->json([
                'code' => HttpCode::SUCCESS,
                'success' => true,
                'data' => $ids,
                'count' => count($ids),
                'message' => 'Order IDs retrieved successfully'
            ], HttpCode::SUCCESS);
        } catch (\Exception $e) {
            Log::error('Failed to get order IDs', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => 'Failed to retrieve order IDs',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], HttpCode::SERVER_ERROR);
        }
    }

    /**
     * Export URLs (PES, EMB, QR) for specific orders
     */
    public function exportOrderUrls(Request $request): JsonResponse
    {
        try {
            $ids = $request->input('ids');

            if (empty($ids) || !is_array($ids)) {
                return response()->json([
                    'code' => HttpCode::BAD_REQUEST,
                    'status' => false,
                    'message' => 'Invalid or missing ids'
                ], HttpCode::BAD_REQUEST);
            }

            $orders = Order::with(['items', 'items.metas' => function ($query) {
                $query->select('id', 'order_item_id', 'meta_key', 'meta_value');
            }])
                ->whereIn('id', $ids)
                ->select('id', 'ref_id', 'order_stt')
                ->get();

            $data = $orders->map(function ($order) {
                // Flatten items
                $itemsData = $order->items->map(function ($item) {
                    $metas = $item->metas;

                    // Group urls
                    // NOTE: pes_urls field is repurposed to expose merge_image URLs
                    // so existing API consumers keep working without changes.
                    $pesUrls = $metas->where('meta_key', 'merge_image')
                        ->pluck('meta_value')->values()->all();

                    $embUrls = $metas->filter(function ($m) {
                        return str_ends_with($m->meta_key, '_emb');
                    })->pluck('meta_value')->values()->all();

                    $qrUrls = $metas->where('meta_key', 'special_design_qr')
                        ->pluck('meta_value')->values()->all();

                    return [
                        'pes_urls' => $pesUrls,
                        'qr_urls' => $qrUrls,
                        'emb_urls' => $embUrls
                    ];
                });

                return [
                    'order_id' => $order->id,
                    'items' => $itemsData
                ];
            });

            return response()->json([
                'code' => HttpCode::SUCCESS,
                'status' => true,
                'data' => $data
            ], HttpCode::SUCCESS);
        } catch (\Exception $e) {
            Log::error('Failed to export order URLs', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => 'Failed to export URLs',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], HttpCode::SERVER_ERROR);
        }
    }

    /**
     * Apply filters to order query
     */
    protected function applyOrderFilters($query, Request $request): void
    {
        // Filter by ref_id (search)
        if ($request->filled('ref_id')) {
            $query->where('ref_id', 'like', '%' . $request->input('ref_id') . '%');
        }

        // Filter by seller_ref (search)
        if ($request->filled('seller_ref')) {
            $query->where('seller_ref', 'like', '%' . $request->input('seller_ref') . '%');
        }

        // Filter by tracking_number
        if ($request->filled('tracking_number')) {
            $trackingNumber = $request->input('tracking_number');
            $query->where(function ($q) use ($trackingNumber) {
                $q->where('tracking_id', 'like', "%{$trackingNumber}%")
                    ->orWhereHas('tracking', function ($q2) use ($trackingNumber) {
                        $q2->where('tracking_id', 'like', "%{$trackingNumber}%");
                    });
            });
        }

        // Filter by Order ID (Primary Key) - supports multiple IDs separated by space/comma
        // Uses EXACT MATCH - searching "46 56" will only return orders with ID 46 and 56
        if ($request->filled('order_id')) {
            $orderIdInput = $request->input('order_id');
            // Split by space, comma, or both and filter empty values
            $orderIds = array_filter(
                preg_split('/[\s,]+/', $orderIdInput),
                fn($id) => !empty(trim($id))
            );

            // Convert to integers since ID is integer
            $orderIds = array_map(fn($id) => (int) trim($id), $orderIds);

            if (count($orderIds) === 1) {
                // Single ID - exact match
                $query->where('id', $orderIds[0]);
            } elseif (count($orderIds) > 1) {
                // Multiple IDs - use whereIn for exact match
                $query->whereIn('id', $orderIds);
            }
        }

        // Filter by fulfill_status
        if ($request->filled('fulfill_status')) {
            $inputStatus = $request->input('fulfill_status');

            if (is_array($inputStatus)) {
                $statuses = $inputStatus;
            } elseif (is_string($inputStatus) && str_contains($inputStatus, ',')) {
                $statuses = array_map('trim', explode(',', $inputStatus));
            } else {
                $statuses = [$inputStatus];
            }

            // Security: If Seller tries to filter by 'pending_stock', convert to 'confirm'
            // This prevents Sellers from discovering stock shortage info via API
            $user = Auth::user();
            $userRole = $user->role->name ?? null;
            $roleMap = UserRole::all();
            if ($userRole === $roleMap[UserRole::SELLER]) {
                // Expand status groups for Seller:
                // - 'confirm' includes: confirm, pending_stock
                // - 'producing' includes: in_stock, producing, qc_pass  
                // - 'shipped' includes: packed, shipped
                $expandedStatuses = [];
                foreach ($statuses as $status) {
                    switch ($status) {
                        case 'confirm':
                            $expandedStatuses = array_merge($expandedStatuses, ['confirm', 'pending_stock']);
                            break;
                        case 'producing':
                            $expandedStatuses = array_merge($expandedStatuses, ['in_stock', 'producing', 'qc_pass']);
                            break;
                        case 'shipped':
                            $expandedStatuses = array_merge($expandedStatuses, ['packed', 'shipped']);
                            break;
                        default:
                            $expandedStatuses[] = $status;
                            break;
                    }
                }
                $statuses = array_unique($expandedStatuses);
            }

            $query->whereIn('fulfill_status', $statuses);
        }

        // Exclude certain statuses (used for default view to hide cancelled/shipped orders)
        if ($request->filled('exclude_status')) {
            $excludeInput = $request->input('exclude_status');
            if (is_array($excludeInput)) {
                $excludeStatuses = $excludeInput;
            } elseif (is_string($excludeInput) && str_contains($excludeInput, ',')) {
                // Handle comma-separated string
                $excludeStatuses = array_map('trim', explode(',', $excludeInput));
            } else {
                $excludeStatuses = [$excludeInput];
            }
            $query->whereNotIn('fulfill_status', $excludeStatuses);
        }

        // Filter by payment_status
        if ($request->filled('payment_status')) {
            $statuses = is_array($request->input('payment_status'))
                ? $request->input('payment_status')
                : [$request->input('payment_status')];
            $query->whereIn('payment_status', $statuses);
        }

        // Filter by processing_status
        if ($request->filled('processing_status')) {
            $query->where('processing_status', $request->input('processing_status'));
        }

        // Filter by seller_id
        if ($request->filled('seller_id')) {
            $query->where('seller_id', $request->input('seller_id'));
        }

        // Filter by store_id
        if ($request->filled('store_id')) {
            $query->where('store_id', $request->input('store_id'));
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        // Filter by shipped date range (when the order actually left the workshop),
        // used to reconcile a given day's outbound batch against the carrier.
        //
        // A "ship day" is bounded by the daily noon cutoff in the workshop's local
        // timezone, NOT by midnight: ship-day D = orders scanned shipped in the window
        // (D-1 12:00, D 12:00] local. This keeps the cross-midnight production batch
        // ("afternoon + next morning → noon ship") whole. shipped_at is stored in UTC,
        // so the local-noon boundaries are converted to UTC before comparing.
        $tz = OrderConstants::SHIP_BUSINESS_TIMEZONE;
        $cutoffHour = OrderConstants::SHIP_CUTOFF_HOUR;

        if ($request->filled('shipped_date_from')) {
            $from = Carbon::parse($request->input('shipped_date_from'), $tz)
                ->subDay()
                ->setTime($cutoffHour, 0, 0)
                ->utc();
            $query->where('shipped_at', '>', $from);
        }

        if ($request->filled('shipped_date_to')) {
            $to = Carbon::parse($request->input('shipped_date_to'), $tz)
                ->setTime($cutoffHour, 0, 0)
                ->utc();
            $query->where('shipped_at', '<=', $to);
        }

        // Filter by total cost range
        if ($request->filled('cost_min')) {
            $query->where('total_cost', '>=', $request->input('cost_min'));
        }

        if ($request->filled('cost_max')) {
            $query->where('total_cost', '<=', $request->input('cost_max'));
        }

        // Search across multiple fields
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('ref_id', 'like', "%{$search}%")
                    ->orWhere('seller_ref', 'like', "%{$search}%")
                    ->orWhere('order_stt', 'like', "%{$search}%")
                    ->orWhere('tracking_id', 'like', "%{$search}%");
            });
        }

        // Filter by Product Name
        if ($request->filled('product_name')) {
            $productName = $request->input('product_name');
            $query->whereHas('items', function ($q) use ($productName) {
                $q->where('product_name', 'like', "%{$productName}%");
            });
        }

        // Filter by Style
        if ($request->filled('style')) {
            $style = $request->input('style');
            $query->whereHas('items.productVariant', function ($q) use ($style) {
                $q->where('style', 'like', "%{$style}%");
            });
        }

        // Filter by Color
        if ($request->filled('color')) {
            $color = $request->input('color');
            $query->whereHas('items.productVariant', function ($q) use ($color) {
                $q->where('color', 'like', "%{$color}%");
            });
        }

        // Filter by Size
        if ($request->filled('size')) {
            $size = $request->input('size');
            $query->whereHas('items.productVariant', function ($q) use ($size) {
                $q->where('size', 'like', "%{$size}%");
            });
        }

        // Filter by Variant ID
        if ($request->filled('variant_id')) {
            $variantId = $request->input('variant_id');
            $query->whereHas('items', function ($q) use ($variantId) {
                $q->where('variant_id', 'like', "%{$variantId}%");
            });
        }

        // Filter by order_type
        if ($request->filled('order_type')) {
            $query->where('order_type', $request->input('order_type'));
        }

        // Filter by Missing Shipping Info (Label, Tracking, or Convert Label)
        if ($request->boolean('missing_shipping_info')) {
            $query->where(function ($q) {
                $q->where(function ($sub) {
                    $sub->whereNull('shipping_label')
                        ->orWhere('shipping_label', '')
                        ->orWhereRaw("TRIM(shipping_label) = ''");
                })
                    ->orWhere(function ($sub) {
                        $sub->whereNull('tracking_id')
                            ->orWhere('tracking_id', '')
                            ->orWhereRaw("TRIM(tracking_id) = ''");
                    })
                    ->orWhere(function ($sub) {
                        $sub->whereNull('convert_label')
                            ->orWhere('convert_label', '')
                            ->orWhereRaw("TRIM(convert_label) = ''");
                    });
            });
        }

        // Filter by Embroidery Type (from order_item_metas.embroidery_type column)
        if ($request->filled('embroidery_type')) {
            $embType = $request->input('embroidery_type');
            $query->whereHas('items.metas', function ($q) use ($embType) {
                $q->whereIn('meta_key', ['front', 'back', 'sleeve_left', 'sleeve_right', 'neck'])
                    ->where('embroidery_type', $embType);
            });
        }
    }

    /**
     * Transform order data for list view based on user role
     */
    protected function transformOrderForList($order): array
    {
        $user = Auth::user();
        $userRole = $user->role->name ?? null;

        // Calculate production status
        $productionStatuses = $order->items->flatMap(function ($item) {
            return $item->productions->pluck('status');
        })->unique()->values()->toArray();

        // Base order data (common for all roles)
        $baseData = [
            'id' => $order->id,
            'ref_id' => $order->ref_id,
            'seller_ref' => $order->seller_ref,
            'order_stt' => $order->order_stt,
            'order_type' => $order->order_type,
            'status' => $order->fulfill_status, // For compatibility
            'fulfill_status' => $order->fulfill_status,
            'payment_status' => $order->payment_status,
            'label_status' => $order->label_status, // ShipDVX shipping-creation status (null = chưa tạo VC)
            'has_ticket' => ($order->tickets_count ?? 0) > 0,
            'support_ticket' => $order->tickets->isNotEmpty() ? [
                'id' => $order->tickets->first()->id
            ] : null,
        ];

        // Role-specific data transformation
        $roleMap = UserRole::all();
        switch ($userRole) {
            case $roleMap[UserRole::ADMIN]:
                return $this->transformForAdmin($order, $baseData, $productionStatuses);

            case $roleMap[UserRole::STAFF]:
                return $this->transformForStaff($order, $baseData, $productionStatuses);

            case $roleMap[UserRole::SELLER]:
                return $this->transformForSeller($order, $baseData, $productionStatuses);

            default:
                // Fallback to seller view for unknown roles
                return $this->transformForSeller($order, $baseData, $productionStatuses);
        }
    }

    /**
     * Transform order data for Admin role - FULL ACCESS
     */
    protected function transformForAdmin($order, $baseData, $productionStatuses): array
    {
        return array_merge($baseData, [
            'processing_status' => $order->processing_status,
            'production_statuses' => $productionStatuses,
            'priority_level' => OrderConstants::DEFAULT_PRIORITY_LEVEL,

            // Fields needed for Edit Order
            'convert_label' => $order->convert_label,
            'note' => $order->note,
            'mockup_product' => $order->items->first()?->productVariant?->product?->mockup,

            // Full seller information
            'seller' => [
                'id' => $order->seller?->id,
                'username' => $order->seller?->username,
                'email' => $order->seller?->email,
                'tier' => $order->seller?->profile?->tier?->name ?? 'Unknown',
                'store_name' => $order->store?->name,
            ],

            // Store info with api_key for edit
            'store' => [
                'id' => $order->store?->id,
                'name' => $order->store?->name,
                'api_key' => $order->store?->api_key,
            ],

            // Full shipping information
            'shipping' => [
                'method' => $order->shipping_method,
                'service' => $order->shipping_service,
                'label_url' => $order->shipping_label,
                'tracking_id' => $order->tracking_id,
                'address' => [
                    'first_name' => $order->first_name,
                    'last_name' => $order->last_name,
                    'phone' => $order->phone,
                    'street1' => $order->address_1,
                    'street2' => $order->address_2,
                    'city' => $order->city,
                    'state' => $order->state,
                    'zip' => $order->postcode,
                    'country' => $order->country,
                ],
            ],

            // Full items information with design files
            'items' => $order->items->map(function ($item) {
                // Group design files by position
                $designsByPosition = [];

                foreach ($item->metas as $meta) {
                    $metaKey = $meta->meta_key;

                    if (in_array($metaKey, ['front_image', 'wrap_image'])) {
                        $position = str_replace('_image', '', $metaKey);
                        if (!isset($designsByPosition[$position])) {
                            $designsByPosition[$position] = [
                                'position' => $position,
                                'meta_id' => null,
                                'pdf_url' => null,
                                'dst_url' => null,
                                'emb_url' => null,
                                'pes_url' => null,
                                'stitch_count' => 0,
                            ];
                        }

                        $designsByPosition[$position]['pdf_url'] = $meta->meta_value;
                        $designsByPosition[$position]['meta_id'] = $meta->id;
                        continue;
                    }

                    // Handle PES files (stored as 'front', 'back', 'sleeve_left', 'sleeve_right', 'neck')
                    if (in_array($metaKey, ['front', 'back', 'sleeve_left', 'sleeve_right', 'neck'])) {
                        $position = $metaKey;
                        if (!isset($designsByPosition[$position])) {
                            $designsByPosition[$position] = [
                                'position' => $position,
                                'meta_id' => null,
                                'pdf_url' => null,
                                'dst_url' => null,
                                'emb_url' => null,
                                'pes_url' => null,
                                'stitch_count' => 0,
                                'embroidery_type' => 'standard',
                            ];
                        }
                        $designsByPosition[$position]['pes_url'] = $meta->meta_value;
                        $designsByPosition[$position]['meta_id'] = $meta->id;
                        $designsByPosition[$position]['embroidery_type'] = $meta->embroidery_type ?? 'standard';
                        continue;
                    }

                    // Handle other files (pdf/dst/emb/json)
                    if (preg_match('/^(front|back|sleeve_left|sleeve_right|neck)_(pdf|dst|emb|json)$/', $metaKey, $matches)) {
                        $position = $matches[1];
                        $type = $matches[2];

                        if (!isset($designsByPosition[$position])) {
                            $designsByPosition[$position] = [
                                'position' => $position,
                                'meta_id' => null,
                                'pdf_url' => null,
                                'dst_url' => null,
                                'emb_url' => null,
                                'pes_url' => null,
                                'stitch_count' => 0,
                            ];
                        }

                        $designsByPosition[$position]["{$type}_url"] = $meta->meta_value;

                        // Wood design (PDF): expose its meta id so "Remake Des" can target it.
                        // Only when no PES/image meta has already claimed meta_id for this position.
                        if ($type === 'pdf' && empty($designsByPosition[$position]['meta_id'])) {
                            $designsByPosition[$position]['meta_id'] = $meta->id;
                        }

                        if ($type === 'dst' && $meta->switch > 0) {
                            $designsByPosition[$position]['stitch_count'] = (int) $meta->switch;
                        }
                    }
                }

                // Get all QR codes for this item
                $qrCodes = $item->metas
                    ->where('meta_key', 'special_design_qr')
                    ->pluck('meta_value')
                    ->filter()
                    ->values()
                    ->toArray();

                // Get all merge images for this item (for Tumbler/Print orders)
                $mergeImages = $item->metas
                    ->where('meta_key', 'merge_image')
                    ->pluck('meta_value')
                    ->filter()
                    ->values()
                    ->toArray();

                return [
                    'id' => $item->id,
                    'variant_id' => $item->variant_id,
                    'product_name' => $item->product_name,
                    'quantity' => (int) $item->quantity,
                    'status' => (bool) $item->status,
                    'mockup' => $item->mockup,
                    'mockup_back' => $item->mockup_back,
                    'designs' => array_values($designsByPosition),
                    'qr_codes' => $qrCodes,
                    'merge_images' => $mergeImages,
                    'variant' => $item->productVariant ? [
                        'style' => $item->productVariant->style,
                        'color' => $item->productVariant->color,
                        'size' => $item->productVariant->size,
                        'sku' => $item->productVariant->sku,
                    ] : null,
                ];
            }),

            // Full pricing information with profit margins
            'pricing' => [
                'print_cost' => (float) ($order->print_cost ?? 0),
                'shipping_cost' => (float) ($order->shipping_cost ?? 0),
                'extra_fee' => (float) ($order->extra_fee ?? 0),
                'refund_fee' => (float) ($order->refund_fee ?? 0),
                'priority_fee' => 0.00, // Not available in current schema
                'total_cost' => (float) ($order->total_cost ?? 0),
                'profit_margin' => $this->calculateProfitMargin($order),
                'cost_breakdown' => [
                    'base_cost' => (float) ($order->print_cost ?? 0),
                    'two_side_cost' => 0.00, // Can be calculated from items
                    'sleeve_cost' => 0.00, // Not available
                    'special_design_cost' => 0.00, // Not available
                ],
            ],



            // Financial information
            'financial' => [
                'seller_payout' => $this->calculateSellerPayout($order),
                'platform_fee' => $this->calculatePlatformFee($order),
            ],

            // Timestamps - No calculated deadlines
            'timestamps' => [
                'created_at' => $order->created_at?->toIso8601String(),
                'updated_at' => $order->updated_at?->toIso8601String(),
                'shipped_at' => $order->shipped_at?->toIso8601String(),
            ],

            // Note
            'note' => $order->note,
        ]);
    }

    /**
     * Transform order data for Staff role - OPERATIONAL ACCESS
     */
    protected function transformForStaff($order, $baseData, $productionStatuses): array
    {
        return array_merge($baseData, [
            'processing_status' => $order->processing_status,
            'production_statuses' => $productionStatuses,
            'priority_level' => OrderConstants::DEFAULT_PRIORITY_LEVEL,

            // Fields needed for Edit Order
            'convert_label' => $order->convert_label,
            'note' => $order->note,
            'mockup_product' => $order->items->first()?->productVariant?->product?->mockup,

            // Basic seller information
            'seller' => [
                'id' => $order->seller?->id,
                'username' => $order->seller?->username,
                'tier' => $order->seller?->profile?->tier?->name ?? 'Unknown',
                'store_name' => $order->store?->name,
            ],

            // Store info with api_key for edit
            'store' => [
                'id' => $order->store?->id,
                'name' => $order->store?->name,
                'api_key' => $order->store?->api_key,
            ],

            // Full shipping information for operations
            'shipping' => [
                'method' => $order->shipping_method,
                'service' => $order->shipping_service,
                'label_url' => $order->shipping_label,
                'tracking_id' => $order->tracking_id,
                'address' => [
                    'first_name' => $order->first_name,
                    'last_name' => $order->last_name,
                    'phone' => $order->phone,
                    'street1' => $order->address_1,
                    'street2' => $order->address_2,
                    'city' => $order->city,
                    'state' => $order->state,
                    'zip' => $order->postcode,
                    'country' => $order->country,
                ],
            ],

            // Items with production details and design files
            'items' => $order->items->map(function ($item) {
                // Group design files by position
                $designsByPosition = [];

                foreach ($item->metas as $meta) {
                    $metaKey = $meta->meta_key;

                    if (in_array($metaKey, ['front_image', 'wrap_image'])) {
                        $position = str_replace('_image', '', $metaKey);
                        if (!isset($designsByPosition[$position])) {
                            $designsByPosition[$position] = [
                                'position' => $position,
                                'meta_id' => null,
                                'pdf_url' => null,
                                'dst_url' => null,
                                'emb_url' => null,
                                'pes_url' => null,
                                'stitch_count' => 0,
                            ];
                        }

                        $designsByPosition[$position]['pdf_url'] = $meta->meta_value;
                        $designsByPosition[$position]['meta_id'] = $meta->id;
                        continue;
                    }

                    // Handle PES files (stored as 'front', 'back', 'sleeve_left', 'sleeve_right', 'neck')
                    if (in_array($metaKey, ['front', 'back', 'sleeve_left', 'sleeve_right', 'neck'])) {
                        $position = $metaKey;
                        if (!isset($designsByPosition[$position])) {
                            $designsByPosition[$position] = [
                                'position' => $position,
                                'meta_id' => null,
                                'pdf_url' => null,
                                'dst_url' => null,
                                'emb_url' => null,
                                'pes_url' => null,
                                'stitch_count' => 0,
                                'embroidery_type' => 'standard',
                            ];
                        }
                        $designsByPosition[$position]['pes_url'] = $meta->meta_value;
                        $designsByPosition[$position]['meta_id'] = $meta->id;
                        $designsByPosition[$position]['embroidery_type'] = $meta->embroidery_type ?? 'standard';
                        continue;
                    }

                    // Handle other files (pdf/dst/emb)
                    if (preg_match('/^(front|back|sleeve_left|sleeve_right|neck)_(pdf|dst|emb|json)$/', $metaKey, $matches)) {
                        $position = $matches[1];
                        $type = $matches[2];

                        if (!isset($designsByPosition[$position])) {
                            $designsByPosition[$position] = [
                                'position' => $position,
                                'meta_id' => null,
                                'pdf_url' => null,
                                'dst_url' => null,
                                'emb_url' => null,
                                'pes_url' => null,
                                'stitch_count' => 0,
                            ];
                        }

                        $designsByPosition[$position]["{$type}_url"] = $meta->meta_value;

                        // Wood design (PDF): expose its meta id so "Remake Des" can target it.
                        // Only when no PES/image meta has already claimed meta_id for this position.
                        if ($type === 'pdf' && empty($designsByPosition[$position]['meta_id'])) {
                            $designsByPosition[$position]['meta_id'] = $meta->id;
                        }

                        if ($type === 'dst' && $meta->switch > 0) {
                            $designsByPosition[$position]['stitch_count'] = (int) $meta->switch;
                        }
                    }
                }

                // Get all QR codes for this item
                $qrCodes = $item->metas
                    ->where('meta_key', 'special_design_qr')
                    ->pluck('meta_value')
                    ->filter()
                    ->values()
                    ->toArray();

                // Get all merge images for this item (for Tumbler/Print orders)
                $mergeImages = $item->metas
                    ->where('meta_key', 'merge_image')
                    ->pluck('meta_value')
                    ->filter()
                    ->values()
                    ->toArray();

                return [
                    'id' => $item->id,
                    'variant_id' => $item->variant_id,
                    'product_name' => $item->product_name,
                    'quantity' => (int) $item->quantity,
                    'status' => (bool) $item->status,
                    'mockup' => $item->mockup,
                    'mockup_back' => $item->mockup_back,
                    'designs' => array_values($designsByPosition),
                    'qr_codes' => $qrCodes,
                    'merge_images' => $mergeImages,
                    'variant' => $item->productVariant ? [
                        'style' => $item->productVariant->style,
                        'color' => $item->productVariant->color,
                        'size' => $item->productVariant->size,
                        'sku' => $item->productVariant->sku,
                    ] : null,
                ];
            }),

            // Pricing information
            'pricing' => [
                'print_cost' => (float) ($order->print_cost ?? 0),
                'shipping_cost' => (float) ($order->shipping_cost ?? 0),
                'extra_fee' => (float) ($order->extra_fee ?? 0),
                'refund_fee' => (float) ($order->refund_fee ?? 0),
                'total_cost' => (float) ($order->total_cost ?? 0),
            ],

            // Production details
            'production' => [
                'status' => $productionStatuses[0] ?? 'pending',
                'assigned_to' => null,
                'estimated_completion' => null,
                'actual_completion' => $order->complete_time?->toIso8601String(),
                'notes' => $order->note,
                'machine_requirements' => ['embroidery', 'cutting'], // Default values
                'material_requirements' => $this->getMaterialRequirements($order),
            ],

            // Timestamps
            'timestamps' => [
                'created_at' => $order->created_at?->toIso8601String(),
                'updated_at' => $order->updated_at?->toIso8601String(),
                'shipped_at' => $order->shipped_at?->toIso8601String(),
                'production_deadline' => $order->created_at?->addDays(3)->toIso8601String(),
            ],
        ]);
    }

    /**
     * Transform order data for Seller role - LIMITED ACCESS
     * Same JSON structure as Admin, but sensitive fields are null/empty
     */
    protected function transformForSeller($order, $baseData, $productionStatuses): array
    {
        // Transform internal statuses to grouped display statuses for Seller:
        // - pending_stock -> confirm
        // - in_stock, qc_pass -> producing
        // - packed -> shipped
        $displayFulfillStatus = match ($order->fulfill_status) {
            'pending_stock' => 'confirm',
            'in_stock', 'qc_pass' => 'producing',
            'packed' => 'shipped',
            default => $order->fulfill_status,
        };

        // Override fulfill_status in baseData for Seller view
        $baseData['status'] = $displayFulfillStatus;
        $baseData['fulfill_status'] = $displayFulfillStatus;

        return array_merge($baseData, [
            // Hidden from seller - set to null
            'processing_status' => null,
            'production_statuses' => [],
            'priority_level' => null,

            // Basic fields for order tracking
            'convert_label' => $order->convert_label,
            'note' => $order->note,
            'mockup_product' => $order->items->first()?->productVariant?->product?->mockup,

            // Seller info - hidden (they know who they are)
            'seller' => null,

            // Store info WITHOUT api_key (sensitive)
            'store' => [
                'id' => $order->store?->id,
                'name' => $order->store?->name,
                'api_key' => null, // Hidden from seller
            ],

            // Shipping information (address visible for seller to verify)
            'shipping' => [
                'method' => $order->shipping_method,
                'service' => $order->shipping_service,
                'label_url' => $order->shipping_label,
                'tracking_id' => $order->tracking_id,
                'address' => [
                    'first_name' => $order->first_name,
                    'last_name' => $order->last_name,
                    'phone' => $order->phone,
                    'street1' => $order->address_1,
                    'street2' => $order->address_2,
                    'city' => $order->city,
                    'state' => $order->state,
                    'zip' => $order->postcode,
                    'country' => $order->country,
                ],
            ],

            // Items with full design info (same as Admin)
            'items' => $order->items->map(function ($item) {
                // Group design files by position - same structure as Admin
                $designsByPosition = [];

                foreach ($item->metas as $meta) {
                    $metaKey = $meta->meta_key;

                    if (in_array($metaKey, ['front_image', 'wrap_image'])) {
                        $position = str_replace('_image', '', $metaKey);
                        if (!isset($designsByPosition[$position])) {
                            $designsByPosition[$position] = [
                                'position' => $position,
                                'meta_id' => null,
                                'pdf_url' => null,
                                'dst_url' => null,
                                'emb_url' => null,
                                'pes_url' => null,
                                'stitch_count' => 0,
                            ];
                        }

                        $designsByPosition[$position]['pdf_url'] = $meta->meta_value;
                        $designsByPosition[$position]['meta_id'] = $meta->id;
                        continue;
                    }

                    // Handle PES files (stored as 'front', 'back', 'sleeve_left', 'sleeve_right', 'neck')
                    if (in_array($metaKey, ['front', 'back', 'sleeve_left', 'sleeve_right', 'neck'])) {
                        $position = $metaKey;
                        if (!isset($designsByPosition[$position])) {
                            $designsByPosition[$position] = [
                                'position' => $position,
                                'meta_id' => null,
                                'pdf_url' => null,
                                'dst_url' => null,
                                'emb_url' => null,
                                'pes_url' => null,
                                'json_url' => null,
                                'stitch_count' => 0,
                                'embroidery_type' => 'standard',
                            ];
                        }
                        $designsByPosition[$position]['pes_url'] = $meta->meta_value;
                        $designsByPosition[$position]['meta_id'] = $meta->id;
                        $designsByPosition[$position]['embroidery_type'] = $meta->embroidery_type ?? 'standard';
                        continue;
                    }

                    // Handle other files (pdf/dst/emb/json)
                    if (preg_match('/^(front|back|sleeve_left|sleeve_right|neck)_(pdf|dst|emb|json)$/', $metaKey, $matches)) {
                        $position = $matches[1];
                        $type = $matches[2];

                        if (!isset($designsByPosition[$position])) {
                            $designsByPosition[$position] = [
                                'position' => $position,
                                'meta_id' => null,
                                'pdf_url' => null,
                                'dst_url' => null,
                                'emb_url' => null,
                                'pes_url' => null,
                                'json_url' => null,
                                'stitch_count' => 0,
                            ];
                        }

                        $designsByPosition[$position]["{$type}_url"] = $meta->meta_value;

                        // Wood design (PDF): expose its meta id so "Remake Des" can target it.
                        // Only when no PES/image meta has already claimed meta_id for this position.
                        if ($type === 'pdf' && empty($designsByPosition[$position]['meta_id'])) {
                            $designsByPosition[$position]['meta_id'] = $meta->id;
                        }

                        if ($type === 'dst' && $meta->switch > 0) {
                            $designsByPosition[$position]['stitch_count'] = (int) $meta->switch;
                        }
                    }
                }

                // Get all QR codes for this item
                $qrCodes = $item->metas
                    ->where('meta_key', 'special_design_qr')
                    ->pluck('meta_value')
                    ->filter()
                    ->values()
                    ->toArray();

                // Get all merge images for this item (for Tumbler/Print orders)
                $mergeImages = $item->metas
                    ->where('meta_key', 'merge_image')
                    ->pluck('meta_value')
                    ->filter()
                    ->values()
                    ->toArray();

                return [
                    'id' => $item->id,
                    'variant_id' => $item->variant_id,
                    'product_name' => $item->product_name,
                    'quantity' => (int) $item->quantity,
                    'status' => (bool) $item->status,
                    'mockup' => $item->mockup,
                    'mockup_back' => $item->mockup_back,
                    'designs' => array_values($designsByPosition),
                    'qr_codes' => $qrCodes,
                    'merge_images' => $mergeImages,
                    'variant' => $item->productVariant ? [
                        'style' => $item->productVariant->style,
                        'color' => $item->productVariant->color,
                        'size' => $item->productVariant->size,
                        'sku' => $item->productVariant->sku,
                    ] : null,
                ];
            }),

            // Pricing - same structure, but internal fields hidden
            'pricing' => [
                'print_cost' => (float) ($order->print_cost ?? 0),
                'shipping_cost' => (float) ($order->shipping_cost ?? 0),
                'extra_fee' => (float) ($order->extra_fee ?? 0),
                'refund_fee' => (float) ($order->refund_fee ?? 0),
                'priority_fee' => (float) ($order->priority_fee ?? 0),
                'total_cost' => (float) ($order->total_cost ?? 0),
                'profit_margin' => null, // Hidden from seller
                'cost_breakdown' => null, // Hidden from seller
            ],

            // Financial - hidden from seller
            'financial' => null,

            // Timestamps - same structure
            'timestamps' => [
                'created_at' => $order->created_at?->toIso8601String(),
                'updated_at' => $order->updated_at?->toIso8601String(),
                'shipped_at' => $order->shipped_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Format full address
     */
    protected function formatFullAddress($order): ?string
    {
        if (empty($order->address_1)) {
            return null;
        }

        $parts = array_filter([
            $order->address_1,
            $order->city,
            $order->state,
            $order->postcode,
            $order->country,
        ]);

        return implode(', ', $parts);
    }

    /**
     * Calculate profit margin for admin view
     */
    protected function calculateProfitMargin($order): float
    {
        if ($order->total_cost <= 0) {
            return 0.0;
        }

        // Simplified calculation - can be enhanced with actual cost data
        $estimatedCost = $order->print_cost * OrderConstants::ESTIMATED_COST_PERCENTAGE;
        $profit = $order->total_cost - $estimatedCost;

        return round(($profit / $order->total_cost) * 100, 1);
    }

    /**
     * Calculate seller payout for admin view
     */
    protected function calculateSellerPayout($order): float
    {
        return round($order->total_cost * OrderConstants::SELLER_PAYOUT_PERCENTAGE, 2);
    }

    /**
     * Calculate platform fee for admin view
     */
    protected function calculatePlatformFee($order): float
    {
        return round($order->total_cost * OrderConstants::PLATFORM_FEE_PERCENTAGE, 2);
    }

    /**
     * Calculate seller profit for seller view
     */
    protected function calculateSellerProfit($order): float
    {
        return round($order->total_cost * OrderConstants::SELLER_PROFIT_PERCENTAGE, 2);
    }

    /**
     * Get order tags for admin view
     */
    protected function getOrderTags($order): array
    {
        $tags = [];

        // Add tags based on order characteristics
        if ($order->fulfill_status === OrderFulfillStatus::EXPRESS) {
            $tags[] = 'rush';
        }

        if ($order->total_cost > OrderConstants::HIGH_VALUE_THRESHOLD) {
            $tags[] = 'high_value';
        }

        if ($order->seller?->profile?->tier?->name === 'Diamond') {
            $tags[] = 'vip_customer';
        }

        return $tags;
    }

    /**
     * Get material requirements for staff view
     */
    protected function getMaterialRequirements($order): array
    {
        $materials = [];

        foreach ($order->items as $item) {
            // Extract material info from product name (simplified)
            if (str_contains(strtolower($item->product_name), 'black')) {
                $materials[] = 'black_tshirt_' . strtolower(substr($item->variant_id, -1));
            }

            $materials[] = 'thread_white'; // Default thread color
        }

        return array_unique($materials);
    }

    /**
     * Mask customer name for seller view
     */
    protected function maskCustomerName(?string $name): ?string
    {
        if (empty($name)) {
            return null;
        }

        $parts = explode(' ', trim($name));
        if (count($parts) === 1) {
            return substr($parts[0], 0, 1) . str_repeat('*', max(1, strlen($parts[0]) - 1));
        }

        // Show first name initial and last name initial
        $firstName = $parts[0];
        $lastName = end($parts);

        return substr($firstName, 0, 1) . str_repeat('*', max(1, strlen($firstName) - 1)) . ' ' .
            substr($lastName, 0, 1) . '.';
    }

    /**
     * Mask phone number for seller view
     */
    protected function maskPhoneNumber(?string $phone): ?string
    {
        if (empty($phone)) {
            return null;
        }

        // Show first 3 and last 4 digits, mask the middle
        if (strlen($phone) >= 7) {
            return substr($phone, 0, 3) . str_repeat('*', strlen($phone) - 7) . substr($phone, -4);
        }

        return substr($phone, 0, 1) . str_repeat('*', max(1, strlen($phone) - 2)) . substr($phone, -1);
    }

    /**
     * Get orders by tier for admin summary
     */
    protected function getOrdersByTier(): array
    {
        return [
            'diamond' => Order::whereHas('seller.profile.tier', function ($query) {
                $query->where('name', 'Diamond');
            })->count(),
            'platinum' => Order::whereHas('seller.profile.tier', function ($query) {
                $query->where('name', 'Platinum');
            })->count(),
            'gold' => Order::whereHas('seller.profile.tier', function ($query) {
                $query->where('name', 'Gold');
            })->count(),
            'silver' => Order::whereHas('seller.profile.tier', function ($query) {
                $query->where('name', 'Silver');
            })->count(),
        ];
    }

    /**
     * Get revenue by time period
     */
    protected function getRevenueByPeriod(string $period): float
    {
        $query = Order::query();

        switch ($period) {
            case OrderConstants::PERIOD_TODAY:
                $query->whereDate('created_at', today());
                break;
            case OrderConstants::PERIOD_WEEK:
                $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
                break;
            case OrderConstants::PERIOD_MONTH:
                $query->whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year);
                break;
        }

        return round($query->sum('total_cost'), 2);
    }

    /**
     * Get seller earnings by period
     */
    protected function getSellerEarningsByPeriod(int $sellerId, string $period): float
    {
        $query = Order::where('seller_id', $sellerId);

        switch ($period) {
            case OrderConstants::PERIOD_MONTH:
                $query->whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year);
                break;
            case OrderConstants::PERIOD_LAST_MONTH:
                $query->whereMonth('created_at', now()->subMonth()->month)
                    ->whereYear('created_at', now()->subMonth()->year);
                break;
        }

        return $query->sum('total_cost') * OrderConstants::SELLER_PROFIT_PERCENTAGE;
    }

    /**
     * Calculate growth rate for admin summary
     */
    protected function calculateGrowthRate(): float
    {
        $thisMonth = $this->getRevenueByPeriod(OrderConstants::PERIOD_MONTH);
        $lastMonth = Order::whereMonth('created_at', now()->subMonth()->month)
            ->whereYear('created_at', now()->subMonth()->year)
            ->sum('total_cost');

        if ($lastMonth <= 0) {
            return 0.0;
        }

        return round((($thisMonth - $lastMonth) / $lastMonth) * 100, 1);
    }

    /**
     * Get date range label for filters
     */
    protected function getDateRangeLabel(Request $request): string
    {
        if ($request->filled('date_from') && $request->filled('date_to')) {
            return OrderConstants::DATE_RANGE_CUSTOM;
        }

        if ($request->filled('date_from')) {
            $dateFrom = $request->input('date_from');
            if ($dateFrom === today()->format('Y-m-d')) {
                return OrderConstants::DATE_RANGE_TODAY;
            }
            if ($dateFrom === today()->subDays(7)->format('Y-m-d')) {
                return OrderConstants::DATE_RANGE_LAST_7_DAYS;
            }
            if ($dateFrom === today()->subDays(30)->format('Y-m-d')) {
                return OrderConstants::DATE_RANGE_LAST_30_DAYS;
            }
        }

        return OrderConstants::DATE_RANGE_ALL;
    }

    /**
     * Track order via QR code (public endpoint)
     * Supports: /track/{orderId}?stt=X&item_id=Y
     */
    public function trackOrder(int $orderId, Request $request): JsonResponse
    {
        try {
            $stt = $request->query('stt') ? (int) $request->query('stt') : null;
            $itemId = $request->query('item_id') ? (int) $request->query('item_id') : null;
            $itemStt = $request->query('item_stt') ? (int) $request->query('item_stt') : null;

            // Cache key matches URL format: track_v2_{order_id}?stt={stt}&item_id={item_id}&item_stt={item_stt}
            $cacheParams = [];
            if ($stt) $cacheParams[] = "stt={$stt}";
            if ($itemId) $cacheParams[] = "item_id={$itemId}";
            if ($itemStt) $cacheParams[] = "item_stt={$itemStt}";
            $cacheKey = "track_v2_{$orderId}" . (count($cacheParams) > 0 ? '?' . implode('&', $cacheParams) : '');

            $data = Cache::remember($cacheKey, 600, function () use ($orderId, $stt, $itemId) {
                $order = Order::with([
                    'seller:id,username,email',
                    'supports:id,order_id,subject',
                    'items:id,order_id,variant_id,product_name,quantity,status,mockup,mockup_back',
                    'items.metas'
                ])->findOrFail($orderId);

                // Find target item by item_id (preferred) or by stt
                $targetItem = null;
                if ($itemId) {
                    $targetItem = $order->items->firstWhere('id', $itemId);
                } elseif ($stt) {
                    $currentStt = 1;
                    foreach ($order->items as $item) {
                        for ($i = 0; $i < $item->quantity; $i++) {
                            if ($currentStt === $stt) {
                                $targetItem = $item;
                                break 2;
                            }
                            $currentStt++;
                        }
                    }
                }

	                $orderData = [
	                    'id' => $order->id,
	                    'ref_id' => $order->ref_id,
                        'order_type' => $order->order_type,
	                    'tracking_id' => $order->tracking_id,
	                    'fulfill_status' => $order->fulfill_status,
                    'fulfill_status_text' => $this->getStatusText($order->fulfill_status),
                    'shipping_label' => $order->shipping_label,
                    'convert_label' => $order->convert_label,
                    'note' => $order->note,
                    'created_at' => $order->created_at?->format('Y-m-d H:i:s'),
                    'customer' => [
                        'id' => $order->seller?->id,
                        'name' => $order->seller?->username,
                        'username' => $order->seller?->username
                    ],
                    'supports' => $order->supports->map(function ($support) {
                        return [
                            'id' => $support->id,
                            'subject' => $support->subject
                        ];
                    })->toArray()
                ];

	                $itemsData = $order->items->map(function ($item) {
	                    $variant = \App\Models\ProductVariant::with('product')->where('variant_id', $item->variant_id)->first();

	                    $designsByPosition = [];
                        $trackablePositions = $this->getTrackableTrackPositionsFromMetas($item->metas);

	                    // Load all workflow statuses for this item (all stages)
                        $allWorkflows = collect();
                        if (!empty($trackablePositions)) {
	                        $allWorkflows = \App\Models\OrderItemWorkflow::where('order_item_id', $item->id)
	                            ->whereIn('position', $trackablePositions)
	                            ->get();
                        }

                    // Group workflows by position and stage
                    $workflowsByPosition = [];
                    foreach ($allWorkflows as $wf) {
                        if (!isset($workflowsByPosition[$wf->position])) {
                            $workflowsByPosition[$wf->position] = [];
                        }
                        $workflowsByPosition[$wf->position][$wf->stage] = $wf->completed ? 1 : 0;
                    }

                    foreach ($item->metas as $meta) {
                        $metaKey = $meta->meta_key;

	                        // Embroidery base metas keep the original position names directly.
	                        if (in_array($metaKey, ['front', 'back', 'sleeve_left', 'sleeve_right', 'neck', 'wrap'])) {
	                            $position = $metaKey;
                                if (!isset($designsByPosition[$position])) {
                                    $designsByPosition[$position] = $this->makeTrackDesignPositionPayload(
                                        $position,
                                        $workflowsByPosition[$position] ?? []
                                    );
                                }

	                            $designsByPosition[$position]['embroidery_type'] = $meta->embroidery_type ?? 'standard';
	                            continue;
	                        }

                            // Tumbler/print image metas are stored as front_image / wrap_image.
                            if (in_array($metaKey, ['front_image', 'wrap_image'])) {
                                $position = str_replace('_image', '', $metaKey);

                                if (!isset($designsByPosition[$position])) {
                                    $designsByPosition[$position] = $this->makeTrackDesignPositionPayload(
                                        $position,
                                        $workflowsByPosition[$position] ?? []
                                    );
                                }

                                $designsByPosition[$position]['pdf_url'] = $meta->meta_value;
                                continue;
                            }

	                        // Check if this is a related file meta - only process json for stitch data
	                        // Pattern: (front|back|sleeve_left|sleeve_right|neck|wrap)_json
	                        if (preg_match('/^(front|back|sleeve_left|sleeve_right|neck|wrap)_json$/', $metaKey, $matches)) {
	                            $position = $matches[1];

	                            if (!isset($designsByPosition[$position])) {
                                    $designsByPosition[$position] = $this->makeTrackDesignPositionPayload(
                                        $position,
                                        $workflowsByPosition[$position] ?? []
                                    );
	                            }

                            $designsByPosition[$position]['json_url'] = $meta->meta_value;

                            // Fetch PES JSON data from URL
                            try {
                                $jsonResponse = \Illuminate\Support\Facades\Http::timeout(3)->get($meta->meta_value);
                                if ($jsonResponse->successful()) {
                                    $pesData = $jsonResponse->json();
                                    if (isset($pesData['file_info'])) {
                                        $designsByPosition[$position]['stitch_count'] = $pesData['file_info']['stitch_count'] ?? null;
                                        $designsByPosition[$position]['width_mm'] = $pesData['file_info']['width_mm'] ?? null;
                                        $designsByPosition[$position]['height_mm'] = $pesData['file_info']['height_mm'] ?? null;
                                        $designsByPosition[$position]['color_count'] = $pesData['file_info']['color_count'] ?? null;
                                    }
                                    if (isset($pesData['needle_assignment']['assignments'])) {
                                        $designsByPosition[$position]['needle_assignment'] = $pesData['needle_assignment']['assignments'];
                                    }
                                    if (isset($pesData['colors'])) {
                                        $designsByPosition[$position]['colors'] = $pesData['colors'];
                                    }
                                }
                            } catch (\Exception $e) {
                                \Illuminate\Support\Facades\Log::warning('Failed to fetch PES JSON', [
                                    'url' => $meta->meta_value,
                                    'error' => $e->getMessage()
                                ]);
                            }
                        }

	                        // Handle PDF files: (front|back|sleeve_left|sleeve_right|neck|wrap)_pdf
	                        if (preg_match('/^(front|back|sleeve_left|sleeve_right|neck|wrap)_pdf$/', $metaKey, $matches)) {
	                            $position = $matches[1];

	                            if (!isset($designsByPosition[$position])) {
                                    $designsByPosition[$position] = $this->makeTrackDesignPositionPayload(
                                        $position,
                                        $workflowsByPosition[$position] ?? []
                                    );
	                            }

	                            $designsByPosition[$position]['pdf_url'] = $meta->meta_value;
	                        }
	                    }

	                    // Generate color image URLs with multiple naming variants for fallback
                        $variantStyle = $variant?->style ?? $variant?->product?->style;
	                    $styleShort = $this->getStyleShortCode($variantStyle);
	                    $color = $variant?->color;
                    $colorImages = [];

                    if ($styleShort && $color) {
                        $baseUrl = "https://lemiex-fulfillment.s3.us-east-005.backblazeb2.com/mockup_product/{$styleShort}";

                        // Generate multiple variants for color naming
                        $colorVariants = [
                            str_replace(' ', '+', ucwords(strtolower($color))),     // Forest+Green
                            str_replace(' ', '+', strtolower($color)),               // forest+green
                            str_replace(' ', '+', ucfirst(strtolower($color))),      // Forest+green
                            str_replace(' ', '+', $color),                           // Original case
                        ];

                        // Remove duplicates and create URLs
                        $colorVariants = array_unique($colorVariants);
                        foreach ($colorVariants as $colorName) {
                            $colorImages[] = "{$baseUrl}/{$colorName}.jpg";
                        }
                    }

                    // Primary color image (first variant)
                    $colorImage = !empty($colorImages) ? $colorImages[0] : null;

                    return [
                        'id' => $item->id,
                        'quantity' => (int) $item->quantity,
                        'status' => (bool) $item->status,
                        'mockup' => $item->mockup,
                        'mockup_back' => $item->mockup_back,
                        'product' => [
	                            'variant_id' => $item->variant_id,
	                            'product_name' => $item->product_name,
	                            'size' => $variant?->size ?? 'Unknown',
	                            'color' => $variant?->color ?? 'Unknown',
	                            'style' => $variantStyle ?? 'Unknown',
	                            'stock' => $variant?->stock ?? 0,
	                            'sku' => $variant?->sku ?? 'Unknown',
	                            'color_image' => $colorImage,
                            'color_images' => $colorImages,  // Array of fallback URLs
                        ],
                        'designs' => array_values($designsByPosition)
                    ];
                })->toArray();

                $availableStatuses = $this->getAvailableStatuses();
                $totalQuantity = $order->items->sum('quantity');

                $result = [
                    'order' => $orderData,
                    'items' => $itemsData,
                    'available_statuses' => $availableStatuses,
                    'total_quantity' => $totalQuantity
                ];

                // Add current_item info when item is found
                if ($targetItem) {
                    $result['current_item'] = [
                        'order_stt' => $order->order_stt,
                        'item_id' => $targetItem->id,
                        'item_stt' => $stt,
                        'product_name' => $targetItem->product_name,
                        'estimated_delivery' => $order->created_at?->addDays(7)->format('Y-m-d'),
                        'qr_code' => $targetItem->metas()->where('meta_key', 'special_design_qr')->first()?->meta_value,
                        'mockup' => $targetItem->mockup,
                        'mockup_back' => $targetItem->mockup_back
                    ];
                }

                return $result;
            });

            if ($data === null) {
                return response()->json([
                    'code' => HttpCode::NOT_FOUND,
                    'status' => false,
                    'message' => ResponseMessage::ITEM_NOT_FOUND
                ], HttpCode::NOT_FOUND);
            }

            return response()->json([
                'success' => true,
                'data' => $data
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'code' => HttpCode::NOT_FOUND,
                'status' => false,
                'message' => ResponseMessage::ORDER_NOT_FOUND
            ], HttpCode::NOT_FOUND);
        } catch (\Exception $e) {
            Log::error('Failed to track order', [
                'order_id' => $orderId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => ResponseMessage::ORDER_TRACK_FAILED
            ], HttpCode::SERVER_ERROR);
        }
    }

    private function makeTrackDesignPositionPayload(string $position, array $positionWorkflows = []): array
    {
        return [
            'position' => $position,
            'pdf_url' => null,
            'json_url' => null,
            'status' => $positionWorkflows['staff'] ?? 0,
            'qc_status' => $positionWorkflows['qc'] ?? 0,
            'packing_status' => $positionWorkflows['packing'] ?? 0,
            'shipout_status' => $positionWorkflows['shipout'] ?? 0,
            'stitch_count' => null,
            'width_mm' => null,
            'height_mm' => null,
            'color_count' => null,
            'needle_assignment' => null,
            'colors' => null,
            'embroidery_type' => 'standard',
        ];
    }

    private function getTrackableTrackPositionsFromMetas($metas): array
    {
        return collect($metas)
            ->map(fn($meta) => $this->extractTrackableTrackPosition($meta->meta_key))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function extractTrackableTrackPosition(string $metaKey): ?string
    {
        if (in_array($metaKey, ['front', 'back', 'sleeve_left', 'sleeve_right', 'neck', 'wrap'])) {
            return $metaKey;
        }

        if (in_array($metaKey, ['front_image', 'wrap_image'])) {
            return str_replace('_image', '', $metaKey);
        }

        if (preg_match('/^(front|back|sleeve_left|sleeve_right|neck|wrap)_(pdf|json|dst|emb)$/', $metaKey, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Get status text from number or string
     */
    protected function getStatusText($status): string
    {
        // If already a string, return as is
        if (is_string($status)) {
            return $status;
        }

        // Convert number to string
        return match ((int)$status) {
            0 => 'new_order',
            1 => 'producing',
            2 => 'quality_check',
            3 => 'ready_to_ship',
            8 => 'shipped',
            9 => 'return_to_support',
            10 => 'cancelled',
            11 => 'delivered',
            default => 'unknown'
        };
    }

    /**
     * Get style short code from full style name
     * Maps: T-Shirt -> TS, Sweatshirt -> SW, Hoodie -> HD, Tank Top -> TC
     */
    protected function getStyleShortCode(?string $style): ?string
    {
        if (!$style) {
            return null;
        }

        $styleMap = [
            'T-Shirt' => 'TS',
            'Tshirt' => 'TS',
            'T Shirt' => 'TS',
            'Sweatshirt' => 'SW',
            'Sweat Shirt' => 'SW',
            'Hoodie' => 'HD',
            'Tank Top' => 'TC',
            'Tanktop' => 'TC',
            'Tank' => 'TC',
            'Comfort Color' => 'CC',
            'Comfort Colors' => 'CC',
            'CC' => 'CC',
        ];

        // Try exact match first
        if (isset($styleMap[$style])) {
            return $styleMap[$style];
        }

        // Try case-insensitive match
        $styleLower = strtolower($style);
        foreach ($styleMap as $key => $value) {
            if (strtolower($key) === $styleLower) {
                return $value;
            }
        }

        // Try partial match
        if (stripos($style, 'shirt') !== false && stripos($style, 'sweat') !== false) {
            return 'SW';
        }
        if (stripos($style, 'hoodie') !== false || stripos($style, 'hood') !== false) {
            return 'HD';
        }
        if (stripos($style, 'tank') !== false) {
            return 'TC';
        }
        if (stripos($style, 'shirt') !== false || stripos($style, 'tee') !== false) {
            return 'TS';
        }

        return null;
    }

    /**
     * Get available statuses
     */
    protected function getAvailableStatuses(): array
    {
        return [
            "0" => "new_order",
            "1" => "producing",
            "8" => "shipped",
            "9" => "return_to_support",
            "10" => "cancelled",
            "11" => "delivered"
        ];
    }

    /**
     * Generate DST filename from meta key
     */
    protected function generateDstFileName(string $metaKey): string
    {
        $position = str_replace(['_pdf', '_emb'], '', $metaKey);
        return $position . '_design.dst';
    }

    /**
     * Get order by ID with full details
     */
    public function getOrderById($id): JsonResponse
    {
        try {
            // Get current user
            $user = Auth::user();
            $userRole = $user->role->name ?? null;

            // Build query with full relationships
            $order = Order::with([
                'seller:id,username,email,role_id',
                'seller.role:id,name',
                'seller.profile:user_id,private_seller,first_name,last_name,wallet_balance',
                'seller.profile.tier:id,tier_id,name',
                'store:id,name,api_key',
                'items:id,order_id,variant_id,product_name,quantity,price,mockup,mockup_back,status,sides',
                'items.metas:id,order_item_id,meta_key,meta_value,switch,embroidery_type',
                'items.productions:id,order_item_id,status,quantity',
                'items.productVariant:id,variant_id,product_id,style,color,size,sku',
                'items.productVariant.product:id,mockup,name',
                'tracking:id,order_id,tracking_id,status,service,method,total_day,update_time',
            ])
                ->withCount(['items', 'items as total_quantity' => function ($query) {
                    $query->select(DB::raw('SUM(quantity)'));
                }])
                ->findOrFail($id);

            // Check permission: Seller can only view their own orders
            $roleMap = UserRole::all();
            if ($userRole === $roleMap[UserRole::SELLER] && $order->seller_id !== $user->id) {
                return response()->json([
                    'code' => HttpCode::FORBIDDEN,
                    'status' => false,
                    'message' => ResponseMessage::ORDER_PERMISSION_DENIED
                ], HttpCode::FORBIDDEN);
            }

            // Transform order data based on role (reuse transform logic from list)
            $productionStatuses = $order->items->flatMap(function ($item) {
                return $item->productions->pluck('status');
            })->unique()->values()->toArray();

            $baseData = [
                'id' => $order->id,
                'ref_id' => $order->ref_id,
                'seller_ref' => $order->seller_ref,
                'order_stt' => $order->order_stt,
                'order_type' => $order->order_type,
                'status' => $order->fulfill_status,
                'fulfill_status' => $order->fulfill_status,
                'payment_status' => $order->payment_status,
            ];

            // Transform based on role
            $roleMap = UserRole::all();
            $transformedOrder = match ($userRole) {
                $roleMap[UserRole::ADMIN] => $this->transformForAdmin($order, $baseData, $productionStatuses),
                $roleMap[UserRole::STAFF] => $this->transformForStaff($order, $baseData, $productionStatuses),
                $roleMap[UserRole::SELLER] => $this->transformForSeller($order, $baseData, $productionStatuses),
                default => $this->transformForSeller($order, $baseData, $productionStatuses)
            };

            return response()->json([
                'code' => HttpCode::SUCCESS,
                'status' => true,
                'message' => ResponseMessage::SUCCESS,
                'data' => $transformedOrder
            ], HttpCode::SUCCESS);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('Order not found', [
                'order_id' => $id,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'code' => HttpCode::NOT_FOUND,
                'status' => false,
                'message' => ResponseMessage::ORDER_NOT_FOUND
            ], HttpCode::NOT_FOUND);
        } catch (\Exception $e) {
            Log::error('Failed to get order by ID', [
                'order_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => ResponseMessage::ORDER_RETRIEVED_FAILED,
                'error' => config('app.debug') ? $e->getMessage() : null
            ], HttpCode::SERVER_ERROR);
        }
    }

    /**
     * Get order timeline
     */
    public function getOrderTimeline($id): JsonResponse
    {
        try {
            // Get current user
            $user = Auth::user();
            $userRole = $user->role->name ?? null;

            // Find order
            $order = Order::findOrFail($id);

            // Check permission: Seller can only view their own orders
            $roleMap = UserRole::all();
            if ($userRole === $roleMap[UserRole::SELLER] && $order->seller_id !== $user->id) {
                return response()->json([
                    'code' => HttpCode::FORBIDDEN,
                    'status' => false,
                    'message' => ResponseMessage::ORDER_TIMELINE_PERMISSION_DENIED
                ], HttpCode::FORBIDDEN);
            }

            // Get timeline events
            $timeline = Timeline::where('object', 'order')
                ->where('object_id', $id)
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($event) {
                    return [
                        'id' => $event->id,
                        'action' => $event->action,
                        'note' => $event->note,
                        'created_at' => $event->created_at?->toIso8601String(),
                        'created_at_formatted' => $event->created_at?->format('M d, Y H:i:s'),
                        'updated_at' => $event->updated_at?->toIso8601String(),
                        'updated_at_formatted' => $event->updated_at?->format('M d, Y H:i:s'),
                    ];
                });

            return response()->json([
                'code' => HttpCode::SUCCESS,
                'status' => true,
                'message' => ResponseMessage::SUCCESS,
                'data' => [
                    'order_id' => $id,
                    'order_stt' => $order->order_stt,
                    'timeline' => $timeline
                ]
            ], HttpCode::SUCCESS);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'code' => HttpCode::NOT_FOUND,
                'status' => false,
                'message' => ResponseMessage::ORDER_NOT_FOUND
            ], HttpCode::NOT_FOUND);
        } catch (\Exception $e) {
            Log::error('Failed to get order timeline', [
                'order_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => ResponseMessage::ORDER_TIMELINE_RETRIEVED_FAILED,
                'error' => config('app.debug') ? $e->getMessage() : null
            ], HttpCode::SERVER_ERROR);
        }
    }

    /**
     * Update label for order (from external service)
     * Carrier types: 0 = USPS, 1 = FedEx, 2 = UPS
     */
    public function updateLabel(Request $request): JsonResponse
    {
        Log::info('[updateLabel] Incoming request', [
            'id' => $request->id ?? null,
            'status' => $request->status ?? null,
            'fedex' => $request->fedex ?? null,
        ]);

        $orderId = $request->id;

        try {
            Log::info('[updateLabel] Start validation', [
                'order_id' => $request->id,
            ]);

            $validated = $request->validate([
                'id' => 'required|integer|exists:orders,id',
                'link' => 'required|url',
                'status' => 'required|in:success,failed,error',
                'tracking_id' => 'required|string|max:255',
                'fedex' => 'required|integer|in:0,1,2',
            ]);

            // $validated['tracking_id'] = (int)$validated['tracking_id'];

            Log::info('[updateLabel] Validation passed', $validated);

            Log::info('[updateLabel] Finding order', [
                'order_id' => $validated['id'],
            ]);

            $order = Order::findOrFail($validated['id']);

            Log::info('[updateLabel] Order found', [
                'order_id' => $order->id,
                'current_note' => $order->note,
            ]);

            if ($validated['status'] === 'error') {
                Log::warning('[updateLabel] Status error - no update', [
                    'order_id' => $order->id,
                ]);

                return response()->json([
                    'code' => HttpCode::SUCCESS,
                    'status' => true,
                    'message' => ResponseMessage::LABEL_NO_CHANGES,
                ], HttpCode::SUCCESS);
            }

            if ($validated['fedex'] > 0 && empty($order->note)) {
                Log::info('[updateLabel] Non-USPS carrier', [
                    'order_id' => $order->id,
                    'carrier' => $this->getCarrierName($validated['fedex']),
                ]);

                $this->handleNonUspsCarrier($order, $validated);

                Log::info('[updateLabel] Non-USPS handled', [
                    'order_id' => $order->id,
                ]);

                return response()->json([
                    'code' => HttpCode::SUCCESS,
                    'status' => true,
                    'message' => ResponseMessage::LABEL_UPDATED,
                    'data' => $order->fresh(),
                ], HttpCode::SUCCESS);
            }

            Log::info('[updateLabel] Standard update', [
                'order_id' => $order->id,
                'convert_label' => $validated['link'],
                'tracking_id' => $validated['tracking_id'],
            ]);

            $order->update([
                'convert_label' => $validated['link'],
                'tracking_id' => $validated['tracking_id'],
            ]);

            // Create timeline for update label
            Timeline::create([
                'object' => 'order',
                'object_id' => $order->id,
                'owner_id' => null,
                'action' => 'update label',
                'note' => "Label updated for order #{$order->order_stt} - Tracking: {$validated['tracking_id']}",
            ]);

            Log::info('[updateLabel] Update success', [
                'order_id' => $order->id,
                'carrier' => $this->getCarrierName($validated['fedex']),
            ]);

            return response()->json([
                'code' => HttpCode::SUCCESS,
                'status' => true,
                'message' => ResponseMessage::LABEL_UPDATED,
                'data' => $order->fresh(),
            ], HttpCode::SUCCESS);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('[updateLabel] Validation failed', [
                'order_id' => $orderId,
                'errors' => $e->errors(),
            ]);

            return response()->json([
                'code' => HttpCode::VALIDATION_ERROR,
                'status' => false,
                'message' => ResponseMessage::VALIDATION_FAILED,
                'errors' => $e->errors(),
            ], HttpCode::VALIDATION_ERROR);
        } catch (\Exception $e) {
            Log::error('[updateLabel] Exception', [
                'order_id' => $orderId,
                'message' => $e->getMessage(),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null,
            ]);

            return response()->json([
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => ResponseMessage::LABEL_UPDATE_FAILED,
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], HttpCode::SERVER_ERROR);
        }
    }

    /**
     * Handle non-USPS carriers (FedEx/UPS) - put order on hold and create ticket
     */
    private function handleNonUspsCarrier(Order $order, array $data): void
    {
        $carrierName = $this->getCarrierName($data['fedex']);
        $noteMessage = "Label is {$carrierName}";

        $order->update([
            'convert_label' => $data['link'],
            'fulfill_status' => OrderFulfillStatus::ON_HOLD,
            'note' => $noteMessage,
            'tracking_id' => $data['tracking_id'],
        ]);

        // Create timeline for update label (non-USPS)
        Timeline::create([
            'object' => 'order',
            'object_id' => $order->id,
            'owner_id' => null,
            'action' => 'update label',
            'note' => "Label updated ({$carrierName}) for order #{$order->order_stt} - Tracking: {$data['tracking_id']} - On Hold",
        ]);

        // Create support ticket for non-USPS carrier
        $ticketData = [
            'order_id' => $order->id,
            'subject' => $noteMessage,
            'status' => 0,
        ];

        $systemUser = User::find(1);
        $supportService = new SupportService();
        $supportService->createTicket($ticketData, $systemUser, null);

        Log::info('Non-USPS carrier detected, ticket created', [
            'order_id' => $order->id,
            'carrier' => $carrierName,
        ]);
    }

    /**
     * Get carrier name from fedex code
     */
    private function getCarrierName(int $code): string
    {
        return match ($code) {
            1 => 'FedEx',
            2 => 'UPS',
            default => 'USPS',
        };
    }

    /**
     * Post label to conversion service
     */
    public function postLabel(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'id' => 'required|integer|exists:orders,id',
            ]);

            $order = Order::findOrFail($request->input('id'));
            $result = ProcessOrderLabelShip::postLabelConvert($order);

            if ($result->status() !== HttpCode::SUCCESS) {
                Log::error('Label post failed', [
                    'order_id' => $order->id,
                    'response_status' => $result->status(),
                ]);

                return response()->json([
                    'code' => HttpCode::SERVER_ERROR,
                    'status' => false,
                    'message' => ResponseMessage::LABEL_POST_FAILED,
                ], HttpCode::SERVER_ERROR);
            }

            return response()->json([
                'code' => HttpCode::SUCCESS,
                'status' => true,
                'message' => ResponseMessage::LABEL_POST_SUCCESS,
            ], HttpCode::SUCCESS);
        } catch (\Exception $e) {
            Log::error('Failed to post label', [
                'order_id' => $request->input('id'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => ResponseMessage::LABEL_POST_FAILED,
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], HttpCode::SERVER_ERROR);
        }
    }

    /**
     * Update order items - compare and update only changed items
     */
    protected function updateOrderItems(Order $order, array $lineItems, $user): void
    {
        try {
            $existingItems = $order->items->keyBy('id');
            $updatedItemIds = [];

            foreach ($lineItems as $itemData) {
                // If item has ID, update existing item
                if (isset($itemData['id']) && $existingItems->has($itemData['id'])) {
                    $item = $existingItems->get($itemData['id']);
                    $itemChanges = [];

                    // Check quantity change
                    if (isset($itemData['quantity']) && $itemData['quantity'] != $item->quantity) {
                        $itemChanges['quantity'] = [
                            'old' => $item->quantity,
                            'new' => $itemData['quantity']
                        ];
                        $item->quantity = $itemData['quantity'];
                    }

                    // Check product change
                    if (isset($itemData['variant_id']) && $itemData['variant_id'] != $item->variant_id) {
                        $itemChanges['variant_id'] = [
                            'old' => $item->variant_id,
                            'new' => $itemData['variant_id']
                        ];
                        $item->variant_id = $itemData['variant_id'];
                    }

                    // Save if changed
                    if (!empty($itemChanges)) {
                        $item->save();

                        Log::info('Order item updated', [
                            'order_id' => $order->id,
                            'item_id' => $item->id,
                            'changes' => $itemChanges
                        ]);
                    }

                    $updatedItemIds[] = $item->id;
                } else {
                    // New item - create it
                    // Note: This is simplified, you may want to add full item creation logic
                    Log::info('New item detected in update', [
                        'order_id' => $order->id,
                        'item_data' => $itemData
                    ]);
                }
            }

            // Optional: Delete items that are not in the update request
            // $itemsToDelete = $existingItems->keys()->diff($updatedItemIds);
            // OrderItem::whereIn('id', $itemsToDelete)->delete();

        } catch (\Exception $e) {
            Log::error('Failed to update order items', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Proxy download for images/files to avoid CORS issues
     * Supports QR codes, images from external domains
     */
    public function proxyDownload(Request $request)
    {
        try {
            $url = $request->query('url');
            $filename = $request->query('filename', 'download.png');

            if (!$url) {
                return response()->json([
                    'success' => false,
                    'message' => 'URL is required'
                ], 400);
            }

            // Validate URL is from allowed domains
            $allowedDomains = [
                'job3.pressify.us',
                'backblazeb2.com', // Matches all subdomains like s3.us-east-005.backblazeb2.com
                'lemiex.us',
                'manage.lemiex.us',
                'pressify.us',
            ];

            $parsedUrl = parse_url($url);
            $host = $parsedUrl['host'] ?? '';

            $isAllowed = false;
            foreach ($allowedDomains as $domain) {
                if (str_contains($host, $domain)) {
                    $isAllowed = true;
                    break;
                }
            }

            if (!$isAllowed) {
                return response()->json([
                    'success' => false,
                    'message' => 'Domain not allowed: ' . $host
                ], 403);
            }

            // Fetch the file
            $client = new \GuzzleHttp\Client();
            $response = $client->get($url, [
                'timeout' => 30,
                'headers' => [
                    'Accept' => 'image/*,*/*',
                    'User-Agent' => 'Laravel-OrderSystem/1.0',
                ]
            ]);

            $contentType = $response->getHeaderLine('Content-Type') ?: 'application/octet-stream';
            $body = $response->getBody()->getContents();

            return response($body, 200)
                ->header('Content-Type', $contentType)
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Cache-Control', 'public, max-age=86400');
        } catch (\Exception $e) {
            Log::error('Failed to proxy download', [
                'url' => $url ?? 'N/A',
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to download file'
            ], 500);
        }
    }

    /**
     * Proxy shipping label PDF to avoid CORS issues
     */
    public function proxyShippingLabel(Request $request)
    {
        try {
            $url = $request->query('url');

            if (!$url) {
                return response()->json([
                    'success' => false,
                    'message' => 'URL is required'
                ], 400);
            }

            // Validate URL is from allowed domains
            $allowedDomains = [
                'api.shipengine.com',
                'shipengine.com',
            ];

            $parsedUrl = parse_url($url);
            $host = $parsedUrl['host'] ?? '';

            $isAllowed = false;
            foreach ($allowedDomains as $domain) {
                if (str_contains($host, $domain)) {
                    $isAllowed = true;
                    break;
                }
            }

            if (!$isAllowed) {
                return response()->json([
                    'success' => false,
                    'message' => 'Domain not allowed'
                ], 403);
            }

            // Fetch the PDF
            $client = new \GuzzleHttp\Client();
            $response = $client->get($url, [
                'timeout' => 30,
                'headers' => [
                    'Accept' => 'application/pdf',
                ]
            ]);

            $contentType = $response->getHeaderLine('Content-Type') ?: 'application/pdf';
            $body = $response->getBody()->getContents();

            return response($body, 200)
                ->header('Content-Type', $contentType)
                ->header('Content-Disposition', 'inline; filename="shipping-label.pdf"')
                ->header('Access-Control-Allow-Origin', '*');
        } catch (\Exception $e) {
            Log::error('Failed to proxy shipping label', [
                'url' => $url ?? 'N/A',
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch shipping label'
            ], 500);
        }
    }

    public function updateOrder(UpdateOrderLabelShipRequest $request): JsonResponse
    {
        try {
            $order = $request->getExistingOrder();
            $changedFields = $request->getChangedFields();

            if (!$request->hasChanges()) {
                return response()->json([
                    'code' => HttpCode::SUCCESS,
                    'status' => true,
                    'message' => 'No changes detected',
                    'data' => ['order_id' => $order->id]
                ]);
            }

            $result = $this->orderService->updateOrder($order, $changedFields);

            if (!$result['success']) {
                return response()->json([
                    'code' => HttpCode::SERVER_ERROR,
                    'status' => false,
                    'message' => $result['message'],
                    'error' => $result['error'] ?? null
                ], HttpCode::SERVER_ERROR);
            }

            // Clear tracking cache
            $this->clearTrackOrderCache($order->id);

            return response()->json([
                'code' => HttpCode::SUCCESS,
                'status' => true,
                'message' => $result['message'],
                'data' => $result['data']
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update order', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => 'Failed to update order',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], HttpCode::SERVER_ERROR);
        }
    }

    /**
     * Remake PES files for specific order item metas that failed during creation
     */
    public function remakeFile(Request $request): JsonResponse
    {
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'order_item_meta_ids' => 'required|array|min:1',
            'order_item_meta_ids.*' => 'required|integer|exists:order_item_metas,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => HttpCode::BAD_REQUEST,
                'status' => false,
                'message' => ResponseMessage::VALIDATION_FAILED,
                'errors' => $validator->errors()
            ], HttpCode::BAD_REQUEST);
        }

        try {
            $metaIds = $request->order_item_meta_ids;
            $designPositions = ['front', 'back', 'sleeve_left', 'sleeve_right', 'neck'];

            $selectedMetas = OrderItemMeta::with('orderItem')
                ->whereIn('id', $metaIds)
                ->get();

            // Wood orders: selected design files are PDFs ({position}_pdf). For these,
            // "Remake Des" means regenerating the merged design image from the PDF
            // (WoodMergeImageService), not PES conversion.
            $pdfMetas = $selectedMetas->filter(
                fn($meta) => str_ends_with($meta->meta_key, '_pdf')
            );

            $woodRemake = ['order_ids' => [], 'item_ids' => []];

            if ($pdfMetas->isNotEmpty()) {
                $woodMergeService = app(\App\Services\WoodMergeImageService::class);

                $itemsByOrder = $pdfMetas
                    ->map(fn($meta) => $meta->orderItem)
                    ->filter()
                    ->unique('id')
                    ->groupBy('order_id');

                foreach ($itemsByOrder as $orderId => $orderItems) {
                    $order = Order::find($orderId);
                    if (!$order) {
                        continue;
                    }

                    $itemIds = $orderItems->pluck('id')->all();
                    $woodMergeService->regenerateForItems($order, $itemIds);
                    $this->clearTrackOrderCache($order->id);

                    $woodRemake['order_ids'][] = (int) $orderId;
                    $woodRemake['item_ids'] = array_merge($woodRemake['item_ids'], $itemIds);
                }

                Log::info('Remake Des: regenerated wood merge images', $woodRemake);
            }

            // PES design metas (embroidery)
            $metas = $selectedMetas
                ->whereIn('meta_key', $designPositions)
                ->values();

            if ($metas->isEmpty()) {
                // Pure wood/PDF selection — merge images already regenerated above.
                if (!empty($woodRemake['order_ids'])) {
                    return response()->json([
                        'code' => HttpCode::SUCCESS,
                        'status' => true,
                        'message' => ResponseMessage::REMAKE_SUCCESS,
                        'data' => $woodRemake
                    ], HttpCode::SUCCESS);
                }

                return response()->json([
                    'code' => HttpCode::BAD_REQUEST,
                    'status' => false,
                    'message' => ResponseMessage::REMAKE_NO_DESIGN_FILES
                ], HttpCode::BAD_REQUEST);
            }

            // Check if any meta has PES file (either .pes extension OR Google Drive URL)
            $hasValidFiles = $metas->some(
                fn($meta) =>
                preg_match('/\.pes$/i', $meta->meta_value) ||
                    str_contains($meta->meta_value, 'drive.google.com')
            );

            if (!$hasValidFiles) {
                return response()->json([
                    'code' => HttpCode::BAD_REQUEST,
                    'status' => false,
                    'message' => ResponseMessage::REMAKE_NO_PES_FILES
                ], HttpCode::BAD_REQUEST);
            }

            // Convert URLs to new format before processing
            $pushService = app(\App\Services\PushFileJsonToBackblazeService::class);
            $bucketName = env('B2_BUCKET', 'Lemiex-Fulfillment');

            foreach ($metas as $meta) {
                $orderItem = $meta->orderItem;

                // Get variant details for filename
                $variant = \App\Models\ProductVariant::where('variant_id', $orderItem->variant_id)->first();
                $sStyle = preg_replace('/[^a-zA-Z0-9]/', '', $variant->style ?? 'Unknown');
                $sSize = preg_replace('/[^a-zA-Z0-9]/', '', $variant->size ?? 'Unknown');
                $sColor = preg_replace('/[^a-zA-Z0-9]/', '', $variant->color ?? 'Unknown');

                $newFileName = "{$orderItem->order_id}_{$orderItem->id}_{$meta->meta_key}_{$sStyle}_{$sSize}_{$sColor}.pes";
                $expectedNewUrl = "https://s3.us-east-005.backblazeb2.com/{$bucketName}/pes_files/{$newFileName}";

                // Skip if already has new format
                if (str_contains($meta->meta_value, $newFileName)) {
                    continue;
                }

                try {
                    if (str_contains($meta->meta_value, 'drive.google.com')) {
                        // Google Drive: Upload to B2 with new name
                        $result = $pushService->pushPesToBlaze(
                            $meta->meta_value,
                            $newFileName,
                            $bucketName
                        );
                        $newUrl = $result['fileName'];

                        Log::info('Remake: Converted Google Drive to B2 with new format', [
                            'meta_id' => $meta->id,
                            'old_url' => $meta->meta_value,
                            'new_url' => $newUrl
                        ]);
                    } elseif (str_contains($meta->meta_value, 'backblazeb2.com')) {
                        // B2 with old format: Copy to new name, delete old
                        $result = $pushService->pushPesToBlaze(
                            $meta->meta_value,
                            $newFileName,
                            $bucketName
                        );
                        $newUrl = $result['fileName'];

                        // Delete old file
                        if (str_contains($meta->meta_value, $bucketName . '/')) {
                            $parts = explode($bucketName . '/', $meta->meta_value, 2);
                            if (isset($parts[1])) {
                                $oldPath = urldecode($parts[1]);
                                \Illuminate\Support\Facades\Storage::disk('b2')->delete($oldPath);
                                Log::info('Remake: Deleted old B2 file', ['path' => $oldPath]);
                            }
                        }

                        Log::info('Remake: Renamed B2 file to new format', [
                            'meta_id' => $meta->id,
                            'old_url' => $meta->meta_value,
                            'new_url' => $newUrl
                        ]);
                    } else {
                        // Other URL (HTTP): Upload to B2 with new name
                        $result = $pushService->pushPesToBlaze(
                            $meta->meta_value,
                            $newFileName,
                            $bucketName
                        );
                        $newUrl = $result['fileName'];

                        Log::info('Remake: Uploaded external URL to B2 with new format', [
                            'meta_id' => $meta->id,
                            'old_url' => $meta->meta_value,
                            'new_url' => $newUrl
                        ]);
                    }

                    // Update meta with new URL
                    $meta->update(['meta_value' => $newUrl]);
                } catch (\Exception $e) {
                    Log::error('Remake: Failed to process file', [
                        'meta_id' => $meta->id,
                        'url' => $meta->meta_value,
                        'error' => $e->getMessage()
                    ]);
                    return response()->json([
                        'code' => HttpCode::BAD_REQUEST,
                        'status' => false,
                        'message' => 'Failed to process file: ' . $e->getMessage()
                    ], HttpCode::BAD_REQUEST);
                }
            }

            // Refresh metas to get updated URLs
            $metas = OrderItemMeta::with('orderItem')
                ->whereIn('id', $metaIds)
                ->whereIn('meta_key', $designPositions)
                ->get();

            $firstOrderItem = $metas->first()->orderItem;
            $order = Order::with(['seller.profile.tier'])->find($firstOrderItem->order_id);

            if (!$order) {
                return response()->json([
                    'code' => HttpCode::BAD_REQUEST,
                    'status' => false,
                    'message' => ResponseMessage::ORDER_NOT_FOUND
                ], HttpCode::BAD_REQUEST);
            }

            $tier = $order->seller->profile->tier->tier_id ?? 1;
            $feeService = app(\App\Services\FeeCalculationService::class);
            $orderProcessingService = app(\App\Services\OrderProcessingService::class);

            $convertResult = $orderProcessingService->processConvertForMetas($metas, $order, $feeService, $tier);
            $order->refresh();

            Log::info('Remake PES file completed', [
                'order_id' => $order->id,
                'meta_ids' => $metaIds,
                'success' => $convertResult['success']
            ]);

            if ($convertResult['success']) {
                // Clear tracking cache
                $this->clearTrackOrderCache($order->id);

                return response()->json([
                    'code' => HttpCode::SUCCESS,
                    'status' => true,
                    'message' => ResponseMessage::REMAKE_SUCCESS,
                    'data' => [
                        'order_id' => $order->id,
                        'processed_metas' => $convertResult['processed_metas'],
                        'extra_fee' => $order->extra_fee,
                        'refund_fee' => $order->refund_fee,
                        'total_cost' => $order->total_cost
                    ]
                ], HttpCode::SUCCESS);
            }

            return response()->json([
                'code' => HttpCode::BAD_REQUEST,
                'status' => false,
                'message' => $convertResult['message'] ?? ResponseMessage::REMAKE_FAILED
            ], HttpCode::BAD_REQUEST);
        } catch (\Exception $e) {
            Log::error('Failed to remake file', [
                'meta_ids' => $request->order_item_meta_ids,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => ResponseMessage::REMAKE_FAILED,
                'error' => config('app.debug') ? $e->getMessage() : null
            ], HttpCode::SERVER_ERROR);
        }
    }
    // Remake QR codes for specific order items
    public function remakeQr(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'order_item_ids' => 'required|array',
                'order_item_ids.*' => 'integer|exists:order_items,id'
            ]);

            $itemIds = $request->order_item_ids;
            $firstItem = OrderItem::find($itemIds[0]);

            if (!$firstItem) {
                return response()->json([
                    'code' => HttpCode::BAD_REQUEST,
                    'status' => false,
                    'message' => ResponseMessage::ITEM_NOT_FOUND
                ], HttpCode::BAD_REQUEST);
            }

            $order = Order::find($firstItem->order_id);

            if (!$order) {
                return response()->json([
                    'code' => HttpCode::BAD_REQUEST,
                    'status' => false,
                    'message' => ResponseMessage::ORDER_NOT_FOUND
                ], HttpCode::BAD_REQUEST);
            }

            // check all items belong to same order
            $validCount = OrderItem::whereIn('id', $itemIds)
                ->where('order_id', $order->id)
                ->count();

            if ($validCount != count(array_unique($itemIds))) {
                return response()->json([
                    'code' => HttpCode::BAD_REQUEST,
                    'status' => false,
                    'message' => 'All items must belong to the same order'
                ], HttpCode::BAD_REQUEST);
            }

            $service = app(\App\Services\OrderProcessingService::class);
            $results = $service->createQRCodesForItems($order, $itemIds);

            return response()->json([
                'code' => HttpCode::SUCCESS,
                'status' => true,
                'message' => ResponseMessage::REMAKE_QR_SUCCESS,
                'data' => $results
            ], HttpCode::SUCCESS);
        } catch (\Exception $e) {
            Log::error('Failed to remake QR', [
                'item_ids' => $request->order_item_ids ?? [],
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => ResponseMessage::REMAKE_QR_FAILED,
                'error' => config('app.debug') ? $e->getMessage() : null
            ], HttpCode::SERVER_ERROR);
        }
    }

    /**
     * Upload video for order item
     * Stores video in B2 'video_order' folder
     * Saves video URLs as JSON array in order_item_metas (meta_key = 'videos')
     */
    public function uploadVideo(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'order_id' => 'required|integer|exists:orders,id',
                'order_item_id' => 'required|integer|exists:order_items,id',
                'video' => 'required|file|mimes:mp4,mov,avi,webm|max:102400', // Max 100MB
            ]);

            $orderId = $validated['order_id'];
            $orderItemId = $validated['order_item_id'];
            $video = $request->file('video');

            // Generate unique filename: {order_id}_{order_item_id}_{timestamp}.{ext}
            $timestamp = now()->format('YmdHis');
            $extension = $video->getClientOriginalExtension();
            $filename = "video_order/{$orderId}_{$orderItemId}_{$timestamp}.{$extension}";

            // Upload to B2
            $videoContent = file_get_contents($video->getRealPath());
            \Illuminate\Support\Facades\Storage::disk('b2')->put($filename, $videoContent, 'public');
            $videoUrl = \Illuminate\Support\Facades\Storage::disk('b2')->url($filename);

            Log::info('Video uploaded to B2', [
                'order_id' => $orderId,
                'order_item_id' => $orderItemId,
                'filename' => $filename,
                'url' => $videoUrl
            ]);

            // Get order item and update pdf column with videos array
            $orderItem = OrderItem::findOrFail($orderItemId);

            // Parse existing videos from pdf column or create new array
            $videos = [];
            if ($orderItem->pdf) {
                $decoded = json_decode($orderItem->pdf, true);
                if (is_array($decoded)) {
                    $videos = $decoded;
                }
            }

            // Push new video to array
            $videos[] = [
                'url' => $videoUrl,
                'filename' => basename($filename),
                'uploaded_at' => now()->toIso8601String(),
            ];

            // Save to pdf column as JSON (unescaped slashes for clean URLs)
            $orderItem->pdf = json_encode($videos, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $orderItem->save();

            // Clear cache for track order
            Cache::forget("track_order_{$orderId}");
            Cache::forget("track_order_{$orderId}_{$orderItemId}");

            return response()->json([
                'code' => HttpCode::SUCCESS,
                'status' => true,
                'message' => 'Video uploaded successfully',
                'data' => [
                    'order_id' => $orderId,
                    'order_item_id' => $orderItemId,
                    'video_url' => $videoUrl,
                    'filename' => basename($filename),
                ]
            ], HttpCode::SUCCESS);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'code' => HttpCode::BAD_REQUEST,
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], HttpCode::BAD_REQUEST);
        } catch (\Exception $e) {
            Log::error('Failed to upload video', [
                'order_id' => $request->order_id ?? null,
                'order_item_id' => $request->order_item_id ?? null,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => 'Failed to upload video',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], HttpCode::SERVER_ERROR);
        }
    }

    /**
     * Get videos for order item
     * Retrieves video URLs from pdf column in order_items table
     */
    public function getVideos(int $orderItemId): JsonResponse
    {
        try {
            $orderItem = OrderItem::with('order:id,order_stt')->find($orderItemId);

            if (!$orderItem) {
                return response()->json([
                    'code' => HttpCode::NOT_FOUND,
                    'status' => false,
                    'message' => 'Order item not found'
                ], HttpCode::NOT_FOUND);
            }

            // Parse videos from pdf column
            $videos = [];
            if ($orderItem->pdf) {
                $decoded = json_decode($orderItem->pdf, true);
                if (is_array($decoded)) {
                    $videos = $decoded;
                }
            }

            return response()->json([
                'code' => HttpCode::SUCCESS,
                'status' => true,
                'message' => 'Videos retrieved successfully',
                'data' => [
                    'order_item_id' => $orderItemId,
                    'order_id' => $orderItem->order_id,
                    'order_stt' => $orderItem->order?->order_stt,
                    'product_name' => $orderItem->product_name,
                    'videos' => $videos,
                    'video_count' => count($videos),
                ]
            ], HttpCode::SUCCESS);
        } catch (\Exception $e) {
            Log::error('Failed to get videos', [
                'order_item_id' => $orderItemId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => 'Failed to get videos',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], HttpCode::SERVER_ERROR);
        }
    }

    /**
     * Get all videos with pagination
     * Retrieves all order items that have videos (pdf column is not null/empty)
     */
    public function getAllVideos(Request $request): JsonResponse
    {
        try {
            $perPage = min($request->input('per_page', 20), 100);
            $orderId = $request->input('order_id');
            $orderItemId = $request->input('order_item_id');

            $query = OrderItem::with(['order:id,order_stt,ref_id,created_at'])
                ->whereNotNull('pdf')
                ->where('pdf', '!=', '')
                ->where('pdf', '!=', '[]');

            // Filter by order_id if provided
            if ($orderId) {
                $query->where('order_id', $orderId);
            }

            // Filter by order_item_id if provided
            if ($orderItemId) {
                $query->where('id', $orderItemId);
            }

            $items = $query->orderBy('created_at', 'desc')
                ->paginate($perPage);

            $data = $items->map(function ($item) {
                $videos = [];
                if ($item->pdf) {
                    $decoded = json_decode($item->pdf, true);
                    if (is_array($decoded)) {
                        $videos = $decoded;
                    }
                }

                return [
                    'order_item_id' => $item->id,
                    'order_id' => $item->order_id,
                    'order_stt' => $item->order?->order_stt,
                    'ref_id' => $item->order?->ref_id,
                    'product_name' => $item->product_name,
                    'color' => $item->color ?? null,
                    'size' => $item->size ?? null,
                    'videos' => $videos,
                    'video_count' => count($videos),
                    'created_at' => $item->created_at?->format('Y-m-d H:i:s'),
                ];
            });

            return response()->json([
                'code' => HttpCode::SUCCESS,
                'status' => true,
                'message' => 'Videos retrieved successfully',
                'data' => $data,
                'pagination' => [
                    'current_page' => $items->currentPage(),
                    'per_page' => $items->perPage(),
                    'total' => $items->total(),
                    'last_page' => $items->lastPage(),
                ],
            ], HttpCode::SUCCESS);
        } catch (\Exception $e) {
            Log::error('Failed to get all videos', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => 'Failed to get videos',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], HttpCode::SERVER_ERROR);
        }
    }

    /**
     * Batch remake QR codes for multiple orders
     * Accepts array of order_ids and processes all items in those orders
     */
    public function batchRemakeQr(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'order_ids' => 'required|array|min:1',
                'order_ids.*' => 'integer|exists:orders,id'
            ]);

            $orderIds = $request->order_ids;
            $service = app(\App\Services\OrderProcessingService::class);

            $results = [];
            $totalItems = 0;
            $successCount = 0;
            $failedOrders = [];

            foreach ($orderIds as $orderId) {
                try {
                    $order = Order::with('items')->find($orderId);

                    if (!$order || $order->items->isEmpty()) {
                        $failedOrders[] = [
                            'order_id' => $orderId,
                            'error' => 'Order not found or has no items'
                        ];
                        continue;
                    }

                    $itemIds = $order->items->pluck('id')->toArray();
                    $totalItems += count($itemIds);

                    $qrResults = $service->createQRCodesForItems($order, $itemIds);

                    $results[] = [
                        'order_id' => $orderId,
                        'items_count' => count($itemIds),
                        'success' => true,
                        'qr_results' => $qrResults
                    ];
                    $successCount++;
                } catch (\Exception $e) {
                    $failedOrders[] = [
                        'order_id' => $orderId,
                        'error' => $e->getMessage()
                    ];
                    Log::error('Batch remake QR failed for order', [
                        'order_id' => $orderId,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            return response()->json([
                'code' => HttpCode::SUCCESS,
                'status' => true,
                'message' => "Batch remake QR completed. Success: {$successCount}/" . count($orderIds) . " orders, {$totalItems} items processed",
                'data' => [
                    'total_orders' => count($orderIds),
                    'success_orders' => $successCount,
                    'total_items' => $totalItems,
                    'results' => $results,
                    'failed_orders' => $failedOrders
                ]
            ], HttpCode::SUCCESS);
        } catch (\Exception $e) {
            Log::error('Batch remake QR failed', [
                'order_ids' => $request->order_ids ?? [],
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => 'Batch remake QR failed',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], HttpCode::SERVER_ERROR);
        }
    }

    /**
     * Batch remake design files for multiple orders
     * Accepts array of order_ids and processes all design metas in those orders
     */
    public function batchRemakeDes(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'order_ids' => 'required|array|min:1',
                'order_ids.*' => 'integer|exists:orders,id'
            ]);

            $orderIds = $request->order_ids;
            $designPositions = ['front', 'back', 'sleeve_left', 'sleeve_right', 'neck'];

            $feeService = app(\App\Services\FeeCalculationService::class);
            $orderProcessingService = app(\App\Services\OrderProcessingService::class);

            $results = [];
            $totalMetas = 0;
            $successCount = 0;
            $failedOrders = [];

            foreach ($orderIds as $orderId) {
                try {
                    $order = Order::with(['seller.profile.tier', 'items.metas'])->find($orderId);

                    if (!$order || $order->items->isEmpty()) {
                        $failedOrders[] = [
                            'order_id' => $orderId,
                            'error' => 'Order not found or has no items'
                        ];
                        continue;
                    }

                    // Get all design metas with PES files
                    $metas = OrderItemMeta::whereIn('order_item_id', $order->items->pluck('id'))
                        ->whereIn('meta_key', $designPositions)
                        ->where('meta_value', 'like', '%.pes')
                        ->get();

                    if ($metas->isEmpty()) {
                        $results[] = [
                            'order_id' => $orderId,
                            'metas_count' => 0,
                            'success' => true,
                            'message' => 'No PES files to remake'
                        ];
                        continue;
                    }

                    $totalMetas += $metas->count();
                    $tier = $order->seller->profile->tier->tier_id ?? 1;

                    $convertResult = $orderProcessingService->processConvertForMetas($metas, $order, $feeService, $tier);

                    $results[] = [
                        'order_id' => $orderId,
                        'metas_count' => $metas->count(),
                        'success' => $convertResult['success'],
                        'processed_metas' => $convertResult['processed_metas'] ?? []
                    ];

                    if ($convertResult['success']) {
                        $successCount++;
                    } else {
                        $failedOrders[] = [
                            'order_id' => $orderId,
                            'error' => $convertResult['message'] ?? 'Unknown error'
                        ];
                    }
                } catch (\Exception $e) {
                    $failedOrders[] = [
                        'order_id' => $orderId,
                        'error' => $e->getMessage()
                    ];
                    Log::error('Batch remake Des failed for order', [
                        'order_id' => $orderId,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            return response()->json([
                'code' => HttpCode::SUCCESS,
                'status' => true,
                'message' => "Batch remake Des completed. Success: {$successCount}/" . count($orderIds) . " orders, {$totalMetas} metas processed",
                'data' => [
                    'total_orders' => count($orderIds),
                    'success_orders' => $successCount,
                    'total_metas' => $totalMetas,
                    'results' => $results,
                    'failed_orders' => $failedOrders
                ]
            ], HttpCode::SUCCESS);
        } catch (\Exception $e) {
            Log::error('Batch remake Des failed', [
                'order_ids' => $request->order_ids ?? [],
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => 'Batch remake Des failed',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], HttpCode::SERVER_ERROR);
        }
    }

    /**
     * Batch convert labels for multiple orders
     * Calls /label/convert API for each order and updates tracking_id from barcode
     */
    public function batchConvertLabel(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'order_ids' => 'required|array|min:1',
                'order_ids.*' => 'integer|exists:orders,id'
            ]);

            $orderIds = $request->order_ids;

            $results = [];
            $successCount = 0;
            $failedOrders = [];

            foreach ($orderIds as $orderId) {
                try {
                    $order = Order::with(['items.productVariant'])->find($orderId);

                    if (!$order) {
                        $failedOrders[] = [
                            'order_id' => $orderId,
                            'error' => 'Order not found'
                        ];
                        continue;
                    }

                    if (empty($order->shipping_label)) {
                        $failedOrders[] = [
                            'order_id' => $orderId,
                            'error' => 'No shipping label'
                        ];
                        continue;
                    }

                    $oldTracking = $order->tracking_id;
                    $response = ProcessOrderLabelShip::postLabelConvert($order);

                    // Reload order to get updated values
                    $order->refresh();

                    $results[] = [
                        'order_id' => $orderId,
                        'success' => true,
                        'old_tracking' => $oldTracking,
                        'new_tracking' => $order->tracking_id,
                        'tracking_updated' => $oldTracking !== $order->tracking_id,
                        'convert_label' => $order->convert_label ? true : false,
                    ];
                    $successCount++;
                } catch (\Exception $e) {
                    $failedOrders[] = [
                        'order_id' => $orderId,
                        'error' => $e->getMessage()
                    ];
                    Log::error('Batch convert label failed for order', [
                        'order_id' => $orderId,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            return response()->json([
                'code' => HttpCode::SUCCESS,
                'status' => true,
                'message' => "Batch Convert Label completed. Success: {$successCount}/" . count($orderIds) . " orders",
                'data' => [
                    'total_orders' => count($orderIds),
                    'success_orders' => $successCount,
                    'results' => $results,
                    'failed_orders' => $failedOrders
                ]
            ], HttpCode::SUCCESS);
        } catch (\Exception $e) {
            Log::error('Batch convert label failed', [
                'order_ids' => $request->order_ids ?? [],
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => 'Batch convert label failed',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], HttpCode::SERVER_ERROR);
        }
    }

    /**
     * Batch recalculate pricing for multiple orders
     * Use this to fix orders that were created with incorrect pricing (e.g., missing variant prices)
     * Accepts array of order_ids and recalculates print_cost, shipping_cost, total_cost
     */
    public function batchRecalculatePricing(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'order_ids' => 'required|array|min:1',
                'order_ids.*' => 'integer|exists:orders,id'
            ]);

            $orderIds = $request->order_ids;
            $pricingService = app(\App\Services\OrderPricingService::class);

            $results = [];
            $successCount = 0;
            $failedOrders = [];

            foreach ($orderIds as $orderId) {
                try {
                    $order = Order::with(['items', 'seller.profile'])->find($orderId);

                    if (!$order) {
                        $failedOrders[] = [
                            'order_id' => $orderId,
                            'error' => 'Order not found'
                        ];
                        continue;
                    }

                    // Get tier from seller's profile (private_seller field)
                    $tier = $order->seller?->profile?->private_seller ?? 0;

                    // Store old values for comparison
                    $oldValues = [
                        'print_cost' => (float) $order->print_cost,
                        'shipping_cost' => (float) $order->shipping_cost,
                        'total_cost' => (float) $order->total_cost,
                    ];

                    // Recalculate pricing
                    $result = $pricingService->calculateOrderPricing($order, $tier);

                    if ($result['success']) {
                        // Reload order to get updated values
                        $order->refresh();

                        $newValues = [
                            'print_cost' => (float) $order->print_cost,
                            'shipping_cost' => (float) $order->shipping_cost,
                            'total_cost' => (float) $order->total_cost,
                        ];

                        $results[] = [
                            'order_id' => $orderId,
                            'success' => true,
                            'old_values' => $oldValues,
                            'new_values' => $newValues,
                            'changes' => [
                                'print_cost' => $newValues['print_cost'] - $oldValues['print_cost'],
                                'shipping_cost' => $newValues['shipping_cost'] - $oldValues['shipping_cost'],
                                'total_cost' => $newValues['total_cost'] - $oldValues['total_cost'],
                            ]
                        ];
                        $successCount++;

                        // Add timeline entry
                        Timeline::create([
                            'object' => 'order',
                            'object_id' => $orderId,
                            'owner_id' => Auth::id(),
                            'action' => 'pricing_recalculated',
                            'note' => sprintf(
                                'Pricing recalculated. Old: $%.2f → New: $%.2f (Change: %+.2f)',
                                $oldValues['total_cost'],
                                $newValues['total_cost'],
                                $newValues['total_cost'] - $oldValues['total_cost']
                            ),
                        ]);

                        Log::info('Order pricing recalculated', [
                            'order_id' => $orderId,
                            'old_values' => $oldValues,
                            'new_values' => $newValues
                        ]);
                    } else {
                        $failedOrders[] = [
                            'order_id' => $orderId,
                            'error' => $result['error'] ?? 'Unknown error'
                        ];
                    }
                } catch (\Exception $e) {
                    $failedOrders[] = [
                        'order_id' => $orderId,
                        'error' => $e->getMessage()
                    ];
                    Log::error('Batch recalculate pricing failed for order', [
                        'order_id' => $orderId,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            return response()->json([
                'code' => HttpCode::SUCCESS,
                'status' => true,
                'message' => "Batch recalculate pricing completed. Success: {$successCount}/" . count($orderIds) . " orders",
                'data' => [
                    'total_orders' => count($orderIds),
                    'success_orders' => $successCount,
                    'results' => $results,
                    'failed_orders' => $failedOrders
                ]
            ], HttpCode::SUCCESS);
        } catch (\Exception $e) {
            Log::error('Batch recalculate pricing failed', [
                'order_ids' => $request->order_ids ?? [],
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => 'Batch recalculate pricing failed',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], HttpCode::SERVER_ERROR);
        }
    }

    /**
     * Get process order status for multiple orders
     * Returns workflow flow status (staff, qc, packing, shipout) for each order
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getProcessOrderStatus(Request $request): JsonResponse
    {
        try {
            $idsParam = $request->query('ids');

            if (empty($idsParam)) {
                return response()->json([
                    'status' => 'error',
                    'count' => 0,
                    'message' => 'Order IDs are required',
                    'data' => []
                ], HttpCode::BAD_REQUEST);
            }

            // Parse order IDs
            $orderIds = array_map('intval', explode(',', $idsParam));
            $orderIds = array_filter($orderIds, fn($id) => $id > 0);

            if (empty($orderIds)) {
                return response()->json([
                    'status' => 'error',
                    'count' => 0,
                    'message' => 'No valid order IDs provided',
                    'data' => []
                ], HttpCode::BAD_REQUEST);
            }

            // Limit to prevent abuse
            if (count($orderIds) > 100) {
                return response()->json([
                    'status' => 'error',
                    'count' => 0,
                    'message' => 'Maximum 100 orders per request',
                    'data' => []
                ], HttpCode::BAD_REQUEST);
            }

            // Get orders with items, metas, and workflows
            $orders = Order::with([
                'items' => function ($query) {
                    $query->select('id', 'order_id', 'status');
                },
                'items.metas' => function ($query) {
                    $query->whereIn('meta_key', ['front_pdf', 'back_pdf', 'sleeve_left_pdf', 'sleeve_right_pdf', 'neck_pdf'])
                        ->select('id', 'order_item_id', 'meta_key', 'meta_value');
                },
                'items.workflows' => function ($query) {
                    $query->where('stage', 'staff');
                }
            ])
                ->whereIn('id', $orderIds)
                ->select('id', 'ref_id', 'fulfill_status')
                ->get();

            $result = [];

            foreach ($orders as $order) {
                $orderData = [
                    'id' => $order->id,
                    'ref_id' => $order->ref_id,
                    'fulfill_status' => $order->fulfill_status,
                    'items' => []
                ];

                foreach ($order->items as $item) {
                    $itemData = [
                        'id' => $item->id,
                        'order_id' => $item->order_id,
                        'status' => $item->status ? 1 : 0,
                        'order_item_metas' => []
                    ];

                    // Map workflows by position for easy lookup
                    $workflowStatus = [];
                    foreach ($item->workflows as $workflow) {
                        $workflowStatus[$workflow->position] = $workflow->completed;
                    }

                    foreach ($item->metas as $meta) {
                        // Extract position from meta_key (front_pdf -> front)
                        $position = str_replace('_pdf', '', $meta->meta_key);

                        // Get status from workflow (default to 0 if not found)
                        $status = isset($workflowStatus[$position]) && $workflowStatus[$position] ? 1 : 0;

                        $itemData['order_item_metas'][] = [
                            'id' => $meta->id,
                            'order_item_id' => $meta->order_item_id,
                            'meta_key' => $meta->meta_key,
                            'meta_value' => $meta->meta_value,
                            'status' => $status
                        ];
                    }

                    $orderData['items'][] = $itemData;
                }

                $result[] = $orderData;
            }

            return response()->json([
                'status' => 'success',
                'count' => count($result),
                'data' => $result
            ], HttpCode::SUCCESS);
        } catch (\Exception $e) {
            Log::error('Get process order status failed', [
                'ids' => $request->query('ids'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'count' => 0,
                'message' => 'Failed to get process order status',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], HttpCode::SERVER_ERROR);
        }
    }

    /**
     * Clear track order cache for an order
     * Cache keys match URL format: track_{order_id}?stt={stt}&item_id={item_id}&item_stt={item_stt}
     */
    protected function clearTrackOrderCache(int $orderId): void
    {
        try {
            // Get all items for this order with their quantities
            $items = OrderItem::where('order_id', $orderId)
                ->select('id', 'quantity')
                ->get();

            $clearedKeys = [];

            $trackPrefixes = ["track_{$orderId}", "track_v2_{$orderId}"];
            foreach ($trackPrefixes as $baseKey) {
                Cache::forget($baseKey);
                $clearedKeys[] = $baseKey;
            }

            // Track cumulative stt and item_stt (page index)
            $cumulativeStt = 0;
            $itemStt = 0;

            foreach ($items as $item) {
                $itemStt++;
                $itemId = $item->id;
                $quantity = $item->quantity ?? 1;

                foreach ($trackPrefixes as $prefix) {
                    Cache::forget("{$prefix}?item_stt={$itemStt}");
                    Cache::forget("{$prefix}?item_id={$itemId}");
                    Cache::forget("{$prefix}?item_id={$itemId}&item_stt={$itemStt}");
                }

                // Clear cache for each quantity of this item
                for ($q = 1; $q <= $quantity; $q++) {
                    $cumulativeStt++;

                    foreach ($trackPrefixes as $prefix) {
                        $combinations = [
                            "{$prefix}?stt={$cumulativeStt}",
                            "{$prefix}?stt={$cumulativeStt}&item_id={$itemId}",
                            "{$prefix}?stt={$cumulativeStt}&item_stt={$itemStt}",
                            "{$prefix}?stt={$cumulativeStt}&item_id={$itemId}&item_stt={$itemStt}",
                        ];

                        foreach ($combinations as $key) {
                            Cache::forget($key);
                            $clearedKeys[] = $key;
                        }
                    }
                }
            }

            Log::info('Cleared track order cache (from OrderController)', [
                'order_id' => $orderId,
                'keys_count' => count($clearedKeys)
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to clear track order cache', [
                'order_id' => $orderId,
                'error' => $e->getMessage()
            ]);
        }
    }
}
