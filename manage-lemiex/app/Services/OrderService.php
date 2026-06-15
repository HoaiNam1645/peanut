<?php

namespace App\Services;

use App\Enums\OrderType;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemMeta;
use App\Models\Production;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\Timeline;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Exception;
use Illuminate\Support\Facades\Auth;

class OrderService
{
    /**
     * Authenticate store by API key and check seller role
     */
    public function authenticateStore(string $apiKey): array
    {
        $store = Store::with(['user.role', 'user.profile.tier'])
            ->where('api_key', $apiKey)
            ->first();

        if (!$store) {
            return [
                'success' => false,
                'message' => 'Invalid API key',
                'code' => 401
            ];
        }

        if (!$store->user) {
            return [
                'success' => false,
                'message' => 'Store user not found',
                'code' => 404
            ];
        }

        // Check if user is Seller (assuming role name is 'Seller')
        if ($store->user->role->name !== 'Seller') {
            return [
                'success' => false,
                'message' => 'Account is not Seller',
                'code' => 403
            ];
        }

        return [
            'success' => true,
            'store' => $store,
            'user' => $store->user,
            'tier' => $store->user->profile->private_seller ?? 0
        ];
    }

    /**
     * Convert order status string to fulfill_status enum
     */
    public function convertOrderStatus(string $orderStatus): string
    {
        $statusMap = [
            'new_order' => 'new_order',
            'test_order' => 'test_order',
            'priority' => 'new_order' // Map priority to new_order as priority is now handled by fulfillment_priority column
        ];

        return $statusMap[$orderStatus] ?? 'new_order';
    }

