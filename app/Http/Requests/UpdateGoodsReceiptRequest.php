<?php

namespace App\Http\Requests;

use App\Support\DecimalQuantity;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGoodsReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isMaster() === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'payment_type' => ['required', 'in:cash,credit'],
            'supplier_id' => [
                'nullable',
                'integer',
                Rule::exists('suppliers', 'id')->where('is_active', true),
            ],
            'supplier_name' => ['nullable', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => DecimalQuantity::rules(),
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'payment_type.required' => 'Pilih metode pembayaran: tunai (kas) atau tempo (hutang).',
            'supplier_id.exists' => 'Pemasok yang dipilih tidak ditemukan atau sudah nonaktif.',
            'items.required' => 'Minimal satu barang harus tetap tercatat pada penerimaan ini.',
        ];
    }
}
