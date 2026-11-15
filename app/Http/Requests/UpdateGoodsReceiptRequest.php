<?php

namespace App\Http\Requests;

use App\Models\GoodsReceipt;
use App\Services\GoodsReceiptService;
use App\Support\DecimalQuantity;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGoodsReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }

        $receipt = GoodsReceipt::query()->find($this->route('id'));
        if ($receipt === null) {
            return true;
        }

        return app(GoodsReceiptService::class)->canRevise($user, $receipt);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->user();
        $receipt = GoodsReceipt::query()->find($this->route('id'));

        $supplierRule = Rule::exists('suppliers', 'id')->where('is_active', true);
        if ($user && ! $user->isMaster()) {
            $supplierRule = $supplierRule->where('branch_id', $user->branch_id);
        }

        $productRule = Rule::exists('products', 'id');
        if ($user && ! $user->isMaster() && $receipt) {
            $productRule = $productRule->where('branch_id', $receipt->branch_id);
        }

        return [
            'payment_type' => ['required', 'in:cash,credit'],
            'supplier_id' => [
                'nullable',
                'integer',
                $supplierRule,
            ],
            'supplier_name' => ['nullable', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', $productRule],
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
            'supplier_id.exists' => 'Pemasok yang dipilih tidak ditemukan, nonaktif, atau milik cabang lain.',
            'items.required' => 'Minimal satu barang harus tetap tercatat pada penerimaan ini.',
            'items.*.product_id.exists' => 'Produk yang dipilih tidak ada di cabang nota ini.',
        ];
    }
}
