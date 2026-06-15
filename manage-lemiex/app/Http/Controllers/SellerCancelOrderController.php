<?php

namespace App\Http\Controllers;

use App\Constants\HttpCode;
use App\Enums\OrderFulfillStatus;
use App\Enums\OrderPaymentStatus;
use App\Models\Order;
use App\Models\Timeline;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SellerCancelOrderController extends Controller
{
    protected OrderService $orderService;

    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    /**
     * Seller self-cancel unpaid order (public endpoint, authenticated via store api_key).
     *
     * Same authentication model as /api/orders/create. Caller passes their store
     * api_key in the request body — no JWT required.
     *
     * Allowed only when:
     * - Order belongs to the authenticated seller (seller_id matches store owner)
     * - Order is still in `new_order` status
     * - Order is NOT paid
     */
    public function sellerCancel(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'api_key' => 'required|string|exists:stores,api_key',
            'order_id' => 'required|integer|exists:orders,id',
            'reason' => 'nullable|string|max:500',
        ]);

        try {
            // Authenticate via store api_key (same as createOrder flow)
            $authResult = $this->orderService->authenticateStore($validated['api_key']);

            if (!$authResult['success']) {
                return response()->json([
                    'code' => $authResult['code'] ?? HttpCode::UNAUTHORIZED,
                    'status' => false,
                    'message' => $authResult['message'] ?? 'Authentication failed',
                    'data' => null,
                ], $authResult['code'] ?? HttpCode::UNAUTHORIZED);
            }

            $seller = $authResult['user'];
            $orderId = (int) $validated['order_id'];
            $reason = $validated['reason'] ?? 'Seller cancelled';

            $order = Order::find($orderId);

            if (!$order) {
                return response()->json([
                    'code' => HttpCode::NOT_FOUND,
                    'status' => false,
                    'message' => 'Order not found',
                    'data' => null,
                ], HttpCode::NOT_FOUND);
            }

            // Ownership: the order must belong to this seller
            if ((int) $order->seller_id !== (int) $seller->id) {
                return response()->json([
                    'code' => HttpCode::FORBIDDEN,
                    'status' => false,
                    'message' => 'You can only cancel your own orders',
                    'data' => null,
                ], HttpCode::FORBIDDEN);
            }

            if ($order->fulfill_status !== OrderFulfillStatus::NEW_ORDER) {
                return response()->json([
                    'code' => HttpCode::BAD_REQUEST,
                    'status' => false,
                    'message' => 'Only new orders can be cancelled by seller. Current status: ' . $order->fulfill_status,
                    'data' => null,
                ], HttpCode::BAD_REQUEST);
            }

            if ($order->payment_status === OrderPaymentStatus::PAID) {
                return response()->json([
                    'code' => HttpCode::BAD_REQUEST,
                    'status' => false,
                    'message' => 'Cannot cancel paid orders. Please contact support for assistance.',
                    'data' => null,
                ], HttpCode::BAD_REQUEST);
            }

            $order->fulfill_status = OrderFulfillStatus::CANCELLED;
            $order->save();

            $username = $seller->username ?? 'Seller';
            Timeline::create([
                'object' => 'order',
                'object_id' => $order->id,
                'owner_id' => $seller->id,
                'action' => 'cancelled',
                'note' => "{$username} cancelled order #{$order->id}" . ($reason ? " - Reason: {$reason}" : ''),
            ]);

            Log::info('Seller cancelled order (via api_key)', [
                'order_id' => $orderId,
                'seller_id' => $order->seller_id,
                'cancelled_by' => $seller->id,
                'reason' => $reason,
            ]);

            return response()->json([
                'code' => HttpCode::SUCCESS,
                'status' => true,
                'message' => 'Order cancelled successfully',
                'data' => [
                    'id' => $order->id,
                    'fulfill_status' => $order->fulfill_status,
                    'updated_at' => $order->updated_at,
                ],
            ], HttpCode::SUCCESS);
        } catch (\Exception $e) {
            Log::error('Failed to cancel order by seller', [
                'order_id' => $request->input('order_id'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => 'Failed to cancel order: ' . $e->getMessage(),
                'data' => null,
            ], HttpCode::SERVER_ERROR);
        }
    }
}
