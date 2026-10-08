<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreManualJournalRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && ($user->isMaster() || $user->isBranchAdmin());
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'transaction_date' => ['required', 'date'],
            'reference_number' => ['nullable', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:500'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.chart_of_account_id' => ['required', 'integer', 'exists:chart_of_accounts,id'],
            'lines.*.debit' => ['required', 'numeric', 'min:0'],
            'lines.*.credit' => ['required', 'numeric', 'min:0'],
            'lines.*.memo' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (ValidatorContract $validator): void {
            $lines = $this->input('lines', []);
            if (! is_array($lines)) {
                return;
            }

            $totalDebit = '0.00';
            $totalCredit = '0.00';

            foreach ($lines as $index => $line) {
                if (! is_array($line)) {
                    continue;
                }

                $debit = number_format((float) ($line['debit'] ?? 0), 2, '.', '');
                $credit = number_format((float) ($line['credit'] ?? 0), 2, '.', '');
                $totalDebit = bcadd($totalDebit, $debit, 2);
                $totalCredit = bcadd($totalCredit, $credit, 2);

                if (bccomp($debit, '0.00', 2) === 0 && bccomp($credit, '0.00', 2) === 0) {
                    $validator->errors()->add("lines.{$index}", 'Setiap baris jurnal harus memiliki debit atau kredit.');
                }
            }

            if (bccomp($totalDebit, $totalCredit, 2) !== 0) {
                $validator->errors()->add(
                    'lines',
                    "Total debit ({$totalDebit}) harus sama dengan total kredit ({$totalCredit})."
                );
            }
        });
    }
}
