<?php

namespace App\Http\Controllers;

use App\Models\ExtraFee;
use App\Models\RefundFee;
use App\Models\EmbroideryFee;
use App\Models\Tier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TierController extends Controller
{
    /**
     * Get all tiers with extra fees and refund fees
     */
    public function index(Request $request): JsonResponse
    {
        $tiers = Tier::with(['extraFees', 'refundFees', 'embroideryFees', 'priorityFees'])
            ->orderBy('tier_id')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'tiers' => $tiers,
            ],
        ]);
    }

    /**
     * Get tier options for dropdown
     */
    public function getTierOptions(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'tiers' => Tier::orderBy('tier_id')->get(['id', 'tier_id', 'name']),
            ],
        ]);
    }

    /**
     * Get available embroidery types (from database)
     * Only returns types that have been configured with pricing
     */
    public function getEmbroideryTypes(): JsonResponse
    {
        // Get distinct embroidery types from embroidery_fee table
        $types = EmbroideryFee::select('embroidery_type')
            ->distinct()
            ->orderBy('embroidery_type')
            ->pluck('embroidery_type');

        // Transform to format expected by frontend
        $data = $types->map(function ($type) {
            return [
                'value' => $type,
                'label' => ucfirst($type),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'status' => true,
            'data' => $data,
        ]);
    }

    /**
     * Create a new tier
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $name = trim($request->name);

        if (Tier::whereRaw('LOWER(name) = ?', [strtolower($name)])->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Tier name already exists',
            ], 400);
        }

        $tierId = ((int) Tier::max('tier_id')) + 1;

        $tier = Tier::create([
            'tier_id' => $tierId,
            'name' => $name,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Tier created successfully',
            'data' => $tier->load(['extraFees', 'refundFees']),
        ], 201);
    }

    /**
     * Update tier name
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $tier = Tier::find($id);

        if (!$tier) {
            return response()->json([
                'success' => false,
                'message' => 'Tier not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $tier->update(['name' => $request->name]);

        return response()->json([
            'success' => true,
            'message' => 'Tier updated successfully',
            'data' => $tier->load(['extraFees', 'refundFees']),
        ]);
    }

    /**
     * Delete a tier
     */
    public function destroy(int $id): JsonResponse
    {
        $tier = Tier::find($id);

        if (!$tier) {
            return response()->json([
                'success' => false,
                'message' => 'Tier not found',
            ], 404);
        }

        // Delete related extra fees and refund fees
        ExtraFee::where('tier_id', $tier->tier_id)->delete();
        RefundFee::where('tier_id', $tier->tier_id)->delete();

        $tier->delete();

        return response()->json([
            'success' => true,
            'message' => 'Tier deleted successfully',
        ]);
    }

    // ==================== EXTRA FEE MANAGEMENT ====================

    /**
     * Add extra fee to tier
     */
    public function addExtraFee(Request $request, int $tierId): JsonResponse
    {
        $tier = Tier::where('tier_id', $tierId)->first();

        if (!$tier) {
            return response()->json([
                'success' => false,
                'message' => 'Tier not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'min_stitch' => 'required|integer|min:0',
            'max_stitch' => 'required|integer|gt:min_stitch',
            'amount' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Check for overlapping ranges
        $overlap = ExtraFee::where('tier_id', $tierId)
            ->where(function ($query) use ($request) {
                $query->whereBetween('min_stitch', [$request->min_stitch, $request->max_stitch])
                    ->orWhereBetween('max_stitch', [$request->min_stitch, $request->max_stitch])
                    ->orWhere(function ($q) use ($request) {
                        $q->where('min_stitch', '<=', $request->min_stitch)
                            ->where('max_stitch', '>=', $request->max_stitch);
                    });
            })
            ->exists();

        if ($overlap) {
            return response()->json([
                'success' => false,
                'message' => 'Stitch range overlaps with existing extra fee',
            ], 400);
        }

        $extraFee = ExtraFee::create([
            'tier_id' => $tierId,
            'min_stitch' => $request->min_stitch,
            'max_stitch' => $request->max_stitch,
            'amount' => $request->amount,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Extra fee added successfully',
            'data' => $extraFee,
        ], 201);
    }

    /**
     * Update extra fee
     */
    public function updateExtraFee(Request $request, int $tierId, int $id): JsonResponse
    {
        $extraFee = ExtraFee::where('tier_id', $tierId)->where('id', $id)->first();

        if (!$extraFee) {
            return response()->json([
                'success' => false,
                'message' => 'Extra fee not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'min_stitch' => 'required|integer|min:0',
            'max_stitch' => 'required|integer|gt:min_stitch',
            'amount' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Check for overlapping ranges (excluding current record)
        $overlap = ExtraFee::where('tier_id', $tierId)
            ->where('id', '!=', $id)
            ->where(function ($query) use ($request) {
                $query->whereBetween('min_stitch', [$request->min_stitch, $request->max_stitch])
                    ->orWhereBetween('max_stitch', [$request->min_stitch, $request->max_stitch])
                    ->orWhere(function ($q) use ($request) {
                        $q->where('min_stitch', '<=', $request->min_stitch)
                            ->where('max_stitch', '>=', $request->max_stitch);
                    });
            })
            ->exists();

        if ($overlap) {
            return response()->json([
                'success' => false,
                'message' => 'Stitch range overlaps with existing extra fee',
            ], 400);
        }

        $extraFee->update([
            'min_stitch' => $request->min_stitch,
            'max_stitch' => $request->max_stitch,
            'amount' => $request->amount,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Extra fee updated successfully',
            'data' => $extraFee,
        ]);
    }

    /**
     * Delete extra fee
     */
    public function deleteExtraFee(int $tierId, int $id): JsonResponse
    {
        $extraFee = ExtraFee::where('tier_id', $tierId)->where('id', $id)->first();

        if (!$extraFee) {
            return response()->json([
                'success' => false,
                'message' => 'Extra fee not found',
            ], 404);
        }

        $extraFee->delete();

        return response()->json([
            'success' => true,
            'message' => 'Extra fee deleted successfully',
        ]);
    }

    // ==================== REFUND FEE MANAGEMENT ====================

    /**
     * Add refund fee to tier
     */
    public function addRefundFee(Request $request, int $tierId): JsonResponse
    {
        $tier = Tier::where('tier_id', $tierId)->first();

        if (!$tier) {
            return response()->json([
                'success' => false,
                'message' => 'Tier not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'stitch' => 'required|integer|min:0',
            'amount' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Check if stitch value already exists for this tier
        if (RefundFee::where('tier_id', $tierId)->where('stitch', $request->stitch)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Refund fee for this stitch count already exists',
            ], 400);
        }

        $refundFee = RefundFee::create([
            'tier_id' => $tierId,
            'stitch' => $request->stitch,
            'amount' => $request->amount,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Refund fee added successfully',
            'data' => $refundFee,
        ], 201);
    }

    /**
     * Update refund fee
     */
    public function updateRefundFee(Request $request, int $tierId, int $id): JsonResponse
    {
        $refundFee = RefundFee::where('tier_id', $tierId)->where('id', $id)->first();

        if (!$refundFee) {
            return response()->json([
                'success' => false,
                'message' => 'Refund fee not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'stitch' => 'required|integer|min:0',
            'amount' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Check if stitch value already exists (excluding current record)
        if (RefundFee::where('tier_id', $tierId)
            ->where('stitch', $request->stitch)
            ->where('id', '!=', $id)
            ->exists()
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Refund fee for this stitch count already exists',
            ], 400);
        }

        $refundFee->update([
            'stitch' => $request->stitch,
            'amount' => $request->amount,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Refund fee updated successfully',
            'data' => $refundFee,
        ]);
    }

    /**
     * Delete refund fee
     */
    public function deleteRefundFee(int $tierId, int $id): JsonResponse
    {
        $refundFee = RefundFee::where('tier_id', $tierId)->where('id', $id)->first();

        if (!$refundFee) {
            return response()->json([
                'success' => false,
                'message' => 'Refund fee not found',
            ], 404);
        }

        $refundFee->delete();

        return response()->json([
            'success' => true,
            'message' => 'Refund fee deleted successfully',
        ]);
    }

    // ============== EMBROIDERY FEE METHODS ==============

    /**
     * Add embroidery fee to tier
     */
    public function addEmbroideryFee(Request $request, int $tierId): JsonResponse
    {
        $tier = Tier::where('tier_id', $tierId)->first();

        if (!$tier) {
            return response()->json([
                'success' => false,
                'message' => 'Tier not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'embroidery_type' => 'required|string|max:50',
            'min_stitch' => 'required|integer|min:0',
            'max_stitch' => 'required|integer|gt:min_stitch',
            'amount' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Check for overlapping ranges within same embroidery type
        $overlap = EmbroideryFee::where('tier_id', $tierId)
            ->where('embroidery_type', $request->embroidery_type)
            ->where(function ($query) use ($request) {
                $query->whereBetween('min_stitch', [$request->min_stitch, $request->max_stitch])
                    ->orWhereBetween('max_stitch', [$request->min_stitch, $request->max_stitch])
                    ->orWhere(function ($q) use ($request) {
                        $q->where('min_stitch', '<=', $request->min_stitch)
                            ->where('max_stitch', '>=', $request->max_stitch);
                    });
            })
            ->exists();

        if ($overlap) {
            return response()->json([
                'success' => false,
                'message' => 'Stitch range overlaps with existing embroidery fee for this type',
            ], 400);
        }

        $embroideryFee = EmbroideryFee::create([
            'tier_id' => $tierId,
            'embroidery_type' => $request->embroidery_type,
            'min_stitch' => $request->min_stitch,
            'max_stitch' => $request->max_stitch,
            'amount' => $request->amount,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Embroidery fee added successfully',
            'data' => $embroideryFee,
        ], 201);
    }

    /**
     * Update embroidery fee
     */
    public function updateEmbroideryFee(Request $request, int $tierId, int $id): JsonResponse
    {
        $embroideryFee = EmbroideryFee::where('tier_id', $tierId)->where('id', $id)->first();

        if (!$embroideryFee) {
            return response()->json([
                'success' => false,
                'message' => 'Embroidery fee not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'embroidery_type' => 'sometimes|string|max:50',
            'min_stitch' => 'sometimes|integer|min:0',
            'max_stitch' => 'sometimes|integer',
            'amount' => 'sometimes|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $minStitch = $request->min_stitch ?? $embroideryFee->min_stitch;
        $maxStitch = $request->max_stitch ?? $embroideryFee->max_stitch;
        $embType = $request->embroidery_type ?? $embroideryFee->embroidery_type;

        if ($maxStitch <= $minStitch) {
            return response()->json([
                'success' => false,
                'message' => 'max_stitch must be greater than min_stitch',
            ], 422);
        }

        // Check for overlapping ranges (excluding current record)
        $overlap = EmbroideryFee::where('tier_id', $tierId)
            ->where('embroidery_type', $embType)
            ->where('id', '!=', $id)
            ->where(function ($query) use ($minStitch, $maxStitch) {
                $query->whereBetween('min_stitch', [$minStitch, $maxStitch])
                    ->orWhereBetween('max_stitch', [$minStitch, $maxStitch])
                    ->orWhere(function ($q) use ($minStitch, $maxStitch) {
                        $q->where('min_stitch', '<=', $minStitch)
                            ->where('max_stitch', '>=', $maxStitch);
                    });
            })
            ->exists();

        if ($overlap) {
            return response()->json([
                'success' => false,
                'message' => 'Stitch range overlaps with existing embroidery fee for this type',
            ], 400);
        }

        $embroideryFee->update([
            'embroidery_type' => $embType,
            'min_stitch' => $minStitch,
            'max_stitch' => $maxStitch,
            'amount' => $request->amount ?? $embroideryFee->amount,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Embroidery fee updated successfully',
            'data' => $embroideryFee,
        ]);
    }

    /**
     * Delete embroidery fee
     */
    public function deleteEmbroideryFee(int $tierId, int $id): JsonResponse
    {
        $embroideryFee = EmbroideryFee::where('tier_id', $tierId)->where('id', $id)->first();

        if (!$embroideryFee) {
            return response()->json([
                'success' => false,
                'message' => 'Embroidery fee not found',
            ], 404);
        }

        $embroideryFee->delete();

        return response()->json([
            'success' => true,
            'message' => 'Embroidery fee deleted successfully',
        ]);
    }
}
