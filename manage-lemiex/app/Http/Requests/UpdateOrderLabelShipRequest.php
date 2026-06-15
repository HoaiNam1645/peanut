<?php

namespace App\Http\Requests;

use App\Constants\HttpCode;
use App\Enums\OrderType;
use App\Enums\OrderFulfillStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateOrderLabelShipRequest extends FormRequest
{
    protected ?Order $existingOrder = null;
    protected array $changedFields = [];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id' => 'required|integer|exists:orders,id',
            'order_type' => 'required|in:label_ship,seller_ship',
            'ref_id' => 'required|string|max:255',
            'api_key' => 'nullable|string|max:255',
            'order_status' => 'required|not_in:producing,closed',
            'shipping_method' => 'required|in:standard,express,priority',
            // shipping_label: required for label_ship, nullable for seller_ship
            'shipping_label' => 'nullable|url|max:1000',
            'seller_ref' => 'nullable|string|max:255',
            'shipping_service' => 'nullable|in:USPS,FedEx,UPS',
            'note' => 'nullable|string|max:1000',
            // address: required for seller_ship, nullable for label_ship
            'address' => 'nullable|array',
            'address.name' => 'nullable|string|max:255',
            'address.phone' => 'nullable|string|max:50',
            'address.street1' => 'nullable|string|max:500',
            'address.street2' => 'nullable|string|max:500',
            'address.city' => 'nullable|string|max:255',
            'address.state' => 'nullable|string|max:100',
            'address.zip' => 'nullable|string|max:20',
            'address.country' => 'nullable|string|max:10',
            'line_items' => 'required|array|min:1',
            // Order item id (sent by the edit UI) so a changed variant_id still
            // resolves to the correct existing item.
            'line_items.*.id' => 'nullable|integer',
            'line_items.*.variant_id' => 'required|string|max:255',
            'line_items.*.product_name' => 'required|string|max:500',
            'line_items.*.quantity' => 'required|integer|min:1',
            'line_items.*.mockup' => 'nullable|url|max:1000',
            'line_items.*.mockup_back' => 'nullable|url|max:1000',
            'line_items.*.mockup_sleeve_left' => 'nullable|url|max:1000',
            'line_items.*.mockup_sleeve_right' => 'nullable|url|max:1000',
            'line_items.*.print_files' => 'required|array|min:1',
            'line_items.*.print_files.*.key' => 'required|in:front,back,sleeve_left,sleeve_right,special_design,neck,wrap',
            'line_items.*.print_files.*.url' => 'nullable|url|max:1000',
            'line_items.*.print_files.*.url_emb' => 'nullable|url|max:1000',
            'line_items.*.print_files.*.url_pes' => 'nullable|url|max:1000',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Find order by id
            $this->existingOrder = Order::find($this->input('id'));

            if (!$this->existingOrder) {
                $validator->errors()->add('id', 'Order not found');
                return;
            }

            // Verify ref_id matches
            if ($this->existingOrder->ref_id !== $this->input('ref_id')) {
                $validator->errors()->add('ref_id', 'ref_id does not match order');
                return;
            }

            // Validate order_type specific requirements
            $orderType = $this->input('order_type');

            // label_ship requires shipping_label
            if ($orderType === 'label_ship' && empty($this->input('shipping_label'))) {
                $validator->errors()->add('shipping_label', 'shipping_label is required for label_ship orders');
                return;
            }

            // Role-based edit permission
            $user = auth('jwt')->user();
            $userRoleId = $user->role_id ?? null;

            // Admin and Staff can edit ALL statuses
            // Seller can only edit orders with status: new_order or on_hold
            if ($userRoleId === UserRole::SELLER) {
                $sellerEditableStatuses = [
                    OrderFulfillStatus::NEW_ORDER,
                    OrderFulfillStatus::ON_HOLD,
                ];

                if (!in_array($this->existingOrder->fulfill_status, $sellerEditableStatuses)) {
                    $validator->errors()->add('order', 'Seller can only edit orders with status: new_order or on_hold. Current status: ' . $this->existingOrder->fulfill_status);
                    return;
                }
            }

            // Filter changed fields only
            $this->filterChangedFields();

            // Variant changes have extra constraints (status gate + variant must exist)
            $this->validateVariantChanges($validator);
        });
    }

    /**
     * Validate variant changes detected in the line items.
     * - Variant can only be changed before the order enters production
     *   (new_order / on_hold), to avoid stock/production side effects.
     * - The new variant must exist and be active.
     */
    protected function validateVariantChanges($validator): void
    {
        $variantChanges = array_filter(
            $this->changedFields['line_items'] ?? [],
            fn($item) => isset($item['variant_id_new'])
        );

        if (empty($variantChanges)) {
            return;
        }

        $allowedStatuses = [OrderFulfillStatus::NEW_ORDER, OrderFulfillStatus::ON_HOLD];
        if (!in_array($this->existingOrder->fulfill_status, $allowedStatuses, true)) {
            $validator->errors()->add(
                'line_items',
                'Variant can only be changed while the order is in new_order or on_hold. Current status: ' . $this->existingOrder->fulfill_status
            );
            return;
        }

        // A paid/refunded order is financially settled (the seller's wallet was
        // charged for the old variant's cost). Changing the variant would
        // recompute the cost and break that settlement, so block it.
        $settledPayments = [
            OrderPaymentStatus::PAID,
            OrderPaymentStatus::FULL_REFUND,
            OrderPaymentStatus::PARTIAL_REFUND,
        ];
        if (in_array($this->existingOrder->payment_status, $settledPayments, true)) {
            $validator->errors()->add(
                'line_items',
                'Variant cannot be changed: the order is already paid (payment_status: ' . $this->existingOrder->payment_status . ').'
            );
            return;
        }

        foreach ($variantChanges as $item) {
            $newVariantId = $item['variant_id_new'];
            $variant = ProductVariant::where('variant_id', $newVariantId)->first();

            if (!$variant) {
                $validator->errors()->add('line_items', "Variant {$newVariantId} not found.");
                continue;
            }

            if (!$variant->active) {
                $validator->errors()->add('line_items', "Variant {$newVariantId} is not active.");
            }
        }
    }

    /**
     * Compare and keep only changed fields
     * Note: id, order_type, ref_id, api_key, seller_ref, order_status are NOT updatable
     */
    protected function filterChangedFields(): void
    {
        $order = $this->existingOrder;
        $orderType = $this->input('order_type');

        // Shipping label - for both label_ship and seller_ship
        if ($this->isLabelChanged()) {
            $this->changedFields['shipping_label'] = $this->input('shipping_label');
        }

        if ($this->input('shipping_method') !== $order->shipping_method) {
            $this->changedFields['shipping_method'] = $this->input('shipping_method');
        }

        if ($this->input('shipping_service') !== $order->shipping_service) {
            $this->changedFields['shipping_service'] = $this->input('shipping_service');
        }

        if ($this->input('note') !== $order->note) {
            $this->changedFields['note'] = $this->input('note');
        }

        // Address - for both label_ship and seller_ship
        $addressChanges = $this->compareAddress();
        if (!empty($addressChanges)) {
            $this->changedFields['address'] = $addressChanges;
        }

        // Line items - compare field by field
        $lineItemChanges = $this->compareLineItems();
        if (!empty($lineItemChanges)) {
            $this->changedFields['line_items'] = $lineItemChanges;
        }
    }

    /**
     * Compare address fields for both order types
     */
    protected function compareAddress(): array
    {
        $order = $this->existingOrder;
        $inputAddress = $this->input('address', []);
        $changedAddress = [];

        // Map input address fields to DB fields
        $addressMapping = [
            'name' => ['first_name', 'last_name'], // Special: name splits into first_name + last_name
            'phone' => 'phone',
            'street1' => 'address_1',
            'street2' => 'address_2',
            'city' => 'city',
            'state' => 'state',
            'zip' => 'postcode',
            'country' => 'country',
        ];

        // Compare name (special handling)
        if (isset($inputAddress['name'])) {
            $inputName = $inputAddress['name'];
            $dbName = trim(($order->first_name ?? '') . ' ' . ($order->last_name ?? ''));
            if ($inputName !== $dbName) {
                $changedAddress['name'] = $inputName;
            }
        }

        // Compare other fields
        foreach ($addressMapping as $inputKey => $dbKey) {
            if ($inputKey === 'name') continue; // Already handled

            $inputValue = $inputAddress[$inputKey] ?? null;
            $dbValue = $order->{$dbKey} ?? null;

            if ($inputValue !== $dbValue) {
                $changedAddress[$inputKey] = $inputValue;
            }
        }

        return $changedAddress;
    }

    /**
     * Check if shipping label has changed (only for label_ship)
     * Handles 3 cases:
     * 1. Non-TikTok link (pdf) - stored as-is, compare directly
     * 2. TikTok link - converted to B2 jpg, compare with expected B2 URL
     * 3. Non-TikTok after convert - stored as B2 jpg
     */
    protected function isLabelChanged(): bool
    {
        $inputLabel = $this->input('shipping_label');
        $dbLabel = $this->existingOrder->shipping_label;

        // seller_ship doesn't have shipping_label
        if (empty($inputLabel)) {
            return false;
        }

        // Case 1: Giống nhau hoàn toàn (non-TikTok pdf)
        if ($inputLabel === $dbLabel) {
            return false;
        }

        // Case 2: Input là TikTok → chuyển thành expected B2 URL rồi so sánh
        if (str_contains($inputLabel ?? '', 'tiktokshops')) {
            $orderId = $this->existingOrder->id;
            $expectedB2Url = env('B2_URL', 'https://s3.us-east-005.backblazeb2.com') . "/label/original_{$orderId}_label.jpg";
            return $expectedB2Url !== $dbLabel;
        }

        // Case 3: Non-TikTok khác → so sánh trực tiếp
        return $inputLabel !== $dbLabel;
    }

    /**
     * Check if this is a seller_ship order (convert_label is null in DB)
     */
    public function isSellerShip(): bool
    {
        return $this->existingOrder && empty($this->existingOrder->convert_label);
    }

    /**
     * Compare line items field by field
     * Returns changed items in same format as body input
     */
    protected function compareLineItems(): array
    {
        // Get existing items preserving order (do NOT use keyBy as it overwrites duplicates)
        $existingItems = OrderItem::with('metas')
            ->where('order_id', $this->existingOrder->id)
            ->orderBy('id') // Preserve original order
            ->get();

        $inputItems = collect($this->input('line_items', []));
        $changedItems = [];

        $printFileKeys = ['front', 'back', 'sleeve_left', 'sleeve_right', 'special', 'special_design', 'neck', 'wrap'];

        // Match items by index (position in the list)
        // This assumes input items are in the same order as existing items
        foreach ($inputItems as $index => $inputItem) {
            $variantId = $inputItem['variant_id'] ?? '';
            $inputItemId = isset($inputItem['id']) ? (int) $inputItem['id'] : null;

            $existingItem = null;

            // Preferred: match by the explicit order item id sent from the edit UI.
            // Required so a CHANGED variant_id still resolves to the right item.
            if ($inputItemId) {
                $existingItem = $existingItems->first(
                    fn($item) => (int) $item->id === $inputItemId && !isset($item->_processed)
                );
            }

            // Fallback (legacy): match by index + variant_id, then by the first
            // unprocessed item with the same variant_id.
            if (!$existingItem) {
                if (isset($existingItems[$index])
                    && $existingItems[$index]->variant_id === $variantId
                    && !isset($existingItems[$index]->_processed)
                ) {
                    $existingItem = $existingItems[$index];
                } else {
                    foreach ($existingItems as $item) {
                        if ($item->variant_id === $variantId && !isset($item->_processed)) {
                            $existingItem = $item;
                            break;
                        }
                    }
                }
            }

            // New item - include all data
            if (!$existingItem) {
                $inputItem['_is_new'] = true;
                $changedItems[] = $inputItem;
                continue;
            }

            // Mark as processed to avoid matching same item twice
            $existingItem->_processed = true;

            // IMPORTANT: Include item_id for exact identification in backend.
            // Keep the existing (old) variant_id here for lookup; the new variant
            // (if any) is carried separately in 'variant_id_new'.
            $itemChanges = [
                'item_id' => $existingItem->id,
                'variant_id' => $existingItem->variant_id,
            ];
            $hasChanges = false;

            // Variant change: swap the variant of an existing item (e.g. fixing a
            // mis-selected variant). product_name follows the new variant.
            // quantity is still NOT updatable.
            if ($variantId !== '' && $variantId !== $existingItem->variant_id) {
                $itemChanges['variant_id_new'] = $variantId;
                $itemChanges['product_name'] = $inputItem['product_name'] ?? $existingItem->product_name;
                $hasChanges = true;
            }

            // Compare mockup fields and print_files

            if (($inputItem['mockup'] ?? null) !== $existingItem->mockup) {
                $itemChanges['mockup'] = $inputItem['mockup'] ?? null;
                $hasChanges = true;
            }

            if (($inputItem['mockup_back'] ?? null) !== $existingItem->mockup_back) {
                $itemChanges['mockup_back'] = $inputItem['mockup_back'] ?? null;
                $hasChanges = true;
            }

            // Compare print_files
            $existingMetas = $existingItem->metas->keyBy('meta_key');
            $inputPrintFiles = collect($inputItem['print_files'] ?? [])->keyBy('key');
            $changedPrintFiles = [];

            foreach ($printFileKeys as $key) {
                $inputFile = $inputPrintFiles->get($key);
                if (!$inputFile) continue;

                $isPrintOrder = $this->existingOrder?->order_type === OrderType::TUMBLER;
                $existingImage = $existingMetas->get($key . '_image')?->meta_value;
                $existingPdf = $existingMetas->get($key . '_pdf')?->meta_value;
                $existingPes = $existingMetas->get($key)?->meta_value;
                $existingEmb = $existingMetas->get($key . '_emb')?->meta_value;

                $inputImage = $inputFile['url'] ?? null;
                $inputPes = $inputFile['url_pes'] ?? null;
                $inputEmb = $inputFile['url_emb'] ?? null;

                $fileChanges = ['key' => $key];
                $fileHasChanges = false;

                if ($isPrintOrder) {
                    if ($inputImage !== $existingImage) {
                        $fileChanges['url'] = $inputImage;
                        $fileHasChanges = true;
                    }
                } else {
                    // Wood order: `url` from frontend maps to `{key}_pdf` meta
                    if ($inputImage !== $existingPdf) {
                        $fileChanges['url'] = $inputImage;
                        $fileHasChanges = true;
                    }

                    if ($inputPes !== $existingPes) {
                        $fileChanges['url_pes'] = $inputPes;
                        $fileHasChanges = true;
                    }

                    if ($inputEmb !== $existingEmb) {
                        $fileChanges['url_emb'] = $inputEmb;
                        $fileHasChanges = true;
                    }
                }

                if ($fileHasChanges) {
                    $changedPrintFiles[] = $fileChanges;
                    $hasChanges = true;
                }
            }

            if (!empty($changedPrintFiles)) {
                $itemChanges['print_files'] = $changedPrintFiles;
            }

            if (($this->existingOrder?->order_type === OrderType::TUMBLER) && !$hasChanges) {
                $hasDesignImage = $existingMetas->has('front_image') || $existingMetas->has('wrap_image');
                $hasQr = $existingMetas->has('special_design_qr');
                $hasMergeImage = $existingMetas->has('merge_image');

                if ($hasDesignImage && $hasQr && !$hasMergeImage) {
                    $itemChanges['_regenerate_merge'] = true;
                    $hasChanges = true;
                }
            }

            if ($hasChanges) {
                $changedItems[] = $itemChanges;
            }
        }

        // Check for deleted items
        foreach ($existingItems as $existingItem) {
            if (!isset($existingItem->_processed)) {
                $changedItems[] = [
                    'item_id' => $existingItem->id,
                    'variant_id' => $existingItem->variant_id,
                    '_is_deleted' => true,
                ];
            }
        }

        return $changedItems;
    }

    // Getter 
    public function getExistingOrder(): ?Order
    {
        return $this->existingOrder;
    }

    public function getChangedFields(): array
    {
        return $this->changedFields;
    }

    public function hasChanges(): bool
    {
        return !empty($this->changedFields);
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'code' => HttpCode::VALIDATION_ERROR,
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], HttpCode::VALIDATION_ERROR)
        );
    }
}
