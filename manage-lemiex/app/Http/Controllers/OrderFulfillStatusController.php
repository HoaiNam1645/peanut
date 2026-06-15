<?php

namespace App\Http\Controllers;

use App\Constants\HttpCode;
use App\Enums\OrderFulfillStatus;
use App\Http\Requests\BatchChangeFulfillStatusRequest;
use App\Http\Requests\ChangeFulfillStatusRequest;
use App\Services\OrderFulfillStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class OrderFulfillStatusController extends Controller
{
    protected $fulfillStatusService;

    public function __construct(OrderFulfillStatusService $fulfillStatusService)
    {
        $this->fulfillStatusService = $fulfillStatusService;
    }

    /**
     * Get all available fulfill statuses with counts
     */
    public function getFulfillStatuses(\Illuminate\Http\Request $request): JsonResponse
    {
        $statuses = OrderFulfillStatus::allWithLabels();
        $user = $request->user();

        // Check if user is a Seller
        $isSeller = $user->role && strtolower($user->role->name) === 'seller';

        // Count orders by status
        $query = \App\Models\Order::query();

        // Apply seller filter if user is a seller
        if ($isSeller) {
            $query->where('seller_id', $user->id);
        }

        // Apply the same date range filter as the orders list so the counts
        // reflect the currently selected "từ ngày đến ngày" range.
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        $counts = $query->select('fulfill_status', \Illuminate\Support\Facades\DB::raw('count(*) as total'))
            ->groupBy('fulfill_status')
            ->pluck('total', 'fulfill_status')
            ->toArray();

        // For Sellers: Merge pending_stock count into confirm
        if ($isSeller && isset($counts['pending_stock'])) {
            $counts['confirm'] = ($counts['confirm'] ?? 0) + $counts['pending_stock'];
            unset($counts['pending_stock']);
        }

        // Merge counts into statuses
        $data = array_map(function ($status) use ($counts) {
            $count = $counts[$status['value']] ?? 0;
            return [
                'value' => $status['value'],
                'label' => $status['label'],
                'count' => $count,
                'display_label' => $status['label'] . " ($count)"
            ];
        }, $statuses);

        // For Sellers: Remove pending_stock from the list
        if ($isSeller) {
            $data = array_values(array_filter($data, function ($status) {
                return $status['value'] !== 'pending_stock';
            }));
        }

        return response()->json([
            'code' => HttpCode::SUCCESS,
            'status' => true,
            'message' => 'Fulfill statuses retrieved successfully',
            'data' => $data
        ], HttpCode::SUCCESS);
    }

    /**
     * Change fulfill status of an order
     */
    public function changeFulfillStatus(ChangeFulfillStatusRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $result = $this->fulfillStatusService->changeFulfillStatus(
                $validated['order_id'],
                $validated['fulfill_status']
            );

            if (!$result['success']) {
                return response()->json([
                    'code' => $result['code'] ?? HttpCode::BAD_REQUEST,
                    'status' => false,
                    'message' => $result['message'],
                    'data' => null
                ], $result['code'] ?? HttpCode::BAD_REQUEST);
            }

            return response()->json([
                'code' => HttpCode::SUCCESS,
                'status' => true,
                'message' => 'Fulfill status changed successfully',
                'data' => $result['data']
            ], HttpCode::SUCCESS);
        } catch (\Exception $e) {
            Log::error('Failed to change fulfill status', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'code' => HttpCode::SERVER_ERROR,
                'status' => false,
                'message' => 'Failed to change fulfill status: ' . $e->getMessage(),
                'data' => null
            ], HttpCode::SERVER_ERROR);
        }
    }

    /**
     * Batch change fulfill status for multiple orders.
     * Each order is processed independently; failures do not stop the rest.
     */
    public function batchChangeFulfillStatus(BatchChangeFulfillStatusRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $orderIds = array_values(array_unique($validated['order_ids']));
        $newStatus = $validated['fulfill_status'];

        $results = [];
        $successCount = 0;
        $failCount = 0;

        foreach ($orderIds as $orderId) {
            try {
                $result = $this->fulfillStatusService->changeFulfillStatus($orderId, $newStatus);
                if ($result['success']) {
                    $successCount++;
                    $results[] = [
                        'order_id' => $orderId,
                        'success' => true,
                        'data' => $result['data'] ?? null,
                    ];
                } else {
                    $failCount++;
                    $results[] = [
                        'order_id' => $orderId,
                        'success' => false,
                        'code' => $result['code'] ?? HttpCode::BAD_REQUEST,
                        'message' => $result['message'] ?? 'Failed',
                    ];
                }
            } catch (\Exception $e) {
                $failCount++;
                $results[] = [
                    'order_id' => $orderId,
                    'success' => false,
                    'code' => HttpCode::SERVER_ERROR,
                    'message' => $e->getMessage(),
                ];
                Log::error('Batch change fulfill status: exception per order', [
                    'order_id' => $orderId,
                    'target_status' => $newStatus,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'code' => HttpCode::SUCCESS,
            'status' => true,
            'message' => "Batch processed: {$successCount} success, {$failCount} failed",
            'data' => [
                'target_status' => $newStatus,
                'total' => count($orderIds),
                'success_count' => $successCount,
                'fail_count' => $failCount,
                'results' => $results,
            ],
        ], HttpCode::SUCCESS);
    }
}
