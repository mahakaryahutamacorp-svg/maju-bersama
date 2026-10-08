<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSalesReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->user();

        return [
            'return_date' => ['required', 'date'],
            'customer_name' => ['nullable', 'string', 'max:150'],
            'sale_id' => [
                $user?->isCashier() ? 'required' : 'nullable',
                'integer',
                Rule::exists('sales', 'id')->where(function ($query) use ($user): void {
                    if ($user && ! $user->isMaster()) {
                        $query->where('branch_id', $user->branch_id);
                    }
                }),
            ],
            'refund_method' => ['required', 'string', 'in:cash,transfer,Cash,Transfer,tunai,bank,piutang,tempo,kredit'],
            'chart_of_account_id' => ['required', 'integer', 'exists:chart_of_accounts,id'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sale_id.required' => 'Kasir wajib meretur berdasarkan nota penjualan cabang sendiri.',
            'sale_id.exists' => 'Nota penjualan tidak ditemukan di cabang Anda.',
            'items.required' => 'Setidaknya satu item retur harus disertakan.',
            'items.min' => 'Setidaknya satu item retur harus disertakan.',
        ];
    }
}
