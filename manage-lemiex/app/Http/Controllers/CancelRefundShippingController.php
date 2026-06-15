<?php

namespace App\Http\Controllers;

use App\Constants\HttpCode;
use App\Constants\OrderStatus;
use App\Enums\OrderFulfillStatus;
use App\Enums\OrderPaymentStatus;
use App\Models\Order;
use App\Models\Timeline;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CancelRefundShippingController extends Controller
{
    /**
     * Cancel order and refund shipping cost
     * 
     * This endpoint allows cancelling an order that is NOT shipped,
     * and refunds only the shipping cost back to the seller's wallet.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function cancelWithShippingRefund(Request $request): JsonResponse
    {
        $request->validate([
            'order_id' => 'required|integer|exists:orders,id',
            'note' => 'nullable|string|max:500',
        ]);

        try {
            DB::beginTransaction();

            $orderId = $request->input('order_id');
            $note = $request->input('note', '');

            // Load order with relations
            $order = Order::with(['seller.profile', 'items.productions', 'items.variant'])
                ->find($orderId);

            if (!$order) {
                DB::rollBack();
                return response()->json([
                    'code' => HttpCode::NOT_FOUND,
                    'status' => false,
                    'message' => 'Order not found',
                    'data' => null
                ], HttpCode::NOT_FOUND);
            }

            $currentStatus = $order->fulfill_status;
            $user = Auth::user();
            $userRole = $user->role->name ?? null;

            // Validate: Cannot cancel if already shipped
            if ($currentStatus === OrderFulfillStatus::SHIPPED) {
                DB::rollBack();
                return response()->json([
                    'code' => HttpCode::BAD_REQUEST,
                    'status' => false,
                    'message' => 'Cannot cancel order that has already been shipped',
                    'data' => null
                ], HttpCode::BAD_REQUEST);
            }

            // Validate: Cannot cancel if already cancelled
            if (in_array($currentStatus, [
                OrderFulfillStatus::CANCELLED,
                OrderFulfillStatus::CANCELLED_REFUND_SHIPPING
            ])) {
                DB::rollBack();
                return response()->json([
                    'code' => HttpCode::BAD_REQUEST,
                    'status' => false,
                    'message' => 'Order is already cancelled',
                    'data' => null
                ], HttpCode::BAD_REQUEST);
            }

            // Validate: Staff cannot cancel from certain statuses
            $restrictedForStaff = [
                OrderFulfillStatus::CONFIRM,
                OrderFulfillStatus::PENDING_STOCK,
                OrderFulfillStatus::PRODUCING,
                OrderFulfillStatus::ON_HOLD,
            ];

            if ($userRole === 'Staff' && in_array($currentStatus, $restrictedForStaff)) {
                DB::rollBack();
                return response()->json([
                    'code' => HttpCode::FORBIDDEN,
                    'status' => false,
                    'message' => 'Staff cannot cancel order from this status',
                    'data' => null
                ], HttpCode::FORBIDDEN);
            }

            // Process refund shipping cost
            $shippingRefunded = 0;
            $seller = $order->seller;
            $profile = $seller?->profile;

            if (
                $order->payment_status === OrderPaymentStatus::PAID &&
                $order->shipping_cost > 0 &&
                $profile
            ) {

                $shippingCost = (float) $order->shipping_cost;

                // Add shipping cost back to wallet
                $profile->wallet_balance += $shippingCost;
                $profile->save();

                // Create refund transaction
                Transaction::create([
                    'order_id' => $order->id,
                    'seller_id' => $seller->id,
                    'amount' => $shippingCost,
                    'remaining_balance' => $profile->wallet_balance,
                    'type' => 'refund',
                    'status' => 'approved',
                    'note' => "Shipping refund for cancelled order #{$order->id}" . ($note ? " - {$note}" : '')
                ]);

                $shippingRefunded = $shippingCost;

                Log::info('Shipping cost refunded', [
                    'order_id' => $order->id,
                    'shipping_cost' => $shippingCost,
                    'seller_id' => $seller->id,
                    'new_balance' => $profile->wallet_balance,
                ]);
            }

            // Cancel all productions & update demand
            foreach ($order->items as $item) {
                // Cancel productions
                $productions = $item->productions ?? [];
                foreach ($productions as $production) {
                    if ($production->status !== 'canceled') {
                        $production->status = 'canceled';
                        $production->save();
                    }
                }

                // If order was in pending_stock, we need to release pending_demand immediately
                if ($currentStatus === OrderFulfillStatus::PENDING_STOCK && $item->variant) {
                    $variant = $item->variant;
                    if ($variant->pending_demand >= $item->quantity) {
                        $variant->decrement('pending_demand', $item->quantity);
                        Log::info("Decremented pending_demand for variant {$variant->id}", [
                            'variant_id' => $variant->id,
                            'decrement_amount' => $item->quantity,
                            'new_pending_demand' => $variant->pending_demand
                        ]);
                    } else {
                        // Fallback just in case
                        $variant->update(['pending_demand' => 0]);
                    }
                }
            }

            // Update order status
            $order->fulfill_status = OrderFulfillStatus::CANCELLED_REFUND_SHIPPING;
            $order->refund_fee = ($order->refund_fee ?? 0) + $shippingRefunded;
            $order->save();

            // Create timeline entry
            $username = $user->username ?? 'System';
            Timeline::create([
                'object' => 'order',
                'object_id' => $order->id,
                'owner_id' => $user->id,
                'action' => 'cancelled_refund_shipping',
                'note' => "{$username} cancelled order #{$order->id} with shipping refund of \${$shippingRefunded}" . ($note ? " - {$note}" : '')
            ]);

            Log::info('Order cancelled with shipping refund', [
                'order_id' => $orderId,
                'from_status' => $currentStatus,
                'to_status' => OrderFulfillStatus::CANCELLED_REFUND_SHIPPING,
                'shipping_refunded' => $shippingRefunded,
                'user_id' => $user->id,
            ]);

            DB::commit();

            return response()->json([
                'code' => HttpCode::SUCCESS,
                'status' => true,
                'message' => 'Order cancelled successfully' . ($shippingRefunded > 0 ? " with \${$shippingRefunded} shipping refund" : ''),
                'data' => [
                    'id' => $order->id,
                    'fulfill_status' => $order->fulfill_status,
                    'shipping_refunded' => $shippingRefunded,
                    'refund_fee' => $order->refund_fee,
                    'updated_at' => $order->updated_at,
                ]
            ], HttpCode::SUCCESS);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to cancel order with shipping refund', [
                'order_id' => $request->input('order_id'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => 'Failed to cancel order: ' . $e->getMessage(),
                'data' => null
            ], HttpCode::SERVER_ERROR);
        }
    }
}
