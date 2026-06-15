<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderIssue;

class OrderValidationService
{
    /** Required meta keys cho 1 line item (wood/no_design) */
    private const REQUIRED_META_KEYS_PATTERNS = [
        // position-suffixed: front_pdf, back_pdf, etc.
        'pdf_per_position' => '/_pdf$/',
        'special_design_qr' => 'special_design_qr',
    ];

    /**
     * Run all checks for an order. Returns array of issues found.
     * Each issue: ['severity' => 'critical|warn', 'type' => string, 'message' => string]
     */
    public function checkOrder(Order $order): array
    {
        $issues = [];

        // Reload relations to ensure fresh data
        $order->load(['items.metas']);

        $issues = array_merge($issues, $this->checkPricing($order));
        $issues = array_merge($issues, $this->checkItems($order));
        $issues = array_merge($issues, $this->checkMetas($order));
        $issues = array_merge($issues, $this->checkAddressOrLabel($order));

        return $issues;
    }

    private function checkPricing(Order $order): array
    {
        $issues = [];

        if (is_null($order->total_cost) || (float) $order->total_cost <= 0) {
            $issues[] = [
                'severity' => OrderIssue::SEVERITY_CRITICAL,
                'type' => 'pricing_zero',
                'message' => "total_cost = " . ($order->total_cost ?? 'null'),
            ];
        }

        if ($order->paid_cost !== null && $order->total_cost !== null
            && (float) $order->paid_cost > (float) $order->total_cost) {
            $issues[] = [
                'severity' => OrderIssue::SEVERITY_WARN,
                'type' => 'paid_exceeds_total',
                'message' => "paid_cost ({$order->paid_cost}) > total_cost ({$order->total_cost})",
            ];
        }

        return $issues;
    }

    private function checkItems(Order $order): array
    {
        $issues = [];

        if ($order->items->isEmpty()) {
            $issues[] = [
                'severity' => OrderIssue::SEVERITY_CRITICAL,
                'type' => 'no_items',
                'message' => 'Đơn không có order_items nào',
            ];
            return $issues; // Không cần check tiếp các meta nếu không có item
        }

        foreach ($order->items as $item) {
            // Check mockup
            if (empty($item->mockup)) {
                $issues[] = [
                    'severity' => OrderIssue::SEVERITY_WARN,
                    'type' => 'item_missing_mockup',
                    'message' => "Item #{$item->id} (variant {$item->variant_id}): thiếu mockup",
                ];
            }

            // Variant không tồn tại trong DB
            $variantExists = \App\Models\ProductVariant::where('variant_id', $item->variant_id)->exists();
            if (!$variantExists) {
                $issues[] = [
                    'severity' => OrderIssue::SEVERITY_CRITICAL,
                    'type' => 'item_variant_orphan',
                    'message' => "Item #{$item->id}: variant_id '{$item->variant_id}' không tồn tại trong product_variants",
                ];
            }
        }

        return $issues;
    }

    private function checkMetas(Order $order): array
    {
        $issues = [];

        foreach ($order->items as $item) {
            $metas = $item->metas;

            if ($metas->isEmpty()) {
                $issues[] = [
                    'severity' => OrderIssue::SEVERITY_CRITICAL,
                    'type' => 'item_no_metas',
                    'message' => "Item #{$item->id} (variant {$item->variant_id}): không có order_item_metas nào",
                ];
                continue;
            }

            // Check meta_value rỗng
            foreach ($metas as $meta) {
                if (empty(trim((string) $meta->meta_value))) {
                    $issues[] = [
                        'severity' => OrderIssue::SEVERITY_CRITICAL,
                        'type' => 'meta_empty_value',
                        'message' => "Item #{$item->id}: meta_key '{$meta->meta_key}' có meta_value trống",
                    ];
                }
            }

            // Check required keys: phải có ít nhất 1 *_pdf, special_design_qr
            $metaKeys = $metas->pluck('meta_key')->all();

            $hasPdf = false;
            foreach ($metaKeys as $k) {
                if (preg_match(self::REQUIRED_META_KEYS_PATTERNS['pdf_per_position'], $k)) {
                    $hasPdf = true;
                    break;
                }
            }
            if (!$hasPdf) {
                $issues[] = [
                    'severity' => OrderIssue::SEVERITY_CRITICAL,
                    'type' => 'meta_missing_pdf',
                    'message' => "Item #{$item->id}: thiếu meta_key '{position}_pdf' (vd: front_pdf)",
                ];
            }

            if (!in_array('special_design_qr', $metaKeys, true)) {
                $issues[] = [
                    'severity' => OrderIssue::SEVERITY_CRITICAL,
                    'type' => 'meta_missing_qr',
                    'message' => "Item #{$item->id}: thiếu meta_key 'special_design_qr'",
                ];
            }
        }

        return $issues;
    }

    private function checkAddressOrLabel(Order $order): array
    {
        $issues = [];

        // Address check tạm bỏ — FormRequest đã reject sync khi tạo đơn nếu seller_ship thiếu address

        if ($order->order_type === 'label_ship') {
            if (empty($order->shipping_label)) {
                $issues[] = [
                    'severity' => OrderIssue::SEVERITY_CRITICAL,
                    'type' => 'missing_shipping_label',
                    'message' => 'order_type=label_ship nhưng shipping_label null',
                ];
            }
        }

        return $issues;
    }
}
