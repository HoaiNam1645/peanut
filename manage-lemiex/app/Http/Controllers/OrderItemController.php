<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\ChangeItemStatusRequest;
use App\Services\OrderItemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class OrderItemController extends Controller
{
    protected $orderItemService;

    public function __construct(OrderItemService $orderItemService)
    {
        $this->orderItemService = $orderItemService;
    }

    /**
     * Change item workflow status
     * Stage is determined by user role:
     * - Staff/Admin/Support → stage: staff
     * - QC → stage: qc
     * - Packing → stage: packing
     * - Shipout → stage: shipout
     */
    public function changeItemStatus(ChangeItemStatusRequest $request): JsonResponse
    {
        try {
            $itemId = $request->input('item_id');
            $metaKey = $request->input('meta_key'); // This is the position (front, back, etc.)
            $status = $request->boolean('status');

            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'code' => 401,
                    'status' => false,
                    'message' => 'Unauthorized',
                    'data' => null
                ], 401);
            }

            // Stage from role. Supervisor roles (Admin/Staff/Support → 'staff') may act on ANY
            // stage via an explicit `stage` (this workshop has 1 worker covering QC/packing/
            // shipout). Dedicated stage roles (QC/Packing/Shipout) stay locked to their own stage.
            $roleStage = $this->getStageByRole($user->role_id);
            $requestedStage = $request->input('stage');
            $stage = ($roleStage === 'staff' && $requestedStage) ? $requestedStage : $roleStage;

            Log::info('Change item workflow status request', [
                'item_id' => $itemId,
                'position' => $metaKey,
                'stage' => $stage,
                'status' => $status,
                'user_id' => $user->id,
                'role_id' => $user->role_id
            ]);

            // For QC/Packing/Shipout: Auto-complete ALL positions (no need for meta_key)
            if (in_array($stage, ['qc', 'packing', 'shipout'])) {
                $result = $this->orderItemService->completeAllPositionsForStage($itemId, $stage, $status);
            } else {
                // For Staff: Update individual position
                $result = $this->orderItemService->changeWorkflowStatus($itemId, $metaKey, $stage, $status);
            }

            if (!$result['success']) {
                $httpCode = $result['code'] ?? 400;
                return response()->json([
                    'code' => $httpCode,
                    'status' => false,
                    'message' => $result['message'] ?? 'Failed to change item status',
                    'data' => null
                ], $httpCode);
            }

            return response()->json([
                'code' => 200,
                'status' => true,
                'message' => $result['message'] ?? 'Item status updated successfully',
                'data' => $result['data'] ?? null
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to change item workflow status', [
                'item_id' => $request->input('item_id'),
                'meta_key' => $request->input('meta_key'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'code' => 500,
                'status' => false,
                'message' => 'Internal server error',
                'data' => null
            ], 500);
        }
    }

    /**
     * Get workflow stage based on user role
     */
    private function getStageByRole(int $roleId): string
    {
        return match ($roleId) {
            UserRole::QC => 'qc',
            UserRole::PACKING => 'packing',
            UserRole::SHIPOUT => 'shipout',
            default => 'staff', // Admin, Staff, Support, etc. → staff stage
        };
    }

    /**
     * QC Reject Item - Reset all workflows, unmap stock, return to support
     * Called by QC app when rejecting an item
     * 
     * Request: { item_id }
     */
    public function qcRejectItem(Request $request): JsonResponse
    {
        try {
            $itemId = $request->input('item_id');

            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'code' => 401,
                    'status' => false,
                    'message' => 'Unauthorized',
                    'data' => null
                ], 401);
            }

            // Only QC or Admin can reject
            if ($user->role_id !== UserRole::QC && $user->role_id !== UserRole::ADMIN) {
                return response()->json([
                    'code' => 403,
                    'status' => false,
                    'message' => 'Only QC can reject items',
                    'data' => null
                ], 403);
            }

            Log::info('QC reject item request', [
                'item_id' => $itemId,
                'user_id' => $user->id
            ]);

            $result = $this->orderItemService->qcRejectItem($itemId);

            if (!$result['success']) {
                $httpCode = $result['code'] ?? 400;
                return response()->json([
                    'code' => $httpCode,
                    'status' => false,
                    'message' => $result['message'] ?? 'Failed to reject item',
                    'data' => null
                ], $httpCode);
            }

            return response()->json([
                'code' => 200,
                'status' => true,
                'message' => $result['message'] ?? 'Item rejected successfully',
                'data' => $result['data'] ?? null
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to reject item', [
                'item_id' => $request->input('item_id'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'code' => 500,
                'status' => false,
                'message' => 'Internal server error',
                'data' => null
            ], 500);
        }
    }
}
