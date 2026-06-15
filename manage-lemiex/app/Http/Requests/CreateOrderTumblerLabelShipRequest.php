<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class CreateOrderTumblerLabelShipRequest extends FormRequest
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
            'ref_id' => 'required|string|max:255',
            'api_key' => 'required|string|max:255',
            'order_status' => 'required|in:new_order,test_order,priority',
            'shipping_method' => 'required|in:standard,express,priority',
            'fulfillment_priority' => 'nullable|in:' . $this->getValidFulfillmentPriorities(),
            'shipping_label' => 'required|url|max:1000',
            'line_items' => 'required|array|min:1',

            // Product type - defaults to Tumbler if not provided
            'product_type' => 'nullable|in:Tumbler',

            // Optional fields
            'seller_ref' => 'nullable|string|max:255',
            'shipping_service' => 'nullable|in:USPS,FedEx,UPS',
            'note' => 'nullable|string|max:1000',

            // Line items validation
            'line_items.*.variant_id' => 'required|string|max:255|exists:product_variants,variant_id',
            'line_items.*.product_name' => 'required|string|max:500',
            'line_items.*.quantity' => 'required|integer|min:1',
            'line_items.*.mockup' => 'nullable|url|max:1000',
            'line_items.*.print_files' => 'required|array|min:1',

            // Print files validation for Tumbler (only image, no PES/EMB)
            'line_items.*.print_files.*.key' => 'required|in:front',
            'line_items.*.print_files.*.url' => 'required|url|max:1000',
            // No url_emb, url_pes, embroidery_type for Tumbler
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
            'api_key.required' => 'API key is required',
            'order_status.required' => 'Order status is required',
            'order_status.in' => 'Order status must be: new_order, test_order, or priority',
            'shipping_method.required' => 'Shipping method is required',
            'shipping_label.required' => 'Shipping label is required for label_ship orders',
            'shipping_label.url' => 'Shipping label must be a valid URL',
            'product_type.required' => 'Product type is required',
            'product_type.in' => 'Product type must be Tumbler for this endpoint',
            'line_items.required' => 'At least one line item is required',
            'line_items.min' => 'At least one line item is required',
            'line_items.*.variant_id.required' => 'Product variant ID is required',
            'line_items.*.variant_id.exists' => 'Product variant ID does not exist',
            'line_items.*.product_name.required' => 'Product name is required',
            'line_items.*.quantity.required' => 'Quantity is required',
            'line_items.*.quantity.min' => 'Quantity must be at least 1',
            'line_items.*.print_files.required' => 'Print files are required',
            'line_items.*.print_files.min' => 'At least one print file is required',
            'line_items.*.print_files.*.key.required' => 'Print file key is required',
            'line_items.*.print_files.*.key.in' => 'Print file key must be: wrap or front',
            'line_items.*.print_files.*.url.required' => 'Design image URL is required',
            'line_items.*.print_files.*.url.url' => 'Design image must be a valid URL',
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
                if (empty($item['mockup'])) {
                    $validator->errors()->add(
                        "line_items.{$index}.mockup",
                        'Mockup image is required for Tumbler orders'
                    );
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
        $priorities = \App\Models\FulfillmentPriority::select('name')
            ->distinct()
            ->pluck('name')
            ->toArray();

        if (empty($priorities)) {
            $priorities = ['normal', 'priority'];
        }

        return implode(',', $priorities);
    }
}
