<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupplierDebtPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && ($user->isMaster() || $user->isBranchAdmin());
    }

    /**
     * Nominal diketik bebas ala toko: "Rp 3.250.000" atau "3250000,50".
     */
    protected function prepareForValidation(): void
    {
        $raw = (string) $this->input('amount', '');
        $clean = preg_replace('/[^\d,]/', '', $raw) ?? '';
        $clean = str_replace(',', '.', $clean);

        $this->merge(['amount' => $clean === '' ? null : $clean]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:1'],
            'account_id' => [
                'required',
                'integer',
                Rule::exists('chart_of_accounts', 'id')->where('type', 'asset')->whereNull('deleted_at'),
            ],
            'payment_date' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:255'],
            'tab' => ['nullable', 'in:riwayat_belanja,riwayat_pembayaran,riwayat_retur'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.required' => 'Nominal dibayar wajib diisi.',
            'amount.numeric' => 'Nominal dibayar harus berupa angka.',
            'amount.min' => 'Nominal dibayar minimal Rp 1.',
            'account_id.required' => 'Pilih sumber uang (kas atau bank).',
            'account_id.exists' => 'Sumber uang tidak valid.',
            'payment_date.before_or_equal' => 'Tanggal bayar tidak boleh di masa depan.',
        ];
    }
}
