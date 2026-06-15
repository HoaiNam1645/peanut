<?php

namespace App\Services;

use App\Models\ExtraFee;
use App\Models\RefundFee;
use App\Models\EmbroideryFee;
use Illuminate\Support\Facades\Log;

class FeeCalculationService
{
    /**
     * Calculate extra fee based on tier and stitch count
     * 
     * @param int $tierId Tier ID (0:Silver, 1:Gold, 2:Platinum, 3:Diamond)
     * @param int $stitchCount Number of stitches
     * @return float Extra fee amount
     */
    public function calExtraFee(int $tierId, int $stitchCount): float
    {
        try {
            $extraFee = ExtraFee::where('tier_id', $tierId)
                ->where('min_stitch', '<=', $stitchCount)
                ->where('max_stitch', '>=', $stitchCount)
                ->first();

            if ($extraFee) {
                Log::info('Extra fee calculated', [
                    'tier_id' => $tierId,
                    'stitch_count' => $stitchCount,
                    'amount' => $extraFee->amount
                ]);

                return (float) $extraFee->amount;
            }

            // If no matching range found, check if stitch count exceeds max
            $maxFee = ExtraFee::where('tier_id', $tierId)
                ->orderBy('max_stitch', 'desc')
                ->first();

            if ($maxFee && $stitchCount > $maxFee->max_stitch) {
                Log::info('Extra fee calculated (max range)', [
                    'tier_id' => $tierId,
                    'stitch_count' => $stitchCount,
                    'amount' => $maxFee->amount
                ]);

                return (float) $maxFee->amount;
            }

            Log::info('No extra fee found', [
                'tier_id' => $tierId,
                'stitch_count' => $stitchCount
            ]);

            return 0.00;
        } catch (\Exception $e) {
            Log::error('Failed to calculate extra fee', [
                'tier_id' => $tierId,
                'stitch_count' => $stitchCount,
                'error' => $e->getMessage()
            ]);

            return 0.00;
        }
    }

    /**
     * Calculate refund fee for items with only one side
     * Refund applies when actual stitch count is LESS THAN the threshold in the table
     * 
     * @param int $tierId Tier ID (0:Silver, 1:Gold, 2:Platinum, 3:Diamond)
     * @param int $stitchCount Number of stitches
     * @return float Refund fee amount
     */
    public function calRefundFee(int $tierId, int $stitchCount): float
    {
        try {
            // Find the smallest threshold that is GREATER than the actual stitch count
            // Example: threshold=10000, amount=$2
            //   - stitchCount=9000 → 10000 > 9000 → Match → Refund $2
            //   - stitchCount=10800 → 10000 > 10800? NO → No match → $0
            $refundFee = RefundFee::where('tier_id', $tierId)
                ->where('stitch', '>', $stitchCount)
                ->orderBy('stitch', 'asc')
                ->first();

            if ($refundFee) {
                Log::info('Refund fee calculated', [
                    'tier_id' => $tierId,
                    'stitch_count' => $stitchCount,
                    'threshold' => $refundFee->stitch,
                    'amount' => $refundFee->amount
                ]);

                return (float) $refundFee->amount;
            }

            Log::info('No refund fee - stitch count exceeds all thresholds', [
                'tier_id' => $tierId,
                'stitch_count' => $stitchCount
            ]);

            return 0.00;
        } catch (\Exception $e) {
            Log::error('Failed to calculate refund fee', [
                'tier_id' => $tierId,
                'stitch_count' => $stitchCount,
                'error' => $e->getMessage()
            ]);

            return 0.00;
        }
    }

    /**
     * Calculate embroidery fee based on tier, embroidery type and stitch count
     * 
     * @param int $tierId Tier ID (0:Silver, 1:Gold, 2:Platinum, 3:Diamond)
     * @param string $embroideryType Embroidery type (standard, metallic, glow, puff)
     * @param int $stitchCount Number of stitches
     * @return float Embroidery fee amount
     */
    public function calEmbroideryFee(int $tierId, string $embroideryType, int $stitchCount): float
    {
        try {
            // Standard type has no extra fee
            if ($embroideryType === EmbroideryFee::TYPE_STANDARD || empty($embroideryType)) {
                return 0.00;
            }

            // Find matching fee by tier, type and stitch range
            $embroideryFee = EmbroideryFee::where('tier_id', $tierId)
                ->where('embroidery_type', $embroideryType)
                ->where('min_stitch', '<=', $stitchCount)
                ->where('max_stitch', '>=', $stitchCount)
                ->first();

            if ($embroideryFee) {
                Log::info('Embroidery fee calculated', [
                    'tier_id' => $tierId,
                    'embroidery_type' => $embroideryType,
                    'stitch_count' => $stitchCount,
                    'amount' => $embroideryFee->amount
                ]);

                return (float) $embroideryFee->amount;
            }

            // If no matching range found, check if stitch count exceeds max
            $maxFee = EmbroideryFee::where('tier_id', $tierId)
                ->where('embroidery_type', $embroideryType)
                ->orderBy('max_stitch', 'desc')
                ->first();

            if ($maxFee && $stitchCount > $maxFee->max_stitch) {
                Log::info('Embroidery fee calculated (max range)', [
                    'tier_id' => $tierId,
                    'embroidery_type' => $embroideryType,
                    'stitch_count' => $stitchCount,
                    'amount' => $maxFee->amount
                ]);

                return (float) $maxFee->amount;
            }

            Log::info('No embroidery fee found', [
                'tier_id' => $tierId,
                'embroidery_type' => $embroideryType,
                'stitch_count' => $stitchCount
            ]);

            return 0.00;
        } catch (\Exception $e) {
            Log::error('Failed to calculate embroidery fee', [
                'tier_id' => $tierId,
                'embroidery_type' => $embroideryType,
                'stitch_count' => $stitchCount,
                'error' => $e->getMessage()
            ]);

            return 0.00;
        }
    }
}
