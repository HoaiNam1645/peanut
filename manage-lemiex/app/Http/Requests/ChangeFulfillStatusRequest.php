<?php

namespace App\Http\Requests;

use App\Enums\OrderFulfillStatus;
use App\Enums\UserRole;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class ChangeFulfillStatusRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * Note: Sellers can only change from new_order to on_hold/cancelled,
     * which is enforced at the service level in OrderFulfillStatusService.
     */
    public function authorize(): bool
    {
        $user = auth('jwt')->user();

        return $user &&
            $user->role &&
            in_array($user->role->id, [UserRole::SELLER, UserRole::STAFF, UserRole::ADMIN, UserRole::HR, UserRole::DESIGNER]);
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation()
    {
        // Convert fulfill_status to lowercase for consistency
        if ($this->has('fulfill_status')) {
            $this->merge([
                'fulfill_status' => strtolower($this->fulfill_status)
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'order_id' => 'required|integer|exists:orders,id',
            'fulfill_status' => [
                'required',
                'string',
                Rule::in(OrderFulfillStatus::all())
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'order_id.required' => 'Order ID is required',
            'order_id.integer' => 'Order ID must be an integer',
            'order_id.exists' => 'Order not found',
            'fulfill_status.required' => 'Fulfill status is required',
            'fulfill_status.string' => 'Fulfill status must be a string',
            'fulfill_status.in' => 'Invalid fulfill status value',
        ];
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'code' => 422,
                'status' => false,
                'message' => 'Validation failed',
                'data' => [
                    'errors' => $validator->errors()
                ]
            ], 422)
        );
    }
}
