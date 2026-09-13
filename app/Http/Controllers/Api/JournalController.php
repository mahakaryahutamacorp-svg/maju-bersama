<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreJournalRequest;
use App\Models\JournalHeader;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class JournalController extends Controller
{
    public function store(StoreJournalRequest $request): JsonResponse
    {
        $validated = $request->validated();

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
}