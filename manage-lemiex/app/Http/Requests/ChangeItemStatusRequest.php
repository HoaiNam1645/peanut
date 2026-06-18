<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class ChangeItemStatusRequest extends FormRequest
{
    private const TRACKABLE_POSITIONS = ['front', 'back', 'sleeve_left', 'sleeve_right', 'neck', 'wrap'];
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Allow roles that can interact with workflow
        $user = $this->user();

        if (!$user) {
            return false;
        }

        $roleName = $user->role->name ?? null;
        $allowedRoles = ['Admin', 'HR', 'Staff', 'Support', 'QC', 'Packing', 'Shipout'];
        return in_array($roleName, $allowedRoles);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'item_id' => 'required|integer|exists:order_items,id',
            'meta_key' => 'required|string|in:' . implode(',', self::TRACKABLE_POSITIONS),
            'status' => 'required|boolean',
            // Optional explicit stage. Supervisor roles (staff/admin/support) may target any
            // stage; dedicated stage roles ignore this and use their own (enforced in controller).
            'stage' => 'nullable|string|in:staff,qc,packing,shipout',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'item_id.required' => 'Item ID is required',
            'item_id.integer' => 'Item ID must be an integer',
            'item_id.exists' => 'Order item not found',
            'meta_key.required' => 'Meta key is required',
            'meta_key.string' => 'Meta key must be a string',
            'meta_key.in' => 'Meta key must be one of: front, back, sleeve_left, sleeve_right, neck, wrap',
            'status.required' => 'Status is required',
            'status.boolean' => 'Status must be true or false',
        ];
    }

    protected function prepareForValidation(): void
    {
        $metaKey = $this->input('meta_key');

        if (!is_string($metaKey) || $metaKey === '') {
            return;
        }

        $normalizedMetaKey = $this->normalizeMetaKey($metaKey);
        if ($normalizedMetaKey !== $metaKey) {
            $this->merge(['meta_key' => $normalizedMetaKey]);
        }
    }

    private function normalizeMetaKey(string $metaKey): string
    {
        if (in_array($metaKey, self::TRACKABLE_POSITIONS, true)) {
            return $metaKey;
        }

        if (preg_match('/^(front|back|sleeve_left|sleeve_right|neck|wrap)_(image|pdf|json|dst|emb)$/', $metaKey, $matches)) {
            return $matches[1];
        }

        return $metaKey;
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

    /**
     * Handle a failed authorization attempt.
     */
    protected function failedAuthorization()
    {
        throw new HttpResponseException(
            response()->json([
                'code' => 403,
                'status' => false,
                'message' => 'You are not authorized to perform this action',
                'data' => null
            ], 403)
        );
    }
}
