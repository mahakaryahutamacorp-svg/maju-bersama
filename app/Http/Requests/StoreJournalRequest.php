<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreJournalRequest extends FormRequest
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
            'transaction_date' => ['required', 'date'],
            'reference_number' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.chart_of_account_id' => ['required', 'integer', 'exists:chart_of_accounts,id'],
            'lines.*.debit' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'lines.*.credit' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'lines.*.memo' => ['nullable', 'string'],
        ];
    }

    /**
     * Configure the validator to add custom after-hook validation.
     *
     * Validates that:
     * 1. Each journal line has at least a debit or credit amount > 0.
     * 2. Total debit equals total credit (balanced journal).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (ValidatorContract $validator): void {
            $lines = $this->input('lines', []);

            if (! is_array($lines)) {
                return;
            }

            $totalDebitCents = 0;
            $totalCreditCents = 0;

            foreach ($lines as $index => $line) {
                if (! is_array($line)) {
                    continue;
                }

                $debitCents = $this->toCents($line['debit'] ?? 0);
                $creditCents = $this->toCents($line['credit'] ?? 0);

                $totalDebitCents += $debitCents;
                $totalCreditCents += $creditCents;

                if ($debitCents === 0 && $creditCents === 0) {
                    $validator->errors()->add(
                        "lines.{$index}",
                        'Each journal line must have a debit or credit amount.'
                    );
                }
            }

            if ($totalDebitCents !== $totalCreditCents) {
                $validator->errors()->add(
                    'lines',
                    'The total debit must equal the total credit.'
                );
            }
        });
    }

    /**
     * Convert a decimal amount to cents (integer) for precise comparison.
     */
    private function toCents(int|float|string $amount): int
    {
        return (int) round((float) $amount * 100);
    }
}
