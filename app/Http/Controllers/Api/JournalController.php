<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JournalHeader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class JournalController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'transaction_date' => ['required', 'date'],
            'reference_number' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.chart_of_account_id' => ['required', 'integer', 'exists:chart_of_accounts,id'],
            'lines.*.debit' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'lines.*.credit' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'lines.*.memo' => ['nullable', 'string'],
        ]);

        $validator->after(function ($validator) use ($request): void {
            $debit = 0;
            $credit = 0;
            $lines = $request->input('lines', []);

            if (! is_array($lines)) {
                return;
            }

            foreach ($lines as $index => $line) {
                if (! is_array($line)) {
                    continue;
                }

                $debit += $this->toCents($line['debit'] ?? 0);
                $credit += $this->toCents($line['credit'] ?? 0);

                if (($line['debit'] ?? 0) == 0 && ($line['credit'] ?? 0) == 0) {
                    $validator->errors()->add("lines.$index", 'Each journal line must have a debit or credit amount.');
                }
            }

            if ($debit !== $credit) {
                $validator->errors()->add('lines', 'The total debit must equal the total credit.');
            }
        });

        $validated = $validator->validate();

        $journal = DB::transaction(function () use ($validated, $request): JournalHeader {
            $journal = JournalHeader::create([
                'branch_id' => $request->user()->branch_id,
                'user_id' => $request->user()->id,
                'transaction_date' => $validated['transaction_date'],
                'reference_number' => $validated['reference_number'],
                'description' => $validated['description'],
            ]);

            $journal->journalLines()->createMany($validated['lines']);

            return $journal->load('journalLines');
        });

        return response()->json([
            'message' => 'Journal created successfully.',
            'journal' => $journal,
        ], 201);
    }

    private function toCents(int|float|string $amount): int
    {
        return (int) round((float) $amount * 100);
    }
}