    /**
     * Create order with transaction
     */
    public function createOrder(array $orderData, array $requestData): array
    {
        try {
            DB::beginTransaction();

            // Check idempotency
            $existingOrder = Order::where('ref_id', $orderData['ref_id'])->first();
            if ($existingOrder) {
                DB::rollBack();

                // Check if order is completed (idempotent success)
                if (in_array($existingOrder->fulfill_status, ['shipped', 'cancelled'])) {
                    return [
                        'success' => true,
                        'order_id' => $existingOrder->id,
                        'message' => 'Order already exists (completed)',
                        'idempotent' => true
                    ];
                }

                // Check if order is being processed
                if (in_array($existingOrder->fulfill_status, ['producing', 'processing'])) {
                    return [
                        'success' => false,
                        'message' => 'Order is being processed',
                        'code' => 409
                    ];
                }

                // Order exists but in other status (new_order, on_hold, etc.)
                // Return existing order as idempotent response
                return [
                    'success' => true,
                    'order_id' => $existingOrder->id,
                    'message' => 'Order already exists',
                    'idempotent' => true
                ];
            }

            // Create order
            $order = Order::create($orderData);

            // Generate order_stt
            $orderStt = $this->generateOrderStt($order->id);
            $order->update(['order_stt' => $orderStt]);

            // Backup JSON to cloud
            $this->backupOrderJson($order->id, $requestData);

            DB::commit();

            return [
                'success' => true,
                'order' => $order
            ];
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to create order', [
                'ref_id' => $orderData['ref_id'] ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to create order',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'code' => 500
            ];
        }
    }

    /**
     * Generate order serial number
     * Format: {month}_{day} {order_id with dots}
     */
    public function generateOrderStt(int $orderId): string
    {
        $month = date('m');
        $day = date('d');
        $formattedId = number_format($orderId, 0, '.', '.');

        return "{$month}_{$day} {$formattedId}";
    }

    /**
     * Backup order JSON to B2
     */
    public function backupOrderJson(int $orderId, array $data): bool
    {
        try {
            $jsonContent = json_encode($data, JSON_PRETTY_PRINT);
            $fileName = "data_json/{$orderId}.json";

            // Upload to Backblaze B2
            Storage::disk('b2')->put($fileName, $jsonContent, 'public');
            $b2Url = Storage::disk('b2')->url($fileName);

            Log::info('Backed up order JSON to B2', [
                'order_id' => $orderId,
                'b2_url' => $b2Url,
                'file' => $fileName
            ]);

            return true;
        } catch (Exception $e) {
            Log::warning('Failed to backup order JSON', [
                'order_id' => $orderId,
                'error' => $e->getMessage()
            ]);

            // Save to order_metas as fallback
            try {
                DB::table('order_metas')->insert([
                    'object_id' => $orderId,
                    'meta_key' => 'backup_json',
                    'meta_value' => json_encode($data),
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            } catch (Exception $metaException) {
                Log::error('Failed to save JSON to order_metas', [
                    'order_id' => $orderId,
                    'error' => $metaException->getMessage()
                ]);
            }

            return false;
        }
    }

    /**
     * Delete old order items (for retry scenarios)
     */
    public function deleteOldOrderItems(int $orderId): void
    {
        OrderItem::where('order_id', $orderId)->delete();
    }

    /**
     * Create order items with transaction
     */
    public function createOrderItems(Order $order, array $lineItems): array
    {
        try {
            DB::beginTransaction();

            // Delete old items if any
            $this->deleteOldOrderItems($order->id);

            $createdItems = [];

            foreach ($lineItems as $item) {
                $quantity = (int) ($item['quantity'] ?? 1);

                // Split items with quantity > 1 into separate rows
                // Each row will have quantity = 1
                for ($i = 0; $i < $quantity; $i++) {
                    $orderItem = OrderItem::create([
                        'order_id' => $order->id,
                        'variant_id' => $item['variant_id'],
                        'product_name' => $item['product_name'],
                        'quantity' => 1, // Always 1 per row
                        'mockup' => $item['mockup'] ?? null,
                        'mockup_back' => $item['mockup_back'] ?? null,
                        'price' => null, // Will be calculated later
                        'status' => false, // Default status
                        'sides' => $item['sides'] ?? 1,
                        'id_style' => $item['id_style'] ?? null
                    ]);

                    $createdItems[] = $orderItem;
                }
            }

            Log::info('Order items created (split by quantity)', [
                'order_id' => $order->id,
                'original_items' => count($lineItems),
                'created_items' => count($createdItems)
            ]);

            DB::commit();

            return [
                'success' => true,
                'items' => $createdItems
            ];
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to create order items', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to create order items',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Create timeline entry
     */
    public function createTimeline(Order $order, string $action, string $note): bool
    {
        try {
            DB::transaction(function () use ($order, $action, $note) {
                Timeline::create([
                    'object' => 'order',
                    'object_id' => $order->id,
                    'owner_id' => $order->seller_id,
                    'action' => $action,
                    'note' => $note
                ]);
            });

            return true;
        } catch (Exception $e) {
            Log::warning('Failed to create timeline', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Create production records
     */
    public function createProductionRecords(Order $order): array
    {
        // Skip for test orders
        if ($order->fulfill_status === 'test_order') {
            return ['success' => true, 'skipped' => true];
        }

        try {
            DB::beginTransaction();

            $items = OrderItem::where('order_id', $order->id)->get();

            foreach ($items as $item) {
                // Check if production already exists
                $exists = Production::where('order_item_id', $item->id)->exists();
                if ($exists) {
                    continue;
                }

                Production::create([
                    'order_item_id' => $item->id,
                    'product_variant_id' => $item->variant_id,
                    'quantity' => $item->quantity,
                    'status' => 'pending',
                    'note' => "Auto-created from order #{$order->id} (Ref: {$order->ref_id})"
                ]);
            }

            DB::commit();

            return ['success' => true];
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to create production records', [
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
     * Create order items with design files (for LABEL SHIP)
     */
    public function createOrderItemsWithDesign(Order $order, array $lineItems): array
    {
        try {
            DB::beginTransaction();

            // Delete old items if any
            $this->deleteOldOrderItems($order->id);

            $createdItems = [];

            foreach ($lineItems as $item) {
                $quantity = (int) ($item['quantity'] ?? 1);
                $printFiles = $item['print_files'] ?? [];

                // Split items with quantity > 1 into separate rows
                // Each row will have quantity = 1 and the same design files
                for ($i = 0; $i < $quantity; $i++) {
                    // Create OrderItem
                    $orderItem = OrderItem::create([
                        'order_id' => $order->id,
                        'variant_id' => $item['variant_id'],
                        'product_name' => $item['product_name'],
                        'quantity' => 1, // Always 1 per row
                        'mockup' => $item['mockup'] ?? null,
                        'mockup_back' => $item['mockup_back'] ?? null,
                        'price' => null, // Will be calculated later
                        'status' => false
                    ]);

                    // Process print_files for each item row
                    foreach ($printFiles as $file) {
                        $key = $file['key'];
                        $embroideryType = $file['embroidery_type'] ?? 'standard';
                        $urlEmb = $file['url_emb'] ?? null;
                        $urlPes = $file['url_pes'] ?? null;
                        $url = $file['url'] ?? null;

                        // Skip if no URLs provided
                        if (!$urlEmb && !$urlPes && !$url) {
                            continue;
                        }

                        // Save EMB file
                        if ($urlEmb) {
                            OrderItemMeta::create([
                                'order_item_id' => $orderItem->id,
                                'meta_key' => $key . '_emb',
                                'meta_value' => $urlEmb,
                                'embroidery_type' => $embroideryType
                            ]);
                        }

                        // Save PES file
                        if ($urlPes) {
                            $pesUrl = $urlPes;

                            // Check if Google Drive URL (only upload once for first item)
                            if (str_contains($urlPes, 'drive.google.com') && $i === 0) {
                                // Upload to local storage (B2 temporarily disabled)
                                try {
                                    $pushService = app(\App\Services\PushFileJsonToBackblazeService::class);

                                    // Get variant for filename
                                    $variant = ProductVariant::where('variant_id', $item['variant_id'])->first();
                                    $sStyle = $variant ? preg_replace('/[^a-zA-Z0-9]/', '', $variant->style ?? 'Unknown') : 'Unknown';
                                    $sSize = $variant ? preg_replace('/[^a-zA-Z0-9]/', '', $variant->size ?? 'Unknown') : 'Unknown';
                                    $sColor = $variant ? preg_replace('/[^a-zA-Z0-9]/', '', $variant->color ?? 'Unknown') : 'Unknown';

                                    $fileName = "{$order->id}_{$orderItem->id}_{$key}_{$sStyle}_{$sSize}_{$sColor}.pes";
                                    $result = $pushService->pushPesToBlaze(
                                        $urlPes,
                                        $fileName,
                                        env('B2_BUCKET', 'Lemiex-Fulfillment')
                                    );
                                    $pesUrl = $result['fileName'];

                                    Log::info('Uploaded Google Drive PES to storage', [
                                        'google_drive_url' => $urlPes,
                                        'storage_url' => $pesUrl,
                                        'file_name' => $fileName
                                    ]);

                                    // Store uploaded URL for subsequent items
                                    $file['url_pes'] = $pesUrl;
                                } catch (Exception $e) {
                                    Log::error('Failed to upload Google Drive PES', [
                                        'url' => $urlPes,
                                        'file_name' => $fileName,
                                        'error' => $e->getMessage()
                                    ]);
                                    // Fallback: keep original URL
                                }
                            } elseif (!str_contains($urlPes, 'drive.google.com')) {
                                // For non-Google Drive URLs (including B2 temp uploads)
                                try {
                                    $pushService = app(\App\Services\PushFileJsonToBackblazeService::class);

                                    // Get variant details from DB
                                    $variantStyle = 'Unknown';
                                    $variantSize = 'Unknown';
                                    $variantColor = 'Unknown';

                                    if (isset($item['variant_id'])) {
                                        $variantItem = ProductVariant::where('variant_id', $item['variant_id'])->first();
                                        if ($variantItem) {
                                            $variantStyle = $variantItem->style ?? 'Unknown';
                                            $variantSize = $variantItem->size ?? 'Unknown';
                                            $variantColor = $variantItem->color ?? 'Unknown';
                                        }
                                    }

                                    // Sanitize for filename (remove special chars, spaces to _)
                                    $sStyle = preg_replace('/[^a-zA-Z0-9]/', '', $variantStyle);
                                    $sSize = preg_replace('/[^a-zA-Z0-9]/', '', $variantSize);
                                    $sColor = preg_replace('/[^a-zA-Z0-9]/', '', $variantColor);

                                    // Format: {order_id}_{item_id}_{key}_{style}_{size}_{color}.pes
                                    $fileName = "{$order->id}_{$orderItem->id}_{$key}_{$sStyle}_{$sSize}_{$sColor}.pes";

                                    $result = $pushService->pushPesToBlaze(
                                        $urlPes,
                                        $fileName,
                                        env('B2_BUCKET', 'Lemiex-Fulfillment')
                                    );
                                    $pesUrl = $result['fileName'];

                                    Log::info('Renamed PES file to standard format', [
                                        'original_url' => $urlPes,
                                        'new_url' => $pesUrl,
                                        'file_name' => $fileName
                                    ]);

                                    // Cleanup: Delete temp file if it's from our B2 bucket
                                    // Only delete if this is the last usage (last quantity item)
                                    if (str_contains($urlPes, env('B2_BUCKET', 'Lemiex-Fulfillment')) && $urlPes !== $pesUrl && $i === $quantity - 1) {
                                        try {
                                            $tempPath = parse_url($urlPes, PHP_URL_PATH);
                                            // Remove bucket name from path
                                            $tempPath = str_replace('/' . env('B2_BUCKET', 'Lemiex-Fulfillment') . '/', '', $tempPath);
                                            Storage::disk('b2')->delete($tempPath);

                                            Log::info('Deleted temp file after rename', [
                                                'temp_url' => $urlPes,
                                                'temp_path' => $tempPath
                                            ]);
                                        } catch (Exception $cleanupEx) {
                                            Log::warning('Failed to cleanup temp file (non-critical)', [
                                                'temp_url' => $urlPes,
                                                'error' => $cleanupEx->getMessage()
                                            ]);
                                        }
                                    }

                                    // Store uploaded URL for subsequent items
                                    $file['url_pes'] = $pesUrl;
                                } catch (Exception $e) {
                                    Log::error('Failed to upload/rename PES file', [
                                        'url' => $urlPes,
                                        'file_name' => $fileName,
                                        'error' => $e->getMessage()
                                    ]);
                                    // Fallback: keep original URL
                                }
                            }

                            OrderItemMeta::create([
                                'order_item_id' => $orderItem->id,
                                'meta_key' => $key,
                                'meta_value' => $pesUrl,
                                'embroidery_type' => $embroideryType
                            ]);
                        }

                        // Wood workshop: save design URL as PDF
                        if (!$urlPes && !$urlEmb && $url) {
                            // Google Drive share links aren't directly downloadable; convert to a
                            // direct-download URL so merge image / factory can fetch the file.
                            $url = app(\App\Services\PushFileJsonToBackblazeService::class)
                                ->toDirectDownloadUrl($url);

                            OrderItemMeta::create([
                                'order_item_id' => $orderItem->id,
                                'meta_key' => $key . '_pdf',
                                'meta_value' => $url,
                            ]);
                        }
                    }

                    $createdItems[] = $orderItem;
                }
            }

            Log::info('Order items with design created (split by quantity)', [
                'order_id' => $order->id,
                'original_items' => count($lineItems),
                'created_items' => count($createdItems)
            ]);

            DB::commit();

            return [
                'success' => true,
                'items' => $createdItems
            ];
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to create order items with design', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to create order items with design',
                'error' => $e->getMessage()
            ];
        }
    }

    // uploadGoogleDrivePesToB2() method removed - logic moved inline to createOrderItemsWithDesign()

    /**
     * Update order with changed fields only
     * Supports both label_ship and seller_ship
     */
    public function updateOrder(Order $order, array $changedFields): array
    {
        try {
            DB::beginTransaction();

            // Build update data
            $updateData = [];

            if (isset($changedFields['shipping_method'])) {
                $updateData['shipping_method'] = $changedFields['shipping_method'];
            }

            if (isset($changedFields['shipping_service'])) {
                $updateData['shipping_service'] = $changedFields['shipping_service'];
            }

            if (isset($changedFields['note'])) {
                $updateData['note'] = $changedFields['note'];
            }

            // Handle shipping_label change (label_ship only)
            if (isset($changedFields['shipping_label'])) {
                $updateData['shipping_label'] = $changedFields['shipping_label'];
            }

            // Handle address change (seller_ship only)
            if (isset($changedFields['address'])) {
                $addressData = $this->buildAddressUpdateData($changedFields['address']);
                $updateData = array_merge($updateData, $addressData);
            }

            if (!empty($updateData)) {
                $order->update($updateData);
                Log::info('Order basic fields updated', [
                    'order_id' => $order->id,
                    'updated_fields' => array_keys($updateData),
                ]);
            }

            if (isset($changedFields['line_items'])) {
                $this->updateOrderItems($order, $changedFields['line_items']);
            }

            DB::commit();

            // Process shipping_label sau khi commit (ngoài transaction) - label_ship only
            if (isset($changedFields['shipping_label'])) {
                $this->processShippingLabelUpdate($order);
            }

            // Process file & pricing follow-up after update
            if (isset($changedFields['line_items'])) {
                $lineItemChanges = $changedFields['line_items'];
                $hasPrintFilesChange = $this->hasPrintFilesChange($lineItemChanges);
                $hasVariantChange = $this->hasVariantChange($lineItemChanges);

                // A variant swap changes the base cost → recompute order pricing.
                if ($hasVariantChange) {
                    $this->recalculateOrderPricing($order);
                }

                // PES conversion restores embroidery fees after the pricing reset
                // (no-op for wood orders). Mirrors the create pipeline (pricing → PES).
                if ($hasPrintFilesChange || $hasVariantChange) {
                    $this->processPesConversion($order);
                }

                // Wood orders: regenerate merge_image for items whose print_files
                // OR variant changed. Tumbler has its own regenerate flow elsewhere.
                if (($hasPrintFilesChange || $hasVariantChange) && !$this->isTumblerOrder($order)) {
                    $changedItemIds = array_values(array_unique(array_merge(
                        $this->itemIdsWithPrintFilesChange($lineItemChanges),
                        $this->itemIdsWithVariantChange($lineItemChanges)
                    )));
                    if (!empty($changedItemIds)) {
                        app(\App\Services\WoodMergeImageService::class)
                            ->regenerateForItems($order, $changedItemIds);
                    }
                }
            }

            // Create timeline for update
            $user = Auth::user();
            $username = $user ? $user->username : 'System';
            $changedFieldsList = implode(', ', array_keys($changedFields));

            $this->createTimeline(
                $order,
                'update order',
                "{$username} updated order #{$order->order_stt}: {$changedFieldsList}"
            );

            Log::info('Order updated successfully', [
                'order_id' => $order->id,
                'order_type' => empty($order->convert_label) ? 'seller_ship' : 'label_ship',
                'changed_fields' => array_keys($changedFields),
            ]);

            return [
                'success' => true,
                'message' => 'Order updated',
                'data' => [
                    'order_id' => $order->id,
                    'changed_fields' => array_keys($changedFields),
                ]
            ];
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to update order', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to update order',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Build address update data from changed address fields
     */
    protected function buildAddressUpdateData(array $addressChanges): array
    {
        $updateData = [];

        // Handle name → split into first_name + last_name
        if (isset($addressChanges['name'])) {
            $nameParts = explode(' ', $addressChanges['name'], 2);
            $updateData['first_name'] = $nameParts[0] ?? null;
            $updateData['last_name'] = $nameParts[1] ?? null;
        }

        // Map other fields
        $fieldMapping = [
            'phone' => 'phone',
            'street1' => 'address_1',
            'street2' => 'address_2',
            'city' => 'city',
            'state' => 'state',
            'zip' => 'postcode',
            'country' => 'country',
        ];

        foreach ($fieldMapping as $inputKey => $dbKey) {
            if (isset($addressChanges[$inputKey])) {
                $updateData[$dbKey] = $addressChanges[$inputKey];
            }
        }

        return $updateData;
    }

    /**
     * Process shipping label update (label_ship only)
     * - TikTok URL: backup to B2 → convert
     * - Non-TikTok: convert only
     */
    protected function processShippingLabelUpdate(Order $order): void
    {
        try {
            // Reload order để lấy shipping_label mới
            $order->refresh();

            // Step 1: Backup nếu là TikTok URL
            \App\Jobs\ProcessOrderLabelShip::backupShippingLabel($order);

            // Reload lại sau khi backup (có thể đã update shipping_label)
            $order->refresh();

            // Step 2: Post label convert
            \App\Jobs\ProcessOrderLabelShip::postLabelConvert($order);

            Log::info('Shipping label processed for update', [
                'order_id' => $order->id,
                'shipping_label' => $order->shipping_label,
            ]);
        } catch (Exception $e) {
            Log::error('Failed to process shipping label update', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
            // Không throw exception - để update vẫn thành công
        }
    }

    /**
     * Update order items (mockup fields and print_files)
     */
    protected function updateOrderItems(Order $order, array $changedItems): void
    {
        foreach ($changedItems as $itemChange) {
            // Skip new/deleted items (xử lý ở phase sau)
            if (isset($itemChange['_is_new']) || isset($itemChange['_is_deleted'])) {
                Log::info('Skipping new/deleted item in update', [
                    'order_id' => $order->id,
                    'item_id' => $itemChange['item_id'] ?? null,
                    'variant_id' => $itemChange['variant_id'] ?? null,
                    'is_new' => $itemChange['_is_new'] ?? false,
                    'is_deleted' => $itemChange['_is_deleted'] ?? false,
                ]);
                continue;
            }

            // Priority: Use item_id if provided (for exact item identification)
            // Fallback: Use variant_id for legacy support (may cause issues with duplicate variants)
            $itemId = $itemChange['item_id'] ?? null;
            $variantId = $itemChange['variant_id'] ?? null;

            $orderItem = null;

            if ($itemId) {
                // Exact match by item_id - preferred method
                $orderItem = OrderItem::where('order_id', $order->id)
                    ->where('id', $itemId)
                    ->first();
            } elseif ($variantId) {
                // Fallback to variant_id (legacy) - may match first item if duplicates exist
                $orderItem = OrderItem::where('order_id', $order->id)
                    ->where('variant_id', $variantId)
                    ->first();

                Log::warning('updateOrderItems using variant_id fallback - may cause issues with duplicate variants', [
                    'order_id' => $order->id,
                    'variant_id' => $variantId,
                ]);
            }

            if (!$orderItem) {
                Log::warning('Order item not found for update', [
                    'order_id' => $order->id,
                    'item_id' => $itemId,
                    'variant_id' => $variantId,
                ]);
                continue;
            }

            // Update fields (mockup + optional variant swap)
            $updateData = [];

            // Variant swap: replace the item's variant and product name. Derived
            // attributes (style/color/size/sku) come from the productVariant
            // relationship, so no other columns need denormalizing here. Price and
            // the wood merge image are recomputed/regenerated after commit.
            if (isset($itemChange['variant_id_new'])) {
                $updateData['variant_id'] = $itemChange['variant_id_new'];
                if (isset($itemChange['product_name'])) {
                    $updateData['product_name'] = $itemChange['product_name'];
                }
            }

            if (isset($itemChange['mockup'])) {
                $updateData['mockup'] = $itemChange['mockup'];
            }

            if (isset($itemChange['mockup_back'])) {
                $updateData['mockup_back'] = $itemChange['mockup_back'];
            }

            if (!empty($updateData)) {
                $orderItem->update($updateData);
                Log::info('Order item updated', [
                    'order_id' => $order->id,
                    'item_id' => $orderItem->id,
                    'variant_id' => $updateData['variant_id'] ?? $variantId,
                    'updated_fields' => array_keys($updateData),
                ]);
            }

            // Update print_files
            if (isset($itemChange['print_files']) && !empty($itemChange['print_files'])) {
                $this->updateOrderItemPrintFiles($order, $orderItem, $itemChange['print_files']);
            }
        }
    }

    /**
     * Update order item print files (PES/EMB)
     * - Google Drive PES: upload to B2
     * - Other URLs: save directly
     */
    protected function updateOrderItemPrintFiles(Order $order, OrderItem $orderItem, array $printFiles): void
    {
        $pushService = app(\App\Services\PushFileJsonToBackblazeService::class);
        $bucketName = env('B2_BUCKET', 'Lemiex-Fulfillment');

        foreach ($printFiles as $file) {
            $key = $file['key'] ?? null;
            if (!$key) continue;

            $urlPdf = array_key_exists('url', $file) ? $file['url'] : null;
            $urlPes = $file['url_pes'] ?? null;
            $urlEmb = $file['url_emb'] ?? null;

            Log::info('Processing print file update', [
                'order_id' => $order->id,
                'item_id' => $orderItem->id,
                'key' => $key,
                'url_pdf' => $urlPdf,
                'url_pes' => $urlPes,
                'url_emb' => $urlEmb,
            ]);

            // Wood orders: update `{key}_pdf` meta from frontend `url` field.
            if (array_key_exists('url', $file)) {
                $pdfMetaKey = $key . '_pdf';
                $existingPdfMeta = OrderItemMeta::where('order_item_id', $orderItem->id)
                    ->where('meta_key', $pdfMetaKey)
                    ->first();
                $currentPdfUrl = $existingPdfMeta?->meta_value;

                if ($currentPdfUrl && $currentPdfUrl !== $urlPdf && str_contains($currentPdfUrl, 'backblazeb2.com')) {
                    try {
                        if (str_contains($currentPdfUrl, $bucketName . '/')) {
                            $parts = explode($bucketName . '/', $currentPdfUrl, 2);
                            if (isset($parts[1])) {
                                $relativePath = urldecode($parts[1]);
                                \Illuminate\Support\Facades\Storage::disk('b2')->delete($relativePath);
                                Log::info('Deleted old B2 PDF file', [
                                    'path' => $relativePath,
                                    'old_url' => $currentPdfUrl,
                                ]);
                            }
                        }
                    } catch (\Exception $e) {
                        Log::warning('Failed to delete old B2 PDF file', ['error' => $e->getMessage()]);
                    }
                }

                if ($urlPdf) {
                    // Google Drive share links aren't directly downloadable; convert to a
                    // direct-download URL so merge image / factory can fetch the file.
                    $urlPdf = $pushService->toDirectDownloadUrl($urlPdf);

                    OrderItemMeta::updateOrCreate(
                        ['order_item_id' => $orderItem->id, 'meta_key' => $pdfMetaKey],
                        ['meta_value' => $urlPdf]
                    );
                } elseif ($existingPdfMeta) {
                    $existingPdfMeta->delete();
                }

                Log::info('PDF URL saved to order_item_metas', [
                    'order_id' => $order->id,
                    'item_id' => $orderItem->id,
                    'meta_key' => $pdfMetaKey,
                    'url' => $urlPdf,
                ]);
            }

            // Process url_pes
            if ($urlPes) {
                // Get existing meta to check for old file
                $existingPesMeta = OrderItemMeta::where('order_item_id', $orderItem->id)
                    ->where('meta_key', $key)
                    ->first();

                $currentPesUrl = $existingPesMeta?->meta_value;

                // Delete old file if URL changed and is a B2 link
                if ($currentPesUrl && $currentPesUrl !== $urlPes && str_contains($currentPesUrl, 'backblazeb2.com')) {
                    try {
                        // Extract relative path: .../BucketName/path/to/file
                        // We need 'path/to/file'
                        if (str_contains($currentPesUrl, $bucketName . '/')) {
                            $parts = explode($bucketName . '/', $currentPesUrl, 2);
                            if (isset($parts[1])) {
                                $relativePath = urldecode($parts[1]); // Decode in case of spaces
                                \Illuminate\Support\Facades\Storage::disk('b2')->delete($relativePath);
                                Log::info('Deleted old B2 PES file', [
                                    'path' => $relativePath,
                                    'old_url' => $currentPesUrl
                                ]);
                            }
                        }
                    } catch (\Exception $e) {
                        Log::warning('Failed to delete old B2 PES file', ['error' => $e->getMessage()]);
                    }
                }

                $finalPesUrl = $urlPes;

                // Check if Google Drive URL OR needs renaming (direct upload)
                $shouldRename = str_contains($urlPes, 'drive.google.com') ||
                    (str_contains($urlPes, 'backblazeb2.com') && !str_contains($urlPes, "{$order->id}_{$orderItem->id}_{$key}_"));

                if ($shouldRename) {
                    try {
                        // Get variant for filename
                        $variant = ProductVariant::where('variant_id', $orderItem->variant_id)->first();
                        $sStyle = $variant ? preg_replace('/[^a-zA-Z0-9]/', '', $variant->style ?? 'Unknown') : 'Unknown';
                        $sSize = $variant ? preg_replace('/[^a-zA-Z0-9]/', '', $variant->size ?? 'Unknown') : 'Unknown';
                        $sColor = $variant ? preg_replace('/[^a-zA-Z0-9]/', '', $variant->color ?? 'Unknown') : 'Unknown';

                        $fileName = "{$order->id}_{$orderItem->id}_{$key}_{$sStyle}_{$sSize}_{$sColor}.pes";
                        $result = $pushService->pushPesToBlaze(
                            $urlPes,
                            $fileName,
                            env('B2_BUCKET', 'Lemiex-Fulfillment')
                        );
                        $finalPesUrl = $result['fileName'];

                        Log::info('PES file renamed/uploaded to B2', [
                            'order_id' => $order->id,
                            'item_id' => $orderItem->id,
                            'key' => $key,
                            'original_url' => $urlPes,
                            'new_url' => $finalPesUrl,
                            'file_name' => $fileName
                        ]);

                        // Cleanup: If it was a temp B2 file, delete it after rename
                        if (str_contains($urlPes, env('B2_BUCKET', 'Lemiex-Fulfillment')) && $urlPes !== $finalPesUrl) {
                            try {
                                $tempPath = parse_url($urlPes, PHP_URL_PATH);
                                $tempPath = str_replace('/' . env('B2_BUCKET', 'Lemiex-Fulfillment') . '/', '', $tempPath);
                                \Illuminate\Support\Facades\Storage::disk('b2')->delete($tempPath);
                                Log::info('Deleted temp file after rename (update)', ['path' => $tempPath]);
                            } catch (\Exception $ex) {
                                // Ignore cleanup error
                            }
                        }
                    } catch (Exception $e) {
                        Log::error('Failed to upload/rename PES in update', [
                            'order_id' => $order->id,
                            'item_id' => $orderItem->id,
                            'key' => $key,
                            'url' => $urlPes,
                            'error' => $e->getMessage(),
                        ]);
                        // Fallback to original URL
                    }
                }

                // Save/Update PES URL to order_item_metas
                $savedMeta = OrderItemMeta::updateOrCreate(
                    [
                        'order_item_id' => $orderItem->id,
                        'meta_key' => $key,
                    ],
                    [
                        'meta_value' => $finalPesUrl,
                    ]
                );

                // Debug: Verify the saved value
                Log::info('PES URL saved to order_item_metas', [
                    'order_id' => $order->id,
                    'item_id' => $orderItem->id,
                    'meta_id' => $savedMeta->id,
                    'key' => $key,
                    'intended_url' => $finalPesUrl,
                    'actual_saved_url' => $savedMeta->meta_value,
                    'wasRecentlyCreated' => $savedMeta->wasRecentlyCreated,
                ]);
            }

            // Process url_emb
            if ($urlEmb) {
                // Get existing meta to check for old file
                $existingEmbMeta = OrderItemMeta::where('order_item_id', $orderItem->id)
                    ->where('meta_key', $key . '_emb')
                    ->first();

                $currentEmbUrl = $existingEmbMeta?->meta_value;

                // Delete old file if URL changed and is a B2 link
                if ($currentEmbUrl && $currentEmbUrl !== $urlEmb && str_contains($currentEmbUrl, 'backblazeb2.com')) {
                    try {
                        if (str_contains($currentEmbUrl, $bucketName . '/')) {
                            $parts = explode($bucketName . '/', $currentEmbUrl, 2);
                            if (isset($parts[1])) {
                                $relativePath = urldecode($parts[1]);
                                \Illuminate\Support\Facades\Storage::disk('b2')->delete($relativePath);
                                Log::info('Deleted old B2 EMB file', [
                                    'path' => $relativePath,
                                    'old_url' => $currentEmbUrl
                                ]);
                            }
                        }
                    } catch (\Exception $e) {
                        Log::warning('Failed to delete old B2 EMB file', ['error' => $e->getMessage()]);
                    }
                }

                OrderItemMeta::updateOrCreate(
                    [
                        'order_item_id' => $orderItem->id,
                        'meta_key' => $key . '_emb',
                    ],
                    [
                        'meta_value' => $urlEmb,
                    ]
                );
            }
        }
    }

    /**
     * Regenerate merge_image metas for updated Tumbler items.
     */
    protected function regenerateTumblerMergeImages(Order $order, array $changedItems): void
    {
        foreach ($changedItems as $itemChange) {
            if (empty($itemChange['print_files']) && empty($itemChange['_regenerate_merge'])) {
                continue;
            }

            $itemId = $itemChange['item_id'] ?? null;
            if (!$itemId) {
                continue;
            }

            $orderItem = OrderItem::where('order_id', $order->id)
                ->where('id', $itemId)
                ->first();

            if (!$orderItem) {
                Log::warning('Tumbler merge regeneration skipped: order item not found', [
                    'order_id' => $order->id,
                    'item_id' => $itemId,
                ]);
                continue;
            }

            $this->regenerateSingleTumblerMergeImage($order, $orderItem);
        }
    }

    /**
     * Delete old merge_image metas and recreate them from current design + QR metas.
     */
    protected function regenerateSingleTumblerMergeImage(Order $order, OrderItem $item): void
    {
        Log::info('Regenerating Tumbler merge images', [
            'order_id' => $order->id,
            'item_id' => $item->id,
        ]);

        $designMeta = OrderItemMeta::where('order_item_id', $item->id)
            ->whereIn('meta_key', ['front_image', 'wrap_image'])
            ->first();

        if (!$designMeta) {
            $designMeta = OrderItemMeta::where('order_item_id', $item->id)
                ->where('meta_key', 'like', '%_image')
                ->where('meta_key', '!=', 'merge_image')
                ->first();
        }

        if (!$designMeta) {
            Log::warning('No design image found for Tumbler merge regeneration', [
                'order_id' => $order->id,
                'item_id' => $item->id,
            ]);
            return;
        }

        $qrUrls = OrderItemMeta::where('order_item_id', $item->id)
            ->where('meta_key', 'special_design_qr')
            ->pluck('meta_value')
            ->filter()
            ->values()
            ->toArray();

        if (empty($qrUrls)) {
            Log::warning('No QR codes found for Tumbler merge regeneration', [
                'order_id' => $order->id,
                'item_id' => $item->id,
            ]);
            return;
        }

        OrderItemMeta::where('order_item_id', $item->id)
            ->where('meta_key', 'merge_image')
            ->delete();

        try {
            $mergeUrl = env('MERGE_IMAGE_SERVICE_URL', 'https://manage.lemiex.us/pes-api/merge-image');
            $payload = [
                'url_image' => $designMeta->meta_value,
                'url_qr' => $qrUrls,
            ];

            Log::info('Calling Merge Image Service for update', [
                'order_id' => $order->id,
                'item_id' => $item->id,
                'qr_count' => count($qrUrls),
                'url' => $mergeUrl,
            ]);

            $response = Http::timeout(120)
                ->withOptions(['verify' => config('app.http_verify_ssl', true)])
                ->post($mergeUrl, $payload);

            if (!$response->successful()) {
                Log::error('Merge Image Service failed during update', [
                    'order_id' => $order->id,
                    'item_id' => $item->id,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return;
            }

            $mergedUrls = $response->json('urls') ?? [];

            foreach ($mergedUrls as $url) {
                OrderItemMeta::create([
                    'order_item_id' => $item->id,
                    'meta_key' => 'merge_image',
                    'meta_value' => $url,
                    'switch' => 0,
                    'status' => false,
                ]);
            }

            Log::info('Tumbler merge images regenerated successfully', [
                'order_id' => $order->id,
                'item_id' => $item->id,
                'merged_count' => count($mergedUrls),
            ]);
        } catch (Exception $e) {
            Log::error('Exception regenerating Tumbler merge images', [
                'order_id' => $order->id,
                'item_id' => $item->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Check if line_items changes contain print_files changes
     */
    protected function hasPrintFilesChange(array $changedItems): bool
    {
        foreach ($changedItems as $item) {
            if (isset($item['print_files']) && !empty($item['print_files'])) {
                return true;
            }
        }
        return false;
    }

    /**
     * Collect order_item IDs whose print_files actually changed in this update.
     */
    protected function itemIdsWithPrintFilesChange(array $changedItems): array
    {
        $ids = [];
        foreach ($changedItems as $item) {
            if (!empty($item['print_files']) && !empty($item['item_id'])) {
                $ids[] = (int) $item['item_id'];
            }
        }
        return array_values(array_unique($ids));
    }

    /**
     * Check if line_items changes contain a variant swap.
     */
    protected function hasVariantChange(array $changedItems): bool
    {
        foreach ($changedItems as $item) {
            if (isset($item['variant_id_new'])) {
                return true;
            }
        }
        return false;
    }

    /**
     * Collect order_item IDs whose variant was swapped in this update.
     */
    protected function itemIdsWithVariantChange(array $changedItems): array
    {
        $ids = [];
        foreach ($changedItems as $item) {
            if (isset($item['variant_id_new']) && !empty($item['item_id'])) {
                $ids[] = (int) $item['item_id'];
            }
        }
        return array_values(array_unique($ids));
    }

    /**
     * Recompute order pricing from the current DB state (used after a variant
     * swap, where the base cost changes). Reuses the same pricing path as order
     * creation so the result is authoritative for the whole order.
     */
    protected function recalculateOrderPricing(Order $order): void
    {
        try {
            $order->loadMissing('seller.profile');
            $tier = $order->seller?->profile?->private_seller ?? 0;

            $lineItems = $this->reconstructLineItemsForPricing($order);

            $result = app(\App\Services\OrderPricingService::class)
                ->calculateOrderPricingWithDesign($order, $tier, $lineItems);

            if (!($result['success'] ?? false)) {
                Log::warning('Pricing recalculation after variant change failed', [
                    'order_id' => $order->id,
                    'error' => $result['error'] ?? 'unknown',
                ]);
                return;
            }

            $order->refresh();
            Log::info('Pricing recalculated after variant change', [
                'order_id' => $order->id,
                'print_cost' => $order->print_cost,
                'total_cost' => $order->total_cost,
            ]);
        } catch (Exception $e) {
            Log::error('Failed to recalculate pricing after variant change', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
            // Do not throw — the variant change itself already committed.
        }
    }

    /**
     * Rebuild the line_items shape OrderPricingService expects (variant_id +
     * print_files keys) from the order's persisted items and design metas.
     * Design positions are detected from meta keys such as "front_pdf",
     * "back_image", or bare "front"/"back" (embroidery).
     */
    protected function reconstructLineItemsForPricing(Order $order): array
    {
        $items = OrderItem::with('metas')->where('order_id', $order->id)->get();
        $positions = ['front', 'back', 'sleeve_left', 'sleeve_right', 'special_design', 'neck', 'wrap'];
        $suffixes = ['_pdf', '_image', '_emb', '_pes'];

        $lineItems = [];
        foreach ($items as $item) {
            $keys = [];
            foreach ($item->metas as $meta) {
                if (empty($meta->meta_value)) {
                    continue;
                }
                $key = $meta->meta_key;
                foreach ($suffixes as $suffix) {
                    if (str_ends_with($key, $suffix)) {
                        $key = substr($key, 0, -strlen($suffix));
                        break;
                    }
                }
                if (in_array($key, $positions, true)) {
                    $keys[$key] = true;
                }
            }

            $lineItems[] = [
                'variant_id' => $item->variant_id,
                'print_files' => array_map(
                    fn($key) => ['key' => $key],
                    array_keys($keys)
                ),
            ];
        }

        return $lineItems;
    }

    /**
     * Detect whether an order is Tumbler. Wood orders go through the new
     * WoodMergeImageService; Tumbler has its own merge regenerate path.
     */
    protected function isTumblerOrder(Order $order): bool
    {
        $type = $order->order_type ?? null;
        if ($type === 'Tumbler') {
            return true;
        }
        $productType = $order->product_type ?? null;
        return $productType === 'Tumbler';
    }

    /**
     * Check if changed items require merge_image regeneration even without design URL changes.
     */
    protected function needsTumblerMergeRegeneration(array $changedItems): bool
    {
        foreach ($changedItems as $item) {
            if (!empty($item['_regenerate_merge'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Process PES conversion after update
     * - Convert PES to JSON
     * - Convert PES to DST
     * - Calculate extra_fee, refund_fee
     */
    protected function processPesConversion(Order $order): void
    {
        try {
            Log::info('Starting PES conversion for order update', [
                'order_id' => $order->id,
            ]);

            // Get tier from order's seller
            $tier = $order->seller?->profile?->private_seller ?? 0;

            // Use OrderProcessingService
            $processingService = app(\App\Services\OrderProcessingService::class);
            $feeService = app(\App\Services\FeeCalculationService::class);

            $result = $processingService->processConvert($order, $feeService, $tier);

            if ($result > 0) {
                Log::info('PES conversion completed for order update', [
                    'order_id' => $order->id,
                    'extra_fee' => $order->extra_fee,
                    'refund_fee' => $order->refund_fee,
                ]);
            } else {
                Log::info('PES conversion skipped (no PES files)', [
                    'order_id' => $order->id,
                ]);
            }
        } catch (Exception $e) {
            Log::error('Failed to process PES conversion for update', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
            // Không throw exception - để update vẫn thành công
        }
    }
}
