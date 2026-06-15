<?php

namespace App\Http\Controllers;

use App\Constants\BuyLabelConstants;
use App\Constants\HttpCode;
use App\Services\BuyLabelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class BuyLabelController extends Controller
{
    private BuyLabelService $buyLabelService;

    public function __construct(BuyLabelService $buyLabelService)
    {
        $this->buyLabelService = $buyLabelService;
    }

    /**
     * Manual buy label for single order (ShipEngine)
     */
    public function buyLabelShipEngine(Request $request): JsonResponse
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            BuyLabelConstants::FIELD_ORDER_ID => 'required|integer|exists:orders,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => HttpCode::VALIDATION_ERROR,
                'status' => false,
                'message' => BuyLabelConstants::INVALID_ORDER_ID,
                'errors' => $validator->errors(),
            ], HttpCode::VALIDATION_ERROR);
        }

        $orderId = $request->input(BuyLabelConstants::FIELD_ORDER_ID);
        $user = Auth::user();
        // Call service
        $result = $this->buyLabelService->buyLabelForOrder($orderId, $user);

        return response()->json($result, $result['code']);
    }

    /**
     * Buy labels for multiple orders (batch)
     */
    public function buyAllLabel(Request $request): JsonResponse
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'order_ids' => 'required|array|min:1',
            'order_ids.*' => 'integer|exists:orders,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => HttpCode::VALIDATION_ERROR,
                'status' => false,
                'message' => BuyLabelConstants::INVALID_ORDER_IDS,
                'errors' => $validator->errors(),
            ], HttpCode::VALIDATION_ERROR);
        }

        $orderIds = $request->input('order_ids');
        $user = Auth::user();

        // Call service
        $result = $this->buyLabelService->dispatchBatchBuyLabel($orderIds, $user);

        return response()->json($result, $result['code']);
    }

    /**
     * Check which orders are eligible for buying labels
     */
    public function checkEligibleOrders(Request $request): JsonResponse
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'order_ids' => 'required|array|min:1',
            'order_ids.*' => 'integer|exists:orders,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => HttpCode::VALIDATION_ERROR,
                'status' => false,
                'message' => BuyLabelConstants::INVALID_ORDER_IDS,
                'errors' => $validator->errors(),
            ], HttpCode::VALIDATION_ERROR);
        }

        $orderIds = $request->input('order_ids');
        $user = Auth::user();

        // Call service
        $result = $this->buyLabelService->checkEligibleOrders($orderIds, $user);

        return response()->json($result, $result['code']);
    }
}
