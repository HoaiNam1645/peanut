<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class CreateOrderNoDesignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ref_id' => 'required|string|max:255|unique:orders,ref_id',
            'api_key' => 'required|string|exists:stores,api_key',
            'seller_ref' => 'nullable|string|max:255',
            'order_status' => 'required|in:new_order,test_order,priority',
            'shipping_method' => 'required|in:standard,express,priority',
            'shipping_service' => 'nullable|in:USPS,FedEx,UPS',
            'note' => 'nullable|string',
            'product_type' => 'nullable|string|max:100',

            // Address validation
            'address' => 'required|array',
            'address.name' => 'required|string|max:255',
            'address.phone' => 'nullable|string|max:20',
            'address.street1' => 'required|string|max:255',
            'address.street2' => 'nullable|string|max:255',
            'address.city' => 'required|string|max:100',
            'address.state' => 'required|string|max:50',
            'address.zip' => 'required|string|max:20',
            'address.country' => 'required|string|size:2',

            'line_items' => 'required|array|min:1',
            'line_items.*.variant_id' => 'required|string|exists:product_variants,variant_id',
            'line_items.*.product_name' => 'required|string',
            'line_items.*.quantity' => 'required|integer|min:1',
            'line_items.*.mockup' => 'nullable|url',
            'line_items.*.mockup_back' => 'nullable|url',
            'line_items.*.mockup_sleeve_left' => 'nullable|url',
            'line_items.*.mockup_sleeve_right' => 'nullable|url',
            'line_items.*.print_files' => 'nullable|array',
        ];
    }

    public function messages(): array
    {
        return [
            'ref_id.required' => 'Order reference ID is required',
            'ref_id.unique' => 'Order reference ID already exists',
            'api_key.required' => 'API key is required',
            'api_key.exists' => 'Invalid API key',
            'order_status.required' => 'Order status is required',
            'order_status.in' => 'Order status must be: new_order, test_order, or priority',
            'shipping_method.required' => 'Shipping method is required',
            'address.required' => 'Shipping address is required',
            'address.name.required' => 'Recipient name is required',
            'address.street1.required' => 'Street address is required',
            'address.city.required' => 'City is required',
            'address.state.required' => 'State is required',
            'address.zip.required' => 'ZIP code is required',
            'address.country.required' => 'Country is required',
            'address.country.size' => 'Country must be 2-letter code (e.g., US, CA)',
            'line_items.required' => 'At least one product is required',
            'line_items.*.variant_id.required' => 'Product variant ID is required',
            'line_items.*.variant_id.exists' => 'Product variant ID does not exist',
            'line_items.*.quantity.min' => 'Quantity must be at least 1',
            'line_items.*.print_files.required' => 'Print files field is required (can be empty array)',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            // Validate each item has at least one mockup
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

                // Validate print_files is empty array for NO DESIGN
                $printFiles = $item['print_files'] ?? [];
                if (!empty($printFiles)) {
                    $validator->errors()->add(
                        "line_items.{$index}.print_files",
                        'Print files must be empty array for NO DESIGN orders'
                    );
                }
            }

            // Validate address country code
            $address = $this->input('address', []);
            if (isset($address['country'])) {
                $validCountries = ['US', 'CA', 'GB', 'AU', 'DE', 'FR', 'JP', 'VN']; // Add more as needed
                if (!in_array(strtoupper($address['country']), $validCountries)) {
                    $validator->errors()->add('address.country', 'Invalid country code');
                }
            }
        });
    }

    protected function isInternalUrl(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) {
            return true;
        }

        // Check for localhost
        if (in_array($host, ['localhost', '127.0.0.1', '::1'])) {
            return true;
        }

        // Only check IP addresses for private ranges, not domain names
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            // It's an IP address, check if it's private/reserved
            if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return true;
            }
        }

        return false;
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
}
