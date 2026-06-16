<?php

namespace App\Http\Requests;

use App\Models\EmbroideryFee;
use App\Models\FulfillmentPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class CreateOrderLabelShipRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            // Required fields
            'order_type' => 'required|in:label_ship',
            'ref_id' => 'required|string|max:255',
            'api_key' => 'required|string|max:255',
            'order_status' => 'required|in:new_order,test_order,priority',
            'shipping_method' => 'required|in:standard,express,priority',
            'fulfillment_priority' => 'nullable|in:' . $this->getValidFulfillmentPriorities(),
            'shipping_label' => 'required|url|max:1000',
            'line_items' => 'required|array|min:1',

            // Optional fields
            'seller_ref' => 'nullable|string|max:255',
            'shipping_service' => 'nullable|in:USPS,FedEx,UPS',
            'note' => 'nullable|string|max:1000',
            'product_type' => 'nullable|string|max:100',

            // Shipping address (optional) — required by ShipDVX for customs even on label-ship
            'address' => 'nullable|array',
            'address.name' => 'nullable|string|max:255',
            'address.phone' => 'nullable|string|max:50',
            'address.street1' => 'nullable|string|max:255',
            'address.street2' => 'nullable|string|max:255',
            'address.city' => 'nullable|string|max:100',
            'address.state' => 'nullable|string|max:100',
            'address.zip' => 'nullable|string|max:20',
            'address.country' => 'nullable|string|max:10',

            // Line items validation
            'line_items.*.variant_id' => 'required|string|max:255|exists:product_variants,variant_id',
            'line_items.*.product_name' => 'required|string|max:500',
            'line_items.*.quantity' => 'required|integer|min:1',
            'line_items.*.mockup' => 'nullable|url|max:1000',
            'line_items.*.mockup_back' => 'nullable|url|max:1000',
            'line_items.*.mockup_sleeve_left' => 'nullable|url|max:1000',
            'line_items.*.mockup_sleeve_right' => 'nullable|url|max:1000',
            'line_items.*.print_files' => 'required|array|min:1',

            // Print files validation
            'line_items.*.print_files.*.key' => 'required|in:front,back,sleeve_left,sleeve_right,special_design',
            'line_items.*.print_files.*.url' => 'nullable|url|max:1000',
            'line_items.*.print_files.*.url_emb' => 'nullable|url|max:1000',
            'line_items.*.print_files.*.url_pes' => 'nullable|url|max:1000',
            'line_items.*.print_files.*.embroidery_type' => 'nullable|string|max:100',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'order_type.required' => 'Order type is required',
            'order_type.in' => 'Order type must be label_ship',
            'ref_id.required' => 'Order reference ID is required',
            'ref_id.unique' => 'Order reference ID already exists',
            'api_key.required' => 'API key is required',
            'order_status.required' => 'Order status is required',
            'order_status.in' => 'Order status must be: new_order, test_order, or priority',
            'shipping_method.required' => 'Shipping method is required',
            'shipping_label.required' => 'Shipping label is required for label_ship orders',
            'shipping_label.url' => 'Shipping label must be a valid URL',
            'line_items.required' => 'At least one line item is required',
            'line_items.min' => 'At least one line item is required',
            'line_items.*.variant_id.required' => 'Product variant ID is required',
            'line_items.*.variant_id.exists' => 'Product variant ID does not exist',
            'line_items.*.product_name.required' => 'Product name is required',
            'line_items.*.quantity.required' => 'Quantity is required',
            'line_items.*.quantity.min' => 'Quantity must be at least 1',
            'line_items.*.print_files.required' => 'Print files are required for label_ship orders',
            'line_items.*.print_files.min' => 'At least one print file is required',
            'line_items.*.print_files.*.key.required' => 'Print file key is required',
            'line_items.*.print_files.*.key.in' => 'Print file key must be: front, back, sleeve_left, sleeve_right, or special_design',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validate each line item has at least one mockup
            $lineItems = $this->input('line_items', []);

            foreach ($lineItems as $index => $item) {
                $hasMockup = !empty($item['mockup']) ||
                    !empty($item['mockup_back']) ||
                    !empty($item['mockup_sleeve_left']) ||
                    !empty($item['mockup_sleeve_right']);

                if (!$hasMockup) {
                    $validator->errors()->add(
                        "line_items.{$index}.mockup",
                        'At least one mockup is required (mockup, mockup_back, mockup_sleeve_left, or mockup_sleeve_right)'
                    );
                }

                // Validate each print_file has at least one URL
                $printFiles = $item['print_files'] ?? [];
                foreach ($printFiles as $fileIndex => $file) {
                    $hasUrl = !empty($file['url']) ||
                        !empty($file['url_emb']) ||
                        !empty($file['url_pes']);

                    if (!$hasUrl) {
                        $validator->errors()->add(
                            "line_items.{$index}.print_files.{$fileIndex}",
                            'Each print file must have at least one URL (url, url_emb, or url_pes)'
                        );
                    }
                }
            }
        });
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator): void
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

        // Fallback to defaults if empty
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
