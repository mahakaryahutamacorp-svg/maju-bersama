<?php

namespace App\Http\Requests;

use App\Models\Product;
use App\Services\MasterCatalogService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->can('manage-catalog');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'category_id' => ['sometimes', 'integer', 'exists:categories,id'],
            'sku' => ['nullable', 'string', 'max:50'],
            'name' => ['sometimes', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:50'],
            'purchase_price' => ['sometimes', 'numeric', 'min:0', 'decimal:0,2'],
            'selling_price' => ['sometimes', 'numeric', 'min:0', 'decimal:0,2'],
            'stock' => ['sometimes', 'integer', 'min:0'],
        ];

        $product = $this->route('product');
        $canManageMasterFields = $this->user() !== null
            && $product instanceof Product
            && app(MasterCatalogService::class)->canManageMasterFieldsIn($this->user(), (int) $product->branch_id);

        if (! $canManageMasterFields) {
            foreach (MasterCatalogService::MASTER_FIELDS as $field) {
                $rules[$field] = ['prohibited'];
            }
        }

        return $rules;
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
            '*.prohibited' => 'Nama, SKU, harga beli, dan harga jual hanya dapat diubah dari cabang acuan atau oleh akun master.',
        ];
    }
}
