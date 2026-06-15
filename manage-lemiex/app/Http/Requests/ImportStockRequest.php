<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;

class ImportStockRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'file' => 'required|file|mimes:csv,txt',
            'stock_type' => 'nullable|string|in:add_stock,subtract_stock,set'
        ];
    }

    public function messages()
    {
        return [
            'file.required' => 'File is required.',
            'file.mimes' => 'File must be CSV or TXT format.',
            'stock_type.in' => 'Stock type must be: add_stock, subtract_stock, or set.'
        ];
    }

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

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'code' => 422,
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422)
        );
    }
}
