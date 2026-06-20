<?php

namespace App\Http\Requests;

use App\Enums\OrderFulfillStatus;
use App\Enums\UserRole;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class BatchChangeFulfillStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = auth('jwt')->user();

        return $user &&
            $user->role &&
            in_array($user->role->id, [UserRole::SELLER, UserRole::STAFF, UserRole::ADMIN, UserRole::HR, UserRole::DESIGNER]);
    }

    protected function prepareForValidation()
    {
        if ($this->has('fulfill_status')) {
            $this->merge([
                'fulfill_status' => strtolower($this->fulfill_status)
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'order_ids' => 'required|array|min:1|max:200',
            'order_ids.*' => 'integer|exists:orders,id',
            'fulfill_status' => [
                'required',
                'string',
                Rule::in(OrderFulfillStatus::all()),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'order_ids.required' => 'order_ids is required',
            'order_ids.array' => 'order_ids must be an array',
            'order_ids.min' => 'order_ids must contain at least 1 id',
            'order_ids.max' => 'order_ids supports at most 200 ids per request',
            'order_ids.*.integer' => 'Each order id must be an integer',
            'order_ids.*.exists' => 'One or more order ids do not exist',
            'fulfill_status.required' => 'fulfill_status is required',
            'fulfill_status.in' => 'Invalid fulfill_status value',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'code' => 422,
                'status' => false,
                'message' => 'Validation failed',
                'data' => ['errors' => $validator->errors()],
            ], 422)
        );
    }
}
