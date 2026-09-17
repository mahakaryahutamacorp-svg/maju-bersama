<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
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
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'sku' => ['nullable', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:50'],
            'purchase_price' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'selling_price' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
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
            'category_id.required' => 'Category is required.',
            'category_id.exists' => 'Selected category does not exist.',
            'name.required' => 'Product name is required.',
            'purchase_price.required' => 'Purchase price is required.',
            'selling_price.required' => 'Selling price is required.',
            'stock.min' => 'Stock cannot be negative.',
        ];
    }
}
