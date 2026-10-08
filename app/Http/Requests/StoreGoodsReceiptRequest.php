<?php

namespace App\Http\Requests;

use App\Models\PurchaseOrder;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class StoreGoodsReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Penerimaan dari PO selalu kredit dan pemasoknya mengikuti PO, bukan input form.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->filled('purchase_order_id')) {
            return;
        }

        $supplierId = PurchaseOrder::query()
            ->whereKey((int) $this->input('purchase_order_id'))
            ->value('supplier_id');

        $this->merge([
            'payment_type' => 'credit',
            'supplier_id' => $supplierId,
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'purchase_order_id' => ['nullable', 'integer', $this->scopedExists('purchase_orders')],
            'payment_type' => ['required', 'in:cash,credit'],
            'supplier_id' => [
                'nullable',
                'required_if:payment_type,credit',
                'integer',
                $this->scopedExists('suppliers')->where('is_active', true),
            ],
            'supplier_name' => ['nullable', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'reference_number' => ['nullable', 'string', 'max:100', 'unique:goods_receipts,reference_number'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'supplier_id.required_if' => 'Pembelian tempo (hutang) wajib memilih pemasok terdaftar agar hutangnya tercatat di Buku Pemasok.',
            'supplier_id.exists' => 'Pemasok yang dipilih tidak ditemukan, nonaktif, atau milik cabang lain.',
            'purchase_order_id.exists' => 'Pesanan barang tidak ditemukan atau milik cabang lain.',
        ];
    }

    private function scopedExists(string $table): Exists
    {
        $rule = Rule::exists($table, 'id');
        $user = $this->user();

        return $user && ! $user->isMaster()
            ? $rule->where('branch_id', $user->branch_id)
            : $rule;
    }
}
