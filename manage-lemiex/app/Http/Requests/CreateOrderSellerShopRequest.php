<?php

namespace App\Http\Requests;

use App\Models\EmbroideryFee;
use App\Models\FulfillmentPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class CreateOrderSellerShopRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Basic order info
            'api_key' => 'required|string',
            'ref_id' => 'required|string|max:255',
            'seller_ref' => 'nullable|string|max:255',
            'order_status' => 'required|in:new_order,test_order,priority',
            'shipping_method' => 'required|in:standard,express,priority',
            'fulfillment_priority' => 'nullable|in:' . $this->getValidFulfillmentPriorities(),
            'shipping_service' => 'nullable|string|in:USPS,FedEx,UPS',
            'note' => 'nullable|string',
            'product_type' => 'nullable|string|max:100',

            // Address (REQUIRED for SELLER SHOP)
            'address' => 'required|array',
            'address.name' => 'required|string|max:255',
            'address.phone' => 'nullable|string|max:50',
            'address.street1' => 'required|string|max:255',
            'address.street2' => 'nullable|string|max:255',
            'address.city' => 'required|string|max:100',
            'address.state' => 'required|string|max:100',
            'address.zip' => 'required|string|max:20',
            'address.country' => 'required|string|size:2',

            // Line items with design files
            'line_items' => 'required|array|min:1',
            'line_items.*.variant_id' => 'required|string|exists:product_variants,variant_id',
            'line_items.*.product_name' => 'required|string',
            'line_items.*.quantity' => 'required|integer|min:1',
            'line_items.*.mockup' => 'nullable|url',
            'line_items.*.mockup_back' => 'nullable|url',

            // Print files (design files)
            'line_items.*.print_files' => 'required|array|min:1',
            'line_items.*.print_files.*.key' => 'required|string|in:front,back,sleeve_left,sleeve_right,neck',
            'line_items.*.print_files.*.url' => 'nullable|url',
            'line_items.*.print_files.*.url_emb' => 'nullable|url',
            'line_items.*.print_files.*.url_pes' => 'nullable|url',
            'line_items.*.print_files.*.embroidery_type' => 'nullable|string|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'api_key.required' => 'API key is required',
            'ref_id.required' => 'Reference ID is required',
            'order_status.required' => 'Order status is required',
            'order_status.in' => 'Order status must be: new_order, test_order, or priority',
            'shipping_method.required' => 'Shipping method is required',
            'shipping_method.in' => 'Shipping method must be: standard, express, or priority',

            // Address messages
            'address.required' => 'Shipping address is required for SELLER SHOP orders',
            'address.name.required' => 'Recipient name is required',
            'address.street1.required' => 'Street address is required',
            'address.city.required' => 'City is required',
            'address.state.required' => 'State is required',
            'address.zip.required' => 'ZIP code is required',
            'address.country.required' => 'Country code is required',
            'address.country.size' => 'Country code must be 2 characters (e.g., US, VN)',

            // Line items messages
            'line_items.required' => 'At least one line item is required',
            'line_items.*.variant_id.required' => 'Product variant ID is required',
            'line_items.*.variant_id.exists' => 'Product variant ID does not exist',
            'line_items.*.product_name.required' => 'Product name is required',
            'line_items.*.quantity.required' => 'Quantity is required',
            'line_items.*.quantity.min' => 'Quantity must be at least 1',
            'line_items.*.print_files.required' => 'Design files are required for SELLER SHOP orders',
            'line_items.*.print_files.*.key.in' => 'Print file key must be: front, back, sleeve_left, sleeve_right, or neck',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'code' => 400,
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400)
        );
    }

    /**
     * Get valid fulfillment priorities from database
     */
    private function getValidFulfillmentPriorities(): string
    {
        $priorities = FulfillmentPriority::select('name')
            ->distinct()
            ->pluck('name')
            ->toArray();

        if (empty($priorities)) {
            $priorities = ['normal', 'priority'];
        }

        return implode(',', $priorities);
    }

    /**
     * Get valid embroidery types from database
     * Note: Validation is now free string, this is for reference only
     */
    private function getValidEmbroideryTypes(): string
    {
        $types = EmbroideryFee::select('embroidery_type')
            ->distinct()
            ->pluck('embroidery_type')
            ->toArray();

        // Always include 'standard' as default
        if (!in_array('standard', $types)) {
            array_unshift($types, 'standard');
        }

        return implode(',', $types);
    }
}
