<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
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
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'category_id' => ['sometimes', 'integer', 'exists:categories,id'],
            'sku' => ['nullable', 'string', 'max:50'],
            'name' => ['sometimes', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:50'],
            'purchase_price' => ['sometimes', 'numeric', 'min:0', 'decimal:0,2'],
            'selling_price' => ['sometimes', 'numeric', 'min:0', 'decimal:0,2'],
            'stock' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'category_id.exists' => 'Selected category does not exist.',
            'stock.min' => 'Stock cannot be negative.',
        ];
    }
}
