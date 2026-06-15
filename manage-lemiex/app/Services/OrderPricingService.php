<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class OrderPricingService
{
    /**
     * Calculate and update order pricing
     */
    public function calculateOrderPricing(Order $order, int $tier): array
    {
        try {
            DB::beginTransaction();

            $totalPrintCost = 0;
            $items = OrderItem::where('order_id', $order->id)->get();

            // Calculate print cost for each item
            foreach ($items as $item) {
                $itemCost = $this->calculateItemPrintCost($item, $tier);

                // Update item price
                $item->update(['price' => $itemCost]);

                $totalPrintCost += $itemCost;
            }

            // Calculate shipping cost
            $shippingCost = $this->calculateShippingCost($order, $items->first(), $tier);

            // Calculate priority fee
            $priorityFee = $this->calculatePriorityFee($order, $tier);

            // Calculate total
            $totalCost = $totalPrintCost + $shippingCost + $priorityFee;

            // Validate pricing
            if ($totalCost <= 0 || $totalPrintCost <= 0) {
                Log::warning('Invalid pricing calculated', [
                    'order_id' => $order->id,
                    'total_cost' => $totalCost,
                    'print_cost' => $totalPrintCost
                ]);
            }

            // Update order
            $order->update([
                'print_cost' => $totalPrintCost,
                'shipping_cost' => $shippingCost,
                'total_cost' => $totalCost,
                'extra_fee' => 0.00,
                'refund_fee' => 0.00
            ]);

            DB::commit();

            return [
                'success' => true,
                'print_cost' => $totalPrintCost,
                'shipping_cost' => $shippingCost,
                'total_cost' => $totalCost
            ];
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to calculate pricing', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Calculate print cost for single item (NO DESIGN = base cost only)
     */
    protected function calculateItemPrintCost(OrderItem $item, int $tier): float
    {
        $variant = ProductVariant::with('priceVariants')
            ->where('variant_id', $item->variant_id)
            ->first();

        if (!$variant) {
            Log::warning('Product variant not found', [
                'variant_id' => $item->variant_id,
                'order_item_id' => $item->id
            ]);
            return 0;
        }

        // Get base cost based on tier
        $baseCost = $this->getBaseCostByTier($variant, $tier);

        // For NO DESIGN: only base cost × quantity
        // No two-side fee, no sleeve fee, no special design fee
        return round($baseCost * $item->quantity, 2);
    }

    /**
     * Get base cost based on seller tier
     */
    protected function getBaseCostByTier(ProductVariant $variant, int $tier): float
    {
        // Tier mapping: 0=Silver, 1=Gold, 2=Platinum, 3=Diamond
        $baseCostPrice = $variant->priceVariants
            ->where('tier_id', $tier)
            ->where('type', 'base_cost')
            ->first();

        if ($baseCostPrice) {
            return $baseCostPrice->price;
        }

        // Fallback to supplier price
        return $variant->supplier_price ?? 0;
    }

    /**
     * Calculate shipping cost
     */
    protected function calculateShippingCost(Order $order, ?OrderItem $firstItem, int $tier): float
    {
        // Check if has shipping label (TikTok type)
        $isTikTokShipping = !empty($order->shipping_label);

        if (!$firstItem) {
            return 0;
        }

        $variant = ProductVariant::with('priceVariants')
            ->where('variant_id', $firstItem->variant_id)
            ->first();

        if (!$variant) {
            return 0;
        }

        if ($isTikTokShipping) {
            // TikTok shipping: NO additional items cost, only base shipping
            // Get TikTok shipping price from database
            $tiktokShipping = $variant->priceVariants
                ->where('tier_id', $tier)
                ->where('type', 'tiktok_shipping')
                ->first();

            if ($tiktokShipping) {
                return round($tiktokShipping->price, 2);
            }

            // Default fallback: 0 (wood orders carry no built-in shipping).
            // If a tier-specific rate is needed later, add a row to
            // product_price_variants with type='tiktok_shipping'.
            return 0.0;
        }

        // Seller shipping: Base shipping + Additional items cost
        $sellerShipping = $variant->priceVariants
            ->where('tier_id', $tier)
            ->where('type', 'seller_shipping')
            ->first();

        $baseShipping = $sellerShipping ? $sellerShipping->price : 0;

        // Calculate additional items cost (SELLER SHIP logic)
        $items = OrderItem::with('productVariant')->where('order_id', $order->id)->get();
        $additionalCost = 0;

        foreach ($items as $index => $item) {
            // Skip first item (index 0) - only base shipping
            if ($index === 0) {
                continue;
            }

            // Get variant to check style
            $itemVariant = ProductVariant::where('variant_id', $item->variant_id)->first();
            if (!$itemVariant) {
                continue;
            }

            // Determine rate based on style
            // T-Shirt variants are cheaper, others (Hoodie, Sweatshirt) are more expensive
            $rate = (stripos($itemVariant->style, 'T-Shirt') !== false ||
                stripos($itemVariant->style, 'Tee') !== false) ? 2 : 3;

            // Calculate: rate × quantity for each additional item
            $additionalCost += $rate * $item->quantity;
        }

        return round($baseShipping + $additionalCost, 2);
    }

    /**
     * Calculate priority fee
     */
    protected function calculatePriorityFee(Order $order, int $tier): float
    {
        // Check fulfillment_priority column
        // Allow 'high', 'urgent', 'priority' (covering DB enum and migration variances)
        if ($order->fulfillment_priority !== 'priority') {
            return 0;
        }

        // Get first item to access variant pricing
        $firstItem = OrderItem::where('order_id', $order->id)->first();
        if (!$firstItem) {
            return 0;
        }

        $variant = ProductVariant::with('priceVariants')
            ->where('variant_id', $firstItem->variant_id)
            ->first();

        if (!$variant) {
            return 0;
        }

        $priorityFee = $variant->priceVariants
            ->where('tier_id', $tier)
            ->where('type', 'priority_shipping')
            ->first();

        return $priorityFee ? $priorityFee->price : 0;
    }

    /**
     * Calculate and update order pricing WITH DESIGN (for LABEL SHIP)
     */
    public function calculateOrderPricingWithDesign(Order $order, int $tier, array $lineItems): array
    {
        try {
            DB::beginTransaction();

            $totalPrintCost = 0;
            $items = OrderItem::with('metas')->where('order_id', $order->id)->get();

            // Calculate print cost for each item
            foreach ($items as $item) {
                // Find corresponding line item to get print_files
                $lineItem = collect($lineItems)->firstWhere('variant_id', $item->variant_id);
                $printFiles = $lineItem['print_files'] ?? [];

                $itemCost = $this->calculateItemPrintCostWithDesign($item, $tier, $printFiles);

                // Update item price
                $item->update(['price' => $itemCost]);

                $totalPrintCost += $itemCost;
            }

            // Calculate shipping cost
            $shippingCost = $this->calculateShippingCost($order, $items->first(), $tier);

            // Calculate priority fee
            $priorityFee = $this->calculatePriorityFee($order, $tier);

            // Calculate total (will be adjusted later after PES conversion)
            $totalCost = $totalPrintCost + $shippingCost + $priorityFee;

            // Validate pricing
            if ($totalCost <= 0 || $totalPrintCost <= 0) {
                Log::warning('Invalid pricing calculated', [
                    'order_id' => $order->id,
                    'total_cost' => $totalCost,
                    'print_cost' => $totalPrintCost
                ]);
            }

            // Update order
            $order->update([
                'print_cost' => $totalPrintCost,
                'shipping_cost' => $shippingCost,
                'total_cost' => $totalCost,
                'extra_fee' => 0.00, // Will be updated after PES conversion
                'refund_fee' => 0.00 // Will be updated after PES conversion
            ]);

            DB::commit();

            return [
                'success' => true,
                'print_cost' => $totalPrintCost,
                'shipping_cost' => $shippingCost,
                'total_cost' => $totalCost
            ];
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to calculate pricing with design', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Calculate print cost for single item WITH DESIGN
     */
    protected function calculateItemPrintCostWithDesign(OrderItem $item, int $tier, array $printFiles): float
    {
        $variant = ProductVariant::with('priceVariants')
            ->where('variant_id', $item->variant_id)
            ->first();

        if (!$variant) {
            Log::warning('Product variant not found', [
                'variant_id' => $item->variant_id,
                'order_item_id' => $item->id
            ]);
            return 0;
        }

        // Get base cost
        $baseCost = $this->getBaseCostByTier($variant, $tier);

        // Count design positions
        $itemMetasCount = 0; // front/back count
        $checkSleeveCount = 0; // sleeve count
        $checkSpecialCount = 0; // special design count

        foreach ($printFiles as $file) {
            $key = $file['key'] ?? '';

            if (in_array($key, ['front', 'back'])) {
                $itemMetasCount++;
            } elseif (in_array($key, ['sleeve_left', 'sleeve_right'])) {
                $checkSleeveCount++;
            } elseif ($key === 'special_design') {
                $checkSpecialCount++;
            }
        }

        // Calculate two-side fee (only if has both front AND back)
        $twoSideCost = 0;
        if ($itemMetasCount == 2) {
            // Try 'back' first, fallback to 'two_side_print'
            $twoSidePrice = $variant->priceVariants
                ->where('tier_id', $tier)
                ->whereIn('type', ['back', 'two_side_print'])
                ->first();
            $twoSideCost = $twoSidePrice ? $twoSidePrice->price : 0;
        }

        // Calculate sleeve fee
        $sleeveCost = 0;
        if ($checkSleeveCount > 0) {
            // Try specific sleeve types first, fallback to generic 'sleeve_print'
            foreach ($printFiles as $file) {
                $key = $file['key'] ?? '';
                if (in_array($key, ['sleeve_left', 'sleeve_right'])) {
                    // Try specific type first (sleeve_left, sleeve_right)
                    $sleevePrice = $variant->priceVariants
                        ->where('tier_id', $tier)
                        ->where('type', $key)
                        ->first();

                    // Fallback to generic 'sleeve_print'
                    if (!$sleevePrice) {
                        $sleevePrice = $variant->priceVariants
                            ->where('tier_id', $tier)
                            ->where('type', 'sleeve_print')
                            ->first();
                    }

                    if ($sleevePrice) {
                        $sleeveCost += $sleevePrice->price;
                    }
                }
            }
        }

        // Calculate special design fee
        $specialCost = 0;
        if ($checkSpecialCount > 0) {
            // Try 'special' first, fallback to 'special_design'
            $specialPrice = $variant->priceVariants
                ->where('tier_id', $tier)
                ->whereIn('type', ['special', 'special_design'])
                ->first();
            $specialCost = $specialPrice ? ($specialPrice->price * $item->quantity) : 0;
        }

        // Total cost
        $totalCost = ($baseCost + $twoSideCost + $sleeveCost) * $item->quantity + $specialCost;

        Log::info('Item print cost calculated with design', [
            'order_item_id' => $item->id,
            'base_cost' => $baseCost,
            'two_side_cost' => $twoSideCost,
            'sleeve_cost' => $sleeveCost,
            'special_cost' => $specialCost,
            'quantity' => $item->quantity,
            'total_cost' => $totalCost
        ]);

        return round($totalCost, 2);
    }
}
