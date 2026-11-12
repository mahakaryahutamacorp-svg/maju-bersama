<?php

namespace App\Http\Requests;

use App\Models\Sale;
use App\Support\DecimalQuantity;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isMaster() === true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('customer_id') === '') {
            $this->merge(['customer_id' => null]);
        }

        if ($this->input('due_date') === '') {
            $this->merge(['due_date' => null]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $sale = $this->route('sale');
        $branchId = $sale instanceof Sale ? (int) $sale->branch_id : 0;

        return [
            'payment_method' => ['required', 'in:cash,transfer,qris,tempo'],
            'customer_id' => [
                'nullable',
                'integer',
                Rule::exists('customers', 'id')->where('branch_id', $branchId),
            ],
            'due_date' => ['nullable', 'date', 'required_if:payment_method,tempo'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')->where('branch_id', $branchId),
            ],
            'items.*.quantity' => DecimalQuantity::rules(),
            'items.*.price' => ['required', 'numeric', 'min:0'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'payment_method.required' => 'Pilih metode pembayaran nota.',
            'payment_method.in' => 'Metode pembayaran harus tunai, transfer, QRIS, atau tempo.',
            'customer_id.exists' => 'Pelanggan yang dipilih bukan pelanggan cabang nota ini.',
            'due_date.required_if' => 'Transaksi tempo wajib punya tanggal jatuh tempo.',
            'items.required' => 'Nota harus tetap berisi minimal satu barang.',
            'items.*.product_id.exists' => 'Salah satu barang bukan produk cabang nota ini.',
            'items.*.price.required' => 'Harga jual setiap barang wajib diisi.',
        ];
    }
}
