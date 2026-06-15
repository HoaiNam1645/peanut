<?php

namespace App\Http\Controllers;

use App\Constants\HttpCode;
use App\Models\FulfillmentPriority;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FulfillmentPriorityController extends Controller
{
    /**
     * Get all fulfillment priorities
     */
    public function getAll(): JsonResponse
    {
        try {
            $priorities = FulfillmentPriority::getAllGrouped();
            $tiers = FulfillmentPriority::getTierNames();

            return response()->json([
                'code' => 200,
                'status' => true,
                'message' => 'Fulfillment priorities retrieved successfully',
                'data' => [
                    'priorities' => $priorities,
                    'tiers' => $tiers,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'status' => false,
                'message' => 'Failed to retrieve fulfillment priorities',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get fulfillment priorities for a specific tier (used by order creation)
     */
    public function getForTier(Request $request): JsonResponse
    {
        try {
            $tierId = $request->input('tier_id', 0);

            $priorities = FulfillmentPriority::where('tier_id', $tierId)
                ->where('active', true)
                ->get()
                ->map(function ($priority) {
                    return [
                        'name' => $priority->name,
                        'display_name' => $priority->display_name,
                        'description' => $priority->description,
                        'price' => (float) $priority->price,
                    ];
                });

            return response()->json([
                'code' => 200,
                'status' => true,
                'message' => 'Fulfillment priorities for tier retrieved successfully',
                'data' => $priorities
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'status' => false,
                'message' => 'Failed to retrieve fulfillment priorities',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Update fulfillment priority pricing (Admin only)
     */
    public function update(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'priorities' => 'required|array',
                'priorities.*.name' => 'required|string|in:normal,priority',
                'priorities.*.tier_id' => 'required|integer|in:0,1,2,3',
                'priorities.*.price' => 'required|numeric|min:0',
                'priorities.*.display_name' => 'sometimes|string|max:100',
                'priorities.*.description' => 'sometimes|string|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'code' => 400,
                    'status' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            $updated = [];
            foreach ($request->priorities as $priorityData) {
                $priority = FulfillmentPriority::updateOrCreate(
                    [
                        'name' => $priorityData['name'],
                        'tier_id' => $priorityData['tier_id'],
                    ],
                    [
                        'price' => $priorityData['price'],
                        'display_name' => $priorityData['display_name'] ?? ucfirst($priorityData['name']),
                        'description' => $priorityData['description'] ?? null,
                        'active' => true,
                    ]
                );
                $updated[] = $priority;
            }

            return response()->json([
                'code' => 200,
                'status' => true,
                'message' => 'Fulfillment priorities updated successfully',
                'data' => $updated
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'status' => false,
                'message' => 'Failed to update fulfillment priorities',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get price for a specific priority and tier
     */
    public function getPrice(Request $request): JsonResponse
    {
        try {
            $priorityName = $request->input('priority', 'normal');
            $tierId = $request->input('tier_id', 0);

            $price = FulfillmentPriority::getPriceForTier($priorityName, $tierId);

            return response()->json([
                'code' => 200,
                'status' => true,
                'data' => [
                    'priority' => $priorityName,
                    'tier_id' => $tierId,
                    'price' => $price,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'status' => false,
                'message' => 'Failed to get price',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Add a new fulfillment priority for a specific tier
     */
    public function add(Request $request, $tierId): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:50',
                'display_name' => 'required|string|max:100',
                'description' => 'nullable|string|max:500',
                'price' => 'required|numeric|min:0',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'code' => 400,
                    'status' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            // Check if already exists
            $existing = FulfillmentPriority::where('name', $request->name)
                ->where('tier_id', $tierId)
                ->first();

            if ($existing) {
                return response()->json([
                    'code' => 400,
                    'status' => false,
                    'message' => 'Priority already exists for this tier'
                ], 400);
            }

            $priority = FulfillmentPriority::create([
                'name' => $request->name,
                'display_name' => $request->display_name,
                'description' => $request->description,
                'tier_id' => $tierId,
                'price' => $request->price,
                'active' => true,
            ]);

            return response()->json([
                'code' => 200,
                'status' => true,
                'success' => true,
                'message' => 'Fulfillment priority added successfully',
                'data' => $priority
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'status' => false,
                'message' => 'Failed to add fulfillment priority',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Update a specific fulfillment priority
     */
    public function updateSingle(Request $request, $tierId, $id): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|string|max:50',
                'display_name' => 'sometimes|string|max:100',
                'description' => 'nullable|string|max:500',
                'price' => 'sometimes|numeric|min:0',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'code' => 400,
                    'status' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            $priority = FulfillmentPriority::where('id', $id)
                ->where('tier_id', $tierId)
                ->first();

            if (!$priority) {
                return response()->json([
                    'code' => 404,
                    'status' => false,
                    'message' => 'Fulfillment priority not found'
                ], 404);
            }

            $priority->update($request->only(['name', 'display_name', 'description', 'price']));

            return response()->json([
                'code' => 200,
                'status' => true,
                'success' => true,
                'message' => 'Fulfillment priority updated successfully',
                'data' => $priority
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'status' => false,
                'message' => 'Failed to update fulfillment priority',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Delete a specific fulfillment priority
     */
    public function delete($tierId, $id): JsonResponse
    {
        try {
            $priority = FulfillmentPriority::where('id', $id)
                ->where('tier_id', $tierId)
                ->first();

            if (!$priority) {
                return response()->json([
                    'code' => 404,
                    'status' => false,
                    'message' => 'Fulfillment priority not found'
                ], 404);
            }

            $priority->delete();

            return response()->json([
                'code' => 200,
                'status' => true,
                'success' => true,
                'message' => 'Fulfillment priority deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'status' => false,
                'message' => 'Failed to delete fulfillment priority',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get fulfillment priorities for a specific tier (for TierCard)
     */
    public function getByTierId($tierId): JsonResponse
    {
        try {
            $priorities = FulfillmentPriority::where('tier_id', $tierId)
                ->where('active', true)
                ->orderBy('name')
                ->get();

            return response()->json([
                'code' => 200,
                'status' => true,
                'success' => true,
                'data' => $priorities
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'status' => false,
                'message' => 'Failed to get priorities',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}
